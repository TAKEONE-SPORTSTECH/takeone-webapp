<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `translations` JSON column to every table that holds
 * club-entered, user-facing text. Non-destructive: existing base columns keep
 * the default/English value; this column stores only non-default locales.
 */
return new class extends Migration
{
    private array $tables = [
        'tenants',
        'club_packages',
        'club_activities',
        'club_facilities',
        'club_instructors',
        'club_perks',
        'club_achievements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'translations')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->json('translations')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'translations')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('translations');
            });
        }
    }
};
