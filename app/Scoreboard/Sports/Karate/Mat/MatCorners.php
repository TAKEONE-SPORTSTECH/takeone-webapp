<?php

namespace App\Scoreboard\Sports\Karate\Mat;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Corners\MatSides;

/**
 * Karate's mat corner side — the shared App\Scoreboard\Corners\MatSides for
 * 'karate'. Kept under this name because Karate's views and endpoints call it.
 */
final class MatCorners
{
    public const KEY = MatSides::KEY;

    /** The mat's side — or one screen's own (MatSides::SCREENS) when `$screen` is given. */
    public static function redRight(ClubEvent $event, string $court, ?string $screen = null): bool
    {
        return MatSides::redRight($event, $court, 'karate', $screen);
    }

    /** @return array<string, bool>  every screen of this mat => red on the right */
    public static function screens(ClubEvent $event, string $court): array
    {
        return MatSides::screens($event, $court, 'karate');
    }

    /** Swap the corners on some screens only (MatSides::flip). @param list<string> $screens */
    public static function flip(ClubEvent $event, string $court, array $screens): void
    {
        MatSides::flip($event, $court, $screens, 'karate');
    }

    public static function set(ClubEvent $event, string $court, bool $redRight): void
    {
        MatSides::set($event, $court, $redRight, 'karate');
    }
}
