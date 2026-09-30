<?php

namespace App\Platform\Controllers;

use App\Events\Models\EventFeatureRequest;
use App\Events\Support\EventFeaturing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The super-admin's side of paid featuring: set the fee, look at each proof of
 * payment, approve / reject / revoke. Behind the platform group's
 * `role:super-admin` + two-factor stack, and EventFeaturing re-checks the role
 * on every decision.
 */
class FeaturedEventsController extends Controller
{
    private const PER_STATUS = 100;

    public function index(Request $request)
    {
        $data = ['fee' => EventFeaturing::fee(), 'currency' => EventFeaturing::currency(), 'requests' => $this->rows()];
        $mobile = $request->attributes->get('is_mobile') && view()->exists('platform::mobile.featured-events');

        return view($mobile ? 'platform::mobile.featured-events' : 'platform::featured-events', $data);
    }

    /** The list again, for an in-place refresh after a decision or a new request. */
    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'requests' => $this->rows()]);
    }

    public function fee(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        $amount = isset($data['amount']) && $data['amount'] !== '' ? (float) $data['amount'] : null;
        EventFeaturing::setFee($amount, strtoupper($data['currency']));

        activity('platform')->causedBy($request->user())
            ->withProperties(['amount' => $amount, 'currency' => strtoupper($data['currency'])])
            ->log('event featuring fee changed');

        return response()->json([
            'success' => true,
            'message' => __('platform.featured_fee_saved'),
            'fee' => EventFeaturing::fee(),
            'currency' => EventFeaturing::currency(),
        ]);
    }

    /** The proof of payment — private storage, served only here. */
    public function proof(EventFeatureRequest $featureRequest): Response
    {
        $path = (string) $featureRequest->proof_path;
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'proof-'.$featureRequest->uuid.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type' => $featureRequest->proof_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function decide(Request $request, EventFeatureRequest $featureRequest, string $decision): JsonResponse
    {
        $data = $request->validate([
            'reason' => [Rule::requiredIf($decision !== 'approve'), 'nullable', 'string', 'max:500'],
        ]);

        $r = match ($decision) {
            'approve' => EventFeaturing::approve($request->user(), $featureRequest),
            'reject' => EventFeaturing::reject($request->user(), $featureRequest, (string) $data['reason']),
            'revoke' => EventFeaturing::revoke($request->user(), $featureRequest, (string) $data['reason']),
        };

        if ($r['ok']) {
            activity('platform')->causedBy($request->user())
                ->withProperties(['request' => $featureRequest->uuid, 'decision' => $decision])
                ->log('event featuring '.$decision);
        }

        return response()->json([
            'success' => $r['ok'],
            'message' => $r['message'],
            'request' => EventFeaturing::row($featureRequest->fresh(['requester', 'decider', 'clubEvent.tenant'])),
        ], $r['ok'] ? 200 : 422);
    }

    private function rows(): array
    {
        $take = fn (array $statuses) => EventFeatureRequest::with(['requester:id,name,full_name', 'decider:id,name,full_name', 'clubEvent.tenant:id,club_name'])
            ->whereIn('status', $statuses)->latest('id')->limit(self::PER_STATUS)->get()
            ->map(fn (EventFeatureRequest $r) => EventFeaturing::row($r));

        return $take([EventFeatureRequest::PENDING])
            ->concat($take([EventFeatureRequest::APPROVED]))
            ->concat($take([EventFeatureRequest::REJECTED, EventFeatureRequest::REVOKED]))
            ->values()->all();
    }
}
