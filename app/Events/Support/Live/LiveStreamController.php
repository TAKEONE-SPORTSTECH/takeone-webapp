<?php

namespace App\Events\Support\Live;

use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Models\ClubEvent;
use App\Models\EventMatch;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Switching a mat on, and watching one.
 *
 * The media plane is deliberately dumb (see LiveAuthController). This decides who
 * may broadcast, mints the short-lived credential the phone publishes with, and
 * hands out the two URLs a player needs.
 */
class LiveStreamController extends Controller
{
    public function __construct(private EventAccess $access) {}

    /**
     * The event's streams — what is live now, and what has been.
     *
     * Read by the console panel, and the reason it is an endpoint rather than
     * data baked into the console: a mat going live is something that happens
     * while somebody is looking at the page, and this is what lets the panel
     * update itself without a reload.
     */
    public function index(ClubEvent $event): JsonResponse
    {
        abort_unless($this->access->visible($event, Auth::user()), 404);

        $canManage = $this->access->canManage($event, Auth::user());

        $streams = LiveStream::where('event_id', $event->id)
            ->latest('id')
            ->limit(40)
            ->get()
            // A viewer who is not running the event has no business seeing the
            // mats that are merely idle — only what is actually on air.
            ->filter(fn (LiveStream $s) => $canManage || $s->isLive())
            ->map(fn (LiveStream $s) => $this->payload($s) + [
                'watch_url' => route('live.watch', $s),
                'broadcast_url' => $canManage ? route('live.broadcast', $s) : null,
            ] + ($canManage ? [
                // Intent and obedience, kept apart: what the console asked for,
                // and whether a phone is actually there to hear it. A spectator
                // is told neither — they get the picture or they do not.
                'armed' => $s->isArmed(),
                'camera_present' => $s->cameraPresent(),
            ] : []))
            ->values();

        return response()->json([
            'streams' => $streams,
            'mats' => $this->mats($event),
            'can_manage' => $canManage,
        ]);
    }

    /**
     * The event's mats, with what is on each one right now.
     *
     * Resolved HERE rather than taken from whatever the console happened to pass
     * in, so the panel works on any event with a draw — including one that has no
     * hall screens and no cameras, which is most of them.
     *
     * `court` on `event_matches` is the mat's identity throughout this platform;
     * there is no separate mats table, and inventing one would be a second truth.
     */
    private function mats(ClubEvent $event): array
    {
        $courts = EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')
            ->distinct()->orderBy('court')
            ->pluck('court')->all();

        // The stream currently attached to each mat — idle or live, never a
        // finished one. This is what lets the panel offer Go live on a mat whose
        // camera is already standing by, instead of sending somebody to the
        // tripod to press it.
        $streams = LiveStream::where('event_id', $event->id)
            ->whereIn('status', [LiveStream::STATUS_IDLE, LiveStream::STATUS_LIVE])
            ->orderBy('id')
            ->get()
            ->keyBy(fn (LiveStream $s) => (string) $s->court);

        return collect($courts)->map(function ($court) use ($event, $streams) {
            $bout = $this->boutOn($event, $court);
            $stream = $streams->get((string) $court);

            return [
                'court' => (string) $court,
                'stream' => $stream ? [
                    'id' => $stream->public_id,
                    'live' => $stream->isLive(),
                    'armed' => $stream->isArmed(),
                    'camera_present' => $stream->cameraPresent(),
                    'watch_url' => route('live.watch', $stream),
                    'broadcast_url' => route('live.broadcast', $stream),
                ] : null,
                'match_id' => $bout?->id,
                // What an organiser reads to know the panel is talking about the
                // right mat. Names, because a bout number means nothing at a mat.
                'bout' => $bout ? trim(($bout->a_name ?? '?').' v '.($bout->b_name ?? '?')) : null,
                'round' => $bout?->round,
            ];
        })->all();
    }

    /**
     * The bout on a mat: the one being fought, or the next one up.
     *
     * `live` first, because that is the answer while a competition is running.
     * Falling back to the next `upcoming` bout is what makes the panel useful
     * BEFORE the mat starts — an organiser sets the camera up during the warm-up,
     * not after the first point.
     */
    private function boutOn(ClubEvent $event, string|int|null $court): ?EventMatch
    {
        $base = fn () => EventMatch::where('event_id', $event->id)->where('court', $court);

        return $base()->where('status', 'live')->first()
            ?? $base()->where('status', 'upcoming')->orderBy('match_no')->orderBy('id')->first();
    }

