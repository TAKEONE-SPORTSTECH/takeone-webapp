<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    /**
     * Seed a starter set of solo/club challenges for every tenant that has none.
     * Idempotent: skips a (tenant, title) pair that already exists.
     */
    public function run(): void
    {
        $blueprints = [
            [
                'title' => '10K Steps a Day', 'tag' => 'Cardio', 'category' => 'cardio',
                'icon' => 'bi-activity', 'color' => '#7c3aed', 'metric' => 'steps',
                'goal' => 70000, 'unit' => '', 'points' => 250, 'days' => 7,
                'about' => 'Hit 10,000 steps every day for a full week. Sync from any tracker or log manually. Keep your streak alive to earn bonus points and a badge.',
                'rules' => [
                    'Log at least 10,000 steps each day',
                    'A missed day breaks your streak (not your entry)',
                    'Manual or auto-synced steps both count',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Strider badge', 'sub' => 'On completion'],
                    ['icon' => 'bi-star-fill',  'label' => '250 points',    'sub' => 'Added to profile'],
                    ['icon' => 'bi-fire',       'label' => 'Streak bonus',  'sub' => '+10 / day'],
                ],
            ],
            [
                'title' => 'Weekend Warrior', 'tag' => 'Attendance', 'category' => 'attendance',
                'icon' => 'bi-calendar2-check', 'color' => '#0ea5e9', 'metric' => 'sessions',
                'goal' => 3, 'unit' => '', 'points' => 150, 'days' => 14,
                'about' => 'Attend 3 weekend training sessions this month. Check in at the club to log attendance automatically.',
                'rules' => [
                    'Attend any 3 Saturday or Sunday sessions',
                    'Check-in at reception counts your session',
                    'Sessions must be in the same calendar month',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Warrior badge', 'sub' => 'On completion'],
                    ['icon' => 'bi-star-fill',  'label' => '150 points',    'sub' => 'Added to profile'],
                ],
            ],
            [
                'title' => 'Plank Challenge', 'tag' => 'Strength', 'category' => 'strength',
                'icon' => 'bi-stopwatch', 'color' => '#f59e0b', 'metric' => 'seconds',
                'goal' => 300, 'unit' => 's', 'points' => 200, 'days' => 21,
                'about' => 'Build up to a 5-minute plank hold over three weeks. Log your best hold each day and watch your endurance climb.',
                'rules' => [
                    'Log your longest plank hold daily',
                    'Best single hold of the challenge counts',
                    'Form check videos optional but encouraged',
                ],
                'rewards' => [
                    ['icon' => 'bi-award-fill', 'label' => 'Iron Core badge', 'sub' => 'Reach 5:00'],
                    ['icon' => 'bi-star-fill',  'label' => '200 points',      'sub' => 'Added to profile'],
                ],
            ],
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($blueprints) {
            foreach ($blueprints as $b) {
                $exists = Challenge::where('tenant_id', $tenant->id)->where('title', $b['title'])->exists();
                if ($exists) {
                    continue;
                }
                Challenge::create([
                    'tenant_id' => $tenant->id,
                    'title'     => $b['title'],
                    'tag'       => $b['tag'],
                    'category'  => $b['category'],
                    'icon'      => $b['icon'],
                    'color'     => $b['color'],
                    'metric'    => $b['metric'],
                    'goal'      => $b['goal'],
                    'unit'      => $b['unit'],
                    'points'    => $b['points'],
                    'starts_at' => now()->subDays(2),
                    'ends_at'   => now()->addDays($b['days']),
                    'about'     => $b['about'],
                    'rules'     => $b['rules'],
                    'rewards'   => $b['rewards'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
