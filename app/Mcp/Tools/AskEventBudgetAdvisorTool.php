<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\Budget\BudgetAdvisor;
use App\Events\Support\Budget\BudgetPlan;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Ask the event budget advisor')]
#[Description('For an event you manage: ask the Budget & profit advisor anything about the event\'s money, in plain words — it reads the budget with its own tools and answers, sometimes with a proposed change (fee, athletes, medals, sponsor, cost lines) worked out by the platform. Read-only: a proposal is not saved. Pass earlier turns in `history` to keep a conversation going.')]
class AskEventBudgetAdvisorTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'message' => $schema->string()->required()->description('The question, up to 500 characters.'),
            'history' => $schema->array()->description('Earlier turns: [{"role": "user"|"assistant", "content": "..."}], up to 8.'),
            'screen' => $schema->object()->description('Optional starting point: {"fee", "entrants", "medals_mode", "sponsor"}.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        if (! BudgetAdvisor::enabled()) {
            return Response::error('The budget advisor is not switched on.');
        }

        $v = $request->validate([
            'event' => 'required|uuid',
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:8',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1200',
            'screen' => 'nullable|array',
        ]);

        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        @set_time_limit(120);
        $answer = BudgetAdvisor::ask($event, BudgetPlan::for($event), $v['message'], $v['history'] ?? [], BudgetAdvisor::screen($v['screen'] ?? null));

        return Response::json(['event' => $event->uuid] + $answer);
    }
}
