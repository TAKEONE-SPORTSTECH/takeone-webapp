<?php

namespace App\Traits;

use App\Models\Business;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

trait HandlesClubAuthorization
{
    private function authorizeClub(Tenant $club): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($club->owner_user_id === $user->id) {
            return;
        }
        if ($user->isClubAdmin($club->id)) {
            return;
        }
        // Chain owners have full control over every club in their approved business.
        if ($club->business_id && $this->ownsClubBusiness($user->id, $club->business_id)) {
            return;
        }
        abort(403, 'Unauthorized access to this club.');
    }

    /** Non-throwing variant of authorizeClub() — true if the current user may manage the club. */
    private function canManageClub(Tenant $club): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($club->owner_user_id === $user->id) {
            return true;
        }
        if ($user->isClubAdmin($club->id)) {
            return true;
        }
        if ($club->business_id && $this->ownsClubBusiness($user->id, $club->business_id)) {
            return true;
        }

        return false;
    }

    private function ownsClubBusiness(int $userId, int $businessId): bool
    {
        return Business::where('id', $businessId)
            ->where('owner_user_id', $userId)
            ->where('status', Business::STATUS_APPROVED)
            ->exists();
    }
}
