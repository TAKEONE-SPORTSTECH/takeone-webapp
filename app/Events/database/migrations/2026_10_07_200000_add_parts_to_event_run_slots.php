<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which PARTS of a division a running-order slot runs — preliminaries,
 * semi-finals, finals (App\Events\Support\BoutPart).
 *
 * The owner, 2026-10-07: some competitions fight every preliminary first and
 * leave the finals for the end of the day, so a division may be put on the
 * day more than once, part by part, until every part is placed. NULL is the
 * whole division — exactly what every existing slot already meant, so nothing
 * that was written before this column changes.
 *
 * Additive; nullable; no data moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->text('parts')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->dropColumn('parts');
        });
    }
};
