<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubPackage;
use Tests\TestCase;

class PackageTest extends TestCase
{
    public function test_owner_can_create_package(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/packages", [
                 'name'            => 'Basic Package',
                 'price'           => 50.00,
                 'duration_months' => 1,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_packages', [
            'tenant_id' => $club->id,
            'name'      => 'Basic Package',
            'price'     => 50.00,
        ]);
    }

    public function test_package_requires_name_price_and_duration(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/packages", [])
             ->assertSessionHasErrors(['name', 'price', 'duration_months']);
    }

    public function test_package_duration_must_be_at_least_one_month(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/packages", [
                 'name'            => 'Bad Package',
                 'price'           => 10,
                 'duration_months' => 0,
             ])
             ->assertSessionHasErrors(['duration_months']);
    }

    public function test_package_price_cannot_be_negative(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/packages", [
                 'name'            => 'Bad Package',
                 'price'           => -10,
                 'duration_months' => 1,
             ])
             ->assertSessionHasErrors(['price']);
    }

    public function test_owner_can_update_package(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $package = ClubPackage::create([
            'tenant_id'       => $club->id,
            'name'            => 'Old Name',
            'price'           => 30,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $this->actingAs($owner)
             ->put("/admin/club/{$club->slug}/packages/{$package->id}", [
                 'name'            => 'Updated Name',
                 'price'           => 75,
                 'duration_months' => 3,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_packages', [
            'id'              => $package->id,
            'name'            => 'Updated Name',
            'duration_months' => 3,
        ]);
    }

    public function test_owner_can_delete_package(): void
    {
        $owner   = $this->createUser();
        $club    = $this->createClub($owner);
        $package = ClubPackage::create([
            'tenant_id'       => $club->id,
            'name'            => 'To Delete',
            'price'           => 20,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $this->actingAs($owner)
             ->delete("/admin/club/{$club->slug}/packages/{$package->id}")
             ->assertRedirect();

        $this->assertDatabaseMissing('club_packages', ['id' => $package->id]);
    }

    public function test_cannot_update_package_belonging_to_another_club(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2  = $this->createUser();
        $club2   = $this->createClub($owner2);
        $package = ClubPackage::create([
            'tenant_id'       => $club2->id,
            'name'            => 'Club2 Package',
            'price'           => 100,
            'duration_months' => 1,
            'is_active'       => true,
        ]);

        $this->actingAs($owner1)
             ->put("/admin/club/{$club1->slug}/packages/{$package->id}", [
                 'name'            => 'Hijacked',
                 'price'           => 1,
                 'duration_months' => 1,
             ])
             ->assertNotFound();

        $this->assertDatabaseHas('club_packages', ['id' => $package->id, 'name' => 'Club2 Package']);
    }

    public function test_packages_page_loads_for_owner(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/packages")
             ->assertOk();
    }
}
