<?php

namespace App\Clubs\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Clubs\Models\ClubFacility;
use App\Clubs\Models\Tenant;
use App\Models\UserNotification;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClubEventController extends Controller
{
    use HandlesClubAuthorization;

    /** Registrations for one event (participants + spectators), for the admin roster. */
    public function participants(Request $request, Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);

        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->with('user')
            ->orderByRaw("CASE WHEN role = 'participant' THEN 0 ELSE 1 END")
            ->orderBy('registered_at')
            ->get();

        $mobile = $request->attributes->get('is_mobile') && view()->exists('clubs::events.participants-mobile');

        return view($mobile ? 'clubs::events.participants-mobile' : 'clubs::events.participants',
            compact('club', 'event', 'registrations'));
    }

    /** Approve/unapprove a participant's manual payment (proof-of-payment workflow). */
    public function markParticipantPaid(Request $request, Tenant $club, $eventId, ClubEventRegistration $registration)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        abort_unless($registration->event_id === $event->id, 404);

        $paid = ! $registration->paid;
        $registration->paid = $paid;
        $registration->paid_at = $paid ? now() : null;
        // Record WHO approved it. The roster shows a payment as merely claimed
        // (amber) until an official has put their name to it.
        $registration->paid_by = $paid ? auth()->id() : null;
        $registration->save();

        if ($paid) {
            UserNotification::notifyUser($registration->user_id, 'event', 'Payment approved', [
                'body' => "Your payment for “{$event->title}” was approved.",
                'action_url' => route('me.events.show', $event->uuid),
                'actor_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'paid' => $paid,
            'message' => $paid ? 'Marked as paid.' : 'Marked as unpaid.',
        ]);
    }

    /** Stream a registration's proof-of-payment image (private disk, club-scoped access). */
    public function participantProof(Tenant $club, $eventId, ClubEventRegistration $registration)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        abort_unless($registration->event_id === $event->id, 404);
        abort_unless($registration->payment_proof && Storage::disk('local')->exists($registration->payment_proof), 404);

        return Storage::disk('local')->response($registration->payment_proof);
    }

    /** Remove a registration from the event (deletes its proof file first). */
    public function removeParticipant(Tenant $club, $eventId, ClubEventRegistration $registration)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        abort_unless($registration->event_id === $event->id, 404);

        if ($registration->payment_proof && Storage::disk('local')->exists($registration->payment_proof)) {
            Storage::disk('local')->delete($registration->payment_proof);
        }
        $registration->delete();

        return response()->json(['success' => true, 'message' => 'Participant removed.']);
    }

    public function events(Tenant $club)
    {
        $this->authorizeClub($club);
        $events = ClubEvent::where('tenant_id', $club->id)->orderBy('date')->orderBy('start_time')->get();
        $facilities = ClubFacility::where('tenant_id', $club->id)->orderBy('name')->get();

        return view(\App\Support\ClubView::pick('events', 'clubs'), compact('club', 'events', 'facilities'));
    }

    public function storeEvent(EventRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $data = $request->only(['title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level', 'description', 'max_capacity', 'cancel_within_days', 'color', 'participant_fee']);
        // The stated price, when the form sent one. Without it the model derives
        // what it can from the display line — the last place that ever guesses.
        if ($request->filled('participant_fee_amount')) {
            $data['participant_fee_amount'] = (float) $request->input('participant_fee_amount');
            $data['fee_currency'] = $club->currency ?: 'BHD';
            $data['participant_fee'] = \App\Events\Support\EventFee::display($data['participant_fee_amount'], $data['fee_currency']);
        }
        $data['tenant_id'] = $club->id;
        $data['status'] = 'active';
        $data['tags'] = $request->filled('tags')
            ? array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))
            : null;

        $images = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = $images ?: null;

        $event = ClubEvent::create($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Event created successfully.', 'event' => $event]);
        }

        return back()->with('success', 'Event created successfully.');
    }

    public function updateEvent(EventRequest $request, Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);

        $data = $request->only(['title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level', 'description', 'max_capacity', 'cancel_within_days', 'color', 'participant_fee']);
        // The stated price, when the form sent one. Without it the model derives
        // what it can from the display line — the last place that ever guesses.
        if ($request->filled('participant_fee_amount')) {
            $data['participant_fee_amount'] = (float) $request->input('participant_fee_amount');
            $data['fee_currency'] = $club->currency ?: 'BHD';
            $data['participant_fee'] = \App\Events\Support\EventFee::display($data['participant_fee_amount'], $data['fee_currency']);
        }
        $data['tags'] = $request->filled('tags')
            ? array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))))
            : null;

        try {
            $keepImages = json_decode($request->input('keep_images', '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->withErrors(['images' => 'Invalid image data.']);
        }
        $newImages = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = array_merge($keepImages, $newImages) ?: null;

        $event->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Event updated successfully.', 'event' => $event]);
        }

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
        $event->update(['is_archived' => ! $event->is_archived]);

        $msg = $event->is_archived ? 'Event archived.' : 'Event unarchived.';

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'is_archived' => (bool) $event->is_archived]);
        }

        return back()->with('success', $msg);
    }

    private function saveEventBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (! str_starts_with($base64, 'data:image')) {
                continue;
            }
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext = $m[1] ?? 'jpg';
            $path = 'clubs/'.$clubId.'/events/'.uniqid('event_').'.'.$ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }

        return $paths;
    }
}
