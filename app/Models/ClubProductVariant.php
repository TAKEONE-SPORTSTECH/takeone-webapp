<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single sellable variant of a ClubProduct — one size/colour/brand combination
 * with its own price and stock. When a product has variants, the variant is the
 * source of truth for the price charged and the stock decremented.
 */
class ClubProductVariant extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, DeletesUploadedFiles;

    protected $table = 'club_product_variants';

    /** Per-variant uploaded image lives on the public disk. */
    protected array $fileUploads = [
        'image_path',
    ];

    protected $fillable = [
        'tenant_id', 'club_product_id',
        'size', 'color', 'color_hex', 'brand', 'sku',
        'price', 'old_price', 'cost', 'quantity', 'low_stock_alert',
        'image_path', 'is_active', 'sort',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'old_price'       => 'decimal:2',
        'cost'            => 'decimal:2',
        'quantity'        => 'integer',
        'low_stock_alert' => 'integer',
        'is_active'       => 'boolean',
        'sort'            => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }

    /** Human label, e.g. "Adidas · Royal Blue · M". Empty parts are dropped. */
    public function getLabelAttribute(): string
    {
        return collect([$this->brand, $this->color, $this->size])
            ->filter()
            ->implode(' · ');
    }

    /** Stock-tracked variant with nothing left? */
    public function isOutOfStock(): bool
    {
        return $this->quantity !== null && $this->quantity <= 0;
    }

    /** Shape consumed by the shop grid + registration equipment picker JS. */
    public function toCardArray(): array
    {
        return [
            'id'        => $this->id,
            'size'      => $this->size,
            'color'     => $this->color,
            'color_hex' => $this->color_hex,
            'brand'     => $this->brand,
            'sku'       => $this->sku,
            'label'     => $this->label,
            'price'     => (float) $this->price,
            'old_price' => $this->old_price !== null ? (float) $this->old_price : null,
            'quantity'  => $this->quantity,
            'image'     => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'is_active' => (bool) $this->is_active,
            'in_stock'  => ! $this->isOutOfStock(),
        ];
    }
}
