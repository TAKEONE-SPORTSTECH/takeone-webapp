<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventOfficial;
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
        // CHANGED — the desk stopped being a screen of its own on 2026-09-03.
        // The gates open from a person's card on the entry list, so what the
        // console owes an official is that door.
        $this->assertStringContainsString(
            'href="'.route('me.events.people', $event->uuid).'"',
            $html, 'an official must still reach the entry list, where the gates now open');

        // …and nothing else is.
        // Assert on the CLICK BINDINGS, not the method names: cancelEvent() and
        // friends are defined in the shared Alpine root that both pages include,
        // so their mere presence proves nothing. What matters is whether anything
        // on the page calls them.
        $this->assertStringNotContainsString('@click="financeOpen=true"', $html, 'an official was offered the P&L');
        $this->assertStringNotContainsString('@click="deleteEvent()"', $html, 'an official was offered the danger zone');
        $this->assertStringNotContainsString('@click="cancelEvent()"', $html, 'an official was offered the danger zone');
        /* Entry management. `me.events.entry-roster` is a JSON endpoint fetched
           by the shared Alpine root, never an href — so asserting on a LINK to
           it could only ever pass vacuously. The organiser's actual control is
           <x-event-add-person>, whose people search is unique to it; that plus
           the endpoint's own refusal is the honest pair. */
        $this->assertStringNotContainsString('entry-search', $html, 'an official was offered entry management');
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
            'entry-search',   // <x-event-add-person>, the organiser's own door into the entry list
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
        $html = $this->actingAs($scale)->get("/me/events/{$event->uuid}/people")->assertOk()->getContent();

        $this->assertStringContainsString('openPerson(', $html, 'the official lost their verification desk');
    }
    /* ===================== What a READER is handed ===================== */

    /**
     * A reader's board carries no write addresses at all.
     *
     * This used to be asserted of the separate public bracket page, for the
     * organiser as well — that page was read-only by design, so neither viewer
     * could be handed an editing endpoint on it. That page is retired: the
     * board at `brackets/manage` is the one draw screen and the organiser is
     * SUPPOSED to have the verbs on it.
     *
     * What survives, and matters more, is the half about the reader: an
     * ordinary member opening the same address gets the same board with no
     * arrange mode and with the endpoints that move people not built into
     * their page source. Authorization is re-checked on every one of those
     * endpoints regardless — this is about what is OFFERED.
     */
    public function test_a_readers_board_is_handed_no_editing_endpoints(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $html = $this->actingAs($this->memberOf($club))
            ->get("/me/events/{$event->uuid}/brackets/manage")->assertOk()->getContent();

        $this->assertStringNotContainsString('data-can-arrange="1"', $html, "a reader's board offers arrange mode");

        // Both spellings: @json() escapes slashes, so a bare "/brackets/arrange"
        // check alone would pass even with the endpoint sitting right there.
        foreach ([
            '/brackets/arrange', '\/brackets\/arrange',
            '/brackets/clear', '\/brackets\/clear',
            '/brackets/third-place', '\/brackets\/third-place',
        ] as $endpoint) {
            $this->assertStringNotContainsString($endpoint, $html, "an editing endpoint reached a reader's board: {$endpoint}");
        }
    }

    /**
     * ...but a draw the organiser is HOLDING BACK is still not readable, and an
     * empty full-screen board says nothing at all. It is answered with a page
     * that says WHEN the draw opens.
     *
     * ⚠️ IT MUST NOT REDIRECT. This used to send the reader to the bracket
     * page, and that page is now itself a redirect BACK to this one — two
     * pages pointing at each other is an endless bounce, which on a hall
     * screen nobody can stop. Pinned here for that reason: a 200 with the veil
     * on it, never a 302.
     */
    public function test_a_withheld_draw_is_answered_in_place_and_never_bounces(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Kids Men -30 kg', 'sort_order' => 1]);
        $event->update(['draw_reveal' => 'hidden']);
        $nobody = $this->memberOf($club);

        $this->actingAs($nobody)
            ->get("/me/events/{$event->uuid}/brackets/manage")
            ->assertOk()
            ->assertViewIs('personal.event-draw-hidden');
    }
}
