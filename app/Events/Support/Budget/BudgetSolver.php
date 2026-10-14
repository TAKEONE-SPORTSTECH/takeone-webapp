<?php

namespace App\Events\Support\Budget;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventBudget;
use App\Events\Models\EventBudgetItem;

/**
 * The advisor's brain — deterministic, no model.
 *
 * Given what is on the organiser's sliders, the goal and the limits they set
 * in conversation ("keep the fee", "no sponsor", "don't touch the referees"),
 * it tries every lever the budget has — the fee, the turnout, the medal rule,
 * a sponsor, trimming cost lines, and mixes of them — works each one out with
 * BudgetPlan::compute(), and picks the plan that reaches the goal with the
 * least disruption. The language model only explains the result.
 *
 * Disruption, roughly: raising the fee by 10 % costs 10; finding 10 % more
 * athletes costs 15 (people are harder to find than prices are to change); a
 * sponsor costs 13 per 10 % of costs (it may not come); trimming every line by
 * 10 % costs 11; the medal rule is free.
 */
final class BudgetSolver
{
    /** How deep a trim across all lines may go before it stops being a plan. */
    private const MAX_TRIM = 0.30;

    /** How much of a single line a cut may take. */
    private const MAX_LINE_CUT = 0.50;

    /**
     * @param  array  $screen  BudgetAdvisor::screen() — the sliders
     * @param  array  $limits  BudgetAdvisor::understand() — goal + constraints
     * @return array{goal: float, already: bool, best: ?array, alternatives: array, compromise: ?array, base: array}
     */
    public static function solve(ClubEvent $event, ?EventBudget $budget, array $screen, array $limits): array
    {
        $base = BudgetPlan::compute($event, $budget, $screen);
        $round = max(0.1, (float) ($budget?->round_to ?: 0.5));
        $goal = $limits['target_profit'] ?? null;
        $goal = $goal !== null ? (float) $goal : ($base['target'] > 0 ? $base['target'] : 0.0);

        $out = ['goal' => $goal, 'already' => $base['net'] >= $goal, 'best' => null, 'alternatives' => [], 'compromise' => null, 'base' => $base];

        if ($out['already'] || ! $budget) {
            return $out;
        }

        $items = $budget->items()->get();
        $protected = array_map('mb_strtolower', $limits['protect'] ?? []);
        // A line already PAID is spent money — it is never offered for cutting.
        $cuttable = $items->filter(fn (EventBudgetItem $i) => $i->status !== 'paid' && ! self::isProtected($i, $protected));

        $candidates = self::candidates($event, $budget, $screen, $base, $goal, $round, $cuttable, $limits, false);
        $meets = array_values(array_filter($candidates, fn ($c) => $c['meets']));
        usort($meets, fn ($a, $b) => $a['score'] <=> $b['score']);

        if ($meets !== []) {
            $out['best'] = $meets[0];
            $seen = [$meets[0]['kind'] => true];
            foreach (array_slice($meets, 1) as $c) {
                if (! isset($seen[$c['kind']]) && count($out['alternatives']) < 2) {
                    $out['alternatives'][] = $c;
                    $seen[$c['kind']] = true;
                }
            }

            return $out;
        }

        // Nothing fits inside the limits. First keep every limit they SAID and only
        // lift the solver's own caps (a deeper trim than 30 %); only if that still
        // fails, the smallest step outside their limits — said honestly either way.
        foreach ([[$limits, $cuttable], [[], $items]] as [$l, $pool]) {
            $loose = self::candidates($event, $budget, $screen, $base, $goal, $round, $pool, $l, true);
            $loose = array_values(array_filter($loose, fn ($c) => $c['meets']));
            usort($loose, fn ($a, $b) => $a['score'] <=> $b['score']);
            if ($loose !== []) {
                $out['compromise'] = $loose[0] + ['within_limits' => $l !== []];
                break;
            }
        }

        return $out;
    }

