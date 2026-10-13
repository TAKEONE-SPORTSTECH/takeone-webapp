<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use Tests\TestCase;

/**
 * Entry & tickets on the event editor: a NEW event is paid by default and its
 * fee list opens with a "Joining fee" row — an ordinary, editable, removable
 * fee type. An existing event shows only what it actually has.
 */
class JoiningFeeDefaultTest extends TestCase
{
    public function test_a_new_event_opens_paid_with_a_joining_fee_row(): void
    {
        $owner = $this->createUser();

        $this->actingAs($owner)->get(route('me.events.create'))
            ->assertOk()
            ->assertSee('participant_free: false', false)
            ->assertSee('Joining fee');
    }

    public function test_editing_an_event_does_not_add_a_joining_fee_row(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Cup', 'event_type' => 'championship',
            'sport' => 'taekwondo', 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $this->actingAs($owner->fresh())->get(route('me.events.edit', $event->uuid))
            ->assertOk()
            ->assertDontSee('Joining fee');
    }
}
