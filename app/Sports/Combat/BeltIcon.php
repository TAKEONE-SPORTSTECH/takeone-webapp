<?php

namespace App\Sports\Combat;

/**
 * The belt, drawn.
 *
 * Bootstrap Icons — the platform's only icon set — has no belt, and rank is one
 * of the few things a combat-sport product says on nearly every screen.
 * `bi-award` was standing in for it, which says "a prize", not "a grade".
 *
 * This holds the two halves a drawing needs and nothing else:
 *
 *   · **The colours** a belt is drawn in, derived from the ONE colour the
 *     platform already agreed on (BeltRank::chip). Three surfaces draw a rank
 *     and they must not each work out their own shade of purple.
 *   · **The geometry**, as one string of rects, so the Blade component and the
 *     `<symbol>` the JS-built lists point at are the same drawing rather than
 *     two that will drift.
 *
 * Why a shape function rather than a .svg file: the belt is drawn in THREE
 * colours that change per athlete, and a file cannot be recoloured without
 * either a CSS mask (one colour only) or a fetch per belt.
 *
 * The drawing is asymmetric on purpose: two equal tails hanging from a centred
 * knot read as a little figure with legs at 24px. One long tail falling left
 * and one short one falling right reads as cloth.
 */
class BeltIcon
{
    /** Any colour at or above this perceived lightness needs an outline. */
    private const PALE = 0.82;

    /** Below this, shading the knot DARKER would make it disappear. */
    private const DARK = 0.25;

    /** The ink a pale belt is outlined in, so a white belt is visible on white. */
    public const OUTLINE = 'rgba(15,23,42,.38)';

    /**
     * What to draw a recorded rank in — or null when nothing is on file.
     *
     * Accepts every shape a belt travels in on this platform: a ladder value
     * ('blue'), a weigh-in column, or the whole `{colour, grade, …}` that
     * BeltRank::for() returns. The colour, the wording and the is-it-too-pale
     * measurement all come from BeltRank::chip(), so the icon can never show a
     * different purple from the chip beside it.
     *
     * Null for a rank the ladder does not know, exactly as the chip is null:
     * one silence, so a reader never sees a belt on one surface and not another.
     *
     * @param  mixed        $belt   a ladder value, or a resolved belt array
     * @param  string|null  $grade  the degree as an organiser typed it
     * @param  string|null  $tip    the rank bar at the tails' ends, if any
     * @return array{band: string, knot: string, tip: ?string, outline: ?string, label: string}|null
     */
    public static function for(mixed $belt, ?string $grade = null, ?string $tip = null): ?array
    {
        $chip = BeltRank::chip($belt, $grade);

        if ($chip === null) {
            return null;
        }

        return self::fromColour($chip['colour'], $tip, $chip['pale'])
            + ['label' => $chip['label']];
    }

    /**
     * The same thing for a colour chosen by hand rather than read off a record
     * — an empty state, a menu entry, a belt that belongs to nobody.
     *
     * @return array{band: string, knot: string, tip: ?string, outline: ?string}
     */
    public static function fromColour(string $hex, ?string $tip = null, ?bool $pale = null): array
    {
        $lum = self::luminance($hex);

        return [
            'band' => $hex,
            /*
             * The knot is shaded so it separates from the band behind it. Which
             * WAY it is shaded has to follow the colour: darkening a black belt
             * gives you a black square on black, so a dark belt's knot is
             * lifted towards white and everything else pushed towards black.
             */
            'knot' => $lum < self::DARK
                ? self::mix($hex, [255, 255, 255], 0.26)
                : self::mix($hex, [0, 0, 0], 0.22),
            'tip' => $tip === null || $tip === '' ? null : self::hex($tip),
            /*
             * A white belt on a white card is invisible, and the colour is not
             * open to falsification — a white belt IS white. So a pale belt gets
             * a silhouette outline. Measured rather than named, so any pale
             * colour added to the ladder later is handled without touching this.
             */
            'outline' => ($pale ?? ($lum > self::PALE)) ? self::OUTLINE : null,
        ];
    }

