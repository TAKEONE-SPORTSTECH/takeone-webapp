<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use Illuminate\Http\Request;

/**
 * Where BACK goes from an event's page — the place the reader came from.
 *
 * The event page's back control used to be a fixed address (My Events). A
 * member who opened an event from Explore, a club page or the feed tapped back
 * and landed somewhere they had never been, with no way to the place they
 * actually started (owner, 2026-10-04: "once I enter I can't go back to the
 * explore"). This remembers, per event and per session, the platform page the
 * reader arrived from, so back returns there — even after they have wandered
 * through the draw, the entry list and back again.
 *
 * Behind `events.return_to` (OFF by default, RULE #1). Off, `for()` answers
 * My Events exactly as before.
 *
 * SAFETY: the Referer is never followed as given. It is only remembered when it
 * is this host, a plain path, and not an auth page or a page of this same
 * event; the query is rebuilt from scalar values. The answer is therefore
 * always a page of this site the reader just had open.
 */
final class EventReturn
{
    private const SESSION = 'event_return';

    /** How many events' origins one session keeps. */
    private const KEEP = 20;

    /** Paths a reader is never sent back to. */
    private const NEVER = [
        '/login', '/logout', '/register', '/forgot-password', '/reset-password',
        '/email', '/two-factor', '/user/two-factor', '/sanctum', '/file', '/mcp',
    ];

    /**
     * Remember where this reader came from (when the Referer says somewhere
     * new) and answer where back goes.
     *
     * @return array{url: string, label: string}
     */
    public static function for(Request $request, ClubEvent $event): array
    {
        $default = ['url' => route('me.events'), 'label' => __('personal.event_show_events')];

        if (! config('events.return_to', false) || ! $request->hasSession()) {
            return $default;
        }

        $key = (string) $event->uuid;
        $stored = (array) $request->session()->get(self::SESSION, []);

        $came = self::origin($request, $key);
        if ($came !== null) {
            unset($stored[$key]);
            $stored[$key] = $came;
            $request->session()->put(self::SESSION, array_slice($stored, -self::KEEP, null, true));
        }

        $path = $stored[$key] ?? null;
        // Re-checked on the way out, and joined to THIS host by hand: url() would
        // pass a protocol-relative "//other.site" through as an absolute address.
        if (! is_string($path) || ! str_starts_with($path, '/') || str_contains($path, '//')) {
            return $default;
        }

        return ['url' => $request->getSchemeAndHttpHost().$path, 'label' => self::label($path)];
    }

    /** The Referer as a safe same-site path, or null when it is not one to remember. */
    private static function origin(Request $request, string $uuid): ?string
    {
        $ref = (string) $request->headers->get('referer');
        if ($ref === '' || strlen($ref) > 1000) {
            return null;
        }

        $host = parse_url($ref, PHP_URL_HOST);
        $path = parse_url($ref, PHP_URL_PATH) ?: '/';

        if ($host !== $request->getHost() || preg_match('#^/[A-Za-z0-9/_\-.]*$#', $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            return null;
        }

        // A page of THIS event (the poster, a section, the console, the entry
        // form) is not where the reader came from — keep what we have.
        if ($path === '/me/events/'.$uuid || str_starts_with($path, '/me/events/'.$uuid.'/')) {
            return null;
        }

        foreach (self::NEVER as $never) {
            if ($path === $never || str_starts_with($path, $never.'/')) {
                return null;
            }
        }

        parse_str((string) parse_url($ref, PHP_URL_QUERY), $q);
        $q = array_filter($q, fn ($v, $k) => is_string($k) && is_scalar($v) && strlen((string) $v) <= 100, ARRAY_FILTER_USE_BOTH);
        $query = $q ? '?'.http_build_query(array_slice($q, 0, 8, true)) : '';

        return $path.(strlen($query) <= 300 ? $query : '');
    }

    /** What the back control is called for that destination. */
    private static function label(string $path): string
    {
        $bare = strtok($path, '?');

        return match (true) {
            $bare === '/explore' || str_starts_with($bare, '/explore/') => __('nav.layouts_app_explore'),
            $bare === '/me/events' => __('personal.event_show_events'),
            default => __('shared.back'),
        };
    }
}
