<?php

namespace App\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationStaff;
use App\Members\Models\Membership;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * PLAN.md Phase 3 — every write to an OLD source is mirrored into
 * party_relations, so the two stay in step until the cut-over.
 *
 * ⚠️ Two promises, both load-bearing on a live site:
 *   1. With `parties.enabled` OFF (the default) every handler returns at once —
 *      registering this changes nothing.
 *   2. A mirror write can NEVER break the original write. Every handler is
 *      wrapped; a failure is reported and swallowed. The old source is still the
 *      truth during this phase, and `parties:verify` reports any gap.
 *
 * Known gaps, caught by `parties:verify` rather than here: writes that bypass
 * Eloquent events — bulk `->delete()` / `->update()` on a query (federation
 * staff removal in FederationDirectory, Business::transferOwnerTo's club
 * reassignment), raw DB::table() inserts (DemoSeed), and the custom-access
 * detach in ClubRoleController.
 */
final class DualWrite
{
    private const ROLE_MAP = ['club-admin' => Roles::ADMIN, 'instructor' => Roles::COACH, 'staff' => Roles::STAFF, 'moderator' => Roles::STAFF];

    public static function register(): void
    {
        foreach ([Tenant::class, Business::class, Federation::class] as $class) {
            $class::saved(fn (Model $org) => self::guard(fn () => self::ownerColumn($org)));
        }
        Tenant::saved(fn (Tenant $club) => self::guard(fn () => self::chain($club)));
        Federation::saved(fn (Federation $f) => self::guard(fn () => self::parent($f)));

        ClubInstructor::saved(fn (ClubInstructor $i) => self::guard(fn () => self::instructor($i)));
        ClubInstructor::deleted(fn (ClubInstructor $i) => self::guard(fn () => self::endFor($i->user_id, Roles::STAFF, Kinds::CLUB, $i->tenant_id, 'removed')));

        Membership::saved(fn (Membership $m) => self::guard(fn () => self::membership($m)));
        Membership::deleted(fn (Membership $m) => self::guard(fn () => self::endFor($m->user_id, Roles::MEMBER, Kinds::CLUB, $m->tenant_id, 'removed')));

        FederationStaff::saved(fn (FederationStaff $s) => self::guard(fn () => self::grantFor($s->user_id, Roles::OFFICER, Kinds::FEDERATION, $s->federation_id, ['title' => $s->role, 'meta' => ['sports' => (array) ($s->sports ?? [])]])));
        FederationStaff::deleted(fn (FederationStaff $s) => self::guard(fn () => self::endFor($s->user_id, Roles::OFFICER, Kinds::FEDERATION, $s->federation_id, 'removed')));

        FederationClub::saved(fn (FederationClub $c) => self::guard(fn () => self::federationClub($c)));

        // saved, not created: a host is often set right after the row exists
        // (a federation's sync, a person's own event) — and may change.
        ClubEvent::saved(fn (ClubEvent $e) => self::guard(fn () => self::eventSaved($e)));
        EventOfficial::created(fn (EventOfficial $o) => self::guard(fn () => self::grantFor($o->user_id, $o->role === 'organiser' ? Roles::ORGANISER : Roles::OFFICIAL, Kinds::EVENT, $o->event_id, ['title' => $o->role_label, 'meta' => ['official_role' => $o->role]])));
        EventOfficial::deleted(fn (EventOfficial $o) => self::guard(fn () => self::endFor($o->user_id, $o->role === 'organiser' ? Roles::ORGANISER : Roles::OFFICIAL, Kinds::EVENT, $o->event_id, 'removed')));
    }

    /** Called by User::assignRole() / removeRole() — pivot writes fire no model events. */
    public static function roleChanged(User $user, string $slug, ?int $tenantId, bool $granted): void
    {
        if (! $tenantId || ! isset(self::ROLE_MAP[$slug])) {
            return;
        }
        self::guard(fn () => $granted
            ? self::grantFor($user->id, self::ROLE_MAP[$slug], Kinds::CLUB, $tenantId, ['meta' => ['role_slug' => $slug]])
            : self::endFor($user->id, self::ROLE_MAP[$slug], Kinds::CLUB, $tenantId, 'role removed'));
    }

