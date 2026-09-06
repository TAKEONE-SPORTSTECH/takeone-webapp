<?php

namespace App\Media;

use App\Models\EventMatch;
use App\Models\EventRecording;
use App\Sports\Combat\SportRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The officiating log, as a highlights bar.
 *
 * Nobody types this timeline. The mat console already recorded WHAT happened and
 * WHEN, in wall-clock terms; the recording knows the wall clock of its own first
 * frame. The whole of this class is that subtraction:
 *
 *     media_time = occurred_at − anchor_at
 *
 * Get the anchor wrong by a second and every marker sits just after the technique
 * it marks, so a reviewer scrubbing to a point sees the aftermath. That is worse
 * than an empty bar, which is why a recording with no anchor returns nothing at
 * all rather than markers placed against a guess.
 *
 * ── Why moments, not points ────────────────────────────────────────────────
 *
 * Both fighters can score in the same instant. Rendered as two rows that reads as
 * two separate attacks and the running score appears to jump twice, so points
 * sharing a timestamp collapse into ONE moment with a split colour bar. The
 * grouping happens here, once, on the server — the panel receives finished rows
 * and never re-derives them, which is how the list and the bar can never disagree.
 *
 * ── Corners are real here ──────────────────────────────────────────────────
 *
 * A marker is coloured by the corner the athlete actually fought in
 * (`event_matches.a_corner`/`b_corner`), not by which draw slot they occupy. The
 * seeding decides the slot; the mat decides the corner, and they do not always
 * agree.
 */
class BoutTimeline
{
    /**
     * Commands worth a marker.
     *
     * The log also carries loads, resyncs, clock nudges and board refreshes — the
     * audit trail of a mat, and noise on a highlights bar. What survives is what
     * changed the score or carried a sanction.
     */
    private const ACTIONS = [
        'point' => 'point',
        'score' => 'point',
        'penalty' => 'penalty',
        'gamjeom' => 'penalty',
        'senshu' => 'senshu',
        'award_round' => 'round',
    ];

    public function __construct(private SportRegistry $sports) {}

    /**
     * Build the bar for one bout against one recording.
     *
     * @return array{anchored: bool, rounds: array<int, array<string, mixed>>, moments: array<int, array<string, mixed>>}
     */
    public function for(EventMatch $match, EventRecording $recording): array
    {
        $empty = ['anchored' => false, 'rounds' => [], 'moments' => []];

        $anchor = $recording->anchor_at ?? $recording->started_at;

        if ($anchor === null) {
            return $empty;
        }

        $rows = DB::table('event_match_events')
            ->where('match_id', $match->id)
            ->orderBy('sequence')
            ->get();

        if ($rows->isEmpty()) {
            return ['anchored' => true, 'rounds' => [], 'moments' => []];
        }

        $sport = $this->sports->get($match->event?->sport);
        $corners = $this->corners($match);

        $rounds = [];
        $points = [];
        $round = 1;

        foreach ($rows as $row) {
            $at = $row->occurred_at ? Carbon::parse($row->occurred_at) : null;

            if ($at === null) {
                continue;
            }

            $seconds = round(
                $at->getPreciseTimestamp(3) / 1000 - $anchor->getPreciseTimestamp(3) / 1000,
                2
            );

            // Anything before the camera rolled or after it stopped has no place
            // in this video. Bouts are re-scored and reset during setup — that
            // history is real, it is simply not in these frames.
            if ($seconds < 0) {
                continue;
            }

            if ($recording->ended_at && $at->gt($recording->ended_at)) {
                continue;
            }

            $payload = json_decode((string) $row->payload, true);
            $logged = (int) ($payload['round'] ?? 0);

            if ($logged > 0 && $logged !== $round) {
                $round = $logged;
            }

            // A round begins where the log says it does, so a three-round bout
            // gets three markers rather than one.
            $rounds[$round] ??= [
                'number' => $round,
                'name' => __('events.bout_video_round', ['n' => $round]),
                'start' => $seconds,
                'moments' => 0,
            ];

            $kind = self::ACTIONS[$row->command] ?? null;

            if ($kind === null) {
                continue;
            }

            // A sanction logged against nobody is a clock correction, not a
            // moment in the bout.
            if (! in_array($row->side, ['a', 'b'], true)) {
                continue;
            }

            $colour = $corners[$row->side];

            $points[] = [
                'round' => $round,
                't' => $seconds,
                'kind' => $kind,
                'points' => (int) ($row->points ?? 0),
                'colour' => $colour,
                'score_red' => (int) ($corners['a'] === 'red' ? $row->score_a : $row->score_b),
                'score_blue' => (int) ($corners['a'] === 'blue' ? $row->score_a : $row->score_b),
            ];
        }

        if ($rounds === [] && $points !== []) {
            $rounds[1] = [
                'number' => 1,
                'name' => __('events.bout_video_round', ['n' => 1]),
                'start' => 0.0,
                'moments' => 0,
            ];
        }

        ksort($rounds);

        $moments = $this->group($points, $sport, $match);

        foreach ($moments as $moment) {
            if (isset($rounds[$moment['round']])) {
                $rounds[$moment['round']]['moments']++;
            }
        }

        return [
            'anchored' => true,
            'rounds' => array_values($rounds),
            'moments' => $moments,
        ];
    }

