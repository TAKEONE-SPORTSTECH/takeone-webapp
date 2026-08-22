<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * How strict a person form is, decided by WHO is filling it in.
 *
 * The platform asks for a gender, a birthdate and a nationality because a member
 * profile is worth having complete. But who is typing changes whether demanding
 * them helps or hurts:
 *
 *   STAFF entering someone else — a super admin, a club owner or admin, an event
 *   organiser or an appointed official — routinely does not have that
 *   information. A referee arrives on a federation list with a name and nothing
 *   else; an athlete is entered at a weigh-in from a paper sheet. Forcing a value
 *   there does not produce data, it produces an INVENTED birthdate, which is
 *   worse than none: it looks authoritative and nobody ever revisits it. So for
 *   them a name is the only requirement.
 *
 *   THE MEMBER on their own profile is the one person who actually knows these
 *   answers, and their first visit to the edit screen is the moment worth asking.
 *   So their form stays strict.
 *
 * Anyone else — a guardian editing a dependent, say — stays strict too: they know
 * the person they are entering.
 *
 * Format rules are NEVER relaxed by any of this. A gender is still Male or
 * Female and a birthdate is still a date; only the demand that a value be
 * present is lifted.
 */
trait PersonFieldRules
{
    /**
     * 'nullable' when staff are entering someone else, 'required' otherwise.
     *
     * @param  int|null  $subjectUserId  the person being edited; null when creating
     */
    protected function personRequirement(?int $subjectUserId = null): string
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return 'required';
        }

        // Their own profile: the one place the questions are worth insisting on.
        if ($subjectUserId !== null && (int) $actor->id === (int) $subjectUserId) {
            return 'required';
        }

        return $actor->entersPeopleOnBehalfOfOthers() ? 'nullable' : 'required';
    }

    /** Prefix a rule string with the right presence rule for this actor. */
    protected function personRule(string $rest, ?int $subjectUserId = null): string
    {
        return $this->personRequirement($subjectUserId).'|'.$rest;
    }
}
