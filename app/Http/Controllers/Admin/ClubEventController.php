<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Models\ClubEvent;
use App\Models\ClubFacility;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubEventController extends Controller
{
    use HandlesClubAuthorization;

    public function events(Tenant $club)
    {
        $this->authorizeClub($club);
        $events     = ClubEvent::where('tenant_id', $club->id)->orderBy('date')->orderBy('start_time')->get();
        $facilities = ClubFacility::where('tenant_id', $club->id)->orderBy('name')->get();
        return view('admin.club.events.index', compact('club', 'events', 'facilities'));
    }

    public function storeEvent(EventRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $data              = $request->only(['title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level', 'description', 'max_capacity', 'cancel_within_days', 'color']);
        $data['tenant_id'] = $club->id;
        $data['status']    = 'active';
        $data['tags']      = $request->filled('tags')
            ? array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))
            : null;

        $images        = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = $images ?: null;

        ClubEvent::create($data);

        return back()->with('success', 'Event created successfully.');
    }

    public function updateEvent(EventRequest $request, Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);

        $data         = $request->only(['title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level', 'description', 'max_capacity', 'cancel_within_days', 'color']);
        $data['tags'] = $request->filled('tags')
            ? array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))
            : null;

        try {
            $keepImages = json_decode($request->input('keep_images', '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->withErrors(['images' => 'Invalid image data.']);
        }
        $newImages      = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = array_merge($keepImages, $newImages) ?: null;

        $event->update($data);

        return back()->with('success', 'Event updated successfully.');
    }

    public function destroyEvent(Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        $event->delete();

        return response()->json(['success' => true, 'message' => 'Event deleted successfully.']);
    }

    public function archiveEvent(Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        $event->update(['is_archived' => !$event->is_archived]);

        $msg = $event->is_archived ? 'Event archived.' : 'Event unarchived.';
        return back()->with('success', $msg);
    }

    private function saveEventBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (!str_starts_with($base64, 'data:image')) continue;
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext  = $m[1] ?? 'jpg';
            $path = 'clubs/' . $clubId . '/events/' . uniqid('event_') . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }
        return $paths;
    }
}
