<?php

namespace App\EventLab\Services;

use App\Clubs\Models\Tenant;
use App\EventLab\Models\SandboxEventClub;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What happens to a temporary club when the competition is over.
 *
 * A club written down at the desk exists for one event. Most of them should
 * stop existing with it — a name typed into a form while somebody was on the
 * phone is not a club. But a club that actually turned up, with athletes who
 * fought, IS a club; it simply had not registered yet. Making that one fill in
 * a form afterwards to become what it already demonstrably is would be asking
 * the wrong party to do the work.
 *
 * So: after the event, every temporary club that brought at least one athlete
 * who did not withdraw becomes a real `tenants` row. Every temporary club that
 * brought nobody stays where it is and goes when the event does.
 *
 * Three rules make this safe to run against real data:
 *
 *  1. **Only after the event.** Before that, "who competed" is not yet a fact.
 *  2. **Only additive.** It creates clubs. It never edits or deletes one, and a
 *     club it already made is never made twice — `promoted_tenant_id` records
 *     the answer and is the idempotency key.
 *  3. **The new club is asleep.** `status = pending`, no public profile, test
 *     mode on. It exists so the history has something to point at; a person
 *     still has to take it over before it behaves like a club anybody can join.
 *     An auto-created club that appeared in /explore the morning after a
 *     tournament would be the platform publishing somebody else's name.
 */
class ClubPromotion
{
    /**
     * Promote every eligible temporary club on one event.
     *
     * @return array{promoted: array<int, string>, skipped: array<int, string>, reason: ?string}
     */
    public function forEvent(ClubEvent $event): array
    {
        if (! $this->isOver($event)) {
            return ['promoted' => [], 'skipped' => [], 'reason' => 'not_over'];
        }

        $promoted = [];
        $skipped = [];

        $clubs = SandboxEventClub::with('entrants')
            ->where('event_id', $event->id)
            ->whereNull('tenant_id')
            ->whereNull('promoted_tenant_id')
            ->get();

        foreach ($clubs as $club) {
            if (! $club->competed()) {
                $skipped[] = $club->name;

                continue;
            }

            $this->promote($club, $event);
            $promoted[] = $club->name;
        }

        return ['promoted' => $promoted, 'skipped' => $skipped, 'reason' => null];
    }

    /**
     * Is the competition finished?
     *
     * Its own status if it says so, otherwise the day after its last date — an
     * organiser who never pressed "completed" has still finished the event, and
     * waiting for a button nobody pressed would mean these clubs never
     * materialise.
     */
    public function isOver(ClubEvent $event): bool
    {
        if (in_array($event->status, ['completed', 'finished', 'archived'], true)) {
            return true;
        }

        $last = $event->end_date ?: $event->date;

        return $last !== null && $last->copy()->endOfDay()->isPast();
    }

    /**
     * One club becomes real.
     *
     * The athletes come with it: an entry that named this club now names the
     * club that exists, so the member's own history says where they competed
     * rather than pointing at a row that was about to be deleted.
     */
    public function promote(SandboxEventClub $club, ClubEvent $event): Tenant
    {
        return DB::transaction(function () use ($club, $event) {
            $tenant = Tenant::create([
                // NOT NULL, and it has to be somebody. The organiser who wrote
                // the club down is the honest answer and the person who can hand
                // it over — not a claim that they own the club.
                'owner_user_id' => $club->created_by ?: $event->created_by,
                'club_name' => $club->name,
                'slug' => $this->slugFor($club->name),
                // ISO-3166 alpha-2, never a country's full name: the public club
                // URL is /{country}/clubs/{slug} and a full name 404s.
                'country' => $club->country ?: 'BH',
                'logo' => $club->logo,
                // Asleep until a person takes it over.
                'status' => 'pending',
                'public_profile_enabled' => false,
                'is_test_mode' => true,
            ]);

            $club->forceFill([
                'promoted_tenant_id' => $tenant->id,
                'promoted_at' => now(),
            ])->save();

            // The entries follow the club into its real identity.
            $ids = $club->entrants()->pluck('club_event_registrations.id');

            if ($ids->isNotEmpty()) {
                ClubEventRegistration::whereIn('id', $ids)
                    ->whereNull('representing_tenant_id')
                    ->update(['representing_tenant_id' => $tenant->id]);
            }

            return $tenant;
        });
    }

    /** A slug nothing else holds. */
    private function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'club';
        $slug = $base;
        $n = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
