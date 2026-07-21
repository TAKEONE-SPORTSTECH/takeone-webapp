<?php

namespace App\Mcp\Concerns;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the "which clubs / members can this user touch" logic so every
 * tool enforces the same tenant scope the web UI does:
 *   super-admin → owner → club-admin → guardian/self.
 */
trait AuthorizesClubAccess
{
    /**
     * IDs of every club the user may read: owned, role-scoped (admin/instructor),
     * and member-of. Super-admins get every club.
     *
     * @return Collection<int, int>
     */
    protected function accessibleClubIds(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Tenant::query()->pluck('id');
        }

        $owned = $user->ownedClubs()->pluck('id');

        $roleScoped = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $memberOf = DB::table('memberships')
            ->where('user_id', $user->id)
            ->pluck('tenant_id');

        return $owned->merge($roleScoped)->merge($memberOf)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * A Tenant query already scoped to what the user may read.
     *
     * @return Builder<Tenant>
     */
    protected function accessibleClubsQuery(User $user): Builder
    {
        $query = Tenant::query();

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', $this->accessibleClubIds($user));
        }

        return $query;
    }

    /**
     * Resolve a club the user may read, by numeric id or slug. Null if it does
     * not exist or the user has no access — tools must treat null as 404/403.
     */
    protected function resolveAccessibleClub(User $user, string|int $idOrSlug): ?Tenant
    {
        $club = Tenant::query()
            ->where('id', is_numeric($idOrSlug) ? (int) $idOrSlug : 0)
            ->orWhere('slug', (string) $idOrSlug)
            ->first();

        if (! $club) {
            return null;
        }

        if ($user->isSuperAdmin() || $this->accessibleClubIds($user)->contains($club->id)) {
            return $club;
        }

        return null;
    }

    /**
     * May the user administer (write to) this club? owner / club-admin / super.
     */
    protected function canAdminClub(User $user, Tenant $club): bool
    {
        return $user->isSuperAdmin()
            || (int) $club->owner_user_id === (int) $user->id
            || $user->isClubAdmin($club->id);
    }

    /**
     * May the user view this member's (private) profile?
     * super-admin → self → confirmed guardian → club-admin of a club the member belongs to.
     */
    protected function canViewMember(User $actor, User $member): bool
    {
        if ($actor->isSuperAdmin() || $actor->id === $member->id) {
            return true;
        }

        $isGuardian = UserRelationship::where('guardian_user_id', $actor->id)
            ->where('dependent_user_id', $member->id)
            ->exists();

        if ($isGuardian) {
            return true;
        }

        $memberTenantIds = DB::table('memberships')
            ->where('user_id', $member->id)
            ->pluck('tenant_id');

        foreach ($memberTenantIds as $tenantId) {
            if ($actor->isClubAdmin((int) $tenantId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * May the user create/edit a member's OWN self-managed records
     * (certifications, work history, goals, event log)? This is stricter than
     * viewing — it mirrors MemberController::authorizeMemberWrite():
     * super-admin → self → confirmed guardian only (NOT club-admins).
     */
    protected function canEditMemberSelfRecords(User $actor, User $member): bool
    {
        if ($actor->isSuperAdmin() || $actor->id === $member->id) {
            return true;
        }

        return UserRelationship::where('guardian_user_id', $actor->id)
            ->where('dependent_user_id', $member->id)
            ->exists();
    }
}
