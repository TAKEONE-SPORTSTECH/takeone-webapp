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
            ['key' => 'referee',    'label' => __('events::events.official_referee')],
            ['key' => 'judge',      'label' => __('events::events.official_judge')],
            ['key' => 'timekeeper', 'label' => __('events::events.official_timekeeper')],
            ['key' => 'recorder',   'label' => __('events::events.official_recorder')],
        ];
    }

    /**
     * This sport's own belts and grades (App\Sports\Combat\BeltLadder).
     * Empty means "none declared", and every screen falls back to the
     * platform's generic ladder.
     */
    public function beltLadder(): array
    {
        return [];
    }

    public function bronzeRule(): string
    {
        return 'repechage';
    }

    // ─── Category and belt systems (2026-09-28) ─────────────────────────
    //
    // A sport runs under more than one rule book. Its DEFAULT system is its
    // own table and its own classifier, untouched — so an event that never
    // chose a system is classified exactly as it always was. A sport adds
    // others by overriding categorySystems() (Karate adds Shotokan);
    // organisers add their own (App\Events\Support\CategorySystems).

    /** The key of this sport's own rule book, e.g. 'wkf'. */
    public function defaultCategorySystem(): string
    {
        return 'standard';
    }

    /** Its name, as an organiser picks it. */
    public function defaultCategoryLabel(): string
    {
        return $this->label();
    }

    /**
     * The age groups of the default table, for an editor that starts a custom
     * system from it. The classifier does not read these — it keeps its own.
     *
     * @return array<int, array{key: string, min: int, max: ?int}>
     */
    public function categoryAgeGroups(): array
    {
        return [];
    }

    /** @return array<string, CategorySystem> */
    public function categorySystems(): array
    {
        $default = new CategorySystem(
            key: $this->defaultCategorySystem(),
            label: $this->defaultCategoryLabel(),
            ageGroups: $this->categoryAgeGroups(),
            classifier: fn (string $g, int $a, float $w, ?string $ruleset = null) => $ruleset !== null ? $this->classify($g, $a, $w, $ruleset) : $this->classify($g, $a, $w),
            table: fn (?string $ruleset = null) => $ruleset !== null ? $this->weightDivisions($ruleset) : $this->weightDivisions(),
        );

        return [$default->key => $default];
    }

    /** @return array<string, BeltSystem> */
    public function beltSystems(): array
    {
        $ladder = $this->beltLadder();

        return $ladder === [] ? [] : [$this->defaultCategorySystem() => new BeltSystem($this->defaultCategorySystem(), $this->defaultCategoryLabel(), $ladder)];
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
            'red' => __('events::events.corner_red'),
            'blue' => __('events::events.corner_blue'),
        ];
    }

    /**
     * Which side of every screen the red corner stands on: 'left' or 'right'.
     *
     * A convention of the sport, not of a screen — the up-next queue, the
     * introduction, the scoreboard and the scoring table all read it, so an
     * athlete is on the same side of the hall wherever they are looked for.
     * Read through App\Sports\Combat\CornerSide, never compared inline.
     */
    public function redCornerSide(): string
    {
        return 'left';
    }
}
