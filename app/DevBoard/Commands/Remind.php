<?php

namespace App\DevBoard\Commands;

use App\DevBoard\Services\CardTools;
use Illuminate\Console\Command;

/**
 * Sends the reminders cards ask for ("1 hour before it is due" and the like)
 * to the people on the card and everyone watching it — once per due moment.
 * Scheduled every minute.
 */
class Remind extends Command
{
    protected $signature = 'devboard:remind';

    protected $description = 'Send TakeNote card reminders that have come due';

    public function handle(CardTools $tools): int
    {
        if (! config('devboard.takenote')) {
            return self::SUCCESS;
        }
        $sent = $tools->remindDue();
        if ($sent) {
            $this->info("Sent {$sent} reminder(s).");
        }

        return self::SUCCESS;
    }
}
