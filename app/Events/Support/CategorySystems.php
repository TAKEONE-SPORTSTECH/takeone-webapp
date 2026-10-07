<?php

namespace App\Events\Support;

use App\Events\Models\CategorySystemRecord;
use App\Events\Models\ClubEvent;
use App\Sports\Combat\BeltLadder;
use App\Sports\Combat\BeltSystem;
use App\Sports\Combat\BoundSport;
use App\Sports\Combat\CategorySystem;
use App\Sports\Combat\CombatSport;
use App\Sports\Combat\SportRegistry;

/**
 * Which rule book an event runs under — its CATEGORY system (age groups ×
 * weight classes) and its BELT system (2026-09-28).
 *
 * The one place that answers it. A sport offers its built-in systems
 * (CombatSport::categorySystems / beltSystems); an organiser's host adds its
 * own (category_systems). The event records its choice
 * (club_events.category_system / belt_system — NULL is the sport's default,
 * which is every event that existed before this).
 *
 * `sportFor()` is how the choice takes effect: every classifier, division
 * generator and belt picker that runs for an event asks it for the sport, and
 * gets the plain plug-in when the event chose nothing — exactly the old
 * behaviour — or the plug-in bound to the chosen systems.
 */
class CategorySystems
{
    /** @return array<string, CategorySystem> built-ins first, then the host's own */
    public function weights(string $sport, ?string $hostType = null, ?int $hostId = null): array
    {
        $plugin = $this->plugin($sport);
        $out = $plugin && method_exists($plugin, 'categorySystems') ? $plugin->categorySystems() : [];

        foreach ($this->customs($sport, 'weights', $hostType, $hostId) as $r) {
            $s = $this->customWeights($r);
            $out[$s->key] = $s;
        }

        return $out;
    }

    /** @return array<string, BeltSystem> */
    public function belts(string $sport, ?string $hostType = null, ?int $hostId = null): array
    {
        $plugin = $this->plugin($sport);
        $out = $plugin && method_exists($plugin, 'beltSystems') ? $plugin->beltSystems() : [];

        foreach ($this->customs($sport, 'belts', $hostType, $hostId) as $r) {
            $s = $this->customBelts($r);
            $out[$s->key] = $s;
        }

        return $out;
    }

    /** The systems an event may choose from — its own host's customs included. */
    public function weightsFor(ClubEvent $event): array
    {
        [$type, $id] = $this->hostOf($event);

        return $event->sport ? $this->weights((string) $event->sport, $type, $id) : [];
    }

    public function beltsFor(ClubEvent $event): array
    {
        [$type, $id] = $this->hostOf($event);

        return $event->sport ? $this->belts((string) $event->sport, $type, $id) : [];
    }

    /** The category system this event runs under (its sport's default when it chose none). */
    public function forEvent(ClubEvent $event): ?CategorySystem
    {
        $all = $this->weightsFor($event);
        if ($all === []) {
            return null;
        }

        return ($event->category_system ? ($all[$event->category_system] ?? null) : null) ?? reset($all);
    }

    public function beltSystemFor(ClubEvent $event): ?BeltSystem
    {
        $all = $this->beltsFor($event);
        if ($all === []) {
            return null;
        }

        return ($event->belt_system ? ($all[$event->belt_system] ?? null) : null) ?? reset($all);
    }

    /**
     * The sport, as THIS event runs it. The plain plug-in when the event chose
     * nothing (or chose its sport's defaults) — the behaviour every event had
     * before systems existed.
     */
    public function sportFor(CombatSport $sport, ?ClubEvent $event): CombatSport
    {
        if (! $event || $sport instanceof BoundSport || (! $event->category_system && ! $event->belt_system)) {
            return $sport;
        }
        $cat = $this->forEvent($event);
        $belt = $event->belt_system ? $this->beltSystemFor($event) : null;
        $default = method_exists($sport, 'defaultCategorySystem') ? $sport->defaultCategorySystem() : null;

        if ((! $cat || $cat->key === $default) && ! $belt) {
            return $sport;
        }

        return new BoundSport($sport, $cat && $cat->key !== $default ? $cat : ($sport->categorySystems()[$default] ?? $cat), $belt);
    }

