<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\ClubEntryDesks;

/**
 * The address the Share control hands on (owner, 2026-10-04: shared links
 * showed no preview). A published event shares its own page; an event that is
 * NOT public but takes entries by invitation shares its INVITATION link —
 * the bare /me/events/{uuid} would send whoever receives it to the login page.
 */
final class EventShareLink
{
    public static function for(ClubEvent $event): string
    {
        $page = route('me.events.show', ['event' => $event->uuid]);
        if (app(PublicEvent::class)->isPublic($event)) {
            return $page;
        }

        return rescue(fn () => ClubEntryDesks::for($event)?->invitationUrl(), null, report: true) ?: $page;
    }
}
