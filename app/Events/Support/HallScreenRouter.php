<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Scoreboard\Fleet;
use Illuminate\Http\Request;

/**
 * Send a console's hall-screen request to the package that owns that event.
 *
 * The event console's three screen endpoints — list, pair, unpair — were wired
 * straight to the Taekwondo controller, for every event of every sport. That
 * was invisible while Taekwondo was the only sport with wall screens. The
 * moment Karate got its own fleet, its own table and its own screen build, pairing
 * a screen from a KARATE console wrote a TAEKWONDO device row pointing at a
 * Karate event: a screen that then resolved against the wrong package, could
 * not be served, and could not be unpaired from the panel that made it.
 *
 * The two implementations are deliberately separate (see the Karate routes
 * block — one token set over one table could hand a Karate screen a Taekwondo
 * board). What was missing is the thing that decides WHICH, and this is it:
 * one dispatcher, resolving from the event's own sport, so adding a third sport
 * with screens is a line here rather than a fork in every caller.
 */
class HallScreenRouter
{
    /**
     * Controllers that own a hall-screen fleet, by sport.
     *
     * A sport that is not listed has no wall screens, and its console never
     * renders the panel — so a request reaching here for one is a 404, not a
     * silent fall-through to somebody else's fleet.
     */
    // The sport => controller map that used to live here is GONE. Every mat now
    // lives in App\Scoreboard, whose controllers are private to it, and this
    // class reaches exactly one thing: App\Scoreboard\Fleet. That is the whole
    // point of the split — a change to a screen controller's signature is no
    // longer a change to a file in the events module.

    public function screens(Request $request, ClubEvent $event)
    {
        return $this->to($event)->screens($request, $event);
    }

    public function pair(Request $request, ClubEvent $event)
    {
        /*
         * Scanned from the OTHER TAKEONE host.
         *
         * The console spots this before it submits — it holds the URL, this
         * server only ever sees six characters — but it reports it here rather
         * than refusing quietly, because a refusal nobody records is a failure
         * nobody can help with. This is answered first: the code is guaranteed
         * to resolve to nothing here, and "nothing on this server" is a true but
         * useless thing to tell somebody who is plainly reading it off a screen.
         */
        $from = strtolower(trim((string) $request->input('from_host')));

        if ($from !== '' && $from !== strtolower($request->getHost()) && preg_match('/^[a-z0-9.-]{1,253}$/', $from)) {
            $told = __('personal.event_screens_other_host', ['there' => $from, 'here' => $request->getHost()]);

            PairingLog::refused($request, $event, 'code_from_the_other_server', $told);

            return response()->json(['success' => false, 'message' => $told], 422);
        }

        // A CAMERA, scanned into the same panel. The hall's wiring is one
        // question — "what is on Mat 2?" — and the answer includes the phones
        // filming it, so the console pairs them through the same door rather
        // than through a second one that happens to look the same.
        //
        // Which KIND of device this is comes from the code, not from the slot
        // the organiser pressed: a code either belongs to a waiting screen or
        // to a waiting camera, and no phone can be talked into being a
        // scoreboard by pairing it into one.
        if ($camera = \App\Models\EventCamera::pairable((string) $request->input('code'))) {
            return $this->pairCamera($request, $event, $camera);
        }

        // A camera position was pressed and the code matched no waiting camera.
        //
        // The honest answer is "this code is not waiting for anything", not
        // "this is a screen" — the code may belong to a screen, or to a camera
        // whose identity no longer exists on this server, or to nothing at all,
        // and this cannot tell those apart. Claiming the wrong one sent people
        // looking for a problem they did not have.
        if ($request->input('surface') === 'camera') {
            $isScreen = PendingScreen::pairable((string) $request->input('code')) !== null;
            $told = $isScreen
                ? __('personal.event_cameras_not_a_camera', ['host' => $request->getSchemeAndHttpHost()])
                : __('personal.event_cameras_code_stale');

            PairingLog::refused($request, $event, $isScreen ? 'camera_slot_got_a_screen' : 'camera_code_unknown', $told);

            return response()->json([
                'success' => false,
                'message' => PendingScreen::pairable((string) $request->input('code'))
                    // A code from the neutral waiting room, offered for a camera
                    // slot. It is not a mistake worth scolding: /screen is the
                    // one address a person is told to open, and nothing on it
                    // says that filming the mat needs a different app. So the
                    // message carries the answer, with this host in it — the
                    // code is only ever valid on the server that issued it.
                    ? __('personal.event_cameras_not_a_camera', ['host' => $request->getSchemeAndHttpHost()])
                    : __('personal.event_cameras_code_stale'),
            ], 422);
        }

        // A code from the sport-neutral waiting room (/screen) — the address a
        // television is opened at. The console must accept these as readily as
        // it accepts a screen's own code: an organiser holding a phone in a hall
        // does not know, and must not need to know, which of the two kinds of
        // screen is in front of them. Without this the console answered "that
        // code does not match a screen waiting to be paired" for a screen that
        // was very plainly waiting, three metres away.
        if ($pending = PendingScreen::pairable((string) $request->input('code'))) {
            return $this->pairPending($request, $event, $pending);
        }

        // A device already in this event's own fleet — a screen that enrolled with
        // the package directly.
        $own = $this->to($event)->pair($request, $event);

        if ($own->getStatusCode() < 400) {
            return $own;
        }

        /*
         * Not this sport's screen — but is it ANOTHER sport's?
         *
         * A screen that enrolled through a package's own door lives in that
         * package's table and polls that package's status endpoint, which
         * answers `{claimed}` and nothing else. There is nowhere to tell it "go
         * to a different sport's board", so it genuinely cannot be adopted here
         * — but "that code does not match a screen waiting to be paired" is a
         * lie about why, and it sent somebody hunting a spent code that was
         * sitting on the wall in front of them, unspent.
         *
         * So say the actual thing: this screen belongs to another fleet, and the
         * way out is to open it at the sport-neutral address, where the EVENT
         * decides which board it becomes.
         */
        $code = (string) $request->input('code');

        foreach (Fleet::fleets() as $sport => $model) {
            if ($sport === (string) $event->sport || ! class_exists($model)) {
                continue;
            }

            if ($model::pairable($code)) {
                PairingLog::refused($request, $event, 'code_in_another_sports_fleet',
                    __('personal.event_screens_other_fleet'));

                return response()->json([
                    'success' => false,
                    'message' => __('personal.event_screens_other_fleet'),
                ], 422);
            }
        }

        return $own;
    }

