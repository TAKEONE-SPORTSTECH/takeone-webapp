<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * A Google Maps link, as somebody pasted it: is it one, and where does it
 * point?
 *
 * Used by the map field's "Google Maps link" box (`<x-fields.location-map
 * maps-url-name=…>`) so the OpenStreetMap pin can FOLLOW a pasted link, and by
 * the forms that store the link, to accept only a real Google Maps address.
 *
 * Share links are usually short (`maps.app.goo.gl/…`) and carry no
 * coordinates until they are followed. `resolve()` follows them — but only
 * ever to Google's own hosts, over https, a few hops at most, never reading a
 * body: a link cannot be used to make this server fetch anything else
 * (CLAUDE.md Part 3 — no SSRF through a convenience).
 */
final class MapLink
{
    private const MAX_HOPS = 4;

    /**
     * https, and a Google Maps host: google.<tld>, maps.google.<tld>, goo.gl,
     * maps.app.goo.gl, and share.google (Google's newer share links — they
     * lead to a search page, not a map, so they store fine but carry no pin).
     */
    public static function isGoogle(?string $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2000) {
            return false;
        }
        $p = parse_url($url);
        if (! $p || strtolower($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) {
            return false;
        }
        $host = strtolower($p['host'] ?? '');

        return in_array($host, ['goo.gl', 'maps.app.goo.gl', 'share.google'], true)
            || (bool) preg_match('/^(www\.|maps\.)?google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host);
    }

    /**
     * The coordinates written in a full Google Maps link, or null. Reads, in
     * order of how exact they are: the place pin (`!3d…!4d…`), the map centre
     * (`@lat,lng`), then a `q=` / `query=` / `ll=` / `destination=` pair.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function coordinates(?string $url): ?array
    {
        if (! self::isGoogle($url)) {
            return null;
        }
        $u = rawurldecode($url);
        $pairs = [
            '/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
            '/@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
            '/[?&](?:q|query|ll|destination|center)=(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/',
        ];
        foreach ($pairs as $re) {
            if (preg_match($re, $u, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ! ($lat == 0.0 && $lng == 0.0)) {
                    return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
                }
            }
        }

        return null;
    }

    /**
     * Where a (possibly short) Google Maps link points. Follows redirects by
     * hand so every hop is checked against isGoogle() before it is requested.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function resolve(?string $url): ?array
    {
        $current = $url;
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            if (! self::isGoogle($current)) {
                return null;
            }
            if ($found = self::coordinates($current)) {
                return $found;
            }
            try {
                $res = Http::timeout(5)->connectTimeout(3)->withoutRedirecting()
                    ->withHeaders(['User-Agent' => config('app.name').' ('.config('app.url').')'])
                    ->head($current);
            } catch (\Throwable) {
                return null;
            }
            $next = $res->header('Location');
            if (! $res->redirect() || ! is_string($next) || $next === '') {
                return null;
            }
            // A relative Location stays on the host it came from.
            if (str_starts_with($next, '/')) {
                $p = parse_url($current);
                $next = 'https://'.$p['host'].$next;
            }
            $current = $next;
        }

        return null;
    }
}
