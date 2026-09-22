<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventRunSlot;
use Illuminate\Support\Facades\DB;

/**
 * Writing the running order: adding parts to a day, moving them, pinning one
 * to a clock time, taking one off.
 *
 * Every method is scoped to ONE event and refuses anything that does not belong
 * to it — a division id from another event, a slot uuid the organiser guessed.
 * Authorisation itself is the caller's job (`EventAccess::canManage`); this
 * class assumes it has already happened and enforces the tenancy of the data.
 *
 * Reading the order back and working out the clock is `RunSheet`'s job, not
 * this one. The split matters: the board writes through here and reads through
 * there, and so does the public page — so an organiser and a visitor can never
 * be looking at two different sets of rules.
 */
class RunOrder
{
    /**
     * Put divisions on a day, in the order given, after whatever is there.
     *
     * Ids that are not this event's divisions are dropped rather than
     * rejected: a stale board should add what it legitimately can, not fail
     * wholesale and lose the organiser's other nine taps.
     *
     * @param  array<int, int>  $categoryIds
     * @return int  how many were added
     */
    public function addDivisions(ClubEvent $event, array $categoryIds, int $day = 1): int
    {
        $ids = array_values(array_unique(array_map('intval', $categoryIds)));
        if (! $ids) {
            return 0;
        }

        $mine = EventCategory::where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->pluck('id')->all();

        // Never the same division twice on one running order — it would draw
        // two slots calling the same competitors to the mat.
        $already = EventRunSlot::where('event_id', $event->id)
            ->whereIn('category_id', $mine)
            ->pluck('category_id')->all();

        $toAdd = array_values(array_diff($mine, $already));
        if (! $toAdd) {
            return 0;
        }

        // Keep the order the caller asked for, not the order the database
        // happened to return.
        usort($toAdd, fn ($a, $b) => array_search($a, $ids, true) <=> array_search($b, $ids, true));

        $position = $this->nextPosition($event, $day);

        foreach ($toAdd as $id) {
            EventRunSlot::create([
                'event_id' => $event->id,
                'category_id' => $id,
                'day' => $day,
                'position' => $position++,
                'kind' => EventRunSlot::KIND_DIVISION,
            ]);
        }

        return count($toAdd);
    }

    /** A part with no bouts: a ceremony, a break, or anything the organiser names. */
    public function addBlock(ClubEvent $event, string $kind, ?string $label, ?int $duration, int $day = 1): EventRunSlot
    {
        $kind = in_array($kind, [EventRunSlot::KIND_CEREMONY, EventRunSlot::KIND_BREAK, EventRunSlot::KIND_CUSTOM], true)
            ? $kind
            : EventRunSlot::KIND_CUSTOM;

        return EventRunSlot::create([
            'event_id' => $event->id,
            'day' => $day,
            'position' => $this->nextPosition($event, $day),
            'kind' => $kind,
            'label' => $this->cleanLabel($label) ?: $this->defaultLabel($kind),
            'duration_minutes' => $this->cleanDuration($duration) ?? EventRunSlot::DEFAULT_DURATIONS[$kind] ?? 15,
        ]);
    }

