<?php

namespace App\Media\Http;

use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Media\MediaVaults;
use App\Models\ClubEvent;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serving video — authorised first, then handed to the web server.
 *
 * ── Authorisation, before a single byte ────────────────────────────────────
 *
 * Every request resolves the media file, finds the EVENT it belongs to, and asks
 * that event whether this viewer may see it (`EventAccess::visible`). Media with
 * no event is super-admin only. A file's uuid is unguessable, but that is defence
 * in depth and not the defence: the check runs on the playlist AND on every
 * segment, because a leaked segment URL is a leaked video otherwise.
 *
 * ── Who actually sends the bytes ───────────────────────────────────────────
 *
 * Not PHP, when it can be helped. With `media.internal_prefix` configured, an
 * authorised request answers with an X-Accel-Redirect and nginx streams the file
 * — one PHP worker per request instead of one per six-second segment, which is
 * the difference between a hall watching a bout and a hall watching a spinner.
 * Without it, Symfony's file response streams it (and still handles Range), which
 * is correct but does not scale; see the note in config/media.php.
 */
class MediaStreamController extends Controller
{
    public function __construct(
        private MediaVaults $vaults,
        private EventAccess $access,
    ) {}

    /**
     * The HLS ladder: the master playlist, a variant playlist, or a segment.
     *
     * One route for all three because they are one contract — the player follows
     * relative paths out of the playlist we hand it, and every one of those
     * comes back through this method and its authorisation.
     */
    public function hls(Request $request, MediaFile $file, string $path = 'playlist.m3u8')
    {
        $this->authorize($file, publicEventOk: true);

        if (! filled($file->hls_rel_path)) {
            // Stored but not yet watchable. 404 rather than an error page: a
            // player asking early should retry, not render a message.
            abort(404);
        }

        $relative = $this->safeSubPath($path);

        if ($relative === null) {
            abort(404);
        }

        $rel = trim($file->hls_rel_path, '/').'/'.$relative;
        $abs = rtrim(config('media.local_root'), '/').'/'.$rel;

        if (! is_file($abs)) {
            abort(404);
        }

        $isPlaylist = str_ends_with($relative, '.m3u8');

        return $this->send($abs, $rel, [
            'Content-Type' => $isPlaylist ? 'application/vnd.apple.mpegurl' : 'video/mp2t',
            // A playlist is regenerated on every transcode; a segment never
            // changes once written, so it can be cached hard. Private either
            // way — these are authorised URLs and must never sit in a shared
            // proxy where the next viewer inherits them.
            'Cache-Control' => $isPlaylist ? 'private, max-age=5' : 'private, max-age=31536000, immutable',
        ]);
    }

