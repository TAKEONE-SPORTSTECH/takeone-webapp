<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventCamera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Why a device did or did not get onto a mat — written down, every time.
 *
 * This exists because the same pairing failure was diagnosed wrongly three
 * times in a row. Each attempt reasoned from the message the organiser
 * reported, and the message was the same string for five different causes, so
 * the reasoning was guesswork wearing a lab coat. The one time a diagnostic
 * happened to be running, the true cause fell out in a single line: the code
 * belonged to the OTHER TAKEONE server.
 *
 * So this is permanent, not a debugging aid to be pulled out afterwards. It
 * answers, for any attempt, the four things nobody can reconstruct later:
 *
 *   · WHAT was sent — the code, the mat, the surface asked for.
 *   · WHERE it was sent — which host, which event, which sport.
 *   · WHAT the code actually IS on this server — a waiting screen, a waiting
 *     camera, a device in some sport's own fleet, or nothing at all. This is
 *     the field that matters: "nothing at all" and "a screen when a camera was
 *     wanted" look identical to the person holding the phone.
 *   · WHAT they were told, verbatim, so a report of "it says the code is wrong"
 *     can be matched to the exact branch that said it.
 *
 * ── What is deliberately NOT recorded ───────────────────────────────────────
 * No device token, no session, no MQTT credential, no personal data. A pairing
 * code IS public — it is printed a metre tall on a wall and photographed by
 * whoever walks past — so recording it leaks nothing that standing in the hall
 * would not. The acting user is recorded by id alone.
 *
 * Read it with `php artisan takeone:pairing`.
 */
class PairingLog
{
    /** A device was successfully put on a mat. */
    public static function paired(Request $request, ClubEvent $event, string $what, string $court, array $extra = []): void
    {
        Log::channel('pairing')->info('paired', array_merge(self::base($request, $event), [
            'outcome' => 'paired',
            'device' => $what,
            'court' => $court,
        ], $extra));
    }

    /**
     * A device was refused, and this is the honest reason.
     *
     * @param  string  $reason  a stable machine slug — grep-able, never translated
     * @param  string  $told    the exact message the organiser saw
     */
    public static function refused(Request $request, ?ClubEvent $event, string $reason, string $told): void
    {
        Log::channel('pairing')->warning('refused', array_merge(self::base($request, $event), [
            'outcome' => 'refused',
            'reason' => $reason,
            'told' => $told,
        ]));
    }

    /** A camera app asked this server for an identity. */
    public static function cameraEnrolled(Request $request, EventCamera $camera): void
    {
        Log::channel('pairing')->info('camera enrolled', [
            'host' => $request->getSchemeAndHttpHost(),
            'ip' => $request->ip(),
            'outcome' => 'enrolled',
            'device' => 'camera',
            'camera_id' => $camera->id,
            'code' => $camera->pairing_code,
            'device_name' => $camera->device_name,
            'app_version' => $camera->app_version,
        ]);
    }

    /** A screen opened the neutral waiting room and was given a code. */
    public static function screenWaiting(Request $request, PendingScreen $screen): void
    {
        Log::channel('pairing')->info('screen waiting', [
            'host' => $request->getSchemeAndHttpHost(),
            'ip' => $request->ip(),
            'outcome' => 'waiting',
            'device' => 'screen',
            'screen_id' => $screen->id,
            'code' => $screen->pairing_code,
            'agent' => substr((string) $request->userAgent(), 0, 120),
        ]);
    }

    /**
     * The shared context every line carries.
     *
     * `code_is` is the whole point: it resolves the submitted code against every
     * place a code can live ON THIS SERVER, so a line either names what the code
     * is or says `nothing`. A `nothing` next to a code the organiser is plainly
     * reading off a screen means the code belongs to a different server — which
     * is not a guess anybody could make from the message alone.
     */
    private static function base(Request $request, ?ClubEvent $event): array
    {
        $code = strtoupper(trim((string) $request->input('code')));

        return [
            'host' => $request->getSchemeAndHttpHost(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'event' => $event?->uuid,
            'sport' => $event?->sport,
            'code' => $code !== '' ? $code : null,
            'code_is' => self::whatIs($code),
            'court' => $request->input('court'),
            'surface' => $request->input('surface'),
        ];
    }

    /** What does this code resolve to on THIS server? */
    private static function whatIs(string $code): string
    {
        if ($code === '') {
            return 'empty';
        }

        if (! preg_match('/^[A-Z0-9]{6}$/', $code)) {
            return 'malformed';
        }

        if (PendingScreen::pairable($code)) {
            return 'waiting screen (/screen)';
        }

        if (EventCamera::pairable($code)) {
            return 'waiting camera';
        }

        foreach (HallScreenRouter::fleets() as $sport => $model) {
            if (class_exists($model) && $model::pairable($code)) {
                return "waiting screen in the {$sport} fleet";
            }
        }

        return 'nothing on this server';
    }
}