    /**
     * Adopt a waiting screen into this event, from the console.
     *
     * The same three rules the scanned form applies, because the two doors must
     * not disagree: the job has to be one this package can serve, a scoring
     * table takes the right to SCORE and not merely to manage, and there is
     * only ever one scoring table per mat.
     */
    private function pairPending(Request $request, ClubEvent $event, PendingScreen $pending)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        /*
         * Does this event have wall boards at all?
         *
         * The EVENT TYPE is the authority on that — it is the thing that either
         * has screens to drive or does not — and asking it here is what makes
         * /screen work for every sport rather than the three this router happens
         * to hold a device class for. Without it, an event whose sport has no
         * fleet failed further down on "that surface is not available", which
         * describes a surface problem and sends somebody looking for a setting
         * that was never the issue.
         */
        if (app(\App\Events\EventTypeRegistry::class)->for($event)->hallScreens($event) === null) {
            PairingLog::refused($request, $event, 'event_has_no_wall_screens',
                __('personal.event_screens_unsupported', ['event' => $event->title]));

            return response()->json([
                'success' => false,
                'message' => __('personal.event_screens_unsupported', ['event' => $event->title]),
            ], 422);
        }

        /*
         * The same surfaces the packages' own pair endpoints accept.
         *
         * This path demanded one of bout/queue/control and rejected everything
         * else with a validation error, while a package's own door accepted
         * `follow` and treated a missing surface as `follow`. So the identical
         * code, entered from a slot that had not settled on a surface, paired
         * through one door and 422'd through the other — and a validation error
         * is the one failure the console cannot explain, because it has no
         * message of its own to show.
         *
         * `follow` means "whatever this mat is showing"; adopt() stores it as no
         * surface at all, which is exactly that.
         */
        $data = $request->validate([
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string', 'in:bout,queue,control,follow'],
        ]);

