<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\EventChecklistItem;
use App\Models\EventOfficial;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The run-day checklist, and the start it gates.
 *
 * The rule this file protects: a competition begins because the organiser
 * started it, and they cannot start it with work outstanding unless they
 * deliberately override — in which case the override is recorded against them.
 */
class EventChecklistTest extends TestCase
{
    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id, 'created_by' => $organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ], $attrs));
    }

    private function official(ClubEvent $event, Tenant $club, string $role = EventOfficial::ROLE_WEIGH_IN): User
    {
        $user = $this->createUser();
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $user->id,
            'role' => $role, 'compensation' => 'volunteer',
        ]);

        return $user->fresh();
    }

    private function item(ClubEvent $event, string $label = 'Mats laid'): EventChecklistItem
    {
        return $event->checklistItems()->create(['label' => $label, 'sort_order' => 1]);
    }

    /* ===================== Starting is now a decision ===================== */

    public function test_an_event_whose_time_has_passed_has_not_started_by_itself(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser, [
            'date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        // The clock used to decide this. It no longer does: the event is late,
        // not running, and its draw is still arrangeable.
        $this->assertFalse($event->hasStarted());
        $this->assertTrue($event->isOverdueToStart());
    }

    public function test_the_organiser_starts_an_event_with_an_empty_checklist(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // No list written is not a reason to be blocked.
        $this->actingAs($organiser->fresh())
            ->postJson("/me/events/{$event->uuid}/start")
            ->assertOk()->assertJson(['success' => true]);

        $event->refresh();
        $this->assertTrue($event->hasStarted());
        $this->assertSame($organiser->id, $event->started_by);
        $this->assertFalse($event->start_overridden);
    }

    public function test_an_outstanding_check_blocks_the_start(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->item($event);

        $this->actingAs($organiser->fresh())
            ->postJson("/me/events/{$event->uuid}/start")
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'checklist_incomplete', 'outstanding' => 1]);

        $this->assertFalse($event->fresh()->hasStarted());
    }

    public function test_clearing_every_check_unblocks_the_start(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $item = $this->item($event);
        $scale = $this->official($event, $club);

        $this->actingAs($scale)
            ->putJson("/me/events/{$event->uuid}/checklist/{$item->uuid}", ['checked' => true])
            ->assertOk()->assertJson(['success' => true, 'outstanding' => 0]);

        // Cleared by the official who did the work, and it says so.
        $this->assertSame($scale->id, $item->fresh()->checked_by);

        $this->actingAs($organiser->fresh())
            ->postJson("/me/events/{$event->uuid}/start")
            ->assertOk();

        $event->refresh();
        $this->assertTrue($event->hasStarted());
        $this->assertFalse($event->start_overridden);
    }

    public function test_the_organiser_can_override_and_the_override_is_recorded(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->item($event);
        $this->item($event, 'First aid on site');

        $this->actingAs($organiser->fresh())
            ->postJson("/me/events/{$event->uuid}/start", ['override' => true])
            ->assertOk()->assertJson(['success' => true, 'overridden' => true]);

        $event->refresh();
        $this->assertTrue($event->hasStarted());
        $this->assertTrue($event->start_overridden, 'starting past an incomplete list must leave a trace');
        $this->assertSame($organiser->id, $event->started_by);
    }

    public function test_an_event_cannot_be_started_twice(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/start")->assertOk();
        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/start")->assertStatus(422);
    }

    public function test_the_checklist_closes_once_the_event_has_started(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $item = $this->item($event);
        $scale = $this->official($event, $club);

        $this->actingAs($organiser->fresh())
            ->postJson("/me/events/{$event->uuid}/start", ['override' => true])->assertOk();

        // A gate you can still edit afterwards is decoration.
        $this->actingAs($scale)
            ->putJson("/me/events/{$event->uuid}/checklist/{$item->uuid}", ['checked' => true])
            ->assertStatus(422);

        $this->assertNull($item->fresh()->checked_at);
    }

    /* ===================== Who may do what ===================== */

    public function test_an_official_may_clear_an_item_but_not_write_the_list(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $item = $this->item($event);
        $scale = $this->official($event, $club);

        $this->actingAs($scale)
            ->putJson("/me/events/{$event->uuid}/checklist/{$item->uuid}", ['checked' => true])->assertOk();

        $this->actingAs($scale)
            ->postJson("/me/events/{$event->uuid}/checklist", ['label' => 'Something else'])
            ->assertForbidden();

        $this->actingAs($scale)
            ->deleteJson("/me/events/{$event->uuid}/checklist/{$item->uuid}")
            ->assertForbidden();
    }

    public function test_an_official_cannot_start_the_event(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $scale = $this->official($event, $club);

        // Starting locks the draw. That is the organiser's call alone.
        $this->actingAs($scale)
            ->postJson("/me/events/{$event->uuid}/start")
            ->assertForbidden();

        $this->assertFalse($event->fresh()->hasStarted());
    }

    public function test_an_ordinary_member_cannot_touch_the_checklist_or_see_it(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $item = $this->item($event, 'Trophies collected from the printers');

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $member = $member->fresh();

        $this->actingAs($member)
            ->putJson("/me/events/{$event->uuid}/checklist/{$item->uuid}", ['checked' => true])
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson("/me/events/{$event->uuid}/start")
            ->assertForbidden();

        // Nor is the organiser's preparation list serialised into their page.
        $this->actingAs($member)->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertDontSee('Trophies collected from the printers');
    }

    public function test_an_item_from_another_event_cannot_be_cleared_through_this_one(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $mine = $this->event($club, $organiser);
        $other = $this->event($club, $organiser, ['title' => 'Autumn Open']);
        $strayItem = $this->item($other);

        // A valid uuid is still not a licence to act on it from anywhere.
        $this->actingAs($organiser->fresh())
            ->putJson("/me/events/{$mine->uuid}/checklist/{$strayItem->uuid}", ['checked' => true])
            ->assertNotFound();

        $this->assertNull($strayItem->fresh()->checked_at);
    }

    /* ===================== The MCP mirror ===================== */

    public function test_the_readiness_tool_answers_officials_and_refuses_everyone_else(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->item($event, 'Scoreboard tested');

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $asOrganiser = $this->mcpCall($organiser->fresh(), ['event' => $event->uuid]);
        $this->assertStringContainsString('Scoreboard tested', $asOrganiser);
        $this->assertStringContainsString('"outstanding":1', $asOrganiser);

        // The MCP must never be a way round the web app's scoping: a competitor
        // gets the same "not found" here as they would on the page.
        $asMember = $this->mcpCall($member->fresh(), ['event' => $event->uuid]);
        $this->assertStringContainsString('not found', strtolower($asMember));
        $this->assertStringNotContainsString('Scoreboard tested', $asMember);
    }

    /** Run the readiness tool as a user and return its text content. */
    private function mcpCall(User $user, array $args): string
    {
        $this->actingAs($user);

        return (string) (new \App\Mcp\Tools\GetEventReadinessTool)
            ->handle(new \Laravel\Mcp\Request($args))
            ->content();
    }
}
