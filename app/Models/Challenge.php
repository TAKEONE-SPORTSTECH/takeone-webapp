<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    protected $fillable = [
        'tenant_id', 'created_by', 'title', 'tag', 'category', 'icon', 'color',
        'metric', 'goal', 'unit', 'points', 'starts_at', 'ends_at', 'about',
        'rules', 'rewards', 'is_active',
    ];

    protected $casts = [
        'rules' => 'array',
        'rewards' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ChallengeParticipation::class);
    }

    /** Lifecycle status derived from dates: upcoming | active | completed. */
    public function lifecycle(): string
    {
        $today = now()->startOfDay();
        if ($this->starts_at && $this->starts_at->gt($today)) {
            return 'upcoming';
        }
        if ($this->ends_at && $this->ends_at->lt($today)) {
            return 'completed';
        }

        return 'active';
    }

    public function daysLeft(): int
    {
        if (! $this->ends_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->startOfDay()->diffInDays($this->ends_at, false)));
    }
}
