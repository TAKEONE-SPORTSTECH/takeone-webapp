<?php

namespace App\Console\Commands;

use App\Models\Duel;
use App\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * Auto-cancels challenges that the opponent never accepted within 3 days.
 */
class ExpirePendingDuels extends Command
{
    protected $signature = 'duels:expire-pending';

    protected $description = 'Cancel pending challenges not accepted within 3 days of being sent.';

    public function handle(): int
    {
        $cutoff = now()->subDays(3);

        $duels = Duel::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($duels as $duel) {
            $duel->update(['status' => 'cancelled', 'cancel_reason' => 'Not accepted within 3 days']);

            try {
                UserNotification::notifyUser($duel->challenger_id, 'duel:cancelled', 'Your challenge expired', [
                    'subject_type' => Duel::class,
                    'subject_id' => $duel->id,
                    'action_url' => route('me.challenge.duel', $duel->id),
                    'icon' => 'bi-lightning-charge-fill',
                    'body' => $duel->discipline.' — not accepted within 3 days',
                ]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $this->info("Expired {$duels->count()} pending challenge(s).");

        return self::SUCCESS;
    }
}
