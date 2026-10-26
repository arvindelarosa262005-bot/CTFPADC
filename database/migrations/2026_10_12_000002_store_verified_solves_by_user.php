<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('challenge_id')->constrained()->cascadeOnDelete();
        });

        DB::table('verified_solves')
            ->where('solver_type', User::class)
            ->update(['user_id' => DB::raw('solver_id')]);

        if (DB::table('verified_solves')->whereNull('user_id')->exists()) {
            throw new \RuntimeException('Verified solves with non-user owners cannot be converted safely.');
        }

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->unique(['challenge_id', 'user_id']);
            $table->index(['user_id', 'verified_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->dropUnique(['challenge_id', 'solver_type', 'solver_id']);
            $table->dropIndex(['solver_type', 'solver_id']);
            $table->dropColumn(['solver_type', 'solver_id']);
        });
    }

    public function down(): void
    {
        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->string('solver_type')->nullable();
            $table->unsignedBigInteger('solver_id')->nullable();
        });

        DB::table('verified_solves')->update([
            'solver_type' => User::class,
            'solver_id' => DB::raw('user_id'),
        ]);

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->string('solver_type')->nullable(false)->change();
            $table->unsignedBigInteger('solver_id')->nullable(false)->change();
            $table->dropUnique(['challenge_id', 'user_id']);
            $table->dropIndex(['user_id', 'verified_at']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('verified_solves', function (Blueprint $table): void {
            $table->unique(['challenge_id', 'solver_type', 'solver_id']);
            $table->index(['solver_type', 'solver_id']);
        });
    }
};
