<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One entry, by one entrant, into one event — carrying the state and the money.
 *
 * What was ENTERED hangs off it in `lab_entry_variants`, one row per thing
 * chosen. That is what lets a single entry cover Gi and No-Gi without becoming
 * two events, and it is why the total lives here while the per-item prices live
 * there.
 *
 * Every figure on this row is a SNAPSHOT of what was charged at the moment of
 * entry. Re-pricing a variant tomorrow must never change what somebody already
 * owes, so nothing recomputes these from live configuration.
 */
class LabEntry extends Model
{
    protected $table = 'lab_entries';

    protected $fillable = [
        'uuid', 'lab_event_id', 'lab_entrant_id', 'state', 'entered_at',
        'is_late', 'late_fee_amount', 'fee_total', 'fee_currency', 'fee_breakdown',
        'payment_state', 'paid_at', 'paid_by', 'created_by',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'paid_at' => 'datetime',
        'is_late' => 'boolean',
        'late_fee_amount' => 'decimal:3',
        'fee_total' => 'decimal:3',
        'fee_breakdown' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabEntry $entry) {
            $entry->uuid = $entry->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LabEvent::class, 'lab_event_id');
    }

    public function entrant(): BelongsTo
    {
        return $this->belongsTo(LabEntrant::class, 'lab_entrant_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(LabEntrySelection::class, 'lab_entry_id');
    }
}
