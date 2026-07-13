<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equipment a member owns (or is acquiring). Serves as both the frozen
 * accounting line (name + price snapshot) and the ownership memory that drives
 * smart default ticks at the next registration.
 */
class MemberEquipment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'member_equipment';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'activity_id',
        'equipment_id',
        'club_product_id',
        'club_product_variant_id',
        'subscription_id',
        'name',
        'variant_label',
        'price',
        'status',
        'acquired_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'acquired_at' => 'datetime',
    ];

    /** Statuses that count as "already owns it" for default-skip purposes. */
    public const OWNED_STATUSES = ['pending', 'owned'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClubActivityEquipment::class, 'equipment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClubProduct::class, 'club_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ClubProductVariant::class, 'club_product_variant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ClubMemberSubscription::class, 'subscription_id');
    }
}
