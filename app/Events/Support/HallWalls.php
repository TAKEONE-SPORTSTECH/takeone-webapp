<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;

/**
 * Tell an event's hall screens — every mat — to redraw their queue.
 *
 * The wall's up-next board orders itself by bout number and prints it
 * ("MATCH 12"), but it only redraws when it is pushed to. A result pushes it;
 * a change to the RUNNING ORDER (a bout renumbered on the tablet, dragged on
 * the day-order board, the day laid out again) has to push it too, or the
 * hall keeps calling bouts in the old order.
 *
 * One door for every sport's screen channel, so a caller never needs to know
 * which sport it is talking to. Best-effort, like every realtime push: the
 * database is the truth and a screen that missed this catches up on the next.
 */
class HallWalls
{
    /** sport => its hall-screen channel */
    private const CHANNELS = [
        'karate' => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::class,
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::class,
    ];

    public static function refresh(ClubEvent $event): void
    {
        $channel = self::CHANNELS[$event->sport] ?? null;

        if ($channel && class_exists($channel)) {
            rescue(fn () => $channel::notifyCourt($event, null), null, false);
        }
    }
}
