<?php

namespace App\Scoreboard\Corners;

use App\Events\Models\ClubEvent;

/**
 * What each corner is CALLED and what COLOUR it is, for one event — for every
 * combat scoreboard (Karate, Taekwondo, BJJ). One implementation, parameterised
 * by a SPEC each sport hands in (Shared Stays Shared):
 *
 *   [
 *     'corners' => [ '<key>' => ['label' => 'AKA', 'colour' => '#b3121f', 'badge' => 'AKA · RED'], … two of them ],
 *     'tuned'   => [ '#rrggbb' => [ hand-tuned shades the sport's screens were designed with ] ],
 *   ]
 *
 * The keys are the sport's own corner ids (aka/ao, blue/white) and never
 * change; only the LOOK does. Stored on the event
 * (`club_events.scoreboard_settings.corners`) — each sport's MatState must
 * carry the key through its own persistSettings(). Every derived colour is
 * computed here from a validated #rrggbb, so nothing a person typed ever
 * reaches a style attribute except a hex this class built.
 */
final class CornerLook
{
    public const KEY = 'corners';

    public const LABEL_MAX = 12;

    private const INK_DARK = '#14161c';

    /** What the organiser stored, cleaned — label and/or colour per corner, or nothing. */
    public static function stored(ClubEvent $event, array $spec): array
    {
        $raw = ((array) $event->scoreboard_settings)[self::KEY] ?? [];
        $out = [];

        foreach (array_keys($spec['corners']) as $side) {
            $c = is_array($raw[$side] ?? null) ? $raw[$side] : [];
            $out[$side] = array_filter([
                'label' => self::cleanLabel($c['label'] ?? null),
                'colour' => self::cleanColour($c['colour'] ?? null),
            ]);
        }

        return $out;
    }

    /**
     * The full palette for both corners — what every view draws from.
     */
    public static function for(?ClubEvent $event, array $spec): array
    {
        $defaults = $spec['corners'];
        $stored = $event ? self::stored($event, $spec) : array_fill_keys(array_keys($defaults), []);
        $tuned = $spec['tuned'] ?? [];
        $out = [];

        foreach ($defaults as $side => $default) {
            $label = $stored[$side]['label'] ?? $default['label'];
            $colour = $stored[$side]['colour'] ?? $default['colour'];
            $custom = isset($stored[$side]['label']) || isset($stored[$side]['colour']);
            // The VS badge says "AKA · RED" by default; a renamed corner is
            // just its name, because we cannot know what to call its colour.
            $badge = $custom ? $label : ($default['badge'] ?? $label);

            $out[$side] = array_replace(self::palette($label, $colour, $badge), $tuned[$colour] ?? []) + [
                'customLabel' => $stored[$side]['label'] ?? null,
                'customColour' => $stored[$side]['colour'] ?? null,
                'defaultLabel' => $default['label'],
                'defaultColour' => $default['colour'],
            ];
        }

        return $out;
    }

    /**
     * Save, or clear with blanks. A value equal to the default is not stored,
     * so the event follows the convention if it is ever changed.
     */
    public static function set(ClubEvent $event, array $spec, array $input): void
    {
        $settings = (array) $event->scoreboard_settings;
        $looks = [];

        foreach ($spec['corners'] as $side => $default) {
            $label = self::cleanLabel($input[$side]['label'] ?? null);
            $colour = self::cleanColour($input[$side]['colour'] ?? null);

            $entry = array_filter([
                'label' => $label !== null && $label !== $default['label'] ? $label : null,
                'colour' => $colour !== null && $colour !== $default['colour'] ? $colour : null,
            ]);

            if ($entry) {
                $looks[$side] = $entry;
            }
        }

        if ($looks) {
            $settings[self::KEY] = $looks;
        } else {
            unset($settings[self::KEY]);
        }

        $event->forceFill(['scoreboard_settings' => $settings])->saveQuietly();
    }

