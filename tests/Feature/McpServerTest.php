<?php

namespace Tests\Feature;

use App\Mcp\Tools\ClubFinancialsTool;
use App\Mcp\Tools\ClubStaffTool;
use App\Mcp\Tools\EnrollMembersTool;
use App\Mcp\Tools\ListClubsTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\SearchPeopleTool;
use App\Mcp\Tools\WhoAmITool;
use App\Models\ClubInstructor;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\Membership;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * The MCP tools run AS the acting user and must enforce the same tenant scope
 * and authorization the web app does. These tests exercise that directly.
 */
class McpServerTest extends TestCase
{
    /** Run a tool and decode its JSON text content. */
    private function callTool(string $tool, array $args = []): array
    {
        $text = (string) (new $tool)->handle(new Request($args))->content();
        $decoded = json_decode($text, true);

        // Error tools return a plain string, not JSON — wrap it for asserting.
        return is_array($decoded) ? $decoded : ['error' => $text];
    }

    public function test_unauthenticated_call_is_rejected(): void
    {
        config(['takeone-mcp.stdio_user_id' => null]);

        $result = $this->callTool(WhoAmITool::class);

        $this->assertStringContainsString('Not authenticated', $result['error']);
    }

    public function test_who_am_i_reports_identity_and_roles(): void
    {
        $user = $this->createUser(['full_name' => 'Mia Coach']);
        $this->makeSuperAdmin($user);
        $this->actingAs($user);

        $result = $this->callTool(WhoAmITool::class);

        $this->assertSame($user->id, $result['id']);
        $this->assertTrue($result['is_super_admin']);
    }

    public function test_list_clubs_is_scoped_to_the_users_clubs(): void
    {
        $ownerA = $this->createUser();
        $ownerB = $this->createUser();
        $clubA = $this->createClub($ownerA, ['club_name' => 'Alpha Club', 'slug' => 'alpha-club']);
        $clubB = $this->createClub($ownerB, ['club_name' => 'Beta Club', 'slug' => 'beta-club']);

        // A plain member of only clubA should not see clubB.
        $member = $this->createUser();
        Membership::create(['tenant_id' => $clubA->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $slugs = collect($this->callTool(ListClubsTool::class)['clubs'])->pluck('slug');

        $this->assertTrue($slugs->contains('alpha-club'));
        $this->assertFalse($slugs->contains('beta-club'));
    }

    public function test_financials_require_admin_access(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'money-club']);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(ClubFinancialsTool::class, ['club' => 'money-club']);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_club_staff_lists_scoped_staff_for_admins(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'staff-club']);
        $this->makeClubAdmin($owner, $club);

        $secretaryUser = $this->createUser(['full_name' => 'Sam Secretary']);
        ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $secretaryUser->id,
            'role' => 'Front Desk',
            'staff_type' => 'secretary',
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => 300,
            'wage_period' => 'monthly',
        ]);

        $this->actingAs($owner);
        $result = $this->callTool(ClubStaffTool::class, ['club' => 'staff-club']);

        $this->assertSame(1, $result['count']);
        $this->assertSame('secretary', $result['staff'][0]['staff_type']);
        $this->assertEquals(300.0, $result['staff'][0]['monthly_wage_cost']);
    }

    public function test_club_staff_denies_non_admin(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'staff-club-2']);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(ClubStaffTool::class, ['club' => 'staff-club-2']);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_admin_can_record_a_transaction(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'txn-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $result = $this->callTool(RecordTransactionTool::class, [
            'club' => 'txn-club',
            'type' => 'income',
            'amount' => 42.0,
            'category' => 'test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'test',
        ]);
    }

    public function test_writes_are_blocked_when_disabled(): void
    {
        config(['takeone-mcp.allow_writes' => false]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'ro-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $result = $this->callTool(RecordTransactionTool::class, [
            'club' => 'ro-club',
            'type' => 'income',
            'amount' => 10.0,
        ]);

        $this->assertStringContainsString('Write operations are disabled', $result['error']);
        $this->assertSame(0, ClubTransaction::count());
    }

    public function test_admin_can_batch_enroll_active_members_into_a_package(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'batch-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Adult Membership',
            'price' => 50,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $memberA = $this->createUser();
        $memberB = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $memberA->id, 'status' => 'active']);
        Membership::create(['tenant_id' => $club->id, 'user_id' => $memberB->id, 'status' => 'active']);

        // Not a member of this club — should be skipped.
        $outsider = $this->createUser();

        $result = $this->callTool(EnrollMembersTool::class, [
            'club' => 'batch-club',
            'member_ids' => [$memberA->id, $memberB->id, $outsider->id],
            'package_id' => $package->id,
            'start_date' => '2026-01-01',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['enrolled_count']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($outsider->id, $result['skipped'][0]['user_id']);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'tenant_id' => $club->id,
            'user_id' => $memberA->id,
            'package_id' => $package->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_due' => 0,
        ]);
    }

    public function test_batch_enroll_requires_admin_access(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'batch-ro-club']);

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Adult Membership',
            'price' => 50,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(EnrollMembersTool::class, [
            'club' => 'batch-ro-club',
            'member_ids' => [$member->id],
            'package_id' => $package->id,
        ]);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_search_people_is_scoped_to_confirmed_clubmates(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'people-club']);

        $me = $this->createUser();
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $me->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        $confirmedMate = $this->createUser(['full_name' => 'Confirmed Mate']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $confirmedMate->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        // Same club, but membership never confirmed by the owner — must not surface.
        $pendingMate = $this->createUser(['full_name' => 'Pending Mate']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $pendingMate->id, 'type' => 'regular',
            'status' => 'pending', 'payment_status' => 'pending_approval',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        // Discoverable, confirmed member of a DIFFERENT club — must not surface.
        $otherClubOwner = $this->createUser();
        $otherClub = $this->createClub($otherClubOwner, ['slug' => 'other-people-club']);
        $stranger = $this->createUser(['full_name' => 'Stranger Person']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $otherClub->id, 'user_id' => $stranger->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        $this->actingAs($me);
        $names = collect($this->callTool(SearchPeopleTool::class, ['query' => 'Mate'])['people'])->pluck('name');

        $this->assertTrue($names->contains('Confirmed Mate'));
        $this->assertFalse($names->contains('Pending Mate'));
        $this->assertFalse($names->contains('Stranger Person'));
    }

    public function test_search_people_returns_empty_without_a_confirmed_club(): void
    {
        $me = $this->createUser();
        $this->actingAs($me);

        $result = $this->callTool(SearchPeopleTool::class, ['query' => 'anyone']);

        $this->assertSame(0, $result['count']);
        $this->assertEmpty($result['people']);
    }
}