    /**
     * Every role a person held in one club was removed at once (the custom
     * access editor detaches them all) — end the matching relationships. Staff
     * that come from club_instructors are left alone: only rows that came from
     * a role (meta.role_slug) are ended.
     */
    public static function clubRolesCleared(User $user, int $tenantId): void
    {
        self::guard(function () use ($user, $tenantId) {
            $club = Tenant::withTrashed()->find($tenantId);
            if (! $club) {
                return;
            }
            $relations = app(Relations::class);
            foreach ($relations->active($club, [Roles::ADMIN, Roles::COACH, Roles::STAFF]) as $r) {
                if ((int) $r->from_id === (int) $user->id && $r->from_type === Kinds::PERSON
                    && ($r->role !== Roles::STAFF || ! empty($r->meta['role_slug']))) {
                    $relations->end($r, 'roles replaced', Auth::id());
                }
            }
        });
    }

    /** A club's role rows were all deleted (the club itself was deleted). */
    public static function clubAllRolesCleared(int $tenantId): void
    {
        self::guard(function () use ($tenantId) {
            $club = Tenant::withTrashed()->find($tenantId);
            if (! $club) {
                return;
            }
            $relations = app(Relations::class);
            foreach ($relations->active($club, [Roles::ADMIN, Roles::COACH, Roles::STAFF]) as $r) {
                if ($r->role !== Roles::STAFF || ! empty($r->meta['role_slug'])) {
                    $relations->end($r, 'club deleted', Auth::id());
                }
            }
        });
    }

    /* ---------------- handlers ---------------- */

    private static function ownerColumn(Model $org): void
    {
        if (! $org->owner_user_id || ! ($org->wasRecentlyCreated || $org->wasChanged('owner_user_id'))) {
            return;
        }

        $relations = app(Relations::class);
        $owners = $relations->owners($org);

        // Co-owned through the new system: the shares are the truth there and a
        // single-owner column cannot describe them — never flatten them.
        if ($owners->count() > 1) {
            return;
        }
        if ($owners->count() === 1 && (int) $owners->first()->from_id === (int) $org->owner_user_id) {
            return;
        }

        $owner = User::withTrashed()->find($org->owner_user_id);
        if ($owner) {
            $relations->setOwners($org, [[$owner, Relations::FULL]], $org->wasRecentlyCreated ? 'created' : 'owner changed', Auth::id());
        }
    }

    private static function chain(Tenant $club): void
    {
        if (! ($club->wasRecentlyCreated || $club->wasChanged('business_id'))) {
            return;
        }
        $relations = app(Relations::class);
        foreach ($relations->heldBy($club, Roles::CHAIN_CLUB) as $r) {
            if ((int) $r->to_id !== (int) $club->business_id) {
                $relations->end($r, 'left the chain', Auth::id());
            }
        }
        if ($club->business_id && ($business = Business::withTrashed()->find($club->business_id))) {
            $relations->grant($club, Roles::CHAIN_CLUB, $business, ['granted_by' => Auth::id()]);
        }
    }

    private static function parent(Federation $f): void
    {
        if (! ($f->wasRecentlyCreated || $f->wasChanged('parent_id'))) {
            return;
        }
        $relations = app(Relations::class);
        foreach ($relations->heldBy($f, Roles::SUB_BODY) as $r) {
            if ((int) $r->to_id !== (int) $f->parent_id) {
                $relations->end($r, 'moved', Auth::id());
            }
        }
        if ($f->parent_id && ($parent = Federation::withTrashed()->find($f->parent_id))) {
            $relations->grant($f, Roles::SUB_BODY, $parent, ['granted_by' => Auth::id()]);
        }
    }

