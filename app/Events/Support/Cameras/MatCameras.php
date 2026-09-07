<?php

namespace App\Events\Support\Cameras;

use App\Models\ClubEvent;
use App\Models\EventCamera;
use App\Models\EventCameraClip;
use Illuminate\Support\Collection;

/**
 * The cameras on ONE mat, as the scoring table sees and drives them.
 *
 * The event console (`<x-court-screens>`) already lists every camera on an
 * event for an organiser sitting at a laptop. This is the other place the
 * question gets asked, and it is asked differently: the person at the scoring
 * table is looking at ONE mat, is holding the tablet that starts and stops
 * those cameras, and needs to know before the final — not after it — whether
 * angle 2 has room left on it and whether yesterday's bouts ever left the
 * phone.
 *
 * ── Why this is one class and not three ────────────────────────────────────
 *
 * Three sports run consoles, and a camera does not know which sport it is
 * pointed at. Everything here is therefore sport-neutral and every console
 * calls it: the panel is COMMON (Shared Stays Shared), and the only per-sport
 * part is which token door the request came through.
 *
 * ── Intent and fact are kept apart ─────────────────────────────────────────
 *
 * Every order is a message to a phone on a hall's wifi, so it can be missed. So
 * an order that changes how the camera RUNS is also written down as intent
 * (`event_cameras.settings`) and handed back on the phone's next config beat —
 * a camera that was asleep when somebody pressed 60fps still ends up at 60fps.
 * What the phone reports it is actually running lands in `reported_settings`,
 * and the console shows the two apart. It never shows an asked-for value as if
 * it were true, for the same reason `broadcasting` and `on_air` are separate
 * fields: a panel that conflates them lies at exactly the moment somebody is
 * relying on it.
 *
 * Nothing here decides WHO may call it. Both doors authorise first — the
 * signed-in console through EventAccess, a paired scoring table through its own
 * device — and then hand over an event and a court that were resolved on the
 * server, never taken from the request.
 */
class MatCameras
{
    /**
     * What the console may ask a camera to change about how it films.
     *
     * A whitelist, and a narrow one: this is a value that arrives from a
     * browser, is stored, and is then handed to a phone that will act on it, so
     * anything not named here is dropped rather than passed through.
     */
    public const SETTINGS = ['fps', 'zoom', 'exposure', 'auto_upload'];

    /** Orders about footage the phone is already holding. */
    public const FOOTAGE_ACTIONS = ['upload', 'play', 'purge', 'delete', 'wipe', 'cancel'];

    /**
     * Everything the mat's camera panel draws.
     *
     * One query for the cameras, one for their clips — never one per camera,
     * because this is polled by a tablet at a mat all day.
     *
     * @return array{court: string, cameras: array<int, array<string, mixed>>, max: int}
     */
    public static function panel(ClubEvent $event, string $court): array
    {
        $cameras = CameraFleet::forCourt($event, $court);

        $clips = EventCameraClip::query()
            ->whereIn('camera_id', $cameras->pluck('id'))
            /*
             * THIS event's footage, not this phone's whole history.
             *
             * A camera is unpaired at the end of one competition and claimed
             * onto the next, keeping its row and every clip it ever filed. Left
             * unscoped, a September console listed a bout from an August event
             * — with `on_device` false, because the phone had long since been
             * cleared — so the panel opened on a greyed-out row nobody could
             * act on, and today's recordings were nowhere to be seen.
             */
            ->where('event_id', $event->id)
            // The bout number is the only thing a clip needs from its match,
            // and eager-loading it is the difference between two queries and
            // one per clip on a panel a tablet polls all day.
            ->with('match:id,match_no')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->groupBy('camera_id');

        return [
            'court' => $court,
            'max' => CameraFleet::MAX_PER_COURT,
            'cameras' => $cameras
                ->map(fn (EventCamera $camera) => static::present($camera, $clips->get($camera->id, collect())))
                ->values()
                ->all(),
        ];
    }

