<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ActivityCatalog;
use App\Models\ClubActivity;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    public function test_editing_a_club_activity_does_not_change_the_global_directory(): void
    {
        // A curated directory entry the whole platform shares.
        $entry = ActivityCatalog::create([
            'name'        => 'Taekwondo',
            'slug'        => 'taekwondo',
            'description' => '<p>Curated master history.</p>',
        ]);

        $owner    = $this->createUser();
        $club     = $this->createClub($owner);
        $activity = ClubActivity::create([
            'tenant_id'   => $club->id,
            'name'        => 'Taekwondo',
            'description' => 'Club copy.',
        ]);

        // Club edits ITS copy — name, description, style.
        $this->actingAs($owner)
             ->putJson("/admin/club/{$club->slug}/activities/{$activity->id}", [
                 'name'        => 'Taekwondo (Evening)',
                 'style'       => 'ITF',
                 'description' => 'Heavily rewritten by the club.',
             ])
             ->assertOk();

        // The club's own row changed…
        $this->assertDatabaseHas('club_activities', [
            'id'    => $activity->id,
            'name'  => 'Taekwondo (Evening)',
            'style' => 'ITF',
        ]);

        // …but the shared directory entry is untouched.
        $entry->refresh();
        $this->assertSame('Taekwondo', $entry->name);
        $this->assertSame('<p>Curated master history.</p>', $entry->description);
    }

    public function test_deleting_a_club_activity_keeps_the_global_directory_entry(): void
    {
        // Permanent master record shown in the super-admin directory.
        $entry = ActivityCatalog::create(['name' => 'Boxing', 'slug' => 'boxing']);

        $owner    = $this->createUser();
        $club     = $this->createClub($owner);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Boxing']);

        $this->actingAs($owner)
             ->deleteJson("/admin/club/{$club->slug}/activities/{$activity->id}")
             ->assertOk();

        // Removed from the club's list…
        $this->assertDatabaseMissing('club_activities', ['id' => $activity->id]);

        // …but the permanent directory entry survives.
        $this->assertDatabaseHas('activity_catalog', ['id' => $entry->id, 'slug' => 'boxing']);
    }

    public function test_deleting_one_clubs_activity_does_not_affect_another_clubs_copy(): void
    {
        $ownerA = $this->createUser();
        $clubA  = $this->createClub($ownerA);
        $actA   = ClubActivity::create(['tenant_id' => $clubA->id, 'name' => 'Judo']);

        $ownerB = $this->createUser();
        $clubB  = $this->createClub($ownerB);
        $actB   = ClubActivity::create(['tenant_id' => $clubB->id, 'name' => 'Judo']);

        $this->actingAs($ownerA)
             ->deleteJson("/admin/club/{$clubA->slug}/activities/{$actA->id}")
             ->assertOk();

        $this->assertDatabaseMissing('club_activities', ['id' => $actA->id]);
        $this->assertDatabaseHas('club_activities', ['id' => $actB->id, 'name' => 'Judo']);
    }

    public function test_editing_one_clubs_activity_does_not_affect_another_clubs_copy(): void
    {
        $ownerA = $this->createUser();
        $clubA  = $this->createClub($ownerA);
        $actA   = ClubActivity::create(['tenant_id' => $clubA->id, 'name' => 'Karate', 'description' => 'A copy']);

        $ownerB = $this->createUser();
        $clubB  = $this->createClub($ownerB);
        $actB   = ClubActivity::create(['tenant_id' => $clubB->id, 'name' => 'Karate', 'description' => 'B copy']);

        $this->actingAs($ownerA)
             ->putJson("/admin/club/{$clubA->slug}/activities/{$actA->id}", [
                 'name'        => 'Karate (Shotokan)',
                 'description' => 'A rewritten',
             ])
             ->assertOk();

        // Club B's copy is unchanged.
        $this->assertDatabaseHas('club_activities', [
            'id'          => $actB->id,
            'name'        => 'Karate',
            'description' => 'B copy',
        ]);
    }

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

        // putJson mirrors the app's real AJAX write path (and keeps a clean 404;
        // browser writes now redirect via the global not-found handler).
        $this->actingAs($owner1)
             ->putJson("/admin/club/{$club1->slug}/activities/{$activity->id}", ['name' => 'Hijacked'])
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
