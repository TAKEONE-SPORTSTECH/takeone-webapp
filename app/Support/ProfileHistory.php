<?php

namespace App\Support;

use App\Clubs\Models\ClubAffiliation;
use App\Models\ClubEventRegistration;
use App\Clubs\Models\Tenant;
use App\Members\Models\TournamentEvent;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ══════════════════════════════════════════════════════════════════════════
 * A member's history, derived from what the platform already knows.
 *
 * The profile's Affiliations and Tournaments tabs read `club_affiliations` and
 * `tournament_events` — both SELF-REPORTED logs a member fills in by hand. The
 * authoritative facts live elsewhere: `memberships` records who actually joined
 * a club, `club_event_registrations` records who actually entered an event. A
 * member could therefore be enrolled in a club and have fought a bout, and still
 * see two empty tabs.
 *
 * This class closes that gap at READ time. It reports only what is NOT already
 * covered by a self-reported row, so nothing appears twice — and once the sync
 * has written real rows for a member, their derived list naturally empties.
 *
 * Read-only by design: it writes nothing and owns no state, so it cannot put the
 * profile out of step with the records it reads from.
 * ══════════════════════════════════════════════════════════════════════════
 */
class ProfileHistory
{
    /**
     * Clubs this member actually belongs to that their affiliation list does not
     * mention yet.
     *
     * @return Collection<int, object>
     */
    public function derivedAffiliations(User $member): Collection
    {
        // Clubs the member has already written up by hand — matched on tenant so
        // a hand-written entry always wins over the derived one.
        // NOTE the column: club_affiliations keys on `member_id`, not `user_id`
        // (tournament_events uses `user_id`). Getting this wrong does not error —
        // it silently matches nothing, which would let every derived row appear
        // even after the member wrote it up by hand.
        $written = ClubAffiliation::query()
            ->where('member_id', $member->id)
            ->get(['tenant_id', 'club_name']);

        $claimedIds = $written->whereNotNull('tenant_id')
            ->pluck('tenant_id')->map(fn ($id) => (int) $id)->all();

        // Older / hand-typed affiliations carry no tenant_id at all, so they can
        // only be matched on the club's NAME. Without this a member who typed
        // their club in by hand would see it listed twice.
        $claimedNames = $written->pluck('club_name')
            ->filter()
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();

        $rows = DB::table('memberships')
            ->where('user_id', $member->id)
            ->when($claimedIds !== [], fn ($q) => $q->whereNotIn('tenant_id', $claimedIds))
            ->orderBy('created_at')
            ->get(['tenant_id', 'status', 'created_at']);

        if ($rows->isEmpty()) {
            return collect();
        }

        $clubs = Tenant::query()
            ->whereIn('id', $rows->pluck('tenant_id'))
            ->get(['id', 'slug', 'club_name', 'logo', 'country'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($clubs, $claimedNames) {
            $club = $clubs->get($row->tenant_id);

            if (! $club) {
                return null;      // membership pointing at a deleted club
            }

            if (in_array(mb_strtolower(trim((string) $club->club_name)), $claimedNames, true)) {
                return null;      // already written up by hand, under this name
            }

            return (object) [
                'tenant_id'  => (int) $row->tenant_id,
                'club_name'  => $club->club_name,
                'club_slug'  => $club->slug,
                'country'    => $club->country,
                'logo'       => $club->logo,
                'status'     => $row->status,
                'started_at' => $row->created_at ? \Carbon\Carbon::parse($row->created_at) : null,
                'club_url'   => $this->clubUrl($club),
            ];
        })->filter()->values();
    }

    /**
     * Events this member actually entered that their tournament log does not
     * mention yet.
     *
     * @return Collection<int, object>
     */
    public function derivedTournaments(User $member): Collection
    {
        // Self-reported tournaments are free text with no event id, so they can
        // only be matched on title + date. An exact match is treated as the same
        // competition and the derived row is suppressed.
        $claimed = TournamentEvent::query()
            ->where('user_id', $member->id)
            ->get(['title', 'date'])
            ->map(fn ($t) => $this->fingerprint($t->title, $t->date))
            ->all();

        $registrations = ClubEventRegistration::query()
            ->where('user_id', $member->id)
            ->with('event:id,uuid,title,date,end_date,location,sport,event_type,tenant_id')
            ->get();

        return $registrations->map(function (ClubEventRegistration $reg) use ($claimed) {
            $event = $reg->event;

            if (! $event) {
                return null;
            }

            if (in_array($this->fingerprint($event->title, $event->date), $claimed, true)) {
                return null;
            }

            return (object) [
                'event_uuid'  => $event->uuid,
                'title'       => $event->title,
                'date'        => $event->date ? \Carbon\Carbon::parse($event->date) : null,
                'location'    => $event->location,
                'sport'       => $event->sport,
                'event_type'  => $event->event_type,
                'role'        => $reg->role,
                'status'      => $reg->status,
                // The club the member competed FOR, which is not always the club
                // running the event (see the entry-channel rules).
                'club_name'   => $this->representingClubName($reg),
            ];
        })->filter()
            ->sortByDesc(fn ($t) => $t->date?->timestamp ?? 0)
            ->values();
    }

    // ── internals ────────────────────────────────────────────────────────

    /** Title+date signature used to spot a self-reported duplicate. */
    private function fingerprint(?string $title, $date): string
    {
        $day = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : '';

        return mb_strtolower(trim((string) $title)).'|'.$day;
    }

    private function representingClubName(ClubEventRegistration $reg): ?string
    {
        $tenantId = $reg->representing_tenant_id ?: optional($reg->event)->tenant_id;

        return $tenantId ? optional(Tenant::find($tenantId))->club_name : null;
    }

    private function clubUrl(Tenant $club): ?string
    {
        $country = strtolower((string) $club->country);

        return ($country !== '' && $club->slug)
            ? url('/'.$country.'/clubs/'.$club->slug)
            : null;
    }
}
