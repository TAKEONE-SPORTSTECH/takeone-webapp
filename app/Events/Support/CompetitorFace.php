<?php

namespace App\Events\Support;

use App\Events\Models\ClubEventRegistration;

/**
 * A competitor's face, decided ONCE for every surface that draws one.
 *
 * There are two pictures of the same athlete and they are not interchangeable:
 *
 *   1. `club_event_registrations.photo` — taken by an official FOR this
 *      competition, at the desk or the weigh-in. It belongs to the entry, and
 *      on a championship run properly it is the picture nearly everybody has.
 *   2. `users.profile_picture` — the member's own face on the platform, shown
 *      only where they have published it (`profile_picture_is_public`). It is
 *      their choice about their own photograph and no competition surface
 *      overrides it.
 *
 * The order is entry first, member second, nothing third — and "nothing" is an
 * ordinary answer, drawn as the gendered silhouette and never labelled.
 *
 * ⚠️ WHY THIS CLASS EXISTS. That reading was written out three times — the
 * bracket board, the podium, and the opponents list on a person's profile —
 * and the third copy had only ever learned about the member's own picture. So
 * a competitor appeared on the board, appeared on the result sheet, and then
 * had a blank tile beside every bout on their own profile, on a championship
 * where every single face was photographed at the desk. One copy now
 * (CLAUDE.md → Shared Stays Shared).
 */
class CompetitorFace
{
    /**
     * @param  string  $size  a name from App\Support\Thumbnails::SIZES, or ''
     *                        for the original — a face drawn SMALL asks for a
     *                        rendition, a face drawn at size reads the file.
     */
    public static function for(?ClubEventRegistration $entry, string $size = 'face'): ?string
    {
        if (! $entry) {
            return null;
        }

        if (EntryPhoto::showable($entry->photo)) {
            return static::stamp(file_url($entry->photo), $size, $entry->updated_at?->timestamp);
        }

        $user = $entry->user;

        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return static::stamp(file_url($user->profile_picture), $size, $user->updated_at?->timestamp);
    }

    /**
     * The rendition, then the cache-buster — with the separator decided rather
     * than guessed, because `thumb_url()` returns a remote avatar untouched and
     * an unsized call adds no query at all.
     */
    private static function stamp(?string $url, string $size, ?int $version): ?string
    {
        $url = $size !== '' ? thumb_url($url, $size) : $url;

        if (! is_string($url) || $url === '') {
            return null;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.($version ?? 0);
    }
}
