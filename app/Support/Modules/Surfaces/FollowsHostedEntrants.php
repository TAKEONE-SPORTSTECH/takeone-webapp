<?php

namespace App\Support\Modules\Surfaces;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;

/**
 * A host that keeps its own record of who is entered in its events (a
 * federation's club entries) is told whenever the EVENT's entrant list
 * changes — an athlete entered, removed, moved to another division, weighed
 * in — whichever screen did it, so its record and its numbers follow.
 *
 * Separate from HostsEvents on purpose: a host with no record of its own does
 * not implement it.
 */
interface FollowsHostedEntrants
{
    /** @param  'created'|'updated'|'deleted'  $change */
    public function hostedEntrantChanged(ClubEvent $event, ClubEventRegistration $registration, string $change): void;

    /** Something the event shows changed — a draw made or cleared, a bout decided, a podium settled, an official appointed or removed. */
    public function hostedContentChanged(ClubEvent $event): void;
}
