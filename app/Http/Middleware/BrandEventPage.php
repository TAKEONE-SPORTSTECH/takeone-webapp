<?php

namespace App\Http\Middleware;

use App\Events\EventTypeRegistry;
use App\Events\Support\PublicEventSkin;
use App\Models\ClubEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform's own event pages, wearing the ORGANISER's brand.
 *
 * `/e/{uuid}` has always been a standalone app: a stranger sent a competition
 * sees the club's mark on the event's colour, and nothing that names the
 * platform serving it. But a member who reached the SAME event from
 * `/me/events` was handed the platform instead — top bar, side drawer, bottom
 * tabs, platform footer — so one competition had two faces depending on which
 * door was used. Asked for on 2026-09-07: an event is a place you ENTER, and
 * the brand inside it is the organiser's for everybody.
 *
 * So this middleware dresses the member event pages the way
 * App\Http\Middleware\SealEventPage dresses the mirrored ones, reusing the same
 * shell, the same skin and the same partials — one design, not a second copy of
 * it (Shared Stays Shared).
 *
 * What it does, and the whole of it:
 *   1. Shares `$shell` = `entry.shell`, `$contentSection` and `$skin`, so a
 *      blade written for the member shell renders inside the event's instead.
 *   2. Serves the MOBILE blade at every width, exactly as the public surface
 *      does — the event is one app read standing at a mat, not a desktop
 *      layout with a phone variant.
 *
 * What it deliberately does NOT do:
 *   · **Gate anything.** Unlike SealEventPage it adds no check of its own: the
 *     route keeps its `auth`/`verified`/`two-factor` stack and the controller
 *     re-runs the same authorization. Nothing becomes reachable that was not
 *     reachable before — only the dressing changes.
 *   · **Rewrite addresses.** The sealed mirror has to move every link into
 *     `/e/{uuid}/admin/…` because it is a second address space. Here the pages
 *     keep their own `/me/events/…` addresses, every one of which is branded
 *     by this same middleware, so a link between them already lands inside the
 *     event. Rewriting would push a reader into the sealed space, which 404s
 *     while the organiser's public switch is off.
 *
 * ⚠️ It NEVER aborts and never throws. An unresolvable event, an event type
 * that has not opted in, an already-sealed request, or the kill switch turned
 * off all mean one thing: pass the request through completely untouched, and
 * the page renders exactly as it shipped (RULE #1).
 */
class BrandEventPage
{
    public function __construct(
        private EventTypeRegistry $types,
        private PublicEventSkin $skin,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->event($request);

        if ($event === null) {
            return $next($request);
        }

        /*
         * ⚠️ Shared view data is PROCESS-wide, not request-wide.
         *
         * Under php-fpm each request is its own process, so sharing and walking
         * away is harmless. It is not harmless under Octane, in a queue worker
         * rendering a view, or in a test harness making several calls — there
         * the NEXT request would inherit `$shell` and render a platform page in
         * some other event's skin. So the keys are put back the way they were
         * found, in a finally: this middleware's dressing lives for this
         * request and no longer. (The same note, and the same fix, as
         * SealEventPage.)
         */
        $shared = [
            'shell' => View::shared('shell'),
            'contentSection' => View::shared('contentSection'),
            'skin' => View::shared('skin'),
            'sealed' => View::shared('sealed'),
        ];

        View::share('shell', 'entry.shell');
        View::share('contentSection', 'personal-content');
        View::share('skin', $this->skin->payload($event));

        /*
         * NOT the sealed space. The dressing is the same; the addresses are
         * not. These pages keep their own `/me/events/…` URLs — every one of
         * which this middleware brands as well, so a link from here already
         * lands inside the event — while `/e/{uuid}/admin/…` exists only while
         * the organiser's public switch is on and would 404 without it.
         *
         * Shared explicitly rather than left unset so the value is never
         * inherited from whatever ran last (see the process-wide note above).
         */
        View::share('sealed', false);

        // Mobile at every width — the event is one app, on a phone column when
        // it is opened on a laptop (entry/partials/skin-style caps the column).
        $wasMobile = $request->attributes->get('is_mobile');
        $request->attributes->set('is_mobile', true);

        try {
            return $next($request);
        } finally {
            foreach ($shared as $key => $was) {
                View::share($key, $was);
            }

            $request->attributes->set('is_mobile', $wasMobile);
        }
    }

    /**
     * The event this request is about, or null when this request is not one
     * this middleware has anything to say about.
     *
     * Four ways to be nobody's business, all of them silent:
     *   · the kill switch is off;
     *   · the route carries no `{event}` at all (`/me/events`, `/me/schedule`);
     *   · SealEventPage is already dressing this request — it is the mirrored
     *     space, it does more than this does, and two dressers on one request
     *     is how the shell ends up restored in the wrong order;
     *   · the event's package has not opted in (Sparring, Open Mat, generic).
     */
    private function event(Request $request): ?ClubEvent
    {
        if (! config('events.branded_surface', true)) {
            return null;
        }

        if ($request->attributes->get('sealed_event') instanceof ClubEvent) {
            return null;
        }

        $event = $request->route()?->parameter('event');

        /*
         * The bound model is not guaranteed even where the URI says
         * `{event:uuid}`: Laravel substitutes an implicit binding only when
         * something in the chain type-hints ClubEvent, and a handful of these
         * actions take the uuid as a string. Resolve it here when the binding
         * did not — and an unknown uuid is simply not our business, never a
         * 404 raised from the dressing.
         */
        if (! $event instanceof ClubEvent) {
            if (! is_string($event) || $event === '') {
                return null;
            }

            $event = ClubEvent::where('uuid', $event)->first();
        }

        if (! $event instanceof ClubEvent) {
            return null;
        }

        return $this->types->for($event)->brandedSurface() ? $event : null;
    }
}
