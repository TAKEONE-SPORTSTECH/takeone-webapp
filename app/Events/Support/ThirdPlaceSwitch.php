<?php

namespace App\Events\Support;

use App\Events\Contracts\AwardsDivisions;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Facades\DB;

/**
 * Turning the play-off for bronze on, or off, on a championship that is already
 * drawn.
 *
 * The switch is not just a column: a draw that was cut without a play-off has
 * no bout to hold one, and a draw that was cut with one has a bout that must
 * not be left behind when it is turned off. Re-cutting the draw would settle
 * both and throw away every result already recorded, so each division is
 * reconciled in place instead.
 *
 * ON  — every division with a semi-final that has no play-off gets one, placed
 *       on the final's slot so it runs BEFORE the final, with both beaten
 *       semi-finalists already standing in it.
 * OFF — an UNFOUGHT play-off is removed. One that has a result is KEPT and
 *       reported: a bout somebody actually fought is a record, and deleting it
 *       to satisfy a setting is the kind of quiet data loss this platform does
 *       not do. The organiser is told, and can clear the result themselves if
 *       they meant it.
 *
 * Either way every division is re-awarded afterwards, so the podium on the
 * results sheet agrees with the convention the same second it changes.
 */
class ThirdPlaceSwitch
{
    /**
     * @param  object|null  $type  the event's own type, asked to re-award each
     *                             division afterwards when it can (the medal
     *                             convention is the package's, not ours)
     * @return array{added: int, removed: int, kept: int}
     */
    public function apply(ClubEvent $event, bool $playOff, ?object $type = null): array
    {
        $added = $removed = $kept = 0;

        foreach (EventCategory::where('event_id', $event->id)->get() as $category) {
            $semis = $category->matches()->where('round', 'Semifinal')->orderBy('slot')->get();
            $third = $category->matches()->where('round', DrawEngine::ROUND_THIRD)->first();

            if ($playOff && $semis->isNotEmpty() && ! $third) {
                DB::transaction(function () use ($event, $category, $semis) {
                    $final = $category->matches()->where('round', 'Final')->orderBy('slot')->first();
                    $slot = $final?->slot ?? ($category->matches()->max('slot') + 1);

                    if ($final) {
                        $final->slot = $slot + 1;
                        $final->save();
                    }

                    $bout = $category->matches()->create([
                        'event_id' => $event->id,
                        'round' => DrawEngine::ROUND_THIRD,
                        'phase' => 'finals',
                        'slot' => $slot,
                        'status' => 'upcoming',
                    ]);

                    foreach ([0 => 'a', 1 => 'b'] as $i => $side) {
                        $semi = $semis[$i] ?? null;

                        if (! $semi instanceof EventMatch || ! $semi->winner) {
                            continue;
                        }

                        $lost = $semi->winner === 'a' ? 'b' : 'a';

                        $bout->{$side.'_name'} = $semi->{$lost.'_name'};
                        $bout->{$side.'_competitor_id'} = $semi->{$lost.'_competitor_id'};
                        $bout->{$side.'_country'} = $semi->{$lost.'_country'};
                        $bout->{$side.'_provisional'} = (bool) $semi->{$lost.'_provisional'};
                    }

                    $bout->save();
                });

                $added++;
            }

            if (! $playOff && $third) {
                if ($third->winner) {
                    $kept++;
                } else {
                    $third->delete();
                    $removed++;
                }
            }

            /*
             * The podium is stored, so it does not re-derive itself on a read.
             * Cleared and re-awarded here: the convention just changed, and a
             * division showing yesterday's medals is the whole complaint this
             * switch exists to answer.
             */
            $category->update(['podium' => []]);

            if ($type instanceof AwardsDivisions) {
                $type->awardDivision($category->fresh());
            }
        }

        return ['added' => $added, 'removed' => $removed, 'kept' => $kept];
    }
}
