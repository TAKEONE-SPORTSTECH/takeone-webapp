<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Angle 2 on Mat 1 holds the video of bout 1-04."
 *
 * The index, not the media. The file itself stays on the phone that shot it —
 * a hall's wifi cannot carry four cameras' worth of video during a competition,
 * and losing a bout because an upload stalled would be a worse failure than
 * having to fetch the phone afterwards. What travels here is the fact that the
 * clip exists, which bout it belongs to, and the handle the phone knows it by.
 *
 * So a row here is a promise a human can act on: plug in THAT phone, look for
 * THAT file. Uploading it somewhere is a later, deliberate step and does not
 * change what this row means.
 */
class EventCameraClip extends Model
{

    /**
     * A half-uploaded clip leaves bytes on disk. They go with the row.
     *
     * The scratch file is keyed by the clip's id (`camera-uploads/clip-{id}.mp4`)
     * and an id is REUSED after a delete, so an orphan is not merely wasted disk:
     * the next clip to be handed that id resumes its upload on top of somebody
     * else's bytes, and the server — which is the authority on the offset —
     * cheerfully reports the stale length and produces a spliced, unplayable
     * file. Nobody would ever look for that.
     *
     * Files before records, per the project rule; best-effort, so a missing file
     * never blocks the delete.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $clip) {
            rescue(function () use ($clip) {
                $scratch = storage_path('app/camera-uploads/clip-'.$clip->id.'.mp4');

                if (is_file($scratch)) {
                    unlink($scratch);
                }
            }, null, false);
        });
    }
    protected $fillable = [
        'camera_id', 'event_id', 'match_id', 'court', 'angle',
        'started_at', 'ended_at', 'duration_seconds', 'bytes', 'local_ref',
        'play_status', 'play_video_key', 'play_video_id', 'on_device',
        'uploaded_bytes', 'upload_started_at', 'uploaded_at', 'upload_error',
    ];

    /** Where a clip is on its way to Play, if it is going at all. */
    public const PLAY_QUEUED = 'queued';

    public const PLAY_UPLOADING = 'uploading';

    /** On Play, transcoding. The video exists; it is not watchable yet. */
    public const PLAY_PROCESSING = 'processing';

    public const PLAY_READY = 'ready';

    public const PLAY_FAILED = 'failed';

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'upload_started_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'uploaded_bytes' => 'integer',
        'play_video_id' => 'integer',
        'duration_seconds' => 'integer',
        'bytes' => 'integer',
        'angle' => 'integer',
        // Nullable on purpose: null is "no phone has told us", which is not
        // the same as false ("the file is gone").
        'on_device' => 'boolean',
    ];

    public function camera(): BelongsTo
    {
        return $this->belongsTo(EventCamera::class, 'camera_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'match_id');
    }
}
