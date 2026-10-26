<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the configuration columns required to publish and operate challenges.
     *
     * The canonical flag is stored encrypted at rest and is never exposed to
     * participants. The target configuration supports both internal challenge
     * websites served by this application and externally hosted targets.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('slug');
            $table->string('target_type', 20)->default('internal')->after('description');
            $table->string('target_url', 500)->nullable()->after('target_type');
            $table->boolean('is_active')->default(false)->after('points');
            $table->string('flag', 255)->nullable()->after('is_active');
            $table->json('hints')->nullable()->after('flag');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropColumn(['description', 'target_type', 'target_url', 'is_active', 'flag', 'hints']);
        });
    }
};
