<?php

namespace App\Events\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Throwing away an entry's photograph without throwing away somebody's face.
 *
 * `club_event_registrations.photo` holds one of two very different things:
 *
 *  1. A picture the ORGANISER took for this competition, written to
 *     `events/{uuid}/competitors/…` and owned by the entry. When the entry
 *     loses it, the file should go.
 *
 *  2. A REFERENCE to the athlete's own `users.profile_picture`. `PublicEntry`
 *     settles an entry that way on purpose — the face they supplied to enter is
 *     already stored on their profile, and copying the bytes a second time to
 *     say the same thing would be waste.
 *
 * Every place that dropped an entry photo treated it as case 1. On a case-2 row
 * that deletes the MEMBER's profile picture from disk while `users.profile_picture`
 * and their `user_photos` row carry on pointing at it — the column survives, the
 * file does not, and their profile renders a broken image with nothing in the
 * logs. Six pictures were lost that way before this class existed.
 *
 * So: ask before deleting. A path another record still claims is not this
 * entry's to remove.
 */
class EntryPhoto
{
    /**
     * Delete an entry's photo file — unless somebody else still points at it.
     *
     * Best-effort, like every other file purge in the platform: the row's own
     * change is what matters and a storage error must never block it.
     */
    public static function discard(?string $path, string $disk = 'public'): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (self::isClaimedElsewhere($path)) {
            return;
        }

        rescue(fn () => Storage::disk($disk)->delete($path), null, false);
    }

    /**
     * A face for this person that a competition ALREADY publishes.
     *
     * The other half of the problem this class exists for. An organiser who
     * photographs a competitor at the desk writes that picture to
     * `events/{uuid}/competitors/…` and nowhere else — it never becomes
     * `users.profile_picture`, because it is the competition's record of who
     * turned up, not a face the member chose to wear on the platform. So an
     * athlete can be looking back at you from an entry list and have a blank
     * silhouette on their own profile a tap later, which reads as a bug even
     * though every part of it is behaving as designed.
     *
     * This closes that gap WITHOUT publishing anything new. The predicate is
     * character-for-character the one App\Support\FileAccess already grants
     * on (`isPublicEntryPhoto`): the entry's event must be in public mode and
     * not archived. A reader who can see this URL here could already see the
     * identical file on the entry list they clicked through from — and the
     * moment the organiser un-publishes the event, both close together.
     *
     * Newest first, because a competitor's most recent weigh-in photograph is
     * the one that still looks like them.
     *
     * @return string|null  the stored path, or null when nothing qualifies
     */
    public static function publicFaceFor(int $userId): ?string
    {
        return DB::table('club_event_registrations as r')
            ->join('club_events as e', 'e.id', '=', 'r.event_id')
            ->where('r.user_id', $userId)
            ->whereNotNull('r.photo')
            ->where('r.photo', '!=', '')
            ->where('e.entry_mode', 'public')
            ->where('e.is_archived', false)
            ->orderByDesc('r.id')
            ->value('r.photo');
    }

    /**
     * Is this entry photo actually SHOWABLE — a path with bytes behind it?
     *
     * Naming a file and having one are different things, and the gap between
     * them is what turned a competitor into a silhouette on every list while
     * their profile showed a perfectly good picture from another path. The
     * resolvers all preferred the entry photo unconditionally, so a dangling
     * path did not fall through to the profile picture — it stopped there.
     *
     * The guards above make new orphans much less likely; this makes the ones
     * already in the database harmless. Both are wanted: a stale path can also
     * arrive from a restore, a half-finished move, or a vault that is briefly
     * unreachable, and none of those should blank a face that exists.
     *
     * One stat per competitor, served from PHP's stat cache, which is the price
     * of not drawing a broken face on a page an organiser hands to a hall.
     */
    public static function showable(?string $path, string $disk = 'public'): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return (bool) rescue(fn () => Storage::disk($disk)->exists($path), false, false);
    }

    /**
     * Is this file still named by a record OTHER than the one being changed?
     *
     * The mirror of `discard()`, and the half that was missing. `discard()`
     * stops an ENTRY from deleting a face the member's profile still points at.
     * Nothing stopped the PROFILE from deleting a face an ENTRY still points
     * at — so replacing or removing a profile picture unlinked the bytes while
     * `club_event_registrations.photo` carried on naming them, and the
     * competitor turned into a silhouette on the entry list, the draw and the
     * hall screen, with nothing in the logs.
     *
     * That is not theoretical either: reg 2462 named
     * `members/…/profile/2ebdd3d7….jpg` for weeks after the file went, while
     * the same member's profile showed a perfectly good picture from a
     * different path. It is what this method now prevents.
     *
     * ⚠️ `users` is checked with an EXCLUSION rather than not at all, because
     * duplicate accounts share a path here — nine "Ali altooq" rows carry the
     * identical `profile_picture`. Deleting the file for one of them blanks it
     * for all nine, so "no other user claims it" is a question that has to be
     * asked, not assumed.
     *
     * @param  int|null $exceptUserId          the user whose row is changing
     * @param  int|null $exceptRegistrationId  the entry whose row is changing
     */
    public static function claimedByAnother(
        string $path,
        ?int $exceptUserId = null,
        ?int $exceptRegistrationId = null,
    ): bool {
        if ($path === '') {
            return false;
        }

        $users = DB::table('users')->where('profile_picture', $path);

        if ($exceptUserId !== null) {
            $users->where('id', '!=', $exceptUserId);
        }

        if ($users->exists()) {
            return true;
        }

        if (Schema::hasTable('user_photos')
            && DB::table('user_photos')->where('path', $path)->exists()) {
            return true;
        }

        $entries = DB::table('club_event_registrations')->where('photo', $path);

        if ($exceptRegistrationId !== null) {
            $entries->where('id', '!=', $exceptRegistrationId);
        }

        return $entries->exists();
    }

    /**
     * Delete a file only if nothing else still names it.
     *
     * The one call every replace/remove path should make instead of reaching
     * for Storage::delete() directly. Best-effort, like `discard()`: the row's
     * own change is what matters and a storage error must never block it.
     */
    public static function discardShared(
        ?string $path,
        ?int $exceptUserId = null,
        ?int $exceptRegistrationId = null,
        string $disk = 'public',
    ): void {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (self::claimedByAnother($path, $exceptUserId, $exceptRegistrationId)) {
            return;
        }

        rescue(fn () => Storage::disk($disk)->delete($path), null, false);
    }

    /**
     * Does a record OTHER than an event entry still name this exact file?
     *
     * Matched on the stored value, not on the path's shape: the shape is
     * exactly what has been unreliable here — a competitor path is safe to
     * delete only because nothing else uses it, and that is a fact about the
     * database, not about the folder name.
     */
    public static function isClaimedElsewhere(string $path): bool
    {
        if (DB::table('users')->where('profile_picture', $path)->exists()) {
            return true;
        }

        if (Schema::hasTable('user_photos')
            && DB::table('user_photos')->where('path', $path)->exists()) {
            return true;
        }

        return false;
    }
}
