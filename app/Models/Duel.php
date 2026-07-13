<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Duel extends Model
{
    protected $fillable = [
        'challenger_id', 'opponent_id', 'opponent_handle', 'opponent_name',
        'type', 'discipline', 'metric', 'format', 'event_id', 'stake_points', 'deadline',
        'location', 'location_url', 'gps_lat', 'gps_long',
        'message', 'status', 'cancel_reason', 'challenger_score', 'opponent_score', 'rounds', 'winner_id',
        'reported_by', 'responded_at', 'completed_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',   // the scheduled challenge time (date + time)
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
        'rounds' => 'array',
    ];

    public const FORMATS = ['single', 'bo3', 'bo5', 'points', 'time'];

    /** Human label for a result format. */
    public static function formatLabel(?string $format): string
    {
        return [
            'single' => 'Single match',
            'bo3' => 'Best of 3',
            'bo5' => 'Best of 5',
            'points' => 'Points — highest wins',
            'time' => 'Fastest time wins',
        ][$format] ?? 'Single match';
    }

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

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DuelMedia::class)->latest();
    }

    public function witnesses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DuelWitness::class)->latest();
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
