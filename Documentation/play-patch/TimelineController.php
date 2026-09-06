<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One bout's timeline, as takeone recorded it happening.
 *
 * The Highlights panel on a match page reads `match_rounds` and `match_points`.
 * Until now the only thing that wrote them was a person watching the video and
 * marking it up by hand. This lets the officiating log do it: the scoring table
 * already recorded every point, penalty and round with a wall-clock stamp, and
 * takeone converts those to positions in the video against the recording's
 * anchor.
 *
 * ── Why this is a separate endpoint from the match push ──────────────────────
 *
 * `MatchController` writes the competition's facts and says in its own docblock
 * that it never touches these tables. That boundary is worth keeping: a bout has
 * ONE match record and one timeline PER VIDEO, because two cameras filming the
 * same bout start at different moments and their markers sit at different
 * seconds. Same facts, different timelines.
 *
 * ── Replace, never merge ─────────────────────────────────────────────────────
 *
 * takeone sends the whole timeline every time and this replaces what was there.
 * A merge would need identity for a marker, which the officiating log does not
 * have a stable notion of once a bout is corrected — and a correction that left
 * the withdrawn point on the bar would be worse than no timeline at all. Retries
 * are therefore free, which is the other half of why it is done this way.
 *
 * Match-type videos only, per RULE #3.
 */
class TimelineController extends Controller
{
    public function update(Request $request, string $video): JsonResponse
    {
        $id = Video::decodeId($video);
        $model = ($id === null || $id <= 0) ? null : Video::find($id);

        if (! $model) {
            return response()->json(['ok' => false, 'error' => 'video_not_found'], 404);
        }

        if ($model->type !== 'match') {
            return response()->json(['ok' => false, 'error' => 'not_a_match_video'], 422);
        }

        $data = $request->validate([
            'rounds' => ['nullable', 'array', 'max:20'],
            'rounds.*.round_number' => ['required', 'integer', 'min:1', 'max:20'],
            'rounds.*.name' => ['nullable', 'string', 'max:80'],
            'rounds.*.start_time_seconds' => ['nullable', 'numeric', 'min:0', 'max:86400'],

            // A competition bout is minutes long; a cap this high is a guard
            // against a runaway push, not a real limit on scoring.
            'points' => ['nullable', 'array', 'max:500'],
            'points.*.round_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'points.*.timestamp_seconds' => ['required', 'numeric', 'min:0', 'max:86400'],
            'points.*.action' => ['required', 'string', 'max:60'],
            'points.*.points' => ['nullable', 'integer', 'min:-20', 'max:20'],
            'points.*.competitor' => ['required', 'string', 'in:blue,red'],
            'points.*.note' => ['nullable', 'string', 'max:255'],
            'points.*.score_blue' => ['nullable', 'integer', 'min:0', 'max:999'],
            'points.*.score_red' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $rounds = $data['rounds'] ?? [];
        $points = $data['points'] ?? [];

        // Nothing to draw and nothing to keep: an explicit empty push clears the
        // bar, which is how a bout whose recording was replaced gets rid of the
        // old markers.
        DB::transaction(function () use ($model, $rounds, $points, &$map) {
            DB::table('match_points')->where('video_id', $model->id)->delete();
            DB::table('match_rounds')->where('video_id', $model->id)->delete();

            $map = [];

            foreach ($rounds as $round) {
                $map[(int) $round['round_number']] = DB::table('match_rounds')->insertGetId([
                    'video_id' => $model->id,
                    'round_number' => (int) $round['round_number'],
                    'name' => $round['name'] ?? ('ROUND '.$round['round_number']),
                    'start_time_seconds' => $round['start_time_seconds'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($points as $point) {
                $number = (int) ($point['round_number'] ?? 1);

                // A marker whose round was not sent still belongs somewhere: the
                // first round there is, or a round created for it. An orphaned
                // point would simply not render.
                if (! isset($map[$number])) {
                    $map[$number] = DB::table('match_rounds')->insertGetId([
                        'video_id' => $model->id,
                        'round_number' => $number,
                        'name' => 'ROUND '.$number,
                        'start_time_seconds' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('match_points')->insert([
                    'video_id' => $model->id,
                    'match_round_id' => $map[$number],
                    'timestamp_seconds' => $point['timestamp_seconds'],
                    'action' => $point['action'],
                    'points' => $point['points'] ?? 0,
                    'competitor' => $point['competitor'],
                    'notes' => $point['note'] ?? '',
                    'score_blue' => $point['score_blue'] ?? 0,
                    'score_red' => $point['score_red'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'rounds' => count($rounds),
            'points' => count($points),
        ]);
    }
}
