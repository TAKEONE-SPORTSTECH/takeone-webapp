<?php

namespace App\Console\Commands;

use App\Events\Support\EntryDeadline;
use App\Models\ClubEvent;
use Illuminate\Console\Command;

/**
 * Take the entries that never got ready off a competition, at the deadline the
 * organiser set.
 *
 * Scheduled hourly. It only ever touches an event whose organiser switched
 * enforcement on (`club_events.entry_deadline_enforced`), whose `weigh_in_at`
 * has passed, and which has not started — so running it often is safe, and
 * running it on the morning of an event while the desk is still weighing people
 * in is safe too: anybody who gets paid up or on the scale between two runs
 * simply stays.
 */
class EnforceEntryDeadline extends Command
{
    protected $signature = 'events:enforce-entry-deadline
                            {--event= : one event, by uuid — ignores the clock but not the opt-in}
                            {--dry : list who would come off, and remove nobody}';

    protected $description = 'Remove unpaid / un-weighed entries once an event\'s weigh-in deadline has passed';

    public function handle(EntryDeadline $deadline): int
    {
        $events = $this->option('event')
            ? ClubEvent::where('uuid', $this->option('event'))->where('entry_deadline_enforced', true)->get()
            : $deadline->due();

        if ($events->isEmpty()) {
            $this->line('Nothing due.');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $this->line("<info>{$event->title}</info>  (weigh-in {$event->weigh_in_at})");

            if ($this->option('dry')) {
                $this->dryRun($event);

                continue;
            }

            $result = $deadline->enforce($event);

            foreach ($result['removed'] as $row) {
                $this->line("  – {$row['name']}  ({$row['reason']})");
            }

            foreach ($result['refused'] as $message) {
                $this->warn("  ! {$message}");
            }

            $this->line(sprintf('  %d removed, %d kept.', count($result['removed']), $result['kept']));
        }

        return self::SUCCESS;
    }

    /** What the sweep would do, without doing it. */
    private function dryRun(ClubEvent $event): void
    {
        $charges = \App\Events\Support\EventFee::chargesAnything($event, 'participant');

        foreach ($event->participantRegistrations()->with('user')->get() as $registration) {
            $unpaid = $charges && ! ($registration->paid && $registration->paid_by !== null);
            $unweighed = ! ($registration->weighed_in_at && $registration->weighed_in_by && $registration->weight !== null);

            if ($unpaid || $unweighed) {
                $this->line(sprintf(
                    '  – %s  (%s)',
                    $registration->user?->full_name ?: '—',
                    \App\Models\EventEntryExclusion::reasonFor($unpaid, $unweighed),
                ));
            }
        }
    }
}
