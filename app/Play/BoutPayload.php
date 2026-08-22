<?php

namespace App\Play;

use App\Models\ClubEvent;
use App\Models\EventMatch;
use App\Models\EventOfficial;

/**
 * One bout, shaped as the competition truth TAKEONE Play should display.
 *
 * Only what the bout page already shows to everyone the event reaches. Nothing
 * here is private: no health data, no contact details, no coaching notes — those
 * stay on takeone and are filtered per viewer (VIDEO-INTEGRATION.md §8.4). If a
 * field would need a permission check to display, it does not belong in this
 * payload at all.
 */
class BoutPayload
{
    /** @return array<string, mixed> */
    public static function for(ClubEvent $event, EventMatch $match, int $revision): array
    {
        $sides = [];

        foreach (['a', 'b'] as $side) {
            $reg = $side === 'a' ? $match->competitorA : $match->competitorB;
            $user = $reg?->user;
            $club = $reg?->competingClub();

            $sides[$side] = array_filter([
                'name' => $match->{$side.'_name'},
                'country' => $reg?->countryCode() ?: $match->{$side.'_country'},
                // The corner is the whole reason Play can attribute a point to a
                // fighter rather than to a colour.
                'corner' => $match->{$side.'_corner'},
                'club' => $club ? array_filter([
                    'name' => $club->club_name,
                    'url' => ($club->slug && $club->country)
                        ? route('clubs.show', ['country' => strtolower($club->country), 'slug' => $club->slug])
                        : null,
                ]) : null,
                // A profile link only for someone discoverable and not a minor —
                // the same rule the bout page itself applies. Withheld silently.
                'profile' => self::profileUrl($user),
            ], fn ($v) => $v !== null && $v !== '');
        }

        /*
         * The whole officiating panel, not just the referee.
         *
         * The LABEL travels with each row because the vocabulary belongs to the
         * sport, not to the video platform: "Referee (Shushin)" is karate's word
         * and Play has no business knowing it (VIDEO-INTEGRATION.md §6.4). Play
         * renders what it is given.
         *
         * An official's flag is their NATIONALITY — they represent no club — which
         * is the opposite of the rule for a competitor.
         */
        $labels = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport)?->officialRoles() ?? [];
        $labels = collect($labels)->pluck('label', 'key')->all();

        $officials = EventOfficial::where('event_id', $event->id)
            // profile_picture, its public flag and updated_at must be listed: a
            // constrained eager load returns NULL for anything omitted, which
            // silently turned every official's photo into a silhouette.
            ->with('user:id,uuid,full_name,name,nationality,is_discoverable,birthdate,profile_picture,profile_picture_is_public,updated_at')
            ->get()
            ->map(fn (EventOfficial $o) => array_filter([
                'role' => $o->role,
                'label' => $labels[$o->role] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', (string) $o->role)),
                'name' => $o->user?->full_name ?: $o->user?->name,
                'uuid' => $o->user?->uuid,
                'country' => self::iso2($o->user?->nationality),
                'profile' => self::profileUrl($o->user),
                /*
                 * Their face, at the platform's portrait ratio (3 wide : 4 tall).
                 *
                 * Honours profile_picture_is_public, the member's own choice about
                 * their picture — the same gate every other surface applies. An
                 * official who has not opted in simply has no photo, and Play draws
                 * a silhouette rather than leaving a gap.
                 */
                'photo' => ($o->user?->profile_picture && $o->user->profile_picture_is_public)
                    ? asset('storage/'.$o->user->profile_picture).'?v='.($o->user->updated_at?->timestamp ?? 0)
                    : null,
            ], fn ($v) => $v !== null && $v !== ''))
            ->filter(fn (array $row) => $row !== [])
            ->values()
            ->all();

        $primary = collect($officials)->firstWhere('role', 'referee');

        return array_filter([
            'revision' => $revision,
            'title' => $match->round ?: ($match->phase ?: null),
            'event_name' => $event->title,
            'sport' => $event->sport,
            'match_type' => $event->sport,
            'match_date' => optional($event->date)->format('Y-m-d'),
            'match_time' => $match->scheduled_time ? substr((string) $match->scheduled_time, 0, 5) : null,
            'venue_name' => $event->location,
            'participant1_name' => $sides['a']['name'] ?? null,
            'participant2_name' => $sides['b']['name'] ?? null,
            'referee_name' => $primary['name'] ?? null,
            'participants' => array_filter([
                'a' => $sides['a'] ?: null,
                'b' => $sides['b'] ?: null,
                'p1_club' => $sides['a']['club']['name'] ?? null,
                'p2_club' => $sides['b']['club']['name'] ?? null,
                'p1_country' => $sides['a']['country'] ?? null,
                'p2_country' => $sides['b']['country'] ?? null,
                'weight_class' => $match->category?->weight_class ?: $match->category?->name,
                'referee' => $primary ? array_filter([
                    'name' => $primary['name'] ?? null,
                    'profile' => isset($primary['uuid']) ? route('people.show', $primary['uuid']) : null,
                ]) : null,
            ], fn ($v) => $v !== null && $v !== ''),
            'result' => array_filter([
                'score_a' => $match->a_score,
                'score_b' => $match->b_score,
                'winner' => $match->winner,
                'winner_name' => $match->winner ? $match->{$match->winner.'_name'} : null,
                'status' => $match->status,
            ], fn ($v) => $v !== null && $v !== ''),
            /*
             * Where the bout sat, and the way back into takeone (§6.6).
             *
             * takeone builds every URL; Play never assembles one, so a route change
             * lands in one place and old matches keep whatever they were sent. The
             * key names are Play's — `match_number` and `court` are what its header
             * reads, and sending `bout_no`/`mat` instead simply rendered nothing.
             *
             * These targets are behind login on takeone, which is the accepted
             * position for this phase (§6.6, option 1): the links serve members,
             * who are the audience that clicks them.
             */
            'competition' => array_filter([
                'round' => $match->round,
                'phase' => $match->phase,
                'match_number' => $match->match_no !== null ? (string) $match->match_no : null,
                'court' => $match->court,
                'day' => $match->day,
                'event' => array_filter([
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'url' => route('me.events.show', $event->uuid),
                ]),
                'bout' => $match->match_no !== null ? array_filter([
                    'match_no' => $match->match_no,
                    'round' => $match->round,
                    'url' => route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]),
                ]) : null,
                'draw' => array_filter([
                    // Scoped to the bout's division, same as the bout page's own
                    // "View draw" — Play links straight through to this board.
                    'url' => route('me.events.bracket', array_filter([
                        'event' => $event->uuid,
                        'category' => $match->category_id,
                        'bout' => $match->match_no,
                    ])),
                ]),
                'category' => array_filter([
                    'name' => $match->category?->name,
                    'weight_class' => $match->category?->weight_class,
                ]) ?: null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []),
            'officials' => $officials,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** A validated ISO-3166 alpha-2 code, or null. */
    private static function iso2(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }

    /**
     * A competitor's profile link, or null.
     *
     * Withheld for anyone who opted out of discovery or is a minor — the same two
     * guards the bout page applies, restated here because a payload crossing to
     * another platform is exactly where a guard gets forgotten.
     */
    private static function profileUrl(?\App\Models\User $user): ?string
    {
        if ($user === null || $user->uuid === null || ! $user->is_discoverable) {
            return null;
        }

        $isMinor = $user->birthdate !== null
            && \Illuminate\Support\Carbon::parse($user->birthdate)->age < 18;

        return $isMinor ? null : route('people.show', $user->uuid);
    }
}
