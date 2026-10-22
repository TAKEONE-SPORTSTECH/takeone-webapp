<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationClubApproval;
use App\Federations\Services\FederationInvitation;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The duplicate guard's federation doors (duplicates.guard.federation_links
 * and .staff_enrol): approving a club links the copies its application left on
 * the federation's platform events, and a known athlete is reused at the same
 * club and never silently taken from another.
 */
class DuplicateGuardFederationTest extends TestCase
{
    public function test_approving_a_federation_club_links_its_copies_on_the_federation_events(): void
    {
        config(['duplicates.guard.federation_links' => true]);
        $this->seedRoles();
        $head = $this->createUser();
        $host = $this->createClub($head);
        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'karate', 'status' => 'approved']);
        $event = ClubEvent::create([
            'tenant_id' => $host->id, 'created_by' => $head->id,
            'title' => 'League', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'inter_club', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        $fe = new FederationEvent;
        $fe->forceFill(['uuid' => (string) Str::uuid(), 'federation_id' => $fed->id, 'created_by' => $head->id, 'name' => 'League',
            'sport' => 'Karate', 'status' => 'Planning', 'club_event_id' => $event->id])->save();
        $app = new FederationClubApplication;
        $app->forceFill(['uuid' => (string) Str::uuid(), 'federation_id' => $fed->id, 'name' => 'Falcon Combat',
            'email' => 'falcon@example.test', 'status' => FederationClubApplication::STATUS_PENDING])->save();
        $typed = EventClub::create(['event_id' => $event->id, 'name' => 'Falcon Combat', 'state' => 'listed']);
        $entry = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => 'participant', 'status' => 'joined']);
        DB::table('event_club_entrants')->insert(['registration_id' => $entry->id, 'event_club_id' => $typed->id, 'created_at' => now(), 'updated_at' => now()]);

        $link = app(FederationClubApproval::class)->approve($app->fresh(), $head);

        $this->assertSame((int) $link->tenant_id, (int) $typed->fresh()->tenant_id);
        $this->assertSame((int) $link->tenant_id, (int) $entry->fresh()->representing_tenant_id);
    }

    public function test_federation_staff_enrolling_a_known_athlete_at_the_same_club_reuses_them(): void
    {
        $head = $this->createUser();
        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'karate', 'status' => 'approved']);
        $club = FederationClub::create(['federation_id' => $fed->id, 'tenant_id' => $this->createClub($head)->id, 'status' => 'approved']);
        $user = User::create(['full_name' => 'Salman Al Jasim', 'name' => 'Salman Al Jasim', 'is_unclaimed' => true, 'birthdate' => '2011-04-02']);
        $athlete = FederationAthlete::create(['user_id' => $user->id, 'takeone_id' => 'BHR-1']);
        FederationAthleteClub::create(['federation_athlete_id' => $athlete->id, 'federation_id' => $fed->id, 'federation_club_id' => $club->id, 'from_ym' => '2026-01']);
        $twinAt = fn (array $row, FederationClub $c) => (new \ReflectionMethod(FederationInvitation::class, 'twinAtClub'))
            ->invoke(app(FederationInvitation::class), $row, $c, collect([$fed->id]));

        $this->assertSame($athlete->id, $twinAt(['name' => 'salman  al jasim', 'dob' => '2011-04-02'], $club)?->id);

        $other = FederationClub::create(['federation_id' => $fed->id, 'tenant_id' => $this->createClub($head)->id, 'status' => 'approved']);
        $this->assertNull($twinAt(['name' => 'Salman Al Jasim', 'dob' => '2011-04-02'], $other), 'at another club it is a transfer, never a silent reuse');
    }
}
