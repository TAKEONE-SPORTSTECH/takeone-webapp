<?php

namespace App\Events\Support;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;

/**
 * What a division is for, and who sits outside it.
 *
 * ── A guide, never a gate ───────────────────────────────────────────────────
 *
 * This class answers "does this athlete match what the group is for?" and
 * NOTHING else. It cannot refuse an assignment and is never asked to: putting a
 * fourteen-year-old into the adult bracket, or a lighter athlete up a weight, is
 * ordinary competition management — a bracket of three needs a fourth, a strong
 * junior is safer against adults than against nobody, and the organiser
 * standing in the hall knows things this table does not.
 *
 * So every method here returns a DESCRIPTION. The picker uses it to sort and to
 * badge; the organiser decides. The one thing the platform owes them is that the
 * exception stays visible afterwards rather than quietly becoming the record.
 *
 * ── Missing data is not a mismatch ──────────────────────────────────────────
 *
 * Birthdate is never required of anyone on this platform (CLAUDE.md → "Who
 * Fills The Form Decides What It Demands"), weight is only known after a
 * weigh-in, and an athlete entered off a paper sheet at the door often has
 * neither, nor a gender. A filter that treated absence as a mismatch would hide
 * most of a real roster on the morning it is needed most.
 *
 * So absence is its OWN answer — 'unknown', never 'outside'. An unknown athlete
 * is always offered, and never wears the badge that says somebody made an
 * exception for them.
 */
class DivisionRange
{
    public function __construct(private EventCategory $category) {}

    /** Does this division narrow anything at all? */
    public function isOpen(): bool
    {
        return $this->category->gender === null
            && $this->category->min_age === null && $this->category->max_age === null
            && $this->category->min_weight === null && $this->category->max_weight === null
            && $this->belts() === [];
    }

    /** The belts this division is for — an empty list meaning ANY. */
    public function belts(): array
    {
        return array_values(array_filter((array) ($this->category->belts ?? [])));
    }

    /** The bounds, for the picker's controls and for display. */
    public function toArray(): array
    {
        return [
            'gender' => $this->category->gender,
            'min_age' => $this->category->min_age,
            'max_age' => $this->category->max_age,
            'min_weight' => $this->category->min_weight,
            'max_weight' => $this->category->max_weight,
            'belts' => $this->belts(),
            'open' => $this->isOpen(),
        ];
    }

    /**
     * How one entrant sits against this division.
     *
     * @return array{fit: 'in'|'out'|'unknown', misses: array<int, string>}
     *   fit 'in'      — matches everything the division narrows by
     *       'out'     — a value is known and falls outside
     *       'unknown' — nothing contradicts it, but something needed is missing
     *   misses        — machine-readable reasons ('gender', 'age', 'weight',
     *                   'belt', 'stream'), which the client turns into words.
     *
     * @param  array<int, string>|null  $granted
     *   The streams this PERSON has bought, as EventStreams keys, when the
     *   caller has them to hand. Null (the default) means "not asked", which is
     *   every caller that predates streams and every event that declares none.
     */
    public function judge(ClubEventRegistration $entrant, ?array $granted = null): array
    {
        $misses = [];
        $unknown = false;

        // Gender.
        if ($this->category->gender !== null) {
            $theirs = $entrant->user?->gender;

            if ($theirs === null || $theirs === '') {
                $unknown = true;
            } elseif (strcasecmp($theirs, $this->category->gender) !== 0) {
                $misses[] = 'gender';
            }
        }

        // Age, in whole years on the day the event runs — not today. A division
        // built in March for an event in December must judge an athlete by how
        // old they will BE, or every borderline junior is misfiled.
        if ($this->category->min_age !== null || $this->category->max_age !== null) {
            $age = $this->ageOf($entrant);

            if ($age === null) {
                $unknown = true;
            } elseif (($this->category->min_age !== null && $age < $this->category->min_age)
                   || ($this->category->max_age !== null && $age > $this->category->max_age)) {
                $misses[] = 'age';
            }
        }

        // Weight, as recorded at the weigh-in.
        if ($this->category->min_weight !== null || $this->category->max_weight !== null) {
            $weight = $entrant->weight === null ? null : (float) $entrant->weight;

            if ($weight === null) {
                $unknown = true;
            } elseif (($this->category->min_weight !== null && $weight < $this->category->min_weight)
                   || ($this->category->max_weight !== null && $weight > $this->category->max_weight)) {
                $misses[] = 'weight';
            }
        }

        /*
         * Belt, resolved the one way every screen resolves it: the weigh-in
         * desk first, then a dated certification, then what they say of
         * themselves (BeltRank). An athlete whose rank nobody has recorded is
         * 'unknown' and stays offered — most entrants at a club's first event
         * have nothing on file, and hiding them would empty the picker.
         */
        if ($this->belts() !== []) {
            $belt = app(\App\Sports\Combat\BeltRank::class)->for($entrant->user, $entrant);
            $colour = $belt['colour'] ?? null;

            if ($colour === null) {
                $unknown = true;
            } elseif (! in_array(strtolower($colour), $this->belts(), true)) {
                $misses[] = 'belt';
            }
        }

        /*
         * The stream — Gi, No-Gi, Kata, Kumite: the part of the event this
         * division belongs to.
         *
         * This one is different in kind from the four above. Gender, age,
         * weight and belt describe the ATHLETE and the organiser may overrule
         * any of them: a strong junior in the adults is a judgement call. What
         * somebody ENTERED is not a judgement call — an athlete who paid for
         * No-Gi is not in the Gi competition, and offering them for a Gi
         * bracket is offering to charge them for something they did not buy.
         *
         * It is still only a badge and a sort, not a gate, because the desk
         * sells an upgrade on the day and the fee lines catch up afterwards.
         * EventStreams::judge() decides, and answers 'unknown' — never 'out' —
         * whenever either side is silent, which is most events.
         */
        if ($granted !== null && EventStreams::judge(EventStreams::ofDivision($this->category), $granted) === 'out') {
            $misses[] = 'stream';
        }

        return [
            // A known mismatch outranks a missing value: an athlete who is the
            // wrong gender AND has no weight is 'out', because the thing we DO
            // know already answers the question.
            'fit' => $misses !== [] ? 'out' : ($unknown ? 'unknown' : 'in'),
            'misses' => $misses,
        ];
    }

    /**
     * An entrant's age on the day of the event, or null when no birthdate is on
     * file — which is common and is not a fault.
     *
     * Measured against the EVENT's date, not today. A division built in March
     * for a competition in December has to judge an athlete by how old they will
     * BE on the day, or every borderline junior is filed into the wrong group
     * and nobody notices until the weigh-in.
     */
    public function ageOf(ClubEventRegistration $entrant): ?int
    {
        $born = $entrant->user?->birthdate;

        if (! $born) {
            // A stated age, when that is all anybody has (2026-10-15).
            return $entrant->declared_age !== null ? (int) $entrant->declared_age : null;
        }

        $born = \Illuminate\Support\Carbon::parse($born);
        $on = $this->category->event?->date;

        return $on
            ? $born->diffInYears(\Illuminate\Support\Carbon::parse($on))
            : $born->age;
    }
}