    /**
     * Start a stream for a mat. Does NOT begin broadcasting — it reserves an
     * identity and a path; the phone goes live from the broadcast page.
     */
    public function store(Request $request, ClubEvent $event): JsonResponse
    {
        abort_unless($this->access->canManage($event, Auth::user()), 403);

        $data = $request->validate([
            'court' => 'nullable|string|max:40',
            'match_id' => 'nullable|integer',
            'title' => 'nullable|string|max:120',
            'visibility' => ['nullable', Rule::in(['event', 'unlisted'])],
        ]);

        // A bout must belong to THIS event. Without the check, an id from
        // another competition would attach a stream to somebody else's bout.
        if (filled($data['match_id'] ?? null)) {
            abort_unless(
                EventMatch::where('id', $data['match_id'])->where('event_id', $event->id)->exists(),
                422
            );
        }

        // One live stream per mat. Pressing the button twice on a mat that is
        // already streaming should return to that stream, not open a second one
        // that quietly competes with it for the same phone.
        $existing = LiveStream::where('event_id', $event->id)
            ->where('court', $data['court'] ?? null)
            ->whereIn('status', [LiveStream::STATUS_IDLE, LiveStream::STATUS_LIVE])
            ->first();

        $stream = $existing ?? LiveStream::create([
            'event_id' => $event->id,
            'court' => $data['court'] ?? null,
            'match_id' => $data['match_id'] ?? null,
            'title' => $data['title'] ?? null,
            'visibility' => $data['visibility'] ?? 'event',
            'created_by' => Auth::id(),
        ]);

        // An existing stream follows the mat: the bout on it changes as the draw
        // advances, and the broadcast should not have to be restarted for that.
        if ($existing && filled($data['match_id'] ?? null) && $existing->match_id !== (int) $data['match_id']) {
            $existing->forceFill(['match_id' => (int) $data['match_id']])->save();
        }

        return response()->json([
            'success' => true,
            'stream' => $this->payload($stream),
            'broadcast_url' => route('live.broadcast', $stream),
            'watch_url' => route('live.watch', $stream),
        ]);
    }

    /**
     * Point a running stream at the bout now on its mat.
     *
     * Called as the draw advances. It exists because a mat's camera runs
     * continuously while bouts come and go, and the platform should know which
     * fight is currently in front of it without anybody restarting anything.
     */
    public function attachBout(Request $request, LiveStream $stream): JsonResponse
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        $data = $request->validate(['match_id' => 'nullable|integer']);

        if (filled($data['match_id'] ?? null)) {
            abort_unless(
                EventMatch::where('id', $data['match_id'])->where('event_id', $stream->event_id)->exists(),
                422
            );
        }

        $next = $data['match_id'] ?? null;

        /* Repointing a RUNNING broadcast changes what its recording is.
         *
         * While it is live, moving the stream from bout 12 to bout 13 means the
         * eventual file spans both — so it is footage of the mat, not of either
         * fight, and it must not be published as one athlete's bout video. The
         * flag is set once here and read once when the recording is filed. */
        $repointed = $stream->match_repointed
            || ($stream->isLive() && filled($stream->match_id) && (int) $stream->match_id !== (int) $next);

        $stream->forceFill(['match_id' => $next, 'match_repointed' => $repointed])->save();

