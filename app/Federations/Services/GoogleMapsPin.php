<?php

namespace App\Federations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns the Google Maps link an organiser pasted into a pin, so the invitation
 * can draw its own map when no pin was dropped by hand.
 *
 * Links come in many shapes: a full maps URL carrying "@lat,lng" or
 * "!3d…!4d…", a short link (maps.app.goo.gl, share.google) that redirects to
 * one of those — or to a plain search for the place's name. For the last kind
 * the coordinates are read from Google's own embed page for that search.
 *
 * Only Google hosts over https are ever requested (every redirect hop is
 * checked before it is followed), so an organiser's link can never make this
 * server fetch anything else. Answers are cached per link — a miss for a day,
 * a hit for a month — so a public page never waits on Google twice.
 */
class GoogleMapsPin
{
    private const HOSTS = '#^(?:[a-z0-9-]+\.)*(?:google\.com|google\.com\.[a-z]{2}|google\.[a-z]{2,3}|goo\.gl|share\.google|g\.co)$#i';

    /** @return array{0: float, 1: float}|null */
    public function resolve(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || ! $this->allowed($url)) {
            return null;
        }
        $key = 'fed-gmaps-pin:'.sha1($url);
        $hit = Cache::get($key);
        if ($hit !== null) {
            return $hit === false ? null : $hit;
        }
        try {
            $pin = $this->lookup($url);
        } catch (Throwable) {
            $pin = null;
        }
        Cache::put($key, $pin ?? false, $pin ? now()->addDays(30) : now()->addDay());

        return $pin;
    }

    private function lookup(string $url): ?array
    {
        // Follow the short link by hand, one checked hop at a time.
        for ($hop = 0; $hop < 5; $hop++) {
            if ($pin = $this->fromUrl($url)) {
                return $pin;
            }
            $res = $this->get($url);
            $next = $res->header('Location');
            if (! $res->redirect() || ! $next) {
                break;
            }
            $next = $this->absolute($next, $url);
            if (! $next || ! $this->allowed($next)) {
                return null;
            }
            $url = $next;
        }
        if ($pin = $this->fromUrl($url)) {
            return $pin;
        }

        // A search for the place's name: ask the embed page where it is.
        $q = $this->placeQuery($url);
        if ($q === null) {
            return null;
        }
        $body = substr((string) $this->get('https://www.google.com/maps/embed?origin=mfe&pb=!1m2!2m1!1s'.rawurlencode($q))->body(), 0, 2_000_000);
        if (preg_match('#\["0x[0-9a-f]+:0x[0-9a-f]+","[^"]*",\[(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)\]#i', $body, $m)) {
            return $this->pin($m[1], $m[2]);
        }

        return null;
    }

    /** Coordinates written into the address itself. */
    private function fromUrl(string $url): ?array
    {
        $u = rawurldecode($url);
        foreach ([
            '#!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)#',
            '#@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)#',
            '#[?&](?:q|query|ll|center|destination)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)#',
        ] as $re) {
            if (preg_match($re, $u, $m)) {
                return $this->pin($m[1], $m[2]);
            }
        }

        return null;
    }

    private function placeQuery(string $url): ?string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $qs);
        $q = is_string($qs['q'] ?? null) ? $qs['q'] : (is_string($qs['query'] ?? null) ? $qs['query'] : null);
        if ($q === null && preg_match('#/maps/place/([^/@]+)#', $parts['path'] ?? '', $m)) {
            $q = str_replace('+', ' ', rawurldecode($m[1]));
        }
        $q = $q === null ? '' : trim(mb_substr($q, 0, 200));

        return $q === '' ? null : $q;
    }

    private function get(string $url): \Illuminate\Http\Client\Response
    {
        return Http::withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; TakeOne/1.0)', 'Accept-Language' => 'en'])
            ->timeout(4)->connectTimeout(3)->get($url);
    }

    private function allowed(string $url): bool
    {
        $p = parse_url($url);

        return ($p['scheme'] ?? '') === 'https' && isset($p['host']) && ! isset($p['user']) && ! isset($p['port'])
            && preg_match(self::HOSTS, $p['host']) === 1;
    }

    private function absolute(string $loc, string $base): ?string
    {
        if (preg_match('#^https?://#i', $loc)) {
            return $loc;
        }
        if (str_starts_with($loc, '/') && ! str_starts_with($loc, '//')) {
            $b = parse_url($base);

            return 'https://'.$b['host'].$loc;
        }

        return null;
    }

    private function pin(string $lat, string $lng): ?array
    {
        $a = (float) $lat;
        $b = (float) $lng;

        return abs($a) <= 90 && abs($b) <= 180 && ($a != 0.0 || $b != 0.0) ? [round($a, 6), round($b, 6)] : null;
    }
}
