<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An event may charge for more than one thing.
 *
 * Until now a competition had exactly two prices: `participant_fee_amount` and
 * `spectator_fee_amount`, one number each. Real events do not work that way —
 * a jiu-jitsu tournament sells Gi and No-Gi separately and a competitor may
 * enter both; a championship sells an entry, a T-shirt and a banquet seat.
 *
 * So a named, priced option, as many as the organiser wants, for either role.
 *
 * ADD-ONS, not replacements
 * -------------------------
 * The two amount columns stay exactly as they are and remain the BASE price.
 * An entrant pays the base plus whatever they ticked. That is what makes this
 * migration safe for the events already in the database: an event with no rows
 * here behaves precisely as it did before, and every existing read path keeps
 * its answer. It is also what stops a flat tick-list from producing a free
 * entry when somebody ticks nothing (agreed 2026-09-06 with the alternative,
 * required option-groups, deliberately rejected as too much machinery).
 *
 * Deactivated, never deleted
 * --------------------------
 * `is_active` rather than a delete, because an entry's frozen fee line points
 * back here for provenance (`event_registration_fee_lines.fee_option_id`) and a
 * removed row would orphan it. Same rule `club_product_variants` already
 * follows for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_fee_options', function (Blueprint $table) {
            $table->id();

            // Public key, per the Unpredictable Resource Identifiers rule: this
            // id travels to the browser and comes back on an entry.
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Which fee this option belongs to. The same mechanism serves both,
            // so "several kinds of spectator ticket" costs nothing extra.
            $table->string('role', 20)->default('participant'); // participant | spectator

            $table->string('label');

            // A price, never null. "Unstated" is a property of the EVENT's base
            // fee (null there means nobody said), not of an option somebody
            // deliberately created — an option with no number is a mistake, and
            // zero is a legitimate free extra.
            $table->decimal('amount', 10, 3)->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // Every read is "the options for this event, for this role, in
            // order" — one index answers all of them.
            $table->index(['event_id', 'role', 'is_active', 'sort'], 'event_fee_options_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_fee_options');
    }
};