    /**
     * The STYLE an event runs under, as a reader would name it — "Shotokan",
     * "Kyokushin" — or null when it runs its sport's own default.
     *
     * Only an official system the organiser explicitly chose names a style:
     * a host's custom table is an edition of something, not a style, and the
     * sport's default says nothing the sport's name does not already say. The
     * weight table speaks first; the belt system names the style when the
     * weights are the default or the organiser's own (a Kyokushin event with
     * hand-made divisions, say).
     */
    public function styleLabel(ClubEvent $event): ?string
    {
        $plugin = $event->sport ? $this->plugin((string) $event->sport) : null;
        if (! $plugin) {
            return null;
        }
        $default = $plugin->defaultCategorySystem();

        if ($event->category_system && $event->category_system !== $default
            && ($w = $this->weightsFor($event)[$event->category_system] ?? null) && ! $w->custom) {
            return $w->label;
        }

        if ($event->belt_system && $event->belt_system !== $default
            && ($b = $this->beltsFor($event)[$event->belt_system] ?? null) && ! $b->custom) {
            return $b->label;
        }

        return null;
    }

    /** The belt ladder this event reads — the chosen system's, else the sport's, else the platform's generic one. */
    public function ladderFor(?ClubEvent $event): array
    {
        if ($event && $event->belt_system && ($b = $this->beltSystemFor($event)) && $b->ladder !== []) {
            return $b->ladder;
        }

        return \App\Sports\Combat\BeltRank::ladder($event?->sport);
    }

    /** Is `$key` a system this event may choose? (Validation: never trust a posted key.) */
    public function validWeights(ClubEvent $event, ?string $key): bool
    {
        return $key === null || $key === '' || array_key_exists($key, $this->weightsFor($event));
    }

    public function validBelts(ClubEvent $event, ?string $key): bool
    {
        return $key === null || $key === '' || array_key_exists($key, $this->beltsFor($event));
    }

    // ─── Changing an event's rule book ──────────────────────────────────────

    /**
     * Set the event's category and belt system, and RE-SORT everybody already
     * entered under the new one (the organiser's choice, 2026-09-28).
     *
     * Refused once a draw has been cut: a bracket is built from the divisions
     * as they stood, and moving people out of it under a new table would leave
     * bouts with the wrong athletes in them.
     *
     * Who is moved is the weigh-in desk's own rule (Enrolment::placeOnWeighIn):
     * an entry the PLATFORM placed is placed again by its weight; one a PERSON
     * placed stays exactly where they put it. Divisions the new table needs for
     * the athletes already entered are created, named the sport's own way.
     *
     * @return array{ok: bool, message?: string, moved?: int, unplaced?: int, created?: int}
     */
    public function change(ClubEvent $event, ?string $category, ?string $belt): array
    {
        $category = $category ?: null;
        $belt = $belt ?: null;
        if (! $this->validWeights($event, $category) || ! $this->validBelts($event, $belt)) {
            return ['ok' => false, 'message' => __('events::events.rulebook_invalid')];
        }
        $changed = $category !== $event->category_system;
        if (! $changed && $belt === $event->belt_system) {
            return ['ok' => true, 'moved' => 0, 'unplaced' => 0, 'created' => 0];
        }
        if ($changed && \App\Events\Models\EventMatch::where('event_id', $event->id)->exists()) {
            return ['ok' => false, 'message' => __('events::events.rulebook_locked')];
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($event, $category, $belt, $changed) {
            $event->forceFill(['category_system' => $category, 'belt_system' => $belt])->save();
            if (! $changed) {
                return ['ok' => true, 'moved' => 0, 'unplaced' => 0, 'created' => 0];
            }

            return ['ok' => true] + $this->resort($event->fresh());
        });
    }

