<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the officiating ledger. Append-only.
 *
 * The application never updates or deletes one of these. A mistake at the table
 * is corrected by APPENDING a reversal that names the row it undoes and carries
 * the reason the operator was made to type — which is why the score can be
 * questioned afterwards and answered from the record.
 *
 * Guarded against mass assignment of the two fields that would break that:
 * `sequence` and `occurred_at` are set by the Ledger from the server's own
 * clock and counter, never from a request.
 */
class MatchEvent extends Model
{
    protected $table = 'bjj_match_events';

    protected $fillable = [
        'event_id', 'match_id', 'court', 'action', 'side', 'value', 'source',
        'reverses_id', 'reason', 'operator_id', 'clock_remaining', 'clock_duration', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'value' => 'integer',
        'clock_remaining' => 'float',
        'clock_duration' => 'float',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /**
     * Who made this entry. Read only by the operator's own event log — a hall
     * board has no business naming the person at the table.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'operator_id');
    }

    /**
     * The bout clock as a competition report cites it — "3:12", not a decimal
     * of seconds. Null when the command landed with no match on the mat.
     */
    public function clockLabel(): ?string
    {
        if ($this->clock_remaining === null) {
            return null;
        }

        $seconds = (int) round($this->clock_remaining);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