    private static function instructor(ClubInstructor $i): void
    {
        if ($i->is_active) {
            self::grantFor($i->user_id, Roles::STAFF, Kinds::CLUB, $i->tenant_id, ['title' => $i->role, 'meta' => ['staff_type' => $i->staff_type ?? 'instructor']]);
        } elseif ($i->wasChanged('is_active')) {
            self::endFor($i->user_id, Roles::STAFF, Kinds::CLUB, $i->tenant_id, 'inactive');
        }
    }

    private static function membership(Membership $m): void
    {
        if ($m->status === 'active') {
            self::grantFor($m->user_id, Roles::MEMBER, Kinds::CLUB, $m->tenant_id);
        } elseif ($m->wasChanged('status')) {
            self::endFor($m->user_id, Roles::MEMBER, Kinds::CLUB, $m->tenant_id, (string) $m->status);
        }
    }

    private static function federationClub(FederationClub $c): void
    {
        if (! $c->tenant_id) {
            return;
        }
        $club = Tenant::withTrashed()->find($c->tenant_id);
        $fed = Federation::withTrashed()->find($c->federation_id);
        if (! $club || ! $fed) {
            return;
        }
        $relations = app(Relations::class);
        if ($c->status === 'approved') {
            $relations->grant($club, Roles::MEMBER_CLUB, $fed, ['granted_by' => $c->decided_by]);
        } elseif ($c->wasChanged('status')) {
            $relations->endRole($club, Roles::MEMBER_CLUB, $fed, (string) $c->status, Auth::id());
        }
    }

    private static function eventSaved(ClubEvent $e): void
    {
        if (! $e->wasRecentlyCreated && ! $e->wasChanged(['tenant_id', 'host_type', 'host_id'])) {
            return;
        }
        $relations = app(Relations::class);
        $host = $e->tenant_id
            ? Tenant::withTrashed()->find($e->tenant_id)
            : match (true) {
                $e->host_type === 'federation' && (bool) $e->host_id => Federation::withTrashed()->find($e->host_id),
                $e->host_type === 'person' && (bool) $e->host_id => User::withTrashed()->find($e->host_id),
                default => null,
            };
        // The host moved (or was set after creation): end any other host first.
        foreach ($relations->active($e, Roles::HOST) as $r) {
            if (! $host || $r->from_type !== Kinds::keyOf($host) || (int) $r->from_id !== (int) $host->getKey()) {
                $relations->end($r, 'host changed', Auth::id());
            }
        }
        if ($host) {
            $relations->grant($host, Roles::HOST, $e, ['granted_by' => $e->created_by]);
        }
        if ($e->wasRecentlyCreated && $e->created_by && ($creator = User::withTrashed()->find($e->created_by))) {
            $relations->grant($creator, Roles::ORGANISER, $e, ['meta' => ['creator' => true]]);
        }
    }

    /* ---------------- helpers ---------------- */

    private static function grantFor($userId, string $role, string $toKind, $toId, array $attrs = []): void
    {
        $user = $userId ? User::withTrashed()->find($userId) : null;
        $to = $toId ? Kinds::find($toKind, (int) $toId) : null;
        if ($user && $to) {
            app(Relations::class)->grant($user, $role, $to, $attrs + ['granted_by' => Auth::id()]);
        }
    }

    private static function endFor($userId, string $role, string $toKind, $toId, string $reason): void
    {
        $user = $userId ? User::withTrashed()->find($userId) : null;
        $to = $toId ? Kinds::find($toKind, (int) $toId) : null;
        if ($user && $to) {
            app(Relations::class)->endRole($user, $role, $to, $reason, Auth::id());
        }
    }

    private static function guard(callable $work): void
    {
        if (! config('parties.enabled')) {
            return;
        }
        try {
            $work();
        } catch (\Throwable $e) {
            report($e);   // never break the original write — parties:verify shows the gap
        }
    }
}
