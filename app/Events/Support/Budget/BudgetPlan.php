<?php

namespace App\Events\Support\Budget;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventBudget;
use App\Events\Models\EventBudgetItem;
use App\Events\Models\EventCategory;
use App\Events\Models\EventFeeOption;
use App\Events\Support\BronzeRule;
use App\Events\Support\EventFee;
use App\Members\Models\User;

/**
 * An event's budget — the arithmetic, in ONE place, for every event type.
 *
 * Common across sports (CLAUDE.md → Shared Stays Shared): a fee, a turnout and
 * a list of costs mean the same thing at a karate championship and a football
 * league. The only sport-shaped input is the medal count, and that is read
 * from the event's own divisions and its own bronze rule (BronzeRule), never
 * decided here.
 *
 * Nothing in this class writes to the event except applyFee(), which is the
 * organiser's explicit "use this fee" and goes through the same fee-option
 * rows and display line the event form writes.
 */
final class BudgetPlan
{
    public static function enabled(): bool
    {
        return (bool) config('events.budget.enabled', false);
    }

    public static function for(ClubEvent $event): ?EventBudget
    {
        return EventBudget::where('event_id', $event->id)->first();
    }

    /** The event's budget, created with sensible defaults on first write. */
    public static function ensure(ClubEvent $event, ?User $by = null): EventBudget
    {
        return EventBudget::firstOrCreate(
            ['event_id' => $event->id],
            ['target_profit' => 0, 'reserve_percent' => 10, 'round_to' => 0.5, 'medals_mode' => 'entries', 'created_by' => $by?->id],
        );
    }

    /**
     * How many medals the event needs.
     *
     * `entries` counts only the places a division can actually fill: one
     * entrant takes gold alone, two take gold and silver, three add one bronze,
     * four or more add the bronzes the event's rule awards. `full` is the
     * every-division-gets-a-full-set answer an organiser orders when they do
     * not know the entries yet.
     *
     * When nobody has been placed in a division yet, `entries` has nothing to
     * count — it then reports the full set and says it is an estimate.
     */
    public static function medals(ClubEvent $event, string $mode = 'entries', ?array $sizes = null): array
    {
        return self::medalsFor($sizes ?? self::divisionSizes($event), ! BronzeRule::playsOff($event), $mode);
    }

    /**
     * How many athletes each division holds (0 for an empty one) — the one
     * input the medal count, the per-division costs and the merge/split
     * simulator all read.
     *
     * @return array<int, int>
     */
    public static function divisionSizes(ClubEvent $event): array
    {
        // Headings ("Boys 8-9") group divisions on the draw; they are not divisions.
        $divisions = EventCategory::where('event_id', $event->id)->where(fn ($q) => $q->whereNull('is_heading')->orWhere('is_heading', false))->pluck('id');

        $counts = ClubEventRegistration::query()
            ->where('event_id', $event->id)
            ->where('role', 'participant')
            ->where('status', 'joined')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as n')
            ->groupBy('category_id')
            ->pluck('n', 'category_id');

        return $divisions->map(fn ($id) => (int) ($counts[$id] ?? 0))->values()->all();
    }

