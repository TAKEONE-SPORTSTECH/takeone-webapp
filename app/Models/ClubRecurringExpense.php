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
        'instructor_id',
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
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'last_run_at' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The staff member this recurring wage rule pays, if this row is a staff wage
     * (as opposed to a generic recurring expense like rent).
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(ClubInstructor::class, 'instructor_id');
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
