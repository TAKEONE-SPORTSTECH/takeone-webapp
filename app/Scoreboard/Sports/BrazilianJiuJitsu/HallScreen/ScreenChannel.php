<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen;

use App\Models\ClubEvent;

/**
 * The realtime channel belonging to one hall screen.
 *
 * A wall screen is not a user: it has no session, no account, and nothing in
 * the app's user-topic scheme fits it. So it gets its own subtree and its own
 * short contract — the server tells a screen that its page changed, or hands it
 * the queue it should now be showing.
 *
 * Why not simply let it poll: cog/WPE on DRM throttles background timers to
 * minutes (measured ~2m50s for a 60s interval on a low-powered screen), so an organiser who
 * unpaired a screen stood in front of it watching the wrong mat's queue. An
 * inbound socket message wakes the page immediately and sidesteps the throttle
 * entirely.
 *
 * Two shapes travel here, and the difference is whether the PAGE changed:
 *
 *   {action: paired|unpaired}   — you are a different screen now. Reload and
 *                                 let the server decide what you are.
 *   {action: board, payload:{}} — same page, new numbers. Redraw in place.
 *
 * Why the board's payload rides along instead of being fetched. Screens are
 * remote — a screen in a hall on the other side of a WAN link, not on our network —
 * so a bare nudge cost a second internet round trip back to the origin, plus a
 * PHP render, before a single pixel could change. Carrying the queue in the
 * message deletes that hop: the frame that wakes the screen already contains
 * what to draw. It also survives the case the nudge could not: a screen whose
 * link blipped reconnects and asks once for the current board, rather than
 * sitting on a finished bout until the next result happens to land.
 *
 * This reverses this channel's original "nothing about the competition travels
 * here" rule, so: the board's content is the least secret thing in the product.
 * It is projected onto a wall, at size, for a room full of strangers, and the
 * organiser's console publishes the same running order to every athlete in the
 * event. The topic is still an HMAC of the device token under the app key —
 * unguessable from anything visible on the screen and not reversible into the
 * token — and the screen's JWT is still subscribe-only on that one topic. What
 * would leak, to someone who already had the topic, is a queue of bout numbers
 * and competitor names that anyone standing in the venue can read. Personal
 * data beyond that never enters the payload: the board already withholds a
 * competitor's photo unless they made it public.
 */
class ScreenChannel
{
    /** Devices subscribe under this, one leaf per screen. */
    private const PREFIX = 'bjj-screen';

    /**
     * Scoring consoles subscribe under this, one leaf per MAT.
     *
     * A screen's topic is derived from its device row, which is right for a
     * screen and useless for a console: the organiser's laptop is not a device
     * and has no row, and the two consoles on one mat — the laptop at the table
     * and the tablet in the referee's hand — have to hear the same things.
     *
     * So the mat gets a topic of its own. Both consoles listen on it, every
     * message this class already sends to the mat's screens is sent here too,
     * and a second official's point appears on the first official's console the
     * moment it is scored. Without it the consoles only learned anything by
     * POSTing, so whichever one was not being touched sat on a stale score —
     * and a mat with no wall screen at all had no live link anywhere.
     */
    private const MAT_PREFIX = 'bjj-mat';

    /**
     * This screen's topic. Derived, so there is no column to migrate and no
     * second secret to keep in step with the token.
     */
    public static function topic(ScreenDevice $device): string
    {
        $prefix = trim((string) \Realtime()->config('prefix', 'takeone'), '/');

        $key = substr(hash_hmac(
            'sha256',
            'bjj-screen|'.$device->id.'|'.$device->getAttributes()['token_hash'],
            (string) config('app.key'),
        ), 0, 32);

        return sprintf('%s/%s/%s', $prefix, self::PREFIX, $key);
    }

    /**
     * This mat's topic. Derived the same way and for the same reason — no column
     * to migrate, and nothing on a console's screen that could be turned back
     * into it.
     */
    public static function matTopic(ClubEvent $event, string $court): string
    {
        $prefix = trim((string) \Realtime()->config('prefix', 'takeone'), '/');

        $key = substr(hash_hmac(
            'sha256',
            'bjj-mat|'.$event->id.'|'.$court,
            (string) config('app.key'),
        ), 0, 32);

        return sprintf('%s/%s/%s', $prefix, self::MAT_PREFIX, $key);
    }

