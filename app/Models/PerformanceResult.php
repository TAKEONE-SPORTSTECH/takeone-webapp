<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceResult extends Model
{
    protected $fillable = [
        'tournament_event_id',
        'description',
        'medal_type',
        'points',
    ];

    public function tournamentEvent(): BelongsTo
    {
        return $this->belongsTo(TournamentEvent::class);
    }
}
