<?php

namespace App\Events\Support\Budget;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventBudget;
use App\Events\Models\EventBudgetItem;
use App\Events\Support\EventFee;

/**
 * The advisor's tools — what the model may READ and what it may DO, in one
 * place, used by the in-app advisor (as tool calls) and by the platform's MCP
 * server (App\Mcp\Tools\EventBudgetToolboxTool) alike.
 *
 * Every number a tool returns comes from the database or from BudgetPlan /
 * BudgetSolver — the model chooses tools and words, never arithmetic.
 *
 * Nothing here writes to the database. "Doing" means PROPOSING: apply_plan and
 * propose_change set the turn's proposal, which the page puts on the
 * organiser's sliders (undoable); keeping it stays the organiser's tap.
 */
final class AdvisorToolbox
{
    /** The working state of one conversation turn. */
    public array $screen;

    /** Plans the solver tools produced this turn, by id, for apply_plan. */
    private array $plans = [];

    /** What the model chose to put on the screen this turn. */
    public ?array $proposal = null;

    /** People lines re-counted this turn: uuid => [quantity, unit_cost]. */
    private array $peopleNotes = [];

    /** Limits heard in words, enforced on every solver call (BudgetAdvisor::keywords). */
    private array $heard;

    /** Whether a write tool may run this turn — the organiser asked for it, or confirmed. */
    private bool $mayWrite;

    /** Set when a write tool changed the budget, so the page re-reads it. */
    public bool $wrote = false;

    /** What the write tools did this turn, in plain words (for the reply and the memory). */
    public array $actions = [];

    public function __construct(
        private ClubEvent $event,
        private ?EventBudget $budget,
        array $screen,
        array $heard = [],
        private ?\App\Members\Models\User $actor = null,
        bool $mayWrite = false,
    ) {
        $this->screen = $screen;
        $this->heard = $heard;
        $this->mayWrite = $mayWrite && $actor !== null;
    }

