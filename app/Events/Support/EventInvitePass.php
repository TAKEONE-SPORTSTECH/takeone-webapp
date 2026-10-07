<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use Illuminate\Http\Request;

/**
 * The invitation pass: somebody who arrived through an event's INVITATION
 * link may read that event's page and its "Register your club" screens without
 * an account (owner, 2026-10-04: "if they came through an invitation link they
 * don't need to be logged in — clubs that are going to participate may not
 * have a record in our system").
 *
 * It lives in the reader's SESSION only — never in a URL, so it cannot be
 * forwarded by copying an address — and it is granted only by a door that has
 * already checked a real invitation (a federation's invitation link,
 * FederationAppController::publicPage). It is per event: a pass for one event
 * opens nothing on another.
 *
 * It widens READING, and only of: the event page (me.events.show), the club
 * entry steps (me.events.club-entry.*) and the page's language switch. The
 * participants, draw and results stay behind the organiser's public switch,
 * and every write keeps its own checks.
 */
final class EventInvitePass
{
    private const KEY = 'evpass';

    private const HOURS = 12;

    /** The routes a pass opens. */
    public const ROUTES = [
        'me.events.show',
        'me.events.club-entry.rules', 'me.events.club-entry.club', 'me.events.club-entry.club-athletes',
        'me.events.club-entry.club-review', 'me.events.club-entry.club-done', 'me.events.club-entry.club-entries',
        'events.public.language.prepare', 'events.public.language.status',
    ];

    public static function grant(Request $request, ClubEvent $event): void
    {
        if (! $request->hasSession()) {
            return;
        }
        $passes = array_filter((array) $request->session()->get(self::KEY, []), fn ($exp) => (int) $exp > time());
        $passes[(string) $event->uuid] = time() + self::HOURS * 3600;
        $request->session()->put(self::KEY, array_slice($passes, -20, null, true));
    }

    public static function has(Request $request, ClubEvent $event): bool
    {
        if (! $request->hasSession() || $event->is_archived) {
            return false;
        }
        $exp = (int) (((array) $request->session()->get(self::KEY, []))[(string) $event->uuid] ?? 0);

        return $exp > time();
    }

    /** Does the pass open THIS request (its route is one of ROUTES)? */
    public static function opens(Request $request, ClubEvent $event): bool
    {
        return in_array((string) $request->route()?->getName(), self::ROUTES, true) && self::has($request, $event);
    }
}