    /**
     * Place one entry by its weight under the event's rule book, CREATING the
     * division the table puts them in when the event does not run it yet.
     *
     * For events whose categories are the rule book's own rather than a list
     * an organiser curates — a federation's competition, where every athlete
     * has always been sorted automatically. A club's event keeps its rule
     * that only the organiser creates divisions (Enrolment::resolveDivision).
     */
    public function placeCreating(ClubEvent $event, \App\Events\Models\ClubEventRegistration $reg): ?\App\Events\Models\EventCategory
    {
        if ($reg->category_id) {
            return $reg->category;
        }
        $u = $reg->user;
        $base = rescue(fn () => app(SportRegistry::class)->get((string) $event->sport), null, report: false);
        if (! $base || ! $u || ! $u->gender || ! $u->birthdate || ! $reg->weight) {
            return null;
        }
        $sport = $this->sportFor($base, $event);
        $class = $sport->classify(strtolower((string) $u->gender), \Illuminate\Support\Carbon::parse($u->birthdate)->age, (float) $reg->weight);
        if (! $class) {
            return null;
        }
        $this->ensureDivision($event, $sport, $class, strtolower((string) $u->gender));
        $placed = app(\App\Events\EventTypeRegistry::class)->for($event)->classifyEntry($event, $reg->fresh('user'));
        if ($placed) {
            $reg->forceFill(['category_source' => \App\Events\Models\ClubEventRegistration::PLACED_AUTO])->save();
        }

        return $placed;
    }

    /**
     * The division one athlete belongs in within one STREAM of the event —
     * Kumite, Kata, Gi, No-Gi — created if the event does not run it yet.
     *
     * A weighed stream divides by the rule book's age group, gender and
     * weight class; an unweighed one (kata, poomsae, forms) by age group and
     * gender only. The division carries the stream, so an athlete entered in
     * both streams holds two entries, drawn apart (EventStreams).
     *
     * Like placeCreating(), for events whose divisions are the rule book's own
     * (a federation's competition); it decides nothing about who may enter.
     */
    public function streamDivision(ClubEvent $event, \App\Members\Models\User $u, ?float $weight, string $streamKey, string $streamLabel, bool $weighed): ?\App\Events\Models\EventCategory
    {
        $base = rescue(fn () => app(SportRegistry::class)->get((string) $event->sport), null, report: false);
        if (! $base || ! $u->gender || ! $u->birthdate || $streamKey === '') {
            return null;
        }
        $sport = $this->sportFor($base, $event);
        $gender = strtolower((string) $u->gender);
        $age = \Illuminate\Support\Carbon::parse($u->birthdate)->age;
        $suffix = ' · '.$streamLabel;

        if ($weighed) {
            if (! $weight) {
                return null;
            }
            $class = $sport->classify($gender, $age, (float) $weight);
            if (! $class) {
                return null;
            }
            $name = mb_substr($sport->divisionName($class['age_group'], $gender, $class['category']).$suffix, 0, 190);
            $group = collect($this->forEvent($event)?->ageGroups ?? [])->firstWhere('key', $class['age_group']);
            $attrs = ['weight_class' => $class['category'], 'min_weight' => (float) $class['min'], 'max_weight' => (float) $class['max']];
        } else {
            $system = $this->forEvent($event);
            $groupKey = $system?->ageGroupFor($age);
            if ($groupKey === null) {
                return null;
            }
            $word = method_exists($sport, 'genderWord') ? $sport->genderWord($gender) : ($gender === 'female' ? 'Women' : 'Men');
            $name = mb_substr($groupKey.' '.$word.$suffix, 0, 190);
            $group = collect($system->ageGroups ?? [])->firstWhere('key', $groupKey);
            $attrs = [];
        }

        $existing = \App\Events\Models\EventCategory::where('event_id', $event->id)->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        return \App\Events\Models\EventCategory::create($attrs + [
            'event_id' => $event->id,
            'name' => $name,
            'gender' => $gender,
            'min_age' => $group['min'] ?? null,
            'max_age' => $group['max'] ?? null,
            'stream' => $streamKey,
            'sort_order' => (int) \App\Events\Models\EventCategory::where('event_id', $event->id)->max('sort_order') + 1,
        ]);
    }

    /** The division a classification names, created on the event if it is not run yet. Returns true when created. */
    private function ensureDivision(ClubEvent $event, CombatSport $sport, array $class, string $gender): bool
    {
        $name = $sport->divisionName($class['age_group'], $gender, $class['category']);
        if (\App\Events\Models\EventCategory::where('event_id', $event->id)->where('name', $name)->exists()) {
            return false;
        }
        $group = collect($this->forEvent($event)?->ageGroups ?? [])->firstWhere('key', $class['age_group']);
        \App\Events\Models\EventCategory::create([
            'event_id' => $event->id,
            'name' => $name,
            'weight_class' => $class['category'],
            'gender' => $gender,
            'min_age' => $group['min'] ?? null,
            'max_age' => $group['max'] ?? null,
            'min_weight' => (float) $class['min'],
            'max_weight' => (float) $class['max'],
            'sort_order' => (int) \App\Events\Models\EventCategory::where('event_id', $event->id)->max('sort_order') + 1,
        ]);

        return true;
    }

