<?php

namespace App\Support;

use App\Clubs\Models\ClubAffiliation;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\Membership;
use App\Clubs\Models\Tenant;
use App\Models\TournamentEvent;
use Illuminate\Support\Facades\Log;

/**
 * ══════════════════════════════════════════════════════════════════════════
 * Keeps a member's profile history in step with what the club actually did.
 *
 * Joining a club writes a `memberships` row; entering an event writes a
 * `club_event_registrations` row. Neither reaches the profile's Affiliations or
 * Tournaments tabs, which read the member's SELF-REPORTED log. This class writes
 * the matching profile row so the record exists in one place — and because the
 * club is the source, those rows are club-confirmed rather than self-reported.
 *
 * IDEMPOTENT by design: every write checks for an existing row first, matched
 * the same way App\Support\ProfileHistory de-duplicates its derived view. Running
 * the backfill twice, or re-saving a membership, can never produce a second copy.
 *
 * NEVER destructive: it only ever creates. A member's own hand-written entry is
 * left exactly as they wrote it, and nothing is deleted when a membership ends —
 * that is history, and history is the point of the tab.
 * ══════════════════════════════════════════════════════════════════════════
 */
class ProfileHistorySync
{
    /**
     * Ensure this membership is represented in the member's affiliation history.
     * Returns the row when one was created, null when nothing was needed.
     */
    public function syncMembership(Membership $membership): ?ClubAffiliation
    {
        $club = Tenant::find($membership->tenant_id);

        if (! $club || ! $membership->user_id) {
            return null;
        }

        $existing = ClubAffiliation::query()
            ->where('member_id', $membership->user_id)
            ->where(function ($q) use ($club) {
                $q->where('tenant_id', $club->id)
                    ->orWhereRaw('LOWER(TRIM(club_name)) = ?', [mb_strtolower(trim((string) $club->club_name))]);
            })
            ->first();

        if ($existing) {
            // Already there. Attach the tenant if the member had typed the club
            // by hand — that upgrades a loose text entry into a linked one
            // without touching anything they wrote.
            if ($existing->tenant_id === null) {
                $existing->tenant_id = $club->id;
                $existing->save();
            }

            return null;
        }

        $affiliation = ClubAffiliation::create([
            'member_id'  => $membership->user_id,
            'tenant_id'  => $club->id,
            'club_name'  => $club->club_name,
            'logo'       => $club->logo,
            'start_date' => $membership->created_at?->toDateString(),
            'location'   => $club->address ?: null,
        ]);

        // The club itself is the source, so this is not a member's claim about
        // themselves — mark it confirmed rather than self_reported. Written
        // directly because AchievementVerificationService::clubConfirm() models a
        // human admin reviewing a claim, which is not what happened here.
        $affiliation->forceFill([
            'verification_status'   => 'verified',
            'verification_method'   => 'club_record',
            'verified_by_tenant_id' => $club->id,
            'verified_at'           => now(),
        ])->save();

        return $affiliation;
    }

    /**
     * Ensure this event entry is represented in the member's tournament history.
     */
    public function syncRegistration(ClubEventRegistration $registration): ?TournamentEvent
    {
        $event = $registration->event ?: ClubEvent::find($registration->event_id);

        if (! $event || ! $registration->user_id) {
            return null;
        }

        // Spectators did not compete, so they get no tournament record.
        if ($registration->role && $registration->role !== 'participant') {
            return null;
        }

        $day = $event->date ? \Carbon\Carbon::parse($event->date)->toDateString() : null;

        $exists = TournamentEvent::query()
            ->where('user_id', $registration->user_id)
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim((string) $event->title))])
            ->when($day, fn ($q) => $q->whereDate('date', $day))
            ->exists();

        if ($exists) {
            return null;
        }

        // Link it to the club the member competed FOR, when that club is already
        // in their affiliation history, so the two tabs agree with each other.
        $tenantId = $registration->representing_tenant_id ?: $event->tenant_id;

        $affiliationId = $tenantId
            ? ClubAffiliation::where('member_id', $registration->user_id)
                ->where('tenant_id', $tenantId)
                ->value('id')
            : null;

        $tournament = TournamentEvent::create([
            'user_id'             => $registration->user_id,
            'club_affiliation_id' => $affiliationId,
            'title'               => $event->title,
            'type'                => $event->event_type ?: 'tournament',
            'sport'               => $event->sport,
            'date'                => $day,
            'location'            => $event->location,
        ]);

        $tournament->forceFill([
            'verification_status'   => 'verified',
            'verification_method'   => 'club_record',
            'verified_by_tenant_id' => $event->tenant_id,
            'verified_at'           => now(),
        ])->save();

        return $tournament;
    }

    /** Best-effort wrapper — a profile-history write must never break a join or an entry. */
    public function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Profile history sync failed', ['error' => $e->getMessage()]);
        }
    }
}
