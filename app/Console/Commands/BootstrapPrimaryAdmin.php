<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

class BootstrapPrimaryAdmin extends Command
{
    protected $signature = 'p4dc:bootstrap-admin';

    protected $description = 'Create or safely activate the configured primary administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) config('auth.primary_admin.email')));
        $username = strtolower(trim((string) config('auth.primary_admin.username')));

        $identityErrors = Validator::make(
            ['email' => $email, 'username' => $username],
            [
                'email' => ['required', 'email', 'max:255'],
                'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/\A[a-zA-Z0-9_.|-]+\z/'],
            ]
        );

        if ($identityErrors->fails()) {
            $this->error('Set valid ADMIN_EMAIL and ADMIN_USERNAME environment variables first.');

            return self::FAILURE;
        }

        $matches = User::query()
            ->where('email', $email)
            ->orWhere('username', $username)
            ->get();

        if ($matches->count() > 1) {
            $this->error('ADMIN_EMAIL and ADMIN_USERNAME belong to different accounts. Resolve the identity conflict manually.');

            return self::FAILURE;
        }

        if ($matches->isEmpty()) {
            return $this->createPrimaryAdmin($email, $username);
        }

        $userId = $matches->first()->id;

        try {
            return DB::transaction(function () use ($userId): int {
                $user = User::query()->lockForUpdate()->findOrFail($userId);

                if ($user->role !== 'admin' && Team::where('captain_id', $user->id)->where('status', 'active')->exists()) {
                    $this->error('Transfer team captaincy before bootstrapping this account as administrator.');

                    return self::FAILURE;
                }

                $changes = [];

                if ($user->role !== 'admin') {
                    $changes['role'] = 'admin';
                }

                if (! $user->is_active) {
                    $changes['is_active'] = true;
                }

                if ($changes !== []) {
                    $previous = ['role' => $user->role, 'is_active' => $user->is_active];
                    $user->update($changes);
                    $this->audit($user, 'primary_admin.bootstrapped', [
                        'from' => $previous,
                        'to' => ['role' => $user->role, 'is_active' => $user->is_active],
                    ]);
                }

                $this->info($changes === []
                    ? 'The configured primary administrator already exists and is active. Password and profile were preserved.'
                    : 'The existing configured account is now an active administrator. Password and profile were preserved.');

                return self::SUCCESS;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Administrator bootstrap failed. Check account identity conflicts and database logs.');

            return self::FAILURE;
        }
    }

    private function createPrimaryAdmin(string $email, string $username): int
    {
        $password = $this->secret('Choose a password for the primary administrator (input hidden)');
        $confirmation = $this->secret('Confirm the primary administrator password (input hidden)');
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', Password::min(8), 'confirmed']]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($email, $username, $password): User {
                $user = User::create([
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'role' => 'admin',
                    'is_active' => true,
                ]);

                $this->audit($user, 'primary_admin.created', ['role' => 'admin', 'is_active' => true]);

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Administrator creation failed. The configured identity may have been created concurrently; rerun bootstrap to reconcile it.');

            return self::FAILURE;
        }

        $this->info('Primary administrator created. Password was securely hashed.');

        return self::SUCCESS;
    }

    private function audit(User $user, string $action, array $details): void
    {
        AdminAuditLog::create([
            'subject_user_id' => $user->id,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
