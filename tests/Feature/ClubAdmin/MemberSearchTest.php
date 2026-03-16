<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\Membership;
use Tests\TestCase;

class MemberSearchTest extends TestCase
{
    private function searchUrl(string $slug, string $query): string
    {
        return "/admin/club/{$slug}/members/search?query=" . urlencode($query);
    }

    public function test_search_returns_users_key_in_response(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->createUser(['full_name' => 'John Smith', 'name' => 'John Smith']);

        $this->actingAs($owner)
             ->getJson($this->searchUrl($club->slug, 'John'))
             ->assertOk()
             ->assertJsonStructure(['users']);
    }

    public function test_search_query_too_short_returns_empty(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->getJson($this->searchUrl($club->slug, 'J'))
             ->assertOk()
             ->assertExactJson(['users' => []]);
    }

    public function test_search_finds_user_by_full_name(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $target = $this->createUser(['full_name' => 'Khalid AlMansouri', 'name' => 'Khalid AlMansouri']);

        $response = $this->actingAs($owner)
                         ->getJson($this->searchUrl($club->slug, 'Khalid'))
                         ->assertOk();

        $ids = collect($response->json('users'))->pluck('id')->all();
        $this->assertContains($target->id, $ids);
    }

    public function test_search_finds_user_by_email(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $target = $this->createUser(['email' => 'unique_target@example.com']);

        $response = $this->actingAs($owner)
                         ->getJson($this->searchUrl($club->slug, 'unique_target@example.com'))
                         ->assertOk();

        $ids = collect($response->json('users'))->pluck('id')->all();
        $this->assertContains($target->id, $ids);
    }

    public function test_search_marks_existing_club_member(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $member = $this->createUser(['full_name' => 'Already Member', 'name' => 'Already Member']);

        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);

        $response = $this->actingAs($owner)
                         ->getJson($this->searchUrl($club->slug, 'Already Member'))
                         ->assertOk();

        $found = collect($response->json('users'))->firstWhere('id', $member->id);
        $this->assertNotNull($found);
        $this->assertTrue($found['is_member']);
    }

    public function test_search_marks_non_member_correctly(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $target = $this->createUser(['full_name' => 'Not A Member', 'name' => 'Not A Member']);

        $response = $this->actingAs($owner)
                         ->getJson($this->searchUrl($club->slug, 'Not A Member'))
                         ->assertOk();

        $found = collect($response->json('users'))->firstWhere('id', $target->id);
        $this->assertNotNull($found);
        $this->assertFalse($found['is_member']);
    }

    public function test_search_requires_authentication(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->getJson($this->searchUrl($club->slug, 'John'))
             ->assertUnauthorized();
    }

    public function test_random_user_cannot_search_club_members(): void
    {
        $owner  = $this->createUser();
        $club   = $this->createClub($owner);
        $rando  = $this->createUser();

        $this->actingAs($rando)
             ->getJson($this->searchUrl($club->slug, 'John'))
             ->assertForbidden();
    }
}
