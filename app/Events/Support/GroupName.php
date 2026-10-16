<?php

namespace App\Events\Support;

/**
 * The name a division gets when nobody typed one (owner, 2026-10-16): its
 * group letter, then its own ages and weights — "Group A : 5-6y, 17-23kg".
 *
 * Read from the division's OWN columns, never from a sport's table, and never
 * an age word ("Kids") or a gender: the organiser's earlier rule. A typed name
 * is never touched — isGenerated() is what tells the two apart, and it is the
 * same test the two division editors make in the browser
 * (div-manage.js dmLooksGenerated, <x-event-divisions> looksGenerated).
 *
 *   both        Group A : 5-6y, 17-23kg
 *   one side    Group D : 16y+, +70kg   ·   Group E : ≤12y, -60kg
 *   equal ends  Group Q : 14-15y, 77.6kg
 *   neither     Group G
 */
final class GroupName
{
    /** "Group A" alone, or followed by " : " and its range. */
    public const PATTERN = '/^Group [A-Z]( : .+)?$/u';

    public static function make(string $letter, $minAge, $maxAge, $minWeight, $maxWeight): string
    {
        $range = self::range($minAge, $maxAge, $minWeight, $maxWeight);

        return 'Group '.$letter.($range !== '' ? ' : '.$range : '');
    }

    /** "5-6y, 17-23kg" — '' when the division narrows neither. */
    public static function range($minAge, $maxAge, $minWeight, $maxWeight): string
    {
        $n = fn ($v) => ($v === null || $v === '') ? null : (float) $v;
        $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $a1 = $n($minAge);
        $a2 = $n($maxAge);
        $w1 = $n($minWeight);
        $w2 = $n($maxWeight);
        $w1 = $w1 !== null && $w1 > 0 ? $w1 : null;
        $w2 = $w2 !== null && $w2 > 0 && $w2 < 200 ? $w2 : null;   // 200 is the tables' open top

        $bits = [];
        if ($a1 !== null || $a2 !== null) {
            $bits[] = match (true) {
                $a1 !== null && $a2 !== null => ($a1 === $a2 ? $num($a1) : $num($a1).'-'.$num($a2)).'y',
                $a1 !== null => $num($a1).'y+',
                default => '≤'.$num($a2).'y',
            };
        }
        if ($w1 !== null || $w2 !== null) {
            $bits[] = match (true) {
                $w1 !== null && $w2 !== null => ($w1 === $w2 ? $num($w1) : $num($w1).'-'.$num($w2)).'kg',
                $w1 !== null => '+'.$num($w1).'kg',
                default => '-'.$num($w2).'kg',
            };
        }

        return implode(', ', $bits);
    }

    public static function isGenerated(?string $name): bool
    {
        return (bool) preg_match(self::PATTERN, trim((string) $name));
    }
}
