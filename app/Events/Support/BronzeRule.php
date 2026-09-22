<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Sports\Combat\SportRegistry;

/**
 * How THIS championship settles third place.
 *
 * The sport has an answer — `CombatSport::bronzeRule()`, the rule book's
 * reading — and it is the right default. It is not the last word: a federation
 * runs the same sport two ways, and the organiser standing in the hall knows
 * which one is happening today. `club_events.third_place_match` is them saying
 * so, and NULL is them not having said anything, which leaves the sport's
 * answer exactly as it was.
 *
 * ⚠️ ONE PLACE. Three separate pieces of code decide something off the back of
 * this — whether the draw cuts a play-off bout (DrawEngine), how the podium is
 * awarded (Advancement), and how a division with no stored podium is read
 * (Results). They disagreed once already, when the board's menu let an
 * organiser pick a convention that only existed in their browser. Every one of
 * them asks HERE.
 */
class BronzeRule
{
    public const PLAY_OFF = 'third_place_match';

    public const BOTH_LOSERS = 'both_sf_losers';

    public static function for(?ClubEvent $event): string
    {
        if ($event?->third_place_match !== null) {
            return $event->third_place_match ? self::PLAY_OFF : self::BOTH_LOSERS;
        }

        return app(SportRegistry::class)->get($event?->sport)?->bronzeRule() ?? self::BOTH_LOSERS;
    }

    /** Does this event play third place off? */
    public static function playsOff(?ClubEvent $event): bool
    {
        return self::for($event) === self::PLAY_OFF;
    }
}
