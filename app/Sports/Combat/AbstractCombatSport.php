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

    /**
     * No name by default. A sport that has one overrides this; a sport that has
     * not says so with a null, and the reader gets the plain "+3" rather than a
     * term this platform made up.
     */
    public function scoreLabel(int $points): ?string
    {
        return null;
    }

    /**
     * The plain colours, for a sport with no vocabulary of its own. Every
     * combat sport has a red corner and a blue one even when it does not have
     * a word for them.
     *
     * @return array{red: string, blue: string}
     */
    public function cornerLabels(): array
    {
        return [
            'red' => __('events.corner_red'),
            'blue' => __('events.corner_blue'),
        ];
    }
}
