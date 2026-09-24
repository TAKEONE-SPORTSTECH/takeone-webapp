<?php

namespace App\Members\Support;

use App\Federations\Models\FederationAthlete;
use App\Members\Models\User;
use Illuminate\Support\Collection;

/**
 * Find a TakeOne member by whatever the person searching has to hand: a name,
 * a phone number, an email, or a TakeOne ID (the athlete ID a federation
 * issues, `BHR-00012`) — or the member's profile uuid.
 *
 * The ONE search behind every "Find a member" control, so the same text finds
 * the same people everywhere. It decides only what matches; WHO may search is
 * the calling route's business (every caller today is super-admin only), and
 * what each result shows is the caller's too.
 *
 * Phone numbers go through User::withPhone(), the same matching as sign-in, so
 * `+973 3774 3277`, `0097337743277` and `37743277` find the same member.
 */
final class MemberLookup
{
    /** @return Collection<int, User> */
    public static function search(string $typed, int $limit = 8): Collection
    {
        $q = trim(str_replace(['%', '_'], '', $typed));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) {
            return collect();
        }

        $query = User::query()->whereNotNull('email')->where('email', '!=', '');

        if (preg_match('/^[A-Za-z]{3}-\d{1,6}$/', $q)) {
            // A TakeOne ID, whole or its beginning.
            if (! class_exists(FederationAthlete::class)) {
                return collect();
            }
            $ids = FederationAthlete::where('takeone_id', 'like', strtoupper($q).'%')->limit($limit)->pluck('user_id');
            $query->whereIn('id', $ids);
        } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $q)) {
            $query->where('uuid', strtolower($q));
        } elseif (str_contains($q, '@')) {
            $query->where('email', 'like', '%'.mb_strtolower($q).'%');
        } elseif (preg_match('/^[\d\s+\-().]+$/', $q) && strlen(preg_replace('/\D/', '', $q)) >= 4) {
            $query->withPhone($q);
        } else {
            $like = '%'.$q.'%';
            // A name — or the start of an email typed without its "@".
            $query->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', mb_strtolower($like)));
        }

        return $query->orderBy('full_name')->limit($limit)
            ->get(['id', 'uuid', 'full_name', 'name', 'email', 'mobile', 'gender', 'profile_picture', 'profile_picture_is_public']);
    }

    /** The face to show beside a result: their photo if they made it public, else the drawn avatar. */
    public static function photo(User $user): string
    {
        return $user->profile_picture_is_public ? ProfilePhoto::url($user) : Avatar::placeholder($user->gender);
    }
}