        $data['surface'] = $data['surface'] ?: 'follow';

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        if ($data['surface'] !== 'follow' && ! in_array($data['surface'], $this->surfaces($event), true)) {
            PairingLog::refused($request, $event, 'surface_not_offered_by_this_event', __('personal.event_screens_surface_unavailable'));

            return response()->json([
                'success' => false,
                'message' => __('personal.event_screens_surface_unavailable'),
            ], 422);
        }

        if ($data['surface'] === 'control') {
            abort_unless(app(EventAccess::class)->canScore($event, $request->user()), 403);

            if ($this->existingControl($event, $court)) {
                PairingLog::refused($request, $event, 'mat_already_has_a_scoring_table', __('personal.event_screens_control_taken'));

                return response()->json([
                    'success' => false,
                    'message' => __('personal.event_screens_control_taken', ['court' => $court]),
                ], 422);
            }
        }

        // Spend the code BEFORE adopting, and only proceed if this request is
        // the one that spent it. Two requests carrying the same code (a
        // double-tapped Pair, two consoles) both used to pass the read above and
        // both created a screen — one live board and one orphan device that no
        // panel could show and nobody could unpair.
        if (! $pending->spend()) {
            PairingLog::refused($request, $event, 'code_already_used', __('personal.event_screens_code_spent'));

            return response()->json([
                'success' => false,
                'message' => __('personal.event_screens_code_spent'),
            ], 409);
        }

        ['device' => $device, 'url' => $url] = $this->adopt($event, $court, $data['surface'], $request->user()->id);

        PairingLog::paired($request, $event, 'screen', $court, ['surface' => $data['surface']]);

