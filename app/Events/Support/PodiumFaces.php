<?php

namespace App\Events\Support;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Events\Models\ClubEventRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A medal row, with the competitor behind it.
 *
 * `event_categories.podium` stores a place, a name and the entry that won it —
 * which is the right thing to STORE (a name printed on a sheet is a record and
 * must not change when somebody edits their profile) and too little to DRAW. A
 * result sheet in a hall carries a face, a flag and a crest, and reading them
 * off the entry is a lookup, not a second copy of the record.
 *
 * So this is the one place that turns stored medals into drawable ones, called
 * by both surfaces that show a podium — the organiser's sheet, through
 * `Results::podium()`, and the public results page, through
 * `PublicEvent::results()` — so the two cannot drift again.
 *
 * ⚠️ WHAT IS AND IS NOT PUBLISHED HERE
 *
 * · The FACE follows the same rule every other competition surface follows: an
 *   entry photograph an organiser took for this event, and otherwise the
 *   member's own profile picture ONLY when they have made it public
 *   (`users.profile_picture_is_public`). A member who keeps their face to
 *   themselves keeps it here too, silently — no picture is not a bug and never
 *   wears a "hidden" label.
 * · The FLAG is the CLUB's country, not the passport's, because that is what a
 *   competition sheet means by a flag — `ClubEventRegistration::countryCode()`
 *   is the one implementation of that rule and this calls it.
 * · The CREST is `club_logo`, the picture an official supplied for THIS event's
 *   screens, falling back to the club's own logo. Never the other way round: an
 *   organiser's crest for the day is the one the hall is showing.
 * · The PERSON's uuid is carried ONLY when this viewer could actually open
 *   their profile — `User::canViewPublicProfile()`, the same predicate
 *   PeopleController makes before it renders one. A link that 404s is a dead
 *   end (CLAUDE.md → Navigation Integrity), and for an anonymous visitor that
 *   rule is deliberately narrow: a member who has opted into being found, and
 *   is not a minor. Everyone else is drawn exactly as before, unlinked, and
 *   nothing on the page says which is which.
 * · Nothing else. No entry ids, no weight, no payment state, no contact — the
 *   same restraint the entry list keeps, and the reason a name is still the
 *   thing this row is about.
 *
 * One query for every competitor on the sheet, not one per medal.
 */
class PodiumFaces
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  division rows, each with `medals` or `places`
     * @return array<int, array<string, mixed>>  the same rows, medals enriched
     */
    public static function enrich(array $rows): array
    {
        $key = static fn (array $row) => array_key_exists('medals', $row) ? 'medals' : 'places';

        $ids = collect($rows)
            ->flatMap(fn (array $row) => collect($row[$key($row)] ?? [])->pluck('competitor_id')->all())
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $rows;
        }

        $entries = static::entries($ids);

        return array_map(function (array $row) use ($entries, $key) {
            $k = $key($row);

            $row[$k] = array_map(function (array $medal) use ($entries) {
                $entry = $medal['competitor_id'] ? ($entries[$medal['competitor_id']] ?? null) : null;

                return $medal + ($entry ?? [
                    'photo' => null, 'country' => null, 'club' => null, 'club_logo' => null,
                ]);
            }, $row[$k] ?? []);

            return $row;
        }, $rows);
    }

    /**
     * competitor id => what may be drawn beside their name.
     *
     * @param  Collection<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private static function entries(Collection $ids): array
    {
        $viewer = Auth::user();

        $entries = ClubEventRegistration::with([
            'user:id,uuid,profile_picture,profile_picture_is_public,nationality,birthdate,is_discoverable,updated_at',
            'representingTenant:id,club_name,country,logo',
        ])
            ->whereIn('id', $ids->all())
            ->get();

        /*
         * ⚠️ A CLUB IS ONE OF TWO THINGS, and asking only the first is how a
         * result sheet ends up with no clubs and no flags on it.
         *
         * A competitor either represents a TENANT on the platform, or is
         * written down by the organiser under a club that exists only for this
         * event (`event_clubs`). `competingClub()` and `countryCode()` know
         * only the first; `EntryClub` is the one place that answers for both,
         * and the bracket board already reads clubs and flags through it. Same
         * source here, same order of precedence — a tenant beats a written-down
         * club — so the podium cannot name a club differently from the board it
         * was won on.
         */
        $written = EntryClub::forMany(
            (int) ($entries->first()?->event_id ?? 0),
            $entries->pluck('id'),
        );

        return $entries
            ->mapWithKeys(fn (ClubEventRegistration $r) => [$r->id => [
                'person' => static::profileUuid($r, $viewer),
                'photo' => static::photo($r),
                'country' => $r->countryCode() ?: ($written[$r->id]['country'] ?? null),
                'club' => static::clubName($r) ?: ($written[$r->id]['name'] ?? null),
                'club_logo' => static::crest($r) ?: ($written[$r->id]['logo'] ?? null),
            ]])
            ->all();
    }

    /**
     * The person's public key — but only where their profile would OPEN.
     *
     * A competitor with no account behind the entry (a name typed at the desk)
     * has nobody to link to, and a member whose profile this viewer may not see
     * is not linked rather than linked to a 404.
     */
    private static function profileUuid(ClubEventRegistration $r, ?User $viewer): ?string
    {
        $user = $r->user;

        if (! $user?->uuid) {
            return null;
        }

        return $user->canViewPublicProfile($viewer) ? $user->uuid : null;
    }

    /**
     * The face, at the size a result row paints it — never the original.
     *
     * The reading itself is App\Events\Support\CompetitorFace, which every
     * surface that draws a competitor shares.
     */
    private static function photo(ClubEventRegistration $r): ?string
    {
        return CompetitorFace::for($r);
    }

    private static function clubName(ClubEventRegistration $r): ?string
    {
        $club = $r->competingClub();

        return $club?->club_name ?: null;
    }

    /**
     * The crest: the one supplied for this event first, the club's own second.
     *
     * Sized for a row rather than a hero — `tile` is the logo rendition, and a
     * logo is drawn on a transparent background with no white tile behind it
     * (Design Rule #5).
     */
    private static function crest(ClubEventRegistration $r): ?string
    {
        if ($r->club_logo) {
            return thumb_url(file_url($r->club_logo), 'tile');
        }

        $club = $r->competingClub();

        return $club instanceof Tenant && $club->logo
            ? thumb_url(file_url($club->logo), 'tile')
            : null;
    }
}
