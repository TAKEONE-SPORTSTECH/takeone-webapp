<?php

namespace App\Rankings\Commands;

use App\Events\Models\ClubEvent;
use App\Rankings\Support\EventPoints;
use App\Rankings\Support\Ledger;
use Illuminate\Console\Command;

/**
 * Recompute ranking points for one event, or for every federation event.
 * Safe to repeat: each event's rows are rewritten, never added to.
 */
class RebuildRankings extends Command
{
    protected $signature = 'rankings:rebuild {event? : The event id} {--all : Every federation-hosted event}';

    protected $description = 'Recompute national ranking points from event results';

    public function handle(Ledger $ledger): int
    {
        if (! $this->argument('event') && ! $this->option('all')) {
            $this->error('Name an event id, or pass --all.');

            return self::FAILURE;
        }

        $events = $this->option('all')
            ? ClubEvent::where('host_type', 'federation')->orderBy('date')->get()
            : ClubEvent::whereKey((int) $this->argument('event'))->get();

        foreach ($events as $event) {
            $n = $ledger->rebuild($event);
            $this->line(sprintf('#%d %s — %s, %d rows', $event->id, $event->title, EventPoints::grants($event) ? 'grants points' : 'grants none', $n));
        }

        return self::SUCCESS;
    }
}
