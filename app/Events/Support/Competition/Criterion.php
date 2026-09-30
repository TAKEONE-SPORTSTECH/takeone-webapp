<?php

namespace App\Events\Support\Competition;

/**
 * WHAT groups competitors fairly into divisions. A sport names the ones it
 * uses; a screen asks only those questions (the division sheet stops asking
 * for weight and belts where the sport has neither).
 *
 * Part of the competition profile (see CompetitionProfile). Values are stable
 * keys — stored, sent to the browser and read by the MCP — never translated.
 */
enum Criterion: string
{
    case Age = 'age';
    case Gender = 'gender';
    case Weight = 'weight';
    case Rank = 'rank';
    case Level = 'level';
    case Rating = 'rating';
}
