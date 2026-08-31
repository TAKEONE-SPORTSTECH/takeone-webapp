<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament\HallScreen;

use App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard\MatState;
use App\Events\Support\EventAccess;
use App\Events\Support\HallScreenRouter;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Brazilian Jiu-Jitsu hall-screen fleet: the wall boards, and the console
 * panel that pairs them.
 *
 * Its own fleet, its own table, its own routes — for the reason set out in the
 * migration: a device resolves by token hash alone, so one shared table would
 * let a sibling package resolve a BJJ screen and hand it the wrong board.
 *
 * The shared App\Events\Support\HallScreenRouter dispatches the organiser's
 * three console endpoints here by the event's sport, so this class implements
 * exactly the three method signatures that router calls — screens(), pair() and
 * revokeScreen() — plus the token-authorised doors a screen itself uses.
 *
 * ── Who is allowed what ─────────────────────────────────────────────────────
 *  · The board and its heartbeat are authorised by the DEVICE TOKEN. A wall
 *    screen has nobody signed in to it, and the token names exactly one event
 *    and one mat.
 *  · Pairing and unpairing require an organiser who can MANAGE the event, and
 *    a scoring table additionally requires the right to SCORE it.
 */
class HallScreenController extends Controller
{
    private const SPORT = 'bjj';

    public function __construct(private ScreenBoard $board) {}

    /**
     * Will the scoring console actually open for this screen?
     *
     * Asked before redirecting to it, so a screen is never sent somewhere that
     * will refuse it. Mirrors ScoreboardController::canOpenControl() — and the
     * identical guard both sibling fleets already carry.
     */
    private function canServeControl(ScreenDevice $device): bool
    {
        if ($device->event?->sport !== self::SPORT || ! $device->court) {
            return false;
        }

        $by = $device->created_by ? \App\Models\User::find($device->created_by) : null;

        return $by !== null && app(EventAccess::class)->canScore($device->event, $by);
    }

    /* ---------------- The screen's own doors (token) ---------------- */

    /**
     * What this screen shows.
     *
     * Three outcomes, and the screen never chooses between them: an unclaimed
     * device gets its pairing code, a scoring table is sent to the console, and
     * a claimed board draws either the mat or the running order depending on
     * what it was hung up to be.
     */
    public function screen(Request $request, string $token)
    {
        $device = ScreenDevice::resolve($token);

        // A token that no longer resolves sends the screen back to the start
        // rather than to a 404 — the same rule the pairing room and both
        // sibling fleets already follow (ScreenPairingController::show,
        // CourtDisplayController::board).
        //
        // This is the ONLY recovery a screen has. The board it was paired to is
        // the address the machine remembers and reopens after a power cut, and
        // that address dies the moment the screen is unpaired, revoked, or its
        // event is deleted. A 404 then leaves a television — or a tablet with
        // no BACK key — parked on an error page it cannot leave, and the only
        // way out was to clear the app's data. Sent to /screen it stands there
        // showing a fresh pairing code, which is a state somebody in the hall
        // can act on.
        //
        // Still ONE response for a bad token and a revoked screen: a wall
        // screen is scanned by whoever walks past it, and differing replies
        // would tell them which tokens are real.
        if (! $device) {
            return redirect()->route('screen.new');
        }

        $device->touchSeen();

        if (! $device->isClaimed() || ! $device->event) {
            /*
             * Back to the sport-neutral room, not this package's own code.
             *
             * A screen standing on a code from THIS fleet can only ever be
             * claimed into an event of THIS sport — and unpairing returns a
             * device to its fleet, so a screen used once for karate could never
             * afterwards be paired to anything else. The organiser reads the code
             * off the wall, types it into the event in front of them, and is told
             * it does not match a screen waiting to be paired. It was waiting;
             * just in a queue that event could not see.
             *
             * /screen issues a code any event of any sport can claim, which is
             * what the fleet being decided by the EVENT at claim time actually
             * requires. The per-package claim door still works for anyone holding
             * one of its codes; nothing stands on one any more.
             */
            return redirect()->route('screen.new');
        }

        // A paired scoring table is a console, not a board. Sent there rather
        // than drawn here, so there is one console page with two front doors.
        //
        // Only when that door will actually open, and only when the URL is not
        // explicitly asking for a board. The console can be shut — the organiser
        // who paired the screen may since have lost the right to score — and
        // redirecting into a refusal leaves a screen in a hall bouncing between
        // two URLs with nothing on it and no way back: tokenControl() sends a
        // console it will not open back to HERE, and without this guard this
        // sent it straight there again. A board it can draw is always better
        // than an error it cannot leave.
        if ($device->surface === 'control'
            && ! in_array($request->query('surface'), ['queue', 'bout'], true)
            && $this->canServeControl($device)) {
            return redirect()->route('bjj-scoreboard.token-control', $token);
        }

        $event = $device->event;

        // Claimed onto an event that is not this sport's any more. Recover the
        // same way as every other dead end here rather than 404 — the screen
        // cannot draw this event, but it can always go back and wait for one.
        if ($event->sport !== self::SPORT) {
            return redirect()->route('screen.new');
        }

        $state = MatState::forMat($event, $device->court);
        $state->setRelation('event', $event);

        return view('event-bjj_tournament::screen.mat', [
            'event' => $event,
            'court' => $device->court,
            // What this screen was hung up to be. 'bout' stays on the mat all
            // day, 'queue' follows the running order, and anything else follows
            // the mat between the two — which in this package is not a change of
            // page, because ONE document draws the queue, the introduction, the
            // match and the celebration. See the note in partials/screen-link.
            'pinned' => in_array($device->surface, ['bout', 'queue'], true) ? $device->surface : 'both',
            'state' => $state->present(),
            'board' => $this->board->payload($event, $device->court),
            'statusUrl' => route('bjj-screen.status', $token, false),
            'stateUrl' => route('bjj-scoreboard.state', $token, false),
            'boardUrl' => route('bjj-screen.payload', $token, false),
            // The subscribe-only socket credential, or null when realtime is
            // off — in which case the heartbeat is the only signal and
            // everything still works, just slowly.
            'screenLink' => ($link = ScreenChannel::credentials($device)) === null ? null
                : $link + [
                    'state_url' => route('bjj-scoreboard.state', $token, false),
                    'payload_url' => route('bjj-screen.payload', $token, false),
                ],
        ]);
    }

