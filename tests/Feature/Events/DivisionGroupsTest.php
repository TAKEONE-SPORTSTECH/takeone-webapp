<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * Building a bracket by hand.
 *
 * The thing being protected here is the ONE behaviour the feature exists for:
 * an organiser must be able to put an athlete into a group they do not strictly
 * belong to. A fourteen-year-old moved up because there is no junior bracket, a
 * lighter athlete given a fight at all — that is competition management, not a
 * mistake to be prevented. The range sorts the roster and marks the exception;
 * it never refuses one.
 *
 * The rest is the usual: scope every division to its event, and let the people
 * who may arrange a draw fill a group without letting them edit the event.
 */
class DivisionGroupsTest extends TestCase
{
    /** @return array{0: User, 1: ClubEvent} */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Manual Draw Open',
            'event_type' => 'championship',
            'sport' => 'bjj',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        return [$owner, $event];
    }

    /** An entrant with whatever is known about them — often not much. */
    private function entrant(ClubEvent $event, string $name, ?string $gender, ?int $age, ?float $weight): ClubEventRegistration
    {
        $user = User::create([
            'full_name' => $name,
            'name' => $name,
            'email' => str()->slug($name).'-'.str()->random(5).'@example.test',
            'password' => bcrypt('secret-secret'),
            'gender' => $gender,
            'birthdate' => $age === null ? null : now()->subYears($age)->subMonths(2)->toDateString(),
            'email_verified_at' => now(),
        ]);

        return ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'participant',
            'status' => 'joined',
            'paid' => true,
            'registered_at' => now(),
            'weight' => $weight,
        ]);
    }

    private function url(ClubEvent $event, string $tail = ''): string
    {
        return "/me/events/{$event->uuid}/divisions".$tail;
    }

    /* ---------------- Making a group ---------------- */

    public function test_an_organiser_can_create_a_group_by_hand(): void
    {
        [$owner, $event] = $this->scenario();

        $response = $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Kids Male -40 kg',
            'gender' => 'Male',
            'min_age' => 10, 'max_age' => 13,
            'min_weight' => 30, 'max_weight' => 40,
        ]);

        $response->assertOk()
            ->assertJsonPath('division.name', 'Kids Male -40 kg')
            ->assertJsonPath('division.range.gender', 'Male')
            ->assertJsonPath('division.range.min_age', 10)
            ->assertJsonPath('division.range.max_weight', 40);

        $this->assertDatabaseHas('event_categories', [
            'event_id' => $event->id, 'name' => 'Kids Male -40 kg', 'gender' => 'Male',
        ]);
    }

    public function test_a_group_with_no_range_at_all_is_allowed(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson($this->url($event), ['name' => 'Open mat'])
            ->assertOk()
            ->assertJsonPath('division.range.open', true);
    }

    public function test_a_backwards_range_is_refused(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Impossible', 'min_age' => 20, 'max_age' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('max_age');

        $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Impossible', 'min_weight' => 90, 'max_weight' => 60,
        ])->assertStatus(422)->assertJsonValidationErrors('max_weight');
    }

    public function test_a_stranger_cannot_create_a_group(): void
    {
        [, $event] = $this->scenario();

        $this->actingAs($this->createUser())
            ->postJson($this->url($event), ['name' => 'Theirs'])
            ->assertForbidden();
    }

    /* ---------------- Who could go in it ---------------- */

    public function test_the_picker_offers_every_entrant_and_says_how_each_one_fits(): void
    {
        [$owner, $event] = $this->scenario();

        $fits    = $this->entrant($event, 'Fits Well', 'Male', 12, 35.0);
        $tooOld  = $this->entrant($event, 'Too Old', 'Male', 30, 35.0);
        $woman   = $this->entrant($event, 'Wrong Gender', 'Female', 12, 35.0);
        $noData  = $this->entrant($event, 'Nothing Known', null, null, null);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Kids Male -40', 'status' => 'enrolling',
            'gender' => 'Male', 'min_age' => 10, 'max_age' => 13, 'min_weight' => 30, 'max_weight' => 40,
        ]);

        $response = $this->actingAs($owner)->getJson($this->url($event, "/{$division->id}/candidates"));
        $response->assertOk();

        $by = collect($response->json('people'))->keyBy('competitor_id');

        // Nobody is hidden — the filtering is the client's job precisely so the
        // organiser can reach past it.
        $this->assertCount(4, $by);

        $this->assertSame('in', $by[$fits->id]['fit']);
        $this->assertSame('out', $by[$tooOld->id]['fit']);
        $this->assertSame(['age'], $by[$tooOld->id]['misses']);
        $this->assertSame('out', $by[$woman->id]['fit']);
        $this->assertSame(['gender'], $by[$woman->id]['misses']);

        // Missing data is its own answer, never a mismatch: birthdate is never
        // required on this platform and weight is only known after a weigh-in.
        $this->assertSame('unknown', $by[$noData->id]['fit']);
        $this->assertSame([], $by[$noData->id]['misses']);
        $this->assertNull($by[$noData->id]['age']);
        $this->assertNull($by[$noData->id]['weight']);
    }

    public function test_age_is_measured_on_the_day_of_the_event_not_today(): void
    {
        [$owner, $event] = $this->scenario();

        // Turns 14 the week after the event: still 13 on the day, so still in.
        $user = User::create([
            'full_name' => 'Birthday Soon', 'name' => 'Birthday Soon',
            'email' => 'bd-'.str()->random(5).'@example.test',
            'password' => bcrypt('secret-secret'), 'gender' => 'Male',
            'birthdate' => now()->addWeeks(3)->subYears(14)->toDateString(),
            'email_verified_at' => now(),
        ]);
        $reg = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'role' => 'participant',
            'status' => 'joined', 'paid' => true, 'registered_at' => now(),
        ]);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Kids', 'status' => 'enrolling',
            'min_age' => 10, 'max_age' => 13,
        ]);

        $people = $this->actingAs($owner)
            ->getJson($this->url($event, "/{$division->id}/candidates"))->json('people');

        $row = collect($people)->firstWhere('competitor_id', $reg->id);

        $this->assertSame(13, $row['age']);
        $this->assertSame('in', $row['fit']);
    }

    /* ---------------- The whole point ---------------- */

    public function test_somebody_outside_the_range_can_still_be_added(): void
    {
        [$owner, $event] = $this->scenario();

        $child = $this->entrant($event, 'Young Prospect', 'Male', 14, 60.0);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Adult Male', 'status' => 'enrolling',
            'gender' => 'Male', 'min_age' => 18, 'max_age' => 40,
        ]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$child->id]])
            ->assertOk();

        $this->assertSame($division->id, $child->fresh()->category_id);

        // And the exception stays visible afterwards rather than becoming the record.
        $row = collect($this->actingAs($owner)
            ->getJson($this->url($event, "/{$division->id}/candidates"))->json('people'))
            ->firstWhere('competitor_id', $child->id);

        $this->assertTrue($row['here']);
        $this->assertSame('out', $row['fit']);
        $this->assertSame(['age'], $row['misses']);
    }

    public function test_moving_someone_between_groups_takes_them_out_of_the_old_bracket(): void
    {
        [$owner, $event] = $this->scenario();

        $from = EventCategory::create(['event_id' => $event->id, 'name' => 'From', 'status' => 'enrolling']);
        $to = EventCategory::create(['event_id' => $event->id, 'name' => 'To', 'status' => 'enrolling']);

        $mover = $this->entrant($event, 'Mover', 'Male', 25, 70.0);
        $mover->update(['category_id' => $from->id]);

        $bout = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $from->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_competitor_id' => $mover->id, 'a_name' => 'Mover',
            'b_name' => 'Someone', 'status' => 'upcoming',
        ]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$to->id}/members"), ['add' => [$mover->id]])
            ->assertOk();

        $this->assertSame($to->id, $mover->fresh()->category_id);

        // A bout cannot keep a competitor the division no longer holds. The
        // package re-cuts the division it was in, so asserting the INVARIANT
        // rather than the row: whether that bout was emptied or replaced
        // wholesale is the package's business, but the mover must not still be
        // standing in a draw they have left.
        $this->assertSame(0, EventMatch::where('category_id', $from->id)
            ->where(fn ($q) => $q->where('a_competitor_id', $mover->id)
                                 ->orWhere('b_competitor_id', $mover->id))
            ->count());

        unset($bout);
    }

    public function test_removing_someone_leaves_them_in_no_group(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $person = $this->entrant($event, 'Leaver', 'Male', 25, 70.0);
        $person->update(['category_id' => $division->id]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['remove' => [$person->id]])
            ->assertOk();

        $this->assertNull($person->fresh()->category_id);
    }

    /* ---------------- Scope and permission ---------------- */

    public function test_a_division_from_another_event_is_invisible(): void
    {
        [$owner, $event] = $this->scenario();
        [, $other] = $this->scenario();

        $theirs = EventCategory::create(['event_id' => $other->id, 'name' => 'Theirs', 'status' => 'enrolling']);

        $this->actingAs($owner)
            ->getJson($this->url($event, "/{$theirs->id}/candidates"))
            ->assertNotFound();

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$theirs->id}/members"), ['add' => []])
            ->assertNotFound();
    }

    public function test_an_entrant_from_another_event_cannot_be_dragged_in(): void
    {
        [$owner, $event] = $this->scenario();
        [, $other] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $outsider = $this->entrant($other, 'Outsider', 'Male', 25, 70.0);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$outsider->id]])
            ->assertOk();

        $this->assertNull($outsider->fresh()->category_id);
    }

    public function test_the_appointed_jury_may_fill_a_group_but_not_create_one(): void
    {
        [$owner, $event] = $this->scenario();

        $jury = $this->createUser();
        $jury->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY,
        ]);

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $person = $this->entrant($event, 'Anyone', 'Male', 25, 70.0);

        // Filling one is the same act as arranging a draw.
        $this->actingAs($jury)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$person->id]])
            ->assertOk();

        // Creating and deleting one is managing the event.
        $this->actingAs($jury)->postJson($this->url($event), ['name' => 'Mine'])->assertForbidden();
    }

    /* ---------------- Deleting ---------------- */

    public function test_a_group_with_people_in_it_is_not_deleted(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Busy', 'status' => 'enrolling']);
        $this->entrant($event, 'Held', 'Male', 25, 70.0)->update(['category_id' => $division->id]);

        $this->actingAs($owner)
            ->deleteJson($this->url($event, "/{$division->id}"))
            ->assertStatus(422);

        $this->assertDatabaseHas('event_categories', ['id' => $division->id]);
    }

    public function test_an_empty_group_is_deleted(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Spare', 'status' => 'enrolling']);

        $this->actingAs($owner)
            ->deleteJson($this->url($event, "/{$division->id}"))
            ->assertOk();

        $this->assertDatabaseMissing('event_categories', ['id' => $division->id]);
    }

    public function test_a_group_can_be_renamed_and_its_range_changed(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Old', 'status' => 'enrolling', 'min_age' => 10,
        ]);

        $this->actingAs($owner)
            ->patchJson($this->url($event, "/{$division->id}"), [
                'name' => 'New', 'min_age' => null, 'gender' => 'Female',
            ])
            ->assertOk()
            ->assertJsonPath('division.name', 'New')
            ->assertJsonPath('division.range.min_age', null)
            ->assertJsonPath('division.range.gender', 'Female');
    }
}
