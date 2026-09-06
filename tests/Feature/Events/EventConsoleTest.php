<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventOfficial;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The event console — /me/events/{uuid}/manage.
 *
 * The rule this file protects: the event PAGE is what a visitor came to read,
 * and the CONSOLE is what the people running it came to do. A competitor must
 * never be handed organiser tooling, and an appointed official gets their job
 * and nothing else — not the money, not the danger zone.
 */
class EventConsoleTest extends TestCase
{
    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
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
            'participant_fee' => 'BHD 10',
        ], $attrs));
    }

    private function memberOf(Tenant $club): User
    {
        $user = $this->createUser();
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user->fresh();
    }

    private function official(ClubEvent $event, Tenant $club, string $role = EventOfficial::ROLE_WEIGH_IN): User
    {
        $user = $this->memberOf($club);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $user->id,
            'role' => $role, 'compensation' => 'volunteer',
        ]);

        return $user->fresh();
    }

    /* ===================== Who gets in ===================== */

    public function test_the_organiser_reaches_the_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk();
    }

    public function test_an_appointed_official_reaches_the_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $scale = $this->official($event, $club);

        $this->actingAs($scale)
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk();
    }

    public function test_an_ordinary_member_is_refused_the_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $nobody = $this->memberOf($club);

        // A browser GET to a forbidden page is rerouted, not 403'd — see the
        // "403s Redirect on Web" rule; the JSON form is the honest 403.
        $this->actingAs($nobody)->get("/me/events/{$event->uuid}/manage")->assertRedirect('/');
        $this->actingAs($nobody)->getJson("/me/events/{$event->uuid}/manage")->assertForbidden();
    }

    /* ===================== What each of them is offered ===================== */

    public function test_an_official_is_not_offered_the_money_or_the_danger_zone(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $scale = $this->official($event, $club);

        $html = $this->actingAs($scale)->get("/me/events/{$event->uuid}/manage")->assertOk()->getContent();

        // Their job is there…
        $this->assertStringContainsString('eventChecklist', $html, 'an official must still reach the preparations list');
        // The desk is a screen of its own; "who's joined" is reading only.
        $this->assertStringContainsString('/verify', $html, 'an official must still reach their verification desk');

        // …and nothing else is.
        // Assert on the CLICK BINDINGS, not the method names: cancelEvent() and
        // friends are defined in the shared Alpine root that both pages include,
        // so their mere presence proves nothing. What matters is whether anything
        // on the page calls them.
        $this->assertStringNotContainsString('@click="financeOpen=true"', $html, 'an official was offered the P&L');
        $this->assertStringNotContainsString('@click="deleteEvent()"', $html, 'an official was offered the danger zone');
        $this->assertStringNotContainsString('@click="cancelEvent()"', $html, 'an official was offered the danger zone');
        // CHANGED — '/entry-roster' is ALSO a bare URL string inside the shared
        // Alpine root (partials/event-show-script) that the console and the
        // public page both include, so its presence proves nothing either: the
        // same false positive the comment above describes for cancelEvent().
        // What matters is that no LINK offers it and the endpoint itself
        // refuses them, which is the stronger claim.
        $this->assertStringNotContainsString(
            'href="'.route('me.events.entry-roster', $event->uuid).'"',
            $html, 'an official was offered entry management');
        $this->actingAs($scale)
            ->getJson(route('me.events.entry-roster', $event->uuid))
            ->assertForbidden();
    }

    public function test_the_organiser_is_offered_the_whole_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $html = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/manage")->assertOk()->getContent();

        // CHANGED — the officials card now opens the OFFICIATING SHEET
        // (me.events.officiating), not the `/officials` JSON endpoint of the
        // same name, which rendered as raw JSON when linked. Asserting the
        // rendered href rather than a loose substring also stops a URL that
        // merely appears in the shared Alpine root from passing for a door.
        $needles = [
            '@click="financeOpen=true"',
            '@click="deleteEvent()"',
            '@click="cancelEvent()"',
            '@click="openResults()"',
            'href="'.route('me.events.entry-roster', $event->uuid).'"',
            'href="'.route('me.events.officiating', $event->uuid).'"',
            'eventChecklist',
        ];

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $html, "the console is missing: {$needle}");
        }
    }

    /* ===================== The public page stopped carrying it ===================== */

    public function test_the_public_event_page_carries_no_organiser_tooling(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // Even for the organiser — the tools live in one place now.
        $html = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}")->assertOk()->getContent();

        $this->assertStringNotContainsString('@click="financeOpen=true"', $html, 'the P&L is back on the public page');
        $this->assertStringNotContainsString('@click="deleteEvent()"', $html, 'the danger zone is back on the public page');
        $this->assertStringNotContainsString('eventChecklist', $html, 'the run-day checklist is back on the public page');

        // …replaced by one door to the console.
        $this->assertStringContainsString("/me/events/{$event->uuid}/manage", $html, 'the console is unreachable from the event page');
    }

    public function test_a_competitor_is_shown_no_door_to_the_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $nobody = $this->memberOf($club);

        $html = $this->actingAs($nobody)->get("/me/events/{$event->uuid}")->assertOk()->getContent();

        $this->assertStringNotContainsString("/me/events/{$event->uuid}/manage", $html);
    }
    /* ===================== The roster is a list, not a file ===================== */

    public function test_a_plain_member_cannot_open_a_persons_details_from_the_roster(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $competitor = $this->memberOf($club);
        $event->registrations()->create([
            'user_id' => $competitor->id, 'role' => 'participant', 'paid' => true,
        ]);

        $nobody = $this->memberOf($club);
        $html = $this->actingAs($nobody)->get("/me/events/{$event->uuid}/people")->assertOk()->getContent();

        // No tap target, and none of the detail the sheet would have shown.
        $this->assertStringNotContainsString('openPerson(', $html, 'a plain member can open the person sheet');
        $this->assertStringNotContainsString('aria-haspopup="dialog"', $html, 'a plain member was given a dialog trigger');
        $this->assertStringNotContainsString('weigh(sel)', $html, 'the weigh-in controls reached a plain member');
        $this->assertStringNotContainsString('pay(sel', $html, 'the payment controls reached a plain member');
    }

    public function test_an_official_can_open_a_persons_details_from_the_desk(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $competitor = $this->memberOf($club);
        $event->registrations()->create([
            'user_id' => $competitor->id, 'role' => 'participant', 'paid' => true,
        ]);

        $scale = $this->official($event, $club);
        $html = $this->actingAs($scale)->get("/me/events/{$event->uuid}/verify")->assertOk()->getContent();

        $this->assertStringContainsString('openPerson(', $html, 'the official lost their verification desk');
    }
    /* ===================== The bracket page shows, it does not run ===================== */

    public function test_the_public_bracket_page_is_read_only_even_for_the_organiser(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        foreach ([$organiser->fresh(), $this->memberOf($club)] as $viewer) {
            $html = $this->actingAs($viewer)->get("/me/events/{$event->uuid}/brackets")->assertOk()->getContent();

            // The board is mounted without arrange mode, and without ever being
            // handed the endpoints that move people.
            $this->assertStringNotContainsString('data-can-arrange="1"', $html, 'the public board offers arrange mode');
            // Both spellings: @json() escapes slashes, so a bare "/brackets/arrange"
            // check alone would pass even with the endpoint sitting right there.
            foreach (['/brackets/arrange', '\/brackets\/arrange', '/brackets/clear', '\/brackets\/clear'] as $endpoint) {
                $this->assertStringNotContainsString($endpoint, $html, "an editing endpoint reached the public board: {$endpoint}");
            }

            // And no organiser controls around it.
            $this->assertStringNotContainsString('/brackets/manage', $html, 'the public bracket page still links to the editor');
            $this->assertStringNotContainsString('generateNewDraw()"', $html, 'the public bracket page still offers a re-draw');
        }
    }

    public function test_the_organiser_still_reaches_the_arrangeable_board_through_the_console(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        // arrange_draw is only offered once there is something to arrange.
        EventCategory::create(['event_id' => $event->id, 'name' => 'Kids Men -30 kg', 'sort_order' => 1]);

        // The console offers it…
        $console = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/manage")->assertOk()->getContent();
        $this->assertStringContainsString('/brackets/manage', $console, 'the console lost the draw editor');

        // …and the editor itself is where arranging lives.
        $board = $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/brackets/manage")->assertOk()->getContent();
        // Escaped, because the component hands it to the client through @json().
        $this->assertStringContainsString('\/brackets\/arrange', $board, 'the draw editor cannot arrange');
    }

    public function test_a_plain_member_cannot_reach_the_draw_editor(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Kids Men -30 kg', 'sort_order' => 1]);
        $nobody = $this->memberOf($club);

        // It bounces back to the read-only board rather than opening.
        $this->actingAs($nobody)
            ->get("/me/events/{$event->uuid}/brackets/manage")
            ->assertRedirect(route('me.events.bracket', $event->uuid));
    }
}
