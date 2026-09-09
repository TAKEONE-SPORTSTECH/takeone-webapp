<?php

namespace App\Support;

/**
 * Colour arithmetic for views that are handed a colour as DATA.
 *
 * An event, a club and a business each bring their own colour, and the public
 * pages build a whole palette out of it — a gradient darkened towards navy, a
 * button lightened towards white, a translucent tint behind a chip. The design
 * expressed those as CSS `color-mix(in oklab, …)`.
 *
 * We do the mixing in PHP instead, for one reason: `color-mix()` needs Chrome
 * 111 / Safari 16.2, and an Android WebView older than that drops the whole
 * declaration — which on a gradient means NO BACKGROUND AT ALL, white text on
 * white. The public event page is also the member app's page (CLAUDE.md → the
 * APK mirrors the mobile web), so it has to render on whatever WebView the
 * phone happens to ship. A hex stop computed here works everywhere.
 *
 * Mixing is done in sRGB rather than oklab. For two stops of the SAME hue —
 * which is every use here — the difference is not visible in a gradient; it
 * would be, for mixes across hues, and this class should not be used for those.
 */
final class Palette
{
    /** The fallback everything falls back to: the platform's own purple. */
    public const DEFAULT = '#7c3aed';

    /**
     * A six-digit hex colour, or the fallback.
     *
     * Every colour reaching a `style` attribute goes through here first. It is
     * organiser-supplied text, and a `style` attribute is an injection sink —
     * the same guard the components apply (CLAUDE.md → Security, and the
     * `event-public-link` / `event-section-band` precedent).
     */
    public static function safe(?string $hex, string $fallback = self::DEFAULT): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? (string) $hex : $fallback;
    }

    /**
     * THE event band — the one background every header on an event's public
     * pages wears.
     *
     * ── Why it is a function and not a line of CSS in nine files ───────────
     * It was nine lines of CSS in nine files, and they drifted: the poster
     * carried the deep navy pair while the section pages still carried Design
     * Rule #6's `colour → colour+b0`, so walking from the poster into
     * "Manage" or "Divisions" changed the colour of the page you were
     * standing on. At the user's instruction (2026-09-05) every event header
     * follows the poster's dark band. One function, so they cannot part
     * company again (CLAUDE.md → Shared Stays Shared).
     *
     * ── Why the dark stop lands at 70% and not 100% ────────────────────────
     * A gradient traverses its BOX, so the same two stops read completely
     * differently depending on the shape of the band they are painted on: the
     * poster's tall header spends most of its height in the dark end, while a
     * short, page-wide band — Manage, an entry form — is nearly all the bright
     * start, which is exactly why those two pages looked like different
     * products. Landing the dark stop early and holding it makes the band read
     * the same on a 300px poster header and a 110px strip.
     *
     * Mixed in PHP rather than with `color-mix()`, because an Android WebView
     * older than Chrome 111 drops that declaration whole and would leave the
     * header with no background at all — and this is the member's APK.
     */
    /**
     * The band a PAGE of an event wears — the two families, chosen by surface.
     *
     * An event's screens come in two dressings and always have:
     *
     *   POSTER family  the event's colour taken DOWN towards navy
     *                  (`eventBand()`), worn by `/e/{uuid}` and its section
     *                  pages — a competition presented to a stranger.
     *   PAGE family    the colour merely lightened (`colour → colour+b0`),
     *                  Design Rule #6's standard hero band, worn by every
     *                  screen inside the platform.
     *
     * They were picked per-file, so the SAME event's draw was deep navy at
     * `/e/{uuid}/draw` and pale at `/me/events/{uuid}/brackets`. Once the
     * member pages started wearing the organiser's brand (2026-09-08) that
     * became the visible seam between an event's two faces — which is the one
     * thing that work exists to remove.
     *
     * So the choice is made HERE, from one question: is this the branded,
     * chrome-less surface? Pass `isset($shell)`. A type that has not opted into
     * the branded surface keeps the page band it has always had, unchanged.
     */
    public static function pageBand(?string $hex, bool $poster, string $angle = '150deg'): string
    {
        $c = self::safe($hex);

        return $poster
            ? self::eventBand($c, $angle)
            : 'linear-gradient('.$angle.', '.$c.', '.$c.'b0)';
    }

    public static function eventBand(?string $hex, string $angle = '155deg'): string
    {
        $c = self::safe($hex);

        return 'linear-gradient('.$angle.', '.self::shade($c, 82).' 0%, '.self::shade($c, 38).' 70%)';
    }

    /**
     * `$percent`% of $hex, the rest $with — the PHP form of
     * `color-mix(in srgb, $hex $percent%, $with)`.
     */
    public static function mix(?string $hex, string $with, int $percent): string
    {
        [$r1, $g1, $b1] = self::rgb(self::safe($hex));
        [$r2, $g2, $b2] = self::rgb(self::safe($with, '#000000'));

        $w = max(0, min(100, $percent)) / 100;

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 * $w + $r2 * (1 - $w)),
            (int) round($g1 * $w + $g2 * (1 - $w)),
            (int) round($b1 * $w + $b2 * (1 - $w)),
        );
    }

    /** Towards the deep navy the public pages sit on. */
    public static function shade(?string $hex, int $percent): string
    {
        return self::mix($hex, '#0b132b', $percent);
    }

    /** Towards white. */
    public static function tint(?string $hex, int $percent): string
    {
        return self::mix($hex, '#ffffff', $percent);
    }

    /**
     * The colour at an alpha, as an 8-digit hex.
     *
     * ⚠️ Hex-only by design. The alpha suffix trick (`{$color}1a`) is what the
     * project already uses, and it is INVALID on a non-hex colour — an
     * `hsl(...)` with `1a` glued on drops the declaration silently, which has
     * shipped as a bug before (CLAUDE.md → Design Rule #8).
     */
    public static function alpha(?string $hex, float $alpha): string
    {
        $a = (int) round(max(0, min(1, $alpha)) * 255);

        return self::safe($hex).sprintf('%02x', $a);
    }

    /** @return array{int,int,int} */
    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [
            (int) hexdec(substr($h, 0, 2)),
            (int) hexdec(substr($h, 2, 2)),
            (int) hexdec(substr($h, 4, 2)),
        ];
    }
}
