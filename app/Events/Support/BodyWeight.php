<?php

namespace App\Events\Support;

use App\Events\Models\ClubEventRegistration;

/**
 * One body, one weight — and once a person has stood on the scale, the scale's
 * reading is THE weight (owner, 2026-10-15: "the weigh-in weight is the
 * authentic one").
 *
 * A person entered twice (Kata and Kumite, or two weight divisions) holds two
 * registrations, and a desk weighs ONE of them. Every placement path — the
 * auto-place, the rule-book re-sort, the merge check, the federation sync —
 * reads the weight off the registration in hand, so a sibling still carrying
 * the declared weight would be placed by a number the scale already replaced.
 *
 *  - weighed()  the scale's reading for this person at this event, or null.
 *  - spread()   puts that reading on their entries not yet weighed. It does
 *               NOT sign those entries off: weighed_in_at stays the record of
 *               which entry a desk actually signed.
 */
class BodyWeight
{
    /** The most recent weigh-in weight this person has at this event, or null. */
    public static function weighed(int $eventId, ?int $userId): ?float
    {
        if (! $userId) {
            return null;
        }

        $w = ClubEventRegistration::where('event_id', $eventId)->where('user_id', $userId)->where('role', 'participant')
            ->whereNotNull('weighed_in_at')->whereNotNull('weighed_in_by')->whereNotNull('weight')
            ->orderByDesc('weighed_in_at')->orderByDesc('id')->value('weight');

        return $w !== null && (float) $w > 0 ? (float) $w : null;
    }

    /**
     * The scale's reading onto the person's entries at this event that no desk
     * has weighed yet. Returns how many entries changed.
     */
    public static function spread(int $eventId, ?int $userId): int
    {
        $w = self::weighed($eventId, $userId);
        if ($w === null) {
            return 0;
        }

        $n = 0;
        ClubEventRegistration::where('event_id', $eventId)->where('user_id', $userId)->where('role', 'participant')
            ->whereNull('weighed_in_at')->get()
            ->each(function (ClubEventRegistration $r) use ($w, &$n) {
                if ($r->weight === null || (float) $r->weight !== $w) {
                    $r->forceFill(['weight' => $w])->save();
                    $n++;
                }
            });

        return $n;
    }
}
