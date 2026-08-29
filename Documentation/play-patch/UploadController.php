<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\CompressVideoJob;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ingest: a match video arriving from takeone, in pieces.
 *
 * Until now a bout video reached this platform the way every other video does —
 * a person picked a file in a browser — and takeone could only annotate one that
 * already existed. This is the other half: the phones that film a mat send their
 * own footage, and the bout's competition truth follows on the existing
 * `PUT /api/v1/matches/{video}`.
 *
 * ── Why chunked, and not one POST ────────────────────────────────────────────
 *
 * Two hard limits, not preferences:
 *
 *   · `post_max_size` here is 512M and nginx caps the body at the same. A
 *     four-minute bout at 1080p30 is around 480MB and a five-minute one at 60fps
 *     is over a gigabyte — so single-shot uploads would fail on exactly the
 *     bouts people most want kept.
 *   · The far end is a phone on a competition hall's wifi. That connection
 *     drops. An upload that cannot resume never finishes; it restarts until
 *     somebody gives up.
 *
 * So: open a session, append chunks at a stated offset, then complete. The
 * offset is authoritative and returned on every call, which makes resuming a
 * matter of asking where we got to.
 *
 * ── What it deliberately does NOT do ─────────────────────────────────────────
 *
 * Per RULE #3 it creates `match` videos and nothing else: no parameter can make
 * it write a music or generic video, and it touches none of the shared upload
 * code. It reuses the ordinary local pipeline (CompressVideoJob → GenerateHlsJob)
 * rather than reimplementing ingest, so a video that arrives here is processed
 * exactly like one a person uploaded.
 */
class UploadController extends Controller
{
    /** A comfortable ceiling per chunk; 512MB chunks would defeat the point. */
    private const MAX_CHUNK = 32 * 1024 * 1024;

    /** A generous cap for one bout. Past this, something upstream is wrong. */
    private const MAX_TOTAL = 4 * 1024 * 1024 * 1024;

    /** Where partial uploads live while they are still arriving. */
    private const SCRATCH = 'private/ingest';

