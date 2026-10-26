<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_user');
        DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_team_user');
        DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_user ON team_memberships (user_id) WHERE left_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX team_memberships_one_active_team_user ON team_memberships (team_id, user_id) WHERE left_at IS NULL');
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_user');
        DB::statement('DROP INDEX IF EXISTS team_memberships_one_active_team_user');
    }
};
