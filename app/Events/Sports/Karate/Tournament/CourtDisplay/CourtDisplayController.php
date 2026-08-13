<?php

namespace App\Events\Sports\Karate\Tournament\CourtDisplay;

use App\Events\Sports\Karate\Tournament\Scoreboard\MatState;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\EventMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The two web surfaces the hall board needs.
 *
 * Neither of these is what the Pi will ultimately talk to — the device receives
 * its board over MQTT and renders a cached copy of this same Blade file. These
 * exist so the board can be built, reviewed and rehearsed by a human in a browser
 * before any hardware is involved, and so the fonts have somewhere to come from.
 */
class CourtDisplayController extends Controller
{
    public function __construct(private CourtDisplay $display) {}

    /**
     * Preview one court's board — organiser only.
     *
     * The Pi's own board is public-by-token (nobody is logged in to a wall), but
     * THIS route is a normal authenticated page and must stay that way: it takes
     * a court straight off the URL, so without the manage check it would be a
     * tidy way to read any event's running order by guessing a uuid.
     */
    public function preview(Request $request, ClubEvent $event, string $court)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $court = trim($court);
        abort_unless($court !== '' && mb_strlen($court) <= 40, 404);

        return view('event-karate_tournament::court-display.board', [
            'payload' => $this->display->payload($event, $court),
        ]);
    }

    /**
     * The board itself, as a paired screen sees it. No session, ever.
     *
     * This is the route the Raspberry Pi opens. A hall screen cannot sign in —
     * there is no keyboard, no person, and a logged-in session sitting on an
     * unattended machine in a public venue would be a worse thing to steal than
     * the board. The device's token IS its identity, and it is scoped to one
     * event and one court: there is no parameter here to tamper with, because
     * the URL carries no event and no court to begin with. Both are read off
     * the device record.
     */
    public function board(Request $request, string $token)
    {
        $device = CourtDisplayDevice::resolve($token);

        // One response for a bad token and a revoked screen — a wall screen is
        // scanned by whoever walks past it, and differing replies would tell
        // them which tokens are real.
        abort_unless($device, 404);

        $device->touchSeen();

        // Not yet told which mat it is: stand there showing the pairing code
        // until an organiser claims it. Also the state a screen returns to if
        // its event is deleted out from under it.
        if (! $device->isClaimed() || ! $device->event) {
            $device->ensurePairable();

            return view('event-karate_tournament::court-display.pairing', [
                'code' => $device->pairing_code,
                'token' => $token,
                'claimUrl' => route('karate-court-display.claim', $device->pairing_code),
                // So a screen standing on its code jumps to the board the moment
                // it is paired, instead of waiting out a throttled poll.
                'screenLink' => ScreenChannel::credentials($device),
            ]);
        }

        // The socket carries both surfaces, so it is built once here: payload_url
        // re-fetches the queue, state_url re-fetches the bout. A screen that
        // reconnects asks for whichever one it is currently showing.
        $link = ScreenChannel::credentials($device);
        $screenLink = $link ? $link + [
            'payload_url' => route('karate-court-display.payload', $token, false),
            'state_url' => route('karate-scoreboard.state', $token, false),
        ] : null;

        // A screen can be PINNED to one surface.
        //
        // By default a mat's screens follow the bout: the queue is what you show
        // when nothing is being fought, and it steps aside the moment one is.
        // That is right for the single board hanging over the mat, and wrong for
        // every other screen in a hall, because it makes each one show whatever
        // the mat happens to be doing rather than the job it was hung up to do:
        //
        //   surface=queue  the running order, always. The call-room screen, the
        //                  corridor screen, the one at the entrance. Its whole
        //                  purpose is "when am I on", and it must NOT blank out
        //                  into a scoreboard for the three minutes that question
        //                  matters most.
        //   surface=bout   the mat itself, always. Opens on the introduction and
        //                  the score, and sits on its own idle card between
        //                  bouts instead of turning into a second copy of the
        //                  queue board next to it.
        //
        // It rides in the URL rather than a column because that is where a
        // screen's identity already lives: the Pi opens one address forever, so
        // the pin survives a reboot with nothing to keep in step, and no
        // migration is needed to hang another board in a hall. It is also not a
        // privilege — the device is entitled to both surfaces and this only
        // chooses which of the two it draws, so a tampered value grants nothing
        // that the bare token did not already grant. Anything but these two
        // words means the default, following behaviour.
        $surface = in_array($request->query('surface'), ['queue', 'bout'], true)
            ? $request->query('surface')
            : null;

        // Is a bout on this mat right now? The scoring table decides, and the
        // answer outlives a reload because it lives in the cache — a screen that
        // reboots mid-bout comes back to the bout, not to the queue.
        $state = MatState::load($device->event, $device->court);

        if ($surface === 'bout' || ($surface === null && $state->mode !== MatState::MODE_UPCOMING)) {
            return view('event-karate_tournament::scoreboard.mat', [
                'event' => $device->event,
                'court' => $device->court,
                'state' => $state->toArray(),
                'screenLink' => $screenLink,
                // Told to the page so it refuses a running-order push meant for
                // a queue board, and stays on this surface between bouts.
                'pinned' => $surface === 'bout' ? 'bout' : false,
                // The host club's crest fills the design's dashed logo box.
                'eventLogo' => $device->event->tenant?->logo
                    ? asset('storage/'.$device->event->tenant->logo) : null,
            ]);
        }

        return view('event-karate_tournament::court-display.board', [
            'payload' => $this->display->payload($device->event, $device->court),
            // Told to the page so it can refuse to be dragged onto a bout by a
            // mat push, and so its own reloads keep the pin.
            'pinned' => $surface === 'queue' ? 'queue' : false,
            // Only a real device gets a heartbeat — the organiser's preview has
            // no token and must not mark any screen as alive.
            // The pin lives in this page's own URL, and reload() keeps it, so
            // the heartbeat needs no help to preserve it.
            'statusUrl' => route('karate-court-display.status', $token, false),
            // The board patches itself from payload_url rather than reloading,
            // so a finished bout never blanks the wall. Null when realtime is
            // off — then there is no socket to carry the nudge either.
            'screenLink' => $screenLink,
        ]);
    }

    /**
     * "Make this screen a display" — Karate's own, for Karate's own fleet.
     *
     * Separate from Taekwondo's for the same reason the whole fleet is: a
     * screen belongs to ONE package, and the address it was opened with is what
     * decides which. A browser that enrolled here can only ever be paired from
     * a Karate console, which is exactly the guarantee that keeps a Karate
     * screen from being handed a Taekwondo board.
     */
    public function screen()
    {
        return view('event-karate_tournament::court-display.screen');
    }

    /**
     * A screen asking for an identity on its very first boot.
     *
     * Nothing is written to the SD card ahead of time, so a Pi that has never
     * run has no token and no way to get one but to ask. What it gets back is
     * deliberately worthless on its own: an UNCLAIMED device, which can render
     * nothing but a pairing code until an authenticated organiser assigns it a
     * mat. There is no event here, no court, and no data — enrolling grants the
     * right to display a QR code and wait.
     *
     * That is what makes an open endpoint acceptable. The cost of abuse is rows
     * in a table, so it is throttled hard, and unclaimed devices are pruned.
     */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        ['device' => $device, 'token' => $token] = CourtDisplayDevice::begin($data['label'] ?? null);

        return response()->json([
            'token' => $token,
            'pairing_code' => $device->pairing_code,
            'board_url' => route('karate-court-display.board', $token),
        ], 201);
    }

    /**
     * "Have I been claimed yet?" — the pairing screen's only question.
     *
     * A JSON flag rather than re-fetching the board: the screen has no keyboard
     * and nobody watching it, so it has to notice being claimed by itself, and
     * this runs every few seconds on every Pi in the hall over a metered 4G
     * link. Sniffing the board's HTML for a marker would also be fragile in a
     * way that fails silently — the pairing page necessarily contains whatever
     * string it searches for, which is a reload loop waiting to happen.
     *
     * Carries a boolean and nothing else. An unpaired screen is a thing anyone
     * can see the code of, so this must not become a way to read an event.
     */
    public function status(Request $request, string $token)
    {
        $device = CourtDisplayDevice::resolve($token);

        abort_unless($device, 404);

        $device->touchSeen();

        return response()->json(['claimed' => $device->isClaimed() && $device->event !== null]);
    }

    /**
     * This screen's board, as JSON — the same payload the page was rendered
     * with, so it can redraw itself without reloading.
     *
     * A wall screen going black for ten seconds every time a bout ends is worse
     * than one that is slightly stale: the hall looks at it, and a blank panel
     * reads as broken. The board has always known how to patch itself in place
     * (window.CourtBoard.update); it only ever lacked somewhere to get fresh
     * numbers from.
     *
     * Carries exactly what the board already displays to a room full of people,
     * and the device's token decides which mat that is — there is no event and
     * no court in this URL to tamper with.
     */
    public function payload(Request $request, string $token)
    {
        $device = CourtDisplayDevice::resolve($token);

        abort_unless($device && $device->isClaimed() && $device->event, 404);

        $device->touchSeen();

        return response()->json($this->display->payload($device->event, $device->court));
    }

    /**
     * The screen's own realtime credentials, for the agent on the device.
     *
     * The board page carries these too, but a page cannot be relied on to act on
     * them: the board animates continuously and on a Pi 3B that saturates the
     * renderer, so an inbound socket message can sit for minutes behind paint
     * work. The agent is a separate process — nothing the browser does can starve
     * it — so it holds the subscription and restarts the display when the
     * assignment changes.
     *
     * Authenticated by the device token exactly as the board is, and it hands
     * back nothing the page did not already contain: a subscribe-only JWT for
     * one topic, which carries one word.
     */
    public function link(Request $request, string $token)
    {
        $device = CourtDisplayDevice::resolve($token);

        abort_unless($device, 404);

        $device->touchSeen();

        $credentials = ScreenChannel::credentials($device);

        // Realtime switched off is not an error: the agent falls back to asking
        // the status endpoint, which is slower and always works.
        abort_unless($credentials, 404);

        return response()->json($credentials);
    }

    /**
     * The page an organiser lands on after scanning a screen's QR.
     *
     * The code is printed on a wall in a public hall, so it is worth nothing on
     * its own: this route is authenticated, and every event offered is one the
     * signed-in user can actually manage. A spectator who scans the screen gets
     * a login page and, past it, an empty list.
     */
    public function claim(Request $request, string $code)
    {
        $device = CourtDisplayDevice::pairable($code);

        abort_unless($device, 404);

        return view('event-karate_tournament::court-display.claim', [
            'device' => $device,
            'code' => $code,
            'events' => $this->manageableEvents($request->user()),
        ]);
    }

    /** Assign the scanned screen to one of the organiser's mats. */
    public function storeClaim(Request $request, string $code)
    {
        $device = CourtDisplayDevice::pairable($code);

        abort_unless($device, 404);

        $data = $request->validate([
            'event' => ['required', 'string', 'size:36'],
            'court' => ['required', 'string', 'max:40'],
        ]);

        $event = ClubEvent::where('uuid', $data['event'])->first();

        // Re-checked here, not trusted from the form that offered it: the list
        // is a convenience, this is the authorization.
        abort_unless($event && app(EventAccess::class)->canManage($event, $request->user()), 403);

        $device->claim($event, trim($data['court']), $request->user()->id);

        return redirect()
            ->route('karate-court-display.claimed', $device->id)
            ->with('status', __('event-karate_tournament::messages.pair_done', [
                'court' => $device->court, 'event' => $event->title,
            ]));
    }

    /**
     * The screens paired to this event — the console's own list.
     *
     * Same data the page rendered with, re-fetched after a pairing or a revoke
     * so the section updates in place, and on a realtime nudge when another
     * organiser pairs a screen at the same venue.
     */
    public function screens(Request $request, ClubEvent $event)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        return response()->json([
            'success' => true,
            'screens' => $this->screensFor($event),
        ]);
    }

    /**
     * Pair a scanned screen to THIS event and a mat on it.
     *
     * The organiser scans the QR on the wall from inside the event they are
     * running, so the event is not a choice here — it comes from the URL and is
     * authorized on its own. That is the whole difference from `storeClaim`,
     * where the scan arrives from a phone's camera app with no event in hand and
     * the organiser must pick one.
     *
     * The pairing code is public by nature — it is printed a metre tall on a
     * wall — so it is not the credential. The credential is this route's session
     * plus canManage on this event. A spectator who scans the same screen gets a
     * login page, and past it, a 403.
     */
    public function pair(Request $request, ClubEvent $event)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{6}$/'],
            'court' => ['required', 'string', 'max:40'],
        ]);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        $device = CourtDisplayDevice::pairable($data['code']);

        // One answer for "no such code", "already claimed" and "revoked". The
        // code is six characters and readable across a hall, so distinguishing
        // them would turn this into a way to probe which screens exist.
        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => __('event-karate_tournament::messages.pair_unknown'),
            ], 404);
        }

        $device->claim($event, $court, $request->user()->id);

        // The screen is standing in front of somebody showing a QR code — it
        // should become the board now, not on its next throttled poll.
        ScreenChannel::notify($device, 'paired');

        $this->screensChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('event-karate_tournament::messages.pair_done', [
                'court' => $device->court, 'event' => $event->title,
            ]),
            'screen' => $device->present(),
            'screens' => $this->screensFor($event),
        ]);
    }

    /**
     * Stop a screen showing this event and send it back to its pairing code.
     *
     * Scoped to the event in the URL, so an organiser can only unpair screens on
     * an event they manage — never one belonging to somebody else's competition.
     *
     * Unclaims rather than revokes: the Pi keeps its token, notices on its next
     * heartbeat that it is no longer claimed, and comes back showing a fresh
     * code ready for another mat. Revoking would kill the token, and the agent
     * only enrols when its token file is empty — the screen would sit on a 404
     * until somebody took the SD card out. That destructive path stays where it
     * belongs, on `court:pair --revoke`, for a device that is lost or stolen.
     */
    public function revokeScreen(Request $request, ClubEvent $event, int $device)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $screen = CourtDisplayDevice::where('event_id', $event->id)->find($device);

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
            'message' => __('event-karate_tournament::messages.pair_revoked'),
            'screens' => $this->screensFor($event),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function screensFor(ClubEvent $event): array
    {
        return CourtDisplayDevice::where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->orderBy('court')->orderBy('id')
            ->get()
            ->map(fn (CourtDisplayDevice $d) => $d->present())
            ->all();
    }

    /**
     * Nudge the other people running this event.
     *
     * A refresh signal, not the screens themselves: whoever receives it re-fetches
     * through `screens()` and is authorized there. Nothing about the hall's
     * hardware rides on the wire, and a competitor on the same event channel —
     * who also receives `events` messages — learns nothing from it.
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

    /** Confirmation, so the organiser knows the wall screen has changed. */
    public function claimed(Request $request, CourtDisplayDevice $device)
    {
        abort_unless($device->event && app(EventAccess::class)->canManage($device->event, $request->user()), 403);

        return view('event-karate_tournament::court-display.claimed', ['device' => $device]);
    }

    /**
     * Events this user may point a screen at.
     *
     * @return \Illuminate\Support\Collection<int, array{uuid: string, title: string, courts: array<int, string>}>
     */
    private function manageableEvents(\App\Models\User $user)
    {
        $access = app(EventAccess::class);

        return ClubEvent::query()
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
                // that will never match a bout.
                'courts' => EventMatch::where('event_id', $e->id)
                    ->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->all(),
            ])
            ->values();
    }

    /**
     * A packaged font file.
     *
     * The fonts live inside the package (deleting the directory takes them with
     * it), which puts them outside the web root — so they are served here rather
     * than published. The name is matched against a strict whitelist pattern and
     * then resolved with basename(): the path is built by us, never by the
     * request, so no traversal input can reach the filesystem.
     */
    public function font(string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]+\.woff2$/', $file), 404);

        $path = __DIR__.'/resources/fonts/'.basename($file);
        abort_unless(is_file($path), 404);

        return Response::file($path, [
            'Content-Type' => 'font/woff2',
            // Immutable: a face never changes under a given filename, and the
            // board should never re-fetch one over a metered 4G link.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
