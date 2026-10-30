<?php

namespace App\Rankings\Jobs;

use App\Events\Models\ClubEvent;
use App\Rankings\Support\Ledger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recompute one event's ranking points.
 *
 * Run after the response by App\Rankings\Rankings::boot(). Still a queueable
 * job (unique per event) so it can be put on a queue again once the queue is no
 * longer shared between boxes.
 */
class RebuildEventRankings implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(Ledger $ledger): void
    {
        if (! config('rankings.enabled')) {
            return;
        }
        if ($event = ClubEvent::find($this->eventId)) {
            $ledger->rebuild($event);
        }
    }
}
