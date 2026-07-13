<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use App\Models\UserFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Follow / connection-request / block actions on the social graph.
 * Every endpoint returns the viewer's relationship state so the wall button
 * can update in place (no reload).
 */
class ConnectionController extends Controller
{
    public function follow(User $user): JsonResponse
    {
        $me = Auth::user();
        $this->guard($me, $user);

        $follow = UserFollow::firstOrCreate(['follower_id' => $me->id, 'followee_id' => $user->id]);

        if ($follow->wasRecentlyCreated) {
            \App\Models\UserNotification::notifyUser($user->id, 'follow', $me->full_name.' started following you', [
                'actor_id' => $me->id,
                'action_url' => route('people.show', $me->uuid),
                'icon' => 'bi-person-plus-fill',
                'context' => $me->full_name,
            ]);
        }

        return $this->state($me, $user, 'Following');
    }

    public function unfollow(User $user): JsonResponse
    {
        $me = Auth::user();
        UserFollow::where('follower_id', $me->id)->where('followee_id', $user->id)->delete();

        return $this->state($me, $user);
    }

    public function block(User $user): JsonResponse
    {
        $me = Auth::user();

        DB::transaction(function () use ($me, $user) {
            // Sever every existing relationship in both directions.
            UserFollow::where(function ($q) use ($me, $user) {
                $q->where(['follower_id' => $me->id, 'followee_id' => $user->id])
                    ->orWhere(['follower_id' => $user->id, 'followee_id' => $me->id]);
            })->delete();
            UserConnection::betweenUsers($me->id, $user->id)->delete();
            UserBlock::firstOrCreate(['blocker_id' => $me->id, 'blocked_id' => $user->id]);
        });

        return $this->state($me, $user, 'User blocked');
    }

    public function unblock(User $user): JsonResponse
    {
        $me = Auth::user();
        UserBlock::where('blocker_id', $me->id)->where('blocked_id', $user->id)->delete();

        return $this->state($me, $user, 'User unblocked');
    }

    /** Refuse interactions when either party has blocked the other. */
    private function guard(User $me, User $user): void
    {
        abort_if($me->id === $user->id, 403);
        abort_if($me->blockedEitherWay($user->id), 403, 'This action is unavailable.');
    }

    private function state(User $me, User $user, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'relationship' => $me->fresh()->relationshipWith($user),
            'canView' => $me->fresh()->canViewWall($user),
        ]);
    }
}
