<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventOfficial;
use Tests\TestCase;

/**
 * The participant card on the public entry list — /e/{uuid}/participants.
 *
 * Ported from drafts/participant-card.html on 2026-09-16 and used as drawn. The
 * tests here are about the two things the port can get wrong and nothing else
 * can catch:
 *
 *   1. WHAT THE CARD DISCLOSES. The design carries a payment / weigh-in pill
 *      and a body weight. This page is the public poster — anybody with the
 *      link, signed in or not — and CLAUDE.md scopes both facts to the
 *      organiser, the officials who sign them, and the athlete's own row. The
 *      card therefore renders those two rows for those readers and ABSENTS them
 *      for everyone else. A greyed badge would not do: it still says there is
 *      something to grey.
 *
 *   2. WHERE THE CARD GOES. This surface is a sealed app and
 *      `events.public.section` is not a mirrored route, so nothing rewrites a
 *      platform URL out of this response. Every destination has to be sealed at
 *      the point it is written, or one tap walks the reader out of the event.
 */
class ParticipantCardTest extends TestCase
{
    private function club(User $owner): Tenant
    {
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Emperor Taekwondo']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(User $creator, Tenant $club): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $creator->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
            'entry_mode' => 'public',
        ]);
    }

    /** An entrant with everything the card can draw. */
    private function entrant(ClubEvent $event, Tenant $club, string $name, array $attrs = []): ClubEventRegistration
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(34)->toDateString(),
            'nationality' => 'BH',
            'is_discoverable' => true,
        ]);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $division = EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Group C (-60) Adult'],
            ['sort_order' => 1],
        );

        return ClubEventRegistration::create(array_merge([
            'event_id' => $event->id, 'tenant_id' => $club->id, 'user_id' => $user->id,
            'category_id' => $division->id,
            'role' => 'participant', 'status' => 'joined',
            'weight' => 72.4, 'paid' => true, 'registered_at' => now(),
            'belt_colour' => 'white',
        ], $attrs));
    }

    /* ===================== What it discloses ===================== */

    public function test_a_stranger_sees_the_card_but_neither_the_readiness_nor_the_weight(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $html = $this->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        // The card itself is there, with everything a public entry list says.
        $this->assertStringContainsString('class="pcard"', $html);
        $this->assertStringContainsString('Mohammed Ali Saloom', $html);
        $this->assertStringContainsString('Emperor Taekwondo', $html);

        // …and neither of the two withheld facts, in any form.
        $this->assertStringNotContainsString('class="status"', $html, 'a stranger was shown who has paid');
        $this->assertStringNotContainsString('data-entry=', $html, 'a stranger was handed a registration id');
        $this->assertStringNotContainsString('officiating: true', $html, 'a stranger was shipped the verification desk');
        $this->assertStringNotContainsString('"reg_id":', str_replace('\u0022', '"', $html));
        $this->assertStringNotContainsString('72.4', $html, 'a stranger was shown an athlete\'s body weight');
        /* The CARD carries no state either. Asked of the <article> itself
           rather than of the page: `data-status` appears in the stylesheet's
           own selectors, which is not a disclosure and must not read as one. */
        $this->assertDoesNotMatchRegularExpression('/<article[^>]*data-status/', $html);
    }

    public function test_the_organiser_sees_the_readiness_pill_and_the_weight(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $html = $this->actingAs($owner->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        /* TWO badges, each coloured by its own fact (split 2026-09-16). Paid is
           settled, so it is green; the scale has not happened, so that one is
           amber. Each badge is ONE SHORT WORD naming its subject — a long
           sentence on this line warps the card — with the state carried by the
           colour AND by the icon, never by colour alone. */
        $this->assertSame(2, substr_count($html, 'class="status"'));

        $this->assertMatchesRegularExpression(
            '/data-status="ready"[^>]*>\s*<span><i class="bi bi-check-circle-fill"[^>]*><\/i>'
            .preg_quote(__('personal.event_card_paid'), '/').'<\/span>/',
            $html,
            'a settled fee is a green tick beside the word Paid',
        );
        $this->assertMatchesRegularExpression(
            '/data-status="paid"[^>]*>\s*<span><i class="bi bi-hourglass-split"[^>]*><\/i>'
            .preg_quote(__('personal.event_card_weighed'), '/').'<\/span>/',
            $html,
            'a weigh-in that has not happened is an amber hourglass beside the word Weighed',
        );

        /* The sentence is not lost, it is just not drawn: a pointer and a
           screen reader still get it. */
        $this->assertStringContainsString(
            'title="'.__('personal.event_card_awaiting_weigh_in').'"',
            $html,
        );
        $this->assertStringNotContainsString(
            '>'.__('personal.event_card_awaiting_weigh_in').'<',
            $html,
            'the long wording must not be drawn on the card',
        );

        $this->assertStringContainsString('72.4', $html);
    }

    public function test_an_appointed_official_sees_it_too(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $scale = $this->createUser();
        $scale->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $scale->id,
            'role' => EventOfficial::ROLE_WEIGH_IN, 'compensation' => 'volunteer',
        ]);

        $this->actingAs($scale->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()
            ->assertSee('data-status="paid"', false);
    }

    /**
     * An ordinary competitor reads their OWN row and nobody else's — "have I
     * paid?" is a question they must be able to answer without phoning anyone.
     */
    public function test_a_competitor_sees_their_own_readiness_and_not_their_rivals(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        $mine = $this->entrant($event, $club, 'My Own Name', ['paid' => false]);
        $this->entrant($event, $club, 'Rival Athlete', ['weight' => 91.5]);

        $html = $this->actingAs($mine->user->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $this->assertStringContainsString('data-status="pending"', $html, 'a competitor cannot see their own unpaid fee');
        // Their own two badges and nobody else's.
        $this->assertSame(2, substr_count($html, 'class="status"'), 'exactly one card wearing badges: their own');
        $this->assertStringNotContainsString('91.5', $html, 'a competitor was shown a rival\'s body weight');
    }

    /**
     * However many cards, ONE stylesheet, ONE lightbox, ONE runtime.
     *
     * The card carries its own (CLAUDE.md → Components Are Standalone), and a
     * component that emitted them per row would put its stylesheet on the page
     * two hundred times at a real championship.
     */
    public function test_the_card_ships_its_singletons_once_per_page(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        foreach (range(1, 3) as $i) {
            $this->entrant($event, $club, 'Athlete '.$i);
        }

        $html = $this->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'class="pcard"'));
        $this->assertSame(1, substr_count($html, 'id="lightbox"'));
        $this->assertSame(1, substr_count($html, '.pcard .gbar'), 'the stylesheet was emitted more than once');
        $this->assertSame(1, substr_count($html, 'window.openLightbox'), 'the runtime was emitted more than once');
    }

    /* ===================== Where it goes ===================== */

    public function test_every_destination_on_the_card_stays_inside_the_event(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $html = $this->actingAs($owner->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $plain = str_replace('\/', '/', $html);

        // The profile is the SEALED one, and the platform's is nowhere on the page.
        $this->assertStringContainsString(
            route('sealed.people.show', ['event' => $event->uuid, 'uuid' => $entry->user->uuid]),
            $plain,
        );
        $this->assertStringNotContainsString(
            route('people.show', ['uuid' => $entry->user->uuid]).'"',
            $plain,
            'a card linked out of the sealed event app',
        );

        /* Nothing on the card navigates to the platform. The organiser's sheet
           does FETCH two platform endpoints — the group list and the write that
           saves it — and that is right: a fetch stays on the page it was made
           from. What must never appear is a platform address a tap could
           follow. (The division chip's own destination is covered by the draw
           test below, which is where the draw is actually out.) */
        $this->assertStringNotContainsString(
            'location.assign(\''.url('/me/events'),
            $plain,
            'a tap on this card would leave the sealed event app',
        );
    }

    /**
     * A card with nowhere to go is not a door. `sealed.people.show` answers a
     * guest with 404 for a member who is not discoverable, so the card is not
     * given a destination it cannot open.
     */
    public function test_a_guest_gets_no_link_to_a_profile_that_would_refuse_them(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Private Person');
        $entry->user->update(['is_discoverable' => false]);

        $html = $this->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $this->assertStringContainsString('Private Person', $html);
        $this->assertStringNotContainsString($entry->user->uuid.'"', str_replace('\/', '/', $html));
    }

    /** The organiser's sheet is not shipped to anybody else's browser. */
    public function test_the_organiser_sheet_is_absent_for_everyone_else(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $this->entrant($event, $club, 'Mohammed Ali Saloom');

        /* The guest FIRST: `actingAs()` holds for the rest of the test, so a
           signed-out request made after a signed-in one is still signed in —
           and this assertion would pass by never being a guest at all. */
        $this->get("/e/{$event->uuid}/participants")->assertOk()
            ->assertDontSee('id="participant-sheet"', false)
            ->assertDontSee(__('personal.event_card_action_enroll'), false);

        $this->actingAs($owner->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()
            ->assertSee('id="participant-sheet"', false);
    }

    /* ===================== Coming back, and the draw ===================== */

    /**
     * A participant's card leads to the profile with `?from=participants`, so
     * the profile's own back control returns to THIS list rather than to the
     * poster or the organiser's entry list.
     */
    public function test_a_participants_card_carries_the_back_key(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $reader = $this->createUser();
        $reader->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $html = str_replace('\/', '/', $this->actingAs($reader->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent());

        $this->assertStringContainsString(
            route('sealed.people.show', ['event' => $event->uuid, 'uuid' => $entry->user->uuid]).'?from=participants',
            $html,
        );

        // …and that key is what the profile reads to find its way back.
        $this->actingAs($reader->fresh())
            ->get(route('sealed.people.show', ['event' => $event->uuid, 'uuid' => $entry->user->uuid]).'?from=participants')
            ->assertOk()
            ->assertSee(route('events.public.section', ['event' => $event->uuid, 'section' => 'participants']), false);
    }

    /**
     * ⚠️ THE ORGANISER'S OWN PATH OUT CARRIES THE SAME KEY.
     *
     * Their card tap opens the sheet rather than the profile, so the profile
     * link is built in JavaScript — and it shipped without `?from=participants`
     * while the participant's card had it. `sealedBackUrl` then fell through to
     * its default for somebody who manages the event, and an organiser who
     * tapped a name on THIS list was sent back to a DIFFERENT one, the admin
     * entry list at /admin/people (reported 2026-09-16).
     *
     * Two doors to one page, and only one of them was wired: exactly the shape
     * of mistake a test that only ever checks the common path misses.
     */
    public function test_the_organiser_sheet_returns_to_this_list_too(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $html = str_replace('\/', '/', $this->actingAs($owner->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent());

        $profile = route('sealed.people.show', ['event' => $event->uuid, 'uuid' => '__UUID__']);

        $this->assertStringContainsString($profile.'?from=participants', $html);
        /* The bare address must not be there at all: it is the one that sends
           them somewhere else. */
        $this->assertStringNotContainsString($profile."'", $html);

        // …and the key does what it says for a manager, who is the reader whose
        // default is different.
        $this->actingAs($owner->fresh())
            ->get(route('sealed.people.show', ['event' => $event->uuid, 'uuid' => $entry->user->uuid]).'?from=participants')
            ->assertOk()
            ->assertSee(route('events.public.section', ['event' => $event->uuid, 'section' => 'participants']), false)
            ->assertDontSee(url('/e/'.$event->uuid.'/admin/people'), false);
    }

    /**
     * "Open verification page" opens THAT PERSON, not the list they are on.
     *
     * The desk stopped being a screen of its own on 2026-09-03 and became a
     * sheet over the entry list, addressed by `?entry=<registration>`. The card
     * used to go and ASK for that id over the network when tapped, and every
     * way that request could disappoint — a redirect, a slow answer, a response
     * that was not JSON — fell through to the bare list. The organiser tapped
     * "verification page" and got the list of everybody who has joined
     * (reported 2026-09-16), which is the one place the action must not land.
     *
     * So the id travels with the page, and this pins BOTH HALVES of the
     * handshake: the card hands over an id, and the desk page has a row
     * carrying exactly that id for deepLink() to find. Either half alone proves
     * nothing — the previous bug was two correct halves that never met.
     */
    public function test_the_verification_action_addresses_one_person(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');
        $other = $this->entrant($event, $club, 'Someone Else');

        $html = $this->actingAs($owner->fresh())
            ->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        // The card carries the entry, so the tap is a plain navigation.
        $this->assertStringContainsString('data-entry="'.$entry->id.'"', $html);
        $this->assertStringContainsString('data-entry="'.$other->id.'"', $html);

        /* The DESK IS ON THIS PAGE. It is not navigated to any more: the
           sheet is hosted here and the tap dispatches `open-verification`, the
           same event the member list's own action menu uses.

           Asked of the DECODED payload, because @js() escapes every quote and
           an assertion against the raw HTML would pass or fail for the wrong
           reason. */
        $gates = str_replace('\u0022', '"', $html);

        $this->assertStringContainsString('"'.$entry->id.'":{"reg_id":'.$entry->id, $gates,
            'the page has no gate for the entry its own card hands over');
        $this->assertStringContainsString('"'.$other->id.'":{"reg_id":'.$other->id, $gates);
        $this->assertStringContainsString('open-verification', $html,
            'the card no longer opens the sheet it is standing next to');
        $this->assertStringContainsString('officiating: true', $html,
            'the sheet did not render for an organiser');

        /* And it does NOT leave the page to do it. */
        $this->assertStringNotContainsString('/admin/people?entry=', str_replace('\/', '/', $html));
    }

    /**
     * A division chip is a door only when the organiser has let the draw out.
     *
     * `draw_reveal = hidden` is their switch; until it is thrown, the chip says
     * WHEN rather than landing a reader on an empty board.
     */
    public function test_a_division_chip_opens_the_draw_only_when_the_draw_is_out(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        /* Four entrants and a cut bracket: "the draw is out" means there is
           one, not merely that a group exists. A chip that opened an empty
           board would be the dead end this gate exists to prevent. */
        foreach (range(1, 4) as $i) {
            $this->entrant($event, $club, 'Athlete '.$i);
        }
        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');

        $event->update(['draw_reveal' => ClubEvent::DRAW_HIDDEN]);

        $html = $this->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $this->assertStringContainsString('onDrawClosed()', $html, 'a withheld draw must not be linked to');
        $this->assertStringNotContainsString('onDivisionTap(this)', $html);

        /* And the other way round. The chip is wired the moment the draw is out
           — the same question the draw PAGE answers, so the two cannot
           disagree about whether there is anything to read. */
        /* No cache to clear: PublicEvent::draw() asks EventAccess BEFORE it
           consults its own memo, so a withheld draw never got memoised. */
        $event->update(['draw_reveal' => ClubEvent::DRAW_ALWAYS]);

        $html = $this->get("/e/{$event->uuid}/participants")->assertOk()->getContent();

        $this->assertStringContainsString('onDivisionTap(this)', $html);
    }

    /* ===================== The enrolment door ===================== */

    public function test_the_groups_door_answers_the_organiser_with_compatibility_worked_out(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');

        // A group this 34-year-old man cannot be in.
        EventCategory::create([
            'event_id' => $event->id, 'name' => 'Group B (-60) Youth',
            'sort_order' => 2, 'min_age' => 10, 'max_age' => 15,
        ]);

        $groups = $this->actingAs($owner->fresh())
            ->getJson("/me/events/{$event->uuid}/people/{$entry->user->uuid}/divisions")
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('groups');

        $this->assertCount(2, $groups);

        $mine = collect($groups)->firstWhere('label', 'Group C (-60) Adult');
        $this->assertTrue($mine['enrolled']);
        $this->assertTrue($mine['compatible']);
        $this->assertSame($entry->id, $mine['registration']);

        $youth = collect($groups)->firstWhere('label', 'Group B (-60) Youth');
        $this->assertFalse($youth['enrolled']);
        $this->assertFalse($youth['compatible'], 'a 34-year-old is not in the youth group');
        $this->assertNotNull($youth['reason']);
        // Still offered: an organiser may put somebody up a category deliberately.
        $this->assertArrayHasKey('id', $youth);
    }

    public function test_the_groups_door_refuses_everyone_but_whoever_runs_the_event(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $entry = $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())
            ->getJson("/me/events/{$event->uuid}/people/{$entry->user->uuid}/divisions")
            ->assertForbidden();

        // Their own entry is no exception: this is the draw, not their profile.
        $this->actingAs($entry->user->fresh())
            ->getJson("/me/events/{$event->uuid}/people/{$entry->user->uuid}/divisions")
            ->assertForbidden();
    }

    /** Somebody who is not in this event has no groups to describe. */
    public function test_the_groups_door_404s_for_a_person_who_did_not_enter(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $this->entrant($event, $club, 'Mohammed Ali Saloom');

        $stranger = $this->createUser();

        $this->actingAs($owner->fresh())
            ->getJson("/me/events/{$event->uuid}/people/{$stranger->uuid}/divisions")
            ->assertNotFound();
    }
}
