<?php

namespace App\Events\Support\Live;

use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * A door for the measurement harness, and nothing else.
 *
 * ── Why this exists ────────────────────────────────────────────────────────
 *
 * The question being measured is whether ONE phone can record each bout locally and
 * publish a live feed from the same camera session. Answering it needs a native
 * app on a real phone, and a native app has no browser session — so it cannot
 * reach `POST /live/{stream}/token`, which is session-authenticated by design.
 *
 * Rather than weaken that route, this is a separate, deliberately tiny surface
 * that hands the harness a publish token for ONE designated lab stream.
 *
 * ── Why it is safe to have shipped ─────────────────────────────────────────
 *
 * · It does not exist unless `LAB_LIVE_KEY` is set in the environment. Absent —
 *   which is the default, and the state of production — every route here 404s
 *   before reading anything.
 * · It requires that key on every request, compared in constant time.
 * · It can only ever touch the ONE stream named by `LAB_LIVE_STREAM`, on the
 *   event named by `LAB_LIVE_EVENT`. It cannot be aimed at a competition.
 * · It grants exactly what the browser flow grants: a single-use publish token
 *   with the same 120-second life. No read access, no event data, no listing.
 * · Throttled hard, and every issue is logged.
 *
 * DELETE THIS FILE, its two routes and the env keys once the measurement is
 * done. It is scaffolding, and scaffolding left standing is how a temporary hole
 * becomes a permanent one.
 */
class LabController extends Controller
{
    /**
     * Mint a publish token for the lab stream.
     *
     * The harness calls this immediately before it goes live, exactly as the
     * broadcast page does, so the token's short life starts when publishing does.
     */
    public function token(Request $request): JsonResponse
    {
        $this->gate($request);

        $stream = $this->stream();

        if ($stream === null) {
            return response()->json(['error' => 'lab stream not configured'], 503);
        }

        $stream->reArm();

        Log::info('lab: publish token issued', ['stream' => $stream->public_id]);

        return response()->json([
            'stream' => $stream->public_id,
            'token' => $stream->issuePublishToken(),
            'expires_in' => LiveStream::PUBLISH_TOKEN_TTL_SECONDS,
            'whip_url' => url('/live-rtc/'.$stream->mediaPath().'/whip'),
            'whep_url' => url('/live-rtc/'.$stream->mediaPath().'/whep'),
            'hls_url' => url('/live-hls/'.$stream->mediaPath().'/index.m3u8'),
            'ice_servers' => array_values(array_filter([
                config('live.stun_url') ? ['urls' => config('live.stun_url')] : null,
                config('live.turn_url') ? array_filter([
                    'urls' => config('live.turn_url'),
                    'username' => config('live.turn_username'),
                    'credential' => config('live.turn_credential'),
                ]) : null,
            ])),
        ]);
    }

    /**
     * Where the harness posts what it measured.
     *
     * The whole point of the exercise: battery, thermal state, frame rate,
     * bitrate, WebRTC's own quality-limitation reason and the size of the file
     * being written, sampled while both jobs run. Written to the log so it can be
     * read off the server rather than off a phone screen somebody is holding.
     */
    public function telemetry(Request $request): JsonResponse
    {
        $this->gate($request);

        $data = $request->validate([
            'run' => 'required|string|max:64',
            'device' => 'nullable|string|max:120',
            'samples' => 'required|array|max:200',
            'samples.*' => 'array',
        ]);

        // One line per sample, so a whole run greps out of the log in order.
        foreach ($data['samples'] as $sample) {
            Log::channel('single')->info('lab.sample', [
                'run' => $data['run'],
                'device' => $data['device'] ?? null,
            ] + array_intersect_key($sample, array_flip([
                'at', 'elapsed', 'phase', 'bout', 'publishing', 'recording',
                'fps', 'kbps', 'width', 'height', 'packets_lost', 'nacks',
                'quality_limit', 'rtt_ms', 'battery', 'thermal', 'file_bytes',
                'file_seconds', 'free_mb', 'note',
            ])));
        }

        return response()->json(['success' => true, 'stored' => count($data['samples'])]);
    }

    /* ──────────────────────────────────────────────────────────────────── */

    /** The key, or nothing at all. */
    private function gate(Request $request): void
    {
        $key = (string) config('live.lab_key');

        // Not configured means this endpoint does not exist. A 404 rather than a
        // 403 so its presence is not discoverable.
        abort_if($key === '', 404);

        $presented = (string) ($request->header('X-Lab-Key') ?? $request->input('key', ''));

        abort_unless(hash_equals($key, $presented), 404);
    }

    /**
     * The one stream this endpoint may touch.
     *
     * Resolved by public id from config — never from the request — so there is no
     * arrangement of parameters that reaches a competition's stream.
     */
    private function stream(): ?LiveStream
    {
        $id = (string) config('live.lab_stream');

        if ($id !== '') {
            return LiveStream::where('public_id', $id)->first();
        }

        // Convenience for a fresh box: create the lab stream once, on the event
        // named in config, and log its id so it can be pinned afterwards.
        $eventUuid = (string) config('live.lab_event');

        if ($eventUuid === '') {
            return null;
        }

        $event = ClubEvent::where('uuid', $eventUuid)->first();

        if ($event === null) {
            return null;
        }

        $stream = LiveStream::firstOrCreate(
            ['event_id' => $event->id, 'title' => 'WebRTC lab'],
            ['court' => 'Lab', 'visibility' => 'unlisted'],
        );

        Log::info('lab: stream in use', ['stream' => $stream->public_id, 'pin_it_with' => 'LAB_LIVE_STREAM']);

        return $stream;
    }
}
