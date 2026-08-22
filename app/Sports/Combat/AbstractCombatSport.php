<?php

namespace App\Sports\Combat;

/** Shared helpers for combat-sport plug-ins (naming conventions). */
abstract class AbstractCombatSport implements CombatSport
{
    /** "male" => "Men", "female" => "Women". */
    public function genderWord(string $gender): string
    {
        return strtolower($gender) === 'female' ? 'Women' : 'Men';
    }

    public function divisionName(string $ageGroup, string $gender, string $label): string
    {
        return $ageGroup.' '.$this->genderWord($gender).' '.$label.' kg';
    }

    /**
     * A neutral panel, so a sport that has not declared its own officiating
     * vocabulary still offers something sensible rather than nothing.
     * Sports override this with their real federation titles.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function officialRoles(): array
    {
        return [
            ['key' => 'referee',    'label' => __('events.official_referee')],
            ['key' => 'judge',      'label' => __('events.official_judge')],
            ['key' => 'timekeeper', 'label' => __('events.official_timekeeper')],
            ['key' => 'recorder',   'label' => __('events.official_recorder')],
        ];
    }

    public function bronzeRule(): string
    {
        return 'repechage';
    }
}
