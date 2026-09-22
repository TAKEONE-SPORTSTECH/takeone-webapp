<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * THE one place a country becomes a flag.
 *
 * Flags are FILES on this box — `public/images/flags/{iso2}.svg`, the flag-icons
 * 4×3 set, vendored with its licence. Not emoji, not a CDN.
 *
 * ── Why, both halves ──────────────────────────────────────────────────────
 *
 * **Not emoji.** The platform built them from the ISO-2 code by codepoint
 * arithmetic (`🇧🇭`). That draws a flag on iOS, Android and macOS and draws the
 * LETTERS "BH" on Windows, which ships no flag glyphs. Most scoring tables and
 * most admin desks are Windows laptops, so the readers who most need to see a
 * country saw two capitals — which is exactly how it was reported, twice.
 *
 * **Not a CDN.** Six layouts loaded flag-icons from jsDelivr and fourteen files
 * fetched PNGs from flagcdn.com. A hall with no uplink, a blocked host or a bad
 * day at the CDN and every flag on every board is empty, with nothing in the
 * logs — and it is a third-party dependency on a critical path (Part 3 §15).
 *
 * ── The two renderings, one source ────────────────────────────────────────
 *
 *   `<x-flag :code="$iso" />`      Blade. Decides the size and the rounding.
 *   `class="fi fi-{iso2}"`         CSS, for markup JAVASCRIPT builds — an
 *                                  Alpine `x-for` cannot call a Blade
 *                                  component. Served by
 *                                  `public/images/flags/flag-icons.css`, which
 *                                  is GENERATED from the same SVGs.
 *
 * Same arrangement, and the same reason, as `<x-belt-icon>` / `<x-belt-icon-defs>`
 * and `<x-medal-icon>` / `<x-medal-icon-defs>`. Never a third way.
 */
final class Flag
{
    /** Where the set lives, relative to public/. */
    public const DIR = 'images/flags';

    /**
     * The URL of a country's flag, or null when we have no file for it.
     *
     * Accepts anything the platform stores — 'BH', 'bh', 'BHR', 'Bahrain' —
     * through `Countries::iso2()`. Null rather than a guess: a wrong flag is
     * worse than none, and every caller already has the code to fall back on.
     */
    public static function url(?string $country): ?string
    {
        $iso = self::iso2($country);

        return $iso ? asset(self::DIR.'/'.$iso.'.svg') : null;
    }

    /** The alpha-2 we actually hold a file for, lowercase, or null. */
    public static function iso2(?string $country): ?string
    {
        $raw = trim((string) $country);

        if ($raw === '') {
            return null;
        }

        $iso = Countries::iso2($raw);

        if (! $iso) {
            return null;
        }

        return is_file(public_path(self::DIR.'/'.$iso.'.svg')) ? $iso : null;
    }

    /** True when there is a file to draw. */
    public static function exists(?string $country): bool
    {
        return self::iso2($country) !== null;
    }

    /**
     * Every code a stored value might be, mapped to what a client needs to draw
     * it: `['BH' => ['bh', 'Bahrain'], 'BHR' => ['bh', 'Bahrain'], …]`.
     *
     * Handed to the page so the six admin screens that used to each fetch
     * `/data/countries.json` and build an emoji do neither — see
     * `partials/nationality-runtime`. Cached for a day; the country list does
     * not change on a deploy cadence.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function map(): array
    {
        return Cache::remember('flags.map', now()->addDay(), function () {
            $path = public_path('data/countries.json');

            if (! is_readable($path)) {
                return [];
            }

            $out = [];

            foreach (json_decode((string) file_get_contents($path), true) ?: [] as $row) {
                $iso2 = strtolower(trim((string) ($row['iso2'] ?? '')));
                $iso3 = strtoupper(trim((string) ($row['iso3'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));

                if ($iso2 === '' || $name === '' || ! is_file(public_path(self::DIR.'/'.$iso2.'.svg'))) {
                    continue;
                }

                $out[strtoupper($iso2)] = [$iso2, $name];

                if ($iso3 !== '') {
                    $out[$iso3] = [$iso2, $name];
                }
            }

            return $out;
        });
    }
}
