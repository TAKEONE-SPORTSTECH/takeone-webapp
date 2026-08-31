<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventParticipantBan;
use App\Models\HealthRecord;
use App\Clubs\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Tests\TestCase;

/**
 * A coach enters their squad in one submission.
 *
 * The rule that matters: bulk entry is a CONVENIENCE, never a bypass. Every
 * athlete passes exactly the checks self-entry applies — scope, bans, window,
 * capacity and the type package's own gate — and partial success is the normal
 * outcome, so each athlete gets its own verdict.
 */
class BulkEntryTest extends TestCase
{
    private Tenant $club;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = $this->createUser(['full_name' => 'Coach Kim']);
        $this->club = $this->createClub($this->coach, ['country' => 'BH', 'currency' => 'BHD']);
        $this->coach->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        $event = ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->coach->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));

        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -68 kg', 'sort_order' => 2]);

        return $event;
    }

    /** An athlete already registered in the system, on this club's roster. */
    private function athlete(string $name, ?float $weight = 57, ?Tenant $club = null): User
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([($club ?? $this->club)->id => ['status' => 'active']]);

        if ($weight !== null) {
            HealthRecord::create(['user_id' => $user->id, 'weight' => $weight, 'recorded_at' => now()]);
        }

        return $user->fresh();
    }

    /* ---------------- The happy path ---------------- */

    public function test_a_coach_enters_a_whole_squad_in_one_submission(): void
    {
        $event = $this->event();
        $squad = collect(['Ali', 'Bader', 'Cyrus'])->map(fn ($n) => $this->athlete($n));

        $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => $squad->pluck('id')->all()])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'entered')
            ->assertJsonCount(0, 'rejected');

        foreach ($squad as $a) {
            $this->assertDatabaseHas('club_event_registrations', [
                'event_id' => $event->id,
                'user_id' => $a->id,
                'role' => 'participant',
                'entered_by' => $this->coach->id,
            ]);
        }
    }

    public function test_entries_are_routed_into_the_right_weight_division(): void
    {
        $event = $this->event();
        $light = $this->athlete('Light', 57);
        $middle = $this->athlete('Middle', 66);

        $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$light->id, $middle->id]])
            ->assertOk();

        $this->assertSame('Senior Men -58 kg',
            ClubEventRegistration::where('user_id', $light->id)->first()->category->name);
        $this->assertSame('Senior Men -68 kg',
            ClubEventRegistration::where('user_id', $middle->id)->first()->category->name);
    }

    public function test_the_athlete_is_told_they_were_entered(): void
    {
        $event = $this->event();
        $athlete = $this->athlete('Ali');

        $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$athlete->id]])
            ->assertOk();

        // They did not do this themselves — they must not find out on the day.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $athlete->id,
            'type' => 'event',
            'subject_id' => $event->id,
        ]);
    }

    public function test_re_submitting_the_same_squad_does_not_duplicate_entries(): void
    {
        $event = $this->event();
        $athlete = $this->athlete('Ali');

        foreach (range(1, 3) as $_) {
            $this->actingAs($this->coach)
                ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$athlete->id]])
                ->assertOk();
        }

        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)->count());
        $this->assertSame(1, UserNotification::where('user_id', $athlete->id)->where('type', 'event')->count(),
            'and they are not pestered about it three times');
    }

    /* ---------------- It is not a bypass ---------------- */

    public function test_an_athlete_with_no_weight_on_file_is_refused_with_a_reason(): void
    {
        $event = $this->event();
        $ok = $this->athlete('Ali');
        $noWeight = $this->athlete('Unweighed', null);

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$ok->id, $noWeight->id]])
            ->assertOk()
            ->assertJsonCount(1, 'entered')
            ->assertJsonCount(1, 'rejected');

        $this->assertSame('no_weight', $response->json('rejected.0.code'));
        $this->assertDatabaseMissing('club_event_registrations', ['user_id' => $noWeight->id]);
    }

    public function test_an_athlete_whose_division_is_not_being_run_is_refused(): void
    {
        $event = $this->event();
        $heavy = $this->athlete('Heavy', 95);   // no +87 division offered

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$heavy->id]])
            ->assertOk();

        $this->assertSame('no_division', $response->json('rejected.0.code'));
    }

    public function test_a_banned_athlete_cannot_be_entered_by_their_coach(): void
    {
        $event = $this->event();
        $banned = $this->athlete('Banned');
        EventParticipantBan::create([
            'scope' => 'event', 'event_id' => $event->id, 'tenant_id' => $this->club->id,
            'user_id' => $banned->id, 'created_by' => $this->coach->id,
        ]);

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$banned->id]])
            ->assertOk();

        $this->assertSame('banned', $response->json('rejected.0.code'));
        $this->assertDatabaseMissing('club_event_registrations', ['user_id' => $banned->id]);
    }

    public function test_entries_are_refused_once_the_window_has_closed(): void
    {
        $event = $this->event(['enrollment_ends_at' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$this->athlete('Ali')->id]])
            ->assertOk();

        $this->assertSame('closed', $response->json('rejected.0.code'));
    }

    public function test_capacity_is_honoured_across_the_batch(): void
    {
        $event = $this->event(['max_capacity' => 2]);
        $squad = collect(['A', 'B', 'C', 'D'])->map(fn ($n) => $this->athlete($n));

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => $squad->pluck('id')->all()])
            ->assertOk();

        $this->assertCount(2, $response->json('entered'), 'a squad fills the last places in order, it does not overflow them');
        $this->assertSame('full', $response->json('rejected.0.code'));
    }

    /* ---------------- Authorization ---------------- */

    public function test_a_coach_cannot_enter_another_clubs_athletes(): void
    {
        $event = $this->event();

        $otherClub = $this->createClub($this->createUser(), ['country' => 'BH']);
        $notMine = $this->athlete('Stranger', 57, $otherClub);

        $response = $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$notMine->id]])
            ->assertOk();

        $this->assertSame('not_yours', $response->json('rejected.0.code'));
        $this->assertDatabaseMissing('club_event_registrations', ['user_id' => $notMine->id]);
    }

    public function test_an_ordinary_member_cannot_bulk_enter_anyone(): void
    {
        $event = $this->event();
        $member = $this->athlete('Just A Member');
        $other = $this->athlete('Someone Else');

        $this->actingAs($member)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$other->id]])
            ->assertForbidden();
    }

    public function test_a_club_admin_who_is_not_the_owner_may_also_enter(): void
    {
        $event = $this->event();
        $admin = $this->createUser();
        $admin->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        $this->makeClubAdmin($admin, $this->club);

        $this->actingAs($admin->fresh())
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$this->athlete('Ali')->id]])
            ->assertOk()
            ->assertJsonCount(1, 'entered');
    }

    /* ---------------- The roster the coach picks from ---------------- */

    public function test_the_roster_shows_who_can_be_entered_and_why_not(): void
    {
        $event = $this->event();
        $ready = $this->athlete('Ready', 57);
        $noWeight = $this->athlete('Unweighed', null);

        $roster = $this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/entry-roster")
            ->assertOk()
            ->json('athletes');

        $rows = collect($roster)->keyBy('id');

        $this->assertTrue($rows[$ready->id]['can_enter']);
        $this->assertSame('Senior Men -58 kg', $rows[$ready->id]['division']);

        $this->assertFalse($rows[$noWeight->id]['can_enter']);
        $this->assertNotEmpty($rows[$noWeight->id]['reason'], 'the coach is told why before submitting, not after');
    }

    public function test_the_roster_marks_who_is_already_in(): void
    {
        $event = $this->event();
        $athlete = $this->athlete('Ali');

        $this->actingAs($this->coach)
            ->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$athlete->id]])->assertOk();

        $rows = collect($this->actingAs($this->coach)
            ->getJson("/me/events/{$event->uuid}/entry-roster")->json('athletes'))->keyBy('id');

        $this->assertTrue($rows[$athlete->id]['entered']);
        $this->assertFalse($rows[$athlete->id]['entered_by_them'], 'the coach entered them, not the athlete');
    }

    public function test_an_ordinary_member_cannot_read_the_roster(): void
    {
        $event = $this->event();

        $this->actingAs($this->athlete('Nobody'))
            ->getJson("/me/events/{$event->uuid}/entry-roster")
            ->assertForbidden();
    }

    /* ---------------- Self-entry still works ---------------- */

    public function test_an_athlete_can_still_enter_themselves(): void
    {
        $event = $this->event();
        $athlete = $this->athlete('Ali');

        $this->actingAs($athlete)
            ->postJson("/me/events/{$event->uuid}/register")
            ->assertOk()
            ->assertJson(['success' => true]);

        $registration = ClubEventRegistration::where('user_id', $athlete->id)->first();
        $this->assertNull($registration->entered_by, 'a self-entry has no author but the athlete');
    }

    /* ---------------- Creating as an owner ---------------- */

    public function test_a_club_owner_can_create_an_event_without_being_a_member_of_their_own_club(): void
    {
        // Owning a club and training in it are different things — an owner who
        // never enrolled as a member must still be able to run events for it.
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $this->assertSame(0, $owner->memberClubs()->count());

        $this->actingAs($owner->fresh())
            ->get('/me/events/create')
            ->assertOk()
            ->assertSee($club->club_name, false);

        $this->actingAs($owner->fresh())
            ->postJson('/me/events', [
                'tenant_id' => $club->id,
                'title' => 'Owner Championship',
                'event_type' => 'championship',
                'sport' => 'taekwondo',
                'scope' => 'internal',
                'date' => now()->addMonth()->toDateString(),
                'start_time' => '09:00',
                'participant_free' => true,
                'spectator_enabled' => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('club_events', [
            'title' => 'Owner Championship',
            'tenant_id' => $club->id,
            'sport' => 'taekwondo',
        ]);
    }

    public function test_someone_who_neither_belongs_to_nor_runs_the_club_still_cannot_create_for_it(): void
    {
        $club = $this->createClub($this->createUser(), ['country' => 'BH']);
        $stranger = $this->createUser();

        $this->actingAs($stranger)
            ->postJson('/me/events', [
                'tenant_id' => $club->id,
                'title' => 'Not Mine',
                'event_type' => 'class',
                'scope' => 'internal',
                'date' => now()->addMonth()->toDateString(),
                'start_time' => '09:00',
                'participant_free' => true,
                'spectator_enabled' => false,
            ])
            ->assertForbidden();
    }
}
