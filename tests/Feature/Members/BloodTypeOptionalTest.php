<?php

namespace Tests\Feature\Members;

use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blood type is optional on the forms, but `users.blood_type` is NOT NULL
 * (default 'Unknown'). Two staff screens wrote the missing value as NULL and
 * crashed with a 500 (fixed 2026-10-03): the super-admin's "add member" and a
 * club owner's "create new owner". A blank now lands as 'Unknown', exactly
 * what FamilyService already did.
 */
class BloodTypeOptionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_can_add_a_member_without_a_blood_type(): void
    {
        $this->seedRoles();
        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $this->actingAs($super)->postJson(route('admin.platform.members.store'), [
            'full_name' => 'No Blood Type', 'email' => 'nobt@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'gender' => 'Male', 'nationality' => 'BH',
        ])->assertSuccessful();

        $this->assertSame('Unknown', User::where('email', 'nobt@example.test')->firstOrFail()->blood_type);
    }

    public function test_a_club_owner_can_create_a_new_owner_without_a_blood_type(): void
    {
        $this->seedRoles();
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)->postJson(route('admin.club.create-owner', $club->slug), [
            'full_name' => 'New Owner', 'email' => 'new-owner@example.test', 'gender' => 'Male',
            'birthdate' => '1990-01-01', 'nationality' => 'BH', 'password' => 'secret-password',
        ])->assertOk();

        $this->assertSame('Unknown', User::where('email', 'new-owner@example.test')->firstOrFail()->blood_type);
    }
}
