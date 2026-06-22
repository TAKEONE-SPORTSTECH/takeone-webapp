<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single member's vote on a poll post (one row per user per poll).
 */
class UserPostPollVote extends Model
{
    protected $fillable = ['user_post_id', 'user_id', 'option'];

    protected $casts = ['option' => 'integer'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(UserPost::class, 'user_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
