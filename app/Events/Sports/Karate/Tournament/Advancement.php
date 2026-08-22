<?php

namespace App\Events\Sports\Karate\Tournament;

use App\Events\Sports\Karate\Tournament\Scoreboard\Scoring;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Sports\Combat\CombatSport;
use Illuminate\Support\Collection;

/**
 * Bracket progression for a single-elimination weight division.
 *
 * When a bout is decided the winner is carried into their next bout
 * automatically, all the way to the final; when the final is decided the
 * division's medals are awarded. Correcting an earlier result re-cuts every
 * bout downstream of it, so a mistyped winner can never leave a stale name
 * sitting in a later round.
 *
 * Bracket shape is derived from slot order, matching how DrawEngine lays a
 * division out: within a round, bout i feeds bout ⌊i/2⌋ of the next round,
 * taking side A when i is even and side B when it is odd.
 */
class Advancement
{
    public function __construct(private CombatSport $sport) {}

    /**
     * Record a bout's outcome and propagate it.
     *
     * @param  array{winner?: ?string, a_score?: ?string, b_score?: ?string, status?: ?string}  $payload
     * @return array{match: array, advanced: array<int, array>, podium: array, division_complete: bool}
     */
    public function record(EventMatch $match, array $payload): array
    {
        $winner = in_array($payload['winner'] ?? null, ['a', 'b'], true) ? $payload['winner'] : null;

        // A side can only win if it has a competitor in it.
        if ($winner === 'a' && ! $match->a_name) {
            $winner = null;
        }
        if ($winner === 'b' && ! $match->b_name) {
            $winner = null;
        }

        $match->a_score = $this->score($payload['a_score'] ?? null);
        $match->b_score = $this->score($payload['b_score'] ?? null);
        $match->winner = $winner;

        // Why, when the score was not the answer — a disqualification, a
        // withdrawal, a doctor's call. Only ever set alongside a winner, and
        // whitelisted here as well as at the console: this reaches the record,
        // and the record is read by the podium, the profile and the export.
        if ($winner !== null && ($payload['win_reason'] ?? null) !== null) {
            $reason = (string) $payload['win_reason'];
            $match->win_reason = in_array($reason, Scoring::WIN_REASONS, true) ? $reason : 'other';
            $note = trim((string) ($payload['win_note'] ?? ''));
            $match->win_note = $note === '' ? null : mb_substr($note, 0, 200);
        } else {
            // Won on points, or the result is being cleared: nothing to explain.
            $match->win_reason = null;
            $match->win_note = null;
        }
        $match->status = $winner ? 'done' : (in_array($payload['status'] ?? null, ['upcoming', 'live'], true) ? $payload['status'] : 'upcoming');
        $match->save();

        $category = $match->category;
        $advanced = $this->propagate($category, $match);

        $podium = $this->awardIfComplete($category);

        return [
            'match' => $this->row($match->refresh()),
            'advanced' => $advanced,
            'podium' => $podium,
            'division_complete' => $podium !== [],
        ];
    }

    /**
     * Carry the result of $from into the next round, then keep walking forward
     * clearing anything the change invalidated.
     *
     * @return array<int, array> the bouts that changed, for an in-place UI patch
     */
    public function propagate(EventCategory $category, EventMatch $from): array
    {
        $rounds = $this->rounds($category);
        $changed = [];
        $current = $from;

        while ($next = $this->nextBout($rounds, $current)) {
            [$bout, $side] = $next;

            $won = $current->winner;
            $name = $won ? $current->{$won.'_name'} : null;
            $competitorId = $won ? $current->{$won.'_competitor_id'} : null;
            $country = $won ? $current->{$won.'_country'} : null;
            $provisional = $won ? (bool) $current->{$won.'_provisional'} : false;

            // Compare on the competitor, falling back to the name for a
            // hand-typed entrant who has no registration behind them.
            $unchanged = $competitorId
                ? $bout->{$side.'_competitor_id'} === $competitorId
                : ($bout->{$side.'_competitor_id'} === null && $bout->{$side.'_name'} === $name);

            if ($unchanged) {
                break; // nothing downstream changed
            }

            $bout->{$side.'_name'} = $name;
            $bout->{$side.'_competitor_id'} = $competitorId;
            $bout->{$side.'_country'} = $name ? $country : null;
            $bout->{$side.'_provisional'} = $name ? $provisional : false;
            $bout->{$side.'_score'} = null;

            // The competitor in this slot changed — any result this bout already
            // held is void, and so is everything after it.
            if ($bout->winner) {
                $bout->winner = null;
                $bout->status = 'upcoming';
            }
            $bout->save();

            $changed[] = $this->row($bout);
            $current = $bout;
        }

        return $changed;
    }

