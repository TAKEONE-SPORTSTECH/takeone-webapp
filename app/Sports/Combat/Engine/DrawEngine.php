<?php

namespace App\Sports\Combat\Engine;

use App\Events\Support\EventFee;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Sports\Combat\SportRegistry;

/**
 * Single-elimination draw generation + lifecycle for a combat championship.
 * Sport-agnostic: stable seeded spread, byes to the next power of two, and the
 * provisional → final → locked lifecycle.
 */
class DrawEngine
{
    public function __construct(private Scheduler $scheduler, private GroupEngine $groups, private SportRegistry $sports) {}

    /** The round a play-off for bronze is held under. */
    public const ROUND_THIRD = 'Third place';

    /**
     * Keep each division's draw current:
     *  - before start: a PROVISIONAL draw including unpaid entrants (rebuilt when the entrant set changes)
     *  - at/after start: a FINAL paid-only draw, built once then locked
     * Manually-built brackets (no draw_state but existing matches) are never touched.
     */
    public function ensure(ClubEvent $event): void
    {
        if (! $event->isCombat() || $event->hasEnded()) {
            return;
        }

        $started = $event->hasStarted();
        $changed = false;

        foreach ($event->categories()->withCount('matches')->get() as $cat) {
            // A heading is a title in the organiser's list of divisions, not a
            // division: nothing is entered in it and there is nothing to draw.
            if ($cat->isHeading()) {
                continue;
            }

            if ($cat->draw_state === null && $cat->matches_count > 0) {
                continue; // hand-built bracket
            }

            // A draw an organiser arranged by hand is theirs. It is never
            // re-cut — not when an entrant joins, and not when the event starts
            // — because re-cutting would silently throw away the matchups they
            // deliberately set. Keeping it in step with the entrant list is the
            // owning package's job (see Tournament::onEntrantsChanged).
            if ($cat->draw_state === 'manual') {
                continue;
            }

            if ($started) {
                if ($cat->draw_state === 'final') {
                    continue; // locked
                }

                /*
                 * ⚠️ NEVER RE-CUT A DIVISION THAT HAS BEEN FOUGHT.
                 *
                 * `build()` deletes the division's bouts and lays new ones —
                 * so reaching here with results on the board destroys them:
                 * every winner, every score, and the link from a recording to
                 * the bout it filmed. It is not hypothetical. Starting an event
                 * only writes `started_at`; the draw stays `provisional` until
                 * something calls this. Bouts fought in that window were then
                 * wiped by the next bracket page view, which is the first thing
                 * anybody does on a mat morning.
                 *
                 * `startEvent` now locks the draw up front, which closes the
                 * window. This closes the DOOR: a result is a fact, and no
                 * housekeeping pass may delete one. The division is marked
                 * final so it is never asked again.
                 */
                if ($this->hasResults($cat)) {
                    $cat->update(['draw_state' => 'final']);

                    continue;
                }

                $this->build($event, $cat, paidOnly: true);
                $changed = true;
            } else {
                $entrants = $cat->registrations()->where('role', 'participant')->count();
                if ($cat->draw_state === 'provisional' && $cat->draw_count === $entrants) {
                    continue; // already fresh
                }
                if ($entrants < 2) {
                    continue;
                }
                $this->build($event, $cat, paidOnly: false);
                $changed = true;
            }
        }

        if ($changed) {
            $this->scheduler->scheduleAndNumber($event);
        }
    }

