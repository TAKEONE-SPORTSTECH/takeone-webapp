<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The link between one bout on takeone and its video on TAKEONE Play.
 *
 * takeone owns this row, which makes it the join for the whole integration:
 * Play needs no reference of its own, because every message from Play can name
 * its video and let this table resolve the bout (Match Sync Contract).
 *
 * A bout with no row here simply has no video, which is the ordinary case — and
 * the sync treats it as a silent no-op, never an error.
 */
class EventRecording extends Model
{
    protected $fillable = [
        'event_id', 'match_id', 'court', 'angle', 'anchor_at', 'started_at', 'ended_at',
        'play_video_id', 'play_video_key', 'play_url', 'status',
        'play_revision', 'pushed_at', 'timeline_pulled_at', 'sync_error',
    ];

    protected $casts = [
        'anchor_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'pushed_at' => 'datetime',
        'timeline_pulled_at' => 'datetime',
    ];

    /** Play still holds the media for this recording. */
    public const STATUS_LINKED = 'linked';

    /**
     * The video is gone from Play, or the bout it belonged to was removed.
     *
     * The row survives on purpose: it is the record that a video once existed.
     * Unlinking clears the reference and NEVER deletes the bout, its result, or
     * its officiating log — deletion never crosses between the platforms.
     */
    public const STATUS_UNLINKED = 'unlinked';

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'match_id');
    }

    public function timelineRounds(): HasMany
    {
        return $this->hasMany(PlayTimelineRound::class, 'event_recording_id')->orderBy('round_number');
    }

    public function timelinePoints(): HasMany
    {
        return $this->hasMany(PlayTimelinePoint::class, 'event_recording_id')->orderBy('timestamp_seconds');
    }
}
