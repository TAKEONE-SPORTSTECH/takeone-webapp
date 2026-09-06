<?php

namespace App\Events\Support\Cameras;

use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The camera panel at the SCORING TABLE, through both of the console's doors.
 *
 * The event console already lists an event's cameras for an organiser at a
 * laptop. This is the same fleet asked about from the other end: one mat, by
 * the person whose tablet is starting and stopping those very cameras. They
 * need three answers the event console does not put in front of them — is this
 * phone going to last the session, did last bout's video ever leave it, and can
 * I change how it is filming without walking over to the tripod.
 *
 * ── Two doors, one body ────────────────────────────────────────────────────
 *
 * The console itself has two front doors and this must have the same two, or
 * the panel only exists for whoever happened to open the page one particular
 * way:
 *
 *   · A SIGNED-IN organiser, by event uuid → the routes on this controller,
 *     authorised with EventAccess::canScore, exactly like the console page.
 *   · A PAIRED SCORING TABLE, by device token → each sport's own
 *     ScoreboardController, which resolves its device and then calls the two
 *     static entry points below. The token door does its own re-check on every
 *     request (the pairing organiser must STILL be able to score), so nothing
 *     here needs to know which door it came through.
 *
 * ── What bounds it, either way ─────────────────────────────────────────────
 *
 *   · ONE mat. The court is resolved on the server — from the device's own row
 *     at the token door, and checked against the event's mats at the signed-in
 *     one. A console on Mat 2 cannot reach Mat 1's cameras by editing an id.
 *   · Scoring authority, not management. Whoever may run this mat may run the
 *     lenses pointed at it; whoever may not, gets a 403 here and cannot see so
 *     much as a battery percentage.
 *   · Reads are a snapshot of what the phones last REPORTED. Nothing in this
 *     controller waits on a device — a camera asleep in a bag must never be
 *     able to hang the scoring table's panel.
 */
class MatCameraController extends Controller
{
    /** The signed-in door: every camera on one of this event's mats. */
    public function index(Request $request, ClubEvent $event): JsonResponse
    {
        $court = $this->court($request, $event);

        return response()->json(MatCameras::panel($event, $court));
    }

    /** The signed-in door: an order to one camera on one mat. */
    public function command(Request $request, ClubEvent $event, int $camera): JsonResponse
    {
        return static::apply($request, $event, $this->court($request, $event), $camera);
    }

    /**
     * The mat this request is about, having proved the caller may run it.
     *
     * The court is validated against the event's OWN mats rather than taken as
     * given: `court` is a free-text column, and an unchecked one would let a
     * request name a mat that does not exist and read back an empty panel — a
     * probe that answers, which is exactly what the anti-enumeration rule is
     * about.
     */
    private function court(Request $request, ClubEvent $event): string
    {
        $user = $request->user();

        abort_unless($user && app(EventAccess::class)->canScore($event, $user), 403);

        $mats = $event->matches()->whereNotNull('court')->distinct()->pluck('court');
        $court = (string) $request->input('mat', $mats->first() ?? '');

        abort_unless($court !== '' && ($mats->isEmpty() || $mats->contains($court)), 404);

        return $court;
    }

    /**
     * One order to one camera — the body BOTH doors run.
     *
     * The event and the court arrive already resolved and already authorised.
     * Everything the request itself supplies is validated here and nowhere
     * else, so the two doors cannot drift into two different vocabularies.
     */
    public static function apply(Request $request, ClubEvent $event, string $court, int $cameraId): JsonResponse
    {
        $data = $request->validate([
            'do' => ['required', 'string', 'in:settings,upload,play,purge,delete,wipe,report'],

            // Footage orders. `clip` is a phone's own handle for a file, or the
            // word "all"; it is passed through to the device and never used to
            // build a path here.
            'clip' => ['nullable', 'string', 'max:190'],
            // Which footage a wipe may take. `uploaded` is the only scope that
            // is safe by construction; `all` is a deliberate, confirmed act by
            // somebody who has been told what it costs — and the PHONE still
            // decides, because the files are there and not here.
            'scope' => ['nullable', 'string', 'in:uploaded,all'],

            // How the camera films.
            'settings' => ['nullable', 'array'],
            'settings.fps' => ['nullable', 'integer', 'in:30,60'],
            'settings.zoom' => ['nullable', 'numeric', 'between:1,10'],
            'settings.exposure' => ['nullable', 'numeric', 'between:-4,4'],
            'settings.auto_upload' => ['nullable', 'boolean'],
        ]);

        $camera = MatCameras::find($event, $court, $cameraId);

        // The same answer for a camera on another mat, a revoked one, and one
        // that never existed. A console must not be able to learn what is
        // parked on the next mat by trying ids.
        abort_unless($camera, 404);

        if ($data['do'] === 'settings') {
            $settings = MatCameras::clean($data['settings'] ?? []);

            abort_if($settings === [], 422);

            return response()->json([
                'success' => true,
                'camera' => MatCameras::configure($camera, $settings),
            ]);
        }

        if ($data['do'] === 'report') {
            MatCameras::ping($camera);

            return response()->json(['success' => true, 'asked' => 'report']);
        }

        // A wipe with no scope is the safe one. Never the other way round: the
        // default for an order that destroys competition footage has to be the
        // one that cannot destroy the only copy of a bout.
        if ($data['do'] === 'wipe') {
            $data['scope'] = $data['scope'] ?? 'uploaded';
        }

        MatCameras::footage($camera, $data + ['action' => $data['do']]);

        return response()->json([
            'success' => true,
            'asked' => $data['do'],
            // What was ASKED, never what happened: this is a message to a phone
            // on a hall's wifi. What happened arrives on the camera's next beat.
            'message' => __('events.mat_camera_asked'),
        ]);
    }

    /** The panel body both doors run. */
    public static function panelFor(ClubEvent $event, string $court): JsonResponse
    {
        return response()->json(MatCameras::panel($event, $court));
    }
}
