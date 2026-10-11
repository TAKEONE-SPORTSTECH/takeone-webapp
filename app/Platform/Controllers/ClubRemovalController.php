<?php

namespace App\Platform\Controllers;

use App\Clubs\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Platform\Services\ClubRemoval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The super-admin's "Remove club": the review, then the removal itself.
 * Authorisation is the route group's (super-admin, 2FA); every rule about
 * what may go lives in ClubRemoval.
 */
class ClubRemovalController extends Controller
{
    public function __construct(private ClubRemoval $removal) {}

    /** What removing this club would do — read-only. */
    public function show(Tenant $club): JsonResponse
    {
        abort_unless(config('features.club_removal_tool'), 404);

        $plan = $this->removal->plan($club);
        unset($plan['_ids']);

        return response()->json(['success' => true, 'plan' => $plan]);
    }

    public function store(Request $request, Tenant $club): JsonResponse
    {
        abort_unless(config('features.club_removal_tool'), 404);

        $data = $request->validate([
            'confirm_name' => ['required', 'string', 'max:255'],
            'fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $result = $this->removal->execute($club, $request->user(), $data['confirm_name'], $data['fingerprint']);

        return response()->json(['success' => $result['ok']] + $result, $result['ok'] ? 200 : (($result['stale'] ?? false) ? 409 : 422));
    }
}