    /** OpenAI-style function schemas, as the TextDriver contract expects. */
    public static function definitions(): array
    {
        $fn = fn (string $name, string $description, array $properties = []) => [
            'type' => 'function',
            'function' => ['name' => $name, 'description' => $description, 'parameters' => ['type' => 'object', 'properties' => (object) $properties]],
        ];
        $num = fn (string $d) => ['type' => 'number', 'description' => $d];
        $bool = fn (string $d) => ['type' => 'boolean', 'description' => $d];

        return [
            $fn('get_overview', 'The event budget right now: fee, athletes, income, costs, net result, profit target and gap, break-even fee, suggested fee, medals, entries so far and the real money taken/spent.'),
            $fn('list_costs', 'Every planned cost line: name, group, fixed or per athlete, amount, cost at this turnout, and status (planned / quoted / paid).'),
            $fn('get_divisions', 'Divisions and medals: how many divisions, how many athletes each has, medals needed counted to the entries vs a full set, cost per medal, bronze rule.'),
            $fn('list_scenarios', 'The saved scenarios and their result today.'),
            $fn('people_plan', 'The people on the budget: each role (referees, judges, organisers, volunteers, medical) with how many and the cost per person, paid vs volunteers, and the officials actually appointed so far.'),
            $fn('list_divisions', 'Every division (weight class) grouped under its heading, in draw order, with its athletes and the medals it uses; divisions with one athlete flagged; and merge suggestions (neighbouring divisions in the same group, one of them small) with the medals and money each saves.'),
            $fn('divisions_what_if', 'What merging small divisions or splitting big ones does to the budget: divisions running, medals by type, medal cost, per-division costs and the net — before and after. A simulation; it changes nothing.', [
                'merge' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'number']], 'description' => 'Specific merges: lists of division ids from list_divisions, e.g. [[637, 636], [670, 672]].'],
                'merge_below' => $num('Merge every division with fewer than this many athletes (e.g. 3).'),
                'split_above' => $num('Split every division with more than this many athletes in two (e.g. 12).'),
            ]),
            $fn('cost_breakdown', 'Analysis of the costs: each line\'s share of the total, cost per athlete, groups, fixed vs per-athlete, paid vs not yet paid.'),
            $fn('risk_check', 'Risk analysis: margin above break-even, how many athletes can drop before a loss, the result if turnout falls 10/20/30%, how much of the cost is fixed, dependence on the biggest line, money not yet spent.'),
            $fn('fee_vs_turnout', 'Price sensitivity: for several fees, the result if turnout drops 0-30%, and how many athletes each fee can afford to lose before missing the target or breaking even. Use it whenever a fee change might make athletes stay away.', [
                'fees' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'Fees to compare. Omit for the current fee and a few steps above.'],
            ]),
            $fn('solve_for_goal', 'Find the least disruptive plan that reaches a profit goal within the organiser\'s limits. Returns the best plan, alternatives, or the closest step if nothing fits. Each plan has a plan_id you can pass to apply_plan.', [
                'target_profit' => $num('The profit to reach. Omit to use the saved target.'),
                'keep_fee' => $bool('The fee must not change.'),
                'no_sponsor' => $bool('No sponsor is available.'),
                'athletes_fixed' => $bool('No more athletes can be found.'),
                'no_cuts' => $bool('Costs must not be cut.'),
                'protect' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Cost lines that must not be touched.'],
            ]),
            $fn('ways_to_save', 'Ways to cut costs, each with what it saves (the medal rule, cutting the biggest lines, an even trim). Lines already paid are never offered. Each has a plan_id.', [
                'protect' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Cost lines that must not be touched.'],
            ]),
            $fn('ways_to_earn_more', 'Ways to make more profit: fee steps, more athletes, and savings — each with what it adds. Each has a plan_id.'),
            $fn('lowest_fee', 'The lowest fee that still meets the profit target, and the break-even fee.', ['target_profit' => $num('Omit to use the saved target.')]),
            $fn('what_if', 'Work out the result of any change without applying it.', [
                'fee' => $num('New entry fee.'),
                'athletes' => $num('New number of paying athletes.'),
                'athletes_change_percent' => $num('Change in athletes, e.g. -20 for 20% fewer.'),
                'medals_mode' => ['type' => 'string', 'enum' => ['entries', 'full']],
                'sponsor' => $num('Costs a sponsor covers.'),
                'cut_line' => ['type' => 'string', 'description' => 'A cost line to change.'],
                'cut_percent' => $num('How much to cut that line, in %.'),
                'line_amount' => $num('Or the new amount for that line.'),
                'line_people' => $num('For a people line: the new number of people.'),
                'line_cost_per_person' => $num('For a people line: the new cost per person.'),
                'trim_all_percent' => $num('Trim every unpaid cost line by this %.'),
            ]),
            $fn('save_cost_line', 'SAVE a cost line to the budget (adds it, or changes the one with that name). For people give role + how_many + price_each (e.g. 10 referees × 30); for anything bought by the unit give how_many + price_each (e.g. 3 trophies × 15); otherwise give amount. Only when the organiser asked for it or confirmed.', [
                'name' => ['type' => 'string', 'description' => 'The line, e.g. "Referees". An existing line with this name is changed.'],
                'group' => ['type' => 'string', 'enum' => EventBudgetItem::CATEGORIES],
                'role' => ['type' => 'string', 'enum' => EventBudgetItem::ROLES, 'description' => 'For people.'],
                'how_many' => $num('Number of people / units.'),
                'price_each' => $num('Cost per person / per unit.'),
                'amount' => $num('A single sum, when not counted by units.'),
                'basis' => ['type' => 'string', 'enum' => EventBudgetItem::BASES],
                'status' => ['type' => 'string', 'enum' => EventBudgetItem::STATUSES],
            ]),
            $fn('remove_cost_line', 'REMOVE a cost line from the budget. Only when the organiser asked for it or confirmed.', ['name' => ['type' => 'string']]),
            $fn('save_plan', 'SAVE the plan\'s numbers: fee, expected athletes, profit target, medal prices (one price, or gold/silver/bronze each), medal rule, sponsor. This does not change the event\'s real entry fee. Only when the organiser asked for it or confirmed.', [
                'fee' => $num(''), 'athletes' => $num(''), 'target_profit' => $num(''), 'sponsor' => $num(''),
                'medal_price' => $num('Default price of one medal.'), 'gold_price' => $num(''), 'silver_price' => $num(''), 'bronze_price' => $num(''),
                'medals_mode' => ['type' => 'string', 'enum' => ['entries', 'full']],
            ]),
            $fn('apply_plan', 'Put a plan from solve_for_goal / ways_to_save / ways_to_earn_more on the organiser\'s screen (their sliders and cost lines move; nothing is saved).', ['plan_id' => ['type' => 'string']]),
            $fn('propose_change', 'Put a specific change on the organiser\'s screen (same fields as what_if, plus target_profit). Nothing is saved; they can undo.', [
                'fee' => $num(''), 'athletes' => $num(''), 'medals_mode' => ['type' => 'string', 'enum' => ['entries', 'full']], 'sponsor' => $num(''),
                'target_profit' => $num('A new profit target.'), 'cut_line' => ['type' => 'string'], 'cut_percent' => $num(''), 'line_amount' => $num(''), 'trim_all_percent' => $num(''),
                'line_people' => $num('For a people line: the new number of people.'), 'line_cost_per_person' => $num('For a people line: the new cost per person.'),
            ]),
        ];
    }

    /** Run one tool. Unknown tools and bad arguments answer with an error the model can read. */
    public function call(string $name, array $args): array
    {
        try {
            return match ($name) {
                'get_overview' => $this->overview(),
                'list_costs' => ['currency' => $this->currency(), 'lines' => $this->lines()],
                'get_divisions' => $this->divisions(),
                'list_scenarios' => ['scenarios' => BudgetAdvisor::facts($this->event, $this->budget, $this->screen)['scenarios']],
                'people_plan' => ['currency' => $this->currency(), 'lines' => array_values(array_filter($this->lines(), fn ($l) => $l['people'] !== null))] + BudgetPlan::people($this->event, $this->budget),
                'list_divisions' => ['currency' => $this->currency()] + BudgetPlan::divisionList($this->event, $this->budget, $this->screen),
                'divisions_what_if' => ! empty($args['merge']) && is_array($args['merge'])
                    ? ['currency' => $this->currency()] + BudgetPlan::mergeImpact($this->event, $this->budget, $this->screen, $args['merge'])
                    : ['currency' => $this->currency()] + BudgetPlan::divisionImpact($this->event, $this->budget, $this->screen,
                    isset($args['merge_below']) && is_numeric($args['merge_below']) ? max(2, (int) $args['merge_below']) : null,
                    isset($args['split_above']) && is_numeric($args['split_above']) ? max(2, (int) $args['split_above']) : null),
                'cost_breakdown' => $this->costBreakdown(),
                'risk_check' => $this->riskCheck(),
                'fee_vs_turnout' => $this->feeVsTurnout($args),
                'solve_for_goal' => $this->solve($args),
                'ways_to_save' => $this->options(BudgetSolver::savings($this->event, $this->budget, $this->screen, $this->limits($args))['options'], 'saves'),
                'ways_to_earn_more' => $this->options(BudgetSolver::improvements($this->event, $this->budget, $this->screen, $this->limits($args))['options'], 'gains'),
                'lowest_fee' => $this->lowestFee($args),
                'what_if' => $this->summarise($this->changePlan($args, 'what_if')),
                'apply_plan' => $this->applyPlan((string) ($args['plan_id'] ?? '')),
                'propose_change' => $this->propose($args),
                'save_cost_line' => $this->guardWrite() ?? $this->saveLine($args),
                'remove_cost_line' => $this->guardWrite() ?? $this->removeLine($args),
                'save_plan' => $this->guardWrite() ?? $this->savePlan($args),
                default => ['error' => 'No tool called '.$name.'.'],
            };
        } catch (\Throwable $e) {
            return ['error' => 'That did not work: '.mb_substr($e->getMessage(), 0, 120)];
        }
    }

    /** Writes need the organiser's word: an instruction in this message, or a yes to a question. */
    private function guardWrite(): ?array
    {
        return $this->mayWrite ? null : [
            'not_saved' => true,
            'say' => 'Nothing was saved. Tell the organiser exactly what you would change and ask them to confirm; save it when they say yes.',
        ];
    }

    private function saveLine(array $a): array
    {
        $name = trim(mb_substr((string) ($a['name'] ?? ''), 0, 120));
        if ($name === '') {
            return ['error' => 'A cost line needs a name.'];
        }
        $num = fn ($v, $max) => is_numeric($v) ? min($max, max(0, (float) $v)) : null;
        $budget = $this->budget ??= BudgetPlan::ensure($this->event, $this->actor);
        $item = $this->findLine($name, $budget->items()->get());

        $q = $num($a['how_many'] ?? null, 10000);
        $u = $num($a['price_each'] ?? null, 1000000);
        $amount = $num($a['amount'] ?? null, 10000000);
        $role = in_array($a['role'] ?? null, EventBudgetItem::ROLES, true) ? $a['role'] : null;
        $group = in_array($a['group'] ?? null, EventBudgetItem::CATEGORIES, true) ? $a['group'] : ($role ? 'people' : ($item->category ?? 'other'));

        $fields = ['label' => $item ? $item->label : $name, 'category' => $group];
        if ($q !== null && $u !== null) {
            $fields += ['quantity' => (int) round($q), 'unit_cost' => $u];
        } elseif ($q !== null && $item?->unit_cost !== null) {
            $fields += ['quantity' => (int) round($q), 'unit_cost' => (float) $item->unit_cost];
        } elseif ($u !== null && $item?->quantity !== null) {
            $fields += ['quantity' => $item->quantity, 'unit_cost' => $u];
        } elseif ($amount !== null) {
            $fields += ['amount' => $amount, 'quantity' => null, 'unit_cost' => null];
        } elseif (! $item) {
            return ['error' => 'Give how_many and price_each, or an amount.'];
        }
        if ($group === 'people') {
            $fields['role'] = $role ?? $item?->role ?? 'other';
            $fields['basis'] = 'fixed';
        } else {
            $fields['role'] = null;
            if (in_array($a['basis'] ?? null, EventBudgetItem::BASES, true)) {
                $fields['basis'] = $a['basis'];
            }
        }
        if (in_array($a['status'] ?? null, EventBudgetItem::STATUSES, true)) {
            $fields['status'] = $a['status'];
        }

        if ($item) {
            $item->update($fields);
        } else {
            if ($budget->items()->count() >= 100) {
                return ['error' => 'A budget can hold up to 100 costs.'];
            }
            $item = $budget->items()->create($fields + ['basis' => $fields['basis'] ?? 'fixed', 'status' => $fields['status'] ?? 'planned', 'sort' => (int) $budget->items()->max('sort') + 1]);
        }
        $item->refresh();
        $this->wrote = true;
        $this->actions[] = ($item->wasRecentlyCreated ? 'Added' : 'Updated').' "'.$item->label.'": '.($item->quantity !== null ? $item->quantity.' × '.(float) $item->unit_cost.' = ' : '').(float) $item->amount;

        return ['saved' => true, 'line' => ['name' => $item->label, 'group' => $item->category, 'how_many' => $item->quantity, 'price_each' => $item->unit_cost, 'amount' => (float) $item->amount], 'net_now' => BudgetPlan::compute($this->event, $budget, $this->screen)['net']];
    }

    private function removeLine(array $a): array
    {
        $item = $this->budget ? $this->findLine((string) ($a['name'] ?? ''), $this->budget->items()->get()) : null;
        if (! $item) {
            return ['error' => 'No cost line called "'.mb_substr((string) ($a['name'] ?? ''), 0, 60).'".'];
        }
        $label = $item->label;
        $item->delete();
        $this->wrote = true;
        $this->actions[] = 'Removed "'.$label.'"';

        return ['removed' => $label, 'net_now' => BudgetPlan::compute($this->event, $this->budget, $this->screen)['net']];
    }

    private function savePlan(array $a): array
    {
        $num = fn ($k, $max) => isset($a[$k]) && is_numeric($a[$k]) ? min($max, max(0, (float) $a[$k])) : null;
        $fields = array_filter([
            'planned_fee' => $num('fee', 100000),
            'expected_entrants' => ($n = $num('athletes', 100000)) !== null ? (int) round($n) : null,
            'target_profit' => $num('target_profit', 10000000),
            'sponsor_amount' => $num('sponsor', 10000000),
            'medal_unit_cost' => $num('medal_price', 10000),
            'medal_gold_cost' => $num('gold_price', 10000),
            'medal_silver_cost' => $num('silver_price', 10000),
            'medal_bronze_cost' => $num('bronze_price', 10000),
            'medals_mode' => in_array($a['medals_mode'] ?? null, ['entries', 'full'], true) ? $a['medals_mode'] : null,
        ], fn ($v) => $v !== null);
        if ($fields === []) {
            return ['error' => 'Nothing to save.'];
        }
        $budget = $this->budget ??= BudgetPlan::ensure($this->event, $this->actor);
        $budget->update($fields);
        $this->wrote = true;
        $this->actions[] = 'Saved the plan: '.implode(', ', array_map(fn ($k, $v) => $k.' '.$v, array_keys($fields), $fields));

        return ['saved' => $fields, 'net_now' => BudgetPlan::compute($this->event, $budget->fresh(), [])['net']];
    }

    private function overview(): array
    {
        $f = BudgetAdvisor::facts($this->event, $this->budget, $this->screen);

        return ['currency' => $f['currency'], 'on_screen' => $f['on_screen_now'], 'result' => $f['result_now'], 'entries_so_far' => $f['entries_so_far'], 'real_money_so_far' => $f['real_money_so_far'], 'medals' => $f['costs']['medals'], 'event_fee_types' => $f['event_fee_types']];
    }

    private function lines(): array
    {
        $plan = BudgetPlan::compute($this->event, $this->budget, $this->screen);
        $n = $plan['entrants'];
        $d = $plan['medals']['divisions_running'];

        return $this->budget ? $this->budget->items()->get()->map(fn (EventBudgetItem $i) => [
            'name' => $i->label, 'group' => $i->category, 'basis' => $i->basis, 'amount' => (float) $i->amount,
            'people' => $i->quantity !== null && $i->category === 'people' ? ['role' => $i->role ?: 'other', 'how_many' => $i->quantity, 'cost_per_person' => (float) $i->unit_cost] : null,
            'units' => $i->quantity !== null && $i->category !== 'people' ? ['how_many' => $i->quantity, 'price_each' => (float) $i->unit_cost] : null,
            'cost_at_this_turnout' => BudgetPlan::lineCost($i, $n, $d), 'status' => $i->status,
        ])->sortByDesc('cost_at_this_turnout')->values()->all() : [];
    }

    private function divisions(): array
    {
        $f = BudgetAdvisor::facts($this->event, $this->budget, $this->screen);

        return ['divisions' => $f['divisions'], 'medals' => $f['costs']['medals']];
    }

    private function costBreakdown(): array
    {
        $plan = BudgetPlan::compute($this->event, $this->budget, $this->screen);
        $total = max(0.001, $plan['costs']);
        $n = max(1, $plan['entrants']);
        $lines = array_map(fn ($l) => $l + [
            'share_of_costs_pct' => round($l['cost_at_this_turnout'] / $total * 100, 1),
            'per_athlete' => round($l['cost_at_this_turnout'] / $n, 2),
        ], $this->lines());
        $medals = $plan['medals']['cost'];

        return [
            'currency' => $this->currency(),
            'total_costs' => round($plan['costs'], 2),
            'cost_per_athlete' => round($plan['costs'] / $n, 2),
            'fee' => $plan['fee'],
            'lines' => $lines,
            'medals' => ['cost' => $medals, 'share_of_costs_pct' => round($medals / $total * 100, 1), 'count' => $plan['medals']['total']],
            'groups_pct' => array_map(fn ($v) => round($v / $total * 100, 1), array_filter($plan['by_category'], fn ($v) => $v > 0)),
            'fixed_share_pct' => round(($plan['fixed'] + $medals) / $total * 100, 1),
            'not_yet_paid' => round(collect($lines)->where('status', '!=', 'paid')->sum('cost_at_this_turnout'), 2),
        ];
    }

    private function riskCheck(): array
    {
        $plan = BudgetPlan::compute($this->event, $this->budget, $this->screen);
        $n = max(1, $plan['entrants']);
        $breakEvenAthletes = $plan['athletes_needed'];
        $drops = [];
        foreach ([10, 20, 30] as $pct) {
            $m = (int) round($n * (1 - $pct / 100));
            $drops[] = ['turnout_drop_pct' => $pct, 'athletes' => $m, 'net' => BudgetPlan::compute($this->event, $this->budget, ['entrants' => $m] + $this->screen)['net']];
        }
        $lines = $this->lines();
        $biggest = $lines[0] ?? null;

        return [
            'currency' => $this->currency(),
            'net_now' => $plan['net'],
            'target' => $plan['target'],
            'athletes_now' => $plan['entrants'],
            'athletes_to_break_even' => $breakEvenAthletes,
            'athletes_that_can_drop_before_a_loss' => $breakEvenAthletes !== null ? max(0, $plan['entrants'] - $breakEvenAthletes) : null,
            'safety_margin_pct' => $breakEvenAthletes !== null ? round(($plan['entrants'] - $breakEvenAthletes) / $n * 100, 1) : null,
            'if_turnout_falls' => $drops,
            'fixed_costs_share_pct' => round(($plan['fixed'] + $plan['medals']['cost']) / max(0.001, $plan['costs']) * 100, 1),
            'biggest_line' => $biggest ? ['name' => $biggest['name'], 'cost' => $biggest['cost_at_this_turnout'], 'share_pct' => round($biggest['cost_at_this_turnout'] / max(0.001, $plan['costs']) * 100, 1)] : null,
            'entries_so_far' => BudgetPlan::turnout($this->event),
            'relies_on_sponsor' => $plan['covered'] > 0 ? round($plan['covered'], 2) : 0,
        ];
    }

    private function feeVsTurnout(array $args): array
    {
        $plan = BudgetPlan::compute($this->event, $this->budget, $this->screen);
        $round = max(0.1, (float) ($this->budget?->round_to ?: 0.5));
        $fees = array_values(array_filter(array_map(fn ($f) => is_numeric($f) ? round(max(0, (float) $f), 3) : null, (array) ($args['fees'] ?? [])), fn ($f) => $f !== null));
        if ($fees === []) {
            $fees = array_map(fn ($k) => round($plan['fee'] + $k * max($round, 1), 3), [0, 1, 2, 3]);
        }
        $fees = array_slice(array_values(array_unique($fees)), 0, 6);
        $n = max(1, $plan['entrants']);
        $fixedish = $plan['fixed'] + $plan['medals']['cost'] - $plan['covered'];

        $rows = [];
        foreach ($fees as $f) {
            $nets = [];
            foreach ([0, 5, 10, 15, 20, 30] as $pct) {
                $m = (int) round($n * (1 - $pct / 100));
                $nets['drop_'.$pct.'pct'] = BudgetPlan::compute($this->event, $this->budget, ['fee' => $f, 'entrants' => $m] + $this->screen)['net'];
            }
            $margin = $f - $plan['per_entrant'];
            $forTarget = $margin > 0 ? (int) ceil(max(0, $fixedish + $plan['target']) / $margin) : null;
            $forBreakEven = $margin > 0 ? (int) ceil(max(0, $fixedish) / $margin) : null;
            $rows[] = [
                'fee' => $f,
                'net_if_turnout_drops' => $nets,
                'athletes_needed_for_target' => $forTarget,
                'can_lose_before_missing_target' => $forTarget !== null ? $n - $forTarget : null,
                'athletes_needed_to_break_even' => $forBreakEven,
                'can_lose_before_a_loss' => $forBreakEven !== null ? $n - $forBreakEven : null,
            ];
        }

        return ['currency' => $this->currency(), 'athletes_now' => $n, 'target' => $plan['target'], 'fees' => $rows];
    }

    private function solve(array $args): array
    {
        $limits = $this->limits($args);
        $s = BudgetSolver::solve($this->event, $this->budget, $this->screen, $limits);

        return [
            'goal' => $s['goal'],
            'already_meets_goal' => $s['already'],
            'best' => $s['best'] ? $this->summarise($s['best']) : null,
            'alternatives' => array_map(fn ($p) => $this->summarise($p), $s['alternatives']),
            'nothing_fits_closest' => $s['compromise'] ? $this->summarise($s['compromise']) : null,
            'limits_used' => array_keys(array_filter($limits, fn ($v) => $v === true)),
        ];
    }

    private function lowestFee(array $args): array
    {
        $l = BudgetSolver::lowestFee($this->event, $this->budget, $this->screen, isset($args['target_profit']) && is_numeric($args['target_profit']) ? (float) $args['target_profit'] : null);

        return ['lowest_fee_for_target' => $l['plan']['fee'], 'target' => $l['goal'], 'break_even_fee' => $l['break_even_fee'], 'plan' => $this->summarise($l['plan'])];
    }

    private function options(array $options, string $key): array
    {
        return ['currency' => $this->currency(), 'options' => array_map(fn ($o) => $this->summarise($o) + [$key => $o[$key] ?? null], array_slice($options, 0, 5))];
    }

    private function applyPlan(string $id): array
    {
        if (! isset($this->plans[$id])) {
            return ['error' => 'Unknown plan_id. Call solve_for_goal, ways_to_save or ways_to_earn_more first.'];
        }

        $this->proposal = $this->plans[$id];

        return ['applied' => true, 'plan' => $this->summarise($this->plans[$id], false)];
    }

    private function propose(array $args): array
    {
        $plan = $this->changePlan($args, 'change');
        $target = isset($args['target_profit']) && is_numeric($args['target_profit']) ? max(0, (float) $args['target_profit']) : null;

        if ($plan['changes'] === [] && $target === null) {
            return ['error' => 'That changes nothing on the screen.'];
        }

        $this->proposal = $plan + ($target !== null ? ['goal' => $target] : []);

        return ['applied' => true, 'plan' => $this->summarise($plan, false)];
    }

    /** A change described in what_if / propose_change terms, worked out as a plan. */
    private function changePlan(array $a, string $kind): array
    {
        $what = [];
        foreach (['fee', 'athletes', 'athletes_change_percent', 'sponsor'] as $k) {
            if (isset($a[$k]) && is_numeric($a[$k])) {
                $what[$k === 'athletes_change_percent' ? 'athletes_change_pct' : $k] = (float) $a[$k];
            }
        }
        if (in_array($a['medals_mode'] ?? null, ['entries', 'full'], true)) {
            $what['medals_mode'] = $a['medals_mode'];
        }
        if (! empty($this->heard['keep_fee'])) {
            unset($what['fee']);   // a limit heard in words outranks the model
        }

        $amounts = [];
        if ($this->budget) {
            $items = $this->budget->items()->get();
            if (! empty($a['cut_line'])) {
                $item = $this->findLine((string) $a['cut_line'], $items);
                if ($item && $item->status !== 'paid') {
                    if ($item->quantity !== null && (isset($a['line_people']) || isset($a['line_cost_per_person']))) {
                        // People: fewer of them, or each paid less — the total follows.
                        $q = isset($a['line_people']) && is_numeric($a['line_people']) ? max(0, (int) round($a['line_people'])) : $item->quantity;
                        $u = isset($a['line_cost_per_person']) && is_numeric($a['line_cost_per_person']) ? max(0, (float) $a['line_cost_per_person']) : (float) $item->unit_cost;
                        $amounts[$item->uuid] = round($q * $u, 3);
                        $this->peopleNotes[$item->uuid] = ['quantity' => $q, 'unit_cost' => $u];
                    } else {
                        $amounts[$item->uuid] = isset($a['line_amount']) && is_numeric($a['line_amount'])
                            ? max(0, (float) $a['line_amount'])
                            : max(0, round($item->amount * (1 - min(100, max(0, (float) ($a['cut_percent'] ?? 0))) / 100), 3));
                    }
                }
            }
            if (isset($a['trim_all_percent']) && is_numeric($a['trim_all_percent'])) {
                $p = min(100, max(0, (float) $a['trim_all_percent'])) / 100;
                foreach ($items->where('status', '!=', 'paid') as $i) {
                    $amounts[$i->uuid] ??= round($i->amount * (1 - $p), 3);
                }
            }
        }

        $plan = BudgetSolver::whatIf($this->event, $this->budget, $this->screen + ['item_amounts' => $amounts], $what);
        $plan['kind'] = $kind;
        $plan['item_amounts'] = $amounts;

        // whatIf() measures against a screen that already holds the cuts — recount
        // what moved against the real screen so the card shows every change.
        return $this->recount($plan);
    }

    private function recount(array $plan): array
    {
        $base = BudgetPlan::compute($this->event, $this->budget, $this->screen);
        $result = BudgetPlan::compute($this->event, $this->budget, [
            'fee' => $plan['fee'], 'entrants' => $plan['entrants'], 'medals_mode' => $plan['medals_mode'], 'sponsor' => $plan['sponsor'],
            'item_amounts' => $plan['item_amounts'],
        ]);

        $changes = [];
        foreach (['fee', 'entrants', 'medals_mode', 'sponsor'] as $k) {
            if ((string) $plan[$k] !== (string) $base[$k]) {
                $changes[] = ['field' => $k, 'from' => $base[$k], 'to' => $plan[$k]];
            }
        }
        if ($plan['item_amounts'] !== [] && $this->budget) {
            foreach ($this->budget->items()->whereIn('uuid', array_keys($plan['item_amounts']))->get() as $i) {
                if (abs((float) $i->amount - (float) $plan['item_amounts'][$i->uuid]) > 0.0005) {
                    $changes[] = ['field' => 'item', 'uuid' => $i->uuid, 'label' => $i->label, 'from' => (float) $i->amount, 'to' => (float) $plan['item_amounts'][$i->uuid]]
                        + (isset($this->peopleNotes[$i->uuid]) ? ['quantity' => $this->peopleNotes[$i->uuid]['quantity'], 'unit_cost' => $this->peopleNotes[$i->uuid]['unit_cost'], 'quantity_from' => $i->quantity, 'unit_cost_from' => (float) $i->unit_cost] : []);
                }
            }
        }

        return ['changes' => $changes, 'net' => $result['net'], 'net_before' => $base['net'], 'meets' => $result['net'] >= $base['target'] - 0.01] + $plan;
    }

    /** A plan the model can read (and refer to by plan_id). */
    private function summarise(array $plan, bool $register = true): array
    {
        $id = 'p'.(count($this->plans) + 1);
        if ($register) {
            $this->plans[$id] = $plan;
        }

        return array_filter([
            'plan_id' => $register ? $id : null,
            'what' => $plan['label'] ?? null,
            'changes' => array_map(fn ($c) => ($c['field'] === 'item' ? $c['label'] : $c['field']).': '.$c['from'].' → '.$c['to'], array_slice($plan['changes'], 0, 6))
                + (count($plan['changes']) > 6 ? [6 => '… and '.(count($plan['changes']) - 6).' more cost lines'] : []),
            'net_before' => $plan['net_before'] ?? null,
            'net_after' => $plan['net'] ?? null,
            'meets_target' => $plan['meets'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** Limits from the model's arguments, with every limit heard in words forced on. */
    private function limits(array $args): array
    {
        $l = [
            'target_profit' => isset($args['target_profit']) && is_numeric($args['target_profit']) ? (float) $args['target_profit'] : ($this->heard['target_profit'] ?? null),
            'protect' => array_values(array_unique(array_merge((array) ($this->heard['protect'] ?? []), array_filter((array) ($args['protect'] ?? []), 'is_string')))),
        ];
        foreach (['keep_fee', 'no_sponsor', 'athletes_fixed', 'no_cuts'] as $flag) {
            $l[$flag] = ($args[$flag] ?? false) === true || ! empty($this->heard[$flag]);
        }

        return $l;
    }

    private function findLine(string $name, $items): ?EventBudgetItem
    {
        $n = mb_strtolower(trim($name));

        return $items->first(fn ($i) => mb_strtolower($i->label) === $n)
            ?? $items->first(fn ($i) => str_contains(mb_strtolower($i->label), $n) || str_contains($n, mb_strtolower($i->label)));
    }

    private function currency(): string
    {
        return EventFee::currency($this->event);
    }
}
