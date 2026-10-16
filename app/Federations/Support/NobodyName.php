<?php

namespace App\Federations\Support;

/**
 * Is this "name" really a way of saying NOBODY?
 *
 * A club filling in its entry is asked for a team manager and two referees,
 * and a club with no referee writes "Nil", "None", "N/A", "-" or "لا يوجد" in
 * the box rather than leaving it empty. Taken literally, that made two people
 * called "Nil" officials of the Premier League (2026-10-15). Every reader of
 * an entry's named officials asks this first, and a yes means the slot is
 * empty.
 */
final class NobodyName
{
    private const WORDS = [
        'nil', 'nill', 'null', 'none', 'no', 'no one', 'nobody', 'nothing', 'n/a', 'na', 'n.a', 'n.a.',
        'tbd', 'tba', 'x', '0', '-', '--', '---', '.', '..', '...', '/', '?',
        'لا يوجد', 'لايوجد', 'لا', 'لا أحد', 'لا احد', 'غير موجود',
    ];

    public static function is(?string $name): bool
    {
        $n = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));

        return $n === '' || in_array($n, self::WORDS, true);
    }
}
