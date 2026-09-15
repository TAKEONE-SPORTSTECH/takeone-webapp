<?php

namespace App\Events\Support;

use App\Members\Models\User;

/**
 * Somebody the platform has never met.
 *
 * A competition is full of them: an athlete entered off a paper sheet at the
 * door, a referee the federation sent who has never opened this app. They are
 * recorded as PEOPLE — so a result, an appointment and a photograph have
 * somebody to belong to — but deliberately not as ACCOUNTS: no password, no
 * email, not discoverable, and flagged `is_unclaimed` so every listing that
 * means "members" leaves them out. They can claim the record later and own it.
 *
 * Blank beats invented. Gender, birthdate and nationality are written only when
 * the person filling the form actually knew: a guessed birthdate is worse than
 * none, because it drives age groups and the minor safeguards (CLAUDE.md → "Who
 * Fills The Form Decides What It Demands").
 */
class UnclaimedPerson
{
    /**
     * @param  array{gender?: ?string, birthdate?: ?string, nationality?: ?string, phone?: ?string, phone_code?: ?string}  $details
     */
    public static function make(string $fullName, array $details = []): User
    {
        $fullName = trim($fullName);

        $phone = trim((string) ($details['phone'] ?? ''));

        return User::create([
            'full_name' => $fullName,
            'name' => $fullName,
            'email' => null,
            'password' => null,
            'is_unclaimed' => true,
            'is_discoverable' => false,
            'gender' => $details['gender'] ?? null,
            'birthdate' => $details['birthdate'] ?? null,
            // An official is printed with a flag on every officiating sheet, so
            // the country is worth asking for even when nothing else is.
            'nationality' => ($details['nationality'] ?? null)
                ? strtoupper((string) $details['nationality'])
                : null,
            // The one contact a desk usually does have. Stored in the platform's
            // own shape so a later claim can match on it.
            'mobile' => $phone !== ''
                ? ['code' => $details['phone_code'] ?? '+973', 'number' => $phone]
                : null,
        ]);
    }
}