    /**
     * Set the whole day's order from a list of slot uuids.
     *
     * The list is the truth, and anything of this event's that the list omits
     * keeps its place after the named ones — so a board built from a stale read
     * can reorder what it knows about without silently dropping a slot another
     * organiser added a second ago.
     *
     * @param  array<int, string>  $uuids
     */
    public function reorder(ClubEvent $event, int $day, array $uuids): void
    {
        $slots = EventRunSlot::where('event_id', $event->id)
            ->where('day', $day)
            ->orderBy('position')->orderBy('id')
            ->get(['id', 'uuid']);

        $byUuid = $slots->keyBy('uuid');
        $seen = [];
        $ordered = [];

        foreach ($uuids as $uuid) {
            if (is_string($uuid) && $byUuid->has($uuid) && ! isset($seen[$uuid])) {
                $seen[$uuid] = true;
                $ordered[] = $byUuid[$uuid]->id;
            }
        }

        foreach ($slots as $slot) {
            if (! isset($seen[$slot->uuid])) {
                $ordered[] = $slot->id;
            }
        }

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $i => $id) {
                EventRunSlot::where('id', $id)->update(['position' => ($i + 1) * 10]);
            }
        });
    }

    /** Pin a slot to a clock time, or let it flow again with null. */
    public function pin(EventRunSlot $slot, ?int $minutes): EventRunSlot
    {
        $slot->update([
            'pinned_at' => $minutes === null ? null : max(0, min(1439, $minutes)),
        ]);

        return $slot->refresh();
    }

    /** Rename a block, or change how long it takes. A division has neither. */
    public function edit(EventRunSlot $slot, ?string $label, ?int $duration): EventRunSlot
    {
        if ($slot->isDivision()) {
            return $slot;
        }

        $changes = [];
        if ($label !== null) {
            /*
             * ⚠️ `sourceAttribute`, never `$slot->label` — that reads the
             * TRANSLATED text now, and writing it back would overwrite the
             * organiser's Arabic with a machine translation of itself. The
             * source destroyed by the act of saving.
             */
            $changes['label'] = $this->cleanLabel($label) ?: $slot->sourceAttribute('label');
        }
        if ($duration !== null) {
            $changes['duration_minutes'] = $this->cleanDuration($duration) ?? $slot->duration_minutes;
        }

        if ($changes) {
            $slot->update($changes);
        }

        return $slot->refresh();
    }

    public function remove(EventRunSlot $slot): void
    {
        $slot->delete();
    }

    /**
     * Athletes who would have to compete in two parts running.
     *
     * This is the mistake that actually costs a competition day: somebody
     * entered in two kinds — Gi and No-Gi, Kumite and Kata — drawn into
     * consecutive slots, with no time to change kit or catch their breath. It
     * is invisible on a list of division names, which is exactly why the board
     * has to say it out loud.
     *
     * @return array<int, array{first:string, second:string, count:int}>
     */
    public function conflicts(ClubEvent $event, int $day = 1): array
    {
        $slots = EventRunSlot::where('event_id', $event->id)
            ->where('day', $day)
            ->orderBy('position')->orderBy('id')
            ->with('category:id,event_id,name')
            ->get();

        $categoryIds = $slots->pluck('category_id')->filter()->unique()->values()->all();
        if (count($categoryIds) < 2) {
            return [];
        }

        $byCategory = [];
        ClubEventRegistration::query()
            ->where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereIn('category_id', $categoryIds)
            ->get(['user_id', 'category_id'])
            ->each(function ($r) use (&$byCategory) {
                $byCategory[$r->category_id][$r->user_id] = true;
            });

        $found = [];
        $list = $slots->values();

        for ($i = 0; $i < $list->count() - 1; $i++) {
            $a = $list[$i];
            $b = $list[$i + 1];

            if (! $a->category_id || ! $b->category_id) {
                continue;
            }

            $shared = array_intersect_key(
                $byCategory[$a->category_id] ?? [],
                $byCategory[$b->category_id] ?? [],
            );

            if ($shared) {
                $found[] = [
                    'first' => $a->displayName(),
                    'second' => $b->displayName(),
                    'count' => count($shared),
                ];
            }
        }

        return $found;
    }

    /* ---------------- internals ---------------- */

    private function nextPosition(ClubEvent $event, int $day): int
    {
        $max = (int) EventRunSlot::where('event_id', $event->id)->where('day', $day)->max('position');

        return $max + 10;
    }

    private function cleanLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        // Untrusted, and it is rendered on a public page. Control characters
        // out, length capped to the column, escaping left to Blade.
        $clean = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $label) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 160);
    }

    private function cleanDuration(?int $minutes): ?int
    {
        if ($minutes === null) {
            return null;
        }

        return max(1, min(600, $minutes));
    }

    private function defaultLabel(string $kind): string
    {
        return match ($kind) {
            EventRunSlot::KIND_CEREMONY => __('events.run_order_add_ceremony'),
            EventRunSlot::KIND_BREAK => __('events.run_order_add_break'),
            default => __('events.run_order_add_custom'),
        };
    }
}
