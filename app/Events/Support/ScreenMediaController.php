<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The organiser's end of the hall's sound: upload a track for a slot, or remove
 * it.
 *
 * Every action re-checks that this person may manage THIS event. The screens
 * read these files through their own token-authorised route; nothing here is
 * reachable by a screen, and nothing there is reachable by a browser.
 */
class ScreenMediaController extends Controller
{
    public function store(Request $request, ClubEvent $event, string $slot): JsonResponse
    {
        if ($denied = $this->refuseUnlessManageable($request, $event)) {
            return $denied;
        }

        if (! in_array($slot, ScreenMedia::SLOTS, true)) {
            return response()->json(['success' => false, 'message' => __('events.screen_audio_unknown_slot')], 422);
        }

        // A first pass on what the client claims, so an obviously wrong upload is
        // refused before it is written anywhere. The REAL check is the byte sniff
        // inside ScreenMedia::put().
        $request->validate([
            'file' => ['required', 'file', 'max:'.(ScreenMedia::MAX_BYTES / 1024)],
        ]);

        $media = ScreenMedia::put($event, $slot, $request->file('file'), $request->user()->id);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => __('events.screen_audio_rejected'),
            ], 422);
        }

        // The screens are holding the OLD file. Nothing about the mat state
        // says a sound changed, so they are told the one thing that recovers
        // it: start again. Without this an organiser replaces the music and
        // every board in the hall goes on playing what it fetched this morning.
        app(EventTypeRegistry::class)->for($event)->reloadHallScreens($event);

        return response()->json([
            'success' => true,
            'message' => __('events.screen_audio_saved'),
            'media' => $this->row($media),
        ]);
    }

    /**
     * Audition a slot from the organiser's own browser.
     *
     * Separate from the route the SCREENS use, and deliberately so: that one is
     * authorised by a screen token and this one by a session, and neither should
     * be reachable with the other's credential. Same file, two doors, two locks.
     */
    public function show(Request $request, ClubEvent $event, string $slot)
    {
        abort_unless(app(EventAccess::class)->canManage($event, $request->user()), 404);

        $media = ScreenMedia::slot($event, $slot);

        abort_unless($media && $media->exists(), 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime ?: 'audio/mpeg',
            // Private: this is one club's licensed audio, not something for a
            // proxy on the way to hold on to.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function destroy(Request $request, ClubEvent $event, string $slot): JsonResponse
    {
        if ($denied = $this->refuseUnlessManageable($request, $event)) {
            return $denied;
        }

        $media = ScreenMedia::slot($event, $slot);

        if ($media) {
            // Files first, then the row — a deleted row with its file still on
            // disk is an orphan nobody will ever find again.
            $media->purge();
        }

        // Same on the way out: a screen that already has the file would keep
        // playing a sound the organiser has just taken away.
        app(EventTypeRegistry::class)->for($event)->reloadHallScreens($event);

        return response()->json([
            'success' => true,
            'message' => __('events.screen_audio_removed'),
            'slot' => $slot,
        ]);
    }

    /**
     * The refusal to send back, or null when this person may manage the event.
     *
     * The route binds the event by uuid, so it exists by the time we are here —
     * what still has to be asked, on every single call, is whether the person
     * holding the URL may manage it.
     */
    private function refuseUnlessManageable(Request $request, ClubEvent $event): ?JsonResponse
    {
        if (app(EventAccess::class)->canManage($event, $request->user())) {
            return null;
        }

        // A 404 rather than a 403, and the same one an unknown uuid would get:
        // a prober learns nothing about which events exist.
        return response()->json(['success' => false, 'message' => __('events.screen_audio_denied')], 404);
    }

    /** Only what the uploader needs to see. No paths, no disk, no ids. */
    private function row(ScreenMedia $media): array
    {
        return [
            'slot' => $media->slot,
            'name' => $media->original_name,
            'bytes' => $media->bytes,
            'uploaded_at' => $media->updated_at?->toIso8601String(),
        ];
    }
}
