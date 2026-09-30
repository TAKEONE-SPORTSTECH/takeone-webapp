<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entrant's result in a field, measured or judged contest — a heat's time,
 * a long jump's attempts, a routine's marks (App\Events\Championship).
 * Head-to-head contests stay EventMatch rows.
 */
class EventPerformance extends Model
{
    public const STATUSES = ['ok', 'dns', 'dnf', 'dq'];

    protected $table = 'event_performances';

    protected $fillable = [
        'event_id', 'category_id', 'round', 'heat', 'lane',
        'registration_id', 'side_id', 'attempts', 'value', 'status', 'rank',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'array',
            'value' => 'float',
            'heat' => 'integer',
            'lane' => 'integer',
            'rank' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }

    public function side(): BelongsTo
    {
        return $this->belongsTo(EventSide::class, 'side_id');
    }
}
