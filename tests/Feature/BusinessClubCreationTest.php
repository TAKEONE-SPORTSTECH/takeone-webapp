<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * A business (chain) owner can create a club from their dashboard
 * (POST /business/clubs → BusinessClubController::store). The acting user is
 * always forced as owner_user_id (StoreClubRequest::prepareForValidation),
 * which auto-links the new club to their chain via Tenant::booted().
 */
class BusinessClubCreationTest extends TestCase
{
    private function createApprovedBusiness($owner): Business
    {
        return Business::create([
            'owner_user_id' => $owner->id,
            'name' => 'Test Chain',
            'slug' => 'test-chain-'.uniqid(),
            'status' => Business::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function test_business_owner_can_create_a_club_with_minimal_fields(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $this->createRole('club-admin');

        $response = $this->actingAs($owner)
            ->postJson('/business/clubs', [
                'club_name' => 'Downtown Branch',
                'slug' => 'downtown-branch-'.uniqid(),
            ])
            ->assertOk();

        $this->assertTrue($response->json('success'));

        $club = Tenant::where('club_name', 'Downtown Branch')->firstOrFail();
        $this->assertSame($owner->id, $club->owner_user_id);
        $this->assertSame($owner->ownedBusiness->id, $club->business_id);
        $this->assertTrue($owner->hasRole('club-admin', $club->id));
    }

    public function test_business_owner_cannot_spoof_a_different_club_owner(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);
        $otherUser = $this->createUser();
        $this->createRole('club-admin');

        $this->actingAs($owner)
            ->postJson('/business/clubs', [
                'club_name' => 'Spoof Test Club',
                'slug' => 'spoof-test-club-'.uniqid(),
                'owner_user_id' => $otherUser->id,
            ])
            ->assertOk();

        $club = Tenant::where('club_name', 'Spoof Test Club')->firstOrFail();
        $this->assertSame($owner->id, $club->owner_user_id);
        $this->assertNotSame($otherUser->id, $club->owner_user_id);
    }

    public function test_user_without_an_approved_business_cannot_create_a_club(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/business/clubs', [
                'club_name' => 'No Business Club',
                'slug' => 'no-business-club-'.uniqid(),
            ])
            ->assertRedirect(route('business.setup'));

        $this->assertDatabaseMissing('tenants', ['club_name' => 'No Business Club']);
    }

    public function test_super_admin_platform_club_creation_still_works_after_service_extraction(): void
    {
        $superAdmin = $this->createUser();
        $this->makeSuperAdmin($superAdmin);
        $targetOwner = $this->createUser();
        $this->createRole('club-admin');

        $this->actingAs($superAdmin)
            ->postJson('/admin/clubs', [
                'club_name' => 'Platform Created Club',
                'slug' => 'platform-created-club-'.uniqid(),
                'owner_user_id' => $targetOwner->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $club = Tenant::where('club_name', 'Platform Created Club')->firstOrFail();
        $this->assertSame($targetOwner->id, $club->owner_user_id);
        $this->assertTrue($targetOwner->hasRole('club-admin', $club->id));
    }
}
