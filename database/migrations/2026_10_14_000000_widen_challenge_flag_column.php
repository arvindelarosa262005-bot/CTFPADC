<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The flag is stored through Laravel's `encrypted` cast, whose payload is
     * much longer than the plaintext (256 chars for a 32-char flag). Widen the
     * column so it can never overflow. Widening varchar -> text is lossless,
     * so existing values are preserved.
     *
     * A native ALTER TABLE ... ALTER COLUMN ... TYPE statement is used instead
     * of Schema::table()->change() because the latter requires the doctrine/dbal
     * package, which is not part of this project's dependencies and is
     * incompatible with the locked carbonphp/carbon-doctrine-types version.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE challenges ALTER COLUMN flag TYPE text');
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: narrowing could truncate encrypted values.
    }
};
