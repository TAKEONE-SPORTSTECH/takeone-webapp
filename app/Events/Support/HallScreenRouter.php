<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use Illuminate\Http\Request;

/**
 * Send a console's hall-screen request to the package that owns that event.
 *
 * The event console's three screen endpoints — list, pair, unpair — were wired
 * straight to the Taekwondo controller, for every event of every sport. That
 * was invisible while Taekwondo was the only sport with wall screens. The
 * moment Karate got its own fleet, its own table and its own Pi build, pairing
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
    private const OWNERS = [
        'taekwondo' => \App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class,
        'karate' => \App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class,
    ];

    public function screens(Request $request, ClubEvent $event)
    {
        return $this->to($event)->screens($request, $event);
    }

    public function pair(Request $request, ClubEvent $event)
    {
        // A code from the sport-neutral waiting room (/screen) — the address a
        // television is opened at. The console must accept these as readily as
        // it accepts a Pi's own code: an organiser holding a phone in a hall
        // does not know, and must not need to know, which of the two kinds of
        // screen is in front of them. Without this the console answered "that
        // code does not match a screen waiting to be paired" for a screen that
        // was very plainly waiting, three metres away.
        if ($pending = PendingScreen::pairable((string) $request->input('code'))) {
            return $this->pairPending($request, $event, $pending);
        }

        // A device already in this event's own fleet — a Pi that enrolled with
        // the package directly.
        return $this->to($event)->pair($request, $event);
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

        $data = $request->validate([
            'court' => ['required', 'string', 'max:40'],
            'surface' => ['required', 'string', 'in:bout,queue,control'],
        ]);

        $court = trim($data['court']);
        abort_unless($court !== '', 422);

        if (! in_array($data['surface'], $this->surfaces($event), true)) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_screens_surface_unavailable'),
            ], 422);
        }

        if ($data['surface'] === 'control') {
            abort_unless(app(EventAccess::class)->canScore($event, $request->user()), 403);

            if ($this->existingControl($event, $court)) {
                return response()->json([
                    'success' => false,
                    'message' => __('personal.event_screens_control_taken', ['court' => $court]),
                ], 422);
            }
        }

        ['device' => $device, 'url' => $url] = $this->adopt($event, $court, $data['surface'], $request->user()->id);

        // The waiting row is spent; the screen polls and moves on by itself.
        $pending->settle($url);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_screens_claimed', ['court' => $court, 'event' => $event->title]),
            'screen' => $device->present(),
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
    ];

    public function surfaces(ClubEvent $event): array
    {
        return self::SURFACES[(string) $event->sport] ?? [];
    }

    /**
     * The address to open ON a screen so it joins THIS event's fleet.
     *
     * The console shows it, as text and as a QR, because otherwise the operator
     * has to know that a Karate screen enrols at a different address from a
     * Taekwondo one — which is a fact about our storage, not about their hall.
     * Getting it wrong is silent until the moment they try to pair, and then
     * reads as "that code does not match a screen waiting to be paired", which
     * points at the code and not at the real mistake.
     */
    public function newScreenUrl(ClubEvent $event): ?string
    {
        return match ((string) $event->sport) {
            'taekwondo' => route('court-display.new'),
            'karate' => route('karate-court-display.new'),
            default => null,
        };
    }

    /**
     * The device models behind each fleet, so a claim can create one.
     */
    private const DEVICES = [
        'taekwondo' => \App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayDevice::class,
        'karate' => \App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayDevice::class,
    ];

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
        $model = self::DEVICES[(string) $event->sport] ?? null;

        abort_unless($model, 404);

        ['device' => $device, 'token' => $token] = $model::issue($event, $court, $by, 'Screen');

        $device->forceFill(['surface' => in_array($surface, $this->surfaces($event), true) ? $surface : null])->save();

        return [
            'device' => $device,
            // A PATH, not an absolute URL. The screen resolves it against its
            // own origin, so a claim made from a phone on a different host — or
            // from a console command, where there is no host at all — can never
            // send a television to the wrong one.
            'url' => $event->sport === 'karate'
                ? route('karate-court-display.board', $token, false)
                : route('court-display.board', $token, false),
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
        $model = self::DEVICES[(string) $event->sport] ?? null;

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
        $owner = self::OWNERS[(string) $event->sport] ?? null;

        abort_unless($owner, 404);

        return app($owner);
    }
}
