<?php

namespace App\Events\Sports\Taekwondo\Tournament\CourtDisplay;

/**
 * The realtime channel belonging to one hall screen.
 *
 * A wall screen is not a user: it has no session, no account, and nothing in
 * the app's user-topic scheme fits it. So it gets its own subtree and its own
 * short contract — the server tells a screen when it has been paired or
 * unpaired, and the screen reloads into whatever it is now. Nothing else is
 * ever published here.
 *
 * Why not simply let it poll: cog/WPE on DRM throttles background timers to
 * minutes (measured ~2m50s for a 60s interval on a Pi 3B), so an organiser who
 * unpaired a screen stood in front of it watching the wrong mat's queue. An
 * inbound socket message wakes the page immediately and sidesteps the throttle
 * entirely.
 *
 * Topic secrecy is deliberate but not load-bearing: the key is derived from the
 * device's stored token hash through the app key, so it cannot be guessed from
 * anything on the wall, and it cannot be reversed into the device's token. Even
 * if it leaked, the subtree carries one word — "paired" or "unpaired" — and the
 * screen's JWT allows subscribe only, never publish.
 */
class ScreenChannel
{
    /** Devices subscribe under this, one leaf per screen. */
    private const PREFIX = 'screen';

    /**
     * This screen's topic. Derived, so there is no column to migrate and no
     * second secret to keep in step with the token.
     */
    public static function topic(CourtDisplayDevice $device): string
    {
        $prefix = trim((string) \Realtime()->config('prefix', 'takeone'), '/');

        $key = substr(hash_hmac(
            'sha256',
            'court-screen|'.$device->id.'|'.$device->getAttributes()['token_hash'],
            (string) config('app.key'),
        ), 0, 32);

        return sprintf('%s/%s/%s', $prefix, self::PREFIX, $key);
    }

    /**
     * What the page needs to open the socket, or null when realtime is off — in
     * which case the board's heartbeat is the only signal and everything still
     * works, just slowly.
     *
     * @return array{ws_url: string, username: string, password: string, topic: string}|null
     */
    public static function credentials(CourtDisplayDevice $device): ?array
    {
        if (! \Realtime()->enabled()) {
            return null;
        }

        $secret = (string) \Realtime()->config('jwt.secret');
        $wsUrl = (string) \Realtime()->config('broker.ws_url');

        if ($secret === '' || $wsUrl === '') {
            return null;
        }

        $topic = self::topic($device);
        $username = 'screen-'.$device->id;

        // Long-lived on purpose: a hall screen is opened once and left running
        // for the duration of a competition, and it has no way to ask a human
        // for a new credential. It is worth less than the device token it sits
        // behind — subscribe-only, one topic, no read access to anything.
        $claims = [
            'iss' => 'takeone-realtime',
            'sub' => $username,
            'username' => $username,
            'iat' => time(),
            'exp' => time() + (14 * 86400),
            'acl' => [
                ['permission' => 'allow', 'action' => 'subscribe', 'topic' => $topic],
                ['permission' => 'deny', 'action' => 'publish', 'topic' => '#'],
            ],
        ];

        return [
            'ws_url' => $wsUrl,
            'username' => $username,
            'password' => self::jwt($claims, $secret),
            'topic' => $topic,
        ];
    }

    /**
     * Tell a screen its assignment changed. Best-effort, like every other push
     * in the app: the database is the truth and the board's heartbeat is the
     * backstop, so a broker outage delays a screen rather than breaking it.
     */
    public static function notify(CourtDisplayDevice $device, string $action): void
    {
        if (! \Realtime()->enabled()) {
            return;
        }

        rescue(fn () => \Realtime()->publishMany([[
            'topic' => self::topic($device),
            // Deliberately no event, no mat, no names. The screen re-fetches its
            // whole page and is authorized by its token on the way, so nothing
            // about the competition needs to travel on this topic.
            'payload' => ['action' => $action],
        ]]), null, false);
    }

    /**
     * HS256, encoded inline.
     *
     * The realtime package mints these for USERS and keeps its encoder private;
     * a screen needs a different subject and a different ACL, and reaching for
     * the user issuer would hand a device a person's whole subtree. Twelve lines
     * of well-specified encoding is the smaller price.
     */
    private static function jwt(array $claims, string $secret): string
    {
        $b64 = fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $header = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $b64(json_encode($claims));
        $signature = $b64(hash_hmac('sha256', $header.'.'.$payload, $secret, true));

        return $header.'.'.$payload.'.'.$signature;
    }
}
