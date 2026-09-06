<?php

namespace Tests\Feature\Devices;

use App\Jobs\IngestClipMedia;
use App\Models\ClubEvent;
use App\Models\EventCamera;
use App\Models\EventCameraClip;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A camera on a tripod: enrolled, paired, filming, and filing its bytes.
 *
 * The second half of the "Unattended Devices Must Always Recover" net. A camera
 * is a screen's opposite — it renders nothing and it writes — so its whole
 * surface is JSON doors, and the rule's second clause is the one that binds:
 * every one of them 404s a dead identity, because that 404 is the only signal
 * that makes the phone discard its token and re-enrol. A phone that ignores it,
 * or a server that answers anything else, is a camera that films nothing for a
 * whole competition and says nothing about it.
 *
 * It also covers the clip UPLOAD endpoint end to end. `CameraController::upload`
 * had ZERO test coverage anywhere in the suite before this file, and it is the
 * one camera door that writes bytes to disk, enforces a declared size and hands
 * a bout's video to the ingest job.
 *
 * Nothing here modifies production code; where behaviour diverges from the
 * STRICT rule it is asserted as it is and marked `// DIVERGENCE:`.
 *
 * All data is fake and created per test against the in-memory SQLite database.
 * ⚠️ Run `php artisan config:clear` first — see CLAUDE.md.
 */
class CameraPairingAndUploadTest extends TestCase
{
    private const MAT = 'Mat 1';

