<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatch extends Model
{
    protected $fillable = [
        'event_id', 'category_id', 'round', 'phase', 'match_no', 'slot',
        'a_name', 'a_country', 'a_seed', 'a_score', 'a_provisional',
        'b_name', 'b_country', 'b_seed', 'b_score', 'b_provisional',
        'winner', 'court', 'scheduled_time', 'status',
    ];

    protected $casts = [
        'a_provisional' => 'boolean',
        'b_provisional' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }
}