    /** @return array{moved: int, unplaced: int, created: int} */
    private function resort(ClubEvent $event): array
    {
        $type = app(\App\Events\EventTypeRegistry::class)->for($event);
        $base = rescue(fn () => app(SportRegistry::class)->get((string) $event->sport), null, report: false);
        if (! $base) {
            return ['moved' => 0, 'unplaced' => 0, 'created' => 0];
        }
        $sport = $this->sportFor($base, $event);
        $created = 0;
        $moved = 0;
        $unplaced = 0;
        $touched = [];

        $entries = \App\Events\Models\ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')->with('user')->get();

        foreach ($entries as $reg) {
            // A person's decision is theirs (the weigh-in desk's rule).
            if ($reg->category_id && $reg->category_source === \App\Events\Models\ClubEventRegistration::PLACED_MANUAL) {
                continue;
            }
            $u = $reg->user;
            // At a hosted event, whose divisions are the rule book's own (a
            // federation's), an entry in one STREAM (Kata, Kumite) stays in
            // that stream, re-placed among its own divisions. A club's event
            // keeps the re-sort it has always had.
            $current = ! $event->tenant_id && $reg->category_id ? \App\Events\Models\EventCategory::find($reg->category_id) : null;
            $stream = $current ? \App\Events\Support\EventStreams::ofDivision($current) : null;
            if ($stream !== null && $u) {
                $label = collect(\App\Events\Support\EventStreams::of($event))->firstWhere('key', $stream)['label'] ?? $stream;
                $weighed = $current->max_weight !== null;
                $existed = \App\Events\Models\EventCategory::where('event_id', $event->id)->count();
                $target = $this->streamDivision($event, $u, $reg->weight ? (float) $reg->weight : null, $stream, $label, $weighed);
                $created += \App\Events\Models\EventCategory::where('event_id', $event->id)->count() - $existed;
                if ($target && (int) $target->id !== (int) $current->id) {
                    $reg->forceFill(['category_id' => $target->id, 'category_source' => \App\Events\Models\ClubEventRegistration::PLACED_AUTO])->save();
                    $moved++;
                    $touched[$target->id] = $target;
                    $touched[$current->id] = $current;
                }

                continue;
            }
            if (! $u || ! $u->gender || ! $u->birthdate || ! $reg->weight) {
                continue;
            }
            $age = \Illuminate\Support\Carbon::parse($u->birthdate)->age;
            $class = $sport->classify(strtolower((string) $u->gender), $age, (float) $reg->weight);

            // The division the new table puts them in — created if the event
            // does not run it yet, so nobody is left out by the change.
            if ($class && $this->ensureDivision($event, $sport, $class, strtolower((string) $u->gender))) {
                $created++;
            }

            $before = $reg->category_id;
            $reg->forceFill(['category_id' => null])->save();
            $placed = $type->classifyEntry($event, $reg->fresh('user'));
            if ($placed) {
                $reg->forceFill(['category_source' => \App\Events\Models\ClubEventRegistration::PLACED_AUTO])->save();
                if ((int) $placed->id !== (int) $before) {
                    $moved++;
                }
                $touched[$placed->id] = $placed;
            } else {
                $unplaced++;
            }
            if ($before) {
                $touched[$before] = \App\Events\Models\EventCategory::find($before);
            }
        }

        foreach (array_filter($touched) as $cat) {
            rescue(fn () => $type->onEntrantsChanged($event, $cat), null, report: true);
        }

        return ['moved' => $moved, 'unplaced' => $unplaced, 'created' => $created];
    }

    // ─── Custom systems ─────────────────────────────────────────────────────

    /**
     * Save an organiser's own system. `$data` is validated into shape here,
     * whatever the caller already checked: weights = age groups + classes per
     * group and gender; belts = a ladder of colours with their grades.
     */
    public function saveCustom(string $sport, string $kind, string $label, array $data, string $hostType, int $hostId, ?int $userId, ?CategorySystemRecord $record = null): CategorySystemRecord
    {
        $clean = $kind === 'belts' ? $this->cleanBelts($data) : $this->cleanWeights($data);

        $r = $record ?? new CategorySystemRecord;
        $r->forceFill([
            'sport' => $sport,
            'kind' => $kind,
            'label' => mb_substr(trim(strip_tags($label)), 0, 120) ?: 'Custom',
            'host_type' => $hostType,
            'host_id' => $hostId,
            'data' => $clean,
            'created_by' => $r->created_by ?? $userId,
        ])->save();

        return $r;
    }

