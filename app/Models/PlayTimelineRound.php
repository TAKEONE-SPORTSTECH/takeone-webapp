<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round of a bout's video timeline, mirrored from TAKEONE Play.
 *
 * Play owns this data. Nothing in the app may write it except
 * App\Play\MirrorBoutTimeline, which replaces the whole set on each pull.
 */
class PlayTimelineRound extends Model
{
    protected $fillable = [
        'event_recording_id', 'play_round_id', 'round_number', 'name', 'start_time_seconds',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'start_time_seconds' => 'float',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(EventRecording::class, 'event_recording_id');
    }

    /**
     * The points inside this round.
     *
     * Joined on Play's own round id alone, which is exact: match_rounds.id is a
     * single global sequence on that platform, so a round id identifies one round
     * across every video. (An earlier version also compared event_recording_id to
     * itself, which is always true and therefore said nothing.)
     */
    public function points(): HasMany
    {
        return $this->hasMany(PlayTimelinePoint::class, 'play_round_id', 'play_round_id')
            ->orderBy('timestamp_seconds');
    }
}
