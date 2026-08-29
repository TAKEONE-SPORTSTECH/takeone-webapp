<?php

namespace App\Events\Support\Live;

use App\Http\Controllers\Controller;
use App\Jobs\IngestLiveRecording;
use App\Models\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * The media server asking this application for permission.
 *
 * ── The arrangement ────────────────────────────────────────────────────────
 *
 * MediaMTX moves the pixels and owns NO POLICY. Every publish and every read is
 * a callback to here, and this decides — against the event, the token, and the
 * stream's own state. Which means live streaming is authorised by the same rules
 * as the rest of the platform rather than by a second, parallel notion of who
 * may see what.
 *
 * ── Why these two routes sit outside the session ───────────────────────────
 *
 * They are called by a process on loopback, not by a browser: there is no user,
 * no cookie and nothing to forge against, so a CSRF token would be theatre. What
 * guards them instead is the address. Anything that is not 127.0.0.1 gets a 403
 * before a single field is read — the media server is on this host, so nothing
 * legitimate ever arrives from anywhere else.
 */
class LiveAuthController extends Controller
{
    /**
     * "May this publisher publish? May this viewer read?"
     *
     * Answered with a bare status code, which is all MediaMTX looks at.
     */
    public function authenticate(Request $request): Response
    {
        if (! $this->fromMediaServer($request)) {
            return response('', 403);
        }

        $action = (string) $request->input('action');

        // The server's own control API authenticates through this same hook.
        // Allowed because it is already loopback-gated above — nothing off this
        // host can reach it — and without it the app could not read live state
        // from the server it runs beside.
        if (in_array($action, ['api', 'metrics', 'pprof'], true)) {
            return response('', 200);
        }

        $stream = $this->resolve((string) $request->input('path'));

        if ($stream === null) {
            // An unknown path is not worth explaining. A publisher probing for
            // valid stream ids learns nothing from a bare 403.
            return response('', 403);
        }

        return match ($action) {
            'publish' => $this->authorizePublish($request, $stream),
            'read' => $this->authorizeRead($stream),
            default => response('', 403),
        };
    }

    /**
     * A publisher must present the single-use token minted when its owner pressed
     * Go Live. The token is burned here, so a captured one cannot start a second
     * broadcast onto the same mat.
     */
    private function authorizePublish(Request $request, LiveStream $stream): Response
    {
        // MediaMTX passes the WHIP credentials through as user/password.
        $presented = (string) ($request->input('password') ?: $request->input('token'));

        if (! $stream->publishTokenMatches($presented)) {
            Log::warning('live: publish refused', [
                'stream' => $stream->public_id,
                'ip' => $request->input('ip'),
            ]);

            return response('', 403);
        }

        $stream->consumePublishToken();
        $stream->markLive();

        Log::info('live: publishing', [
            'stream' => $stream->public_id,
            'event' => $stream->event_id,
            'court' => $stream->court,
        ]);

        return response('', 200);
    }

    /**
     * Reads are anonymous at this layer — a player fetching HLS parts carries no
     * credentials — so the gate is the stream's state and its visibility. For an
     * event-scoped stream the unguessable path is what keeps it to the people who
     * were given the link; the event's own rule is enforced on the WATCH PAGE,
     * which is where there is a signed-in user to ask about.
     */
    private function authorizeRead(LiveStream $stream): Response
    {
        if (! $stream->isLive()) {
            return response('', 403);
        }

        return response('', 200);
    }

    /**
     * Lifecycle notifications from the media server.
     *
     * The auth hook only fires on the way IN. Without this, a mat whose phone lost
     * signal would read as live for the rest of the competition, and the recording
     * would never be picked up.
     */
    public function hook(Request $request): Response
    {
        if (! $this->fromMediaServer($request)) {
            return response('', 403);
        }

        $stream = $this->resolve((string) $request->input('path'));

        if ($stream === null) {
            return response('', 204);
        }

        match ((string) $request->input('event')) {
            'publish' => $stream->touchSeen(),
            'unpublish' => $this->ended($stream),
            'read' => $stream->viewerJoined(),
            'unread' => $stream->viewerLeft(),
            default => null,
        };

        return response('', 204);
    }

    /**
     * The broadcast is over. Two things follow, in this order.
     *
     * The stream is marked ended first, so nothing shows a hall a mat that is no
     * longer live even for a second. Then the recording is picked up — and that
     * is the point of recording at all: a streamed bout has to leave the same
     * kind of video behind as one filmed by a camera, filed under the same bout,
     * on the same storage.
     */
    private function ended(LiveStream $stream): void
    {
        $stream->markEnded();

        Log::info('live: publisher gone', [
            'stream' => $stream->public_id,
            'duration' => $stream->duration_seconds,
        ]);

        IngestLiveRecording::dispatch($stream->id)->onQueue('media');
    }

    /**
     * Loopback only.
     *
     * The media server runs on this host and binds loopback itself; a request
     * from anywhere else is either a misconfiguration or somebody trying to
     * authorise their own broadcast. Both get the same answer.
     *
     * Note this deliberately does NOT trust proxy headers. The application
     * trusts them for real browser traffic (TLS is terminated upstream), which
     * is exactly why they cannot be trusted here: a forwarded header is
     * attacker-controlled, and this check is the whole security boundary.
     */
    private function fromMediaServer(Request $request): bool
    {
        return in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }

    /** The stream behind a media path (`live/{public_id}`), or null. */
    private function resolve(string $path): ?LiveStream
    {
        if (! preg_match('#^live/([A-Za-z0-9]{8,32})$#', trim($path, '/'), $m)) {
            return null;
        }

        return LiveStream::with('event')->where('public_id', $m[1])->first();
    }
}
