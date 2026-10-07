<?php

namespace App\Clubs\Controllers;

use App\Http\Controllers\Controller;

use App\Clubs\Requests\StoreClubRequest;
use App\Clubs\Support\ClubCreationService;

/**
 * Lets a business (chain) owner create a new club from their dashboard.
 * Authorization is entirely handled by the route's middleware group
 * (auth, verified, two-factor, business) — EnsureHasBusiness already
 * guarantees the acting user owns an APPROVED Business before this runs.
 * StoreClubRequest::prepareForValidation() forces owner_user_id to the
 * acting user for this route, so Tenant::booted() auto-links the new club
 * to their chain — no business_id handling needed here.
 */
class BusinessClubController extends Controller
{
    public function store(StoreClubRequest $request, ClubCreationService $clubs)
    {
        try {
            $club = $clubs->createFromValidatedRequest($request);

            return response()->json([
                'success' => true,
                'message' => __('shared.club_modal_created'),
                'club' => $club,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Business club create failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('shared.club_modal_create_failed'),
            ], 500);
        }
    }
}
