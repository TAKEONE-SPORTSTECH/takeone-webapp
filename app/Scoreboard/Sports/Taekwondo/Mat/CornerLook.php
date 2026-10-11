<?php

namespace App\Scoreboard\Sports\Taekwondo\Mat;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Corners\CornerLook as Shared;
use App\Scoreboard\Corners\MatSides;

/**
 * Taekwondo's corner names and colours, and which side of each mat red stands
 * on — the shared App\Scoreboard\Corners\{CornerLook, MatSides} with
 * Taekwondo's spec: HONG (red) / CHUNG (blue), keyed by the mat state's own
 * `aka` / `ao`.
 *
 * skin() turns that look into the CSS the Taekwondo screens are drawn with.
 * While a corner keeps its default colour it returns EXACTLY the values the
 * screens were designed with (oklch with hex fallbacks), so an event nobody
 * customised looks as it always did; any other colour takes shades from the
 * shared palette.
 */
final class CornerLook
{
    public const SPORT = 'taekwondo';

    private const RED = '#c8382c';

    private const BLUE = '#1f5aa8';

    /** The screens' original CSS, per default colour. */
    private const DESIGNED = [
        self::RED => [
            'cardFrom' => 'oklch(0.55 0.21 25)', 'cardTo' => 'oklch(0.34 0.13 25)', 'cardGlow' => 'oklch(0.6 0.22 25 / 0.35)',
            'barFrom' => 'oklch(0.3 0.11 25)', 'barLine' => 'oklch(0.6 0.22 25)',
            'vsPanel' => 'background:#2b1416;background:oklch(0.28 0.09 25);',
            'vsGlow' => ['rgba(154,55,48,0.55)', 'oklch(0.45 0.18 25 / 0.55)'],
            'badgeBg' => 'background:#c8382c;background:oklch(0.55 0.2 25);',
            'country' => 'color:#e8b3ad; color:oklch(0.85 0.05 25);',
            'tally' => 'oklch(0.65 0.22 25)', 'callout' => '#ff5548',
            'accent' => 'oklch(0.6 0.22 25)', 'softInk' => '#ff8b80',
        ],
        self::BLUE => [
            'cardFrom' => 'oklch(0.5 0.18 255)', 'cardTo' => 'oklch(0.3 0.11 255)', 'cardGlow' => 'oklch(0.52 0.19 255 / 0.35)',
            'barFrom' => 'oklch(0.28 0.1 255)', 'barLine' => 'oklch(0.52 0.19 255)',
            'vsPanel' => 'background:#10233f;background:oklch(0.28 0.09 255);',
            'vsGlow' => ['rgba(40,86,152,0.55)', 'oklch(0.45 0.15 255 / 0.55)'],
            'badgeBg' => 'background:#1f5aa8;background:oklch(0.5 0.16 255);',
            'country' => 'color:#a9c6ea; color:oklch(0.85 0.05 255);',
            'tally' => 'oklch(0.62 0.19 255)', 'callout' => '#4d9aff',
            'accent' => 'oklch(0.52 0.19 255)', 'softInk' => '#8bb8ff',
        ],
    ];

    public static function spec(): array
    {
        return [
            'corners' => [
                'aka' => ['label' => __('scoreboard::taekwondo_messages.sb_hong'), 'colour' => self::RED],
                'ao' => ['label' => __('scoreboard::taekwondo_messages.sb_chung'), 'colour' => self::BLUE],
            ],
            'tuned' => [],
        ];
    }

    public static function for(?ClubEvent $event): array
    {
        return Shared::for($event, self::spec());
    }

    public static function set(ClubEvent $event, array $input): void
    {
        Shared::set($event, self::spec(), $input);
    }

    public static function rules(): array
    {
        return Shared::rules(self::spec());
    }

    public static function redRight(ClubEvent $event, string $court): bool
    {
        return MatSides::redRight($event, $court, self::SPORT);
    }

    public static function setRedRight(ClubEvent $event, string $court, bool $redRight): void
    {
        MatSides::set($event, $court, $redRight, self::SPORT);
    }

    /** True when either corner has a name or colour of its own. */
    public static function customised(array $look): bool
    {
        foreach ($look as $c) {
            if ($c['customLabel'] !== null || $c['customColour'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The CSS the Taekwondo screens need for each corner. Every value is either
     * a constant above or built by the shared palette from a validated hex.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function skin(array $look): array
    {
        $out = [];

        foreach ($look as $side => $c) {
            $designed = $c['customColour'] === null ? (self::DESIGNED[$c['colour']] ?? null) : null;
            $light = (bool) $c['light'];

            $out[$side] = $designed ? $designed + [
                'label' => $c['label'], 'badge' => $c['badge'], 'light' => false,
                'cardLabelInk' => 'rgba(255,255,255,0.75)', 'scoreInk' => '#fffdf5',
                'scoreShadow' => '0 16px 70px rgba(0,0,0,0.55)', 'gamInk' => 'rgba(255,255,255,0.7)',
                'badgeInk' => '#fff', 'accentInk' => '#fff', 'celebrate' => null,
            ] : [
                'label' => $c['label'], 'badge' => $c['badge'], 'light' => $light,
                'cardFrom' => $c['panelFrom'], 'cardTo' => $c['deep'], 'cardGlow' => $c['vsGlow'],
                'barFrom' => $c['laneTo'], 'barLine' => $c['colour'],
                'vsPanel' => 'background:'.$c['vsPanel'].';',
                'vsGlow' => [$c['vsGlow'], $c['vsGlow']],
                'badgeBg' => 'background:'.$c['badgeBg'].';',
                'country' => 'color:'.$c['countryInk'].';',
                'tally' => $c['accent'], 'callout' => $c['callout'],
                // The console: the corner's colour, the type on it, and the
                // colour as text on the console's dark ground.
                'accent' => $c['colour'], 'accentInk' => $c['badgeInk'], 'softInk' => $c['accent'],
                'cardLabelInk' => $light ? 'rgba(20,22,28,0.7)' : 'rgba(255,255,255,0.75)',
                'scoreInk' => $light ? '#14161c' : '#fffdf5',
                'scoreShadow' => $light ? '0 10px 40px rgba(255,255,255,0.45)' : '0 16px 70px rgba(0,0,0,0.55)',
                'gamInk' => $light ? 'rgba(20,22,28,0.7)' : 'rgba(255,255,255,0.7)',
                'badgeInk' => $c['badgeInk'],
                // Only a corner with a colour of its own hands the celebration
                // a palette; the default keeps the celebration's own scene.
                'celebrate' => $c['celebrate'],
            ];
        }

        return $out;
    }
}
