<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DuelMedia extends Model
{
    use DeletesUploadedFiles;

    protected $table = 'duel_media';

    protected $fillable = ['duel_id', 'user_id', 'type', 'url', 'caption'];

    // Uploaded image/video files live on the public disk and are purged before the row.
    // External links (type 'link') store a URL here — a no-op for the file purge.
    protected array $fileUploads = [
        'url' => 'public',
    ];

    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Public URL for display: stored path → asset URL; external link → as-is. */
    public function getFullUrlAttribute(): string
    {
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')) {
            return $this->url;
        }
        return Storage::disk('public')->url($this->url);
    }
}
