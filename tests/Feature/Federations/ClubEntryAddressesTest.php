<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * "Register your club" at the event's own addresses (ClubEntryController):
 * one address per screen, the same shared page for every host, opened at its
 * step — and never a door for anybody who may not read the event.
 */
class ClubEntryAddressesTest extends TestCase
{
    private const PATHS = [1 => 'rules', 2 => 'club', 3 => 'club/athletes', 4 => 'club/review', 5 => 'club/done', 6 => 'club/entries'];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'federations.enabled' => true, 'federations.platform_events' => true, 'federations.invite_v2' => true,
            'events.unified_page' => true, 'events.club_entry' => true,
        ]);
        $this->owner = $this->createUser();
    }

    private function federationEvent(): ClubEvent
    {
        $fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $fed->save();
        $fe = (new FederationEvent)->forceFill([
            'federation_id' => $fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $fe->save();

        return app(FederationPlatformEvent::class)->sync($fe->fresh('federation'));
    }

    public function test_every_screen_has_its_own_address_on_the_one_shared_page(): void
    {
        $ev = $this->federationEvent();

        foreach (self::PATHS as $n => $path) {
            $this->actingAs($this->owner)->get('/me/events/'.$ev->uuid.'/'.$path)
                ->assertOk()
                ->assertSee('clubInvite({&quot;initial&quot;:'.$n.',', false)
                ->assertSee('federation-store.platform.js', false);
        }
    }

    public function test_a_member_who_may_not_read_the_event_is_not_let_in_by_any_screen(): void
    {
        $ev = $this->federationEvent();
        $stranger = $this->createUser();

        foreach (self::PATHS as $path) {
            // (A signed-in browser's 404 is turned into a redirect home by the
            // global handler; the JSON door shows the real answer.)
            $this->actingAs($stranger)->getJson('/me/events/'.$ev->uuid.'/'.$path)->assertNotFound();
        }
    }

    public function test_a_club_hosted_event_has_no_such_screens_yet(): void
    {
        // Club-hosted events have their own screens only with EVENT_CLUB_ENTRY_PLATFORM (PlatformClubEntryTest).
        config(['events.club_entry_platform' => false]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $ev = ClubEvent::create(['tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Club Open',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'internal', 'status' => 'active',
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00']);

        $this->actingAs($owner)->getJson('/me/events/'.$ev->uuid.'/rules')->assertNotFound();
    }

    public function test_switched_off_the_addresses_do_not_exist(): void
    {
        $ev = $this->federationEvent();
        config(['events.club_entry' => false]);

        $this->actingAs($this->owner)->getJson('/me/events/'.$ev->uuid.'/club')->assertNotFound();
    }

    public function test_with_the_screens_off_the_invitation_keeps_its_in_page_steps(): void
    {
        $ev = $this->federationEvent();
        $fe = FederationEvent::where('club_event_id', $ev->id)->first();
        config(['events.club_entry' => false]);

        $this->get('/federation/app/Club%20Invitation.dc.html?event='.$fe->uuid)
            ->assertOk()->assertSee('clubInvite()', false);
    }

    private function invitationOf(ClubEvent $ev): string
    {
        return '/federation/app/Club%20Invitation.dc.html?event='.FederationEvent::where('club_event_id', $ev->id)->value('uuid');
    }

    public function test_the_invitation_link_lets_a_signed_out_club_read_the_event_and_register(): void
    {
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members'])->save();   // not published

        // Without the invitation, a stranger is sent to sign in.
        $this->get('/me/events/'.$ev->uuid)->assertRedirect();
        $this->assertNotSame(200, $this->get('/me/events/'.$ev->uuid.'/rules')->getStatusCode());

        // The invitation forwards to the event's own page and carries a pass.
        $this->get($this->invitationOf($ev))->assertRedirect('/me/events/'.$ev->uuid.'#cover');

        $this->get('/me/events/'.$ev->uuid)->assertOk()
            ->assertSee('/me/events/'.$ev->uuid.'/rules', false)       // Register your club
            ->assertDontSee('/me/events/'.$ev->uuid.'/participants', false);   // no doors the pass does not open
        foreach (self::PATHS as $path) {
            $this->get('/me/events/'.$ev->uuid.'/'.$path)->assertOk();
        }
    }

    public function test_the_pass_opens_only_the_event_page_and_its_steps(): void
    {
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members'])->save();
        $this->get($this->invitationOf($ev));

        foreach (['participants', 'brackets/manage', 'officiating', 'gallery', 'results'] as $section) {
            $this->assertNotSame(200, $this->get('/me/events/'.$ev->uuid.'/'.$section)->getStatusCode(), $section);
        }
    }

    public function test_a_pass_for_one_event_opens_no_other(): void
    {
        $a = $this->federationEvent();
        $b = $this->federationEvent();
        $a->forceFill(['entry_mode' => 'members'])->save();
        $b->forceFill(['entry_mode' => 'members'])->save();

        $this->get($this->invitationOf($a));

        $this->get('/me/events/'.$a->uuid)->assertOk();
        $this->get('/me/events/'.$b->uuid)->assertRedirect();
        $this->assertNotSame(200, $this->get('/me/events/'.$b->uuid.'/rules')->getStatusCode());
    }

    public function test_continuing_from_the_rules_records_the_acceptance_and_a_changed_rule_book_asks_again(): void
    {
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members', 'requirements' => ['Bring a passport.']])->save();
        $this->get($this->invitationOf($ev));   // signed out, with the pass

        $this->get('/me/events/'.$ev->uuid.'/rules')->assertOk()->assertSee('&quot;consented&quot;:false', false);

        $this->postJson('/me/events/'.$ev->uuid.'/consent')->assertOk()->assertJsonPath('success', true);
        $row = \App\Events\Models\EventEntryConsent::sole();
        $this->assertSame($ev->id, $row->event_id);
        $this->assertNull($row->user_id);
        $this->assertCount(2, $row->statements);       // the host's own two statements, not the request's

        $this->get('/me/events/'.$ev->uuid.'/rules')->assertSee('&quot;consented&quot;:true', false);

        // The organiser changes the rules: the acceptance no longer counts.
        $ev->forceFill(['requirements' => ['Bring a passport.', 'Weigh-in is at 8:00.']])->save();
        $this->get('/me/events/'.$ev->uuid.'/rules')->assertSee('&quot;consented&quot;:false', false);
    }

    public function test_nobody_without_a_way_in_can_record_an_acceptance(): void
    {
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members'])->save();

        $this->postJson('/me/events/'.$ev->uuid.'/consent')->assertNotFound();
        $this->actingAs($this->createUser())->postJson('/me/events/'.$ev->uuid.'/consent')->assertNotFound();
        $this->assertSame(0, \App\Events\Models\EventEntryConsent::count());
    }

    public function test_only_the_organiser_reads_the_acceptances_over_mcp(): void
    {
        $ev = $this->federationEvent();
        $this->get($this->invitationOf($ev));
        $this->postJson('/me/events/'.$ev->uuid.'/consent')->assertOk();

        $call = function (\App\Members\Models\User $as) use ($ev) {
            $this->actingAs($as);
            $d = json_decode((string) (new \App\Mcp\Tools\ListEventEntryConsentsTool)->handle(new \Laravel\Mcp\Request(['event' => $ev->uuid]))->content(), true);

            return is_array($d) ? $d : ['error' => true];
        };

        $this->assertSame(1, $call($this->owner)['count']);              // the federation's owner runs the event
        $this->assertArrayHasKey('error', $call($this->createUser()));    // a stranger is told nothing
    }

    public function test_a_link_reader_gets_the_preview_and_a_person_gets_the_event(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members'])->save();

        // WhatsApp: the preview tags themselves — no forward, no login page.
        $html = $this->withHeaders(['User-Agent' => 'WhatsApp/2.24.20.12 A'])
            ->get($this->invitationOf($ev))->assertOk()->getContent();
        $this->assertStringContainsString('og:title" content="National Karate Cup', $html);
        $this->assertStringContainsString('/me/events/'.$ev->uuid.'/share-card.jpg', $html);

        // The drawn picture is served (no artwork: colour, name, "Tap to enroll").
        $this->get('/me/events/'.$ev->uuid.'/share-card.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // A person is forwarded to the event page with the pass, as before.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($this->invitationOf($ev))
            ->assertRedirect('/me/events/'.$ev->uuid.'#cover');
    }

    public function test_the_card_is_not_served_for_an_unpublished_event_that_takes_no_invitations(): void
    {
        // Club-hosted events have their own screens only with EVENT_CLUB_ENTRY_PLATFORM (PlatformClubEntryTest).
        config(['events.club_entry_platform' => false]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $ev = ClubEvent::create(['tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Private Club Open',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'internal', 'status' => 'active',
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00', 'entry_mode' => 'members']);

        $this->get('/me/events/'.$ev->uuid.'/share-card.jpg')->assertNotFound();
    }

    public function test_the_event_address_itself_unfurls_for_a_link_reader_and_its_share_hands_on_the_invitation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $ev = $this->federationEvent();
        $ev->forceFill(['entry_mode' => 'members'])->save();

        // WhatsApp on the bare event address: the preview, not the login page.
        $this->withHeaders(['User-Agent' => 'WhatsApp/2.24.20.12 A'])->get('/me/events/'.$ev->uuid)
            ->assertOk()->assertSee('og:title" content="National Karate Cup', false);

        // A person without the invitation is still sent to sign in.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get('/me/events/'.$ev->uuid)->assertRedirect();

        // With it, the page's Share hands on the invitation link, which opens without an account.
        $fe = FederationEvent::where('club_event_id', $ev->id)->first();
        $this->get($this->invitationOf($ev));
        $this->get('/me/events/'.$ev->uuid)->assertOk()
            ->assertSee(str_replace('/', '\\/', 'Club%20Invitation.dc.html?event='.$fe->uuid), false);
    }
}
