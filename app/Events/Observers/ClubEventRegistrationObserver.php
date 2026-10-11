<?php

namespace App\Events\Observers;

use App\Events\Models\ClubEventRegistration;
use App\Events\Support\EventHost;
use App\Members\Support\ProfileHistorySync;

/**
 * Mirrors a competitor's event entry into their tournament history, so the
 * profile's Tournaments tab reflects events they were actually entered into.
 *
 * Created-only and best-effort: entering an event must never fail because a
 * profile row could not be written.
 */
class ClubEventRegistrationObserver
{
    public function __construct(private ProfileHistorySync $sync) {}

    public function created(ClubEventRegistration $registration): void
    {
        $this->sync->safely(fn () => $this->sync->syncRegistration($registration));
        EventHost::entrantChanged($registration, 'created');
    }

    /** Only the changes a host's figures read: division, weigh-in, weight, entry state. */
    public function updated(ClubEventRegistration $registration): void
    {
        if ($registration->wasChanged(['category_id', 'weighed_in_at', 'weight', 'entry_state', 'role'])) {
            EventHost::entrantChanged($registration, 'updated');
        }
    }

    public function deleted(ClubEventRegistration $registration): void
    {
        EventHost::entrantChanged($registration, 'deleted');
    }
}
