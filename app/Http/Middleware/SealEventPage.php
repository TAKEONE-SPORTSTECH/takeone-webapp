<?php

namespace App\Http\Middleware;

use App\Events\Support\PublicEvent;
use App\Events\Support\PublicEventSkin;
use App\Models\ClubEvent;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the public event surface sealed.
 *
 * Applied to the mirrored management routes under `/e/{uuid}/admin/…` (see
 * App\Events\Support\SealedEventRoutes), it does four things and nothing else:
 *
 *   1. **Gates the surface.** The mirror exists only while the organiser's own
 *      public-page switch is on. Anything else 404s — the same 404 an unknown
 *      uuid gets, so the mirror cannot be used to discover events.
 *
 *   2. **Dresses the page.** Shares `$shell`, `$contentSection` and `$skin`, so
 *      a screen written for the member shell renders inside the event's instead.
 *      Nothing is shared outside these routes, so every platform page behaves
 *      exactly as it always did.
 *
 *   3. **Serves the MOBILE blade at every width**, exactly as the public poster
 *      does — asked for explicitly, and it covers the management screens too:
 *      every page under /e/{uuid} is one app, and a competition is run standing
 *      at a mat with a phone. A laptop gets the same app as a column on the
 *      ground (entry/partials/skin-style), never a desktop layout. Do not
 *      "improve" this into device detection; it was tried and reverted.
 *
 *   4. **Closes the exits.** Every redirect that would leave the event is
 *      turned into one that stays, and every address in the rendered page is
 *      moved into the sealed space. This is the whole point of the middleware:
 *      the screens being re-served were written for a product with a bottom
 *      tab bar, and any one of their links, form actions or fetch() URLs would
 *      otherwise drop the reader onto a platform they never asked to see.
 *
 * ⚠️ It grants NOTHING. The mirrored routes keep the exact middleware stack
 * they carry on the platform — auth, verified, two-factor, the same throttles —
 * and the same controller re-runs the same authorization. This middleware only
 * decides where a REFUSAL lands, never whether it happens.
 */
class SealEventPage
{
    public function __construct(private PublicEvent $publisher, private PublicEventSkin $skin) {}

    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        /*
         * ⚠️ The bound model is NOT guaranteed, even though the URI says
         * `{event:uuid}`.
         *
         * Laravel substitutes an implicit binding only when something in the
         * chain is type-hinted `ClubEvent`. Most mirrored routes point at a
         * controller action that takes one — but not all of them do (the
         * sealed person profile hands off to PeopleController::show(Request,
         * string $uuid)), and for those `route('event')` is still the raw uuid
         * STRING. Aborting on that made every athlete's name on the entry list
         * bounce the organiser back to the event home, which is the exact
         * opposite of what the mirror is for.
         *
         * So resolve it here when the binding did not: same key, same lookup,
         * and a genuinely unknown uuid still 404s.
         */
        if (! $event instanceof ClubEvent) {
            $event = ClubEvent::where('uuid', (string) $event)->first();
        }

        if (! $event instanceof ClubEvent) {
            abort(404);
        }

        abort_unless($this->publisher->isPublic($event), 404);

        $request->attributes->set('sealed_event', $event);

        // Mobile at every width — see (3) above.
        $request->attributes->set('is_mobile', true);

        $skin = $this->skin->payload($event);

        /*
         * ⚠️ Shared view data is PROCESS-wide, not request-wide.
         *
         * Under php-fpm each request is its own process, so sharing and walking
         * away is harmless there. It is not harmless anywhere a process serves
         * more than one request — Octane, a queue worker rendering a view, or a
         * test/CLI harness making several calls — and there the next request
         * would inherit `$shell` and render a PLATFORM page in the event's
         * shell. Caught exactly that way while verifying the header fix.
         *
         * So the keys are put back the way they were found, in a finally: this
         * middleware's dressing lives for this request and no longer.
         */
        $shared = [
            'shell' => View::shared('shell'),
            'contentSection' => View::shared('contentSection'),
            'skin' => View::shared('skin'),
        ];

