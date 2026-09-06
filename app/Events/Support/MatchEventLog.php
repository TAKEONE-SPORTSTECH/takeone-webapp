<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\EventMatchEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appends one row to the officiating timeline for every scoring command.
 *
 * Shared deliberately: both combat packages record the same shape, and a second
 * implementation would drift from the first. The packages keep what is theirs —
 * their command vocabulary, which corner is which, what a point is worth — and
 * hand this class only neutral values.
 *
 * ── The rule that governs everything here ──────────────────────────────────
 *
 * THIS MUST NEVER BREAK A LIVE MAT. A referee pressing a button during a bout
 * cannot be shown an error because an audit row failed to insert. Every path
 * is wrapped, every failure is swallowed and logged, and the scoring command
 * proceeds regardless. The log is a witness to scoring, never a participant in
 * it: losing a row costs a marker, while throwing here would stop a tournament.
 *
 * Documentation/VIDEO-INTEGRATION.md §5.1
 */
class MatchEventLog
{
    /**
     * Commands that change nothing and are not worth a row.
     *
     * 'refresh' re-reads and re-publishes the current state; logging it would
     * bury the actual officiating under polling noise.
     */
    private const SKIP = ['refresh'];

    /** Defensive cap on the stored payload; officiating payloads are tiny. */
    private const MAX_PAYLOAD_BYTES = 4096;

    /**
     * Record a command that has already been applied.
     *
     * Call AFTER the state has been mutated and BEFORE it is saved, so the
     * scores below are the running totals as of this command.
     *
     * @param  string  $sport  which package is writing — its vocabulary applies
     * @param  int|null  $matchId  null when the command hit an empty mat
     * @param  int  $scoreA  running score of side 'a' (aka) after this command
     * @param  int  $scoreB  running score of side 'b' (ao) after this command
     */
    public static function record(
        ClubEvent $event,
        string $court,
        string $sport,
        string $command,
        array $payload,
        ?int $matchId,
        int $scoreA,
        int $scoreB,
        // Where in the BOUT this happened. Optional so every existing caller is
        // unchanged, and null for a command that arrives with no bout on the mat.
        ?float $clockRemaining = null,
        ?float $clockDuration = null,
    ): void {
        try {
            if (! config('events.match_log', true)) {
                return;
            }

            if (in_array($command, self::SKIP, true)) {
                return;
            }

            EventMatchEvent::create([
                'event_id'    => $event->id,
                'match_id'    => $matchId,
                'court'       => $court,
                'sport'       => $sport,
                'command'     => $command,
                'payload'     => self::trimPayload($payload),
                'side'        => self::side($payload),
                'points'      => self::points($payload),
                'score_a'     => $scoreA,
                'score_b'     => $scoreB,
                // Where in the bout it happened, which is how a competition
                // report cites it: "Ippon to AKA at 1:32", not "at 10:29:29".
                'clock_remaining' => $clockRemaining,
                'clock_duration'  => $clockDuration,
                // The recorder's own clock, not the caller's: a console with a
                // drifted clock must not be able to shift the timeline (§4).
                'occurred_at' => now(),
                'sequence'    => self::nextSequence($event->id, $court),
            ]);
        } catch (Throwable $e) {
            // Deliberately swallowed. See the class docblock: a failed audit
            // row must never surface to the mat.
            Log::warning('Match event not recorded', [
                'event_id' => $event->id,
                'court'    => $court,
                'command'  => $command,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * The sport's corner, as a neutral side.
     *
     * Both packages speak aka/ao internally and both map aka→a, ao→b when a
     * bout is loaded (Scoring::load builds them from the match's a_/b_ columns),
     * so this mapping is exact rather than conventional.
     */
    private static function side(array $payload): ?string
    {
        $side = strtolower(trim((string) ($payload['side'] ?? '')));

        return match ($side) {
            'aka', 'a' => 'a',
            'ao', 'b'  => 'b',
            default    => null,
        };
    }

    /** The magnitude carried by the command, where it has one. */
    private static function points(array $payload): ?int
    {
        return isset($payload['n']) && is_numeric($payload['n'])
            ? (int) $payload['n']
            : null;
    }

    /**
     * Next position on this mat's timeline.
     *
     * Best-effort by design. Under a race two commands can take the same
     * number, which is why the column is not unique and readers order by
     * (occurred_at, id) instead. Getting this wrong must cost ordering
     * cosmetics, never a write.
     */
    private static function nextSequence(int $eventId, string $court): int
    {
        return (int) EventMatchEvent::where('event_id', $eventId)
            ->where('court', $court)
            ->max('sequence') + 1;
    }

    /** Keep a pathological payload out of the table without losing the command. */
    private static function trimPayload(array $payload): ?array
    {
        if (empty($payload)) {
            return null;
        }

        if (strlen((string) json_encode($payload)) <= self::MAX_PAYLOAD_BYTES) {
            return $payload;
        }

        return ['_truncated' => true];
    }
}
