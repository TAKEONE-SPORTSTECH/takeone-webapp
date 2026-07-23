<?php

namespace Tests\Feature;

use App\Models\ActivityCatalog;
use App\Models\ClubActivity;
use App\Models\ClubAffiliation;
use App\Models\User;
use Tests\TestCase;

/**
 * The Add-Skill activity picker is a searchable combobox over two groups: the
 * affiliation club's own activities (which carry the activity_id that links
 * provenance) and the global directory (name-only suggestions). It previously
 * fed a native <datalist> that only ever received one of the two, so most
 * activities were simply not offered.
 */
class AffiliationActivityPickerTest extends TestCase
{
    private function affiliationFor(User $owner, User $member, ?int $tenantId): ClubAffiliation
    {
        return ClubAffiliation::create([
            'member_id' => $member->id,
            'tenant_id' => $tenantId,
            'club_name' => 'Test Club',
            'start_date' => now()->subYear()->toDateString(),
        ]);
    }

    public function test_a_linked_affiliation_returns_club_activities_and_the_directory(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Taekwondo']);
        ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Boxing']);
        ActivityCatalog::create(['name' => 'Fencing', 'is_active' => true]);

        $member = $this->createUser();
        $affiliation = $this->affiliationFor($owner, $member, $club->id);

        $data = $this->actingAs($member)
            ->getJson("/member/{$member->id}/affiliations/{$affiliation->id}/activities")
            ->assertOk()
            ->json();

        $this->assertTrue($data['linked']);
        $this->assertEqualsCanonicalizing(['Taekwondo', 'Boxing'], array_column($data['activities'], 'name'));
        $this->assertContains('Fencing', array_column($data['suggestions'], 'name'));

        // Club rows carry the id that links provenance; catalog rows never do.
        $this->assertNotNull($data['activities'][0]['id']);
        foreach ($data['suggestions'] as $s) {
            $this->assertNull($s['id']);
        }
    }

    public function test_the_directory_does_not_duplicate_a_clubs_own_activity(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Judo']);
        ActivityCatalog::create(['name' => 'Judo', 'is_active' => true]);
        ActivityCatalog::create(['name' => 'Karate', 'is_active' => true]);

        $member = $this->createUser();
        $affiliation = $this->affiliationFor($owner, $member, $club->id);

        $data = $this->actingAs($member)
            ->getJson("/member/{$member->id}/affiliations/{$affiliation->id}/activities")
            ->assertOk()
            ->json();

        $this->assertNotContains('Judo', array_column($data['suggestions'], 'name'));
        $this->assertContains('Karate', array_column($data['suggestions'], 'name'));
    }

    public function test_an_off_platform_affiliation_returns_the_directory_only(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        ActivityCatalog::create(['name' => 'Surfing', 'is_active' => true]);
        ActivityCatalog::create(['name' => 'Retired Sport', 'is_active' => false]);

        $affiliation = $this->affiliationFor($owner, $member, null);

        $data = $this->actingAs($member)
            ->getJson("/member/{$member->id}/affiliations/{$affiliation->id}/activities")
            ->assertOk()
            ->json();

        $this->assertFalse($data['linked']);
        $this->assertSame([], $data['activities']);
        $this->assertContains('Surfing', array_column($data['suggestions'], 'name'));
        $this->assertNotContains('Retired Sport', array_column($data['suggestions'], 'name'));
    }

    /**
     * Security: the picker must not become a way to read someone else's affiliations.
     * authorizeForMember() denies with a 404 rather than a 403 on purpose — a stranger
     * learns nothing about whether that member or affiliation exists.
     */
    public function test_an_unrelated_user_cannot_read_another_members_activities(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $stranger = $this->createUser();
        $affiliation = $this->affiliationFor($owner, $member, null);

        $this->actingAs($stranger)
            ->getJson("/member/{$member->id}/affiliations/{$affiliation->id}/activities")
            ->assertNotFound();
    }
}
