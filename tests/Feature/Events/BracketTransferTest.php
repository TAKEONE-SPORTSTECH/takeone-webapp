<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventRegistrationFeeLine;
use App\Members\Models\HealthRecord;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The split view's drop: a competitor dragged from one division's draw into
 * ANOTHER's (App\Events\Support\Tournament\Transfer).
 *
 * MOVE takes the entry out of the old division and into the new one; COPY
 * makes a second entry and the organiser says whether it is charged.
 */
class BracketTransferTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['events.draw_transfer' => true]);

        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
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
    }

    private function enter(ClubEvent $event, EventCategory $category, string $name): ClubEventRegistration
    {
        $user = $this->createUser([
            'full_name' => $name, 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'category_id' => $category->id,
            'role' => 'participant', 'paid' => true, 'weight' => 57,
        ]);
    }

    /** Two divisions of four, both drawn. */
    private function twoDrawnDivisions(ClubEvent $event): array
    {
        $a = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -58 kg', 'sort_order' => 1]);
        $b = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -63 kg', 'sort_order' => 2]);

        foreach (range(1, 4) as $i) {
            $this->enter($event, $a, 'A Athlete '.$i);
            $this->enter($event, $b, 'B Athlete '.$i);
        }

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/actions/generate_draw")
            ->assertOk();

        return [$a->fresh(), $b->fresh()];
    }

    private function firstRound(EventCategory $category)
    {
        return $category->matches()->orderBy('slot')->get()
            ->groupBy('round')
            ->sortBy(fn ($bouts) => $bouts->min('slot'))
            ->first()->sortBy('slot')->values();
    }

    private function transfer(ClubEvent $event, array $body, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/transfer", $body);
    }

    public function test_the_door_is_closed_while_the_flag_is_off(): void
    {
        config(['events.draw_transfer' => false]);
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $bout = $this->firstRound($a)[0];

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $bout->a_competitor_id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'move',
        ])->assertNotFound();
    }

    public function test_move_takes_them_out_of_the_old_draw_and_into_the_new_division(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $bout = $this->firstRound($a)[0];
        $id = $bout->a_competitor_id;

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'move',
        ])->assertOk()->assertJson(['success' => true]);

        $entry = ClubEventRegistration::find($id);
        $this->assertSame($b->id, (int) $entry->category_id, 'the one entry now belongs to the new division');
        $this->assertSame(ClubEventRegistration::PLACED_MANUAL, $entry->category_source);
        $this->assertNotContains($id, $a->matches()->get()->flatMap->competitorIds()->all(), 'gone from the old draw');
        $this->assertSame(8, ClubEventRegistration::where('event_id', $event->id)->count(), 'a move makes no new entry');
    }

    public function test_move_into_a_corner_of_the_new_draw(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $id = $this->firstRound($a)[0]->a_competitor_id;
        $target = $this->firstRound($b)[1];
        $displaced = $target->b_competitor_id;

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $id, 'to_category_id' => $b->id,
            'to' => ['type' => 'slot', 'match_id' => $target->id, 'side' => 'b'], 'mode' => 'move',
        ])->assertOk();

        $this->assertSame($id, (int) $target->fresh()->b_competitor_id, 'standing in the corner it was dropped on');
        $this->assertSame($b->id, (int) ClubEventRegistration::find($displaced)->category_id, 'the displaced athlete stays in the division');
        $this->assertNotContains($displaced, $b->matches()->get()->flatMap->competitorIds()->all(), '…on its basket');
    }

    public function test_copy_without_charge_is_a_second_free_entry_and_leaves_the_first_alone(): void
    {
        $event = $this->event(['participant_fee_amount' => 20]);
        [$a, $b] = $this->twoDrawnDivisions($event);
        $bout = $this->firstRound($a)[0];
        $id = $bout->a_competitor_id;
        $original = ClubEventRegistration::find($id);

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'copy', 'charge' => false,
        ])->assertOk();

        $this->assertSame($a->id, (int) $original->fresh()->category_id, 'the original entry has not moved');
        $this->assertSame($id, (int) $bout->fresh()->a_competitor_id, '…nor left its corner');

        $copy = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $original->user_id)->where('category_id', $b->id)->firstOrFail();
        $this->assertTrue((bool) $copy->paid);
        $this->assertSame(0.0, (float) EventRegistrationFeeLine::where('registration_id', $copy->id)->sum('amount'));
        $this->assertTrue(EventRegistrationFeeLine::where('registration_id', $copy->id)->exists(), 'recorded as free, never re-valued');
    }

    public function test_copy_with_charge_is_an_unpaid_entry_at_the_entry_price(): void
    {
        $event = $this->event(['participant_fee_amount' => 20]);
        [$a, $b] = $this->twoDrawnDivisions($event);
        $original = ClubEventRegistration::find($this->firstRound($a)[0]->a_competitor_id);

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $original->id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'copy', 'charge' => true,
        ])->assertOk();

        $copy = ClubEventRegistration::where('user_id', $original->user_id)->where('category_id', $b->id)->firstOrFail();
        $this->assertFalse((bool) $copy->paid);
        $this->assertNull($copy->payment_proof);
        $this->assertSame(20.0, (float) EventRegistrationFeeLine::where('registration_id', $copy->id)->sum('amount'));
    }

    public function test_a_person_already_in_the_target_division_is_refused(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $id = $this->firstRound($a)[0]->a_competitor_id;

        $body = [
            'from_category_id' => $a->id, 'competitor_id' => $id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'copy', 'charge' => false,
        ];
        $this->transfer($event, $body)->assertOk();
        $this->transfer($event, $body)->assertStatus(422)->assertJson(['success' => false]);
        $this->transfer($event, ['mode' => 'move'] + $body)->assertStatus(422);
    }

    public function test_somebody_who_has_fought_cannot_be_moved_out_but_can_be_copied(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $bout = $this->firstRound($a)[0];
        $bout->update(['status' => 'done', 'winner' => 'a', 'a_score' => 5]);

        $body = [
            'from_category_id' => $a->id, 'competitor_id' => $bout->a_competitor_id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'],
        ];

        $this->transfer($event, $body + ['mode' => 'move'])->assertStatus(422);
        $this->assertSame($a->id, (int) ClubEventRegistration::find($bout->a_competitor_id)->category_id);

        $this->transfer($event, $body + ['mode' => 'copy', 'charge' => false])->assertOk();
    }

    public function test_a_stranger_cannot_transfer_anybody(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $id = $this->firstRound($a)[0]->a_competitor_id;

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $id,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'move',
        ], $this->createUser())->assertForbidden();

        $this->assertSame($a->id, (int) ClubEventRegistration::find($id)->category_id);
    }

    public function test_an_entry_from_another_event_resolves_to_nothing(): void
    {
        $event = $this->event();
        [$a, $b] = $this->twoDrawnDivisions($event);
        $other = $this->event(['title' => 'Other Open']);
        [$oa] = $this->twoDrawnDivisions($other);
        $foreign = $this->firstRound($oa)[0]->a_competitor_id;

        $this->transfer($event, [
            'from_category_id' => $a->id, 'competitor_id' => $foreign,
            'to_category_id' => $b->id, 'to' => ['type' => 'bench'], 'mode' => 'move',
        ])->assertStatus(422);

        $this->assertSame($oa->id, (int) ClubEventRegistration::find($foreign)->category_id);
    }
}
