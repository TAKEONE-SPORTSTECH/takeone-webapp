<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One planned cost. `basis = fixed` is a sum for the whole event (the hall);
 * `per_entrant` is a unit cost that grows with every entrant (water, badges).
 */
class EventBudgetItem extends Model
{
    public const CATEGORIES = ['people', 'awards', 'venue', 'running', 'other'];

    public const BASES = ['fixed', 'per_entrant', 'per_division'];

    public const STATUSES = ['planned', 'quoted', 'paid'];

    /** People a line can count. A volunteer may cost nothing, or their meal / T-shirt. */
    public const ROLES = ['referee', 'judge', 'organiser', 'volunteer', 'medical', 'other'];

    protected $fillable = ['uuid', 'budget_id', 'category', 'role', 'label', 'amount', 'quantity', 'unit_cost', 'basis', 'status', 'sort'];

    protected $casts = [
        'amount' => 'float',
        'quantity' => 'integer',
        'unit_cost' => 'float',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventBudgetItem $item) {
            $item->uuid ??= (string) Str::uuid();
        });

        // A people line's total IS count × cost per person — kept in step on
        // every save, so everything that sums `amount` stays right.
        static::saving(function (EventBudgetItem $item) {
            if ($item->quantity !== null && $item->unit_cost !== null) {
                $item->amount = round($item->quantity * $item->unit_cost, 3);
            }
        });
    }

    public function isPeople(): bool
    {
        return $this->quantity !== null;
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(EventBudget::class, 'budget_id');
    }
}
