<?php

namespace App\Clubs\Concerns;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use Illuminate\Support\Facades\Auth;

trait HandlesClubAuthorization
{
    private function authorizeClub(Tenant $club): void
    {
        // One rule, written once (canManageClub). Same four doors as always.
        if (! $this->canManageClub($club)) {
            abort(403, 'Unauthorized access to this club.');
        }
    }

    /** Non-throwing variant of authorizeClub() — true if the current user may manage the club. */
    private function canManageClub(Tenant $club): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $ok = $user->isSuperAdmin()
            || $club->owner_user_id === $user->id
            || $user->isClubAdmin($club->id)
            // Chain owners have full control over every club in their approved business.
            || ($club->business_id && $this->ownsClubBusiness($user->id, $club->business_id));

        // Shadow mode (PARTIES_SHADOW): the relationships answer the same
        // question and any disagreement is recorded. $ok still decides.
        return \App\Parties\Shadow::compare('club.manage', $user, 'club:'.$club->id, $ok,
            fn () => app(\App\Parties\Authority::class)->manageClub($user, $club));
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
        $ok = $user->isSuperAdmin()
            || $club->owner_user_id === $user->id
            || ($club->business_id && $this->ownsClubBusiness($user->id, $club->business_id));

        return \App\Parties\Shadow::compare('club.owner', $user, 'club:'.$club->id, $ok,
            fn () => app(\App\Parties\Authority::class)->actAsClubOwner($user, $club));
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
