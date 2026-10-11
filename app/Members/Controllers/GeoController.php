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

        // The lookup, its rounding and its cache live in App\Support\GeoCountry,
        // shared with the scoring console's door.
        $iso = \App\Support\GeoCountry::forCoordinates((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'success' => true,
            'country' => $iso,
        ]);
    }

}
