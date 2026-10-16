<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;

/**
 * What a divisions editor needs to know about an event, prepared once.
 *
 * Two editors read it: <x-event-divisions> (the platform's weight-classes page
 * and the create form) and the federation dashboard's Divisions tab, which
 * asks for it as JSON (me.events.divisions.data). It used to be computed in
 * the component's own @php block; it moved here so the two editors offer the
 * same weight-class table, the same belts and the same questions — one copy
 * (CLAUDE.md → Shared Stays Shared). Nothing about it changed in the move.
 */
class DivisionEditor
{
    /**
     * The sport's weight-class table, reduced to what a picker needs: one entry
     * per ruleset × band, each with its ages and its classes per gender.
     *
     * @param  array  $presets  the event type's formCatalog() (weight_divisions,
     *                          weight_divisions_nogi, age_bands), as the
     *                          controller's rulebookPresets() leaves it
     * @return list<array{ruleset: string, band: string, min_age: mixed, max_age: mixed, genders: array}>
     */
    public static function presetTable(array $presets): array
    {
        $presetTable = [];
        /* One entry per RULESET the sport weighs in under. Gi and no-gi are two
           tables, not one table with a couple of kilos taken off: an athlete
           weighs in without a gi, so both the limits and the ladder differ. A
           sport that publishes only one simply offers one. */
        $presetRulesets = array_filter([
            'gi' => $presets['weight_divisions'] ?? null,
            'nogi' => $presets['weight_divisions_nogi'] ?? null,
        ]);
        foreach ($presetRulesets as $ruleset => $table) {
            foreach ((array) $table as $band => $byGender) {
                if (! is_array($byGender)) {
                    continue;
                }
                $ages = $presets['age_bands'][$band] ?? null;
                $entry = ['ruleset' => $ruleset, 'band' => (string) $band,
                    'min_age' => $ages['min'] ?? null, 'max_age' => $ages['max'] ?? null, 'genders' => []];
                foreach ($byGender as $gender => $classes) {
                    if (! is_array($classes) || ! in_array(strtolower((string) $gender), ['male', 'female'], true)) {
                        continue;
                    }
                    $entry['genders'][strtolower((string) $gender)] = array_values(array_map(fn ($c) => [
                        'label' => (string) ($c['label'] ?? ''),
                        'name' => $c['name'] ?? null,
                        // 0 and 200 are the table's way of writing an open end; the
                        // division stores NULL for "no bound", so they are translated
                        // here rather than every reader having to know the convention.
                        'min' => ($c['min'] ?? 0) > 0 ? (float) $c['min'] : null,
                        'max' => ($c['max'] ?? 0) >= 200 ? null : (float) ($c['max'] ?? 0),
                    ], $classes));
                }
                if ($entry['genders']) {
                    $presetTable[] = $entry;
                }
            }
        }

        return $presetTable;
    }

    /**
     * The belts a division may be for: the event's own belt system when it has
     * one (CategorySystems), else the sport's ladder.
     *
     * @return list<array{value: string, label: string, bg: string, fg: string}>
     */
    public static function beltLadder(?ClubEvent $event, ?string $sport): array
    {
        return array_map(fn ($b) => [
            'value' => $b['value'], 'label' => $b['label'], 'bg' => $b['bg'], 'fg' => $b['fg'],
        ], $event && $event->belt_system
            ? app(CategorySystems::class)->ladderFor($event)
            : \App\Sports\Combat\BeltRank::ladder($sport));
    }

    /**
     * The sports whose competitions divide by weight / by rank, from the
     * profiles every event type gives of itself — so a new sport package that
     * weighs its athletes is asked about weight without an editor changing.
     *
     * @return array{weight: list<string>, rank: list<string>}
     */
    public static function criteriaSports(): array
    {
        $criteriaSports = ['weight' => [], 'rank' => []];
        foreach (app(\App\Events\EventTypeRegistry::class)->all() as $profiledType) {
            $profile = $profiledType->profile();
            if (! $profile->sport) {
                continue;
            }
            if ($profile->uses(Competition\Criterion::Weight)) {
                $criteriaSports['weight'][] = $profile->sport;
            }
            if ($profile->uses(Competition\Criterion::Rank)) {
                $criteriaSports['rank'][] = $profile->sport;
            }
        }

        return $criteriaSports;
    }
}
