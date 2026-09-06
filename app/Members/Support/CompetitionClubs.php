<?php

namespace App\Members\Support;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEventRegistration;
use App\Models\EventClub;
use Illuminate\Support\Collection;

/**
 * The clubs a person has COMPETED FOR, as opposed to belonged to.
 *
 * A profile's club list used to answer one question — which clubs is this
 * person a member of — and read them from exactly two places: a typed
 * `club_affiliations` row, and an active `memberships` row. Both belong to
 * somebody who joined a club on TAKEONE.
 *
 * An entrant at a competition has neither, and often never will. They are
 * signed up by an organiser on the morning, from a club that may not be on the
 * platform at all — it is written down as an `event_clubs` row so the draw can
 * name the team they are actually fighting for. The person's profile then
 * showed "0 clubs" the day after they competed under a club crest on the wall
 * board, which reads as an error and is one.
 *
 * So this is the third source, kept apart from the other two on purpose:
 *
 *   · It is a fact about a COMPETITION, not a membership. The row says
 *     "represented at <event>", never "member since".
 *   · It is dated by the event, so it sorts and reads correctly beside real
 *     affiliations.
 *   · It never overrides a real one. A club the person is actually a member of
 *     is shown as a membership, and this adds nothing for it.
 *
 * Two shapes reach it, because there are two ways an entry names a club:
 * `representing_tenant_id` (a club the platform knows) and an `event_clubs`
 * row through `event_club_entrants` (one it does not).
 */
class CompetitionClubs
{
    /**
     * Card-shaped rows for the profile's club list.
     *
     * The shape matches what `people.partials.club-card` / `club-row` already
     * draw for an affiliation — `tenant`, `club_name`, `logo`, `start_date`,
     * `end_date` — plus two optional fields those partials use only when they
     * are present, so nothing about an ordinary affiliation changes:
     *
     *   note   the line under the name, in place of "member since"
     *   badge  the chip on the right, in place of "active"
     *
     * @param  array<int, int>  $excludeTenantIds  tenants already listed as real clubs
     * @param  array<int, string>  $excludeNames    lower-cased names already listed
     * @return Collection<int, object>
     */
    public static function forUser(User $person, array $excludeTenantIds = [], array $excludeNames = []): Collection
    {
        $registrations = ClubEventRegistration::query()
            ->where('user_id', $person->id)
            // A withdrawn or removed entry is not a competition this person
            // took part in, and must not put a club on their profile.
            ->where(fn ($q) => $q->whereNull('entry_state')->orWhereNotIn('entry_state', ['withdrawn', 'removed']))
            ->with('event:id,uuid,title,date')
            ->get(['id', 'event_id', 'representing_tenant_id', 'club_disowned_at']);

        if ($registrations->isEmpty()) {
            return collect();
        }

        $rows = collect();

        // ── Clubs the platform knows, named by the entry itself ────────────
        //
        // `club_disowned_at` is the club saying "this athlete did not enter for
        // us". An entry it disowned must not put its name on the athlete's
        // profile — that is the whole point of the column.
        $claimed = $registrations->whereNull('club_disowned_at')->whereNotNull('representing_tenant_id');

        if ($claimed->isNotEmpty()) {
            $tenants = Tenant::whereIn('id', $claimed->pluck('representing_tenant_id')->unique())
                ->get(['id', 'club_name', 'logo', 'slug', 'country', 'translations'])
                ->keyBy('id');

            foreach ($claimed as $registration) {
                $tenant = $tenants->get($registration->representing_tenant_id);

                if (! $tenant) {
                    continue;
                }

                $rows->push(static::row(
                    tenant: $tenant,
                    name: $tenant->club_name,
                    logo: $tenant->logo,
                    registration: $registration,
                ));
            }
        }

        // ── Clubs written down for one event, which may not exist elsewhere ─
        $eventClubs = EventClub::query()
            ->whereHas('entrants', fn ($q) => $q->whereIn('club_event_registrations.id', $registrations->pluck('id')))
            ->with(['entrants:id,event_id', 'tenant:id,club_name,logo,slug,country,translations'])
            ->get();

        foreach ($eventClubs as $club) {
            $registration = $registrations->firstWhere(
                'id',
                $club->entrants->pluck('id')->intersect($registrations->pluck('id'))->first()
            );

            $rows->push(static::row(
                // A temporary club has no page to link to, and the card falls
                // back to a plain row rather than a dead link.
                tenant: $club->tenant,
                name: $club->tenant?->club_name ?: $club->name,
                logo: $club->logo ?: $club->tenant?->logo,
                registration: $registration,
            ));
        }

        // Newest competition first, then one row per club: an athlete who
        // fought three times for the same club is with one club, not three.
        return $rows
            ->sortByDesc(fn ($row) => $row->start_date)
            ->reject(function ($row) use ($excludeTenantIds, $excludeNames) {
                if ($row->tenant_id && in_array((int) $row->tenant_id, $excludeTenantIds, true)) {
                    return true;
                }

                return in_array(mb_strtolower(trim((string) $row->club_name)), $excludeNames, true);
            })
            ->unique(fn ($row) => $row->tenant_id ?: mb_strtolower(trim((string) $row->club_name)))
            ->values();
    }

    /** One card row. */
    private static function row(?Tenant $tenant, ?string $name, ?string $logo, ?ClubEventRegistration $registration): object
    {
        $event = $registration?->event;

        return (object) [
            'tenant_id' => $tenant?->id,
            'tenant' => $tenant,
            'club_name' => $name,
            'logo' => $logo,
            'start_date' => $event?->date,
            'end_date' => null,
            // Said plainly, because it is a different fact from membership: the
            // reader should not have to guess whether this person trains there.
            'note' => $event?->title
                ? __('personal.competed_for_at', ['event' => $event->title])
                : __('personal.competed_for'),
            'badge' => __('personal.competed_for'),
        ];
    }
}
