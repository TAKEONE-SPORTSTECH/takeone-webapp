<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventExpense extends Model
{
    protected $fillable = ['event_id', 'label', 'amount', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
