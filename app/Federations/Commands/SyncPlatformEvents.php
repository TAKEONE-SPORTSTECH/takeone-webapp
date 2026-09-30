<?php

namespace App\Federations\Commands;

use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEntries;
use App\Federations\Services\FederationPlatformEvent;
use Illuminate\Console\Command;

/**
 * Link every federation event to its platform event — for the day
 * `federations.platform_events` is switched on, when events saved before it
 * have none yet. Safe to run again: each sync is idempotent.
 */
class SyncPlatformEvents extends Command
{
    protected $signature = 'federation:sync-platform-events {--dry-run : list what would be linked, write nothing}';

    protected $description = 'Create / refresh the platform event behind every federation event, and bring its entries across';

    public function handle(FederationPlatformEvent $events, FederationPlatformEntries $entries): int
    {
        if (! $events->enabled()) {
            $this->error('federations.platform_events is off (FEDERATION_PLATFORM_EVENTS). Nothing to do.');

            return self::FAILURE;
        }

        foreach (FederationEvent::with('federation')->orderBy('id')->get() as $fe) {
            if ($this->option('dry-run')) {
                $this->line(($fe->club_event_id ? 'refresh ' : 'link    ').$fe->uuid.'  '.$fe->name);

                continue;
            }
            $ev = $events->sync($fe);
            if ($ev) {
                $entries->sync($fe->fresh());
                $this->info('linked  '.$fe->uuid.' → '.$ev->uuid.'  '.$fe->name);
            } else {
                $this->warn('skipped '.$fe->uuid.'  '.$fe->name.' (no start date yet)');
            }
        }

        return self::SUCCESS;
    }
}