    /**
     * Open an upload session.
     *
     * Returns the id to append against and the offset to start at — always 0
     * here, but stated so the client has one shape to handle everywhere.
     */
    public function begin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TOTAL],
            // Untrusted, and only ever used to pick an extension from a
            // whitelist below — never as a path.
            'extension' => ['nullable', 'string', 'max:8'],
        ]);

        $id = (string) Str::uuid();

        Storage::put(self::SCRATCH.'/'.$id.'.part', '');
        Storage::put(self::SCRATCH.'/'.$id.'.json', json_encode([
            'user_id' => Auth::id(),
            'size' => $data['size'],
            'extension' => $this->extension($data['extension'] ?? null),
            'started_at' => now()->toIso8601String(),
        ]));

        return response()->json(['ok' => true, 'upload' => $id, 'offset' => 0]);
    }

    /**
     * Append one chunk at `Upload-Offset`, and say where we are now.
     *
     * The offset is checked rather than trusted: a chunk that does not continue
     * exactly where the file ends is refused along with the real offset, so a
     * client that lost its place resumes instead of corrupting the file with a
     * silent gap.
     */
    public function chunk(Request $request, string $upload): JsonResponse
    {
        $session = $this->session($upload);

        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'unknown_upload'], 404);
        }

        $path = Storage::path(self::SCRATCH.'/'.$upload.'.part');
        $have = (int) (file_exists($path) ? filesize($path) : 0);
        $offset = (int) $request->header('Upload-Offset', '-1');
        $body = $request->getContent();

        if ($offset !== $have) {
            return response()->json(['ok' => false, 'error' => 'offset_mismatch', 'offset' => $have], 409);
        }

        if (strlen($body) === 0 || strlen($body) > self::MAX_CHUNK) {
            return response()->json(['ok' => false, 'error' => 'bad_chunk', 'offset' => $have], 422);
        }

        if ($have + strlen($body) > (int) $session['size']) {
            return response()->json(['ok' => false, 'error' => 'over_declared_size', 'offset' => $have], 422);
        }

        file_put_contents($path, $body, FILE_APPEND);

        return response()->json(['ok' => true, 'offset' => (int) filesize($path)]);
    }

    /**
     * Finish: move the assembled file into the library and create the video.
     *
     * Created as `match`, owned by the integration account, and handed to the
     * same jobs a browser upload uses. takeone follows this with
     * `PUT /api/v1/matches/{video}` to write the bout — this endpoint knows
     * nothing about competitors and does not want to.
     */
    public function complete(Request $request, string $upload): JsonResponse
    {
        $session = $this->session($upload);

        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'unknown_upload'], 404);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // The caller decides, and takeone sends `public`: an uploaded bout
            // is meant to be found.
            //
            // This defaulted to `unlisted` in the first cut, on the reasoning
            // that a phone finishing a recording should not publish footage by
            // itself. That was the wrong shape while nothing on either side can
            // publish afterwards — it produced videos that existed, played from
            // their link, and appeared in no gallery, with no way to change it.
            //
            // The concern behind it is still real and still open: competitor
            // consent, and minors especially (VIDEO-INTEGRATION.md §5.4). When
            // that gate is built it belongs on the takeone side, which knows who
            // fought and how old they are — not here, which knows neither.
            'visibility' => ['nullable', 'string', 'in:public,unlisted,private'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        $part = Storage::path(self::SCRATCH.'/'.$upload.'.part');

        if (! file_exists($part)) {
            return response()->json(['ok' => false, 'error' => 'nothing_uploaded'], 422);
        }

        $bytes = (int) filesize($part);

        if ($bytes !== (int) $session['size']) {
            return response()->json([
                'ok' => false,
                'error' => 'incomplete',
                'offset' => $bytes,
                'expected' => (int) $session['size'],
            ], 422);
        }

        // The library's own naming, so nothing here is distinguishable from an
        // ordinary upload once it has landed.
        $filename = date('YmdHis').'_'.Str::random(10).'.'.$session['extension'];
        $target = 'public/videos/'.$filename;

        Storage::makeDirectory('public/videos');
        rename($part, Storage::path($target));
        Storage::delete(self::SCRATCH.'/'.$upload.'.json');

        [$width, $height] = $this->dimensions(Storage::path($target));

        $video = Video::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'filename' => $filename,
            'path' => $target,
            'size' => $bytes,
            'mime_type' => 'video/mp4',
            'orientation' => ($width >= $height) ? 'landscape' : 'portrait',
            'width' => $width,
            'height' => $height,
            'duration' => $data['duration'] ?? 0,
            'status' => 'processing',
            'visibility' => $data['visibility'] ?? 'public',
            // The only type this endpoint can produce. RULE #3.
            'type' => 'match',
            'download_access' => 'disabled',
            'share_token' => Str::random(32),
        ]);

        /*
         * The same pipeline a browser upload takes: compress (NVENC) marks the
         * video ready and chains HLS generation.
         *
         * On the `video-processing` queue, which is the ONLY queue the workers on
         * this host actually watch — the supervisor runs
         * `queue:work database --queue=video-processing,nas-sync`. Dispatching to
         * the default queue instead leaves the video sitting at `processing`
         * forever with no error anywhere, which is exactly what the first upload
         * through this endpoint did.
         */
        CompressVideoJob::dispatch($video)
            ->onQueue('video-processing')
            ->onConnection('database');

        return response()->json([
            'ok' => true,
            'video' => Video::encodeId($video->id),
            'id' => $video->id,
            'status' => $video->status,
        ], 201);
    }

    /** Abandon a session and delete whatever arrived. */
    public function cancel(string $upload): JsonResponse
    {
        if ($this->session($upload)) {
            Storage::delete([self::SCRATCH.'/'.$upload.'.part', self::SCRATCH.'/'.$upload.'.json']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The session, if it exists AND belongs to the caller.
     *
     * @return array<string, mixed>|null
     */
    private function session(string $upload): ?array
    {
        if (! preg_match('/^[0-9a-f-]{36}$/', $upload)) {
            return null;
        }

        $meta = self::SCRATCH.'/'.$upload.'.json';

        if (! Storage::exists($meta)) {
            return null;
        }

        $session = json_decode((string) Storage::get($meta), true);

        // One caller's upload is not another's, even between service tokens.
        return (is_array($session) && (int) ($session['user_id'] ?? 0) === (int) Auth::id()) ? $session : null;
    }

    /** Extensions this endpoint will write, and the fallback for anything else. */
    private function extension(?string $raw): string
    {
        $ext = strtolower(trim((string) $raw, ". \t\n"));

        return in_array($ext, ['mp4', 'mov', 'm4v'], true) ? $ext : 'mp4';
    }

    /** @return array{0: int, 1: int} */
    private function dimensions(string $path): array
    {
        try {
            $ffprobe = config('ffmpeg.ffprobe.binaries', '/usr/bin/ffprobe');
            $out = [];
            exec(escapeshellcmd($ffprobe).' -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0:s=x '
                .escapeshellarg($path), $out);

            $parts = explode('x', trim($out[0] ?? ''));

            if (count($parts) === 2 && (int) $parts[0] > 0) {
                return [(int) $parts[0], (int) $parts[1]];
            }
        } catch (\Throwable $e) {
            // A video whose dimensions could not be read is still a video; the
            // transcode fills these in properly.
        }

        return [1920, 1080];
    }
}
