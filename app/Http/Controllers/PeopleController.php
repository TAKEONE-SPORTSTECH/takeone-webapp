<?php

namespace App\Http\Controllers;

use App\Models\ClubAchievement;
use App\Models\Duel;
use App\Models\User;
use App\Services\PeopleRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * People discovery: a club-scoped member search ("Find People" only surfaces
 * discoverable members who share a club with the viewer) and a SAFE public
 * profile that exposes only non-sensitive fields (no health, billing,
 * documents, contacts, or family). Private data stays on the family/admin-gated
 * member.show. Members opt out via users.is_discoverable.
 */
class PeopleController extends Controller
{
    /** The Find-People search page with a "Suggested for you" default state. */
    public function index(Request $request, PeopleRecommendationService $recommender)
    {
        $suggestions = $recommender->suggest(Auth::user());

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // The mobile shell labels its header from $shellTitle for routes that
        // aren't in its own nav list.
        return view($isMobile ? 'people.mobile.index' : 'people.desktop.index', compact('suggestions'))
            ->with('shellTitle', __('personal.find_people'));
    }

    /** AJAX club-scoped member search (discoverable club-mates only). */
    public function search(Request $request)
    {
        $me = Auth::user();
        $q = trim((string) $request->query('q', ''));

        // Blocks are mutual: hide anyone the viewer blocked or who blocked them.
        $blockedIds = \App\Models\UserBlock::idsBlockedEitherWayWith($me->id);

        // Only members who share at least one club with the viewer are discoverable.
        $clubMateIds = $this->clubMateIds($me);

        $users = User::query()
            ->where('is_discoverable', true)
            ->whereNotIn('id', $blockedIds)
            ->whereIn('id', $clubMateIds)
            // Match by name, email, or phone (mobile is stored as a
            // {"code","number"} JSON blob, so LIKE matches the digits too).
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('mobile', 'like', "%{$q}%")))
            ->orderBy('full_name')
            ->limit(24)
            ->get(['id', 'uuid', 'slug', 'full_name', 'name', 'profile_picture', 'gender', 'is_personal_trainer', 'updated_at']);

        $followingIds = $me->following()->pluck('users.id');

        return response()->json([
            'success' => true,
            'query' => $q,
            'people' => $users->map(fn ($u) => [
                'uuid' => $u->uuid,
                'slug' => $u->slug,
                'name' => $u->full_name ?: $u->name,
                'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
                'gender' => $u->gender,
                'is_trainer' => (bool) $u->is_personal_trainer,
                'is_following' => $followingIds->contains($u->id),
                'profile_url' => route('people.show', $u->uuid),
            ])->values(),
        ]);
    }

    /** SAFE public profile — broadly viewable, sensitive data omitted. */
    public function show(Request $request, string $uuid)
    {
        $me = Auth::user();
        $person = User::where('uuid', $uuid)->firstOrFail();

        // Your own public profile → go to your full private profile instead.
        if ($person->id === $me->id) {
            return redirect()->route('member.show', $person->uuid);
        }

        abort_unless($person->canViewPublicProfile($me), 404);

        $clubIds = $person->memberClubs()->pluck('tenants.id');

        $affiliations = $person->clubAffiliations()
            ->with(['tenant:id,slug,country,club_name', 'skillAcquisitions:id,club_affiliation_id,skill_name'])
            ->orderByDesc('start_date')
            ->get();
        $activeAffil = $affiliations->whereNull('end_date')->values();
        $pastAffil = $affiliations->whereNotNull('end_date')->values();

        // Medals earned via the person's clubs' achievements (safe, public).
        $awards = $clubIds->isEmpty() ? collect() : ClubAchievement::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->orderByDesc('achievement_date')
            ->with('tenant:id,club_name,slug,translations')
            ->get()
            ->map(function ($a) use ($person) {
                $athletes = is_array($a->athletes) ? $a->athletes : [];
                $mine = collect($athletes)->first(fn ($x) => is_array($x) && (int) ($x['user_id'] ?? 0) === (int) $person->id);
                $a->member_award = $mine['role'] ?? null;

                return $mine ? $a : null;
            })->filter()->values();

        // Challenge (duel) win-rate.
        $duelsTotal = Duel::where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_id', $person->id)->orWhere('opponent_id', $person->id))
            ->count();
        $duelWins = Duel::where('status', 'completed')->where('winner_id', $person->id)->count();
        $winRate = $duelsTotal > 0 ? round(($duelWins / $duelsTotal) * 100) : 0;

        $skills = $affiliations->flatMap(fn ($a) => $a->skillAcquisitions->pluck('skill_name'))
            ->filter()->unique()->take(12)->values();

        $data = [
            'person' => $person,
            'isMe' => false,
            'isFollowing' => $me->isFollowing($person->id),
            'canMessage' => $me->canMessage($person),
            'activeAffil' => $activeAffil,
            'pastAffil' => $pastAffil,
            'awards' => $awards,
            'skills' => $skills,
            'winRate' => $winRate,
            'duelsTotal' => $duelsTotal,
            'duelWins' => $duelWins,
        ];

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'people.mobile.show' : 'people.desktop.show', $data);
    }

    /** IDs of every user who shares at least one active club membership with the given user. */
    private function clubMateIds(User $user): \Illuminate\Support\Collection
    {
        $clubIds = $user->memberClubs()->wherePivot('status', 'active')->pluck('tenants.id');

        if ($clubIds->isEmpty()) {
            return collect();
        }

        return \Illuminate\Support\Facades\DB::table('memberships')
            ->whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id');
    }
}
