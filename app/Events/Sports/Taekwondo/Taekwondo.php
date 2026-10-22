<?php

namespace App\Events\Sports\Taekwondo;

use App\Sports\Combat\AbstractCombatSport;

/**
 * Taekwondo (World Taekwondo) — the first combat-sport plug-in.
 * Wraps the single-source weight tables (config/taekwondo_divisions.php) and the
 * package's own Classifier (formerly the global classifyTaekwondo helper).
 */
class Taekwondo extends AbstractCombatSport
{
    public function key(): string
    {
        return 'taekwondo';
    }

    public function label(): string
    {
        return __('sport-taekwondo::messages.sport_label');
    }

    /**
     * Geup, poom and dan — the World Taekwondo / Kukkiwon system, declared
     * here and nowhere else (see App\Sports\Combat\BeltLadder). Coloured
     * belts from 10th geup (white) to 1st geup (red), the red-and-black poom
     * belt for black-belt holders under 15 (1st–4th poom), and black from 1st
     * to 9th dan. Tipped belts are drawn with their stripe colour.
     */
    public function beltLadder(): array
    {
        $geup = fn (array $n) => \App\Sports\Combat\BeltLadder::numbered('sport-taekwondo::messages.grade_geup', $n);
        $rank = fn (string $v, string $g, array $grades) => \App\Sports\Combat\BeltLadder::rank($v, $g, $grades);

        return [
            $rank('white', 'geup', $geup([10])),
            $rank('white-yellow', 'geup', $geup([9])),
            $rank('yellow', 'geup', $geup([8])),
            $rank('yellow-green', 'geup', $geup([7])),
            $rank('green', 'geup', $geup([6])),
            $rank('green-blue', 'geup', $geup([5])),
            $rank('blue', 'geup', $geup([4])),
            $rank('blue-red', 'geup', $geup([3])),
            $rank('red', 'geup', $geup([2, 1])),
            $rank('red-black', 'poom', \App\Sports\Combat\BeltLadder::numbered('sport-taekwondo::messages.grade_poom', range(1, 4))),
            $rank('black', 'dan', \App\Sports\Combat\BeltLadder::numbered('sport-taekwondo::messages.grade_dan', range(1, 9))),
        ];
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
        return 'wt';
    }

    public function defaultCategoryLabel(): string
    {
        return 'World Taekwondo';
    }

    /** The bands classifyTaekwondo() applies (app/Events/Helpers/taekwondo_weightclass.php). */
    public function categoryAgeGroups(): array
    {
        return [
            ['key' => 'Kids', 'min' => 6, 'max' => 11],
            ['key' => 'Cadet', 'min' => 12, 'max' => 14],
            ['key' => 'Junior', 'min' => 15, 'max' => 17],
            ['key' => 'Senior', 'min' => 18, 'max' => 30],
            ['key' => 'Master', 'min' => 31, 'max' => null],
        ];
    }

    /** WT awards two bronzes via repechage. */
    public function bronzeRule(): string
    {
        return 'repechage';
    }

    /**
     * The WT kyorugi officiating panel, in the order it is listed on a match
     * sheet: one Center Referee in the ring, three corner Judges, then the
     * table and court officials.
     *
     * Keys are stable and never translated — they are what gets stored against a
     * bout and sent to the video platform. Only the labels are localised.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function officialRoles(): array
    {
        return [
            ['key' => 'center_referee',   'label' => __('sport-taekwondo::messages.official_center_referee'), 'hint' => __('sport-taekwondo::messages.official_center_referee_hint'), 'section' => 'referees'],
            ['key' => 'corner_judge_1',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 1]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint'), 'section' => 'referees'],
            ['key' => 'corner_judge_2',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 2]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint'), 'section' => 'referees'],
            ['key' => 'corner_judge_3',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 3]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint'), 'section' => 'referees'],
            ['key' => 'review_jury',      'label' => __('sport-taekwondo::messages.official_review_jury'), 'hint' => __('sport-taekwondo::messages.official_review_jury_hint'), 'section' => 'technical'],
            ['key' => 'court_supervisor', 'label' => __('sport-taekwondo::messages.official_court_supervisor'), 'hint' => __('sport-taekwondo::messages.official_court_supervisor_hint'), 'section' => 'technical'],
            ['key' => 'table_recorder',   'label' => __('sport-taekwondo::messages.official_table_recorder'), 'hint' => __('sport-taekwondo::messages.official_table_recorder_hint'), 'section' => 'table'],
            ['key' => 'timekeeper',       'label' => __('sport-taekwondo::messages.official_timekeeper'), 'hint' => __('sport-taekwondo::messages.official_timekeeper_hint'), 'section' => 'table'],
        ];
    }

    /**
     * WT kyorugi scoring, by value: five for a turning head kick, four for a
     * turning body kick, three to the head, two to the body, one for a punch.
     * The technique is what the value MEANS, which is why the bar can name it
     * from the number alone.
     */
    public function scoreLabel(int $points): ?string
    {
        return match ($points) {
            5 => __('sport-taekwondo::messages.score_turning_head'),
            4 => __('sport-taekwondo::messages.score_turning_body'),
            3 => __('sport-taekwondo::messages.score_head_kick'),
            2 => __('sport-taekwondo::messages.score_body_kick'),
            1 => __('sport-taekwondo::messages.score_punch'),
            default => null,
        };
    }

    /** HONG is the red corner, CHUNG the blue. */
    public function cornerLabels(): array
    {
        return [
            'red' => __('sport-taekwondo::messages.corner_hong'),
            'blue' => __('sport-taekwondo::messages.corner_chung'),
        ];
    }
}
