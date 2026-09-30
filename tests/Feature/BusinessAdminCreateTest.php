<?php

namespace Tests\Feature;

use App\Clubs\Models\Business;
use Tests\TestCase;

/**
 * A super-admin can create a business for an owner from /admin/businesses
 * (POST /admin/businesses → BusinessApprovalController::store) and then add
 * clubs to it through the existing attach endpoint.
 */
class BusinessAdminCreateTest extends TestCase
{
    public function test_super_admin_creates_an_approved_business_and_adds_a_club(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $owner = $this->createUser();
        $ownClub = $this->createClub($owner, ['slug' => 'own-'.uniqid()]);
        $other = $this->createClub($this->createUser(), ['slug' => 'other-'.uniqid(), 'club_name' => 'Other Club']);

        $res = $this->actingAs($admin)->postJson('/admin/businesses', [
            'name' => 'Gulf Chain',
            'owner_user_id' => $owner->id,
        ])->assertOk();

        $this->assertTrue($res->json('success'));
        $this->assertStringContainsString('Gulf Chain', $res->json('html'));

        $business = Business::where('name', 'Gulf Chain')->firstOrFail();
        $this->assertSame(Business::STATUS_APPROVED, $business->status);
        $this->assertSame($owner->id, $business->owner_user_id);
        $this->assertSame($business->id, $ownClub->fresh()->business_id);

        $this->actingAs($admin)->postJson("/admin/businesses/{$business->id}/clubs/attach", [
            'club_id' => $other->id,
        ])->assertOk();
        $this->assertSame($business->id, $other->fresh()->business_id);
    }

    public function test_owner_may_hold_only_one_business(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $owner = $this->createUser();
        Business::create(['owner_user_id' => $owner->id, 'name' => 'First', 'slug' => 'first', 'status' => Business::STATUS_PENDING]);

        $this->actingAs($admin)->postJson('/admin/businesses', [
            'name' => 'Second',
            'owner_user_id' => $owner->id,
        ])->assertStatus(422);

        $this->assertSame(1, Business::where('owner_user_id', $owner->id)->count());
    }

    public function test_non_super_admin_cannot_create_a_business_here(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/admin/businesses', [
            'name' => 'Sneaky',
            'owner_user_id' => $user->id,
        ])->assertForbidden();

        $this->assertFalse(Business::where('name', 'Sneaky')->exists());
    }
}
