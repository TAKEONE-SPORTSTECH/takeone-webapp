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

    /** The rulesets this sport weighs in under, and the table each reads. */
    public const RULESETS = ['gi' => 'bjj_divisions', 'nogi' => 'bjj_nogi_divisions'];

    /**
     * The weight table for a ruleset.
     *
     * Defaults to GI, which is what every existing caller meant when there was
     * only one table — so nothing that already asks for weightDivisions() has
     * to know a second one now exists.
     */
    /**
     * The IBJJF belt system — this sport's own, declared here and nowhere
     * else (see App\Sports\Combat\BeltLadder). Kids (4–15) wear the grey,
     * yellow, orange and green families, each in its white-striped, solid and
     * black-striped form; adults (16+) white to black; the coral and red belts
     * are the masters' 7th to 10th degree. Stripes 0–4 on every coloured belt,
     * degrees 1–6 on black.
     */
    public function beltLadder(): array
    {
        $stripes = \App\Sports\Combat\BeltLadder::numbered('sport-brazilianjiujitsu::messages.grade_stripes', range(0, 4));
        $degrees = \App\Sports\Combat\BeltLadder::numbered('sport-brazilianjiujitsu::messages.grade_degree', range(1, 6));
        $rank = fn (string $v, string $g, array $grades) => \App\Sports\Combat\BeltLadder::rank($v, $g, $grades);

        $kids = [$rank('white', 'kids', $stripes)];
        foreach (['grey', 'yellow', 'orange', 'green'] as $family) {
            foreach ([$family.'-white', $family, $family.'-black'] as $v) {
                $kids[] = $rank($v, 'kids', $stripes);
            }
        }

        return array_merge($kids, [
            $rank('blue', 'adult', $stripes),
            $rank('purple', 'adult', $stripes),
            $rank('brown', 'adult', $stripes),
            $rank('black', 'adult', $degrees),
            $rank('red-black', 'master', \App\Sports\Combat\BeltLadder::numbered('sport-brazilianjiujitsu::messages.grade_degree', [7])),
            $rank('red-white', 'master', \App\Sports\Combat\BeltLadder::numbered('sport-brazilianjiujitsu::messages.grade_degree', [8])),
            $rank('red', 'master', \App\Sports\Combat\BeltLadder::numbered('sport-brazilianjiujitsu::messages.grade_degree', [9, 10])),
        ]);
    }

    public function weightDivisions(string $ruleset = 'gi'): array
    {
        /* This sport's own tables, in its own folder (divisions/gi.php and
           divisions/nogi.php) — moved out of the shared events config on
           2026-09-27; the old config keys still answer for any reader that has
           not moved. The data files are the published limits, untouched; the
           official structure IBJJF adds on top of them is added here. */
        static $tables = [];

        $file = $ruleset === 'nogi' ? 'nogi' : 'gi';

        return $tables[$file] ??= self::withOfficialStructure(
            (static fn () => require __DIR__.'/divisions/'.$file.'.php')()
        );
    }

    /** The IBJJF masters bands: same weights, separated by age. */
    public const MASTER_BANDS = [
        'Master 1' => ['min' => 30, 'max' => 35],
        'Master 2' => ['min' => 36, 'max' => 40],
        'Master 3' => ['min' => 41, 'max' => 45],
        'Master 4' => ['min' => 46, 'max' => 50],
        'Master 5' => ['min' => 51, 'max' => 55],
        'Master 6' => ['min' => 56, 'max' => 60],
        'Master 7' => ['min' => 61, 'max' => null],
    ];

    /**
     * What IBJJF runs beyond the weight limits themselves (2026-09-27):
     *
     *   · the OPEN CLASS (absolute) — every weight, entered in addition to a
     *     weight class, for Juvenile, Adult and every Master band. Flagged
     *     `open` so classify() never places an athlete into it by weight, and
     *     the range fallback in Enrolment prefers the tighter weight class.
     *   · MASTER 1–7 — the masters split by age (30–35, 36–40 … 61+), each on
     *     the adult weights. Added AFTER the existing 'Master' band, which
     *     stays (30+, every master together), so every division already named
     *     after it, and every placement made against it, is unchanged.
     *
     * Kids are left as the table has them: IBJJF weighs kids per year of age,
     * and those limits are to be supplied from the federation's own sheet
     * rather than written from memory.
     */
    private static function withOfficialStructure(array $table): array
    {
        $open = ['label' => 'Open', 'name' => 'Open Class', 'min' => 0, 'max' => 200, 'open' => true];

        foreach (['Juvenile', 'Adult', 'Master'] as $band) {
            foreach (['male', 'female'] as $gender) {
                if (isset($table[$band][$gender]) && ! collect($table[$band][$gender])->contains('open', true)) {
                    $table[$band][$gender][] = $open;
                }
            }
        }

        if (isset($table['Master'])) {
            foreach (array_keys(self::MASTER_BANDS) as $band) {
                $table[$band] ??= $table['Master'];
            }
        }

        return $table;
    }

    /**
     * The age each IBJJF band covers, as DATA rather than as a comment.
     *
     * classify() has always known these — they were a `match` inside it — but
     * only well enough to answer "which band is this athlete in". Building a
     * division needs the other direction: "what ages does Juvenile mean", so a
     * preset can fill in min_age and max_age instead of an organiser typing 16
     * and 17 and hoping they match what registration will decide.
     *
     * One source, so the two can never disagree: classify() reads this too.
     *
     * `null` for an open end — Master has no upper age, and a bracket that
     * refused a 70-year-old would be a bug rather than a rule.
     *
     * ⚠️ Bands are federation property. These are IBJJF's; a national body
     * running its own kids ladder edits HERE, and both directions follow.
     *
     * @return array<string, array{min: int, max: int|null}>
     */
    public function defaultCategorySystem(): string
    {
        return 'ibjjf';
    }

    public function defaultCategoryLabel(): string
    {
        return 'IBJJF';
    }

    public function categoryAgeGroups(): array
    {
        return collect(self::ageBands())->map(fn ($r, $k) => ['key' => $k, 'min' => $r['min'], 'max' => $r['max']])->values()->all();
    }

    public static function ageBands(): array
    {
        return [
            'Kids' => ['min' => 4, 'max' => 15],
            'Juvenile' => ['min' => 16, 'max' => 17],
            'Adult' => ['min' => 18, 'max' => 29],
            // Master 1 opens at 30 and every band above it shares these
            // weights, which is why they share one entry.
            'Master' => ['min' => 30, 'max' => null],
        ] + self::MASTER_BANDS;   // AFTER 'Master', so classify() still answers 'Master'
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
     * @param  float  $weight  Weight in kg, as weighed under $ruleset
     * @param  string  $ruleset  'gi' (default) or 'nogi'
     * @return array{age_group: string, category: string, name: ?string, min: float, max: float}|null
     */
    public function classify(string $gender, int $age, float $weight, string $ruleset = 'gi'): ?array
    {
        $gender = strtolower($gender) === 'female' ? 'female' : 'male';

        // Read from the one table, so placing an athlete and building a
        // division can never disagree about what "Juvenile" means.
        $group = null;

        foreach (self::ageBands() as $band => $range) {
            if ($age >= $range['min'] && ($range['max'] === null || $age <= $range['max'])) {
                $group = $band;
                break;
            }
        }

        if ($group === null) {
            return null;
        }

        // The same athlete is a different weight class with and without a gi,
        // so the table is chosen before anybody is placed in it.
        $table = $this->weightDivisions($ruleset);

        foreach ($table[$group][$gender] ?? [] as $class) {
            // The open class takes every weight; nobody is PLACED in it by
            // the scale — it is a division an athlete enters on purpose.
            if ($class['open'] ?? false) {
                continue;
            }

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
     * ONE BRONZE, FOUGHT FOR — and fought BEFORE the final.
     *
     * The two beaten semi-finalists meet, and the winner takes third.
     *
     * ⚠️ This has now been set both ways in one day, so the history matters
     * more than usual. It was `third_place_match` on a reading of the IBJJF
     * rule book; it was changed to `both_sf_losers` on 2026-09-18 on the
     * grounds that the mat does not bear that reading out, which also had the
     * engine cutting a bout AFTER the final that nobody would ever call; and it
     * was set back the same day by the owner, who runs these championships and
     * wants the play-off. The complaint that drove the first change — a bout
     * sitting after the final as a pointless appendix — is answered separately
     * in `DrawEngine::build()`, which now cuts it BEFORE the final so it runs
     * while the finalists rest.
     *
     * The consequence is one extra bout per division of four or more, and a
     * podium that waits for it: `Advancement` fills third from this bout's
     * winner rather than awarding it to both losing semi-finalists.
     *
     * The other conventions the engine accepts are listed in config/combat.php.
     * Karate and Taekwondo reach two bronzes through repechage, which is a
     * different route to a different podium and stays theirs.
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
            ['key' => 'referee',       'label' => __('sport-brazilianjiujitsu::messages.official_referee'),       'hint' => __('sport-brazilianjiujitsu::messages.official_referee_hint'), 'section' => 'referees'],
            ['key' => 'mat_organiser', 'label' => __('sport-brazilianjiujitsu::messages.official_mat_organiser'), 'hint' => __('sport-brazilianjiujitsu::messages.official_mat_organiser_hint'), 'section' => 'technical'],
            ['key' => 'scorekeeper',   'label' => __('sport-brazilianjiujitsu::messages.official_scorekeeper'),   'hint' => __('sport-brazilianjiujitsu::messages.official_scorekeeper_hint'), 'section' => 'table'],
            ['key' => 'timekeeper',    'label' => __('sport-brazilianjiujitsu::messages.official_timekeeper'),    'hint' => __('sport-brazilianjiujitsu::messages.official_timekeeper_hint'), 'section' => 'table'],
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
     * RED and BLUE, like every other combat sport here.
     *
     * ── This used to be BLUE and WHITE ─────────────────────────────────────
     * The competitors wear a blue gi and a white one, so the corners were
     * painted to match and the 'red' key carried the WHITE corner. Changed
     * 2026-09-20 at the owner's request: a white corner cannot be told from
     * the board's own light furniture at ten metres, and every other sport in
     * the hall fights red against blue. Side A is red, side B is blue.
     *
     * ⚠️ `corner_blue` is now the RED corner's label and `corner_white` the
     * BLUE one — the KEYS are the side, not the colour. They are what
     * `bjj_mat_states.blue` / `.white` and 1,100 recorded ledger rows are
     * keyed by, so renaming them is a migration rather than a lick of paint;
     * registered in Documentation/HOUSE-CLEANING.md.
     *
     * ⚠️ Red now means two things on this sport's screens — a corner, and a
     * penalty. The corner plate is a deep crimson (#c1121f) and the alarm is
     * the brighter #ff3b47, which is what keeps them apart. Anything new that
     * reaches for red must pick a side of that line deliberately.
     *
     * @return array{red: string, blue: string}
     */
    public function cornerLabels(): array
    {
        return [
            'red' => __('sport-brazilianjiujitsu::messages.corner_blue'),
            'blue' => __('sport-brazilianjiujitsu::messages.corner_white'),
        ];
    }

    /** The red corner stands on the RIGHT of every screen in this sport. */
    public function redCornerSide(): string
    {
        return 'right';
    }
}