    /**
     * (Re)build a single-elimination bracket for a division. Entrants are spread by
     * a stable pseudo-random order (changes only when the entrant set changes — no
     * reshuffle jitter); field padded to the next power of two with byes that auto-advance.
     */
    /**
     * @param  bool  $paidOnly  the FINAL draw: only officially verified entries.
     */
    public function build(ClubEvent $event, EventCategory $cat, bool $paidOnly): void
    {
        /*
         * A division can be run as a GROUP instead of a ladder.
         *
         * The dispatch lives here, at the one door every caller already uses —
         * `ensure()`, each package's `generateDraw`, the entrant-set hooks — so
         * a second shape of competition needed no branch in any of them, and a
         * division whose format is edited simply builds differently next time.
         */
        if (($cat->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            $this->groups->build($event, $cat, $paidOnly);

            return;
        }

        // The final draw is the one people actually fight, so an entry earns its
        // place by being CHECKED, not by claiming to be ready:
        //
        //   paid_by        a payments official matched the proof to the account
        //   weighed_in_by  a weigh-in official put the athlete on the scale
        //
        // `paid` and `weight` alone are assertions — a member ticks a box and
        // types a number. Drawing on those is how someone reaches the mat
        // without having paid or made weight. The provisional draw still shows
        // everyone, so nobody disappears from the board before the weigh-in.
        /*
         * ⚠️ A FREE competition has no payment to sign for.
         *
         * `paid_by` is the payments official's name against an entry, and it is
         * the right demand when money is owed. It is nonsense when nothing is:
         * a free club event has no proof to check and no official who ever
         * opens that sheet, so every entrant carried `paid_by = null` and the
         * final draw dropped ALL of them — the bracket emptied the moment the
         * organiser pressed Start, with no message and nothing to fight.
         *
         * So the money half of the test is asked only of an event that charges.
         * The weigh-in half always stands: a weight is a safety matter and it
         * is free to record.
         */
        $charges = EventFee::chargesAnything($event, 'participant');

        $regs = $cat->registrations()
            ->where('role', 'participant')
            ->when($paidOnly, fn ($q) => $q
                ->when($charges, fn ($q) => $q->where('paid', true)->whereNotNull('paid_by'))
                ->whereNotNull('weight')->whereNotNull('weighed_in_by'))
            ->with('user:id,full_name,name')
            ->get();

        // Stable pseudo-random spread; provisional = at risk of removal at start (unpaid OR not weighed in).
        // Each slot carries the ENTRY it came from, so a bout knows who is
        // actually fighting it — not just what to print on the board.
        $competitors = $regs->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->user?->full_name ?? $r->user?->name ?? 'Athlete',
            // The entry's country (ISO alpha-2), so the board can fly a flag.
            // Same source Arrangement::place() reads when a draw is hand-made.
            'country' => $r->countryCode(),
            // "At risk of removal when the draw goes final" — so it has to ask
            // the same question the final draw asks, signatures included. Marking
            // a self-declared entry as safe here would promise a place the final
            // build then takes away.
            'provisional' => ! $paidOnly && (
                ($charges && (! $r->paid || ! $r->paid_by)) || $r->weight === null || ! $r->weighed_in_by
            ),
            'key' => md5($cat->id.':'.$r->user_id),
        ])->sortBy('key')->values();

        $cat->matches()->delete();

