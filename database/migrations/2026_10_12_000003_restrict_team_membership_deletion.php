<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('team_memberships', function (Blueprint $table): void {
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
