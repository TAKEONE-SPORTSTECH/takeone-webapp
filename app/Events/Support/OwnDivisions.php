<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;

/**
 * An event whose organiser made their OWN divisions is filled into those —
 * never into the rule book's (owner, 2026-10-15: "the auto fill must obey my
 * self-created divisions, not the Karate Kumite ones").
 *
 * Before this, every automatic placement — the federation sync, the athlete
 * editor, a club's entry, auto-place — went through
 * CategorySystems::placeCreating(), which classified by the WKF table and
 * CREATED "Kids Kumite Male -35 kg" (ages 6–11) beside the organiser's own
 * "Group L · 8–9 yrs · 27–32 kg" whenever somebody fitted none of theirs.
 *
 *  - uses()   the event has at least one division the organiser made by hand:
 *             a competing division with no rule-book weight class on it.
 *  - place()  the tightest of the event's divisions the entry fits OUTRIGHT
 *             (DivisionRange — the same verdict as the "Not in a division"
 *             list and the editor's preview), by the weigh-in weight where
 *             there is one. Nothing fits: the entry stays unplaced, for the
 *             organiser to see. It NEVER creates a division.
 */
class OwnDivisions
{
    /**
     * The organiser's CHOICE first (club_events.division_source, set by the
     * auto-place popup and kept until they choose again): 'own' always fills
     * their divisions, 'table' always follows the weight table. Never chosen:
     * their own divisions win as soon as they have made one.
     */
    public static function uses(ClubEvent $event): bool
    {
        $source = $event->division_source ?? null;
        if ($source === 'own') {
            return true;
        }
        if ($source === 'table') {
            return false;
        }

        return EventCategory::where('event_id', $event->id)->competing()
            ->where(fn ($q) => $q->whereNull('weight_class')->orWhere('weight_class', ''))
            ->exists();
    }

    public static function place(ClubEvent $event, ClubEventRegistration $reg): ?EventCategory
    {
        if ($reg->category_id) {
            return $reg->category;
        }
        if (BodyWeight::spread($event->id, $reg->user_id)) {
            $reg->refresh();
        }
        $reg->loadMissing('user');

        $granted = EventStreams::grantedForMany([$reg->id])[$reg->id] ?? null;
        $best = null;
        $bestKey = null;
        foreach (EventCategory::where('event_id', $event->id)->competing()->orderBy('sort_order')->orderBy('id')->get() as $c) {
            $c->setRelation('event', $event);
            if ($c->range()->judge($reg, $granted ?: null)['fit'] !== 'in') {
                continue;
            }
            $w = ($c->max_weight !== null && (float) $c->max_weight > 0 ? (float) $c->max_weight : 300)
                - ($c->min_weight !== null ? (float) $c->min_weight : 0);
            $a = ($c->max_age !== null ? (int) $c->max_age : 100) - ($c->min_age !== null ? (int) $c->min_age : 0);
            $key = [$w, $a];
            if ($best === null || $key < $bestKey) {
                $best = $c;
                $bestKey = $key;
            }
        }

        if ($best) {
            $reg->forceFill(['category_id' => $best->id, 'category_source' => ClubEventRegistration::PLACED_AUTO])->save();
        }

        return $best;
    }
}
