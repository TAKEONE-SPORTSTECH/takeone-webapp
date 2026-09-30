<?php

namespace App\Events\Support\Competition;

use Illuminate\Validation\ValidationException;

/**
 * Turns what the organiser typed for a HEAD-TO-HEAD contest into a checked
 * result — who won, the score as stored, and the detail a score cannot hold —
 * by the sport's own rules (its recipe's `scoring`).
 *
 * Two shapes, by the recipe's ResultKind:
 *
 *   sets   {sets: [[6,4],[3,6],[10,7]]}           tennis, padel, table tennis,
 *                                                  badminton, squash, volleyball,
 *                                                  an esports series
 *   score  {a: 2, b: 1, penalties: [4, 3]}        football, basketball, chess…
 *
 * Either may instead be a walkover ({walkover: 'a'}: a wins without playing)
 * or a retirement ({retired: 'b', sets|a,b…}: b stopped, a wins).
 *
 * `$drawAllowed` is the CONTEST's answer, not the sport's: a football group
 * game may be drawn, a football final may not.
 *
 * @return array{winner: ?string, a_score: string, b_score: string, detail: array}
 */
final class ResultRules
{
    public function apply(SportRecipe $recipe, array $payload, bool $drawAllowed): array
    {
        if (in_array($payload['walkover'] ?? null, ['a', 'b'], true)) {
            $w = $payload['walkover'];

            return ['winner' => $w, 'a_score' => $w === 'a' ? 'W/O' : '', 'b_score' => $w === 'b' ? 'W/O' : '', 'detail' => ['walkover' => true]];
        }

        return $recipe->result() === ResultKind::Sets
            ? $this->sets($recipe->scoring(), $payload)
            : $this->score($recipe->scoring(), $payload, $drawAllowed);
    }

    /* ---------------- sets and games ---------------- */

    private function sets(array $rules, array $payload): array
    {
        $bestOf = max(1, (int) ($rules['best_of'] ?? 3));
        $need = intdiv($bestOf, 2) + 1;
        $sets = array_values(array_filter((array) ($payload['sets'] ?? []), fn ($s) => is_array($s) && count($s) === 2));
        $retired = in_array($payload['retired'] ?? null, ['a', 'b'], true) ? $payload['retired'] : null;

        if ($sets === [] && ! $retired) {
            $this->fail('sets', __('events::competition.result_sets_required'));
        }

        if (count($sets) > $bestOf) {
            $this->fail('sets', trans_choice('events::competition.result_too_many_sets', $bestOf, ['n' => $bestOf]));
        }

        $won = ['a' => 0, 'b' => 0];
        $games = ['a' => 0, 'b' => 0];
        $clean = [];

        foreach ($sets as $i => [$x, $y]) {
            $x = (int) $x; $y = (int) $y;

            if ($x < 0 || $y < 0 || $x > 99 || $y > 99) {
                $this->fail('sets', __('events::competition.result_set_invalid', ['n' => $i + 1]));
            }

            $final = $i === $bestOf - 1;
            $last = $i === count($sets) - 1;

            // A set left unfinished by a retirement is kept as played.
            if (! ($retired && $last) && ! $this->setIsComplete($rules, $x, $y, $final)) {
                $this->fail('sets', __('events::competition.result_set_invalid', ['n' => $i + 1]));
            }

            if (! ($retired && $last && ! $this->setIsComplete($rules, $x, $y, $final))) {
                $won[$x > $y ? 'a' : 'b']++;
            }

            $games['a'] += $x; $games['b'] += $y;
            $clean[] = [$x, $y];

            if (max($won) >= $need && ! $last) {
                $this->fail('sets', __('events::competition.result_after_decided'));
            }
        }

        if ($retired) {
            $winner = $retired === 'a' ? 'b' : 'a';
        } else {
            if (max($won) < $need) {
                $this->fail('sets', trans_choice('events::competition.result_not_decided', $need, ['n' => $need]));
            }
            $winner = $won['a'] > $won['b'] ? 'a' : 'b';
        }

        return [
            'winner' => $winner,
            'a_score' => (string) $won['a'],
            'b_score' => (string) $won['b'],
            'detail' => array_filter(['sets' => $clean, 'games' => [$games['a'], $games['b']], 'retired' => $retired]),
        ];
    }

