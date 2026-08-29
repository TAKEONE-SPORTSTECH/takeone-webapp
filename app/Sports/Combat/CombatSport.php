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

    /**
     * The officiating positions this sport's matches are run by, in panel order.
     *
     * Each entry is ['key' => string, 'label' => string]: a stable machine key
     * and a translated human label. A karate kumite bout is run by a Shushin and
     * four Fukushin; a taekwondo kyorugi bout by a Center Referee and corner
     * judges — so the vocabulary belongs to the SPORT, not to any one screen
     * that happens to display it.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function officialRoles(): array;

    /**
     * What a score of this size is CALLED in this sport.
     *
     * A highlights bar that reads "Point +3" tells a parent nothing; "Ippon"
     * tells a karateka everything. The vocabulary belongs to the sport rather
     * than to any screen, because the same three points mean Ippon on a karate
     * mat and a head kick on a taekwondo one, and both a bout video, a court
     * display and a printed sheet want the same word for it.
     *
     * Returns null when the sport has no name for that value, in which case the
     * caller shows the plain count. Never invent a term to fill the gap.
     */
    public function scoreLabel(int $points): ?string;

    /**
     * The two corners, as this sport names them: ['red' => …, 'blue' => …].
     *
     * Karate fights AKA and AO, taekwondo HONG and CHUNG, and a boxing ring
     * simply has a red and a blue corner. The colour is the constant; the word
     * is the sport's.
     *
     * @return array{red: string, blue: string}
     */
    public function cornerLabels(): array;
}
