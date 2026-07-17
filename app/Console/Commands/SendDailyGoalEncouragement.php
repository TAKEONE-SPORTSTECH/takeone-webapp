<?php

namespace App\Console\Commands;

use App\Models\Goal;
use App\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * Nudges every member with at least one active goal, once a day, so the goal
 * doesn't go cold between check-ins.
 */
class SendDailyGoalEncouragement extends Command
{
    protected $signature = 'goals:daily-encouragement';

    protected $description = 'Send a daily cheering notification to every member with an active goal.';

    private const MESSAGES = [
        'Keep pushing — every rep gets you closer to your goal! 💪',
        'One more day, one step closer. You’ve got this!',
        'Don’t stop now — your goal is within reach.',
        'Consistency beats intensity. Show up for yourself today.',
        'Your future self will thank you for today’s effort.',
        'Small steps every day add up to big results. Keep going!',
        'You started this goal for a reason — stay with it!',
        'Progress, not perfection. Keep moving forward.',
    ];

    public function handle(): int
    {
        $goals = Goal::where('status', 'active')
            ->orderBy('target_date')
            ->get(['id', 'user_id', 'title', 'target_date', 'current_progress_value', 'target_value']);

        $goalsByUser = $goals->groupBy('user_id');
        $uuidsByUserId = \App\Models\User::whereIn('id', $goalsByUser->keys())->pluck('uuid', 'id');
        $count = 0;

        foreach ($goalsByUser as $userId => $userGoals) {
            $uuid = $uuidsByUserId->get($userId);
            if (! $uuid) {
                continue;
            }

            $nearest = $userGoals->first();
            $message = self::MESSAGES[array_rand(self::MESSAGES)];

            try {
                UserNotification::notifyUser((int) $userId, 'goal:encouragement', $message, [
                    'subject_type' => Goal::class,
                    'subject_id' => $nearest->id,
                    'icon' => 'bi-bullseye',
                    'body' => __(':title — :current/:target :progress', [
                        'title' => $nearest->title,
                        'current' => $nearest->current_progress_value,
                        'target' => $nearest->target_value,
                        'progress' => round($nearest->progress_percentage, 0).'%',
                    ]),
                    'action_url' => route('member.show', $uuid),
                ]);
                $count++;
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $this->info("Sent {$count} goal-encouragement notification(s).");

        return self::SUCCESS;
    }
}