    /**
     * Divisions re-shaped, to see what it does to the budget: every division
     * with fewer than `mergeBelow` athletes is folded into the next-smallest
     * until it reaches that size; every division above `splitAbove` is cut in
     * two. An approximation of what an organiser does by hand in the draw —
     * the budget only simulates it, it never touches a division.
     *
     * @param  array<int, int>  $sizes
     * @return array<int, int>
     */
    public static function reshape(array $sizes, ?int $mergeBelow = null, ?int $splitAbove = null): array
    {
        $out = array_values(array_filter($sizes, fn ($n) => $n > 0));
        $empty = count($sizes) - count($out);

        if ($splitAbove !== null && $splitAbove >= 2) {
            $split = [];
            foreach ($out as $n) {
                if ($n > $splitAbove) {
                    $split[] = intdiv($n, 2);
                    $split[] = $n - intdiv($n, 2);
                } else {
                    $split[] = $n;
                }
            }
            $out = $split;
        }

        if ($mergeBelow !== null && $mergeBelow >= 2) {
            sort($out);
            $merged = [];
            $carry = 0;
            foreach ($out as $n) {
                if ($n >= $mergeBelow && $carry === 0) {
                    $merged[] = $n;
                    continue;
                }
                $carry += $n;
                if ($carry >= $mergeBelow) {
                    $merged[] = $carry;
                    $carry = 0;
                }
            }
            if ($carry > 0) {
                // The last small group joins the smallest division that is left.
                if ($merged !== []) {
                    sort($merged);
                    $merged[0] += $carry;
                } else {
                    $merged[] = $carry;
                }
            }
            $out = $merged;
        }

        return array_merge($out, array_fill(0, $empty, 0));
    }

    /** @param array<int, int> $sizes */
    public static function medalsFor(array $sizes, bool $twoBronzes, string $mode = 'entries'): array
    {
        $full = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
        $real = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
        $spread = ['1' => 0, '2' => 0, '3' => 0, '4+' => 0];

        foreach ($sizes as $n) {
            $full['gold']++;
            $full['silver']++;
            $full['bronze'] += $twoBronzes ? 2 : 1;

            if ($n <= 0) {
                continue;
            }

            $spread[$n >= 4 ? '4+' : (string) $n]++;
            $real['gold']++;

            if ($n >= 2) {
                $real['silver']++;
            }

            if ($n === 3) {
                $real['bronze']++;
            } elseif ($n >= 4) {
                $real['bronze'] += $twoBronzes ? 2 : 1;
            }
        }

        $fullTotal = array_sum($full);
        $realTotal = array_sum($real);
        $estimated = $mode === 'entries' && $realTotal === 0;
        $use = ($mode === 'full' || $estimated) ? $full : $real;
        $running = count(array_filter($sizes, fn ($n) => $n > 0));

        return [
            'mode' => $mode,
            'estimated' => $estimated,
            'gold' => $use['gold'],
            'silver' => $use['silver'],
            'bronze' => $use['bronze'],
            'total' => array_sum($use),
            'full_total' => $fullTotal,
            'entries_total' => $realTotal,
            'divisions' => count($sizes),
            // The divisions that actually run (have someone in them) — what a
            // per-division cost is counted against, unless a full set is ordered.
            'divisions_running' => ($mode === 'full' || $estimated) ? count($sizes) : $running,
            'spread' => $spread,
            'two_bronzes' => $twoBronzes,
        ];
    }

    /**
     * What one line costs at this turnout and this many running divisions —
     * the one place the three bases are told apart.
     */
    public static function lineCost(EventBudgetItem $item, int $entrants, int $divisions): float
    {
        return round(match ($item->basis) {
            'per_entrant' => $item->amount * $entrants,
            'per_division' => $item->amount * $divisions,
            default => $item->amount,
        }, 3);
    }

    /** The multiplier a line's amount is scaled by — to turn a target line cost back into an amount. */
    public static function lineScale(EventBudgetItem $item, int $entrants, int $divisions): int
    {
        return match ($item->basis) {
            'per_entrant' => max(1, $entrants),
            'per_division' => max(1, $divisions),
            default => 1,
        };
    }

