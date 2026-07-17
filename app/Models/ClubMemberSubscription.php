<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\DeletesUploadedFiles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubMemberSubscription extends Model
{
    use BelongsToTenant, DeletesUploadedFiles, HasFactory, LogsActivity;

    /**
     * Uploaded proof files removed automatically before the record is deleted.
     * Both live on the private `local` disk. See DeletesUploadedFiles trait.
     */
    protected array $fileUploads = [
        'proof_of_payment' => 'local',
        'refund_proof' => 'local',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('membership')
            ->logOnly(['status', 'payment_status', 'amount_paid', 'amount_due', 'start_date', 'end_date', 'package_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
        'type',
        'user_id',
        'club_affiliation_id',
        'package_id',
        'start_date',
        'end_date',
        'status',
        'payment_status',
        'settled_at',
        'amount_paid',
        'amount_due',
        'registration_fee',
        'registration_group_id',
        'notes',
        'proof_of_payment',
        'refund_proof',
        'active_key',
        'is_test',
    ];

    /**
     * Active statuses that occupy the deduplication slot.
     */
    private const ACTIVE_STATUSES = ['active', 'pending'];

    /**
     * Keep active_key in sync automatically.
     * active_key is non-null only for active/pending subscriptions, which
     * lets the unique index on that column block duplicates while allowing
     * multiple expired/cancelled rows for the same (tenant, user, package).
     */
    protected static function booted(): void
    {
        $setKey = function (self $sub): void {
            $sub->active_key = in_array($sub->status, self::ACTIVE_STATUSES, true)
                ? "{$sub->tenant_id}:{$sub->user_id}:".($sub->package_id ?? 'null')
                : null;
        };

        static::creating($setKey);
        static::updating($setKey);

        // Stamp the club's current test/live mode onto new subscriptions,
        // unless the caller already set it explicitly.
        static::creating(function (self $sub): void {
            if (is_null($sub->is_test) && $sub->tenant_id) {
                $sub->is_test = Tenant::isTestMode($sub->tenant_id);
            }
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'settled_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'is_test' => 'boolean',
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
     * Get the club affiliation associated with the subscription.
     */
    public function clubAffiliation(): BelongsTo
    {
        return $this->belongsTo(ClubAffiliation::class, 'club_affiliation_id');
    }

    /**
     * Get the transactions for the subscription.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ClubTransaction::class, 'subscription_id');
    }

    /**
     * Equipment lines purchased with this enrollment.
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(MemberEquipment::class, 'subscription_id');
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

    /**
     * IDs of every user who shares at least one club with the given user, where
     * BOTH sides have an actually club-owner-confirmed membership (status =
     * 'active'). Not `memberships.status`, which is set to 'active' the instant
     * a subscription record exists — even a still-pending self-registration.
     * Shared by the web "Find People" feature and the `search_people` MCP tool
     * so both enforce the same club-scoping.
     */
    public static function confirmedClubMateIds(int $userId): \Illuminate\Support\Collection
    {
        $clubIds = self::where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('tenant_id')
            ->unique();

        if ($clubIds->isEmpty()) {
            return collect();
        }

        return self::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id');
    }
}
