<?php

namespace App\Events\Support;

use App\Models\ClubEvent;

/**
 * The sealed public event surface: what it looks like, and where its edges are.
 *
 * `/e/{uuid}` is a standalone app. Somebody who opens a shared competition link
 * — a spectator, an entrant, or the organiser who signs in to run it — must
 * never be handed off to the platform behind it. There is no back button on a
 * home-screen app and no address bar in an installed one, so a single link to
 * /explore is not a cosmetic slip: it is the exit from the product the reader
 * thinks they are in, with no way back.
 *
 * This class owns the two halves of keeping that promise:
 *
 *   1. **The skin** — the small payload the sealed shell paints with: the
 *      event's title, colour, host club and icons. Deliberately independent of
 *      whatever `$e` a platform controller happens to build, because the
 *      screens being re-served were written for a different payload.
 *
 *   2. **The edges** — the URL arithmetic. Every management screen already
 *      exists under `/me/events/{uuid}/…`; the mirror re-serves the same
 *      actions under `/e/{uuid}/admin/…`, and `rewrite()` moves any address
 *      that page produced into the sealed space.
 *
 * @see \App\Http\Middleware\SealEventPage  the middleware that applies all this
 */
class PublicEventSkin
{
    /** The route space every management screen lives in on the platform. */
    public const PLATFORM_PREFIX = 'me/events/{event:uuid}';

    /** The same space, sealed inside the event. */
    public const SEALED_PREFIX = 'e/{event:uuid}/admin';

    /**
     * What the sealed shell needs in order to look like this event.
     *
     * Never the platform's name, mark or colour — see entry/layout for why
     * that is a rule rather than a preference.
     *
     * @return array{title: string, host: ?string, host_logo: ?string, color: string, icons: array<int, string>, home: string, console: string}
     */
    public function payload(ClubEvent $event): array
    {
        $brand = app(PublicBrand::class);

        return [
            'title' => (string) $event->title,
            'host' => $event->tenant?->club_name,
            'host_logo' => $event->tenant?->logo ? file_url($event->tenant->logo) : null,
            'color' => app(PublicEvent::class)->color($event),
            'icons' => $brand->iconUrls($event),

            // Where "out" goes inside the sealed surface: the event's own page,
            // never a platform home screen.
            'home' => route('events.public', ['event' => $event->uuid]),
            'console' => $this->sealed(route('me.events.manage', ['event' => $event->uuid]), $event),
        ];
    }

    /**
     * Is this request inside the sealed surface at all?
     */
    public function seals(\Illuminate\Http\Request $request): bool
    {
        return $request->attributes->get('sealed_event') instanceof ClubEvent;
    }

    /**
     * Move one address into the sealed space.
     *
     * Only THIS event's own management space is translated — a link to another
     * event's platform page is not something to quietly rewrite into this
     * event's, and a link to a file, an asset or the locale switch is not
     * leaving the app at all.
     *
     * Everything else that would navigate away is answered by `escapes()`.
     */
    public function sealed(string $url, ClubEvent $event): string
    {
        $from = url('/me/events/'.$event->uuid);
        $to = url('/e/'.$event->uuid.'/admin');

        /* ===== The EVENT PAGE is the poster =====
         *
         * `/me/events/{uuid}` is the platform's member event page. Mirrored, it
         * became `/e/{uuid}/admin` — a second event page inside the app, with a
         * header from a different blade family: four round controls, a `sliders`
         * icon where the poster has a gear, a different gradient. Two headers
         * for one thing, and the reader had no idea where the second came from.
         *
         * Inside this app the event page is the POSTER. That is what was
         * shared, it is the app's root, and it already has the header. So the
         * root of the member space maps there and the mirrored duplicate stops
         * being linked to at all. Anything DEEPER (people, verify, the draw)
         * still maps into /admin — those screens have no public counterpart.
         */
        if ($url === $from || str_starts_with($url, $from.'?') || str_starts_with($url, $from.'#')) {
            return route('events.public', ['event' => $event->uuid]).substr($url, strlen($from));
        }

        if (str_starts_with($url, $from.'/')) {
            return $to.substr($url, strlen($from));
        }

        // The member's event LIST is a platform destination with no counterpart
        // here; inside the event, "back" is the event.
        $list = url('/me/events');
        if ($url === $list || str_starts_with($url, $list.'?')) {
            return route('events.public', ['event' => $event->uuid]);
        }

        // A person's public profile, re-served in the skin so tapping a name on
        // the entry list does not drop the reader onto the platform.
        $people = url('/people/');
        if (str_starts_with($url, $people)) {
            return $to.'/person/'.substr($url, strlen($people));
        }

        return $url;
    }

    /**
     * Would following this address leave the sealed surface?
     *
     * Used on REDIRECTS only. A 403 handler that sends a signed-in visitor to
     * `/`, an unverified address bounced to the verification notice, a
     * controller that redirects to the member console — each is correct on the
     * platform and each is the exit this surface must not have.
     */
    public function escapes(string $url, ClubEvent $event): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $host = parse_url($url, PHP_URL_HOST);

        // Somewhere else entirely — a payment provider, a federation site. Not
        // ours to seal, and rewriting it would break a legitimate hand-off.
        if ($host !== null && $host !== request()->getHost()) {
            return false;
        }

