<?php

namespace App\Events\Sports\Karate;

use App\Sports\Combat\AbstractCombatSport;

/**
 * Karate (World Karate Federation) — a combat-sport plug-in.
 * Wraps the single-source weight tables (config/karate_divisions.php) and the
 * autoloaded classifier helper (classifyKarate).
 */
class Karate extends AbstractCombatSport
{
    public function key(): string
    {
        return 'karate';
    }

    public function label(): string
    {
        return __('sport-karate::messages.sport_label');
    }

    public function weightDivisions(): array
    {
        return config('karate_divisions', []);
    }

    public function classify(string $gender, int $age, float $weight): ?array
    {
        return classifyKarate($gender, $age, $weight);
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
}
