<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Suggested for you" people recommendations (the default Find-People state,
 * social-media style). Candidates are always restricted to discoverable
 * club-mates (never platform-wide) and ranked by closeness signals — shared
 * clubs, mutual follows, trainer status, popularity — with a human "reason"
 * attached to each suggestion.
 */
class PeopleRecommendationService
{
    private const W_SHARED_CLUB = 50;   // per club in common (strongest signal)

    private const W_MUTUAL = 30;   // per mutual follow (friend-of-friend)

    private const W_TRAINER = 15;   // coaches are broadly useful to surface

    private const W_POPULARITY = 0.5;  // gentle tiebreaker, capped below

    public function suggest(User $me, int $limit = 24): Collection
    {
        $myClubIds = DB::table('memberships')->where('user_id', $me->id)->pluck('tenant_id');
        $myFollowingIds = DB::table('user_follows')->where('follower_id', $me->id)->pluck('followee_id');

        $blockedIds = UserBlock::where('blocker_id', $me->id)->pluck('blocked_id')
            ->merge(UserBlock::where('blocked_id', $me->id)->pluck('blocker_id'));

        // Suggestions exclude yourself, blocks, and people you already follow.
        $exclude = collect([$me->id])->merge($blockedIds)->merge($myFollowingIds)->unique();

        // --- Signal 1: club-mates (shared-club count + one sample club per user) ---
        $clubMates = $myClubIds->isEmpty() ? collect() : DB::table('memberships')
            ->whereIn('tenant_id', $myClubIds)
            ->whereNotIn('user_id', $exclude)
            ->select('user_id', DB::raw('COUNT(*) as shared'))
            ->groupBy('user_id')->get()->keyBy('user_id');

        $sampleClubByUser = $myClubIds->isEmpty() ? collect() : DB::table('memberships')
            ->whereIn('tenant_id', $myClubIds)
            ->whereNotIn('user_id', $exclude)
            ->get(['user_id', 'tenant_id'])
            ->groupBy('user_id')->map(fn ($rows) => $rows->first()->tenant_id);

        $clubNames = $myClubIds->isEmpty() ? collect() : Tenant::whereIn('id', $myClubIds)->pluck('club_name', 'id');

        // --- Signal 2: mutual follows (people followed by people you follow) ---
        $mutuals = $myFollowingIds->isEmpty() ? collect() : DB::table('user_follows')
            ->whereIn('follower_id', $myFollowingIds)
            ->whereNotIn('followee_id', $exclude)
            ->select('followee_id', DB::raw('COUNT(*) as mutuals'))
            ->groupBy('followee_id')->get()->keyBy('followee_id');

        // Candidates are restricted to club-mates only — discovery never crosses club
        // boundaries, so a mutual follow with no shared club is not a valid candidate.
        $candidateIds = $clubMates->keys()->values();

        $chosen = collect();
        if ($candidateIds->isNotEmpty()) {
            $users = User::whereIn('id', $candidateIds)
                ->where('is_discoverable', true)
                ->withCount('followers')
                ->get(['id', 'uuid', 'slug', 'full_name', 'name', 'profile_picture', 'gender', 'is_personal_trainer', 'updated_at']);

            $chosen = $users->map(function ($u) use ($clubMates, $mutuals, $sampleClubByUser, $clubNames) {
                $shared = (int) ($clubMates[$u->id]->shared ?? 0);
                $mut = (int) ($mutuals[$u->id]->mutuals ?? 0);
                $score = $shared * self::W_SHARED_CLUB
                    + $mut * self::W_MUTUAL
                    + ($u->is_personal_trainer ? self::W_TRAINER : 0)
                    + min((int) $u->followers_count, 20) * self::W_POPULARITY;

                $clubName = $shared > 0 ? ($clubNames[$sampleClubByUser[$u->id] ?? null] ?? null) : null;

                return $this->present($u, $score, $this->reasonFor($shared, $mut, $u->is_personal_trainer, $clubName));
            })->sortByDesc('_score')->values();
        }

        // No platform-wide top-up: suggestions never cross club boundaries, so once
        // every discoverable club-mate has been surfaced, the list simply ends there.

        // Drop internal-only fields before the list reaches the view/JSON.
        return $chosen->take($limit)
            ->map(fn ($p) => collect($p)->except(['_score', 'id'])->all())
            ->values();
    }

    private function reasonFor(int $shared, int $mutuals, bool $isTrainer, ?string $clubName): string
    {
        if ($shared > 0 && $clubName) {
            return __('personal.reason_same_club', ['club' => $clubName]);
        }
        if ($mutuals > 0) {
            return trans_choice('personal.reason_mutual', $mutuals, ['count' => $mutuals]);
        }
        if ($isTrainer) {
            return __('personal.reason_trainer');
        }

        return __('personal.reason_suggested');
    }

    private function present(User $u, float $score, string $reason): array
    {
        return [
            'id' => $u->id,
            '_score' => $score,
            'uuid' => $u->uuid,
            'slug' => $u->slug,
            'name' => $u->full_name ?: $u->name,
            'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
            'gender' => $u->gender,
            'is_trainer' => (bool) $u->is_personal_trainer,
            'is_following' => false, // suggestions exclude already-followed
            'reason' => $reason,
            'profile_url' => route('people.show', $u->uuid),
        ];
    }
}
