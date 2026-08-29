<?php

namespace App\Events\Support\Cameras;

use App\Http\Controllers\Controller;
use App\Models\EventCamera;
use App\Models\EventCameraClip;
use App\Models\EventMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything a camera phone says to the server, and everything it is told.
 *
 * Four endpoints and no more, because a camera is entitled to four things:
 * to exist, to ask what it is, to say it is alive, and to file a clip. There
 * is deliberately no way from here to read a draw, an entry list, another mat,
 * or another camera — a phone left on a tripod in a public hall is the easiest
 * device in the product to walk off with, and it must be worth nothing to
 * whoever does.
 *
 * Every response is JSON and every one of them is anonymous-with-a-token: no
 * session, no cookie, no CSRF (there is no browser here and nothing to forge
 * on behalf of a logged-in user — the token IS the whole authorisation, and it
 * only ever reaches its own row).
 */
class CameraController extends Controller
{
    /**
     * 8MB a chunk, matching what this server then sends onward to Play.
     *
     * Small enough that a dropped connection costs seconds; large enough that a
     * 500MB bout is around sixty requests rather than five hundred.
     */
    private const MAX_CHUNK = 8 * 1024 * 1024;

    /**
     * "I am a new camera."
     *
     * Hands back the one plaintext token this device will ever have, plus the
     * code an organiser scans or types to say which mat it is. Open, like the
     * screens' enrolment: what it yields is an UNCLAIMED device that can be
     * told nothing and can read nothing. Rate-limited at the route so a loop
     * cannot fill the table.
     */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Untrusted decoration, shown to organisers so they can tell four
            // identical black phones apart. Escaped everywhere it renders.
            'device_name' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        ['camera' => $camera, 'token' => $token] = EventCamera::begin(
            $data['device_name'] ?? null,
            $data['app_version'] ?? null,
        );

