<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Corners\CornerLook as Shared;

/**
 * Brazilian jiu-jitsu's corner names and colours — the shared
 * App\Scoreboard\Corners\CornerLook with BJJ's spec.
 *
 * ⚠️ The KEYS are the side, not the colour (see bjj/scoreboard/partials/
 * console-styles): `blue` is side A, painted RED since 2026-09-20, and `white`
 * is side B, painted BLUE. The defaults below are the exact hexes and hand-tuned
 * shades the wall and console draw today, so an event nobody customised looks
 * exactly as it always did. The broadcast overlay and the winner celebration
 * keep their OWN palettes unless a corner is given a custom colour.
 */
final class CornerLook
{
    public const KEY = Shared::KEY;

    public const LABEL_MAX = Shared::LABEL_MAX;

    /** The shades BJJ's screens were designed with, for its two default colours. */
    public const TUNED = [
        // Side A ("blue" in the data) — red.
        '#c1121f' => [
            'deep' => '#5c0a10', 'accent' => '#ff9aa0', 'plateInk' => '#c1121f', 'nameInk' => '#ff9aa0',
            'vsPanel' => '#3f1013', 'vsGlow' => 'rgba(40,86,152,0.55)', 'countryInk' => '#a9c6ea',
            'badgeBg' => '#c1121f', 'badgeInk' => '#ffffff', 'callout' => '#ff5a63',
            'laneFrom' => '#a21921', 'laneTo' => '#551112', 'bar' => '#ea3c3f',
        ],
        // Side B ("white" in the data) — blue.
        '#1362d1' => [
            'deep' => '#08284f', 'accent' => '#8ab4ff', 'plateInk' => '#2f7bdb', 'nameInk' => '#8ab4ff',
            'vsPanel' => '#10233f', 'vsGlow' => 'rgba(226,232,240,0.42)', 'countryInk' => '#dfe6ef',
            'badgeBg' => '#1362d1', 'badgeInk' => '#ffffff', 'callout' => '#4d9aff',
            'laneFrom' => '#004b97', 'laneTo' => '#012854', 'bar' => '#026fd7',
        ],
    ];

    /** BJJ's spec for the shared class. */
    public static function spec(?ClubEvent $event = null): array
    {
        return ['corners' => self::defaults(), 'tuned' => self::TUNED];
    }

    /** The sport's own names (translated) and colours — what a blank setting means. */
    public static function defaults(): array
    {
        $blue = (string) __('sport-brazilianjiujitsu::messages.corner_blue');
        $white = (string) __('sport-brazilianjiujitsu::messages.corner_white');

        return [
            'blue' => ['label' => $blue, 'colour' => '#c1121f', 'badge' => $blue],
            'white' => ['label' => $white, 'colour' => '#1362d1', 'badge' => $white],
        ];
    }

    public static function for(?ClubEvent $event): array
    {
        return Shared::for($event, self::spec($event));
    }

    public static function set(ClubEvent $event, array $input): void
    {
        Shared::set($event, self::spec($event), $input);
    }

    public static function rules(): array
    {
        return Shared::rules(self::spec());
    }
}
