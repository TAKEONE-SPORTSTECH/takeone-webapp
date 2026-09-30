<?php

namespace App\Events\Support\Competition;

/**
 * WHAT one contest is.
 *
 *   head_to_head  A against B — combat, tennis, padel, football, chess
 *   field         many at once, ranked — running, swimming, cycling
 *   judged        a performance scored by judges — gymnastics, kata, diving
 *   measured      attempts measured — long jump, weightlifting, archery
 *
 * Part of the competition profile (see CompetitionProfile). Values are stable
 * keys — stored, sent to the browser and read by the MCP — never translated.
 */
enum Contest: string
{
    case HeadToHead = 'head_to_head';
    case Field = 'field';
    case Judged = 'judged';
    case Measured = 'measured';
}
