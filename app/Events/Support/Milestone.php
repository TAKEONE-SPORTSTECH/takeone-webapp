<?php

namespace App\Events\Support;

use Carbon\CarbonInterface;

/**
 * One point in an event's life at which people are told something.
 *
 * A package declares its own set (a championship has weigh-in and draw
 * milestones; a belt test has neither), and the shared runner decides when each
 * is due and who receives it. Declaring a milestone is not sending it — the
 * ledger guarantees each one fires exactly once per event.
 */
final class Milestone
{
    public const AUDIENCE_SCOPE = 'scope';              // broadcast, widened by the event's scope

    public const AUDIENCE_REGISTRANTS = 'registrants';  // everyone who joined, + the organiser

    public const AUDIENCE_PARTICIPANTS = 'participants'; // competitors only

    public function __construct(
        /** Stable key, unique per event — the ledger's idempotency key. */
        public readonly string $key,
        public readonly string $title,
        public readonly string $body,
        /** When it becomes due. Null = fire immediately (a state trigger). */
        public readonly ?CarbonInterface $at = null,
        public readonly string $audience = self::AUDIENCE_REGISTRANTS,
        public readonly string $icon = 'bi-calendar-event',
    ) {}

    /** Broadcasts respect the announcements opt-out; the rest are reminders. */
    public function isAnnouncement(): bool
    {
        return $this->audience === self::AUDIENCE_SCOPE;
    }

    public function isDue(): bool
    {
        return $this->at === null || $this->at->isPast();
    }
}
