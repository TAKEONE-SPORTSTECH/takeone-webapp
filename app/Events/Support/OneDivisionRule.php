<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;

/**
 * "One division per person" (owner, 2026-10-16): when an event switches it on,
 * nobody may stand in a SECOND division's bracket.
 *
 * Off by default (`club_events.one_division_per_person`), which is how every
 * event has always worked. The switch lives on the federation Draw tab and on
 * the draw desk; the doors that put a person onto a board — arranging from a
 * basket, admitting a late entry, copying into another division, pulling a
 * candidate in — all ask `refusal()` first, so no screen can get round it.
 *
 * "In a bracket" means standing in one of the division's BOUTS, not merely
 * holding an entry: an entry waiting in a basket is a booking, not a place.
 */
final class OneDivisionRule
{
    public static function on(ClubEvent $event): bool
    {
        return (bool) $event->one_division_per_person;
    }

    /**
     * The other division whose bracket this person already stands in, or null.
     * A hand-typed name with no account behind it is never matched.
     */
    public static function placedElsewhere(ClubEvent $event, ?int $userId, ?int $exceptCategoryId, ?int $exceptEntryId = null): ?EventCategory
    {
        if (! $userId) {
            return null;
        }

        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->where('user_id', $userId)
            ->whereNotNull('category_id')
            ->when($exceptCategoryId, fn ($q) => $q->where('category_id', '!=', $exceptCategoryId))
            // The entry being MOVED leaves where it was: moving is not a second division.
            ->when($exceptEntryId, fn ($q) => $q->where('id', '!=', $exceptEntryId))
            ->get(['id', 'category_id']);

        if ($entries->isEmpty()) {
            return null;
        }

        $ids = $entries->pluck('id')->all();

        $bout = EventMatch::whereIn('category_id', $entries->pluck('category_id')->unique()->all())
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $ids)->orWhereIn('b_competitor_id', $ids))
            ->first(['category_id']);

        return $bout ? EventCategory::find($bout->category_id) : null;
    }

    /**
     * The sentence that refuses putting this entry's person into $target's
     * bracket, or null when it is allowed (rule off, or nowhere else).
     */
    public static function refusal(ClubEvent $event, ?ClubEventRegistration $entry, ?int $targetCategoryId, bool $moving = false): ?string
    {
        if (! $entry || ! self::on($event)) {
            return null;
        }

        $other = self::placedElsewhere($event, $entry->user_id, $targetCategoryId, $moving ? (int) $entry->id : null);

        if (! $other) {
            return null;
        }

        $entry->loadMissing('user:id,full_name,name');
        $name = $entry->user?->full_name ?: $entry->user?->name ?: __('events::events.athlete');

        return __('events::events.one_division_refused', ['name' => $name, 'division' => $other->name]);
    }

    /**
     * Every person standing in some bracket of this event: user id → the
     * divisions they stand in. For the candidate list, in three queries
     * however long it is.
     *
     * @return array<int, list<array{id: int, name: string}>>
     */
    public static function placements(ClubEvent $event): array
    {
        $bouts = EventMatch::whereIn('category_id', $event->categories()->pluck('id'))
            ->get(['category_id', 'a_competitor_id', 'b_competitor_id']);

        $byEntry = [];
        foreach ($bouts as $b) {
            foreach ([$b->a_competitor_id, $b->b_competitor_id] as $id) {
                if ($id) {
                    $byEntry[(int) $id] ??= (int) $b->category_id;
                }
            }
        }

        if ($byEntry === []) {
            return [];
        }

        $names = EventCategory::whereIn('id', array_unique($byEntry))->pluck('name', 'id');
        $out = [];

        ClubEventRegistration::whereIn('id', array_keys($byEntry))
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->each(function ($r) use (&$out, $byEntry, $names) {
                $cat = $byEntry[$r->id];
                $out[(int) $r->user_id][] = ['id' => $cat, 'name' => (string) ($names[$cat] ?? '')];
            });

        return $out;
    }
}
