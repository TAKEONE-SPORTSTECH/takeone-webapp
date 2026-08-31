<?php

namespace Tests\Feature\Events;

use App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplay;
use App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayDevice;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The hall board for one mat — the payload behind the screen court screen.
 *
 * Two properties matter more than the rest and are covered first: the queue is
 * ordered closest-bout-first and shortens as results land, and the board never
 * shows anything a spectator in the room could not already see.
 */
class CourtDisplayTest extends TestCase
{
    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
            'title' => 'Bahrain National Championship',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    private function category(ClubEvent $event): EventCategory
    {
        return EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Senior Men -68 kg',
            'weight_class' => '-68 kg',
            'status' => 'live',
        ]);
    }

    /** A bout on a mat. Everything the board reads comes off this row. */
    private function bout(ClubEvent $event, EventCategory $cat, array $attrs = []): EventMatch
    {
        return EventMatch::create(array_merge([
            'event_id' => $event->id,
            'category_id' => $cat->id,
            'round' => 'Quarterfinal',
            'court' => 'Mat 1',
            'status' => 'upcoming',
            'a_name' => 'Ali Shamlan',
            'b_name' => 'Omar Radhi',
        ], $attrs));
    }

    private function display(): CourtDisplay
    {
        return app(CourtDisplay::class);
    }

    public function test_the_board_lists_this_mats_bouts_closest_first(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);

        // Deliberately created out of order — running order comes from match_no.
        $this->bout($event, $cat, ['match_no' => 9, 'a_name' => 'Third']);
        $this->bout($event, $cat, ['match_no' => 3, 'a_name' => 'First']);
        $this->bout($event, $cat, ['match_no' => 6, 'a_name' => 'Second']);

        $payload = $this->display()->payload($event, 'Mat 1');

        $this->assertSame(
            ['First', 'Second', 'Third'],
            array_column($payload['matches'], 'redName')
        );
    }

    public function test_it_shows_only_the_requested_mat(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);

        $this->bout($event, $cat, ['match_no' => 1, 'court' => 'Mat 1', 'a_name' => 'Mine']);
        $this->bout($event, $cat, ['match_no' => 2, 'court' => 'Mat 2', 'a_name' => 'Theirs']);

        $payload = $this->display()->payload($event, 'Mat 1');

        $this->assertCount(1, $payload['matches']);
        $this->assertSame('Mine', $payload['matches'][0]['redName']);
    }

    public function test_a_decided_bout_drops_off_the_board(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);

        $first = $this->bout($event, $cat, ['match_no' => 1, 'a_name' => 'Finished']);
        $this->bout($event, $cat, ['match_no' => 2, 'a_name' => 'Next Up']);

        $this->assertSame('Finished', $this->display()->payload($event, 'Mat 1')['matches'][0]['redName']);

        // The result lands — the queue must shorten, which is the whole point of
        // the board: the top row is always who is actually on next.
        $first->update(['winner' => 'a', 'status' => 'done']);

        $after = $this->display()->payload($event, 'Mat 1');
        $this->assertCount(1, $after['matches']);
        $this->assertSame('Next Up', $after['matches'][0]['redName']);
    }

    public function test_the_version_changes_only_when_the_board_changes(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);
        $bout = $this->bout($event, $cat, ['match_no' => 1]);

        $before = $this->display()->payload($event, 'Mat 1')['version'];

        $this->assertSame($before, $this->display()->payload($event, 'Mat 1')['version'], 'an unchanged board must not repaint');

        $bout->update(['b_name' => 'Someone Else']);

        $this->assertNotSame($before, $this->display()->payload($event, 'Mat 1')['version']);
    }

    public function test_it_carries_the_bout_number_round_and_weight_class(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);
        $this->bout($event, $cat, ['match_no' => 12, 'round' => 'Semifinal']);

        $row = $this->display()->payload($event, 'Mat 1')['matches'][0];

        $this->assertSame('12', $row['number']);
        $this->assertSame('Semifinal', $row['stage']);
        $this->assertSame('-68 kg', $row['weightClass']);
    }

    public function test_the_division_name_stands_in_when_no_weight_class_is_set(): void
    {
        // How the seeded events actually look: the division carries the full
        // "Senior Men -68 kg" in `name` and `weight_class` is left blank. The
        // plate must print something either way.
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);
        $cat->update(['weight_class' => '']);
        $this->bout($event, $cat, ['match_no' => 1]);

        $this->assertSame('Senior Men -68 kg', $this->display()->payload($event, 'Mat 1')['matches'][0]['weightClass']);
    }

    public function test_an_empty_mat_produces_an_empty_board_not_an_error(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $payload = $this->display()->payload($event, 'Mat 4');

        $this->assertSame([], $payload['matches']);
        $this->assertSame('Mat 4', $payload['court']);
    }

    // ── Every field the design draws ─────────────────────────────────────────

    public function test_it_supplies_every_field_the_layout_draws(): void
    {
        // The approved layout declares exactly these. If a future change stops
        // publishing one, its element goes blank on a wall with no other signal
        // — so the contract is pinned here rather than discovered at a venue.
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, $this->category($event), ['match_no' => 1]);

        $payload = $this->display()->payload($event, 'Mat 1');

        $this->assertSame(
            ['court', 'courtNumber', 'event', 'matches', 'version'],
            collect(array_keys($payload))->sort()->values()->all()
        );

        $this->assertSame(
            [
                'blueClub', 'blueFlag', 'blueLogo', 'blueName', 'bluePhoto',
                'number', 'redClub', 'redFlag', 'redLogo', 'redName', 'redPhoto',
                'stage', 'weightClass',
            ],
            collect(array_keys($payload['matches'][0]))->sort()->values()->all()
        );
    }

    public function test_the_header_prints_the_mats_number_not_its_whole_name(): void
    {
        // The design already supplies the word COURT, so "Mat 1" must read as
        // "COURT 1" — never "COURT Mat 1".
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->assertSame('1', $this->display()->payload($event, 'Mat 1')['courtNumber']);
        $this->assertSame('2', $this->display()->payload($event, 'Court 2')['courtNumber']);
        // No digits to take — show the name whole rather than an empty badge.
        $this->assertSame('Red Zone', $this->display()->payload($event, 'Red Zone')['courtNumber']);
    }

    public function test_missing_values_are_published_as_null_never_invented(): void
    {
        // A hall screen must not show detail the system does not have: no
        // stand-in silhouette, no initial-letter crest. The field is published
        // empty and the board hides that element.
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);

        // A hand-typed entrant: a name and nothing else behind it.
        $this->bout($event, $cat, ['match_no' => null, 'a_name' => 'Walk-in Entry', 'a_country' => null]);

        $row = $this->display()->payload($event, 'Mat 1')['matches'][0];

        $this->assertSame('Walk-in Entry', $row['redName']);
        $this->assertNull($row['number']);
        $this->assertNull($row['redFlag']);
        $this->assertNull($row['redPhoto']);
        $this->assertNull($row['redLogo']);
        $this->assertSame('', $row['redClub']);

        // Absent, but still present as keys — the element exists to be filled.
        foreach (['number', 'redFlag', 'redPhoto', 'redLogo', 'redClub'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
    }

    public function test_a_competitors_flag_falls_back_through_the_sources_it_has(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser, ['country' => 'BH']);
        $event = $this->event($club, $organiser);
        $cat = $this->category($event);

        $athlete = $this->createUser(['nationality' => 'JO']);
        $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $entry = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'confirmed',
        ]);

        // Recorded on the draw — that is what the athlete competes under.
        $bout = $this->bout($event, $cat, ['match_no' => 1, 'a_competitor_id' => $entry->id, 'a_country' => 'KW']);
        $this->assertSame('kw', $this->display()->payload($event, 'Mat 1')['matches'][0]['redFlag']);

        // Nothing on the draw — their own nationality stands in.
        $bout->update(['a_country' => null]);
        $this->assertSame('jo', $this->display()->payload($event, 'Mat 1')['matches'][0]['redFlag']);

        // Nor that — the club's country is the last thing we honestly know.
        $athlete->update(['nationality' => null]);
        $this->assertSame('bh', $this->display()->payload($event, 'Mat 1')['matches'][0]['redFlag']);
    }

    // ── What the board must never leak ───────────────────────────────────────

    public function test_a_private_profile_picture_is_never_projected_on_the_wall(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $cat = $this->category($event);

        $shy = $this->createUser(['profile_picture' => 'people/x/profile/a.jpg', 'profile_picture_is_public' => false]);
        $open = $this->createUser(['profile_picture' => 'people/y/profile/b.jpg', 'profile_picture_is_public' => true]);
        foreach ([$shy, $open] as $u) {
            $u->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        }

        $entryA = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $shy->id, 'role' => 'participant', 'status' => 'confirmed']);
        $entryB = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $open->id, 'role' => 'participant', 'status' => 'confirmed']);

        $this->bout($event, $cat, [
            'match_no' => 1,
            'a_competitor_id' => $entryA->id,
            'b_competitor_id' => $entryB->id,
        ]);

        $row = $this->display()->payload($event, 'Mat 1')['matches'][0];

        $this->assertNull($row['redPhoto'], 'a member who has not published their picture must not appear on a hall screen');
        $this->assertNotNull($row['bluePhoto']);
    }

    public function test_the_board_carries_no_identifiers_or_personal_data(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $cat = $this->category($event);

        $athlete = $this->createUser(['email' => 'athlete@example.com']);
        $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $entry = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'confirmed', 'weight' => 67.4,
        ]);

        $this->bout($event, $cat, ['match_no' => 1, 'a_competitor_id' => $entry->id]);

        $json = json_encode($this->display()->payload($event, 'Mat 1'));

        // A photographed board must be worth nothing to whoever photographed it.
        $this->assertStringNotContainsString('athlete@example.com', $json);
        $this->assertStringNotContainsString('67.4', $json);
        $this->assertStringNotContainsString($event->uuid, $json);
        $this->assertStringNotContainsString('"user_id"', $json);
        $this->assertStringNotContainsString('"id"', $json);
    }

    // ── The preview route ────────────────────────────────────────────────────

    public function test_an_organiser_can_preview_the_board(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);
        $this->bout($event, $cat, ['match_no' => 4, 'a_name' => 'Ali Shamlan']);

        $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/court/Mat%201/preview")
            ->assertOk()
            ->assertSee('Ali Shamlan', false)
            ->assertSee('Upcoming Matches', false);
    }

    public function test_someone_else_cannot_preview_the_board(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        // A browser GET to a forbidden page is rerouted home, not 403'd
        // (bootstrap/app.php) — denial is the assertion, not the status code.
        $this->actingAs($this->createUser()->fresh())
            ->get("/me/events/{$event->uuid}/court/Mat%201/preview")
            ->assertRedirect('/');
    }

    public function test_the_preview_requires_a_signed_in_user(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->get("/me/events/{$event->uuid}/court/Mat%201/preview")->assertRedirect('/login');
    }

    // ── The paired screen: no session, ever ──────────────────────────────────

    public function test_a_paired_screen_opens_its_board_without_signing_in(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, $this->category($event), ['match_no' => 7, 'a_name' => 'Ali Shamlan']);

        ['token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        // No actingAs anywhere — a wall screen has nobody to sign it in.
        $this->get("/court/{$token}")
            ->assertOk()
            ->assertSee('Ali Shamlan', false);
    }

    public function test_the_board_url_carries_no_event_or_court_to_tamper_with(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $cat = $this->category($event);
        $this->bout($event, $cat, ['match_no' => 1, 'court' => 'Mat 1', 'a_name' => 'Mine']);
        $this->bout($event, $cat, ['match_no' => 2, 'court' => 'Mat 2', 'a_name' => 'Other Mat']);

        ['token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        // Both the event and the mat come off the device record, so a screen
        // cannot be pointed at a board it was not paired to.
        $this->get("/court/{$token}")
            ->assertOk()
            ->assertSee('Mine', false)
            ->assertDontSee('Other Mat', false);
    }

    public function test_an_unknown_or_revoked_token_is_indistinguishable_from_a_wrong_one(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        $this->get("/court/{$token}")->assertOk();

        $device->revoke();

        // A wall screen is read by whoever walks past it. A revoked token and a
        // fabricated one must answer identically, or the difference maps which
        // tokens are real.
        $this->get("/court/{$token}")->assertNotFound();
        $this->get('/court/'.str_repeat('A', 40))->assertNotFound();
    }

    public function test_the_plaintext_token_is_never_stored(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        $row = (array) \DB::table('court_displays')->find($device->id);

        $this->assertNotContains($token, $row, 'a leaked row must not be replayable as a device');
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        // Nor may it ride along in a serialised model.
        $this->assertStringNotContainsString($token, json_encode($device->fresh()->toArray()));
    }

    public function test_a_screen_records_that_it_is_alive(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        $this->assertNull($device->last_seen_at);

        $this->get("/court/{$token}")->assertOk();

        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    // ── Pairing a fresh screen ───────────────────────────────────────────────

    public function test_an_unpaired_screen_shows_a_qr_pairing_code_not_a_board(): void
    {
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::begin('Wall screen');

        $response = $this->get("/court/{$token}")->assertOk();

        $response->assertSee($device->pairing_code, false);
        $response->assertSee('<svg', false);                  // the QR itself
        $response->assertSee('Pairing code', false);
        $response->assertDontSee('id="courtBadge"', false);    // definitely not the board
    }

    public function test_an_unpaired_screen_can_ask_whether_it_has_been_claimed(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device, 'token' => $token] = CourtDisplayDevice::begin();

        $this->getJson("/court/{$token}/status")->assertOk()->assertExactJson(['claimed' => false]);

        $device->claim($event, 'Mat 1', $organiser->id);

        // One boolean and nothing else — an unpaired screen's code is visible to
        // anyone in the hall, so this must never become a way to read an event.
        $this->getJson("/court/{$token}/status")->assertOk()->assertExactJson(['claimed' => true]);
    }

    public function test_the_pairing_code_avoids_characters_that_are_read_wrong(): void
    {
        // Read aloud across a hall and typed by hand when a camera will not
        // focus, so no vowels (no accidental words) and no 0/O/1/I.
        for ($i = 0; $i < 20; $i++) {
            $code = CourtDisplayDevice::begin()['device']->pairing_code;

            $this->assertMatchesRegularExpression('/^[BCDFGHJKLMNPQRSTVWXYZ2-9]{6}$/', $code);
        }
    }

    public function test_an_organiser_claims_the_screen_and_it_becomes_that_board(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, $this->category($event), ['match_no' => 3, 'a_name' => 'Ali Shamlan']);

        ['device' => $device, 'token' => $token] = CourtDisplayDevice::begin();

        $this->actingAs($organiser->fresh())
            ->post("/court/claim/{$device->pairing_code}", ['event' => $event->uuid, 'court' => 'Mat 1'])
            ->assertRedirect();

        // The wall screen's own URL never changed — what it answers with did.
        $this->get("/court/{$token}")->assertOk()->assertSee('Ali Shamlan', false);
    }

    public function test_a_spectator_who_scans_the_screen_cannot_claim_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device] = CourtDisplayDevice::begin();

        // The code is printed on a wall in a public hall, so it must be worth
        // nothing without an organiser behind it.
        $this->get("/court/claim/{$device->pairing_code}")->assertRedirect('/login');

        $this->actingAs($this->createUser()->fresh())
            ->post("/court/claim/{$device->pairing_code}", ['event' => $event->uuid, 'court' => 'Mat 1'])
            ->assertRedirect('/');

        $this->assertFalse($device->fresh()->isClaimed());
    }

    public function test_a_pairing_code_cannot_be_used_twice(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $device] = CourtDisplayDevice::begin();
        $code = $device->pairing_code;

        $this->actingAs($organiser->fresh())
            ->post("/court/claim/{$code}", ['event' => $event->uuid, 'court' => 'Mat 1'])
            ->assertRedirect();

        // Spent the moment it is used, so a photograph of the screen taken an
        // hour ago cannot repoint it. A signed-in web request is rerouted rather
        // than 404'd (bootstrap/app.php), so the assertion is that the screen
        // did not move — not the status code.
        $this->actingAs($organiser->fresh())
            ->post("/court/claim/{$code}", ['event' => $event->uuid, 'court' => 'Mat 2'])
            ->assertRedirect();

        $this->assertSame('Mat 1', $device->fresh()->court);

        // The same request as JSON gets the real 404, which is the honest check
        // that the code is gone rather than merely rejected.
        $this->actingAs($organiser->fresh())
            ->postJson("/court/claim/{$code}", ['event' => $event->uuid, 'court' => 'Mat 2'])
            ->assertNotFound();
    }

    public function test_the_claim_page_offers_only_events_the_organiser_manages(): void
    {
        $mine = $this->createUser();
        $theirs = $this->createUser();
        $ours = $this->event($this->clubFor($mine), $mine);
        $ours->update(['title' => 'My Championship']);
        $notOurs = $this->event($this->clubFor($theirs), $theirs);
        $notOurs->update(['title' => 'Somebody Elses Cup']);

        ['device' => $device] = CourtDisplayDevice::begin();

        $this->actingAs($mine->fresh())
            ->get("/court/claim/{$device->pairing_code}")
            ->assertOk()
            ->assertSee('My Championship', false)
            ->assertDontSee('Somebody Elses Cup', false);
    }

    // ── A screen's first boot ────────────────────────────────────────────────────

    public function test_a_fresh_screen_can_enroll_itself_with_no_credential(): void
    {
        // Nothing is written to the device ahead of time, so a screen that has
        // never run has no credential and no keyboard — it has to be able to ask.
        $response = $this->postJson('/court/enroll', ['label' => 'mat-1-wall'])
            ->assertCreated();

        $token = $response->json('token');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token);

        // What it got back can show a pairing code and nothing else.
        $this->get("/court/{$token}")
            ->assertOk()
            ->assertSee($response->json('pairing_code'), false)
            ->assertDontSee('id="courtBadge"', false);
    }

    public function test_enrolling_grants_access_to_no_event_data(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, $this->category($event), ['match_no' => 1, 'a_name' => 'Ali Shamlan']);

        $token = $this->postJson('/court/enroll')->assertCreated()->json('token');

        // The open endpoint is only acceptable because this is true: an
        // unclaimed screen is a QR code, not a window into the event.
        $this->get("/court/{$token}")->assertOk()->assertDontSee('Ali Shamlan', false);
        $this->getJson("/court/{$token}/status")->assertExactJson(['claimed' => false]);
    }

    public function test_enrolment_is_throttled(): void
    {
        // A screen enrols once, ever. Anything past a handful an hour is somebody
        // making rows for the sake of it.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/court/enroll')->assertCreated();
        }

        $this->postJson('/court/enroll')->assertStatus(429);
    }

    public function test_abandoned_enrolments_are_pruned_but_claimed_screens_are_not(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $abandoned = CourtDisplayDevice::begin()['device'];
        $abandoned->forceFill(['created_at' => now()->subDays(3)])->save();

        $working = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id)['device'];
        $working->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('court:pair --prune')->assertSuccessful();

        $this->assertNull(CourtDisplayDevice::find($abandoned->id));
        // A screen in a cupboard between events must come back to its own mat.
        $this->assertNotNull(CourtDisplayDevice::find($working->id));
    }

    // ── Fonts ────────────────────────────────────────────────────────────────

    public function test_the_board_asks_for_its_fonts_on_the_origin_serving_it(): void
    {
        // An absolute URL is built from APP_URL, so a board opened on any other
        // host or port asks a machine that is not there and silently falls back
        // to a system sans — which collapses the layout, since the typography is
        // the design.
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);

        $this->get("/court/{$token}")
            ->assertOk()
            ->assertSee('src: url("/court-display/font/anton-400-latin.woff2")', false)
            ->assertDontSee('src: url("http', false);
    }

    public function test_a_packaged_font_is_served(): void
    {
        $this->get('/court-display/font/anton-400-latin.woff2')
            ->assertOk()
            ->assertHeader('content-type', 'font/woff2');
    }

    public function test_the_font_route_refuses_anything_but_a_packaged_face(): void
    {
        // The filename is whitelisted before it is ever joined to a path, so
        // neither traversal nor a foreign extension can reach the filesystem.
        $this->get('/court-display/font/'.urlencode('../../../../.env'))->assertNotFound();
        $this->get('/court-display/font/nope.woff2')->assertNotFound();
    }
}
