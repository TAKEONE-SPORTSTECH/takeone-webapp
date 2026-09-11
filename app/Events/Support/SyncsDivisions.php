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
            $cat->sort_order = $i + 1;
            if (! $cat->exists) {
                $cat->status = 'enrolling';
            }

            /*
             * A heading carries a NAME and nothing else — the same shape
             * PersonalEventController::headingSafe() enforces on the editor's
             * own endpoint, so the two ways into this table cannot disagree
             * about what a heading is.
             *
             * Read from the form rather than from the row: the create form is
             * where a heading can be added, and an existing one that arrived
             * with `is_heading` false has been deliberately turned back into a
             * division. Everything a heading may not hold is cleared, so it can
             * never be left carrying a weight range nothing would ever read.
             */
            $cat->is_heading = (bool) ($d['is_heading'] ?? false);

            if ($cat->is_heading) {
                $cat->capacity = null;
                $cat->schedule = [];
                $cat->save();

                continue;
            }

            $cap = $d['capacity'] ?? null;
            $cat->capacity = ($cap === null || $cap === '') ? null : (int) $cap;
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
            // A title in the list rather than a division. See sync().
            'divisions.*.is_heading' => ['nullable', 'boolean'],
            'divisions.*.capacity' => ['nullable', 'integer', 'min:2', 'max:512'],
            'divisions.*.schedule' => ['nullable', 'array'],
            'divisions.*.schedule.preliminary' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.quarterfinals' => ['nullable', 'integer', 'min:1', 'max:60'],
            'divisions.*.schedule.finals' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
