<?php

namespace App\Play;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only way this application talks to TAKEONE Play.
 *
 * Every call is gated on config('play.enabled'), which is OFF by default: with
 * the flag down this class makes no network calls at all, so an event must be
 * indistinguishable from one running before any of this existed
 * (VIDEO-INTEGRATION.md §0).
 *
 * Nothing here throws. Play being slow, down, or refusing a request is a normal
 * condition on an event day, and the database is the source of truth either way
 * — so a failure returns null and the caller records it, rather than propagating
 * an exception into a page render or a scoring path.
 */
class PlayClient
{
    public function enabled(): bool
    {
        return (bool) config('play.enabled');
    }

    /**
     * Push one bout's competition truth to Play.
     *
     * Authenticated as the service client with the `match:write` ability — never
     * as a person, never through a session. Returns Play's answer, or null when
     * the call could not be made at all, so the caller can tell "refused" from
     * "unreachable" and retry only the latter.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, stale?: bool, revision?: int}|null
     */
    public function pushMatch(string $videoKey, array $payload): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $token = (string) config('play.token');

        if ($token === '') {
            // Saying so beats a silent no-op that looks like a successful push.
            Log::warning('play.push.no_token');

            return null;
        }

        $url = rtrim((string) config('play.url'), '/').'/api/v1/matches/'.rawurlencode($videoKey);

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('play.timeout', 10))
                ->acceptJson()
                ->asJson()
                ->put($url, $payload);
        } catch (ConnectionException $e) {
            Log::warning('play.push.unreachable', ['video' => $videoKey]);

            return null;
        }

        if (! $response->successful()) {
            // Status only — the body could echo the payload, and the payload
            // carries competitor names. Never the token, which Http masks anyway.
            Log::warning('play.push.rejected', ['video' => $videoKey, 'status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * A video's annotated timeline, straight from Play.
     *
     * Reads the endpoint the Highlights panel already uses. It is deliberately
     * PUBLIC on Play and needs no token — and it enforces Play's own
     * Video::canView(), so a private video answers 403 to an anonymous caller.
     * That is a legitimate outcome, not a failure: it means the video is not
     * ours to mirror. Distinguished from a transport error by the caller.
     *
     * @return array{rounds: array<int, array<string, mixed>>}|null
     */
    public function timeline(string $videoKey): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $url = rtrim((string) config('play.url'), '/').'/videos/'.rawurlencode($videoKey).'/match-data';

        try {
            $response = Http::timeout((int) config('play.timeout', 10))
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $e) {
            // The message can carry the host but never a credential; this call
            // is unauthenticated, so there is no token to leak here.
            Log::warning('play.timeline.unreachable', ['video' => $videoKey]);

            return null;
        }

        if ($response->status() === 403 || $response->status() === 404) {
            // Not visible to us, or gone. Both mean "nothing to mirror".
            return null;
        }

        if (! $response->successful()) {
            Log::warning('play.timeline.failed', ['video' => $videoKey, 'status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['success'] ?? false) !== true || ! is_array($data['rounds'] ?? null)) {
            Log::warning('play.timeline.malformed', ['video' => $videoKey]);

            return null;
        }

        /*
         * `reviews` is dropped here and never travels further.
         *
         * Play returns every coach_review on the video to every viewer, and that
         * table has no column that could scope one to a club — so one video shared
         * by two opposing clubs would leak each side's notes to the other
         * (VIDEO-INTEGRATION.md §8.4). Coaching notes live only in takeone's own
         * match_annotations, filtered per viewer. Discarding them at the boundary
         * means no later change to the mirror can accidentally start storing them.
         */
        return ['rounds' => $data['rounds']];
    }
}
