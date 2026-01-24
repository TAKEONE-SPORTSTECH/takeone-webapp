<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
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
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'current_progress_value' => 'decimal:2',
        'target_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
