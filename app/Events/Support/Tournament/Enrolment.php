<?php

namespace App\Events\Support\Tournament;

use App\Events\Support\DivisionRange;
use App\Events\Support\EnrolmentDecision;
use App\Events\Support\EventStreams;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Members\Models\User;
use App\Sports\Combat\CombatSport;
use Carbon\Carbon;

/**
 * Who may compete in a bracketed championship, and in which weight division.
 *
 * A competitor is placed by their own gender / age / weight — never by choosing
 * a division from a list — and only into a division the organiser actually set
 * up for this event. A member whose class isn't being run cannot compete; they
 * are steered to a spectator ticket instead. Official weight is confirmed at
 * weigh-in; the declared weight only decides eligibility.
 */
abstract class Enrolment
{
    use SpeaksPackageLanguage;

    public function __construct(private CombatSport $sport) {}

    public function gate(ClubEvent $event, User $user, ?ClubEventRegistration $existing = null): EnrolmentDecision
    {
        // Already competing — stay eligible so the confirmed state keeps rendering.
        if ($existing && $existing->role === 'participant') {
            return EnrolmentDecision::allow($existing->category, $existing->weight ? (float) $existing->weight : null);
        }

        if (! $user->gender || ! $user->birthdate) {
            return EnrolmentDecision::deny(
                'no_profile',
                $this->t('gate_no_profile'),
            );
        }

        // No weight on file. That is a fact nobody has recorded yet, not a
        // reason to keep an athlete out of a competition where every entrant
        // stands on the scale on the day — so it is DEFERRED to weigh-in, and a
        // coach entering their own squad is not stopped by it.
        $weight = $this->declaredWeight($user);
        if (! $weight) {
            return EnrolmentDecision::defer(
                'no_weight',
                $this->t('gate_no_weight'),
            );
        }

        $division = $this->resolveDivision($event, $user, $weight);
        if (! $division) {
            return EnrolmentDecision::deny(
                'no_division',
                $event->spectator_enabled
                    ? $this->t('gate_no_division_spectator')
                    : $this->t('gate_no_division'),
                offerSpectator: (bool) $event->spectator_enabled,
            );
        }

        return EnrolmentDecision::allow($division, $weight);
    }

    /**
     * Classify the member and return the matching division — but only one the
     * organiser defined for THIS event. Never invents a division.
     */
    public function resolveDivision(ClubEvent $event, User $user, ?float $weight = null, ?ClubEventRegistration $entrant = null): ?EventCategory
    {
        if (! $user->gender || ! $user->birthdate) {
            return null;
        }

        $weight ??= $this->declaredWeight($user);
        if (! $weight) {
            return null;
        }

        /*
         * The federation's table first — but only as a SHORTCUT, never as a
         * gate.
         *
         * An organiser is free to run weight classes the sport's own table has
         * never heard of: a club invitational with three home-made groups, a
         * masters bracket nobody's federation publishes, a division above the
         * table's top weight. `classify()` answers null for all of those, and
         * returning here on that null meant the organiser's own divisions were
         * never even looked at — the platform would only place an athlete into
         * a class its rule book already knew.
         *
         * So a miss falls through to the ranges the organiser actually typed.
         */
        $class = $this->sport->classify($user->gender, Carbon::parse($user->birthdate)->age, $weight);

        if ($class) {
            $name = $this->sport->divisionName($class['age_group'], $user->gender, $class['category']);

            if ($byName = EventCategory::where('event_id', $event->id)->where('name', $name)->first()) {
                return $byName;
            }
        }

        return $this->matchByRange($event, $user, $weight, $entrant);
    }

