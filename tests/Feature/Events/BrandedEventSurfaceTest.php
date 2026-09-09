<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use Tests\TestCase;

/**
 * The event is the organiser's, on the platform's own addresses.
 *
 * A martial-arts championship is served with the platform's frame taken away
 * and the club's brand over it — on `/me/events/{uuid}` exactly as on
 * `/e/{uuid}` — so one competition has one face whichever door was used to
 * reach it (App\Http\Middleware\BrandEventPage, asked for on 2026-09-07).
 *
 * What each test here is really protecting:
 *   · the frame is gone, and the brand is the club's;
 *   · a type that has NOT opted in is untouched, byte for byte;
 *   · the addresses stay in `/me/events` — the sealed mirror only exists while
 *     the organiser's public switch is on, so a page that linked there would
 *     dead-end on an event set to `members`;
 *   · there is a way OUT, which a chrome-less page has to carry itself;
 *   · the dressing grants nothing.
 */
class BrandedEventSurfaceTest extends TestCase
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
            // Deliberately NOT public: the branded surface must stand up for an
            // event whose poster does not exist.
            'entry_mode' => 'members',
        ], $attrs));
    }

    public function test_the_three_martial_arts_tournaments_opt_in_and_nothing_else_does(): void
    {
        $registry = app(\App\Events\EventTypeRegistry::class);

        $branded = [];

        foreach ($registry->all() as $key => $type) {
            if ($type->brandedSurface()) {
                $branded[] = $key;
            }
        }

        sort($branded);

        $this->assertSame(['bjj_tournament', 'karate_tournament', 'taekwondo_tournament'], $branded);
        $this->assertFalse($registry->fallback()->brandedSurface(), 'the generic bucket must keep the member shell');
    }

    public function test_a_championship_is_served_without_the_platforms_frame(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        $html = $this->actingAs($this->member($club))
            ->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->getContent();

        // The event's own column and skin (entry/partials/skin-style).
        $this->assertStringContainsString('ev-app', $html, 'the event skin did not render');

        // And none of the platform's chrome.
        $this->assertStringNotContainsString('id="shell-content"', $html, 'the member shell is still wrapping the event');
        $this->assertStringNotContainsString('mobile-shell-nav', $html);
    }

    public function test_the_addresses_stay_on_the_platform_so_a_members_only_event_has_no_dead_link(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        foreach (['', '/manage', '/brackets', '/gallery'] as $tail) {
            $html = $this->actingAs($owner->fresh())
                ->get("/me/events/{$event->uuid}{$tail}")
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString(
                "/e/{$event->uuid}/admin",
                $html,
                "the branded page {$tail} links into the sealed mirror, which 404s while the event is not public"
            );
        }
    }

    public function test_the_chrome_less_event_page_still_carries_the_way_out(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        // With no tab bar and no drawer, Back on the event page is the ONLY
        // route out — and it has to be an address, not history.back().
        $this->actingAs($this->member($club))
            ->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertSee(route('me.events'), false);
    }

    public function test_a_type_that_has_not_opted_in_is_untouched(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club, [
            'title' => 'City League',
            'sport' => 'football',
            'event_type' => 'league',
        ]);

        $member = $this->member($club);

        $branded = $this->actingAs($member)->get("/me/events/{$event->uuid}")->assertOk()->getContent();

        config(['events.branded_surface' => false]);

        $plain = $this->actingAs($member)->get("/me/events/{$event->uuid}")->assertOk()->getContent();

        $this->assertSame(
            $this->stable($plain),
            $this->stable($branded),
            'a generic event rendered differently with the branded surface on — it must not be reached at all'
        );
    }

    public function test_the_kill_switch_puts_the_member_shell_back(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        config(['events.branded_surface' => false]);

        // On a PHONE, because #shell-content is the mobile member shell's — the
        // desktop member layout never had one.
        $html = $this->actingAs($this->member($club))
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ev-app', $html);
        $this->assertStringContainsString('id="shell-content"', $html, 'the member shell did not come back');
    }

    public function test_the_dressing_grants_nothing(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        /* The contract is not a particular status — it is that the status does
           not CHANGE. Every page is asked for twice, once dressed and once
           not, and the two answers have to match: the route keeps its
           middleware stack and the controller re-runs the same authorization,
           so the brand can only ever decide what a page LOOKS like. */
        $member = $this->member($club);
        $stranger = $this->createUser();

        foreach ([$member, $stranger, $owner->fresh()] as $viewer) {
            foreach (['', '/manage', '/people', '/brackets'] as $tail) {
                config(['events.branded_surface' => true]);
                $on = $this->actingAs($viewer)->get("/me/events/{$event->uuid}{$tail}");

                config(['events.branded_surface' => false]);
                $off = $this->actingAs($viewer)->get("/me/events/{$event->uuid}{$tail}");

                $this->assertSame(
                    $off->getStatusCode(),
                    $on->getStatusCode(),
                    "the brand changed the answer for {$tail} — it must change only the dressing"
                );
            }
        }

        // And a signed-out visitor is still sent to sign in, not shown a page.
        // (forgetGuards, because actingAs() above keeps the guard resolved.)
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get("/me/events/{$event->uuid}")->assertRedirect(route('login'));
    }

    /**
     * The band's open-the-public-page control exists only when there IS one.
     *
     * It is the fourth round control in the header row (Design Rule #6), and it
     * leaves for `/e/{uuid}` — which refuses everybody while `entry_mode` is
     * `members`. A control that lands on a refusal is a dead end, so it hangs
     * off a NULL-or-real url rather than off the assumption that a poster
     * exists.
     */
    public function test_the_band_only_offers_the_public_page_when_one_is_published(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);

        // `members` — the default in this file's helper.
        $private = $this->event($owner, $club);

        $this->actingAs($owner->fresh())
            ->get("/me/events/{$private->uuid}")
            ->assertOk()
            ->assertViewHas('publicUrl', null)
            ->assertDontSee('bi-box-arrow-up-right', false);

        $published = $this->event($owner, $club, ['entry_mode' => 'public', 'title' => 'Open Cup']);

        $this->actingAs($owner->fresh())
            ->get("/me/events/{$published->uuid}")
            ->assertOk()
            ->assertViewHas('publicUrl', route('events.public', ['event' => $published->uuid]))
            ->assertSee('bi-box-arrow-up-right', false);
    }

    /**
     * The Participants tile opens the event's own ENTRY LIST, not the
     * organiser's roster.
     *
     * Two pages, two jobs: "who is in" is a published fact about a competition,
     * while the roster carries moderation, payment state and contact details
     * and belongs to the console. Asked for on 2026-09-08.
     *
     * The fallback is the point of the second half: with no public page there is
     * nothing to open, so the tile keeps the roster rather than pointing at a
     * refusal.
     */
    public function test_the_participants_tile_opens_the_public_entry_list_when_there_is_one(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $member = $this->member($club);

        $published = $this->event($owner, $club, ['entry_mode' => 'public']);

        $this->actingAs($member)
            ->get("/me/events/{$published->uuid}")
            ->assertOk()
            ->assertSee(route('events.public.section', ['event' => $published->uuid, 'section' => 'participants']), false)
            ->assertDontSee(route('me.events.people', $published->uuid), false);

        // And that destination actually renders, for the member and the owner.
        $this->actingAs($member)
            ->get(route('events.public.section', ['event' => $published->uuid, 'section' => 'participants']))
            ->assertOk();

        // No public page → the tile keeps the roster it always had.
        $private = $this->event($owner, $club, ['title' => 'Closed Trials']);

        $this->actingAs($member)
            ->get("/me/events/{$private->uuid}")
            ->assertOk()
            ->assertSee(route('me.events.people', $private->uuid), false);
    }

    /**
     * Moving that tile must not cost the organiser their roster: it is a row of
     * the console, and that is the door it keeps.
     */
    public function test_the_console_still_carries_the_organisers_roster(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club, ['entry_mode' => 'public']);

        $this->actingAs($owner->fresh())
            ->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->assertSee(route('me.events.people', $event->uuid), false);
    }

    /** Strip the values that differ between any two renders of one page. */
    private function stable(string $html): string
    {
        return preg_replace(
            ['/name="csrf-token" content="[^"]*"/', '/_token: "[^"]*"/', '/qr_[A-Za-z0-9]{6}/'],
            ['csrf', 'token', 'qr'],
            $html
        );
    }
}
