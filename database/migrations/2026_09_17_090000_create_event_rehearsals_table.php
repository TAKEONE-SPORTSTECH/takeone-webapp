<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dress rehearsal of a competition, and the way back from it.
 *
 * An organiser needs to run their event before the day: load a bout, score it,
 * end it, file the result, watch the bracket advance, watch the wall boards
 * follow — on the real event, with the real draw, the real entrants and the
 * real screens and cameras they will use in the hall. Anything less tests
 * something other than what they are about to run.
 *
 * What makes that safe is this row. Before the rehearsal begins, the event's
 * competition state is copied into `snapshot` as it stands. Everything then
 * runs through the ordinary engines with NO special cases — which is the whole
 * point: a rehearsal that takes a different code path proves nothing. When the
 * rehearsal ends, the snapshot is written back and whatever the rehearsal
 * created is removed, so the event is as it was.
 *
 * Additive, and inert until used: an event with no row here behaves exactly as
 * it always has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at');
            /** Null while the rehearsal is RUNNING. This is the live flag. */
            $table->timestamp('ended_at')->nullable();

            /**
             * The event's competition state before anything was rehearsed:
             * the event row, every division and every bout, all columns.
             * Restored verbatim when the rehearsal ends.
             */
            $table->longText('snapshot');

            /**
             * The last id in each table that the rehearsal may delete from, as
             * it stood when the rehearsal began. Anything ABOVE these marks was
             * written by the rehearsal and goes with it; anything below is real
             * and is never touched.
             */
            $table->json('marks')->nullable();

            /** What the organiser did while it was running, for the report. */
            $table->unsignedInteger('bouts_run')->default(0);

            $table->timestamps();

            // One rehearsal at a time per event is enforced in code (an open row
            // blocks a second begin); this index is what makes that check cheap
            // and what every "is this event rehearsing?" read goes through.
            $table->index(['event_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rehearsals');
    }
};
