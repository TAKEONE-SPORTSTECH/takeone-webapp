<?php

namespace App\Support;

use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;

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

        // An event poster, for an event whose page anybody may open. Event
        // images have always been written under the CLUB
        // (clubs/{id}/events/…), so the public page would otherwise show a
        // broken poster for every real event. Narrow deliberately: this exact
        // file must be the poster of an event the organiser put in public mode
        // — it is not "club event images are public".
        if ($purpose === 'events' && self::isPublicEventPoster(implode('/', $segments))) {
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

        if (in_array($purpose, ['profile', 'photos'], true)) {
            /*
             * The face somebody supplied to ENTER a competition whose page
             * anybody may open.
             *
             * Entering through the public link REQUIRES a photo, and it is
             * required so the competition can announce them. It is stored on
             * their profile with `profile_picture_is_public` off, because
             * coming to compete is not publishing your face across the
             * platform — so without this the one thing we insisted on could
             * never be shown, and the entry list a stranger opens was a column
             * of silhouettes.
             *
             * Narrow the same way the poster exception is narrow: this EXACT
             * path must be the `photo` of an entry in an event that is in
             * public mode. A guessed filename proves nothing — a row has to
             * name the file. It is not "member profile pictures are public",
             * and the moment the organiser un-publishes the event it stops.
             */
            if (self::isPublicEntryPhoto(implode('/', $segments))) {
                return true;
            }

            // Otherwise a face is the member's own decision.
            // `profile_picture_is_public` is the flag the rest of the product
            // already honours on any surface wider than their own profile, so
            // it decides here too.
            return (bool) ($owner->profile_picture_is_public ?? true);
        }

        // documents, payments, achievements, certifications, affiliations, chat —
        // never anyone else's business.
        return false;
    }

    /**
     * Is this exact path the poster of an event anybody may open?
     *
     * Asked of the stored `images` list rather than of the path's shape, so a
     * guessed filename proves nothing — the row has to name the file.
     */
    private static function isPublicEventPoster(string $path): bool
    {
        // `images` is a JSON column, and json_encode escapes forward slashes —
        // the stored text is "clubs\/67\/events\/x.jpg". Matching the raw path
        // silently finds nothing, so the needle is encoded the same way.
        $needle = trim(json_encode($path), '"');

        return ClubEvent::query()
            ->where('entry_mode', 'public')
            ->where('is_archived', false)
            ->whereNotNull('images')
            ->where('images', 'like', '%'.$needle.'%')
            ->exists();
    }

    /**
     * Is this exact path the competitor photo of an entry in a public event?
     *
     * Asked of the entry ROW, like isPublicEventPoster() is asked of the
     * event's own `images` — the path's shape proves nothing on its own.
     */
    private static function isPublicEntryPhoto(string $path): bool
    {
        return \App\Models\ClubEventRegistration::query()
            ->where('photo', $path)
            ->whereHas('event', fn ($q) => $q
                ->where('entry_mode', 'public')
                ->where('is_archived', false))
            ->exists();
    }

    /**
     * Is this exact path the crest of a club written down for THIS event?
     *
     * Asked of the row, like its two neighbours: the event has to own the club
     * and the club has to name the file. Scoped to the event the path itself
     * claims, so one event's uuid can never open another's folder.
     */
    private static function isEventClubCrest(ClubEvent $event, string $path): bool
    {
        return \App\Models\EventClub::query()
            ->where('event_id', $event->id)
            ->where('logo', $path)
            ->exists();
    }

    /** An event's files follow the event's own visibility rules. */
    private static function event(array $segments, ?User $viewer): bool
    {
        $event = ClubEvent::where('uuid', $segments[1] ?? '')->first();

        if ($event === null) {
            return false;
        }

        // An event with a page anybody may open needs a POSTER anybody may see —
        // and, since 2026-09-02, the FILES it publishes too: the rulebook, the
        // entry form, the schedule. Both are gated on the organiser's own
        // switch, so turning the public page off closes them again in the same
        // instant. See Documentation/EVENTS-PUBLIC-ENTRY.md, Phase B.
        //
        // Still narrow: `branding` and `documents` only. An event's CLIPS and
        // its screens stay exactly as closed as they have always been — bout
        // footage is not a poster fact.
        if (in_array($segments[2] ?? '', ['branding', 'documents'], true)
            && app(\App\Events\Support\PublicEvent::class)->isPublic($event)) {
            return true;
        }

        /*
         * A competitor's face, on the entry list of a competition anybody may
         * open.
         *
         * The same exception member() already makes, on the other shape the
         * same picture takes. An entry photo is written to
         * `events/{uuid}/competitors/…` when an ORGANISER supplies it (the
         * weigh-in desk, the entry editor) and left pointing at the athlete's
         * own `members/{uuid}/profile/…` when they entered through the public
         * door. member() was taught about the second shape and nothing was
         * taught about the first, so a public entry list showed a photograph
         * for the athletes who uploaded their own and a broken image for every
         * one the organiser photographed — 8 of 30 on the first real event.
         *
         * Narrow the identical way: this EXACT path must be the `photo` of an
         * entry in an event that is in public mode. A guessed filename proves
         * nothing, and un-publishing the event closes it again.
         */
        if (($segments[2] ?? '') === 'competitors'
            && self::isPublicEntryPhoto(implode('/', $segments))) {
            return true;
        }

        /*
         * The CREST of a club an organiser wrote down at the desk.
         *
         * A club that is not on the platform is typed in with a name and a
         * logo, and that logo is written to `events/{uuid}/clubs/…` — while a
         * platform club's crest sits in `clubs/{ISO3}/{slug}/branding/`, which
         * is public without condition. Same kind of object, two shapes, and
         * only one of them was reachable: everything that reads a competition
         * WITHOUT a session showed a blank where the crest should be.
         *
         * The hall board is the case that made it obvious. A wall screen is
         * authorised by its pairing TOKEN, not by a session, so it is anonymous
         * here — the introduction drew the names, the flags and the faces (which
         * have their own exception above) and left the crests empty. It looked
         * correct in a browser purely because the person checking was logged in.
         * The public event page had the same hole for every visitor.
         *
         * Deliberately NOT gated on the event's public mode, unlike the poster
         * and the entry photo either side of this. Those two are somebody's
         * competition being advertised and somebody's face; a club crest is a
         * logo its owner publishes, of the same class as the `branding/` folder
         * that is open to everyone — and it was supplied expressly to be put on
         * a wall in front of a hall.
         *
         * Still path-proven, like its neighbours: an `event_clubs` row for THIS
         * event has to name this exact file. A guessed filename proves nothing.
         */
        if (($segments[2] ?? '') === 'clubs'
            && self::isEventClubCrest($event, implode('/', $segments))) {
            return true;
        }

        if ($viewer === null) {
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
