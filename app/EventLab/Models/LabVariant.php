<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A kind of competition inside one event — Gi, No-Gi, a points division, an
 * absolute. An athlete enters one or more of them on a single entry, and each
 * carries its own fee.
 *
 * The live platform cannot express this: `club_event_registrations` is unique
 * per (event, user), so a second thing to enter has to be a second event. That
 * is the constraint this table exists to test a way around.
 */
class LabVariant extends Model
{
    protected $table = 'lab_variants';

    protected $fillable = [
        'lab_event_id', 'name', 'slug', 'description', 'fee_amount', 'capacity', 'sort_order',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:3',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(LabEvent::class, 'lab_event_id');
    }

    public function fee(): float
    {
        return (float) $this->fee_amount;
    }
}
