<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link between one bout and the footage of it.
 *
 * One row per bout per camera angle, pointing at the `media_files` row that
 * knows where the bytes actually live. `anchor_at` — the wall clock of the
 * recording's first frame — is what makes a highlights bar possible, since
 * every marker is `occurred_at − anchor_at` (App\Media\BoutTimeline).
 *
 * A bout with no row here simply has no video, which is the ordinary case.
 *
 * The `play_*` columns are dormant. They belonged to a video-platform
 * integration that has been removed; a few rows still carry a URL published
 * there, kept so those links keep working, and nothing writes them any more.
 */
class EventRecording extends Model
{
    protected $fillable = [
        'event_id', 'match_id', 'court', 'angle', 'anchor_at', 'started_at', 'ended_at',
        'play_video_id', 'play_video_key', 'play_url', 'status',
        'play_revision', 'pushed_at', 'timeline_pulled_at', 'sync_error',
        // Media this platform holds itself. Its absence here was silent data
        // loss: every mass-assigned write of a locally-recorded bout produced a
        // row marked `linked` that pointed at no video at all.
        'media_file_id',
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

    /**
     * The video this platform holds itself, when it holds one.
     *
     * A recording row now has two possible homes for its media: `play_url` on
     * TAKEONE Play, or this. Either may be null; a row with neither is the
     * record that a video once existed.
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }
}
