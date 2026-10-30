<?php

namespace App\Rankings;

use App\Events\Models\EventCategory;
use App\Rankings\Jobs\RebuildEventRankings;
use App\Support\Modules\AbstractModule;
use App\Support\Modules\Boots;

/**
 * National rankings: a federation's athletes, ordered by the points they earned
 * at that federation's own events.
 *
 * Not to be confused with App\Ranks (belts and grading) — a rank is what you
 * WEAR, a ranking is where you STAND.
 *
 * The points come from a SCHEME chosen by the event's sport (karate → the WKF
 * World Ranking formula, App\Rankings\Schemes\Wkf). Every federation-hosted
 * event grants points; a club's own event does not.
 *
 * The store is a LEDGER (`ranking_points`): one row per athlete, per event, per
 * category, holding what they earned and why. A ranking is read off it — with
 * the one-year 50% decay applied at read time — so nothing has to run at
 * midnight for a ranking to stay true, and correcting a result simply rewrites
 * that event's rows.
 *
 * Off unless RANKINGS_ENABLED. Delete this folder, its table and its line in
 * config/modules.php and nothing else notices.
 */
class Rankings extends AbstractModule implements Boots
{
    public function key(): string
    {
        return 'rankings';
    }

    /** Events already scheduled for a rebuild in this request. */
    private static array $scheduled = [];

    public function boot(): void
    {
        // A podium being stored is the moment a division's points are known.
        //
        // AFTER THE RESPONSE, not on the queue: the scorer's screen never waits
        // for it, and it does not depend on a queue worker having this code —
        // the database queue is shared with another box, whose worker took the
        // first such job and failed it as an unknown class (2026-10-30).
        // Once per event per request, however many divisions finish in it.
        EventCategory::updated(function (EventCategory $category) {
            if (! config('rankings.enabled') || ! $category->wasChanged(['podium', 'status'])) {
                return;
            }
            $eventId = (int) $category->event_id;
            if (isset(self::$scheduled[$eventId])) {
                return;
            }
            self::$scheduled[$eventId] = true;
            rescue(fn () => RebuildEventRankings::dispatchAfterResponse($eventId), null, report: true);
        });
    }
}
