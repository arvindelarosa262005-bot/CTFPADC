<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'p4dc:make-admin {email : Email address of an existing account}';

    protected $description = 'Promote an existing account to administrator from the server console';

    public function handle(): int
    {
        $user = User::where('email', strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->error('No account exists for that email address. Register the account first.');

            return self::FAILURE;
        }

        if ($user->role !== 'admin' && Team::where('captain_id', $user->id)->where('status', 'active')->exists()) {
            $this->error('Transfer team captaincy before promoting this account.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promote {$user->username} to administrator?")) {
            $this->info('No changes made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user): void {
            $previousRole = $user->role;
            $user->update(['role' => 'admin', 'is_active' => true]);

            AdminAuditLog::create([
                'subject_user_id' => $user->id,
                'action' => 'user.admin.promoted',
                'details' => ['from' => $previousRole, 'to' => 'admin'],
                'created_at' => now(),
            ]);
        });

        $this->info("{$user->username} is now an active administrator.");

        return self::SUCCESS;
    }
}
