<?php

namespace App\Support;

use App\Models\ClubEvent;
use App\Models\ClubProduct;
use App\Models\Duel;
use App\Models\User;
use App\Models\UserPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Computes "unseen" (red-dot) indicators for the member app's sections and feed
 * tabs by comparing the latest activity timestamp in each section against when
 * the user last viewed it (user_section_views). Best-effort + cheap: a handful
 * of MAX() queries, memoised per request.
 */
class SectionActivity
{
    /** Sections a client may mark seen. */
    public const SECTIONS = ['feed:all', 'feed:club', 'feed:following', 'feed:mine', 'challenge', 'events', 'market'];

    private array $memo = [];

    /** Persist that the user has now seen a section (clears its dot). */
    public function markSeen(User $user, string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return;
        }

        DB::table('user_section_views')->updateOrInsert(
            ['user_id' => $user->id, 'section' => $section],
            ['seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** Dots for the bottom nav: feed / challenge / events / market. */
    public function navDots(User $user): array
    {
        $d = $this->dots($user);

        return [
            'feed' => $d['feed:all'],
            'challenge' => $d['challenge'],
            'events' => $d['events'],
            'market' => $d['market'],
        ];
    }

    /** Dots for the feed sub-tabs: all / club / following / mine. */
    public function feedTabDots(User $user): array
    {
        $d = $this->dots($user);

        return [
            'all' => $d['feed:all'],
            'club' => $d['feed:club'],
            'following' => $d['feed:following'],
            'mine' => $d['feed:mine'],
        ];
    }

    /** section => bool (unseen content exists). Memoised. */
    public function dots(User $user): array
    {
        if (isset($this->memo[$user->id])) {
            return $this->memo[$user->id];
        }

        $seen = DB::table('user_section_views')->where('user_id', $user->id)
            ->pluck('seen_at', 'section')
            ->map(fn ($t) => $t ? Carbon::parse($t) : null);

        $latest = $this->latestActivity($user);

        $dots = [];
        foreach (self::SECTIONS as $section) {
            $at = $latest[$section] ?? null;
            $seenAt = $seen[$section] ?? null;
            $dots[$section] = $at !== null && ($seenAt === null || $at->gt($seenAt));
        }

        return $this->memo[$user->id] = $dots;
    }

    /** section => latest Carbon (or null). */
    private function latestActivity(User $user): array
    {
        $clubIds = $user->memberClubs()->pluck('tenants.id');
        $followingIds = DB::table('user_follows')->where('follower_id', $user->id)->pluck('followee_id');

        $clubMateIds = $clubIds->isEmpty() ? collect() : DB::table('memberships')
            ->whereIn('tenant_id', $clubIds)->where('user_id', '!=', $user->id)->distinct()->pluck('user_id');

        $memberAuthorIds = $clubMateIds->merge($followingIds)->unique();

        $clubPostLatest = $clubIds->isEmpty() ? null : optional(DB::table('club_timeline_posts')
            ->whereIn('tenant_id', $clubIds)->max('created_at'), fn ($t) => Carbon::parse($t));

        $followingLatest = $followingIds->isEmpty() ? null : $this->maxUserPost(
            UserPost::whereIn('user_id', $followingIds)->whereNull('hidden_at')
        );

        $mineLatest = $this->maxUserPost(UserPost::where('user_id', $user->id));

        $memberPostLatest = $memberAuthorIds->isEmpty() ? null : $this->maxUserPost(
            UserPost::whereIn('user_id', $memberAuthorIds)->whereNull('hidden_at')
        );

        $allLatest = collect([$clubPostLatest, $memberPostLatest, $followingLatest])
            ->filter()->sortDesc()->first();

        $challengeLatest = optional(Duel::where('opponent_id', $user->id)->where('status', 'pending')->max('created_at'),
            fn ($t) => Carbon::parse($t));

        $eventsLatest = $clubIds->isEmpty() ? null : optional(ClubEvent::whereIn('tenant_id', $clubIds)
            ->where('is_archived', false)->whereDate('date', '>=', now()->toDateString())->max('created_at'),
            fn ($t) => Carbon::parse($t));

        $marketLatest = $clubIds->isEmpty() ? null : optional(ClubProduct::whereIn('tenant_id', $clubIds)->max('created_at'),
            fn ($t) => Carbon::parse($t));

        return [
            'feed:all' => $allLatest,
            'feed:club' => $clubPostLatest,
            'feed:following' => $followingLatest,
            'feed:mine' => $mineLatest,
            'challenge' => $challengeLatest,
            'events' => $eventsLatest,
            'market' => $marketLatest,
        ];
    }

    private function maxUserPost($query): ?Carbon
    {
        $max = $query->max('created_at');

        return $max ? Carbon::parse($max) : null;
    }
}
