<?php

namespace App\Support;

use App\Models\ClubEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelationship;

/**
 * Who may read one stored file.
 *
 * There is ONE storage root. A file is not reachable because of where it sits
 * on disk — it is reachable because this class says so. That is the whole point
 * of collapsing the public/private split: "which folder is it in" was an
 * access-control decision made by a symlink, and a symlink cannot ask who is
 * asking.
 *
 * Deny by default. A path shape nobody has taught this class about is refused,
 * so a new upload folder is invisible until somebody decides, in code, who
 * should see it.
 */
class FileAccess
{
    /**
     * May $viewer (possibly null — an anonymous visitor) read $path?
     */
    public static function allows(string $path, ?User $viewer): bool
    {
        $path = ltrim($path, '/');

        // A traversal or an empty path is not a file we own.
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        $segments = explode('/', $path);
        $root = $segments[0] ?? '';

        return match ($root) {
            'clubs' => self::club($segments, $viewer),
            'members' => self::member($segments, $viewer),
            'events' => self::event($segments, $viewer),
            default => false,   // deny by default
        };
    }

    /**
     * A club's own branding, gallery, facilities and package covers.
     *
     * Public by intent: these render on the club's public page, which anyone
     * may open. The club's DOCUMENTS are not in that list and stay closed.
     */
    private static function club(array $segments, ?User $viewer): bool
    {
        // clubs/{ISO3}/{slug}/{purpose}/...
        $purpose = $segments[3] ?? '';

        if (in_array($purpose, ['branding', 'gallery', 'facilities', 'packages', 'products', 'activities', 'timeline', 'achievements'], true)) {
            return true;
        }

        // Anything else under a club — documents, and whatever is added later —
        // is for people who run that club.
        $club = Tenant::where('slug', $segments[2] ?? '')->first();

        return $club !== null && $viewer !== null && self::runsClub($viewer, $club);
    }

    /**
     * A member's own files.
     *
     * The member, their guardian and platform staff may always read them.
     * Beyond that only the two things a member chooses to show — their profile
     * picture and gallery photos — and only while that choice stands.
     */
    private static function member(array $segments, ?User $viewer): bool
    {
        $uuid = $segments[1] ?? '';
        $purpose = $segments[2] ?? '';

        $owner = User::where('uuid', $uuid)->first();
        if ($owner === null) {
            return false;
        }

        if ($viewer !== null && self::speaksFor($viewer, $owner)) {
            return true;
        }

        // Faces are the member's own decision. `profile_picture_is_public` is
        // the flag the rest of the product already honours on any surface wider
        // than their own profile, so it decides here too.
        if (in_array($purpose, ['profile', 'photos'], true)) {
            return (bool) ($owner->profile_picture_is_public ?? true);
        }

        // documents, payments, achievements, certifications, affiliations, chat —
        // never anyone else's business.
        return false;
    }

    /** An event's files follow the event's own visibility rules. */
    private static function event(array $segments, ?User $viewer): bool
    {
        $event = ClubEvent::where('uuid', $segments[1] ?? '')->first();

        if ($event === null || $viewer === null) {
            return false;
        }

        return app(\App\Events\Support\EventAccess::class)->visible($event, $viewer);
    }

    /** The member themselves, their guardian, or platform staff. */
    private static function speaksFor(User $viewer, User $owner): bool
    {
        if ($viewer->id === $owner->id) {
            return true;
        }

        if ($viewer->hasRole('super-admin')) {
            return true;
        }

        return UserRelationship::where('guardian_user_id', $viewer->id)
            ->where('dependent_user_id', $owner->id)
            ->exists();
    }

    private static function runsClub(User $viewer, Tenant $club): bool
    {
        return $viewer->hasRole('super-admin')
            || (int) $club->owner_user_id === (int) $viewer->id
            || $viewer->hasRole('club-admin', $club->id);
    }
}
