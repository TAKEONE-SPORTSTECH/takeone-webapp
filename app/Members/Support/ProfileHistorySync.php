<?php

namespace App\Members\Support;

use App\Clubs\Models\ClubAffiliation;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Members\Models\Membership;
use App\Members\Models\PerformanceResult;
use App\Clubs\Models\Tenant;
use App\Members\Models\TournamentEvent;
use Illuminate\Support\Facades\DB;
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
 * the same way App\Members\Support\ProfileHistory de-duplicates its derived view. Running
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

        // A HIDDEN event leaves no copy on the member's public record (its name
        // would be listed where the event itself is not). Their own profile
        // still shows the entry, read live from the event.
        if ($event->isHidden()) {
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
        ] + (self::linksEvents() ? ['club_event_id' => $event->id] : []))->save();

        return $tournament;
    }

    /**
     * Put a division's medals on its medallists' profiles (owner, 2026-10-17).
     *
     * The podium is written to `event_categories.podium` by every sport's
     * Advancement and by the championship Runner; nothing carried it any
     * further, so a gold won here left the profile's medal count at 0.
     *
     * Replaces exactly the medals THIS division wrote before
     * (`performance_results.source_category_id`) with the podium as it stands
     * now — so a corrected or cleared result corrects the profile too, and a
     * medal a member typed in by hand is never touched. Place 4 is a placing,
     * not a medal (see <x-medal-icon>), and an entry with no account behind it
     * has no profile to write to.
     *
     * @return int  medals now on profiles for this division
     */
    public function syncPodium(EventCategory $category): int
    {
        if (! self::linksEvents()) {
            return 0;
        }

        $event = $category->event ?: ClubEvent::find($category->event_id);
        if (! $event || $event->isHidden()) {
            return 0;
        }

        $medals = [];
        foreach ((array) ($category->podium ?: []) as $row) {
            $place = (int) ($row['place'] ?? 0);
            $entryId = (int) ($row['competitor_id'] ?? 0);
            if ($entryId && isset(self::MEDALS[$place])) {
                $medals[] = [$entryId, self::MEDALS[$place]];
            }
        }

        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_column($medals, 0))
            ->get()->keyBy('id');

        return DB::transaction(function () use ($category, $event, $medals, $entries) {
            PerformanceResult::where('source_category_id', $category->id)->delete();

            $written = 0;
            foreach ($medals as [$entryId, $medal]) {
                $entry = $entries->get($entryId);
                $tournament = $entry ? $this->tournamentFor($entry, $event) : null;
                if (! $tournament) {
                    continue;
                }

                $result = new PerformanceResult([
                    'tournament_event_id' => $tournament->id,
                    'description' => (string) $category->name,
                    'medal_type' => $medal,
                ]);
                $result->forceFill(['source_category_id' => $category->id])->save();
                $written++;
            }

            return $written;
        });
    }

    /** A division that is gone takes the medals it wrote with it. */
    public function forgetPodium(int $categoryId): void
    {
        if (self::linksEvents()) {
            PerformanceResult::where('source_category_id', $categoryId)->delete();
        }
    }

    /**
     * The athlete's club-recorded tournament row for this event, made if missing.
     *
     * Found by the event's id; failing that, the row made before that link
     * existed — same member, same day, club-recorded, same title once spacing
     * is ignored (an event renamed after entries opened differs only there) —
     * which is then stamped with the id so a later rename cannot lose it.
     */
    public function tournamentFor(ClubEventRegistration $entry, ClubEvent $event): ?TournamentEvent
    {
        if (! $entry->user_id || ($entry->role && $entry->role !== 'participant')) {
            return null;
        }

        $linked = TournamentEvent::where('user_id', $entry->user_id)->where('club_event_id', $event->id)->first();
        if ($linked) {
            return $linked;
        }

        $day = $event->date ? \Carbon\Carbon::parse($event->date)->toDateString() : null;
        $squash = fn (?string $s) => mb_strtolower(preg_replace('/\s+/u', '', (string) $s));

        $found = TournamentEvent::where('user_id', $entry->user_id)
            ->whereNull('club_event_id')
            ->where('verification_method', 'club_record')
            ->when($day, fn ($q) => $q->whereDate('date', $day))
            ->get()
            ->first(fn (TournamentEvent $t) => $squash($t->title) === $squash($event->title));

        $found ??= $this->syncRegistration($entry);

        if ($found && ! $found->club_event_id) {
            $found->forceFill(['club_event_id' => $event->id])->save();
        }

        return $found;
    }

    /** Place on a podium → the profile's medal vocabulary (performance_results.medal_type). */
    private const MEDALS = [1 => '1st', 2 => '2nd', 3 => '3rd'];

    /** The feature is on, and its columns are there (EVENT_PROFILE_MEDALS). */
    private static function linksEvents(): bool
    {
        return (bool) config('events.profile_medals')
            && \Illuminate\Support\Facades\Schema::hasColumn('performance_results', 'source_category_id');
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
