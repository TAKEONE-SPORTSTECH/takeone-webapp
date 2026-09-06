<?php

namespace App\Events\Support\Cameras;

use App\Models\EventCamera;

/**
 * The realtime channel belonging to one camera.
 *
 * The whole point of the camera app is that nobody touches it once it is
 * clamped in place: the mat says hajime and four phones start, the bout is
 * filed and four phones stop. That only works if the instruction ARRIVES —
 * polling would mean a camera starting several seconds late, which on a
 * ninety-second bout is the first exchange missed.
 *
 * Modelled on ScreenChannel, and for the same reasons: derived topic (no column
 * to migrate, no second secret), subscribe-only JWT, one topic per device.
 *
 * What travels here, and why it is safe to:
 *
 *   {action: 'record', match: {...}}  — start rolling; this is the bout.
 *   {action: 'stop'}                  — stop rolling and file the clip.
 *   {action: 'standby', match: {...}} — a bout was loaded but not started.
 *   {action: 'paired'|'unpaired'}     — your assignment changed; re-read it.
 *
 * The bout identification carried by 'record' is the same running order that is
 * projected on the wall of the hall at size — bout number, round, and the two
 * names being announced over the PA as it starts. Nothing personal beyond that
 * enters the payload, and the camera has no way to ask for more.
 */
class CameraChannel
{
    private const PREFIX = 'camera';

    /** This camera's topic. Derived from the token hash under the app key. */
    public static function topic(EventCamera $camera): string
    {
        $prefix = trim((string) \Realtime()->config('prefix', 'takeone'), '/');

        $key = substr(hash_hmac(
            'sha256',
            'event-camera|'.$camera->id.'|'.$camera->getAttributes()['token_hash'],
            (string) config('app.key'),
        ), 0, 32);

        return sprintf('%s/%s/%s', $prefix, self::PREFIX, $key);
    }

    /**
     * What the app needs to open the socket, or null when realtime is off — in
     * which case the app falls back to its config poll and still works, just
     * with a start that lands a few seconds late.
     *
     * @return array{ws_url: string, username: string, password: string, topic: string}|null
     */
    public static function credentials(EventCamera $camera): ?array
    {
        if (! \Realtime()->enabled()) {
            return null;
        }

        $secret = (string) \Realtime()->config('jwt.secret');
        $wsUrl = (string) \Realtime()->config('broker.ws_url');

        if ($secret === '' || $wsUrl === '') {
            return null;
        }

        $topic = self::topic($camera);
        $username = 'camera-'.$camera->id;

        // Long-lived on purpose: a camera is set up in the morning and left
        // alone for the day, and it has nobody to ask for a new credential.
        // It is worth less than the device token behind it — subscribe-only,
        // one topic, and no read access to anything in the product.
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
     * Tell one camera something. Best-effort, like every other push in the app:
     * the database is the truth and the app's own poll is the backstop, so a
     * broker outage delays a camera rather than breaking it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function send(EventCamera $camera, array $payload): void
    {
        self::sendMany([$camera], $payload);
    }

    /**
     * Tell every camera on a mat the same thing, in one publish.
     *
     * @param  iterable<EventCamera>  $cameras
     * @param  array<string, mixed>  $payload
     */
    public static function sendMany(iterable $cameras, array $payload): void
    {
        if (! \Realtime()->enabled()) {
            return;
        }

        $messages = [];

        foreach ($cameras as $camera) {
            $messages[] = ['topic' => self::topic($camera), 'payload' => $payload];
        }

        if ($messages === []) {
            return;
        }

        // Never allowed to break scoring. A command that could not be published
        // leaves the mat exactly as it was; the phone picks the change up on
        // its next config poll.
        rescue(fn () => \Realtime()->publishMany($messages), null, false);
    }

    /** HS256, hand-rolled to match the rest of the realtime plugin's tokens. */
    private static function jwt(array $claims, string $secret): string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $encode($claims);
        $signature = rtrim(strtr(base64_encode(
            hash_hmac('sha256', $header.'.'.$body, $secret, true)
        ), '+/', '-_'), '=');

        return $header.'.'.$body.'.'.$signature;
    }
}
