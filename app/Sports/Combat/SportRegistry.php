<?php

namespace App\Sports\Combat;

/** Resolves combat-sport plug-ins from config/combat.php. */
class SportRegistry
{
    /** @return array<string, CombatSport> keyed by sport key */
    public function all(): array
    {
        return collect(config('combat.sports', []))
            ->mapWithKeys(fn ($class, $key) => [$key => app($class)])
            ->all();
    }

    public function get(?string $key): ?CombatSport
    {
        $class = $key ? config('combat.sports.' . $key) : null;

        return $class ? app($class) : null;
    }

    public function has(?string $key): bool
    {
        return $this->get($key) !== null;
    }
}