        View::share('shell', 'entry.shell');
        View::share('contentSection', 'personal-content');
        View::share('skin', $skin);

        try {
            $response = $next($request);
        } finally {
            foreach ($shared as $key => $was) {
                View::share($key, $was);
            }
        }

        if ($response instanceof RedirectResponse || $response instanceof SymfonyRedirect) {
            return $this->keepInside($response, $event, $skin);
        }

        /*
         * A JSON `redirect` is a navigation instruction, so it is sealed too.
         *
         * The write endpoints on these screens answer AJAX and hand the address
         * to go to next back in the payload — `{"success":true,"redirect":…}`.
         * That string never went through the body pass (it is not in any HTML),
         * so saving an edit inside the event app answered with the PLATFORM
         * event page and the browser walked straight out of the app, header,
         * footer and all (reported 2026-09-04).
         */
        return $this->sealJson($response, $event, $skin)
            ?? $this->rewriteLinks($response, $event);
    }

    /**
     * A redirect that would leave the event becomes one that does not.
     *
     * Three shapes reach here, all of them correct on the platform:
     *   · a controller sending the organiser to the member console;
     *   · `auth` bouncing a signed-out visitor to /login;
     *   · the 403 handler in bootstrap/app.php sending a signed-in visitor to
     *     the platform home.
     *
     * The first is translated (the same screen exists here). The other two
     * land on the event's own sign-in page, which is where somebody who cannot
     * see a management screen should be standing: it is in the event's skin, it
     * says nothing about who runs the event, and signing in there comes back.
     */
    private function keepInside(Response $response, ClubEvent $event, array $skin): Response
    {
        $target = (string) $response->headers->get('Location');

        if ($target === '') {
            return $response;
        }

        $sealed = $this->skin->sealed($target, $event);

        if ($sealed !== $target) {
            $response->headers->set('Location', $sealed);

            return $response;
        }

        if ($this->skin->escapes($target, $event)) {
            $response->headers->set('Location', route('events.public.manage', ['event' => $event->uuid]));
        }

        return $response;
    }

    /**
     * Seal the `redirect` an AJAX write hands back.
     *
     * ONLY that one top-level key, and only on a JSON response. Everything
     * else in a payload is data — a name, a label, a colour, a file path — and
     * rewriting addresses blindly through a JSON body is how a value that was
     * never an address gets mangled. `redirect` is unambiguous: it exists to be
     * assigned to `window.location`.
     *
     * The exception is an event that no longer EXISTS. Deleting one from inside
     * its own app leaves nothing to return to — every address under `/e/{uuid}`
     * 404s from that moment — so the platform destination the controller chose
     * is left exactly as it is. That is the one case where leaving the app is
     * the only truthful answer.
     *
     * Returns null when this response is not JSON, so the caller falls through
     * to the HTML pass.
     */
    private function sealJson(Response $response, ClubEvent $event, array $skin): ?Response
    {
        if (! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return null;
        }

        $payload = json_decode((string) $response->getContent(), true);

        if (! is_array($payload) || ! isset($payload['redirect']) || ! is_string($payload['redirect'])) {
            return $response;
        }

        // Gone. Nothing inside the app to send anybody to — see above.
        if (! ClubEvent::whereKey($event->getKey())->exists()) {
            return $response;
        }

        $target = $payload['redirect'];
        $sealed = $this->skin->sealed($target, $event);

        if ($sealed === $target && $this->skin->escapes($target, $event)) {
            $sealed = route('events.public', ['event' => $event->uuid]);
        }

        if ($sealed === $target) {
            return $response;
        }

        $payload['redirect'] = $sealed;
        $response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    /**
     * Move the addresses in a rendered page into the sealed space.
     *
     * HTML only: a JSON payload feeding a component is consumed by script on a
     * page that has already been through here, and rewriting arbitrary JSON
     * risks touching values that are not addresses at all.
     */
    private function rewriteLinks(Response $response, ClubEvent $event): Response
    {
        $type = (string) $response->headers->get('Content-Type');

        if (! str_contains($type, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $response->setContent($this->skin->rewriteBody($content, $event));

        return $response;
    }
}
