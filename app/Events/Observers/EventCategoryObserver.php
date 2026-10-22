<?php

namespace App\Events\Observers;

use App\Events\Models\EventCategory;
use App\Members\Support\ProfileHistorySync;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Carries a division's podium onto its medallists' profiles (owner,
 * 2026-10-17 — a gold won here left the profile's medal count at 0).
 *
 * One hook for every writer of `podium` — each sport's Advancement, the
 * championship Runner, a reverted result clearing it — so no sport has to
 * remember to call it.
 *
 * After commit, so it never runs inside a scoring transaction, and
 * best-effort, so a profile write can never fail a result being recorded.
 * Does nothing unless EVENT_PROFILE_MEDALS is on (ProfileHistorySync).
 */
class EventCategoryObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private ProfileHistorySync $sync) {}

    public function saved(EventCategory $category): void
    {
        // A podium that is set, or was just changed (cleared on a revert).
        if ($category->wasChanged('podium') || ! empty($category->podium)) {
            $this->sync->safely(fn () => $this->sync->syncPodium($category));
        }
    }

    public function deleted(EventCategory $category): void
    {
        $this->sync->safely(fn () => $this->sync->forgetPodium((int) $category->id));
    }
}
