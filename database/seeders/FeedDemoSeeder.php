<?php

namespace Database\Seeders;

use App\Models\ClubTimelinePost;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPost;
use App\Models\UserPostComment;
use App\Models\UserPostLike;
use App\Models\UserStory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Turns the old hard-coded "/me" demo feed into real, DB-backed rows so the
 * page looks identical but every post/poll/story now persists.
 *
 * Everything it creates is clearly tagged and reversible:
 *   • demo users   → email ends with "@demo.takeone.bh"
 *   • demo clubs   → slug starts with "demo-"
 * Wire-up (memberships, follows, own posts & stories) targets ONE account,
 * the viewer — Super Admin (id 1) by default, override with DEMO_FEED_EMAIL.
 *
 * Idempotent: safe to run repeatedly. Re-running tops up like counts only.
 *
 *   php artisan db:seed --class=FeedDemoSeeder
 *
 * To undo, see FeedDemoSeeder::purge() (or delete rows matching the tags).
 */
class FeedDemoSeeder extends Seeder
{
    private const DEMO_DOMAIN = '@demo.takeone.bh';
    private const CLUB_PREFIX = 'demo-';

    public function run(): void
    {
        $email  = env('DEMO_FEED_EMAIL');
        $viewer = ($email ? User::where('email', $email)->first() : null)
            ?? User::find(1)
            ?? User::orderBy('id')->first();

        if (! $viewer) {
            $this->command?->warn('No users exist — nothing to wire the demo feed to.');
            return;
        }

        $now = Carbon::now();

        // ---- People (coaches + commenters) -------------------------------
        $coachAdam = $this->demoUser('coach-adam',  'Coach Adam');
        $layla     = $this->demoUser('layla-ahmad', 'Layla Ahmad');
        $yousef    = $this->demoUser('yousef-hadi', 'Yousef Hadi');
        $sara      = $this->demoUser('sara-m',      'Sara Mansour');
        $omar      = $this->demoUser('omar-k',      'Omar Khalid');
        $noor      = $this->demoUser('noor-s',      'Noor Saleh');

        // ---- Clubs (tenants) ---------------------------------------------
        $eta     = $this->demoClub($viewer, 'eta-athletics', 'Eta Athletics Club');
        $coachTn = $this->demoClub($viewer, 'coach-adam',    'Coach Adam');
        $boxing  = $this->demoClub($viewer, 'boxing-team',   'Boxing Team');
        $fuel    = $this->demoClub($viewer, 'fuellab',       'FuelLab');

        // ---- Memberships: viewer is in every demo club; people share Eta --
        foreach ([$eta, $coachTn, $boxing, $fuel] as $club) {
            $this->enroll($viewer, $club);
        }
        foreach ([$coachAdam, $layla, $yousef, $sara, $omar, $noor] as $person) {
            $this->enroll($person, $eta);
        }

        // ---- Viewer follows the coaches (their posts hit "Following") -----
        foreach ([$coachAdam, $layla, $yousef] as $person) {
            $this->follow($viewer, $person);
        }

        // ---- Club timeline posts -----------------------------------------
        $this->clubPost($eta, 'Match Day', "🏆 What a finish! Our sprint squad swept the podium at today's Summer Cup — 3 golds and a new club record in the 100m. Proud of every single athlete. #EtaPride", $now->copy()->subHours(1), 142, [
            [$sara, 'So proud of the team! 🥇'],
            [$omar, 'That 100m record was insane 🔥'],
        ], ['color' => '#7c3aed', 'icon' => 'bi-trophy-fill', 'label' => 'Summer Cup · 3 Golds']);
        $this->clubPost($coachTn, 'Tip of the day', "Recovery is where the gains happen. Aim for 7–9 hours of sleep, hydrate well, and don't skip your mobility work. Your body will thank you tomorrow. 💪", $now->copy()->subHours(4), 64, [
            [$layla, 'Saving this 🙌'],
        ]);
        $this->clubPost($eta, 'Event', "📣 New this week: Sunrise Yoga every Sunday at 8 AM on the rooftop. Limited to 15 spots — book through the app. Namaste 🧘", $now->copy()->subHours(7), 38, [], ['color' => '#10b981', 'icon' => 'bi-flower1', 'label' => 'Sunrise Yoga · Sundays']);
        $this->clubPost($boxing, 'Milestone', "Big shoutout to Omar K. for landing his 10th win this season! 🥊 The grind never stops. Who's next in the ring?", $now->copy()->subHours(11), 97, [
            [$yousef, 'Legend 👏'],
        ]);
        $this->clubPost($fuel, 'Community', "Fuel feature 🥤 — your favourite post-workout shake just got a new strawberry flavour. Drop a 💗 if you want us to stock it at the club café!", $now->copy()->subHours(20), 51, [], ['color' => '#ec4899', 'icon' => 'bi-cup-straw', 'label' => 'New Flavour Drop']);
        $this->clubPost($eta, 'Throwback', "#ThrowbackThursday to last month's regional friendly. The energy in the stands was unreal — thank you to everyone who came out to support. 🙌", $now->copy()->subHours(28), 73, []);

        // ---- "Following" member posts ------------------------------------
        $this->memberPost($coachAdam, "Form check 📹 — keep your core braced and drive through the heels on every squat. Small fix, big difference. Who's training legs today? 🦵", $now->copy()->subHours(2), 41, [
            [$sara, 'Needed this reminder, thanks Coach!'],
            [$omar, 'Legs today 🔥'],
        ], ['color' => '#0ea5e9', 'icon' => 'bi-camera-video-fill', 'label' => 'Form Check 📹']);
        $this->memberPost($layla, "New 5K PB this morning — 22:48! ☀️🏃‍♀️ Six months ago I couldn't run 1K without stopping. Consistency really does pay off.", $now->copy()->subHours(6), 88, [
            [$noor, 'Amazing progress 👏'],
        ], ['color' => '#f59e0b', 'icon' => 'bi-lightning-charge-fill', 'label' => '5K PB · 22:48']);
        $this->memberPost($yousef, "Anyone up for a doubles padel match this weekend? Looking for 2 more players 🎾", $now->copy()->subHours(14), 17, []);

        // ---- A poll (new post type) — Coach Adam, so the viewer can vote --
        $this->pollPost($coachAdam, 'Which extra class should we add next term?', ['Olympic lifting', 'Mobility & stretch', 'Boxing fundamentals', 'Sunrise yoga'], $now->copy()->subHours(5), [$sara, $omar, $noor, $layla, $viewer]);

        // ---- The viewer's OWN posts --------------------------------------
        $this->memberPost($viewer, "Hit a new bench press PR today — 80kg for 3 reps 💪 Slow and steady. Next stop: 85.", $now->copy()->subHours(3), 32, [
            [$coachAdam, "That's the way — proud of you!"],
        ], ['color' => '#7c3aed', 'icon' => 'bi-trophy-fill', 'label' => 'Bench PR · 80kg']);
        $this->memberPost($viewer, "Rest day done right: mobility, a long walk and an early night. 😴 Recovery is part of the program.", $now->copy()->subHours(26), 14, []);

        // ---- Stories (24h) -----------------------------------------------
        $this->story($coachAdam, 'Early morning grind 💪', '#0ea5e9', 'bi-person');
        $this->story($sara,      'New personal best today! 🏃‍♀️', '#ec4899', 'bi-person');
        $this->story($omar,      'Sparring session 🥊', '#10b981', 'bi-person');
        $this->story($layla,     'Match day at the stadium! 🏟️', '#7c3aed', 'bi-trophy');
        $this->story($yousef,    'Champions! 🏆', '#ef4444', 'bi-trophy');

        $this->command?->info("Demo feed seeded for: {$viewer->full_name} <{$viewer->email}>");
        $this->command?->info('Demo users: *' . self::DEMO_DOMAIN . '  |  demo clubs: slug "' . self::CLUB_PREFIX . '*"');
    }

