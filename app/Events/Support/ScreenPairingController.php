<?php

namespace App\Events\Support;

use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One address, any screen, any job.
 *
 * The packages each own a fleet, and rightly so. But a person carrying a
 * television into a hall has no idea which package will own it, and should not
 * have to: they open ONE address, the screen shows a code, an organiser scans
 * it and says what it is. Only then is there enough information to know which
 * fleet it belongs to — and at that moment the screen is adopted into it and
 * sent on its way.
 *
 * Nothing here can display a competition. A waiting screen can render its own
 * pairing code and nothing else; every claim is an authenticated act checked
 * against the event the organiser named.
 */
class ScreenPairingController extends Controller
{
    public function __construct(private HallScreenRouter $router) {}

    /** Remembers which waiting screen this machine already is. */
    private const COOKIE = 'takeone_screen';

    /**
     * The address you open on a screen. Resolves SERVER-SIDE and redirects.
     *
     * There is no page here any more, and that is the point. It used to render
     * a spinner and then enrol over fetch — which meant a television could sit
     * on "Setting this screen up" forever if anything at all went wrong with
     * the request, and the one machine that cannot be debugged is a screen
     * bolted to a wall with no keyboard. Every failure mode of that design
     * (a hung fetch, an exhausted rate limit, stale cached JavaScript, storage
     * disabled, JS off entirely) produced the same silent spinner.
     *
     * Now the work happens before a byte is sent: this either finds the screen
     * this machine already is, or creates one, and redirects straight to its
     * code. Nothing to hang, nothing to retry, and it works with JavaScript
     * switched off.
     *
     * The identity lives in a cookie rather than localStorage for the same
     * reason — the server can read a cookie, so a reload is resolved here
     * instead of by a script that has to run first.
     */
    public function screen(Request $request)
    {
        // "This is a DIFFERENT screen" — the explicit start-over, so a machine
        // that has already been one can become a second.
        if (! $request->has('new')) {
            $existing = PendingScreen::resolve($request->cookie(self::COOKIE));

            // Only a screen that is still WAITING. A settled row is spent — it
            // has already become a real device and holds nothing but the
            // address that device was sent to.
            //
            // Reusing one was the bug that made every scan land on the same
            // board: pair a monitor as the upcoming list, then come back to
            // this address to make it a scoreboard, and the cookie sent you
            // straight back to the settled row, which redirected to the
            // upcoming board again. It looked like the surface choice was being
            // ignored; in fact the choice was never reached. Coming back here
            // means "make this a screen", so a spent identity is dropped.
            if ($existing && $existing->claimed_at === null) {
                return redirect()->route('screen.show', $this->tokenFor($request));
            }
        }

        ['token' => $token] = PendingScreen::begin();

        // A month: long enough that a television which is switched off between
        // competitions comes back as itself, short enough to be forgotten.
        return redirect()->route('screen.show', $token)
            ->withCookie(cookie(self::COOKIE, $token, 60 * 24 * 30, null, null, true, true));
    }

    /** The raw token this machine is carrying — validated by resolve() above. */
    private function tokenFor(Request $request): string
    {
        return (string) $request->cookie(self::COOKIE);
    }

    /** A waiting screen asks for an identity. Grants only the right to wait. */
    public function enroll(Request $request): JsonResponse
    {
        ['screen' => $screen, 'token' => $token] = PendingScreen::begin();

        return response()->json([
            'token' => $token,
            'code' => $screen->pairing_code,
            'url' => route('screen.show', $token),
        ]);
    }

    /** The waiting screen itself: its code, and a QR of the claim page. */
    public function show(string $token)
    {
        $screen = PendingScreen::resolve($token);

        // A token that no longer resolves sends the screen back to the start
        // rather than to a 404 — which, for a signed-in browser, is a redirect
        // home and looks to a television like the app simply left. Starting
        // over is the only recovery a wall screen has.
        if (! $screen) {
            return redirect()->route('screen.new');
        }

        // Already told what it is while this page was loading — go there.
        if ($screen->destination) {
            return redirect()->away($screen->destination);
        }

        return view('events.screen.waiting', [
            'code' => $screen->pairing_code,
            'claimUrl' => route('screen.claim', $screen->pairing_code),
            'statusUrl' => route('screen.status', $token),
            // The address as it is READ off the glass and typed into a
            // sideloader — without the scheme, which is eight keystrokes on a
            // remote that a sideloader adds by itself. The button's href is the
            // full route; this is only the legible half. Null when no build is
            // published, so the page never offers a download that 404s.
            'appUrl' => self::appAvailable('tv')
                ? preg_replace('#^https?://#', '', route('screen.app'))
                : null,
            'tabUrl' => self::appAvailable('tab')
                ? preg_replace('#^https?://#', '', route('screen.app.tab'))
                : null,
        ]);
    }

