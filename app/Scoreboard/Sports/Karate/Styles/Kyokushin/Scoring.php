<?php

namespace App\Scoreboard\Sports\Karate\Styles\Kyokushin;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Scoreboard\Sports\Karate\Mat\Scoring as BaseScoring;
use App\Scoreboard\Sports\Karate\Styles\CustomButtons;
use App\Scoreboard\Sports\Karate\Styles\CustomPenalties;

/**
 * The Kyokushin scoring engine — knockdown karate, as the IKO runs it.
 *
 * Started 2026-10-07 as an exact copy of the shared Karate board and changed
 * here, and only here, to Kyokushin's rules (the owner chose the IKO the same
 * day). The plumbing — mat state, screens, the live channel, saving the result
 * — stays in the shared base.
 *
 * ── What differs from point karate ────────────────────────────────────────
 *  · Scoring is by knockdown: WAZA-ARI (down under three seconds) and IPPON
 *    (down longer, or unwilling to continue). Ippon ends the bout; a second
 *    waza-ari is awasete ippon and ends it too. These are the mat's default
 *    buttons, and an organiser may design their own (CustomButtons).
 *  · No point gap and no senshu: a bout is decided by knockdowns, then by the
 *    judges.
 *  · Penalties: Chui (warning), Genten (a penalty point), and Shikkaku
 *    (disqualification), which hands the bout to the other corner.
 *  · HANTEI: with no ippon at the bell, the referee and four judges show flags
 *    — aka, ao, or hikiwake (draw). A majority (three of five) wins; anything
 *    else is a draw, and the bout goes to
 *  · ENCHO: an extension on the same scores, two minutes by default, up to
 *    three — and after them, if still level,
 *  · a WEIGHT decision: the lighter fighter may be given the bout.
 *
 * Every rule is enforced here; the console only reports what the officials
 * did. Like the gap and the ladder in the base engine, an ending parks the
 * bout for the table to confirm rather than announcing it on its own.
 */
class Scoring extends BaseScoring
{
    use CustomButtons {
        styleCommand as buttonCommand;
    }
    use CustomPenalties;

    public const COMMANDS = [...BaseScoring::COMMANDS, 'buttons', 'button', 'button_undo', 'hantei', 'encho', 'weight', 'penalties'];

    /** Chui, two Genten, then Shikkaku — the top rung ends the bout. */
    public const PENALTIES = ['CHUI', 'GENTEN 1', 'GENTEN 2', 'SHIKKAKU'];

    /** Kyokushin's own ladder, as steps (CustomPenalties). */
    public const DEFAULT_PENALTIES = [
        ['id' => 'chui', 'label' => 'CHUI', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'genten1', 'label' => 'GENTEN 1', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'genten2', 'label' => 'GENTEN 2', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'shikkaku', 'label' => 'SHIKKAKU', 'effect' => 'none', 'value' => 0, 'ends' => true],
    ];

    /** The officials who show flags at a hantei: the referee and four judges. */
    public const FLAGS = 5;

    /** Extensions before the weight decision. */
    public const MAX_ENCHO = 3;

    /** An extension's length, in seconds. */
    public const ENCHO_SECONDS = 120;

    /** The mat's buttons until an organiser designs its own. */
    public const DEFAULT_BUTTONS = [
        // Waza-ari is a half point and Ippon a full one, as the IKO counts them.
        ['id' => 'wazaari', 'label' => 'Waza-ari', 'value' => 0.5, 'effect' => 'win_after', 'times' => 2],
        ['id' => 'ippon', 'label' => 'Ippon', 'value' => 1, 'effect' => 'win', 'times' => 2],
    ];

    protected function defaultButtons(): ?array
    {
        return self::DEFAULT_BUTTONS;
    }

    protected function penaltyLadder(): array
    {
        return self::PENALTIES;
    }