    /**
     * One camera, its telemetry, its settings and its footage.
     *
     * Built on the model's own `present()` so the mat panel and the event
     * console cannot describe the same camera two different ways.
     *
     * @param  Collection<int, EventCameraClip>  $clips
     * @return array<string, mixed>
     */
    public static function present(EventCamera $camera, Collection $clips): array
    {
        $reported = static::clean($camera->reported_settings ?? []);
        $wanted = static::clean($camera->settings ?? []);

        return $camera->present() + [
            // The two halves of "what is this camera set to", never merged.
            // `settings` is what the phone says; `pending` is what it has been
            // asked for and has not confirmed yet.
            'settings' => $reported,
            'pending' => array_filter(
                $wanted,
                fn ($value, $key) => ! array_key_exists($key, $reported) || $reported[$key] !== $value,
                ARRAY_FILTER_USE_BOTH,
            ),
            'storage_total_gb' => $camera->storage_total_bytes
                ? round($camera->storage_total_bytes / 1073741824, 1)
                : null,
            'device_name' => $camera->device_name,
            'app_version' => $camera->app_version,
            // Every clip this event knows about, newest first — including ones
            // whose file has since been deleted at the phone, which is the
            // difference between "we never filmed it" and "it is gone" — plus
            // whatever the phone is holding that this event has NO row for.
            'footage' => static::footageList($camera, $clips),
            'on_phone' => is_array($camera->reported_clips)
                ? count($camera->reported_clips)
                : $clips->where('on_device', '!==', false)->count(),
            'in_vault' => $clips->filter(fn ($c) => $c->play_video_key !== null)->count(),
        ];
    }

    /**
     * The list the panel draws: this event's clips, plus the phone's orphans.
     *
     * (Not to be confused with footage() below, which SENDS an order about
     * footage. This one describes it.)
     *
     * The second half is the point. A clip reaches the index only if the phone
     * managed to file it — one call, at the end of a bout, over a hall's wifi —
     * and a camera that was at another competition, or that missed that one
     * call, ends up carrying files no console can see. The operator is then
     * looking at a phone with two recordings on it and a panel showing none,
     * which reads as broken software and is, in effect, exactly that.
     *
     * So a file the phone reports and this event has no row for is listed too,
     * and labelled for what it is: on the phone, not in this event's index. It
     * can be played and it can be deleted — both are questions about the phone's
     * own disk. It cannot be uploaded from here, because there is no bout on
     * this event to attach it to; the camera files it itself when it can, and
     * refuses to attach a week-old bout to today's competition.
     *
     * @param  Collection<int, EventCameraClip>  $clips
     * @return array<int, array<string, mixed>>
     */
    private static function footageList(EventCamera $camera, Collection $clips): array
    {
        $rows = $clips->map(fn (EventCameraClip $clip) => static::clip($clip))->values();

        $known = $rows->pluck('ref')->filter()->all();

        // Everything on the phone that this event has never been told about.
        // Untrusted text off a device, so it is length-capped and rendered with
        // textContent at the far end like every other value here.
        $orphans = collect(is_array($camera->reported_clips) ? $camera->reported_clips : [])
            ->filter(fn ($ref) => is_string($ref) && $ref !== '' && ! in_array($ref, $known, true))
            ->map(fn (string $ref) => [
                'id' => null,
                'ref' => mb_substr($ref, 0, 190),
                'bout' => null,
                'match_id' => null,
                'seconds' => null,
                'bytes' => null,
                'at' => null,
                'status' => null,
                'uploaded' => false,
                'uploaded_at' => null,
                'on_device' => true,
                // What makes this row read differently: the file is here, the
                // index is not.
                'unfiled' => true,
            ])
            ->values();

        return $rows->concat($orphans)->all();
    }

    /**
     * One recording, as a mat may know it.
     *
     * No storage path, no media id, no token — a row here says which bout it
     * is, how big it is, and whether the server has it. `uploaded` is the
     * question the panel exists to answer and is derived from the same field
     * the phone itself trusts: a key exists only once the bytes landed and were
     * accepted.
     *
     * @return array<string, mixed>
     */
    public static function clip(EventCameraClip $clip): array
    {
        return [
            'id' => $clip->id,
            'ref' => $clip->local_ref,
            'bout' => $clip->match?->match_no,
            'match_id' => $clip->match_id,
            'seconds' => $clip->duration_seconds,
            'bytes' => $clip->bytes,
            'at' => $clip->started_at?->format('H:i'),
            'status' => $clip->play_status,
            'uploaded' => $clip->play_video_key !== null,
            'uploaded_at' => $clip->uploaded_at?->diffForHumans(),
            // NULL is "no phone has said" — an old build, or one that has not
            // beaten since this shipped. Shown as unknown, never as missing.
            'on_device' => $clip->on_device,
            // This event has a row for it — see footage() for the ones that
            // only exist on the phone.
            'unfiled' => false,
        ];
    }

