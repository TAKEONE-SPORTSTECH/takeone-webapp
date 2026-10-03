<?php

namespace App\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Parties\Models\PartyRelation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Copies today's scattered "who holds what" sources into party_relations
 * (PLAN.md Phase 2), and checks the two agree (Phase 3's drift check).
 *
 * Reads the old sources and NEVER writes them. Idempotent: every row carries
 * a `source` key, so a second run adds only what is new. Shared by the
 * `parties:backfill` / `parties:verify` commands and the tests.
 */
final class Backfill
{
    /** user_roles slug → role here. Platform-wide rows (no tenant) and custom member-* roles are not relationships. */
    private const ROLE_MAP = [
        'club-admin' => Roles::ADMIN,
        'instructor' => Roles::COACH,
        'staff' => Roles::STAFF,
        'moderator' => Roles::STAFF,
    ];

    public function __construct(private Relations $relations) {}

    /**
     * @return array<string, int> rows created per source (dry run: rows that WOULD be created)
     */
    public function run(bool $apply): array
    {
        $counts = [];
        $count = function (string $source, bool $isNew) use (&$counts) {
            $counts[$source] = ($counts[$source] ?? 0) + ($isNew ? 1 : 0);
        };

        $work = function () use ($apply, $count) {
            $this->owners(Tenant::withTrashed(), 'tenants', $apply, $count);
            $this->owners(Business::withTrashed(), 'businesses', $apply, $count);
            $this->owners(Federation::withTrashed(), 'federations', $apply, $count);
            $this->userRoles($apply, $count);
            $this->clubInstructors($apply, $count);
            $this->federationStaff($apply, $count);
            $this->memberships($apply, $count);
            $this->federationClubs($apply, $count);
            $this->chainClubs($apply, $count);
            $this->subBodies($apply, $count);
            $this->eventHosts($apply, $count);
            $this->eventOfficials($apply, $count);
        };

        $apply ? DB::transaction($work) : $work();

        return $counts;
    }

    /**
     * Where the old sources and the relationships table disagree, as readable
     * lines. Empty = in step. Checks what decides access today: owners, club
     * admins, active staff, federation staff and approved member clubs.
     *
     * @return list<string>
     */
    public function drift(): array
    {
        $out = [];

        foreach ([[Tenant::withTrashed(), 'club'], [Business::withTrashed(), 'business'], [Federation::withTrashed(), 'federation']] as [$query, $kind]) {
            foreach ($query->whereNotNull('owner_user_id')->cursor() as $org) {
                $owners = $this->relations->owners($org);
                if ($owners->count() !== 1 || (int) $owners->first()->from_id !== (int) $org->owner_user_id || $owners->first()->from_type !== Kinds::PERSON) {
                    $out[] = "{$kind} {$org->id}: owner_user_id={$org->owner_user_id}, relations say ".($owners->map(fn ($r) => $r->from_id.'@'.$r->share_bp)->join(',') ?: 'nobody');
                }
            }
        }

        foreach (DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereNotNull('user_roles.tenant_id')->whereIn('roles.slug', array_keys(self::ROLE_MAP))
            ->get(['user_roles.user_id', 'user_roles.tenant_id', 'roles.slug']) as $r) {
            if (! $this->activeExists(Kinds::PERSON, $r->user_id, Kinds::CLUB, $r->tenant_id, self::ROLE_MAP[$r->slug])) {
                $out[] = "user_roles: user {$r->user_id} is {$r->slug} of club {$r->tenant_id}; no active relation";
            }
        }

        // The other direction — the one that matters once Authority decides: an
        // ACTIVE admin relationship whose role row is gone would let somebody
        // keep a club they were removed from.
        foreach (PartyRelation::query()->active()->where('role', Roles::ADMIN)->where('from_type', Kinds::PERSON)->where('to_type', Kinds::CLUB)->get() as $r) {
            $still = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $r->from_id)->where('user_roles.tenant_id', $r->to_id)->where('roles.slug', 'club-admin')->exists();
            if (! $still) {
                $out[] = "party_relations: user {$r->from_id} is still admin of club {$r->to_id}; no club-admin role row";
            }
        }

        foreach (DB::table('club_instructors')->where('is_active', true)->get(['user_id', 'tenant_id']) as $r) {
            if (! $this->activeExists(Kinds::PERSON, $r->user_id, Kinds::CLUB, $r->tenant_id, Roles::STAFF)) {
                $out[] = "club_instructors: user {$r->user_id} active staff of club {$r->tenant_id}; no active relation";
            }
        }

        foreach (DB::table('federation_staff')->get(['user_id', 'federation_id']) as $r) {
            if (! $this->activeExists(Kinds::PERSON, $r->user_id, Kinds::FEDERATION, $r->federation_id, Roles::OFFICER)) {
                $out[] = "federation_staff: user {$r->user_id} of federation {$r->federation_id}; no active relation";
            }
        }

        foreach (DB::table('federation_clubs')->where('status', 'approved')->whereNotNull('tenant_id')->get(['tenant_id', 'federation_id']) as $r) {
            if (! $this->activeExists(Kinds::CLUB, $r->tenant_id, Kinds::FEDERATION, $r->federation_id, Roles::MEMBER_CLUB)) {
                $out[] = "federation_clubs: club {$r->tenant_id} approved in federation {$r->federation_id}; no active relation";
            }
        }

        return $out;
    }

    /* ---------------- sources ---------------- */

    private function owners($query, string $table, bool $apply, callable $count): void
    {
        foreach ($query->whereNotNull('owner_user_id')->cursor() as $org) {
            $source = "{$table}.owner_user_id:{$org->id}";
            $isNew = ! PartyRelation::where('source', $source)->exists()
                && $this->relations->owners($org)->isEmpty();
            $count("{$table}.owner_user_id", $isNew);
            if (! $isNew || ! $apply) {
                continue;
            }
            $owner = User::withTrashed()->find($org->owner_user_id);
            if (! $owner) {
                continue;
            }
            $this->relations->setOwners($org, [[$owner, Relations::FULL]], 'backfill', null, [
                'source' => $source, 'starts_at' => $this->at($org->created_at),
            ]);
        }
    }

    private function userRoles(bool $apply, callable $count): void
    {
        $rows = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereNotNull('user_roles.tenant_id')->whereIn('roles.slug', array_keys(self::ROLE_MAP))
            ->get(['user_roles.id', 'user_roles.user_id', 'user_roles.tenant_id', 'user_roles.created_at', 'roles.slug']);

        foreach ($rows as $r) {
            $this->grantFrom('user_roles', $r->id, Kinds::PERSON, $r->user_id, self::ROLE_MAP[$r->slug], Kinds::CLUB, $r->tenant_id,
                ['starts_at' => $this->at($r->created_at), 'meta' => ['role_slug' => $r->slug]], $apply, $count);
        }
    }

    private function clubInstructors(bool $apply, callable $count): void
    {
        foreach (DB::table('club_instructors')->get() as $r) {
            $attrs = ['starts_at' => $this->at($r->paid_since ?? $r->created_at), 'title' => $r->role,
                'meta' => ['staff_type' => $r->staff_type ?? 'instructor']];
            if (! $r->is_active) {
                $attrs += ['ends_at' => $this->at($r->updated_at), 'end_reason' => 'inactive'];
            }
            $this->grantFrom('club_instructors', $r->id, Kinds::PERSON, $r->user_id, Roles::STAFF, Kinds::CLUB, $r->tenant_id, $attrs, $apply, $count);
        }
    }

    private function federationStaff(bool $apply, callable $count): void
    {
        foreach (DB::table('federation_staff')->get() as $r) {
            $this->grantFrom('federation_staff', $r->id, Kinds::PERSON, $r->user_id, Roles::OFFICER, Kinds::FEDERATION, $r->federation_id,
                ['starts_at' => $this->at($r->created_at), 'title' => $r->role,
                    'meta' => ['sports' => json_decode((string) $r->sports, true) ?: []], 'granted_by' => $r->invited_by], $apply, $count);
        }
    }

    private function memberships(bool $apply, callable $count): void
    {
        foreach (DB::table('memberships')->get() as $r) {
            $attrs = ['starts_at' => $this->at($r->created_at)];
            if ($r->status !== 'active') {
                $attrs += ['ends_at' => $this->at($r->updated_at), 'end_reason' => (string) $r->status];
            }
            $this->grantFrom('memberships', $r->id, Kinds::PERSON, $r->user_id, Roles::MEMBER, Kinds::CLUB, $r->tenant_id, $attrs, $apply, $count);
        }
    }

    private function federationClubs(bool $apply, callable $count): void
    {
        foreach (DB::table('federation_clubs')->whereIn('status', ['approved', 'removed'])->whereNotNull('tenant_id')->get() as $r) {
            $attrs = ['starts_at' => $this->at($r->decided_at ?? $r->created_at), 'granted_by' => $r->decided_by];
            if ($r->status === 'removed') {
                $attrs += ['ends_at' => $this->at($r->updated_at), 'end_reason' => 'removed'];
            }
            $this->grantFrom('federation_clubs', $r->id, Kinds::CLUB, $r->tenant_id, Roles::MEMBER_CLUB, Kinds::FEDERATION, $r->federation_id, $attrs, $apply, $count);
        }
    }

    private function chainClubs(bool $apply, callable $count): void
    {
        foreach (DB::table('tenants')->whereNotNull('business_id')->get(['id', 'business_id', 'updated_at']) as $r) {
            $this->grantFrom('tenants.business_id', $r->id, Kinds::CLUB, $r->id, Roles::CHAIN_CLUB, Kinds::BUSINESS, $r->business_id,
                ['starts_at' => $this->at($r->updated_at)], $apply, $count);
        }
    }

    private function subBodies(bool $apply, callable $count): void
    {
        foreach (DB::table('federations')->whereNotNull('parent_id')->get(['id', 'parent_id', 'created_at']) as $r) {
            $this->grantFrom('federations.parent_id', $r->id, Kinds::FEDERATION, $r->id, Roles::SUB_BODY, Kinds::FEDERATION, $r->parent_id,
                ['starts_at' => $this->at($r->created_at)], $apply, $count);
        }
    }

    private function eventHosts(bool $apply, callable $count): void
    {
        foreach (DB::table('club_events')->get(['id', 'tenant_id', 'host_type', 'host_id', 'created_by', 'created_at']) as $e) {
            if ($e->tenant_id) {
                $this->grantFrom('club_events.tenant_id', $e->id, Kinds::CLUB, $e->tenant_id, Roles::HOST, Kinds::EVENT, $e->id,
                    ['starts_at' => $this->at($e->created_at)], $apply, $count);
            } elseif ($e->host_type === 'federation' && $e->host_id) {
                $this->grantFrom('club_events.host', $e->id, Kinds::FEDERATION, $e->host_id, Roles::HOST, Kinds::EVENT, $e->id,
                    ['starts_at' => $this->at($e->created_at)], $apply, $count);
            }
            if ($e->created_by) {
                $this->grantFrom('club_events.created_by', $e->id, Kinds::PERSON, $e->created_by, Roles::ORGANISER, Kinds::EVENT, $e->id,
                    ['starts_at' => $this->at($e->created_at), 'meta' => ['creator' => true]], $apply, $count);
            }
        }
    }

    private function eventOfficials(bool $apply, callable $count): void
    {
        foreach (DB::table('event_officials')->get(['id', 'event_id', 'user_id', 'role', 'role_label', 'assigned_by', 'created_at']) as $o) {
            $role = $o->role === 'organiser' ? Roles::ORGANISER : Roles::OFFICIAL;
            $this->grantFrom('event_officials', $o->id, Kinds::PERSON, $o->user_id, $role, Kinds::EVENT, $o->event_id,
                ['starts_at' => $this->at($o->created_at), 'title' => $o->role_label, 'granted_by' => $o->assigned_by,
                    'meta' => ['official_role' => $o->role]], $apply, $count);
        }
    }

    /* ---------------- helpers ---------------- */

    private function grantFrom(string $table, int $id, string $fromKind, $fromId, string $role, string $toKind, $toId, array $attrs, bool $apply, callable $count): void
    {
        $source = "{$table}:{$id}";
        // Not new if this source was copied before, or if the same open
        // relationship already exists from another source (a creator who is
        // also an appointed organiser is ONE organiser) — grant() would
        // return that row rather than create one.
        $isNew = ! PartyRelation::where('source', $source)->exists()
            && ! (empty($attrs['ends_at']) && $this->activeExists($fromKind, $fromId, $toKind, $toId, $role));
        $count($table, $isNew);
        if (! $isNew || ! $apply || ! $fromId || ! $toId) {
            return;
        }

        $from = Kinds::find($fromKind, (int) $fromId);
        $to = Kinds::find($toKind, (int) $toId);
        if (! $from || ! $to) {
            return;   // dangling reference in the old data — nothing to relate
        }

        $this->relations->grant($from, $role, $to, $attrs + ['source' => $source]);
    }

    private function activeExists(string $fromType, $fromId, string $toType, $toId, string $role): bool
    {
        return PartyRelation::query()->active()
            ->where(['from_type' => $fromType, 'from_id' => (int) $fromId, 'to_type' => $toType, 'to_id' => (int) $toId, 'role' => $role])
            ->exists();
    }

    private function at($value): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }
}
