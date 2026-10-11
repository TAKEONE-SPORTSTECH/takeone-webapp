<?php

namespace App\Events\Support;

use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;

/**
 * Which PART of a division a bout belongs to — preliminaries, semi-finals or
 * finals — for a running order that places a division on the day part by part
 * (owner, 2026-10-07: "some competitions schedule the preliminaries first and
 * leave the finals for the end of the day").
 *
 * Read from the bout's ROUND, not its phase: the engines file a semi-final
 * under the `finals` phase, and an organiser who says "semi-finals" means the
 * round. Everything before the semi-finals (a round of 16, the quarter-finals,
 * a pool) is preliminary; the final and the bronze bout are the finals.
 *
 * Common to every bracketed sport, so it lives here once.
 */
final class BoutPart
{
    public const PRELIMS = 'prelims';

    public const SEMIS = 'semis';

    public const FINALS = 'finals';

    /**
     * The division's medal ceremony (owner, 2026-10-07: "we need to add the
     * awarding too"). A part of the division on the day, with no bouts of its
     * own — timed as a ceremony.
     */
    public const AWARDING = 'awarding';

    /** The parts that hold bouts, in running order. */
    public const ALL = [self::PRELIMS, self::SEMIS, self::FINALS];

    /** Everything a division can be placed as, in running order. */
    public const PLACEABLE = [self::PRELIMS, self::SEMIS, self::FINALS, self::AWARDING];

    public static function of(?string $round): string
    {
        return match (true) {
            $round === 'Semifinal' => self::SEMIS,
            in_array($round, ['Final', 'Third place'], true) => self::FINALS,
            default => self::PRELIMS,
        };
    }

    /** Only known parts, in running order, no repeats; null when that is all of them or none. */
    public static function normalise(?array $parts): ?array
    {
        if ($parts === null) {
            return null;
        }
        $out = array_values(array_filter(self::PLACEABLE, fn ($p) => in_array($p, $parts, true)));

        return $out === [] ? null : $out;
    }

    public static function label(string $part): string
    {
        return __('events::events.bout_part_'.$part);
    }

    /** "Preliminaries & Semi-finals" — or null for a whole division. */
    public static function labels(?array $parts): ?string
    {
        return $parts ? implode(' & ', array_map([self::class, 'label'], $parts)) : null;
    }

    /** A bout nobody fights: one corner empty and already decided. */
    public static function isBye(EventMatch $m): bool
    {
        $empty = fn (string $side) => ! $m->{$side.'_competitor_id'} && ($m->{$side.'_name'} === null || $m->{$side.'_name'} === '');

        return $m->winner && ($empty('a') || $empty('b'));
    }

    /**
     * The parts this division actually has bouts in (byes are not bouts). An
     * empty list means no draw yet — the division can only go on the day whole.
     *
     * @return list<string>
     */
    public static function present(EventCategory $division): array
    {
        $parts = EventMatch::where('category_id', $division->id)->get(['round', 'winner', 'a_competitor_id', 'b_competitor_id', 'a_name', 'b_name'])
            ->reject(fn ($m) => self::isBye($m))
            ->map(fn ($m) => self::of($m->round))
            ->unique()->all();

        return array_values(array_filter(self::ALL, fn ($p) => in_array($p, $parts, true)));
    }

    /** Bouts (not byes) per division and part: [category_id][part] => count. */
    public static function counts(int $eventId): array
    {
        $out = [];
        foreach (EventMatch::where('event_id', $eventId)->get(['category_id', 'round', 'winner', 'a_competitor_id', 'b_competitor_id', 'a_name', 'b_name']) as $m) {
            if (! self::isBye($m)) {
                $out[$m->category_id][self::of($m->round)] = ($out[$m->category_id][self::of($m->round)] ?? 0) + 1;
            }
        }

        return $out;
    }
}
