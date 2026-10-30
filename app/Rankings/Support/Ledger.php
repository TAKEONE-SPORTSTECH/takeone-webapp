<?php

namespace App\Rankings\Support;

use App\Events\Models\ClubEvent;
use App\Rankings\Models\RankingPoint;
use Illuminate\Support\Facades\DB;

/**
 * Writes one event's rows into the rankings ledger.
 *
 * Always a full rewrite of THAT event, in one transaction: a corrected result,
 * a division re-drawn or an event that stopped granting points (moved off the
 * federation, cancelled) all come out right by recomputing, with no
 * bookkeeping about what changed. Only this event's rows are ever touched.
 */
class Ledger
{
    public function __construct(private EventPoints $points) {}

    /** @return int rows now on file for the event */
    public function rebuild(ClubEvent $event): int
    {
        $rows = $this->points->for($event);
        $now = now();

        DB::transaction(function () use ($event, $rows, $now) {
            RankingPoint::where('event_id', $event->id)->delete();

            foreach (array_chunk(array_values($rows), 200) as $chunk) {
                RankingPoint::insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $chunk));
            }
        });

        return count($rows);
    }
}