    /**
     * Kyokushin's ladder until an organiser designs the mat's own in Settings
     * → Penalties: Chui and two Genten are recorded and shown, Shikkaku
     * disqualifies. None of them moves the score by default — referees
     * weigh a Genten at hantei. An organiser who scores it (for example −0.5)
     * sets that per step.
     */
    protected function defaultPenalties(): array
    {
        return self::DEFAULT_PENALTIES;
    }

    protected function penaltyEndReason(): string
    {
        return 'shikkaku';
    }

    /** Kyokushin has no point gap: knockdowns and the judges decide. */
    protected function checkGap(MatState $state): void {}

    protected function styleCommand(ClubEvent $event, string $court, MatState $state, string $command, array $payload): void
    {
        match ($command) {
            'hantei' => $this->hantei($state, $payload),
            'encho' => $this->encho($state),
            'weight' => $this->weight($state, $payload),
            'penalties' => $this->savePenalties($event, $state, $payload),
            default => $this->buttonCommand($event, $court, $state, $command, $payload),
        };
    }

    /**
     * The flags. Counted, never trusted: no more than five officials, and a
     * corner needs a majority of all five to win. Anything short of that is a
     * draw — shown on every screen, waiting for ENCHO.
     */
    private function hantei(MatState $state, array $payload): void
    {
        if (! $state->hasBout()) {
            return;
        }

        $aka = max(0, (int) ($payload['flags_aka'] ?? 0));
        $ao = max(0, (int) ($payload['flags_ao'] ?? 0));
        $draw = max(0, (int) ($payload['flags_draw'] ?? 0));
        if ($aka + $ao + $draw > self::FLAGS || $aka + $ao + $draw === 0) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ky_hantei_count', ['n' => self::FLAGS]));
        }

        $majority = intdiv(self::FLAGS, 2) + 1;
        $winner = $aka >= $majority ? 'aka' : ($ao >= $majority ? 'ao' : null);

        $state->running = false;
        $state->tally['hantei'] = ['aka' => $aka, 'ao' => $ao, 'draw' => $draw, 'winner' => $winner, 'ts' => (int) (microtime(true) * 1000)];

        if ($winner) {
            $this->declareEnd($state, [
                'winner' => $winner,
                'reason' => 'other',
                'note' => 'Hantei '.max($aka, $ao).'–'.min($aka, $ao),
            ]);

            return;
        }

        // A draw: the bout is not over, it is waiting for an extension.
        $state->finished = true;
        $state->winner = null;
        $state->winReason = null;
        $state->winNote = null;
        $state->awaitingDecision = false;
        $state->celebrationClosed = true;
    }

    /**
     * Another round on the same scores. Waza-ari carry over; the decision,
     * the clock and any declared result do not.
     */
    private function encho(MatState $state): void
    {
        if (! $state->hasBout()) {
            return;
        }

        $n = (int) ($state->tally['encho'] ?? 0);
        if ($n >= self::MAX_ENCHO) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ky_encho_max', ['n' => self::MAX_ENCHO]));
        }

        $state->tally['encho'] = $n + 1;
        $state->tally['enchoSeconds'] = self::ENCHO_SECONDS;
        unset($state->tally['hantei']);

        $state->remaining = self::ENCHO_SECONDS;
        $state->running = false;
        $state->finished = false;
        $state->winner = null;
        $state->winReason = null;
        $state->winNote = null;
        $state->awaitingDecision = false;
        $state->celebrationClosed = false;
        $state->lastEvent = null;
        $state->mode = MatState::MODE_SCOREBOARD;
    }

    /** The lighter fighter is given the bout. Which one is the table's call. */
    private function weight(MatState $state, array $payload): void
    {
        $winner = in_array($payload['winner'] ?? null, ['aka', 'ao'], true) ? $payload['winner'] : null;
        if (! $winner || ! $state->hasBout()) {
            return;
        }

        $this->declareEnd($state, ['winner' => $winner, 'reason' => 'other', 'note' => 'Weight decision']);
    }
}
