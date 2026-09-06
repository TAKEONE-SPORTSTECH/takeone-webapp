<?php

namespace App\Events\Support\Cameras;

use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\EventCamera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organiser's side of the camera fleet: see them, and take one off a mat.
 *
 * Two endpoints, both authenticated as a person who manages the event — the
 * mirror image of CameraController, which is authenticated as a device holding
 * a token. Nothing here is reachable by a camera, and nothing there is
 * reachable by a browser.
 */
class CameraConsoleController extends Controller
{
    /** Every camera on this event, for the console panel. */
    public function index(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        return response()->json(CameraFleet::console($event) ?? ['mats' => [], 'cameras' => [], 'max' => CameraFleet::MAX_PER_COURT]);
    }

    /**
     * Switch one camera's feed on or off.
     *
     * The decision the app deliberately took away from whoever holds the phone
     * — and then gave to nobody, so a paired camera broadcast until it was
     * unpaired. It belongs here: the person who knows whether this mat should be
     * watchable from outside the hall is at the scoring table.
     *
     * Intent only. The stream goes live when the phone publishes and the media
     * server sees it arrive; switching off writes the order, tells the phone at
     * once over its own channel, and ends the stream this side. A phone that
     * missed the message reads it on its next beat, and a phone on an old build
     * that asks to publish anyway is refused the credential (CameraController).
     */
    /**
     * Ask a camera to do something with footage it is already holding.
     *
     * Three asks, and the phone is the one that answers them:
     *
     *   upload  send a bout now — the manual counterpart to the automatic
     *           upload a camera already performs on hall wifi, and the way to
     *           retry one that failed.
     *   purge   free space. The phone refuses anything it cannot prove the
     *           server holds, because an un-uploaded bout is the only copy of
     *           a fight that happened once. This endpoint cannot override that
     *           and is not meant to: the console asks, the files decide.
     *   play    put a clip on the phone's own screen, for somebody standing at
     *           the mat. Useless while the handset is asleep, which is why the
     *           video is better watched where it has been uploaded to.
     *
     * `clip` names one recording by its local ref, or `all`. Omitted means the
     * most recent, which is what "that bout" means to somebody at the mat.
     *
     * Best-effort by nature — this is a message to a phone on a hall's wifi,
     * not a transaction. The answer says what was ASKED, never what happened;
     * what happened arrives on the camera's next beat.
     */
    public function footage(Request $request, ClubEvent $event, int $camera): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $data = $request->validate([
            'action' => ['required', 'string', 'in:upload,purge,play'],
            'clip' => ['nullable', 'string', 'max:120'],
        ]);

        $device = EventCamera::where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->find($camera);

        abort_unless($device, 404);

        CameraChannel::send($device, [
            'action' => $data['action'],
            'clip' => $data['clip'],
            'at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'asked' => $data['action'],
            'message' => __('personal.event_cameras_footage_'.$data['action']),
        ]);
    }

    public function broadcast(Request $request, ClubEvent $event, int $camera): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        $data = $request->validate(['on' => ['required', 'boolean']]);
        $on = (bool) $data['on'];

        $device = EventCamera::where('id', $camera)
            ->where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->whereNotNull('claimed_at')
            ->first();

        abort_unless($device, 404);

        // Written before the publish, so the console shows what was ASKED FOR
        // even with the broker down — the phone corrects itself on its beat.
        $device->forceFill(['broadcasting' => $on])->save();

        if (! $on && $device->liveStream) {
            // The row is closed this side too. The media server's unpublish hook
            // is still the fact; this is the intent, and it is what stops a
            // viewer's page claiming a feed that is being taken down.
            $device->liveStream->disarm($request->user()?->id);
            $device->liveStream->markEnded();
        }

        CameraChannel::send($device, [
            'action' => $on ? 'live' : 'offair',
            'at' => now()->toIso8601String(),
        ]);

        CameraFleet::consolesChanged($event);

        return response()->json([
            'success' => true,
            'camera' => $device->fresh()->present(),
            'cameras' => CameraFleet::console($event)['cameras'] ?? [],
        ]);
    }

    /**
     * Take a camera off its mat, freeing its angle for another phone.
     *
     * Unpair, never revoke — the same distinction the screens make. A revoked
     * token resolves to nothing and the phone would sit on a dead screen with
     * no way back except reinstalling; unclaimed, it polls, sees itself free,
     * and comes back showing a fresh pairing code, which is what somebody
     * moving a camera to another mat actually wants.
     *
     * A rolling camera is stopped first. Otherwise the phone would keep filling
     * its disk with a bout it is no longer part of, and the file would never be
     * closed against a bout at all.
     */
    public function unpair(Request $request, ClubEvent $event, int $camera): JsonResponse
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 403);

        // Scoped to THIS event: an id from another competition is a 404 here,
        // not somebody else's camera being unpaired.
        $device = EventCamera::where('id', $camera)
            ->where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->first();

        abort_unless($device, 404);

        if ($device->recording) {
            CameraChannel::send($device, ['action' => 'stop', 'at' => now()->toIso8601String()]);
        }

        // A camera taken off a mat is off the air as well. Leaving the feed up
        // would broadcast a mat this phone is no longer assigned to, from a row
        // the console has stopped drawing — which is the definition of a
        // broadcast nobody can stop.
        if ($device->liveStream) {
            $device->liveStream->disarm($request->user()?->id);
            $device->liveStream->markEnded();
        }

        $device->forceFill(['broadcasting' => true])->save();

        $device->unclaim();
        CameraFleet::notify($device, 'unpaired');
        CameraFleet::consolesChanged($event);

        return response()->json([
            'success' => true,
            'cameras' => CameraFleet::console($event)['cameras'] ?? [],
        ]);
    }
}
