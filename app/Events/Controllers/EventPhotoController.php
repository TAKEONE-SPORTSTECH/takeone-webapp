<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventPhoto;
use App\Events\Support\EventAccess;
use App\Events\Support\EventPhotos;
use App\Events\Support\PublicEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The gallery's photographs.
 *
 * Two rules, kept apart on purpose:
 *   · upload / delete — whoever may MANAGE the event;
 *   · viewing         — whoever may READ it: anybody while the organiser has
 *                       the public page on, otherwise a signed-in member the
 *                       event reaches. Re-checked on every image request.
 *
 * Every refusal to view is the same 404, so a photo uuid from one event never
 * confirms anything through another event's address.
 */
class EventPhotoController extends Controller
{
    public function __construct(private EventAccess $access, private EventPhotos $photos) {}

    /** Add one photograph. Organiser only. */
    public function store(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless(EventPhotos::enabled(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        abort_unless($this->access->canManage($event, $me), 403);

        $request->validate([
            // Framework guard rail only; EventPhotos inspects the real bytes.
            'photo' => ['required', 'file', 'max:'.(int) (EventPhotos::MAX_BYTES / 1024)],
        ]);

        $photo = $this->photos->store($request->file('photo'), $event, $me);

        if ($photo === null) {
            return response()->json([
                'success' => false,
                'message' => __('events::events.gallery_photo_rejected'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('events::events.gallery_photo_added'),
            'photo' => $this->photos->present($photo, $event, true),
        ]);
    }

    /** Remove one photograph. Organiser only; files go before the row (model trait). */
    public function destroy(ClubEvent $event, EventPhoto $photo): JsonResponse
    {
        abort_unless(EventPhotos::enabled(), 404);

        $me = Auth::user();
        abort_unless($this->access->canManage($event, $me), 403);
        abort_unless($photo->event_id === $event->id, 404);

        $uuid = $photo->uuid;
        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => __('events::events.gallery_photo_removed'),
            'uuid' => $uuid,
        ]);
    }

    /** The picture itself — `thumb` for the grid, `full` for the viewer. */
    public function show(ClubEvent $event, EventPhoto $photo, string $size)
    {
        abort_unless(EventPhotos::enabled(), 404);
        abort_if($event->is_archived, 404);
        abort_unless($photo->event_id === $event->id, 404);

        $me = Auth::user();
        $mayRead = app(PublicEvent::class)->isPublic($event)
            || ($me !== null && $this->access->visible($event, $me));

        abort_unless($mayRead, 404);

        $path = $size === 'full' ? $photo->path : $photo->thumb_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
