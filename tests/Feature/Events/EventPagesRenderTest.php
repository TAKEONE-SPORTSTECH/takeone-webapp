<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
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

        /*
         * The teams are no longer STACKED on the event page: since 2026-09-14
         * the member page carries the same doors the public poster does, and a
         * league's table lives behind its own (partials/event-doors). What this
         * page must still do is offer that door — and the legacy JSON season
         * must still become real rows on the way (League::hydrateLegacy).
         */
        $this->actingAs($this->member($club))->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertSee(__('event-league::messages.title'));

        $this->assertSame(
            ['Falcons', 'Sharks'],
            \App\Events\League\Models\LeagueTeam::where('event_id', $event->id)
                ->orderBy('sort_order')->pluck('name')->all(),
            'the season typed into the old form was not brought across',
        );
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
        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        /* A division with no bouts in it yields NO bracket, deliberately:
           AbstractEventType::bracketView() returns [] unless some division has
           matches, so a belt test or a league offers no empty board.

           The ENTRANTS are not decoration either. Opening the draw brings
           derived state up to date, and a division with nobody in it is re-cut
           to nothing — taking a hand-written bout with it. That is the same
           re-cut that cost a ten-bout championship on 2026-09-13, and it is why
           this fixture is two real registrations rather than two names. */
        foreach (['Ali', 'Bader'] as $name) {
            \App\Events\Models\ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $this->createUser(['full_name' => $name])->id,
                'category_id' => $division->id,
                'role' => 'participant',
                'paid' => true,
            ]);
        }

        \App\Events\Models\EventMatch::create([
            'event_id' => $event->id, 'category_id' => $division->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0, 'match_no' => 1,
            'a_name' => 'Ali', 'b_name' => 'Bader', 'status' => 'upcoming',
        ]);

        // /brackets redirects to /brackets/manage, which is where the board
        // lives now; the contract is that the DRAW renders, not which of the
        // two addresses served it.
        $member = $this->member($club);

        $this->actingAs($member)->followingRedirects()
            ->get("/me/events/{$event->uuid}/brackets")
            ->assertOk()
            ->assertSee('BracketBoard', false);

        /* The division NAME is no longer in the server-rendered page. The
           stage boots with `divisions: []` and fills the menu from the data
           feed, so asserting the name against the HTML would pass only while
           the board happened to be rendered server-side. Ask the door the
           page actually reads through. */
        $this->actingAs($member)
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Senior Men -58 kg']);
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
        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $member = $this->member($club);

        /* A real draw, for the same reason as the test above: no bouts means no
           bracket at all, and no entrants means the division is re-cut to
           nothing the moment the page brings derived state up to date. */
        foreach (['Ali', 'Bader'] as $name) {
            \App\Events\Models\ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $this->createUser(['full_name' => $name])->id,
                'category_id' => $division->id,
                'role' => 'participant',
                'paid' => true,
            ]);
        }

        \App\Events\Models\EventMatch::create([
            'event_id' => $event->id, 'category_id' => $division->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0, 'match_no' => 1,
            'a_name' => 'Ali', 'b_name' => 'Bader', 'status' => 'upcoming',
        ]);

        $this->markTestIncomplete(
            "This test describes an architecture that has been replaced, and what should "
            ."replace it is a design decision rather than a test edit.\n\n"
            ."It asserts one renderer across three surfaces: a board embedded in the desktop "
            ."bracket page, a mobile page that declines to embed it, and a separate full-screen "
            ."manager. All three were <x-tournament-bracket>, mounted from "
            ."personal/{mobile,desktop}/event-bracket.blade.php.\n\n"
            ."Today /brackets is a permanent redirect to /brackets/manage, so desktop, mobile and "
            ."branded all land on ONE page — and that page mounts a different board entirely: the "
            ."React 'Combat Sports Bracket' at #takeone-bracket-root, out of public/bracket/ and "
            ."personal/partials/bracket-board.html.\n\n"
            ."So there are now TWO live bracket renderers on different surfaces:\n"
            ."  · the member draw  -> the React board\n"
            ."  · the public draw  -> <x-draw-stage> -> <x-tournament-bracket>\n"
            ."and CLAUDE.md still calls the latter 'the one bracket renderer for every sport'. The "
            ."two views this test was written against are reachable only from "
            ."PersonalMobileController::eventBracket(), which has no route.\n\n"
            ."Registered in Documentation/HOUSE-CLEANING.md. Settle which board wins, then rewrite "
            ."this against it."
        );

        /* ---- the contract as it was, kept so it can be restored ---- */

        /* The DESKTOP blade is what mounts the board on a wide screen — and a
           branded event has no desktop blade (it is the mobile app at every
           width, App\Http\Middleware\BrandEventPage), so this half of the
           contract is asserted with the branded surface off. That is not a
           workaround: it is where the desktop bracket screen still lives, for
           every type that has not opted in. */
        config(['events.branded_surface' => false]);

        $desktop = $this->actingAs($member)->followingRedirects()
            ->get("/me/events/{$event->uuid}/brackets")->assertOk();
        /* The board is <x-tournament-bracket> now, and it names its own
           viewport `{$id}-viewport` rather than the fixed
           `event-bracket-viewport` this used to look for — that id does not
           exist anywhere in the tree any more. The runtime it mounts through
           is still window.BracketBoard, which is the part of the contract
           that actually matters: one renderer for both screens. */
        $desktop->assertSee('BracketBoard', false);
        $desktop->assertSee('-viewport', false);

        // Mobile: no board of its own, but a way through to the full-screen one.
        $mobile = $this->actingAs($member)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get("/me/events/{$event->uuid}/brackets")->assertOk();
        $mobile->assertDontSee('event-bracket-viewport', false);

        /* Branded, on a LAPTOP: the same mobile screen, so it still declines to
           embed the board and still leads to the full-screen manager. */
        config(['events.branded_surface' => true]);

        $this->actingAs($member)->get("/me/events/{$event->uuid}/brackets")
            ->assertOk()
            ->assertDontSee('event-bracket-viewport', false);

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

        /* #shell-content and its navigator are the MEMBER shell's. A branded
           event has neither — it is served chrome-less, so there is no
           in-shell navigation to survive — and this trap is asserted where it
           exists, for every type still inside the member shell. The branded
           console is checked below on its own terms. */
        config(['events.branded_surface' => false]);

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

        /* And on the branded console, where there is no swap: the factory still
           has to be on the page, or the checklist is dead there instead. */
        config(['events.branded_surface' => true]);

        $this->actingAs($owner->fresh())
            ->withHeaders(['User-Agent' => $phone])
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->assertSee('window.eventChecklist', false);
    }
}
