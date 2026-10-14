<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Facades\DB;

/**
 * A board for a division that has none — made by dropping a competitor on it.
 *
 * A division of one never had a draw cut ("No draw yet"), and the athlete sat
 * in its basket with nowhere to be dropped: arranging moves people between
 * SLOTS, and there were none. So a drop on the empty board names no slot
 * (`to.type = slot`, no match id), and this lays the one bout such a division
 * needs — a final — and hands the move on with the new bout as its target.
 * The package's own Arrangement then places them exactly as any drag would,
 * and its re-flow makes the lonely final a walkover until a second competitor
 * is put in the other corner.
 *
 * Shared by every bracketed sport (CLAUDE.md → Shared Stays Shared): the final
 * is geometry, not a rule book. Knockout only — a pool has no slots to make.
 * The new bout and the move are one transaction, so a refused move leaves no
 * empty final behind.
 */
class LoneFinal
{
    /**
     * @param  callable(array): array  $move  the package's Arrangement::move, bound to the event
     * @return array  the move's own result, or a refusal
     */
    public static function arrange(ClubEvent $event, array $payload, callable $move): array
    {
        if (! self::wanted($payload)) {
            return $move($payload);
        }

        $category = $event->categories()->find($payload['category_id'] ?? null);

        if (! $category || $category->isHeading()) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN
            || $category->matches()->exists()) {
            return ['ok' => false, 'message' => __('events::events.bracket_slot_invalid')];
        }

        try {
            return DB::transaction(function () use ($event, $category, $payload, $move) {
                $final = $category->matches()->create([
                    'event_id' => $event->id,
                    'round' => 'Final',
                    'phase' => 'finals',
                    'slot' => 0,
                    'status' => 'upcoming',
                ]);

                // Laid by a person: never re-cut by the engine behind them.
                $category->update(['draw_state' => 'manual', 'draw_count' => 1]);

                $result = $move(['to' => ['type' => 'slot', 'match_id' => $final->id, 'side' => 'a']] + $payload);

                if (! ($result['ok'] ?? false)) {
                    throw new TransferRefused($result['message'] ?? __('events::events.bracket_move_failed'));
                }

                return $result;
            });
        } catch (TransferRefused $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** A drop on a board with no slot to name — and the flag is on. */
    private static function wanted(array $payload): bool
    {
        return DrawEngine::loneFinals()
            && ($payload['to']['type'] ?? null) === 'slot'
            && empty($payload['to']['match_id'])
            && ($payload['from']['type'] ?? null) === 'bench';
    }
}