    /**
     * Resolve one camera on THIS mat, or null.
     *
     * Scoped to the event AND the court, so a console paired to Mat 2 cannot
     * reach Mat 1's cameras by editing an id — the same rule that already
     * governs scoring commands from a token console.
     */
    public static function find(ClubEvent $event, string $court, int $id): ?EventCamera
    {
        return EventCamera::query()
            ->where('id', $id)
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->whereNull('revoked_at')
            ->whereNotNull('claimed_at')
            ->first();
    }

    /**
     * Ask a camera to film differently.
     *
     * Written first, published second: the write is what makes the order
     * survive a phone that was asleep, a broker that was down, or an app that
     * restarted before the message arrived. The publish is only what makes it
     * fast.
     *
     * @param  array<string, mixed>  $settings  already validated by the caller
     * @return array<string, mixed>  the camera as the panel should now draw it
     */
    public static function configure(EventCamera $camera, array $settings): array
    {
        $wanted = static::clean(array_merge($camera->settings ?? [], $settings));

        $camera->forceFill(['settings' => $wanted])->save();

        CameraChannel::send($camera, [
            'action' => 'settings',
            'settings' => $wanted,
            'at' => now()->toIso8601String(),
        ]);

        return static::present($camera->fresh(), $camera->clips()
            ->where('event_id', $camera->event_id)
            ->with('match:id,match_no')
            ->orderByDesc('id')
            ->limit(200)
            ->get());
    }

    /**
     * Ask a camera to do something with footage it is holding.
     *
     * The phone is the one that answers. In particular `wipe` with scope `all`
     * is an ASK, not a guarantee: the phone still refuses to destroy the only
     * copy of a bout unless it was told explicitly, and this endpoint cannot
     * and must not be able to override that on its own — the files are there,
     * not here.
     *
     * @param  array<string, mixed>  $data  action, clip, scope
     */
    public static function footage(EventCamera $camera, array $data): void
    {
        CameraChannel::send($camera, array_filter([
            'action' => $data['action'],
            'clip' => $data['clip'] ?? null,
            'scope' => $data['scope'] ?? null,
            'at' => now()->toIso8601String(),
        ], fn ($v) => $v !== null));
    }

    /**
     * "Tell me how you are, now."
     *
     * A camera beats every 30 seconds, which is right for a phone on a tripod
     * and wrong for somebody standing at the panel waiting for a number to
     * change. This asks for a beat immediately; the answer arrives the ordinary
     * way, through telemetry, so nothing here waits on the phone.
     */
    public static function ping(EventCamera $camera): void
    {
        CameraChannel::send($camera, ['action' => 'report', 'at' => now()->toIso8601String()]);
    }

    /**
     * Keep only the settings we recognise, in the shape we store them in.
     *
     * Applied to BOTH directions — what a console asks for and what a phone
     * reports — because a phone is no more trusted than a browser here: both
     * are strangers writing into a column that is later handed to the other one.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>
     */
    public static function clean(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach (static::SETTINGS as $key) {
            if (! array_key_exists($key, $raw) || $raw[$key] === null) {
                continue;
            }

            $out[$key] = match ($key) {
                // The two rates the app offers. Anything else is somebody
                // typing into the request.
                'fps' => in_array((int) $raw[$key], [30, 60], true) ? (int) $raw[$key] : null,
                // The lens's own range, clamped rather than refused: a phone
                // reporting 8× on a camera that goes to 10 is not an error.
                'zoom' => max(1.0, min(10.0, round((float) $raw[$key], 2))),
                'exposure' => max(-4.0, min(4.0, round((float) $raw[$key], 1))),
                'auto_upload' => (bool) $raw[$key],
                default => null,
            };

            if ($out[$key] === null) {
                unset($out[$key]);
            }
        }

        return $out;
    }
}
