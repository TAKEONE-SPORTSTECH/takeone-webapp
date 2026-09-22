<?php

namespace App\Support;

/**
 * A medal, drawn once.
 *
 * Bootstrap Icons has no medal and the platform was using the emoji — 🥇 🥈 🥉
 * — in seven places, each with its own hex beside it for the tint of the row
 * behind it. An emoji is the operating system's drawing, not ours: a different
 * object on Android, iOS and Windows, it ignores the size it is given, and it
 * cannot take our ink.
 *
 * So the medal is drawn here, in one place, and every surface uses that one
 * drawing — the same arrangement `App\Sports\Combat\BeltIcon` has for belts,
 * and for the same reason. Two consumers:
 *
 *   · `<x-medal-icon>`      renders `shapes()` inline, so the component stays
 *                           standalone and needs nothing on the page.
 *   · `<x-medal-icon-defs>` renders each place as a `<symbol>`, for a medal
 *                           drawn by JAVASCRIPT — the bracket canvas builds its
 *                           podium card in JS and cannot call a Blade
 *                           component. It `<use>`s the symbol instead, which is
 *                           how the geometry stays in one file.
 *
 * ⚠️ The geometry lives in `shapes()` and nowhere else. A medal re-drawn in a
 * second place is a medal that will eventually be two different medals.
 */
final class Medal
{
    /**
     * The three metals.
     *
     * Each is a pair — the lit edge and the shaded body — so the disc reads as
     * struck metal rather than as a flat circle. `ink` is what the numeral is
     * written in, chosen for contrast against the body: dark on gold and
     * silver, white on bronze.
     *
     * @return array<string, string>|null  null for a place that is not a medal
     */
    public static function metal(int $place): ?array
    {
        return [
            1 => ['lit' => '#fde68a', 'body' => '#f59e0b', 'rim' => '#b45309', 'ink' => '#452a05',
                'ribbon' => '#dc2626', 'ribbon2' => '#991b1b'],
            2 => ['lit' => '#f1f5f9', 'body' => '#cbd5e1', 'rim' => '#94a3b8', 'ink' => '#1e293b',
                'ribbon' => '#3b82f6', 'ribbon2' => '#1d4ed8'],
            3 => ['lit' => '#e7b08a', 'body' => '#c2703c', 'rim' => '#8a4a22', 'ink' => '#ffffff',
                'ribbon' => '#16a34a', 'ribbon2' => '#15803d'],
        ][$place] ?? null;
    }

    /**
     * A placing that is NOT a medal — fourth, and anything past it.
     *
     * Slate, no ribbon, the numeral doing all the work. Coming off the podium
     * by a single bout is a placing an athlete carries and a federation reads;
     * dressing it as metal would put somebody on a podium they came off.
     *
     * @return array<string, string>
     */
    public static function plain(): array
    {
        return ['body' => '#e2e8f0', 'rim' => '#94a3b8', 'ink' => '#475569'];
    }

    /** The viewBox a medal is drawn in. Taller than wide: the ribbon hangs above the disc. */
    public static function viewBox(int $place): string
    {
        return self::metal($place) ? '0 0 24 32' : '0 0 24 24';
    }

    /** How much taller than wide, so a caller can size the box from the disc's diameter. */
    public static function ratio(int $place): float
    {
        return self::metal($place) ? 1.32 : 1.0;
    }

    /**
     * The medal itself: ribbon, rim, body, the lit crescent, and the numeral.
     *
     * Returns SVG markup for the INSIDE of an `<svg>` or a `<symbol>` — never a
     * wrapper, because the two consumers need different ones.
     *
     * Every value interpolated here is a constant from this file or an integer
     * cast from `$place`, so there is nothing to escape: no caller-supplied
     * string reaches the output.
     */
    public static function shapes(int $place, bool $numeral = true): string
    {
        $m = self::metal($place);
        $c = $m ?? self::plain();
        $cy = $m ? 22 : 12;
        $r = $m ? 9.4 : 11;
        $inner = $m ? 8.2 : 9.8;

        $svg = '';

        if ($m) {
            // The ribbon: two straps from the shoulders down to the disc,
            // drawn first so the metal sits on top of them.
            $svg .= '<path d="M6.6 0.8 L12 12.4 L8.2 14.6 L2.8 3" fill="'.$m['ribbon2'].'"/>'
                  . '<path d="M17.4 0.8 L12 12.4 L15.8 14.6 L21.2 3" fill="'.$m['ribbon'].'"/>';
        }

        $svg .= '<circle cx="12" cy="'.$cy.'" r="'.$r.'" fill="'.$c['rim'].'"/>'
              . '<circle cx="12" cy="'.$cy.'" r="'.$inner.'" fill="'.$c['body'].'"/>';

        if ($m) {
            // A lit crescent on the leading shoulder, so it reads as struck
            // rather than printed.
            $svg .= '<path d="M12 13.8 a8.2 8.2 0 0 0 -8.2 8.2 a8.2 8.2 0 0 1 12.6 -6.9 z" fill="'
                  . $m['lit'].'" opacity="0.85"/>';
        }

        if ($numeral && $place > 0) {
            $svg .= '<text x="12" y="'.$cy.'" text-anchor="middle" dominant-baseline="central"'
                  . ' font-family="Inter, system-ui, sans-serif" font-weight="900" font-size="'
                  . ($m ? '9.5' : '11').'" fill="'.$c['ink'].'">'.(int) $place.'</text>';
        }

        return $svg;
    }
}
