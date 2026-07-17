<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\UserRelationship;
use Tests\TestCase;

class GoalTest extends TestCase
{
    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Bench press 80kg',
            'description' => 'Strength target for the quarter',
            'target_value' => 80,
            'unit' => 'kg',
            'target_date' => now()->addMonth()->toDateString(),
            'priority_level' => 'high',
            'before_proof' => self::TINY_PNG,
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

        $goal = Goal::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($goal->before_proof);
    }

    public function test_goal_creation_requires_a_before_photo(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/goal", $this->payload(['before_proof' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['before_proof']);
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

    public function test_completing_a_goal_requires_an_after_photo(): void
    {
        $user = $this->createUser();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Run 10k',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addWeek()->toDateString(),
            'current_progress_value' => 5,
            'target_value' => 10,
            'status' => 'active',
            'unit' => 'km',
            'before_proof' => 'goal-proofs/before.png',
        ]);

        $this->actingAs($user)
            ->putJson("/member/goal/{$goal->id}", [
                'current_progress_value' => 10,
                'status' => 'completed',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'status' => 'active', 'completed_at' => null]);
    }

    public function test_updating_progress_without_completing_ignores_an_empty_after_proof(): void
    {
        // Regression guard: the desktop edit form's "after photo" input is always present
        // in the DOM (just hidden via CSS), so a plain progress update submits it as ''.
        $user = $this->createUser();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Run 10k',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addWeek()->toDateString(),
            'current_progress_value' => 5,
            'target_value' => 10,
            'status' => 'active',
            'unit' => 'km',
            'before_proof' => 'goal-proofs/before.png',
        ]);

        $this->actingAs($user)
            ->putJson("/member/goal/{$goal->id}", [
                'current_progress_value' => 7,
                'status' => 'active',
                'after_proof' => '',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'current_progress_value' => 7, 'status' => 'active']);
    }

    public function test_completing_a_goal_with_an_after_photo_stamps_completion(): void
    {
        $user = $this->createUser();
        $goal = Goal::create([
            'user_id' => $user->id,
            'title' => 'Run 10k',
            'start_date' => now()->subDays(3)->toDateString(),
            'target_date' => now()->addWeek()->toDateString(),
            'current_progress_value' => 5,
            'target_value' => 10,
            'status' => 'active',
            'unit' => 'km',
            'before_proof' => 'goal-proofs/before.png',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/member/goal/{$goal->id}", [
                'current_progress_value' => 10,
                'status' => 'completed',
                'after_proof' => self::TINY_PNG,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $response->assertJsonPath('goal.status', 'completed');
        $this->assertNotNull($response->json('goal.days_taken'));
        $this->assertNotNull($response->json('goal.after_proof'));

        $goal->refresh();
        $this->assertSame('completed', $goal->status);
        $this->assertNotNull($goal->completed_at);
        $this->assertNotNull($goal->after_proof);
    }
}
