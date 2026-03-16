<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubPackage;
use App\Models\User;
use Tests\TestCase;

class WalkInRegistrationTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'guardian' => [
                'name'        => 'Ahmad Al-Rashidi',
                'email'       => 'ahmad.walkin@example.com',
                'password'    => 'secret123',
                'phone'       => '39001234',
                'dob'         => '1985-06-15',
                'gender'      => 'm',
                'nationality' => 'BH',
                'countryCode' => '+973',
            ],
            'people' => [
                [
                    'name'               => 'Ahmad Al-Rashidi',
                    'dob'                => '1985-06-15',
                    'gender'             => 'm',
                    'type'               => 'guardian',
                    'selectedPackageIds' => [],
                ],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Success cases
    // -------------------------------------------------------------------------

    public function test_walk_in_creates_guardian_user_and_membership(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $usersBefore = User::count();

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $this->validPayload())
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseCount('users', $usersBefore + 1);
        $this->assertDatabaseHas('memberships', ['tenant_id' => $club->id]);
    }

    public function test_walk_in_with_child_creates_user_relationship(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $payload = $this->validPayload([
            'people' => [
                [
                    'name'               => 'Ahmad Al-Rashidi',
                    'dob'                => '1985-06-15',
                    'gender'             => 'm',
                    'type'               => 'guardian',
                    'selectedPackageIds' => [],
                ],
                [
                    'name'               => 'Little Ahmad',
                    'dob'                => '2015-03-10',
                    'gender'             => 'm',
                    'type'               => 'child',
                    'nationality'        => 'BH',
                    'selectedPackageIds' => [],
                ],
            ],
        ]);

        $usersBefore = User::count();

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload)
             ->assertOk()
             ->assertJson(['success' => true]);

        // Guardian + child = 2 new users
        $this->assertDatabaseCount('users', $usersBefore + 2);
        $this->assertDatabaseHas('user_relationships', ['relationship_type' => 'child']);
    }

    public function test_walk_in_with_package_creates_subscription_and_transaction(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $package = ClubPackage::create([
            'tenant_id'       => $club->id,
            'name'            => 'Monthly',
            'price'           => 50,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $payload = $this->validPayload([
            'people' => [
                [
                    'name'               => 'Ahmad Al-Rashidi',
                    'dob'                => '1985-06-15',
                    'gender'             => 'm',
                    'type'               => 'guardian',
                    'selectedPackageIds' => [$package->id],
                ],
            ],
        ]);

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload)
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'tenant_id'      => $club->id,
            'package_id'     => $package->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'type'      => 'income',
            'category'  => 'subscription',
            'amount'    => 50,
        ]);
    }

    public function test_walk_in_ignores_package_from_another_club(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $owner2 = $this->createUser();
        $club2  = $this->createClub($owner2);

        $foreignPackage = ClubPackage::create([
            'tenant_id'       => $club2->id,
            'name'            => 'Foreign',
            'price'           => 999,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $payload = $this->validPayload([
            'people' => [
                [
                    'name'               => 'Ahmad Al-Rashidi',
                    'dob'                => '1985-06-15',
                    'gender'             => 'm',
                    'type'               => 'guardian',
                    'selectedPackageIds' => [$foreignPackage->id],
                ],
            ],
        ]);

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload)
             ->assertOk();

        // No subscription should be created for the foreign package
        $this->assertDatabaseMissing('club_member_subscriptions', [
            'package_id' => $foreignPackage->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_walk_in_requires_guardian_fields(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", [
                 'guardian' => [],
                 'people'   => [],
             ])
             ->assertUnprocessable()
             ->assertJsonValidationErrors([
                 'guardian.name',
                 'guardian.email',
                 'guardian.password',
                 'guardian.phone',
                 'guardian.dob',
                 'guardian.gender',
                 'guardian.nationality',
                 'people',
             ]);
    }

    public function test_walk_in_email_must_be_unique(): void
    {
        $owner      = $this->createUser();
        $club       = $this->createClub($owner);
        $existing   = $this->createUser(['email' => 'already@taken.com']);

        $payload = $this->validPayload();
        $payload['guardian']['email'] = 'already@taken.com';

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload)
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['guardian.email']);
    }

    public function test_walk_in_guardian_dob_must_be_in_past(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);

        $payload = $this->validPayload();
        $payload['guardian']['dob'] = '2099-01-01';

        $this->actingAs($owner)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload)
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['guardian.dob']);
    }

    public function test_unauthorized_user_cannot_perform_walk_in(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);
        $rando = $this->createUser();

        $this->actingAs($rando)
             ->postJson("/admin/club/{$club->slug}/members/walk-in", $this->validPayload())
             ->assertForbidden();
    }
}