    /**
     * What merging small divisions or splitting big ones does to the budget —
     * divisions, medals by type, their cost, the per-division lines and the
     * net, before and after. A simulation only.
     */
    public static function divisionImpact(ClubEvent $event, ?EventBudget $budget, array $screen, ?int $mergeBelow, ?int $splitAbove): array
    {
        $sizes = self::divisionSizes($event);
        $reshaped = self::reshape($sizes, $mergeBelow, $splitAbove);
        $before = self::compute($event, $budget, $screen);
        $after = self::compute($event, $budget, $screen + ['division_sizes' => $reshaped]);
        $side = fn ($p) => [
            'divisions_running' => $p['medals']['divisions_running'],
            'medals' => ['gold' => $p['medals']['gold'], 'silver' => $p['medals']['silver'], 'bronze' => $p['medals']['bronze'], 'total' => $p['medals']['total']],
            'medal_cost' => $p['medals']['cost'],
            'per_division_costs' => $p['division_cost'],
            'net' => $p['net'],
        ];

        return [
            'merge_below' => $mergeBelow,
            'split_above' => $splitAbove,
            'before' => $side($before),
            'after' => $side($after),
            'net_change' => round($after['net'] - $before['net'], 3),
            'sizes_after' => array_values(array_filter($reshaped, fn ($n) => $n > 0)),
        ];
    }

