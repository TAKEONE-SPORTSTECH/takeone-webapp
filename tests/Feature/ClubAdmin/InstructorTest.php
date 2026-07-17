<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubInstructor;
use App\Models\User;
use Tests\TestCase;

class InstructorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Add new instructor (creates a brand-new platform user)
    // -------------------------------------------------------------------------

    public function test_owner_can_add_new_instructor(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $usersBefore = User::count();

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'new',
                'name' => 'Ali Hassan',
                'email' => 'ali.hassan@example.com',
                'password' => 'secret123',
                'phone' => '39001234',
                'country_code' => '+973',
                'gender' => 'Male',
                'birthdate' => '1990-05-15',
                'nationality' => 'BH',
                'specialty' => 'Muay Thai',
                'experience' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('users', $usersBefore + 1);
        $this->assertDatabaseHas('club_instructors', [
            'tenant_id' => $club->id,
            'role' => 'Muay Thai',
        ]);
    }

    public function test_adding_new_instructor_creates_club_instructor_record(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'new',
                'name' => 'Sara Ahmed',
                'email' => 'sara@example.com',
                'password' => 'secret123',
                'phone' => '39005678',
                'country_code' => '+973',
                'gender' => 'Female',
                'birthdate' => '1992-03-20',
                'nationality' => 'BH',
                'specialty' => 'Boxing',
            ]);

        $instructor = ClubInstructor::where('tenant_id', $club->id)->latest()->first();
        $this->assertNotNull($instructor);

        $user = User::find($instructor->user_id);
        $this->assertEquals('Sara Ahmed', $user->full_name);
    }

    // -------------------------------------------------------------------------
    // Add existing member as instructor (no new user created)
    // -------------------------------------------------------------------------

    public function test_owner_can_add_existing_member_as_instructor(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $existingMember = $this->createUser(['full_name' => 'Existing Person']);

        $usersBefore = User::count();

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => $existingMember->id,
                'specialty_existing' => 'Judo',
                'experience_existing' => 3,
            ])
            ->assertRedirect();

        // No new user should be created
        $this->assertDatabaseCount('users', $usersBefore);

        // But a ClubInstructor record should link the existing user
        $this->assertDatabaseHas('club_instructors', [
            'tenant_id' => $club->id,
            'user_id' => $existingMember->id,
            'role' => 'Judo',
        ]);
    }

    public function test_adding_existing_instructor_updates_user_profile_fields(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $existingMember = $this->createUser();

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => $existingMember->id,
                'specialty_existing' => 'Boxing',
                'experience_existing' => 7,
                'bio_existing' => 'Champion boxer with 7 years coaching.',
            ]);

        $existingMember->refresh();
        $this->assertEquals(7, $existingMember->experience_years);
        $this->assertEquals('Champion boxer with 7 years coaching.', $existingMember->bio);
    }

    // -------------------------------------------------------------------------
    // Delete instructor (removes ClubInstructor, NOT the user)
    // -------------------------------------------------------------------------

    public function test_owner_can_remove_instructor(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $userMember = $this->createUser();

        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $userMember->id,
            'role' => 'Trainer',
        ]);

        $this->actingAs($owner)
            ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        // Removal is a soft-terminate, not a delete: the record (and any wage
        // history/settlement it's linked to) stays, just deactivated.
        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id, 'is_active' => false]);

        // The underlying user must still exist
        $this->assertDatabaseHas('users', ['id' => $userMember->id]);
    }

    public function test_cannot_remove_instructor_belonging_to_another_club(): void
    {
        $owner1 = $this->createUser();
        $club1 = $this->createClub($owner1);

        $owner2 = $this->createUser();
        $club2 = $this->createClub($owner2);

        $user = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club2->id,
            'user_id' => $user->id,
            'role' => 'Trainer',
        ]);

        $this->actingAs($owner1)
            ->deleteJson("/admin/club/{$club1->slug}/instructors/{$instructor->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id]);
    }

    // -------------------------------------------------------------------------
    // Only instructors can be assigned to package classes/schedules
    // -------------------------------------------------------------------------

    private function createPackageSlot($club): int
    {
        $packageId = \Illuminate\Support\Facades\DB::table('club_packages')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Test Package', 'type' => 'single',
            'gender' => 'mixed', 'price' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = \Illuminate\Support\Facades\DB::table('club_activities')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Test Activity', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return \Illuminate\Support\Facades\DB::table('club_package_activities')->insertGetId([
            'package_id' => $packageId, 'activity_id' => $activityId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_non_instructor_staff_cannot_be_assigned_a_package_slot(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $slotId = $this->createPackageSlot($club);

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => $staffUser->id,
                'specialty_existing' => 'Front Desk',
                'staff_type' => 'secretary',
                'package_slots' => [$slotId],
            ])
            ->assertRedirect();

        $instructor = ClubInstructor::where('tenant_id', $club->id)->where('user_id', $staffUser->id)->firstOrFail();

        $this->assertDatabaseHas('club_package_activities', ['id' => $slotId, 'instructor_id' => null]);
        $this->assertSame(0, $instructor->activities()->count());
    }

    public function test_converting_instructor_to_other_staff_type_clears_their_package_slots(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $trainerUser = $this->createUser();
        $slotId = $this->createPackageSlot($club);

        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $trainerUser->id, 'role' => 'Trainer', 'staff_type' => 'instructor',
        ]);
        \Illuminate\Support\Facades\DB::table('club_package_activities')->where('id', $slotId)->update(['instructor_id' => $instructor->id]);

        $this->actingAs($owner)
            ->put("/admin/club/{$club->slug}/instructors/{$instructor->id}", [
                'role' => 'Front Desk',
                'staff_type' => 'operator',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('club_package_activities', ['id' => $slotId, 'instructor_id' => null]);
    }

    // -------------------------------------------------------------------------
    // Instructors page renders the "Manage Access" entry point + underlying
    // role-assignment endpoint it uses (App\Http\Controllers\Admin\ClubRoleController)
    // -------------------------------------------------------------------------

    public function test_instructors_page_renders_manage_access_action(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->get("/admin/club/{$club->slug}/instructors")
            ->assertOk()
            ->assertSee('Manage Access');
    }

    public function test_mobile_instructors_page_renders_manage_access_action(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'])
            ->get("/admin/club/{$club->slug}/instructors")
            ->assertOk()
            ->assertSee('Manage Access');
    }

    public function test_manage_access_can_assign_a_standard_role_to_staff(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->createRole('moderator');
        $staffUser = $this->createUser();
        ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $staffUser->id, 'role' => 'Front Desk', 'staff_type' => 'secretary',
        ]);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/roles/member/permissions", [
                'user_id' => $staffUser->id,
                'role' => 'moderator',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue($staffUser->fresh()->hasRole('moderator', $club->id));
    }

    // -------------------------------------------------------------------------
    // Adding instructor requires valid existing user ID
    // -------------------------------------------------------------------------

    public function test_adding_nonexistent_user_as_instructor_fails_validation(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => 99999,
            ])
            ->assertSessionHasErrors(['selected_member_id']);
    }
}
