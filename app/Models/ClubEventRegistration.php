<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubEventRegistration extends Model
{
    protected $table = 'club_event_registrations';

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'status',
        'paid',
        'payment_proof',
        'paid_at',
        'category_id',
        'weight',
        'weighed_in_at',
        'weighed_in_by',
        'meta',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'weighed_in_at' => 'datetime',
        'paid_at' => 'datetime',
        'paid' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }
}
