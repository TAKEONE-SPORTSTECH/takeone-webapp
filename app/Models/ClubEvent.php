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

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['title', 'date', 'end_date', 'status', 'scope', 'is_archived', 'max_capacity'])
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
        'weigh_in_at',
        'enrollment_starts_at',
        'enrollment_ends_at',
        'minutes_per_match',
        'courts',
        'break_minutes',
        'day_courts',
        'location',
        'gps_lat',
        'gps_long',
        'location_url',
        'break_start',
        'break_end',
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
        'scope',
        'uuid',
        'is_archived',
        // mobile Events extensions
        'event_type',
        'sport',
        'league',
        'icon',
        'participant_fee',
        'spectator_enabled',
        'spectator_fee',
        'prize',
        'results',
        'requirements',
        'phases',
        'agenda',
        'created_by',
    ];

    protected $casts = [
        'date'         => 'date',
        'end_date'     => 'date',
        'weigh_in_at'  => 'datetime',
        'enrollment_starts_at' => 'date',
        'enrollment_ends_at'   => 'date',
        'tags'         => 'array',
        'images'       => 'array',
        'max_capacity'       => 'integer',
        'cancel_within_days' => 'integer',
        'spots_taken'        => 'integer',
        'minutes_per_match'  => 'integer',
        'courts'             => 'integer',
        'break_minutes'      => 'integer',
        'day_courts'         => 'array',
        'is_archived'  => 'boolean',
        'spectator_enabled' => 'boolean',
        'requirements' => 'array',
        'phases'       => 'array',
        'agenda'       => 'array',
        'results'      => 'array',
        'league'       => 'array',
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

    /** True once the event's start moment has passed. */
    public function hasStarted(): bool
    {
        if (! $this->date) return false;

        return $this->date->copy()->setTimeFromTimeString($this->start_time ?: '00:00')->isPast();
    }

    /** True when this event's sport is a registered combat sport (config/combat.php). */
    public function isCombat(): bool
    {
        return $this->sport && array_key_exists($this->sport, config('combat.sports', []));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class, 'event_id')->orderBy('sort_order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'event_id');
    }

    /** Participant registrations (not spectators). */
    public function participantRegistrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id')->where('role', 'participant');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EventExpense::class, 'event_id');
    }
}
