<?php

namespace App\DevBoard\Support;

use App\DevBoard\Services\NoteHtml;
use Illuminate\Support\Facades\Http;

/**
 * A TikTok share link, as a phone's share sheet hands it over
 * (`vm.tiktok.com/…`, `vt.tiktok.com/…`, `tiktok.com/t/…`): which video it is.
 *
 * Those links carry no video id until they are followed, and a browser cannot
 * follow them (no CORS), so the note editor asks the server. `resolve()`
 * follows them — but only ever to TikTok's own hosts, over https, a few hops at
 * most, HEAD only and never reading a body: a pasted link cannot make this
 * server fetch anything else (CLAUDE.md Part 3 — no SSRF through a
 * convenience). Same shape as App\Support\MapLink.
 */
final class VideoLink
{
    private const MAX_HOPS = 4;

    private const HOSTS = ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'];

    /** https, on a TikTok host, no user, password or port. */
    public static function isTikTok(?string $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2000) {
            return false;
        }
        $p = parse_url($url);

        return $p && strtolower($p['scheme'] ?? '') === 'https' && ! isset($p['user']) && ! isset($p['pass']) && ! isset($p['port'])
            && in_array(strtolower($p['host'] ?? ''), self::HOSTS, true);
    }

    /** `tiktok:{id}` read straight off a full TikTok video address, or null. */
    public static function fromAddress(?string $url): ?string
    {
        if (! self::isTikTok($url) || ! preg_match('#/(?:video|v|embed/v2|player/v1)/([0-9]{15,22})(?:[/?.\#]|$)#', (string) parse_url($url, PHP_URL_PATH).'/', $m)) {
            return null;
        }
        return NoteHtml::embed('tiktok', $m[1]);
    }

    /** Where a (possibly short) TikTok link points, as `tiktok:{id}`, or null. */
    public static function resolve(?string $url): ?string
    {
        $current = $url;
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            if (! self::isTikTok($current)) {
                return null;
            }
            if ($found = self::fromAddress($current)) {
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
                $next = 'https://'.parse_url($current, PHP_URL_HOST).$next;
            }
            $current = $next;
        }

        return null;
    }
}
