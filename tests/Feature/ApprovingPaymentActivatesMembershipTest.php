<?php

namespace Tests\Feature;

use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Tests\TestCase;

/**
 * The club-page (wizard) registration path creates the membership 'inactive' on
 * purpose — its own comment says "the club activates it on approval" — but that
 * activation was never implemented. Result: a member registered and paid through
 * the club page, the admin approved the payment, and they still never showed up
 * in the club's members list, because every members query filters on
 * memberships.status = 'active'.
 */
class ApprovingPaymentActivatesMembershipTest extends TestCase
{
    private function pendingRegistration(Tenant $club, User $member): ClubMemberSubscription
    {
        // Exactly what WizardRegistrationController::createSubscriptions() writes.
        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $member->id],
            ['status' => 'inactive']
        );

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Monthly',
            'price' => 45,
            'duration_days' => 30,
        ]);

        return ClubMemberSubscription::create([
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'package_id' => $package->id,
            'type' => 'regular',
            'status' => 'pending',
            'payment_status' => 'pending_approval',
            'amount_due' => 45,
            'amount_paid' => 0,
            'start_date' => now()->toDateString(),
            'is_test' => $club->is_test_mode,
        ]);
    }

    public function test_approving_the_payment_activates_the_membership(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $subscription = $this->pendingRegistration($club, $member);

        $this->assertSame('inactive', Membership::where('user_id', $member->id)->value('status'));

        app(SubscriptionService::class)->approvePayment($subscription, null, $owner);

        $this->assertSame('active', Membership::where('user_id', $member->id)->value('status'));
    }

    /**
     * The regression as the user actually experienced it: paid, but not in the list.
     */
    public function test_an_approved_member_appears_in_the_club_members_list(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser(['full_name' => 'Anniebale Liwag']);
        $subscription = $this->pendingRegistration($club, $member);

        // Before approval they are correctly NOT counted as an active member.
        $before = $this->actingAs($owner)->get("/admin/club/{$club->slug}/members")->assertOk();
        $this->assertSame(0, $before->viewData('activeCount'));

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/subscriptions/{$subscription->id}/approve-payment")
            ->assertOk();

        $after = $this->actingAs($owner)->get("/admin/club/{$club->slug}/members")->assertOk();
        $this->assertSame(1, $after->viewData('activeCount'));
    }

    public function test_approval_does_not_disturb_an_already_active_membership(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $member = $this->createUser();
        $subscription = $this->pendingRegistration($club, $member);
        Membership::where('user_id', $member->id)->update(['status' => 'active']);

        app(SubscriptionService::class)->approvePayment($subscription, null, $owner);

        $this->assertSame(1, Membership::where('tenant_id', $club->id)->where('user_id', $member->id)->count());
        $this->assertSame('active', Membership::where('user_id', $member->id)->value('status'));
    }

    /**
     * Approval must not reach across tenants: only THIS club's membership row is
     * touched, never the same user's membership of another club.
     */
    public function test_approval_only_activates_the_membership_for_that_club(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $otherOwner = $this->createUser();
        $otherClub = $this->createClub($otherOwner);

        $member = $this->createUser();
        $subscription = $this->pendingRegistration($club, $member);
        Membership::firstOrCreate(
            ['tenant_id' => $otherClub->id, 'user_id' => $member->id],
            ['status' => 'inactive']
        );

        app(SubscriptionService::class)->approvePayment($subscription, null, $owner);

        $this->assertSame('active', Membership::where('tenant_id', $club->id)->where('user_id', $member->id)->value('status'));
        $this->assertSame('inactive', Membership::where('tenant_id', $otherClub->id)->where('user_id', $member->id)->value('status'));
    }
}
