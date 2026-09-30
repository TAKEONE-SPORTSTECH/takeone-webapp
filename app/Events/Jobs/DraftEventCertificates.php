<?php

namespace App\Events\Jobs;

use App\Certificates\Support\CertificateRealtime;
use App\Events\Models\ClubEvent;
use App\Events\Support\EventCertificates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Drafts an event's earned certificates after a division's podium is decided
 * or the event is completed (App\Events\Observers\EventCertificateObserver).
 *
 * Off the request that scored the bout: a result must never wait on, or fail
 * because of, paperwork. Drafts only — nothing reaches an athlete until the
 * organiser approves. Unique per event, and generate() is idempotent, so a
 * burst of finished divisions collapses into one pass.
 */
class DraftEventCertificates implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $uniqueFor = 60;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return 'certificates:'.$this->eventId;
    }

    public function handle(EventCertificates $certificates): void
    {
        $event = ClubEvent::find($this->eventId);
        if (! config('certificates.enabled') || ! $event || $event->is_archived) {
            return;
        }

        $r = $certificates->generate($event, null);
        if ($r['created'] > 0) {
            CertificateRealtime::refresh($certificates->audience($event));
        }
    }
}
