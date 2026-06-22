<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A trainee's rating + optional comment about a club class (the class itself, not the trainer). */
class ClassRating extends Model
{
    protected $fillable = ['package_activity_id', 'slot_day', 'slot_start', 'user_id', 'rating', 'comment'];
    protected $casts = ['rating' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
