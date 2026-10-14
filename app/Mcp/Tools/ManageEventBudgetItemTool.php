<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventBudgetItem;
use App\Events\Support\Budget\BudgetPlan;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Add, change or remove an event budget cost')]
#[Description('For an event you manage: add a planned cost to its budget (omit `item`), change one (pass its `item` uuid from get_event_budget), or remove one (`item` + `remove: true`). `basis` is "fixed" (one sum) or "per_entrant" (amount per athlete). For PEOPLE (referees, organisers, volunteers…) pass role + quantity + unit_cost and the total is worked out. Returns the whole recomputed budget.')]
class ManageEventBudgetItemTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'item' => $schema->string()->description('The cost\'s uuid, to change or remove it. Omit to add.'),
            'remove' => $schema->boolean()->description('With `item`: remove it.'),
            'label' => $schema->string()->description('What it is, e.g. "Referees".'),
            'amount' => $schema->number()->description('The sum, or the amount per athlete when basis is per_entrant.'),
            'category' => $schema->string()->enum(EventBudgetItem::CATEGORIES),
            'basis' => $schema->string()->enum(EventBudgetItem::BASES),
            'status' => $schema->string()->enum(EventBudgetItem::STATUSES),
            'role' => $schema->string()->enum(EventBudgetItem::ROLES)->description('For people lines: referee, judge, organiser, volunteer, medical, other.'),
            'quantity' => $schema->integer()->description('For people lines: how many people. With unit_cost, the total is quantity × unit_cost.'),
            'unit_cost' => $schema->number()->description('For people lines: cost per person (0 for an unpaid volunteer).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        if (! BudgetPlan::enabled()) {
            return Response::error('Event budgets are not switched on.');
        }

        $v = $request->validate([
            'event' => 'required|uuid',
            'item' => 'nullable|uuid',
            'remove' => 'nullable|boolean',
            'label' => 'required_without:item|string|max:120',
            'amount' => 'nullable|numeric|min:0|max:10000000',
            'role' => 'nullable|in:'.implode(',', EventBudgetItem::ROLES),
            'quantity' => 'nullable|integer|min:0|max:10000|required_with:unit_cost',
            'unit_cost' => 'nullable|numeric|min:0|max:1000000|required_with:quantity',
            'category' => 'nullable|in:'.implode(',', EventBudgetItem::CATEGORIES),
            'basis' => 'nullable|in:'.implode(',', EventBudgetItem::BASES),
            'status' => 'nullable|in:'.implode(',', EventBudgetItem::STATUSES),
        ]);

        // One answer for missing and not-yours.
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $fields = array_filter(array_intersect_key($v, array_flip(['label', 'amount', 'category', 'basis', 'status', 'role', 'quantity', 'unit_cost'])), fn ($x) => $x !== null);
        if (isset($fields['quantity'])) {
            $fields['category'] ??= 'people';
            $fields['basis'] = 'fixed';
        } elseif (empty($v['item']) && ! isset($fields['amount'])) {
            return Response::error('Give an amount, or quantity and unit_cost for a people line.');
        }

        if (! empty($v['item'])) {
            $budget = BudgetPlan::for($event);
            $item = $budget?->items()->where('uuid', $v['item'])->first();
            if (! $item) {
                return Response::error('Cost not found.');
            }

            ! empty($v['remove']) ? $item->delete() : $item->update($fields);
        } else {
            $budget = BudgetPlan::ensure($event, $user);
            if ($budget->items()->count() >= 100) {
                return Response::error('A budget can hold up to 100 costs.');
            }
            $budget->items()->create($fields + ['category' => 'other', 'basis' => 'fixed', 'status' => 'planned', 'sort' => (int) $budget->items()->max('sort') + 1]);
        }

        return Response::json(BudgetPlan::payload($event, BudgetPlan::for($event)));
    }
}
