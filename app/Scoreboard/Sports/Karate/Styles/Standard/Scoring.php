<?php

namespace App\Scoreboard\Sports\Karate\Styles\Standard;

use App\Scoreboard\Sports\Karate\Mat\Scoring as BaseScoring;

/**
 * The default Karate board's engine — the shared one, plus Settings → Points
 * and Fouls (Styles\OrganiserScoring), so the board with no style of its own
 * has the same Settings as every style. Unchanged until somebody designs
 * buttons or a foul ladder. Picked by the Karate console when an event names
 * no style (ScoreboardController::scoringFor).
 */
class Scoring extends BaseScoring
{
    use \App\Scoreboard\Sports\Karate\Styles\OrganiserScoring;

    public const COMMANDS = [...BaseScoring::COMMANDS, 'buttons', 'button', 'button_undo', 'penalties'];
}