    /** The validation rules an endpoint applies to the two corners. */
    public static function rules(array $spec): array
    {
        $rules = [];
        foreach (array_keys($spec['corners']) as $side) {
            $rules[$side] = ['required', 'array'];
            $rules[$side.'.label'] = ['nullable', 'string', 'max:'.self::LABEL_MAX];
            $rules[$side.'.colour'] = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $rules;
    }

    public static function cleanLabel(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        // Letters, digits, spaces and a little punctuation — it is a word on a
        // wall, and it is uppercased for display.
        $v = trim(preg_replace('/[^\p{L}\p{N} .\-]/u', '', strip_tags($v)) ?? '');
        $v = mb_strtoupper(mb_substr($v, 0, self::LABEL_MAX));

        return $v === '' ? null : $v;
    }

    public static function cleanColour(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : null;
    }

    /** Every colour a surface needs, from one. */
    public static function palette(string $label, string $hex, string $badge): array
    {
        $rgb = self::rgb($hex);
        $light = self::luminance($rgb) > 0.45;

        return [
            'label' => $label,
            'badge' => $badge,
            'colour' => $hex,
            'light' => $light,
            // The gradient: the colour itself, to its far end.
            'panelFrom' => $hex,
            'deep' => self::mix($rgb, [0, 0, 0], $light ? 0.18 : 0.4),
            // Text ON the corner's colour.
            'ink' => $light ? self::INK_DARK : '#ffffff',
            'inkDim' => $light ? 'rgba(20,22,28,.6)' : 'rgba(255,255,255,.65)',
            'inkSoft' => $light ? 'rgba(20,22,28,.75)' : 'rgba(255,255,255,.8)',
            'inkRgb' => $light ? '20,22,28' : '255,255,255',
            // The corner's colour as TEXT on the dark console and VS screen.
            'accent' => $light ? $hex : self::mix($rgb, [255, 255, 255], 0.45),
            // The console's score-plate label and a competitor's name in the bouts list.
            'plateInk' => $light ? $hex : self::mix($rgb, [255, 255, 255], 0.3),
            'nameInk' => $light ? $hex : self::mix($rgb, [255, 255, 255], 0.4),
            // The VS screen's tinted half, and the upcoming board's lane.
            'vsPanel' => self::mix($rgb, [0, 0, 0], 0.72),
            'countryInk' => self::mix($rgb, [255, 255, 255], 0.7),
            'badgeBg' => $hex,
            'badgeInk' => $light ? self::INK_DARK : '#ffffff',
            // The ring and glow of a point call-out over this corner's half.
            'callout' => $light ? self::INK_DARK : self::mix($rgb, [255, 255, 255], 0.2),
            'vsGlow' => self::rgba($rgb, 0.45),
            'laneFrom' => self::mix($rgb, [0, 0, 0], $light ? 0.35 : 0.1),
            'laneTo' => self::mix($rgb, [0, 0, 0], 0.62),
            // The upcoming board's header bar.
            'bar' => $hex,
            // The winner celebration's scene.
            'celebrate' => [
                'a' => $light ? $hex : self::mix($rgb, [255, 255, 255], 0.15),
                'b' => self::mix($rgb, [255, 255, 255], 0.5),
                'glow' => self::rgba($rgb, 0.6),
                'confetti' => [
                    self::mix($rgb, [255, 255, 255], 0.8), self::mix($rgb, [255, 255, 255], 0.55), $hex,
                    '#ffffff', self::mix($rgb, [255, 255, 255], 0.3), '#ffd666',
                ],
                'flame' => [$hex, self::mix($rgb, [255, 255, 255], 0.35), self::mix($rgb, [255, 255, 255], 0.6)],
                'base' => self::rgba($rgb, 0.32),
                'core' => '#ffffff',
                'spread' => '50%',
                'fade' => '78%',
                'edge' => $light ? self::mix($rgb, [0, 0, 0], 0.45) : null,
            ],
        ];
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function luminance(array $rgb): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function mix(array $rgb, array $with, float $t): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(
            fn ($a, $b) => (int) round($a + ($b - $a) * $t), $rgb, $with
        ));
    }

    private static function rgba(array $rgb, float $a): string
    {
        return sprintf('rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], $a);
    }
}
