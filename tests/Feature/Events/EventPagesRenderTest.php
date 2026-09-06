<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
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
     * The zoomable board is mounted by the desktop bracket screen and by the
     * full-screen draw manager — one renderer for both, so a draw looks and
     * behaves the same on a phone (and therefore inside the Android app).
     *
     * The MOBILE bracket screen deliberately no longer embeds it: 62vh of board
     * under a header was worse than a link to a screen that gives it the whole
     * viewport. That page keeps the readable round-by-round detail instead.
     */
    public function test_the_zoomable_board_mounts_where_it_belongs(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $member = $this->member($club);

        $desktop = $this->actingAs($member)->get("/me/events/{$event->uuid}/brackets")->assertOk();
        $desktop->assertSee('BracketBoard.mount', false);
        $desktop->assertSee('event-bracket-viewport', false);

        // Mobile: no board of its own, but a way through to the full-screen one.
        $mobile = $this->actingAs($member)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get("/me/events/{$event->uuid}/brackets")->assertOk();
        $mobile->assertDontSee('event-bracket-viewport', false);

        // The organiser's full-screen manager is where a phone arranges a draw.
        $manage = $this->actingAs($owner)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get("/me/events/{$event->uuid}/brackets/manage")->assertOk();
        $manage->assertSee('BracketBoard.mount', false);
        $manage->assertSee('manage-bracket-viewport', false);
    }

    /**
     * The quick-facts chips are links to elsewhere on the page, and a link to a
     * missing anchor is a chip that silently does nothing. Both device views
     * must ship the chip AND its landing point together.
     */
    public function test_the_quick_fact_chips_have_somewhere_to_land(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club, [
            'gps_lat' => 26.2285, 'gps_long' => 50.5860, 'location' => 'Isa Town Hall',
        ]);
        $member = $this->member($club);

        $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

        foreach ([null, $phone] as $agent) {
            $req = $this->actingAs($member);
            if ($agent) {
                $req = $req->withHeaders(['User-Agent' => $agent]);
            }

            $page = $req->get("/me/events/{$event->uuid}")->assertOk();

            // Each chip…
            $page->assertSee("jump(['run-start', 'how-it-runs'])", false)
                ->assertSee("jump('join-participate')", false)
                ->assertSee("jump('where')", false);

            // …and the thing it scrolls to.
            $page->assertSee('id="join-participate"', false)
                ->assertSee('id="where"', false);
        }
    }

    /**
     * partials/event-show-script is rendered INSIDE an x-data attribute, so a
     * literal double quote anywhere in it — including in a comment — closes the
     * attribute early. Everything after that point stops being script and
     * spills onto the page as visible text, which is how it is noticed: raw
     * code across the top of the banner.
     *
     * Asserting the attribute still holds the LAST thing the object defines is
     * what catches it; a page that renders 200 OK tells you nothing here.
     */
    public function test_the_event_alpine_root_survives_intact(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $member = $this->member($club);

        $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

        foreach ([null, $phone] as $agent) {
            $req = $this->actingAs($member);
            if ($agent) {
                $req = $req->withHeaders(['User-Agent' => $agent]);
            }

            $html = $req->get("/me/events/{$event->uuid}")->assertOk()->getContent();

            // Slice out the attribute: from the x-data that opens the event
            // object to the first double quote after it.
            $anchor = strpos($html, 'goingCount:');
            $this->assertNotFalse($anchor, 'the event Alpine root did not render');
            $open = strrpos(substr($html, 0, $anchor), 'x-data="') + 8;
            $attr = substr($html, $open, strpos($html, '"', $anchor) - $open);

            $this->assertStringContainsString('jump(', $attr, 'the x-data attribute was cut short — look for a literal double quote in event-show-script');
            $this->assertStringEndsWith('}', rtrim($attr));
        }
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
    /**
     * The run-day panel's Alpine factory has to travel INSIDE #shell-content.
     *
     * It lives on the event CONSOLE now — the public event page carries no
     * organiser tooling — but the shell trap is the same one either way.
     *
     * The mobile shell navigator swaps only that element and re-runs only the
     * scripts it finds inside it; anything @push('scripts')-ed lands in
     * #shell-scripts, outside the swap. When the checklist pushed its script,
     * navigating to the event from within the shell left eventChecklist()
     * undefined — so tick, add and Start all did nothing until a hard refresh.
     */
    public function test_the_run_day_panel_script_travels_with_the_swapped_content(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = $this->event($owner, $club);

        $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

        $html = $this->actingAs($owner->fresh())
            ->withHeaders(['User-Agent' => $phone])
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('eventChecklist', $html, 'the run-day panel did not render at all');

        $main = strpos($html, 'id="shell-content"');
        $this->assertNotFalse($main, 'the mobile shell content element is missing');
        $end = strpos($html, 'id="shell-scripts"', $main);
        $this->assertNotFalse($end, 'the pushed-scripts container is missing');

        $swapped = substr($html, $main, $end - $main);
        $this->assertStringContainsString(
            'window.eventChecklist',
            $swapped,
            'eventChecklist() is defined outside #shell-content — an in-shell navigation will not re-run it'
        );
    }
}
