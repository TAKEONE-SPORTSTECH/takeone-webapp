<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\Budget\BudgetPlan;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set an event budget\'s plan')]
#[Description('For an event you manage: set its budget\'s planned fee and turnout, profit target, safety reserve %, fee rounding, cost per medal, medal rule ("entries" or "full") and sponsor amount. Only the fields you pass change. This never changes the event\'s real entry fee — that is done by approving a scenario in the event console.')]
class SetEventBudgetTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'planned_fee' => $schema->number(),
            'expected_entrants' => $schema->integer(),
            'target_profit' => $schema->number(),
            'reserve_percent' => $schema->number(),
            'round_to' => $schema->number()->description('0, 0.1, 0.25, 0.5, 1 or 5.'),
            'medal_unit_cost' => $schema->number(),
            'medals_mode' => $schema->string()->enum(['entries', 'full']),
            'sponsor_amount' => $schema->number(),
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

        // The same rules as the console's PUT /me/events/{uuid}/budget.
        $v = $request->validate([
            'event' => 'required|uuid',
            'planned_fee' => 'nullable|numeric|min:0|max:100000',
            'expected_entrants' => 'nullable|integer|min:0|max:100000',
            'target_profit' => 'nullable|numeric|min:0|max:10000000',
            'reserve_percent' => 'nullable|numeric|min:0|max:100',
            'round_to' => 'nullable|in:0,0.1,0.25,0.5,1,5',
            'medal_unit_cost' => 'nullable|numeric|min:0|max:10000',
            'medals_mode' => 'nullable|in:entries,full',
            'sponsor_amount' => 'nullable|numeric|min:0|max:10000000',
        ]);

        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $fields = array_filter(array_diff_key($v, ['event' => 1]), fn ($x) => $x !== null);
        BudgetPlan::ensure($event, $user)->update($fields);

        return Response::json(BudgetPlan::payload($event, BudgetPlan::for($event)));
    }
}
