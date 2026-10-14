<?php

namespace App\Events\Support;

use App\Events\Models\EventCategory;

/**
 * Finding a division by the name a sport gives it — including the names it
 * gave before.
 *
 * Divisions are matched to an athlete by NAME ("Cadet Kumite Male -52 kg").
 * On 2026-10-13 the words changed — "Male"/"Female", never "Men"/"Women", and
 * Karate gained "Kumite" — so a division named before then ("Cadet Men -52 kg")
 * must still be found, or its entrants stop landing in it. Today's name is
 * tried first; the older wordings only when it is not there.
 */
final class DivisionNames
{
    /** @return list<string> today's name first, then the older wordings */
    public static function candidates(object $sport, string $ageGroup, string $gender, string $label, string $suffix = ''): array
    {
        $female = strtolower($gender) === 'female';
        $names = [
            $sport->divisionName($ageGroup, $gender, $label),
            $ageGroup.' '.($female ? 'Female' : 'Male').' '.$label.' kg',
            $ageGroup.' '.($female ? 'Women' : 'Men').' '.$label.' kg',
        ];

        return array_values(array_unique(array_map(fn ($n) => mb_substr($n.$suffix, 0, 190), $names)));
    }

    /** The event's division under the first of these names that exists. */
    public static function find(int $eventId, array $names): ?EventCategory
    {
        $found = EventCategory::where('event_id', $eventId)->whereIn('name', $names)->get()->keyBy('name');
        foreach ($names as $n) {
            if ($found->has($n)) {
                return $found->get($n);
            }
        }

        return null;
    }
}
