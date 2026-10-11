<?php

namespace App\Scoreboard\Sports\Karate\Styles;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Sports\Karate\Mat\MatState;

/**
 * A penalty ladder an organiser designs for a mat — any number of steps, each
 * with its own name, score effect and whether it ends the bout.
 *
 * Asked for on 2026-10-07 for the Kyokushin board, beside the scoring buttons
 * (CustomButtons). Written once, here, so any Karate style engine can take it
 * with one `use`. A style engine that uses it adds `penalties` to its
 * COMMANDS and routes that command here (savePenalties).
 *
 * ── A step ────────────────────────────────────────────────────────────────
 *   id      stable, server-made
 *   label   what the official presses and the hall reads ("Chui", "Genten")
 *   effect  none    → recorded and shown, no change to the score
 *           deduct  → takes `value` off THIS corner's score
 *           award   → gives `value` to the OTHER corner
 *   value   points, in halves (0.5 is a waza-ari)
 *   ends    reaching this step disqualifies the corner: the other one wins
 *
 * The ladder is climbed and descended one step at a time, as it always was —
 * pressing a cell gives every step up to it, pressing the top one again takes
 * it back off — and every step's effect is applied on the way up and undone on
 * the way down, so a penalty given by mistake leaves no trace on the score.
 */
trait CustomPenalties
{
    /** Most steps a ladder may have. */
    public static int $maxPenalties = 8;

    public const PENALTY_EFFECTS = ['none', 'deduct', 'award'];

    /** The style's own ladder, used while a mat has none of its own. */
    protected function defaultPenalties(): array
    {
        $labels = MatState::PENALTIES;
        $last = count($labels) - 1;

        return array_map(fn ($l, $i) => [
            'id' => 'p'.$i, 'label' => $l, 'effect' => 'none', 'value' => 0, 'ends' => $i === $last,
        ], $labels, array_keys($labels));
    }

    protected function effectivePenalties(MatState $state): array
    {
        return $state->penalties ?? $this->defaultPenalties();
    }

    /** Replace the mat's ladder, or go back to the style's own with `penalties_default`. */
    private function savePenalties(ClubEvent $event, MatState $state, array $payload): void
    {
        $state->penalties = ! empty($payload['penalties_default'])
            ? null
            : self::cleanPenalties((array) ($payload['penalties'] ?? []));

        // A shorter ladder cannot leave a corner standing above its top step.
        $top = count($this->effectivePenalties($state));
        $state->akaPen = min($state->akaPen, $top);
        $state->aoPen = min($state->aoPen, $top);

        $event->exists && $state->persistSettings($event);
    }

    /**
     * Never trusted as sent: trimmed, capped, whitelisted, given an id.
     *
     * @return list<array{id: string, label: string, effect: string, value: int|float, ends: bool}>|null
     */
    public static function cleanPenalties(array $raw): ?array
    {
        $out = [];
        $seen = [];

        foreach (array_slice(array_values($raw), 0, self::$maxPenalties) as $i => $p) {
            if (! is_array($p)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($p['label'] ?? '')));
            if ($label === '') {
                continue;
            }
            $id = preg_match('/^[a-z0-9]{1,12}$/', (string) ($p['id'] ?? '')) ? (string) $p['id'] : '';
            if ($id === '' || isset($seen[$id])) {
                $id = 'p'.substr(md5($label.'|'.$i.'|'.microtime()), 0, 8);
            }
            $seen[$id] = true;
            $effect = in_array($p['effect'] ?? 'none', self::PENALTY_EFFECTS, true) ? $p['effect'] : 'none';

            $out[] = [
                'id' => $id,
                'label' => mb_substr($label, 0, 16),
                'effect' => $effect,
                'value' => $effect === 'none' ? 0 : MatState::points(max(0, min(20, (float) ($p['value'] ?? 0)))),
                'ends' => ! empty($p['ends']),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * Up or down the mat's own ladder, applying or undoing each step's effect.
     * Same two ways in as the shared engine: `level` (the cell pressed) or
     * `dir` (one step).
     */
    protected function penalty(MatState $state, array $payload): void
    {
        $side = $this->side($payload);
        if (! $side) {
            return;
        }

        $list = $this->effectivePenalties($state);
        $top = count($list);
        $field = $side.'Pen';
        $before = min($state->$field, $top);

        $after = array_key_exists('level', $payload)
            ? max(0, min($top, (int) $payload['level']))
            : max(0, min($top, $before + (((int) ($payload['dir'] ?? 1)) >= 0 ? 1 : -1)));

        if ($after === $before) {
            $state->lastEvent = null;

            return;
        }

        $other = $side === 'aka' ? 'ao' : 'aka';
        $up = $after > $before;
        foreach (range(min($before, $after), max($before, $after) - 1) as $i) {
            $step = $list[$i];
            $v = (float) ($step['value'] ?? 0) * ($up ? 1 : -1);
            if ($step['effect'] === 'deduct') {
                $state->{$side.'Score'} = MatState::points($state->{$side.'Score'} - $v);
            } elseif ($step['effect'] === 'award') {
                $state->{$other.'Score'} = MatState::points($state->{$other.'Score'} + $v);
            }
        }
        $state->$field = $after;

        // Heard going up, silent coming down — as the shared ladder does.
        $state->lastEvent = $up
            ? ['side' => $side, 'n' => 0, 'penalty' => true, 'label' => $list[$after - 1]['label'], 'ts' => (int) (microtime(true) * 1000)]
            : null;

        if (! $up || $state->finished || ! $state->hasBout() || ! $state->winByPenalties) {
            return;
        }

        // A step that disqualifies, reached on this press, hands the bout over.
        foreach (range($before, $after - 1) as $i) {
            if (! empty($list[$i]['ends'])) {
                $this->autoEnd($state, ['winner' => $other, 'reason' => $this->penaltyEndReason()]);

                return;
            }
        }
    }
}
