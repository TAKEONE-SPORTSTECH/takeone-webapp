<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * The few raw SQL expressions that differ between SQLite and PostgreSQL, in one
 * place. On SQLite each returns exactly the expression the code used before.
 *
 * `$column` is always a column name written by the code, never user input.
 */
final class Sql
{
    private static function pgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /** A date/datetime column as 'YYYY-MM'. */
    public static function yearMonth(string $column): string
    {
        return self::pgsql() ? "to_char({$column}, 'YYYY-MM')" : "strftime('%Y-%m', {$column})";
    }

    /** One top-level key of a JSON column, as text (NULL when absent). */
    public static function jsonText(string $column, string $key): string
    {
        return self::pgsql() ? "({$column}::json ->> '{$key}')" : "json_extract({$column}, '\$.{$key}')";
    }

    /**
     * The phone digits of `users.mobile`, '' when there are none. SQLite needs
     * the json_valid guard for legacy non-JSON rows; a PostgreSQL json column
     * cannot hold one.
     */
    public static function mobileNumber(): string
    {
        return self::pgsql()
            ? "coalesce(trim(mobile::json ->> 'number'), '')"
            : "case when json_valid(coalesce(mobile, '')) = 1 then coalesce(trim(json_extract(mobile, '\$.number')), '') else '' end";
    }

    /** Case-insensitive LIKE for raw SQL (SQLite's LIKE already is). */
    public static function like(): string
    {
        return self::pgsql() ? 'ILIKE' : 'LIKE';
    }

    /** Cast to text for a LIKE match. CHAR on PostgreSQL is char(1). */
    public static function asText(string $expr): string
    {
        return self::pgsql() ? "CAST({$expr} AS TEXT)" : "CAST({$expr} AS CHAR)";
    }
}
