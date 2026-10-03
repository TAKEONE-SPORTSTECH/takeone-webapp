<?php

namespace App\Parties;

use App\Parties\Models\PartyRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY writer of party_relations, and the keeper of its invariants
 * (PLAN.md §3.2). Every module that records "who holds what in which party"
 * calls this; nothing writes the table directly.
 *
 * Invariants:
 *   · a relationship is ENDED, never deleted;
 *   · ownership is changed only as a whole set (setOwners / transferShare), in
 *     one transaction, and the active owners' shares always sum to exactly
 *     100% (10000 basis points) — never 99, never 101, never zero owners;
 *   · granting a role someone already actively holds is a no-op, so dual writes
 *     and re-run backfills cannot duplicate;
 *   · a write carrying a `source` that already exists returns that row — the
 *     backfill is idempotent by construction.
 */
final class Relations
{
    public const FULL = 10000;

    public const MAJORITY = 5000;   // MORE than this decides (owner, 2026-10-03)

    /* ================= reading ================= */

    /** @return Collection<int, PartyRelation> active relations held IN a party */
    public function active(Model $to, string|array|null $role = null, $at = null): Collection
    {
        return PartyRelation::query()->in($to)->active($at)
            ->when($role !== null, fn ($q) => $q->whereIn('role', (array) $role))
            ->orderBy('starts_at')->orderBy('id')
            ->get();
    }

    /** @return Collection<int, PartyRelation> active relations a party HOLDS elsewhere */
    public function heldBy(Model $from, string|array|null $role = null, $at = null): Collection
    {
        return PartyRelation::query()->heldBy($from)->active($at)
            ->when($role !== null, fn ($q) => $q->whereIn('role', (array) $role))
            ->orderBy('starts_at')->orderBy('id')
            ->get();
    }

    public function holds(Model $from, string|array $role, Model $to, $at = null): bool
    {
        return PartyRelation::query()->heldBy($from)->in($to)->active($at)
            ->whereIn('role', (array) $role)->exists();
    }

    /** @return Collection<int, PartyRelation> every relation ever held in a party, oldest first */
    public function history(Model $to): Collection
    {
        return PartyRelation::query()->in($to)->orderBy('starts_at')->orderBy('id')->get();
    }

    /** @return Collection<int, PartyRelation> the active owners, largest share first */
    public function owners(Model $to, $at = null): Collection
    {
        return $this->active($to, Roles::OWNER, $at)->sortByDesc('share_bp')->values();
    }

    /** The owner holding MORE than half, if there is one. A 50/50 split has none. */
    public function majorityOwner(Model $to): ?Model
    {
        $top = $this->owners($to)->first();

        return $top && $top->share_bp > self::MAJORITY ? $top->holder() : null;
    }

    /* ================= writing ================= */

    /**
     * Start a (non-ownership) relationship. Idempotent: an identical ACTIVE
     * relation, or any row with the same `source`, is returned as it is.
     *
     * @param  array{title?: ?string, meta?: ?array, starts_at?: mixed, granted_by?: ?int, source?: ?string, ends_at?: mixed, end_reason?: ?string}  $attrs
     */
    public function grant(Model $from, string $role, Model $to, array $attrs = []): PartyRelation
    {
        if (! Roles::exists($role)) {
            throw new \InvalidArgumentException("Unknown role '{$role}' (App\\Parties\\Roles).");
        }
        if ($role === Roles::OWNER) {
            throw new \LogicException('Ownership changes as a whole set: use setOwners() or transferShare().');
        }

        if (! empty($attrs['source']) && ($existing = PartyRelation::where('source', $attrs['source'])->first())) {
            return $existing;
        }

        // Only an open-ended grant can be a duplicate of an active one; a
        // historical row (already ended — a backfilled past stint) never is.
        if (empty($attrs['ends_at'])
            && ($existing = PartyRelation::query()->heldBy($from)->in($to)->active()->where('role', $role)->first())) {
            return $existing;
        }

        return PartyRelation::create($this->row($from, $role, $to, $attrs));
    }

    /** End one relationship. Owners are ended through setOwners(), never here. */
    public function end(PartyRelation $relation, string $reason, ?int $by = null, $at = null): PartyRelation
    {
        if ($relation->role === Roles::OWNER) {
            throw new \LogicException('An owner is ended by changing the ownership set: use setOwners() or transferShare().');
        }
        if (! $relation->isActive()) {
            return $relation;
        }

        $relation->forceFill(['ends_at' => $at ?? now(), 'end_reason' => $reason, 'ended_by' => $by])->save();

        return $relation;
    }

    /** End every active `role` that `from` holds in `to`. Returns how many ended. */
    public function endRole(Model $from, string $role, Model $to, string $reason, ?int $by = null): int
    {
        $n = 0;
        foreach (PartyRelation::query()->heldBy($from)->in($to)->active()->where('role', $role)->get() as $r) {
            $this->end($r, $reason, $by);
            $n++;
        }

        return $n;
    }