    public function cleanWeights(array $data): array
    {
        $groups = [];
        foreach (array_slice((array) ($data['age_groups'] ?? []), 0, 30) as $g) {
            $key = mb_substr(trim(strip_tags((string) ($g['key'] ?? ''))), 0, 40);
            if ($key === '' || isset($groups[$key])) {
                continue;
            }
            $min = max(0, min(120, (int) ($g['min'] ?? 0)));
            $max = isset($g['max']) && $g['max'] !== '' && $g['max'] !== null ? max($min, min(120, (int) $g['max'])) : null;
            $groups[$key] = ['key' => $key, 'min' => $min, 'max' => $max];
        }
        $classes = [];
        foreach ($groups as $key => $_) {
            foreach (['male', 'female'] as $gender) {
                $rows = [];
                foreach (array_slice((array) ($data['classes'][$key][$gender] ?? []), 0, 40) as $c) {
                    $label = mb_substr(trim(strip_tags((string) ($c['label'] ?? ''))), 0, 24);
                    if ($label === '') {
                        continue;
                    }
                    $min = max(0, min(300, (float) ($c['min'] ?? 0)));
                    $max = max($min, min(300, (float) ($c['max'] ?? 200)));
                    $rows[] = ['label' => $label, 'min' => $min, 'max' => $max];
                }
                $classes[$key][$gender] = $rows;
            }
        }

        return ['age_groups' => array_values($groups), 'classes' => $classes];
    }

    public function cleanBelts(array $data): array
    {
        $ladder = [];
        foreach (array_slice((array) ($data['ladder'] ?? []), 0, 30) as $b) {
            $value = strtolower((string) ($b['value'] ?? ''));
            // Colours only from the platform's own palette (a stripe as "a-b").
            if (! preg_match('/^[a-z]+(-[a-z]+)?$/', $value)
                || collect(explode('-', $value))->contains(fn ($c) => ! isset(BeltLadder::HEX[$c]))) {
                continue;
            }
            $ladder[] = [
                'value' => $value,
                'label' => mb_substr(trim(strip_tags((string) ($b['label'] ?? ''))), 0, 40) ?: null,
                'grades' => collect((array) ($b['grades'] ?? []))->map(fn ($x) => mb_substr(trim(strip_tags((string) $x)), 0, 40))->filter()->take(20)->values()->all(),
            ];
        }

        return ['ladder' => $ladder];
    }

    private function customWeights(CategorySystemRecord $r): CategorySystem
    {
        $d = $this->cleanWeights((array) $r->data);

        return new CategorySystem(key: $r->key(), label: (string) $r->label, ageGroups: $d['age_groups'], classes: $d['classes'], custom: true);
    }

    private function customBelts(CategorySystemRecord $r): BeltSystem
    {
        $ladder = array_map(fn ($b) => BeltLadder::rank($b['value'], null, $b['grades'], $b['label']), $this->cleanBelts((array) $r->data)['ladder']);

        return new BeltSystem(key: $r->key(), label: (string) $r->label, ladder: $ladder, custom: true);
    }

    /** @return \Illuminate\Support\Collection<int, CategorySystemRecord> */
    private function customs(string $sport, string $kind, ?string $hostType, ?int $hostId)
    {
        if (! $hostType || ! $hostId) {
            return collect();
        }

        return CategorySystemRecord::where('sport', $sport)->where('kind', $kind)
            ->where('host_type', $hostType)->where('host_id', $hostId)->orderBy('label')->get();
    }

    /** @return array{0: ?string, 1: ?int} */
    public function hostOf(ClubEvent $event): array
    {
        if ($event->tenant_id) {
            return ['club', (int) $event->tenant_id];
        }

        return $event->host_type && $event->host_id ? [(string) $event->host_type, (int) $event->host_id] : [null, null];
    }

    private function plugin(string $sport): ?CombatSport
    {
        return rescue(fn () => app(SportRegistry::class)->get($sport), null, report: false);
    }
}
