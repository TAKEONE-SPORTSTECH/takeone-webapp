<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A saved what-if: a fee, a turnout, a medal rule and a sponsor amount, laid
 * against the budget's costs. `net` is the result when it was saved — the list
 * shows it; opening a scenario recomputes it from today's costs.
 */
class EventBudgetScenario extends Model
{
    protected $fillable = ['uuid', 'budget_id', 'name', 'fee', 'entrants', 'medals_mode', 'sponsor_amount', 'net', 'created_by'];

    protected $casts = [
        'fee' => 'float',
        'entrants' => 'integer',
        'sponsor_amount' => 'float',
        'net' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventBudgetScenario $scenario) {
            $scenario->uuid ??= (string) Str::uuid();
        });
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(EventBudget::class, 'budget_id');
    }
}
