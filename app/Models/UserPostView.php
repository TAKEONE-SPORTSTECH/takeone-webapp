<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single member's view of a post (one row per user per post).
 */
class UserPostView extends Model
{
    protected $fillable = ['user_post_id', 'user_id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(UserPost::class, 'user_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