        // The waiting row now knows where to send the screen; it polls and moves
        // on by itself.
        $pending->land($url);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_screens_claimed', ['court' => $court, 'event' => $event->title]),
            'screen' => $device->present(),
            'screens' => $this->screensList($request, $event),
        ]);
    }

    /**
     * Put a scanned phone on a mat as one of its cameras.
     *
     * Deliberately NOT dispatched to a sport: a camera fleet is sport-neutral,
     * because pointing a lens at a mat needs nothing from the rules being
     * fought under it. Managing the event is the right that matters — a camera
     * records, it cannot score, so unlike a scoring table it does not also
     * require the right to score.
     */
    private function pairCamera(Request $request, ClubEvent $event, \App\Models\EventCamera $camera)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $data = $request->validate([
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['nullable', 'string'],
        ]);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        // Pressed a board's slot and scanned a camera: say so rather than
        // quietly adopting it as something else. The mat is right, the row is
        // not, and the organiser is standing there with the phone in hand.
        if (($data['surface'] ?? 'camera') !== 'camera') {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_cameras_is_a_camera'),
            ], 422);
        }

        $angle = \App\Events\Support\Cameras\CameraFleet::nextAngle($event, $court);

        if ($angle === null) {
            return response()->json([
                'success' => false,
                'message' => __('events.camera_claim_full', ['court' => $court]),
            ], 422);
        }

        $camera->claim($event, $court, $angle, $request->user()->id);
        \App\Events\Support\Cameras\CameraFleet::notify($camera, 'paired');
        \App\Events\Support\Cameras\CameraFleet::consolesChanged($event);

        return response()->json([
            'success' => true,
            'message' => __('events.camera_claim_done', [
                'angle' => $angle, 'court' => $court, 'event' => $event->title,
            ]),
            'cameras' => \App\Events\Support\Cameras\CameraFleet::console($event)['cameras'] ?? [],
            'screens' => $this->screensList($request, $event),
        ]);
    }

    /** The event's screens as the console lists them, from the owning package. */
    private function screensList(Request $request, ClubEvent $event): array
    {
        $body = json_decode($this->to($event)->screens($request, $event)->getContent(), true);

        return $body['screens'] ?? [];
    }

    public function revoke(Request $request, ClubEvent $event, int $device)
    {
        return $this->to($event)->revokeScreen($request, $event, $device);
    }

    /**
     * Which surfaces this event's screens can actually be.
     *
     * The panel asks before it offers: a slot an organiser can fill but the
     * package cannot serve is worse than no slot at all — it ends with a screen
     * in a hall showing an error and no way back.
     *
     * Both fleets now serve all three. A sport that gains wall screens without
     * a token-scoped scoring table must say so here, or an organiser will pair
     * a console the package cannot open.
     */
    private const SURFACES = [
        'taekwondo' => ['bout', 'queue', 'control'],
        'karate' => ['bout', 'queue', 'control'],
        'bjj' => ['bout', 'queue', 'control'],
    ];

    public function surfaces(ClubEvent $event): array
    {
        return self::SURFACES[(string) $event->sport] ?? [];
    }

    /**
     * The address to open ON a screen so it can be paired to this event.
     *
     * ONE address, for every sport and every job: `/screen`. It used to be a
     * per-sport enrolment URL, which meant the operator had to know that a
     * Karate screen enrols somewhere different from a Taekwondo one — a fact
     * about our storage, not about their hall. Getting it wrong was silent
     * until the moment they tried to pair, and then read as "that code does not
     * match a screen waiting to be paired", which points at the code and not at
     * the real mistake.
     *
     * Nothing is lost by unifying it: a screen at `/screen` is sport-neutral
     * until it is claimed, and `pair()` above adopts a waiting code into THIS
     * event's fleet — deciding sport, mat and surface at the one moment an
     * organiser is actually looking at the screen. The old doors redirect here.
     *
     * Still nullable: a sport with no fleet has no panel, so it gets no address
     * rather than an address that cannot lead anywhere.
     */
    public function newScreenUrl(ClubEvent $event): ?string
    {
        return app(Fleet::class)->runs($event->sport)
            ? route('screen.new')
            : null;
    }

    /**
     * The fleets, for anything that must ask every one of them a question.
     *
     * Kept as a passthrough because PairingLog and the console already call it
     * here; the list itself belongs to the module that owns the mats.
     *
     * @return array<string, class-string>
     */
    public static function fleets(): array
    {
        return Fleet::fleets();
    }

    /**
     * Turn a waiting screen into a real one on this event's mat.
     *
     * Issues a device in the OWNING package's fleet — a fresh token, because the
     * pending token belongs to the waiting room and must not follow the screen
     * into a fleet where it means nothing — and hands back the board address to
     * send the screen to.
     *
     * @return array{url: string, device: \Illuminate\Database\Eloquent\Model}
     */
    public function adopt(ClubEvent $event, string $court, string $surface, ?int $by): array
    {
        $model = Fleet::deviceFor($event->sport);

        abort_unless($model, 404);

        ['device' => $device, 'token' => $token] = $model::issue($event, $court, $by, 'Screen');

        $device->forceFill(['surface' => in_array($surface, $this->surfaces($event), true) ? $surface : null])->save();

        return [
            'device' => $device,
            // A PATH, not an absolute URL. The screen resolves it against its
            // own origin, so a claim made from a phone on a different host — or
            // from a console command, where there is no host at all — can never
            // send a television to the wrong one.
            'url' => route(
                Fleet::boardRoute($event->sport) ?? 'court-display.board',
                $token,
                false,
            ),
        ];
    }

    /**
     * The screen already doing this job on this mat, if there is one.
     *
     * Only asked about SCORE CONTROL. A hall hangs as many scoreboards and as
     * many upcoming-matches boards as it has walls — the corridor, the call
     * room, both ends of the mat — and every one of them is drawing the same
     * thing, so more is simply more. The scoring table is the opposite: it is
     * the mat's single source of truth, and two of them means two officials
     * entering different results into the same bout with no way for either to
     * see the other. One per mat, enforced here rather than left to a
     * convention nobody can see.
     */
    public function existingControl(ClubEvent $event, string $court)
    {
        $model = Fleet::deviceFor($event->sport);

        if (! $model) {
            return null;
        }

        return $model::where('event_id', $event->id)
            ->where('court', $court)
            ->where('surface', 'control')
            ->whereNull('revoked_at')
            ->first();
    }

    private function to(ClubEvent $event)
    {
        /*
         * One door, for every sport. A sport with no mat is a 404 here rather
         * than a silent fall-through to somebody else's fleet — the mistake this
         * class was written to stop.
         */
        $fleet = app(Fleet::class);

        abort_unless($fleet->runs($event->sport), 404);

        return $fleet;
    }
}
