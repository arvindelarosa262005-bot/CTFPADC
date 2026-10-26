<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('team_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique('user_id');
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 140)->unique();
            $table->string('category', 40);
            $table->string('difficulty', 20);
            $table->unsignedInteger('points');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('challenge_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->char('submission_hash', 64);
            $table->boolean('is_correct')->default(false);
            $table->timestamp('submitted_at')->useCurrent();
            $table->index(['challenge_id', 'user_id', 'submitted_at']);
        });

        Schema::create('verified_solves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_awarded');
            $table->timestamp('verified_at')->useCurrent();
            $table->unique(['challenge_id', 'user_id']);
            $table->unique(['challenge_id', 'team_id']);
            $table->index(['user_id', 'verified_at']);
            $table->index(['team_id', 'verified_at']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('verified_solves');
        Schema::dropIfExists('challenge_submissions');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
    }
};
