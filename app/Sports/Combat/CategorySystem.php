<?php

namespace App\Sports\Combat;

/**
 * One rule book's age groups × weight classes — WKF, Shotokan, IBJJF, or an
 * organiser's own (2026-09-28).
 *
 * The same shape every sport's table already had — [ageGroup => [gender =>
 * [['label','min','max'], …]]] — plus the age groups themselves, which used to
 * be hard-coded inside each sport's classifier. A sport's DEFAULT system keeps
 * its own classifier (`$classifier`), so an event that never chose a system
 * is classified exactly as it always was.
 */
final class CategorySystem
{
    /**
     * @param  array<int, array{key: string, label?: string, min: int, max: ?int}>  $ageGroups
     * @param  array<string, array<string, array<int, array>>>  $classes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $ageGroups = [],
        public readonly array $classes = [],
        public readonly bool $custom = false,
        private readonly ?\Closure $classifier = null,
        private readonly ?\Closure $table = null,
        public readonly ?string $note = null,
    ) {}

    /** The weight table, in the shape every division generator reads. */
    public function table(?string $ruleset = null): array
    {
        return $this->table ? ($this->table)($ruleset) : $this->classes;
    }

    /** Where a person of this gender, age and weight belongs, or null. */
    public function classify(string $gender, int $age, float $weight, ?string $ruleset = null): ?array
    {
        if ($this->classifier) {
            return ($this->classifier)($gender, $age, $weight, $ruleset);
        }

        $group = $this->ageGroupFor($age);
        if ($group === null) {
            return null;
        }
        foreach ($this->table($ruleset)[$group][strtolower($gender)] ?? [] as $class) {
            if ($class['open'] ?? false) {
                continue;
            }
            if ($weight >= (float) $class['min'] && $weight <= (float) $class['max']) {
                return [
                    'age_group' => $group,
                    'category' => (string) $class['label'],
                    'name' => $class['name'] ?? null,
                    'min' => $class['min'],
                    'max' => $class['max'],
                ];
            }
        }

        return null;
    }

    public function ageGroupFor(int $age): ?string
    {
        foreach ($this->ageGroups as $g) {
            if ($age >= (int) $g['min'] && ($g['max'] === null || $age <= (int) $g['max'])) {
                return (string) $g['key'];
            }
        }

        return null;
    }

    /** What a form or an editor needs to draw it. */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'custom' => $this->custom,
            'note' => $this->note,
            'age_groups' => $this->ageGroups,
            'classes' => $this->table(),
        ];
    }
}
