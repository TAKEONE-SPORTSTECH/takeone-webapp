<?php

namespace App\Sports\Taekwondo;

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
        return 'Taekwondo';
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
}
