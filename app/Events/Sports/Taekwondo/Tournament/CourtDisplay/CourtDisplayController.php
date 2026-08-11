<?php

namespace App\Events\Sports\Taekwondo\Tournament\CourtDisplay;

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

        return view('event-taekwondo_tournament::court-display.board', [
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

            return view('event-taekwondo_tournament::court-display.pairing', [
                'code' => $device->pairing_code,
                'token' => $token,
                'claimUrl' => route('court-display.claim', $device->pairing_code),
            ]);
        }

        return view('event-taekwondo_tournament::court-display.board', [
            'payload' => $this->display->payload($device->event, $device->court),
        ]);
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
            'board_url' => route('court-display.board', $token),
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

        return view('event-taekwondo_tournament::court-display.claim', [
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
            ->route('court-display.claimed', $device->id)
            ->with('status', __('event-taekwondo_tournament::messages.pair_done', [
                'court' => $device->court, 'event' => $event->title,
            ]));
    }

    /** Confirmation, so the organiser knows the wall screen has changed. */
    public function claimed(Request $request, CourtDisplayDevice $device)
    {
        abort_unless($device->event && app(EventAccess::class)->canManage($device->event, $request->user()), 403);

        return view('event-taekwondo_tournament::court-display.claimed', ['device' => $device]);
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
