<?php

namespace App\Events\Support\Competition;

/**
 * WHO takes part in one contest: one person, a pair, or a team.
 *
 * Part of the competition profile (see CompetitionProfile). Values are stable
 * keys — stored, sent to the browser and read by the MCP — never translated.
 */
enum Entrant: string
{
    case Individual = 'individual';
    case Pair = 'pair';
    case Team = 'team';
}
