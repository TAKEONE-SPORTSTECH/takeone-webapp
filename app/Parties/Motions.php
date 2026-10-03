<?php

namespace App\Parties;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Parties\Models\PartyMotion;
use App\Parties\Models\PartyMotionVote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Changing who owns something, the way the owners decide it (owner, 2026-10-03):
 *
 *   · an owner holding MORE than 50% — or a super-admin — decides alone: the
 *     change applies at once (and is still recorded as a passed motion);
 *   · anyone else who owns part of it PROPOSES, and the owners vote, weighted
 *     by their shares. MORE than 50% of the shares saying yes passes it;
 *     50% or more saying no rejects it (it can no longer pass). A 50/50 split
 *     therefore never passes;
 *   · a new proposal supersedes an open one; the proposer may withdraw theirs;
 *   · every motion and vote is kept, dated.
 *
 * While the old single `owner_user_id` column is still read by the rest of the
 * platform (until the cut-over), an applied change also keeps it pointing at
 * the largest holder, and — for a club — gives every owner the club-admin role
 * the old checks need. Nothing is ever taken away from a previous owner here,
 * exactly as the transfer screen has always done.
 */
final class Motions
{
    public function __construct(private Relations $relations) {}

    /**
     * @param  list<array{0: User, 1: int}>  $owners  the ownership asked for
     */
    public function propose(Model $party, User $by, array $owners, ?string $reason = null): PartyMotion
    {
        $this->relations->validateOwnerSet($owners);

        $isOwner = $this->relations->holds($by, Roles::OWNER, $party);
        if (! $by->isSuperAdmin() && ! $isOwner) {
            throw new \DomainException('Only an owner may propose a change of ownership.');
        }

        return DB::transaction(function () use ($party, $by, $owners, $reason) {
            PartyMotion::where(['to_type' => Kinds::keyOf($party), 'to_id' => $party->getKey(), 'status' => 'open'])
                ->update(['status' => 'superseded', 'decided_at' => now()]);

            $motion = PartyMotion::create([
                'to_type' => Kinds::keyOf($party), 'to_id' => $party->getKey(), 'kind' => 'set_owners',
                'proposal' => array_map(fn ($o) => ['type' => Kinds::keyOf($o[0]), 'id' => $o[0]->getKey(), 'share_bp' => (int) $o[1]], $owners),
                'reason' => $reason, 'proposed_by' => $by->id, 'status' => 'open',
            ]);

            $majority = $this->relations->majorityOwner($party);
            if ($by->isSuperAdmin() || ($majority && $majority->is($by))) {
                $this->apply($motion);   // the majority (or the platform) decides alone

                return $motion->fresh();
            }

            $this->cast($motion, $by, true);
            $this->notifyOwners($motion, $by);

            return $motion->fresh();
        });
    }

    public function vote(PartyMotion $motion, User $voter, bool $approve): PartyMotion
    {
        if (! $motion->isOpen()) {
            throw new \DomainException('This proposal has already been decided.');
        }
        $party = $motion->party();
        if (! $party || ! $this->relations->holds($voter, Roles::OWNER, $party)) {
            throw new \DomainException('Only an owner may vote.');
        }

        return DB::transaction(function () use ($motion, $voter, $approve) {
            $this->cast($motion, $voter, $approve);

            return $motion->fresh();
        });
    }

    public function withdraw(PartyMotion $motion, User $by): PartyMotion
    {
        if (! $motion->isOpen() || ((int) $motion->proposed_by !== (int) $by->id && ! $by->isSuperAdmin())) {
            throw new \DomainException('Only the person who proposed it may withdraw an open proposal.');
        }
        $motion->update(['status' => 'withdrawn', 'decided_at' => now()]);

        return $motion;
    }

    /**
     * Where the vote stands, in basis points of the CURRENT shares.
     *
     * @return array{yes: int, no: int, undecided: int}
     */
    public function tally(PartyMotion $motion): array
    {
        $party = $motion->party();
        $shares = $party ? $this->relations->owners($party)->mapWithKeys(fn ($r) => [$r->from_id => (int) $r->share_bp]) : collect();
        $votes = $motion->votes()->get()->keyBy('user_id');

        $yes = $no = 0;
        foreach ($shares as $userId => $bp) {
            if (! isset($votes[$userId])) {
                continue;
            }
            $votes[$userId]->approve ? $yes += $bp : $no += $bp;
        }

        return ['yes' => $yes, 'no' => $no, 'undecided' => Relations::FULL - $yes - $no];
    }

    /** @return \Illuminate\Support\Collection<int, PartyMotion> */
    public function open(Model $party)
    {
        return PartyMotion::where(['to_type' => Kinds::keyOf($party), 'to_id' => $party->getKey(), 'status' => 'open'])->latest('id')->get();
    }

    /* ---------------- internals ---------------- */

    private function cast(PartyMotion $motion, User $voter, bool $approve): void
    {
        $share = (int) ($this->relations->owners($motion->party())->firstWhere('from_id', $voter->id)?->share_bp ?? 0);

        PartyMotionVote::updateOrCreate(
            ['motion_id' => $motion->id, 'user_id' => $voter->id],
            ['share_bp' => $share, 'approve' => $approve],
        );

        $t = $this->tally($motion);
        if ($t['yes'] > Relations::MAJORITY) {
            $this->apply($motion);
        } elseif ($t['no'] >= Relations::MAJORITY) {
            $motion->update(['status' => 'rejected', 'decided_at' => now()]);
        }
    }

    private function apply(PartyMotion $motion): void
    {
        $party = $motion->party();
        $owners = array_map(fn ($o) => [Kinds::find($o['type'], (int) $o['id']), (int) $o['share_bp']], $motion->proposal);

        $this->relations->setOwners($party, $owners, 'motion:'.$motion->uuid, (int) $motion->proposed_by);
        $motion->update(['status' => 'passed', 'decided_at' => now()]);

        $this->syncLegacy($party);
    }

    /**
     * Keep the old single-owner view truthful while it is still read: the
     * column names the largest holder (the current one stays on a tie), and a
     * club's owners all hold club-admin so the old checks let them in.
     */
    private function syncLegacy(Model $party): void
    {
        $owners = $this->relations->owners($party);
        $top = (int) $owners->first()->share_bp;
        $leaders = $owners->filter(fn ($r) => (int) $r->share_bp === $top)->pluck('from_id')->map(fn ($id) => (int) $id);

        if (! $leaders->contains((int) $party->owner_user_id)) {
            $party->forceFill(['owner_user_id' => $leaders->first()])->save();
        }

        if ($party instanceof Tenant) {
            foreach ($owners as $row) {
                $user = User::find($row->from_id);
                if ($user && ! $user->hasRole('club-admin', $party->id)) {
                    $user->assignRole('club-admin', $party->id);
                }
            }
        }
    }

    private function notifyOwners(PartyMotion $motion, User $by): void
    {
        $party = $motion->party();
        foreach ($this->relations->owners($party) as $row) {
            try {
                UserNotification::notifyUser((int) $row->from_id, 'ownership_motion', __('parties::parties.motion_notify_title'), [
                    'actor_id' => $by->id,
                    'body' => __('parties::parties.motion_notify_body', ['name' => $by->full_name ?? $by->name]),
                    'tenant_id' => $party instanceof Tenant ? $party->id : null,
                    'icon' => 'bi-people',
                ]);
            } catch (\Throwable $e) {
                report($e);   // a lost notification never loses the motion
            }
        }
    }
}
