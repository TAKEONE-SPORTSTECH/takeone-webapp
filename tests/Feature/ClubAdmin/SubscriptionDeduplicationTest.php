<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class SubscriptionDeduplicationTest extends TestCase
{
    private function makePackage($club): ClubPackage
    {
        return ClubPackage::create([
            'tenant_id'       => $club->id,
            'name'            => 'Test Package',
            'price'           => 50,
            'duration_months' => 1,
            'is_active'       => true,
        ]);
    }

    private function subscribe($club, $user, $package, string $status = 'active'): ClubMemberSubscription
    {
        return ClubMemberSubscription::create([
            'tenant_id'      => $club->id,
            'user_id'        => $user->id,
            'package_id'     => $package->id,
            'type'           => 'regular',
            'status'         => $status,
            'payment_status' => 'paid',
            'amount_paid'    => $package->price,
            'amount_due'     => 0,
            'start_date'     => now(),
            'end_date'       => now()->addMonth(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Model: active_key is set / cleared automatically
    // -------------------------------------------------------------------------

    public function test_active_key_is_set_for_active_subscription(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $sub = $this->subscribe($club, $member, $package, 'active');

        $this->assertEquals("{$club->id}:{$member->id}:{$package->id}", $sub->active_key);
    }

    public function test_active_key_is_set_for_pending_subscription(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $sub = $this->subscribe($club, $member, $package, 'pending');

        $this->assertNotNull($sub->active_key);
    }

    public function test_active_key_is_null_for_expired_subscription(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $sub = $this->subscribe($club, $member, $package, 'expired');

        $this->assertNull($sub->active_key);
    }

    public function test_active_key_clears_when_subscription_is_cancelled(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $sub = $this->subscribe($club, $member, $package, 'active');
        $this->assertNotNull($sub->active_key);

        $sub->update(['status' => 'cancelled']);

        $this->assertNull($sub->fresh()->active_key);
    }

    // -------------------------------------------------------------------------
    // DB constraint: blocks duplicate active subscriptions
    // -------------------------------------------------------------------------

    public function test_duplicate_active_subscription_is_rejected_at_db_level(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $this->subscribe($club, $member, $package, 'active');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->subscribe($club, $member, $package, 'active');
    }

    public function test_duplicate_pending_subscription_is_rejected_at_db_level(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $this->subscribe($club, $member, $package, 'pending');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->subscribe($club, $member, $package, 'pending');
    }

    // -------------------------------------------------------------------------
    // Renewals: expired subscription does NOT block a new active one
    // -------------------------------------------------------------------------

    public function test_renewal_is_allowed_after_subscription_expires(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $first = $this->subscribe($club, $member, $package, 'active');
        $first->update(['status' => 'expired']);

        // Should not throw
        $renewal = $this->subscribe($club, $member, $package, 'active');

        $this->assertNotNull($renewal->id);
        $this->assertEquals("{$club->id}:{$member->id}:{$package->id}", $renewal->active_key);
    }

    public function test_multiple_expired_records_are_allowed(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        $s1 = $this->subscribe($club, $member, $package, 'expired');
        $s2 = $this->subscribe($club, $member, $package, 'expired');
        $s3 = $this->subscribe($club, $member, $package, 'cancelled');

        // All three should exist with NULL active_key
        $this->assertDatabaseCount('club_member_subscriptions', 3);
        $this->assertNull($s1->active_key);
        $this->assertNull($s2->active_key);
        $this->assertNull($s3->active_key);
    }

    // -------------------------------------------------------------------------
    // Application layer: joinClub endpoint rejects duplicates early
    // -------------------------------------------------------------------------

    public function test_join_club_endpoint_rejects_duplicate_active_subscription(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $member  = $this->createUser();
        $package = $this->makePackage($club);

        // Give the member an active subscription already
        $this->subscribe($club, $member, $package, 'active');

        $this->actingAs($member)
             ->postJson('/clubs/join', [
                 'club_id'     => $club->id,
                 'registrants' => [[
                     'type'         => 'self',
                     'name'         => $member->full_name,
                     'user_id'      => $member->id,
                     'package_id'   => $package->id,
                 ]],
                 'pay_later' => true,
             ])
             ->assertStatus(422)
             ->assertJsonFragment(['success' => false]);
    }
}