        // Inside the event, or one of the doors the sealed pages legitimately
        // use without navigating away from the app.
        foreach (['/e/'.$event->uuid, '/file/', '/qr/'] as $stay) {
            if (str_starts_with($path, $stay)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rewrite every address in a rendered page into the sealed space.
     *
     * A body pass rather than a per-link edit in a dozen views, and
     * deliberately so: these screens build URLs in `href`s, in form actions,
     * in `fetch()` calls and in `@js(route(…))` blobs, and one of those was
     * always going to be missed. The substitution is a fixed, fully-qualified
     * prefix carrying this event's own uuid, so it cannot match anything it was
     * not meant to.
     */
    public function rewriteBody(string $html, ClubEvent $event): string
    {
        $uuid = $event->uuid;

        $poster = route('events.public', ['event' => $uuid]);

        $map = [
            /* The event page ITSELF → the poster (see sealed()). Quoted, so it
               only matches the whole address and never eats the prefix of a
               deeper one — str_replace works left to right and these have to
               win before the prefix rules below. */
            url('/me/events/'.$uuid).'"' => $poster.'"',
            url('/me/events/'.$uuid)."'" => $poster."'",
            '"/me/events/'.$uuid.'"' => '"'.$poster.'"',
            "'/me/events/".$uuid."'" => "'".$poster."'",

            // Everything deeper: the mirrored management screens.
            url('/me/events/'.$uuid) => url('/e/'.$uuid.'/admin'),
            '/me/events/'.$uuid => '/e/'.$uuid.'/admin',

            // The member event list → the event itself.
            url('/me/events') => route('events.public', ['event' => $uuid]),
            '"/me/events"' => '"'.route('events.public', ['event' => $uuid]).'"',

            // Public profiles, re-served in the skin.
            url('/people/') => url('/e/'.$uuid.'/admin/person/'),
            '"/people/' => '"/e/'.$uuid.'/admin/person/',

            // Platform destinations that reach the page inside an onclick or a
            // JS string, where the anchor pass cannot see them. Narrow and
            // named — never a blanket "/me/* → here", which would rewrite the
            // endpoints these pages legitimately fetch.
            url('/me/people') => route('events.public', ['event' => $uuid]),
            "'".url('/me/people')."'" => "'".route('events.public', ['event' => $uuid])."'",

            // The one stray platform link on the event form. BOTH shapes: the
            // form renders it absolutely (url('/openmat')), so matching only
            // the quoted relative form missed it entirely and the link merely
            // opened in a new tab.
            url('/openmat') => route('events.public', ['event' => $uuid]),
            '"/openmat"' => '"'.route('events.public', ['event' => $uuid]).'"',
        ];

        /*
         * ⚠️ THE SAME MAP AGAIN, WITH THE SLASHES ESCAPED.
         *
         * `@js(route(…))` and `json_encode` write a URL as
         * `http:\/\/host\/me\/events\/{uuid}\/checklist` — Laravel escapes
         * forward slashes by default — and a fixed-string map keyed on the
         * plain form cannot see a single one of them. Sixteen platform
         * addresses were surviving inside the sealed console on one event:
         * the checklist, the cover, the documents, the cameras, the draw
         * reveal, the public-page toggle and the entry accept/decline calls.
         *
         * The middleware's own note above predicted exactly this — "these
         * screens build URLs in hrefs, form actions, fetch() calls and
         * @js(route(…)) blobs, and one of those was always going to be missed".
         * It was the @js one. Found by a navigation audit, 2026-09-08.
         *
         * Derived from `$map` rather than written out a second time, so the two
         * can never drift: a rule added above is escaped here automatically.
         * Applied FIRST, because escaping is unambiguous and the plain pass
         * would otherwise leave the escaped copies behind untouched.
         */
        $escaped = [];

        foreach ($map as $from => $to) {
            $ef = str_replace('/', '\\/', $from);

            if ($ef !== $from) {
                $escaped[$ef] = str_replace('/', '\\/', $to);
            }
        }

        $html = str_replace(array_keys($escaped), array_values($escaped), $html);

        $html = str_replace(array_keys($map), array_values($map), $html);

        /*
         * The same space, built in script at runtime:
         *
         *     fetch(`/me/events/${config.event}/competitors/${id}/photo`)
         *
         * A fixed-prefix swap cannot see those because the uuid is not in the
         * source. The shape is, so it is rewritten by shape — the expression
         * inside ${…} is carried across untouched and only the route around it
         * moves.
         */
        $html = preg_replace(
            '#/me/events/\$\{([^{}]{1,200})\}#',
            '/e/${$1}/admin',
            $html
        );

        return $this->openEscapesInATab($html, $event);
    }

    /**
     * Anything that still leads out of the event opens BESIDE it, not instead
     * of it.
     *
     * A handful of honest destinations have no counterpart inside a
     * competition: a club's own public page, the scoring table, a federation's
     * rulebook. Mirroring a whole club microsite into an event's skin would be
     * a second product; making the link dead would be a dead end. So the link
     * keeps working and the event app stays exactly where it was — the reader
     * comes back by closing a tab, which is the one navigation an installed
     * home-screen app can always do.
     *
     * `rel=noopener` on every one: the opened document never gets a handle on
     * this one.
     */
    private function openEscapesInATab(string $html, ClubEvent $event): string
    {
        return (string) preg_replace_callback('#<a\b([^>]*)>#i', function (array $m) use ($event) {
            $attrs = $m[1];

            // Already opens elsewhere, or has no static address (an Alpine
            // `:href` is resolved in the browser and cannot be judged here).
            if (preg_match('#\btarget\s*=#i', $attrs) || ! preg_match('#\shref="([^"]*)"#i', $attrs, $href)) {
                return $m[0];
            }

            $url = trim($href[1]);

            if ($url === '' || str_starts_with($url, '#')
                || preg_match('#^(mailto:|tel:|javascript:|data:|blob:)#i', $url)) {
                return $m[0];
            }

            if (! $this->escapes($url, $event)) {
                return $m[0];
            }

            return '<a'.$attrs.' target="_blank" rel="noopener">';
        }, $html);
    }
}
