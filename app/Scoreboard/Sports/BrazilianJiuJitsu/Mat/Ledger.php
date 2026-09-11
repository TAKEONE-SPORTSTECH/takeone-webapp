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
     * The point AMOUNTS the scoring table presses directly, and the only
     * amounts a value-priced point or a deduction may name.
     *
     * ── Why the console stopped naming actions (2026-09-11) ────────────────
     *
     * Six buttons priced by action read as three "+2"s, a "+3" and two "+4"s:
     * the number an official is thinking in appeared three times over, and the
     * word underneath it was the only thing telling them apart. At a mat that
     * is a row to READ rather than a row to hit. And there was no way to take a
     * score back at the same reach — a correction meant opening the score log.
     *
     * So the grid is +2/-2, +3/-3, +4/-4, and the VALUE is the thing pressed.
     * Still a closed list, still priced here: `point` accepts one of these
     * amounts, OR an action out of POINT_SOURCES — which the wall board, an MCP
     * tool and the React console may still send, and which keeps its word on
     * the record. Nothing a client posts can mint an amount outside this list.
     */
    public const POINT_VALUES = [2, 3, 4];

    /**
     * A deduction that has nothing left to take back.
     *
     * `deduct` prefers to REVERSE the matching score, because that is the
     * honest shape of a mis-tap: both rows survive and the total is simply the
     * replay of the rest. When there is no matching score to reverse — the
     * points came from a stalling award, or from the other console, or the
     * table is correcting a total it inherited — it appends one of these
     * instead, and the replay subtracts it. Floored at zero: a corner cannot
     * owe points.
     */
    public const CORRECTION = 'correction';

    /**
     * What the referee may award the OTHER corner when a stalling count runs
     * out, instead of penalising the corner that stalled.
     *
     * Here rather than in POINT_SOURCES, and that placement is the whole point:
     * POINT_SOURCES is the list of actions an official PRESSES, and the console
     * builds its six scoring buttons by walking it — adding a seventh entry
     * would put a "stalling award" button in both corners, to be pressed at any
     * moment for no reason. This is a closed list of VALUES for one command
     * (`stall`, phase `award`), priced here, on the server, exactly like every
     * other number in this file. The console names the amount; it cannot invent
     * one outside this list.
     */
    public const STALL_AWARDS = [2, 3, 4];

    /** The source recorded against a stalling award, so the row says what it was. */
    public const STALL_AWARD_SOURCE = 'stalling_award';

    /**
     * Why a penalty was given. `stalling` is the one the referee's private
     * countdown produces; the rest are given on the spot.
     */
    public const PENALTY_REASONS = [
        'stalling', 'fleeing', 'grip_infraction', 'illegal_technique', 'conduct', 'other',
    ];

    /** Actions the replay counts. Everything else is context, not score. */
    private const SCORING = ['point', 'advantage', 'penalty', self::CORRECTION];

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
     * Which rows of a match are CANCELLED — and it is not simply "named by a
     * reversal".
     *
     * ── Why this is a pass of its own ──────────────────────────────────────
     *
     * Until 2026-09-12 a reversal could not itself be reversed, and the reason
     * given was this function: the replay collected every `reverses_id` in one
     * sweep and skipped those rows, which is correct only while reversals
     * cannot stack. Take A(+2), B(reverse A), C(reverse B) — the operator took
     * two points back and then changed their mind. A one-pass sweep collects
     * {A, B} and drops A, so the points stay off the board even though C put
     * them back. The score would be silently wrong, which is the one thing
     * this file exists to prevent.
     *
     * So: a row is cancelled when an ACTIVE reversal names it, and a reversal
     * is active unless it is itself cancelled. Resolved by walking the match
     * NEWEST FIRST — a reversal always lands after the row it undoes, so by
     * the time the walk reaches one, whether that reversal survived is already
     * known, and its target is still ahead.
     *
     * Linear, one pass, any depth of chain.
     *
     * @param  \Illuminate\Support\Collection<int, MatchEvent>  $rows  ordered by sequence, ascending
     * @return array<int, true>
     */
    private function cancelled($rows): array
    {
        $out = [];

        foreach ($rows->reverse() as $row) {
            if (isset($out[$row->id])) {
                continue;                       // this reversal was itself undone
            }

            if ($row->action === 'reverse' && $row->reverses_id) {
                $out[$row->reverses_id] = true;
            }
        }

        return $out;
    }

    /**
     * The score as it stands, by replaying every surviving row of this match.
     *
     * "Surviving" is the whole mechanism: a reversal names the row it undoes,
     * and both rows stay. Nothing is ever deleted — so a correction is visible
     * in the record and invisible in the total, which is exactly the contract.
     * Which rows survive is cancelled()'s question, and it is not a trivial
     * one once a reversal can itself be reversed.
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

        $cancelled = $this->cancelled($rows);

        $n = ['a' => ['point' => 0, 'advantage' => 0, 'penalty' => 0],
            'b' => ['point' => 0, 'advantage' => 0, 'penalty' => 0]];

        foreach ($rows as $row) {
            if (isset($cancelled[$row->id])
                || ! in_array($row->action, self::SCORING, true)
                || ! isset($n[$row->side])) {
                continue;
            }

            $other = $row->side === 'a' ? 'b' : 'a';

            match ($row->action) {
                // A point carries its worth.
                'point' => $n[$row->side]['point'] += max(0, (int) $row->value),

                /*
                 * An advantage is one on its own ladder AND one point to the
                 * man who earned it.
                 *
                 * ⚠️ This is NOT the IBJJF convention, where an advantage never
                 * touches a points total and only breaks a tie. It is what this
                 * platform's organiser asked for on 2026-09-12, and it is a
                 * rule-book decision, which is why it lives in this sport's own
                 * package (CLAUDE.md → Shared Stays Shared). If another
                 * federation's ruleset is ever run here, this is the line that
                 * becomes a setting.
                 */
                'advantage' => [
                    $n[$row->side]['advantage']++,
                    $n[$row->side]['point']++,
                ],

                /*
                 * A penalty is one on the offender's ladder AND one point to
                 * the OPPONENT — it costs the man who gave it away twice, once
                 * on the ladder that can disqualify him and once on the score.
                 * The opponent is credited rather than the offender debited, so
                 * a penalty against a corner on zero still shows somewhere.
                 */
                'penalty' => [
                    $n[$row->side]['penalty']++,
                    $n[$other]['point']++,
                ],

                /*
                 * A correction takes points off, and is the ONE row that does.
                 * It is still a row — visible in the log, itself reversible —
                 * rather than an edit to a number, which is the whole contract
                 * of this file: the score is what replaying the record says,
                 * never a value somebody wrote over the top of it.
                 *
                 * Note what does NOT need a case here: taking back an advantage
                 * or a penalty. Those are REVERSALS, so the row is skipped at
                 * the top of this loop and both of its effects — the ladder and
                 * the point, on whichever corner got it — disappear together.
                 * One mechanism, and nothing to keep in step by hand.
                 */
                self::CORRECTION => $n[$row->side]['point'] -= max(0, (int) $row->value),

                default => null,
            };
        }

        // A corner cannot owe points. A correction larger than the score it was
        // aimed at lands on zero rather than below it.
        return new Tally(
            bluePoints: max(0, $n['a']['point']),
            whitePoints: max(0, $n['b']['point']),
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

        // The WHOLE match, in order, because whether a row still stands is a
        // question about the chain above it and not about the page being
        // shown. Ids and shape only — the rows themselves are the page above.
        $cancelled = $this->cancelled(
            MatchEvent::query()
                ->where('event_id', $event->id)
                ->where('court', $court)
                ->where('match_id', $matchId)
                ->orderBy('sequence')
                ->get(['id', 'action', 'reverses_id'])
        );

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
            // reader cannot see is a correction they cannot check. A reversal
            // that was ITSELF undone is not struck through — it no longer
            // stands, and the row it was aimed at is back.
            'reversed' => isset($cancelled[$r->id]),
            'clock' => $r->clockLabel(),
            'at' => $r->occurred_at?->toIso8601String(),
            'by' => $r->operator?->full_name ?? $r->operator?->name,
        ])->all();
    }

    /**
     * The most recent entry of this kind on this side that is still standing.
     *
     * What every "-" button on the console aims at: a -2 asks for the last
     * two-point score, a -1 under the advantages asks for the last advantage.
     * NEWEST first, because the mis-tap an official is correcting is almost
     * always the one they just made, and because taking back the OLDEST of
     * three identical entries would leave the record saying something that did
     * not happen — the clock on the row would be wrong even though the total
     * came out right.
     *
     * `$value` narrows a point to its worth and is ignored for the two ladders,
     * where every row counts one.
     *
     * Null when there is nothing to take back. What the caller does with that
     * differs by kind and is its decision, not this one's: points fall back to
     * a CORRECTION row, the ladders refuse.
     */
    public function lastStanding(ClubEvent $event, string $court, ?int $matchId, string $side, string $action, ?int $value = null): ?MatchEvent
    {
        if (! $matchId) {
            return null;
        }

        $rows = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->orderBy('sequence')
            ->get(['id', 'action', 'side', 'value', 'source', 'reverses_id']);

        $cancelled = $this->cancelled($rows);
        $wanted = $side === 'blue' ? 'a' : 'b';

        return $rows->reverse()->first(fn (MatchEvent $r) => $r->action === $action
            && $r->side === $wanted
            && ($value === null || (int) $r->value === $value)
            && ! isset($cancelled[$r->id]));
    }

    /**
     * Every row of this match that still stands — nothing cancelled, in order.
     *
     * The one place that answers "does this row still count?", so the score,
     * the reset and the console's undo cannot drift apart.
     *
     * @return \Illuminate\Support\Collection<int, MatchEvent>
     */
    public function standing(ClubEvent $event, string $court, ?int $matchId)
    {
        if (! $matchId) {
            return collect();
        }

        $rows = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->orderBy('sequence')
            ->get(['id', 'action', 'side', 'value', 'source', 'reverses_id']);

        $cancelled = $this->cancelled($rows);

        return $rows->reject(fn (MatchEvent $r) => isset($cancelled[$r->id]))->values();
    }

    /**
     * The row a reversal may name: this match, something that moved the score,
     * and still standing.
     *
     * ⚠️ `reverse` is in that list since 2026-09-12, so EVERY entry the score
     * log shows as undoable really is — including an undo. Taking back a
     * correction is an ordinary act at a mat ("no, that point was good"), and
     * it used to be refused on the grounds that the replay could not read a
     * chain. It can now; see cancelled().
     *
     * Still refused: a row that does not stand. Undoing the same entry twice
     * is a double subtraction from a score nobody can trace, and undoing one
     * that a later reversal already restored is a press with no meaning.
     */
    public function reversible(ClubEvent $event, string $court, ?int $matchId, int $id): ?MatchEvent
    {
        if (! $matchId) {
            return null;
        }

        $row = MatchEvent::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->where('match_id', $matchId)
            ->whereIn('action', array_merge(self::SCORING, ['reverse']))
            ->find($id);

        if (! $row) {
            return null;
        }

        $cancelled = $this->cancelled(
            MatchEvent::query()
                ->where('event_id', $event->id)
                ->where('court', $court)
                ->where('match_id', $matchId)
                ->orderBy('sequence')
                ->get(['id', 'action', 'reverses_id'])
        );

        return isset($cancelled[$row->id]) ? null : $row;
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
