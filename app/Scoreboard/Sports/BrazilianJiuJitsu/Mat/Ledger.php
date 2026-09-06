<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

use App\Models\ClubEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The officiating record for one mat — and the only place a score comes from.
 *
 * ── Audit, not erase ────────────────────────────────────────────────────────
 * Nothing here is ever updated or deleted. A point is a row; taking it back is
 * ANOTHER row that names the first and carries the reason the operator had to
 * type. The score is then whatever replaying the surviving rows says it is.
 *
 * That is not bookkeeping fastidiousness. A jiu-jitsu score is contested at the
 * table — "that was a sweep, not a takedown", "the pass was before the buzzer" —
 * and a mutable counter can only answer with the number it currently holds. A
 * ledger answers with the whole match: what was given, when in the match, by
 * whom, what was taken back and why. It exports with the result.
 *
 * ── Why the score is not cached ─────────────────────────────────────────────
 * Because a cached total is a second truth, and the moment the two disagree the
 * cache wins silently. A match is thirty rows; the replay is one indexed query
 * over an index built for exactly it (bjj_events_replay_idx). The cost is a
 * rounding error next to a wrong score on a wall.
 */
class Ledger
{
    /**
     * What a point is worth, by what earned it — decided HERE, never by the
     * client. The console posts "guard pass"; the value comes from this table.
     * A console that has fallen behind, a replayed request, or anything else
     * POSTing at this endpoint cannot mint a five-point mount.
     */
    public const POINT_SOURCES = [
        'takedown' => 2,
        'sweep' => 2,
        'knee_on_belly' => 2,
        'guard_pass' => 3,
        'mount' => 4,
        'back_control' => 4,
    ];

    /**
     * Why a penalty was given. `stalling` is the one the referee's private
     * countdown produces; the rest are given on the spot.
     */
    public const PENALTY_REASONS = [
        'stalling', 'fleeing', 'grip_infraction', 'illegal_technique', 'conduct', 'other',
    ];

    /** Actions the replay counts. Everything else is context, not score. */
    private const SCORING = ['point', 'advantage', 'penalty'];

    /**
     * Append one row and hand it back.
     *
     * Called from inside Scoring, after the command has been validated and
     * before the mat state is saved, so the clock recorded is the match time as
     * the command landed rather than as it was last painted.
     *
     * Unlike the shared MatchEventLog next door, this one is NOT allowed to
     * fail silently: the score is derived from these rows, so a lost row is a
     * wrong score on a wall rather than a missing marker in a report. A failure
     * here surfaces, and the command that caused it does not take effect.
     *
     * @param  array<string, mixed>  $payload
     */
    public function append(
        ClubEvent $event,
        string $court,
        string $action,
        ?int $matchId,
        ?string $side = null,
        int $value = 0,
        ?string $source = null,
        ?int $reversesId = null,
        ?string $reason = null,
        ?int $operatorId = null,
        ?float $clockRemaining = null,
        ?float $clockDuration = null,
        array $payload = [],
    ): MatchEvent {
        $row = new MatchEvent([
            'event_id' => $event->id,
            'match_id' => $matchId,
            'court' => $court,
            'action' => $action,
            // Blue is side 'a', white is side 'b' — the same neutral mapping the
            // draw's a_/b_ columns use, so a ledger row and a bracket slot mean
            // the same person.
            'side' => $side === null ? null : ($side === 'blue' ? 'a' : 'b'),
            'value' => max(0, $value),
            'source' => $source,
            'reverses_id' => $reversesId,
            'reason' => $reason === null ? null : mb_substr(trim($reason), 0, 200),
            'operator_id' => $operatorId,
            'clock_remaining' => $clockRemaining,
            'clock_duration' => $clockDuration,
            'payload' => $this->trim($payload),
        ]);

        // Set by the server, never by the request: a console with a drifted
        // clock must not be able to shift the timeline, and the sequence is
        // what the replay orders by.
        $row->forceFill([
            'occurred_at' => now(),
            'sequence' => $this->nextSequence($event->id, $court),
        ])->save();

        return $row;
    }

