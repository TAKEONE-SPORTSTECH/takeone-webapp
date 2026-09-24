<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A billing account's TakeOne subscription. One row per paying federation. */
class FederationSubscription extends Model
{
    protected $fillable = ['federation_id', 'plan', 'cycle', 'trial_ends_at', 'paid_until'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'paid_until' => 'datetime',
    ];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
