<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;

/**
 * The mats an event runs on, for the hall-screen and camera panel.
 *
 * Once there is a draw: the mats it has put bouts on, exactly as before.
 * Before it: the mats the event is configured for (`club_events.courts`, at
 * least one), so screens, score control and cameras can be paired before the
 * draw exists. An event set up the day before still needs its boards on the
 * wall. Named "Mat N", exactly as the Scheduler names them, so a screen paired
 * to "Mat 1" now is on the mat the draw later puts bouts on.
 */
final class EventMats
{
    /** @return array<int, string> */
    public static function of(ClubEvent $event): array
    {
        $mats = EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')->distinct()->pluck('court')
            ->map(fn ($c) => (string) $c)->all();

        if ($mats !== []) {
            sort($mats);

            return $mats;
        }

        for ($i = 1; $i <= max(1, (int) ($event->courts ?: 0)); $i++) {
            $mats[] = 'Mat '.$i;
        }

        $mats = array_values(array_unique($mats));
        // "Mat 10" after "Mat 9", not between "Mat 1" and "Mat 2".
        usort($mats, fn ($a, $b) => strnatcasecmp($a, $b));

        return $mats;
    }
}
