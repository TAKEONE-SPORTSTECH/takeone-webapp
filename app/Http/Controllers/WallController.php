<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WallController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        $me = Auth::user();

        // Your own wall lives at /me.
        if ($me->id === $user->id) {
            return redirect()->route('me.home');
        }

        $relationship = $me->relationshipWith($user);
        $canView      = $me->canViewWall($user);
        $blocked      = $relationship['blocked'] || $relationship['blockedBy'];

        $posts = collect();
        if ($canView && ! $blocked) {
            $posts = UserPost::where('user_id', $user->id)
                ->with(['user:id,slug,full_name,profile_picture,updated_at', 'likes:id,user_post_id,user_id', 'comments.user:id,full_name,profile_picture,updated_at'])
                ->withCount(['likes', 'views'])
                ->latest()
                ->get()
                ->map(fn ($p) => $p->toFeedArray($me))
                ->values();
        }

        $stats = [
            'posts'          => UserPost::where('user_id', $user->id)->count(),
            'followers'      => $user->followers()->count(),
            'following'      => $user->following()->count(),
            'participations' => $user->tournamentEvents()->count(),
            'achievements'   => \App\Models\PerformanceResult::whereHas(
                'tournamentEvent', fn ($q) => $q->where('user_id', $user->id)
            )->whereIn('medal_type', ['gold', 'silver', 'bronze'])->count(),
        ];

        // Clubs this member belongs to (shown under their name).
        $clubs = $user->memberClubs()->pluck('club_name')->all();

        // Shared-club context (which of those you also belong to).
        $sharedClubs = $me->memberClubs()
            ->whereIn('tenants.id', $user->memberClubs()->pluck('tenants.id'))
            ->pluck('club_name')
            ->all();

        return view('personal.wall', [
            'profile'      => $user,
            'avatar'       => $user->profile_picture
                ? asset('storage/' . $user->profile_picture) . '?v=' . optional($user->updated_at)->timestamp
                : null,
            'posts'        => $posts,
            'relationship' => $relationship,
            'canView'      => $canView,
            'blocked'      => $blocked,
            'stats'        => $stats,
            'clubs'        => $clubs,
            'sharedClubs'  => $sharedClubs,
        ]);
    }
}
