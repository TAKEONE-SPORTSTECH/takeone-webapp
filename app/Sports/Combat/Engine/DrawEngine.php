<?php

namespace App\Sports\Combat\Engine;

use App\Events\Support\EventFee;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\SportRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                if ($this->hasResults($cat) || $this->isBeingFought($cat, $event)) {
                    $cat->update(['draw_state' => 'final']);

                    continue;
                }

                /*
                 * ⚠️ A BOARD THAT ALREADY EXISTS IS FROZEN, NEVER RE-CUT
                 * (2026-09-18, at the user's instruction).
                 *
                 * Starting the day used to rebuild every division as the final
                 * paid-and-weighed-in-only draw. That is defensible on paper —
                 * a final draw is supposed to hold only verified entries — and
                 * it is brutal in a hall: the moment the day starts, anybody
                 * without a weigh-in row DISAPPEARS from a bracket that was on
                 * the wall a second earlier. It happened to nine athletes
                 * across six divisions at 08:01 on 2026-09-18, and the first
                 * anybody knew of it was coaches asking where their fighters
                 * had gone.
                 *
                 * So the rule is now about the BOARD, not the entries: a
                 * division that has already been drawn keeps the draw it has,
                 * and the organiser adds or removes people deliberately through
                 * Arrange. A division that has never been drawn is still cut
                 * paid-only below — that is a first cut, and it cannot take
                 * anybody off a board they were never on.
                 *
                 * The two checks above are now subsumed by this one (a fought
                 * or in-progress division has bouts by definition). They are
                 * kept deliberately: they name the two things that must never
                 * be re-cut under ANY future relaxation of this broader rule.
                 */
                if ($cat->matches_count > 0) {
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

        $this->pruneBronzeBouts($event);
    }

    /**
     * Take back a third-place bout the sport no longer awards.
     *
     * ── Why this exists ───────────────────────────────────────────────────
     *
     * `bronzeRule()` is the sport's answer, and an answer can change: jiu-jitsu
     * was read as running a play-off for bronze and does not — corrected on
     * 2026-09-18. Changing the rule stops the engine CUTTING the bout; it does
     * nothing about the ones already on the board, and those carry a mat and a
     * number, so they sit on the running order as a bout nobody will ever call.
     * On the event that reported it there were thirteen.
     *
     * A rebuild would have taken them: `build()` lays the division again from
     * scratch. But it is refused for a hand-arranged draw and skipped for a
     * provisional one whose entrant count has not moved — which is every
     * division here — and forcing one would throw away the organiser's own
     * matchups to fix a bout that is merely surplus. So this removes exactly
     * the surplus bout and touches nothing else.
     *
     * ── What it will not touch ────────────────────────────────────────────
     *
     * A bout that was FOUGHT, in any sense: a winner, a name in either corner,
     * or a recording filed against it. A result is a fact and no housekeeping
     * pass may delete one — the same rule the re-cut guard above states. If a
     * sport's convention changes after a division has been fought, that
     * division keeps the bout and its result, and the podium it already awarded
     * stands.
     *
     * The mat is renumbered afterwards so the ORDER is unchanged and only the
     * integers close up: `match_no` is a position, and a gap in it is a caller
     * announcing bout 99 after bout 97.
     */
    private function pruneBronzeBouts(ClubEvent $event): void
    {
        if (\App\Events\Support\BronzeRule::playsOff($event)) {
            return;
        }

        $surplus = EventMatch::where('event_id', $event->id)
            ->where('round', self::ROUND_THIRD)
            ->whereNull('winner')
            ->whereNull('a_name')
            ->whereNull('b_name')
            ->get()
            ->reject(fn (EventMatch $m) => DB::table('event_recordings')->where('match_id', $m->id)->exists());

        if ($surplus->isEmpty()) {
            return;
        }

        // The mats to close up, noted before the rows go.
        $mats = $surplus
            ->filter(fn (EventMatch $m) => filled($m->court))
            ->map(fn (EventMatch $m) => [(int) ($m->day ?: 1), (string) $m->court])
            ->unique(fn (array $k) => implode('|', $k));

        EventMatch::whereIn('id', $surplus->pluck('id'))->delete();

        foreach ($mats as [$day, $court]) {
            $no = 0;

            $bouts = EventMatch::where('event_id', $event->id)
                ->where('day', $day)
                ->where('court', $court)
                ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
                ->orderBy('match_no')
                ->orderBy('slot')
                ->get();

            foreach ($bouts as $bout) {
                // A bye has no number and never gains one — Scheduler decides
                // what is a bye, and this is not that.
                if ($bout->match_no === null) {
                    continue;
                }

                $bout->match_no = ++$no;
                $bout->save();
            }
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
         * ⚠️ THE DOOR, NOT THE DOORMEN: A FOUGHT DIVISION IS NEVER RE-CUT.
         *
         * Everything below this line deletes the division's bouts and lays new
         * ones. `ensure()` guards that — and only on the branch where the event
         * has STARTED, which is a button somebody presses, not a fact about
         * whether anybody has fought. An organiser who never pressed Start had
         * no guard at all: every entrant added, every weigh-in placement, every
         * view of the bracket page could delete a morning's results, and the
         * three other callers (`generateDraw`, `growDraw`, `onEntrantsChanged`)
         * each carried their own version of the check or none.
         *
         * So it lives HERE, beside the delete, where no caller can forget it —
         * the same reason `carryByes()` was moved into this method. A result is
         * a fact; a housekeeping pass may not spend one, whatever the clock says.
         *
         * The way to change a division that HAS been fought is
         * `App\Events\Support\Tournament\LateEntry`, which grafts onto the
         * board instead of re-cutting it.
         */
        if ($cat->matches()->contested()->exists()) {
            Log::warning('Refused to re-cut a division that has been fought.', [
                'event_id' => $event->id,
                'category_id' => $cat->id,
            ]);

            return;
        }

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
                /*
                 * SAID, not inferred. A walkover and a bout somebody won are
                 * both "a winner and done", and the only thing that ever told
                 * them apart was the other corner being empty — which stops
                 * being true the moment an organiser drops a late entrant into
                 * it. Everything that must not treat a bye as a result reads
                 * this flag now rather than guessing from the shape.
                 */
                'won_by_bye' => $bye,
                'status' => $bye ? 'done' : 'upcoming',
            ]);
        }

        for ($r = 1; $r < count($rounds); $r++) {
            $count = $size / (2 ** ($r + 1));

            /*
             * ===== The play-off for bronze, BEFORE the final =====
             *
             * Cut here — immediately ahead of the last round — so it takes the
             * slot before the final and therefore runs before it. That is the
             * order a hall actually wants: the two beaten semi-finalists fight
             * for third while the finalists rest, and the day ends on the final
             * rather than on an appendix after the medals have been imagined.
             * Asked for on 2026-09-18; it used to be written after the final.
             *
             * The slot is presentation and running order ONLY. It takes no part
             * in the winner-walk: `Advancement::rounds()` rejects this round
             * before it groups anything, so the final's winner is never walked
             * into it and the bracket geometry is unchanged by where it sits.
             * It is fed by `syncThirdPlace()` from the semi-finals' losers.
             *
             * Only for a sport that says so, and only when there are semi-finals
             * to lose (a field of four or more).
             */
            if ($r === count($rounds) - 1 && $this->awardsThirdPlaceBout($event, $rounds)) {
                $cat->matches()->create([
                    'event_id' => $event->id,
                    'round' => self::ROUND_THIRD,
                    'phase' => 'finals',
                    'slot' => $slot++,
                    'status' => 'upcoming',
                ]);
            }

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
     * Is one of this division's bouts ON A MAT right now?
     *
     * The companion to `hasResults`, and it exists because that question is
     * asked a moment too late. A division's FIRST contested bout carries no
     * result until the officials file one — so between the athletes being
     * called and the winner being declared, `hasResults` says the division is
     * untouched and `build()` is free to delete the very bout being fought.
     *
     * That window used to be narrow. It stopped being narrow when the mat was
     * allowed to start the day itself (BJJ Scoring::startTheDay): the first
     * thing that happens there is a draw lock, triggered BY the result coming
     * in, with that result's own bout loaded on the mat and not yet written.
     * Without this check the lock would delete the bout mid-command.
     *
     * Deliberately not folded into `hasResults`: that one answers "has anything
     * been decided here", which a bye satisfies on paper and must not. This
     * answers "is somebody fighting", which no bye ever does.
     */
    private function isBeingFought(EventCategory $cat, ClubEvent $event): bool
    {
        $onMats = \App\Scoreboard\Fleet::matchesOnMats($event);

        if ($onMats === []) {
            return false;
        }

        return $cat->matches()->whereIn('id', $onMats)->exists();
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
            && \App\Events\Support\BronzeRule::playsOff($event);
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
