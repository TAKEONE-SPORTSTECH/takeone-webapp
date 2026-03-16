<?php

namespace Tests\Feature\ClubAdmin;

use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Unauthenticated
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_club_admin_dashboard(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->get("/admin/club/{$club->slug}/dashboard")
             ->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // Unverified email
    // -------------------------------------------------------------------------

    public function test_unverified_user_cannot_access_club_admin_dashboard(): void
    {
        $owner = $this->createUnverifiedUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/dashboard")
             ->assertRedirect('/email/verify');
    }

    // -------------------------------------------------------------------------
    // Club owner
    // -------------------------------------------------------------------------

    public function test_club_owner_can_access_dashboard(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/dashboard")
             ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Club admin role
    // -------------------------------------------------------------------------

    public function test_club_admin_role_can_access_dashboard(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club);

        $this->actingAs($admin)
             ->get("/admin/club/{$club->slug}/dashboard")
             ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Super admin
    // -------------------------------------------------------------------------

    public function test_super_admin_can_access_any_club_dashboard(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);
        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $this->actingAs($super)
             ->get("/admin/club/{$club->slug}/dashboard")
             ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Unauthorized regular user
    // -------------------------------------------------------------------------

    public function test_random_user_cannot_access_another_clubs_dashboard(): void
    {
        $owner     = $this->createUser();
        $club      = $this->createClub($owner);
        $randoUser = $this->createUser();

        $this->actingAs($randoUser)
             ->get("/admin/club/{$club->slug}/dashboard")
             ->assertForbidden();
    }

    public function test_club_admin_of_one_club_cannot_access_another_clubs_dashboard(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2 = $this->createUser();
        $club2  = $this->createClub($owner2);

        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $club1);

        // admin of club1 should not access club2
        $this->actingAs($admin)
             ->get("/admin/club/{$club2->slug}/dashboard")
             ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Key sub-pages follow the same rules (spot check)
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_club_financials(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->get("/admin/club/{$club->slug}/financials")
             ->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_club_members(): void
    {
        $owner = $this->createUnverifiedUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/members")
             ->assertRedirect('/email/verify');
    }
}
