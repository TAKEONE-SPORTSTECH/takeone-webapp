<?php

namespace App\Sports\Combat;

/**
 * A combat sport plug-in for the Combat-Championship engine.
 *
 * The engine (draw / scheduling / results) is sport-agnostic. Everything that
 * differs between sports — the weight tables, how a competitor is classified,
 * the bronze-medal convention — lives behind this interface. Add a sport by
 * creating app/Sports/<Name>/ and registering it in config/combat.php.
 */
interface CombatSport
{
    /** Stable key, e.g. "taekwondo". Matches ClubEvent->sport. */
    public function key(): string;

    /** Human label, e.g. "Taekwondo". */
    public function label(): string;

    /**
     * Weight divisions table: age-group => gender => [ ['label','min','max'], ... ].
     *
     * @return array<string, array<string, array<int, array{label:string,min:float,max:float}>>>
     */
    public function weightDivisions(): array;

    /**
     * Classify a competitor into an age-group + weight class.
     *
     * @return array{age_group:string, category:string, min:float, max:float}|null
     */
    public function classify(string $gender, int $age, float $weight): ?array;

    /** Canonical division name, e.g. "Senior Men -58 kg". */
    public function divisionName(string $ageGroup, string $gender, string $label): string;

    /** Bronze-medal convention: 'repechage' | 'both_sf_losers' | 'third_place_match'. */
    public function bronzeRule(): string;
}
