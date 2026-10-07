<?php

namespace App\Events\Middleware;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Support\PublicEvent;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who is reading `/me/events/{uuid}` — the one event address.
 *
 * That route used to sit inside the member stack (`auth`, `verified`,
 * `two-factor`) like every other /me page. With `events.unified_page` ON it is
 * the event's ONE page, so a stranger holding the link must be able to open it
 * — but only while the organiser's public switch is on, exactly as `/e/{uuid}`
 * has always behaved.
 *
 * The whole decision, and nothing else:
 *   · flag OFF → the member stack runs, unchanged. Byte-for-byte what the route
 *     did inside its group (RULE #1).
 *   · flag ON, event published, branded type → nobody is stopped at the door.
 *     A reader who has not finished signing in (no account, an unverified
 *     address, a pending 2FA step) is a VISITOR and gets the poster a stranger
 *     gets — never a login form, which is the thing a public link must not
 *     turn into. Everybody else is a MEMBER.
 *   · flag ON, anything else → the member stack runs, unchanged.
 *
 * The answer travels as the request attribute `event_reader` ('visitor' or
 * 'member'). It decides what the page OFFERS; it grants nothing — every write
 * the page reaches keeps its own auth stack and re-checks.
 */
class EventPageReader
{
    /** The stack this route carried before it left the /me group. */
    private const MEMBER_STACK = ['auth', 'verified', 'two-factor'];

    public function __construct(
        private Router $router,
        private EventTypeRegistry $types,
        private PublicEvent $publisher,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // A LINK READER (WhatsApp, Instagram…) fetching a shared event address
        // gets the preview tags — it keeps no session, so for an event that is
        // not public it would otherwise unfurl the login page. Only for events
        // that are published or take entries by invitation; it reads nothing a
        // holder of the link would not see. (App\Events\Support\ShareCard)
        if ($preview = $this->linkPreview($request)) {
            return $preview;
        }

        $event = $this->openEvent($request);

        if ($event === null) {
            return $this->memberStack($request, $next);
        }

        $request->attributes->set('event_reader', $this->signedInFully($request) ? 'member' : 'visitor');

        return $next($request);
    }

    private function linkPreview(Request $request): ?Response
    {
        if (! $request->isMethod('GET') || ! \App\Events\Support\ShareCard::isLinkReader($request)
            || ! in_array((string) $request->route()?->getName(), \App\Events\Support\EventInvitePass::ROUTES, true)) {
            return null;
        }
        $event = $request->route()?->parameter('event');
        if (! $event instanceof ClubEvent) {
            $event = is_string($event) && $event !== '' ? ClubEvent::where('uuid', $event)->first() : null;
        }
        if (! $event instanceof ClubEvent || $event->is_archived
            || ! ($this->publisher->isPublic($event) || \App\Events\Support\ClubEntry\ClubEntryDesks::for($event) !== null)) {
            return null;
        }
        $share = rescue(fn () => app(\App\Events\Support\ShareCard::class)->meta($event), null, report: true);
        if (! $share) {
            return null;
        }

        return response()->view('events::entry.share-preview', [
            'share' => $share, 'url' => $request->fullUrl(), 'host' => $event->host()->name,
        ])->header('Cache-Control', 'public, max-age=600');
    }

    /** The published, branded event this request names — or null. Never throws. */
    private function openEvent(Request $request): ?ClubEvent
    {
        // Not behind `events.unified_page` any more: since /e/ went, these
        // addresses ARE the public event pages, so a published event must
        // open for a stranger whatever the member-side flag says.
        if (! $request->isMethod('GET')) {
            return null;
        }

        // The mirrored /e/{uuid}/admin/… copy of this route (SealedEventRoutes)
        // is the organiser's space and keeps the member stack whatever the
        // flag says — a stranger there is sent to sign in, as before.
        if ($request->attributes->get('sealed_event') instanceof ClubEvent) {
            return null;
        }

        $event = $request->route()?->parameter('event');

        if (! $event instanceof ClubEvent) {
            $event = is_string($event) && $event !== '' ? ClubEvent::where('uuid', $event)->first() : null;
        }

        // An invitation pass (EventInvitePass) opens it as well — for the event
        // page and its club-entry steps only, never its other sections.
        return $event instanceof ClubEvent
            && ($this->publisher->isPublic($event) || \App\Events\Support\EventInvitePass::opens($request, $event))
            ? $event : null;
    }

    /** Would this reader have passed the member stack? Asked, never enforced. */
    private function signedInFully(Request $request): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        // As EnsureEmailIsVerifiedOrImpersonating: an admin viewing as
        // somebody passes the verified check whatever that account's state.
        $impersonating = $request->hasSession() && $request->session()->has('impersonate.original_id');

        if (! $impersonating && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return false;
        }

        // The same question RequiresTwoFactor asks.
        return ! ($user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor.verified'));
    }

    private function memberStack(Request $request, Closure $next): Response
    {
        $request->attributes->set('event_reader', 'member');

        return (new Pipeline(app()))
            ->send($request)
            ->through($this->router->resolveMiddleware(self::MEMBER_STACK))
            ->then($next);
    }
}
