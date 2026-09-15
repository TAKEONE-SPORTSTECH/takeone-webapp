<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STREAMS — the parts of an event somebody enters separately.
 *
 * "Gi" and "No-Gi" in jiu-jitsu; "Kata" and "Kumite" in karate; "Poomsae" and
 * "Sparring" in taekwondo; whatever a sport nobody has thought of yet calls
 * them. The platform never learns what any of them MEAN: the organiser names
 * them, and every screen shows that word back in the language it was written.
 *
 * Until now this was read out of TEXT in two different places — a division's
 * name ending in "[Gi]", a fee option's label containing "(Gi)" — by two
 * regexes that could not be joined up. So the entrant picker offered a No-Gi
 * competitor for a Gi weight class and nothing on the row said otherwise.
 *
 *   club_events.streams        the vocabulary, once per event
 *   event_fee_options.grants   which streams BUYING this option entitles you to
 *                              (that is what a combined "Gi + No-Gi" is)
 *   event_categories.stream    which stream a division belongs to
 *
 * Then "does this person belong in this division" is set membership, not string
 * comparison: `division.stream ∈ entry.granted`.
 *
 * ── fee_selection ───────────────────────────────────────────────────────────
 * Whether an entrant picks ONE option or several — a radio or a checkbox, the
 * organiser's call. Not decoration: an event selling Gi (10), No-Gi (10) and
 * Gi + No-Gi (15) is a radio set, and ticking the first two would charge 20 for
 * what is priced at 15 and write two fee lines for one purchase.
 *
 * Defaults to `multiple`, which is exactly what every existing event does
 * today, so nothing changes for anybody until an organiser says otherwise.
 *
 * All four columns are nullable or defaulted, and nothing is rewritten: every
 * name, label and price stays exactly as it is. The backfill that reads the old
 * tags into these columns is a separate, repeatable step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->json('streams')->nullable()->after('sport');
            $table->string('fee_selection', 16)->default('multiple')->after('streams');
        });

        Schema::table('event_fee_options', function (Blueprint $table) {
            $table->json('grants')->nullable()->after('amount');
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('stream', 40)->nullable()->after('belts');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['streams', 'fee_selection']);
        });

        Schema::table('event_fee_options', function (Blueprint $table) {
            $table->dropColumn('grants');
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn('stream');
        });
    }
};
