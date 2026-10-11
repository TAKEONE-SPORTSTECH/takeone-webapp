<?php

namespace App\Federations\Support;

use App\Federations\Models\FederationAthlete;
use App\Members\Models\User;

/**
 * A member's account was deleted: their federation athlete record lets go of
 * the national ID it holds.
 *
 * A national ID may belong to one federation athlete only (unique index), and
 * entering an athlete is refused while ANY record holds it. Left in place, a
 * deleted athlete's ID blocks that same person from ever being entered again —
 * which is what happened when a club's athletes were deleted and its coach
 * tried to re-enter them (2026-10-08).
 *
 * The record itself stays (anonymised), so results and entries that point at
 * it keep their history; only the identifier is released. Restoring the
 * account does not bring the ID back — it is entered again, like any change
 * of ID.
 */
final class ReleaseDeletedAthlete
{
    public static function handle(User $user): void
    {
        if (! config('federations.enabled')) {
            return;
        }

        rescue(fn () => FederationAthlete::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNotNull('national_id_hash')->orWhereNotNull('national_id'))
            ->get()
            ->each(fn (FederationAthlete $a) => $a->forceFill([
                'national_id' => null, 'national_id_hash' => null, 'anonymised_at' => $a->anonymised_at ?? now(),
            ])->save()), null, report: true);
    }
}
