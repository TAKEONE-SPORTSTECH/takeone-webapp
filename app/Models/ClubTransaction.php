<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubTransaction extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('financial')
            ->logOnly(['type', 'amount', 'description', 'category', 'payment_method', 'transaction_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'category',
        'amount',
        'payment_method',
        'description',
        'transaction_date',
        'reference_number',
        'subscription_id',
        'instructor_id',
        'recurring_expense_id',
        'is_test',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'is_test' => 'boolean',
    ];

    /**
     * Stamp the club's current test/live mode onto new transactions, unless
     * the caller already set it explicitly.
     */
    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            if (is_null($transaction->is_test) && $transaction->tenant_id) {
                $transaction->is_test = Tenant::isTestMode($transaction->tenant_id);
            }
        });
    }

    /**
     * Get the club that owns the transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user associated with the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with the transaction.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ClubMemberSubscription::class, 'subscription_id');
    }

    /**
     * The staff member this transaction pays (wage / final settlement), if any.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(ClubInstructor::class, 'instructor_id');
    }

    /**
     * Scope a query to only include income transactions.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    /**
     * Scope a query to only include expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    /**
     * Scope a query to only include refund transactions.
     */
    public function scopeRefund($query)
    {
        return $query->where('type', 'refund');
    }
}
