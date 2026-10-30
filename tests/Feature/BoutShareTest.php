<?php

namespace Tests\Feature;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventRecording;
use App\Jobs\RenderBoutShare;
use App\Media\BoutShare;
use App\Media\BoutShareRenderer;
use App\Media\Models\MediaFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A bout as a file to send (App\Media\BoutShare) — the three doors on
 * BoutVideoController and the decisions behind them.
 *
 * Nothing here encodes video: the job is faked, and a "finished" file is a few
 * bytes written where the service looks for it. The encode itself is exercised
 * against real footage outside the suite.
 */
class BoutShareTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        // Rendered files land under media.local_root; keep the suite's own.
        $this->root = storage_path('framework/testing/bout-share-'.uniqid());
        config([
            'media.local_root' => $this->root,
            'media.share_export.enabled' => true,
        ]);

        if (! app(\App\Media\Ffmpeg::class)->available()) {
            $this->markTestSkipped('No ffmpeg on this machine — the feature is off without it.');
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /* ──────────────────────────────────────────────────────────────────
     | Fixtures
     ────────────────────────────────────────────────────────────────── */

    /** @return array{0: ClubEvent, 1: \App\Members\Models\User, 2: EventMatch} */
    private function filmedBout(bool $withPoints = true): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->createClub($organiser, ['country' => 'BH']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        $category = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        foreach (range(1, 4) as $i) {
            $athlete = $this->createUser([
                'full_name' => 'Athlete '.$i, 'gender' => 'Male',
                'birthdate' => now()->subYears(25)->toDateString(),
            ]);
            $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
            ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $category->id,
                'role' => 'participant', 'paid' => true, 'weight' => 57,
            ]);
        }

        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');

        $match = EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->forceFill(['a_corner' => 'red', 'b_corner' => 'blue', 'court' => '1'])->save();

        $file = MediaFile::create([
            'kind' => 'clip', 'rel_path' => 'events/x/matches/1/clips/a.mp4',
            'hls_rel_path' => 'cache/hls/a', 'original_name' => 'bout.mp4',
            'mime' => 'video/mp4', 'bytes' => 1024, 'duration_seconds' => 120,
            'width' => 1920, 'height' => 1080, 'status' => MediaFile::STATUS_READY,
            'meta' => ['event_id' => $event->id], 'created_by' => $organiser->id,
        ]);

        $anchor = now()->startOfMinute();

        EventRecording::create([
            'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1', 'angle' => 'main',
            'anchor_at' => $anchor, 'started_at' => $anchor, 'ended_at' => $anchor->copy()->addSeconds(120),
            'media_file_id' => $file->id, 'status' => EventRecording::STATUS_LINKED,
        ]);

        if ($withPoints) {
            foreach ([
                ['s' => 1, 'off' => 20, 'side' => 'a', 'p' => 2, 'a' => 2, 'b' => 0],
                ['s' => 2, 'off' => 70, 'side' => 'b', 'p' => 1, 'a' => 2, 'b' => 1],
            ] as $row) {
                DB::table('event_match_events')->insert([
                    'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1',
                    'sport' => $event->sport, 'command' => 'point',
                    'payload' => json_encode(['round' => 1]), 'side' => $row['side'], 'points' => $row['p'],
                    'score_a' => $row['a'], 'score_b' => $row['b'],
                    'occurred_at' => $anchor->copy()->addSeconds($row['off'])->toDateTimeString(),
                    'sequence' => $row['s'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return [$event, $organiser->fresh(), $match->fresh()];
    }

    private function door(string $name, ClubEvent $event, EventMatch $match, string $kind): string
    {
        return route('me.events.bout.video.share'.$name, ['event' => $event->uuid, 'matchNo' => $match->match_no, 'kind' => $kind]);
    }

    private function outsider(): \App\Members\Models\User
    {
        $outsider = $this->createUser();
        $club = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $outsider->fresh();
    }

    /** The bout's footage row, as the fixture made it. */
    private function media(): MediaFile
    {
        return MediaFile::where('rel_path', 'events/x/matches/1/clips/a.mp4')->firstOrFail();
    }

    /**
     * The render key the door itself computed — taken off the queued job, so
     * the test never re-derives (or reaches into) how the door hashes a bout.
     */
    private function keyFor(ClubEvent $event, EventMatch $match, string $kind): string
    {
        Queue::fake();
        $this->postJson($this->door('.request', $event, $match, $kind))->assertOk();

        $key = null;
        Queue::assertPushed(RenderBoutShare::class, function (RenderBoutShare $job) use (&$key) {
            $key = $job->key;

            return true;
        });

        // Forget the "being made" mark this request left, and release the
        // uniqueness lock the faked job never ran to release — exactly what a
        // finished job leaves behind — so the test starts clean.
        Cache::forget('bout-share:'.$key);
        Cache::lock('laravel_unique_job:'.RenderBoutShare::class.':bout-share-'.$key)->forceRelease();

        return $key;
    }

    /* ──────────────────────────────────────────────────────────────────
     | The flag
     ────────────────────────────────────────────────────────────────── */

    public function test_every_door_is_a_404_while_the_feature_is_off(): void
    {
        config(['media.share_export.enabled' => false]);
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $this->getJson($this->door('', $event, $match, 'full'))->assertNotFound();
        $this->postJson($this->door('.request', $event, $match, 'full'))->assertNotFound();
        $this->getJson($this->door('.file', $event, $match, 'full'))->assertNotFound();
    }

    /* ──────────────────────────────────────────────────────────────────
     | Status and request
     ────────────────────────────────────────────────────────────────── */

    public function test_status_starts_idle_and_carries_the_points_as_text(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $res = $this->getJson($this->door('', $event, $match, 'highlights'))->assertOk();

        $res->assertJson(['success' => true, 'kind' => 'highlights', 'state' => 'idle', 'file' => null]);
        // Both moments, written out — chapters for an app that shows none.
        $this->assertStringContainsString('0:20', $res->json('message'));
        $this->assertStringContainsString('1:10', $res->json('message'));
        $this->assertStringEndsWith('-highlights.mp4', $res->json('filename'));
    }

    public function test_asking_queues_one_render_on_the_media_queue_and_asking_again_does_not(): void
    {
        Queue::fake();
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $this->postJson($this->door('.request', $event, $match, 'full'))
            ->assertOk()->assertJson(['state' => 'working']);
        $this->postJson($this->door('.request', $event, $match, 'full'))
            ->assertOk()->assertJson(['state' => 'working']);

        Queue::assertPushedOn('media', RenderBoutShare::class);
        Queue::assertPushed(RenderBoutShare::class, 1);

        $this->getJson($this->door('', $event, $match, 'full'))->assertJson(['state' => 'working']);
    }

    public function test_a_bout_with_no_scoring_moments_has_no_highlights_but_can_still_be_sent_whole(): void
    {
        [$event, $organiser, $match] = $this->filmedBout(withPoints: false);
        $this->actingAs($organiser);

        $this->getJson($this->door('', $event, $match, 'highlights'))->assertNotFound();
        $this->getJson($this->door('', $event, $match, 'full'))->assertOk()->assertJson(['state' => 'idle']);
    }

    public function test_an_unknown_kind_is_not_a_door(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $this->getJson('/me/events/'.$event->uuid.'/bout/'.$match->match_no.'/video/share/everything')->assertNotFound();
    }

    /* ──────────────────────────────────────────────────────────────────
     | The file
     ────────────────────────────────────────────────────────────────── */

    public function test_a_finished_file_is_reported_ready_and_handed_out(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $status = $this->getJson($this->door('', $event, $match, 'full'))->assertOk();
        // Not made yet: no file. (A browser GET to a missing page is sent home
        // by the platform's 404 handler; a JSON caller gets the real 404.)
        $this->getJson($this->door('.file', $event, $match, 'full'))->assertNotFound();

        // "Render" it: the bytes where the service looks, under the key the
        // door itself asked the job to fill.
        $share = app(BoutShare::class);
        $path = $share->path($this->media(), $this->keyFor($event, $match, 'full'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'not really a video');

        $this->getJson($this->door('', $event, $match, 'full'))
            ->assertOk()
            ->assertJson(['state' => 'ready', 'bytes' => 18, 'file' => $this->door('.file', $event, $match, 'full')]);

        $download = $this->get($this->door('.file', $event, $match, 'full'))->assertOk();
        $this->assertSame('video/mp4', $download->headers->get('Content-Type'));
        $this->assertStringContainsString($status->json('filename'), (string) $download->headers->get('Content-Disposition'));
        // The response never names where the file lives.
        $this->assertStringNotContainsString('bout-share', (string) $status->getContent());
    }

    public function test_a_failed_render_is_reported_and_may_be_asked_for_again(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        app(BoutShare::class)->settle($this->keyFor($event, $match, 'full'), false, 'ffmpeg exploded at /secret/path');
        Queue::fake();

        $res = $this->getJson($this->door('', $event, $match, 'full'))->assertOk()->assertJson(['state' => 'failed']);
        // The reason stays in the log; the person gets a plain sentence.
        $this->assertStringNotContainsString('/secret/path', (string) $res->getContent());

        $this->postJson($this->door('.request', $event, $match, 'full'))->assertJson(['state' => 'working']);
        Queue::assertPushed(RenderBoutShare::class, 1);
    }

    /* ──────────────────────────────────────────────────────────────────
     | Who may
     ────────────────────────────────────────────────────────────────── */

    public function test_someone_who_may_not_watch_the_bout_gets_nothing_from_any_door(): void
    {
        Queue::fake();
        [$event, , $match] = $this->filmedBout();
        $this->actingAs($this->outsider());

        foreach (['full', 'highlights'] as $kind) {
            $this->getJson($this->door('', $event, $match, $kind))->assertNotFound();
            $this->postJson($this->door('.request', $event, $match, $kind))->assertNotFound();
            $this->getJson($this->door('.file', $event, $match, $kind))->assertNotFound();
            $this->get($this->door('.file', $event, $match, $kind))->assertRedirect('/');
        }

        Queue::assertNothingPushed();
    }

    public function test_a_bout_that_does_not_exist_answers_exactly_like_one_that_is_not_yours(): void
    {
        [$event] = $this->filmedBout();
        $this->actingAs($this->outsider());

        $this->getJson('/me/events/'.$event->uuid.'/bout/999/video/share/full')->assertNotFound();
    }

    public function test_an_athlete_may_send_their_own_bout_from_an_archived_internal_event(): void
    {
        [$event, , $match] = $this->filmedBout();
        $event->forceFill(['scope' => 'internal', 'is_archived' => true])->save();

        $athlete = ClubEventRegistration::find($match->a_competitor_id)->user;
        $this->actingAs($athlete->fresh());

        $this->getJson($this->door('', $event, $match, 'full'))->assertOk()->assertJson(['state' => 'idle']);
    }

    public function test_a_guest_gets_nothing(): void
    {
        [$event, , $match] = $this->filmedBout();

        $this->getJson($this->door('', $event, $match, 'full'))->assertUnauthorized();
    }

    /* ──────────────────────────────────────────────────────────────────
     | The bout pages, both ways round
     ────────────────────────────────────────────────────────────────── */

    public function test_both_bout_pages_render_with_the_sheet_only_while_the_feature_is_on(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        $page = route('me.events.bout.video', ['event' => $event->uuid, 'matchNo' => $match->match_no]);
        $phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';
        $desk = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36';

        foreach ([$phone, $desk] as $agent) {
            config(['media.share_export.enabled' => false]);
            $off = $this->withHeader('User-Agent', $agent)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('boutShareSheet', $off);

            config(['media.share_export.enabled' => true]);
            $on = $this->withHeader('User-Agent', $agent)->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('id="boutShareSheet"', $on);
            $this->assertStringContainsString('BoutShareSheet.open()', $on);
        }
    }

    /* ──────────────────────────────────────────────────────────────────
     | One scoreboard, two builders (until the page moves onto the shared one)
     ────────────────────────────────────────────────────────────────── */

    public function test_the_shared_scoreboard_is_exactly_what_the_page_draws(): void
    {
        [$event, $organiser, $match] = $this->filmedBout();
        $this->actingAs($organiser);

        // The mobile page prints its scoreboard state as MSB_STATE; that is
        // the page's own builder speaking.
        $html = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
            ->get(route('me.events.bout.video', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/const MSB_STATE\s*=\s*(\{.*?\});\n/s', $html);
        preg_match('/const MSB_STATE\s*=\s*(\{.*?\});\n/s', $html, $m);
        $page = json_decode($m[1], true);

        $match = EventMatch::with(['category', 'competitorA', 'competitorB'])->find($match->id);
        $recording = app(\App\Media\BoutFilm::class)->recordings($match)->first();
        $shared = app(\App\Media\BoutScoreboard::class)->for($event, $match, $recording);

        $this->assertNotEmpty($page['points']);
        $this->assertEquals($page, $shared);
    }

    /* ──────────────────────────────────────────────────────────────────
     | From the camera itself (boutcam's Send button)
     ────────────────────────────────────────────────────────────────── */

    /** @return array{0: string, 1: \App\Events\Models\EventCameraClip} the camera's token and the clip it filed */
    private function cameraClip(ClubEvent $event, EventMatch $match, bool $uploaded = true): array
    {
        ['camera' => $camera, 'token' => $token] = \App\Events\Models\EventCamera::begin('Pixel 8');
        $camera->claim($event, '1', 1, $event->created_by);

        $clip = \App\Events\Models\EventCameraClip::create([
            'camera_id' => $camera->id, 'event_id' => $event->id, 'match_id' => $match->id,
            'court' => '1', 'angle' => 1, 'local_ref' => 'bout-1.mp4', 'duration_seconds' => 120,
        ]);

        if ($uploaded) {
            $clip->forceFill(['media_file_id' => $this->media()->id])->save();
        }

        return [$token, $clip];
    }

    public function test_a_camera_can_have_its_own_uploaded_clip_with_the_score_bar(): void
    {
        Queue::fake();
        [$event, , $match] = $this->filmedBout();
        [$token, $clip] = $this->cameraClip($event, $match);

        $res = $this->postJson("/camera/{$token}/clip/{$clip->id}/share/highlights")
            ->assertOk()
            ->assertJson(['kind' => 'highlights', 'state' => 'working', 'file' => null]);

        $this->assertStringContainsString('0:20', $res->json('message'));
        Queue::assertPushedOn('media', RenderBoutShare::class);

        // Checking back is a read: same answer, no second render.
        $this->getJson("/camera/{$token}/clip/{$clip->id}/share/highlights")
            ->assertOk()->assertJson(['state' => 'working']);
        Queue::assertPushed(RenderBoutShare::class, 1);

        // Not ready yet: the file door has nothing.
        $this->getJson("/camera/{$token}/clip/{$clip->id}/share/highlights/file")->assertNotFound();
    }

    public function test_a_camera_cannot_reach_another_cameras_clip(): void
    {
        Queue::fake();
        [$event, , $match] = $this->filmedBout();
        [, $clip] = $this->cameraClip($event, $match);
        ['token' => $stranger] = \App\Events\Models\EventCamera::begin('Somebody else');

        $this->postJson("/camera/{$stranger}/clip/{$clip->id}/share/full")->assertNotFound();
        $this->getJson("/camera/{$stranger}/clip/{$clip->id}/share/full/file")->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_a_clip_not_uploaded_yet_is_not_here_and_the_phone_sends_its_own(): void
    {
        Queue::fake();
        [$event, , $match] = $this->filmedBout();
        [$token, $clip] = $this->cameraClip($event, $match, uploaded: false);

        $this->postJson("/camera/{$token}/clip/{$clip->id}/share/full")->assertNotFound();
        Queue::assertNothingPushed();
    }

    /* ──────────────────────────────────────────────────────────────────
     | The job, without encoding anything
     ────────────────────────────────────────────────────────────────── */

    public function test_the_job_settles_as_failed_when_the_footage_is_gone(): void
    {
        $key = str_repeat('a', 40);

        (new RenderBoutShare('00000000-0000-0000-0000-000000000000', 'full', [], 'x', $key, ['user_id' => 0]))
            ->handle(app(\App\Media\MediaVaults::class), app(BoutShareRenderer::class), app(BoutShare::class));

        $this->assertSame('failed', Cache::get('bout-share:'.$key)['state']);
    }

    /* ──────────────────────────────────────────────────────────────────
     | The cut
     ────────────────────────────────────────────────────────────────── */

    public function test_highlight_windows_merge_when_moments_are_close_and_stay_inside_the_footage(): void
    {
        config(['media.share_export.highlight_before' => 6, 'media.share_export.highlight_after' => 3]);
        $r = app(BoutShareRenderer::class);

        $points = $r->points(['points' => [
            ['t' => 2, 'pts' => 1, 'side' => 'red', 'sr' => 1, 'sb' => 0],
            ['t' => 8, 'pts' => 1, 'side' => 'blue', 'sr' => 1, 'sb' => 1],
            ['t' => 40, 'pts' => 2, 'side' => 'red', 'sr' => 3, 'sb' => 1],
            ['t' => 99, 'pts' => 3, 'side' => 'red', 'sr' => 6, 'sb' => 1],   // past the end
        ]], 60.0);

        $this->assertCount(3, $points);
        $this->assertSame([[0.0, 11.0], [34.0, 43.0]], $r->windows($points, 60.0));
    }
}
