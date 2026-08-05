<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person appointed to officiate one event — by default, the jury.
 *
 * Scoped to a single event on purpose: officiating a championship says nothing
 * about the next one. See App\Events\Support\EventAccess for what it grants
 * (arranging the draw before the event starts, and nothing else).
 */
class EventOfficial extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'assigned_by',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
