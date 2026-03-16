<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubEvent extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['title', 'date', 'end_date', 'status', 'is_archived', 'max_capacity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'club_events';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'level',
        'max_capacity',
        'cancel_within_days',
        'spots_taken',
        'ribbon_label',
        'ribbon_type',
        'tags',
        'images',
        'color',
        'cta_text',
        'status',
        'is_archived',
    ];

    protected $casts = [
        'date'         => 'date',
        'end_date'     => 'date',
        'tags'         => 'array',
        'images'       => 'array',
        'max_capacity'       => 'integer',
        'cancel_within_days' => 'integer',
        'spots_taken'        => 'integer',
        'is_archived'  => 'boolean',
    ];

    /**
     * True once the event's end has passed.
     * - Uses end_date (end of that day) if set.
     * - Falls back to date + end_time, or end of start day.
     */
    public function hasEnded(): bool
    {
        if ($this->end_date) {
            return $this->end_date->copy()->endOfDay()->isPast();
        }

        if ($this->end_time) {
            return $this->date->copy()->setTimeFromTimeString($this->end_time)->isPast();
        }

        // No explicit end info — never auto-archive
        return false;
    }

    /**
     * True while the event is running (started but not yet ended).
     */
    public function isOngoing(): bool
    {
        if ($this->hasEnded()) return false;

        return $this->date->copy()->setTimeFromTimeString($this->start_time)->isPast();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id');
    }
}