    /**
     * Where the SCALE says this entry belongs.
     *
     * The weigh-in is the moment a championship stops guessing: until then a
     * division was chosen from a health record months old, or from what the
     * athlete said of themselves, or not at all. So this is the one place the
     * platform is allowed to move somebody — and it is deliberately narrow.
     *
     * Four outcomes:
     *   'placed'   an entry that had no division now has one (what the desk has
     *              always done).
     *   'moved'    the PLATFORM had placed them, the recorded weight puts them
     *              outside that division, and there is one it puts them inside.
     *              They go there, and the desk is told — a competitor quietly
     *              left in the wrong bracket is how somebody fights the wrong
     *              opponent.
     *   'mismatch' a PERSON placed them and the scale disagrees. They stay
     *              exactly where the organiser put them; the desk is told so the
     *              disagreement is visible rather than silent.
     *   null       nothing to do, or nothing safe to do.
     *
     * ── Whose decision is whose ─────────────────────────────────────────────
     *
     * Weight classes are not only the federation's. An organiser runs the
     * classes they choose, and moves people between them for reasons no table
     * holds: a bracket of three that needs a fourth, a strong junior safer up an
     * age group, two clubs who travelled and must fight somebody. Those are
     * judgement calls and they are not the platform's to undo — so a division a
     * person chose (`category_source = manual`, and anything from before that
     * column existed) is never overruled here.
     *
     * What it will NOT do, in order of importance:
     *   · touch anything once the event has STARTED — the draw is final and
     *     re-cutting a live bracket is an organiser's decision, never a side
     *     effect of the desk;
     *   · move a competitor out of a division that has already FOUGHT — a
     *     result is a fact, and a bout cannot be un-had;
     *   · move somebody whose current division still fits, even loosely. Only a
     *     known, recorded mismatch moves anybody; an 'unknown' never does.
     *
     * @return array{division: ?EventCategory, action: ?string, from: ?EventCategory}
     */
    public function placeOnWeighIn(ClubEvent $event, ClubEventRegistration $registration): array
    {
        $nothing = ['division' => null, 'action' => null, 'from' => null];

        if (! $registration->user || ! $registration->weight) {
            return $nothing;
        }

        $weight = (float) $registration->weight;

        if (! $registration->category_id) {
            $division = $this->resolveDivision($event, $registration->user, $weight, $registration);

            if ($division) {
                $registration->update([
                    'category_id' => $division->id,
                    'category_source' => ClubEventRegistration::PLACED_AUTO,
                ]);
            }

            return ['division' => $division, 'action' => $division ? 'placed' : null, 'from' => null];
        }

        if ($event->hasStarted()) {
            return $nothing;
        }

        $current = $registration->category;

        if (! $current || $current->isHeading()) {
            return $nothing;
        }

        $current->setRelation('event', $event);

        // Only a mismatch the SCALE just proved moves anybody. A division that
        // fits, or that cannot be judged for want of a birthdate, is left as
        // the organiser left it.
        if (! in_array('weight', (new DivisionRange($current))->judge($registration)['misses'], true)) {
            return $nothing;
        }

        /*
         * A person put them here. Say so — and leave them alone.
         *
         * Reported rather than silently accepted, because the desk is the one
         * place where "this athlete is 4 kg over the group they are entered in"
         * is still cheap to act on. The organiser may have meant it exactly; if
         * they did not, they now know.
         */
        if ($registration->category_source !== ClubEventRegistration::PLACED_AUTO) {
            return ['division' => $current, 'action' => 'mismatch', 'from' => null];
        }

        if ($this->hasBeenFought($current)) {
            return $nothing;
        }

        $target = $this->resolveDivision($event, $registration->user, $weight, $registration);

        if (! $target || $target->id === $current->id || $this->hasBeenFought($target)) {
            return $nothing;
        }

        $registration->update([
            'category_id' => $target->id,
            'category_source' => ClubEventRegistration::PLACED_AUTO,
        ]);

        return ['division' => $target, 'action' => 'moved', 'from' => $current];
    }

    /**
     * Has anything in this division actually been CONTESTED yet?
     *
     * ⚠️ A bye is not a contest. Byes are written with a winner and
     * `status = 'done'` the moment the draw is cut, so "has a winner" alone
     * answers yes for every bracket that is not a clean power of two — and the
     * weigh-in then refuses to move anybody out of it, which is exactly the
     * case this rule exists for. A bout is fought when two named people met.
     */
    private function hasBeenFought(EventCategory $division): bool
    {
        return $division->matches()
            ->whereNotNull('winner')
            ->whereNotNull('a_name')
            ->whereNotNull('b_name')
            ->exists();
    }

