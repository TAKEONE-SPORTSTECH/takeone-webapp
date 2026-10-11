<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventRunSlot;
use Illuminate\Support\Facades\DB;
use App\Events\Support\BoutPart;

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
    public function addDivisions(ClubEvent $event, array $categoryIds, int $day = 1, bool $together = false): int
    {
        // `$together`: these divisions are awarded at ONE medal ceremony for
        // everyone at the end of the day (togetherCeremony), not each at its own.
        // Each item is a division id (the whole of what is left of it) or
        // {id, parts} — some of its parts (App\Events\Support\BoutPart).
        $wanted = [];
        foreach ($categoryIds as $item) {
            $id = (int) (is_array($item) ? ($item['id'] ?? 0) : $item);
            if ($id > 0 && ! isset($wanted[$id])) {
                $wanted[$id] = is_array($item) ? BoutPart::normalise(isset($item['parts']) ? (array) $item['parts'] : null) : null;
            }
        }
        if (! $wanted) {
            return 0;
        }

        // Only this event's divisions, and never a heading (it holds nobody).
        $mine = EventCategory::where('event_id', $event->id)
            ->whereIn('id', array_keys($wanted))
            ->where(fn ($q) => $q->whereNull('is_heading')->orWhere('is_heading', false))
            ->get()->keyBy('id');

        $position = $this->nextPosition($event, $day);
        $added = 0;
        $placed = [];

        // The order the caller asked for, not the order the database returned.
        foreach ($wanted as $id => $parts) {
            $division = $mine->get($id);
            if (! $division) {
                continue;
            }

            // Never the same part twice on one running order — it would draw
            // two slots calling the same competitors to the mat.
            $left = $this->remainingParts($division);
            if ($left === []) {
                continue;
            }

            $present = BoutPart::present($division);
            if ($together) {
                $left = array_values(array_diff($left, [BoutPart::AWARDING]));
            }
            $take = $parts === null ? $left : array_values(array_intersect($left, $parts));
            if ($take === []) {
                continue;
            }

            EventRunSlot::create([
                'event_id' => $event->id,
                'category_id' => $id,
                // A division with no draw yet goes on whole (NULL, as every slot
                // did before parts existed). With a draw, the parts are written
                // out — awarding included — so the day can be timed by them.
                'parts' => $present === [] ? null : $take,
                'day' => $day,
                'position' => $position++,
                'kind' => EventRunSlot::KIND_DIVISION,
            ]);
            $added++;
            $placed[] = $id;
        }

        if ($together && $placed) {
            $this->togetherCeremony($event, $day, $placed);
        }

        if ($added) {
            $this->renumber($event);
        }

        return $added;
    }

    /**
     * The parts of a division not on the day yet, in running order. A division
     * with no draw has one part — itself — so it is either on the day or not.
     *
     * @return list<string>  empty when nothing is left to place
     */
    public function remainingParts(EventCategory $division): array
    {
        $slots = EventRunSlot::where('category_id', $division->id)->get(['parts']);
        $present = BoutPart::present($division);

        if ($present === []) {
            return $slots->isEmpty() ? BoutPart::ALL : [];
        }

        // What can be placed: its bout parts, then its awarding — unless a
        // ceremony for everyone already awards it.
        $offer = in_array($division->id, $this->awardedTogether($division->event_id), true)
            ? $present
            : [...$present, BoutPart::AWARDING];

        // A slot written before parts existed (NULL) placed the whole division,
        // awarding and all — it is never reopened.
        $taken = [];
        foreach ($slots as $slot) {
            $taken = array_merge($taken, $slot->parts ?? $offer);
        }

        return array_values(array_diff($offer, $taken));
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
     * The break the organiser set when they created the event
     * (club_events.break_start / break_end), offered on the running order's
     * bench until it is on the day — owner, 2026-10-07: "I don't see the
     * break that we entered in the creation of the championship".
     *
     * @return array{starts_at: int, duration: int}|null  null when the event has
     *         no break, or this day already carries it
     */
    public function eventBreak(ClubEvent $event, int $day = 1): ?array
    {
        if (! $event->break_start || ! $event->break_end) {
            return null;
        }

        $mins = function (string $t): int {
            [$h, $m] = array_map('intval', explode(':', substr($t, 0, 5)) + [0, 0]);

            return $h * 60 + $m;
        };
        $start = $mins((string) $event->break_start);
        $duration = $mins((string) $event->break_end) - $start;
        if ($duration <= 0) {
            return null;
        }

        // Already on the day: a break pinned at that time.
        $placed = EventRunSlot::where('event_id', $event->id)->where('day', $day)
            ->where('kind', EventRunSlot::KIND_BREAK)->where('pinned_at', $start)->exists();

        return $placed ? null : ['starts_at' => $start, 'duration' => $duration,
            // For the edit sheet: the times as stored, 24-hour.
            'start' => substr((string) $event->break_start, 0, 5), 'end' => substr((string) $event->break_end, 0, 5)];
    }

    /**
     * Set the event's break to new times. A break already on the day — the one
     * pinned at the old start — moves with it.
     */
    public function moveEventBreak(ClubEvent $event, string $start, string $end, int $day = 1): void
    {
        $toMin = fn (string $t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $old = $event->break_start ? $toMin((string) $event->break_start) : null;

        DB::transaction(function () use ($event, $start, $end, $old, $toMin, $day) {
            $slot = $old === null ? null : EventRunSlot::where('event_id', $event->id)->where('day', $day)
                ->where('kind', EventRunSlot::KIND_BREAK)->where('pinned_at', $old)->first();

            $event->forceFill(['break_start' => $start, 'break_end' => $end])->save();

            $slot?->update(['pinned_at' => $toMin($start), 'duration_minutes' => $toMin($end) - $toMin($start)]);
        });
    }

    /**
     * Put the event's own break on the day: pinned at its time, as long as it
     * was set, and slotted in where the day reaches that time — not at the end.
     */
    public function addEventBreak(ClubEvent $event, int $day = 1): ?EventRunSlot
    {
        $break = $this->eventBreak($event, $day);
        if (! $break) {
            return null;
        }

        // Where it belongs: before the first part that would start at or after it.
        $rows = app(RunSheet::class)->rows($event, $day);
        $at = count($rows);
        foreach ($rows as $i => $row) {
            if ($row['starts_at'] >= $break['starts_at']) {
                $at = $i;
                break;
            }
        }

        $slot = $this->addBlock($event, EventRunSlot::KIND_BREAK, null, $break['duration'], $day);
        $slot->update(['pinned_at' => $break['starts_at']]);

        $order = array_column($rows, 'uuid');
        array_splice($order, $at, 0, [$slot->uuid]);
        $this->reorder($event, $day, $order);

        return $slot->refresh();
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

        $this->renumber($event);
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
        $event = $slot->isDivision() ? $slot->event : null;
        $slot->delete();

        $event && $this->renumber($event);
    }

    /**
     * Bout numbers follow the day's running order (owner, 2026-10-07: "the
     * bouts must be numbered as per the day schedule").
     *
     * The shared Scheduler already numbers a mat division by division in the
     * running order, each one from its first round to its final — but it only
     * ran when the DRAW changed, so arranging the day left every number where
     * the old phase-by-phase order had put it. Now arranging the day renumbers.
     *
     * Only before the event starts: a number the hall has already been called
     * by is never moved under it — the same rule the packages apply. Only for
     * the combat packages that schedule through this engine; anything else
     * has no bout numbers to keep in step.
     */
    private function renumber(ClubEvent $event): void
    {
        $event = $event->fresh();

        if (! $event || $event->hasStarted() || $event->hasEnded() || ! $event->sport
            || ! app(\App\Sports\Combat\SportRegistry::class)->get((string) $event->sport)
            || ! \App\Events\Models\EventMatch::where('event_id', $event->id)->exists()) {
            return;
        }

        app(\App\Sports\Combat\Engine\Scheduler::class)->scheduleAndNumber($event);
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

    /**
     * One medal ceremony for everyone, at the end of the day (owner,
     * 2026-10-07) — the alternative to each division's own awarding.
     *
     * A ceremony slot whose `parts` holds {"awards": [division ids]}. Adding
     * divisions this way joins the day's existing one, and moves it back to the
     * end of the day so it stays last; there is never more than one per day.
     *
     * @param  list<int>  $divisionIds
     */
    public function togetherCeremony(ClubEvent $event, int $day, array $divisionIds): EventRunSlot
    {
        $slot = $this->togetherSlot($event, $day);
        $awards = array_values(array_unique(array_map('intval', [...($slot ? (array) ($slot->parts['awards'] ?? []) : []), ...$divisionIds])));

        if (! $slot) {
            $slot = $this->addBlock($event, EventRunSlot::KIND_CEREMONY, null, null, $day);
        }
        $slot->update([
            'parts' => ['awards' => $awards],
            // A medal round per division, never under a quarter of an hour.
            'duration_minutes' => max(15, 2 * count($awards)),
        ]);

        // Last on the day.
        $order = EventRunSlot::where('event_id', $event->id)->where('day', $day)
            ->where('id', '!=', $slot->id)->orderBy('position')->orderBy('id')->pluck('uuid')->all();
        $this->reorder($event, $day, [...$order, $slot->uuid]);

        return $slot->refresh();
    }

    public function togetherSlot(ClubEvent $event, int $day): ?EventRunSlot
    {
        return EventRunSlot::where('event_id', $event->id)->where('day', $day)
            ->where('kind', EventRunSlot::KIND_CEREMONY)->whereNotNull('parts')->get()
            ->first(fn ($s) => is_array($s->parts) && array_key_exists('awards', $s->parts));
    }

    /** Divisions a ceremony for everyone already awards, on any day. @return list<int> */
    public function awardedTogether(int $eventId): array
    {
        return EventRunSlot::where('event_id', $eventId)->where('kind', EventRunSlot::KIND_CEREMONY)->whereNotNull('parts')->get()
            ->flatMap(fn ($s) => is_array($s->parts) ? (array) ($s->parts['awards'] ?? []) : [])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function defaultLabel(string $kind): string
    {
        return match ($kind) {
            EventRunSlot::KIND_CEREMONY => __('events::events.run_order_add_ceremony'),
            EventRunSlot::KIND_BREAK => __('events::events.run_order_add_break'),
            default => __('events::events.run_order_add_custom'),
        };
    }
}
