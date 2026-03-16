<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubActivity;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    public function test_owner_can_create_activity(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/activities", [
                 'name'             => 'Muay Thai',
                 'description'      => 'Traditional Thai martial art.',
                 'duration_minutes' => 60,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_activities', [
            'tenant_id' => $club->id,
            'name'      => 'Muay Thai',
        ]);
    }

    public function test_activity_name_is_required(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/activities", [
                 'description' => 'No name given.',
             ])
             ->assertSessionHasErrors(['name']);
    }

    public function test_owner_can_update_activity(): void
    {
        $owner    = $this->createUser();
        $club     = $this->createClub($owner);
        $activity = ClubActivity::create([
            'tenant_id' => $club->id,
            'name'      => 'Old Name',
        ]);

        $this->actingAs($owner)
             ->put("/admin/club/{$club->slug}/activities/{$activity->id}", [
                 'name'             => 'New Name',
                 'duration_minutes' => 90,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_activities', [
            'id'               => $activity->id,
            'name'             => 'New Name',
            'duration_minutes' => 90,
        ]);
    }

    public function test_owner_can_delete_activity(): void
    {
        $owner    = $this->createUser();
        $club     = $this->createClub($owner);
        $activity = ClubActivity::create([
            'tenant_id' => $club->id,
            'name'      => 'To Be Deleted',
        ]);

        $this->actingAs($owner)
             ->delete("/admin/club/{$club->slug}/activities/{$activity->id}")
             ->assertRedirect();

        $this->assertDatabaseMissing('club_activities', ['id' => $activity->id]);
    }

    public function test_cannot_update_activity_of_another_club(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2   = $this->createUser();
        $club2    = $this->createClub($owner2);
        $activity = ClubActivity::create(['tenant_id' => $club2->id, 'name' => 'Club2 Activity']);

        $this->actingAs($owner1)
             ->put("/admin/club/{$club1->slug}/activities/{$activity->id}", ['name' => 'Hijacked'])
             ->assertNotFound();

        $this->assertDatabaseHas('club_activities', ['id' => $activity->id, 'name' => 'Club2 Activity']);
    }

    public function test_duration_must_be_a_positive_integer(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/activities", [
                 'name'             => 'Test',
                 'duration_minutes' => -5,
             ])
             ->assertSessionHasErrors(['duration_minutes']);
    }
}