        $n = $competitors->count();
        if ($n < 2) {
            $cat->update(['draw_state' => $paidOnly ? 'final' : 'provisional', 'draw_count' => $n]);

            return;
        }

        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }

        // Standard bracket-seed slots so byes are spread out.
        $slots = array_fill(0, $size, null);
        foreach ($this->seedOrder($size) as $slotIndex => $seedNum) {
            if ($seedNum <= $n) {
                $slots[$slotIndex] = $competitors[$seedNum - 1];
            }
        }

        $rounds = $this->roundNames($size);
        $slot = 0;

        for ($i = 0; $i < $size; $i += 2) {
            $a = $slots[$i];
            $b = $slots[$i + 1];
            $bye = (($a === null) xor ($b === null));
            $cat->matches()->create([
                'event_id' => $event->id,
                'round' => $rounds[0],
                'phase' => $this->phaseForRound($rounds[0]),
                'slot' => $slot++,
                'a_name' => $a['name'] ?? null,
                'a_competitor_id' => $a['id'] ?? null,
                'a_country' => $a['country'] ?? null,
                'a_provisional' => $a['provisional'] ?? false,
                'b_name' => $b['name'] ?? null,
                'b_competitor_id' => $b['id'] ?? null,
                'b_country' => $b['country'] ?? null,
                'b_provisional' => $b['provisional'] ?? false,
                'winner' => $bye ? ($a ? 'a' : 'b') : null,
                'status' => $bye ? 'done' : 'upcoming',
            ]);
        }

        for ($r = 1; $r < count($rounds); $r++) {
            $count = $size / (2 ** ($r + 1));
            for ($i = 0; $i < $count; $i++) {
                $cat->matches()->create([
                    'event_id' => $event->id,
                    'round' => $rounds[$r],
                    'phase' => $this->phaseForRound($rounds[$r]),
                    'slot' => $slot++,
                    'status' => 'upcoming',
                ]);
            }
        }

        /*
         * ===== The play-off for bronze =====
         *
         * A sport whose convention is `third_place_match` awards ONE bronze,
         * to the winner of a bout between the two beaten semi-finalists — and
         * that bout has to EXIST for anybody to fight it. It never did:
         * `Advancement::awardIfComplete()` has always looked for a bout
         * `round = 'Third place'` and nothing in the platform ever created one,
         * so every knockout division in such a sport stopped at silver.
         *
         * It is cut here, with the rest of the ladder, because it is part of
         * the draw — not something a desk remembers to add on the day.
         *
         * Written LAST, after the final, so it sits at the end of the slot
         * order and reads as the appendix it is. It takes no part in the
         * winner-walk: `Advancement` excludes this round from the bracket
         * geometry and feeds it the semi-final LOSERS instead.
         *
         * Only for a sport that says so, and only when there are semi-finals to
         * lose (a field of four or more). Karate and taekwondo settle bronze by
         * repechage and are untouched.
         */
        if ($this->awardsThirdPlaceBout($event, $rounds)) {
            $cat->matches()->create([
                'event_id' => $event->id,
                'round' => self::ROUND_THIRD,
                'phase' => 'finals',
                'slot' => $slot++,
                'status' => 'upcoming',
            ]);
        }

        /*
         * ⚠️ A BYE MUST WALK FORWARD, EVERY TIME THE DRAW IS CUT.
         *
         * A bye is written above with a winner and `status = done`, which is
         * the RESULT — it is not the same as putting that athlete in the next
         * round. Carrying them forward used to be done by the owning package,
         * in `generateDraw()` alone, and the two OTHER callers of this method
         * never did it: `ensure()` (which re-cuts the division the moment the
         * event STARTS, as the final paid-only draw) and `onEntrantsChanged()`.
         *
         * So pressing Start erased every bye advancement, and the semi-final
         * behind it became "(tbd) v Somebody" — un-runnable, for ever: once an
         * event has started the draw actions are withdrawn, so the organiser
         * could neither re-cut it nor arrange it, and the only way past was to
         * award a walkover against a competitor who had already won their way
         * through. Every knockout division of 5, 6, 7, 9… entrants was exposed.
         *
         * It belongs here, beside the code that writes the bye, so it cannot be
         * forgotten by a caller again. Pure geometry — bout i feeds bout ⌊i/2⌋,
         * side a when i is even — the same rule Advancement walks with.
         */
        $this->carryByes($cat);

        $cat->update([
            'draw_state' => $paidOnly ? 'final' : 'provisional',
            'draw_count' => $n,
            'schedule' => $cat->schedule ?: $this->scheduler->defaultSchedule($event, $cat),
            'status' => ($paidOnly && $cat->status === 'enrolling') ? 'live' : $cat->status,
        ]);
    }

    /**
     * Put every first-round bye into the round it has already won its way to.
     *
     * Only a BYE: a bout with a winner and nobody in the other corner. A bout
     * two people actually fought is a result, and results are propagated by
     * Advancement when they are recorded, not by the draw being cut.
     */
    private function carryByes(EventCategory $cat): void
    {
        $rounds = $cat->matches()->orderBy('slot')->get()
            ->reject(fn (EventMatch $m) => $m->round === self::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn ($bouts) => $bouts->min('slot'))
            ->values();

        $first = $rounds->first();
        $next = $rounds->get(1);

        if (! $first || ! $next) {
            return;     // a one-bout division has nowhere to carry anybody
        }

        foreach ($first->values() as $i => $bout) {
            $won = $bout->winner;

            if (! $won || ! $bout->{$won.'_name'}) {
                continue;
            }

            // Both corners named means they fought; that is not a bye.
            $other = $won === 'a' ? 'b' : 'a';

            if ($bout->{$other.'_name'}) {
                continue;
            }

            $target = $next->values()->get(intdiv($i, 2));

            if (! $target) {
                continue;
            }

            $side = $i % 2 === 0 ? 'a' : 'b';

            $target->{$side.'_name'} = $bout->{$won.'_name'};
            $target->{$side.'_competitor_id'} = $bout->{$won.'_competitor_id'};
            $target->{$side.'_country'} = $bout->{$won.'_country'};
            $target->{$side.'_provisional'} = (bool) $bout->{$won.'_provisional'};
            $target->save();
        }
    }

    /**
     * Has anything in this division been decided by two people meeting?
     *
     * A bye is not a result — it is written with a winner the moment the draw
     * is cut, so counting it would freeze every bracket that is not a clean
     * power of two at its provisional shape.
     */
    private function hasResults(EventCategory $cat): bool
    {
        return $cat->matches()
            ->whereNotNull('winner')
            ->whereNotNull('a_name')
            ->whereNotNull('b_name')
            ->exists();
    }

    /**
     * Does this event's sport settle bronze with a play-off, and is there a
     * semi-final for its two losers to come out of?
     *
     * @param  array<int, string>  $rounds
     */
    private function awardsThirdPlaceBout(ClubEvent $event, array $rounds): bool
    {
        return in_array('Semifinal', $rounds, true)
            && $this->sports->get($event->sport)?->bronzeRule() === 'third_place_match';
    }

    /** Standard single-elimination seed positions (index = slot, value = seed #). */
    public function seedOrder(int $size): array
    {
        $seeds = [1];
        $rounds = (int) log($size, 2);
        for ($r = 0; $r < $rounds; $r++) {
            $sum = (2 ** ($r + 1)) + 1;
            $next = [];
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $sum - $s;
            }
            $seeds = $next;
        }

        return $seeds;
    }

    /** Round labels for a power-of-two bracket, largest first. */
    public function roundNames(int $size): array
    {
        $names = [];
        for ($m = $size / 2; $m >= 1; $m /= 2) {
            $names[] = match ((int) $m) {
                1 => 'Final',
                2 => 'Semifinal',
                4 => 'Quarterfinal',
                default => 'Round of '.((int) $m * 2),
            };
        }

        return $names;
    }

    /** Group a round into one of the 3 schedulable phases. */
    public function phaseForRound(string $round): string
    {
        if (str_starts_with($round, 'Round of')) {
            return 'preliminary';
        }

        return match ($round) {
            'Quarterfinal' => 'quarterfinals',
            'Semifinal', 'Final' => 'finals',
            default => 'preliminary',
        };
    }
}
