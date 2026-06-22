<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    protected $fillable = [
        'event_id', 'name', 'weight_class', 'capacity', 'status', 'draw_state', 'draw_count', 'schedule', 'note', 'podium', 'sort_order',
    ];

    protected $casts = [
        'podium'     => 'array',
        'schedule'   => 'array',
        'draw_count' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'category_id')->orderBy('slot');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'category_id');
    }
}
