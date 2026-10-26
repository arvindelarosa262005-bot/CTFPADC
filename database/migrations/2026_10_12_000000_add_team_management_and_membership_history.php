<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->foreignId('captain_id')->nullable()->after('name')->constrained('users')->restrictOnDelete();
            $table->string('invitation_code_hash', 64)->nullable()->unique()->after('captain_id');
            $table->string('status', 20)->default('active')->after('invitation_code_hash')->index();
        });

        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->timestamp('left_at')->nullable()->index();
            $table->dropUnique(['user_id']);
            $table->dropUnique(['team_id', 'user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_user ON team_memberships (user_id) WHERE left_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_team_user ON team_memberships (team_id, user_id) WHERE left_at IS NULL');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_user ON team_memberships (user_id) WHERE left_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_team_user ON team_memberships (team_id, user_id) WHERE left_at IS NULL');
        }

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->foreignId('team_id_at_solve')->nullable()->after('solver_id')->constrained('teams')->nullOnDelete();
            $table->index(['team_id_at_solve', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->dropIndex(['team_id_at_solve', 'verified_at']);
            $table->dropConstrainedForeignId('team_id_at_solve');
        });

        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_user');
            DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_team_user');
        }

        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->dropIndex(['left_at']);
            $table->dropColumn('left_at');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('captain_id');
            $table->dropUnique(['invitation_code_hash']);
            $table->dropColumn(['invitation_code_hash', 'status']);
        });
    }
};
