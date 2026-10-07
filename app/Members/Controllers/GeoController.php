<?php

namespace App\Members\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Which country a pair of coordinates is in — nothing else.
 *
 * Forms on this platform ask a lot of people for a country: an entrant, a
 * referee written down at the desk, a member filling their own profile. The
 * device usually knows the answer already, and typing it is the kind of work
 * software exists to remove.
 *
 * Deliberately narrow, because coordinates are personal data:
 *  - it answers with an ISO-2 code and NOTHING else — no address, no place
 *    name, no reverse-geocode payload passed through to the browser,
 *  - the code must be one the app's own country list carries, so a surprising
 *    answer from an outside service cannot become a value no form can show,
 *  - coordinates are ROUNDED to one decimal (~11 km) before they are used as a
 *    cache key or sent anywhere, which is plenty for a country and useless for
 *    following somebody around,
 *  - nothing is logged and nothing is stored against the user,
 *  - a failure is a null answer, never an error: the form simply stays empty,
 *    which is exactly what it did before this existed.
 */
class GeoController extends Controller
{
    /** How long one rounded coordinate's country is remembered. */
    private const TTL = 60 * 60 * 24 * 30;

    /**
     * Where a pasted Google Maps link points, so the map field's pin can follow
     * it. Answers coordinates and nothing else; a link that is not Google Maps,
     * or cannot be followed, is a null answer — the field just leaves the pin.
     * The following itself is App\Support\MapLink, which never leaves Google.
     */
    public function mapLink(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2000']]);
        $url = trim($data['url']);

        // Only a found pin is remembered: one timeout must not make a good link
        // read as "not found" for a month.
        $at = [];
        if (\App\Support\MapLink::isGoogle($url)) {
            $key = 'geo:maplink:'.sha1($url);
            $at = Cache::get($key) ?: (\App\Support\MapLink::resolve($url) ?? []);
            if ($at) {
                Cache::put($key, $at, self::TTL);
            }
        }

        return response()->json([
            'success' => true,
            'lat' => $at['lat'] ?? null,
            'lng' => $at['lng'] ?? null,
        ]);
    }

    public function country(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // ~11 km. A country is a very coarse question; this is the resolution
        // it deserves, and it makes the cache actually hit.
        $lat = round((float) $data['lat'], 1);
        $lng = round((float) $data['lng'], 1);

        $iso = Cache::remember("geo:country:{$lat},{$lng}", self::TTL, function () use ($lat, $lng) {
            return $this->lookup($lat, $lng) ?? '';
        });

        return response()->json([
            'success' => true,
            'country' => $iso !== '' ? $iso : null,
        ]);
    }

    /**
     * Ask the geocoder, and believe it only if the answer is a country this app
     * already knows how to display.
     */
    private function lookup(float $lat, float $lng): ?string
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

        return $this->known($code) ? $code : null;
    }

    /**
     * The app's own country list — the same file every country picker reads, so
     * a seeded value is always one the picker can show back.
     */
    private function known(string $iso): bool
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
