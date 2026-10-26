<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->dropUnique(['challenge_id', 'user_id']);
            $table->dropUnique(['challenge_id', 'team_id']);
            $table->dropIndex(['user_id', 'verified_at']);
            $table->dropIndex(['team_id', 'verified_at']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['team_id']);
            $table->dropColumn(['user_id', 'team_id']);
        });

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->morphs('solver');
            $table->unique(['challenge_id', 'solver_type', 'solver_id']);
        });
    }

    public function down(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->dropUnique(['challenge_id', 'solver_type', 'solver_id']);
            $table->dropMorphs('solver');
        });

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['challenge_id', 'user_id']);
            $table->unique(['challenge_id', 'team_id']);
            $table->index(['user_id', 'verified_at']);
            $table->index(['team_id', 'verified_at']);
        });
    }
};
