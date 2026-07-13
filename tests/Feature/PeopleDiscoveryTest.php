<?php

namespace Tests\Feature;

use App\Models\UserBlock;
use Tests\TestCase;

class PeopleDiscoveryTest extends TestCase
{
    // ---------------- Search ----------------

    public function test_search_returns_matching_discoverable_members(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser(['full_name' => 'Me Myself']);
        $target = $this->createUser(['full_name' => 'Zayed Al Falasi']);
        $this->joinClub($me, $club);
        $this->joinClub($target, $club);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=Zayed')
            ->assertOk()
            ->assertJson(['success' => true]);

        $names = collect($res->json('people'))->pluck('name');
        $this->assertTrue($names->contains('Zayed Al Falasi'));
    }

    public function test_search_excludes_members_outside_my_clubs(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $this->joinClub($me, $club);
        $stranger = $this->createUser(['full_name' => 'Outside Stranger']); // not a club-mate

        $res = $this->actingAs($me)->getJson('/me/people/search?q=Outside')->assertOk();
        $this->assertEmpty($res->json('people'));
    }

    public function test_search_excludes_self(): void
    {
        $me = $this->createUser(['full_name' => 'Unique Selfname']);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=Unique')->assertOk();
        $this->assertEmpty($res->json('people'));
    }

    public function test_search_excludes_non_discoverable_members(): void
    {
        $me = $this->createUser();
        $hidden = $this->createUser(['full_name' => 'Hidden Person', 'is_discoverable' => false]);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=Hidden')->assertOk();
        $this->assertEmpty($res->json('people'));
    }

    public function test_search_excludes_blocked_members(): void
    {
        $me = $this->createUser();
        $blocked = $this->createUser(['full_name' => 'Blocked Guy']);
        UserBlock::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=Blocked')->assertOk();
        $this->assertEmpty($res->json('people'));
    }

    public function test_search_by_email(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $target = $this->createUser(['full_name' => 'Emailed Guy', 'email' => 'find.me@example.com']);
        $this->joinClub($me, $club);
        $this->joinClub($target, $club);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=find.me@example.com')->assertOk();
        $this->assertTrue(collect($res->json('people'))->pluck('uuid')->contains($target->uuid));
    }

    public function test_search_by_phone_number(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $target = $this->createUser(['full_name' => 'Phoned Guy', 'mobile' => ['code' => '+973', 'number' => '39007788']]);
        $this->joinClub($me, $club);
        $this->joinClub($target, $club);

        $res = $this->actingAs($me)->getJson('/me/people/search?q=39007788')->assertOk();
        $this->assertTrue(collect($res->json('people'))->pluck('uuid')->contains($target->uuid));
    }

    public function test_find_people_page_renders_mobile_and_desktop(): void
    {
        // Regression: the index views reference routes (back button, search) that
        // must exist — a bad route() call there throws a ViewException.
        $me = $this->createUser();

        $this->actingAs($me)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get('/me/people')->assertOk()->assertSee('Find People');

        $this->actingAs($me)->get('/me/people')->assertOk()->assertSee('Find People');
    }

    // ---------------- Suggestions (default state) ----------------

