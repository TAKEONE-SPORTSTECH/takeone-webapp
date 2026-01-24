<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationMedia extends Model
{
    protected $fillable = [
        'club_affiliation_id',
        'media_type',
        'media_url',
        'title',
        'description',
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

        return asset('storage/' . $this->media_url);
    }

    /**
     * Get icon class for media type.
     */
    public function getIconClassAttribute(): string
    {
        return match($this->media_type) {
            'certificate' => 'bi-file-earmark-text',
            'photo' => 'bi-image',
            'video' => 'bi-play-circle',
            'document' => 'bi-file-text',
            default => 'bi-file',
        };
    }
}
