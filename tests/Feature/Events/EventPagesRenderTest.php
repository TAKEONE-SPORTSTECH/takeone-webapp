<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The event screens must render for BOTH a packaged type and a type still on
 * the generic fallback — that is the contract the registry has to keep while
 * types are ported one at a time.
 */
class EventPagesRenderTest extends TestCase
{
    private function club(User $owner): Tenant
    {
        return $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
    }

    private function member(Tenant $club): User
    {
        $user = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user->fresh();
    }

    private function event(User $creator, Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $creator->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'spectator_enabled' => true,
            'spectator_fee' => 'Free',
        ], $attrs));
    }

    public function test_events_list_renders(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $this->event($owner, $club);
        $this->event($owner, $club, ['sport' => 'football', 'event_type' => 'league', 'title' => 'City League']);

        $this->actingAs($this->member($club))->get('/me/events')
            ->assertOk()
            ->assertSee('Spring Open')
            ->assertSee('City League');
    }

    public function test_packaged_event_detail_page_renders(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $this->actingAs($this->member($club))->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertSee('Spring Open');
    }

    public function test_generic_event_detail_page_renders_with_its_league_table(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club, [
            'sport' => 'football',
            'event_type' => 'league',
            'title' => 'City League',
            'league' => [
                'teams' => ['Falcons', 'Sharks'],
                'fixtures' => [['home' => 'Falcons', 'away' => 'Sharks', 'date' => '2026-09-01', 'home_score' => 2, 'away_score' => 1]],
            ],
        ]);

        $this->actingAs($this->member($club))->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertSee('Falcons');
    }

    public function test_manager_sees_the_detail_page_with_finance(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = $this->event($owner, $club, ['participant_fee' => 'BHD 10']);

        $this->actingAs($owner->fresh())->get("/me/events/{$event->uuid}")->assertOk();
    }

    public function test_bracket_page_renders_for_a_packaged_event(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $this->actingAs($this->member($club))->get("/me/events/{$event->uuid}/brackets")
            ->assertOk()
            ->assertSee('Senior Men -58 kg');
    }

    /**
     * Both bracket screens mount the shared zoomable board. Device-split per
     * CLAUDE.md, but one renderer — so a draw looks and behaves the same on a
     * phone (and therefore inside the Android app) as on a desktop.
     */
    public function test_both_bracket_screens_mount_the_zoomable_board(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $member = $this->member($club);

        $desktop = $this->actingAs($member)->get("/me/events/{$event->uuid}/brackets")->assertOk();
        $desktop->assertSee('BracketBoard.mount', false);
        $desktop->assertSee('event-bracket-viewport', false);

        $mobile = $this->actingAs($member)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get("/me/events/{$event->uuid}/brackets")->assertOk();
        $mobile->assertSee('BracketBoard.mount', false);
        $mobile->assertSee('event-bracket-viewport', false);
    }

    public function test_create_and_edit_forms_render(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = $this->event($owner, $club);

        $this->actingAs($owner->fresh())->get('/me/events/create')->assertOk();
        $this->actingAs($owner->fresh())->get("/me/events/{$event->uuid}/edit")->assertOk();
    }
}
