<?php

namespace Tests\Feature;

use App\Models\UserRelationship;
use Tests\TestCase;

class GoalTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Bench press 80kg',
            'description' => 'Strength target for the quarter',
            'target_value' => 80,
            'unit' => 'kg',
            'target_date' => now()->addMonth()->toDateString(),
            'priority_level' => 'high',
        ], $overrides);
    }

    public function test_member_can_create_a_goal_for_themselves(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/goal", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'title' => 'Bench press 80kg',
            'status' => 'active',
            'unit' => 'kg',
        ]);
    }

    public function test_guardian_can_create_a_goal_for_their_dependent(): void
    {
        $guardian = $this->createUser();
        $dependent = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => 'child',
        ]);

        $this->actingAs($guardian)
            ->postJson("/member/{$dependent->id}/goal", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('goals', ['user_id' => $dependent->id, 'title' => 'Bench press 80kg']);
    }

    public function test_unrelated_user_cannot_create_a_goal_for_someone_else(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();

        $this->actingAs($rando)
            ->postJson("/member/{$owner->id}/goal", $this->payload())
            ->assertNotFound(); // guardian relationship lookup firstOrFail() -> 404

        $this->assertDatabaseCount('goals', 0);
    }

    public function test_goal_creation_validates_required_fields(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/goal", $this->payload(['title' => '', 'target_value' => '', 'unit' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'target_value', 'unit']);
    }

    public function test_target_date_must_not_be_in_the_past(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/goal", $this->payload(['target_date' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_date']);
    }
}
