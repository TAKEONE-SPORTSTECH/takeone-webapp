<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventCategory;

/**
 * Shared foundation for packages whose entrants are split into divisions
 * (weight classes, age groups, race categories) stored as event_categories.
 *
 * This is a helper packages CALL — it holds no type-specific rules. What a
 * division is named, how wide it is and who may enter it stays in the package.
 */
trait SyncsDivisions
{
    /**
     * Create/keep the event's divisions, non-destructively.
     *
     * Divisions the manager removed are deleted ONLY when empty (no entrants,
     * no matches) — a division with a bracket or an entrant is never silently
     * dropped by an edit.
     *
     * @param  array<int, array{name?: string, capacity?: mixed, schedule?: array}>  $divisions
     */
    protected function syncDivisions(ClubEvent $event, array $divisions): void
    {
        $names = [];

        foreach ($divisions as $i => $d) {
            $name = trim((string) ($d['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $names[] = $name;

            $cat = EventCategory::firstOrNew(['event_id' => $event->id, 'name' => $name]);
            $cap = $d['capacity'] ?? null;
            $cat->capacity = ($cap === null || $cap === '') ? null : (int) $cap;
            $cat->sort_order = $i + 1;
            if (! $cat->exists) {
                $cat->status = 'enrolling';
            }
            if (! empty($d['schedule']) && is_array($d['schedule'])) {
                $cat->schedule = $this->normalizePhaseSchedule($d['schedule']);
            }
            $cat->save();
        }

        $event->categories()
            ->whereNotIn('name', $names ?: [''])
            ->whereDoesntHave('registrations')
            ->whereDoesntHave('matches')
            ->delete();
    }

    /** Owner-set day per phase — a later phase can never precede an earlier one. */
    protected function normalizePhaseSchedule(array $schedule): array
    {
        $pre = max(1, (int) ($schedule['preliminary'] ?? 1));
        $qf = max($pre, (int) ($schedule['quarterfinals'] ?? $pre));
        $fin = max($qf, (int) ($schedule['finals'] ?? $qf));

        return ['preliminary' => $pre, 'quarterfinals' => $qf, 'finals' => $fin];
    }

    /** Validation rules for the divisions section. */
    protected function divisionRules(): array
    {
        return [
            'divisions' => ['nullable', 'array', 'max:64'],
            'divisions.*.name' => ['nullable', 'string', 'max:80'],
            'divisions.*.capacity' => ['nullable', 'integer', 'min:2', 'max:512'],
            'divisions.*.schedule' => ['nullable', 'array'],
            'divisions.*.schedule.preliminary' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.quarterfinals' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.finals' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
