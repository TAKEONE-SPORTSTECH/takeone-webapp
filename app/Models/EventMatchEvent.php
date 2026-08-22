<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One officiating command, as it happened.
 *
 * Append-only by intent: rows are written by MatchEventLog from inside
 * Scoring::apply() and are never updated or deleted in normal operation. A
 * correction is a new row (an undo, an adjust), not an edit of an old one —
 * the point of an audit trail is that it records the correction too.
 *
 * Sides are 'a' / 'b', matching event_matches. The sports' own vocabularies
 * (aka/ao, blue/red) are a presentation concern their packages own.
 *
 * ⚠️ score_a / score_b are WHAT THE SCOREBOARD SHOWED, and the two sports do
 * not mean the same thing by that:
 *
 *   · Karate  — the running total for the whole bout.
 *   · Taekwondo — the CURRENT ROUND only. MatState clears akaScore/aoScore at
 *     every round break; the match standing lives in akaRounds/aoRounds.
 *
 * So a reader must not sum or compare these across sports, and a Taekwondo
 * timeline has to establish round boundaries (the 'award_round' and 'rest'
 * commands) before the numbers mean anything. Recorded here rather than
 * normalised away, because the row's job is to say what the mat displayed at
 * that moment — anything else is a reading of the evidence, not the evidence.
 */
class EventMatchEvent extends Model
{
    use HasFactory;

    /**
     * Millisecond precision on every timestamp.
     *
     * Laravel's default 'Y-m-d H:i:s' truncates, and a truncated stamp cannot
     * order two commands issued inside the same second — which is exactly what
     * a fast exchange produces. Media offsets are derived from these values,
     * so the precision is not decorative.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'event_id',
        'match_id',
        'court',
        'sport',
        'command',
        'payload',
        'side',
        'points',
        'score_a',
        'score_b',
        // Where in the BOUT it happened. Without these here the columns exist,
        // the writer passes them, and mass assignment drops them on the floor —
        // which is exactly what happened, and silently, because the log swallows
        // its own failures by design.
        'clock_remaining',
        'clock_duration',
        'occurred_at',
        'sequence',
    ];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
        'points'      => 'integer',
        'score_a'     => 'integer',
        'score_b'     => 'integer',
        'clock_remaining' => 'float',
        'clock_duration'  => 'float',
        'sequence'    => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'match_id');
    }

    /**
     * The bout's commands in the order they happened.
     *
     * Ordered by (occurred_at, id) rather than by sequence: sequence is
     * best-effort and never unique-constrained, while this pair is total.
     */
    public function scopeForMatch($query, int $matchId)
    {
        return $query->where('match_id', $matchId)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
