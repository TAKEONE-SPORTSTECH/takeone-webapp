<?php

namespace App\Events\Support;

use App\Models\ClubEventRegistration;
use App\Models\EventCategory;

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
            && $this->category->min_weight === null && $this->category->max_weight === null;
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
     *   misses        — machine-readable reasons ('gender', 'age', 'weight'),
     *                   which the client turns into words.
     */
    public function judge(ClubEventRegistration $entrant): array
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
            return null;
        }

        $born = \Illuminate\Support\Carbon::parse($born);
        $on = $this->category->event?->date;

        return $on
            ? $born->diffInYears(\Illuminate\Support\Carbon::parse($on))
            : $born->age;
    }
}
