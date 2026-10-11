<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Where a coordinate is, as a COUNTRY CODE and nothing else.
 *
 * The one lookup behind every "seed the country from where the device is":
 * the member pickers' door (`me.geo.country`) and the Karate scoring console's
 * own door, which a paired tablet with no session can reach. Moved here
 * unchanged from the Members module's geo door on 2026-10-09 — same rounding,
 * same cache key, same geocoder, same "only a country this app can show" rule
 * — so the two doors cannot drift.
 */
class GeoCountry
{
    /** How long one rounded coordinate's country is remembered. */
    private const TTL = 60 * 60 * 24 * 30;

    /** An upper-case ISO-3166 alpha-2 this app knows, or null. */
    public static function forCoordinates(float $lat, float $lng): ?string
    {
        // ~11 km. A country is a very coarse question; this is the resolution
        // it deserves, and it makes the cache actually hit.
        $lat = round($lat, 1);
        $lng = round($lng, 1);

        $iso = Cache::remember("geo:country:{$lat},{$lng}", self::TTL, function () use ($lat, $lng) {
            return self::lookup($lat, $lng) ?? '';
        });

        return $iso !== '' ? $iso : null;
    }

    /**
     * Ask the geocoder, and believe it only if the answer is a country this app
     * already knows how to display.
     */
    private static function lookup(float $lat, float $lng): ?string
    {
        try {
            $res = Http::timeout(4)
                ->withHeaders(['User-Agent' => config('app.name').' ('.config('app.url').')'])
                ->get(config('services.geocoder.reverse', 'https://nominatim.openstreetmap.org/reverse'), [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    // Country level is all that is wanted; asking for less
                    // detail means a smaller answer and a cheaper request.
                    'zoom' => 3,
                    'addressdetails' => 1,
                ]);

            if (! $res->ok()) {
                return null;
            }

            $code = strtoupper((string) ($res->json('address.country_code') ?? ''));
        } catch (\Throwable) {
            // The geocoder being down is not this request's problem.
            return null;
        }

        return self::known($code) ? $code : null;
    }

    /**
     * The app's own country list — the same file every country picker reads, so
     * a seeded value is always one the picker can show back.
     */
    private static function known(string $iso): bool
    {
        if (! preg_match('/^[A-Z]{2}$/', $iso)) {
            return false;
        }

        $codes = Cache::remember('geo:country-codes', 3600, function () {
            $path = public_path('data/countries.json');

            if (! is_file($path)) {
                return [];
            }

            return collect(json_decode((string) file_get_contents($path), true) ?: [])
                ->pluck('iso2')->filter()->map('strtoupper')->all();
        });

        return in_array($iso, $codes, true);
    }
}
