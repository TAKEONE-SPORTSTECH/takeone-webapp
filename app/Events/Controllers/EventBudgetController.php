<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventBudget;
use App\Events\Models\EventBudgetChat;
use App\Events\Models\EventBudgetItem;
use App\Events\Models\EventBudgetScenario;
use App\Events\Support\Budget\BudgetAdvisor;
use App\Events\Support\Budget\BudgetPlan;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /me/events/{uuid}/budget — the organiser's Budget & profit.
 *
 * Organiser only (EventAccess::canManage) on every door: costs, fees and the
 * profit an event aims for are the organiser's business, not an appointed
 * official's. Behind EVENT_BUDGET — off, every door answers 404.
 *
 * Every write answers with the whole fresh payload so the page re-renders in
 * place (No-Reload); nothing here is pushed to anybody else, because only
 * the organiser's own console shows it.
 */
class EventBudgetController extends Controller
{
    public function show(ClubEvent $event): View
    {
        $this->guard($event);

        // Every page at an event's address is drawn in the event's own branded
        // shell on every device (BrandEventPage), so there is one view.
        return view('events::budget.mobile.show', [
            'event' => $event,
            'payload' => BudgetPlan::payload($event, BudgetPlan::for($event)),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? $event->color : '#7c6bf5',
            'owner' => $event->host()->name,
        ]);
    }

    public function data(ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        return $this->fresh($event);
    }

    public function update(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $data = $request->validate([
            'planned_fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'expected_entrants' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'target_profit' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
            'reserve_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'round_to' => ['sometimes', Rule::in([0, 0.1, 0.25, 0.5, 1, 5])],
            'medal_unit_cost' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'medal_gold_cost' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'medal_silver_cost' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'medal_bronze_cost' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'medals_mode' => ['sometimes', Rule::in(EventBudget::MEDALS_MODES)],
            'sponsor_amount' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
        ]);

        BudgetPlan::ensure($event, Auth::user())->update($data);

        return $this->fresh($event, __('events::events.budget_saved'));
    }

    public function storeItem(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        $data = $this->validateItem($request);

        $budget = BudgetPlan::ensure($event, Auth::user());

        if ($budget->items()->count() >= 100) {
            return response()->json(['success' => false, 'message' => __('events::events.budget_too_many_items')], 422);
        }

        $budget->items()->create($data + ['sort' => (int) $budget->items()->max('sort') + 1]);

        return $this->fresh($event, __('events::events.budget_item_added'));
    }

    public function updateItem(Request $request, ClubEvent $event, string $item): JsonResponse
    {
        $this->guard($event);
        $row = $this->itemOf($event, $item);

        $row->update($this->validateItem($request));

        return $this->fresh($event, __('events::events.budget_saved'));
    }

    public function destroyItem(ClubEvent $event, string $item): JsonResponse
    {
        $this->guard($event);
        $this->itemOf($event, $item)->delete();

        return $this->fresh($event, __('events::events.budget_item_removed'));
    }

    public function storeScenario(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'entrants' => ['required', 'integer', 'min:0', 'max:100000'],
            'medals_mode' => ['required', Rule::in(EventBudget::MEDALS_MODES)],
            'sponsor' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $budget = BudgetPlan::ensure($event, Auth::user());

        if ($budget->scenarios()->count() >= 30) {
            return response()->json(['success' => false, 'message' => __('events::events.budget_too_many_scenarios')], 422);
        }

        $net = BudgetPlan::compute($event, $budget, [
            'fee' => $data['fee'], 'entrants' => $data['entrants'],
            'medals_mode' => $data['medals_mode'], 'sponsor' => $data['sponsor'] ?? 0,
        ])['net'];

        $budget->scenarios()->create([
            'name' => trim($data['name']), 'fee' => $data['fee'], 'entrants' => $data['entrants'],
            'medals_mode' => $data['medals_mode'], 'sponsor_amount' => $data['sponsor'] ?? 0,
            'net' => $net, 'created_by' => Auth::id(),
        ]);

        return $this->fresh($event, __('events::events.budget_scenario_saved'));
    }

    public function destroyScenario(ClubEvent $event, string $scenario): JsonResponse
    {
        $this->guard($event);
        $row = $this->scenarioOf($event, $scenario);
        $budget = $row->budget;

        DB::transaction(function () use ($row, $budget) {
            if ((int) $budget->approved_scenario_id === (int) $row->id) {
                $budget->update(['approved_scenario_id' => null, 'approved_at' => null, 'approved_by' => null]);
            }
            $row->delete();
        });

        return $this->fresh($event, __('events::events.budget_scenario_removed'));
    }

