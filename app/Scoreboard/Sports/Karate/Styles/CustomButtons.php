<?php

namespace App\Scoreboard\Sports\Karate\Styles;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Sports\Karate\Mat\MatState;

/**
 * Scoring buttons an organiser designs for a mat — any number of them, each
 * with its own name, points value and effect.
 *
 * Asked for on 2026-10-07 for the Kyokushin board ("the score control scoring
 * is completely customised"). Written once, here, so any Karate style engine
 * can take it with one `use` rather than a copy (CLAUDE.md → Shared Stays
 * Shared). A style engine that uses it adds `button`, `button_undo` and
 * `buttons` to its COMMANDS.
 *
 * ── A button ──────────────────────────────────────────────────────────────
 *   id      stable, server-made, what a press names
 *   label   what the official presses and the hall reads ("Waza-ari")
 *   value   points added to that corner's score, in halves (0.5 is a
 *           Kyokushin waza-ari); 0 is allowed (a button that only counts),
 *           negative is not — Correct takes points back
 *   effect  add       → adds the value
 *           win       → adds the value and the corner WINS ("Ippon")
 *           win_after → adds the value; the corner wins on the Nth press of
 *                       THIS button in the bout ("two waza-ari make ippon")
 *   times   N for win_after (2–9)
 *
 * Every rule is enforced HERE, never in a browser: the console only says
 * which button was pressed. A win parks the bout for the table to confirm,
 * exactly as the gap and the penalty ladder already do (autoEnd).
 */
trait CustomButtons
{
    /** Most buttons a corner may have — enough for any rule book, few enough to press. */
    public static int $maxButtons = 8;

    public const BUTTON_EFFECTS = ['add', 'win', 'win_after'];

    protected function styleCommand(ClubEvent $event, string $court, MatState $state, string $command, array $payload): void
    {
        match ($command) {
            'buttons' => $this->saveButtons($event, $state, $payload),
            'button' => $this->pressButton($state, $payload),
            'button_undo' => $this->undoButton($state, $payload),
            default => null,
        };
    }

    /**
     * Replace the mat's buttons, or go back to the style's own with
     * `buttons_default`. Written to the event with the rest of its rules.
     */
    private function saveButtons(ClubEvent $event, MatState $state, array $payload): void
    {
        if (! empty($payload['buttons_default'])) {
            $state->buttons = null;
        } else {
            $state->buttons = self::cleanButtons((array) ($payload['buttons'] ?? []));
        }

        $event->exists && $state->persistSettings($event);
    }

    /**
     * Never trusted as sent: trimmed, capped, whitelisted, given an id it can
     * be named by. An empty list is "no custom buttons" — the style's own.
     *
     * @return list<array{id: string, label: string, value: int|float, effect: string, times: int}>|null
     */
    public static function cleanButtons(array $raw): ?array
    {
        $out = [];
        $seen = [];

        foreach (array_slice(array_values($raw), 0, self::$maxButtons) as $i => $b) {
            if (! is_array($b)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($b['label'] ?? '')));
            if ($label === '') {
                continue;
            }
            $id = preg_match('/^[a-z0-9]{1,12}$/', (string) ($b['id'] ?? '')) ? (string) $b['id'] : '';
            if ($id === '' || isset($seen[$id])) {
                $id = 'b'.substr(md5($label.'|'.$i.'|'.microtime()), 0, 8);
            }
            $seen[$id] = true;
            $effect = in_array($b['effect'] ?? 'add', self::BUTTON_EFFECTS, true) ? $b['effect'] : 'add';

            $out[] = [
                'id' => $id,
                'label' => mb_substr($label, 0, 24),
                'value' => MatState::points(max(0, min(20, (float) ($b['value'] ?? 0)))),
                'effect' => $effect,
                'times' => max(2, min(9, (int) ($b['times'] ?? 2))),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * The style's own buttons, used while a mat has none of its own. Null is
     * "this style has no defaults" — the console keeps its built-in column.
     */
    protected function defaultButtons(): ?array
    {
        return null;
    }

    /** What the mat's buttons are right now: its own, else the style's. */
    protected function effectiveButtons(MatState $state): array
    {
        return $state->buttons ?? $this->defaultButtons() ?? [];
    }

    private function findButton(MatState $state, ?string $id): ?array
    {
        foreach ($this->effectiveButtons($state) as $b) {
            if (($b['id'] ?? null) === $id) {
                return $b;
            }
        }

        return null;
    }

    private function pressButton(MatState $state, array $payload): void
    {
        $side = $this->side($payload);
        $b = $this->findButton($state, $payload['button'] ?? null);

        // A press on a finished bout, or for a button this mat no longer has
        // (a second console with a stale list), changes nothing.
        if (! $side || ! $b || ! $state->hasBout() || $state->finished) {
            return;
        }

        $state->{$side.'Score'} = MatState::points($state->{$side.'Score'} + (float) $b['value']);

        $t = $state->tally[$side] ?? ['counts' => [], 'log' => []];
        $t['counts'][$b['id']] = ($t['counts'][$b['id']] ?? 0) + 1;
        $t['log'][] = $b['id'];
        $t['log'] = array_slice($t['log'], -60);
        $state->tally[$side] = $t;

        // The hall's callout: the button's own name, in the corner's colour.
        $state->lastEvent = ['side' => $side, 'n' => MatState::points($b['value']), 'label' => $b['label'], 'ts' => (int) (microtime(true) * 1000)];

        $wins = $b['effect'] === 'win'
            || ($b['effect'] === 'win_after' && $t['counts'][$b['id']] >= (int) $b['times']);

        if ($wins) {
            $this->autoEnd($state, [
                'winner' => $side,
                'reason' => 'other',
                'note' => $b['effect'] === 'win_after' ? $b['label'].' × '.$b['times'] : $b['label'],
            ]);

            return;
        }

        $this->checkGap($state);
    }

    /** Take back that corner's last press — its points and its count. */
    private function undoButton(MatState $state, array $payload): void
    {
        $side = $this->side($payload);
        $t = $side ? ($state->tally[$side] ?? null) : null;

        if (! $t || empty($t['log']) || ! $state->hasBout()) {
            return;
        }

        $id = array_pop($t['log']);
        $t['counts'][$id] = max(0, ($t['counts'][$id] ?? 1) - 1);
        $state->tally[$side] = $t;

        $b = $this->findButton($state, $id);
        $state->{$side.'Score'} = MatState::points(max(0, $state->{$side.'Score'} - (float) ($b['value'] ?? 0)));
        $state->lastEvent = null;
    }
}
