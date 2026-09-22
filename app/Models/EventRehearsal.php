<?php

namespace App\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dress rehearsal of one event. See the migration for what it is for, and
 * App\Events\Support\Rehearsal for what it does.
 *
 * `ended_at` is the whole state machine: null means a rehearsal is RUNNING on
 * this event, and every other question ("may this result be filed?", "should
 * this athlete be told?", "is the draw fit to publish?") is answered from that
 * one fact.
 */
class EventRehearsal extends Model
{
    protected $fillable = ['event_id', 'started_by', 'started_at', 'ended_at', 'snapshot', 'marks', 'bouts_run'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'snapshot' => 'array',
        'marks' => 'array',
        'bouts_run' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }
}
