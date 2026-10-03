<?php

namespace App\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The ONE answer to "may this person do this here?", read from the active
 * relationships (PLAN.md §3.4).
 *
 * Phase 4 rule: every method here reproduces TODAY's check exactly — quirks
 * included — so that shadow mode can prove the two agree before anything is
 * cut over. A rule is broadened deliberately afterwards, never by accident here.
 * (Example kept on purpose: a chain owner manages a CLUB but not, today, that
 * club's EVENTS — EventAccess never asked about chains.)
 */
final class Authority
{
    public function __construct(private Relations $relations) {}

    /** HandlesClubAuthorization::canManageClub(): super · owner · club admin · approved chain owner. */
    public function manageClub(User $user, Tenant $club): bool
    {
        return $user->isSuperAdmin()
            || $this->relations->holds($user, [Roles::OWNER, Roles::ADMIN], $club)
            || $this->ownsApprovedChainOf($user, $club);
    }

    /**
     * HandlesClubAuthorization::canActAsClubOwner(): transfer or delete the club.
     * super · the club's owner (a sole owner, or the one holding a majority —
     * "the majority decides", owner 2026-10-03) · the approved chain's owner.
     */
    public function actAsClubOwner(User $user, Tenant $club): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $majority = $this->relations->majorityOwner($club);
        if ($majority && $majority->is($user)) {
            return true;
        }

        return $this->ownsApprovedChainOf($user, $club);
    }

    /**
     * EventAccess::canManage(): super · creator or appointed organiser · the
     * host club's owner or club admin · the host federation's managers.
     */
    public function manageEvent(User $user, ClubEvent $event): bool
    {
        if ($user->isSuperAdmin() || $this->relations->holds($user, Roles::ORGANISER, $event)) {
            return true;
        }

        foreach ($this->relations->active($event, Roles::HOST) as $host) {
            $party = $host->holder();
            if ($party instanceof Tenant && $this->relations->holds($user, [Roles::OWNER, Roles::ADMIN], $party)) {
                return true;
            }
            if ($party instanceof Federation && $this->managesFederationEvents($user, $party)) {
                return true;
            }
        }

        return false;
    }

    /**
     * FederationDirectory::access(), reduced to what it decides: which body the
     * user lands on, and whether they manage it. Null = no access.
     *
     * @return array{root: int, manager: bool}|null
     */
    public function federationAccess(User $user): ?array
    {
        $owned = $this->relations->heldBy($user, Roles::OWNER)
            ->where('to_type', Kinds::FEDERATION)
            ->map(fn ($r) => $r->party())
            ->filter(fn ($f) => $f instanceof Federation && ! $f->trashed() && $f->isApproved() && ! $f->suspended_at && $this->usable($f))
            ->sortBy([fn ($f) => count($this->lineage($f)), fn ($f) => $f->id])
            ->first();
        if ($owned) {
            return ['root' => $owned->id, 'manager' => true];
        }

        $officer = $this->relations->heldBy($user, Roles::OFFICER)
            ->where('to_type', Kinds::FEDERATION)
            ->sortBy('id')
            ->first(fn ($r) => ($f = $r->party()) instanceof Federation && ! $f->trashed() && $this->usable($f));
        if ($officer) {
            return ['root' => (int) $officer->to_id, 'manager' => array_values(array_filter((array) ($officer->meta['sports'] ?? []))) === []];
        }

        if ($user->isSuperAdmin()) {
            $top = Federation::approved()->whereNull('parent_id')->oldest('id')->first();

            return $top ? ['root' => $top->id, 'manager' => true] : null;
        }

        return null;
    }

    /* ---------------- internals ---------------- */

    private function ownsApprovedChainOf(User $user, Tenant $club): bool
    {
        foreach ($this->relations->heldBy($club, Roles::CHAIN_CLUB) as $link) {
            $business = $link->party();
            if ($business instanceof Business && ! $business->trashed() && $business->status === Business::STATUS_APPROVED
                && ($owner = $this->relations->majorityOwner($business)) && $owner->is($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Federations::managesEventsOf(), exactly: the ONE body the user lands on
     * (federationAccess) must be a manager's, the host must not be suspended,
     * and the host must sit in that body's approved subtree. Off entirely
     * while the federations module is.
     */
    private function managesFederationEvents(User $user, Federation $host): bool
    {
        if (! config('federations.enabled') || $host->isSuspended()) {
            return false;
        }
        $access = $this->federationAccess($user);
        if (! $access || ! $access['manager']) {
            return false;
        }

        // Walk down from the root over APPROVED bodies (FederationDirectory::subtree()).
        $frontier = [$access['root']];
        $seen = [];
        for ($depth = 0; $frontier && $depth <= Federation::MAX_DEPTH; $depth++) {
            if (in_array((int) $host->id, $frontier, true)) {
                return true;
            }
            $seen = array_merge($seen, $frontier);
            $frontier = Federation::approved()->whereIn('parent_id', $frontier)->whereNotIn('id', $seen)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return false;
    }

    private function usable(Federation $f): bool
    {
        if (! $f->isApproved()) {
            return false;
        }
        foreach ($this->lineage($f) as $body) {
            if ($body->isSuspended()) {
                return false;
            }
        }

        return ! $f->isSuspended();
    }

    /** The bodies above this one, nearest first (relations first, the column as a fallback). */
    private function lineage(Federation $f): array
    {
        $out = [];
        $seen = [$f->id => true];
        $cur = $f;
        while (true) {
            $link = $this->relations->heldBy($cur, Roles::SUB_BODY)->first();
            $parentId = $link ? (int) $link->to_id : (int) DB::table('federations')->where('id', $cur->id)->value('parent_id');
            if (! $parentId || isset($seen[$parentId]) || ! ($parent = Federation::find($parentId))) {
                break;
            }
            $out[] = $parent;
            $seen[$parentId] = true;
            $cur = $parent;
        }

        return $out;
    }
}
