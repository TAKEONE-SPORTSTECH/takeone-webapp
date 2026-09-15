<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weigh-in deadline, and what it does to an entry that never got ready.
 *
 * A championship cannot draw an athlete nobody has weighed or taken money from
 * — the final draw already refuses them (DrawEngine::build, paidOnly) — but
 * until now they simply sat on the list until a human noticed. This is the
 * organiser saying "at the weigh-in, that decides itself".
 *
 * Two additions, both deliberate:
 *
 *  · `entry_deadline_enforced` is an OPT-IN, defaulted OFF. Enforcement deletes
 *    entries, and no event that exists today asked for that. An organiser turns
 *    it on for their own event.
 *
 *  · `event_entry_exclusions` is the RECORD. Removing an entry deletes the row,
 *    so without this the athlete opening the page would see an ordinary "not
 *    entered" screen and have no idea why — which is the complaint that started
 *    this. One row per person per event, saying what was missing and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->boolean('entry_deadline_enforced')->default(false)->after('weigh_in_at');
        });

        Schema::create('event_entry_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 'unpaid' | 'no_weigh_in' | 'unpaid_and_no_weigh_in' — a machine
            // key, so the sentence the athlete reads is translated at the
            // surface rather than frozen in English in the database.
            $table->string('reason', 40);

            // Whose decision it was. Null = the deadline itself, which is the
            // ordinary case and is not a person.
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('excluded_at');
            $table->timestamps();

            // One standing answer per person per event: a second sweep updates
            // the reason rather than stacking a second row.
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_entry_exclusions');

        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('entry_deadline_enforced');
        });
    }
};