    /**
     * Ways to SAVE — asked for directly ("how can I cut costs?"), whether or
     * not the target is already met. Each option is worked out on its own;
     * the proposal is the gentlest one that still saves something real.
     *
     * @return array{options: array, proposal: ?array}
     */
    public static function savings(ClubEvent $event, ?EventBudget $budget, array $screen, array $limits): array
    {
        $base = BudgetPlan::compute($event, $budget, $screen);
        $options = [];

        if (! $budget) {
            return ['options' => [], 'proposal' => null, 'base' => $base];
        }

        if ($base['medals_mode'] === 'full') {
            $p = self::plan('medals', 'order medals to the entries, not a full set', $event, $budget, $screen, ['medals_mode' => 'entries'], [], $base, $base['target'], 0);
            if ($p['net'] > $base['net']) {
                $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3)];
            }
        }

        if (empty($limits['no_cuts'])) {
            $protected = array_map('mb_strtolower', $limits['protect'] ?? []);
            $n = max(1, $base['entrants']);
            $lines = $budget->items()->get()
                ->filter(fn (EventBudgetItem $i) => $i->status !== 'paid' && ! self::isProtected($i, $protected))
                ->sortByDesc(fn ($i) => BudgetPlan::lineCost($i, $n, $base['medals']['divisions_running']))
                ->take(3);

            foreach ($lines as $item) {
                $p = self::plan('cut_line', 'cut "'.$item->label.'" by 20%', $event, $budget, $screen, [], [$item->uuid => self::down($item->amount * 0.8)], $base, $base['target'], 20);
                $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3)];
            }

            // People: one fewer of a paid role, each paid 10% less, or paid people
            // swapped for volunteers (who cost only what a volunteer line says).
            $people = $budget->items()->where('category', 'people')->whereNotNull('quantity')->get()
                ->filter(fn (EventBudgetItem $i) => $i->status !== 'paid' && ! self::isProtected($i, $protected));
            $volunteerEach = (float) ($people->firstWhere('role', 'volunteer')?->unit_cost ?? 0);
            foreach ($people->filter(fn ($i) => $i->role !== 'volunteer' && (float) $i->unit_cost > 0) as $item) {
                if ($item->quantity >= 2) {
                    $p = self::plan('people_fewer', 'one fewer '.mb_strtolower($item->label).' ('.($item->quantity - 1).' instead of '.$item->quantity.')', $event, $budget, $screen, [], [$item->uuid => round(($item->quantity - 1) * $item->unit_cost, 3)], $base, $base['target'], 8);
                    $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3), 'people' => [$item->uuid => ['quantity' => $item->quantity - 1, 'unit_cost' => (float) $item->unit_cost]]];
                }
                $u = self::down($item->unit_cost * 0.9);
                $p = self::plan('people_rate', 'pay '.mb_strtolower($item->label).' 10% less ('.$u.' each)', $event, $budget, $screen, [], [$item->uuid => round($item->quantity * $u, 3)], $base, $base['target'], 10);
                $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3), 'people' => [$item->uuid => ['quantity' => $item->quantity, 'unit_cost' => $u]]];
                if (in_array($item->role, ['organiser', 'other'], true) && $item->quantity >= 2) {
                    $swap = (int) floor($item->quantity / 2);
                    $saves = round($swap * ((float) $item->unit_cost - $volunteerEach), 3);
                    if ($saves > 0) {
                        $p = self::plan('volunteers', 'swap '.$swap.' paid '.mb_strtolower($item->label).' for volunteers', $event, $budget, $screen, [], [$item->uuid => round(($item->quantity - $swap) * $item->unit_cost + $swap * $volunteerEach, 3)], $base, $base['target'], 12);
                        $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3), 'people' => [$item->uuid => ['quantity' => $item->quantity - $swap, 'unit_cost' => (float) $item->unit_cost, 'volunteers_added' => $swap]]];
                    }
                }
            }

            $pool = $budget->items()->get()->filter(fn (EventBudgetItem $i) => $i->status !== 'paid' && ! self::isProtected($i, $protected));
            if ($pool->count() > 1) {
                $p = self::plan('trim', 'trim every unpaid cost line by 10%', $event, $budget, $screen, [], $pool->mapWithKeys(fn ($i) => [$i->uuid => self::down($i->amount * 0.9)])->all(), $base, $base['target'], 11);
                $options[] = $p + ['saves' => round($p['net'] - $base['net'], 3)];
            }
        }

        usort($options, fn ($a, $b) => $b['saves'] <=> $a['saves']);

        // The proposal: the medal rule if it is there (free), else the even 10 % trim.
        $proposal = collect($options)->firstWhere('kind', 'medals') ?? collect($options)->firstWhere('kind', 'trim') ?? ($options[0] ?? null);

        return ['options' => $options, 'proposal' => $proposal, 'base' => $base];
    }

    /**
     * Ways to make MORE — for an event already on target, or "how do I make
     * more profit?": small fee steps, a little more turnout, and the savings.
     */
    public static function improvements(ClubEvent $event, ?EventBudget $budget, array $screen, array $limits): array
    {
        $base = BudgetPlan::compute($event, $budget, $screen);
        $round = max(0.1, (float) ($budget?->round_to ?: 0.5));
        $options = [];

        if (empty($limits['keep_fee'])) {
            foreach ([1, 2] as $steps) {
                $f = round($base['fee'] + $steps * $round, 3);
                $p = self::plan('fee', 'raise the fee to '.$f, $event, $budget, $screen, ['fee' => $f], [], $base, $base['target'], $steps * 5);
                $options[] = $p + ['gains' => round($p['net'] - $base['net'], 3)];
            }
        }
        if (empty($limits['athletes_fixed'])) {
            $n = (int) ceil($base['entrants'] * 1.1);
            $p = self::plan('athletes', '10% more athletes ('.$n.')', $event, $budget, $screen, ['entrants' => $n], [], $base, $base['target'], 15);
            $options[] = $p + ['gains' => round($p['net'] - $base['net'], 3)];
        }
        foreach (self::savings($event, $budget, $screen, $limits)['options'] as $o) {
            $options[] = $o + ['gains' => $o['saves']];
        }

        usort($options, fn ($a, $b) => $b['gains'] <=> $a['gains']);

        return ['options' => array_slice($options, 0, 5), 'proposal' => null, 'base' => $base];
    }

    /** The lowest fee that still meets the target (or breaks even with no target). */
    public static function lowestFee(ClubEvent $event, ?EventBudget $budget, array $screen, ?float $goal = null): array
    {
        $base = BudgetPlan::compute($event, $budget, $screen);
        $round = max(0.1, (float) ($budget?->round_to ?: 0.5));
        $goal ??= $base['target'];
        $n = max(1, $base['entrants']);

        $f = BudgetPlan::roundUp(($base['expenses'] + $goal) / $n, $round);
        $breakEven = BudgetPlan::roundUp($base['expenses'] / $n, $round);

        return [
            'plan' => self::plan('lowest_fee', 'the lowest fee that meets the target', $event, $budget, $screen, ['fee' => $f], [], $base, $goal, 0),
            'break_even_fee' => $breakEven,
            'goal' => $goal,
            'base' => $base,
        ];
    }

    /** A what-if: exactly the change asked for, worked out. */
    public static function whatIf(ClubEvent $event, ?EventBudget $budget, array $screen, array $what): array
    {
        $base = BudgetPlan::compute($event, $budget, $screen);
        $with = $screen;

        if (isset($what['fee'])) {
            $with['fee'] = max(0, (float) $what['fee']);
        }
        if (isset($what['athletes'])) {
            $with['entrants'] = max(0, (int) $what['athletes']);
        } elseif (isset($what['athletes_change_pct'])) {
            $with['entrants'] = max(0, (int) round($base['entrants'] * (1 + (float) $what['athletes_change_pct'] / 100)));
        }
        if (in_array($what['medals_mode'] ?? null, ['entries', 'full'], true)) {
            $with['medals_mode'] = $what['medals_mode'];
        }
        if (isset($what['sponsor'])) {
            $with['sponsor'] = max(0, (float) $what['sponsor']);
        }

        return self::plan('what_if', 'what if', $event, $budget, $screen, $with, [], $base, $base['target'], 0);
    }

    private static function candidates(ClubEvent $event, EventBudget $budget, array $screen, array $base, float $goal, float $round, $cuttable, array $limits, bool $loose): array
    {
        $c = [];
        $fee = $base['fee'];
        $n = max(1, $base['entrants']);
        $per = $base['per_entrant'];
        $gap = $goal - $base['net'];
        $costs = max(1, $base['expenses']);

        $mayFee = empty($limits['keep_fee']);
        $mayAthletes = empty($limits['athletes_fixed']);
        $maySponsor = empty($limits['no_sponsor']);
        $mayCut = empty($limits['no_cuts']);
        $maxFee = isset($limits['max_fee']) ? (float) $limits['max_fee'] : null;
        $maxAthletes = isset($limits['max_athletes']) ? (int) $limits['max_athletes'] : null;

        // The medal rule first: ordering only what divisions can award is free money.
        $medalBase = [];
        if ($base['medals_mode'] === 'full') {
            $entries = BudgetPlan::compute($event, $budget, ['medals_mode' => 'entries'] + $screen);
            if ($entries['expenses'] < $base['expenses']) {
                $medalBase = ['medals_mode' => 'entries'];
                $c[] = self::plan('medals', 'order medals to the entries, not a full set', $event, $budget, $screen, $medalBase, [], $base, $goal, 0);
            }
        }
        $start = BudgetPlan::compute($event, $budget, $medalBase + $screen);
        $gap = $goal - $start['net'];
        $fixedish = $start['fixed'] + $start['medals']['cost'] - $start['covered'];

        // The fee alone.
        if ($mayFee) {
            $f = BudgetPlan::roundUp(($start['expenses'] + $goal) / $n, $round);
            if ($maxFee === null || $f <= $maxFee) {
                $c[] = self::plan('fee', 'raise the fee', $event, $budget, $screen, $medalBase + ['fee' => $f], [], $base, $goal, ($f - $fee) / max($fee, 1) * 100);
            }
        }

        // The turnout alone.
        if ($mayAthletes && ($fee - $per) > 0) {
            $need = (int) ceil(max(0, $fixedish + $goal) / ($fee - $per));
            if ($maxAthletes === null || $need <= $maxAthletes) {
                $c[] = self::plan('athletes', 'more paying athletes', $event, $budget, $screen, $medalBase + ['entrants' => $need], [], $base, $goal, ($need - $n) / $n * 100 * 1.5);
            }
        }

        // A sponsor.
        if ($maySponsor) {
            $s = round((float) ($screen['sponsor'] ?? $budget->sponsor_amount ?? 0) + $gap, 3);
            $c[] = self::plan('sponsor', 'a sponsor covering costs', $event, $budget, $screen, $medalBase + ['sponsor' => $s], [], $base, $goal, $gap / $costs * 100 * 1.3);
        }

        if ($mayCut && $cuttable->isNotEmpty()) {
            // One line taking the whole cut — only if it is not gutted doing so.
            foreach ($cuttable as $item) {
                $cost = BudgetPlan::lineCost($item, $n, $start['medals']['divisions_running']);
                if ($cost > 0 && $gap <= $cost * ($loose ? 1 : self::MAX_LINE_CUT)) {
                    $newCost = $cost - $gap;
                    $amount = self::down($newCost / BudgetPlan::lineScale($item, $n, $start['medals']['divisions_running']));
                    $c[] = self::plan('cut_line', 'cut "'.$item->label.'"', $event, $budget, $screen, $medalBase, [$item->uuid => $amount], $base, $goal, $gap / $cost * 100 * 1.2);
                }
            }

            // A trim across every line it may touch.
            $pool = $cuttable->sum(fn ($i) => BudgetPlan::lineCost($i, $n, $start['medals']['divisions_running']));
            if ($pool > 0) {
                $p = $gap / $pool;
                if ($p <= ($loose ? 1 : self::MAX_TRIM)) {
                    $amounts = $cuttable->mapWithKeys(fn ($i) => [$i->uuid => self::down($i->amount * (1 - $p))])->all();
                    $c[] = self::plan('trim', 'trim every cost line by '.round($p * 100).'%', $event, $budget, $screen, $medalBase, $amounts, $base, $goal, $p * 100 * 1.1);
                }
            }
        }

        // Mixes: a modest fee rise and the athletes still needed.
        if ($mayFee && $mayAthletes) {
            foreach ([1, 2] as $steps) {
                $f = round($fee + $steps * $round, 3);
                if (($f - $per) <= 0 || ($maxFee !== null && $f > $maxFee)) {
                    continue;
                }
                $need = (int) ceil(max(0, $fixedish + $goal) / ($f - $per));
                if ($maxAthletes !== null && $need > $maxAthletes) {
                    continue;
                }
                $score = ($f - $fee) / max($fee, 1) * 100 + max(0, $need - $n) / $n * 100 * 1.5;
                $c[] = self::plan('fee_athletes', 'a small fee rise plus more athletes', $event, $budget, $screen, $medalBase + ['fee' => $f, 'entrants' => max($n, $need)], [], $base, $goal, $score);
            }
        }

        // Mix: a modest fee rise and a light trim for the rest.
        if ($mayFee && $mayCut && $cuttable->isNotEmpty()) {
            $f = round($fee + $round, 3);
            if ($maxFee === null || $f <= $maxFee) {
                $after = BudgetPlan::compute($event, $budget, $medalBase + ['fee' => $f] + $screen);
                $rest = $goal - $after['net'];
                $pool = $cuttable->sum(fn ($i) => BudgetPlan::lineCost($i, $n, $start['medals']['divisions_running']));
                if ($rest > 0 && $pool > 0 && $rest / $pool <= self::MAX_TRIM) {
                    $p = $rest / $pool;
                    $amounts = $cuttable->mapWithKeys(fn ($i) => [$i->uuid => self::down($i->amount * (1 - $p))])->all();
                    $c[] = self::plan('fee_trim', 'a small fee rise plus a '.round($p * 100).'% trim', $event, $budget, $screen, $medalBase + ['fee' => $f], $amounts, $base, $goal, ($f - $fee) / max($fee, 1) * 100 + $p * 100 * 1.1);
                }
            }
        }

        return $c;
    }

    /** One worked-out plan: its overrides, its result, and what moved. */
    private static function plan(string $kind, string $label, ClubEvent $event, EventBudget|null $budget, array $screen, array $with, array $itemAmounts, array $base, float $goal, float $score): array
    {
        $result = BudgetPlan::compute($event, $budget, $with + $screen + ['item_amounts' => $itemAmounts]);

        $changes = [];
        foreach (['fee' => $base['fee'], 'entrants' => $base['entrants'], 'medals_mode' => $base['medals_mode'], 'sponsor' => $base['sponsor']] as $k => $was) {
            $to = $k === 'entrants' ? $result['entrants'] : ($k === 'fee' ? $result['fee'] : ($k === 'medals_mode' ? $result['medals_mode'] : $result['sponsor']));
            if ((string) $to !== (string) $was) {
                $changes[] = ['field' => $k, 'from' => $was, 'to' => $to];
            }
        }

        if ($itemAmounts !== [] && $budget) {
            foreach ($budget->items()->whereIn('uuid', array_keys($itemAmounts))->get() as $item) {
                if (abs((float) $item->amount - (float) $itemAmounts[$item->uuid]) > 0.0005) {
                    $changes[] = ['field' => 'item', 'uuid' => $item->uuid, 'label' => $item->label, 'from' => (float) $item->amount, 'to' => (float) $itemAmounts[$item->uuid]];
                }
            }
        }

        return [
            'kind' => $kind,
            'label' => $label,
            'fee' => $result['fee'],
            'entrants' => $result['entrants'],
            'medals_mode' => $result['medals_mode'],
            'sponsor' => $result['sponsor'],
            'item_amounts' => $itemAmounts,
            'changes' => $changes,
            'net' => $result['net'],
            'net_before' => $base['net'],
            // A fils of rounding is not a miss.
            'meets' => $result['net'] >= $goal - 0.01,
            'score' => round($score, 3),
        ];
    }

    /** Cuts round DOWN to the fils, so rounding can never leave a plan just short. */
    private static function down(float $x): float
    {
        return max(0, floor($x * 1000) / 1000);
    }

    private static function isProtected(EventBudgetItem $item, array $protected): bool
    {
        $label = mb_strtolower($item->label);
        foreach ($protected as $p) {
            if ($p !== '' && (str_contains($label, $p) || str_contains($p, $label) || $item->category === $p)) {
                return true;
            }
        }

        return false;
    }
}
