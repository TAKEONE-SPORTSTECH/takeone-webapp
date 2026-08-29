<?php

namespace App\Observers;

use App\Models\User;

/**
 * Keeps a member's several pictures in step with their avatar.
 *
 * `users.profile_picture` stays authoritative for the avatar, but the profile's
 * picture viewer and the photo sheet read `user_photos`. Older upload paths
 * (the member/platform `upload-picture` endpoints, imports, seeders) write only
 * the column, so a member could have a face on file that those surfaces showed
 * as empty. Adopting the avatar into the set here fixes every path at once.
 */
class UserObserver
{
    public function saved(User $user): void
    {
        if (! $user->wasChanged('profile_picture')) {
            return;
        }

        $path = $user->profile_picture;

        if (! $path) {
            return;
        }

        // Already one of the profile's pictures (the photo sheet's own uploads
        // and avatar switches land here) — nothing to adopt.
        if ($user->photos()->where('path', $path)->exists()) {
            return;
        }

        $user->photos()->create([
            'path' => $path,
            'sort_order' => (int) $user->photos()->max('sort_order') + 1,
        ]);

        $user->unsetRelation('photos');
    }
}
