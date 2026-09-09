<?php

namespace App\Http\Controllers;

use App\Events\Support\EventAccess;
use App\Events\Support\PublicBrand;
use App\Events\Support\PublicEvent;
use App\Events\Support\PublicEventSkin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\ClubEvent;
use App\Models\EventDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The event page anybody may open — the link an organiser puts on Instagram.
 *
 * Phase B of Documentation/EVENTS-PUBLIC-ENTRY.md. READ-ONLY: the whole new
 * surface is a GET, so the page can ship and be judged before any public write
 * exists (that is Phase C).
 *
 * Two rules do all the work here:
 *   1. Opt-in. `entry_mode = public`, set by the organiser on one event at a
 *      time. Anything else 404s — the same 404 an unknown uuid gets, so the
 *      page cannot be used to discover which events exist.
 *   2. `App\Events\Support\PublicEvent` decides what may be said. This
 *      controller never reads a column of its own.
 */
class PublicEventController extends Controller
{
    public function show(Request $request, ClubEvent $event, PublicEvent $publisher)
    {
        $e = $publisher->payload($event);

        abort_if($e === null, 404);

        // The page renders for EVERYONE, signed in or not.
        //
        // It used to bounce a signed-in viewer to the member page, which was
        // wrong twice over: the point of opening a shared link is to see the
        // page that was shared, and `me.events.show` sits behind auth +
        // verified + two-factor — so a stale session, an unverified address or
        // a pending 2FA step turned a PUBLIC link into a login form. A public
        // page that can redirect to login is not a public page.
        // ⚠️ The MOBILE view, at every width and on every device — asked for
        // explicitly, and it is the same decision as the manifest and the
        // fullscreen tap: this surface is an APP that happens to be reachable
        // by URL, not a website with a small-screen variant. A competition is
        // read in a hall, on a phone, one-handed. Serving a laptop a different
        // page meant two layouts to keep in step for a reader who is standing
        // at a mat, and the desktop one was where they drifted apart.
        //
        // The shell caps the column at a phone's width on a wide screen
        // (entry/layout.blade.php), so this reads as the app it is rather than
        // a mobile page stretched across a monitor.
        //
        // The desktop blades are LEFT ON DISK, unreferenced: nothing renders
        // them, and deleting them is a separate decision from ceasing to serve
        // them.
        /*
         * The gear's destination, decided here.
         *
         * Shown to EVERYBODY either way — hiding it would tell a stranger who
         * the organisers are — but for somebody who already runs the event it
         * goes straight to the console. That keeps `/e/{uuid}/manage` off the
         * normal path entirely, which is half of why the Back button used to
         * be trapped (see the note in manage()).
         */
        $me = Auth::user();
        $console = ($me && app(EventAccess::class)->canManage($event, $me))
            ? app(PublicEventSkin::class)->payload($event)['console']
            : null;

        /*
         * Does the person reading this already have an entry here?
         *
         * The panel that lets them fix a mistyped name or a wrong weight has
         * always existed at `/e/{uuid}/my-entry` — but nothing on this page
         * said so, and tapping Enter again told them they were already in and
         * offered only a way back. A screen nobody can find is a screen that
         * does not exist, so the poster now carries the way to it.
         *
         * Three answers only — 'entered', 'pending', or nothing — and it is
         * read for the VIEWER alone. Nobody learns anything about anybody else
         * from it, which is why it may sit on a page open to the world.
         */
        $mine = null;

        if ($me) {
            $entered = \App\Models\ClubEventRegistration::where('event_id', $event->id)
                ->where('user_id', $me->id)
                ->exists();

            $asked = ! $entered && \App\Models\EventPublicEntry::where('event_id', $event->id)
                ->where('user_id', $me->id)
                ->where('state', 'pending')
                ->exists();

            $mine = $entered ? 'entered' : ($asked ? 'pending' : null);
        }

        return view('entry.public.mobile', ['e' => $e, 'console' => $console, 'mine' => $mine]);
    }

