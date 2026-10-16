<?php

namespace App\Events\Sports\Karate;

use App\Sports\Combat\AbstractCombatSport;

/**
 * Karate (World Karate Federation) — a combat-sport plug-in.
 * Wraps the single-source weight tables (config/karate_divisions.php) and the
 * package's own Classifier (formerly the global classifyKarate helper).
 */
class Karate extends AbstractCombatSport
{
    public function key(): string
    {
        return 'karate';
    }

    /** "[Age Division] Kumite [Gender] [Weight Class]" — "Cadet Kumite Male -52 kg" (decided 2026-10-13). */
    public function divisionName(string $ageGroup, string $gender, string $label): string
    {
        return $ageGroup.' Kumite '.$this->genderWord($gender).' '.$label.' kg';
    }

    public function label(): string
    {
        return __('sport-karate::messages.sport_label');
    }

    /**
     * Kyu and dan — the WKF grading system, declared here and nowhere else
     * (see App\Sports\Combat\BeltLadder). Kyu belt colours differ between
     * styles and federations; this is the widely used sequence, white at
     * 10th kyu to brown at 1st kyu, then black from 1st to 10th dan.
     */
    public function beltLadder(): array
    {
        $kyu = fn (array $n) => \App\Sports\Combat\BeltLadder::numbered('sport-karate::messages.grade_kyu', $n);
        $rank = fn (string $v, string $g, array $grades) => \App\Sports\Combat\BeltLadder::rank($v, $g, $grades);

        return [
            $rank('white', 'kyu', $kyu([10, 9])),
            $rank('yellow', 'kyu', $kyu([8])),
            $rank('orange', 'kyu', $kyu([7])),
            $rank('green', 'kyu', $kyu([6])),
            $rank('blue', 'kyu', $kyu([5])),
            $rank('purple', 'kyu', $kyu([4])),
            $rank('brown', 'kyu', $kyu([3, 2, 1])),
            $rank('black', 'dan', \App\Sports\Combat\BeltLadder::numbered('sport-karate::messages.grade_dan', range(1, 10))),
        ];
    }

    /**
     * The grading of each Karate STYLE the platform's activity list names, as
     * its main world association grades it. Each is a belt system an event may
     * choose (CategorySystems → `club_events.belt_system`), and choosing one is
     * what names the event's style ("Kyokushin Karate").
     *
     * Kyu colours are NOT standard even within one style — a national body or
     * a dojo often swaps a colour — so each ladder follows one named source:
     *
     *  - Kyokushin — IKO (Kyokushinkaikan), the owner's choice 2026-10-07.
     *  - Shotokan  — JKA.
     *  - Goju-ryu  — IOGKF.
     *  - Shito-ryu — Shitokai.
     *  - Wado-ryu  — Wado-kai.
     *
     * A colour worn at two or three kyu carries them all ("9th kyu" on an
     * orange belt). The stripe or tab that marks the higher of them is not
     * drawn: what it looks like varies by branch, and the grade already says
     * it. Grading only — none of these styles has one weight table every
     * organisation shares, so an organiser builds their own with the category
     * editor (Shotokan's season table is the one exception, see categorySystems()).
     *
     * @return array<string, array<int, array>>  style key => BeltLadder rows
     */
    public function styleLadders(): array
    {
        $kyu = fn (array $n) => \App\Sports\Combat\BeltLadder::numbered('sport-karate::messages.grade_kyu', $n);
        $rank = fn (string $v, array $grades, string $group = 'kyu') => \App\Sports\Combat\BeltLadder::rank($v, $group, $grades);
        $black = $rank('black', \App\Sports\Combat\BeltLadder::numbered('sport-karate::messages.grade_dan', range(1, 10)), 'dan');
        $beginner = fn (string $key) => [__('sport-karate::messages.'.$key)];

        return [
            'kyokushin' => [
                $rank('white', $beginner('grade_mukyu')),
                $rank('orange', $kyu([10, 9])),
                $rank('blue', $kyu([8, 7])),
                $rank('yellow', $kyu([6, 5])),
                $rank('green', $kyu([4, 3])),
                $rank('brown', $kyu([2, 1])),
                $black,
            ],
            'shotokan' => [
                $rank('white', $kyu([10])),
                $rank('orange', $kyu([9])),
                $rank('red', $kyu([8])),
                $rank('yellow', $kyu([7])),
                $rank('green', $kyu([6])),
                $rank('purple', $kyu([5, 4])),
                $rank('brown', $kyu([3, 2, 1])),
                $black,
            ],
            'goju_ryu' => [
                $rank('white', array_merge($beginner('grade_ungraded'), $kyu([10]))),
                $rank('blue', $kyu([9, 8])),
                $rank('yellow', $kyu([7, 6])),
                $rank('green', $kyu([5, 4])),
                $rank('brown', $kyu([3, 2, 1])),
                $black,
            ],
            'shito_ryu' => [
                $rank('white', $beginner('grade_ungraded')),
                $rank('red', $kyu([9])),
                $rank('yellow', $kyu([8])),
                $rank('orange', $kyu([7])),
                $rank('green', $kyu([6])),
                $rank('blue', $kyu([5])),
                $rank('purple', $kyu([4])),
                $rank('brown', $kyu([3, 2, 1])),
                $black,
            ],
            'wado_ryu' => [
                $rank('white', $beginner('grade_ungraded')),
                $rank('yellow', $kyu([8])),
                $rank('orange', $kyu([7])),
                $rank('green', $kyu([6])),
                $rank('blue', $kyu([5])),
                $rank('purple', $kyu([4])),
                $rank('brown', $kyu([3, 2, 1])),
                $black,
            ],
        ];
    }

