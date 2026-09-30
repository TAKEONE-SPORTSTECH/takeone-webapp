<?php

namespace App\Events\Support\Competition;

/**
 * WHAT a contest's result is recorded as — a score (points, goals), sets and
 * games, a time, a distance or weight, judges' marks, or only a finishing
 * place (typed by the organiser).
 *
 * Part of the competition profile (see CompetitionProfile). Values are stable
 * keys — stored, sent to the browser and read by the MCP — never translated.
 */
enum ResultKind: string
{
    case Score = 'score';
    case Sets = 'sets';
    case Time = 'time';
    case Distance = 'distance';
    case Marks = 'marks';
    case Place = 'place';
}
