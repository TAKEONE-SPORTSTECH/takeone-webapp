<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'club_product_id', 'club_product_variant_id',
        'name', 'brand', 'image_path', 'color', 'size', 'variant_label',
        'fulfillment', 'price', 'qty', 'line_total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'qty' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ClubProductVariant::class, 'club_product_variant_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}
