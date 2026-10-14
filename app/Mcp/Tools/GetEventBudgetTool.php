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

#[Title('Get an event budget')]
#[Description('For an event you manage: its Budget & profit plan — planned costs (fixed or per athlete), medals counted from the real divisions, the fee and turnout assumed, net result, break-even fee, athletes needed, suggested fee, saved scenarios, and the real income/costs so far. Pass fee/entrants/medals_mode/sponsor to work out a what-if without saving it. `advice_facts` holds pre-computed answers (athletes for the profit target, fee for the target, medal saving, sponsor needed, biggest costs) — quote those rather than recalculating.')]
class GetEventBudgetTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'fee' => $schema->number()->description('What-if: the entry fee per athlete.'),
            'entrants' => $schema->integer()->description('What-if: paying athletes.'),
            'medals_mode' => $schema->string()->enum(['entries', 'full'])->description('What-if: "entries" counts only places divisions can fill; "full" a full set per division.'),
            'sponsor' => $schema->number()->description('What-if: costs a sponsor covers.'),
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
            'fee' => 'nullable|numeric|min:0|max:100000',
            'entrants' => 'nullable|integer|min:0|max:100000',
            'medals_mode' => 'nullable|in:entries,full',
            'sponsor' => 'nullable|numeric|min:0|max:10000000',
        ]);

        // One answer for missing and not-yours.
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $budget = BudgetPlan::for($event);
        $out = BudgetPlan::payload($event, $budget);
        // The same pre-computed facts the in-app advisor reasons from, so an
        // outside assistant never has to do the arithmetic itself.
        $out['advice_facts'] = \App\Events\Support\Budget\BudgetAdvisor::facts($event, $budget);

        $with = array_filter(array_intersect_key($v, array_flip(['fee', 'entrants', 'medals_mode', 'sponsor'])), fn ($x) => $x !== null);
        if ($with !== []) {
            $out['what_if'] = BudgetPlan::compute($event, $budget, $with);
        }

        return Response::json($out);
    }
}
