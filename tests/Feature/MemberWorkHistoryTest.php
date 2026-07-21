<?php

namespace Tests\Feature;

use App\Models\MemberWorkHistory;
use App\Models\UserRelationship;
use Tests\TestCase;

class MemberWorkHistoryTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Head Coach',
            'organization' => 'Manama Taekwondo Academy',
            'employment_type' => 'Full-time',
            'location' => 'Manama',
            'start_date' => now()->subYears(3)->toDateString(),
            'end_date' => null,
            'description' => 'Led the competition squad.',
        ], $overrides);
    }

    public function test_member_can_add_own_work_history(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/work-history", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true, 'work' => ['current' => true]]);

        $this->assertDatabaseHas('member_work_history', [
            'user_id' => $user->id,
            'title' => 'Head Coach',
            'organization' => 'Manama Taekwondo Academy',
            'end_date' => null,
        ]);
    }

    public function test_guardian_can_add_work_history_for_dependent(): void
    {
        $guardian = $this->createUser();
        $dependent = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => 'child',
        ]);

        $this->actingAs($guardian)
            ->postJson("/member/{$dependent->id}/work-history", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_work_history', ['user_id' => $dependent->id, 'title' => 'Head Coach']);
    }

    public function test_unrelated_user_cannot_add_work_history_for_someone_else(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();

        $this->actingAs($rando)
            ->postJson("/member/{$owner->id}/work-history", $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('member_work_history', 0);
    }

    public function test_work_history_requires_title_org_and_start(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/work-history", $this->payload(['title' => '', 'organization' => '', 'start_date' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'organization', 'start_date']);
    }

    public function test_work_history_rejects_end_before_start(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/work-history", $this->payload([
                'start_date' => now()->toDateString(),
                'end_date' => now()->subYear()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_member_can_update_and_delete_own_work_history(): void
    {
        $user = $this->createUser();
        $work = MemberWorkHistory::create([
            'user_id' => $user->id, 'title' => 'Assistant', 'organization' => 'Old Org',
            'start_date' => now()->subYears(2)->toDateString(),
        ]);

        $this->actingAs($user)
            ->putJson("/member/work-history/{$work->id}", $this->payload(['title' => 'Head Coach']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_work_history', ['id' => $work->id, 'title' => 'Head Coach']);

        $this->actingAs($user)
            ->deleteJson("/member/work-history/{$work->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('member_work_history', ['id' => $work->id]);
    }
}
