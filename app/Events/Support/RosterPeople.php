<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Support\Collection;

/**
 * The roster as a READING surface — who is competing, and which clubs they came
 * from.
 *
 * This is not the officials' desk. Nothing here can be acted on: no registration
 * ids, no weights, no payment state, no moderation. It takes the rows an event
 * type already built and adds only the three things a viewer needs to recognise
 * someone and follow them somewhere — a face, a club, and a link.
 *
 * It is deliberately sport-agnostic. Every event type's rosterRows() is keyed by
 * user id, and "what does this person look like, and where do they train" is the
 * same question whatever the sport, so this lives beside the types rather than
 * inside one.
 */
class RosterPeople
{
    /**
     * Split a roster into the two things the people page shows.
     *
     * @param  array<int, array<string, mixed>>  $rows  rosterRows() output
     * @return array{participants: array<int, array<string, mixed>>, clubs: array<int, array<string, mixed>>}
     */
    public function build(array $rows, ?ClubEvent $event = null): array
    {
        $users = $this->users($rows);
        $representing = $event ? $this->representing($event) : collect();

        $participants = collect($rows)
            ->map(fn (array $row) => $this->participant(
                $row,
                $users->get($row['id'] ?? null),
                $representing->has($row['id'] ?? null) ? $representing->get($row['id']) : false,
            ))
            ->values()->all();

        return [
            'participants' => $participants,
            'clubs' => $this->clubs($participants),
        ];
    }

    /**
     * Fold a roster of ENTRIES into a roster of PEOPLE.
     *
     * One athlete may hold more than one entry in the same event — a Gi group
     * and a No-Gi group at one championship is the ordinary case, and the owner
     * settled the model on 2026-09-11: "the person is one, the payment and
     * activity he is paying for is multi". The event types build their rosters
     * per ENTRY because that is what the draw needs; every surface that reads
     * as a list of PEOPLE folds them first, or the same face appears twice with
     * nothing on either card saying why.
     *
     * The FIRST row a person appears on is kept whole, so nothing changes for
     * the entrant who holds one entry. Two things are added:
     *
     *   · `divisions` — every group they are entered in, for the card's chips.
     *   · `entries`   — each entry's own id and division, in roster order. This
     *     is what keeps the money and the scale honest: a fee, a receipt and a
     *     weigh-in signature belong to an ENTRY, and the verification desk
     *     works one at a time.
     *
     * A row with no user behind it (a name typed at the desk, never linked to
     * an account) is never merged with anything.
     *
     * Lives here rather than in a controller because it is the same question
     * everywhere: the organiser's roster page, the public entry list and the
     * MCP tool all answer it, and CLAUDE.md's *Shared Stays Shared* says that
     * is one implementation.
     *
     * @param  array<int, array<string, mixed>>  $rows  rosterRows() output
     * @return array<int, array<string, mixed>>
     */
    public function byPerson(array $rows): array
    {
        $merged = [];

        foreach (array_values($rows) as $i => $row) {
            $entry = [
                'registration' => $row['registration'] ?? null,
                'division' => $row['category'] ?? $row['weight_class'] ?? null,
            ];

            $key = ($row['id'] ?? null) !== null ? 'u'.$row['id'] : 'r'.$i;

            if (! isset($merged[$key])) {
                $row['entries'] = [$entry];
                $row['divisions'] = array_values(array_filter([$entry['division']]));
                $merged[$key] = $row;

                continue;
            }

            $merged[$key]['entries'][] = $entry;

            if ($entry['division'] && ! in_array($entry['division'], $merged[$key]['divisions'], true)) {
                $merged[$key]['divisions'][] = $entry['division'];
            }
        }

        /* WHICH ACTIVITIES each person is in — "Gi", "No-Gi", "Gi + No-Gi" —
           read off the division names they hold (ActivityTag). Null for an
           event that runs one activity, which is most of them. */
        foreach ($merged as $key => $row) {
            $merged[$key]['activity'] = ActivityTag::label($row['divisions'] ?? []);
        }

        return array_values($merged);
    }

