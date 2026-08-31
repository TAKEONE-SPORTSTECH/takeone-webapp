<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserFollow;
use App\Models\UserNotification;
use App\Models\UserPost;
use Illuminate\Support\Facades\DB;

/*
 * Shared kernel — deliberately NOT private to a module.
 * Consumed by App\Http (UserPostController) and by AchievementVerificationService.
 * Feed fan-out serves every vertical that publishes, so it stays shared.
 */

/**
 * Live-delivers a freshly created feed post: MQTT push (posts channel) to the author's
 * followers (Following + All feeds) and club-mates (All feed), plus a notification to
 * each — minus anyone blocked either way, and never the author themselves.
 *
 * Single source of truth for feed fan-out, used by the manual post flow
 * (UserPostController) and by system announcements (e.g. a verified achievement).
 */
class FeedPublisher
{
    public function fanOut(
        User $author,
        UserPost $post,
        array $card,
        string $snippet,
        string $notifType = 'post',
        ?string $notifTitle = null,
        string $notifIcon = 'bi-postcard-heart',
    ): void {
        $followerIds = UserFollow::where('followee_id', $author->id)->pluck('follower_id');

        $clubIds = $author->memberClubs()->pluck('tenants.id');
        $clubMateIds = $clubIds->isEmpty()
            ? collect()
            : DB::table('memberships')->whereIn('tenant_id', $clubIds)
                ->where('user_id', '!=', $author->id)->distinct()->pluck('user_id');

        $blockedIds = UserBlock::where('blocker_id', $author->id)->pluck('blocked_id')
            ->merge(UserBlock::where('blocked_id', $author->id)->pluck('blocker_id'))
            ->map(fn ($id) => (int) $id);

        $allowed = fn ($id) => (int) $id !== (int) $author->id && ! $blockedIds->contains((int) $id);
        $followers = $followerIds->filter($allowed)->unique()->values();
        $clubOnly = $clubMateIds->filter(fn ($id) => $allowed($id) && ! $followerIds->contains($id))->unique()->values();

        $card['author']['isMe'] = false;
        $this->broadcast($followers, ['action' => 'new', 'feeds' => ['following', 'all'], 'post' => $card]);
        $this->broadcast($clubOnly, ['action' => 'new', 'feeds' => ['all'], 'post' => $card]);

        foreach ($followers->merge($clubOnly)->unique() as $recipientId) {
            UserNotification::notifyUser((int) $recipientId, $notifType, $notifTitle ?? ($author->full_name.' shared a new post'), [
                'actor_id' => $author->id,
                'action_url' => $post->permalink(),
                'icon' => $notifIcon,
                'body' => $snippet,
                'subject_type' => 'post',
                'subject_id' => $post->id,
            ]);
        }
    }

    private function broadcast($userIds, array $payload): void
    {
        try {
            if (! (function_exists('Realtime') && Realtime()->enabled())) {
                return;
            }
            $batch = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values()
                ->map(fn ($uid) => ['topic' => Realtime()->userTopic($uid, 'posts'), 'payload' => $payload])
                ->all();
            if ($batch) {
                Realtime()->publishMany($batch);
            }
        } catch (\Throwable $e) {
            // Realtime is best-effort; the DB is the source of truth.
        }
    }
}