        return response()->json([
            'success' => true,
            'stream' => $this->payload($stream),
            'broadcast_url' => route('live.broadcast', $stream),
            'watch_url' => route('live.watch', $stream),
        ]);
    }

    /** The phone's page: camera preview, and a button. */
    public function broadcast(LiveStream $stream)
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        $stream->loadMissing('event');

        return view('events.live.broadcast', [
            'stream' => $stream,
            'whipUrl' => $this->whipUrl($stream),
            'watchUrl' => route('live.watch', $stream),
            'iceServers' => $this->iceServers(),
            'capture' => [
                'width' => (int) config('live.video_width'),
                'height' => (int) config('live.video_height'),
                'fps' => (int) config('live.video_fps'),
                'bitrate' => (int) config('live.max_bitrate'),
            ],
        ]);
    }

    /**
     * Mint the publish credential.
     *
     * Separate from the page load on purpose: the token's short life starts when
     * somebody actually presses Go Live, not when they opened the tab and went to
     * find a tripod.
     */
    public function token(LiveStream $stream): JsonResponse
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        // A previous attempt that failed must not burn the link.
        $stream->reArm();

        // Pressing Go live ON THE PHONE is the same instruction as pressing it
        // on the console, and must leave the same standing order behind —
        // otherwise the phone's own next beat would read "idle" and stop the
        // broadcast the volunteer just started.
        $stream->arm(Auth::id());

        return response()->json([
            'token' => $stream->issuePublishToken(),
            'expires_in' => LiveStream::PUBLISH_TOKEN_TTL_SECONDS,
            'whip_url' => $this->whipUrl($stream),
            'ice_servers' => $this->iceServers(),
        ]);
    }

    /** The viewer's page. */
    public function watch(LiveStream $stream)
    {
        abort_unless($stream->isWatchableBy(Auth::user()), 404);

        $stream->loadMissing(['event', 'match', 'mediaFile']);

        return view('events.live.watch', [
            'stream' => $stream,
            'whepUrl' => $this->whepUrl($stream),
            'hlsUrl' => $this->hlsUrl($stream),
            'iceServers' => $this->iceServers(),
            'canManage' => $stream->canBroadcast(Auth::user()),
            // Where the header's Back goes — or null, meaning show none.
            //
            // This page is PUBLIC now, and the event page it used to point at is
            // not: a spectator who followed a shared link was one tap from a
            // login screen, on a page that exists precisely so they never need an
            // account. So the destination is resolved against the viewer, and a
            // viewer with nowhere to go is offered nothing rather than a door
            // that shuts in their face.
            'backUrl' => Auth::user() && $stream->event
                && app(\App\Events\Support\EventAccess::class)->visible($stream->event, Auth::user())
                    ? route('me.events.show', $stream->event->uuid)
                    : null,
            // Once the broadcast is over and its recording has been transcoded,
            // the same page plays the video instead of the stream.
            'replayUrl' => $stream->mediaFile && $stream->mediaFile->isPlayable()
                ? route('media.hls', ['file' => $stream->mediaFile->uuid])
                : null,
        ]);
    }

    /**
     * The QR that hands this mat's camera to somebody's phone.
     *
     * An image rather than a page: it is shown inside the console panel, and a
     * volunteer at the mat scans it off the organiser's screen. Rendered offline
     * by our own encoder — a hall's wifi is captive or filtered as often as not,
     * and a QR that needs the open internet to draw itself is useless there.
     *
     * Organisers only: the code encodes the broadcast URL, and while that URL is
     * itself authorised, there is no reason to hand it to spectators.
     */
    public function qr(LiveStream $stream)
    {
        abort_unless($stream->canBroadcast(Auth::user()), 404);

        return response(
            \App\Support\Qr::svg(route('live.broadcast', $stream), 320, 4, '#111111', '#ffffff'),
            200,
            [
                'Content-Type' => 'image/svg+xml',
                // Private: this is a per-stream credential-adjacent URL and must
                // never sit in a shared cache.
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }

    /** Cheap poll for the viewer page: still live, how many watching. */
    public function status(LiveStream $stream): JsonResponse
    {
        abort_unless($stream->isWatchableBy(Auth::user()), 404);

        return response()->json($this->payload($stream));
    }

    /**
     * Take a mat off air. Organisers only.
     *
     * Two things at once, and they are not the same thing: the row is marked
     * ended, AND the standing order becomes idle — which is what the phone
     * reads on its next beat and tears itself down for. Before this, pressing
     * Stop here left the camera happily publishing to a stream nobody could
     * see, because the only real switch was on the phone.
     */
    public function stop(LiveStream $stream): JsonResponse
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        $stream->disarm(Auth::id());
        $stream->markEnded();

        $this->nudgeConsoles($stream);

        // The recording is picked up by the media server's own unpublish hook
        // when the phone actually stops sending. Pressing Stop here is the
        // organiser's intent; the hook is the fact.
        return response()->json(['success' => true, 'stream' => $this->payload($stream)]);
    }

    /**
     * Put a mat on air from the console.
     *
     * The person who knows whether this mat should be public is at the scoring
     * table, not holding the camera — so the switch is here, and the phone on
     * the tripod obeys it. It sets INTENT only: the stream goes live when the
     * media server sees a publisher arrive, and if the phone is not there to
     * hear the order, the panel says so rather than lighting a red dot over a
     * mat with no picture behind it.
     */
    public function arm(LiveStream $stream): JsonResponse
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        $stream->arm(Auth::id());

        $this->nudgeConsoles($stream);

        return response()->json([
            'success' => true,
            'stream' => $this->payload($stream) + [
                'armed' => true,
                'camera_present' => $stream->cameraPresent(),
            ],
            // Honest about the one case that matters: armed with nobody
            // listening. The console shows this rather than pretending.
            'camera_present' => $stream->cameraPresent(),
        ]);
    }

    /**
     * The viewfinder asking what it should be doing.
     *
     * Polled by the phone every few seconds, and the whole reason the console's
     * buttons reach it: there is no socket on this page and there does not need
     * to be — a two-field answer on a short beat is enough to start a mat within
     * a few seconds of somebody asking, and it keeps working when the broker is
     * down, which a hall's wifi regularly arranges.
     *
     * It doubles as the phone's presence beat: answering this is what makes the
     * console say a camera is standing by on that mat.
     */
    public function orders(LiveStream $stream): JsonResponse
    {
        abort_unless($stream->canBroadcast(Auth::user()), 403);

        $stream->touchCamera();

        return response()->json([
            'desired' => $stream->desired_state ?? LiveStream::WANT_IDLE,
            // What the server believes is happening, so a phone that dropped
            // without anybody noticing can tell it is no longer the publisher.
            'status' => $stream->status,
            'match_id' => $stream->match_id,
        ]);
    }

    /**
     * Nudge every organiser running this event that a mat's live state changed.
     *
     * A refresh signal carrying no data — what a console may see depends on who
     * is looking, so each one re-fetches its own answer. Best-effort: the
     * panel's own poll is the backstop and a broker outage delays a badge.
     */
    private function nudgeConsoles(LiveStream $stream): void
    {
        $event = $stream->event;

        if (! $event) {
            return;
        }

        $ids = $event->officials()->pluck('user_id')->all();

        if ($event->created_by) {
            $ids[] = $event->created_by;
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        if (! $ids) {
            return;
        }

        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => [
                'topic' => \Realtime()->userTopic($id, 'events'),
                'payload' => ['action' => 'live', 'event' => $event->uuid],
            ],
            $ids,
        )), null, false);
    }

    /* ── Payload ─────────────────────────────────────────────────────── */

    private function payload(LiveStream $stream): array
    {
        return [
            'id' => $stream->public_id,
            'label' => $stream->label,
            'status' => $stream->status,
            'live' => $stream->isLive(),
            'court' => $stream->court,
            'match_id' => $stream->match_id,
            'viewers' => $stream->current_viewers,
            'peak_viewers' => $stream->peak_viewers,
            'duration' => $stream->duration_seconds,
            'recording' => $stream->media_file_id ? [
                'uuid' => $stream->mediaFile?->uuid,
                'status' => $stream->mediaFile?->status,
            ] : null,
        ];
    }

    /* ── URLs ────────────────────────────────────────────────────────── */

    /*
     * Same-origin paths, proxied to the media server on loopback. Same-origin is
     * not a nicety: the page is served over HTTPS, and a cross-origin or
     * mixed-content media URL is blocked by the browser before WebRTC starts.
     */

    private function whipUrl(LiveStream $stream): string
    {
        return url('/live-rtc/'.$stream->mediaPath().'/whip');
    }

    private function whepUrl(LiveStream $stream): string
    {
        return url('/live-rtc/'.$stream->mediaPath().'/whep');
    }

    private function hlsUrl(LiveStream $stream): string
    {
        return url('/live-hls/'.$stream->mediaPath().'/index.m3u8');
    }

    /**
     * ICE servers for the browser.
     *
     * ⚠️ The honest state of this: no inbound UDP port is forwarded to this
     * server, so a phone OUTSIDE the venue's network can only reach it through a
     * TURN relay. On the hall's own wifi the host candidates work directly and
     * TURN is never touched. Until `LIVE_TURN_URL` is set, treat broadcasting as
     * a LAN capability — watching over LL-HLS works from anywhere, because that
     * is plain HTTPS through the same proxy as the rest of the site.
     */
    private function iceServers(): array
    {
        $servers = [];

        if ($stun = config('live.stun_url')) {
            $servers[] = ['urls' => $stun];
        }

        if ($turn = config('live.turn_url')) {
            $servers[] = array_filter([
                'urls' => $turn,
                'username' => config('live.turn_username'),
                'credential' => config('live.turn_credential'),
            ]);
        }

        return $servers;
    }
}
