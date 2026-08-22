<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scoring event on a bout's video timeline, mirrored from TAKEONE Play.
 *
 * `competitor` is Play's vocabulary (blue / red); `side` is the same fact in
 * takeone's (a / b), resolved through the bout's recorded corners. `side` is null
 * when the bout has no corners on file — an unattributed point is honest, a
 * misattributed one is not.
 */
class PlayTimelinePoint extends Model
{
    protected $fillable = [
        'event_recording_id', 'play_point_id', 'play_round_id', 'timestamp_seconds',
        'action', 'points', 'competitor', 'side', 'score_blue', 'score_red', 'notes',
    ];

    protected $casts = [
        'timestamp_seconds' => 'float',
        'points' => 'integer',
        'score_blue' => 'integer',
        'score_red' => 'integer',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(EventRecording::class, 'event_recording_id');
    }
}
