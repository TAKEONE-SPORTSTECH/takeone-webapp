<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Support\EventNotifier;
use App\Events\Support\Milestone;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * Hidden events (owner, 2026-10-04): in no list anywhere, no announcements,
 * reached only by the link — which anybody holding it may open. Organisers and
 * people already entered still find it in their own lists.
 */
class HiddenEventTest extends TestCase
{
    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id, 'created_by' => $club->owner_user_id, 'title' => 'Secret Cup',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'inter_club', 'status' => 'active',
            'is_archived' => false, 'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00:00',
        ], $attrs));
    }

    private function exploreTitles(User $as): array
    {
        return collect($this->actingAs($as)->getJson('/explore/events')->assertOk()->json('events'))->pluck('title')->all();
    }

    public function test_a_hidden_event_is_in_nobodys_explore_but_its_organisers(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $stranger = $this->createUser();
        $this->clubFor($stranger);

        $this->event($club, ['title' => 'Open Cup']);
        $this->event($club, ['visibility' => ClubEvent::HIDDEN]);

        $this->assertContains('Secret Cup', $this->exploreTitles($owner));
        $this->assertNotContains('Secret Cup', $this->exploreTitles($member));      // even its own club's members
        $this->assertNotContains('Secret Cup', $this->exploreTitles($stranger));
        $this->assertContains('Open Cup', $this->exploreTitles($stranger));
    }

    public function test_people_entered_in_it_still_list_it_and_the_link_opens_it_for_anybody(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $ev = $this->event($club, ['visibility' => ClubEvent::HIDDEN, 'entry_mode' => 'members']);

        $entrant = $this->createUser();
        \App\Events\Models\ClubEventRegistration::forceCreate(['event_id' => $ev->id, 'user_id' => $entrant->id, 'role' => 'participant', 'status' => 'joined']);
        $this->assertSame([$ev->id], ClubEvent::listedFor($entrant)->pluck('id')->all());
        $this->assertSame([], ClubEvent::listedFor($this->createUser())->pluck('id')->all());

        // The link: a signed-out stranger opens the page.
        config(['events.unified_page' => true]);
        $this->assertTrue(app(\App\Events\Support\PublicEvent::class)->isPublic($ev->fresh()));
        $this->get('/me/events/'.$ev->uuid)->assertOk();
    }

    public function test_a_hidden_event_announces_nothing_until_it_is_listed(): void
    {
        $owner = $this->createUser();
        $ev = $this->event($this->clubFor($owner), ['visibility' => ClubEvent::HIDDEN]);
        $announce = new Milestone('created', 'New event', 'Body', null, Milestone::AUDIENCE_SCOPE);

        $this->assertNull(app(EventNotifier::class)->fire($ev, $announce));
        // Not used up: listing it later lets the announcement go out.
        $this->assertSame(0, \App\Events\Models\EventNotificationSent::where('event_id', $ev->id)->count());
    }

    public function test_only_the_organiser_switches_it(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $ev = $this->event($club);
        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member)->postJson('/me/events/'.$ev->uuid.'/visibility', ['visibility' => 'hidden'])->assertForbidden();
        $this->assertFalse($ev->fresh()->isHidden());

        $this->actingAs($owner)->postJson('/me/events/'.$ev->uuid.'/visibility', ['visibility' => 'hidden'])
            ->assertOk()->assertJsonPath('visibility', 'hidden');
        $this->assertTrue($ev->fresh()->isHidden());

        $this->actingAs($owner)->postJson('/me/events/'.$ev->uuid.'/visibility', ['visibility' => 'secret'])->assertStatus(422);
    }

    public function test_the_club_page_does_not_list_it(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $this->event($club, ['title' => 'Open Cup']);
        $this->event($club, ['visibility' => ClubEvent::HIDDEN]);

        $events = Tenant::with(['events' => fn ($q) => $q->listed()])->find($club->id)->events->pluck('title')->all();
        $this->assertSame(['Open Cup'], $events);
    }

    public function test_the_mcp_tool_lets_only_the_organiser_hide_it(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $ev = $this->event($club);
        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $call = function (User $as, array $args) {
            $this->actingAs($as);
            $d = json_decode((string) (new \App\Mcp\Tools\SetEventVisibilityTool)->handle(new \Laravel\Mcp\Request($args))->content(), true);

            return is_array($d) ? $d : ['error' => true];
        };

        $this->assertArrayHasKey('error', $call($member, ['event' => $ev->uuid, 'visibility' => 'hidden']));
        $this->assertFalse($ev->fresh()->isHidden());
        $this->assertSame('hidden', $call($owner, ['event' => $ev->uuid, 'visibility' => 'hidden'])['visibility']);
        $this->assertTrue($ev->fresh()->isHidden());
    }
}
