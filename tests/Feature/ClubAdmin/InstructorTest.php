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
        $club  = $this->createClub($owner);

        $usersBefore = User::count();

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/instructors", [
                 'creation_type' => 'new',
                 'name'          => 'Ali Hassan',
                 'email'         => 'ali.hassan@example.com',
                 'password'      => 'secret123',
                 'phone'         => '39001234',
                 'country_code'  => '+973',
                 'gender'        => 'm',
                 'birthdate'     => '1990-05-15',
                 'nationality'   => 'BH',
                 'specialty'     => 'Muay Thai',
                 'experience'    => 5,
             ])
             ->assertRedirect();

        $this->assertDatabaseCount('users', $usersBefore + 1);
        $this->assertDatabaseHas('club_instructors', [
            'tenant_id' => $club->id,
            'role'      => 'Muay Thai',
        ]);
    }

    public function test_adding_new_instructor_creates_club_instructor_record(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/instructors", [
                 'creation_type' => 'new',
                 'name'          => 'Sara Ahmed',
                 'email'         => 'sara@example.com',
                 'password'      => 'secret123',
                 'phone'         => '39005678',
                 'country_code'  => '+973',
                 'gender'        => 'f',
                 'birthdate'     => '1992-03-20',
                 'nationality'   => 'BH',
                 'specialty'     => 'Boxing',
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
        $owner          = $this->createUser();
        $club           = $this->createClub($owner);
        $existingMember = $this->createUser(['full_name' => 'Existing Person']);

        $usersBefore = User::count();

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/instructors", [
                 'creation_type'      => 'existing',
                 'selected_member_id' => $existingMember->id,
                 'specialty_existing' => 'Judo',
                 'experience_existing'=> 3,
             ])
             ->assertRedirect();

        // No new user should be created
        $this->assertDatabaseCount('users', $usersBefore);

        // But a ClubInstructor record should link the existing user
        $this->assertDatabaseHas('club_instructors', [
            'tenant_id' => $club->id,
            'user_id'   => $existingMember->id,
            'role'      => 'Judo',
        ]);
    }

    public function test_adding_existing_instructor_updates_user_profile_fields(): void
    {
        $owner          = $this->createUser();
        $club           = $this->createClub($owner);
        $existingMember = $this->createUser();

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/instructors", [
                 'creation_type'       => 'existing',
                 'selected_member_id'  => $existingMember->id,
                 'specialty_existing'  => 'Boxing',
                 'experience_existing' => 7,
                 'bio_existing'        => 'Champion boxer with 7 years coaching.',
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
        $owner      = $this->createUser();
        $club       = $this->createClub($owner);
        $userMember = $this->createUser();

        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id'   => $userMember->id,
            'role'      => 'Trainer',
        ]);

        $this->actingAs($owner)
             ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('club_instructors', ['id' => $instructor->id]);

        // The underlying user must still exist
        $this->assertDatabaseHas('users', ['id' => $userMember->id]);
    }

    public function test_cannot_remove_instructor_belonging_to_another_club(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2 = $this->createUser();
        $club2  = $this->createClub($owner2);

        $user       = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club2->id,
            'user_id'   => $user->id,
            'role'      => 'Trainer',
        ]);

        $this->actingAs($owner1)
             ->deleteJson("/admin/club/{$club1->slug}/instructors/{$instructor->id}")
             ->assertForbidden();

        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id]);
    }

    // -------------------------------------------------------------------------
    // Adding instructor requires valid existing user ID
    // -------------------------------------------------------------------------

    public function test_adding_nonexistent_user_as_instructor_fails_validation(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/instructors", [
                 'creation_type'      => 'existing',
                 'selected_member_id' => 99999,
             ])
             ->assertSessionHasErrors(['selected_member_id']);
    }
}