    /**
     * The competitors, hydrated.
     *
     * Only fields that are already public to anyone standing in the hall. The
     * division tokens come from the row the event type built and are passed
     * through untouched — they are the same ones printed on the draw.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function participant(array $row, ?User $user, mixed $representing = false): array
    {
        // What the entry itself says the athlete competes for beats what their
        // profile implies. `false` means the entry had nothing to say (a row
        // predating the column), `null` means it said UNATTACHED — either
        // because they claimed nobody or because the club disowned the claim —
        // and that answer must not be quietly overwritten by their membership.
        $club = $representing === false
            ? $user?->memberClubs->first()
            : $representing;

        return [
            // The PUBLIC key, never the numeric id: this is what the row links
            // to, and /people/{uuid} is the reference for that rule.
            'uuid' => $user?->uuid,
            'name' => $row['name'] ?? __('personal.event_people_unknown'),
            'gender' => $row['gender'] ?? null,
            /* The rank, drawn as the card's edge.
               Two shapes arrive here and both are legitimate: the event view
               resolves the ANNOUNCED belt through App\Sports\Combat\BeltRank
               and hands over `{colour, grade, label, source}`, while the public
               participants payload carries the plain colour. Normalised to a
               colour plus a degree so the card never has to know. */
            'belt' => is_array($row['belt'] ?? null)
                ? ($row['belt']['colour'] ?? null)
                : ($row['belt'] ?? null),
            'belt_grade' => is_array($row['belt'] ?? null)
                ? ($row['belt']['grade'] ?? null)
                : null,
            'category' => $row['category'] ?? null,
            'weight_class' => $row['weight_class'] ?? null,
            /* EVERY division this person is entered in, and every entry behind
               them — passed through untouched from the row the caller built.
               A roster of entries has one of each per row and needs neither;
               a roster folded into one row per PERSON (the people page) carries
               both, because one card then stands for two entries. */
            'divisions' => $row['divisions'] ?? null,
            'entries' => $row['entries'] ?? null,
            'activity' => $row['activity'] ?? null,
            // The flag is the CLUB's country, not the person's passport. Someone
            // competing for a Bahraini club is on the sheet as Bahrain whatever
            // their nationality — their own is a fact about them, on their
            // profile, and it is not what a competition prints.
            'country' => $club?->country ?: ($row['country'] ?? null),
            'photo' => $this->photo($user, $row['registration_photo'] ?? null),
            // The entry, so a manager can put a face on it. Null for a roster
            // built from anything other than real entries.
            'registration' => $row['registration'] ?? null,
            'has_entry_photo' => ! empty($row['registration_photo']),
            'club' => $club ? [
                'name' => $club->club_name,
                'logo' => $club->logo ? file_url($club->logo) : null,
                'country' => $club->country,
                'href' => $this->clubHref($club),
            ] : null,
        ];
    }

    /**
     * The clubs with someone in this event, biggest squad first.
     *
     * Built from the participants rather than queried separately, so the two
     * tabs can never disagree about who is here — one athlete's club appearing
     * in a list that no athlete row shows would read as a mistake in the draw.
     *
     * @param  array<int, array<string, mixed>>  $participants
     * @return array<int, array<string, mixed>>
     */
    private function clubs(array $participants): array
    {
        return collect($participants)
            ->pluck('club')
            ->filter()
            ->groupBy('name')
            ->map(fn (Collection $group) => $group->first() + ['athletes' => $group->count()])
            ->sortByDesc('athletes')
            ->values()->all();
    }

    /**
     * The competitor's picture, or null when they have not published one.
     *
     * `profile_picture_is_public` is the member's own choice about whether their
     * face may be shown outside their club. An event roster is read by everyone
     * else entered in the event, so a false here means the silhouette — the same
     * answer the hall screen gives.
     */
    private function photo(?User $user, ?string $entryPhoto = null): ?string
    {
        // A photo the ORGANISER uploaded against this entry comes first: it was
        // taken for this competition, and it is the only picture most
        // paper-entered competitors have. It carries no privacy gate because
        // uploading it here WAS the decision to show it on this event's surfaces.
        // Only when the bytes are actually there. A path with no file behind
        // it used to win here anyway and stop the fall-through, blanking a
        // competitor whose profile picture was perfectly good.
        if (EntryPhoto::showable($entryPhoto)) {
            return file_url($entryPhoto);
        }

        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return file_url($user->profile_picture);
    }

    /**
     * A club's public page. Country-prefixed, because `clubs.show` is bound
     * under `{country}` and omitting it is a runtime 404, not a compile error.
     */
    private function clubHref(mixed $club): ?string
    {
        if (! $club->slug || ! $club->country) {
            return null;
        }

        return route('clubs.show', [
            'country' => strtolower($club->country),
            'slug' => $club->slug,
        ]);
    }

    /**
     * The club each entry says the athlete competes FOR, keyed by user id.
     *
     * Present-but-null is meaningful: the entry answered "unattached". Absent
     * means the entry never recorded a club at all, and only then may the
     * roster fall back to the athlete's own membership.
     *
     * @return \Illuminate\Support\Collection<int, Tenant|null>
     */
    private function representing(ClubEvent $event): Collection
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with('representingTenant:id,club_name,slug,logo,country')
            ->get(['id', 'user_id', 'representing_tenant_id', 'club_disowned_at'])
            ->filter(fn (ClubEventRegistration $r) => $r->representing_tenant_id !== null || $r->isDisowned())
            ->mapWithKeys(fn (ClubEventRegistration $r) => [
                (int) $r->user_id => $r->isDisowned() ? null : $r->representingTenant,
            ]);
    }

    /**
     * One query for the whole roster rather than two per row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function users(array $rows): Collection
    {
        $ids = collect($rows)->pluck('id')->filter()->unique()->all();

        if (! $ids) {
            return collect();
        }

        return User::whereIn('id', $ids)
            ->with(['memberClubs:id,club_name,slug,logo,country'])
            ->get(['id', 'uuid', 'profile_picture', 'profile_picture_is_public'])
            ->keyBy('id');
    }
}
