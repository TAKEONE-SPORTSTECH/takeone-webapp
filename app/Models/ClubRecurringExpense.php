<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubRecurringExpense extends Model
{
    use BelongsToTenant;

    protected $table = 'club_recurring_expenses';

    protected $fillable = [
        'tenant_id',
        'description',
        'amount',
        'category',
        'payment_method',
        'day_of_month',
        'notes',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'is_active'   => 'boolean',
        'last_run_at' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Whether this recurring expense has already run this month.
     */
    public function hasRunThisMonth(): bool
    {
        return $this->last_run_at !== null
            && $this->last_run_at->month === now()->month
            && $this->last_run_at->year === now()->year;
    }
}
