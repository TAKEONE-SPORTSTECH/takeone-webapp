<?php

namespace App\Events\Support\Clubs;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Events\Models\EventOfficial;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A club written down for an event turns out to be a club the platform
 * already holds: say so, once, everywhere.
 *
 * A typed-in event club (`tenant_id` NULL) that is really a platform club is
 * why a competitor shows with no crest and no flag on the bracket and the hall
 * screen: the screens read the platform club through
 * `representing_tenant_id`, and the typed copy has none. Linking makes the copy
 * name the real club, and gives every entry and official it holds the
 * representing club they were missing — the same writes the organiser's own
 * "Link" makes (EventClubsController::assign) and the ones done by hand for
 * events 104 and 105.
 *
 * Only ever FILLS: an entry or official already representing a club keeps it,
 * and payment arrangements are left exactly as they were. When the event
 * already lists that platform club (it was invited), the typed copy's athletes
 * move onto that row instead, and the copy is left empty — never deleted here.
 *
 * Idempotent. Every link is written to the activity log.
 */
class EventClubLink
{
    /**
     * @return array{ok: bool, entries: int, officials: int, into: ?int, message?: string}
     */
    public static function link(EventClub $club, Tenant $tenant, ?User $by = null, string $why = 'manual'): array
    {
        if ((int) $club->tenant_id === (int) $tenant->id) {
            return ['ok' => true, 'entries' => 0, 'officials' => 0, 'into' => $club->id];
        }
        if ($club->tenant_id !== null) {
            return ['ok' => false, 'entries' => 0, 'officials' => 0, 'into' => null, 'message' => 'linked_to_another_club'];
        }

        return DB::transaction(function () use ($club, $tenant, $by, $why) {
            // The event may already list this platform club (invited): its
            // athletes join that row rather than making a second one.
            $twin = EventClub::where('event_id', $club->event_id)->where('tenant_id', $tenant->id)
                ->where('id', '!=', $club->id)->lockForUpdate()->first();
            $target = $twin ?? $club;

            $ids = DB::table('event_club_entrants')->where('event_club_id', $club->id)->pluck('registration_id')->all();

            if ($twin) {
                if ($ids) {
                    DB::table('event_club_entrants')->whereIn('registration_id', $ids)
                        ->update(['event_club_id' => $twin->id, 'updated_at' => now()]);
                }
            } else {
                $club->forceFill(['tenant_id' => $tenant->id])->save();
            }

            $entries = $ids
                ? ClubEventRegistration::whereIn('id', $ids)->where('event_id', $club->event_id)
                    ->whereNull('representing_tenant_id')
                    ->update(['representing_tenant_id' => $tenant->id])
                : 0;

            $officialQuery = EventOfficial::where('event_id', $club->event_id)->where('event_club_id', $club->id);
            $officials = (clone $officialQuery)->whereNull('representing_tenant_id')
                ->update(['representing_tenant_id' => $tenant->id]);
            if ($twin) {
                $officialQuery->update(['event_club_id' => $twin->id]);
            }

            activity()->causedBy($by)->performedOn($tenant)
                ->withProperties([
                    'event_id' => $club->event_id, 'event_club' => $club->uuid, 'into' => $target->uuid,
                    'entries' => $entries, 'officials' => $officials, 'why' => $why,
                ])
                ->log('events.club_linked');

            return ['ok' => true, 'entries' => $entries, 'officials' => $officials, 'into' => $target->id];
        });
    }
}
