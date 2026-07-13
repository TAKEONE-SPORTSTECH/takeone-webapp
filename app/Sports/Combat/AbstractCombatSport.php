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

    public function bronzeRule(): string
    {
        return 'repechage';
    }
}
