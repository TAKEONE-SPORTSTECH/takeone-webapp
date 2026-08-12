<?php

namespace App\Events\Support;

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
    public function build(array $rows): array
    {
        $users = $this->users($rows);

        $participants = collect($rows)
            ->map(fn (array $row) => $this->participant($row, $users->get($row['id'] ?? null)))
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
    private function participant(array $row, ?User $user): array
    {
        $club = $user?->memberClubs->first();

        return [
            // The PUBLIC key, never the numeric id: this is what the row links
            // to, and /people/{uuid} is the reference for that rule.
            'uuid' => $user?->uuid,
            'name' => $row['name'] ?? __('personal.event_people_unknown'),
            'gender' => $row['gender'] ?? null,
            'category' => $row['category'] ?? null,
            'weight_class' => $row['weight_class'] ?? null,
            'country' => $row['country'] ?? $user?->nationality ?? null,
            'photo' => $this->photo($user),
            'club' => $club ? [
                'name' => $club->club_name,
                'logo' => $club->logo ? asset('storage/'.$club->logo) : null,
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
    private function photo(?User $user): ?string
    {
        if (! $user?->profile_picture || ! $user->profile_picture_is_public) {
            return null;
        }

        return asset('storage/'.$user->profile_picture);
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
            ->get(['id', 'uuid', 'nationality', 'profile_picture', 'profile_picture_is_public'])
            ->keyBy('id');
    }
}
