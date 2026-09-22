<?php

namespace App\Members\Support;

use App\Members\Models\User;
use App\Members\Models\UserPhoto;
use App\Support\Avatar;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;

/**
 * THE one place a person's photograph is stored, and the one place it is read.
 *
 * A face belongs to a PERSON, not to the surface that happened to capture it.
 * It had stopped being true: an organiser photographing a competitor at the
 * weigh-in desk wrote to `events/{uuid}/competitors/…`, and that picture existed
 * only there — the athlete looked back at you from the bracket and was a blank
 * silhouette on their own profile and in the admin members list a tap later.
 * Forty-five people were in exactly that state. Two stores for one face is one
 * store too many, and the second one always loses.
 *
 * So: every photograph of a person is written under THEIR subtree
 * (`members/{uuid}/photos/…`), recorded as a `user_photos` row, and named by
 * `users.profile_picture` when they have no face yet. Anything else that wants
 * a picture of them — an entry, a bracket, a hall screen — holds a REFERENCE to
 * that path. Nothing copies the bytes and nothing keeps a private second copy.
 *
 * This works by DEFAULT: a capture path does not opt in, it simply calls
 * `store()` instead of writing a file itself, and the person gets their face.
 *
 * What it does NOT do:
 *  - It never overwrites a face the member chose. A desk photograph fills a
 *    BLANK profile picture; it never replaces one. Their profile is theirs.
 *  - It never decides who may SEE the picture. `profile_picture_is_public` and
 *    the event's own visibility still govern display (CLAUDE.md → Profile
 *    Pictures Are Portrait 3:4). Storage and disclosure are different questions.
 *
 * @see \App\Events\Support\EntryPhoto  which holds the reference and, crucially,
 *      refuses to delete a file another record still names.
 */
final class ProfilePhoto
{
    use StoresBase64Images;

    /**
     * Store a photograph OF THIS PERSON, wherever it was taken.
     *
     * Validated by real bytes (never by the data URI's claim), named by the
     * server, and filed under the member's own subtree — the three things
     * CLAUDE.md requires of every upload, in one call so no capture path has to
     * remember them.
     *
     * @return string|null  the stored path, or null when the bytes were refused
     */
    public static function store(User $user, string $base64): ?string
    {
        $path = (new self)->storeBase64Image(
            $base64,
            StoragePath::memberPhotos($user),
            (string) \Illuminate\Support\Str::ulid(),
        );

        if ($path === null) {
            return null;
        }

        self::adopt($user, $path);

        return $path;
    }

    /**
     * Store a photograph taken of somebody at a competition — the one call every
     * capture surface makes.
     *
     * A competitor's photo at the weigh-in desk, an official's at the jury
     * table, a corner photographed at the mat: all of them are a picture of a
     * PERSON, so all of them land in that person's own store and the row that
     * triggered it keeps a reference.
     *
     * The fallback is not a loophole — it is the one honest case. An entry made
     * from a name on a paper list has no account behind it yet, and a
     * photograph still has to go somewhere; it goes in the event's folder and
     * moves to the person the moment there is one.
     *
     * @param  string  $fallbackFolder  where to put it when there is no person
     * @return string|null  the stored path, or null when the bytes were refused
     */
    public static function capture(?User $user, string $base64, string $fallbackFolder, string $fallbackName): ?string
    {
        if ($user) {
            return self::store($user, $base64);
        }

        return (new self)->storeBase64Image($base64, $fallbackFolder, $fallbackName);
    }

    /**
     * Register a path that is ALREADY stored as this person's photograph.
     *
     * The door for bytes that arrived some other way — a migration, a backfill,
     * an import. Idempotent: calling it twice on the same path adds nothing.
     *
     * @param  bool  $makeAvatar  fill a blank `profile_picture` with it. Never
     *                            replaces one that is already set.
     */
    public static function adopt(User $user, string $path, bool $makeAvatar = true): void
    {
        $path = ltrim(trim($path), '/');

        if ($path === '') {
            return;
        }

        if (! UserPhoto::where('user_id', $user->id)->where('path', $path)->exists()) {
            UserPhoto::create([
                'user_id' => $user->id,
                'path' => $path,
                // Newest first is what every gallery here shows, and a face
                // taken today is the one that still looks like them.
                'sort_order' => (int) UserPhoto::where('user_id', $user->id)->min('sort_order') - 1,
            ]);
        }

        if ($makeAvatar && trim((string) $user->profile_picture) === '') {
            $user->forceFill(['profile_picture' => $path])->save();
        }
    }

    /**
     * The person's current photograph, as a stored path.
     *
     * `profile_picture` first because it is the face they chose, then the
     * newest picture in their gallery — a member whose avatar column was
     * cleared still has a face, and showing a silhouette instead would be
     * losing information we hold.
     */
    public static function path(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $chosen = trim((string) $user->profile_picture);

        if ($chosen !== '') {
            return $chosen;
        }

        return UserPhoto::where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->value('path');
    }

    /**
     * A URL for that photograph, or the drawn avatar when there is none.
     *
     * Never null: a face container always has something in it, and a missing
     * face is the drawn avatar rather than an icon or initials.
     */
    public static function url(?User $user): string
    {
        $path = self::path($user);

        return $path ? (string) file_url($path) : Avatar::placeholder($user?->gender);
    }
}
