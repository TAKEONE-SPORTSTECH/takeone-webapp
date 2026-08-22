<?php

namespace App\Play;

use App\Models\EventRecording;
use App\Models\PlayTimelinePoint;
use App\Models\PlayTimelineRound;
use Illuminate\Support\Facades\DB;

/**
 * Pull one bout's video timeline from TAKEONE Play and replace takeone's mirror.
 *
 * Flow B of the Match Sync Contract, pull half. Play owns the timeline, so this
 * is a wholesale REPLACE rather than a merge of deltas: whatever Play returns is
 * the truth, and rebuilding from it means a missed notification costs freshness
 * and never correctness. There is nothing to reconcile, because takeone never
 * writes these rows.
 *
 * Deliberately does not touch anything on the competition side. The winner, the
 * scores, the officials and event_match_events are takeone's own fields and no
 * inbound message may alter them — which is also why no loop can form.
 */
class MirrorBoutTimeline
{
    public function __construct(private PlayClient $play) {}

    /**
     * @return array{ok: bool, rounds: int, points: int, reason?: string}
     */
    public function __invoke(EventRecording $recording): array
    {
        if (! $this->play->enabled()) {
            return ['ok' => false, 'rounds' => 0, 'points' => 0, 'reason' => 'disabled'];
        }

        // No video means nothing to mirror. The ordinary case for most bouts, and
        // explicitly not an error: no retry, no stored failure, no log line.
        if ($recording->play_video_key === null || $recording->status === EventRecording::STATUS_UNLINKED) {
            return ['ok' => true, 'rounds' => 0, 'points' => 0, 'reason' => 'no_video'];
        }

        $data = $this->play->timeline($recording->play_video_key);

        if ($data === null) {
            // Visible failure, so a broken sync can be found without reading logs.
            $recording->forceFill(['sync_error' => 'timeline pull failed'])->save();

            return ['ok' => false, 'rounds' => 0, 'points' => 0, 'reason' => 'unavailable'];
        }

        /*
         * Colour → athlete, through the bout's recorded corners.
         *
         * Play's timeline knows only 'blue' and 'red'. Which fighter that was is a
         * fact about the mat, and it lives on event_matches.{a,b}_corner. With no
         * corners recorded the map is empty and every point mirrors with side null
         * — unattributed rather than attributed to whoever the draw happened to
         * list first, which is exactly the error this column exists to prevent.
         */
        $match = $recording->match;
        $sideFor = [];

        if ($match !== null) {
            foreach (['a', 'b'] as $side) {
                $corner = $match->{$side.'_corner'};
                if ($corner !== null) {
                    $sideFor[mb_strtolower((string) $corner)] = $side;
                }
            }
        }

        $rounds = 0;
        $points = 0;

        DB::transaction(function () use ($recording, $data, $sideFor, &$rounds, &$points) {
            // Replace, don't merge: rows Play deleted must disappear here too, and
            // a diff would have to reproduce Play's own ordering to be correct.
            PlayTimelinePoint::where('event_recording_id', $recording->id)->delete();
            PlayTimelineRound::where('event_recording_id', $recording->id)->delete();

            foreach ($data['rounds'] as $round) {
                if (! is_array($round) || ! isset($round['id'])) {
                    continue;
                }

                PlayTimelineRound::create([
                    'event_recording_id' => $recording->id,
                    'play_round_id' => (int) $round['id'],
                    'round_number' => isset($round['round_number']) ? (int) $round['round_number'] : null,
                    'name' => $this->text($round['name'] ?? null, 255),
                    'start_time_seconds' => isset($round['start_time_seconds']) ? (float) $round['start_time_seconds'] : null,
                ]);
                $rounds++;

                foreach (($round['points'] ?? []) as $point) {
                    if (! is_array($point) || ! isset($point['id'])) {
                        continue;
                    }

                    // Constrained to Play's own vocabulary rather than stored as
                    // typed: this value ends up selecting a colour and a fighter.
                    $competitor = mb_strtolower(trim((string) ($point['competitor'] ?? '')));
                    $competitor = in_array($competitor, ['blue', 'red'], true) ? $competitor : null;

                    PlayTimelinePoint::create([
                        'event_recording_id' => $recording->id,
                        'play_point_id' => (int) $point['id'],
                        'play_round_id' => isset($point['match_round_id']) ? (int) $point['match_round_id'] : null,
                        'timestamp_seconds' => isset($point['timestamp_seconds']) ? (float) $point['timestamp_seconds'] : null,
                        'action' => $this->text($point['action'] ?? null, 255),
                        'points' => isset($point['points']) ? (int) $point['points'] : null,
                        'competitor' => $competitor,
                        'side' => $competitor !== null ? ($sideFor[$competitor] ?? null) : null,
                        'score_blue' => isset($point['score_blue']) ? (int) $point['score_blue'] : null,
                        'score_red' => isset($point['score_red']) ? (int) $point['score_red'] : null,
                        // Annotation text typed on Play. Mirrored because it is
                        // part of the public timeline, and escaped on render like
                        // any other untrusted string.
                        'notes' => $this->text($point['notes'] ?? null, 2000),
                    ]);
                    $points++;
                }
            }

            $recording->forceFill([
                'timeline_pulled_at' => now(),
                'sync_error' => null,
            ])->save();
        });

        return ['ok' => true, 'rounds' => $rounds, 'points' => $points];
    }

    /** Trim and bound a string coming from the other platform, or null. */
    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
