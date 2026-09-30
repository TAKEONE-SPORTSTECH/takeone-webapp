<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventRunSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The running order, turned into a clock.
 *
 * ORDER is what the organiser arranges; TIME is what this works out. A day
 * starts at the event's start time and each part takes as long as its bouts
 * take — so moving one division earlier moves every later time with it, and
 * nobody retypes sixteen clock times to insert a lunch break.
 *
 * Two things are NOT derived:
 *   • a PINNED slot holds its own clock time (the prayer break at 12:10 does
 *     not move because the division before it ran long), and the day flows
 *     around it;
 *   • a block's length is the organiser's, because no draw can imply how long
 *     a medal ceremony takes.
 *
 * Status is read from the CLOCK here. That is the honest answer before an
 * event starts and a decent one during it, but it is only half the truth: a
 * stage really ends when its last bout is scored. Reading that from the
 * scoreboard is a separate, later step — when it lands it overrides `status`
 * here, and nothing else about this class changes.
 *
 * Self-contained and read-only: it writes nothing, and every caller (the
 * organiser's board, the public page, the event console) gets the same rows.
 */
class RunSheet
{
    /** Minutes between one part ending and the next beginning. */
    public const TURNAROUND = 6;

    /** What a bout takes when the event has not said. */
    public const DEFAULT_BOUT_MINUTES = 5;

    /**
     * Every part of one day, in order, with the clock worked out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(ClubEvent $event, int $day = 1, ?Carbon $now = null): array
    {
        $slots = EventRunSlot::query()
            ->where('event_id', $event->id)
            ->where('day', $day)
            ->orderBy('position')
            ->orderBy('id')
            /*
             * ⚠️ `event_id` is not optional in this select. A division's name
             * is translated through its EVENT's document, which it reaches via
             * `$this->event` — and a constrained eager load that omits the
             * foreign key leaves the relation unresolvable, so every division
             * silently renders in the language it was typed in while the blocks
             * beside it translate correctly. Same trap as the one the stat
             * cards hit by omitting `updated_at`.
             */
            ->with('category:id,event_id,name')
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        $bouts = $this->boutCounts($event);
        $entrants = $this->entrantCounts($event);
        $boutMinutes = (int) ($event->minutes_per_match ?: self::DEFAULT_BOUT_MINUTES);

        $cursor = $this->dayStartMinutes($event);
        $rows = [];

        foreach ($slots as $slot) {
            $duration = $this->duration($slot, $bouts, $entrants, $boutMinutes);

            /*
             * A PIN IS THE TIME. When the organiser has said this part happens
             * at 12:10, it is shown at 12:10 — full stop.
             *
             * It used to be clamped forward whenever our own estimate of the
             * preceding bouts overran it, which quietly rewrote the organiser's
             * published plan into our arithmetic about it. That is backwards:
             * the estimate is a guess and the pin is a decision, and a
             * competition whose printed sheet says 12:10 must not show 12:31
             * because we think a division takes six minutes longer than they do.
             *
             * Unpinned parts still flow from wherever the day has reached, so
             * the derived half of the sheet is unchanged.
             */
            $startsAt = $slot->pinned_at !== null ? $slot->pinned_at : $cursor;

            $cursor = $startsAt + $duration;

            $rows[] = [
                'uuid' => $slot->uuid,
                'kind' => $slot->kind,
                'name' => $slot->displayName(),
                'category_id' => $slot->category_id,
                'is_division' => $slot->isDivision(),
                'pinned' => $slot->pinned_at !== null,
                'starts_at' => $startsAt,
                'duration' => $duration,
                'ends_at' => $startsAt + $duration,
                'entrants' => $slot->category_id ? ($entrants[$slot->category_id] ?? 0) : null,
                'bouts' => $slot->category_id ? ($bouts[$slot->category_id] ?? 0) : null,
                // A division whose category has been deleted underneath it.
                // Shown, never hidden: a silent gap in a running order is how
                // somebody misses their own bout.
                'orphaned' => $slot->kind === EventRunSlot::KIND_DIVISION && $slot->category_id === null,
            ];
        }

        return $this->withStatus($event, $rows, $day, $now);
    }

    /** When the day's last part ends, in minutes from midnight. */
    public function finishesAt(ClubEvent $event, int $day = 1): ?int
    {
        $rows = $this->rows($event, $day);

        return $rows ? (int) end($rows)['ends_at'] : null;
    }

    /**
     * Divisions of this event that no slot on any day has claimed yet.
     *
     * The board's bench. Nothing is ever auto-scheduled — an organiser adds
     * what they mean to run, so a division created by mistake never appears on
     * a published running order on its own.
     *
     * @return Collection<int, EventCategory>
     */
    public function unscheduled(ClubEvent $event): Collection
    {
        $taken = EventRunSlot::where('event_id', $event->id)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->all();

        return EventCategory::query()
            ->where('event_id', $event->id)
            ->when($taken, fn ($q) => $q->whereNotIn('id', $taken))
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /** How many days this event's running order covers. */
    public function days(ClubEvent $event): array
    {
        $days = EventRunSlot::where('event_id', $event->id)
            ->distinct()->orderBy('day')->pluck('day')->all();

        return $days ?: [1];
    }

    /* ---------------- internals ---------------- */

    /**
     * Mark each row done / now / upcoming against the wall clock.
     *
     * Before the day: everything is upcoming. After it: everything is done.
     * Neither is a guess — a running order for next Friday has not started,
     * and one from last month has finished.
     */
    private function withStatus(ClubEvent $event, array $rows, int $day, ?Carbon $now): array
    {
        /*
         * ⚠️ The sheet's times are the HALL's wall clock (10:00 means 10:00 in
         * Manama), so "now" must be read on that clock too. The app runs in
         * UTC; comparing a UTC reading against local minutes put the "now"
         * marker three hours behind the hall on 18 Sep 2026 — competitors were
         * told their division was on when it was not.
         */
        $tz = self::timezone($event);
        $now = ($now ?? Carbon::now())->copy()->setTimezone($tz);
        $date = $event->date ? Carbon::parse($event->date->toDateString(), $tz)->addDays($day - 1)->startOfDay() : null;

        $state = 'upcoming';
        if ($date) {
            if ($now->lt($date)) {
                $state = 'before';
            } elseif ($now->gte($date->copy()->addDay())) {
                $state = 'after';
            } else {
                $state = 'during';
            }
        }

        $minutes = (int) ($now->hour * 60 + $now->minute);

        foreach ($rows as $i => $row) {
            $status = 'upcoming';

            if ($state === 'after') {
                $status = 'done';
            } elseif ($state === 'during') {
                if ($minutes >= $row['ends_at']) {
                    $status = 'done';
                } elseif ($minutes >= $row['starts_at']) {
                    $status = 'now';
                }
            }

            $rows[$i]['status'] = $status;
        }

        return $rows;
    }

    /** The timezone the event's clock times are written in: its host's, else the app's. */
    public static function timezone(ClubEvent $event): string
    {
        return $event->zone();
    }

    private function dayStartMinutes(ClubEvent $event): int
    {
        if (! $event->start_time) {
            return 9 * 60;
        }

        $t = Carbon::parse($event->start_time);

        return $t->hour * 60 + $t->minute;
    }

    private function duration(EventRunSlot $slot, array $bouts, array $entrants, int $boutMinutes): int
    {
        if (! $slot->isDivision()) {
            return (int) ($slot->duration_minutes
                ?: (EventRunSlot::DEFAULT_DURATIONS[$slot->kind] ?? 15));
        }

        // The draw is the better answer when it exists; before it is built,
        // a knockout of n entrants is n-1 contested bouts.
        $count = $bouts[$slot->category_id] ?? 0;
        if ($count < 1) {
            $count = max(1, ($entrants[$slot->category_id] ?? 2) - 1);
        }

        return $count * $boutMinutes + self::TURNAROUND;
    }

    /**
     * Contested bouts per division — a pairing with only one name in it is a
     * bye, and a bye takes no mat time.
     */
    private function boutCounts(ClubEvent $event): array
    {
        return EventMatch::query()
            ->where('event_id', $event->id)
            ->whereNotNull('a_name')->where('a_name', '!=', '')
            ->whereNotNull('b_name')->where('b_name', '!=', '')
            ->selectRaw('category_id, COUNT(*) as c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    private function entrantCounts(ClubEvent $event): array
    {
        return \App\Events\Models\ClubEventRegistration::query()
            ->where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }
}
