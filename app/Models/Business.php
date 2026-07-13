<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A Business (chain) groups several clubs (tenants) under one owner.
 * Created in a "pending" state and activated once a super-admin approves it.
 */
class Business extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'logo',
        'description',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * The user who owns the chain.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * The super-admin who approved the chain.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The clubs (tenants) that belong to this chain.
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Tenant::class, 'business_id');
    }

    /**
     * The ownership-transfer audit trail (most recent first).
     */
    public function ownershipLogs(): HasMany
    {
        return $this->hasMany(BusinessOwnershipLog::class)->latest();
    }

    /**
     * Transfer the chain to a new owner and record the change in the audit trail.
     *
     * @param  bool  $reassignClubs  Also set owner_user_id on every club in the chain.
     */
    public function transferOwnerTo(int $newOwnerId, ?int $changedBy = null, bool $reassignClubs = false, ?string $note = null): BusinessOwnershipLog
    {
        $previousOwnerId = $this->owner_user_id;
        $reassignedCount = 0;

        if ($reassignClubs) {
            $reassignedCount = $this->clubs()->update(['owner_user_id' => $newOwnerId]);
        }

        $this->update(['owner_user_id' => $newOwnerId]);

        // Drop both users' sessions so the view switcher reflects the change immediately.
        DB::table('sessions')->whereIn('user_id', array_filter([$previousOwnerId, $newOwnerId]))->delete();

        return $this->ownershipLogs()->create([
            'from_user_id' => $previousOwnerId,
            'to_user_id' => $newOwnerId,
            'changed_by' => $changedBy,
            'clubs_reassigned' => $reassignClubs,
            'clubs_reassigned_count' => $reassignedCount,
            'note' => $note,
        ]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approve the business and auto-link every club the owner already owns.
     * New clubs the owner creates later inherit business_id at creation time.
     */
    public function approve(?int $approverId = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $approverId,
        ]);

        Tenant::where('owner_user_id', $this->owner_user_id)
            ->whereNull('business_id')
            ->update(['business_id' => $this->id]);

        // Drop sessions so the switcher appears immediately for the owner.
        DB::table('sessions')->where('user_id', $this->owner_user_id)->delete();
    }

    /**
     * Reject the business with an optional reason.
     */
    public function reject(?string $reason = null, ?int $approverId = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => $approverId,
        ]);
    }
}
