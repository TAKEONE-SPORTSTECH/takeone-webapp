<?php

namespace App\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An athlete's request to be taken out of a competition.
 *
 * The row is the request, not the departure: the registration is only removed
 * when an organiser grants it. See the migration for why it is a queue rather
 * than a delete button.
 */
class EventWithdrawalRequest extends Model
{
    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'registration_id',
        'state',
        'reason',
        'response',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    /** Route model binding uses the public key, never the id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->state === 'pending';
    }
}