    /**
     * The score as it stands, by replaying every surviving row of this match.
     *
     * "Surviving" is the whole mechanism: a reversal names the row it undoes,
     * and both rows stay. The replay collects the reversed ids in one pass and
     * skips them in the next — so a correction is visible in the record and
     * invisible in the total, which is exactly the contract.
     */
    public function tally(ClubEvent $event, string $court, ?int $matchId): Tally
    {
        if (! $matchId) {
            return new Tally;
        }

        $rows = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->orderBy('sequence')
            ->get(['id', 'action', 'side', 'value', 'reverses_id']);

        $reversed = $rows->pluck('reverses_id')->filter()->flip();

        $n = ['a' => ['point' => 0, 'advantage' => 0, 'penalty' => 0],
            'b' => ['point' => 0, 'advantage' => 0, 'penalty' => 0]];

        foreach ($rows as $row) {
            if ($reversed->has($row->id)
                || ! in_array($row->action, self::SCORING, true)
                || ! isset($n[$row->side])) {
                continue;
            }

            // A point carries its worth; an advantage and a penalty are one each.
            $n[$row->side][$row->action] += $row->action === 'point' ? max(0, (int) $row->value) : 1;
        }

        return new Tally(
            bluePoints: $n['a']['point'],
            whitePoints: $n['b']['point'],
            blueAdvantages: $n['a']['advantage'],
            whiteAdvantages: $n['b']['advantage'],
            bluePenalties: $n['a']['penalty'],
            whitePenalties: $n['b']['penalty'],
        );
    }

    /**
     * The match as the console's event log shows it, newest first.
     *
     * Read-only, and it never leaves the operator's screen — an entry names the
     * operator who made it, which is not something a hall board has any business
     * showing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(ClubEvent $event, string $court, ?int $matchId, int $limit = 60): array
    {
        if (! $matchId) {
            return [];
        }

        $rows = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->with('operator:id,full_name,name')
            ->orderByDesc('sequence')
            ->limit(max(1, min(200, $limit)))
            ->get();

        $reversed = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('match_id', $matchId)
            ->whereNotNull('reverses_id')
            ->pluck('reverses_id')
            ->flip();

        return $rows->map(fn (MatchEvent $r) => [
            'id' => $r->id,
            'action' => $r->action,
            // Back to the words this package speaks. The column is neutral so
            // the row means the same thing as a bracket slot; the screen is not.
            'side' => $r->side === null ? null : ($r->side === 'a' ? 'blue' : 'white'),
            'value' => $r->value,
            'source' => $r->source,
            'reason' => $r->reason,
            'reverses' => $r->reverses_id,
            // Struck through on the console rather than hidden: a correction the
            // reader cannot see is a correction they cannot check.
            'reversed' => $reversed->has($r->id),
            'clock' => $r->clockLabel(),
            'at' => $r->occurred_at?->toIso8601String(),
            'by' => $r->operator?->full_name ?? $r->operator?->name,
        ])->all();
    }

    /** The row a reversal may name: this match, scoring, and not already undone. */
    public function reversible(ClubEvent $event, string $court, ?int $matchId, int $id): ?MatchEvent
    {
        if (! $matchId) {
            return null;
        }

        $row = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->whereIn('action', self::SCORING)
            ->find($id);

        if (! $row) {
            return null;
        }

        // Undoing an undo is not a thing: the ledger would stop being readable
        // in one pass, and the operator's actual intention — give it back — is
        // an ordinary new row.
        $already = MatchEvent::where('event_id', $event->id)
            ->where('match_id', $matchId)
            ->where('reverses_id', $row->id)
            ->exists();

        return $already ? null : $row;
    }

    /**
     * Everything this match ever recorded, for the export that ships with the
     * result. Reversals included — that is the point of keeping them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(ClubEvent $event, int $matchId): array
    {
        return MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('match_id', $matchId)
            ->orderBy('sequence')
            ->get()
            ->map(fn (MatchEvent $r) => [
                'sequence' => $r->sequence,
                'at' => $r->occurred_at?->toIso8601String(),
                'clock' => $r->clockLabel(),
                'action' => $r->action,
                'side' => $r->side === null ? null : ($r->side === 'a' ? 'blue' : 'white'),
                'value' => $r->value,
                'source' => $r->source,
                'reason' => $r->reason,
                'reverses' => $r->reverses_id,
                'operator_id' => $r->operator_id,
            ])->all();
    }

    /**
     * The next sequence number for this mat.
     *
     * MAX+1 under the write, not a count: rows are never deleted, so the two
     * agree — but MAX+1 keeps agreeing if one ever is, by hand, in a repair.
     */
    private function nextSequence(int $eventId, string $court): int
    {
        try {
            return 1 + (int) DB::table('bjj_match_events')
                ->where('event_id', $eventId)
                ->where('court', $court)
                ->max('sequence');
        } catch (Throwable $e) {
            Log::warning('BJJ ledger sequence unavailable', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /** Defensive cap. Officiating payloads are tiny; anything large is a bug. */
    private function trim(array $payload): array
    {
        unset($payload['_token']);

        $json = json_encode($payload);

        return ($json !== false && strlen($json) <= 4096) ? $payload : ['truncated' => true];
    }
}
