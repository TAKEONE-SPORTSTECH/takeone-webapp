<?php

namespace App\Federations\Commands;

use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationEvents;
use App\Members\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes a QA test event — and ONLY one: the event's name must start with
 * "[QA TEST]" and it must be in test mode, or nothing happens.
 *
 * It does exactly what a manager would do by hand, through the same code:
 * switch test mode off (FederationEvents::setTestMode — a verified backup
 * first, then every row the test created is removed and every row it changed
 * is put back), then delete the event (FederationEvents::delete — the event is
 * soft-deleted and its platform event archived, entries and bouts kept).
 *
 * Dry run by default: prints what the test ledger holds. --apply to act.
 */
class PurgeQaTestEvent extends Command
{
    protected $signature = 'federation:purge-qa-event
        {uuid : The federation event uuid}
        {--as= : Email of a manager of the event\'s federation}
        {--apply : Actually remove it (otherwise a dry run)}';

    protected $description = 'Remove a "[QA TEST]" federation event and everything its test mode recorded';

    public function handle(FederationDirectory $directory, FederationEvents $events): int
    {
        $event = FederationEvent::where('uuid', (string) $this->argument('uuid'))->first();
        if (! $event) {
            $this->error('No federation event with that uuid.');

            return self::FAILURE;
        }
        if (! str_starts_with((string) $event->name, '[QA TEST]')) {
            $this->error("Refusing: \"{$event->name}\" is not a [QA TEST] event.");

            return self::FAILURE;
        }
        if (! $event->test_mode_at) {
            $this->error('Refusing: the event is not in test mode, so its test data cannot be told apart.');

            return self::FAILURE;
        }

        $user = User::where('email', (string) $this->option('as'))->first();
        $access = $user ? $directory->access($user) : null;
        if (! $access || ! ($access['manager'] ?? false)) {
            $this->error('--as must be the email of a manager of this event\'s federation.');

            return self::FAILURE;
        }

        $ledger = DB::table('federation_test_records')->where('federation_event_id', $event->id)
            ->selectRaw('kind, table_name, count(*) as n')->groupBy('kind', 'table_name')->orderBy('table_name')->get();
        $this->info("Event: {$event->name} ({$event->uuid}), test mode since {$event->test_mode_at}");
        $this->table(['kind', 'table', 'rows'], $ledger->map(fn ($r) => [$r->kind, $r->table_name, $r->n])->all());
        $this->line('Not in the ledger (left by routes the recorder does not wrap): weigh-ins, division edits, outcomes, schedule. They hang off the event and are archived with it.');

        if (! $this->option('apply')) {
            $this->comment('Dry run. Re-run with --apply to remove it.');

            return self::SUCCESS;
        }

        $off = $events->setTestMode($user, $access, $event->uuid, false);
        $this->line($off['message']);
        if (! $off['ok']) {
            return self::FAILURE;
        }
        if (! $events->delete($user, $access, $event->uuid)) {
            $this->error('Test data removed, but the event could not be deleted.');

            return self::FAILURE;
        }
        $this->info('Event deleted and its platform event archived.');

        return self::SUCCESS;
    }
}
