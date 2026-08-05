<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (event, milestone) already delivered.
 *
 * The unique index on that pair is what makes a scheduled reminder fire exactly
 * once — a cron re-run, a queue retry or two workers racing all collide on the
 * insert instead of notifying people twice.
 */
class EventNotificationSent extends Model
{
    protected $table = 'event_notifications_sent';

    protected $fillable = ['event_id', 'milestone', 'recipients', 'skipped', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'recipients' => 'integer',
        'skipped' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
