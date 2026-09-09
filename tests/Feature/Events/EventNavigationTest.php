<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use Tests\TestCase;

/**
 * Navigation between an event's two faces.
 *
 * An event is reachable at `/me/events/{uuid}` (the platform, branded
 * chrome-less for the martial-arts packages) and at `/e/{uuid}` (the poster a
 * stranger is sent, plus the sealed mirror under `/e/{uuid}/admin`). Each
 * surface is internally coherent; every defect these tests pin was at a
 * CROSSING between them, and all five were found by a navigation audit on
 * 2026-09-08.
 *
 * They are one file because they are one class of bug: a control that names a
 * destination it does not go to, or that goes nowhere a reader can leave.
 */
class EventNavigationTest extends TestCase
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
            'entry_mode' => 'public',
        ], $attrs));
    }

    /**
     * ⚠️ A 404 must never redirect to itself.
     *
     * An organiser sitting on a sealed page who switches the public page off
     * makes that very page 404; a reload sends it as its own referer, and
     * `back()` sent them there again — forever, with no address bar in an
     * installed app.
     */
    public function test_a_missing_page_never_redirects_to_itself(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club, ['entry_mode' => 'members']);

        $here = url("/e/{$event->uuid}/admin/manage");

        $response = $this->actingAs($owner->fresh())
            ->withHeaders(['referer' => $here])
            ->get($here);

        $this->assertTrue($response->isRedirect(), 'expected a redirect away from the missing page');
        $this->assertNotSame(
            rtrim($here, '/'),
            rtrim((string) $response->headers->get('Location'), '/'),
            'the 404 answered with itself — that is an infinite loop'
        );
    }

    /** A 404 reached by a link still returns to the page that linked to it. */
    public function test_a_missing_page_still_goes_back_where_it_came_from(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        $from = url("/me/events/{$event->uuid}");

        $this->actingAs($owner->fresh())
            ->withHeaders(['referer' => $from])
            ->get("/me/events/{$event->uuid}/no-such-page")
            ->assertRedirect($from);
    }

    /**
     * The sealed mirror must rewrite the addresses `@js(route(…))` writes.
     *
     * Laravel escapes forward slashes in JSON, so `@js(route('me.events.checklist.store'))`
     * lands in the page as `http:\/\/host\/me\/events\/{uuid}\/checklist` — and a
     * fixed-string map keyed on the plain form saw none of them. Sixteen of the
     * console's own write endpoints still pointed at the platform from inside
     * the event app.
     *
     * Asserted against App\Events\Support\PublicEventSkin::rewriteBody()
     * directly rather than through a rendered `/e/{uuid}/admin/…` page, and
     * deliberately: `SealedEventRoutes::$registered` is a PROCESS static while
     * the test harness builds a fresh router for every test, so the mirror is
     * registered by whichever test dispatches first and is absent from every
     * one after it. A test that renders the sealed page therefore passes alone
     * and fails in a suite — it would be measuring the harness, not the seal.
     * (That static is worth revisiting; it is not what this test is for.)
     */
    public function test_the_seal_rewrites_the_addresses_js_writes_escaped(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        $skin = app(\App\Events\Support\PublicEventSkin::class);

        $plain = url("/me/events/{$event->uuid}/checklist");
        $escaped = str_replace('/', '\\/', $plain);

        // The shape @js() actually emits.
        $html = "<script>fetch('{$escaped}', {});</script><a href=\"{$plain}\">x</a>";

        $out = $skin->rewriteBody($html, $event);

        $this->assertStringNotContainsString($escaped, $out, 'the JSON-escaped address survived the seal');
        $this->assertStringNotContainsString($plain, $out, 'the plain address survived the seal');

        $sealedEscaped = str_replace('/', '\\/', url("/e/{$event->uuid}/admin/checklist"));

        $this->assertStringContainsString($sealedEscaped, $out, 'the escaped address was not moved into the sealed space');
        $this->assertStringContainsString(url("/e/{$event->uuid}/admin/checklist"), $out);
    }

    /** "View public page" opens the public page — and only when there is one. */
    public function test_the_consoles_view_public_control_opens_the_poster(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);

        $published = $this->event($owner, $club);

        $this->actingAs($owner->fresh())
            ->get("/me/events/{$published->uuid}/manage")
            ->assertOk()
            ->assertSee(route('events.public', ['event' => $published->uuid]), false);

        // No poster → no preview control at all, rather than one that lies.
        $private = $this->event($owner, $club, ['entry_mode' => 'members', 'title' => 'Closed']);

        $this->actingAs($owner->fresh())
            ->get("/me/events/{$private->uuid}/manage")
            ->assertOk()
            ->assertDontSee(__('personal.event_manage_view_public'), false);
    }

    /**
     * The crossing closes: a reader sent from the platform into a public
     * section page can get back to the platform.
     */
    public function test_a_section_page_entered_from_the_platform_goes_back_to_it(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $member = $this->member($club);

        $this->actingAs($member)
            ->get("/e/{$event->uuid}/participants?from=me")
            ->assertOk()
            ->assertSee(route('me.events.show', ['event' => $event->uuid]), false);

        // The stranger's normal path is unchanged: back to the poster.
        $this->actingAs($member)
            ->get("/e/{$event->uuid}/participants")
            ->assertOk()
            ->assertSee(route('events.public', ['event' => $event->uuid]), false)
            ->assertDontSee(route('me.events.show', ['event' => $event->uuid]), false);
    }

    /**
     * `from` is a KEY, not a destination — and it is only honoured for somebody
     * who could open the page it names. A signed-out stranger forging it must
     * not be handed a Back button that answers with a login form.
     */
    public function test_a_forged_from_key_falls_back_to_the_poster(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);

        $this->get("/e/{$event->uuid}/participants?from=me")
            ->assertOk()
            ->assertDontSee(route('me.events.show', ['event' => $event->uuid]), false);
    }

    /**
     * The board is `assertVisible` only, so an ordinary member opens it — and
     * its one control used to be the console, which answers 403 and bounces
     * them off the event entirely.
     */
    public function test_the_boards_only_control_opens_for_whoever_is_reading_it(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->event($owner, $club);
        $member = $this->member($club);

        // The competitor: back to the event page, which they may open.
        $this->actingAs($member)
            ->get("/me/events/{$event->uuid}/board")
            ->assertOk()
            ->assertSee(route('me.events.show', ['event' => $event->uuid]), false);

        $this->actingAs($member)
            ->get(route('me.events.show', ['event' => $event->uuid]))
            ->assertOk();

        // The organiser keeps the console, exactly as before.
        $this->actingAs($owner->fresh())
            ->get("/me/events/{$event->uuid}/board")
            ->assertOk()
            ->assertSee(route('me.events.manage', ['event' => $event->uuid]), false);
    }
}
