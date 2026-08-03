<?php

namespace App\Events\Sports\Taekwondo\Tournament;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
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
    /**
     * The athlete's next bout, or null when they have none left.
     *
     * @return array{
     *     match_id: int, court: ?string, bout_no: ?int, code: ?string, round: string,
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
        $eta = $this->eta($event, $ahead);

        return [
            'match_id' => $bout->id,
            'court' => $bout->court,
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
            'is_next' => $ahead === 0,
        ];
    }

    /**
     * How many undecided bouts sit in front of this one on the same mat.
     *
     * Counts only bouts that have NOT been decided, so the number falls as the
     * day is scored rather than as the clock runs.
     */
    public function boutsAhead(ClubEvent $event, EventMatch $bout): int
    {
        if (! $bout->court || $bout->match_no === null) {
            return 0;
        }

        return $this->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->court === $bout->court
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
     * Everything still to be fought, in running order.
     *
     * A bout is "upcoming" until it has a winner — a walkover is already
     * decided at draw time and never appears.
     */
    public function upcomingBouts(ClubEvent $event)
    {
        return EventMatch::where('event_id', $event->id)
            ->whereNull('winner')
            ->where('status', '!=', 'done')
            ->with('category:id,name')
            ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('court')
            ->orderBy('match_no')
            ->orderBy('slot')
            ->get();
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
        $queue = $this->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->court === $court)
            ->values();

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
     * @return array<int, array>
     */
    public function venueBoard(ClubEvent $event, int $upcoming = 4): array
    {
        return $this->upcomingBouts($event)
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

        foreach ($this->upcomingBouts($event) as $bout) {
            if ($court && $bout->court !== $court) {
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
