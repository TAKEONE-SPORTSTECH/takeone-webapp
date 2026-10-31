<?php

namespace App\Rankings\Support;

use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use App\Rankings\Models\RankingEvent;
use Illuminate\Support\Facades\Schema;

/**
 * An event's ranking coefficient — the WKF "event factor" every point earned
 * there is multiplied by. Set per event by whoever runs it; an event nobody
 * has set uses `rankings.default_factor`.
 *
 * Decides nothing about WHO may set it: the caller authorises.
 */
class EventFactor
{
    public const MIN = 1;

    public const MAX = 20;

    public static function for(ClubEvent $event): int
    {
        $set = Schema::hasTable('ranking_events') ? RankingEvent::where('event_id', $event->id)->value('factor') : null;

        return max(self::MIN, min(self::MAX, (int) ($set ?: config('rankings.default_factor', 1))));
    }

    /** Store it and recompute that event's points at once. */
    public static function set(ClubEvent $event, int $factor, ?User $by): int
    {
        $factor = max(self::MIN, min(self::MAX, $factor));

        RankingEvent::updateOrCreate(['event_id' => $event->id], ['factor' => $factor, 'set_by' => $by?->id]);
        app(Ledger::class)->rebuild($event);

        return $factor;
    }
}
