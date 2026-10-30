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
 * Status is read from the CLOCK before anything has been fought. Once bouts
 * have been timed on the day, the MATS are the truth (owner, 2026-10-25: "make
 * it realistic — it's impossible to be way beyond the expected when the actual
 * is way less"): a division that has run shows when it really ran, the one on
 * the mat its real start plus what is left at the pace the day is actually
 * keeping, and everything after it flows from there (withActuals). The plan's
 * own guesses — the bout length, the turnaround — then step aside.
 *
 * Self-contained and read-only: it writes nothing, and every caller (the
 * organiser's board, the public page, the event console) gets the same rows.
 */
class RunSheet
{
    /** Minutes between one part ending and the next beginning. */
    public const TURNAROUND = 6;

    /** A division's own podium, presented at the mat once its final is decided. */
    public const AWARDING_MINUTES = 5;

    /** What a bout takes when the event has not said. */
    public const DEFAULT_BOUT_MINUTES = 5;

    /**
     * Every part of one day, in order, with the clock worked out.
     *
     * @return array<int, array<string, mixed>>
     */
    /** Bouts per division and part, for the slot being worked out (BoutPart::counts). */
    private array $partCounts = [];

    /** @var list<int> divisions a medal ceremony for everyone awards (RunOrder::awardedTogether) */
    private array $awardedTogether = [];

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
        $this->awardedTogether = app(RunOrder::class)->awardedTogether($event->id);
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

        $rows = $this->withActuals($event, $slots, $rows, $day, $now, $boutMinutes);
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

            // What the mats say outranks what the clock says (withActuals).
            if (isset($row['actual_status'])) {
                $rows[$i]['status'] = $row['actual_status'];
                unset($rows[$i]['actual_status']);

                continue;
            }

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

    /**
     * A division's podium at the mat: a few minutes, not a ceremony's — and
     * nothing when a medal ceremony for everyone awards it later in the day.
     */
    private function awarding(EventRunSlot $slot): int
    {
        if (! $slot->parts || ! in_array(BoutPart::AWARDING, $slot->parts, true)) {
            return 0;
        }
        if (in_array((int) $slot->category_id, $this->awardedTogether, true)) {
            return 0;
        }

        // Placed on the day as its own part, it is a ceremony; at the end of the
        // division's own bouts, it is a podium at the mat.
        return array_intersect($slot->parts, BoutPart::ALL) === []
            ? (int) (EventRunSlot::DEFAULT_DURATIONS[EventRunSlot::KIND_CEREMONY] ?? 15)
            : self::AWARDING_MINUTES;
    }

    /**
     * The day as the mats have actually run it.
     *
     * Nothing changes until a bout has been timed on this day — before that
     * the plan is all there is. After it:
     *  · a division whose bouts are all decided shows its real first start and
     *    last bell, and is done;
     *  · a division under way shows its real start, and ends after what is
     *    left of it at the day's own pace (no earlier than now);
     *  · every part not yet begun flows from the latest of those — never
     *    before now on the day — at that pace. A pinned part keeps its time.
     * The pace is measured, not assumed: the average time a bout took on the
     * mat plus the typical changeover between bouts (pauses longer than half
     * an hour — a break, a ceremony — are not changeovers).
     */
    private function withActuals(ClubEvent $event, Collection $slots, array $rows, int $day, ?Carbon $now, int $boutMinutes): array
    {
        $timed = $this->boutTimes($event, $day);
        if ($timed === []) {
            return $rows;
        }

        $tz = self::timezone($event);
        $now = ($now ?? Carbon::now())->copy()->setTimezone($tz);
        $date = $event->date ? Carbon::parse($event->date->toDateString(), $tz)->addDays($day - 1)->startOfDay() : null;
        $nowMin = $date && $now->isSameDay($date) ? $now->hour * 60 + $now->minute : null;

        $pace = $this->pace($timed) ?? (float) $boutMinutes;
        $tally = $this->tally($event, $day);

        $cursor = min(array_column($timed, 'start'));
        foreach ($rows as $i => $row) {
            $slot = $slots[$i];

            if ($slot->isDivision() && $slot->category_id) {
                $parts = array_values(array_intersect($slot->parts ?: BoutPart::ALL, BoutPart::ALL));
                $mine = array_filter($timed, fn ($t) => $t['cat'] === (int) $slot->category_id && in_array($t['part'], $parts, true));
                $total = $decided = 0;
                foreach ($parts as $p) {
                    $total += $tally[$slot->category_id][$p]['total'] ?? 0;
                    $decided += $tally[$slot->category_id][$p]['decided'] ?? 0;
                }
                $awarding = $this->awarding($slot);

                if ($mine !== [] || ($total > 0 && $decided >= $total)) {
                    $start = $mine ? min(array_column($mine, 'start')) : $cursor;
                    $lastBell = $mine ? max(array_filter(array_column($mine, 'end'), 'is_int') ?: [$start]) : $start;

                    if ($decided >= $total) {
                        $end = $lastBell;
                        $status = 'done';
                    } else {
                        $end = (int) ceil(max($lastBell, $nowMin ?? 0) + ($total - $decided) * $pace + $awarding);
                        $status = 'now';
                    }
                    $rows[$i] = ['starts_at' => $start, 'ends_at' => $end, 'duration' => max(0, $end - $start),
                        'actual' => true, 'actual_status' => $status] + $rows[$i];
                    $cursor = max($cursor, $end);

                    continue;
                }

                $duration = $total > 0 ? (int) ceil($total * $pace) + $awarding : $row['duration'];
            } else {
                $duration = $row['duration'];
            }

            $start = $slot->pinned_at !== null ? (int) $slot->pinned_at : max($cursor, $nowMin ?? 0);
            $rows[$i] = ['starts_at' => $start, 'ends_at' => $start + $duration, 'duration' => $duration, 'actual' => false] + $rows[$i];
            $cursor = max($cursor, $start + $duration);
        }

        return $rows;
    }

    /**
     * When each bout of the day really started and finished, in minutes after
     * the day's local midnight — from the mats' officiating log, or for a bout
     * fought as an exhibition and linked to it, from that exhibition.
     *
     * @return list<array{cat: int, part: string, court: string, start: int, end: ?int}>
     */
    private function boutTimes(ClubEvent $event, int $day): array
    {
        if (! $event->date) {
            return [];
        }
        $tz = self::timezone($event);
        $from = Carbon::parse($event->date->toDateString(), $tz)->addDays($day - 1)->startOfDay();
        $to = $from->copy()->addDay();
        $min = function ($utc) use ($tz, $from): int {
            $t = Carbon::parse((string) $utc, 'UTC')->setTimezone($tz);

            return (int) round(($t->getTimestamp() - $from->getTimestamp()) / 60);
        };

        $bouts = EventMatch::where('event_id', $event->id)->whereNotNull('category_id')
            ->get(['id', 'category_id', 'round', 'court', 'day'])->keyBy('id');

        $log = \Illuminate\Support\Facades\DB::table('event_match_events')
            ->where('event_id', $event->id)->whereNotNull('match_id')
            ->whereIn('command', ['load', 'start', 'finish'])
            ->whereBetween('occurred_at', [$from->copy()->utc()->format('Y-m-d H:i:s'), $to->copy()->utc()->format('Y-m-d H:i:s')])
            ->orderBy('occurred_at')
            ->get(['match_id', 'command', 'occurred_at']);

        // The attempt that counted: a bout loaded (or tried) early and fought
        // later is timed from its last load, as the analytics replay it.
        $times = [];
        foreach ($log->groupBy('match_id') as $id => $rows) {
            $loadAt = $clockAt = $start = $end = null;
            foreach ($rows as $e) {
                if ($e->command === 'load') {
                    $loadAt = $e->occurred_at;
                    $clockAt = null;
                } elseif ($e->command === 'start') {
                    $clockAt ??= $e->occurred_at;
                } else {
                    $start = $clockAt ?? $loadAt;
                    $end = $e->occurred_at;
                }
            }
            $start ??= $clockAt ?? $loadAt;
            if ($start) {
                $times[(int) $id] = [$min($start), $end ? $min($end) : null];
            }
        }

        // A bout fought as an exhibition and linked to the draw ran when the exhibition did.
        if (\Illuminate\Support\Facades\Schema::hasColumn('event_exhibition_bouts', 'linked_match_id')) {
            foreach (\Illuminate\Support\Facades\DB::table('event_exhibition_bouts')->where('event_id', $event->id)
                ->where('status', 'linked')->whereNotNull('linked_match_id')
                ->whereBetween('created_at', [$from->copy()->utc()->format('Y-m-d H:i:s'), $to->copy()->utc()->format('Y-m-d H:i:s')])
                ->get(['linked_match_id', 'created_at', 'finished_at']) as $x) {
                $times[(int) $x->linked_match_id] ??= [$min($x->created_at), $x->finished_at ? $min($x->finished_at) : null];
            }
        }

        $out = [];
        foreach ($times as $id => [$start, $end]) {
            $b = $bouts->get($id);
            if (! $b || ((int) ($b->day ?: 1)) !== $day) {
                continue;
            }
            $out[] = ['cat' => (int) $b->category_id, 'part' => BoutPart::of($b->round), 'court' => (string) $b->court,
                'start' => $start, 'end' => $end !== null && $end >= $start ? $end : null];
        }

        return $out;
    }

    /**
     * Minutes a bout really costs the day: the average time on the mat plus
     * the typical (median) changeover between one bout and the next on the
     * same mat. Null until three bouts have run — too little to measure.
     */
    private function pace(array $timed): ?float
    {
        $done = array_values(array_filter($timed, fn ($t) => $t['end'] !== null));
        if (count($done) < 3) {
            return null;
        }

        $wall = array_sum(array_map(fn ($t) => $t['end'] - $t['start'], $done)) / count($done);

        $gaps = [];
        foreach (collect($done)->groupBy('court') as $mat) {
            $mat = $mat->sortBy('start')->values();
            for ($k = 1; $k < $mat->count(); $k++) {
                $g = $mat[$k]['start'] - $mat[$k - 1]['end'];
                if ($g >= 0 && $g < 30) {
                    $gaps[] = $g;
                }
            }
        }
        sort($gaps);
        $gap = $gaps ? $gaps[intdiv(count($gaps), 2)] : 0;

        return max(1.0, $wall + $gap);
    }

    /**
     * Contested bouts per division and part, and how many of them are decided.
     *
     * @return array<int, array<string, array{total: int, decided: int}>>
     */
    private function tally(ClubEvent $event, int $day): array
    {
        $out = [];
        foreach (EventMatch::where('event_id', $event->id)->whereNotNull('category_id')
            ->whereNotNull('a_name')->where('a_name', '!=', '')
            ->whereNotNull('b_name')->where('b_name', '!=', '')
            ->get(['category_id', 'round', 'winner', 'won_by_bye']) as $m) {
            $p = BoutPart::of($m->round);
            $out[$m->category_id][$p]['total'] = ($out[$m->category_id][$p]['total'] ?? 0) + 1;
            $out[$m->category_id][$p]['decided'] = ($out[$m->category_id][$p]['decided'] ?? 0) + ($m->winner ? 1 : 0);
        }

        return $out;
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

            $awarding = $this->awarding($slot);

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
