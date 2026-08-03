<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Jobs\DeliverEventNotification;
use App\Models\ClubEvent;
use App\Models\EventNotificationSent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Fires an event's notification milestones — exactly once each.
 *
 * Owns the three things that keep a broadcast from becoming a spam engine:
 *  - the ledger claim, so a milestone can never be sent twice
 *  - the recipient cap, so one event cannot flood the notifications table or
 *    the MQTT broker (and anything dropped is recorded and logged, never silent)
 *  - queued, chunked delivery, so a nationwide announcement never runs inline
 *    in the web request that created the event
 */
class EventNotifier
{
    public function __construct(
        private EventTypeRegistry $registry,
        private AudienceResolver $audience,
    ) {}

    /**
     * Fire every milestone of this event that is now due and not yet sent.
     *
     * @return array<string, int> milestone key => recipients queued
     */
    public function fireDue(ClubEvent $event): array
    {
        $fired = [];

        foreach ($this->registry->for($event)->notificationSchedule($event) as $milestone) {
            if (! $milestone->isDue()) {
                continue;
            }
            $count = $this->fire($event, $milestone);
            if ($count !== null) {
                $fired[$milestone->key] = $count;
            }
        }

        return $fired;
    }

    /** Fire one milestone by key, if it is due. Used by state triggers (creation). */
    public function fireOnce(ClubEvent $event, string $key): ?int
    {
        foreach ($this->registry->for($event)->notificationSchedule($event) as $milestone) {
            if ($milestone->key === $key) {
                return $this->fire($event, $milestone);
            }
        }

        return null;
    }

    /**
     * Claim the milestone, resolve its audience, and queue delivery.
     *
     * @return int|null recipients queued, or null when already sent / disabled
     */
    public function fire(ClubEvent $event, Milestone $milestone): ?int
    {
        if (! config('event_notifications.enabled', true)) {
            return null;
        }

        // A cancelled or archived event stops talking to people.
        if ($event->status === 'cancelled' || $event->is_archived) {
            return null;
        }

        // Claim FIRST. If another worker already has it the unique index throws
        // and we stop — nobody gets a duplicate.
        if (! $this->claim($event, $milestone)) {
            return null;
        }

        $recipients = $this->resolve($event, $milestone);

        $cap = (int) config('event_notifications.max_recipients', 5000);
        $skipped = max(0, count($recipients) - $cap);
        if ($skipped > 0) {
            $recipients = array_slice($recipients, 0, $cap);
            Log::warning('Event notification capped', [
                'event' => $event->uuid,
                'milestone' => $milestone->key,
                'delivered' => count($recipients),
                'skipped' => $skipped,
            ]);
        }

        EventNotificationSent::where('event_id', $event->id)
            ->where('milestone', $milestone->key)
            ->update(['recipients' => count($recipients), 'skipped' => $skipped, 'sent_at' => now()]);

        foreach (array_chunk($recipients, (int) config('event_notifications.chunk', 500)) as $chunk) {
            DeliverEventNotification::dispatch($event->id, $milestone->key, $chunk, [
                'title' => $milestone->title,
                'body' => $milestone->body,
                'icon' => $milestone->icon,
            ]);
        }

        return count($recipients);
    }

    /**
     * Take exclusive ownership of this (event, milestone). Returns false when
     * someone already has it.
     */
    private function claim(ClubEvent $event, Milestone $milestone): bool
    {
        try {
            EventNotificationSent::create([
                'event_id' => $event->id,
                'milestone' => $milestone->key,
            ]);

            return true;
        } catch (QueryException) {
            return false;   // unique index — already claimed
        }
    }

    /** @return array<int, int> */
    private function resolve(ClubEvent $event, Milestone $milestone): array
    {
        return match ($milestone->audience) {
            Milestone::AUDIENCE_SCOPE => $this->audience->forEvent($event, announcement: true),
            Milestone::AUDIENCE_PARTICIPANTS => $this->audience->participants($event),
            default => $this->audience->registrants($event),
        };
    }
}
