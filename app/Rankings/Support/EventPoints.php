<?php

namespace App\Rankings\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\EntryClub;
use App\Rankings\Schemes\Wkf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What every athlete earned at one event — the rows the ledger stores.
 *
 * Read from the event's own record of what happened: each bout's two entries
 * and its winner, and each division's stored podium. Nothing here re-decides a
 * result.
 *
 * WKF rules applied (World Ranking Rules 2026, "Points by Place" notes):
 *   • participation counts only once the athlete has fought at least one bout;
 *   • a bye is not a bout won — unless the athlete then wins a real bout in the
 *     same division, when it counts as one;
 *   • a bout won by kiken/walk-over against a real opponent IS a bout won;
 *   • points are awarded ONCE per event and category: an athlete entered twice
 *     in the same division (a spare entry, a duplicated division) keeps their
 *     best row, never the sum.
 */
class EventPoints
{
    /** Is this event one that grants ranking points? */
    public static function grants(ClubEvent $event): bool
    {
        return $event->host_type === 'federation'
            && $event->host_id
            && isset(config('rankings.schemes', [])[$event->sport])
            && in_array($event->category_system, (array) config('rankings.category_systems', []), true)
            && ! in_array($event->status, ['cancelled', 'draft'], true);
    }

    /**
     * The ledger rows for one event, keyed "user_id|category_key".
     *
     * @return array<string, array<string, mixed>>
     */
    public function for(ClubEvent $event): array
    {
        if (! self::grants($event)) {
            return [];
        }

        $factor = max(1, (int) config('rankings.default_factor', 6));
        $earnedOn = ($event->end_date ?: $event->date);

        $divisions = $event->categories()->with('matches')->get()
            ->filter(fn (EventCategory $c) => ! $c->is_heading && $c->status === 'completed' && ! empty($c->podium));

        $tallies = [];
        foreach ($divisions as $division) {
            foreach ($this->division($division) as $regId => $stats) {
                $tallies[] = ['division' => $division, 'registration_id' => $regId] + $stats;
            }
        }

        $registrations = ClubEventRegistration::with('user:id')
            ->whereIn('id', array_column($tallies, 'registration_id'))
            ->get()->keyBy('id');

        $rows = [];
        foreach ($tallies as $t) {
            $reg = $registrations->get($t['registration_id']);
            if (! $reg || ! $reg->user_id) {
                continue;   // a name with no account behind it cannot hold a ranking
            }

            /** @var EventCategory $division */
            $division = $t['division'];
            $kata = Str::contains(Str::lower($division->name), 'kata');

            $placePoints = $t['place'] ? Wkf::placePoints($t['place']) : $t['eliminated'];
            $medalRound = ($kata && $t['place']) ? Wkf::MEDAL_ROUND : 0;
            $raw = $t['participation'] + $t['wins'] * Wkf::PER_WIN + $placePoints + $medalRound;
            if ($raw <= 0) {
                continue;
            }

            $key = self::categoryKey($event, $division->name);
            $row = [
                'federation_id' => (int) $event->host_id,
                'user_id' => (int) $reg->user_id,
                'event_id' => (int) $event->id,
                'registration_id' => (int) $reg->id,
                'scheme' => Wkf::KEY,
                'category_key' => $key,
                'category_name' => (string) $division->name,
                'category_order' => (int) $division->sort_order,
                'discipline' => $kata ? 'kata' : 'kumite',
                'participation' => $t['participation'],
                'wins' => $t['wins'],
                'place' => $t['place'],
                'place_points' => $placePoints,
                'medal_round' => $medalRound,
                'factor' => $factor,
                'points' => $raw * $factor,
                'score_for' => $t['for'],
                'score_against' => $t['against'],
                'earned_on' => substr((string) $earnedOn, 0, 10),
            ];

            $slot = $row['user_id'].'|'.$row['category_key'];
            if (! isset($rows[$slot]) || $rows[$slot]['points'] < $row['points']) {
                $rows[$slot] = $row;
            }
        }

        // The club an athlete stood for, as the event recorded it.
        foreach ($rows as &$row) {
            $club = rescue(fn () => EntryClub::for($registrations->get($row['registration_id'])), null, report: false);
            $row['club_name'] = isset($club['name']) ? mb_substr((string) $club['name'], 0, 255) : null;
            $row['country'] = isset($club['country']) ? mb_substr((string) $club['country'], 0, 8) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Per-entry tallies for one finished division.
     *
     * @return array<int, array{participation:int, wins:int, place:?int, eliminated:int, for:int, against:int}>
     */
    private function division(EventCategory $division): array
    {
        $bouts = $division->matches->sortBy('slot');

        // Knockout rounds in bracket order, earliest first; the bronze bout sits
        // beside the final, not after it.
        $rounds = $bouts->where('round', '!=', 'Third place')
            ->groupBy('round')
            ->sortBy(fn (Collection $b) => $b->min('slot'))
            ->keys()->values();
        $finalIndex = $rounds->count() - 1;

        $s = [];
        $touch = function (?int $id) use (&$s) {
            if ($id && ! isset($s[$id])) {
                $s[$id] = ['real' => 0, 'wins' => 0, 'byes' => 0, 'lost_round' => null, 'for' => 0, 'against' => 0];
            }
        };

        foreach ($bouts as $bout) {
            $a = $bout->a_competitor_id;
            $b = $bout->b_competitor_id;
            $touch($a);
            $touch($b);

            if (! in_array($bout->winner, ['a', 'b'], true)) {
                continue;
            }
            $winner = $bout->winner === 'a' ? $a : $b;
            $loser = $bout->winner === 'a' ? $b : $a;

            if ($a && $b && ! $bout->won_by_bye) {
                $s[$a]['real']++;
                $s[$b]['real']++;
                $s[$winner]['wins']++;
                $s[$a]['for'] += (int) $bout->a_score;
                $s[$a]['against'] += (int) $bout->b_score;
                $s[$b]['for'] += (int) $bout->b_score;
                $s[$b]['against'] += (int) $bout->a_score;
                if ($bout->round !== 'Third place') {
                    $s[$loser]['lost_round'] = $bout->round;
                }
            } elseif ($winner) {
                $s[$winner]['byes']++;
            }
        }

        $places = [];
        foreach ((array) $division->podium as $medal) {
            $id = (int) ($medal['competitor_id'] ?? 0);
            $place = (int) ($medal['place'] ?? 0);
            if ($id && $place && (! isset($places[$id]) || $places[$id] > $place)) {
                $places[$id] = $place;
                $touch($id);
            }
        }

        $knockout = ($division->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_KNOCKOUT;

        $out = [];
        foreach ($s as $id => $x) {
            $eliminated = 0;
            if ($knockout && ! isset($places[$id]) && $x['lost_round'] !== null) {
                $index = $rounds->search($x['lost_round']);
                if ($index !== false) {
                    $eliminated = Wkf::eliminatedPoints($finalIndex - $index);
                }
            }

            $out[$id] = [
                'participation' => $x['real'] > 0 ? Wkf::PARTICIPATION : 0,
                // A bye only counts once a real bout has been won after it.
                'wins' => $x['wins'] + ($x['wins'] > 0 ? $x['byes'] : 0),
                'place' => $places[$id] ?? null,
                'eliminated' => $eliminated,
                'for' => $x['for'],
                'against' => $x['against'],
            ];
        }

        return $out;
    }

    /** The ranking category a division of this event counts towards. */
    public static function categoryKey(ClubEvent $event, string $divisionName): string
    {
        $system = Str::slug((string) ($event->category_system ?: $event->sport)) ?: 'open';

        return mb_substr($system.':'.self::slug($divisionName), 0, 190);
    }

    /** A category's stable, readable key: "-25 kg" and "+25 kg" must not collide. */
    public static function slug(string $name): string
    {
        $name = preg_replace('/\+\s*(\d)/', ' over $1', $name);
        $name = preg_replace('/(^|\s)-\s*(\d)/', '$1under $2', (string) $name);

        return Str::slug((string) $name);
    }
}
