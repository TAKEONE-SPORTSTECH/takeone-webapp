<?php

namespace App\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An event's PLAN — the fee and turnout it assumes, what it means to spend,
 * and the profit it aims for. See App\Events\Support\Budget\BudgetPlan for the
 * arithmetic; this model holds only the organiser's inputs.
 */
class EventBudget extends Model
{
    public const MEDALS_MODES = ['entries', 'full'];

    protected $fillable = [
        'uuid', 'event_id', 'planned_fee', 'expected_entrants', 'target_profit', 'reserve_percent',
        'round_to', 'medal_unit_cost', 'medal_gold_cost', 'medal_silver_cost', 'medal_bronze_cost', 'medals_mode', 'sponsor_amount', 'approved_scenario_id',
        'approved_at', 'approved_by', 'copied_from_event_id', 'created_by',
    ];

    protected $casts = [
        'planned_fee' => 'float',
        'expected_entrants' => 'integer',
        'target_profit' => 'float',
        'reserve_percent' => 'float',
        'round_to' => 'float',
        'medal_unit_cost' => 'float',
        'medal_gold_cost' => 'float',
        'medal_silver_cost' => 'float',
        'medal_bronze_cost' => 'float',
        'sponsor_amount' => 'float',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventBudget $budget) {
            $budget->uuid ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventBudgetItem::class, 'budget_id')->orderBy('sort')->orderBy('id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(EventBudgetScenario::class, 'budget_id')->latest('id');
    }

    public function approvedScenario(): BelongsTo
    {
        return $this->belongsTo(EventBudgetScenario::class, 'approved_scenario_id');
    }
}