    /**
     * Replace the ownership of a party with a new set, atomically.
     *
     * Owners whose share is unchanged keep their row (their start date is
     * history worth keeping); everyone else's row is ended and a new one
     * started. The new set must be non-empty, every share positive, and the
     * total exactly 100%.
     *
     * @param  list<array{0: Model, 1: int}>  $owners  [[person, share_bp], …]
     * @return Collection<int, PartyRelation> the active owners afterwards
     */
    public function setOwners(Model $to, array $owners, string $reason, ?int $by = null, array $attrs = []): Collection
    {
        $wanted = $this->validateOwnerSet($owners);

        return $this->applyOwners($to, $wanted, $reason, $by, $attrs);
    }

    /**
     * Check an ownership set without writing it: non-empty, every share
     * positive, nobody twice, total exactly 100%. Throws on the first problem.
     *
     * @param  list<array{0: Model, 1: int}>  $owners
     * @return array<string, array{0: Model, 1: int}> keyed "kind:id"
     */
    public function validateOwnerSet(array $owners): array
    {
        $wanted = [];
        foreach ($owners as [$holder, $share]) {
            $share = (int) $share;
            if ($share <= 0) {
                throw new \InvalidArgumentException('Every owner must hold a positive share.');
            }
            $key = Kinds::keyOf($holder).':'.$holder->getKey();
            if (isset($wanted[$key])) {
                throw new \InvalidArgumentException('An owner is listed twice.');
            }
            $wanted[$key] = [$holder, $share];
        }
        if ($wanted === []) {
            throw new \InvalidArgumentException('An owned party must keep at least one owner.');
        }
        if (array_sum(array_column($wanted, 1)) !== self::FULL) {
            throw new \InvalidArgumentException('Ownership shares must add up to exactly 100%.');
        }

        return $wanted;
    }

    /** @param  array<string, array{0: Model, 1: int}>  $wanted */
    private function applyOwners(Model $to, array $wanted, string $reason, ?int $by, array $attrs): Collection
    {
        return DB::transaction(function () use ($to, $wanted, $reason, $by, $attrs) {
            $now = now();

            foreach ($this->lockedOwners($to) as $row) {
                $key = $row->from_type.':'.$row->from_id;
                if (isset($wanted[$key]) && (int) $row->share_bp === $wanted[$key][1]) {
                    unset($wanted[$key]);   // unchanged: keep the row and its history

                    continue;
                }
                $row->forceFill(['ends_at' => $now, 'end_reason' => $reason, 'ended_by' => $by])->save();
            }

            foreach ($wanted as [$holder, $share]) {
                PartyRelation::create($this->row($holder, Roles::OWNER, $to, [
                    'share_bp' => $share, 'starts_at' => $now, 'granted_by' => $by,
                ] + $attrs));
            }

            $this->assertOwnership($to);

            return $this->owners($to);
        });
    }

    /**
     * Move `$share` basis points of `$to` from one owner to another person
     * (a sale of part or all of a stake). The seller must hold at least that much.
     */
    public function transferShare(Model $to, Model $seller, Model $buyer, int $share, string $reason, ?int $by = null): Collection
    {
        $set = [];
        foreach ($this->owners($to) as $row) {
            $set[$row->from_type.':'.$row->from_id] = [$row->holder(), (int) $row->share_bp];
        }

        $sellerKey = Kinds::keyOf($seller).':'.$seller->getKey();
        $buyerKey = Kinds::keyOf($buyer).':'.$buyer->getKey();

        if (! isset($set[$sellerKey]) || $set[$sellerKey][1] < $share || $share <= 0) {
            throw new \InvalidArgumentException('The seller does not hold that share.');
        }

        $set[$sellerKey][1] -= $share;
        if ($set[$sellerKey][1] === 0) {
            unset($set[$sellerKey]);
        }
        $set[$buyerKey] = [$buyer, ($set[$buyerKey][1] ?? 0) + $share];

        return $this->setOwners($to, array_values($set), $reason, $by);
    }

    /** Throws when the active owners of a party break an invariant. */
    public function assertOwnership(Model $to): void
    {
        $owners = $this->active($to, Roles::OWNER);
        if ($owners->isEmpty()) {
            return;   // never had owners recorded (e.g. not backfilled yet) — nothing to check
        }
        if ((int) $owners->sum('share_bp') !== self::FULL) {
            throw new \LogicException('Ownership of '.Kinds::keyOf($to).' '.$to->getKey().' does not sum to 100%.');
        }
    }

    /* ================= internals ================= */

    /** @return Collection<int, PartyRelation> */
    private function lockedOwners(Model $to): Collection
    {
        return PartyRelation::query()->in($to)->active()->where('role', Roles::OWNER)->lockForUpdate()->get();
    }

    private function row(Model $from, string $role, Model $to, array $attrs): array
    {
        return [
            'from_type' => Kinds::keyOf($from),
            'from_id' => $from->getKey(),
            'to_type' => Kinds::keyOf($to),
            'to_id' => $to->getKey(),
            'role' => $role,
            'share_bp' => $attrs['share_bp'] ?? null,
            'title' => $attrs['title'] ?? null,
            'meta' => $attrs['meta'] ?? null,
            'starts_at' => $attrs['starts_at'] ?? now(),
            'ends_at' => $attrs['ends_at'] ?? null,
            'end_reason' => $attrs['end_reason'] ?? null,
            'granted_by' => $attrs['granted_by'] ?? null,
            'source' => $attrs['source'] ?? null,
        ];
    }
}
