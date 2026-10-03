<?php

namespace App\Clubs\Controllers;

use App\Clubs\Concerns\HandlesClubAuthorization;
use App\Clubs\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Parties\Kinds;
use App\Parties\Models\PartyMotion;
use App\Parties\Motions;
use App\Parties\Relations;
use App\Parties\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A club's owners, their shares, the history, and changing them the way the
 * owners decide (PLAN.md Phase 5; owner's rules 2026-10-03): the majority
 * owner acts alone, otherwise the owners vote by share — more than 50% passes.
 *
 * Only while PARTIES_ENABLED — otherwise every door answers 404. Reading needs
 * the same access as the rest of the club's admin; proposing and voting are
 * the owners' (App\Parties\Motions refuses anyone else, whatever this
 * controller is asked).
 */
class ClubOwnershipController extends Controller
{
    use HandlesClubAuthorization;

    public function __construct(private Relations $relations, private Motions $motions) {}

    public function show(Tenant $club): JsonResponse
    {
        $this->guard($club);

        return response()->json(['success' => true] + $this->state($club));
    }

    public function propose(Request $request, Tenant $club): JsonResponse
    {
        $this->guard($club);

        $data = $request->validate([
            'owners' => ['required', 'array', 'min:1', 'max:20'],
            'owners.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'owners.*.share' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $set = [];
        foreach ($data['owners'] as $row) {
            $set[] = [User::findOrFail($row['user_id']), (int) round(((float) $row['share']) * 100)];
        }
        if (array_sum(array_column($set, 1)) !== Relations::FULL) {
            return $this->fail(__('parties::parties.err_total'), 422);
        }

        try {
            $motion = $this->motions->propose($club, $request->user(), $set, $data['reason'] ?? null);
        } catch (\DomainException) {
            return $this->fail(__('parties::parties.err_owner_only'), 403);
        } catch (\InvalidArgumentException) {
            return $this->fail(__('parties::parties.err_total'), 422);
        }

        $this->broadcast($club);

        return response()->json([
            'success' => true,
            'message' => $motion->status === 'passed' ? __('parties::parties.saved_applied') : __('parties::parties.saved_open'),
        ] + $this->state($club->fresh()));
    }

    public function vote(Request $request, Tenant $club, PartyMotion $motion): JsonResponse
    {
        $this->guard($club, $motion);
        $data = $request->validate(['approve' => ['required', 'boolean']]);

        try {
            $this->motions->vote($motion, $request->user(), (bool) $data['approve']);
        } catch (\DomainException) {
            return $this->fail($motion->isOpen() ? __('parties::parties.err_owner_only') : __('parties::parties.err_decided'), 403);
        }

        $this->broadcast($club);

        return response()->json(['success' => true, 'message' => __('parties::parties.saved_vote')] + $this->state($club->fresh()));
    }

    public function withdraw(Request $request, Tenant $club, PartyMotion $motion): JsonResponse
    {
        $this->guard($club, $motion);

        try {
            $this->motions->withdraw($motion, $request->user());
        } catch (\DomainException) {
            return $this->fail(__('parties::parties.err_owner_only'), 403);
        }

        $this->broadcast($club);

        return response()->json(['success' => true, 'message' => __('parties::parties.saved_withdrawn')] + $this->state($club->fresh()));
    }

    /* ---------------- internals ---------------- */

    private function guard(Tenant $club, ?PartyMotion $motion = null): void
    {
        abort_unless(config('parties.enabled'), 404);
        $this->authorizeClub($club);
        // A motion is reached only through ITS club — never another club's uuid.
        abort_if($motion && ($motion->to_type !== Kinds::CLUB || (int) $motion->to_id !== (int) $club->id), 404);
    }

    /** Everything the panel draws, scoped to what this viewer may do. Never an email or a phone. */
    private function state(Tenant $club): array
    {
        $me = auth()->user();
        $owners = $this->relations->owners($club);
        $majority = $this->relations->majorityOwner($club);
        $person = fn (?User $u) => $u ? [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
            'photo' => $u->profile_picture ? file_url($u->profile_picture) : null,
            'gender' => $u->gender,
        ] : null;

        $iOwn = $owners->contains(fn ($r) => (int) $r->from_id === (int) $me->id);
        $motion = $this->motions->open($club)->first();
        $motionOut = null;
        if ($motion) {
            $myVote = $motion->votes()->where('user_id', $me->id)->first();
            $motionOut = [
                'uuid' => $motion->uuid,
                'by' => $person(User::find($motion->proposed_by))['name'] ?? '',
                'reason' => $motion->reason,
                'proposal' => collect($motion->proposal)->map(fn ($p) => ['share_bp' => (int) $p['share_bp']] + ($person(Kinds::find($p['type'], (int) $p['id'])) ?? []))->all(),
                'tally' => $this->motions->tally($motion),
                'my_vote' => $myVote ? (bool) $myVote->approve : null,
                'can_vote' => $iOwn,
                'can_withdraw' => (int) $motion->proposed_by === (int) $me->id || $me->isSuperAdmin(),
                'at' => $motion->created_at?->toIso8601String(),
            ];
        }

        return [
            'owners' => $owners->map(fn ($r) => ($person($r->holder()) ?? []) + [
                'share_bp' => (int) $r->share_bp,
                'since' => $r->starts_at?->toDateString(),
                'me' => (int) $r->from_id === (int) $me->id,
            ])->values()->all(),
            'history' => $this->relations->history($club)->where('role', Roles::OWNER)->whereNotNull('ends_at')->sortByDesc('ends_at')
                ->map(fn ($r) => ($person($r->holder()) ?? []) + [
                    'share_bp' => (int) $r->share_bp,
                    'from' => $r->starts_at?->toDateString(),
                    'to' => $r->ends_at?->toDateString(),
                ])->values()->all(),
            'motion' => $motionOut,
            'can_propose' => $iOwn || $me->isSuperAdmin(),
            'i_decide' => $me->isSuperAdmin() || ($majority && $majority->is($me)),
        ];
    }

    /** Owners and admins re-fetch their own view (the payload differs per viewer). */
    private function broadcast(Tenant $club): void
    {
        try {
            if (! function_exists('Realtime') || ! Realtime()->enabled()) {
                return;
            }
            $ids = $this->relations->active($club, [Roles::OWNER, Roles::ADMIN])->pluck('from_id')->unique();
            foreach ($ids as $id) {
                Realtime()->publishToUser((int) $id, 'ownership', ['action' => 'refresh', 'club' => $club->slug]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