    /**
     * The livestream lower third — the same mat, 1920×160 on alpha.
     *
     * A separate surface rather than a mode of the board, because it is
     * composited into a video feed rather than hung on a wall: no background, a
     * 96px safe area, and only the four things a viewer at home needs.
     */
    public function overlay(Request $request, string $token)
    {
        $device = ScreenDevice::resolve($token);

        // A page, not an endpoint — so every dead end here recovers to the
        // waiting room rather than 404ing, exactly as screen() does. A device
        // parked on an overlay whose token, pairing or event has gone has no
        // keyboard to type its way out with.
        if (! $device || ! $device->isClaimed() || ! $device->event
            || $device->event->sport !== self::SPORT) {
            return redirect()->route('screen.new');
        }

        $device->touchSeen();

        $state = MatState::forMat($device->event, $device->court);
        $state->setRelation('event', $device->event);

        return view('event-bjj_tournament::screen.overlay', [
            'event' => $device->event,
            'court' => $device->court,
            'state' => $state->present(),
            'stateUrl' => route('bjj-scoreboard.state', $token, false),
            'screenLink' => ($link = ScreenChannel::credentials($device)) === null ? null
                : $link + ['state_url' => route('bjj-scoreboard.state', $token, false)],
        ]);
    }

    /**
     * Is this screen still the thing it thinks it is?
     *
     * The heartbeat and the unpair signal in one field. A screen that has been
     * taken off a mat notices by itself and goes back to its code, which is the
     * only recovery a device with no keyboard has.
     */
    /**
     * A screen enrolling itself with this package directly.
     *
     * The second pairing door, and the one this package was missing: karate and
     * taekwondo both have it, so a BJJ screen could only ever be paired through
     * the sport-neutral waiting room at /screen. Every hall screen now enrols
     * the same way whatever sport it will show — which is the point, because the
     * person setting one up in a hall does not know or care which package will
     * end up driving it.
     *
     * Returns the token the screen keeps and the code a human reads off it. The
     * code is worth nothing on its own: claiming it is authenticated, and the
     * claim only offers events the signed-in organiser can actually manage.
     */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        ['device' => $device, 'token' => $token] = ScreenDevice::begin($data['label'] ?? null);

