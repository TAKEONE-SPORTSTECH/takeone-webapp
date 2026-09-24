<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Starting a competition becomes a deliberate act.
 *
 * Until now "has this started?" was answered by the clock: if the start time
 * had passed, the event was running and the draw was locked. Nobody decided it.
 * That is fine for a class in a calendar and wrong for a competition, where
 * the organiser is standing in a hall deciding whether the day can begin —
 * and where a readiness checklist is supposed to be able to hold it back.
 *
 * `started_at` is now the single source of truth. `started_by` records who
 * made the call, and `start_overridden` records that they made it with items
 * still outstanding: an override is a decision someone should be able to
 * answer for afterwards, so it is kept rather than inferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->unsignedBigInteger('started_by')->nullable()->after('started_at');
            $table->boolean('start_overridden')->default(false)->after('started_by');
        });

        // Events whose start time has already passed were "started" under the
        // old rule, and many of them are finished. Backfilling keeps that true:
        // without it every past event would spring back to "not started yet",
        // unlocking draws that were settled months ago.
        //
        // Done in PHP rather than one UPDATE ... WHERE datetime(...): the date
        // and time are two columns and stitching them together is a different
        // expression in SQLite and MySQL. This runs once, over one table.
        DB::table('club_events')
            ->select('id', 'date', 'start_time')
            ->whereNull('started_at')
            ->whereNotNull('date')
            ->orderBy('id')
            ->chunk(500, function ($events) {
                $started = [];

                foreach ($events as $event) {
                    $at = rescue(
                        fn () => Carbon::parse($event->date)->setTimeFromTimeString($event->start_time ?: '00:00'),
                        null,
                        false,
                    );

                    if ($at && $at->isPast()) {
                        $started[] = $event->id;
                    }
                }

                if ($started) {
                    DB::table('club_events')->whereIn('id', $started)->update(['started_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'started_by', 'start_overridden']);
        });
    }
};
