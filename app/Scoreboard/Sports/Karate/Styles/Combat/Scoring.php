<?php

namespace App\Scoreboard\Sports\Karate\Styles\Combat;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Scoreboard\Sports\Karate\Mat\Scoring as BaseScoring;
use App\Scoreboard\Sports\Karate\Styles\CustomButtons;
use App\Scoreboard\Sports\Karate\Styles\CustomPenalties;

/**
 * The Combat scoring engine — its own rules for a bout.
 *
 * Started 2026-10-16 as an exact copy of the Shotokan style (the owner: "copy
 * the exact Shotokan score control and scoreboard and VS screen and everything
 * but name it Combat … a new folder and a new scoring system … don't disturb
 * the current Shotokan"). Rules that differ for Combat are overridden HERE and
 * nowhere else — never in Shotokan's engine, which stays exactly as it is; the
 * plumbing (mat state, screens, the live channel, saving the result) stays in
 * the shared base.
 *
 * The same day the owner asked for "Points" and "Fouls" in the match settings
 * — how many scoring buttons each corner has and what a press does, and the
 * foul ladder likewise, the same for both corners. That is the shared
 * Styles\CustomButtons and Styles\CustomPenalties (Kyokushin's too), taken
 * here with COMBAT's defaults, which are exactly what the mat ran before:
 * Yuko +1, Waza-ari +2, Ippon +3, and C1 · C2 · C3 · HC · H with H ending the
 * bout. Everything else — the point gap, senshu, hansoku — stays Shotokan's.
 *
 * Its screens are its own copies in resources/views/karate/styles/combat.
 * Chosen by App\Scoreboard\Sports\Karate\Styles\KarateStyles for an event whose
 * rule book is `combat`.
 */
class Scoring extends BaseScoring
{
    use CustomButtons {
        styleCommand as buttonCommand;
    }
    use CustomPenalties;

    public const COMMANDS = [...BaseScoring::COMMANDS, 'buttons', 'button', 'button_undo', 'penalties'];

    /** Combat's own points — the buttons the mat had before any were designed. */
    public const DEFAULT_BUTTONS = [
        ['id' => 'yuko', 'label' => 'Yuko', 'value' => 1, 'effect' => 'add', 'times' => 2],
        ['id' => 'wazaari', 'label' => 'Waza-ari', 'value' => 2, 'effect' => 'add', 'times' => 2],
        ['id' => 'ippon', 'label' => 'Ippon', 'value' => 3, 'effect' => 'add', 'times' => 2],
    ];

    /** Combat's own fouls — the shared ladder, its top step ending the bout. */
    public const DEFAULT_PENALTIES = [
        ['id' => 'c1', 'label' => 'C1', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'c2', 'label' => 'C2', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'c3', 'label' => 'C3', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'hc', 'label' => 'HC', 'effect' => 'none', 'value' => 0, 'ends' => false],
        ['id' => 'h', 'label' => 'H', 'effect' => 'none', 'value' => 0, 'ends' => true],
    ];

    protected function defaultButtons(): ?array
    {
        return self::DEFAULT_BUTTONS;
    }

    protected function defaultPenalties(): array
    {
        return self::DEFAULT_PENALTIES;
    }

    protected function styleCommand(ClubEvent $event, string $court, MatState $state, string $command, array $payload): void
    {
        match ($command) {
            'penalties' => $this->savePenalties($event, $state, $payload),
            default => $this->buttonCommand($event, $court, $state, $command, $payload),
        };
    }
}
