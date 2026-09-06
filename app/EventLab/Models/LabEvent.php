<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A competition, as the sandbox models one.
 *
 * `source_event_id` names the real `club_events` row this was modelled on. It is
 * deliberately NOT a foreign key and is never written back to: the sandbox reads
 * the real event to copy its shape, and that is the entire relationship between
 * them.
 */
class LabEvent extends Model
{
    use HasFactory;

    protected $table = 'lab_events';

    protected $fillable = [
        'uuid', 'source_event_id', 'tenant_id', 'title', 'sport', 'date', 'end_date',
        'weigh_in_at', 'entries_open_at', 'entries_close_at',
        'late_entry_from', 'late_fee_amount', 'fee_currency', 'status', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'weigh_in_at' => 'datetime',
        'entries_open_at' => 'datetime',
        'entries_close_at' => 'datetime',
        'late_entry_from' => 'datetime',
        'late_fee_amount' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabEvent $event) {
            $event->uuid = $event->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function variants(): HasMany
    {
        return $this->hasMany(LabVariant::class, 'lab_event_id')->orderBy('sort_order');
    }

    public function entrants(): HasMany
    {
        return $this->hasMany(LabEntrant::class, 'lab_event_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LabEntry::class, 'lab_event_id');
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(LabClub::class, 'lab_event_id');
    }

    /**
     * Is an entry committed NOW a late one?
     *
     * No late window means no penalty — an event that never said entries close
     * cannot charge somebody for being late.
     */
    public function isLateNow(): bool
    {
        return $this->late_entry_from !== null && now()->greaterThanOrEqualTo($this->late_entry_from);
    }

    /** The penalty for entering late, or zero when none was set. */
    public function lateFee(): float
    {
        return (float) ($this->late_fee_amount ?? 0);
    }
}
