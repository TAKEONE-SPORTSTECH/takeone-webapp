<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCategory extends Model
{
    /**
     * How this division is decided.
     *
     * `knockout` is the only one the engine can actually run today —
     * `DrawEngine` cuts a single-elimination ladder; `GroupEngine` runs an
     * all-play-all group that feeds a knockout final (added 2026-09-07). A
     * format is offered to an organiser only once something can run it: one
     * somebody can choose but the mats cannot honour is worse than one that is
     * missing.
     */
    public const FORMAT_KNOCKOUT = 'knockout';
    public const FORMAT_ROUND_ROBIN = 'round_robin';

    /** Formats an organiser may actually pick right now. */
    public const RUNNABLE_FORMATS = [self::FORMAT_KNOCKOUT, self::FORMAT_ROUND_ROBIN];

    /** Everything the column recognises, runnable or not. */
    public const FORMATS = [self::FORMAT_KNOCKOUT, self::FORMAT_ROUND_ROBIN];

    protected $fillable = [
        'event_id', 'name', 'weight_class', 'format', 'capacity', 'status', 'draw_state', 'draw_count', 'schedule', 'note', 'podium', 'sort_order',
        // What the division is FOR. Every one is nullable and null means ANY —
        // see App\Events\Support\DivisionRange, and the migration that added
        // them for why they are a guide rather than a gate.
        'gender', 'min_age', 'max_age', 'min_weight', 'max_weight',
    ];

    protected $casts = [
        'podium' => 'array',
        'schedule' => 'array',
        'draw_count' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'min_weight' => 'float',
        'max_weight' => 'float',
    ];

    /** Can the engine actually run this division's format today? */
    public function isRunnableFormat(): bool
    {
        return in_array($this->format ?: self::FORMAT_KNOCKOUT, self::RUNNABLE_FORMATS, true);
    }

    /** What this division is for, and who sits outside it. */
    public function range(): \App\Events\Support\DivisionRange
    {
        return new \App\Events\Support\DivisionRange($this);
    }

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