    private const DEAD = 'deadtokendeadtokendeadtokendeadtoken0001';

    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH', 'currency' => 'BHD']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    /** A championship on one mat, run by the organiser who created it. */
    private function event(User $organiser): ClubEvent
    {
        $event = ClubEvent::create([
            'tenant_id' => $this->clubFor($organiser)->id,
            'created_by' => $organiser->id,
            'title' => 'Camera Baseline Open',
            'event_type' => 'championship',
            'sport' => 'bjj',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $category = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Adult Men Light',
            'sort_order' => 1,
        ]);

        EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'round' => 'Final',
            'phase' => 'finals',
            'slot' => 0,
            'match_no' => 4,
            'a_name' => 'Ali',
            'b_name' => 'Bader',
            'court' => self::MAT,
            'status' => 'upcoming',
        ]);

        return $event;
    }

    /** A fresh, unclaimed camera, through the real enrolment door. */
    private function enroll(string $name = 'Pixel on a tripod'): array
    {
        $res = $this->postJson('/camera/enroll', ['device_name' => $name, 'app_version' => '1.0'])
            ->assertCreated()
            ->assertJsonStructure(['token', 'code', 'claim_url']);

        return [$res->json('token'), $res->json('code')];
    }

    /** POST a raw body, which is what a chunked upload actually is. */
    private function chunk(string $token, int $clip, string $body, int $offset, bool $final = false)
    {
        return $this->call(
            'POST',
            "/camera/{$token}/clip/{$clip}/upload".($final ? '?final=1' : ''),
            [], [], [],
            ['HTTP_UPLOAD_OFFSET' => (string) $offset, 'CONTENT_TYPE' => 'application/octet-stream'],
            $body,
        );
    }

    private function scratchFor(int $clip): string
    {
        return storage_path('app/camera-uploads/clip-'.$clip.'.mp4');
    }

    // ---------------------------------------------------------------------
    // The full pair, end to end
    // ---------------------------------------------------------------------

    /**
     * Enrol → show a code → an organiser scans it → the phone learns its mat.
     *
     * The whole journey through the REAL doors, including the shared claim page
     * (`/screen/claim/{code}`), which is deliberately one door for a screen and
     * a lens — an organiser in a hall does not know which is in front of them.
     */
    public function test_a_camera_enrols_shows_a_code_and_is_paired_onto_a_mat(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        [$token, $code] = $this->enroll();

        // Unclaimed: it knows nothing, and says so with its own code.
        $this->getJson("/camera/{$token}/config")
            ->assertOk()
            ->assertJsonPath('claimed', false)
            ->assertJsonPath('code', $code);

        // An unclaimed camera must not be able to read an event, a mat, or a bout.
        $this->getJson("/camera/{$token}/config")
            ->assertJsonMissingPath('event')
            ->assertJsonMissingPath('court');

        // The organiser's phone, on the shared claim page.
        $this->actingAs($organiser)->get("/screen/claim/{$code}")->assertOk();

        $this->actingAs($organiser)->post("/screen/claim/{$code}", [
            'event' => $event->uuid,
            'court' => self::MAT,
            'surface' => 'camera',
        ])->assertRedirect(route('screen.claimed'));

        $camera = EventCamera::resolve($token);
        $this->assertNotNull($camera);
        $this->assertSame($event->id, $camera->event_id);
        $this->assertSame(self::MAT, $camera->court);
        $this->assertSame(1, $camera->angle);

        // And the phone learns it from the door it was already polling.
        $this->getJson("/camera/{$token}/config")
            ->assertOk()
            ->assertJsonPath('claimed', true)
            ->assertJsonPath('court', self::MAT)
            ->assertJsonPath('angle', 1)
            ->assertJsonPath('event.uuid', $event->uuid);
    }

    /** A stranger cannot put somebody else's camera on somebody else's mat. */
    public function test_a_stranger_cannot_pair_a_camera_to_an_event_they_do_not_run(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);
        [$token, $code] = $this->enroll();

        $this->actingAs($this->createUser())->post("/screen/claim/{$code}", [
            'event' => $event->uuid,
            'court' => self::MAT,
            'surface' => 'camera',
            // A browser POST, so the app's global handler turns the 403 into a
            // redirect home (CLAUDE.md, "403s Redirect on Web"). Access is still
            // denied — which is what the row below actually proves.
        ])->assertRedirect('/');

        $this->assertNull(EventCamera::resolve($token)->event_id);
    }

    // ---------------------------------------------------------------------
    // The upload — previously untested anywhere in the suite
    // ---------------------------------------------------------------------

    /**
     * A bout's video, in chunks, resumable, ending in the ingest job.
     *
     * The server's offset is authoritative: a phone that lost its place asks
     * with offset 0 and is TOLD the real one rather than sending a gigabyte
     * again.
     */
    public function test_a_clip_uploads_in_chunks_and_is_handed_to_the_ingest_job(): void
    {
        Queue::fake();

        $organiser = $this->createUser();
        $event = $this->event($organiser);
        $match = EventMatch::where('event_id', $event->id)->firstOrFail();

        [$token, $code] = $this->enroll();
        $this->actingAs($organiser)->post("/screen/claim/{$code}", [
            'event' => $event->uuid, 'court' => self::MAT, 'surface' => 'camera',
        ])->assertRedirect(route('screen.claimed'));

        $first = str_repeat('a', 64);
        $second = str_repeat('b', 32);
        $bytes = strlen($first) + strlen($second);

        $clipId = $this->postJson("/camera/{$token}/clip", [
            'match_id' => $match->id,
            'local_ref' => 'DCIM/bout-1-04.mp4',
            'duration_seconds' => 312,
            'bytes' => $bytes,
        ])->assertCreated()->assertJsonPath('stored', true)->json('id');

        $scratch = $this->scratchFor($clipId);
        @unlink($scratch);

        try {
            // A phone that lost its place is told the real offset, not accepted blindly.
            $this->chunk($token, $clipId, $first, 0)
                ->assertOk()
                ->assertJsonPath('done', false)
                ->assertJsonPath('offset', strlen($first));

            $this->chunk($token, $clipId, $second, 0)
                ->assertStatus(409)
                ->assertJsonPath('error', 'offset_mismatch')
                ->assertJsonPath('offset', strlen($first));

            // The declared size is a ceiling — a camera must not be able to
            // fill the event server's disk.
            $this->chunk($token, $clipId, str_repeat('c', 999), strlen($first))
                ->assertStatus(422)
                ->assertJsonPath('error', 'over_declared_size');

            // An empty chunk is refused rather than treated as an end-of-file.
            $this->chunk($token, $clipId, '', strlen($first))
                ->assertStatus(422)
                ->assertJsonPath('error', 'bad_chunk');

            $this->chunk($token, $clipId, $second, strlen($first), final: true)
                ->assertOk()
                ->assertJsonPath('done', true)
                ->assertJsonPath('queued', true)
                ->assertJsonPath('offset', $bytes);

            $this->assertFileExists($scratch);
            $this->assertSame($first.$second, file_get_contents($scratch));

            Queue::assertPushed(IngestClipMedia::class);

            $clip = EventCameraClip::findOrFail($clipId);
            $this->assertSame(EventCameraClip::PLAY_QUEUED, $clip->play_status);
            $this->assertSame($bytes, (int) $clip->uploaded_bytes);
            $this->assertSame($match->id, $clip->match_id);
            $this->assertSame(self::MAT, $clip->court);
        } finally {
            @unlink($scratch);
        }
    }

    /** A token reaches its own row and nothing else — including its own clips. */
    public function test_one_camera_cannot_upload_into_another_cameras_clip(): void
    {
        Queue::fake();

        $organiser = $this->createUser();
        $event = $this->event($organiser);

        $tokens = [];

        foreach (['first', 'second'] as $which) {
            [$token, $code] = $this->enroll($which);
            $this->actingAs($organiser)->post("/screen/claim/{$code}", [
                'event' => $event->uuid, 'court' => self::MAT, 'surface' => 'camera',
            ])->assertRedirect(route('screen.claimed'));
            $tokens[$which] = $token;
        }

        $clipId = $this->postJson("/camera/{$tokens['first']}/clip", [
            'local_ref' => 'DCIM/mine.mp4', 'bytes' => 8,
        ])->assertCreated()->json('id');

        // Same 404 as an unknown clip: nothing here tells a caller that a clip
        // it may not touch exists.
        $this->chunk($tokens['second'], $clipId, str_repeat('x', 8), 0)
            ->assertNotFound()
            ->assertJsonPath('error', 'unknown_clip');

        $this->chunk($tokens['second'], 99999, str_repeat('x', 8), 0)
            ->assertNotFound()
            ->assertJsonPath('error', 'unknown_clip');

        $this->assertFileDoesNotExist($this->scratchFor($clipId));
    }

    /** A clip filed against a bout of another competition drops the bout, not the clip. */
    public function test_a_clip_cannot_be_hung_off_another_events_bout(): void
    {
        $organiser = $this->createUser();
        $mine = $this->event($organiser);
        $theirs = $this->event($this->createUser());
        $foreign = EventMatch::where('event_id', $theirs->id)->firstOrFail();

        [$token, $code] = $this->enroll();
        $this->actingAs($organiser)->post("/screen/claim/{$code}", [
            'event' => $mine->uuid, 'court' => self::MAT, 'surface' => 'camera',
        ])->assertRedirect(route('screen.claimed'));

        $clipId = $this->postJson("/camera/{$token}/clip", [
            'match_id' => $foreign->id,
            'local_ref' => 'DCIM/strange.mp4',
            'bytes' => 4,
        ])->assertCreated()->json('id');

        $this->assertNull(EventCameraClip::findOrFail($clipId)->match_id);
    }

    /** An unclaimed camera has no mat, so it has nothing to film and nothing to file. */
    public function test_an_unclaimed_camera_can_neither_file_nor_upload_a_clip(): void
    {
        [$token] = $this->enroll();

        $this->postJson("/camera/{$token}/clip", ['local_ref' => 'DCIM/x.mp4'])
            ->assertNotFound()
            ->assertExactJson(['error' => 'unknown']);

        $this->chunk($token, 1, 'xxxx', 0)
            ->assertNotFound()
            ->assertExactJson(['error' => 'unknown']);
    }

    // ---------------------------------------------------------------------
    // The recovery contract: every camera door 404s a dead identity
    // ---------------------------------------------------------------------

    /**
     * The clause the client half depends on. A camera has no page to be sent
     * back to, so a 404 IS its recovery: `CameraApi::configResult()` discards
     * the stored token on one and re-enrols.
     */
    public function test_every_camera_door_404s_an_unknown_token(): void
    {
        $this->getJson('/camera/'.self::DEAD.'/config')->assertNotFound()->assertExactJson(['error' => 'unknown']);
        $this->postJson('/camera/'.self::DEAD.'/telemetry', [])->assertNotFound()->assertExactJson(['error' => 'unknown']);
        $this->postJson('/camera/'.self::DEAD.'/clip', ['local_ref' => 'x'])->assertNotFound()->assertExactJson(['error' => 'unknown']);
        $this->chunk(self::DEAD, 1, 'xxxx', 0)->assertNotFound()->assertExactJson(['error' => 'unknown']);
        $this->deleteJson('/camera/'.self::DEAD.'/clip/1')->assertNotFound()->assertExactJson(['error' => 'unknown']);

        // The live door resolves the token FIRST, so a dead identity still gets
        // the one 404 that means "re-enrol" — even though a live camera gets 410
        // (broadcasting was removed from this server). Pinned in both directions
        // below, because a 410 here would be a token a phone could never shed.
        $this->postJson('/camera/'.self::DEAD.'/live', [])->assertNotFound()->assertExactJson(['error' => 'unknown']);
    }

    /**
     * A REAL camera asking to broadcast is told 410, not 404 — the media plane
     * is gone, its identity is not. The distinction matters: 404 is the word
     * that makes a phone throw its token away.
     */
    public function test_a_live_camera_is_told_the_feed_is_gone_not_that_it_is(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        [$token, $code] = $this->enroll();
        $this->actingAs($organiser)->post("/screen/claim/{$code}", [
            'event' => $event->uuid, 'court' => self::MAT, 'surface' => 'camera',
        ])->assertRedirect(route('screen.claimed'));

        $this->postJson("/camera/{$token}/live", [])->assertStatus(410);

        // And an unclaimed one is 409 — alive, but with no mat to broadcast.
        [$fresh] = $this->enroll('unclaimed');
        $this->postJson("/camera/{$fresh}/live", [])->assertStatus(409);
    }

    /** A revoked camera is indistinguishable from a token that never existed. */
    public function test_a_revoked_camera_answers_exactly_as_an_unknown_token_does(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        [$token, $code] = $this->enroll();
        $this->actingAs($organiser)->post("/screen/claim/{$code}", [
            'event' => $event->uuid, 'court' => self::MAT, 'surface' => 'camera',
        ])->assertRedirect(route('screen.claimed'));

        EventCamera::resolve($token)->revoke();

        foreach ([
            ['get', "/camera/{$token}/config", "/camera/".self::DEAD."/config"],
        ] as [$verb, $gone, $bad]) {
            $goneRes = $this->getJson($gone);
            $badRes = $this->getJson($bad);

            $this->assertSame($badRes->getStatusCode(), $goneRes->getStatusCode());
            $this->assertSame($badRes->getContent(), $goneRes->getContent());
        }
    }

    /**
     * The camera's own page — the no-app door a phone lands on — holds no token
     * and therefore can never be dead. It renders whatever else has happened.
     */
    public function test_the_browser_camera_page_always_renders(): void
    {
        $this->get('/camera')->assertOk();
    }
}