    // ===================== builders =====================

    private function demoUser(string $handle, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $handle . self::DEMO_DOMAIN],
            [
                'full_name'         => $name,
                'name'              => $name,
                'password'          => Hash::make(\Illuminate\Support\Str::random(24)),
                'email_verified_at' => now(),
            ],
        );
    }

    private function demoClub(User $owner, string $slug, string $name): Tenant
    {
        // Reuse a soft-deleted demo club if one exists (its slug still holds the
        // unique index), otherwise create it.
        $club = Tenant::withTrashed()->where('slug', self::CLUB_PREFIX . $slug)->first();
        if ($club) {
            if ($club->trashed()) {
                $club->restore();
            }
            return $club;
        }

        return Tenant::create([
            'slug'          => self::CLUB_PREFIX . $slug,
            'owner_user_id' => $owner->id,
            'club_name'     => $name,
            'country'       => 'BH',
            'status'        => 'approved',
        ]);
    }

    private function enroll(User $user, Tenant $club): void
    {
        DB::table('memberships')->updateOrInsert(
            ['tenant_id' => $club->id, 'user_id' => $user->id],
            ['status' => 'active', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function follow(User $follower, User $followee): void
    {
        if ($follower->id === $followee->id) {
            return;
        }
        DB::table('user_follows')->updateOrInsert(
            ['follower_id' => $follower->id, 'followee_id' => $followee->id],
            ['updated_at' => now(), 'created_at' => now()],
        );
    }

    /** Club timeline post + comments + top-up likes (idempotent by tenant+body). */
    private function clubPost(Tenant $club, string $category, string $body, Carbon $at, int $likes, array $comments, ?array $cover = null): void
    {
        $post = ClubTimelinePost::firstOrCreate(
            ['tenant_id' => $club->id, 'body' => $body],
            ['category' => $category, 'status' => 'published', 'posted_at' => $at, 'cover' => $cover],
        );
        // Backfill the cover on a club post seeded before covers existed.
        if (! $post->wasRecentlyCreated && $cover && ! $post->cover) {
            $post->update(['cover' => $cover]);
        }

        foreach ($comments as [$author, $text]) {
            ClubTimelinePostComment::firstOrCreate(
                ['post_id' => $post->id, 'user_id' => $author->id, 'body' => $text],
            );
        }

        $this->topUpLikes($likes, $post->likes()->pluck('user_id')->all(),
            fn ($uid) => ClubTimelinePostLike::create(['post_id' => $post->id, 'user_id' => $uid]));
    }

    /** Member post + comments + top-up likes (idempotent by user+body). */
    private function memberPost(User $author, string $body, Carbon $at, int $likes, array $comments, ?array $cover = null): void
    {
        $post = UserPost::firstOrCreate(
            ['user_id' => $author->id, 'body' => $body],
            ['type' => $cover ? 'highlight' : 'text', 'cover' => $cover],
        );
        $this->backdate($post, $at);
        // Backfill a cover onto a post seeded before highlights existed.
        if (! $post->wasRecentlyCreated && $cover && ! $post->cover) {
            $post->update(['type' => 'highlight', 'cover' => $cover]);
        }

        foreach ($comments as [$cAuthor, $text]) {
            UserPostComment::firstOrCreate(
                ['user_post_id' => $post->id, 'user_id' => $cAuthor->id, 'body' => $text],
            );
        }

        $this->topUpLikes($likes, $post->likes()->pluck('user_id')->all(),
            fn ($uid) => UserPostLike::create(['user_post_id' => $post->id, 'user_id' => $uid]));
    }

    /** Poll post + seeded votes (idempotent by user+question). */
    private function pollPost(User $author, string $question, array $options, Carbon $at, array $voters): void
    {
        $post = UserPost::firstOrCreate(
            ['user_id' => $author->id, 'body' => $question],
            ['type' => 'poll', 'poll' => ['question' => $question, 'options' => $options]],
        );
        $this->backdate($post, $at);

        foreach ($voters as $i => $voter) {
            DB::table('user_post_poll_votes')->updateOrInsert(
                ['user_post_id' => $post->id, 'user_id' => $voter->id],
                ['option' => $i % count($options), 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /** Set a freshly-created post's created_at so its "x hours ago" looks right. */
    private function backdate(UserPost $post, Carbon $at): void
    {
        if ($post->wasRecentlyCreated) {
            $post->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
    }

    private function story(User $author, string $caption, string $color, string $icon): void
    {
        // Refresh the 24h window each run so demo stories stay visible.
        UserStory::updateOrCreate(
            ['user_id' => $author->id, 'caption' => $caption],
            ['type' => 'text', 'color' => $color, 'icon' => $icon, 'expires_at' => now()->addDay()],
        );
    }

    /** Add likes from random real users until the post reaches $target likes. */
    private function topUpLikes(int $target, array $existing, callable $make): void
    {
        $have = count($existing);
        if ($have >= $target) {
            return;
        }
        $need = $target - $have;
        $candidates = User::whereNotIn('id', $existing ?: [0])
            ->inRandomOrder()->limit($need)->pluck('id');
        foreach ($candidates as $uid) {
            $make($uid);
        }
    }

    /** Remove everything this seeder creates. Call manually if needed. */
    public static function purge(): void
    {
        $userIds = User::withTrashed()->where('email', 'like', '%' . self::DEMO_DOMAIN)->pluck('id');
        $clubIds = Tenant::withTrashed()->where('slug', 'like', self::CLUB_PREFIX . '%')->pluck('id');

        ClubTimelinePost::whereIn('tenant_id', $clubIds)->get()->each->delete();
        UserPost::whereIn('user_id', $userIds)->get()->each->delete();
        DB::table('memberships')->whereIn('tenant_id', $clubIds)->delete();
        DB::table('user_follows')->whereIn('followee_id', $userIds)->orWhereIn('follower_id', $userIds)->delete();
        UserStory::whereIn('user_id', $userIds)->delete();
        Tenant::withTrashed()->whereIn('id', $clubIds)->forceDelete();
        User::withTrashed()->whereIn('id', $userIds)->forceDelete();
    }
}
