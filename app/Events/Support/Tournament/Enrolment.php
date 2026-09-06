<?php

namespace App\Events\Support\Tournament;

use App\Events\Support\EnrolmentDecision;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
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
    public function resolveDivision(ClubEvent $event, User $user, ?float $weight = null): ?EventCategory
    {
        if (! $user->gender || ! $user->birthdate) {
            return null;
        }

        $weight ??= $this->declaredWeight($user);
        if (! $weight) {
            return null;
        }

        $class = $this->sport->classify($user->gender, Carbon::parse($user->birthdate)->age, $weight);
        if (! $class) {
            return null;
        }

        $name = $this->sport->divisionName($class['age_group'], $user->gender, $class['category']);

        return EventCategory::where('event_id', $event->id)->where('name', $name)->first();
    }

    /** Latest recorded weight for the member, or null when they have none. */
    public function declaredWeight(User $user): ?float
    {
        $w = optional($user->latestHealthRecord)->weight;

        return $w ? (float) $w : null;
    }
}
