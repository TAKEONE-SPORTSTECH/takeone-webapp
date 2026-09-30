<?php

namespace App\Sports\Package;

use App\Sports\Combat\BeltSystem;
use App\Sports\Combat\CategorySystem;
use App\Sports\Combat\CombatSport;

/**
 * One version of one style's rule book — what a sport package hands to anyone
 * who needs to know how that style is run (Documentation: the Sport Package
 * Plan, phase 2).
 *
 * PHASE 2 IS A WRAPPER, NOT A REWRITE. Version 1 of every style is today's
 * code, reached through the exact objects that already run the platform:
 * `$combat` is the CombatSport the enrolment desk classifies with (the plain
 * plug-in, or the BoundSport an event's chosen system produces), and the
 * category/belt systems are the ones CategorySystems already resolves. So a
 * rule book can never classify an athlete differently from the live path —
 * it IS the live path, with a name on it. Scoring, screens and dated editions
 * come in later phases.
 *
 * A club's or federation's own system is a CUSTOM edition: stored against
 * its host, derived from an official one, and never overwriting it — other
 * clubs keep the original.
 */
final class RuleBook
{
    /**
     * @param  array<int, string>  $kinds  The competition kinds this rule book classifies for
     *                                      (BJJ: gi, nogi). One kind, `standard`, for a sport
     *                                      that has no such split yet.
     */
    public function __construct(
        public readonly string $sport,
        public readonly string $style,
        public readonly string $styleLabel,
        public readonly string $edition,
        public readonly bool $official,
        public readonly CombatSport $combat,
        public readonly CategorySystem $categories,
        public readonly ?BeltSystem $belts,
        public readonly array $kinds,
        public readonly ?string $note = null,
    ) {}

    /**
     * Gender + age + weight → division, exactly as the enrolment desk does it.
     *
     * @return array{age_group: string, category: string}|null
     */
    public function classify(string $gender, int $age, float $weight, ?string $kind = null): ?array
    {
        $kind = $kind !== null && $kind !== 'standard' && in_array($kind, $this->kinds, true) ? $kind : null;

        return $kind !== null
            ? $this->combat->classify($gender, $age, $weight, $kind)
            : $this->combat->classify($gender, $age, $weight);
    }

    /** Does this rule book have ranks (a belt or grade ladder)? */
    public function hasRanks(): bool
    {
        return (bool) ($this->belts?->ladder);
    }

    /** What screens may ask about, instead of asking which sport it is. */
    public function capabilities(): array
    {
        return array_values(array_filter([
            $this->hasRanks() ? 'ranks' : null,
            $this->hasRanks() ? 'grading' : null,
            'weigh_in',
            'brackets',
        ]));
    }

    /** The division's name, the sport's own way ("Senior Men -67 kg"). */
    public function divisionName(string $ageGroup, string $gender, string $label): string
    {
        return $this->combat->divisionName($ageGroup, $gender, $label);
    }

    /** The weight table for one kind, as the rule book publishes it. */
    public function weightTable(?string $kind = null): array
    {
        $kind = $kind !== null && $kind !== 'standard' && in_array($kind, $this->kinds, true) ? $kind : null;

        return $this->categories->table($kind);
    }

    /** What an integration or a settings page reads — no closures, no internals. */
    public function toArray(bool $withTables = false): array
    {
        $out = [
            'sport' => $this->sport,
            'style' => $this->style,
            'style_label' => $this->styleLabel,
            'edition' => $this->edition,
            'official' => $this->official,
            'kinds' => $this->kinds,
            'age_groups' => array_values($this->categories->ageGroups),
            'belts' => $this->belts?->ladder ? array_map(fn ($b) => ['value' => $b['value'] ?? null, 'label' => $b['label'] ?? null], $this->belts->ladder) : [],
            'capabilities' => $this->capabilities(),
            'medal_rule' => $this->combat->bronzeRule(),
            'officials' => $this->combat->officialRoles(),
            'corners' => $this->combat->cornerLabels(),
            'red_corner_side' => \App\Sports\Combat\CornerSide::red($this->sport),
            'note' => $this->note,
        ];
        if ($withTables) {
            foreach ($this->kinds as $k) {
                $out['weight_tables'][$k] = $this->weightTable($k);
            }
        }

        return $out;
    }
}
