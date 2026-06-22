<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\Duel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChallengeShowcaseSeeder extends Seeder
{
    /**
     * Populate the challenge hub with realistic, demo-ready data for ONE hero
     * account: joined solo challenges with a populated leaderboard, plus a full
     * spread of 1v1 duels (incoming invites, live, sent, reported, and a
     * win/loss history). Idempotent: clears the hero's prior demo rows first.
     *
     * Set the hero via `HERO_EMAIL=you@example.com php artisan db:seed --class=ChallengeShowcaseSeeder`.
     */
    public function run(): void
    {
        $email = env('HERO_EMAIL', 'alaaissa538@gmail.com');
        $hero  = User::where('email', $email)->first()
            ?? User::find(DB::table('memberships')->value('user_id'));

        if (! $hero) {
            $this->command?->warn('No hero user / memberships found — nothing to seed.');
            return;
        }

        $club = $hero->memberClubs()->first();
        if (! $club) {
            $this->command?->warn("Hero {$hero->id} has no club — nothing to seed.");
            return;
        }

        $mates = User::whereIn('id', DB::table('memberships')
                ->where('tenant_id', $club->id)
                ->where('user_id', '!=', $hero->id)
                ->distinct()->limit(20)->pluck('user_id'))
            ->get()->values();

        if ($mates->count() < 2) {
            $this->command?->warn('Not enough club-mates to build a showcase.');
            return;
        }

        $this->command?->info("Seeding showcase for {$hero->full_name} (#{$hero->id}) @ {$club->club_name}, {$mates->count()} mates.");

        // ---- reset hero's prior demo rows so re-runs stay clean ----
        Duel::where('challenger_id', $hero->id)->orWhere('opponent_id', $hero->id)->delete();
        ChallengeParticipation::where('user_id', $hero->id)->delete();

        $this->seedChallenges($club->id, $hero, $mates);
        $this->seedDuels($hero, $mates);

        $this->command?->info('Done. Duels: ' . Duel::where('challenger_id', $hero->id)->orWhere('opponent_id', $hero->id)->count()
            . ' · Participations(hero): ' . ChallengeParticipation::where('user_id', $hero->id)->count());
    }

    /* ----------------------------------------------------------------- */

    private function seedChallenges(int $tenantId, User $hero, $mates): void
    {
        foreach ($this->catalog() as $b) {
            $state = $b['state']; // active | upcoming | completed
            [$start, $end] = match ($state) {
                'upcoming'  => [now()->addDays($b['in'] ?? 5), now()->addDays(($b['in'] ?? 5) + ($b['days'] ?? 30))],
                'completed' => [now()->subDays(($b['days'] ?? 30) + 6), now()->subDays($b['ended'] ?? 6)],
                default     => [now()->subDays(2), now()->addDays($b['days'] ?? 21)],
            };

            $challenge = Challenge::updateOrCreate(
                ['tenant_id' => $tenantId, 'title' => $b['title']],
                [
                    'tag' => $b['tag'], 'category' => $b['category'], 'icon' => $b['icon'], 'color' => $b['color'],
                    'metric' => $b['metric'], 'goal' => $b['goal'], 'unit' => $b['unit'] ?? '', 'points' => $b['points'],
                    'starts_at' => $start, 'ends_at' => $end, 'about' => $b['about'],
                    'rules' => $b['rules'], 'rewards' => $b['rewards'], 'is_active' => true,
                ]
            );

            // Upcoming challenges only have a few early sign-ups; others get a full crowd.
            if ($state === 'upcoming') {
                foreach ($mates->take(6) as $i => $mate) {
                    $this->participate($challenge, $mate->id, max(1, (int) $challenge->goal), 0);
                }
                if (! empty($b['heroJoins'])) {
                    $this->participate($challenge, $hero->id, max(1, (int) $challenge->goal), 0);
                }
                continue;
            }

            $this->enrollCrowd($challenge, $hero, $mates,
                heroPct: $b['heroPct'] ?? 60,
                allFinished: $state === 'completed',
                heroJoins: $b['heroJoins'] ?? true,
            );
        }
    }

    /** Rich, detailed challenge catalog spanning categories + lifecycle states. */
    private function catalog(): array
    {
        return [
            [
                'title' => '10K Steps a Day', 'state' => 'active', 'heroPct' => 78, 'days' => 7,
                'tag' => 'Cardio', 'category' => 'cardio', 'icon' => 'bi-activity', 'color' => '#7c3aed',
                'metric' => 'steps', 'goal' => 70000, 'points' => 250,
                'about' => 'Hit 10,000 steps every day for a full week. Sync from any tracker or log manually. Keep your streak alive to earn bonus points and a badge.',
                'rules' => ['Log at least 10,000 steps each day', 'A missed day breaks your streak (not your entry)', 'Manual or auto-synced steps both count'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Strider badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '250 points', 'sub' => 'Added to profile'], ['icon' => 'bi-fire', 'label' => 'Streak bonus', 'sub' => '+10 / day']],
            ],
            [
                'title' => 'Weekend Warrior', 'state' => 'active', 'heroPct' => 52, 'days' => 14,
                'tag' => 'Attendance', 'category' => 'attendance', 'icon' => 'bi-calendar2-check', 'color' => '#0ea5e9',
                'metric' => 'sessions', 'goal' => 3, 'points' => 150,
                'about' => 'Attend 3 weekend training sessions this month. Check in at the club to log attendance automatically.',
                'rules' => ['Attend any 3 Saturday or Sunday sessions', 'Check-in at reception counts your session', 'Sessions must be in the same calendar month'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Warrior badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '150 points', 'sub' => 'Added to profile']],
            ],
            [
                'title' => 'Plank Challenge', 'state' => 'active', 'heroPct' => 64, 'days' => 21,
                'tag' => 'Strength', 'category' => 'strength', 'icon' => 'bi-stopwatch', 'color' => '#f59e0b',
                'metric' => 'seconds', 'goal' => 300, 'unit' => 's', 'points' => 200,
                'about' => 'Build up to a 5-minute plank hold over three weeks. Log your best hold each day and watch your endurance climb.',
                'rules' => ['Log your longest plank hold daily', 'Best single hold of the challenge counts', 'Form check videos optional but encouraged'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Iron Core badge', 'sub' => 'Reach 5:00'], ['icon' => 'bi-star-fill', 'label' => '200 points', 'sub' => 'Added to profile']],
            ],
            [
                'title' => 'Burpee Blitz — 1,000', 'state' => 'active', 'heroPct' => 41, 'days' => 30,
                'tag' => 'Conditioning', 'category' => 'fitness', 'icon' => 'bi-lightning-charge-fill', 'color' => '#ef4444',
                'metric' => 'burpees', 'goal' => 1000, 'points' => 350,
                'about' => 'Bang out 1,000 burpees this month — split them however you like. Great for explosive conditioning and a serious calorie burn.',
                'rules' => ['Log burpees after each session', 'Chest-to-floor, full stand at the top', 'No daily minimum — pace yourself'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Blitz badge', 'sub' => 'Reach 1,000'], ['icon' => 'bi-star-fill', 'label' => '350 points', 'sub' => 'Added to profile'], ['icon' => 'bi-fire', 'label' => 'Milestone pings', 'sub' => 'Every 250']],
            ],
            [
                'title' => 'Hydration Hero', 'state' => 'active', 'heroPct' => 88, 'days' => 14,
                'tag' => 'Wellness', 'category' => 'nutrition', 'icon' => 'bi-droplet-half', 'color' => '#06b6d4',
                'metric' => 'days', 'goal' => 14, 'points' => 120,
                'about' => 'Drink your daily water target (2.5L) for two weeks straight. Small habit, big recovery and performance gains.',
                'rules' => ['Hit 2.5L every day', 'Tap the log button once you’re done', 'Caffeinated drinks don’t count'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Hydration badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '120 points', 'sub' => 'Added to profile']],
            ],
            [
                'title' => 'Pull-Up Progression', 'state' => 'active', 'heroPct' => 33, 'days' => 28,
                'tag' => 'Strength', 'category' => 'strength', 'icon' => 'bi-arrow-up-circle', 'color' => '#8b5cf6',
                'metric' => 'pull-ups', 'goal' => 100, 'points' => 220,
                'about' => 'Total 100 strict pull-ups across the month and build serious upper-body pulling strength. Bands allowed while you build up.',
                'rules' => ['Strict form — chin over the bar', 'Banded reps count at half', 'Log after each session'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Grip badge', 'sub' => 'Reach 100'], ['icon' => 'bi-star-fill', 'label' => '220 points', 'sub' => 'Added to profile']],
            ],
            [
                'title' => 'Early Bird Club', 'state' => 'active', 'heroPct' => 60, 'days' => 21,
                'tag' => 'Attendance', 'category' => 'attendance', 'icon' => 'bi-sunrise', 'color' => '#10b981',
                'metric' => 'sessions', 'goal' => 10, 'points' => 180,
                'about' => 'Make 10 morning sessions (before 8 AM) in three weeks. Own your mornings and set the tone for the day.',
                'rules' => ['Check in before 8:00 AM', 'Any class counts', '10 sessions in the window'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Sunrise badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '180 points', 'sub' => 'Added to profile']],
            ],
            // --- upcoming ---
            [
                'title' => 'Pre-Season Bootcamp', 'state' => 'upcoming', 'in' => 5, 'days' => 30, 'heroJoins' => true,
                'tag' => 'Strength', 'category' => 'strength', 'icon' => 'bi-lightning-charge-fill', 'color' => '#0ea5e9',
                'metric' => 'sessions', 'goal' => 12, 'points' => 300,
                'about' => 'A 4-week pre-season bootcamp to peak before the competition window. Twelve coached sessions, progressive overload, and a fitness re-test at the end.',
                'rules' => ['Attend 12 bootcamp sessions', 'Sessions are Mon/Wed/Fri', 'Re-test on the final day'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Bootcamp badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '300 points', 'sub' => 'Added to profile']],
            ],
            [
                'title' => 'Summer Shred — 30 Day', 'state' => 'upcoming', 'in' => 9, 'days' => 30,
                'tag' => 'Transformation', 'category' => 'fitness', 'icon' => 'bi-fire', 'color' => '#f97316',
                'metric' => 'workouts', 'goal' => 24, 'points' => 400,
                'about' => '24 workouts in 30 days with a nutrition guide and weekly check-ins. The flagship summer transformation challenge.',
                'rules' => ['Complete 24 logged workouts', 'Weekly check-in photo (private)', 'Follow the nutrition guide'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Shred badge', 'sub' => 'On completion'], ['icon' => 'bi-star-fill', 'label' => '400 points', 'sub' => 'Added to profile'], ['icon' => 'bi-trophy-fill', 'label' => 'Top 3 prizes', 'sub' => 'Club store credit']],
            ],
            // --- completed (feeds history) ---
            [
                'title' => 'Spring 5K Streak', 'state' => 'completed', 'heroPct' => 100, 'days' => 34, 'ended' => 6,
                'tag' => 'Running', 'category' => 'cardio', 'icon' => 'bi-trophy', 'color' => '#10b981',
                'metric' => 'runs', 'goal' => 10, 'points' => 300,
                'about' => 'Ten 5K runs through spring. GPS or treadmill both counted. A spring classic — congratulations to everyone who finished!',
                'rules' => ['Run 10 separate 5K sessions', 'GPS or treadmill both count', 'One 5K per day max'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Spring Runner badge', 'sub' => 'Earned'], ['icon' => 'bi-star-fill', 'label' => '300 points', 'sub' => 'Earned']],
            ],
            [
                'title' => 'Winter Consistency', 'state' => 'completed', 'heroPct' => 100, 'days' => 28, 'ended' => 20,
                'tag' => 'Attendance', 'category' => 'attendance', 'icon' => 'bi-snow', 'color' => '#3b82f6',
                'metric' => 'sessions', 'goal' => 16, 'points' => 250,
                'about' => 'Sixteen sessions through the winter slump. Showing up when it’s cold is what builds champions.',
                'rules' => ['16 logged sessions', 'Any class type counts', 'Within the challenge window'],
                'rewards' => [['icon' => 'bi-award-fill', 'label' => 'Consistency badge', 'sub' => 'Earned'], ['icon' => 'bi-star-fill', 'label' => '250 points', 'sub' => 'Earned']],
            ],
        ];
    }

    /** Enroll hero + a slice of mates with believable, varied progress. */
    private function enrollCrowd(Challenge $challenge, User $hero, $mates, int $heroPct, bool $allFinished = false, bool $heroJoins = true): void
    {
        $goal = max(1, (int) $challenge->goal);

        // A deterministic spread of progress percentages for a full leaderboard.
        $spread = [97, 91, 84, 78, 71, 65, 58, 52, 46, 39, 33, 27, 21, 15, 9];
        $slice  = $mates->take(count($spread));

        foreach ($slice as $i => $mate) {
            $pct = $allFinished ? max(40, 100 - $i * 6) : $spread[$i];
            $this->participate($challenge, $mate->id, $goal, $pct);
        }

        if ($heroJoins) {
            $this->participate($challenge, $hero->id, $goal, $heroPct);
        }
    }

    private function participate(Challenge $challenge, int $userId, int $goal, int $pct): void
    {
        $progress = (int) round($goal * min(100, $pct) / 100);
        ChallengeParticipation::updateOrCreate(
            ['challenge_id' => $challenge->id, 'user_id' => $userId],
            [
                'progress'     => $progress,
                'streak'       => $pct >= 100 ? rand(5, 12) : (int) round($pct / 12),
                'completed_at' => $progress >= $goal ? now()->subDays(rand(0, 8)) : null,
            ]
        );
    }

    /* ----------------------------------------------------------------- */

    private function seedDuels(User $hero, $mates): void
    {
        $m = fn (int $i) => $mates[$i % $mates->count()];

        // Incoming invitations (someone challenged the hero).
        Duel::create([
            'challenger_id' => $m(0)->id, 'opponent_id' => $hero->id,
            'type' => 'fight', 'discipline' => 'Sparring — 3 Rounds', 'metric' => 'Best of 3 rounds',
            'stake_points' => 200, 'deadline' => now()->addDays(3), 'location' => 'Ring A',
            'message' => 'Think you can last 3 rounds with me? Let’s settle it. 🥊',
            'status' => 'pending', 'created_at' => now()->subHours(2),
        ]);
        Duel::create([
            'challenger_id' => $m(1)->id, 'opponent_id' => $hero->id,
            'type' => 'athletic', 'discipline' => '500m Row Sprint', 'metric' => 'Fastest 500m',
            'stake_points' => 120, 'deadline' => now()->addDays(5), 'location' => 'Rowing Studio',
            'message' => 'Row-off this weekend? Loser buys the smoothies. 🚣',
            'status' => 'pending', 'created_at' => now()->subHours(6),
        ]);

        // Active duels (accepted, in progress).
        Duel::create([
            'challenger_id' => $hero->id, 'opponent_id' => $m(2)->id,
            'type' => 'athletic', 'discipline' => '100m Sprint Duel', 'metric' => 'Best of 3 runs',
            'stake_points' => 150, 'deadline' => now()->addDays(2), 'location' => 'Main Track',
            'message' => 'Two runs each logged — one to go!',
            'status' => 'active', 'responded_at' => now()->subDay(), 'created_at' => now()->subDays(3),
        ]);

        // A reported duel awaiting the hero's confirmation (rival reported).
        Duel::create([
            'challenger_id' => $m(3)->id, 'opponent_id' => $hero->id,
            'type' => 'fight', 'discipline' => 'Grappling Match', 'metric' => 'Best of 3 submissions',
            'stake_points' => 180, 'location' => 'Mat Room', 'message' => 'GG — submitted you 2–1!',
            'status' => 'reported', 'winner_id' => $m(3)->id, 'reported_by' => $m(3)->id,
            'challenger_score' => '2', 'opponent_score' => '1',
            'responded_at' => now()->subDays(2), 'created_at' => now()->subDays(4),
        ]);

        // Sent invitation (hero challenged someone; awaiting reply).
        Duel::create([
            'challenger_id' => $hero->id, 'opponent_id' => $m(4)->id,
            'type' => 'athletic', 'discipline' => 'Plank Hold Battle', 'metric' => 'Longest hold',
            'stake_points' => 100, 'deadline' => now()->addDays(4), 'location' => 'Gym Floor 1',
            'message' => 'Plank-off? First to drop loses. 💪',
            'status' => 'pending', 'created_at' => now()->subHours(5),
        ]);

        // Completed history — 3 wins, 1 loss (record 3W · 1L).
        $history = [
            ['rival' => $m(5), 'heroWon' => true,  'type' => 'fight',    'disc' => 'Boxing Bout',     'my' => '2', 'op' => '0', 'pts' => 180, 'days' => 12],
            ['rival' => $m(6), 'heroWon' => true,  'type' => 'athletic', 'disc' => '1km Time Trial',   'my' => '3:42', 'op' => '3:58', 'pts' => 150, 'days' => 19],
            ['rival' => $m(7), 'heroWon' => true,  'type' => 'fight',    'disc' => 'Taekwondo Spar',   'my' => '14', 'op' => '9', 'pts' => 200, 'days' => 26],
            ['rival' => $m(8), 'heroWon' => false, 'type' => 'athletic', 'disc' => '5K Race',          'my' => '24:10', 'op' => '22:48', 'pts' => 120, 'days' => 33],
        ];
        foreach ($history as $h) {
            $heroChallenger = (bool) random_int(0, 1);
            $winnerId = $h['heroWon'] ? $hero->id : $h['rival']->id;
            Duel::create([
                'challenger_id'    => $heroChallenger ? $hero->id : $h['rival']->id,
                'opponent_id'      => $heroChallenger ? $h['rival']->id : $hero->id,
                'type'             => $h['type'], 'discipline' => $h['disc'],
                'metric'           => 'Best of 3', 'stake_points' => $h['pts'],
                'status'           => 'completed', 'winner_id' => $winnerId, 'reported_by' => $h['rival']->id,
                'challenger_score' => $heroChallenger ? $h['my'] : $h['op'],
                'opponent_score'   => $heroChallenger ? $h['op'] : $h['my'],
                'completed_at'     => now()->subDays($h['days']),
                'created_at'       => now()->subDays($h['days'] + 4),
            ]);
        }
    }
}
