<?php

namespace App\Events\Sports\Taekwondo;

use App\Sports\Combat\AbstractCombatSport;

/**
 * Taekwondo (World Taekwondo) — the first combat-sport plug-in.
 * Wraps the single-source weight tables (config/taekwondo_divisions.php) and the
 * autoloaded classifier helper (classifyTaekwondo).
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

    public function weightDivisions(): array
    {
        return config('taekwondo_divisions', []);
    }

    public function classify(string $gender, int $age, float $weight): ?array
    {
        return classifyTaekwondo($gender, $age, $weight);
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
            ['key' => 'center_referee',   'label' => __('sport-taekwondo::messages.official_center_referee'), 'hint' => __('sport-taekwondo::messages.official_center_referee_hint')],
            ['key' => 'corner_judge_1',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 1]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint')],
            ['key' => 'corner_judge_2',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 2]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint')],
            ['key' => 'corner_judge_3',   'label' => __('sport-taekwondo::messages.official_corner_judge', ['n' => 3]), 'hint' => __('sport-taekwondo::messages.official_corner_judge_hint')],
            ['key' => 'review_jury',      'label' => __('sport-taekwondo::messages.official_review_jury'), 'hint' => __('sport-taekwondo::messages.official_review_jury_hint')],
            ['key' => 'court_supervisor', 'label' => __('sport-taekwondo::messages.official_court_supervisor'), 'hint' => __('sport-taekwondo::messages.official_court_supervisor_hint')],
            ['key' => 'table_recorder',   'label' => __('sport-taekwondo::messages.official_table_recorder'), 'hint' => __('sport-taekwondo::messages.official_table_recorder_hint')],
            ['key' => 'timekeeper',       'label' => __('sport-taekwondo::messages.official_timekeeper'), 'hint' => __('sport-taekwondo::messages.official_timekeeper_hint')],
        ];
    }
}
