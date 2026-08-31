<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Clubs\Models\Tenant;
use App\Models\User;
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
            'category' => $row['category'] ?? null,
            'weight_class' => $row['weight_class'] ?? null,
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
        if ($entryPhoto) {
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
