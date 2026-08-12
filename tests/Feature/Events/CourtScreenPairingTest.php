<?php

namespace Tests\Feature\Events;

use App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayDevice;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Pairing a hall screen from inside the event console.
 *
 * The organiser scans the QR on a Raspberry Pi while standing in the event they
 * are running, so the event comes from the URL rather than from the form. That
 * shifts where the security sits, and these cover it: the scanned code is public
 * by design (it is printed a metre tall on a wall), so the session plus manage
 * rights on THIS event are the whole credential.
 */
class CourtScreenPairingTest extends TestCase
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

    /** A bout on a mat — the only reason the event knows "Mat 1" exists. */
    private function bout(ClubEvent $event, string $court): void
    {
        $cat = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Senior Men -68 kg',
            'weight_class' => '-68 kg',
            'status' => 'live',
        ]);

        EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $cat->id,
            'round' => 'Final',
            'court' => $court,
            'match_no' => 1,
            'status' => 'upcoming',
        ]);
    }

    private function unpairedScreen(): CourtDisplayDevice
    {
        return CourtDisplayDevice::begin('hall-screen-1')['device'];
    }

    public function test_an_organiser_pairs_a_scanned_screen_to_one_of_their_mats(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, 'Mat 1');
        $screen = $this->unpairedScreen();

        $res = $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $screen->pairing_code,
            'court' => 'Mat 1',
        ]);

        $res->assertOk()->assertJsonPath('success', true)->assertJsonPath('screen.court', 'Mat 1');

        $screen->refresh();
        $this->assertSame($event->id, $screen->event_id);
        $this->assertSame('Mat 1', $screen->court);
        $this->assertNotNull($screen->claimed_at);

        // Spent: a photograph of the wall is worth nothing once the code is used.
        $this->assertNull($screen->pairing_code);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $screen = $this->unpairedScreen();
        $code = $screen->pairing_code;

        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $code, 'court' => 'Mat 1',
        ])->assertOk();

        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $code, 'court' => 'Mat 2',
        ])->assertNotFound()->assertJsonPath('success', false);
    }

    /**
     * The one that matters most: the code is public, so it must buy nothing on
     * an event the scanner does not run.
     */
    public function test_a_stranger_cannot_pair_a_screen_to_someone_elses_event(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $screen = $this->unpairedScreen();

        $stranger = $this->createUser();

        $this->actingAs($stranger)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $screen->pairing_code,
            'court' => 'Mat 1',
        ])->assertForbidden();

        $this->assertNull($screen->fresh()->event_id);
    }

    public function test_an_unknown_code_is_refused_without_saying_why(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        // A well-formed code that belongs to nothing, and a claimed one, answer
        // identically — nothing here may be used to probe which screens exist.
        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => 'ZZZZZZ', 'court' => 'Mat 1',
        ])->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_a_malformed_code_or_empty_mat_is_rejected(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $screen = $this->unpairedScreen();

        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => 'abc', 'court' => 'Mat 1',
        ])->assertStatus(422);

        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $screen->pairing_code, 'court' => '   ',
        ])->assertStatus(422);
    }

    public function test_the_console_lists_only_this_events_screens(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $other = $this->event($this->clubFor($organiser), $organiser);

        CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id, 'mine');
        CourtDisplayDevice::issue($other, 'Mat 1', $organiser->id, 'theirs');

        $res = $this->actingAs($organiser)->getJson("/me/events/{$event->uuid}/screens");

        $res->assertOk()->assertJsonCount(1, 'screens')->assertJsonPath('screens.0.label', 'mine');

        // The token never rides along in a console response, in any form.
        $body = $res->getContent();
        $this->assertStringNotContainsString('token', $body);
    }

    /**
     * Unpairing must leave the device RECOVERABLE. The agent on the Pi only
     * enrols when its token file is empty, so killing the token here would
     * strand the screen on a 404 until somebody pulled the SD card.
     */
    public function test_unpairing_sends_a_screen_back_to_its_pairing_code(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $screen, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id, 'hall');

        $this->actingAs($organiser)
            ->deleteJson("/me/events/{$event->uuid}/screens/{$screen->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'screens');

        $screen->refresh();
        $this->assertNull($screen->event_id);
        $this->assertNull($screen->court);
        $this->assertNull($screen->claimed_at);
        $this->assertNotNull($screen->revoked_at === null ? $screen->pairing_code : null, 'the screen must come back with a code to show');

        // The token still works — it now resolves to a screen showing its code.
        $this->assertNotNull(CourtDisplayDevice::resolve($token));

        // And the board route hands it the pairing screen, not a 404.
        $this->get("/court/{$token}")->assertOk()->assertSee($screen->pairing_code);
    }

    public function test_a_fresh_code_is_issued_on_unpair_so_an_old_photograph_is_useless(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $screen = $this->unpairedScreen();
        $firstCode = $screen->pairing_code;

        $this->actingAs($organiser)->postJson("/me/events/{$event->uuid}/screens", [
            'code' => $firstCode, 'court' => 'Mat 1',
        ])->assertOk();

        $this->actingAs($organiser)
            ->deleteJson("/me/events/{$event->uuid}/screens/{$screen->id}")
            ->assertOk();

        $this->assertNotSame($firstCode, $screen->fresh()->pairing_code);
    }

    public function test_a_screen_on_another_event_cannot_be_unpaired_through_this_one(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $other = $this->event($this->clubFor($organiser), $organiser);

        $screen = CourtDisplayDevice::issue($other, 'Mat 1', $organiser->id, 'theirs')['device'];

        $this->actingAs($organiser)
            ->deleteJson("/me/events/{$event->uuid}/screens/{$screen->id}")
            ->assertNotFound();

        $this->assertNull($screen->fresh()->revoked_at);
    }

    /**
     * The device agent's realtime credentials: authenticated by the device
     * token, subscribe-only, and scoped to that screen's own topic.
     */
    public function test_a_screen_can_fetch_its_own_realtime_link(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        ['device' => $screen, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id, 'hall');

        $res = $this->getJson("/court/{$token}/link");

        if ($res->status() === 404) {
            // Realtime is optional; with it disabled the agent falls back to
            // polling and there is nothing to assert.
            $this->markTestSkipped('realtime is disabled in this environment');
        }

        $res->assertOk()->assertJsonStructure(['ws_url', 'username', 'password', 'topic']);

        $claims = json_decode(base64_decode(strtr(
            explode('.', $res->json('password'))[1], '-_', '+/'
        )), true);

        $topic = $res->json('topic');
        $this->assertSame([
            ['permission' => 'allow', 'action' => 'subscribe', 'topic' => $topic],
            ['permission' => 'deny', 'action' => 'publish', 'topic' => '#'],
        ], $claims['acl'], 'a screen must never be able to publish');

        // Another screen's token must not resolve to this screen's topic.
        $other = CourtDisplayDevice::begin('other')['device'];
        $this->assertNotSame($topic, \App\Events\Sports\Taekwondo\Tournament\CourtDisplay\ScreenChannel::topic($other));
    }

    public function test_an_unknown_token_gets_no_realtime_link(): void
    {
        $this->getJson('/court/'.str_repeat('a', 40).'/link')->assertNotFound();
    }

    /**
     * The console asks the event TYPE what screens it has, so a type with no
     * wall boards produces no section — and no branching in the shared view.
     */
    public function test_the_type_reports_its_mats_and_screens(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->bout($event, 'Mat 2');
        CourtDisplayDevice::issue($event, 'Mat 2', $organiser->id, 'hall');

        $type = app(\App\Events\EventTypeRegistry::class)->for($event);
        $screens = $type->hallScreens($event);

        $this->assertSame(['Mat 2'], $screens['mats']);
        $this->assertCount(1, $screens['screens']);
        $this->assertSame('Mat 2', $screens['screens'][0]['court']);
        $this->assertArrayNotHasKey('token_hash', $screens['screens'][0]);
    }
}
