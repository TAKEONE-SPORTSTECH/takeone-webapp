<?php

namespace App\Events\Support\Cameras;

use App\Http\Controllers\Controller;
use App\Events\Models\EventCamera;
use App\Events\Models\EventCameraClip;
use App\Events\Models\EventMatch;
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

        // The one line that answers "did the phone ever reach this server?".
        // Three separate mornings were lost to a camera that was talking to the
        // OTHER TAKEONE host: nothing appeared here, and an absence is invisible
        // unless something writes down the presences.
        \App\Events\Support\PairingLog::cameraEnrolled($request, $camera);

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
            // The mat's standing orders about how to film. Same field as the
            // beat's, so a phone that reads either learns the same thing.
            'settings' => MatCameras::clean($camera->settings ?? []),
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
            // How this camera is actually filming: frame rate, zoom, exposure,
            // and whether it sends a bout by itself. Reported, never obeyed —
            // it is what the mat's panel shows beside what it ASKED for, so an
            // order a phone never received is visible rather than assumed.
            'settings' => ['nullable', 'array'],
            'settings.fps' => ['nullable', 'integer'],
            'settings.zoom' => ['nullable', 'numeric'],
            'settings.exposure' => ['nullable', 'numeric'],
            'settings.auto_upload' => ['nullable', 'boolean'],
            // What is still ON the phone. The server keeps a clip's row
            // forever; the FILE can be deleted at the mat, and without this the
            // panel would go on offering to upload a recording that no longer
            // exists. Capped so a phone cannot make one beat expensive.
            'inventory' => ['nullable', 'array', 'max:200'],
            'inventory.*' => ['string', 'max:190'],
        ]);

        // Whitelisted and clamped on the way in: a phone is a stranger writing
        // into a column that is later handed to a browser.
        $reported = MatCameras::clean($data['settings'] ?? []);

        $camera->forceFill(array_filter([
            'storage_total_bytes' => $data['storage_total_bytes'] ?? null,
            'storage_free_bytes' => $data['storage_free_bytes'] ?? null,
            'battery_percent' => $data['battery_percent'] ?? null,
            'device_name' => $data['device_name'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'reported_settings' => $reported ?: null,
        ], fn ($v) => $v !== null) + [
            'last_seen_at' => now(),
        ])->saveQuietly();

        // Reconcile what the phone is holding against what we filed for it.
        //
        // Scoped to THIS camera's own rows, so a token can only ever describe
        // its own footage, and only ever flips a boolean — there is no way from
        // here to delete a row, move a clip to another bout, or claim a
        // recording somebody else filed.
        if (array_key_exists('inventory', $data) && is_array($data['inventory'])) {
            $held = array_values(array_unique(array_filter($data['inventory'], 'is_string')));

            $camera->clips()->whereIn('local_ref', $held)->update(['on_device' => true]);
            $camera->clips()->whereNotIn('local_ref', $held)->update(['on_device' => false]);

            // …and kept as the phone's own answer to "what is on your disk",
            // which is NOT the same question as "what has this event been told
            // about". A camera re-paired onto a new competition still carries
            // last month's files, and the mat needs to see them to clear them.
            // A snapshot, rewritten whole each beat, never merged.
            $camera->forceFill(['reported_clips' => $held])->saveQuietly();
        }

        return response()->json([
            // The beat doubles as a correction channel: the phone learns from
            // the reply whether the mat still wants it rolling — and whether it
            // should still be on air.
            'recording' => (bool) $camera->recording,
            'broadcasting' => (bool) $camera->broadcasting,
            'match' => $this->bout($camera),
            'claimed' => $camera->isClaimed(),
            // What the mat has ASKED this camera to run at. Carried on every
            // beat rather than only pushed, because an order given while the
            // phone was asleep, or while the broker was down, would otherwise
            // simply be lost — and the operator would be looking at a panel
            // saying 60fps over a camera still filming at 30.
            'settings' => MatCameras::clean($camera->settings ?? []),
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

        // A clip recovered from the phone's gallery after the app was
        // reinstalled arrives from a NEW camera identity, often under an old
        // file name that never carried the bout. The event already knows which
        // bout that file is — an earlier row of THIS event filed it under the
        // same name — so it keeps that bout instead of becoming an orphan.
        // Scoped to the camera's own event: a name cannot reach another one.
        $earlier = null;

        if ($matchId === null) {
            $earlier = EventCameraClip::where('event_id', $camera->event_id)
                ->where('local_ref', $data['local_ref'])
                ->whereNotNull('match_id')
                ->orderBy('id')
                ->first(['match_id', 'started_at']);

            $matchId = $earlier?->match_id;
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
                'started_at' => $data['started_at'] ?? $earlier?->started_at,
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
     * "Give me this bout as a video I can send" — the score bar burned in, the
     * points as chapters (App\Media\BoutShare), for the operator sending a clip
     * from the phone straight to WhatsApp.
     *
     * Reaches ONE thing: a clip this camera filed, once it has been uploaded and
     * attached to its bout. A token cannot ask about another camera's clip, and
     * the camera need not still be on a mat — it filmed this bout, which is the
     * whole of its claim. Idempotent: a file made or being made is reported as
     * it stands, and only an idle one is queued, so the phone asks again to
     * learn when it is ready.
     *
     * 404 means "not here" — no such clip, not uploaded yet, no bout, nothing to
     * cut, or the feature off — and the phone sends its own plain copy instead.
     */
    public function share(Request $request, string $token, int $clip, string $kind): JsonResponse
    {
        $subject = $this->shareSubject($token, $clip, $kind);

        if ($subject === null) {
            // Every byte is here and the vault is still filing it: not "never",
            // just "a moment". The phone checks back instead of giving up.
            if ($this->arriving($token, $clip)) {
                return response()->json(['kind' => $kind, 'state' => 'arriving', 'bytes' => null, 'file' => null]);
            }

            return response()->json(['error' => 'unavailable'], 404);
        }

        [$record, $event, $match, $media, $msb, $duration] = $subject;
        $share = app(\App\Media\BoutShare::class);

        // POST asks for it; GET only reads where it stands, so the phone can
        // check back every few seconds without spending the render budget.
        $status = $request->isMethod('post')
            ? $share->request($media, $kind, $msb, $share->title($msb, (string) $event->title), [
                'event' => $event->uuid,
                'match_no' => $match->match_no,
                'user_id' => 0,
            ])
            : $share->status($media, $kind, $msb);

        return response()->json([
            'kind' => $kind,
            'state' => $status['state'],
            'bytes' => $status['bytes'],
            'file' => $status['state'] === 'ready'
                ? route('camera.clip.share.file', ['token' => $token, 'clip' => $record->id, 'kind' => $kind])
                : null,
            'filename' => $share->filename($msb, $kind, $match->match_no),
            'message' => $share->message($msb, $share->title($msb, (string) $event->title), $duration, ''),
        ]);
    }

    /** The finished file, through the same check. */
    public function shareFile(Request $request, string $token, int $clip, string $kind)
    {
        $subject = $this->shareSubject($token, $clip, $kind);

        if ($subject === null) {
            return response()->json(['error' => 'unavailable'], 404);
        }

        [, , $match, $media, $msb] = $subject;
        $share = app(\App\Media\BoutShare::class);
        $path = $share->file($media, $kind, $msb);

        if ($path === null) {
            return response()->json(['error' => 'not_ready'], 404);
        }

        return response()->download($path, $share->filename($msb, $kind, $match->match_no), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * This camera's clip of a bout is fully uploaded and waiting for
     * IngestClipMedia to attach it — the seconds between the last chunk and
     * the footage existing.
     */
    private function arriving(string $token, int $clip): bool
    {
        if (! app(\App\Media\BoutShare::class)->enabled()) {
            return false;
        }

        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return false;
        }

        $record = EventCameraClip::where('id', $clip)->where('camera_id', $camera->id)->first();

        return $record
            && $record->match_id
            && ! $record->media_file_id
            && (int) $record->bytes > 0
            && (int) $record->uploaded_bytes >= (int) $record->bytes
            && $record->play_status !== EventCameraClip::PLAY_FAILED;
    }

    /**
     * "Which bouts are these?" — the phone naming the clips it found in its
     * gallery, usually after a reinstall.
     *
     * A file name is all that survives an uninstall, and an old one never
     * carried the bout. The event still knows: a camera filed that exact name
     * before, or (for this camera's own event) the name carries a bout id. The
     * answer is what the bracket shows — number, round, division, the two
     * names and the score — so the list on the phone reads as bouts, not file
     * names.
     *
     * Two answers, two levels of trust:
     *   · this camera's OWN event: everything, plus the bout id the upload and
     *     the score bar need (`here: true`);
     *   · any other event: only through a name a camera already filed there,
     *     only while that event's draw is public, and never the bout id — a
     *     clip may be READ back to its bout from anywhere, but footage is only
     *     ever filed by a camera paired to that event.
     * A name nobody filed reaches nothing.
     */
    public function known(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $data = $request->validate([
            'clips' => ['required', 'array', 'max:300'],
            'clips.*.ref' => ['required', 'string', 'max:190'],
            'clips.*.match_id' => ['nullable', 'integer'],
        ]);

        $placed = $this->place($camera, $data['clips']);

        // An exhibition — any two entrants, outside the draw — is named in the
        // file by its own number ("bout-ex4") and kept in its own table. Only
        // for a clip already placed at an event, so the number cannot wander.
        $exNumber = fn (string $ref): ?int => $this->exhibitionNumber($ref);
        $exhibitions = \App\Events\Models\EventExhibitionBout::whereIn('event_id', $placed->map(fn ($p) => $p['event']->id)->unique()->values())
            ->whereIn('number', $placed->keys()->map($exNumber)->filter()->unique()->values())
            ->get(['event_id', 'number', 'a_registration_id', 'b_registration_id', 'a_name', 'b_name', 'a_score', 'b_score', 'winner', 'status', 'linked_match_id'])
            ->keyBy(fn ($x) => $x->event_id.':'.$x->number);

        // An exhibition since LINKED to its draw bout is that bout now
        // (ExhibitionLink) — including a clip filed after the link was made,
        // which the link's own move of the clips never saw.
        $linkedBouts = EventMatch::with('category')
            ->whereIn('id', $exhibitions->pluck('linked_match_id')->filter()->unique()->values())
            ->get()->keyBy('id');

        // Each corner's club, for the phone's "list one team" filter — the club
        // the bout page and the score bar name (competingClub), loaded in one go.
        $clubRelations = ['representingTenant', 'user.memberClubs'];
        $bouts = new \Illuminate\Database\Eloquent\Collection($placed->pluck('bout')->filter()->merge($linkedBouts->values())->unique('id')->values()->all());
        $bouts->loadMissing(['competitorA' => fn ($q) => $q->with($clubRelations), 'competitorB' => fn ($q) => $q->with($clubRelations)]);
        $exEntrants = \App\Events\Models\ClubEventRegistration::with($clubRelations)
            ->whereIn('id', $exhibitions->flatMap(fn ($x) => [$x->a_registration_id, $x->b_registration_id])->filter()->unique()->values())
            ->get()
            ->keyBy('id');
        $club = fn (?\App\Events\Models\ClubEventRegistration $reg): ?string => $reg?->competingClub()?->club_name;

        $answer = [];

        foreach ($placed as $ref => $where) {
            $event = $where['event'];
            $bout = $where['bout'];
            $here = $where['here'];

            $ex = ! $bout && ($n = $exNumber($ref)) ? $exhibitions->get($event->id.':'.$n) : null;

            if ($ex && $ex->linked_match_id && ($linked = $bouts->firstWhere('id', (int) $ex->linked_match_id))
                && (int) $linked->event_id === (int) $event->id) {
                $bout = $linked;
                $ex = null;
            }

            if ($ex) {
                $over = $ex->status === \App\Events\Models\EventExhibitionBout::STATUS_DONE;

                $answer[] = [
                    'ref' => $ref,
                    'here' => $here,
                    'event' => $event->title,
                    'match_id' => null,
                    'number' => null,
                    'stage' => 'Exhibition',
                    'division' => null,
                    'red' => $ex->a_name,
                    'blue' => $ex->b_name,
                    'red_club' => $club($exEntrants->get($ex->a_registration_id)),
                    'blue_club' => $club($exEntrants->get($ex->b_registration_id)),
                    'red_score' => $ex->a_score !== null ? (string) $ex->a_score : null,
                    'blue_score' => $ex->b_score !== null ? (string) $ex->b_score : null,
                    'winner' => $over ? match ($ex->winner) { 'a' => 'red', 'b' => 'blue', default => null } : null,
                    'final' => $over || $ex->status === \App\Events\Models\EventExhibitionBout::STATUS_ABANDONED,
                ];

                continue;
            }

            $done = $bout && $bout->status === 'done';

            $answer[] = [
                'ref' => $ref,
                'here' => $here,
                'event' => $event->title,
                'match_id' => $here ? $bout?->id : null,
                'number' => $bout && $bout->match_no !== null ? (string) $bout->match_no : null,
                'stage' => $bout ? ($bout->round ?: $bout->phase) : null,
                'division' => $bout?->category?->name,
                'red' => $bout?->a_name,
                'blue' => $bout?->b_name,
                'red_club' => $club($bout?->competitorA),
                'blue_club' => $club($bout?->competitorB),
                'red_score' => $bout ? $bout->a_score : null,
                'blue_score' => $bout ? $bout->b_score : null,
                'winner' => $done ? match ($bout->winner) { 'a' => 'red', 'b' => 'blue', default => null } : null,
                'final' => $done,
            ];
        }

        $camera->touchSeen();

        return response()->json(['clips' => $answer]);
    }

    /**
     * Where each named clip was filmed and which bout it is — the one rule
     * `known` and `plan` share.
     *
     * A name a camera already filed places a clip at that event (and at its
     * bout, if the row knew one). A bout id typed into a name is believed only
     * for this camera's OWN event. Another event answers only while its draw
     * is public. Anything else is absent.
     *
     * @param  list<array{ref: string, match_id?: ?int}>  $clips
     * @return \Illuminate\Support\Collection<string, array{event: \App\Events\Models\ClubEvent, bout: ?EventMatch, here: bool, started_at: mixed}>
     */
    private function place(EventCamera $camera, array $clips): \Illuminate\Support\Collection
    {
        $home = $camera->isClaimed() ? (int) $camera->event_id : null;
        $refs = collect($clips)->pluck('ref')->unique()->values();

        // The earliest row that knows the bout wins; a row without one still
        // proves where the clip was filmed.
        $filed = EventCameraClip::whereIn('local_ref', $refs)
            ->whereNotNull('event_id')
            ->orderByRaw('match_id is null')
            ->orderBy('id')
            ->get(['local_ref', 'event_id', 'match_id', 'started_at'])
            ->groupBy('local_ref')
            ->map->first();

        $wanted = collect($clips)->mapWithKeys(function ($c) use ($filed, $home) {
            $row = $filed->get($c['ref']);

            if ($row) {
                $own = (int) $row->event_id === $home;

                return [$c['ref'] => [
                    'event' => (int) $row->event_id,
                    'match' => $row->match_id ?: ($own ? ($c['match_id'] ?? null) : null),
                    'started_at' => $row->started_at,
                    'filed' => true,
                ]];
            }

            return [$c['ref'] => $home && ! empty($c['match_id'])
                ? ['event' => $home, 'match' => (int) $c['match_id'], 'started_at' => null, 'filed' => false]
                : null];
        })->filter();

        $events = \App\Events\Models\ClubEvent::whereIn('id', $wanted->pluck('event')->unique()->values())
            ->get()
            ->keyBy('id')
            ->filter(fn ($event) => (int) $event->id === $home || app(\App\Events\Support\EventAccess::class)->drawVisible($event));

        $bouts = EventMatch::with('category')
            ->whereIn('id', $wanted->pluck('match')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return $wanted->map(function ($where) use ($events, $bouts, $home) {
            $event = $events->get($where['event']);

            if (! $event) {
                return null;
            }

            $bout = $where['match'] ? $bouts->get($where['match']) : null;

            // A bout id that does not belong to the event it claims is dropped.
            if ($bout && (int) $bout->event_id !== (int) $event->id) {
                $bout = null;
            }

            // Placed only by a bout id that did not check out: not placed at all.
            if (! $bout && ! $where['filed']) {
                return null;
            }

            return [
                'event' => $event,
                'bout' => $bout,
                'here' => (int) $event->id === $home,
                'started_at' => $where['started_at'],
            ];
        })->filter();
    }

    /**
     * "Draw me the score bar for this clip" — everything boutcam needs to put
     * the bar on the video and cut the highlights ITSELF, on the phone's own
     * video chip, without uploading a byte.
     *
     * The bar is the server's: the same scoreboard (App\Media\BoutScoreboard)
     * lined up against this clip's own start and end, and the same states and
     * highlight windows the server's own renderer would draw
     * (BoutShareRenderer::plan) — so a bout sent from the phone and one sent
     * from the bout page read alike. Read-only, and placed by the same rule as
     * `known`: a clip from another event only through a name already filed
     * there, and only while its draw is public.
     */
    public function plan(Request $request, string $token): JsonResponse
    {
        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return response()->json(['error' => 'unknown'], 404);
        }

        $data = $request->validate([
            'ref' => ['required', 'string', 'max:190'],
            'match_id' => ['nullable', 'integer'],
            'started_at' => ['nullable', 'date'],
            'seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);

        $where = $this->place($camera, [['ref' => $data['ref'], 'match_id' => $data['match_id'] ?? null]])->first();

        // An exhibition — outside the draw, logged with no bout id — gets the
        // same bar, read from its own mat's log for its own minutes.
        $exhibition = null;
        $rows = null;

        if ($where && ! $where['bout'] && ($n = $this->exhibitionNumber($data['ref']))) {
            $exhibition = \App\Events\Models\EventExhibitionBout::where('event_id', $where['event']->id)
                ->where('number', $n)
                ->first();

            if ($exhibition) {
                [$where['bout'], $rows] = $this->exhibitionBout($where['event'], $exhibition);
            }
        }

        if (! $where || ! $where['bout']) {
            return response()->json(['error' => 'no_bout'], 404);
        }

        // The clip's first frame on the wall clock: what the server filed when
        // the clip was made, else what the phone says now.
        $start = $where['started_at'] ?? $data['started_at'] ?? null;

        if (! $start) {
            return response()->json(['error' => 'no_start'], 422);
        }

        $start = \Illuminate\Support\Carbon::parse($start);
        $duration = (int) $data['seconds'];

        // Never saved: the timeline only needs the clip's two ends.
        $recording = new \App\Events\Models\EventRecording();
        $recording->started_at = $start;
        $recording->ended_at = $start->copy()->addSeconds($duration);

        $event = $where['event'];
        $match = $exhibition ? $where['bout'] : $where['bout']->loadMissing(['category', 'event', 'competitorA', 'competitorB']);
        $msb = app(\App\Media\BoutScoreboard::class)->for($event, $match, $recording, $rows);
        $share = app(\App\Media\BoutShare::class);
        $title = $share->title($msb, (string) $event->title);

        // The pictures the bar carries, as small data URIs inside the answer —
        // the event's own crest, each corner's club and flag. Inline rather than
        // linked: a federation's crest is not a public file, and the phone needs
        // them only to paint this one bar.
        $renderer = app(\App\Media\BoutShareRenderer::class);
        [$redSide, $blueSide] = ($match->a_corner ?: 'red') === 'blue' ? ['b', 'a'] : ['a', 'b'];
        $pictures = [
            'event_logo' => $this->logoData($event->host()->logo, 200),
            'red' => [
                'club_logo' => $this->logoData($this->cornerClub($match, $redSide)?->logo, 160),
                'flag_png' => $renderer->flagPng($msb['red']['flag'] ?? null),
                'photo' => $this->photoData($match, $redSide),
            ],
            'blue' => [
                'club_logo' => $this->logoData($this->cornerClub($match, $blueSide)?->logo, 160),
                'flag_png' => $renderer->flagPng($msb['blue']['flag'] ?? null),
                'photo' => $this->photoData($match, $blueSide),
            ],
        ];

        $filename = fn (string $kind) => $exhibition
            ? str_replace('-bout-0', '-exhibition-'.$exhibition->number, $share->filename($msb, $kind, 0))
            : $share->filename($msb, $kind, (int) $match->match_no);

        $camera->touchSeen();

        $plan = $renderer->plan($msb, (float) $duration);

        // The bout's statistics (App\Media\BoutStats), for the card that closes
        // the video after the winner is declared. Read from the whole bout's
        // log, not this clip's window: the card tells the bout, and a clip cut
        // short still ended with the same result. Null when nothing was logged.
        $sheet = app(\App\Media\BoutStats::class)->for($match, $rows);
        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);
        $statLabels = [];
        foreach ([1, 2, 3, 4] as $n) {
            if ($label = $sport?->scoreLabel($n)) {
                $statLabels[(string) $n] = $label;
            }
        }

        // The phone's bar carries the division, match and mat on its own strip
        // (`tab`), so its event line is the event alone.
        $plan['sub'] = (string) $event->title;
        $plan['red'] = array_merge($plan['red'], $pictures['red'], ['country' => \App\Support\Countries::name($plan['red']['flag'] ?? null)]);
        $plan['blue'] = array_merge($plan['blue'], $pictures['blue'], ['country' => \App\Support\Countries::name($plan['blue']['flag'] ?? null)]);

        return response()->json(array_merge(
            $plan,
            [
                'event_logo' => $pictures['event_logo'],
                // The weight class in full, for the VS screen's top line.
                'division' => $exhibition ? 'Exhibition '.$exhibition->number : $this->divisionLine($match->category),
                // The strip riding the score bar: match, mat, round, division.
                'tab' => [
                    'match' => $exhibition ? 'EX'.$exhibition->number : ($match->match_no !== null ? (string) $match->match_no : ''),
                    'court' => (string) ($match->court ?? ''),
                    'round' => (string) ($exhibition ? 'Exhibition' : ($match->round ?: $match->phase ?: '')),
                    // The whole weight class — group, ages, gender, limit — as one.
                    'division' => $exhibition ? '' : $this->divisionLine($match->category),
                ],
                // The VS screen that opens the video: as the bout page's.
                'vs' => [
                    'event' => (string) $event->title,
                    'stage' => (string) ($exhibition ? 'Exhibition' : ($match->round ?: $match->phase ?: '')),
                    'match' => $exhibition ? 'EX'.$exhibition->number : ($match->match_no !== null ? (string) $match->match_no : ''),
                    'court' => (string) ($match->court ?? ''),
                ],
                'title' => $title,
                // The closing statistics card: the sheet minus the rows a card
                // never draws, and the sport's own words for each score value.
                'stats' => $sheet ? \Illuminate\Support\Arr::except($sheet, ['events']) : null,
                'stat_labels' => $statLabels,
                // The sport's own words for the corners (AKA / AO), for that card.
                'corner_labels' => $sport?->cornerLabels() ?? ['red' => 'RED', 'blue' => 'BLUE'],
                'message' => $share->message($msb, $title, $duration, ''),
                'filename' => ['full' => $filename('full'), 'highlights' => $filename('highlights')],
            ],
        ));
    }

    /**
     * A corner's face for the VS screen — only when they chose to show it, the
     * same rule the bout page and the bracket follow (profile_picture_is_public).
     * Absence is silent: the panel simply carries its colour.
     */
    private function photoData(EventMatch $match, string $side): ?string
    {
        $user = ($side === 'a' ? $match->competitorA : $match->competitorB)?->user;

        return ($user?->profile_picture && $user->profile_picture_is_public)
            ? $this->logoData($user->profile_picture, 800, jpeg: true)
            : null;
    }

    /**
     * A division as a reader on the video wants it: the age group, the ages,
     * who, and the weight limit — "Cadets · 14–15 yrs · Male · −52 kg" — from
     * the category's own fields, not its free-text name alone.
     */
    private function divisionLine(?\App\Events\Models\EventCategory $category, bool $withWeight = true): string
    {
        if (! $category) {
            return '';
        }

        // The age group is the name up to the discipline or the gender word
        // ("Under 10 Years Kumite Male -25 kg" → "Under 10 Years").
        $name = (string) $category->name;
        $group = trim((string) preg_split('/\s+(Kumite|Kata|Kyorugi|Poomsae|Male|Female|Men|Women|Boys|Girls|[-+]\d)/i', $name, 2)[0]);

        $min = $category->min_age;
        $max = $category->max_age;
        $ages = match (true) {
            $min && $max => $min.'–'.$max.' yrs',
            (bool) $min => $min.'+ yrs',
            (bool) $max => 'U'.($max + 1),
            default => null,
        };

        $gender = match (strtolower(substr((string) $category->gender, 0, 1))) {
            'm' => 'Male',
            'f' => 'Female',
            default => null,
        };

        $weight = $this->weightLimit($category);

        return collect([$group !== $name || $ages || $weight ? $group : $name, $ages, $gender, $withWeight ? $weight : null])
            ->filter()
            ->implode(' · ');
    }

    /** "−52 kg" / "+55 kg" — the category's weight limit, or null. */
    private function weightLimit(?\App\Events\Models\EventCategory $category): ?string
    {
        if (! $category) {
            return null;
        }

        return $category->weight_class
            ? str_replace('-', '−', (string) $category->weight_class).' kg'
            : ($category->max_weight ? '−'.$category->max_weight.' kg' : null);
    }

    /** The club a corner fought for, as the bout page names it. */
    private function cornerClub(EventMatch $match, string $side): ?\App\Clubs\Models\Tenant
    {
        $reg = $side === 'a' ? $match->competitorA : $match->competitorB;

        return $reg?->competingClub();
    }

    /**
     * A stored logo, shrunk to fit `$max` pixels, as a PNG data URI — or null.
     *
     * Takes a storage path or a `/file/…` URL (EventHost hands out URLs). Reads
     * only from this server's own disk, refuses anything that is not a real
     * raster image by its bytes, and keeps the transparency logos rely on.
     */
    private function logoData(?string $pathOrUrl, int $max, bool $jpeg = false): ?string
    {
        if (! $pathOrUrl) {
            return null;
        }

        $path = parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl;
        $isUrl = (bool) parse_url($pathOrUrl, PHP_URL_HOST);

        // A logo served by its own public door (a federation's crest) rather
        // than as a stored file: fetched from THIS site only — never another
        // host — and only when it is not a /file/ path read straight off disk.
        $own = $isUrl && ! str_starts_with($path, '/file/')
            && parse_url($pathOrUrl, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($isUrl && ! $own && ! str_starts_with($path, '/file/')) {
            return null;
        }

        $path = ltrim(rawurldecode(str_starts_with($path, '/file/') ? substr($path, 6) : $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return \Illuminate\Support\Facades\Cache::remember('camera-logo:'.$max.($jpeg ? ':jpg' : '').':'.($own ? $pathOrUrl : $path), now()->addDay(), function () use ($path, $max, $own, $pathOrUrl, $jpeg) {
            if ($own) {
                $response = rescue(fn () => \Illuminate\Support\Facades\Http::timeout(4)->get($pathOrUrl), null, report: false);
                $bytes = $response?->successful() ? $response->body() : '';

                if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
                    return null;
                }
            } else {
                $disk = \Illuminate\Support\Facades\Storage::disk('local');

                if (! $disk->exists($path) || $disk->size($path) > 8 * 1024 * 1024) {
                    return null;
                }

                $bytes = $disk->get($path);
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                return null;
            }

            $img = @imagecreatefromstring($bytes);

            if (! $img) {
                return null;
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $k = min(1, $max / max($w, $h));
            $out = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);

            // A photograph has no transparency to keep: JPEG, a tenth the size.
            ob_start();
            $jpeg ? imagejpeg($out, null, 84) : imagepng($out);
            $bytes = (string) ob_get_clean();

            return 'data:image/'.($jpeg ? 'jpeg' : 'png').';base64,'.base64_encode($bytes);
        });
    }

    /** The exhibition number a file name carries ("…_bout-ex4_…"), or null. */
    private function exhibitionNumber(string $ref): ?int
    {
        return preg_match('/(?:^|_)bout-ex(\d+)(?:_|\.mp4$)/i', $ref, $m) ? (int) $m[1] : null;
    }

    /**
     * An exhibition dressed as a bout for the scoreboard — never saved — and
     * the log it was scored in: its mat's rows with no bout id, from the
     * moment it was put on the mat to the moment it finished.
     *
     * @return array{0: EventMatch, 1: \Illuminate\Support\Collection}
     */
    private function exhibitionBout(\App\Events\Models\ClubEvent $event, \App\Events\Models\EventExhibitionBout $ex): array
    {
        $bout = new EventMatch();
        $bout->forceFill([
            'event_id' => $event->id,
            'a_name' => $ex->a_name,
            'b_name' => $ex->b_name,
            'court' => $ex->court,
            'round' => 'Exhibition',
        ]);
        $bout->setRelation('event', $event);
        // Named where a division would be, so the bar's event line says what it was.
        $bout->setRelation('category', new \App\Events\Models\EventCategory(['name' => 'Exhibition '.$ex->number]));
        $bout->setRelation('competitorA', $ex->a_registration_id ? \App\Events\Models\ClubEventRegistration::find($ex->a_registration_id) : null);
        $bout->setRelation('competitorB', $ex->b_registration_id ? \App\Events\Models\ClubEventRegistration::find($ex->b_registration_id) : null);

        // Its own mat's rows for its own minutes — one query, shared with the
        // draw bout it may later be linked to (ExhibitionLink::rowsForBout).
        $rows = app(\App\Events\Support\ExhibitionLink::class)->logRows($ex);

        return [$bout, $rows];
    }

    /**
     * This camera's clip, its bout, its footage and the scoreboard — or null.
     *
     * @return array{0: EventCameraClip, 1: \App\Events\Models\ClubEvent, 2: EventMatch, 3: \App\Media\Models\MediaFile, 4: array, 5: int}|null
     */
    private function shareSubject(string $token, int $clip, string $kind): ?array
    {
        $share = app(\App\Media\BoutShare::class);

        if (! $share->enabled()) {
            return null;
        }

        $camera = EventCamera::resolve($token);

        if (! $camera) {
            return null;
        }

        $record = EventCameraClip::where('id', $clip)->where('camera_id', $camera->id)->first();

        if (! $record || ! $record->media_file_id || ! $record->match_id) {
            return null;
        }

        // The recording IngestClipMedia wrote for this clip: same bout, same
        // footage. Its anchor is what lines the points up with the picture.
        $recording = \App\Events\Models\EventRecording::with('mediaFile')
            ->where('match_id', $record->match_id)
            ->where('media_file_id', $record->media_file_id)
            ->where('status', \App\Events\Models\EventRecording::STATUS_LINKED)
            ->first();

        $match = EventMatch::with(['category', 'event', 'competitorA', 'competitorB'])->find($record->match_id);

        if (! $recording || ! $recording->mediaFile || ! $match || ! $match->event) {
            return null;
        }

        $msb = app(\App\Media\BoutScoreboard::class)->for($match->event, $match, $recording);
        $duration = (int) ($recording->mediaFile->duration_seconds ?? $record->duration_seconds ?? 0);

        if (! $share->possible($kind, $msb, $duration)) {
            return null;
        }

        $camera->touchSeen();

        return [$record, $match->event, $match, $recording->mediaFile, $msb, $duration];
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
