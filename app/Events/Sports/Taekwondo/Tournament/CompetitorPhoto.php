<?php

namespace App\Events\Sports\Taekwondo\Tournament;

use App\Models\ClubEventRegistration;
use App\Models\User;

/**
 * Which picture the screens show for a competitor.
 *
 * One answer, asked by every surface of the event — the introduction, the mat,
 * the upcoming board. It used to be answered twice: the VS screen preferred the
 * picture an official added at the desk, while the upcoming board only ever
 * looked at the member's account picture. So a photo taken at the scoring table
 * appeared on one screen in the hall and not on the one beside it.
 *
 * ── The order, and why ──────────────────────────────────────────────────────
 *
 *  1. THIS registration's own picture. An official supplied it FOR this event's
 *     screens, so it needs no further permission and grants none elsewhere.
 *
 *  2. Any picture the same PERSON has on another entry in the SAME event. An
 *     athlete entered in two divisions is two registrations, and nobody at the
 *     desk should have to photograph them twice — nor should the hall see them
 *     with a face in one bout and a silhouette in the next. Still confined to
 *     one competition: it never reaches across events.
 *
 *  3. The member's own profile picture, and ONLY when they have published it.
 *     `profile_picture_is_public` is their answer about whether their face may
 *     be shown to people who are not their club, and a hall screen is the most
 *     public surface in the product.
 *
 * Otherwise null, and the screen draws its silhouette.
 */
class CompetitorPhoto
{
    /**
     * Desk-added pictures for one event, as user id => storage path.
     *
     * Filled by ONE query the first time an event is asked about, because the
     * upcoming board resolves a dozen competitors per render and re-renders on
     * every result entered on any mat. Kept per request only — this object is
     * resolved fresh each time, so a picture added at the desk is visible to
     * the very next request.
     *
     * @var array<int, array<int, string>>
     */
    private array $byEvent = [];

    public function url(?ClubEventRegistration $entry, ?User $user, ?int $eventId = null): ?string
    {
        if ($entry?->photo) {
            return file_url($entry->photo);
        }

        $eventId ??= $entry?->event_id;

        if ($user && $eventId && ($path = $this->sameEvent($eventId, $user->id))) {
            return file_url($path);
        }

        if ($user?->profile_picture && $user->profile_picture_is_public) {
            return file_url($user->profile_picture);
        }

        return null;
    }

    /** A picture this person already has on some other entry in this event. */
    private function sameEvent(int $eventId, int $userId): ?string
    {
        $this->byEvent[$eventId] ??= ClubEventRegistration::where('event_id', $eventId)
            ->whereNotNull('photo')
            ->pluck('photo', 'user_id')
            ->all();

        return $this->byEvent[$eventId][$userId] ?? null;
    }

    /**
     * Crests supplied at the desk for one event, as club id => storage path.
     *
     * @var array<int, array<int, string>>
     */
    private array $crestsByEvent = [];

    /**
     * The crest shown beside a competitor's club name.
     *
     * Same shape of answer as the picture, one rung different in the middle:
     *
     *  1. THIS registration's own crest, supplied at the desk for this event.
     *
     *  2. A crest supplied for the SAME CLUB anywhere in this event. A club
     *     sends a squad, not one athlete — an official who photographs the
     *     visiting club's badge once has answered it for every one of their
     *     competitors, and a hall where half a team carries a crest and half
     *     does not looks like a fault.
     *
     *  3. The club's own logo from its account, which is the normal case and
     *     needs no help from anybody at the desk.
     *
     * @param  \App\Clubs\Models\Tenant|null  $club
     */
    public function crestUrl(?ClubEventRegistration $entry, $club, ?int $eventId = null): ?string
    {
        if ($entry?->club_logo) {
            return file_url($entry->club_logo);
        }

        $eventId ??= $entry?->event_id;

        if ($club && $eventId && ($path = $this->sameClub($eventId, (int) $club->id))) {
            return file_url($path);
        }

        return $club?->logo ? file_url($club->logo) : null;
    }

    /**
     * A crest already supplied for this club somewhere in this event.
     *
     * Which club an entry belongs to is the member's own first club — the same
     * answer every screen already uses to print the club NAME, so the crest can
     * never end up beside the wrong one. Only entries that actually carry a
     * crest are loaded, which is a handful even at a full championship.
     */
    private function sameClub(int $eventId, int $clubId): ?string
    {
        $this->crestsByEvent[$eventId] ??= ClubEventRegistration::where('event_id', $eventId)
            ->whereNotNull('club_logo')
            ->with('user.memberClubs:id')
            ->get()
            ->reduce(function (array $map, ClubEventRegistration $r) {
                $id = $r->user?->memberClubs->first()?->id;

                // First one wins: two officials photographing the same badge
                // must not make the crest flicker between renders.
                if ($id && ! isset($map[$id])) {
                    $map[$id] = $r->club_logo;
                }

                return $map;
            }, []);

        return $this->crestsByEvent[$eventId][$clubId] ?? null;
    }
}
