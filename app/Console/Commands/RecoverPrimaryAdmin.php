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

class RecoverPrimaryAdmin extends Command
{
    protected $signature = 'p4dc:recover-admin';

    protected $description = 'Explicitly recover the configured primary administrator password';

    public function handle(): int
    {
        $email = strtolower(trim((string) config('auth.primary_admin.email')));
        $username = strtolower(trim((string) config('auth.primary_admin.username')));
        $validator = Validator::make(
            ['email' => $email, 'username' => $username],
            ['email' => ['required', 'email'], 'username' => ['required', 'string', 'min:3', 'max:30']]
        );

        if ($validator->fails()) {
            $this->error('Set valid ADMIN_EMAIL and ADMIN_USERNAME environment variables first.');

            return self::FAILURE;
        }

        $matches = User::query()
            ->where('email', $email)
            ->orWhere('username', $username)
            ->get();

        if ($matches->count() !== 1) {
            $this->error($matches->isEmpty()
                ? 'The configured primary administrator does not exist. Run p4dc:bootstrap-admin first.'
                : 'ADMIN_EMAIL and ADMIN_USERNAME belong to different accounts. Resolve the identity conflict manually.');

            return self::FAILURE;
        }

        $user = $matches->first();
        if ($user->role !== 'admin' && Team::where('captain_id', $user->id)->where('status', 'active')->exists()) {
            $this->error('Transfer team captaincy before recovering this account as administrator.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset the password for primary administrator {$user->username}? This is an explicit recovery operation.")) {
            $this->info('No changes made.');

            return self::SUCCESS;
        }

        $password = $this->secret('New password (input hidden)');
        $confirmation = $this->secret('Confirm new password (input hidden)');
        $passwordValidator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', Password::min(8), 'confirmed']]
        );

        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($user, $password): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $previous = ['role' => $lockedUser->role, 'is_active' => $lockedUser->is_active];
                $lockedUser->update([
                    'password' => $password,
                    'role' => 'admin',
                    'is_active' => true,
                ]);

                AdminAuditLog::create([
                    'subject_user_id' => $lockedUser->id,
                    'action' => 'primary_admin.password_recovered',
                    'details' => ['from' => $previous, 'to' => ['role' => 'admin', 'is_active' => true]],
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Administrator recovery failed. Check the application logs.');

            return self::FAILURE;
        }

        $this->info('Primary administrator password recovered. No password was logged.');

        return self::SUCCESS;
    }
}
