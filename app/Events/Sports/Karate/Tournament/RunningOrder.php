<?php

namespace App\Events\Sports\Karate\Tournament;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Sports\Combat\Engine\Scheduler;
use Carbon\Carbon;

/**
 * "When am I on, and where?" — the only question an athlete has all day.
 *
 * The answer is a countdown, not a fixture list: which mat, which bout number,
 * how many bouts are still ahead of theirs on that mat, and roughly how long
 * that is. The bout count is the honest number and leads; the time is derived
 * from it and is explicitly an estimate, because a mat that runs slow makes any
 * fixed clock time a lie within the first hour.
 *
 * Because it counts from bouts that are still UNDECIDED, the estimate drifts
 * with the real day as results are entered — which is the whole point.
 */
class RunningOrder
{
    private ?Scheduler $scheduler = null;

    /**
     * The competition day the hall is on. Resolved once per instance — a board
     * repaint asks for it on every mat.
     */
    public function today(ClubEvent $event): int
    {
        return ($this->scheduler ??= app(Scheduler::class))->currentDay($event);
    }

    /**
     * The athlete's next bout, or null when they have none left.
     *
     * @return array{
     *     match_id: int, court: ?string, day: ?int, bout_no: ?int, code: ?string, round: string,
     *     division: ?string, opponent: ?string, corner: string,
     *     bouts_ahead: int, eta_minutes: ?int, eta_at: ?string, is_next: bool
     * }|null
     */
    public function nextFor(ClubEvent $event, ClubEventRegistration $entry): ?array
    {
        $bout = $this->upcomingBouts($event)
            ->first(fn (EventMatch $m) => $m->a_competitor_id === $entry->id || $m->b_competitor_id === $entry->id);

        if (! $bout) {
            return null;
        }

        $corner = $bout->a_competitor_id === $entry->id ? 'a' : 'b';
        $otherSide = $corner === 'a' ? 'b' : 'a';
        $ahead = $this->boutsAhead($event, $bout);

        // A bout on a later day of the competition has no meaningful countdown:
        // the minutes between now and it are mostly a night. The athlete is told
        // which day it falls on instead, and "you are next" is reserved for the
        // day it is actually being fought — announcing it the evening before is
        // how someone ends up at the mat twelve hours early.
        $isToday = $bout->day === null || $bout->day === $this->today($event);
        $eta = $isToday ? $this->eta($event, $ahead) : null;

        return [
            'match_id' => $bout->id,
            'court' => $bout->court,
            'day' => $bout->day,
            'bout_no' => $bout->match_no,
            'code' => $this->code($bout),
            'round' => $bout->round,
            'division' => $bout->category?->name,
            // Null when the feeder bout hasn't been decided — "winner of bout 1-08".
            'opponent' => $bout->{$otherSide.'_name'},
            'corner' => $corner === 'a' ? 'red' : 'blue',
            'bouts_ahead' => $ahead,
            'eta_minutes' => $eta,
            'eta_at' => $eta !== null ? now()->addMinutes($eta)->toIso8601String() : null,
            'is_next' => $ahead === 0 && $isToday,
        ];
    }