        return response()->json([
            'token' => $token,
            'code' => $camera->pairing_code,
            // Where an organiser's phone should land when it scans the QR this
            // camera is about to draw. The claim page is the SHARED one — an
            // organiser holding a phone does not know, and must not need to
            // know, whether the thing in front of them is a screen or a lens.
            'claim_url' => route('screen.claim', $camera->pairing_code),
        ], 201);
    }

    /**
     * "What am I, and what should I be listening to?"
     *
     * Polled every few seconds while unclaimed, and on every app resume after
     * that. It is also the backstop for the whole feature: if MQTT is down or
     * the phone missed a message, this is what tells it that it is supposed to
     * be recording bout 1-04 right now.
     */
    public function config(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        // One answer for a bad token and a revoked camera. A phone in a hall is
        // picked up by whoever walks past it, and differing replies would tell
        // them which tokens are real.
        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $camera->touchSeen();

        if (! $camera->isClaimed()) {
            $camera->ensurePairable();

            return response()->json([
                'claimed' => false,
                'code' => $camera->pairing_code,
                'claim_url' => route('screen.claim', $camera->pairing_code),
            ]);
        }

        return response()->json([
            'claimed' => true,
            'event' => [
                'uuid' => $camera->event?->uuid,
                'title' => $camera->event?->title,
            ],
            'court' => $camera->court,
            'angle' => $camera->angle,
            'label' => $camera->label,
            // What the mat believes this camera is doing. On a fresh launch
            // mid-bout this is how the phone learns it should already be
            // rolling; it is also how a phone that crashed rejoins.
            'recording' => (bool) $camera->recording,
            // …and whether its feed should be up at all. Pairing used to mean
            // "broadcast until unpaired"; now the console can say otherwise, and
            // this is where a phone learns it — on launch, on resume, and as the
            // backstop for a command it missed.
            'broadcasting' => (bool) $camera->broadcasting,
            'match' => $this->bout($camera),
            'realtime' => CameraChannel::credentials($camera),
        ]);
    }

    /**
     * "I am alive, here is how much room I have left."
     *
     * The storage number is the reason this endpoint exists. A phone that fills
     * up stops being a camera silently, and the only moment that matters is
     * BEFORE the final rather than after it — so it is reported on every beat
     * and shown on the organiser's console next to the mat.
     */
    public function telemetry(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $data = $request->validate([
            'storage_total_bytes' => ['nullable', 'integer', 'min:0', 'max:9007199254740992'],
            'storage_free_bytes' => ['nullable', 'integer', 'min:0', 'max:9007199254740992'],
            'battery_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            // What the PHONE believes it is doing. Recorded as reported rather
            // than trusted as instruction: it never starts or stops anything,
            // it only lets the console show a camera that did not obey.
            'recording' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $camera->forceFill(array_filter([
            'storage_total_bytes' => $data['storage_total_bytes'] ?? null,
            'storage_free_bytes' => $data['storage_free_bytes'] ?? null,
            'battery_percent' => $data['battery_percent'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'app_version' => $data['app_version'] ?? null,
        ], fn ($v) => $v !== null) + [
            'last_seen_at' => now(),
        ])->saveQuietly();

        return response()->json([
            // The beat doubles as a correction channel: the phone learns from
            // the reply whether the mat still wants it rolling — and whether it
            // should still be on air.
            'recording' => (bool) $camera->recording,
            'broadcasting' => (bool) $camera->broadcasting,
            'match' => $this->bout($camera),
            'claimed' => $camera->isClaimed(),
            // And where its uploads got to. The phone hands over bytes and then
            // has no way of knowing what became of them — the forward to Play
            // and the transcode both happen after its part is done — so the
            // beat carries the answer back.
            'clips' => $camera->clips()
                ->whereNotNull('play_status')
                ->latest('id')
                ->limit(50)
                ->get(['id', 'play_status', 'play_video_key'])
                ->map(fn ($clip) => [
                    'id' => $clip->id,
                    'play_status' => $clip->play_status,
                    'play_video_key' => $clip->play_video_key,
                ])
                ->all(),
        ]);
    }

    /**
     * "Give me a credential to publish the live feed for the mat I am on."
     *
     * The camera's own token is the whole authorisation, exactly as it is for
     * every other endpoint here — there is no shared secret compiled into the
     * app, and nothing for whoever picks the phone up to reuse anywhere else.
     * This replaces the lab door (`/api/lab/live` + `X-Lab-Key`), which needed a
     * key baked into the build precisely because it was not scoped to anything.
     *
     * What it can reach is fixed by the camera's row and never by the request:
     * ONE stream, on the event this camera was claimed onto, for the court it
     * was claimed onto. An unclaimed camera gets nothing, because a phone that
     * nobody has put on a mat has no mat to broadcast.
     *
     * The token is single-use with a 120-second life, minted the moment before
     * publishing starts — the same grant the browser's broadcast page gets, and
     * no more. No read access, no event data, no listing.
     */
    public function live(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        // The same single answer as everywhere else in this controller: a bad
        // token and a revoked camera are indistinguishable from outside.
        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $camera->touchSeen();

        // Unclaimed is not an error worth explaining either. The app is already
        // showing its pairing code in this state and has nothing to publish.
        if (! $camera->isClaimed()) {
            return response()->json(['error' => 'unclaimed'], 409);
        }

        // Switched off from the console. Enforced HERE and not only in the app,
        // because the app is the thing being told: a build that predates the
        // switch, or one that missed the message, must still fail to get a
        // credential rather than publishing anyway. The word is the same one the
        // config beat carries, so a phone that reads either learns the same
        // thing.
        // Live broadcasting was removed from this server: there is no media
        // plane to publish to, so a credential cannot be issued and a camera
        // must not be told to start. Recording is unaffected — a camera still
        // films the bout and files the clip through IngestClipMedia.
        return response()->json(['error' => 'live_disabled'], 410);
    }

    /**
     * "I finished a clip, and it is bout 1-04."
     *
     * The file stays on the phone. What is filed here is the INDEX — which
     * camera, which bout, how long, how big, and the handle the phone knows the
     * file by — so that afterwards somebody can be told: plug in angle 2 on Mat
     * 1 and look for this file. Uploading it is a later, deliberate step and
     * does not change what this row means.
     */
    public function clip(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera || ! $camera->isClaimed()) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $data = $request->validate([
            'match_id' => ['nullable', 'integer'],
            'local_ref' => ['required', 'string', 'max:190'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'bytes' => ['nullable', 'integer', 'min:0', 'max:9007199254740992'],
        ]);

        // The bout must belong to THIS camera's event. A token scoped to one
        // mat must not be able to hang a clip off somebody else's competition,
        // and an id that does not check out is dropped rather than refused —
        // the clip is still real and still worth recording.
        $matchId = null;

        if (! empty($data['match_id'])) {
            $matchId = EventMatch::where('id', $data['match_id'])
                ->where('event_id', $camera->event_id)
                ->value('id');
        }

        // Idempotent on (camera, local_ref): a phone that retries a report over
        // a flaky hall network must not file the same clip twice.
        $clip = EventCameraClip::updateOrCreate(
            ['camera_id' => $camera->id, 'local_ref' => $data['local_ref']],
            [
                'event_id' => $camera->event_id,
                'match_id' => $matchId,
                'court' => $camera->court,
                'angle' => $camera->angle,
                'started_at' => $data['started_at'] ?? null,
                'ended_at' => $data['ended_at'] ?? null,
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'bytes' => $data['bytes'] ?? null,
            ],
        );

        $camera->touchSeen();

        return response()->json(['id' => $clip->id, 'stored' => true], 201);
    }

    /**
     * "Here is the video, in pieces."
     *
     * The phone streams a finished clip up to this server, which ingests it into
     * our own media store and attaches the bout (see IngestClipMedia). Chunked
     * and resumable because the far end is a phone on a competition hall's wifi,
     * and an upload that cannot resume never finishes on a bad connection — it
     * just restarts until somebody gives up.
     *
     * The offset this server holds is authoritative. A phone that lost its place
     * asks with `Upload-Offset: 0`, is told the real offset, and continues from
     * there rather than sending a gigabyte again.
     */
    public function upload(Request $request, string $token, int $clip): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera || ! $camera->isClaimed()) {
            return response()->json(['error' => 'unknown'], 404);
        }

        // The camera's OWN clip, never another's — a token reaches one row.
        $record = EventCameraClip::where('id', $clip)->where('camera_id', $camera->id)->first();

        if (! $record) {
            return response()->json(['error' => 'unknown_clip'], 404);
        }

        if ($record->play_video_key) {
            // Already on Play. Say so rather than accepting bytes that would be
            // thrown away — a phone retrying after a lost reply must not upload
            // a second copy of a bout.
            return response()->json([
                'done' => true,
                'video' => $record->play_video_key,
                'offset' => (int) ($record->bytes ?? 0),
            ]);
        }

        $scratch = $this->scratchPath($record);
        $have = is_file($scratch) ? (int) filesize($scratch) : 0;
        $offset = (int) $request->header('Upload-Offset', '-1');
        $body = $request->getContent();

        if ($offset !== $have) {
            return response()->json(['error' => 'offset_mismatch', 'offset' => $have], 409);
        }

        if ($body === '' || strlen($body) > self::MAX_CHUNK) {
            return response()->json(['error' => 'bad_chunk', 'offset' => $have], 422);
        }

        // A clip cannot exceed what the phone told us it was when it filed it.
        // Without this, a camera could fill the event server's disk.
        $declared = (int) ($record->bytes ?? 0);

        if ($declared > 0 && $have + strlen($body) > $declared) {
            return response()->json(['error' => 'over_declared_size', 'offset' => $have], 422);
        }

        if (! is_dir(dirname($scratch))) {
            mkdir(dirname($scratch), 0775, true);
        }

        file_put_contents($scratch, $body, FILE_APPEND);
        $now = (int) filesize($scratch);

        $record->forceFill([
            'uploaded_bytes' => $now,
            'play_status' => EventCameraClip::PLAY_UPLOADING,
            'upload_started_at' => $record->upload_started_at ?? now(),
        ])->save();

        $camera->touchSeen();

        // The phone says when it is done rather than this server guessing from a
        // byte count, because only the phone knows the file ended.
        if ($request->boolean('final') || ($declared > 0 && $now >= $declared)) {
            // Where the bytes go from here: our own media store, on whatever
            // vault is attached or on local disk when none is (App\Media\MediaVaults).
            //
            // There is no longer a second destination. This used to fork on a
            // `media_own_video` setting, the other branch forwarding the clip to
            // an external video platform; that integration has been removed and
            // the setting with it.
            \App\Jobs\IngestClipMedia::dispatch($record->id, $scratch);

            $record->forceFill(['play_status' => EventCameraClip::PLAY_QUEUED])->save();

            return response()->json(['done' => true, 'offset' => $now, 'queued' => true]);
        }

        return response()->json(['done' => false, 'offset' => $now]);
    }

    /**
     * Where this server holds a clip while it is arriving and being forwarded.
     *
     * Private storage, named for the clip so a resumed upload finds its own
     * partial file and nothing else. Deleted by the job either way.
     */
    private function scratchPath(EventCameraClip $clip): string
    {
        return storage_path('app/camera-uploads/clip-'.$clip->id.'.mp4');
    }

    /**
     * "I deleted that clip."
     *
     * The phone is the authority on its own storage: the video lives there, so
     * a volunteer deleting it there is the whole truth, and this row is now a
     * promise about a file that no longer exists. Left behind, it sends
     * somebody hunting through a phone for a bout that was deleted at the mat
     * an hour earlier — worse than never having recorded it.
     *
     * Scoped to the camera that filed it: a token cannot delete another
     * camera's index, and an id from another event resolves to nothing. Idempotent
     * — a retry over a flaky hall network is a success, not a 404, because the
     * outcome the phone asked for is the outcome that holds.
     */
    public function deleteClip(Request $request, string $token, int $clip): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        EventCameraClip::where('id', $clip)->where('camera_id', $camera->id)->delete();

        $camera->touchSeen();

        return response()->json(['deleted' => true]);
    }

    /**
     * The bout this camera is on, as it is allowed to know it.
     *
     * @return array<string, mixed>|null
     */
    private function bout(EventCamera $camera): ?array
    {
        if (! $camera->recording_match_id) {
            return null;
        }

        $bout = EventMatch::query()
            ->where('id', $camera->recording_match_id)
            ->where('event_id', $camera->event_id)
            ->first(['id', 'match_no', 'a_name', 'b_name']);

        return $bout ? [
            'id' => $bout->id,
            'number' => $bout->match_no !== null ? (string) $bout->match_no : null,
            'red' => $bout->a_name,
            'blue' => $bout->b_name,
        ] : null;
    }
}
