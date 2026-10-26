<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use App\Models\AdminAuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrimaryAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('auth.primary_admin.email')));
        $username = strtolower(trim((string) config('auth.primary_admin.username')));

        if ($email === '' || $username === '') {
            $this->command?->warn('Primary admin seeder skipped: configure ADMIN_EMAIL and ADMIN_USERNAME.');

            return;
        }

        DB::transaction(function () use ($email, $username): void {
            $matches = User::query()
                ->where('email', $email)
                ->orWhere('username', $username)
                ->lockForUpdate()
                ->get();

            if ($matches->count() > 1) {
                $this->command?->error('Primary admin identifiers match different accounts; no changes made.');

                return;
            }

            $user = $matches->first();

            if ($user === null) {
                $this->command?->info('Primary admin account not found. Run p4dc:bootstrap-admin to create it securely.');

                return;
            }

            if ($user->role !== 'admin' && Team::where('captain_id', $user->id)->where('status', 'active')->exists()) {
                $this->command?->error('Primary account is an active team captain; transfer captaincy before bootstrap.');

                return;
            }

            if ($user->role !== 'admin' || ! $user->is_active) {
                $previous = ['role' => $user->role, 'is_active' => $user->is_active];
                $user->update(['role' => 'admin', 'is_active' => true]);
                AdminAuditLog::create([
                    'subject_user_id' => $user->id,
                    'action' => 'primary_admin.seed_reconciled',
                    'details' => [
                        'from' => $previous,
                        'to' => ['role' => 'admin', 'is_active' => true],
                    ],
                    'created_at' => now(),
                ]);
                $this->command?->info('Primary administrator role/status restored. Existing password and profile were preserved.');

                return;
            }

            $this->command?->info('Primary administrator is already active. No account fields changed.');
        });
    }
}
