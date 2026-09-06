<?php

namespace App\Events\Support\Cameras;

use App\Models\ClubEvent;
use App\Models\EventCamera;
use Illuminate\Support\Collection;

/**
 * The cameras on a mat, and the one rule that drives them: the mat decides.
 *
 * Nobody presses record. The scoring table calls hajime, and every phone
 * pointed at that mat starts within a frame or two of each other; the bout is
 * decided and filed, and they all stop. That is the whole feature, and it is
 * why this lives beside the scoring engine rather than in a controller — the
 * only place that reliably knows a bout began is the command that began it.
 *
 * Sport-neutral on purpose. Screens are per-sport because what they DRAW is
 * per-sport; a camera draws nothing, and "point a lens at the mat, roll while
 * they fight" is the same act in Taekwondo, Karate and everything after them.
 * Each sport's Scoring calls observe() with the same five facts.
 *
 * Nothing here may ever throw into scoring. A mat must not stop because a
 * camera did — see the rescue() in CameraChannel.
 */
class CameraFleet
{
    /**
     * Four lenses per mat.
     *
     * Not arbitrary: it is the most a small hall crew can physically place
     * around one mat (two corners, one overhead, one roaming) and, more to the
     * point, the most that can be reviewed afterwards without the footage
     * becoming its own filing problem. The cap is on LIVE cameras — revoking
     * one frees its angle immediately.
     */
    public const MAX_PER_COURT = 4;

    /** Commands that mean "the bout is running". */
    private const ROLL = ['start'];

    /**
     * Commands that mean "this bout is over, one way or another".
     *
     * `finish` decides it, `commit` files it, `clear` empties the mat, `reset`
     * throws it away and starts again — from a camera's point of view all four
     * end the clip. A fresh hajime after a reset simply opens the next one.
     */
    private const CUT = ['finish', 'commit', 'clear', 'reset'];

    /** A bout is loaded but not started: get ready, and know what this is. */
    private const STANDBY = ['load'];

    /**
     * The live cameras assigned to one mat, in angle order.
     *
     * @return Collection<int, EventCamera>
     */
    public static function forCourt(ClubEvent $event, string $court): Collection
    {
        return EventCamera::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->whereNull('revoked_at')
            ->whereNotNull('claimed_at')
            ->orderBy('angle')
            ->get();
    }

    /**
     * The lowest free angle on this mat, or null when all four are taken.
     *
     * Lowest rather than next: unpairing camera 2 of 4 and adding another
     * should give back angle 2, not angle 5. The angle is how a clip is
     * identified after the fact, so it has to mean a position on the mat.
     */
    public static function nextAngle(ClubEvent $event, string $court): ?int
    {
        $taken = static::forCourt($event, $court)->pluck('angle')->filter()->all();

        for ($angle = 1; $angle <= self::MAX_PER_COURT; $angle++) {
            if (! in_array($angle, $taken, true)) {
                return $angle;
            }
        }

        return null;
    }

    /**
     * What the event console shows: every camera on the event, by mat.
     *
     * Null when there are none AND the event has no mats to put one on — an
     * event that cannot be filmed shows no camera panel at all, the same way a
     * type with no wall boards shows no screen panel.
     *
     * @return array{mats: array<int, string>, cameras: array<int, array<string, mixed>>, max: int}|null
     */
    public static function console(ClubEvent $event): ?array
    {
        $mats = \App\Models\EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->values()->all();

        $cameras = EventCamera::query()
            ->where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->whereNotNull('claimed_at')
            ->orderBy('court')
            ->orderBy('angle')
            ->get()
            ->map(fn (EventCamera $c) => $c->present())
            ->all();

        if ($mats === [] && $cameras === []) {
            return null;
        }

        return ['mats' => $mats, 'cameras' => $cameras, 'max' => self::MAX_PER_COURT];
    }

