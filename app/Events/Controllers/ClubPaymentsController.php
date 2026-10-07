<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventEntryInvoice;
use App\Events\Support\ClubEntry\PlatformClubEntry;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The organiser's side of club payments (EventEntryInvoice): the list, each
 * proof, and the decision. Whoever may verify payments at this event
 * (EventAccess::canVerifyPayments) — the same people as the payment desk.
 */
class ClubPaymentsController extends Controller
{
    private function guard(Request $request, ClubEvent $event): void
    {
        abort_unless($request->user() && ! $event->is_archived && app(EventAccess::class)->canVerifyPayments($event, $request->user()), 403);
    }

    public function index(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($request, $event);

        return response()->json(['success' => true, 'payments' => (new PlatformClubEntry($event))->organiserList()]);
    }

    public function proof(Request $request, ClubEvent $event, string $invoice)
    {
        $this->guard($request, $event);
        $row = EventEntryInvoice::where('event_id', $event->id)->where('uuid', $invoice)->firstOrFail();
        abort_unless($row->proof_path && Storage::disk('local')->exists($row->proof_path), 404);

        return Storage::disk('local')->response($row->proof_path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function decide(Request $request, ClubEvent $event, string $invoice): JsonResponse
    {
        $this->guard($request, $event);
        $data = $request->validate(['approve' => 'required|boolean', 'reason' => 'nullable|string|max:500']);
        $row = EventEntryInvoice::where('event_id', $event->id)->where('uuid', $invoice)->firstOrFail();
        abort_if($row->state !== EventEntryInvoice::SUBMITTED, 422, __('events::events.public_review_already_decided'));

        PlatformClubEntry::decide($event, $row, (bool) $data['approve'], $data['reason'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => $data['approve'] ? __('events::events.club_payments_approved') : __('events::events.club_payments_rejected'),
            'payments' => (new PlatformClubEntry($event))->organiserList(),
        ]);
    }
}
