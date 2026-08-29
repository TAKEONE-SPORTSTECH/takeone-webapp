<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Challenge;
use App\Models\ClubEvent;
use App\Models\Duel;
use App\Models\EventMatch;
use App\Models\Tenant;
use App\Models\User;

/**
 * Where everything this platform stores actually goes.
 *
 * ── Why one class ──────────────────────────────────────────────────────────
 *
 * Because the alternative is what is on disk today: `avatars/`, `documents/`,
 * `payment-screenshots/`, `order-proofs/{id}/`, `perks/{slug}/`, `timeline/{slug}/`,
 * a dozen flat folders invented one feature at a time, none of which can answer
 * "what belongs to this member?" or "delete everything for this club" without a
 * database query and a guess. Video makes that untenable — a competition is
 * gigabytes, it goes on storage somebody browses by hand, and it has to be
 * findable there.
 *
 * So paths are built HERE and nowhere else. Every path is:
 *
 *     {owner}/{owner-public-id}/{purpose}/[{child}/{child-id}/]{generated-name}
 *
 * ── The four rules the shape encodes ───────────────────────────────────────
 *
 * 1. OWNER FIRST. The top two segments say who this belongs to, so everything
 *    for one member, club or event is one subtree — one place to browse, to
 *    total up, to move to another vault, to delete when they leave.
 *
 * 2. PUBLIC IDS, never auto-increment ids, wherever the entity has one. A
 *    member is a uuid, a club is its slug, an event is its uuid. Nothing about
 *    the platform's size or ordering is legible from a path. (Where an entity
 *    genuinely has no public id yet — a bout, a package — its numeric id is
 *    used, and that is safe because these paths are never URLs: media is served
 *    by the media file's own uuid, through a controller that authorises first.)
 *
 * 3. NAMES ARE OURS. The stored filename is always generated — a uuid plus a
 *    server-decided extension. An uploaded filename is untrusted metadata and
 *    is kept in the database if it is wanted, never on disk.
 *
 * 4. DERIVED FILES LIVE UNDER `cache/`. An HLS ladder, a thumbnail rendition, a
 *    generated poster — anything that can be rebuilt from a source — goes under
 *    one root, so "delete this to reclaim disk" is always a safe instruction and
 *    the sync layer knows what never needs to reach a NAS.
 *
 * ── The bout, which is what prompted all this ──────────────────────────────
 *
 *     events/{event-uuid}/matches/{match-id}/clips/{media-uuid}.mp4
 *
 * The video sits under the bout it is of, under the event that bout belongs to.
 * Two cameras on the same bout are two files in the same folder; a whole
 * competition's footage is one folder to copy, archive or hand over.
 *
 * ── What this class does NOT do ────────────────────────────────────────────
 *
 * It does not move anything that already exists. The legacy folders above are
 * still read by the code that wrote them, and nothing here touches them —
 * migrating them is a separate, deliberate act with a backup behind it. This is
 * the shape everything NEW takes.
 */
class StoragePath
{
    /** Everything regenerable. Never synced to a vault, always safe to delete. */
    public const CACHE = 'cache';

    /* ──────────────────────────────────────────────────────────────────────
     | People
     ────────────────────────────────────────────────────────────────────── */

    /**
     * A member's own subtree.
     *
     * Keyed by uuid, not by name or id: a member can change their name, and
     * their folder must not have to be renamed for it — nor should a path reveal
     * that they were the four-hundredth person to sign up.
     */
    public static function member(User $user, string $purpose = ''): string
    {
        return self::join('members', self::key($user->uuid, $user->id), $purpose);
    }

    /** Profile picture, cover — the images a member chooses for themselves. */
    public static function memberProfile(User $user): string
    {
        return self::member($user, 'profile');
    }

    /** Identity documents, certificates, medical papers. Private, always. */
    public static function memberDocuments(User $user): string
    {
        return self::member($user, 'documents');
    }

    /** Proof of payment for one subscription, under the person who paid it. */
    public static function memberPayments(User $user, int|string|null $subscription = null): string
    {
        return self::join(self::member($user, 'payments'), $subscription !== null ? (string) $subscription : '');
    }

    /**
     * Files a member SENT in a conversation.
     *
     * Under the sender, not under the conversation. A conversation is a join
     * between people and owns nothing on disk; the person who chose to send the
     * file is the one it belongs to — which is what makes "everything for this
     * member" one subtree that can be exported, moved or erased in one go.
     */
    public static function memberChat(User $user): string
    {
        return self::member($user, 'chat');
    }