    /** The WKF ladder (the sport's default), then each style's beside it. */
    public function beltSystems(): array
    {
        $systems = parent::beltSystems();

        foreach ($this->styleLadders() as $key => $ladder) {
            $systems[$key] = new \App\Sports\Combat\BeltSystem($key, __('sport-karate::messages.style_'.$key), $ladder);
        }

        return $systems;
    }

    public function weightDivisions(): array
    {
        // This sport's own table, in its own folder (divisions.php) — moved out
        // of the shared events config on 2026-09-27; the old config key still
        // answers for any reader that has not moved.
        static $table = null;

        return $table ??= (static fn () => require __DIR__.'/divisions.php')();
    }

    public function classify(string $gender, int $age, float $weight): ?array
    {
        return Classifier::classify($gender, $age, $weight);
    }

    public function defaultCategorySystem(): string
    {
        return 'wkf';
    }

    public function defaultCategoryLabel(): string
    {
        return 'WKF';
    }

    /** The bands classifyKarate() applies (app/Events/Helpers/karate_weightclass.php). */
    public function categoryAgeGroups(): array
    {
        return [
            ['key' => 'Kids', 'min' => 6, 'max' => 11],
            ['key' => 'Cadet', 'min' => 12, 'max' => 15],
            ['key' => 'Junior', 'min' => 16, 'max' => 17],
            ['key' => 'Senior', 'min' => 18, 'max' => null],
        ];
    }

    /**
     * WKF (the sport's own table), and Shotokan — the season table the
     * federation already publishes (public/libraries/karate-shotukan-weightclasses),
     * read from that one file so the two can never disagree.
     */
    public function categorySystems(): array
    {
        $systems = parent::categorySystems();

        $shotokan = \App\Support\KarateShotukanWeightclasses::categorySystem();
        if ($shotokan) {
            $systems[$shotokan->key] = $shotokan;
        }

        return $systems;
    }

    /**
     * WKF awards two bronzes through repechage: the athletes beaten by each
     * finalist fight back up their side of the draw, and the two repechage
     * winners take bronze. Same rule as World Taekwondo, arrived at separately —
     * verify against your federation's current rules before a real competition,
     * since a national body may run a third-place match instead (the other
     * conventions the engine accepts are listed in config/combat.php).
     */
    public function bronzeRule(): string
    {
        return 'repechage';
    }

    /**
     * The WKF kumite officiating panel, in match-sheet order: the Shushin
     * (Referee) in the tatami, four Fukushin (Judges) at the corners, the Kansa
     * (Match Supervisor) at the table, then the tatami and table officials.
     *
     * Karate names its officials in Japanese and the federation titles are what
     * appear on a bout sheet, so the label carries both — "Referee (Shushin)" —
     * rather than forcing a choice between the two.
     *
     * Keys are stable and never translated: they are what gets stored against a
     * bout and sent to the video platform.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function officialRoles(): array
    {
        return [
            ['key' => 'referee',          'label' => __('sport-karate::messages.official_referee'),          'hint' => __('sport-karate::messages.official_referee_hint')],
            ['key' => 'judge_1',          'label' => __('sport-karate::messages.official_judge', ['n' => 1]), 'hint' => __('sport-karate::messages.official_judge_hint')],
            ['key' => 'judge_2',          'label' => __('sport-karate::messages.official_judge', ['n' => 2]), 'hint' => __('sport-karate::messages.official_judge_hint')],
            ['key' => 'judge_3',          'label' => __('sport-karate::messages.official_judge', ['n' => 3]), 'hint' => __('sport-karate::messages.official_judge_hint')],
            ['key' => 'judge_4',          'label' => __('sport-karate::messages.official_judge', ['n' => 4]), 'hint' => __('sport-karate::messages.official_judge_hint')],
            ['key' => 'match_supervisor', 'label' => __('sport-karate::messages.official_match_supervisor'), 'hint' => __('sport-karate::messages.official_match_supervisor_hint')],
            ['key' => 'tatami_manager',   'label' => __('sport-karate::messages.official_tatami_manager'), 'hint' => __('sport-karate::messages.official_tatami_manager_hint')],
            ['key' => 'scorekeeper',      'label' => __('sport-karate::messages.official_scorekeeper'), 'hint' => __('sport-karate::messages.official_scorekeeper_hint')],
            ['key' => 'timekeeper',       'label' => __('sport-karate::messages.official_timekeeper'), 'hint' => __('sport-karate::messages.official_timekeeper_hint')],
        ];
    }

    /**
     * WKF kumite scoring, by value: three points is Ippon, two Waza-ari, one
     * Yuko. These are the words a karate crowd uses and the words on the bout
     * sheet, so a highlights bar should say them too.
     */
    public function scoreLabel(int $points): ?string
    {
        return match ($points) {
            3 => __('sport-karate::messages.score_ippon'),
            2 => __('sport-karate::messages.score_wazari'),
            1 => __('sport-karate::messages.score_yuko'),
            default => null,
        };
    }

    /** AKA is the red corner, AO the blue — the WKF's own words. */
    public function cornerLabels(): array
    {
        return [
            'red' => __('sport-karate::messages.corner_aka'),
            'blue' => __('sport-karate::messages.corner_ao'),
        ];
    }

    /** The red corner stands on the RIGHT of every screen in this sport. */
    public function redCornerSide(): string
    {
        return 'right';
    }

    /** A karateka enters Kata, Kumite, or both (the registration form's tick boxes). */
    public function entryDisciplines(): array
    {
        return [['key' => 'kata'], ['key' => 'kumite']];
    }
}