    /**
     * A scoring command happened on this mat. Tell the cameras if it matters.
     *
     * Called from each sport's Scoring::apply, after the state is saved, in the
     * same place and for the same reason MatchEventLog::record is: it is the
     * one funnel every command passes through, so a camera cannot miss a bout
     * because some other caller took a shortcut.
     *
     * @param  array<string, mixed>  $bout  number/stage/red/blue, as announced
     */
    public static function observe(ClubEvent $event, string $court, string $command, ?int $matchId, array $bout = []): void
    {
        $roll = in_array($command, self::ROLL, true);
        $cut = in_array($command, self::CUT, true);
        $standby = in_array($command, self::STANDBY, true);

        if (! $roll && ! $cut && ! $standby) {
            return;
        }

        rescue(function () use ($event, $court, $command, $matchId, $bout, $roll, $cut, $standby) {
            $cameras = static::forCourt($event, $court);

            if ($cameras->isEmpty()) {
                return;
            }

            if ($roll) {
                // Written BEFORE the publish, so the console shows what was
                // asked for even if the broker is down and the phones never
                // heard it. The phones correct this on their next beat.
                EventCamera::whereIn('id', $cameras->pluck('id'))
                    ->update(['recording' => true, 'recording_match_id' => $matchId]);

                CameraChannel::sendMany($cameras, [
                    'action' => 'record',
                    'match' => static::bout($matchId, $bout),
                    // The phone stamps its own clip, but the mat's clock is the
                    // one every camera shares — so a reviewer can line four
                    // angles up against the officiating log later.
                    'at' => now()->toIso8601String(),
                ]);

                return;
            }

            if ($cut) {
                EventCamera::whereIn('id', $cameras->pluck('id'))
                    ->update(['recording' => false, 'recording_match_id' => null]);

                CameraChannel::sendMany($cameras, [
                    'action' => 'stop',
                    'match' => static::bout($matchId, $bout),
                    'at' => now()->toIso8601String(),
                ]);

                return;
            }

            // Standby: no file is opened, but the phone now knows which bout is
            // about to happen — so the clip it opens on hajime is named before
            // the first exchange rather than after it.
            CameraChannel::sendMany($cameras, [
                'action' => 'standby',
                'match' => static::bout($matchId, $bout),
            ]);
        }, null, false);
    }

    /**
     * Tell every camera on a mat that its assignment changed.
     *
     * The phone re-reads its own config rather than trusting anything in the
     * message — same contract as a screen's paired/unpaired nudge.
     */
    public static function notify(EventCamera $camera, string $action): void
    {
        CameraChannel::send($camera, ['action' => $action]);
    }

    /**
     * Nudge every organiser running this event that its cameras changed.
     *
     * A refresh signal, carrying no data: what a console may see depends on who
     * is looking, so each one re-fetches its own answer (Realtime rule #4).
     * Best-effort, like every push in the app.
     */
    public static function consolesChanged(ClubEvent $event): void
    {
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
                'payload' => ['action' => 'cameras', 'event' => $event->uuid],
            ],
            $ids,
        )), null, false);
    }

    /**
     * The bout, as a camera is allowed to know it.
     *
     * Exactly what the hall is being told over the PA and shown on the wall as
     * the bout starts, and nothing else: no competitor ids, no clubs, no
     * photos, no draw. A camera files a clip against a bout; it does not need
     * to know who anybody is.
     *
     * @param  array<string, mixed>  $bout
     * @return array<string, mixed>
     */
    private static function bout(?int $matchId, array $bout): array
    {
        return [
            'id' => $matchId,
            'number' => isset($bout['number']) ? (string) $bout['number'] : null,
            'stage' => isset($bout['stage']) ? (string) $bout['stage'] : null,
            'red' => isset($bout['red']) ? (string) $bout['red'] : null,
            'blue' => isset($bout['blue']) ? (string) $bout['blue'] : null,
        ];
    }
}
