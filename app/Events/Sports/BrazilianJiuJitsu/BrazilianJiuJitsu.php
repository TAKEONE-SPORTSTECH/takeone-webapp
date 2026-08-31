<?php

namespace App\Events\Sports\BrazilianJiuJitsu;

use App\Sports\Combat\AbstractCombatSport;

/**
 * Brazilian Jiu-Jitsu (IBJJF) — a combat-sport plug-in.
 *
 * Wraps the single-source weight tables (config/bjj_divisions.php) and the
 * classification rules. Nothing outside app/Events/Sports/BrazilianJiuJitsu/
 * needs to know this sport exists; the engine reaches it through the registry.
 */
class BrazilianJiuJitsu extends AbstractCombatSport
{
    /**
     * The belt ladder, coarsest to finest.
     *
     * Not on the CombatSport contract — no other sport is graded like this —
     * so it is offered as the sport's own extra and read only by this sport's
     * packages. A BJJ division is belt × age × weight, and the belt is the part
     * an organiser picks first.
     */
    public const BELTS = ['white', 'blue', 'purple', 'brown', 'black'];

    public function key(): string
    {
        return 'bjj';
    }

    public function label(): string
    {
        return __('sport-brazilianjiujitsu::messages.sport_label');
    }

    public function weightDivisions(): array
    {
        return config('bjj_divisions', []);
    }

    /**
     * Place a competitor by IBJJF age band and gi weight class.
     *
     * Deliberately NOT a global helper function the way Taekwondo's and
     * Karate's are. Those predate the sport plug-in and are autoloaded from
     * composer.json's `files` list — which means adding one is an edit to a
     * shared manifest and a `composer dump-autoload` on every box. A method on
     * the plug-in is reachable through exactly the same registry the engine
     * already uses, and it leaves this sport deletable by removing one folder.
     *
     * @param  string  $gender  "male" or "female"
     * @param  int  $age  Age in years
     * @param  float  $weight  Weight in kg, with the gi
     * @return array{age_group: string, category: string, name: ?string, min: float, max: float}|null
     */
    public function classify(string $gender, int $age, float $weight): ?array
    {
        $gender = strtolower($gender) === 'female' ? 'female' : 'male';

        $group = match (true) {
            $age >= 4 && $age <= 15 => 'Kids',
            $age >= 16 && $age <= 17 => 'Juvenile',
            $age >= 18 && $age <= 29 => 'Adult',
            // Master 1 opens at 30 and the bands above it share these weights,
            // so age alone cannot pick between them: an event that runs a
            // specific Master band selects that division explicitly.
            $age >= 30 => 'Master',
            default => null,   // younger than 4
        };

        if ($group === null) {
            return null;
        }

        foreach (config('bjj_divisions.'.$group.'.'.$gender, []) as $class) {
            if ($weight >= $class['min'] && $weight <= $class['max']) {
                return [
                    'age_group' => $group,
                    'category' => $class['label'],
                    'name' => $class['name'] ?? null,
                    'min' => $class['min'],
                    'max' => $class['max'],
                ];
            }
        }

        return null;
    }

    /**
     * IBJJF runs a third-place MATCH rather than repechage: the two losing
     * semi-finalists meet, and the winner takes bronze. Verify against your
     * federation's current rules before a real competition — the other
     * conventions the engine accepts are listed in config/combat.php.
     */
    public function bronzeRule(): string
    {
        return 'third_place_match';
    }

    /**
     * The IBJJF mat officiating positions, in match-sheet order.
     *
     * A jiu-jitsu match is run by ONE referee on the mat, with the table
     * keeping the score and the clock — there is no corner-judge panel the way
     * karate and taekwondo have one, so this list is deliberately short rather
     * than padded to match the neighbours.
     *
     * Keys are stable and never translated: they are what gets stored against
     * a match.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function officialRoles(): array
    {
        return [
            ['key' => 'referee',       'label' => __('sport-brazilianjiujitsu::messages.official_referee'),       'hint' => __('sport-brazilianjiujitsu::messages.official_referee_hint')],
            ['key' => 'mat_organiser', 'label' => __('sport-brazilianjiujitsu::messages.official_mat_organiser'), 'hint' => __('sport-brazilianjiujitsu::messages.official_mat_organiser_hint')],
            ['key' => 'scorekeeper',   'label' => __('sport-brazilianjiujitsu::messages.official_scorekeeper'),   'hint' => __('sport-brazilianjiujitsu::messages.official_scorekeeper_hint')],
            ['key' => 'timekeeper',    'label' => __('sport-brazilianjiujitsu::messages.official_timekeeper'),    'hint' => __('sport-brazilianjiujitsu::messages.official_timekeeper_hint')],
        ];
    }

    /**
     * What a score of this size is CALLED in jiu-jitsu.
     *
     * Unlike karate, the number does not name the action on its own: two points
     * is a takedown, a sweep OR knee-on-belly, and only the referee's signal
     * says which. So this returns the AMBIGUOUS-SAFE word — the value's own
     * position in the table — and the officiating log carries the specific
     * source alongside it (Scoring::POINT_SOURCES). Never invent a term to
     * fill the gap; a highlights bar that guesses "sweep" for a takedown is
     * worse than one that says "2 points".
     */
    public function scoreLabel(int $points): ?string
    {
        return match ($points) {
            4 => __('sport-brazilianjiujitsu::messages.score_four'),
            3 => __('sport-brazilianjiujitsu::messages.score_three'),
            2 => __('sport-brazilianjiujitsu::messages.score_two'),
            default => null,
        };
    }

    /**
     * BLUE and WHITE — never red.
     *
     * The contract's array is keyed red/blue because every other combat sport
     * has a red corner. Jiu-jitsu does not: the competitors wear a blue gi and
     * a white one, and red is reserved for penalties and disqualification on
     * every screen this sport draws. So the 'red' key carries the WHITE corner,
     * and everything in this package speaks blue/white rather than reading the
     * key's name as a colour.
     *
     * @return array{red: string, blue: string}
     */
    public function cornerLabels(): array
    {
        return [
            'red' => __('sport-brazilianjiujitsu::messages.corner_white'),
            'blue' => __('sport-brazilianjiujitsu::messages.corner_blue'),
        ];
    }
}
