<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the two boards this screen was hung up to be.
 *
 * The surface already existed as a `?surface=` on the URL, which is right for a
 * Raspberry Pi — it is flashed with one address and opens it forever, so the pin
 * rides along with its identity. It is useless for a screen that is a browser
 * somebody pointed at a QR code: they never type a URL, they scan, and whatever
 * they are given has to remember what it is.
 *
 *   'queue'  the running order, always. Corridor, call room, entrance.
 *   'bout'   the mat itself, always. Introduction, then the score.
 *    null    follow the mat: the queue between matches, the bout during one.
 *            The single board hanging over the mat, and the default.
 *
 * The URL still wins when it says something, so a Pi already in a hall keeps
 * behaving exactly as it was flashed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['court_displays', 'karate_court_displays'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'surface')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('surface', 10)->nullable()->after('court');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['court_displays', 'karate_court_displays'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'surface')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('surface');
                });
            }
        }
    }
};