    /** The poster frame, for a card or a player's first paint. */
    public function poster(MediaFile $file)
    {
        $this->authorize($file, publicEventOk: true);

        $rel = trim((string) $file->hls_rel_path, '/').'/poster.jpg';
        $abs = rtrim(config('media.local_root'), '/').'/'.$rel;

        if (! filled($file->hls_rel_path) || ! is_file($abs)) {
            abort(404);
        }

        return $this->send($abs, $rel, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * The original file — progressive playback, or a download.
     *
     * Streamed off its vault in place when the vault allows it (a mounted share),
     * which is the case where nothing is ever copied to this server. An SMB vault
     * has to be fetched first; that is the cost of that driver, and the reason
     * the storage page says so when you attach one.
     */
    public function original(Request $request, MediaFile $file)
    {
        $this->authorize($file);

        $abs = $this->vaults->inPlacePath($file) ?? $this->vaults->ensureLocal($file);

        if ($abs === null || ! is_file($abs)) {
            abort(404);
        }

        $download = $request->boolean('download');

        // A stored file's own name is a uuid, so a download gets a name a person
        // can recognise — built here, never taken from the client.
        $filename = $download
            ? ($file->original_name ?: 'bout-'.substr($file->uuid, 0, 8).'.'.pathinfo($file->rel_path, PATHINFO_EXTENSION))
            : null;

        $response = response()->file($abs, array_filter([
            'Content-Type' => $file->mime ?: 'video/mp4',
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ]));

        if ($download && $response instanceof BinaryFileResponse) {
            $response->setContentDisposition('attachment', (string) $filename);
        }

        return $response;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Authorisation
     ────────────────────────────────────────────────────────────────────── */

    /**
     * May the current viewer see this file?
     *
     * Resolved through the EVENT, because that is where the answer already lives:
     * an event decides who can see its footage exactly as it decides who can see
     * its draw. Media with no event has no such authority to defer to, so it is
     * restricted to a super-admin rather than guessed about.
     *
     * A refusal is a 404, not a 403 — whether a given uuid exists is itself
     * information, and this route is enumerable by definition.
     */
    private function authorize(MediaFile $file, bool $publicEventOk = false): void
    {
        $user = Auth::user();
        $event = $this->eventFor($file);

        // An event whose page anybody may open publishes its FOOTAGE too.
        //
        // Added 2026-09-02, when the public event page grew a gallery. It is the
        // organiser's own switch and nothing else — turning the public page off
        // closes this again in the same instant — and it is narrow in two ways
        // that matter:
        //
        //   · Playback only. `$publicEventOk` is passed by hls() and poster()
        //     and NOT by original(), because the original of a long bout is a
        //     gigabyte: an open door to it is cheap to knock on and ruinous to
        //     answer (CLAUDE.md → attack class 14). A public viewer gets the
        //     ladder, which is what a player needs anyway.
        //   · Additive. Every existing path below is untouched; this only ever
        //     says yes where the old code would have said no.
        if ($publicEventOk
            && $event !== null
            && app(\App\Events\Support\PublicEvent::class)->isPublic($event)) {
            return;
        }

        if ($user === null) {
            abort(404);
        }

        if ($event === null) {
            abort_unless($user->hasRole('super-admin'), 404);

            return;
        }

        if ($this->access->visible($event, $user)) {
            return;
        }

        // An athlete may always watch the bout they fought.
        //
        // `visible()` answers a different question — may this person see this
        // EVENT — and it says no for an internal-scope event they are not a
        // member of, and no for every archived event. Both are right for a draw
        // and wrong for footage of the reader's own fight: a visiting club's
        // competitor was filmed by us, at an event they were entered into, and
        // an event becoming archived is how competitions END, not a reason to
        // take somebody's own bout away from them.
        //
        // Deliberately narrow. This grants THEIR bout, resolved from the file,
        // and nothing else: not the division, not the event, not the next mat.
        abort_unless($this->competedInFilmedBout($file, $user), 404);
    }

    /**
     * Is this viewer a competitor in the bout these bytes are of?
     *
     * Resolved from the file rather than from the request, so a URL cannot ask
     * about a bout other than its own. A file with no bout — mat footage, an
     * unassigned clip — is not covered here and stays with `visible()`.
     */
    private function competedInFilmedBout(MediaFile $file, \App\Members\Models\User $user): bool
    {
        $matchId = \App\Models\EventRecording::where('media_file_id', $file->id)
            ->whereNotNull('match_id')
            ->value('match_id')
            ?? $file->owner?->match_id;

        if ($matchId === null) {
            return false;
        }

        $match = \App\Models\EventMatch::find($matchId);

        if ($match === null) {
            return false;
        }

        $mine = \App\Models\ClubEventRegistration::where('user_id', $user->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->exists();

        return $mine;
    }

    /** The event these bytes belong to, via the owner or the ingest metadata. */
    private function eventFor(MediaFile $file): ?ClubEvent
    {
        $owner = $file->owner;

        $eventId = $owner?->event_id ?? ($file->meta['event_id'] ?? null);

        return $eventId ? ClubEvent::find($eventId) : null;
    }

    /* ──────────────────────────────────────────────────────────────────────
     | Sending bytes
     ────────────────────────────────────────────────────────────────────── */

    /**
     * Hand the file to nginx when it can send it, otherwise send it ourselves.
     *
     * X-Accel-Redirect only ever names a path under the LOCAL media root, which
     * is the one place the internal location maps onto. That is why the relative
     * path is passed in rather than derived from the absolute one: nothing a
     * caller supplies, and nothing off a vault, can become an internal redirect.
     */
    private function send(string $abs, string $relativeToRoot, array $headers): Response
    {
        $prefix = config('media.internal_prefix');

        if (filled($prefix)) {
            return response('', 200, array_merge($headers, [
                'X-Accel-Redirect' => rtrim($prefix, '/').'/'.ltrim($relativeToRoot, '/'),
            ]));
        }

        return response()->file($abs, $headers);
    }

    /**
     * A path from a playlist, constrained to what a ladder actually contains.
     *
     * Segments and variant playlists only: one optional directory level, then a
     * file whose name is ours. Anything else — traversal, an absolute path, a
     * name with a null byte — is refused rather than sanitised into something
     * plausible.
     */
    private function safeSubPath(string $path): ?string
    {
        $clean = ltrim(str_replace('\\', '/', $path), '/');

        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, "\0")) {
            return null;
        }

        return preg_match('#^(?:[A-Za-z0-9_-]+/)?[A-Za-z0-9_-]+\.(m3u8|ts|jpg)$#', $clean) === 1
            ? $clean
            : null;
    }
}
