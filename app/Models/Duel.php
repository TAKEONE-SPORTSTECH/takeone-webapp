<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Duel extends Model
{
    protected $fillable = [
        'challenger_id', 'opponent_id', 'opponent_handle', 'opponent_name',
        'type', 'discipline', 'metric', 'stake_points', 'deadline', 'location',
        'message', 'status', 'challenger_score', 'opponent_score', 'winner_id',
        'reported_by', 'responded_at', 'completed_at',
    ];

    protected $casts = [
        'deadline'     => 'date',
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /** Is $userId one of the two participants? */
    public function involves(int $userId): bool
    {
        return $this->challenger_id === $userId || $this->opponent_id === $userId;
    }

    /** The opposite participant's id for a given viewer. */
    public function rivalId(int $userId): ?int
    {
        return $this->challenger_id === $userId ? $this->opponent_id : $this->challenger_id;
    }
}