    private function joinClub($user, $club): void
    {
        \Illuminate\Support\Facades\DB::table('memberships')->insert([
            'tenant_id' => $club->id, 'user_id' => $user->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_suggestions_surface_clubmates_with_reason(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['club_name' => 'Champions Club']);
        $me = $this->createUser();
        $mate = $this->createUser(['full_name' => 'Club Mate']);
        $this->joinClub($me, $club);
        $this->joinClub($mate, $club);

        $suggestions = app(\App\Services\PeopleRecommendationService::class)->suggest($me);
        $found = collect($suggestions)->firstWhere('uuid', $mate->uuid);

        $this->assertNotNull($found, 'club-mate should be suggested');
        $this->assertStringContainsString('Champions Club', $found['reason']);
        $this->assertFalse($found['is_following']);
    }

    public function test_suggestions_exclude_self_blocked_hidden_and_already_followed(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $followed = $this->createUser();
        $blocked = $this->createUser();
        $hidden = $this->createUser(['is_discoverable' => false]);
        foreach ([$me, $followed, $blocked, $hidden] as $u) {
            $this->joinClub($u, $club);
        }
        $me->following()->attach($followed->id);
        UserBlock::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $ids = collect(app(\App\Services\PeopleRecommendationService::class)->suggest($me))->pluck('uuid');

        $this->assertFalse($ids->contains($me->uuid));
        $this->assertFalse($ids->contains($followed->uuid));
        $this->assertFalse($ids->contains($blocked->uuid));
        $this->assertFalse($ids->contains($hidden->uuid));
    }

    public function test_suggestions_never_cross_club_boundaries(): void
    {
        // Discovery is club-scoped: with no shared club, a platform-wide member must
        // never be suggested, even when there are no other signals to rank by.
        $me = $this->createUser(); // no clubs, no follows
        $other = $this->createUser(['full_name' => 'Somebody Else']);

        $suggestions = app(\App\Services\PeopleRecommendationService::class)->suggest($me);

        $this->assertFalse(collect($suggestions)->pluck('uuid')->contains($other->uuid));
    }

    public function test_index_page_shows_suggested_clubmate(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $mate = $this->createUser(['full_name' => 'Suggested Buddy']);
        $this->joinClub($me, $club);
        $this->joinClub($mate, $club);

        $this->actingAs($me)->get('/me/people')->assertOk()->assertSee('Suggested Buddy');
    }

    // ---------------- Public profile ----------------

    public function test_public_profile_renders_safe_data_only(): void
    {
        $me = $this->createUser();
        $person = $this->createUser(['full_name' => 'Public Athlete']);

        $this->actingAs($me)->get("/people/{$person->uuid}")
            ->assertOk()
            ->assertSee('Public Athlete')
            // Sensitive sections must NOT appear on the public profile.
            ->assertDontSee('Billing')
            ->assertDontSee('Health Records')
            ->assertDontSee('Emergency');
    }

    public function test_viewing_own_public_profile_redirects_to_full_profile(): void
    {
        $me = $this->createUser();

        $this->actingAs($me)->get("/people/{$me->uuid}")
            ->assertRedirect("/member/{$me->uuid}");
    }

    public function test_blocked_user_public_profile_is_hidden(): void
    {
        $me = $this->createUser();
        $person = $this->createUser();
        UserBlock::create(['blocker_id' => $person->id, 'blocked_id' => $me->id]);

        // Signed-in browser navigation to a 404 redirects back (home, absent a
        // referer) instead of rendering a raw 404 (bootstrap/app.php); JSON
        // callers still get a real 404.
        $this->actingAs($me)->getJson("/people/{$person->uuid}")->assertNotFound();
        $this->actingAs($me)->get("/people/{$person->uuid}")->assertRedirect();
    }

    // ---------------- Discoverable toggle ----------------

    public function test_member_can_toggle_discoverable_off(): void
    {
        $me = $this->createUser();
        $this->assertTrue($me->fresh()->isDiscoverable());

        $this->actingAs($me)->putJson('/me/discoverable', ['is_discoverable' => false])
            ->assertOk()
            ->assertJson(['success' => true, 'is_discoverable' => false]);

        $this->assertFalse($me->fresh()->isDiscoverable());
    }

    // ---------------- Messaging a discovered member ----------------

    public function test_can_start_a_chat_with_a_discoverable_member(): void
    {
        $me = $this->createUser();
        $person = $this->createUser(); // discoverable by default, not a club-mate

        $this->assertTrue($me->canMessage($person->fresh()));

        $this->actingAs($me)->postJson("/messages/start/{$person->id}")
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_cannot_start_chat_with_hidden_non_clubmate(): void
    {
        $me = $this->createUser();
        $hidden = $this->createUser(['is_discoverable' => false]);

        $this->assertFalse($me->canMessage($hidden->fresh()));
    }

    // ---------------- Feed renders with People-you-may-know ----------------

    public function test_feed_renders_with_clubmate_suggestions(): void
    {
        // Regression: the feed's "People you may know" builds route('people.show',
        // $u->uuid); the suggestion query must select uuid or route generation throws.
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $me = $this->createUser();
        $mate = $this->createUser(['full_name' => 'Club Mate']);
        foreach ([$me, $mate] as $u) {
            \Illuminate\Support\Facades\DB::table('memberships')->insert([
                'tenant_id' => $club->id, 'user_id' => $u->id, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($me)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get('/me')
            ->assertOk();
    }
}
