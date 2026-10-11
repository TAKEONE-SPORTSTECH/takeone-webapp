<?php

namespace App\Scoreboard\Sports\Karate\Mat;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Corners\CornerLook as Shared;
use App\Scoreboard\Sports\Karate\Styles\KarateStyles;

/**
 * Karate's corner names and colours — the shared App\Scoreboard\Corners\
 * CornerLook with Karate's spec: AKA red / AO blue, or AKA red / SHIRO white
 * for Kyokushin (IKO/IFK/EKO), and the shades Karate's screens were designed
 * with. Kept as its own class so every Karate view and endpoint keeps calling
 * the name it always has.
 */
final class CornerLook
{
    public const KEY = Shared::KEY;

    public const LABEL_MAX = Shared::LABEL_MAX;

    public const TUNED = [
        '#b3121f' => [
            'deep' => '#7c0a14', 'accent' => '#ff6b78', 'plateInk' => '#b3121f', 'nameInk' => '#ff6b78', 'vsPanel' => '#2b1416',
            'vsGlow' => 'rgba(154,55,48,0.55)', 'laneFrom' => '#a21921', 'laneTo' => '#551112', 'bar' => '#ea3c3f',
            'countryInk' => '#e8b3ad', 'badgeBg' => '#c8382c', 'callout' => '#ff3b47',
            'celebrate' => [
                'a' => '#ff3d2e', 'b' => '#ff9d2e', 'glow' => 'rgba(255,90,46,.6)',
                'confetti' => ['#ff9d2e', '#ff5e2e', '#ffd08a', '#ffb84d', '#ff7a3d', '#ffe3a8'],
                'flame' => ['#ff7a3d', '#ff5e2e', '#ffb84d'], 'base' => 'rgba(255,94,46,.32)',
                'core' => '#fff2d8', 'spread' => '45%', 'fade' => '75%', 'edge' => null,
            ],
        ],
        '#0d55b8' => [
            'deep' => '#083473', 'accent' => '#8ab4ff', 'plateInk' => '#2f7bdb', 'nameInk' => '#6ea8ff', 'vsPanel' => '#10233f',
            'vsGlow' => 'rgba(40,86,152,0.55)', 'laneFrom' => '#004b97', 'laneTo' => '#012854', 'bar' => '#026fd7',
            'countryInk' => '#a9c6ea', 'badgeBg' => '#1f5aa8', 'callout' => '#4d9aff',
            'celebrate' => [
                'a' => '#2e7bff', 'b' => '#2ee6ff', 'glow' => 'rgba(46,160,255,.6)',
                'confetti' => ['#eaf6ff', '#bfe6ff', '#8ad4ff', '#ffffff', '#a8ecff', '#d6f1ff'],
                'flame' => ['#3d9bff', '#2ee6ff', '#6b9dff'], 'base' => 'rgba(46,155,255,.32)',
                'core' => '#ffffff', 'spread' => '55%', 'fade' => '80%', 'edge' => null,
            ],
        ],
        // Kyokushin's SHIRO.
        '#e9ecf2' => [
            'panelFrom' => '#f6f6f3', 'deep' => '#c8ccd4', 'accent' => '#ffffff', 'plateInk' => '#ffffff', 'nameInk' => '#ffffff', 'vsPanel' => '#2a2c33',
            'vsGlow' => 'rgba(235,238,245,0.30)', 'laneFrom' => '#9da2ad', 'laneTo' => '#3a3d45', 'bar' => '#f2f2ef',
            'countryInk' => '#e6e8ee', 'badgeBg' => '#f2f2ef', 'callout' => '#14161c',
            'celebrate' => [
                'a' => '#ffffff', 'b' => '#c9ced8', 'glow' => 'rgba(255,255,255,.55)',
                'confetti' => ['#ffffff', '#f1f3f7', '#dfe3ea', '#ffe9a8', '#c9ced8', '#ffd666'],
                'flame' => ['#ffffff', '#e6e9ef', '#c9ced8'], 'base' => 'rgba(235,238,245,.3)',
                'core' => '#ffffff', 'spread' => '55%', 'fade' => '80%', 'edge' => '#7d828f',
            ],
        ],
    ];

    /** Karate's spec for the shared class. */
    public static function spec(?ClubEvent $event): array
    {
        return ['corners' => self::defaults($event), 'tuned' => self::TUNED];
    }

    /** The style's own names and colours — what a blank setting means. */
    public static function defaults(?ClubEvent $event): array
    {
        $kyokushin = $event && KarateStyles::of($event) === 'kyokushin';

        return [
            'aka' => ['label' => 'AKA', 'colour' => '#b3121f', 'badge' => 'AKA · RED'],
            'ao' => $kyokushin
                ? ['label' => 'SHIRO', 'colour' => '#e9ecf2', 'badge' => 'SHIRO · WHITE']
                : ['label' => 'AO', 'colour' => '#0d55b8', 'badge' => 'AO · BLUE'],
        ];
    }

    public static function stored(ClubEvent $event): array
    {
        return Shared::stored($event, self::spec($event));
    }

    public static function for(?ClubEvent $event): array
    {
        return Shared::for($event, self::spec($event));
    }

    public static function set(ClubEvent $event, array $input): void
    {
        Shared::set($event, self::spec($event), $input);
    }

    public static function cleanLabel(mixed $v): ?string
    {
        return Shared::cleanLabel($v);
    }

    public static function cleanColour(mixed $v): ?string
    {
        return Shared::cleanColour($v);
    }
}
