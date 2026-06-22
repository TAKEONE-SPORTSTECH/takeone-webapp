<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPostComment extends Model
{
    protected $fillable = ['user_post_id', 'user_id', 'body'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(UserPost::class, 'user_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toFeedArray(): array
    {
        $u = $this->user;

        return [
            'id'     => $this->id,
            'name'   => $u?->full_name ?? 'Member',
            'avatar' => $u && $u->profile_picture
                ? asset('storage/' . $u->profile_picture) . '?v=' . optional($u->updated_at)->timestamp
                : null,
            'body'   => $this->body,
        ];
    }
}