    /**
     * Place an athlete by what a division IS FOR, rather than by what it is
     * CALLED.
     *
     * ⚠️ This is the half that was missing, and without it automatic placement
     * did nothing at all. The lookup above builds the sport's canonical name —
     * "Adult Men -70 kg" — and asks for a division called exactly that. No
     * organiser names a division that way: the ones in this database read
     * "Group C (-60) Adult [Gi]", and the platform's OWN preset generator
     * writes "Group A · Blue Belt · Male · Adult · -76 kg". So the name never
     * matched, `resolveDivision` returned null every time, and every athlete
     * either fell out of the entry gate as `no_division` or sat unplaced
     * through the weigh-in waiting for a human to file them.
     *
     * The divisions already carry the answer in columns — `gender`,
     * `min_age`/`max_age`, `min_weight`/`max_weight`, `belts` — and
     * `DivisionRange` already reads them for the organiser's own picker. So the
     * placement asks the same question the picker asks, and takes the division
     * this athlete is unambiguously inside.
     *
     * Deliberately conservative, because this writes a competitor into a
     * bracket without anybody looking:
     *   · only a division that says `in` — every bound it narrows by is known
     *     AND satisfied. An 'unknown' (no birthdate, no belt on file) is never
     *     guessed at; that athlete stays unplaced for a human to decide.
     *   · an open division that narrows nothing is never auto-filled — it would
     *     swallow the entire event.
     *   · when two divisions both fit, the NARROWEST wins (most bounds, then
     *     the tightest weight window); a genuine tie is left alone rather than
     *     picked by row order.
     *   · a streamed event (Gi / No-Gi) only auto-places into a division that
     *     belongs to no stream — what somebody entered is not something to
     *     infer from their body weight.
     */
    private function matchByRange(ClubEvent $event, User $user, ?float $weight, ?ClubEventRegistration $entrant = null): ?EventCategory
    {
        $divisions = $event->categories()
            ->orderBy('sort_order')->get()
            ->reject(fn (EventCategory $c) => $c->isHeading());

        if ($divisions->isEmpty()) {
            return null;
        }

        $streamed = EventStreams::of($event) !== [];

        /*
         * The REAL entry wherever there is one — it carries the belt the desk
         * just wrote down, and a division that narrows by belt can only be
         * judged with it. Judging a stand-in instead reads every entrant's rank
         * as "unknown", and an unknown is never placed: the first run of this
         * against a belt-limited division placed nobody at all.
         *
         * Before an entry exists (the entry gate, deciding whether somebody may
         * enter at all) a stand-in is the only thing there is, and BeltRank
         * still answers from the athlete's own profile.
         */
        $entrant ??= tap(new ClubEventRegistration(['weight' => $weight]), function ($row) use ($user) {
            $row->setRelation('user', $user);
        });

        $fits = $divisions
            ->filter(function (EventCategory $division) use ($entrant, $event, $streamed) {
                if ($streamed && EventStreams::ofDivision($division) !== null) {
                    return false;
                }

                $division->setRelation('event', $event);
                $range = new DivisionRange($division);

                return ! $range->isOpen() && $range->judge($entrant)['fit'] === 'in';
            })
            ->values();

        if ($fits->count() === 1) {
            return $fits->first();
        }

        if ($fits->isEmpty()) {
            return null;
        }

        /*
         * Narrowest first: the division that says the most about who belongs in
         * it, and among equals the one with the tightest weight window. An
         * organiser running a broad class and a specific one — an open -74 kg
         * and a 55-62 kg round robin — means the specific one for anybody it
         * covers.
         *
         * ⚠️ An explicit comparator, NOT `sortBy([$closure, $closure])`.
         * Laravel calls a callable in that array as a COMPARATOR — `$prop($a,
         * $b)` — not as a key extractor, so a one-argument closure returning a
         * key silently becomes a nonsense comparison and the order is junk.
         * That shipped: an athlete who fitted both classes landed in the wider
         * one.
         */
        $ranked = $fits->sort(fn (EventCategory $a, EventCategory $b) => [
            $this->boundCount($b), $this->weightWindow($a),
        ] <=> [
            $this->boundCount($a), $this->weightWindow($b),
        ])->values();

        $first = $ranked->first();
        $second = $ranked->get(1);

        $tied = $this->boundCount($first) === $this->boundCount($second)
            && $this->weightWindow($first) === $this->weightWindow($second);

        // Two divisions this athlete fits equally well is a question for the
        // organiser, not a coin toss the bracket keeps.
        return $tied ? null : $first;
    }

    /** How many things a division narrows by — its specificity. */
    private function boundCount(EventCategory $division): int
    {
        $range = new DivisionRange($division);
        $b = $range->toArray();

        return (int) ($b['gender'] !== null)
            + (int) ($b['min_age'] !== null) + (int) ($b['max_age'] !== null)
            + (int) ($b['min_weight'] !== null) + (int) ($b['max_weight'] !== null)
            + (int) ($b['belts'] !== []);
    }

    /** The width of a division's weight window; an open end counts as wide. */
    private function weightWindow(EventCategory $division): float
    {
        $min = $division->min_weight === null ? 0.0 : (float) $division->min_weight;
        $max = $division->max_weight === null ? 999.0 : (float) $division->max_weight;

        return $max - $min;
    }

    /** Latest recorded weight for the member, or null when they have none. */
    public function declaredWeight(User $user): ?float
    {
        $w = optional($user->latestHealthRecord)->weight;

        return $w ? (float) $w : null;
    }
}
