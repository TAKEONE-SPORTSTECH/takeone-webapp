<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Links a shop product (ClubProduct) to an activity as required/optional gear.
 * The product is the single source of truth for name/price/image/stock; this
 * row only adds the registration-specific "required" flag and activity scope.
 * The price charged is snapshotted onto MemberEquipment at registration time.
 */
class ClubActivityEquipment extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'club_activity_equipment';

    protected $fillable = [
        'tenant_id',
        'activity_id',
        'club_product_id',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['club_product_id', 'is_required', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Convenience: the linked product's current name/price (live from the shop). */
    public function getNameAttribute(): ?string
    {
        return $this->product?->name;
    }

    public function getPriceAttribute(): float
    {
        return (float) ($this->product?->price ?? 0);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }

    public function memberEquipment(): HasMany
    {
        return $this->hasMany(MemberEquipment::class, 'equipment_id');
    }
}