    /**
     * The drawing itself — the only copy of it.
     *
     * The band is two pieces because the knot sits between them; the narrow
     * strip down the knot is the belt passing through it. `$extra` is how the
     * same rects are re-drawn underneath as the outline, fractionally fatter.
     *
     * @param  array{band: string, knot: string, tip: ?string}  $c
     */
    public static function shape(array $c, string $extra = ''): string
    {
        $tips = ($c['tip'] ?? null) === null ? '' : <<<SVG
            <rect x="9.1" y="20.4" width="2.6" height="1.8" rx="1" transform="rotate(15 10.4 13.8)" fill="{$c['tip']}" {$extra}/>
            <rect x="12.4" y="18.6" width="2.6" height="1.8" rx="1" transform="rotate(-7 13.7 13.8)" fill="{$c['tip']}" {$extra}/>
        SVG;

        return <<<SVG
            <rect x="0.6" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{$c['band']}" {$extra}/>
            <rect x="14" y="9.8" width="9.4" height="4.4" rx="1.3" fill="{$c['band']}" {$extra}/>
            <rect x="9.1" y="13.2" width="2.6" height="9" rx="1" transform="rotate(15 10.4 13.8)" fill="{$c['band']}" {$extra}/>
            <rect x="12.4" y="13.2" width="2.6" height="7.2" rx="1" transform="rotate(-7 13.7 13.8)" fill="{$c['band']}" {$extra}/>
            {$tips}
            <rect x="8.6" y="8.8" width="6.8" height="6" rx="2" fill="{$c['knot']}" {$extra}/>
            <rect x="11.1" y="8.8" width="1.8" height="6" rx="0.3" fill="{$c['band']}" {$extra}/>
        SVG;
    }

    /**
     * The colours as CSS custom properties, for a belt drawn by JAVASCRIPT.
     *
     * A list built client-side (the division people picker, a card patched
     * after a save) cannot call this class per row — so the server sends the
     * colours and the page holds ONE copy of the geometry, as the `<symbol>`
     * <x-belt-icon-defs> prints. The instance is then `<svg :style="…">
     * <use href="#belt-icon"/></svg>` and nothing about the drawing is
     * re-implemented in JS.
     *
     * Absent properties fall back to `transparent` inside the symbol, which is
     * how "no tip bar" and "no outline" are expressed without a second symbol.
     *
     * @param  array{band: string, knot: string, tip: ?string, outline: ?string}  $c
     */
    public static function style(array $c): string
    {
        $vars = ['--belt-band: '.$c['band'], '--belt-knot: '.$c['knot']];

        if ($c['tip'] ?? null) {
            $vars[] = '--belt-tip: '.$c['tip'];
        }

        if ($c['outline'] ?? null) {
            $vars[] = '--belt-outline: '.$c['outline'];
        }

        return implode('; ', $vars);
    }

    /**
     * Everything a JSON payload needs to draw one belt: the style string, and
     * the wording for the title/aria-label.
     *
     * @return array{style: string, label: string}|null
     */
    public static function payload(mixed $belt, ?string $grade = null, ?string $tip = null): ?array
    {
        $c = self::for($belt, $grade, $tip);

        return $c === null ? null : ['style' => self::style($c), 'label' => $c['label']];
    }

    /** A ladder value → its hex; a hex → itself. */
    private static function hex(string $c): string
    {
        $c = trim($c);

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $c)
            ? $c
            : (BeltRank::chip($c)['colour'] ?? '#111827');
    }

    /** Perceived lightness, 0–1. Expanded from #abc first, or substr reads half a channel. */
    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param  array{0: int, 1: int, 2: int}  $towards */
    private static function mix(string $hex, array $towards, float $amount): string
    {
        $out = '#';

        foreach (self::channels($hex) as $i => $v) {
            $out .= str_pad(dechex((int) round($v + ($towards[$i] - $v) * $amount)), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }
}
