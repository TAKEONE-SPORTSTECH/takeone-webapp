<?php

namespace App\Console\Commands;

use App\Events\Support\EventNotifier;
use App\Models\ClubEvent;
use Illuminate\Console\Command;

/**
 * Fires the TIME-triggered event milestones — the ones nobody makes a request
 * for: enrolment opening, the closing reminder, closing itself, weigh-in day,
 * and the event-morning reminder.
 *
 * Safe to run as often as you like: every milestone is claimed in the ledger
 * before it is delivered, so re-running never double-notifies.
 */
class SendDueEventNotifications extends Command
{
    protected $signature = 'events:send-notifications
                            {--event= : only this event uuid}
                            {--dry : resolve and report without sending}';

    protected $description = 'Deliver any event notification milestones that have fallen due';

    public function handle(EventNotifier $notifier): int
    {
        if (! config('event_notifications.enabled', true)) {
            $this->warn('Event notifications are disabled (event_notifications.enabled).');

            return self::SUCCESS;
        }

        $query = ClubEvent::query()
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->with('tenant:id,club_name,country,timezone,gps_lat,gps_long');

        if ($uuid = $this->option('event')) {
            $query->where('uuid', $uuid);
        } else {
            // Only events still in or near their window can have a milestone fall
            // due — long-finished events are skipped cheaply.
            $query->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('end_date')->whereDate('date', '>=', now()->subDay()))
                ->orWhereDate('end_date', '>=', now()->subDay()));
        }

        $events = $query->cursor();

        $total = 0;
        $touched = 0;

        foreach ($events as $event) {
            if ($this->option('dry')) {
                $this->line("• {$event->title} ({$event->uuid})");
                foreach (app(\App\Events\EventTypeRegistry::class)->for($event)->notificationSchedule($event) as $m) {
                    $this->line(sprintf('    %-18s %-10s %s', $m->key, $m->isDue() ? 'DUE' : 'pending', $m->at?->toDateTimeString() ?? 'on creation'));
                }

                continue;
            }

            $fired = $notifier->fireDue($event);
            if ($fired) {
                $touched++;
                $total += array_sum($fired);
                foreach ($fired as $key => $count) {
                    $this->info("{$event->title}: {$key} → {$count} recipient(s)");
                }
            }
        }

        if (! $this->option('dry')) {
            $this->info("Done — {$total} notification(s) queued across {$touched} event(s).");
        }

        return self::SUCCESS;
    }
}
