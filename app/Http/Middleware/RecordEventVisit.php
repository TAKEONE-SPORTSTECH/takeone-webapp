<?php

namespace App\Http\Middleware;

use App\Events\Support\EventVisitors;
use App\Models\ClubEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts the people who open an event's public page.
 *
 * Applied to the poster route only — the event's front door. Deliberately not
 * to every URL under `/e/`: "how many people have seen this" is a question
 * about the page they were shown, and counting the entry form, the draw and
 * every section besides would inflate a per-person visit count without telling
 * an organiser anything they asked for.
 *
 * ── It runs AFTER the response ───────────────────────────────────────────────
 *
 * Nothing about this may delay the page or be able to break it. The recording
 * happens once the response exists, and the whole of it is inside a try/catch
 * one layer down (EventVisitors::record). A public poster the morning of a
 * competition renders whatever the counter does.
 *
 * ── The cookie ──────────────────────────────────────────────────────────────
 *
 * A first-party token this application mints, so a browser can be recognised
 * on its next visit without anything being taken from the visitor. Not
 * `httpOnly=false` — no script needs it; `SameSite=Lax`, so it survives
 * arriving from a shared link; and it is set on the RESPONSE here rather than
 * through the cookie queue so it lands even on a response the framework built
 * elsewhere.
 *
 * It is not a session and it is not authentication: it identifies a browser for
 * counting, nothing reads it for any decision, and losing it costs one
 * duplicate row in a statistics table.
 */
class RecordEventVisit
{
    /** A year, so a visitor who comes back next month is the same person. */
    private const COOKIE_DAYS = 365;

    public function __construct(private EventVisitors $visitors) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only a page that was actually SERVED. A 404 (an event that is not
        // public, or does not exist) is not a visit to anything, and counting
        // one would turn this table into a log of people probing addresses.
        if (! $response->isSuccessful()) {
            return $response;
        }

        $event = $request->route('event');

        if (! $event instanceof ClubEvent) {
            return $response;
        }

        $token = (string) $request->cookie(EventVisitors::COOKIE);
        $fresh = $token === '';

        if ($fresh) {
            $token = $this->visitors->newToken();

            $response->headers->setCookie(Cookie::create(
                name: EventVisitors::COOKIE,
                value: $token,
                expire: now()->addDays(self::COOKIE_DAYS)->getTimestamp(),
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }

        $this->visitors->record($event, $request, $token);

        return $response;
    }
}
