<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Lane;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Voice notes and short videos in a note: probed by their real contents,
 * re-encoded before they are served, kept inside their own card, and only
 * ever shown to board members.
 */
class MarginClipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Route::has('me.margin.media.store')) {
            $this->markTestSkipped('DEVBOARD_ENABLED is off');
        }
        if (! app(\App\Media\Ffmpeg::class)->available()) {
            $this->markTestSkipped('no ffmpeg on this box');
        }
        Storage::fake('local');
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function card(User $by, string $title = 'Idea'): Card
    {
        $lane = Lane::first() ?? Lane::create(['name' => 'Inbox', 'color' => '#7c6bf5', 'position' => 1024]);

        return Card::create(['lane_id' => $lane->id, 'title' => $title, 'priority' => 'normal', 'position' => 1024, 'created_by' => $by->id]);
    }

    /** A real recording, made the way a phone would: WebM/Opus voice, or a small H.264 clip. */
    private function recording(string $kind): UploadedFile
    {
        $out = tempnam(sys_get_temp_dir(), 'clip').($kind === 'audio' ? '.webm' : '.mp4');
        $ffmpeg = app(\App\Media\Ffmpeg::class)->ffmpeg();
        $cmd = $kind === 'audio'
            ? [$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-c:a', 'libopus', $out]
            : [$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=size=320x240:rate=15:duration=1',
                '-f', 'lavfi', '-i', 'sine=duration=1', '-shortest', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', $out];
        (new Process($cmd))->mustRun();

        return new UploadedFile($out, basename($out), null, null, true);
    }

    public function test_a_voice_note_is_re_encoded_measured_and_played_back(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $res = $this->actingAs($me)->post(route('me.margin.media.store', $card), ['kind' => 'audio', 'media' => $this->recording('audio')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('asset.kind', 'audio')
            ->assertJsonPath('asset.status', 'ready');

        $asset = Asset::where('uuid', $res->json('asset.uuid'))->firstOrFail();
        $this->assertSame('audio/mp4', $asset->mime);
        $this->assertEqualsWithDelta(2000, $asset->duration_ms, 250);
        $this->assertCount(48, $asset->meta['peaks']);
        $this->assertNull($asset->source_path, 'the upload as received is not kept');
        $this->assertStringEndsWith('.m4a', $asset->path);
        Storage::disk('local')->assertExists($asset->path);

        $this->actingAs($me)->get(route('me.margin.media.show', $asset))->assertOk()->assertHeader('Content-Type', 'audio/mp4');
        // It is a recording, not a picture: the picture door does not serve it.
        $this->actingAs($me)->getJson(route('me.margin.pictures.show', $asset))->assertNotFound();

        // In a note, the SERVER decides the kind — a client claiming "video" does not change it.
        $note = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<p>listen</p><img data-asset="'.$asset->uuid.'" data-kind="video" onerror="x()">',
        ])->assertOk();
        $this->assertStringContainsString('<img data-asset="'.$asset->uuid.'" data-kind="audio" alt="">', $note->json('note.html'));
        $this->assertStringNotContainsString('onerror', $note->json('note.html'));
        $this->assertSame('audio', $note->json('note.media.'.$asset->uuid.'.kind'));
        $this->assertSame([], $note->json('note.assets'), 'a voice note is not counted as a picture');
    }

    public function test_a_video_is_prepared_with_a_poster_and_served_for_seeking(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        // The queue runs inline in tests, so the media job has finished by the time this answers.
        $res = $this->actingAs($me)->post(route('me.margin.media.store', $card), ['kind' => 'video', 'media' => $this->recording('video')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('asset.kind', 'video');

        $asset = Asset::where('uuid', $res->json('asset.uuid'))->firstOrFail();
        $this->assertSame('ready', $asset->status);
        $this->assertSame(320, $asset->width);
        $this->assertNotNull($asset->preview_path);
        $this->assertNull($asset->source_path);

        $this->actingAs($me)->getJson(route('me.margin.media.status', $asset))->assertOk()->assertJsonPath('asset.status', 'ready')->assertJsonPath('asset.poster', true);
        $this->actingAs($me)->get(route('me.margin.media.show', $asset))->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('Accept-Ranges', 'bytes');
        $this->actingAs($me)->get(route('me.margin.media.show', $asset).'?poster=1')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_what_is_not_really_a_recording_is_refused(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $fake = UploadedFile::fake()->createWithContent('voice.webm', "<?php echo 'hi'; ?>");
        $this->actingAs($me)->post(route('me.margin.media.store', $card), ['kind' => 'audio', 'media' => $fake], ['Accept' => 'application/json'])->assertStatus(422);

        // A real video with no sound is not a voice note.
        $silent = tempnam(sys_get_temp_dir(), 'clip').'.mp4';
        (new Process([app(\App\Media\Ffmpeg::class)->ffmpeg(), '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=size=160x120:duration=1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $silent]))->mustRun();
        $this->actingAs($me)->post(route('me.margin.media.store', $card), ['kind' => 'audio', 'media' => new UploadedFile($silent, 'a.mp4', null, null, true)], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($me)->post(route('me.margin.media.store', $card), ['kind' => 'script', 'media' => $this->recording('audio')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, Asset::count());
    }

    public function test_a_recording_stays_inside_its_own_card_and_its_own_board(): void
    {
        $me = $this->owner();
        $mine = $this->card($me, 'Mine');
        $other = $this->card($me, 'Other');
        $uuid = $this->actingAs($me)->post(route('me.margin.media.store', $mine), ['kind' => 'audio', 'media' => $this->recording('audio')], ['Accept' => 'application/json'])->json('asset.uuid');

        // Another card cannot borrow it.
        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $other), ['html' => '<p>x</p><img data-asset="'.$uuid.'" data-kind="audio">'])->json('note.html');
        $this->assertStringNotContainsString($uuid, $html);

        // Nobody outside the board hears it or adds to it.
        $stranger = $this->createUser();
        $this->assertContains($this->actingAs($stranger)->get(route('me.margin.media.show', $uuid))->status(), [302, 404]);
        $this->actingAs($stranger)->getJson(route('me.margin.media.status', $uuid))->assertNotFound();
        $this->actingAs($stranger)->post(route('me.margin.media.store', $mine), ['kind' => 'audio', 'media' => $this->recording('audio')], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_the_takenote_list_leads_with_a_voice_note(): void
    {
        if (! Route::has('me.takenote.data')) {
            $this->markTestSkipped('TAKENOTE_ENABLED is off');
        }
        $me = $this->owner();
        $note = $this->actingAs($me)->postJson(route('me.takenote.store'), ['html' => ''])->json('note');
        $asset = $this->actingAs($me)->post(route('me.margin.media.store', $note['uuid']), ['kind' => 'audio', 'media' => $this->recording('audio')], ['Accept' => 'application/json'])->json('asset');

        // Only the recording so far: the note is named for what it holds, not "Untitled".
        $title = $this->actingAs($me)->patchJson(route('me.takenote.update', $note['uuid']), ['version' => $note['version'], 'html' => '<img data-asset="'.$asset['uuid'].'">'])
            ->assertOk()->json('note.title');
        $this->assertStringStartsWith('Voice note · ', $title);

        $this->actingAs($me)->patchJson(route('me.takenote.update', $note['uuid']), ['version' => $note['version'] + 1, 'html' => '<img data-asset="'.$asset['uuid'].'"><p>after training</p>'])
            ->assertOk()->assertJsonPath('note.title', 'after training');

        $this->actingAs($me)->getJson(route('me.takenote.data'))
            ->assertOk()
            ->assertJsonPath('notes.0.lead', 'audio')
            ->assertJsonPath('notes.0.voice', 1)
            ->assertJsonPath('notes.0.pictures', 0);
        $this->assertEqualsWithDelta(2000, $this->actingAs($me)->getJson(route('me.takenote.data'))->json('notes.0.lead_ms'), 250);
        $this->actingAs($me)->getJson(route('me.takenote.show', $note['uuid']))->assertJsonPath('note.media.'.$asset['uuid'].'.kind', 'audio');
    }
}
