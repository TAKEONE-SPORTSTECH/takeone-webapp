<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorReview extends Model
{
    protected $fillable = [
        'instructor_id',
        'reviewer_user_id',
        'rating',
        'comment',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the instructor that owns the review.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(ClubInstructor::class, 'instructor_id');
    }

    /**
     * Get the user who wrote the review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * Check if review was updated after initial creation.
     */
    public function wasUpdated(): bool
    {
        return $this->updated_at && $this->updated_at->gt($this->created_at);
    }

    /**
     * Get formatted review date.
     */
    public function getFormattedDateAttribute(): string
    {
        if ($this->wasUpdated()) {
            return 'Updated ' . $this->updated_at->diffForHumans();
        }
        return $this->reviewed_at->diffForHumans();
    }
}
