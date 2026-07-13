<?php

namespace Tests\Feature;

use App\Mcp\Tools\ClubFinancialsTool;
use App\Mcp\Tools\EnrollMembersTool;
use App\Mcp\Tools\ListClubsTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\WhoAmITool;
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
}
