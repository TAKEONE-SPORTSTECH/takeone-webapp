<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ClubMemberSubscription extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_member_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'package_id',
        'start_date',
        'end_date',
        'status',
        'payment_status',
        'amount_paid',
        'amount_due',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    /**
     * Get the club that owns the subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the member associated with the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package associated with the subscription.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ClubPackage::class, 'package_id');
    }

    /**
     * Get the transactions for the subscription.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ClubTransaction::class, 'subscription_id');
    }

    /**
     * Check if subscription is expiring soon (within 3 days).
     */
    public function isExpiringSoon(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $daysUntilExpiry = Carbon::now()->diffInDays($this->end_date, false);
        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= 3;
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->end_date < Carbon::now();
    }

    /**
     * Get days until expiry.
     */
    public function daysUntilExpiry(): int
    {
        return Carbon::now()->diffInDays($this->end_date, false);
    }
}
