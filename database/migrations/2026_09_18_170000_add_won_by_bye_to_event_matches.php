<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tell a WALKOVER apart from a bout somebody actually won.
 *
 * Both are written the same way — a winner and `status = 'done'` — and until
 * now the only way to tell them apart was to look at whether the other corner
 * was empty. That heuristic is true exactly until the corner is FILLED, which
 * is the one moment it matters: an organiser drops a late athlete into a bye's
 * empty side, the bout now has two names and a stale verdict, and every reader
 * sees a contest that was won before it was fought. It shipped on 2026-09-18
 * and had to be undone by hand.
 *
 * A flag written at the moment the walkover is created answers it durably, and
 * it has to be a flag rather than an inference because the organiser can now
 * record a winner with no score and no reason — which is indistinguishable
 * from a bye by any other measure.
 *
 * Additive and defaulted 0, so every existing row reads "this was fought" —
 * the safe answer, because it is the one that makes nothing re-derive itself.
 * The backfill below then marks the rows that are demonstrably byes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->boolean('won_by_bye')->default(false)->after('winner');
        });

        /*
         * The byes on file right now, by the old heuristic — which is still
         * correct for every row that predates this column, because nothing
         * could yet fill a decided bye's empty corner without a human doing it
         * by hand today.
         */
        \Illuminate\Support\Facades\DB::table('event_matches')
            ->whereNotNull('winner')
            ->where(function ($q) {
                $q->where(fn ($q) => $q->where('winner', 'a')->whereNull('b_name'))
                  ->orWhere(fn ($q) => $q->where('winner', 'b')->whereNull('a_name'));
            })
            ->update(['won_by_bye' => true]);
    }

    public function down(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropColumn('won_by_bye');
        });
    }
};
