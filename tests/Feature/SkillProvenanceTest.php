<?php

namespace Tests\Feature;

use App\Models\ClubActivity;
use App\Models\ClubAffiliation;
use App\Models\SkillAcquisition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AchievementVerificationService;
use Tests\TestCase;

/**
 * Self-added skills carry provenance (activity + since-when + club) and earn
 * authenticity through the same verification engine as medals.
 */
class SkillProvenanceTest extends TestCase
{
    private function affiliation(User $member, ?Tenant $club): ClubAffiliation
    {
        return ClubAffiliation::create([
            'member_id' => $member->id,
            'tenant_id' => $club?->id,
            'club_name' => $club?->club_name ?? 'Old Dojang',
            'start_date' => now()->subYears(3),
        ]);
    }

    public function test_activities_endpoint_returns_the_named_clubs_activities(): void
    {
        $club = $this->createClub($this->createUser());
        $act = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Sparring']);
        $member = $this->createUser();
        $aff = $this->affiliation($member, $club);

        $this->actingAs($member)
            ->getJson("/member/{$member->id}/affiliations/{$aff->id}/activities")
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('activities.0.name', 'Sparring')
            ->assertJsonPath('activities.0.id', $act->id);
    }

    public function test_self_added_skill_records_activity_and_is_self_reported(): void
    {
        $club = $this->createClub($this->createUser());
        $act = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Sparring']);
        $member = $this->createUser();
        $aff = $this->affiliation($member, $club);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/skills", [
                'skill_name' => 'Roundhouse kick',
                'proficiency_level' => 'advanced',
                'activity_id' => $act->id,
                // Must sit inside the affiliation (opened 3 years ago) and carry a
                // span — see SkillSpanValidationTest for those rules.
                'start_date' => now()->subYears(2)->toDateString(),
                'duration_months' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('skill.activity', 'Sparring')
            ->assertJsonPath('skill.verification.status', 'self_reported');

        $this->assertDatabaseHas('skill_acquisitions', [
            'user_id' => $member->id, 'skill_name' => 'Roundhouse kick',
            'activity_id' => $act->id, 'verification_status' => 'self_reported',
        ]);
    }

    public function test_activity_id_must_belong_to_the_affiliation_club(): void
    {
        $clubA = $this->createClub($this->createUser());
        $clubB = $this->createClub($this->createUser());
        $foreignActivity = ClubActivity::create(['tenant_id' => $clubB->id, 'name' => 'Boxing']);
        $member = $this->createUser();
        $aff = $this->affiliation($member, $clubA);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/skills", [
                'skill_name' => 'Jab', 'proficiency_level' => 'beginner', 'activity_id' => $foreignActivity->id,
            ])
            ->assertStatus(422);
    }

    public function test_skill_verification_request_and_club_confirm(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);
        $member = $this->createUser();
        $aff = $this->affiliation($member, $club);
        $skill = $aff->skillAcquisitions()->create([
            'user_id' => $member->id, 'skill_name' => 'Poomsae', 'proficiency_level' => 'expert', 'duration_months' => 24,
        ])->fresh();

        // Member requests verification → pending + owner notified.
        $this->actingAs($member)
            ->postJson("/member/{$member->id}/affiliations/{$aff->id}/skills/{$skill->uuid}/request-verification")
            ->assertOk()
            ->assertJsonPath('verification.status', 'pending');

        $this->assertDatabaseHas('user_notifications', ['user_id' => $owner->id, 'type' => 'verification:requested']);

        // Club confirms via the shared service → verified.
        app(AchievementVerificationService::class)->clubConfirm($skill->fresh(), $admin);

        $this->assertDatabaseHas('skill_acquisitions', [
            'id' => $skill->id, 'verification_status' => 'verified', 'verified_by_tenant_id' => $club->id,
        ]);
    }

    public function test_off_platform_skill_verifies_via_coach_vouch(): void
    {
        $member = $this->createUser();
        $aff = $this->affiliation($member, null); // no tenant_id
        $skill = $aff->skillAcquisitions()->create([
            'user_id' => $member->id, 'skill_name' => 'Kata', 'proficiency_level' => 'advanced', 'activity_name' => 'Karate', 'duration_months' => 12,
        ])->fresh();

        // A coach vouch (weight 1.5) alone is below threshold → still pending.
        $coach = $this->createUser();
        app(AchievementVerificationService::class)->addVouch($skill, $coach, 'vouch', 'coach');
        $this->assertNotSame('verified', $skill->fresh()->verification_status);
    }

    private function callTool(string $tool, array $args = []): array
    {
        $text = (string) (new $tool)->handle(new \Laravel\Mcp\Request($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }

    public function test_mcp_get_member_exposes_verified_skills_only(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $club = $this->createClub($this->createUser());
        $member = $this->createUser();
        $aff = $this->affiliation($member, $club);

        $verified = $aff->skillAcquisitions()->create(['user_id' => $member->id, 'skill_name' => 'Sparring', 'proficiency_level' => 'advanced', 'duration_months' => 12]);
        $verified->forceFill(['verification_status' => 'verified'])->save();
        $aff->skillAcquisitions()->create(['user_id' => $member->id, 'skill_name' => 'Secret', 'proficiency_level' => 'beginner', 'duration_months' => 1]); // self_reported

        $this->actingAs($admin);
        $result = $this->callTool(\App\Mcp\Tools\GetMemberTool::class, ['member' => $member->uuid]);

        $names = collect($result['skills'])->pluck('skill');
        $this->assertTrue($names->contains('Sparring'));
        $this->assertFalse($names->contains('Secret'));
    }

    public function test_mcp_verify_tool_confirms_a_skill(): void
    {
        $club = $this->createClub($this->createUser());
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);
        $member = $this->createUser();
        $aff = $this->affiliation($member, $club);
        $skill = $aff->skillAcquisitions()->create(['user_id' => $member->id, 'skill_name' => 'Poomsae', 'proficiency_level' => 'expert', 'duration_months' => 24])->fresh();

        $this->actingAs($admin);
        $result = $this->callTool(\App\Mcp\Tools\VerifyAchievementTool::class, [
            'uuid' => $skill->uuid, 'type' => 'skill', 'decision' => 'confirm',
        ]);

        $this->assertSame('verified', $result['verification_status']);
    }
}