    /**
     * The web-app manifest that makes the link installable AS THE EVENT.
     *
     * Somebody who adds a shared competition to their home screen gets the
     * event's name under the event's mark, and it opens on the event with no
     * browser chrome. Nothing here says which platform served it, because
     * nothing about the link they were sent did either.
     */
    public function manifest(ClubEvent $event, PublicEvent $publisher, PublicBrand $brand)
    {
        abort_if(! $publisher->isPublic($event), 404);

        return response()
            ->json($brand->manifest($event))
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Download one of the event's attached documents — the rulebook, the entry
     * form, the schedule.
     *
     * A SEPARATE door from `me.events.documents.download`, deliberately. That
     * route sits behind auth + verified + 2FA and asks
     * `EventAccess::visible($event, $user)`, whose $user is not nullable — a
     * guest cannot be asked the question at all. Rather than loosen a route
     * every member surface depends on, this adds a new one beside it and leaves
     * the old path exactly as it was (RULE #1: build the new path, don't rewrite
     * the working one).
     *
     * The gate here is the organiser's own switch and nothing else: documents on
     * an event whose public page is ON may be downloaded by anybody holding the
     * link. That is a deliberate decision recorded by the person who owns the
     * event — turning the page off closes these again in the same instant.
     */
    public function document(ClubEvent $event, EventDocument $document, PublicEvent $publisher)
    {
        abort_unless($publisher->isPublic($event), 404);

        // Never let a document from one event be fetched through another's URL.
        abort_unless($document->event_id === $event->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        // The stored name is a generated uuid; the reader gets the human title
        // back with a safe extension (EventDocument::downloadName()).
        return Storage::disk('local')->download($document->path, $document->downloadName());
    }

    /**
     * One section of the event, on its own page.
     *
     * The four doors on the poster — the draw, the officiating sheet, the
     * footage and the entry list — each open one of these, exactly as the
     * member page's four rows open their own pages. One action rather than
     * four, because the four differ only in which body the shell includes.
     *
     * `$section` reaches a VIEW NAME, so it is never trusted from the request:
     * the route allowlists the four values, and this method looks the label and
     * glyph up in its own table. An unknown section therefore 404s at the
     * router, and cannot arrive here at all.
     */
    public function section(Request $request, ClubEvent $event, string $section, PublicEvent $publisher)
    {
        $e = $publisher->payload($event);

        abort_if($e === null, 404);

        $meta = [
            'draw' => ['bi-diagram-3-fill bracket-icon', __('personal.event_show_tile_draw')],
            'officials' => ['bi-person-badge-fill', __('personal.event_show_tile_officials')],
            'gallery' => ['bi-camera-reels-fill', __('events.bout_gallery_title')],
            'participants' => ['bi-people-fill', __('personal.event_show_tile_participants')],
        ][$section] ?? null;

        abort_if($meta === null, 404);

        // An event that runs no draw has no draw page either — the poster shows
        // no door to one, and the address must agree with the door.
        abort_if($section === 'draw' && $e['divisions'] === [] && ! $e['draw']['published'], 404);

        // The mobile reading, at every width — see show(). The four doors are
        // part of the same app and cannot answer that question differently.
        /*
         * Whether the reader is the person RUNNING this event. Only used to
         * OFFER the organiser's own doors — the member profile behind them has
         * its own authorization and re-checks on the way in.
         */
        $me = Auth::user();

        /*
         * Where BACK goes — the poster, unless the reader arrived from the
         * PLATFORM.
         *
         * These four pages are the event's own section pages, and the member
         * event page sends readers straight into them (the Participants tile).
         * Back went to the poster unconditionally, so that crossing was a
         * ONE-WAY DOOR: somebody reading their own event at
         * /me/events/{uuid} tapped one tile and could not get back — the
         * branded surface has no tab bar and no drawer to escape through.
         * Found by a navigation audit, 2026-09-08.
         *
         * `?from=` names the surface with a KEY, never a URL — the same rule,
         * and the same reason, as the sealed person profile's own back
         * destination (`PeopleController::sealedBackUrl()`, in the members
         * module): a destination taken from the query string is an open
         * redirect with extra steps. One key is understood, anything else falls
         * through to the poster.
         *
         * (Named without its namespace on purpose. `ModuleBoundaryTest` scans
         * these files as TEXT for another module's private `Controllers\`
         * namespace, and it is right to — the rule is that nothing outside a
         * module names its internals, and a docblock that spells one out reads
         * to the scanner exactly like a call. Pointing at it in prose keeps the
         * cross-reference useful without teaching the next person that the
         * namespace is fair game.)
         *
         * And the key is only honoured for somebody who can actually open the
         * page it names: `me.events.show` sits behind auth, so handing a
         * stranger that address would answer a Back tap with a login form.
         */
        $fromPlatform = $request->query('from') === 'me'
            && $me !== null
            && app(EventAccess::class)->visible($event, $me);

        return view('entry.public.section-mobile', [
            'canManage' => $me !== null && app(EventAccess::class)->canManage($event, $me),
            'signedIn' => $me !== null,
            'e' => $e,
            'section' => $section,
            'sectionIcon' => $meta[0],
            'sectionLabel' => $meta[1],
            /* Both destinations ARE "the event", so the label does not change
               with them — only the address does. That is the same shape the
               console's back control already has on its two addresses. */
            'backUrl' => $fromPlatform
                ? route('me.events.show', ['event' => $event->uuid])
                : route('events.public', ['event' => $event->uuid]),
        ]);
    }

    /**
     * The draw — the same JSON `<x-tournament-bracket>` eats on the member
     * page, narrowed by PublicEvent::draw().
     *
     * A SEPARATE door from `me.events.bracket.data`, for the same reason the
     * document route is separate: that one sits behind auth + verified + 2FA
     * and hands back an organiser's working view (the entrants bench, the
     * arrange permission). This one is the wall sheet — read-only, no bench,
     * no faces, nobody's payment state.
     *
     * `can_arrange` is FALSE and `locked` is TRUE as constants, not as a
     * derivation somebody could later get wrong: the arranging endpoints do
     * not exist on this surface at all, so the board must never offer a
     * gesture whose save has nowhere to go.
     */
    public function drawData(ClubEvent $event, PublicEvent $publisher)
    {
        abort_unless($publisher->isPublic($event), 404);

        $divisions = $publisher->draw($event);

        // No draw is not an error — the board says so itself, in its own words.
        return response()->json([
            'divisions' => $divisions,
            'can_arrange' => false,
            'locked' => true,
        ]);
    }

    /**
     * The organiser's way IN — from the event's own page, in the event's own skin.
     *
     * The point of this surface is that a shared competition looks and behaves
     * like its own app: its name, its mark, its colour, installable on a home
     * screen, no platform anywhere on it. The person RUNNING it was the one
     * exception — they had to leave, find the platform's login, sign in and
     * navigate back. On an event day, on a phone, that is three chances to end
     * up somewhere that is not this competition.
     *
     * So the gear on the poster lands here instead, and here is still the event.
     *
     * ⚠️ There is no new authentication in this file, and there must never be.
     * The POST hands the credentials to the ONE login the platform has
     * (AuthenticatedSessionController@store) and takes back whatever it decides:
     * the lockout counter, the throttle, the unverified-address bounce, the
     * two-factor challenge and the activity log all apply exactly as they do at
     * /login. What is different here is the SKIN and the destination, which is
     * all that was ever wrong.
     *
     * Three states, decided on the server:
     *   · signed in and this event is theirs   → straight to the console
     *   · signed in as somebody else            → said plainly, with a way to
     *                                             sign in as the right account
     *   · not signed in                         → the branded sign-in
     *
     * It discloses nothing. The gear is on the page for every stranger, and
     * every one of them sees the same sign-in — who manages this event is never
     * named, and a wrong password here says what a wrong password says at
     * /login, no more.
     */
    /**
     * Sign out, and stay in the event.
     *
     * The platform's own /logout redirects to `/`, which for somebody using
     * this as an installed app is not "signed out" — it is being thrown out of
     * the application into a website they never asked for, with no way back but
     * the original link. So the seal gets its own door: same teardown as
     * AuthenticatedSessionController::destroy (guard logout, session
     * invalidated, CSRF token regenerated), landing on the poster.
     *
     * POST, so it keeps CSRF and cannot be fired by a link somebody else plants
     * — signing a reader out is a state change like any other.
     */
    public function signOut(Request $request, ClubEvent $event, PublicEvent $publisher)
    {
        abort_if(! $publisher->isPublic($event), 404);

        if (Auth::check()) {
            activity('auth')
                ->causedBy(Auth::user())
                ->withProperties(['ip' => $request->ip(), 'via' => 'event:'.$event->uuid])
                ->log('User logged out');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('events.public', ['event' => $event->uuid])
            ->with('status', __('events.public_signed_out'));
    }

    public function manage(Request $request, ClubEvent $event, PublicEvent $publisher, EventAccess $access)
    {
        abort_if(! $publisher->isPublic($event), 404);

        $e = $publisher->payload($event);
        abort_if($e === null, 404);

        $me = Auth::user();
        $manages = $me !== null && $access->canManage($event, $me);

        /*
         * ⚠️ THIS PAGE MUST NEVER REDIRECT.
         *
         * It used to send a manager straight on to the console with a 302, and
         * that one line was the "I can't go back" bug: the redirect stays in
         * the history stack, so pressing Back lands here and is thrown FORWARD
         * again. The organiser could never step back past sign-in to the
         * poster — every session, on every event day, and worst of all in an
         * installed home-screen app where Back is the only chrome there is.
         *
         * A page a person can land on by pressing Back has to RENDER. So a
         * manager gets the console offered as a door instead of being pushed
         * through it; the normal path never comes here at all, because the
         * poster's gear links a manager straight to the console and signIn()
         * lands them there directly.
         */
        return view('entry.public.manage', [
            'e' => $e,
            // Signed in, but not as anybody who runs this. Their own name is
            // theirs to be shown — nothing about the event's staff is.
            'wrongAccount' => $me !== null && ! $manages,
            'who' => $me?->full_name ?: $me?->name,
            // Signed in AS somebody who runs it: the console, as a door.
            'console' => $manages ? app(PublicEventSkin::class)->payload($event)['console'] : null,
        ]);
    }

    /**
     * Sign in, without leaving the event.
     *
     * A thin wrapper and deliberately nothing else: it decides WHERE a
     * successful login lands and delegates everything else. The destination is
     * built here from the event's own uuid and put in the session — never taken
     * from the request — so this cannot be turned into an open redirect by
     * anyone who can send somebody a link.
     *
     * Where it lands is decided AFTER the password is checked, because that is
     * the first moment we know who they are:
     *   · somebody who runs this event → the console, directly. Not via
     *     `manage`, which would leave a redirect in the history stack for the
     *     Back button to fall into (see the note in manage()).
     *   · anybody else → wherever they were headed. An entrant who tapped
     *     "I already have an account" from the entry form is returned to the
     *     ENTRY FORM, not told on a sign-in page that they do not run the
     *     event — which is what happened while this overwrote `url.intended`
     *     unconditionally.
     *
     * Every destination is built here from the event's own uuid or read from
     * the session — never from the request — so this cannot be turned into an
     * open redirect by anyone who can send somebody a link.
     */
    public function signIn(Request $request, ClubEvent $event, PublicEvent $publisher, AuthenticatedSessionController $auth, EventAccess $access)
    {
        abort_if(! $publisher->isPublic($event), 404);

        // Only when nothing is already stashed: the page that sent them here
        // (the entry form, say) knows better than this one where they belong.
        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', route('events.public.manage', $event->uuid));
        }

        $response = $auth->store($request);

        $me = Auth::user();

        // Wrong password, throttled, unverified — whatever the login stack
        // decided, it decided it. Hand its answer back untouched.
        if ($me === null) {
            return $response;
        }

        if ($access->canManage($event, $me)) {
            $request->session()->forget('url.intended');

            return redirect()->to(app(PublicEventSkin::class)->payload($event)['console']);
        }

        return $response;
    }

    /**
     * The app icon: the host club's mark on a tile of the event's colour.
     *
     * Open, so it is cheap by construction — a fixed size allowlist, generated
     * once per event and cached, and served with a long immutable lifetime
     * because the URL carries a version that changes when the mark does.
     */
    public function icon(Request $request, ClubEvent $event, int $size, PublicEvent $publisher, PublicBrand $brand)
    {
        /*
         * The mark is served while the poster is up — and, since 2026-09-07, to
         * somebody who may already open the event on the platform.
         *
         * The member event pages wear the organiser's brand now
         * (App\Http\Middleware\BrandEventPage), and their tab icon is THIS
         * one. Gated on the public switch alone, an event still set to
         * `members` had a broken mark in the tab of a page it was branding —
         * the one thing a white-labelled surface must not get wrong.
         *
         * The widening is by AUTHORIZATION, not by removal: `visible()` is the
         * same check the event page itself runs, so nobody sees a mark for an
         * event they could not already open, and a stranger holding a uuid
         * still gets the same 404 as before. Anti-enumeration is untouched.
         */
        $viewer = $request->user();

        abort_if(
            ! $publisher->isPublic($event)
            && ! ($viewer && app(EventAccess::class)->visible($event, $viewer)),
            404
        );

        return response($brand->icon($event, $size))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=1209600, immutable');
    }
}