    /**
     * The divisions, as an organiser reads them on the draw: grouped under
     * their headings, in order, each with its athletes and the medals it
     * uses — plus the merges worth considering: a division next to another in
     * the same group, at least one of them small, with what folding them
     * together would save.
     */
    public static function divisionList(ClubEvent $event, ?EventBudget $budget, array $screen = []): array
    {
        $rows = EventCategory::where('event_id', $event->id)->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'is_heading', 'gender', 'min_age', 'max_age', 'min_weight', 'max_weight', 'weight_class']);
        $counts = ClubEventRegistration::query()
            ->where('event_id', $event->id)->where('role', 'participant')->where('status', 'joined')->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        $twoBronzes = ! BronzeRule::playsOff($event);
        $plan = self::compute($event, $budget, $screen);
        $prices = $plan['medals']['prices'];
        $perDivision = $plan['per_division'];
        $medalsOf = fn (int $n) => self::medalsFor([$n], $twoBronzes);
        $costOf = fn (array $m) => $m['gold'] * $prices['gold'] + $m['silver'] * $prices['silver'] + $m['bronze'] * $prices['bronze'];

        $groups = [];
        $current = null;
        foreach ($rows as $row) {
            if ($row->is_heading) {
                $groups[] = ['heading' => $row->name, 'divisions' => []];
                $current = count($groups) - 1;
                continue;
            }
            if ($current === null) {
                $groups[] = ['heading' => null, 'divisions' => []];
                $current = count($groups) - 1;
            }
            $n = (int) ($counts[$row->id] ?? 0);
            $m = $medalsOf($n);
            $groups[$current]['divisions'][] = [
                'id' => $row->id,
                'name' => $row->name,
                'athletes' => $n,
                'medals' => ['gold' => $m['gold'], 'silver' => $m['silver'], 'bronze' => $m['bronze']],
                'medal_cost' => round($costOf($m), 3),
                'alone' => $n === 1,
                'small' => $n > 0 && $n <= 2,
            ];
        }
        $groups = array_values(array_filter($groups, fn ($g) => $g['divisions'] !== []));

        // Neighbours in the same group, at least one small: what merging them saves.
        $suggestions = [];
        foreach ($groups as $g) {
            $ds = $g['divisions'];
            for ($i = 0; $i < count($ds) - 1; $i++) {
                [$a, $b] = [$ds[$i], $ds[$i + 1]];
                if ($a['athletes'] === 0 || $b['athletes'] === 0 || min($a['athletes'], $b['athletes']) > 2) {
                    continue;
                }
                $after = $medalsOf($a['athletes'] + $b['athletes']);
                $saveMedals = ($a['medals']['gold'] + $a['medals']['silver'] + $a['medals']['bronze'] + $b['medals']['gold'] + $b['medals']['silver'] + $b['medals']['bronze'])
                    - ($after['gold'] + $after['silver'] + $after['bronze']);
                $saves = round($a['medal_cost'] + $b['medal_cost'] - $costOf($after) + $perDivision, 3);
                $suggestions[] = [
                    'ids' => [$a['id'], $b['id']],
                    'names' => [$a['name'], $b['name']],
                    'group' => $g['heading'],
                    'athletes' => [$a['athletes'], $b['athletes']],
                    'merged_athletes' => $a['athletes'] + $b['athletes'],
                    'medals_saved' => $saveMedals,
                    'saves' => $saves,
                ];
            }
        }
        usort($suggestions, fn ($x, $y) => [$y['saves'], $x['merged_athletes']] <=> [$x['saves'], $y['merged_athletes']]);

        $all = collect($groups)->flatMap(fn ($g) => $g['divisions']);

        return [
            'groups' => $groups,
            'counts' => [
                'divisions' => $all->count(),
                'running' => $all->where('athletes', '>', 0)->count(),
                'alone' => $all->where('alone', true)->count(),
                'small' => $all->where('small', true)->count(),
                'empty' => $all->where('athletes', 0)->count(),
            ],
            'suggestions' => array_slice($suggestions, 0, 12),
        ];
    }

    /**
     * The budget with chosen divisions merged (pairs or groups of division ids)
     * — before and after. Ids outside this event are ignored.
     *
     * @param  array<int, array<int, int>>  $merges
     */
    public static function mergeImpact(ClubEvent $event, ?EventBudget $budget, array $screen, array $merges): array
    {
        $sizesById = [];
        $ids = EventCategory::where('event_id', $event->id)->where(fn ($q) => $q->whereNull('is_heading')->orWhere('is_heading', false))->pluck('id');
        $counts = ClubEventRegistration::query()
            ->where('event_id', $event->id)->where('role', 'participant')->where('status', 'joined')->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        foreach ($ids as $id) {
            $sizesById[$id] = (int) ($counts[$id] ?? 0);
        }

        $merged = [];
        foreach ($merges as $set) {
            $set = array_values(array_unique(array_filter(array_map('intval', (array) $set), fn ($id) => isset($sizesById[$id]))));
            if (count($set) < 2) {
                continue;
            }
            $sum = 0;
            foreach ($set as $id) {
                $sum += $sizesById[$id];
                unset($sizesById[$id]);
            }
            $merged[] = $sum;
        }

        $reshaped = array_merge(array_values($sizesById), $merged);
        $before = self::compute($event, $budget, $screen);
        $after = self::compute($event, $budget, $screen + ['division_sizes' => $reshaped]);
        $side = fn ($p) => [
            'divisions_running' => $p['medals']['divisions_running'],
            'medals' => ['gold' => $p['medals']['gold'], 'silver' => $p['medals']['silver'], 'bronze' => $p['medals']['bronze'], 'total' => $p['medals']['total']],
            'medal_cost' => $p['medals']['cost'],
            'per_division_costs' => $p['division_cost'],
            'net' => $p['net'],
        ];

        return ['before' => $side($before), 'after' => $side($after), 'net_change' => round($after['net'] - $before['net'], 3), 'merged_groups' => count($merged)];
    }

    /** People entered and people paid so far — one per person, not per division entry. */
    public static function turnout(ClubEvent $event): array
    {
        $billing = EventFee::billing($event, 'participant');

        return ['entered' => $billing->count(), 'paid' => $billing->where('paid', true)->count()];
    }

    /**
     * The plan worked out. `$with` overrides the budget's own inputs for a
     * what-if (fee, entrants, medals_mode, sponsor) without saving anything.
     */
    public static function compute(ClubEvent $event, ?EventBudget $budget, array $with = []): array
    {
        $turnout = self::turnout($event);
        $items = $budget ? $budget->items()->get() : collect();

        // A what-if may re-price cost lines without saving them ([uuid => amount]).
        if (! empty($with['item_amounts']) && is_array($with['item_amounts'])) {
            $items = $items->map(function (EventBudgetItem $i) use ($with) {
                if (array_key_exists($i->uuid, $with['item_amounts']) && is_numeric($with['item_amounts'][$i->uuid])) {
                    $i = clone $i;
                    $i->amount = max(0, (float) $with['item_amounts'][$i->uuid]);
                }

                return $i;
            });
        }

        $n = max(0, (int) ($with['entrants'] ?? $budget?->expected_entrants ?? $turnout['entered']));
        $fee = max(0.0, (float) ($with['fee'] ?? $budget?->planned_fee ?? EventFee::listPrice($event, 'participant')));
        $mode = in_array($with['medals_mode'] ?? null, EventBudget::MEDALS_MODES, true) ? $with['medals_mode'] : ($budget?->medals_mode ?? 'entries');
        $sponsor = max(0.0, (float) ($with['sponsor'] ?? $budget?->sponsor_amount ?? 0));
        $target = max(0.0, (float) ($with['target'] ?? $budget?->target_profit ?? 0));
        $reserve = (float) ($budget?->reserve_percent ?? 10);
        $round = (float) ($budget?->round_to ?? 0.5);
        $unitMedal = (float) ($budget?->medal_unit_cost ?? 0);
        // Each medal type at its own price; a type left blank costs the single price.
        $prices = [
            'gold' => (float) ($with['medal_gold_cost'] ?? $budget?->medal_gold_cost ?? $unitMedal),
            'silver' => (float) ($with['medal_silver_cost'] ?? $budget?->medal_silver_cost ?? $unitMedal),
            'bronze' => (float) ($with['medal_bronze_cost'] ?? $budget?->medal_bronze_cost ?? $unitMedal),
        ];

        // A what-if may re-shape the divisions (merge small ones, split big ones).
        $sizes = (isset($with['division_sizes']) && is_array($with['division_sizes'])) ? array_map('intval', $with['division_sizes']) : null;
        $medals = self::medals($event, $mode, $sizes);
        $medalCost = round($medals['gold'] * $prices['gold'] + $medals['silver'] * $prices['silver'] + $medals['bronze'] * $prices['bronze'], 3);

        $fixed = (float) $items->where('basis', 'fixed')->sum('amount');
        $perEntrant = (float) $items->where('basis', 'per_entrant')->sum('amount');
        // Per-division lines (a trophy per division, certificates per division…)
        // count against the divisions that run — merging divisions moves them too.
        $perDivision = (float) $items->where('basis', 'per_division')->sum('amount');
        $divisionCost = round($perDivision * $medals['divisions_running'], 3);
        $fixed += $divisionCost;

        $costs = $fixed + $perEntrant * $n + $medalCost;
        $covered = min($sponsor, $costs);
        $expenses = round($costs - $covered, 3);
        $revenue = round($fee * $n, 3);
        $net = round($revenue - $expenses, 3);

        $margin = $fee - $perEntrant;
        $needed = $margin > 0 ? (int) ceil(max(0, $fixed + $medalCost - $covered) / $margin) : null;
        $breakEven = $n > 0 ? round($expenses / $n, 3) : null;
        $suggested = $n > 0 ? self::roundUp(($expenses * (1 + $reserve / 100) + $target) / $n, $round) : null;

        $status = $net < 0 ? 'loss' : (($target > 0 && $net < $target) ? 'thin' : 'profitable');

        // Where the money goes — the item categories, with medals counted under awards.
        $byCategory = array_fill_keys(EventBudgetItem::CATEGORIES, 0.0);
        foreach ($items as $item) {
            $byCategory[$item->category] = ($byCategory[$item->category] ?? 0) + match ($item->basis) {
                'per_entrant' => $item->amount * $n,
                'per_division' => $item->amount * $medals['divisions_running'],
                default => $item->amount,
            };
        }
        $byCategory['awards'] += $medalCost;

        return [
            'fee' => round($fee, 3),
            'entrants' => $n,
            'medals_mode' => $mode,
            'sponsor' => round($sponsor, 3),
            'medals' => $medals + ['unit_cost' => $unitMedal, 'prices' => $prices, 'cost' => $medalCost, 'cost_by_type' => [
                'gold' => round($medals['gold'] * $prices['gold'], 3), 'silver' => round($medals['silver'] * $prices['silver'], 3), 'bronze' => round($medals['bronze'] * $prices['bronze'], 3),
            ]],
            'per_division' => round($perDivision, 3),
            'division_cost' => $divisionCost,
            'fixed' => round($fixed, 3),
            'per_entrant' => round($perEntrant, 3),
            'costs' => round($costs, 3),
            'covered' => round($covered, 3),
            'expenses' => $expenses,
            'revenue' => $revenue,
            'net' => $net,
            'target' => $target,
            'break_even_fee' => $breakEven,
            'athletes_needed' => $needed,
            'suggested_fee' => $suggested,
            'status' => $status,
            'by_category' => array_map(fn ($v) => round($v, 3), $byCategory),
        ];
    }

    /**
     * The people side of the budget: who is planned (per role, paid vs
     * volunteer) beside who has actually been appointed as an official.
     */
    public static function people(ClubEvent $event, ?EventBudget $budget): array
    {
        $lines = $budget ? $budget->items()->where('category', 'people')->whereNotNull('quantity')->get() : collect();
        $planned = $lines->groupBy(fn ($i) => $i->role ?: 'other')->map(fn ($g) => [
            'people' => (int) $g->sum('quantity'),
            'cost' => round((float) $g->sum('amount'), 3),
        ])->all();

        $officials = \App\Events\Models\EventOfficial::where('event_id', $event->id)->get(['role', 'compensation', 'fee']);

        return [
            'planned' => $planned,
            'headcount' => (int) $lines->sum('quantity'),
            'paid' => (int) $lines->filter(fn ($i) => (float) $i->unit_cost > 0 && $i->role !== 'volunteer')->sum('quantity'),
            'volunteers' => (int) $lines->filter(fn ($i) => $i->role === 'volunteer')->sum('quantity'),
            'cost' => round((float) $lines->sum('amount'), 3),
            // Who has really been appointed (Event console › Officials).
            'appointed' => [
                'total' => $officials->count(),
                'paid' => $officials->where('compensation', 'paid')->count(),
                'volunteers' => $officials->where('compensation', '!=', 'paid')->count(),
                'pay' => round((float) $officials->where('compensation', 'paid')->sum('fee'), 3),
            ],
        ];
    }

    /** Everything the budget page needs, in one answer. */
    public static function payload(ClubEvent $event, ?EventBudget $budget): array
    {
        $finance = app(EventTypeRegistry::class)->for($event)->finance($event);

        return [
            'event' => $event->uuid,
            'currency' => EventFee::currency($event),
            'exists' => $budget !== null,
            'settings' => [
                'planned_fee' => $budget?->planned_fee,
                'expected_entrants' => $budget?->expected_entrants,
                'target_profit' => (float) ($budget?->target_profit ?? 0),
                'reserve_percent' => (float) ($budget?->reserve_percent ?? 10),
                'round_to' => (float) ($budget?->round_to ?? 0.5),
                'medal_unit_cost' => (float) ($budget?->medal_unit_cost ?? 0),
                'medal_gold_cost' => $budget?->medal_gold_cost !== null ? (float) $budget->medal_gold_cost : null,
                'medal_silver_cost' => $budget?->medal_silver_cost !== null ? (float) $budget->medal_silver_cost : null,
                'medal_bronze_cost' => $budget?->medal_bronze_cost !== null ? (float) $budget->medal_bronze_cost : null,
                'medals_mode' => $budget?->medals_mode ?? 'entries',
                'sponsor_amount' => (float) ($budget?->sponsor_amount ?? 0),
            ],
            'list_price' => EventFee::listPrice($event, 'participant'),
            'turnout' => self::turnout($event),
            // Both medal counts, so the page can move between them live.
            'medal_options' => ['entries' => self::medals($event, 'entries'), 'full' => self::medals($event, 'full')],
            'division_sizes' => self::divisionSizes($event),
            'plan' => self::compute($event, $budget),
            'items' => $budget
                ? $budget->items()->get()->map(fn (EventBudgetItem $i) => [
                    'uuid' => $i->uuid, 'category' => $i->category, 'label' => $i->label,
                    'amount' => (float) $i->amount, 'basis' => $i->basis, 'status' => $i->status,
                    'role' => $i->role, 'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost !== null ? (float) $i->unit_cost : null,
                ])->values()->all()
                : [],
            'people' => self::people($event, $budget),
            'scenarios' => $budget
                ? $budget->scenarios()->get()->map(fn ($s) => [
                    'uuid' => $s->uuid, 'name' => $s->name, 'fee' => (float) $s->fee, 'entrants' => (int) $s->entrants,
                    'medals_mode' => $s->medals_mode, 'sponsor' => (float) $s->sponsor_amount, 'net' => (float) $s->net,
                    'approved' => (int) $budget->approved_scenario_id === (int) $s->id,
                    // Recomputed against today's costs — the saved `net` is history.
                    'now' => self::compute($event, $budget, ['fee' => $s->fee, 'entrants' => $s->entrants, 'medals_mode' => $s->medals_mode, 'sponsor' => $s->sponsor_amount])['net'],
                ])->values()->all()
                : [],
            'approved_at' => $budget?->approved_at?->toIso8601String(),
            'actual' => [
                'revenue' => (float) ($finance['revenue'] ?? 0),
                'expenses' => (float) ($finance['expenses_total'] ?? 0),
                'profit' => (float) ($finance['profit'] ?? 0),
                'paid' => (int) ($finance['paid_participants'] ?? 0),
            ],
        ];
    }

    /**
     * The organiser's "use this fee": sets the event's first entry fee type to
     * it (or creates the Joining fee row when the event has none), then
     * rewrites the display line exactly as the event form does. Entries
     * already taken keep the fee they were charged — their lines are frozen.
     */
    public static function applyFee(ClubEvent $event, float $fee): void
    {
        $fee = round(max(0, $fee), 3);

        $option = EventFeeOption::where('event_id', $event->id)
            ->where('role', 'participant')
            ->where('is_active', true)
            ->orderBy('sort')->orderBy('id')
            ->first();

        if ($option) {
            $option->update(['amount' => $fee]);
        } else {
            EventFeeOption::create([
                'event_id' => $event->id, 'role' => 'participant',
                'label' => __('events::events.fee_line_joining'), 'amount' => $fee,
                'is_active' => true, 'sort' => 0,
            ]);
        }

        EventFee::refreshDisplay($event->fresh());
    }

    /**
     * The create form's hint: the most recent budget the user planned on
     * another event they manage — its cost total, as a yardstick. Null when
     * they have never planned one.
     */
    public static function yardstick(User $user): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $access = app(\App\Events\Support\EventAccess::class);

        $budgets = EventBudget::with('event')
            ->whereHas('items')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        foreach ($budgets as $budget) {
            if (! $budget->event || ! $access->canManage($budget->event, $user)) {
                continue;
            }

            $plan = self::compute($budget->event, $budget);

            if ($plan['expenses'] <= 0) {
                continue;
            }

            return ['title' => $budget->event->title, 'expenses' => $plan['expenses'], 'currency' => EventFee::currency($budget->event)];
        }

        return null;
    }

    public static function roundUp(float $value, float $step): float
    {
        if ($step <= 0) {
            return round($value, 3);
        }

        return round(ceil(round($value / $step, 6)) * $step, 3);
    }
}
