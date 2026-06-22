<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPostLike extends Model
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
