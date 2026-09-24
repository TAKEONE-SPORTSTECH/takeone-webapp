<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One TakeOne invoice to a billing account. `number` doubles as the bank
 * reference. Paid only when a platform administrator confirms the transfer.
 */
class FederationInvoice extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_PAID = 'Paid';

    public const STATUS_VOID = 'Void';

    protected $fillable = [
        'federation_id', 'number', 'description', 'cycle', 'amount', 'currency', 'lines',
        'method', 'status', 'requested_by', 'confirmed_at', 'confirmed_by',
    ];

    protected $casts = [
        'lines' => 'array',
        'amount' => 'decimal:3',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (FederationInvoice $i) => $i->uuid ??= (string) Str::uuid());
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
