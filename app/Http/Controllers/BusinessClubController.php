<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreClubRequest;
use App\Services\ClubCreationService;

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
                'message' => 'Club created successfully!',
                'club' => $club,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create club: '.$e->getMessage(),
            ], 500);
        }
    }
}
