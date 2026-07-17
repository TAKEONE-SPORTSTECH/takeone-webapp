<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use DeletesUploadedFiles;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_date',
        'target_date',
        'current_progress_value',
        'target_value',
        'status',
        'priority_level',
        'unit',
        'icon_type',
        'before_proof',
        'after_proof',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'current_progress_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /** Proof photos removed from disk before the row is deleted. See DeletesUploadedFiles trait. */
    protected array $fileUploads = [
        'before_proof' => 'public',
        'after_proof' => 'public',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Days from goal creation to completion, or null while still active. */
    public function getDaysTakenAttribute(): ?int
    {
        if (! $this->completed_at) {
            return null;
        }

        return $this->created_at->diffInDays($this->completed_at);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_value == 0) {
            return 0;
        }

        return min(100, ($this->current_progress_value / $this->target_value) * 100);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed' || $this->current_progress_value >= $this->target_value;
    }
}
