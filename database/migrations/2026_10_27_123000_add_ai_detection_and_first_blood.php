<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->boolean('is_first_blood')->default(false)->after('points_awarded');
            $table->unsignedInteger('bonus_points')->default(0)->after('is_first_blood');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_ai_flagged')->default(false)->after('is_active');
            $table->unsignedInteger('ai_suspicion_score')->default(0)->after('is_ai_flagged');
            $table->string('ai_flag_reason', 255)->nullable()->after('ai_suspicion_score');
        });

        Schema::create('ai_telemetry_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('severity', 20)->default('medium');
            $table->text('details');
            $table->text('submitted_content')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_telemetry_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_ai_flagged', 'ai_suspicion_score', 'ai_flag_reason']);
        });

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->dropColumn(['is_first_blood', 'bonus_points']);
        });
    }
};
