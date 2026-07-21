<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks low-stock alert state for one product: when the owner was last nagged
 * ({@see last_notified_at}, drives the 24h re-alert cadence) and whether alerts
 * for this item are muted indefinitely ({@see muted_at}).
 */
class StockAlertState extends Model
{
    protected $fillable = ['tenant_id', 'club_product_id', 'last_notified_at', 'muted_at'];

    protected $casts = [
        'last_notified_at' => 'datetime',
        'muted_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }
}
