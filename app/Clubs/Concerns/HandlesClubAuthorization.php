<?php

namespace App\Clubs\Concerns;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
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

    /**
     * May the current user act as this club's OWNER — give it to somebody
     * else, or delete it? (security fix, 2026-10-03)
     *
     * Narrower than canManageClub() on purpose: running a club and giving it
     * away (or destroying it, uploaded files and all) are different powers. A
     * club admin runs it; only the people who own it — the owner, the approved
     * chain owner above it, or a super-admin — may transfer or delete it.
     */
    private function canActAsClubOwner(Tenant $club): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin() || $club->owner_user_id === $user->id) {
            return true;
        }

        return $club->business_id && $this->ownsClubBusiness($user->id, $club->business_id);
    }

    private function authorizeClubOwnerAction(Tenant $club): void
    {
        if (! $this->canActAsClubOwner($club)) {
            abort(403, 'Only the club\'s owner may do this.');
        }
    }

    private function ownsClubBusiness(int $userId, int $businessId): bool
    {
        return Business::where('id', $businessId)
            ->where('owner_user_id', $userId)
            ->where('status', Business::STATUS_APPROVED)
            ->exists();
    }
}
