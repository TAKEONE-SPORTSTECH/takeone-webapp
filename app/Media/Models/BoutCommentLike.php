<?php

namespace App\Media\Models;

use App\Media\Models\BoutComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Members\Models\User;

/** One person's like on one bout comment. Rows, not a counter — see the migration. */
class BoutCommentLike extends Model
{
    protected $fillable = ['comment_id', 'user_id'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(BoutComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