    /**
     * Award the division when its final is decided: gold, silver, and bronze by
     * the sport's own convention (World Karate awards two).
     *
     * @return array<int, array{place: int, name: string}>
     */
    public function awardIfComplete(EventCategory $category): array
    {
        $matches = $category->matches()->orderBy('slot')->get();
        $final = $matches->firstWhere('round', 'Final');

        if (! $final || ! $final->winner) {
            return [];
        }

        $win = $final->winner;
        $lose = $win === 'a' ? 'b' : 'a';

        $podium = [$this->medal(1, $final, $win)];
        if ($final->{$lose.'_name'}) {
            $podium[] = $this->medal(2, $final, $lose);
        }

        // Bronze: both semi-final losers (WT repechage / both_sf_losers), or a
        // single third-place bout winner when the sport says so.
        if ($this->sport->bronzeRule() === 'third_place_match') {
            $third = $matches->firstWhere('round', 'Third place');
            if ($third?->winner) {
                $podium[] = $this->medal(3, $third, $third->winner);
            }
        } else {
            foreach ($matches->where('round', 'Semifinal') as $sf) {
                if (! $sf->winner) {
                    continue;
                }
                $loserSide = $sf->winner === 'a' ? 'b' : 'a';
                if ($sf->{$loserSide.'_name'}) {
                    $podium[] = $this->medal(3, $sf, $loserSide);
                }
            }
        }

        $category->update(['podium' => $podium, 'status' => 'completed']);

        return $podium;
    }

    /** One medal, carrying the entry it was won by. */
    private function medal(int $place, EventMatch $bout, string $side): array
    {
        return [
            'place' => $place,
            'name' => $bout->{$side.'_name'},
            'competitor_id' => $bout->{$side.'_competitor_id'},
        ];
    }

    /* ---------------- Bracket shape ---------------- */

    /**
     * Bouts grouped into rounds, in bracket order (earliest round first), each
     * round's bouts in slot order.
     *
     * @return Collection<int, Collection<int, EventMatch>>
     */
    private function rounds(EventCategory $category): Collection
    {
        return $category->matches()->orderBy('slot')->get()
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->values();
    }

    /**
     * The bout $from feeds into, and which side of it.
     *
     * @return array{0: EventMatch, 1: string}|null
     */
    private function nextBout(Collection $rounds, EventMatch $from): ?array
    {
        foreach ($rounds as $r => $bouts) {
            $index = $bouts->search(fn (EventMatch $m) => $m->id === $from->id);
            if ($index === false) {
                continue;
            }

            $nextRound = $rounds->get($r + 1);
            if (! $nextRound) {
                return null; // this was the final
            }

            $bout = $nextRound->get(intdiv($index, 2));

            return $bout ? [$bout, $index % 2 === 0 ? 'a' : 'b'] : null;
        }

        return null;
    }

    private function score(?string $raw): ?string
    {
        $s = trim((string) $raw);

        return $s === '' ? null : mb_substr($s, 0, 16);
    }

    /** @return array<string, mixed> */
    private function row(EventMatch $m): array
    {
        return [
            'id' => $m->id,
            'round' => $m->round,
            'no' => $m->match_no,
            'court' => $m->court,
            'status' => $m->status,
            'winner' => $m->winner,
            'a' => ['name' => $m->a_name, 'competitor_id' => $m->a_competitor_id, 'score' => $m->a_score, 'provisional' => (bool) $m->a_provisional],
            'b' => ['name' => $m->b_name, 'competitor_id' => $m->b_competitor_id, 'score' => $m->b_score, 'provisional' => (bool) $m->b_provisional],
        ];
    }
}
