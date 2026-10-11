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

    public static function redRight(ClubEvent $event, string $court): bool
    {
        return MatSides::redRight($event, $court, 'karate');
    }

    public static function set(ClubEvent $event, string $court, bool $redRight): void
    {
        MatSides::set($event, $court, $redRight, 'karate');
    }
}
