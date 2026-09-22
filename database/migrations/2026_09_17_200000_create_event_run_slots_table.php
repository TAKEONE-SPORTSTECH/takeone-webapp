<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The running order: what happens on a competition day, in the order it happens.
 *
 * An event's day is not only its divisions. It is divisions AND the things
 * between them — the medal ceremony, the prayer and lunch break, the opening,
 * the closing. None of those has an entrant, a draw or a bout, so none of them
 * can be derived from the draw; an organiser writes them down. That is what
 * this table holds, and it is why `category_id` is nullable: a slot is either a
 * division being run, or a block the organiser named.
 *
 * ORDER is the input; TIME is the output. `position` is what the organiser
 * arranges, and `App\Events\Support\RunSheet` works the clock out from the
 * day's start time, each division's bout count and the bout length. The one
 * exception is `pinned_at` — a break that must happen at 12:10 because that is
 * when the prayer is holds its own time, and the rest of the day flows around
 * it. A pinned slot is the only place a clock time is stored.
 *
 * Purely additive: a new table, no column added to `club_events` and no
 * existing row touched. An event with no rows here behaves exactly as it did —
 * the day row on the public page shows what it always showed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_run_slots', function (Blueprint $table) {
            $table->id();

            // The public key. These are never enumerable in a URL (CLAUDE.md →
            // Unpredictable Resource Identifiers); the numeric id stays internal.
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            /*
             * A division being run. NULL means this slot is a block the
             * organiser named instead — a ceremony, a break, anything else.
             * nullOnDelete: deleting a division must not silently delete the
             * organiser's running order around it. The slot survives as an
             * orphan the board shows as needing attention, which is recoverable;
             * a vanished row is not.
             */
            $table->foreignId('category_id')->nullable()
                ->constrained('event_categories')->nullOnDelete();

            // Which day of a multi-day event. Day 1 for everything single-day.
            $table->unsignedTinyInteger('day')->default(1);

            // What the organiser arranges. Gaps are fine and expected — a move
            // renumbers the day, it does not compact it.
            $table->unsignedInteger('position')->default(0);

            /*
             * 'division' | 'ceremony' | 'break' | 'custom'.
             * A string rather than an enum: the vocabulary of what happens
             * between bouts is the organiser's, and a check constraint here
             * would be one more migration every time somebody runs an event
             * with a part nobody anticipated. Validated at the door instead.
             */
            $table->string('kind', 24)->default('division');

            /*
             * The organiser's own words, for a slot that is not a division.
             * Untrusted text: escaped on output, translated through
             * App\Translation like every other authored string on an event.
             * A division slot leaves this NULL and reads its name from the
             * category, so renaming a division can never leave the running
             * order showing the old name.
             */
            $table->string('label', 160)->nullable();

            // How long a non-division block takes. A division's length is
            // derived from its draw and is never stored.
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            /*
             * Minutes from midnight, when this slot must happen at a fixed
             * clock time regardless of what ran before it. NULL — the ordinary
             * case — means "whenever the day reaches you".
             */
            $table->unsignedSmallInteger('pinned_at')->nullable();

            $table->timestamps();

            // The board reads one day in order; the public page reads the lot.
            $table->index(['event_id', 'day', 'position'], 'ers_event_day_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_run_slots');
    }
};
