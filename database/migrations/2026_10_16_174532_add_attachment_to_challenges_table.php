<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->string('attachment_path', 500)->nullable()->after('hints');
            $table->string('attachment_filename', 255)->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropColumn(['attachment_path', 'attachment_filename']);
        });
    }
};
