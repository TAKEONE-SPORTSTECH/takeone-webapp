<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The persisted officiating timeline.
 *
 * Scoring today mutates a MatState blob and saves it, so the platform knows
 * what the score IS but has no record of how it got there. This table is that
 * record: one append-only row per command that passes Scoring::apply().
 *
 * It is the substrate for the video timeline (Documentation/VIDEO-INTEGRATION.md
 * §5.1), but it earns its place without video — it is an audit trail of
 * officiating, which is the thing a federation asks for after a disputed bout.
 *
 * Strictly additive: a new table, no existing column or table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard so re-running can never fail a deploy half-way.
        if (Schema::hasTable('event_match_events')) {
            return;
        }

        Schema::create('event_match_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // Nullable, and it SURVIVES the bout being deleted: an audit trail
            // that disappears when the thing it audits is removed is not an
            // audit trail. A command issued on an empty mat has no bout either.
            $table->foreignId('match_id')->nullable()
                ->constrained('event_matches')->nullOnDelete();

            $table->string('court');

            // Which package wrote the row. The vocabulary below is per-sport,
            // so a reader needs to know whose dictionary to use.
            $table->string('sport', 40)->nullable();

            // load | start | pause | point | score | penalty | gamjeom |
            // senshu | finish | commit | … — each package's own command set.
            $table->string('command', 40);

            // Exactly as received, for replay and dispute. Never trusted as
            // structure by anything reading it.
            $table->json('payload')->nullable();

            // Denormalised out of the payload so a query does not have to open
            // the JSON. 'a' / 'b' — the same sides as event_matches, never the
            // sport's own aka/ao/blue/red, which the package maps on export.
            $table->string('side', 1)->nullable();
            $table->integer('points')->nullable();

            // The running score AFTER this command, which is what makes a row
            // readable on its own rather than only as a diff of its neighbours.
            $table->integer('score_a')->default(0);
            $table->integer('score_b')->default(0);

            // Wall clock, millisecond precision (the model sets the format).
            // Media time is DERIVED from this at publish; storing only media
            // time would mean a re-encode silently moved every marker.
            $table->dateTime('occurred_at');

            // Monotonic per (event, court). Deliberately NOT unique: a clash
            // must never throw inside a live scoring request. Ordering falls
            // back to (occurred_at, id), which is total regardless.
            $table->unsignedInteger('sequence')->default(0);

            $table->timestamps();

            $table->index(['event_id', 'court', 'sequence']);
            $table->index(['match_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_match_events');
    }
};
