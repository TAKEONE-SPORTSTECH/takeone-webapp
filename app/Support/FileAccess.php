<?php

namespace App\Support;

use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
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
            default => self::legacy($root, $segments, $viewer),
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
        // clubs/{ISO3}/{slug}/{purpose}/... is the shape everything new takes.
        // clubs/{id}/{purpose}/... is what the old layout wrote, and files
        // written before the move are still on disk under it.
        $legacy = ctype_digit((string) ($segments[1] ?? ''));
        $purpose = $legacy ? ($segments[2] ?? '') : ($segments[3] ?? '');

        if (in_array($purpose, ['branding', 'gallery', 'facilities', 'packages', 'products', 'activities', 'timeline', 'achievements'], true)) {
            return true;
        }

        // Anything else under a club — documents, and whatever is added later —
        // is for people who run that club.
        $club = $legacy
            ? Tenant::find((int) $segments[1])
            : Tenant::where('slug', $segments[2] ?? '')->first();

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

    /**
     * The folders that predate the owner-first layout.
     *
     * These were not organised by owner, so the path alone does not say whose
     * file it is. What it did say, until the roots were merged, was which DISK
     * it sat on — and that was the access decision: everything under the public
     * disk was readable by anyone holding the URL, everything under the private
     * one was not. That split is reproduced here deliberately, so collapsing the
     * two roots changed where files live without changing who can read them.
     *
     * New uploads never land here; StoragePath writes the owner-first shape.
     * When the last legacy file is migrated, this method goes.
     */
    private static function legacy(string $root, array $segments, ?User $viewer): bool
    {
        // Presentation assets: club and product imagery, catalogue art, avatars.
        // Public before the merge, public now.
        $public = [
            'achievements', 'activity-catalog', 'avatars', 'business-logos',
            'club-products', 'images', 'packages', 'people', 'perks',
            'temp', 'timeline', 'user-posts', 'users',
        ];

        if (in_array($root, $public, true)) {
            return true;
        }

        // Everything else that was on the private disk. Staff may read it; the
        // member it belongs to may read it; nobody else may, and an anonymous
        // visitor never gets this far.
        if ($viewer === null) {
            return false;
        }

        if ($viewer->hasRole('super-admin')) {
            return true;
        }

        $path = implode('/', $segments);

        return match ($root) {
            'goal-proofs' => self::ownsRow($viewer, 'goals', ['before_proof', 'after_proof'], $path),
            'order-proofs' => self::ownsRow($viewer, 'orders', ['payment_proof_path'], $path),
            'payment-proofs', 'payment-screenshots', 'event-payment-proofs' => self::ownsRow(
                $viewer, 'club_member_subscriptions', ['proof_of_payment', 'refund_proof'], $path
            ),
            'chat-attachments' => self::sentOrReceived($viewer, $path),
            'documents' => self::ownsRow($viewer, 'users', ['documents'], $path),
            default => false,   // deny by default, including backups and demo
        };
    }

    /**
     * Does a row this viewer owns point at this exact path?
     *
     * Matched against the stored value rather than parsed out of the path,
     * because the legacy filenames carry no owner. LIKE is used only for the
     * JSON columns that hold several paths in one field.
     */
    private static function ownsRow(User $viewer, string $table, array $columns, string $path): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return false;
        }

        $owner = $table === 'users' ? 'id' : 'user_id';

        if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $owner)) {
            return false;
        }

        $query = \Illuminate\Support\Facades\DB::table($table)->where($owner, $viewer->id);

        $query->where(function ($q) use ($columns, $path, $table) {
            foreach ($columns as $column) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                    continue;
                }
                $q->orWhere($column, $path)
                  ->orWhere($column, '/'.$path)
                  ->orWhere($column, 'like', '%"'.$path.'"%');
            }
        });

        return $query->exists();
    }

    /** A chat attachment belongs to both ends of the conversation. */
    private static function sentOrReceived(User $viewer, string $path): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('messages')) {
            return false;
        }

        $message = \Illuminate\Support\Facades\DB::table('messages')
            ->where('attachment_path', $path)
            ->orWhere('attachment_path', '/'.$path)
            ->first();

        if ($message === null) {
            return false;
        }

        foreach (['sender_id', 'user_id', 'recipient_id', 'receiver_id'] as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('messages', $column)
                && (int) ($message->{$column} ?? 0) === (int) $viewer->id) {
                return true;
            }
        }

        return false;
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
