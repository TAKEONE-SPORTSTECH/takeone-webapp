<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\Membership;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Members page
    // -------------------------------------------------------------------------

    public function test_members_page_loads_for_owner(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/members")
             ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Add members
    // -------------------------------------------------------------------------

    public function test_owner_can_add_existing_user_as_member(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $target = $this->createUser();

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members", [
                 'user_ids' => [$target->id],
             ])
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseHas('memberships', [
            'tenant_id' => $club->id,
            'user_id'   => $target->id,
            'status'    => 'active',
        ]);
    }

    public function test_adding_multiple_users_creates_multiple_memberships(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $user1  = $this->createUser();
        $user2  = $this->createUser();

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members", [
                 'user_ids' => [$user1->id, $user2->id],
             ])
             ->assertOk()
             ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('memberships', ['tenant_id' => $club->id, 'user_id' => $user1->id]);
        $this->assertDatabaseHas('memberships', ['tenant_id' => $club->id, 'user_id' => $user2->id]);
    }

    public function test_adding_already_member_does_not_duplicate(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $member = $this->createUser();

        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members", [
                 'user_ids' => [$member->id],
             ])
             ->assertOk()
             ->assertJsonPath('count', 0);

        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_adding_member_requires_user_ids(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/members", [])
             ->assertSessionHasErrors(['user_ids']);
    }

    public function test_adding_nonexistent_user_fails_validation(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/members", [
                 'user_ids' => [99999],
             ])
             ->assertSessionHasErrors(['user_ids.0']);
    }

    public function test_unauthorized_user_cannot_add_members(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $target = $this->createUser();
        $rando  = $this->createUser();

        $this->actingAs($rando)
             ->postJson("/admin/club/{$club->slug}/members", [
                 'user_ids' => [$target->id],
             ])
             ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Approve payment
    // -------------------------------------------------------------------------

    public function test_owner_can_approve_pending_subscription_payment(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = ClubPackage::create([
            'tenant_id'       => $club->id,
            'name'            => 'Test Package',
            'price'           => 100,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $subscription = ClubMemberSubscription::create([
            'tenant_id'      => $club->id,
            'user_id'        => $member->id,
            'package_id'     => $package->id,
            'type'           => 'regular',
            'status'         => 'active',
            'payment_status' => 'pending',
            'amount_paid'    => 0,
            'amount_due'     => 100,
            'start_date'     => now(),
            'end_date'       => now()->addMonth(),
        ]);

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/subscriptions/{$subscription->id}/approve-payment")
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id'             => $subscription->id,
            'payment_status' => 'paid',
            'amount_due'     => 0,
        ]);
    }

    public function test_cannot_approve_payment_for_another_clubs_subscription(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2 = $this->createUser();
        $club2  = $this->createClub($owner2);

        $member  = $this->createUser();
        $package = ClubPackage::create([
            'tenant_id'       => $club2->id,
            'name'            => 'Club2 Package',
            'price'           => 80,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $subscription = ClubMemberSubscription::create([
            'tenant_id'      => $club2->id,
            'user_id'        => $member->id,
            'package_id'     => $package->id,
            'type'           => 'regular',
            'status'         => 'active',
            'payment_status' => 'pending',
            'amount_paid'    => 0,
            'amount_due'     => 80,
            'start_date'     => now(),
            'end_date'       => now()->addMonth(),
        ]);

        $this->actingAs($owner1)
             ->postJson("/admin/club/{$club1->slug}/subscriptions/{$subscription->id}/approve-payment")
             ->assertForbidden();
    }
}
