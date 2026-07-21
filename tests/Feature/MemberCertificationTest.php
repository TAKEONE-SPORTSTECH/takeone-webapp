<?php

namespace Tests\Feature;

use App\Models\MemberCertification;
use App\Models\UserRelationship;
use Tests\TestCase;

class MemberCertificationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'World Taekwondo Poomsae Referee',
            'issuer' => 'World Taekwondo',
            'issue_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'credential_id' => 'WT-12345',
            'credential_url' => 'https://worldtaekwondo.org/verify/WT-12345',
            'notes' => 'International level.',
        ], $overrides);
    }

    public function test_member_can_add_own_certification(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/certification", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_certifications', [
            'user_id' => $user->id,
            'title' => 'World Taekwondo Poomsae Referee',
            'credential_id' => 'WT-12345',
        ]);
    }

    public function test_guardian_can_add_certification_for_dependent(): void
    {
        $guardian = $this->createUser();
        $dependent = $this->createUser();
        UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $dependent->id,
            'relationship_type' => 'child',
        ]);

        $this->actingAs($guardian)
            ->postJson("/member/{$dependent->id}/certification", $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_certifications', ['user_id' => $dependent->id, 'title' => 'World Taekwondo Poomsae Referee']);
    }

    public function test_unrelated_user_cannot_add_certification_for_someone_else(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();

        $this->actingAs($rando)
            ->postJson("/member/{$owner->id}/certification", $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('member_certifications', 0);
    }

    public function test_certification_requires_title(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/certification", $this->payload(['title' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_certification_rejects_unsafe_credential_url(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson("/member/{$user->id}/certification", $this->payload(['credential_url' => 'javascript:alert(1)']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credential_url']);
    }

    public function test_member_can_update_and_delete_own_certification(): void
    {
        $user = $this->createUser();
        $cert = MemberCertification::create(['user_id' => $user->id, 'title' => 'Old title']);

        $this->actingAs($user)
            ->putJson("/member/certification/{$cert->id}", $this->payload(['title' => 'New title']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('member_certifications', ['id' => $cert->id, 'title' => 'New title']);

        $this->actingAs($user)
            ->deleteJson("/member/certification/{$cert->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('member_certifications', ['id' => $cert->id]);
    }

    public function test_unrelated_user_cannot_update_others_certification(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();
        $cert = MemberCertification::create(['user_id' => $owner->id, 'title' => 'Owned']);

        $this->actingAs($rando)
            ->putJson("/member/certification/{$cert->id}", $this->payload())
            ->assertNotFound();

        $this->assertDatabaseHas('member_certifications', ['id' => $cert->id, 'title' => 'Owned']);
    }
}