    /**
     * How many undecided bouts sit in front of this one on the same mat, on the
     * same day.
     *
     * Counts only bouts that have NOT been decided, so the number falls as the
     * day is scored rather than as the clock runs.
     *
     * The day is part of the comparison because bout numbers RESTART each
     * morning: without it, tomorrow's Mat 1 bout #1 counts as being ahead of
     * today's Mat 1 bout #4, and every athlete on that mat is quoted a wait
     * that includes a competition day they are not fighting in.
     */
    public function boutsAhead(ClubEvent $event, EventMatch $bout): int
    {
        if (! $bout->court || $bout->match_no === null) {
            return 0;
        }

        return $this->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->court === $bout->court
                && $m->day === $bout->day
                && $m->match_no !== null
                && $m->match_no < $bout->match_no)
            ->count();
    }

    /**
     * Minutes until the bout is likely called, from the number of bouts ahead
     * and the event's own per-bout allowance — plus the break, when the wait
     * would run through it.
     */
    public function eta(ClubEvent $event, int $boutsAhead): ?int
    {
        $perBout = (int) ($event->minutes_per_match ?: config('combat.defaults.minutes_per_match', 8));
        $minutes = $boutsAhead * $perBout;

        return $minutes + $this->breakDelay($event, $minutes);
    }

    /** Break minutes that fall inside the wait, else zero. */
    private function breakDelay(ClubEvent $event, int $minutes): int
    {
        if (! $event->break_start || ! $event->break_end) {
            return 0;
        }

        $start = Carbon::parse($event->break_start);
        $end = Carbon::parse($event->break_end);

        $breakStartsAt = now()->copy()->setTime((int) $start->format('H'), (int) $start->format('i'));
        $callAt = now()->copy()->addMinutes($minutes);

        // The wait runs through the break window → add its length.
        return $callAt->gt($breakStartsAt) && now()->lt($breakStartsAt)
            ? (int) $start->diffInMinutes($end)
            : 0;
    }

    /**
     * Everything still to be fought, across every day, in running order.
     *
     * A bout is "upcoming" until it has a winner — a walkover is already
     * decided at draw time and never appears.
     *
     * DAY LEADS the sort, and must. A bout's place in the running order is
     * (day, mat, number), because the scheduler numbers each mat afresh every
     * morning — sorting by number alone interleaves the days and produces a
     * list with two bout #1s in it. Callers that are about the hall RIGHT NOW
     * want matQueue(), which narrows this to today; this one spans the event
     * because an athlete asking "when am I on" is owed an answer even when the
     * answer is tomorrow.
     */
    public function upcomingBouts(ClubEvent $event)
    {
        return EventMatch::where('event_id', $event->id)
            ->whereNull('winner')
            ->where('status', '!=', 'done')
            ->with('category:id,name')
            ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN day IS NULL THEN 1 ELSE 0 END')
            ->orderBy('day')
            ->orderBy('court')
            ->orderBy('match_no')
            ->orderBy('slot')
            ->get();
    }

    /**
     * One mat's queue — the SINGLE definition of what is next on that mat.
     *
     * Three surfaces have to agree about this or the hall sees a contradiction:
     * the wall board announces "GET READY" over a bout, the scoring table lists
     * what to load, and committing a result calls the next one up. They used to
     * each decide for themselves, and they disagreed — the board would announce
     * a bout that was still waiting on a feeder while the table skipped past it
     * to the next runnable one. An official then pressed "next bout" and got two
     * different names from the ones the hall had just been told to expect.
     *
     * RUNNABLE FIRST. A bout with a side still to be decided cannot be called —
     * nobody can walk out for "winner of bout 3" — so it is not what is next,
     * whatever its number says. It stays in the queue, visible, just not at the
     * front. Within each group the running order is preserved, because PHP's
     * sort is stable.
     *
     * ONE DAY ONLY. This is the queue for a mat as it stands in the hall, and a
     * hall only ever runs one competition day at a time. Bout numbers restart
     * each morning, so a queue spanning both days shows two bout #1s and reads
     * as corrupt from ten metres — and worse, it lets a bout scheduled for
     * tomorrow reach the front, where the board announces GET READY over it and
     * the scoring table offers to load it. Pass `$day` to look at another day
     * deliberately; the default is the day the venue is on.
     *
     * A bout with NO day is shown on every day rather than hidden from all of
     * them. It means the scheduler has not run over it, which is a data fault —
     * and the failure mode of dropping it is a mat that quietly stops listing a
     * bout that is still going to be fought. Duplicated on a wall is a question
     * someone asks; missing from a wall is a competitor who is never called.
     */
    public function matQueue(ClubEvent $event, string $court, ?int $day = null)
    {
        $day ??= $this->today($event);

        return $this->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->court === $court && ($m->day === null || $m->day === $day))
            ->sortBy(fn (EventMatch $m) => ($m->a_competitor_id && $m->b_competitor_id) ? 0 : 1)
            ->values();
    }

    /** True when both corners are known, so the bout can actually be called. */
    public function isRunnable(EventMatch $m): bool
    {
        return (bool) ($m->a_competitor_id && $m->b_competitor_id);
    }

    /**
     * The venue board for one mat: what is on now, and what is queued behind it.
     *
     * Addressed by PLACE, not by person — nobody is logged in to a hall screen,
     * so it carries names and bout numbers only, never anything personal.
     *
     * @return array{court: string, now: ?array, on_deck: array<int, array>}
     */
    public function matBoard(ClubEvent $event, string $court, int $upcoming = 4): array
    {
        $queue = $this->matQueue($event, $court);

        $row = fn (?EventMatch $m) => $m ? [
            'code' => $this->code($m),
            'bout_no' => $m->match_no,
            'round' => $m->round,
            'division' => $m->category?->name,
            'red' => $m->a_name,
            'blue' => $m->b_name,
            'status' => $m->status,
        ] : null;

        return [
            'court' => $court,
            'now' => $row($queue->first()),
            'on_deck' => $queue->slice(1, $upcoming)->map($row)->values()->all(),
        ];
    }

    /**
     * Every mat currently in play, in order — the whole-venue board.
     *
     * The mats are those in play TODAY. A mat that is only used on the second
     * day of the competition is not a mat the venue is running now, and listing
     * it with an empty queue reads as a mat that has finished.
     *
     * @return array<int, array>
     */
    public function venueBoard(ClubEvent $event, int $upcoming = 4): array
    {
        $today = $this->today($event);

        return $this->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->day === null || $m->day === $today)
            ->pluck('court')->filter()->unique()->sort()->values()
            ->map(fn (string $court) => $this->matBoard($event, $court, $upcoming))
            ->all();
    }

    /** Mat number + bout number, as the callers announce it ("Mat 1, bout 4" → 1-04). */
    private function code(EventMatch $bout): ?string
    {
        $courtNo = ($bout->court && preg_match('/(\d+)/', $bout->court, $m)) ? (int) $m[1] : null;

        return ($courtNo && $bout->match_no)
            ? $courtNo.'-'.str_pad((string) $bout->match_no, 2, '0', STR_PAD_LEFT)
            : null;
    }

    /**
     * Entries whose bout has come within a notification threshold on this mat,
     * keyed by threshold — the input to the warm-up and call-room pushes.
     *
     * @return array<int, array<int, array{entry_id: int, bout: EventMatch, ahead: int}>>
     */
    public function callsDue(ClubEvent $event, ?string $court = null): array
    {
        $thresholds = array_keys(config('event_notifications.call_thresholds', []));
        if (! $thresholds) {
            return [];
        }

        $due = [];
        $today = $this->today($event);

        foreach ($this->upcomingBouts($event) as $bout) {
            if ($court && $bout->court !== $court) {
                continue;
            }

            // Never call an athlete for a bout on a later day. boutsAhead() is
            // day-scoped, so tomorrow's first bout on a mat sits at zero bouts
            // ahead and would otherwise trip every warm-up and call-room
            // threshold at once, the day before it is fought.
            if ($bout->day !== null && $bout->day !== $today) {
                continue;
            }

            $ahead = $this->boutsAhead($event, $bout);

            foreach ($thresholds as $threshold) {
                if ($ahead > $threshold) {
                    continue;
                }
                foreach ($bout->competitorIds() as $entryId) {
                    $due[$threshold][] = ['entry_id' => $entryId, 'bout' => $bout, 'ahead' => $ahead];
                }
            }
        }

        return $due;
    }
}
