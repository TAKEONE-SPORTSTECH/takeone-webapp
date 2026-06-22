<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A product sold in a club's Shop. Held in stock (quantity tracked) or
 * dropshipped (supplier ships on order).
 */
class ClubProduct extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'club_products';

    protected $fillable = [
        'tenant_id', 'name', 'brand', 'category', 'price', 'old_price', 'cost',
        'margin_type', 'margin_value', 'badge',
        'availability', 'featured', 'color', 'icon', 'image_path', 'description',
        'colors', 'specs', 'fulfillment', 'quantity', 'low_stock_alert',
        'supplier', 'supplier_url', 'ships_in', 'status', 'sort',
        'rating_count', 'rating_sum',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'old_price'    => 'decimal:2',
        'cost'         => 'decimal:2',
        'margin_value' => 'decimal:2',
        'featured'  => 'boolean',
        'colors'    => 'array',
        'specs'     => 'array',
        'quantity'  => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Shape for the shop grid / market card (matches the JS product object). */
    public function toCardArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'brand'       => $this->brand,
            'cat'         => $this->category,
            'price'       => (float) $this->price,
            'old'         => $this->old_price !== null ? (float) $this->old_price : null,
            'cost'        => $this->cost !== null ? (float) $this->cost : null,
            'marginType'  => $this->margin_type ?? 'fixed',
            'marginValue' => $this->margin_value !== null ? (float) $this->margin_value : null,
            'badge'       => $this->badge,
            'availability'=> $this->availability,
            'featured'    => (bool) $this->featured,
            'color'       => $this->color,
            'icon'        => $this->icon,
            'image'       => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'stock'       => $this->availability,   // display label for the market
            'rating'      => $this->rating_count ? round($this->rating_sum / $this->rating_count, 1) : 0,
            'reviews'     => (int) $this->rating_count,
            'desc'        => $this->description,
            'colors'      => $this->colors ?? [],
            'specs'       => $this->specs ?? [],
            'fulfillment' => $this->fulfillment,
            'quantity'    => $this->quantity,
            'lowStock'    => $this->low_stock_alert,
            'supplier'    => $this->supplier,
            'supplierUrl' => $this->supplier_url,
            'shipsIn'     => $this->ships_in,
        ];
    }
}
