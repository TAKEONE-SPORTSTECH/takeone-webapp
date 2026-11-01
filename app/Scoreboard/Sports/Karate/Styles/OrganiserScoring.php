<?php

namespace App\Scoreboard\Sports\Karate\Styles;

use App\Events\Models\ClubEvent;
use App\Scoreboard\Sports\Karate\Mat\MatState;

/**
 * An organiser's own scoring on a Karate style that has none built in —
 * Settings → Points (CustomButtons) and Settings → Fouls (CustomPenalties), the
 * two tabs Combat and Kyokushin had, for every other style too (2026-11-01).
 *
 * Written once here so Shotokan, Goju-ryu, Shito-ryu, Wado-ryu and the default
 * board take it with one `use` (CLAUDE.md → Shared Stays Shared). A style that
 * uses it adds 'buttons', 'button', 'button_undo' and 'penalties' to COMMANDS.
 *
 * ⚠️ Nothing changes until an organiser designs something:
 *  · no style buttons of its own (defaultButtons() is null), so the console
 *    keeps its built-in Yuko / Waza-ari / Ippon, scored through the engine's
 *    own `point` — senshu included;
 *  · the foul ladder is the engine's own `penalty()` until the mat HAS a
 *    ladder of its own; only then does the custom ladder take over.
 */
trait OrganiserScoring
{
    use CustomButtons {
        styleCommand as buttonCommand;
    }
    use CustomPenalties {
        penalty as customPenalty;
    }

    protected function styleCommand(ClubEvent $event, string $court, MatState $state, string $command, array $payload): void
    {
        match ($command) {
            'penalties' => $this->savePenalties($event, $state, $payload),
            default => $this->buttonCommand($event, $court, $state, $command, $payload),
        };
    }

    /** The style's own ladder, exactly as before, until the mat has one of its own. */
    protected function penalty(MatState $state, array $payload): void
    {
        if ($state->penalties === null) {
            parent::penalty($state, $payload);

            return;
        }

        $this->customPenalty($state, $payload);
    }
}
