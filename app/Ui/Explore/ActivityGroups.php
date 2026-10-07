<?php

namespace App\Ui\Explore;

/**
 * Which shelf an activity sits on in Explore → Activities, and its colour.
 *
 * Presentation only: the catalogue has no group column yet (NEW-UI.md, phase 8
 * moves this onto the record). Unknown names land in "outdoor & more" rather
 * than disappearing.
 */
class ActivityGroups
{
    /** key => [label translation key, colour] — in display order */
    public const GROUPS = [
        'combat' => ['ui::explore.g_combat', '#e11d48'],
        'fitness' => ['ui::explore.g_fitness', '#7c3aed'],
        'mind' => ['ui::explore.g_mind', '#db2777'],
        'racket' => ['ui::explore.g_racket', '#0d9488'],
        'team' => ['ui::explore.g_team', '#16a34a'],
        'water' => ['ui::explore.g_water', '#0284c7'],
        'outdoor' => ['ui::explore.g_outdoor', '#ca8a04'],
    ];

    private const WORDS = [
        'combat' => ['taekwondo', 'karate', 'jiu', 'judo', 'boxing', 'kickbox', 'muay', 'mixed martial', 'mma', 'wrestl', 'fencing', 'kung fu', 'aikido', 'capoeira', 'grappl', 'sambo'],
        'fitness' => ['fitness', 'crossfit', 'weight', 'bodybuild', 'functional', 'spinning', 'aerobic', 'zumba', 'strength', 'gym'],
        'mind' => ['yoga', 'pilates', 'ballet', 'dance', 'chess', 'meditat'],
        'racket' => ['padel', 'tennis', 'squash', 'badminton', 'pickle'],
        'team' => ['football', 'soccer', 'basket', 'volley', 'handball', 'cricket', 'rugby', 'hockey', 'futsal'],
        'water' => ['swim', 'water polo', 'diving', 'rowing', 'surf', 'aqua'],
    ];

    /** Colours for the sports the platform runs most, so a club's chip and its activity match. */
    private const COLOURS = [
        'taekwondo' => '#e11d48', 'karate' => '#d97706', 'jiu' => '#2563eb', 'football' => '#16a34a',
        'padel' => '#0d9488', 'swim' => '#0284c7', 'boxing' => '#ea580c', 'fitness' => '#7c3aed', 'yoga' => '#db2777',
    ];

    public static function groupFor(string $name): string
    {
        $n = mb_strtolower($name);
        foreach (self::WORDS as $group => $words) {
            foreach ($words as $w) {
                if (str_contains($n, $w)) {
                    return $group;
                }
            }
        }

        return 'outdoor';
    }

    public static function colourFor(string $name): string
    {
        $n = mb_strtolower($name);
        foreach (self::COLOURS as $w => $c) {
            if (str_contains($n, $w)) {
                return $c;
            }
        }

        return self::GROUPS[self::groupFor($name)][1];
    }
}
