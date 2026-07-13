<?php

namespace Tests\Feature;

use App\Models\UserRelationship;
use Tests\TestCase;

class MemberEventTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'City 10K Fun Run',
            'event_date' => now()->subWeek()->toDateString(),
            'location' => 'Manama',
            'role' => 'Participant',
            'result' => 'Finished 52:10',
            'notes' => 'First road race',
        ], $overrides);
    }

    public function test_member_can_add_own_event_log_entry(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/event-log", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_events', [
            'user_id' => $user->id,
            'title' => 'City 10K Fun Run',
            'role' => 'Participant',
        ]);
    }

    public function test_guardian_can_add_event_for_dependent(): void
    {
        $guardian = $this->createUser();
        $dependent = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => 'child',
        ]);

        $this->actingAs($guardian)
            ->postJson("/member/{$dependent->id}/event-log", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_events', ['user_id' => $dependent->id, 'title' => 'City 10K Fun Run']);
    }

    public function test_unrelated_user_cannot_add_event_for_someone_else(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();

        $this->actingAs($rando)
            ->postJson("/member/{$owner->id}/event-log", $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('member_events', 0);
    }

    public function test_event_validation_requires_title_and_date(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/event-log", $this->payload(['title' => '', 'event_date' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'event_date']);
    }
}