    /**
     * "Have I been told yet?" — polled by the waiting screen.
     *
     * A pending screen has no realtime credentials (it is entitled to nothing,
     * so it is issued nothing), which is exactly why this poll exists. Cheap:
     * one indexed read, and it stops the moment the screen moves on.
     */
    public function status(string $token): JsonResponse
    {
        $screen = PendingScreen::resolve($token);

        // RETURNED, not aborted. An abort raises an exception, and this app
        // deliberately turns a 404 into a redirect home for a signed-in browser
        // — which is right for a person who mistyped a URL and catastrophic
        // here: the screen's fetch follows the redirect, sees 200 and HTML,
        // concludes it is fine, and carries on asking about a token that no
        // longer exists. A returned response cannot be rewritten by a handler.
        if (! $screen) {
            return response()->json(['error' => 'unknown'], 404);
        }

        return response()->json(['go' => $screen->destination]);
    }

    /**
     * Hands a television the app that makes it a screen.
     *
     * Open, like the rest of this controller, and for a sharper version of the
     * same reason: the machine fetching this has no account, no keyboard and no
     * app — it is a TV with a sideloader and a remote control. Requiring a
     * session here would mean the one address a bare screen needs is the one
     * address it cannot reach.
     *
     * What it serves is not a secret. The APK is a kiosk browser pinned to this
     * host; it carries no credential, and every screen it can ever become still
     * has to be claimed by an authenticated organiser. It is rate limited
     * because it is big, not because it is sensitive.
     *
     * Served from private storage rather than the web root so the file is never
     * listable, always passes through the limiter, and can be replaced without
     * a deploy.
     */
    public function app(string $variant = 'tv')
    {
        $path = self::appPath($variant);

        // An unknown variant is a 404, not a guess. The value reaches the
        // filesystem, so it is resolved through a whitelist rather than
        // interpolated — there is no arrangement of characters a caller can send
        // that becomes a path.
        //
        // A missing FILE is also a 404 rather than an error page: it is an
        // artifact nobody has published yet, not a broken route, and the screen
        // page hides the address of anything it cannot serve.
        abort_unless($path && is_file($path), 404);

        return response()->download($path, 'takeone-screen-'.$variant.'.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            // A sideloader on a TV must fetch the bytes, not a cached 304 from
            // whatever proxy the venue's wifi runs.
            'Cache-Control' => 'no-store, must-revalidate',
        ]);
    }

    /** The published builds, by the only names there are. */
    private const APPS = [
        'tv' => 'takeone-screen-tv.apk',
        'tab' => 'takeone-screen-tab.apk',
        // The phone that films the mat. Same door, same whitelist, same
        // limiter — it is another unattended device that needs its app before
        // it can be anything.
        // The camera IS boutcam now: one app that records the bout and carries
        // the live feed from the same camera session, shipped under the camera's
        // own application id so it upgrades the older build in place rather than
        // sitting beside it. One camera icon on a phone, not two.
        'cam' => 'takeone-screen-cam.apk',
    ];

    /** Resolves a variant to a file, or null if it is not one of ours. */
    private static function appPath(string $variant): ?string
    {
        $file = self::APPS[$variant] ?? null;

        return $file ? storage_path('app/private/tv/'.$file) : null;
    }

    /** Whether a given build has been published. Cheap enough to ask per render. */
    public static function appAvailable(string $variant = 'tv'): bool
    {
        $path = self::appPath($variant);

        return $path !== null && is_file($path);
    }

    /**
     * The organiser's form, reached by scanning the screen — or the camera.
     *
     * One door for both on purpose. Somebody standing in a hall with a phone
     * has just scanned a QR off a device; making them know in advance whether
     * that device was a television or a lens, and pick the right app screen
     * accordingly, is a distinction that matters to this codebase and to nobody
     * in the building.
     */
    public function claim(Request $request, string $code)
    {
        $screen = PendingScreen::pairable($code);
        $camera = $screen ? null : \App\Models\EventCamera::pairable($code);

        abort_unless($screen || $camera, 404);

        return view('events.screen.claim', [
            'code' => $code,
            'isCamera' => (bool) $camera,
            'events' => $this->manageableEvents($request->user(), forCamera: (bool) $camera),
        ]);
    }

    /**
     * Adopt the screen: create the real device, and send the screen to it.
     */
    public function storeClaim(Request $request, string $code)
    {
        $screen = PendingScreen::pairable($code);
        $camera = $screen ? null : \App\Models\EventCamera::pairable($code);

        abort_unless($screen || $camera, 404);

        $data = $request->validate([
            'event' => ['required', 'string', 'size:36'],
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['required', 'string', 'in:bout,queue,control,camera'],
        ]);

        // A camera is adopted here and then leaves this flow entirely: it has no
        // page to be sent to, so there is no destination to settle — the phone
        // is polling its own config and will see itself claimed within seconds.
        if ($camera) {
            return $this->claimCamera($request, $camera, $data);
        }

        $event = ClubEvent::where('uuid', $data['event'])->first();

        // Re-checked here, never trusted from the form that offered it: the
        // list is a convenience, this is the authorization.
        abort_unless($event && app(EventAccess::class)->canManage($event, $request->user()), 403);

        // The job must be one this event's package can actually serve.
        abort_unless(in_array($data['surface'], $this->router->surfaces($event), true), 422);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        // A screen that SCORES can write results, so it takes more than the
        // right to manage the event: it takes the right to score it.
        if ($data['surface'] === 'control') {
            abort_unless(app(EventAccess::class)->canScore($event, $request->user()), 403);

            // And there is only ever one per mat. Two scoring tables on one mat
            // means two officials entering different results into the same bout
            // with no way for either to see the other.
            if ($existing = $this->router->existingControl($event, $court)) {
                return back()->withErrors([
                    'surface' => __('personal.event_screens_control_taken', ['court' => $court]),
                ])->withInput();
            }
        }

        ['url' => $url] = $this->router->adopt($event, $court, $data['surface'], $request->user()->id);

        // The waiting row is spent: its code is freed and it holds only the
        // address the screen should now be showing.
        $screen->settle($url);

        return redirect()->route('screen.claimed')->with('status', __('personal.event_screens_claimed', [
            'court' => $court,
            'event' => $event->title,
        ]));
    }

    /**
     * Put a scanned phone on a mat as one of its cameras.
     *
     * Authorisation is re-checked here and never taken from the form that
     * offered the list, exactly as it is for a screen. Managing the event is
     * the right that matters: a camera records the mat, it cannot score it, so
     * it deliberately does NOT require the right to score the way a control
     * screen does.
     *
     * @param  array<string, mixed>  $data
     */
    private function claimCamera(Request $request, \App\Models\EventCamera $camera, array $data)
    {
        $event = ClubEvent::where('uuid', $data['event'])->first();

        abort_unless($event && app(EventAccess::class)->canManage($event, $request->user()), 403);
        abort_unless($data['surface'] === 'camera', 422);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        // Four lenses per mat, and the fifth is refused HERE rather than by a
        // constraint — the cap is about live cameras, and unpairing one frees
        // its angle immediately.
        $angle = \App\Events\Support\Cameras\CameraFleet::nextAngle($event, $court);

        if ($angle === null) {
            return back()->withErrors([
                'surface' => __('events.camera_claim_full', ['court' => $court]),
            ])->withInput();
        }

        $camera->claim($event, $court, $angle, $request->user()->id);
        \App\Events\Support\Cameras\CameraFleet::notify($camera, 'paired');
        // Every other organiser's console picks the new camera up without a
        // reload — the panel re-fetches on this nudge.
        \App\Events\Support\Cameras\CameraFleet::consolesChanged($event);

        return redirect()->route('screen.claimed')->with('status', __('events.camera_claim_done', [
            'angle' => $angle,
            'court' => $court,
            'event' => $event->title,
        ]));
    }

    /** A plain "done" page for the organiser's phone. */
    public function claimed()
    {
        return view('events.screen.claimed');
    }

    /**
     * Events this person may put a screen on: the ones they manage that
     * actually drive screens, with the mats their draw really made.
     */
    private function manageableEvents($user, bool $forCamera = false)
    {
        if (! $user) {
            return collect();
        }

        $access = app(EventAccess::class);

        return ClubEvent::query()
            ->whereIn('status', ['active', 'published', 'running'])
            ->orderByDesc('date')
            ->limit(40)
            ->get()
            // A screen is only offered events whose package can actually draw
            // one. A camera has no such limit — pointing a lens at a mat needs
            // nothing from the sport — so it is offered every event the person
            // manages that runs bouts on named mats.
            ->filter(fn (ClubEvent $e) => $access->canManage($e, $user) && ($forCamera || $this->router->surfaces($e)))
            ->map(fn (ClubEvent $e) => [
                'uuid' => $e->uuid,
                'title' => $e->title,
                'courts' => \App\Models\EventMatch::where('event_id', $e->id)
                    ->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->all(),
                'surfaces' => $forCamera ? ['camera'] : $this->router->surfaces($e),
                // So the form can grey out a mat's control slot that is taken,
                // rather than refusing after the fact.
                'controls' => $forCamera ? [] : $this->takenControls($e),
                // The same courtesy for cameras: how many of the four lenses on
                // each mat are already spoken for.
                'cameras' => $forCamera ? $this->cameraSlots($e) : [],
            ])
            ->values();
    }

    /**
     * Cameras already live on each mat of this event, keyed by mat.
     *
     * @return array<string, int>
     */
    private function cameraSlots(ClubEvent $event): array
    {
        return \App\Models\EventCamera::query()
            ->where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->whereNotNull('claimed_at')
            ->selectRaw('court, COUNT(*) as used')
            ->groupBy('court')
            ->pluck('used', 'court')
            ->all();
    }

    /** Mats on this event that already have a scoring table. */
    private function takenControls(ClubEvent $event): array
    {
        $taken = [];

        foreach (\App\Models\EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')->distinct()->pluck('court') as $court) {
            if ($this->router->existingControl($event, $court)) {
                $taken[] = $court;
            }
        }

        return $taken;
    }
}
