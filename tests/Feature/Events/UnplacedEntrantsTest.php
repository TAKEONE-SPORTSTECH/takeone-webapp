<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * me.events.divisions.unplaced — who is in no division, and where each fits
 * (App\Events\Support\UnplacedEntrants). Placing is divisions.members.
 */
class UnplacedEntrantsTest extends ContractTestCase
{
    public function test_the_organiser_sees_who_is_unplaced_and_the_tightest_division_they_fit(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser, ['date' => now()->addMonth()->toDateString()]);
        $kids = ['gender' => 'male', 'min_age' => 6, 'max_age' => 11, 'min_weight' => 0];
        $wide = EventCategory::create(['event_id' => $event->id, 'name' => 'Kids -40', 'sort_order' => 1, 'max_weight' => 40] + $kids);
        $tight = EventCategory::create(['event_id' => $event->id, 'name' => 'Kids -30', 'sort_order' => 2, 'max_weight' => 30] + $kids);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Girls -30', 'sort_order' => 3, 'max_weight' => 30, 'gender' => 'female', 'min_age' => 6, 'max_age' => 11]);

        $kid = $this->createUser(['full_name' => 'Small Boy', 'gender' => 'Male', 'birthdate' => now()->subYears(9)->toDateString()]);
        $entry = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $kid->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 24]);

        // Somebody already placed is not listed.
        $placed = $this->createUser(['full_name' => 'Placed Boy', 'gender' => 'Male']);
        ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $placed->id, 'category_id' => $wide->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 35]);

        $res = $this->actingAs($organiser)->getJson(route('me.events.divisions.unplaced', $event->uuid))->assertOk();

        $this->assertSame(['Small Boy'], array_column($res->json('people'), 'name'));
        $this->assertSame($entry->id, $res->json('people.0.entry'));
        $this->assertSame($tight->id, $res->json('people.0.suggested'), 'the narrowest division he fits');
        $this->assertNotContains('Girls -30', array_column($res->json('people.0.options'), 'name'));

        // And the existing door places him.
        $this->actingAs($organiser)->putJson(route('me.events.divisions.members', [$event->uuid, $tight->id]), ['add' => [$entry->id]])->assertOk();
        $this->actingAs($organiser)->getJson(route('me.events.divisions.unplaced', $event->uuid))->assertOk()->assertJsonCount(0, 'people');
    }

    public function test_nobody_else_reads_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);

        $this->actingAs($this->createUser())->getJson(route('me.events.divisions.unplaced', $event->uuid))->assertForbidden();
    }
}