        return response()->json([
            'token' => $token,
            'pairing_code' => $device->pairing_code,
            'board_url' => route('bjj-screen.board', $token),
        ], 201);
    }

    /**
     * The realtime credentials for one screen, so it can be pushed to rather
     * than poll.
     *
     * Realtime being switched off is not an error — the screen falls back to the
     * status endpoint, which is slower and always works.
     */
    public function link(Request $request, string $token)
    {
        $device = ScreenDevice::resolve($token);

        abort_unless($device, 404);

        $device->touchSeen();

        $credentials = ScreenChannel::credentials($device);

        abort_unless($credentials, 404);

        return response()->json($credentials);
    }

    public function status(string $token): JsonResponse
    {
        $device = ScreenDevice::resolve($token);

        // RETURNED, not aborted — the same rule ScreenPairingController::status
        // is written to. abort() raises an exception this app rewrites into a
        // redirect home for a session-bearing browser; the agent then sees 200
        // and HTML, concludes all is well, and polls a token that no longer
        // exists for ever. A returned response cannot be rewritten by a handler.
        if (! $device) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $device->touchSeen();

        return response()->json(['claimed' => $device->isClaimed() && $device->event !== null]);
    }

    /** The running order for this screen's mat, for a board that reconnected. */
    public function payload(string $token): JsonResponse
    {
        $device = ScreenDevice::resolve($token);

        abort_unless($device && $device->isClaimed() && $device->event, 404);

        $device->touchSeen();

        return response()->json($this->board->payload($device->event, $device->court));
    }

    /* ---------------- The organiser's console (session) ---------------- */

    /** Every screen on this event, as the console lists them. */
    public function screens(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        return response()->json(['success' => true, 'screens' => $this->screensFor($event)]);
    }

    /**
     * Adopt a waiting screen onto a mat.
     *
     * Two rules beyond "may manage", and both are here rather than in the
     * console: a scoring table takes the right to SCORE, not merely to manage,
     * and there is only ever ONE scoring table per mat — two of them means two
     * officials entering different results into the same match with no way for
     * either to see the other.
     */
    public function pair(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{6}$/'],
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string', 'in:queue,bout,control,follow'],
        ]);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        $device = ScreenDevice::pairable($data['code']);

        // One answer for "no such code", "already claimed" and "revoked". The
        // code is six characters and readable across a hall, so distinguishing
        // them would turn this into a way to probe which screens exist.
        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => __('event-bjj_tournament::messages.pair_unknown'),
            ], 404);
        }

        $router = app(HallScreenRouter::class);
        $surface = $data['surface'] ?? 'follow';

        abort_unless(in_array($surface, array_merge($router->surfaces($event), ['follow']), true), 422);

        if ($surface === 'control') {
            abort_unless(app(EventAccess::class)->canScore($event, $request->user()), 403);

            if ($router->existingControl($event, $court)) {
                return response()->json([
                    'success' => false,
                    'message' => __('personal.event_screens_control_taken', ['court' => $court]),
                ], 422);
            }
        }

        $device->claim($event, $court, $request->user()->id, $surface === 'follow' ? null : $surface);

        // The screen is standing in front of somebody showing a QR code — it
        // should become the board now, not on its next throttled poll.
        ScreenChannel::notify($device, 'paired');
        $this->screensChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('event-bjj_tournament::messages.pair_done', [
                'court' => $device->court, 'event' => $event->title,
            ]),
            'screen' => $device->present(),
            'screens' => $this->screensFor($event),
        ]);
    }

    /**
     * Take a screen off its mat.
     *
     * UNCLAIM, deliberately not revoke: a revoked token resolves to nothing, and
     * a screen agent only enrols when its token file is empty — it would sit on
     * a 404 for ever, recoverable only by editing the SD card. Unclaiming keeps
     * the token valid, so the device polls, sees it is no longer claimed, and
     * comes back showing a fresh code, which is what an organiser moving a
     * screen between mats actually wants.
     */
    public function revokeScreen(Request $request, ClubEvent $event, int $device): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $screen = ScreenDevice::where('event_id', $event->id)->find($device);

        abort_unless($screen, 404);

        $screen->unclaim();

        // AFTER the write, never before: the screen answers this by re-fetching
        // its page, and a push that overtook the update would send it back to
        // the board it was just taken off. The topic survives unclaim() — it is
        // keyed on the token hash, which does not change.
        ScreenChannel::notify($screen, 'unpaired');
        $this->screensChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('event-bjj_tournament::messages.pair_revoked'),
            'screens' => $this->screensFor($event),
        ]);
    }

    /* ---------------- Scan-to-claim (organiser, session) ---------------- */

    /**
     * The page an organiser lands on after scanning an unpaired screen's code.
     *
     * The other way in is the event console, which pairs by code through the
     * shared HallScreenRouter. Both exist because an organiser in a hall is
     * sometimes holding a phone in front of the screen and sometimes sitting at
     * the console — and both apply the same three rules, in the same order.
     */
    public function claim(Request $request, string $code)
    {
        $device = ScreenDevice::pairable($code);

        abort_unless($device, 404);

        return view('event-bjj_tournament::screen.claim', [
            'device' => $device,
            'code' => $code,
            'events' => $this->manageableEvents($request->user()),
        ]);
    }

    /** Assign the scanned screen to one of the organiser's mats. */
    public function storeClaim(Request $request, string $code)
    {
        $device = ScreenDevice::pairable($code);

        abort_unless($device, 404);

        $data = $request->validate([
            'event' => ['required', 'string', 'size:36'],
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string', 'in:queue,bout,control,follow'],
        ]);

        $event = ClubEvent::where('uuid', $data['event'])->first();

        // Re-checked here, not trusted from the form that offered it: the list
        // is a convenience, this is the authorization.
        abort_unless($event && app(EventAccess::class)->canManage($event, $request->user()), 403);
        abort_unless($event->sport === self::SPORT, 404);

        $router = app(HallScreenRouter::class);
        $surface = $data['surface'] ?? 'follow';
        $court = trim($data['court']);

        abort_unless($court !== '', 422);
        abort_unless(in_array($surface, array_merge($router->surfaces($event), ['follow']), true), 422);

        if ($surface === 'control') {
            abort_unless(app(EventAccess::class)->canScore($event, $request->user()), 403);

            if ($router->existingControl($event, $court)) {
                return back()->withErrors([
                    'surface' => __('personal.event_screens_control_taken', ['court' => $court]),
                ])->withInput();
            }
        }

        $device->claim($event, $court, $request->user()->id, $surface === 'follow' ? null : $surface);

        ScreenChannel::notify($device, 'paired');
        $this->screensChanged($event);

        return redirect()
            ->route('bjj-screen.claimed', $device->id)
            ->with('status', __('event-bjj_tournament::messages.pair_done', [
                'court' => $device->court, 'event' => $event->title,
            ]));
    }

    /** The "done" page, so the organiser knows which screen they just placed. */
    public function claimed(Request $request, ScreenDevice $device)
    {
        abort_unless($device->event && app(EventAccess::class)->canManage($device->event, $request->user()), 403);

        return view('event-bjj_tournament::screen.claimed', ['device' => $device]);
    }

    /**
     * Events this user may point a screen at.
     *
     * BJJ only: a fleet serves one sport, and offering an organiser an event
     * this package cannot draw ends with a screen in a hall showing an error.
     *
     * @return \Illuminate\Support\Collection<int, array{uuid: string, title: string, courts: array<int, string>}>
     */
    private function manageableEvents(\App\Models\User $user)
    {
        $access = app(EventAccess::class);

        return ClubEvent::query()
            ->where('sport', self::SPORT)
            ->where('is_archived', false)
            ->whereDate('date', '>=', now()->subDay()->toDateString())
            ->orderBy('date')
            ->limit(50)
            ->get()
            ->filter(fn (ClubEvent $e) => $access->canManage($e, $user))
            ->map(fn (ClubEvent $e) => [
                'uuid' => $e->uuid,
                'title' => $e->title,
                // The mats this event actually runs on, taken from its own draw
                // — so the organiser picks from reality instead of typing a name
                // that will never match a match.
                'courts' => \App\Models\EventMatch::where('event_id', $e->id)
                    ->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->all(),
            ])
            ->values();
    }

    /* ---------------- Helpers ---------------- */

    /** @return array<int, array<string, mixed>> */
    private function screensFor(ClubEvent $event): array
    {
        return ScreenDevice::where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->orderBy('court')->orderBy('id')
            ->get()
            ->map(fn (ScreenDevice $d) => $d->present())
            ->all();
    }

    /**
     * Nudge every organiser watching this event's console.
     *
     * A refresh signal rather than a payload: the panel renders differently per
     * viewer (only someone who can score sees the scoring-table slot), so each
     * console re-fetches what IT may see. CLAUDE.md → Realtime / MQTT.
     */
    private function screensChanged(ClubEvent $event): void
    {
        $ids = $event->officials()->pluck('user_id')->all();

        if ($event->created_by) {
            $ids[] = $event->created_by;
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        if (! $ids) {
            return;
        }

        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => [
                'topic' => \Realtime()->userTopic($id, 'events'),
                'payload' => ['action' => 'screens', 'event' => $event->uuid],
            ],
            $ids,
        )), null, false);
    }
}
