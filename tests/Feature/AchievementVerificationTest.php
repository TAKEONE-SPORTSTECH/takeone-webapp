<?php

namespace Tests\Feature;

use App\Mcp\Tools\GetMemberTool;
use App\Mcp\Tools\VerifyAchievementTool;
use App\Models\AchievementVouch;
use App\Models\ClubAffiliation;
use App\Models\ClubInstructor;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\TournamentEvent;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\AchievementVerificationService;
use Laravel\Mcp\Request as McpRequest;
use Tests\TestCase;

/**
 * Authenticity layer for member self-claimed achievements: a claim is only ever
 * shown as verified once a trusted authority attests to it (club-confirm or
 * credible peer/coach vouches). Status is set only server-side, never trusted
 * from the client.
 */
class AchievementVerificationTest extends TestCase
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

    private function seedClaim(User $member, ?Tenant $club = null, string $medal = '1st'): TournamentEvent
    {
        $aff = $this->affiliation($member, $club);
        $t = TournamentEvent::create([
            'user_id' => $member->id,
            'club_affiliation_id' => $aff->id,
            'title' => 'Nationals 2019',
            'type' => 'championship',
            'sport' => 'Taekwondo',
            'date' => '2019-05-01',
        ]);
        $t->performanceResults()->create(['medal_type' => $medal]);

        return $t->fresh();
    }

    private function service(): AchievementVerificationService
    {
        return app(AchievementVerificationService::class);
    }

    // ---- creation / request -------------------------------------------------

    public function test_new_tournament_is_self_reported_by_default(): void
    {
        $member = $this->createUser();
        $club = $this->createClub($this->createUser());
        $aff = $this->affiliation($member, $club);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament", [
                'title' => 'City Open', 'type' => 'tournament', 'sport' => 'Judo',
                'date' => '2020-02-02', 'club_affiliation_id' => $aff->id,
                'performance_results' => [['medal_type' => '1st']],
            ])
            ->assertOk()
            ->assertJsonPath('tournament.verification.status', 'self_reported');

        $this->assertDatabaseHas('tournament_events', [
            'user_id' => $member->id, 'verification_status' => 'self_reported',
        ]);
    }

    public function test_client_cannot_force_verified_status(): void
    {
        $member = $this->createUser();

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament", [
                'title' => 'Fake', 'type' => 'tournament', 'sport' => 'Judo', 'date' => '2020-02-02',
                'verification_status' => 'verified', 'verified_at' => now(), // must be ignored
            ])
            ->assertOk();

        $this->assertDatabaseMissing('tournament_events', ['verification_status' => 'verified']);
    }

    public function test_request_verification_moves_claim_to_pending_and_notifies_admin(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $member = $this->createUser();
        $claim = $this->seedClaim($member, $club);

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament/{$claim->uuid}/request-verification")
            ->assertOk()
            ->assertJsonPath('verification.status', 'pending');

        $this->assertDatabaseHas('tournament_events', ['id' => $claim->id, 'verification_status' => 'pending']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $owner->id, 'type' => 'verification:requested']);
    }

    public function test_request_verification_requires_a_platform_club(): void
    {
        $member = $this->createUser();
        $claim = $this->seedClaim($member, null); // affiliation has no tenant_id

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament/{$claim->uuid}/request-verification")
            ->assertStatus(422);
    }

    // ---- club confirm / reject ---------------------------------------------

    public function test_only_the_named_clubs_admin_can_confirm(): void
    {
        $clubA = $this->createClub($this->createUser());
        $clubB = $this->createClub($this->createUser());
        $adminB = $this->createUser();
        $this->makeClubAdmin($adminB, $clubB);

        $member = $this->createUser();
        $claim = $this->seedClaim($member, $clubA);

        // Admin of the WRONG club cannot verify.
        $this->actingAs($adminB)
            ->postJson("/admin/club/{$clubB->slug}/achievements/verifications/achievement/{$claim->uuid}/confirm")
            ->assertNotFound(); // claim doesn't name clubB → 404

        $adminA = $this->createUser();
        $this->makeClubAdmin($adminA, $clubA);

        $this->actingAs($adminA)
            ->postJson("/admin/club/{$clubA->slug}/achievements/verifications/achievement/{$claim->uuid}/confirm")
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('tournament_events', [
            'id' => $claim->id, 'verification_status' => 'verified',
            'verification_method' => 'club_confirm', 'verified_by_tenant_id' => $clubA->id,
        ]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $member->id, 'type' => 'verification:approved']);
    }

    public function test_club_reject_records_reason(): void
    {
        $club = $this->createClub($this->createUser());
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);
        $member = $this->createUser();
        $claim = $this->seedClaim($member, $club);

        $this->actingAs($admin)
            ->postJson("/admin/club/{$club->slug}/achievements/verifications/achievement/{$claim->uuid}/reject", ['note' => 'No record of this'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('tournament_events', [
            'id' => $claim->id, 'verification_status' => 'rejected', 'verification_note' => 'No record of this',
        ]);
    }

    // ---- evidence security --------------------------------------------------

    public function test_evidence_svg_is_rejected(): void
    {
        $member = $this->createUser();
        // An SVG data-URI (carries script risk) must be refused by the byte sniff.
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament", [
                'title' => 'X', 'type' => 'tournament', 'sport' => 'Judo', 'date' => '2020-02-02',
                'evidence' => $svg,
            ])
            ->assertStatus(422);
    }

    public function test_valid_evidence_is_stored_on_the_private_disk(): void
    {
        $member = $this->createUser();
        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $res = $this->actingAs($member)
            ->postJson("/member/{$member->id}/tournament", [
                'title' => 'Y', 'type' => 'tournament', 'sport' => 'Judo', 'date' => '2020-02-02',
                'evidence' => $png,
            ])
            ->assertOk();

        $t = TournamentEvent::where('user_id', $member->id)->first();
        $this->assertNotNull($t->evidence_path);
        $this->assertStringStartsWith('people/', $t->evidence_path);
    }

    // ---- hero tally split ---------------------------------------------------

    public function test_hero_tally_counts_verified_only(): void
    {
        $member = $this->createUser();
        $this->seedClaim($member, null, '1st')->forceFill(['verification_status' => 'verified'])->save();
        $this->seedClaim($member, null, '2nd'); // stays self_reported

        $res = $this->actingAs($member)->get(route('member.show', $member->uuid))->assertOk();

        $this->assertSame(1, $res->viewData('awardCounts')['1st']);
        $this->assertSame(0, $res->viewData('awardCounts')['2nd']);
        $this->assertSame(1, $res->viewData('selfReportedCounts')['2nd']);
    }

    public function test_mobile_medal_boxes_render_even_with_no_medals(): void
    {
        $member = $this->createUser(); // zero tournaments, zero clubs, zero medals

        $html = $this->actingAs($member)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) Mobile Safari/537.36'])
            ->get(route('member.show', $member->uuid))
            ->assertOk()
            ->getContent();

        // The 4-box medal showcase must be present (not hidden behind a >0 gate).
        $this->assertStringContainsString('mp-medal', $html);
        $this->assertStringContainsString(__('member.medal_gold'), $html);
    }

    // ---- vouching -----------------------------------------------------------

    public function test_member_cannot_vouch_for_their_own_claim(): void
    {
        $member = $this->createUser();
        $claim = $this->seedClaim($member, null);

        $this->assertFalse($this->service()->canVouch($member, $claim));

        $this->actingAs($member)
            ->postJson("/attestations/achievement/{$claim->uuid}/vouch", ['relationship' => 'teammate'])
            ->assertStatus(403);
    }

    public function test_family_member_cannot_vouch(): void
    {
        $member = $this->createUser();
        $parent = $this->createUser();
        UserRelationship::create(['guardian_user_id' => $parent->id, 'dependent_user_id' => $member->id, 'relationship_type' => 'child']);
        $claim = $this->seedClaim($member, null);

        $this->assertFalse($this->service()->canVouch($parent, $claim));
        $this->assertSame(0.0, $this->service()->credibilityWeight($parent, $claim));
    }

    public function test_club_staff_vouch_reaches_threshold_and_verifies(): void
    {
        $club = $this->createClub($this->createUser());
        $coach = $this->createUser();
        $this->makeClubAdmin($coach, $club); // admin of the named club → highest weight

        $member = $this->createUser();
        $claim = $this->seedClaim($member, $club);

        $this->actingAs($coach)
            ->postJson("/attestations/achievement/{$claim->uuid}/vouch", ['relationship' => 'coach'])
            ->assertOk();

        // Club-staff weight (3.0) meets the threshold → verified via vouch.
        $this->assertDatabaseHas('tournament_events', [
            'id' => $claim->id, 'verification_status' => 'verified', 'verification_method' => 'vouch',
        ]);
    }

    public function test_single_teammate_vouch_stays_pending(): void
    {
        $member = $this->createUser();
        $teammate = $this->createUser();
        $claim = $this->seedClaim($member, null);

        $this->service()->addVouch($claim, $teammate, 'vouch', 'teammate');

        // One teammate (weight 1.0) is below the 3.0 threshold → not yet verified.
        $this->assertNotSame('verified', $claim->fresh()->verification_status);
        $this->assertDatabaseCount('achievement_vouches', 1);
    }

    public function test_duplicate_vouch_updates_not_duplicates(): void
    {
        $member = $this->createUser();
        $teammate = $this->createUser();
        $claim = $this->seedClaim($member, null);

        $this->service()->addVouch($claim, $teammate, 'vouch', 'teammate');
        $this->service()->addVouch($claim, $teammate, 'vouch', 'coach', 'changed my mind');

        $this->assertDatabaseCount('achievement_vouches', 1);
    }

    public function test_reciprocal_ring_vouch_is_blocked(): void
    {
        $a = $this->createUser();
        $b = $this->createUser();

        // A owns a claim that B has already vouched for.
        $aClaim = $this->seedClaim($a, null);
        AchievementVouch::create(['vouchable_type' => TournamentEvent::class, 'vouchable_id' => $aClaim->id, 'voucher_user_id' => $b->id, 'stance' => 'vouch', 'relationship' => 'teammate']);

        // Now A tries to vouch for B's claim → reciprocal ring, blocked.
        $bClaim = $this->seedClaim($b, null);
        $this->assertFalse($this->service()->canVouch($a, $bClaim));
    }

    // ---- MCP ----------------------------------------------------------------

    private function callTool(string $tool, array $args = []): array
    {
        $text = (string) (new $tool)->handle(new McpRequest($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }

    public function test_mcp_get_member_exposes_verified_medals_only(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $member = $this->createUser();
        $verified = $this->seedClaim($member, null, '1st');
        $verified->forceFill(['verification_status' => 'verified'])->save();
        $this->seedClaim($member, null, '2nd'); // self_reported → must be hidden

        $this->actingAs($admin);
        $result = $this->callTool(GetMemberTool::class, ['member' => $member->uuid]);

        $awards = collect($result['medals'])->pluck('award');
        $this->assertTrue($awards->contains('1st'));
        $this->assertFalse($awards->contains('2nd'));
    }

    public function test_mcp_verify_achievement_confirms_for_club_admin(): void
    {
        $club = $this->createClub($this->createUser());
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);
        $member = $this->createUser();
        $claim = $this->seedClaim($member, $club);

        $this->actingAs($admin);
        $result = $this->callTool(VerifyAchievementTool::class, [
            'uuid' => $claim->uuid, 'decision' => 'confirm',
        ]);

        $this->assertSame('verified', $result['verification_status']);
    }

    public function test_mcp_verify_achievement_denies_non_admin(): void
    {
        $club = $this->createClub($this->createUser());
        $stranger = $this->createUser();
        $member = $this->createUser();
        $claim = $this->seedClaim($member, $club);

        $this->actingAs($stranger);
        $result = $this->callTool(VerifyAchievementTool::class, [
            'uuid' => $claim->uuid, 'decision' => 'confirm',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not authorized', $result['error']);
        $this->assertDatabaseHas('tournament_events', ['id' => $claim->id, 'verification_status' => 'self_reported']);
    }
}
