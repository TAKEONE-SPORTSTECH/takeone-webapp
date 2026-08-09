<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventExpense extends Model
{
    protected $fillable = ['event_id', 'event_official_id', 'label', 'amount', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function official(): BelongsTo
    {
        return $this->belongsTo(EventOfficial::class, 'event_official_id');
    }

    /**
     * True when this line is owned by a paid appointment rather than typed in.
     *
     * System-managed lines are not hand-editable: deleting one would drop a real
     * cost out of the P&L while the person is still down as being paid. The way
     * to remove it is to change the appointment.
     */
    public function isSystemManaged(): bool
    {
        return $this->event_official_id !== null;
    }
}
