<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A member's story — a short-lived (24h) image/text card shown in the feed's
 * stories row. Mirrors the shape the feed JS expects for a story bubble.
 */
class UserStory extends Model
{
    protected $fillable = ['user_id', 'type', 'image_path', 'caption', 'color', 'icon', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Only stories that haven't expired yet. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now());
    }

    public function deleteImageFile(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    /** Shape for the stories row + viewer (matches the Blade/JS story object). */
    public function toFeedArray(): array
    {
        $u = $this->user;
        $first = $u ? strtok($u->full_name ?? 'Member', ' ') : 'Member';

        return [
            'id'      => $this->id,
            'name'    => $first ?: 'Member',
            'me'      => false,
            'color'   => $this->color ?: '#7c3aed',
            'icon'    => $this->icon ?: 'bi-person',
            'seen'    => false,
            'caption' => $this->caption ?? '',
            'image'   => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'avatar'  => $u && $u->profile_picture
                ? asset('storage/' . $u->profile_picture) . '?v=' . optional($u->updated_at)->timestamp
                : null,
        ];
    }
}