    /** Does one set's score end the set, by this sport's rules? */
    private function setIsComplete(array $rules, int $x, int $y, bool $final): bool
    {
        $hi = max($x, $y);
        $lo = min($x, $y);

        if ($hi === $lo) {
            return false;
        }

        // Rally-point sports: table tennis, badminton, squash, volleyball.
        if (isset($rules['points_to'])) {
            $to = (int) ($final && isset($rules['final_set_to']) ? $rules['final_set_to'] : $rules['points_to']);
            $by = (int) ($rules['win_by'] ?? 2);
            $cap = isset($rules['cap']) ? (int) $rules['cap'] : null;

            if ($cap !== null && $hi === $cap) {
                return true;
            }

            return $hi >= $to && ($hi - $lo) >= $by && ($hi === $to || $hi - $lo === $by);
        }

        // Game-based sports: tennis, padel.
        $to = (int) ($rules['games_to'] ?? 6);

        if ($final && ($rules['final_set'] ?? null) === 'super_tiebreak') {
            $st = (int) ($rules['super_tiebreak_to'] ?? 10);

            // A match tie-break is written as its points (10-8)…
            if ($hi >= $st && ($hi - $lo) >= 2 && ($hi === $st || $hi - $lo === 2)) {
                return true;
            }
        }

        $tb = isset($rules['tiebreak_at']) ? (int) $rules['tiebreak_at'] : null;

        if ($tb !== null && $hi === $tb + 1 && $lo === $tb) {
            return true;   // 7-6
        }

        return ($hi === $to && $lo <= $to - 2) || ($hi === $to + 1 && $lo === $to - 1);   // 6-4, 7-5
    }

    /* ---------------- a score ---------------- */

    private function score(array $rules, array $payload, bool $drawAllowed): array
    {
        if (! is_numeric($payload['a'] ?? null) || ! is_numeric($payload['b'] ?? null)) {
            $this->fail('a', __('events::competition.result_score_required'));
        }

        $a = (float) $payload['a'];
        $b = (float) $payload['b'];

        if ($a < 0 || $b < 0 || $a > 999 || $b > 999) {
            $this->fail('a', __('events::competition.result_score_required'));
        }

        $retired = in_array($payload['retired'] ?? null, ['a', 'b'], true) ? $payload['retired'] : null;
        $detail = array_filter(['extra_time' => ! empty($payload['extra_time']) ? true : null, 'retired' => $retired]);

        if ($retired) {
            $winner = $retired === 'a' ? 'b' : 'a';
        } elseif ($a !== $b) {
            $winner = $a > $b ? 'a' : 'b';
        } elseif ($drawAllowed) {
            $winner = null;
        } else {
            // Level in a contest that must have a winner: the sport's decider.
            $pens = $payload['penalties'] ?? null;

            if (is_array($pens) && count($pens) === 2 && is_numeric($pens[0]) && is_numeric($pens[1]) && (int) $pens[0] !== (int) $pens[1]) {
                $detail['penalties'] = [(int) $pens[0], (int) $pens[1]];
                $winner = (int) $pens[0] > (int) $pens[1] ? 'a' : 'b';
            } elseif (in_array($payload['decided_by'] ?? null, ['a', 'b'], true)) {
                $detail['decided_by'] = $payload['decided_by'];   // a super over, a golden score…
                $winner = $payload['decided_by'];
            } else {
                $this->fail('penalties', __('events::competition.result_needs_decider'));
            }
        }

        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');

        return ['winner' => $winner, 'a_score' => $fmt($a), 'b_score' => $fmt($b), 'detail' => $detail];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