    /**
     * What a scoring console needs to open the socket, or null when realtime is
     * off — in which case the console still works exactly as it did before this
     * existed: it draws whatever its own commands hand back.
     *
     * Subscribe-only on one mat, like a screen's. The console can already read
     * and write everything on this mat through its own authorised endpoints, so
     * the credential grants it nothing it did not have; it only lets it be TOLD.
     *
     * @return array{ws_url: string, username: string, password: string, topic: string}|null
     */
    public static function consoleCredentials(ClubEvent $event, string $court): ?array
    {
        if (! \Realtime()->enabled()) {
            return null;
        }

        $secret = (string) \Realtime()->config('jwt.secret');
        $wsUrl = (string) \Realtime()->config('broker.ws_url');

        if ($secret === '' || $wsUrl === '') {
            return null;
        }

        $topic = self::matTopic($event, $court);
        $username = 'console-'.$event->id.'-'.substr(md5($court), 0, 8);

        $claims = [
            'iss' => 'takeone-realtime',
            'sub' => $username,
            'username' => $username,
            'iat' => time(),
            // Long-lived for the same reason a screen's is: a scoring table is
            // opened at the start of a competition and left alone. It expiring
            // mid-event would drop the link on the one surface that must not be
            // touched while a bout is running.
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
     * What the page needs to open the socket, or null when realtime is off — in
     * which case the board's heartbeat is the only signal and everything still
     * works, just slowly.
     *
     * @return array{ws_url: string, username: string, password: string, topic: string}|null
     */
    public static function credentials(ScreenDevice $device): ?array
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
    public static function notify(ScreenDevice $device, string $action): void
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
     * Hand the screens on a mat their new queue.
     *
     * A bout ends and the board behind it is instantly wrong — the finished
     * fight still at the top, the next one not called. Everyone else following
     * the event already learns this over their own channel; the wall is the one
     * surface that had no way to hear it.
     *
     * Scoped to the mat when one is given: Mat 2's screen has no reason to
     * redraw because Mat 1 finished a bout.
     *
     * One connection for the whole fan-out, and one payload built per MAT
     * rather than per screen. Both matter because this runs inside the
     * organiser's request while they wait: the old shape opened a fresh TCP
     * connection and MQTT handshake per screen, so a four-mat event paid four
     * of them to say four identical things.
     *
     * `$message` overrides what is sent. Two things travel to a mat and they are
     * not the same shape: the QUEUE changed (a bout ended, the running order
     * moved) or the BOUT changed (a point, the clock, hajime). The queue is
     * rebuilt per mat and differs between them; a bout message is one payload
     * the caller already has, identical for every screen on that mat. Passing it
     * in avoids rebuilding a board nobody asked for on every single keypress at
     * the scoring table.
     */
    public static function notifyCourt(ClubEvent $event, ?string $court, ?array $message = null): void
    {
        if (! \Realtime()->enabled()) {
            return;
        }

        $screens = ScreenDevice::where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->whereNotNull('court')
            ->when($court, fn ($q) => $q->where('court', $court))
            ->get();

        // Every mat this message concerns, whether or not anything is hanging on
        // a wall above it. A mat's SCREENS are optional; its scoring table is
        // not, and the table listens on the mat's own topic. Returning early on
        // an empty screen list — which is what this did — meant the one surface
        // that is always present was the one surface never told anything.
        $courts = $court !== null && $court !== ''
            ? [$court]
            : $event->matches()->whereNotNull('court')->distinct()->pluck('court')->all();

        $courts = array_values(array_filter(array_unique(
            array_merge($courts, $screens->pluck('court')->all())
        )));

        if ($screens->isEmpty() && ! $courts) {
            return;
        }

        $display = app(ScreenBoard::class);
        $boards = [];
        $messages = [];

        // Two screens on the same mat show the same thing, and so does the mat's
        // own topic — build it once per mat.
        $payloadFor = fn (string $mat): array => $message ?? [
            'action' => 'board',
            'payload' => $boards[$mat] ??= $display->payload($event, $mat),
        ];

        foreach ($screens as $screen) {
            $messages[] = [
                'topic' => self::topic($screen),
                'payload' => $payloadFor($screen->court),
            ];
        }

        foreach ($courts as $mat) {
            $messages[] = [
                'topic' => self::matTopic($event, $mat),
                'payload' => $payloadFor($mat),
            ];
        }

        if (! $messages) {
            return;
        }

        rescue(fn () => \Realtime()->publishMany($messages), null, false);
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
