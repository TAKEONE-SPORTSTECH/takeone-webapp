<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A shop order — one per club (tenant) per checkout. Manual fulfilment:
 * pending → confirmed → fulfilled (or cancelled). No payment gateway.
 */
class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'tenant_id', 'user_id', 'status', 'subtotal', 'total',
        'currency', 'has_dropship', 'note', 'payment_proof_path', 'received_at',
        'income_transaction_id',
    ];

    protected $dates = ['received_at'];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'total'        => 'decimal:2',
        'has_dropship' => 'boolean',
    ];

    public const STATUSES = ['pending', 'confirmed', 'fulfilled', 'received', 'cancelled'];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->reference)) {
                do {
                    $ref = 'TK-' . strtoupper(Str::random(6));
                } while (static::where('reference', $ref)->exists());
                $order->reference = $ref;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getQuantityAttribute(): int
    {
        return (int) ($this->items_sum_qty ?? $this->items()->sum('qty'));
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path ? asset('storage/' . $this->payment_proof_path) : null;
    }
}
