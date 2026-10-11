<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;

/**
 * Which sport's corner classes and screen channel the two corner tools talk to.
 * Not a tool — BaseTool is not extended, so the server never lists it.
 */
final class CornerTools
{
    private const LOOKS = [
        'karate' => \App\Scoreboard\Sports\Karate\Mat\CornerLook::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\Mat\CornerLook::class,
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\CornerLook::class,
    ];

    private const CHANNELS = [
        'karate' => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::class,
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::class,
    ];

    public static function supports(string $sport): bool
    {
        return isset(self::LOOKS[$sport]);
    }

    /** @return class-string */
    public static function look(string $sport): string
    {
        return self::LOOKS[$sport];
    }

    /** Tell the mat's (or every mat's) screens and consoles to redraw their corners. */
    public static function push(ClubEvent $event, ?string $court): void
    {
        rescue(fn () => (self::CHANNELS[$event->sport])::notifyCourt($event, $court, ['action' => 'corners']), null, false);
    }
}
