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
    use BelongsToTenant, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Entries cannot close AFTER the event they are for. The create form
        // already refuses it, but a seeder, an import or an admin path could
        // still write it — and an event advertising a closing date past its own
        // start reads as open when it is not.
        static::saving(function (self $event) {
            if ($event->enrollment_ends_at && $event->date && $event->enrollment_ends_at->gt($event->date)) {
                $event->enrollment_ends_at = $event->date;
            }
        });

        // Keep the money in step with the display line, whichever a caller set.
        //
        // Several forms still post only the sentence ("BHD 20"), assembled in
        // the browser from an amount and the club's currency. Rather than trust
        // that string later — which is how "10-15 BHD" came to bill 10 — the
        // number is derived here, once, at the edge. A caller that sets the
        // amount itself is authoritative and is never overwritten.
        static::saving(function (self $event) {
            if (! $event->fee_currency) {
                $event->fee_currency = $event->tenant?->currency ?: 'BHD';
            }

            foreach (['participant_fee', 'spectator_fee'] as $column) {
                $amountColumn = $column.'_amount';

                if ($event->isDirty($column) && ! $event->isDirty($amountColumn)) {
                    $event->{$amountColumn} = \App\Events\Support\EventFee::parse($event->{$column});
                }
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
        'scoreboard_settings',
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
        'notify_countries',
        'uuid',
        'is_archived',
        // mobile Events extensions
        'event_type',
        'sport',
        'league',
        'icon',
        // `*_fee` is the DISPLAY line; `*_fee_amount` + `fee_currency` are the
        // money. See App\Events\Support\EventFee — nothing computes a price
        // from the string any more.
        'participant_fee',
        'participant_fee_amount',
        'spectator_enabled',
        'spectator_fee',
        'spectator_fee_amount',
        'fee_currency',
        'prize',
        'results',
        'requirements',
        'phases',
        'agenda',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'weigh_in_at' => 'datetime',
        'enrollment_starts_at' => 'date',
        'enrollment_ends_at' => 'date',
        'tags' => 'array',
        'notify_countries' => 'array',
        'images' => 'array',
        'max_capacity' => 'integer',
        'cancel_within_days' => 'integer',
        'spots_taken' => 'integer',
        'minutes_per_match' => 'integer',
        'courts' => 'integer',
        'break_minutes' => 'integer',
        'day_courts' => 'array',
        'scoreboard_settings' => 'array',
        'is_archived' => 'boolean',
        'spectator_enabled' => 'boolean',
        'participant_fee_amount' => 'decimal:3',
        'spectator_fee_amount' => 'decimal:3',
        'requirements' => 'array',
        'phases' => 'array',
        'agenda' => 'array',
        'results' => 'array',
        'league' => 'array',
        'started_at' => 'datetime',
        'start_overridden' => 'boolean',
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
        return $this->hasStarted() && ! $this->hasEnded();
    }

    /**
     * True once someone has STARTED this event.
     *
     * Deliberately not the clock. The start time is a plan; starting is a
     * decision, made by the organiser once the hall is ready — and the
     * readiness checklist can hold that decision back. An event whose start
     * time has passed but which nobody started is late, not running, and its
     * draw is still arrangeable.
     *
     * Everything that asks "may this still be changed?" (draw locking, the
     * arrange screens, the package actions) reads this one method, so there is
     * exactly one answer.
     */
    public function hasStarted(): bool
    {
        return $this->started_at !== null;
    }

    /** The moment the clock says it was MEANT to begin. */
    public function scheduledStart(): ?\Carbon\Carbon
    {
        if (! $this->date) {
            return null;
        }

        return $this->date->copy()->setTimeFromTimeString($this->start_time ?: '00:00');
    }

    /** Its scheduled start has passed and nobody has started it. */
    public function isOverdueToStart(): bool
    {
        $due = $this->scheduledStart();

        return ! $this->hasStarted() && ! $this->hasEnded() && $due !== null && $due->isPast();
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

    /** People appointed to officiate this event (jury by default). */
    public function officials(): HasMany
    {
        return $this->hasMany(EventOfficial::class, 'event_id');
    }

    /** What must be true before the day can begin, in the organiser's order. */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventChecklistItem::class, 'event_id')
            ->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Nothing on the checklist is still outstanding.
     *
     * An event with no checklist is ready by definition — the organiser who
     * never wrote a list is not thereby blocked from starting their event.
     */
    public function isReadyToStart(): bool
    {
        return $this->outstandingChecks() === 0;
    }

    /** How many checklist items are still unchecked. */
    public function outstandingChecks(): int
    {
        return $this->checklistItems()->whereNull('checked_at')->count();
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

    /** Files attached for people to download (rulebook, entry form, schedule). */
    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class, 'event_id')->orderBy('sort_order');
    }
}
