<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Where this visitor appears to be, from their connection alone.
 *
 * Used to PRE-FILL a country field, never to decide anything. A guess that is
 * right most of the time saves a stranger a search through 196 countries on a
 * phone at a mat; a guess that is wrong costs them one tap, because the field
 * stays theirs to change. Nothing is validated, gated or priced on it.
 *
 * The signal is the CDN's, not a lookup we perform: Cloudflare stamps
 * `CF-IPCountry` on every request it proxies, which means no third-party
 * geo-IP service, no API key, no per-visit HTTP call, and — importantly — no
 * permission prompt. A browser geolocation prompt on an entry form asks
 * somebody to hand over their coordinates to answer a question they can answer
 * with one tap; the connection already told us, quietly and for free.
 *
 * Deliberately NOT a fallback chain onto a paid service: when the header is
 * absent the answer is "we do not know", the field opens empty, and the form
 * behaves exactly as it did before.
 */
class VisitorCountry
{
    /**
     * Headers the edge sets, most trusted first. Each is stamped by our own
     * proxy — a client can send whatever it likes, and it only ever changes
     * which country a picker OPENS on, so there is nothing to gain by lying.
     */
    private const HEADERS = [
        'CF-IPCountry',              // Cloudflare (what stage and production run behind)
        'CloudFront-Viewer-Country', // AWS CloudFront
        'X-Geo-Country',             // a generic reverse proxy convention
    ];

    /**
     * Values that mean "no answer" rather than a place: Cloudflare uses XX for
     * unknown, T1 for Tor exit nodes, and reserves the ISO user-assigned range.
     */
    private const NOT_A_PLACE = ['XX', 'T1', 'ZZ', 'AP', 'EU'];

    /** ISO-3166-1 alpha-2, uppercase, or null when the connection says nothing. */
    public static function guess(Request $request): ?string
    {
        foreach (self::HEADERS as $header) {
            $value = strtoupper(trim((string) $request->header($header, '')));

            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, self::NOT_A_PLACE, true)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The dial code for an ISO-2, so a guessed country can also open the phone
     * field on the right code.
     *
     * Read from the same `public/data/countries.json` the pickers themselves
     * fetch — one list, so the server and the browser can never disagree about
     * which code belongs to which country.
     */
    public static function dialCode(?string $iso2): ?string
    {
        if ($iso2 === null || ! preg_match('/^[A-Za-z]{2}$/', $iso2)) {
            return null;
        }

        return self::dialCodes()[strtoupper($iso2)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function dialCodes(): array
    {
        // Cached: this is read on a public, throttled page that a whole hall
        // may open at once, and the file never changes between deploys.
        return Cache::remember('visitor-country:dial-codes', now()->addDay(), function (): array {
            $path = public_path('data/countries.json');

            if (! is_file($path)) {
                return [];
            }

            $rows = json_decode((string) file_get_contents($path), true);

            if (! is_array($rows)) {
                return [];
            }

            $map = [];

            foreach ($rows as $row) {
                $iso = strtoupper((string) ($row['iso2'] ?? ''));
                $code = (string) ($row['call_code'] ?? '');

                if ($iso !== '' && preg_match('/^\+[0-9]{1,6}$/', $code)) {
                    $map[$iso] = $code;
                }
            }

            return $map;
        });
    }
}
