<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One entry per DIVISION, not one per event.
 *
 * An athlete at a jiu-jitsu championship routinely competes twice: Gi and
 * No-Gi are separate divisions, entered separately and drawn separately. The
 * fee side of the platform already said so — an event can sell "Gi", "No-Gi"
 * and "Gi + No-Gi" as participant options, and on the Victory BJJ Championship
 * sixteen athletes had already PAID for both — while `unique(event_id, user_id)`
 * meant they could hold only one entry, carrying one `category_id`, and so
 * could be drawn in only one of the two brackets they had bought.
 *
 * The index becomes (event_id, user_id, category_id): the same person may hold
 * one entry in each division of an event, and still only one per division.
 *
 * ⚠️ This does NOT stop two entries with NO division. NULLs compare as
 * distinct in both SQLite and MySQL, so `(65, 1378, NULL)` twice satisfies the
 * index. That guard belongs in EntryService, which resolves an existing
 * entry before creating one — the index is the backstop, not the rule.
 *
 * Additive: no column is added, no row is rewritten, and every existing entry
 * keeps the division it already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropUnique('club_event_registrations_event_id_user_id_unique');
            $table->unique(['event_id', 'user_id', 'category_id'], 'cer_event_user_category_unq');
        });
    }

    /**
     * Reversible only while no athlete actually holds two entries in one event.
     * If one does, re-adding the old index FAILS — loudly, and correctly: going
     * back means deciding which of their divisions to throw away, and a
     * migration must not make that choice on an organiser's behalf.
     */
    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropUnique('cer_event_user_category_unq');
            $table->unique(['event_id', 'user_id']);
        });
    }
};
