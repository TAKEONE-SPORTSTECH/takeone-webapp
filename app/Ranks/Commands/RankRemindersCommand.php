<?php

namespace App\Ranks\Commands;

use App\Ranks\Support\RankNotices;
use Illuminate\Console\Command;

/** The daily ranks reminders — each notice sent once. Does nothing while ranks are off. */
class RankRemindersCommand extends Command
{
    protected $signature = 'ranks:reminders';

    protected $description = 'Send ranks & grading reminders (ready for grading, classes to go, grading tomorrow, results not recorded)';

    public function handle(RankNotices $notices): int
    {
        if (! config('ranks.enabled')) {
            $this->info('Ranks are switched off — nothing sent.');

            return self::SUCCESS;
        }
        $this->info($notices->daily().' notices sent.');

        return self::SUCCESS;
    }
}
