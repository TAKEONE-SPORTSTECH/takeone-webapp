<?php

namespace App\Events\Models;

use App\Members\Models\User;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A photograph in an event's gallery.
 *
 * Both renditions sit on the private disk and are served only by
 * EventPhotoController, which re-checks who may see the event on every
 * request. Files go before the row (CLAUDE.md → Delete Files Before Records).
 */
class EventPhoto extends Model
{
    use DeletesUploadedFiles;

    protected array $fileUploads = [
        'path' => 'local',
        'thumb_path' => 'local',
    ];

    protected $fillable = [
        'uuid', 'event_id', 'path', 'thumb_path', 'width', 'height', 'bytes', 'caption', 'uploaded_by',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $photo) {
            if (empty($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
