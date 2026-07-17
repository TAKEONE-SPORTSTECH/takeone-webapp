<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
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
    use DeletesUploadedFiles, SoftDeletes;

    /**
     * Uploaded payment proof removed automatically on force-delete (not on
     * soft-delete). Lives on the public disk. See DeletesUploadedFiles trait.
     */
    protected array $fileUploads = [
        'payment_proof_path' => 'public',
    ];

    protected $fillable = [
        'reference', 'tenant_id', 'user_id', 'status', 'subtotal', 'total',
        'currency', 'has_dropship', 'note', 'payment_proof_path', 'received_at',
        'income_transaction_id', 'is_test',
    ];

    protected $dates = ['received_at'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'has_dropship' => 'boolean',
        'is_test' => 'boolean',
    ];

    public const STATUSES = ['pending', 'confirmed', 'fulfilled', 'received', 'cancelled'];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->reference)) {
                do {
                    $ref = 'TK-'.strtoupper(Str::random(6));
                } while (static::where('reference', $ref)->exists());
                $order->reference = $ref;
            }

            if (is_null($order->is_test) && $order->tenant_id) {
                $order->is_test = Tenant::isTestMode($order->tenant_id);
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
        return $this->payment_proof_path ? asset('storage/'.$this->payment_proof_path) : null;
    }
}
