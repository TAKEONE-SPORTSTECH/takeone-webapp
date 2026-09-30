<?php

namespace App\Sports\Package;

use App\Events\Models\ClubEvent;
use App\Events\Support\CategorySystems;
use App\Sports\Combat\CombatSport;
use App\Sports\Combat\SportRegistry;

/**
 * The sport packages — the one door to "how is this sport run?".
 *
 * Phase 2 of the Sport Package Plan: every combat sport answers through this,
 * built over the code that already runs it (see RuleBook). Nothing calls it
 * yet on a live path; it is the contract later phases move onto, proved equal
 * to the live path first (tests/Feature/SportPackagesTest.php and the parity
 * run on stage data).
 */
final class SportPackages
{
    public function __construct(
        private SportRegistry $registry,
        private CategorySystems $systems,
    ) {}

    /** @return array<int, string> sport keys */
    public function sports(): array
    {
        return array_keys($this->registry->all());
    }

    /**
     * The OFFICIAL styles of a sport, each at version 1 (today's rules).
     * Custom systems are not styles — they are editions a host derived.
     *
     * @return array<string, RuleBook> keyed by style
     */
    public function styles(string $sport): array
    {
        $plugin = $this->registry->get($sport);
        if (! $plugin) {
            return [];
        }

        $out = [];
        foreach ($this->systems->weights($sport) as $key => $system) {
            if ($system->custom) {
                continue;
            }
            $out[$key] = $this->book($sport, $plugin, $key, $system, null);
        }

        return $out;
    }

    /** One official style's rule book, or null when the sport has no such style. */
    public function style(string $sport, string $style): ?RuleBook
    {
        return $this->styles($sport)[$style] ?? null;
    }

    /**
     * The rule book THIS event runs under — its chosen system (official or its
     * host's own), else its sport's default. Built from the very CombatSport
     * the enrolment desk uses for this event.
     */
    public function forEvent(ClubEvent $event): ?RuleBook
    {
        $plugin = $event->sport ? $this->registry->get((string) $event->sport) : null;
        $system = $plugin ? $this->systems->forEvent($event) : null;
        if (! $plugin || ! $system) {
            return null;
        }

        return new RuleBook(
            sport: (string) $event->sport,
            style: $system->key,
            styleLabel: $system->label,
            // A host's own system is its own edition; an official style is the
            // version this event is locked to (see editionFor()).
            edition: $system->custom ? 'custom' : $this->editionFor($event, $system->key),
            official: ! $system->custom,
            combat: $this->systems->sportFor($plugin, $event),
            categories: $system,
            belts: $this->systems->beltSystemFor($event),
            kinds: $this->kinds($plugin),
            note: $system->note,
        );
    }

    // ─── Versions (phase 4) ────────────────────────────────────────────────

    /**
     * The published versions of one official style, oldest first.
     *
     * Version 1 is today's rules and has always been in force (no publish
     * date). Later versions are published by a super-admin from the world
     * federation's own documents and carry the moment they took effect.
     *
     * @return array<string, array{key: string, label: string, published_at: ?\Carbon\CarbonInterface}>
     */
    public function editions(string $sport, string $style): array
    {
        if (! $this->style($sport, $style)) {
            return [];
        }

        return ['v1' => ['key' => 'v1', 'label' => __('events::events.rulebook_edition_v1'), 'published_at' => null]];
    }

    /**
     * The version an event is LOCKED to: the organiser's explicit choice when
     * they made one (and it still exists), otherwise the newest version that had
     * been published when the event was created — so a rule change published
     * later never reaches an event that already existed.
     */
    public function editionFor(ClubEvent $event, ?string $style = null): string
    {
        $style ??= $this->systems->forEvent($event)?->key;
        $editions = $style ? $this->editions((string) $event->sport, $style) : [];
        if ($editions === []) {
            return 'v1';
        }

        $chosen = \App\Events\Models\EventRuleBook::where('event_id', $event->id)->value('edition');
        if ($chosen && isset($editions[$chosen])) {
            return $chosen;
        }

        $created = $event->created_at;
        $locked = 'v1';
        foreach ($editions as $key => $e) {
            if ($e['published_at'] === null || ($created && $e['published_at']->lte($created))) {
                $locked = $key;
            }
        }

        return $locked;
    }

    private function book(string $sport, CombatSport $plugin, string $key, $system, $belts): RuleBook
    {
        $default = method_exists($plugin, 'defaultCategorySystem') ? $plugin->defaultCategorySystem() : null;
        $beltSystems = method_exists($plugin, 'beltSystems') ? $plugin->beltSystems() : [];

        return new RuleBook(
            sport: $sport,
            style: $key,
            styleLabel: $system->label,
            edition: 'v1',
            official: true,
            // The default style IS the plain plug-in; another style binds its own table.
            combat: $key === $default ? $plugin : new \App\Sports\Combat\BoundSport($plugin, $system, null),
            categories: $system,
            belts: $beltSystems[$key] ?? ($beltSystems[$default] ?? null),
            kinds: $this->kinds($plugin),
            note: $system->note,
        );
    }

    /** @return array<int, string> */
    private function kinds(CombatSport $plugin): array
    {
        $rulesets = defined(get_class($plugin).'::RULESETS') ? array_keys(constant(get_class($plugin).'::RULESETS')) : [];

        return $rulesets !== [] ? $rulesets : ['standard'];
    }
}
