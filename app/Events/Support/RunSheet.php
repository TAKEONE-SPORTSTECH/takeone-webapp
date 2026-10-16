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
    /** Bouts per division and part, for the slot being worked out (BoutPart::counts). */
    private array $partCounts = [];

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
            ->with('category:id,event_id,name,gender')
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        // With groups on, a group's classes run straight after its header,
        // wherever their own positions put them (RunSessions::ordered).
        if (RunSessions::enabled()) {
            $slots = RunSessions::ordered($slots);
        }

        $bouts = $this->boutCounts($event);
        $this->partCounts = BoutPart::counts($event->id);
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
                // The division's gender, for the board's card colour: 'male',
                // 'female', or null (mixed or not said).
                'gender' => $slot->isDivision() ? $this->genderOf($slot->category) : null,
                'is_division' => $slot->isDivision(),
                'pinned' => $slot->pinned_at !== null,
                'starts_at' => $startsAt,
                'duration' => $duration,
                'ends_at' => $startsAt + $duration,
                'entrants' => $slot->category_id ? ($entrants[$slot->category_id] ?? 0) : null,
                'bouts' => $slot->category_id
                    ? ($slot->parts
                        ? array_sum(array_map(fn ($p) => $this->partCounts[$slot->category_id][$p] ?? 0, $slot->parts))
                        : ($bouts[$slot->category_id] ?? 0))
                    : null,
                // The event's own break (club_events.break_start), on the day.
                'is_event_break' => $slot->kind === EventRunSlot::KIND_BREAK && $slot->pinned_at !== null
                    && $event->break_start && $slot->pinned_at === ((int) substr((string) $event->break_start, 0, 2)) * 60 + (int) substr((string) $event->break_start, 3, 2),
                // Which parts of the division run here; null is all of it.
                'parts' => $slot->isDivision() ? $slot->parts : null,
                'parts_label' => $slot->isDivision() ? BoutPart::labels($slot->parts) : null,
                // A division whose category has been deleted underneath it.
                // Shown, never hidden: a silent gap in a running order is how
                // somebody misses their own bout.
                'orphaned' => $slot->kind === EventRunSlot::KIND_DIVISION && $slot->category_id === null,
            ];
        }

        $rows = $this->withSessions($event, $slots, $rows, $day);

        return $this->withStatus($event, $rows, $day, $now);
    }

    /**
     * Which GROUP each row sits in and how that group calls its bouts
     * (App\Events\Support\RunSessions). A group row carries `group_head`
     * (its method and how many classes it holds); every row under it carries
     * `group` (the group's uuid) and `session_mode`.
     *
     * In a group that is NOT one class at a time, a class's bouts are spread
     * across the whole group, so each class row reads the group's span — its
     * start to its end — rather than a block of its own that the mat will not
     * actually run.
     */
    private function withSessions(ClubEvent $event, Collection $slots, array $rows, int $day): array
    {
        $enabled = RunSessions::enabled();
        $groups = $slots->where('kind', EventRunSlot::KIND_GROUP)->keyBy('id');
        $heads = [];

        foreach ($rows as $i => $row) {
            $slot = $slots[$i];
            if ($slot->kind === EventRunSlot::KIND_GROUP) {
                $rows[$i]['group_head'] = $enabled ? ['mode' => RunSessions::normalise($slot->mode), 'size' => 0] : null;
                $rows[$i]['group'] = null;
                $rows[$i]['session_mode'] = null;
                $heads[$slot->id] = $i;

                continue;
            }
            $group = $enabled && $slot->group_id !== null ? $groups->get($slot->group_id) : null;
            $rows[$i]['group'] = $group?->uuid;
            $rows[$i]['session_mode'] = $group ? RunSessions::normalise($group->mode) : RunSessions::CLASS_AT_A_TIME;
            if ($group && $row['is_division']) {
                $rows[$heads[$group->id]]['group_head']['size']++;
            }
        }

        // The span of each mixed group, over its division rows.
        $span = [];
        foreach ($rows as $row) {
            if (! $row['is_division'] || $row['group'] === null
                || $row['session_mode'] === RunSessions::CLASS_AT_A_TIME) {
                continue;
            }
            $g = $row['group'];
            $span[$g] = [
                min($span[$g][0] ?? PHP_INT_MAX, $row['starts_at']),
                max($span[$g][1] ?? 0, $row['ends_at']),
            ];
        }
        foreach ($rows as $i => $row) {
            if ($row['is_division'] && $row['group'] !== null && isset($span[$row['group']])) {
                [$rows[$i]['starts_at'], $rows[$i]['ends_at']] = $span[$row['group']];
                $rows[$i]['interleaved'] = true;
            }
        }

        return $rows;
    }

    /**
     * 'male', 'female' or null. The division's own column first; a division
     * that never set one is read from its name ("Cadet Kumite Male -52 kg"),
     * the way every generated division is named. Anything else is null.
     */
    private function genderOf(?EventCategory $category): ?string
    {
        $g = strtolower((string) ($category?->gender ?? ''));
        if (in_array($g, ['male', 'female'], true)) {
            return $g;
        }

        $name = (string) ($category?->getRawOriginal('name') ?? '');

        return match (true) {
            (bool) preg_match('/\b(female|women|girls?)\b/i', $name) => 'female',
            (bool) preg_match('/\b(male|men|boys?)\b/i', $name) => 'male',
            default => null,
        };
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
        // A division leaves the bench only once EVERY part of it is on the day
        // (BoutPart); a heading holds nobody and was never one to place.
        $order = app(RunOrder::class);

        return EventCategory::query()
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('is_heading')->orWhere('is_heading', false))
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'event_id', 'name'])
            ->each(function ($c) use ($order) {
                $c->remaining_parts = $order->remainingParts($c);
                $c->present_parts = BoutPart::present($c);
            })
            ->filter(fn ($c) => $c->remaining_parts !== [])
            ->values();
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
        // A group heads its classes; it takes no time of its own.
        if ($slot->kind === EventRunSlot::KIND_GROUP) {
            return 0;
        }

        if (! $slot->isDivision()) {
            return (int) ($slot->duration_minutes
                ?: (EventRunSlot::DEFAULT_DURATIONS[$slot->kind] ?? 15));
        }

        // Part of a division: the bouts in those parts (BoutPart), byes aside.
        if ($slot->parts) {
            $byPart = $this->partCounts[$slot->category_id] ?? [];
            $count = array_sum(array_map(fn ($p) => $byPart[$p] ?? 0, $slot->parts));
            $bouts = array_intersect($slot->parts, BoutPart::ALL) !== [] ? max(1, $count) * $boutMinutes + self::TURNAROUND : 0;

            // The division's awarding takes a ceremony's time.
            $awarding = in_array(BoutPart::AWARDING, $slot->parts, true)
                ? (int) (EventRunSlot::DEFAULT_DURATIONS[EventRunSlot::KIND_CEREMONY] ?? 15)
                : 0;

            return max(1, $bouts + $awarding);
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