    /**
     * Approve a scenario: it becomes the budget's plan (fee, turnout, medal
     * rule, sponsor), and — only when asked — its fee becomes the event's real
     * entry fee. That is the one step on this page that changes the event.
     */
    public function approve(Request $request, ClubEvent $event, string $scenario): JsonResponse
    {
        $this->guard($event);
        $row = $this->scenarioOf($event, $scenario);
        $applyFee = $request->boolean('apply_fee');

        DB::transaction(function () use ($row, $event, $applyFee) {
            $row->budget->update([
                'planned_fee' => $row->fee,
                'expected_entrants' => $row->entrants,
                'medals_mode' => $row->medals_mode,
                'sponsor_amount' => $row->sponsor_amount,
                'approved_scenario_id' => $row->id,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            if ($applyFee) {
                BudgetPlan::applyFee($event, (float) $row->fee);
            }
        });

        Log::info('Event budget approved', ['event' => $event->uuid, 'scenario' => $row->uuid, 'fee_applied' => $applyFee, 'by' => Auth::id()]);

        return $this->fresh($event, $applyFee ? __('events::events.budget_fee_applied') : __('events::events.budget_approved'));
    }

    /** Events this organiser manages that have a budget to start from. */
    public function sources(ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        $access = app(EventAccess::class);

        $rows = EventBudget::with('event')
            ->where('event_id', '!=', $event->id)
            ->whereHas('items')
            ->withCount('items')
            ->latest('updated_at')
            ->limit(60)
            ->get()
            ->filter(fn ($b) => $b->event && ! $b->event->is_archived && $access->canManage($b->event, Auth::user()))
            ->take(20)
            ->map(fn ($b) => [
                'uuid' => $b->event->uuid,
                'title' => $b->event->title,
                'date' => $b->event->date ? \Illuminate\Support\Carbon::parse($b->event->date)->format('j M Y') : null,
                'items' => $b->items_count,
                'expenses' => BudgetPlan::compute($b->event, $b)['expenses'],
            ])->values();

        return response()->json(['success' => true, 'sources' => $rows]);
    }

    /** Copy another event's planned costs (and medal cost) into this budget. */
    public function copy(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['from' => ['required', 'uuid']]);

        $from = ClubEvent::where('uuid', $data['from'])->first();
        $source = $from ? BudgetPlan::for($from) : null;

        // One answer for missing and not-yours.
        if (! $from || ! $source || $from->id === $event->id || ! app(EventAccess::class)->canManage($from, Auth::user())) {
            return response()->json(['success' => false, 'message' => __('events::events.budget_source_missing')], 404);
        }

        DB::transaction(function () use ($event, $source, $from) {
            $budget = BudgetPlan::ensure($event, Auth::user());
            $sort = (int) $budget->items()->max('sort');

            foreach ($source->items()->get() as $item) {
                $budget->items()->create([
                    'category' => $item->category, 'label' => $item->label, 'amount' => $item->amount,
                    'role' => $item->role, 'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost,
                    'basis' => $item->basis, 'status' => 'planned', 'sort' => ++$sort,
                ]);
            }

            $budget->update([
                'medal_unit_cost' => $budget->medal_unit_cost ?: $source->medal_unit_cost,
                'target_profit' => $budget->target_profit ?: $source->target_profit,
                'copied_from_event_id' => $from->id,
            ]);
        });

        return $this->fresh($event, __('events::events.budget_copied', ['title' => $from->title]));
    }

    /** Merge small / split big divisions — the budget impact, simulated. */
    public function divisions(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate([
            'merge_below' => ['nullable', 'integer', 'min:2', 'max:16'],
            'split_above' => ['nullable', 'integer', 'min:2', 'max:256'],
            // Chosen merges: each a list of division ids to fold into one.
            'merges' => ['nullable', 'array', 'max:40'],
            'merges.*' => ['array', 'min:2', 'max:8'],
            'merges.*.*' => ['integer'],
            'screen' => ['nullable', 'array'],
        ]);
        $budget = BudgetPlan::for($event);
        $screen = BudgetAdvisor::screen($data['screen'] ?? null);

        return response()->json([
            'success' => true,
            'list' => BudgetPlan::divisionList($event, $budget, $screen),
            'impact' => ! empty($data['merges'])
                ? BudgetPlan::mergeImpact($event, $budget, $screen, $data['merges'])
                : BudgetPlan::divisionImpact($event, $budget, $screen, $data['merge_below'] ?? null, $data['split_above'] ?? null),
        ]);
    }

    /**
     * Ask the advisor. Read-only: it answers and may PROPOSE a scenario; saving
     * one is the organiser's tap on the ordinary scenario endpoint.
     */
    public function advise(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        abort_unless(BudgetAdvisor::enabled(), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
            // What is on the organiser's sliders — the advisor starts from there.
            'screen' => ['nullable', 'array'],
            'screen.fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'screen.entrants' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'screen.medals_mode' => ['nullable', Rule::in(EventBudget::MEDALS_MODES)],
            'screen.sponsor' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        // A local model can take longer than PHP's 30 s web limit (a cold load
        // alone is 10–15 s). This request only — the model call has its own
        // 75 s timeout inside, and the page gives up at 110 s.
        @set_time_limit(120);

        // The agent's memory lives on the server — one conversation per organiser
        // per event — so it survives a closed sheet or a reload.
        $chat = EventBudgetChat::for($event, (int) Auth::id());
        $history = array_map(fn ($t) => [
            'role' => $t['role'],
            'content' => $t['content'].(! empty($t['actions']) ? ' [Saved: '.implode('; ', $t['actions']).']' : ''),
        ], $chat->messages ?? []);

        $answer = BudgetAdvisor::ask($event, BudgetPlan::for($event), $data['message'], $history, BudgetAdvisor::screen($data['screen'] ?? null), Auth::user());

        $chat->remember('user', $data['message']);
        $chat->remember('assistant', $answer['reply'], array_filter(['actions' => $answer['actions'] ?? []]));
        $chat->save();

        Log::info('Event budget advisor asked', ['event' => $event->uuid, 'by' => Auth::id(), 'source' => $answer['source'], 'wrote' => $answer['wrote'] ?? false]);

        // When the agent SAVED something, the page re-reads the budget in place.
        if (! empty($answer['wrote'])) {
            $answer['budget'] = BudgetPlan::payload($event->fresh(), BudgetPlan::for($event));
        }

        return response()->json(['success' => true] + $answer);
    }

    /** The conversation so far, to restore the chat when the sheet opens. */
    public function advisorHistory(ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        abort_unless(BudgetAdvisor::enabled(), 404);

        $chat = EventBudgetChat::where('event_id', $event->id)->where('user_id', Auth::id())->first();

        return response()->json(['success' => true, 'messages' => array_map(
            fn ($t) => ['role' => $t['role'], 'content' => $t['content'], 'actions' => $t['actions'] ?? []],
            $chat?->messages ?? [],
        )]);
    }

    /** "New chat" — the advisor forgets this conversation. */
    public function advisorReset(ClubEvent $event): JsonResponse
    {
        $this->guard($event);
        abort_unless(BudgetAdvisor::enabled(), 404);

        EventBudgetChat::where('event_id', $event->id)->where('user_id', Auth::id())->delete();

        return response()->json(['success' => true]);
    }

    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(EventBudgetItem::CATEGORIES)],
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required_without:quantity', 'nullable', 'numeric', 'min:0', 'max:10000000'],
            'basis' => ['required', Rule::in(EventBudgetItem::BASES)],
            'status' => ['required', Rule::in(EventBudgetItem::STATUSES)],
            // People: how many × what each costs. Sent together or not at all.
            'role' => ['nullable', Rule::in(EventBudgetItem::ROLES)],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:10000', 'required_with:unit_cost'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'required_with:quantity'],
        ]);

        // Priced per unit: the total is how many × the price of one. A PEOPLE
        // line is always fixed (its headcount does not grow with entrants).
        if (($data['quantity'] ?? null) !== null) {
            if ($data['category'] === 'people') {
                $data['basis'] = 'fixed';
            } else {
                $data['role'] = null;
            }
            $data['amount'] = round($data['quantity'] * $data['unit_cost'], 3);
        } else {
            $data['quantity'] = null;
            $data['unit_cost'] = null;
        }

        return $data;
    }

    private function itemOf(ClubEvent $event, string $uuid): EventBudgetItem
    {
        $budget = BudgetPlan::for($event);
        abort_unless($budget, 404);

        return $budget->items()->where('uuid', $uuid)->firstOrFail();
    }

    private function scenarioOf(ClubEvent $event, string $uuid): EventBudgetScenario
    {
        $budget = BudgetPlan::for($event);
        abort_unless($budget, 404);

        return EventBudgetScenario::where('budget_id', $budget->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function fresh(ClubEvent $event, ?string $message = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'budget' => BudgetPlan::payload($event->fresh(), BudgetPlan::for($event)),
        ], fn ($v) => $v !== null));
    }

    private function guard(ClubEvent $event): void
    {
        abort_unless(BudgetPlan::enabled(), 404);
        abort_unless(app(EventAccess::class)->canManage($event, Auth::user()), 403);
    }
}