    /**
     * Which colour each side fought in.
     *
     * Falls back to the old aka='a' / ao='b' assumption only when the mat
     * recorded nothing, so an unannotated bout reads exactly as it always did.
     *
     * @return array{a: string, b: string}
     */
    private function corners(EventMatch $match): array
    {
        return [
            'a' => $match->a_corner === 'blue' ? 'blue' : 'red',
            'b' => $match->b_corner === 'red' ? 'red' : 'blue',
        ];
    }

    /**
     * Collapse points sharing a timestamp into one moment per instant.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>
     */
    private function group(array $points, $sport, EventMatch $match): array
    {
        usort($points, fn ($x, $y) => $x['t'] <=> $y['t']);

        $groups = [];

        foreach ($points as $point) {
            // The key is the timestamp as a string, so two events logged at the
            // identical instant land in the same bucket whatever float noise the
            // frame arithmetic left behind.
            $groups[(string) $point['t']][] = $point;
        }

        $labels = $sport?->cornerLabels() ?? [
            'red' => __('events.corner_red'),
            'blue' => __('events.corner_blue'),
        ];

        $moments = [];

        foreach ($groups as $bucket) {
            /*
             * The anchor is the LAST point of the exchange in LOG order, because
             * that is the row carrying the score after the whole moment. The log
             * is already in sequence order and the timestamp sort above is
             * stable, so the bucket is still in the order the mat recorded it.
             *
             * Ordering by colour first — which is how the platform this replaces
             * did it — quietly picks the wrong row: a simultaneous exchange then
             * reports the score after only ONE of its two points, so a 1–1
             * exchange reads as 1–0 and the running score appears to lag a beat
             * behind the fight. Caught exactly that way in testing.
             */
            $anchor = end($bucket);

            // Blue first for DISPLAY only: the deltas read left to right in a
            // consistent order whichever corner scored first.
            $display = $bucket;
            usort($display, fn ($x, $y) => ($x['colour'] === 'blue' ? 0 : 1) <=> ($y['colour'] === 'blue' ? 0 : 1));
            $bucket = $display;

            $colours = array_column($bucket, 'colour');
            $both = in_array('red', $colours, true) && in_array('blue', $colours, true);

            $moments[] = [
                't' => $anchor['t'],
                'round' => $anchor['round'],
                'clock' => $this->clock($anchor['t']),
                'side' => $both ? 'both' : $colours[0],
                'label' => $this->label($bucket, $sport),
                // The bare number of points in this moment, beside the phrase.
                // The card's ticker needs the value on its own — it prints a
                // "+2" of its own and picks a colour from it — and deriving that
                // by parsing the label back out of "Waza-ari +2" would make a
                // display string load-bearing.
                'points' => array_sum(array_map(fn (array $p) => (int) ($p['points'] ?? 0), $bucket)),
                'who' => $both
                    ? __('events.bout_video_both_scored')
                    : ($labels[$colours[0]] ?? $colours[0]),
                'score_red' => $anchor['score_red'],
                'score_blue' => $anchor['score_blue'],
                /*
                 * The individual points that made up this moment, in display
                 * order. The grouped row above is what a highlights list wants;
                 * an on-video scoreboard ticker wants the exchange broken back
                 * out, one entry per corner, and deriving that by parsing the
                 * label string back apart would make a display string
                 * load-bearing. Additive: nothing that predates it reads this.
                 */
                'deltas' => array_map(fn (array $p) => [
                    'colour' => $p['colour'],
                    'points' => (int) ($p['points'] ?? 0),
                    'kind' => $p['kind'],
                    'score_red' => $p['score_red'],
                    'score_blue' => $p['score_blue'],
                ], $bucket),
                'names' => [
                    'red' => $match->{($this->corners($match)['a'] === 'red' ? 'a' : 'b').'_name'},
                    'blue' => $match->{($this->corners($match)['a'] === 'blue' ? 'a' : 'b').'_name'},
                ],
            ];
        }

        return $moments;
    }

    /**
     * What to call this moment.
     *
     * A single scoring point gets the sport's own word for its value — Ippon,
     * a turning body kick — because that is what a coach and a crowd call it.
     * A value the sport has no name for, or an exchange where both scored, falls
     * back to the plain count. Nothing here invents terminology.
     *
     * @param  array<int, array<string, mixed>>  $bucket
     */
    private function label(array $bucket, $sport): string
    {
        if (count($bucket) === 1) {
            $one = $bucket[0];

            if ($one['kind'] === 'penalty') {
                return __('events.bout_video_penalty');
            }

            if ($one['kind'] === 'senshu') {
                return __('events.bout_video_senshu');
            }

            if ($one['kind'] === 'round') {
                return __('events.bout_video_round_awarded');
            }

            $named = $sport?->scoreLabel($one['points']);

            return $named
                ? $named.' +'.$one['points']
                : __('events.bout_video_point').' +'.$one['points'];
        }

        $deltas = implode(' / ', array_map(fn ($p) => '+'.$p['points'], $bucket));

        return __('events.bout_video_point').' '.$deltas;
    }

    /** MM:SS, floored — the reader wants the second, not the frame. */
    private function clock(float $seconds): string
    {
        $whole = max(0, (int) floor($seconds));

        return sprintf('%02d:%02d', intdiv($whole, 60), $whole % 60);
    }
}
