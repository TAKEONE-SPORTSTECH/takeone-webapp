<?php

namespace App\Members\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Clubs\Models\ClubAffiliation;

class AffiliationMedia extends Model
{
    use DeletesUploadedFiles;

    protected $fillable = [
        'club_affiliation_id',
        'media_type',
        'media_url',
        'title',
        'description',
    ];

    // Purge the uploaded image before the row is deleted. External URLs (video /
    // document links) simply aren't files on disk, so the delete is a safe no-op.
    protected array $fileUploads = [
        'media_url' => 'public',
    ];

    /**
     * Get the club affiliation that owns the media.
     */
    public function clubAffiliation(): BelongsTo
    {
        return $this->belongsTo(ClubAffiliation::class);
    }

    /**
     * Get the full URL for the media.
     */
    public function getFullUrlAttribute(): string
    {
        if (filter_var($this->media_url, FILTER_VALIDATE_URL)) {
            return $this->media_url;
        }

        return file_url($this->media_url);
    }

    /**
     * Get icon class for media type.
     */
    public function getIconClassAttribute(): string
    {
        return match ($this->media_type) {
            'certificate' => 'bi-file-earmark-text',
            'photo' => 'bi-image',
            'video' => 'bi-play-circle',
            'document' => 'bi-file-text',
            default => 'bi-file',
        };
    }
}
