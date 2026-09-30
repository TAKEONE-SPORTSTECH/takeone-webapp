<?php

namespace App\Events\Observers;

use App\Events\Jobs\DraftEventCertificates;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventCertificateSetting;

/**
 * Notices the two moments an event's certificates are earned, and asks for
 * drafts — per the organiser's choice (event_certificate_settings.trigger):
 *
 *   division  a division's podium was just stored (Advancement writes it)
 *   event     the event was just completed
 *   manual    never on its own
 *
 * ⚠️ This runs inside the request that scored a bout. It only DISPATCHES, and
 * every failure is swallowed: a certificate may be late, a result may not.
 */
class EventCertificateObserver
{
    public function categoryUpdated(EventCategory $category): void
    {
        if (! config('certificates.enabled') || ! $category->wasChanged('podium') || empty($category->podium)) {
            return;
        }
        $this->dispatchFor((int) $category->event_id, 'division');
    }

    public function eventUpdated(ClubEvent $event): void
    {
        if (! config('certificates.enabled') || ! $event->wasChanged('status') || $event->status !== 'completed') {
            return;
        }
        // Completing the event also catches anything a per-division pass missed.
        $this->dispatchFor((int) $event->id, null);
    }

    private function dispatchFor(int $eventId, ?string $only): void
    {
        rescue(function () use ($eventId, $only) {
            $trigger = EventCertificateSetting::where('event_id', $eventId)->value('trigger') ?? 'division';
            if ($trigger === 'manual' || ($only !== null && $trigger !== $only)) {
                return;
            }
            DraftEventCertificates::dispatch($eventId)->afterCommit();
        }, null, report: true);
    }
}
