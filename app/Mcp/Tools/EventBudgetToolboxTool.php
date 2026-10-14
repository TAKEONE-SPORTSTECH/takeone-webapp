<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\Budget\AdvisorToolbox;
use App\Events\Support\Budget\BudgetAdvisor;
use App\Events\Support\Budget\BudgetPlan;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Use the event budget advisor\'s tools')]
#[Description('For an event you manage: run one of the SAME tools the in-app Budget & profit advisor uses — get_overview, list_costs, get_divisions, list_scenarios, cost_breakdown, risk_check, fee_vs_turnout (price sensitivity), people_plan, list_divisions (weight classes, singles, merge suggestions), divisions_what_if (merge chosen divisions / merge small / split big), solve_for_goal (least-disruptive plan to a profit goal within limits), ways_to_save, ways_to_earn_more, lowest_fee, what_if. Every number comes from the platform\'s exact calculator. Read-only: nothing is saved (to change the budget use manage_event_budget_item / set_event_budget). Pass the tool\'s own arguments in `args`, and optionally the sliders in `screen`.')]
class EventBudgetToolboxTool extends BaseTool
{
    private const READ_TOOLS = ['get_overview', 'list_costs', 'get_divisions', 'list_scenarios', 'cost_breakdown', 'risk_check', 'fee_vs_turnout', 'people_plan', 'list_divisions', 'divisions_what_if', 'solve_for_goal', 'ways_to_save', 'ways_to_earn_more', 'lowest_fee', 'what_if'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'tool' => $schema->string()->enum(self::READ_TOOLS)->required(),
            'args' => $schema->object()->description('The tool\'s arguments, e.g. {"target_profit": 250, "keep_fee": true} for solve_for_goal, or {"athletes_change_percent": -20} for what_if.'),
            'screen' => $schema->object()->description('Optional starting point: {"fee", "entrants", "medals_mode", "sponsor"}. Omit to use the saved plan.'),
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
            'tool' => 'required|in:'.implode(',', self::READ_TOOLS),
            'args' => 'nullable|array',
            'screen' => 'nullable|array',
        ]);

        // One answer for missing and not-yours.
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $box = new AdvisorToolbox($event, BudgetPlan::for($event), BudgetAdvisor::screen($v['screen'] ?? null));

        return Response::json(['event' => $event->uuid, 'tool' => $v['tool'], 'result' => $box->call($v['tool'], $v['args'] ?? [])]);
    }
}