    /** Photos a member added to their own gallery. */
    public static function memberPhotos(User $user): string
    {
        return self::member($user, 'photos');
    }

    /** Certificates a member holds. */
    public static function memberCertifications(User $user): string
    {
        return self::member($user, 'certifications');
    }

    /** Evidence supporting one self-claimed tournament achievement. */
    public static function memberAchievement(User $user, string $tournamentUuid): string
    {
        return self::member($user, 'achievements/'.self::key($tournamentUuid, null));
    }

    /** Media a member attached to one club affiliation. */
    public static function memberAffiliationMedia(User $user, int|string $affiliation): string
    {
        return self::member($user, 'affiliations/'.$affiliation.'/media');
    }

    /** A member's own feed posts and stories. */
    public static function memberPosts(User $user, int|string|null $post = null): string
    {
        return self::join(self::member($user, 'posts'), $post !== null ? (string) $post : '');
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Clubs and businesses
     ────────────────────────────────────────────────────────────────────── */

    /**
     * A club's subtree, keyed by slug.
     *
     * The slug rather than a uuid because a club is a PUBLIC entity — its slug
     * is already in its public URL, it is stable, and it makes a NAS browsable
     * by somebody who knows the clubs but not their ids.
     *
     * Grouped under the country as ISO 3166-1 alpha-3 (`clubs/BHR/my-club`),
     * which mirrors the club's own public URL `/{country}/clubs/{slug}` and
     * keeps a growing list browsable. The slug is already unique platform-wide
     * (`tenants_slug_unique`), so the country groups clubs — it is not what
     * makes them distinct. A club with no country on file lands under `UNK`
     * rather than collapsing the segment.
     */
    public static function club(Tenant $club, string $purpose = ''): string
    {
        return self::clubBySlug((string) $club->slug, $purpose, $club->country, $club->id);
    }

    /**
     * A club subtree when the club does not exist yet.
     *
     * Creation stores the logo before the row is written, which is why those
     * paths used to be a flat `clubs/logos`. The slug is already validated and
     * in hand at that point, so the folder can be the club's own from the very
     * first file — no flat root, and nothing to migrate afterwards.
     */
    public static function clubBySlug(
        string $slug,
        string $purpose = '',
        ?string $country = null,
        int|string|null $id = null,
    ): string {
        return self::join('clubs', Countries::iso3($country), self::key($slug, $id), $purpose);
    }

    public static function clubBranding(Tenant $club): string
    {
        return self::club($club, 'branding');       // logo, cover
    }

    public static function clubGallery(Tenant $club): string
    {
        return self::club($club, 'gallery');
    }

    public static function clubDocuments(Tenant $club): string
    {
        return self::club($club, 'documents');
    }

    /** A package's own images, under the club that sells it. */
    public static function clubPackage(Tenant $club, int|string $package): string
    {
        return self::join(self::club($club, 'packages'), (string) $package);
    }

    /** A shop product's images, under the club that sells it. */
    public static function clubProduct(Tenant $club, int|string $product): string
    {
        return self::join(self::club($club, 'products'), (string) $product);
    }

    /** A club timeline post's attachments. */
    public static function clubPost(Tenant $club, int|string $post): string
    {
        return self::join(self::club($club, 'posts'), (string) $post);
    }

    public static function business(Business $business, string $purpose = ''): string
    {
        return self::join('businesses', self::key($business->slug, $business->id), $purpose);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Events, and the bouts inside them
     ────────────────────────────────────────────────────────────────────── */

    public static function event(ClubEvent $event, string $purpose = ''): string
    {
        return self::join('events', self::key($event->uuid, $event->id), $purpose);
    }

    /** Attached files an organiser publishes — rules, brackets, schedules. */
    public static function eventDocuments(ClubEvent $event): string
    {
        return self::event($event, 'documents');
    }

    /** Poster, cover, the event's own branding. */
    public static function eventBranding(ClubEvent $event): string
    {
        return self::event($event, 'branding');
    }

    /**
     * One bout's folder, under its event.
     *
     * A bout keyed by its numeric id: `event_matches` has no public id, and this
     * path is never a URL. It is also the number an organiser sees on the draw,
     * which is exactly what makes the folder findable by hand.
     */
    public static function match(ClubEvent $event, EventMatch|int|null $match, string $purpose = ''): string
    {
        $id = $match instanceof EventMatch ? $match->getKey() : $match;

        // Footage filmed with nothing loaded on the mat still has to go
        // somewhere sensible — and somewhere an organiser will think to look.
        return $id === null
            ? self::event($event, self::join('unassigned', $purpose))
            : self::join(self::event($event, 'matches'), (string) $id, $purpose);
    }

    /** Where a camera's video of a bout lands. The path this all existed for. */
    public static function boutClips(ClubEvent $event, EventMatch|int|null $match): string
    {
        return self::match($event, $match, 'clips');
    }

    /**
     * A MAT's own folder — for footage of the mat rather than of one bout.
     *
     * A live broadcast is usually this: somebody points a phone at mat 2 and
     * leaves it running while six bouts happen. Filing an hour of that under the
     * first bout would be a lie, and filing it under "unassigned" would hide it.
     * So a continuous broadcast belongs to the mat, and a recording started for a
     * particular bout belongs to the bout.
     */
    public static function matClips(ClubEvent $event, string|int|null $court): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $court);

        return $safe === ''
            ? self::event($event, 'unassigned/clips')
            : self::join(self::event($event, 'mats'), $safe, 'clips');
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Challenges and duels
     ────────────────────────────────────────────────────────────────────── */

    public static function challenge(Challenge $challenge, string $purpose = ''): string
    {
        return self::join('challenges', self::key(null, $challenge->getKey()), $purpose);
    }

    /** A duel's evidence — the video and photos that settle it. */
    public static function duel(Duel $duel, string $purpose = 'media'): string
    {
        return self::join('duels', self::key(null, $duel->getKey()), $purpose);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Platform-owned things, belonging to nobody
     ────────────────────────────────────────────────────────────────────── */

    /** The shared activity directory, screen app builds, imports. */
    public static function platform(string $purpose): string
    {
        return self::join('platform', $purpose);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Derived files
     ────────────────────────────────────────────────────────────────────── */

    /**
     * The HLS ladder for one media file.
     *
     * Under `cache/`, and deliberately NOT beside its source: the source belongs
     * on attached storage, while the ladder is read as dozens of small files per
     * minute of playback and has to be local. Keeping it in one root means
     * reclaiming disk never requires walking the whole library, and the vault
     * sync never has to be taught what to skip.
     */
    public static function hls(string $mediaUuid): string
    {
        return self::join(self::CACHE, 'hls', $mediaUuid);
    }

    /** A copy fetched off a vault that cannot be read in place. Disposable. */
    public static function fetched(string $mediaUuid): string
    {
        return self::join(self::CACHE, 'fetched', $mediaUuid);
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Filenames
     ────────────────────────────────────────────────────────────────────── */

    /**
     * A generated filename inside a directory.
     *
     * The extension is whitelisted here rather than trusted: it is the last
     * place a client-supplied string could otherwise become `.php` on disk.
     */
    public static function file(string $directory, string $uuid, string $extension): string
    {
        $safeUuid = preg_replace('/[^A-Za-z0-9\-]/', '', $uuid) ?: 'file';
        $safeExt = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?: 'bin');

        return self::join($directory, $safeUuid.'.'.$safeExt);
    }

    /**
     * The little file that makes a folder legible to a human on the NAS.
     *
     * An event folder named by a uuid tells somebody standing at a file browser
     * nothing at all. This sits beside the footage and says which competition it
     * was. Minimal by design — enough to identify the folder, never a copy of
     * the record.
     */
    public static function meta(string $directory): string
    {
        return self::join($directory, 'meta.json');
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Internals
     ────────────────────────────────────────────────────────────────────── */

    /**
     * The public key for an entity, falling back to its id.
     *
     * A missing uuid must not produce a path with an empty segment in it — that
     * silently collapses two owners' files into one folder. The `id-` prefix
     * makes the fallback obvious when it happens.
     */
    private static function key(?string $public, int|string|null $id): string
    {
        $clean = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $public);

        return $clean !== '' ? $clean : 'id-'.($id ?? 'unknown');
    }

    /** Join segments, dropping empties, so an absent purpose adds no slash. */
    private static function join(string ...$segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $segment = trim(str_replace('\\', '/', $segment), '/');

            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
