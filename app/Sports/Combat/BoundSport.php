<?php

namespace App\Sports\Combat;

/**
 * A sport, as ONE EVENT runs it (2026-09-28): the same plug-in, answering
 * weight tables, classification and belts from the category and belt system
 * that event chose. Everything else — naming, officials, bronze rule, scores —
 * is the sport's own, delegated untouched.
 *
 * Handed out only when an event chose something other than the sport's
 * default (App\Events\Support\CategorySystems::sportFor), so an event that
 * never chose is served by the plain plug-in, exactly as before.
 */
final class BoundSport implements CombatSport
{
    public function __construct(
        private readonly CombatSport $sport,
        private readonly CategorySystem $categories,
        private readonly ?BeltSystem $belts = null,
    ) {}

    public function base(): CombatSport
    {
        return $this->sport;
    }

    public function categorySystem(): CategorySystem
    {
        return $this->categories;
    }

    public function key(): string { return $this->sport->key(); }
    public function label(): string { return $this->sport->label(); }

    public function weightDivisions(...$args): array
    {
        return $this->categories->table($args[0] ?? null);
    }

    public function classify(string $gender, int $age, float $weight, ...$rest): ?array
    {
        return $this->categories->classify($gender, $age, $weight, $rest[0] ?? null);
    }

    public function beltLadder(): array
    {
        return $this->belts ? $this->belts->ladder : (method_exists($this->sport, 'beltLadder') ? $this->sport->beltLadder() : []);
    }

    public function divisionName(string $ageGroup, string $gender, string $label): string { return $this->sport->divisionName($ageGroup, $gender, $label); }
    public function bronzeRule(): string { return $this->sport->bronzeRule(); }
    public function officialRoles(): array { return $this->sport->officialRoles(); }
    public function scoreLabel(int $points): ?string { return $this->sport->scoreLabel($points); }
    public function cornerLabels(): array { return $this->sport->cornerLabels(); }
    public function redCornerSide(): string { return method_exists($this->sport, 'redCornerSide') ? $this->sport->redCornerSide() : 'left'; }

    /** Anything else the plug-in offers (a sport's own helpers) is its own. */
    public function __call(string $name, array $args): mixed
    {
        return $this->sport->{$name}(...$args);
    }
}
