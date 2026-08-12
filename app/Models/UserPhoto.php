<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One picture on a member's profile.
 *
 * The avatar is whichever photo's path currently sits in `users.profile_picture`
 * — the column stays authoritative so every existing avatar render keeps working
 * untouched; this table just remembers the others.
 */
class UserPhoto extends Model
{
    use DeletesUploadedFiles;

    /**
     * The file is purged before the row, so deleting a picture can never leave
     * an orphan on disk (CLAUDE.md → "Delete Files Before Records").
     */
    protected array $fileUploads = [
        'path',   // bare entry → 'public' disk
    ];

    protected $fillable = [
        'user_id',
        'path',
        'sort_order',
        'uuid',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $photo) {
            if (empty($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->path).'?v='.optional($this->updated_at)->timestamp;
    }

    /** True when this picture is the one the profile shows as its avatar. */
    public function isAvatar(): bool
    {
        return $this->path !== null && $this->path === $this->user?->profile_picture;
    }

    /** The shape the photo sheet and the API speak in. */
    public function toSheetArray(?string $avatarPath = null): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url(),
            'is_avatar' => $this->path === ($avatarPath ?? $this->user?->profile_picture),
        ];
    }
}
