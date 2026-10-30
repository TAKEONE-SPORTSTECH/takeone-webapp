<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL only — let a string column hold what it always could.
 *
 * SQLite never enforced a VARCHAR length: `string('end_reason', 40)` stored
 * 43 characters for years and the app was written against that (a party
 * relation's end_reason is "motion:<uuid>"). PostgreSQL enforces every length,
 * so the same write would fail on the day it first runs there. This removes
 * the length from every varchar column, which is what the platform has
 * actually run on. Lengths that matter are validated by the app.
 *
 * In PostgreSQL, dropping a varchar length is a catalogue change — no table is
 * rewritten and no index is rebuilt — so it is cheap on the live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $columns = DB::select(<<<'SQL'
            select table_name, column_name
            from information_schema.columns
            where table_schema = current_schema()
              and data_type = 'character varying'
              and character_maximum_length is not null
        SQL);

        foreach ($columns as $c) {
            DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "%s" TYPE varchar', $c->table_name, $c->column_name));
        }
    }

    public function down(): void
    {
        // Not restored: rows longer than the old limits may exist by then.
    }
};
