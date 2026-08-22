<?php

namespace App\Observers;

use App\Models\Membership;
use App\Support\ProfileHistorySync;

/**
 * Mirrors a new club membership into the member's affiliation history, so the
 * profile's Affiliations tab reflects clubs they actually joined instead of only
 * what they typed in themselves.
 *
 * Created-only and best-effort: joining a club must never fail because a profile
 * row could not be written.
 */
class MembershipObserver
{
    public function __construct(private ProfileHistorySync $sync) {}

    public function created(Membership $membership): void
    {
        $this->sync->safely(fn () => $this->sync->syncMembership($membership));
    }
}
