<?php

namespace App\Jobs;

use App\Models\ClubEvent;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers one chunk of an event notification.
 *
 * Runs on the queue so a nationwide announcement never blocks the request that
 * triggered it, and is chunked so each job stays small. `notifyUser()` writes
 * the row AND pushes it over MQTT with a deep link, so recipients see it live.
 *
 * Idempotency lives one level up (the ledger claim in EventNotifier) — by the
 * time a job runs, the milestone is already owned, so a retry of a single chunk
 * is the only duplication risk and is bounded to that chunk.
 */
class DeliverEventNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<int, int>  $userIds
     * @param  array{title: string, body: string, icon: string}  $message
     */
    public function __construct(
        private int $eventId,
        private string $milestone,
        private array $userIds,
        private array $message,
    ) {}

    public function handle(): void
    {
        $event = ClubEvent::find($this->eventId);

        // The event went away (or was cancelled/archived) between queueing and
        // running — say nothing.
        if (! $event || $event->status === 'cancelled' || $event->is_archived) {
            return;
        }

        $url = route('me.events.show', $event->uuid);

        foreach ($this->userIds as $userId) {
            UserNotification::notifyUser($userId, 'event', $this->message['title'], [
                'body' => $this->message['body'],
                'icon' => $this->message['icon'],
                'action_url' => $url,
                'tenant_id' => $event->tenant_id,
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $event->id,
                'context' => $this->milestone,
            ]);
        }
    }
}
