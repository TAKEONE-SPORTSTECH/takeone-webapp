<?php

namespace Tests\Feature;

use App\Models\UserRelationship;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'session_datetime' => now()->subDay()->format('Y-m-d H:i:s'),
            'session_type' => 'Personal Training',
            'trainer_name' => 'Coach Sami',
            'status' => 'completed',
            'notes' => 'Good session',
        ], $overrides);
    }

    public function test_member_can_add_own_attendance_record(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/attendance", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('members_attendance', [
            'member_id' => $user->id,
            'session_type' => 'Personal Training',
            'status' => 'completed',
        ]);
    }

    public function test_guardian_can_add_attendance_for_dependent(): void
    {
        $guardian = $this->createUser();
        $dependent = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => 'child',
        ]);

        $this->actingAs($guardian)
            ->postJson("/member/{$dependent->id}/attendance", $this->payload(['status' => 'no_show']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('members_attendance', ['member_id' => $dependent->id, 'status' => 'no_show']);
    }

    public function test_unrelated_user_cannot_add_attendance_for_someone_else(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();

        $this->actingAs($rando)
            ->postJson("/member/{$owner->id}/attendance", $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('members_attendance', 0);
    }

    public function test_attendance_validation(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/attendance", $this->payload([
                'session_datetime' => '',
                'session_type' => '',
                'status' => 'invalid',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session_datetime', 'session_type', 'status']);
    }
}
