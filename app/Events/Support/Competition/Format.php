<?php

namespace App\Events\Support\Competition;

/**
 * HOW contests chain into a competition. `knockout` and `league` are built;
 * the rest are the vocabulary the shared engines grow into, so a profile can
 * already say what its sport runs.
 *
 * Part of the competition profile (see CompetitionProfile). Values are stable
 * keys — stored, sent to the browser and read by the MCP — never translated.
 */
enum Format: string
{
    case Knockout = 'knockout';
    case RoundRobin = 'round_robin';
    case GroupsKnockout = 'groups_knockout';
    case League = 'league';
    case Swiss = 'swiss';
    case Heats = 'heats';
    case Open = 'open';
}
