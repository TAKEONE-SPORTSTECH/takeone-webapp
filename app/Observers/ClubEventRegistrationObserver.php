<?php

namespace App\Observers;

use App\Models\ClubEventRegistration;
use App\Support\ProfileHistorySync;

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
    }
}
