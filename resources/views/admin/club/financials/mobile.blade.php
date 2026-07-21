@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_financials'))

@section('club-admin-content')
@php
    $cur = $currency ?? ($club->currency ?: '');
    $today = date('Y-m-d');
    $currentMonth = date('Y-m');   // ledger + hero default to this month; the hero stepper changes it
    $shopOrders = $shopOrders ?? collect();   // order (ref => Order w/ items) for shop-sale line items
    $counts = [
        'all'     => $transactions->count() + $pendingSubscriptions->count(),
        'pending' => $pendingSubscriptions->count(),
        'income'  => $transactions->where('type', 'income')->count(),
        'expense' => $transactions->where('type', 'expense')->count(),
        'refund'  => $transactions->where('type', 'refund')->count(),
    ];

    // Pending (unpaid) subscriptions surfaced as ledger entries — interleaved with transactions
    // by date below rather than grouped together, matching the desktop chronological ledger.
    $pendingLedger = $pendingSubscriptions->map(function ($sub) use ($cur, $club) {
        $name = $sub->user->full_name ?? $sub->user->name ?? __('admin.fin_member');
        $amt  = (float) ($sub->amount_due ?? 0);
        return [
            'id'              => 'pending-' . $sub->id,
            'type'            => 'pending',
            'label'           => $name,
            'member'          => '',
            'items'           => [],
            'items_summary'   => '',
            'month'           => $sub->start_date ? \Illuminate\Support\Carbon::parse($sub->start_date)->format('Y-m') : '',
            'amount'          => $amt,
            'amount_fmt'      => $cur . ' ' . number_format($amt, 2),
            'date_label'      => $sub->package?->name ?? __('admin.fin_awaiting'),
            'pm_label'        => '',
            'search'          => mb_strtolower(trim($name . ' ' . ($sub->package?->name ?? '') . ' pending ' . __('admin.fin_awaiting') . ' ' . number_format($amt, 2))),
            'refundable'      => false,
            'subscription_id' => $sub->id,
            'member_url'      => $sub->user?->uuid ? route('member.show', $sub->user->uuid) : route('admin.club.members', $club->slug),
            'sort_date'       => $sub->start_date ? \Illuminate\Support\Carbon::parse($sub->start_date) : null,
        ];
    })->values();

    $txLedger = $transactions->map(function ($t) use ($cur, $shopOrders) {
        $sub        = $t->subscription;
        $refundable = $t->type === 'income' && $sub && $sub->payment_status === 'paid';
        $label      = $t->description ?: ucfirst($t->category ?? $t->type);
        $payer      = $sub && $sub->user
            ? ($sub->user->full_name ?? $sub->user->name)
            : ($t->user->full_name ?? $t->user->name ?? '');

        // What was actually sold — resolve the linked shop order and expose its line-item
        // snapshots (name/variant/qty/total) so the ledger shows products, not just the code.
        $order    = $t->reference_number ? $shopOrders->get($t->reference_number) : null;
        $items    = $order ? $order->items->map(fn ($it) => [
            'name'       => $it->name ?: __('admin.fin_item'),
            'variant'    => $it->variant_label ?: trim(implode(' · ', array_filter([$it->brand, $it->color, $it->size]))),
            'qty'        => (int) $it->qty,
            'line_total' => $cur . ' ' . number_format((float) $it->line_total, 2),
        ])->values()->all() : [];
        $itemsSummary = $order
            ? $order->items->map(fn ($it) => ($it->name ?: __('admin.fin_item')) . ' ×' . (int) $it->qty)->implode(' · ')
            : '';
        $pmKey      = $t->payment_method ? ($t->payment_method === 'bank_transfer' ? 'bank' : $t->payment_method) : '';
        $dateLabel  = $t->transaction_date ? $t->transaction_date->locale(app()->getLocale())->isoFormat('D MMM YYYY') : '';
        $search     = mb_strtolower(trim(implode(' ', array_filter([
            $label, $payer, $itemsSummary, $t->category, $t->type, $t->reference_number,
            number_format((float) $t->amount, 2), $dateLabel,
        ]))));

        return [
            'id'               => $t->id,
            'type'             => $t->type,
            'label'            => $label,
            'member'           => $payer,
            'month'            => $t->transaction_date ? $t->transaction_date->format('Y-m') : '',
            'amount'           => (float) $t->amount,
            'amount_fmt'       => $cur . ' ' . number_format((float) $t->amount, 2),
            'date_label'       => $dateLabel,
            'pm_label'         => $pmKey ? __('admin.fin_pm_' . $pmKey) : '',
            'search'           => $search,
            // shop-sale line items (empty for everything else)
            'items'            => $items,
            'items_summary'    => $itemsSummary,
            // raw fields for the edit sheet
            'description'      => $t->description ?? '',
            'transaction_date' => $t->transaction_date?->format('Y-m-d'),
            'category'         => $t->category ?? '',
            'payment_method'   => $t->payment_method ?? 'cash',
            'reference_number' => $t->reference_number ?? '',
            // refund + delete
            'refundable'       => $refundable,
            'subscription_id'  => $sub?->id,
            'amount_paid'      => $refundable ? (float) $sub->amount_paid : 0,
            'ref'              => $t->reference_number ?: $label,
            'sort_date'        => $t->transaction_date,
        ];
    })->values();

    // Single date-sorted ledger — pending rows are interleaved with transactions by date rather
    // than grouped together, matching the desktop ledger exactly.
    $ledger = $pendingLedger->concat($txLedger)
        ->sortByDesc(fn ($row) => $row['sort_date'] ?? \Illuminate\Support\Carbon::minValue())
        ->map(function ($row) { unset($row['sort_date']); return $row; })
        ->values();

    $netVal      = (float) ($summary['net_profit'] ?? 0);
    $incomeVal   = (float) ($summary['total_income'] ?? 0);
    $expensesVal = (float) ($summary['total_expenses'] ?? 0);
    $refundsVal  = (float) ($summary['refunds'] ?? 0);
    $collectVal  = (float) ($summary['pending'] ?? 0);

    // Income/expense split bar in the hero.
    $flowTotal   = $incomeVal + $expensesVal;
    $incomeShare = $flowTotal > 0 ? round($incomeVal / $flowTotal * 100, 1) : 0;

    // ── Trends: 12-month bars, scaled to the biggest single value in the year ──
    $monthly   = collect($monthlyData ?? []);
    $peak      = max(1, (float) $monthly->flatMap(fn ($m) => [$m['income'] ?? 0, $m['expenses'] ?? 0])->max());
    $trendData = $monthly->map(fn ($m) => [
        'month'    => $m['month'] ?? '',
        'income'   => (float) ($m['income'] ?? 0),
        'expenses' => (float) ($m['expenses'] ?? 0),
        'profit'   => (float) ($m['profit'] ?? 0),
        'collect'  => (float) ($m['cash_to_collect'] ?? 0),
        'ih'       => (int) round(((float) ($m['income'] ?? 0)) / $peak * 100),
        'eh'       => (int) round(((float) ($m['expenses'] ?? 0)) / $peak * 100),
    ])->values();

    // ── Hero sparkline: 12-month revenue curve (server-rendered SVG, no JS/library) —
    //    same technique as the dashboard's dashSpark, mapped into a 0..100 × 0..30 box. ──
    $sparkVals = $monthly->map(fn ($m) => (float) ($m['income'] ?? 0))->values();
    $sparkN    = max($sparkVals->count(), 1);
    $sparkPeak = max($sparkVals->max() ?: 0, 1);
    $sparkPts  = [];
    foreach ($sparkVals as $i => $v) {
        $x = $sparkN > 1 ? round($i / ($sparkN - 1) * 100, 2) : 0;
        $y = round(30 - ($v / $sparkPeak) * 26 - 2, 2);
        $sparkPts[] = "$x,$y";
    }
    $sparkLine = implode(' ', $sparkPts);
    $sparkArea = $sparkLine !== '' ? "0,30 {$sparkLine} 100,30" : '';

    // ── Reports: margin + payment-method distribution of real income ──
    $margin      = $incomeVal > 0 ? round($netVal / $incomeVal * 100, 1) : 0;
    $incomeTxs   = $transactions->where('type', 'income');
    $pmTotal     = max(0.01, (float) $incomeTxs->sum('amount'));
    $pmBreakdown = $incomeTxs->groupBy(fn ($t) => $t->payment_method ?: 'other')
        ->map(fn ($rows, $pm) => [
            'key'   => $pm,
            'label' => __('admin.fin_pm_' . ($pm === 'bank_transfer' ? 'bank' : $pm)),
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'pct'   => round((float) $rows->sum('amount') / $pmTotal * 100, 1),
        ])->sortByDesc('total')->values();

    $expenseTotal = max(0.01, (float) $expenseCategories->sum('total'));
    $recurringActive = $recurringExpenses->where('is_active', true)->count();

    // ── Committed-but-not-yet-posted money ──
    // A staff wage is stored as a recurring RULE (ClubRecurringExpense); it only becomes a
    // real expense transaction when `expenses:process-recurring` runs on its day_of_month.
    // Totals above are posted cash only, so surface the committed figure separately —
    // otherwise a club that just hired someone sees 0 expenses and assumes it's broken.
    $committedMonthly = (float) $recurringExpenses->where('is_active', true)->sum('amount');
    $awaitingPost = $recurringExpenses->where('is_active', true)->reject(fn ($r) => $r->hasRunThisMonth());
    $awaitingTotal = (float) $awaitingPost->sum('amount');

    // Figures are scoped to the club's current test/live mode. Say so when the other side
    // holds records, so nothing silently disappears behind the filter.
    $otherModeCount = \App\Models\ClubTransaction::where('tenant_id', $club->id)
        ->where('is_test', ! $isTestMode)->count();

    // Known expense categories have a translation; anything else falls back to its raw value.
    $knownCats = ['rent', 'utilities', 'equipment', 'salaries', 'maintenance', 'marketing', 'insurance', 'other'];
    $catLabel = fn ($c) => in_array($c, $knownCats, true)
        ? __('admin.fin_cat_' . $c)
        : ($c ? ucfirst($c) : __('admin.fin_cat_other'));
@endphp

<div class="-mx-4 -mt-4" x-data="financialsHub()" x-init="init()">

    {{-- ══════════════ Hero — the one number that matters, plus the flow split ══════════════ --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                    <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_financials') }}</h1>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($isTestMode)
                        <span class="px-2.5 py-1 rounded-full bg-amber-400/90 text-amber-950 text-[10px] font-black uppercase tracking-wide">{{ __('admin.fin_test_mode') }}</span>
                    @endif
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                        <i class="bi bi-bank text-xl m-float"></i>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                {{-- Month scope — the hero + ledger both read the selected month; step back to
                     review past months, capped at the current one (no future). --}}
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] uppercase tracking-wide text-white/70">{{ __('admin.dash_net') }}</p>
                    <div class="inline-flex items-center gap-0.5 rounded-full bg-white/12 border border-white/20 p-0.5">
                        <button type="button" @click="shiftMonth(-1)" aria-label="{{ __('admin.fin_prev_month') }}"
                                class="m-press w-7 h-7 rounded-full grid place-items-center text-white/90 hover:bg-white/15 transition-colors">
                            <i class="bi bi-chevron-left rtl:rotate-180 text-xs"></i>
                        </button>
                        <span class="min-w-[92px] text-center text-xs font-bold tracking-wide" x-text="monthLabel"></span>
                        <button type="button" @click="shiftMonth(1)" :disabled="! canGoNextMonth" aria-label="{{ __('admin.fin_next_month') }}"
                                class="m-press w-7 h-7 rounded-full grid place-items-center text-white/90 hover:bg-white/15 transition-colors disabled:opacity-30 disabled:pointer-events-none">
                            <i class="bi bi-chevron-right rtl:rotate-180 text-xs"></i>
                        </button>
                    </div>
                </div>
                <p class="text-3xl font-black tabular-nums leading-none mt-1">
                    <span x-text="(monthSum.net >= 0 ? '+' : '') + fmt0(monthSum.net)"></span><span class="text-sm font-bold ms-1">{{ $cur }}</span>
                </p>

                {{-- 12-month revenue curve for context (server-rendered SVG, no JS) --}}
                @if($sparkLine !== '')
                    <div class="mt-3.5">
                        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-10 overflow-visible" aria-hidden="true">
                            <defs>
                                <linearGradient id="finHeroSpark" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#fff" stop-opacity="0.30" />
                                    <stop offset="100%" stop-color="#fff" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <polygon points="{{ $sparkArea }}" fill="url(#finHeroSpark)" />
                            <polyline points="{{ $sparkLine }}" fill="none" stroke="#fff" stroke-width="1.5"
                                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" opacity="0.92" />
                        </svg>
                    </div>
                @endif

                <p x-show="monthLedger.length === 0" x-cloak class="mt-2 text-[11px] text-white/70 flex items-center gap-1.5">
                    <i class="bi bi-calendar-x"></i>{{ __('admin.fin_no_month_activity') }}
                </p>

                {{-- Why a figure may look "missing": these totals are POSTED cash only, in the
                     club's current data mode. Both caveats are stated rather than left implicit. --}}
                @if($awaitingTotal > 0 || ($isTestMode && $otherModeCount > 0))
                    <div class="mt-3 space-y-1">
                        @if($awaitingTotal > 0)
                            <button type="button" @click="open('recurring')"
                                    class="w-full flex items-center gap-2 text-[11px] text-white/85 bg-white/12 border border-white/20 rounded-xl px-3 py-2 text-start">
                                <i class="bi bi-clock-history flex-shrink-0"></i>
                                <span class="flex-1 min-w-0 truncate">{{ __('admin.fin_awaiting_post', ['amount' => number_format($awaitingTotal, 2) . ' ' . $cur]) }}</span>
                                <i class="bi bi-chevron-right rtl:rotate-180 flex-shrink-0"></i>
                            </button>
                        @endif
                        @if($isTestMode && $otherModeCount > 0)
                            <p class="text-[11px] text-white/75 flex items-start gap-1.5 px-1">
                                <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>
                                <span>{{ __('admin.fin_mode_scoped', ['count' => $otherModeCount]) }}</span>
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- ══════════════ HUB ══════════════ --}}
    <div x-show="panel === null" class="px-4 pt-5 pb-6 space-y-5">

        {{-- Primary actions — a matched pair reading money-in / money-out. The top
             hairline and the arrow icons are the same language the record sheet uses,
             so tapping one feels like it opens exactly what it promised. --}}
        <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="openIncome()" class="m-card m-press relative overflow-hidden p-3.5 text-start">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center">
                    <i class="bi bi-arrow-down-left"></i>
                </span>
                <p class="mt-2 text-sm font-bold text-foreground">{{ __('admin.fin_income') }}</p>
                <p class="text-[11px] text-muted-foreground leading-snug">{{ __('admin.fin_money_in') }}</p>
            </button>
            <button type="button" @click="openExpense()" class="m-card m-press relative overflow-hidden p-3.5 text-start">
                <span class="absolute inset-x-0 top-0 h-1 bg-primary"></span>
                <span class="w-9 h-9 rounded-xl bg-accent text-primary grid place-items-center">
                    <i class="bi bi-arrow-up-right"></i>
                </span>
                <p class="mt-2 text-sm font-bold text-foreground">{{ __('admin.fin_expense') }}</p>
                <p class="text-[11px] text-muted-foreground leading-snug">{{ __('admin.fin_money_out') }}</p>
            </button>
        </div>

        {{-- Secondary KPIs the hero doesn't carry --}}
        <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="open('collect')" class="m-card m-press p-3.5 text-start">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-hourglass-split text-sm"></i></span>
                    <span class="text-[11px] font-semibold text-muted-foreground truncate">{{ __('admin.fin_cash_to_collect') }}</span>
                </div>
                <p class="text-lg font-black text-foreground tabular-nums mt-2"><span x-text="fmt0(sum.collect)"></span> <span class="text-[10px] font-bold text-muted-foreground">{{ $cur }}</span></p>
            </button>
            <div class="m-card p-3.5">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 grid place-items-center flex-shrink-0"><i class="bi bi-arrow-counterclockwise text-sm"></i></span>
                    <span class="text-[11px] font-semibold text-muted-foreground truncate">{{ __('admin.fin_refunds') }}</span>
                </div>
                <p class="text-lg font-black text-foreground tabular-nums mt-2"><span x-text="fmt0(monthSum.refunds)"></span> <span class="text-[10px] font-bold text-muted-foreground">{{ $cur }}</span></p>
            </div>
        </div>

        {{-- Committed money — wages and other recurring rules that have not hit the ledger yet. --}}
        @if($committedMonthly > 0)
            <button type="button" @click="open('recurring')" class="m-card m-press w-full p-3.5 text-start flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-cyan-100 text-cyan-600 grid place-items-center flex-shrink-0"><i class="bi bi-calendar-check"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[11px] font-semibold text-muted-foreground">{{ __('admin.fin_committed_monthly') }}</span>
                    <span class="block text-[11px] text-muted-foreground/80 truncate">{{ __('admin.fin_committed_sub') }}</span>
                </span>
                <span class="text-base font-black text-foreground tabular-nums flex-shrink-0">{{ number_format($committedMonthly, 0) }} <span class="text-[10px] font-bold text-muted-foreground">{{ $cur }}</span></span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>
        @endif

        {{-- Drill-down sections --}}
        <div class="space-y-2.5 mobile-stagger">
            @php
                $hubRows = [
                    ['part'=>'ledger',    'icon'=>'bi-journal-text',       'tint'=>'bg-primary/10 text-primary',      'title'=>__('admin.fin_ledger'),          'sub'=>__('admin.fin_ledger_sub'),    'count'=>$counts['all']],
                    ['part'=>'collect',   'icon'=>'bi-hourglass-split',    'tint'=>'bg-amber-100 text-amber-600',     'title'=>__('admin.fin_cash_to_collect'), 'sub'=>__('admin.fin_collect_sub'),    'count'=>$counts['pending']],
                    ['part'=>'trends',    'icon'=>'bi-graph-up-arrow',     'tint'=>'bg-indigo-100 text-indigo-600',   'title'=>__('admin.fin_trends'),          'sub'=>__('admin.fin_trends_sub'),     'count'=>null],
                    ['part'=>'expenses',  'icon'=>'bi-pie-chart',          'tint'=>'bg-rose-100 text-rose-600',       'title'=>__('admin.fin_by_category'),     'sub'=>__('admin.fin_by_category_sub'),'count'=>$expenseCategories->count()],
                    ['part'=>'recurring', 'icon'=>'bi-arrow-repeat',       'tint'=>'bg-cyan-100 text-cyan-600',       'title'=>__('admin.fin_recurring'),       'sub'=>__('admin.fin_recurring_sub'),  'count'=>$recurringExpenses->count()],
                    ['part'=>'reports',   'icon'=>'bi-clipboard-data',     'tint'=>'bg-emerald-100 text-emerald-600', 'title'=>__('admin.fin_reports'),         'sub'=>__('admin.fin_reports_sub'),    'count'=>null],
                ];
            @endphp
            @foreach($hubRows as $row)
                <button type="button" @click="open('{{ $row['part'] }}')" class="m-card m-press w-full flex items-center gap-3 p-4 text-start">
                    <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 {{ $row['tint'] }}"><i class="bi {{ $row['icon'] }} text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-foreground truncate">{{ $row['title'] }}</span>
                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $row['sub'] }}</span>
                    </span>
                    @if(!is_null($row['count']))
                        <span class="text-sm font-bold text-foreground flex-shrink-0">{{ $row['count'] }}</span>
                    @endif
                    <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══════════════ PANEL CHROME ══════════════ --}}
    <div x-show="panel !== null" x-cloak
         class="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border px-3 py-2.5 flex items-center gap-2">
        <button type="button" @click="close()" class="m-press w-10 h-10 rounded-xl bg-white border border-border grid place-items-center flex-shrink-0" aria-label="{{ __('admin.cs_back') }}">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </button>
        <span class="font-bold text-foreground text-sm truncate flex-1" x-text="title"></span>
        <button type="button" x-show="panel === 'ledger'" @click="openIncome()"
                class="m-press w-10 h-10 rounded-xl bg-green-600 text-white grid place-items-center flex-shrink-0" aria-label="{{ __('admin.fin_income') }}"><i class="bi bi-plus-lg"></i></button>
        <button type="button" x-show="panel === 'ledger' || panel === 'recurring'" @click="openExpense()"
                class="m-press w-10 h-10 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0" aria-label="{{ __('admin.fin_expense') }}"><i class="bi bi-dash-lg"></i></button>
    </div>

    {{-- ══════════════ PANEL: Ledger ══════════════ --}}
    <section x-show="panel === 'ledger'" x-cloak class="px-4 py-4 pb-6 m-panel-in" id="fin-ledger">

        <div class="relative mb-2.5">
            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
            <input type="text" x-model="txSearch" placeholder="{{ __('admin.fin_search_ph') }}"
                   class="w-full ps-10 pe-9 py-2.5 bg-muted rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
            <button type="button" x-show="txSearch" x-cloak @click="txSearch = ''"
                    class="absolute end-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center"><i class="bi bi-x text-sm"></i></button>
        </div>

        {{-- Five equal-width pills that always fit the viewport (no horizontal scroll). --}}
        <div class="flex items-center gap-1 mb-3">
            @foreach(['all' => __('admin.fin_filter_all'), 'pending' => __('admin.fin_filter_pending'), 'income' => __('admin.fin_income'), 'expense' => __('admin.fin_expense'), 'refund' => __('admin.fin_refunds')] as $key => $label)
                <button type="button" @click="txFilter = '{{ $key }}'"
                        :class="txFilter === '{{ $key }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'"
                        class="m-press relative flex-1 min-w-0 px-2 py-1.5 rounded-full text-[11px] font-medium transition-colors inline-flex items-center justify-center">
                    <span class="truncate">{{ $label }}</span>
                    <span x-show="counts['{{ $key }}'] > 0"
                          class="absolute -top-1 -end-1 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold leading-none inline-flex items-center justify-center shadow-sm"
                          x-text="counts['{{ $key }}']"></span>
                </button>
            @endforeach
        </div>

        <p x-show="monthLedger.length === 0" x-cloak class="text-sm text-muted-foreground py-10 text-center">{{ __('admin.fin_no_month_activity') }}</p>
        <p x-show="monthLedger.length > 0 && filteredLedger.length === 0" x-cloak class="text-sm text-muted-foreground py-10 text-center">{{ __('admin.fin_no_results') }}</p>

        <div class="divide-y divide-gray-50">
            <template x-for="r in filteredLedger" :key="r.id">
                <div class="flex items-center gap-3 py-2.5 m-press cursor-pointer" x-data="{ menu: false }"
                     @click="r.type === 'pending' ? openMemberPage(r) : openEdit(r)" role="button" tabindex="0">
                    <span class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                          :class="r.type === 'income' ? 'bg-green-50 text-green-600' : (r.type === 'refund' ? 'bg-amber-50 text-amber-600' : (r.type === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'))">
                        <i class="bi" :class="r.type === 'income' ? 'bi-arrow-down-left' : (r.type === 'refund' ? 'bi-arrow-counterclockwise' : (r.type === 'pending' ? 'bi-hourglass-split' : 'bi-arrow-up-right'))"></i>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-foreground truncate" x-text="r.label"></p>
                        <template x-if="r.member">
                            <p class="text-xs text-primary/90 truncate flex items-center gap-1">
                                <i class="bi bi-person-circle text-[11px]"></i><span x-text="r.member"></span>
                            </p>
                        </template>
                        <template x-if="r.items_summary">
                            <p class="text-xs text-emerald-600/90 truncate flex items-center gap-1">
                                <i class="bi bi-bag text-[11px]"></i><span x-text="r.items_summary"></span>
                            </p>
                        </template>
                        <p class="text-xs text-muted-foreground truncate">
                            <span x-text="r.date_label"></span><template x-if="r.pm_label"><span> · <span x-text="r.pm_label"></span></span></template>
                        </p>
                    </div>

                    <span class="text-sm font-semibold flex-shrink-0"
                          :class="r.type === 'income' ? 'text-green-600' : (r.type === 'refund' || r.type === 'pending' ? 'text-amber-600' : 'text-red-600')">
                        <span x-text="(r.type === 'income' ? '+' : (r.type === 'pending' ? '' : '-')) + r.amount_fmt"></span>
                    </span>

                    <div class="relative flex-shrink-0" @click.stop>
                        <button type="button" @click="menu = !menu" class="m-press w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground" aria-label="{{ __('admin.actions') }}">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div x-show="menu" x-cloak @click.outside="menu = false"
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute end-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-30">

                            <template x-if="r.type === 'pending'">
                                <div>
                                    <button type="button" class="w-full text-start px-4 py-3 text-sm text-green-700 hover:bg-green-50 flex items-center gap-3" @click="menu = false; markPaid(r)">
                                        <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center shrink-0"><i class="bi bi-check-circle text-green-600 text-xs"></i></span>
                                        <span class="font-medium">{{ __('admin.fin_mark_paid') }}</span>
                                    </button>
                                    <button type="button" class="w-full text-start px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="menu = false; openMemberPage(r)">
                                        <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-person text-blue-600 text-xs"></i></span>
                                        <span class="font-medium">{{ __('admin.fin_open_member') }}</span>
                                    </button>
                                </div>
                            </template>

                            <template x-if="r.type !== 'pending'">
                                <div>
                                    <button type="button" class="w-full text-start px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="menu = false; openEdit(r)">
                                        <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span>
                                        <span class="font-medium">{{ __('admin.fin_edit') }}</span>
                                    </button>
                                    <button type="button" x-show="r.refundable" class="w-full text-start px-4 py-3 text-sm text-amber-700 hover:bg-amber-50 flex items-center gap-3" @click="menu = false; openRefund(r)">
                                        <span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="bi bi-arrow-counterclockwise text-amber-600 text-xs"></i></span>
                                        <span class="font-medium">{{ __('admin.fin_refund') }}</span>
                                    </button>
                                    <button type="button" class="w-full text-start px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="menu = false; openDelete(r.id, r.ref)">
                                        <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span>
                                        <span class="font-medium">{{ __('admin.fin_delete') }}</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ══════════════ PANEL: Cash to collect ══════════════ --}}
    <section x-show="panel === 'collect'" x-cloak class="px-4 py-4 pb-6 m-panel-in">
        <div class="m-card p-4 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_cash_to_collect') }}</p>
            <p class="text-2xl font-black text-amber-600 tabular-nums mt-1"><span x-text="fmt(sum.collect)"></span> <span class="text-xs font-bold">{{ $cur }}</span></p>
            <p class="text-xs text-muted-foreground mt-1"><span x-text="pendingRows.length"></span> {{ __('admin.fin_awaiting') }}</p>
        </div>

        <p x-show="pendingRows.length === 0" x-cloak class="text-sm text-muted-foreground py-10 text-center">{{ __('admin.fin_no_pending') }}</p>

        <div class="space-y-2.5">
            <template x-for="r in pendingRows" :key="r.id">
                <div class="m-card p-3.5">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-amber-50 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-hourglass-split"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate" x-text="r.label"></p>
                            <p class="text-xs text-muted-foreground truncate" x-text="r.date_label"></p>
                        </div>
                        <span class="text-sm font-bold text-amber-600 tabular-nums flex-shrink-0" x-text="r.amount_fmt"></span>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button type="button" @click="markPaid(r)" class="m-press flex-1 py-2.5 rounded-xl bg-green-600 text-white text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="bi bi-check-circle"></i>{{ __('admin.fin_mark_paid') }}
                        </button>
                        <button type="button" @click="openMemberPage(r)" class="m-press flex-1 py-2.5 rounded-xl border border-border text-foreground text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="bi bi-person"></i>{{ __('admin.fin_open_member') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ══════════════ PANEL: Trends (dependency-free 12-month bars) ══════════════ --}}
    <section x-show="panel === 'trends'" x-cloak class="px-4 py-4 pb-6 m-panel-in">
        <div class="m-card p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="text-sm font-semibold text-foreground">{{ now()->year }}</p>
                <div class="flex items-center gap-3 text-[10px] text-muted-foreground">
                    <span><i class="bi bi-circle-fill text-emerald-500 text-[7px] me-1"></i>{{ __('market.stat_revenue') }}</span>
                    <span><i class="bi bi-circle-fill text-rose-400 text-[7px] me-1"></i>{{ __('admin.dash_expenses') }}</span>
                </div>
            </div>

            {{-- Tap a month to read its numbers below the chart. --}}
            <div class="flex items-end justify-between gap-1 h-36">
                @foreach($trendData as $i => $m)
                    <button type="button" @click="trendIdx = (trendIdx === {{ $i }} ? null : {{ $i }})"
                            class="flex-1 min-w-0 h-full flex flex-col items-center justify-end gap-1 rounded-lg transition-colors"
                            :class="trendIdx === {{ $i }} ? 'bg-muted' : ''"
                            aria-label="{{ $m['month'] }}">
                        <span class="w-full flex items-end justify-center gap-[2px] h-full">
                            <span class="w-1/2 max-w-[10px] rounded-t bg-emerald-500 m-bar-fill" style="height: {{ max($m['ih'], $m['income'] > 0 ? 3 : 0) }}%"></span>
                            <span class="w-1/2 max-w-[10px] rounded-t bg-rose-400 m-bar-fill" style="height: {{ max($m['eh'], $m['expenses'] > 0 ? 3 : 0) }}%"></span>
                        </span>
                        <span class="text-[9px] text-muted-foreground truncate w-full text-center">{{ mb_substr($m['month'], 0, 1) }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <template x-if="trendIdx !== null">
            <div class="m-card p-4 mt-3 m-panel-in">
                <p class="text-sm font-bold text-foreground" x-text="trend[trendIdx].month"></p>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('market.stat_revenue') }}</p><p class="text-base font-bold text-green-600 tabular-nums" x-text="fmt(trend[trendIdx].income)"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.dash_expenses') }}</p><p class="text-base font-bold text-red-600 tabular-nums" x-text="fmt(trend[trendIdx].expenses)"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.dash_net') }}</p><p class="text-base font-bold tabular-nums" :class="trend[trendIdx].profit >= 0 ? 'text-green-600' : 'text-red-600'" x-text="fmt(trend[trendIdx].profit)"></p></div>
                    <div><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_cash_to_collect') }}</p><p class="text-base font-bold text-amber-600 tabular-nums" x-text="fmt(trend[trendIdx].collect)"></p></div>
                </div>
            </div>
        </template>

        <p x-show="trendIdx === null" class="text-xs text-muted-foreground text-center mt-3">{{ __('admin.fin_tap_month') }}</p>
    </section>

    {{-- ══════════════ PANEL: Expenses by category ══════════════ --}}
    <section x-show="panel === 'expenses'" x-cloak class="px-4 py-4 pb-6 m-panel-in">
        @if($expenseCategories->isEmpty())
            <p class="text-sm text-muted-foreground py-10 text-center">{{ __('admin.fin_no_expenses') }}</p>
        @else
            <div class="space-y-2.5">
                @foreach($expenseCategories as $cat)
                    @php $pct = round($cat['total'] / $expenseTotal * 100, 1); @endphp
                    <div class="m-card overflow-hidden" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="w-full p-4 text-start">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 grid place-items-center flex-shrink-0"><i class="bi bi-tag"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $catLabel($cat['category']) }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ $cat['items']->count() }} · {{ $pct }}%</p>
                                </div>
                                <span class="text-sm font-bold text-foreground tabular-nums flex-shrink-0">{{ $cur }} {{ number_format($cat['total'], 2) }}</span>
                                <i class="bi bi-chevron-down text-muted-foreground text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                            </div>
                            <div class="mt-3 h-1.5 rounded-full bg-muted overflow-hidden">
                                <div class="h-full rounded-full bg-rose-400 m-bar-fill" style="width: {{ $pct }}%"></div>
                            </div>
                        </button>

                        <div x-show="open" x-cloak class="px-4 pb-4 space-y-2 border-t border-gray-50 pt-3"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            @foreach($cat['items'] as $item)
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="min-w-0 truncate text-foreground">{{ $item->description ?: __('admin.fin_cat_other') }}</span>
                                    <span class="flex-shrink-0 text-muted-foreground tabular-nums">{{ optional($item->transaction_date)->format('d M') }} · {{ number_format((float) $item->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ══════════════ PANEL: Recurring expenses ══════════════ --}}
    <section x-show="panel === 'recurring'" x-cloak class="px-4 py-4 pb-6 m-panel-in">
        <div class="m-card p-4 mb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-600 grid place-items-center flex-shrink-0"><i class="bi bi-arrow-repeat"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground">{{ $recurringActive }} / {{ $recurringExpenses->count() }} {{ __('admin.fin_recurring_active') }}</p>
                    <p class="text-[11px] text-muted-foreground">{{ __('admin.fin_recurring_help') }}</p>
                </div>
                <span class="text-sm font-bold text-foreground tabular-nums flex-shrink-0">{{ $cur }} {{ number_format($committedMonthly, 2) }}</span>
            </div>
            {{-- These rules are NOT in the ledger totals until the daily job posts them. --}}
            <p class="text-[11px] text-muted-foreground mt-3 flex items-start gap-1.5 border-t border-gray-50 pt-3">
                <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i><span>{{ __('admin.fin_recurring_not_counted') }}</span>
            </p>
        </div>

        @if($recurringExpenses->isEmpty())
            <div class="text-center py-10">
                <i class="bi bi-arrow-repeat text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">{{ __('admin.fin_no_recurring') }}</p>
                <button type="button" @click="openExpense(); tx.recurring = true" class="m-press mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold"><i class="bi bi-plus-lg"></i>{{ __('admin.fin_record_recurring') }}</button>
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($recurringExpenses as $re)
                    <div class="m-card p-3.5" id="recur-{{ $re->id }}" x-data="{ active: {{ $re->is_active ? 'true' : 'false' }}, busy: false }">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 transition-colors"
                                  :class="active ? 'bg-cyan-100 text-cyan-600' : 'bg-gray-100 text-gray-400'"><i class="bi bi-arrow-repeat"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate" :class="active ? '' : 'line-through opacity-60'">{{ $re->description }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">
                                    @if($re->category){{ $catLabel($re->category) }} · @endif
                                    @if(! $re->is_active)
                                        {{ __('admin.fin_paused') }}
                                    @elseif($re->hasRunThisMonth())
                                        <span class="text-emerald-600 font-medium">{{ __('admin.fin_posted_this_month') }}</span>
                                    @else
                                        {{ __('admin.fin_posts_on', ['day' => $re->day_of_month]) }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-sm font-bold text-foreground tabular-nums flex-shrink-0">{{ $cur }} {{ number_format((float) $re->amount, 2) }}</span>
                        </div>

                        <div class="flex items-center gap-2 mt-3">
                            <button type="button" @click="busy = true; toggleRecurring({{ $re->id }}).then(v => { if (v !== null) active = v; }).finally(() => busy = false)"
                                    :disabled="busy"
                                    class="m-press flex-1 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 disabled:opacity-50 transition-colors"
                                    :class="active ? 'border border-border text-foreground' : 'bg-primary text-white'">
                                <i class="bi" :class="active ? 'bi-pause-circle' : 'bi-play-circle'"></i>
                                <span x-text="active ? @js(__('admin.fin_pause')) : @js(__('admin.fin_activate'))"></span>
                            </button>
                            <button type="button" @click="deleteRecurring({{ $re->id }}, @js($re->description))"
                                    class="m-press w-11 py-2.5 rounded-xl border border-red-200 text-red-600 grid place-items-center" aria-label="{{ __('admin.fin_delete') }}">
                                <i class="bi bi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ══════════════ PANEL: Reports ══════════════ --}}
    <section x-show="panel === 'reports'" x-cloak class="px-4 py-4 pb-6 m-panel-in space-y-3">

        {{-- Profit & loss --}}
        <div class="m-card p-4">
            <h3 class="text-sm font-bold text-foreground mb-3">{{ __('admin.fin_pl') }}</h3>
            <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">{{ __('market.stat_revenue') }}</span>
                    <span class="font-semibold text-green-600 tabular-nums">{{ $cur }} {{ number_format($incomeVal, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">{{ __('admin.dash_expenses') }}</span>
                    <span class="font-semibold text-red-600 tabular-nums">− {{ $cur }} {{ number_format($expensesVal, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">{{ __('admin.fin_refunds') }}</span>
                    <span class="font-semibold text-amber-600 tabular-nums">− {{ $cur }} {{ number_format($refundsVal, 2) }}</span>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 pt-2.5">
                    <span class="font-bold text-foreground">{{ __('admin.dash_net') }}</span>
                    <span class="font-black tabular-nums {{ $netVal >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $cur }} {{ number_format($netVal, 2) }}</span>
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-muted/50 p-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground">{{ __('admin.fin_margin') }}</span>
                <span class="text-lg font-black tabular-nums {{ $margin >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $margin }}%</span>
            </div>
        </div>

        {{-- Transaction summary --}}
        <div class="m-card p-4">
            <h3 class="text-sm font-bold text-foreground mb-3">{{ __('admin.fin_tx_summary') }}</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div><p class="text-lg font-black text-green-600">{{ $counts['income'] }}</p><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_income') }}</p></div>
                <div><p class="text-lg font-black text-red-600">{{ $counts['expense'] }}</p><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_expense') }}</p></div>
                <div><p class="text-lg font-black text-amber-600">{{ $counts['refund'] }}</p><p class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_refunds') }}</p></div>
            </div>
        </div>

        {{-- Payment methods --}}
        <div class="m-card p-4">
            <h3 class="text-sm font-bold text-foreground mb-3">{{ __('admin.fin_pm_breakdown') }}</h3>
            @if($pmBreakdown->isEmpty())
                <p class="text-sm text-muted-foreground py-4 text-center">{{ __('admin.fin_no_transactions') }}</p>
            @else
                <div class="space-y-3">
                    @foreach($pmBreakdown as $pm)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-medium text-foreground">{{ $pm['label'] }} <span class="text-muted-foreground">· {{ $pm['count'] }}</span></span>
                                <span class="text-muted-foreground tabular-nums">{{ $cur }} {{ number_format($pm['total'], 2) }} · {{ $pm['pct'] }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                <div class="h-full rounded-full bg-primary m-bar-fill" style="width: {{ $pm['pct'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <p class="text-[11px] text-muted-foreground text-center px-4 pt-1">{{ __('admin.fin_desktop_only_note') }}</p>
    </section>

    {{-- ═══════════ Transaction sheet (income / expense / edit) ═══════════
         A tinted badge says which way the money moves; the amount
         sits on the one tinted slab in the sheet with +5/+10/+25/+50 step chips
         (cash entries are round numbers, and tapping beats typing). Everything
         below is white rows separated by thin rules — one decision each.
    --}}
    <template x-teleport="body">
    <div x-show="txOpen" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">
        <div x-show="txOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="txOpen = false"></div>
        <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <div x-show="txOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                 class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-md flex flex-col overflow-hidden"
                 {{-- inline, not a utility class: the cap must hold even if the CSS bundle
                      is stale, otherwise the body never scrolls and the action button is
                      pushed off-screen. dvh keeps it right under mobile browser chrome. --}}
                 style="max-height: 92vh; max-height: 92dvh;" @click.stop>

                <form x-ref="txForm" method="POST" :action="txAction" class="flex flex-col min-h-0 flex-1" @submit.prevent="submitTx()">
                @csrf
                <template x-if="txMode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

                <div class="pt-2 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-9 h-1 rounded-full bg-gray-200"></span></div>

                <div class="flex items-center justify-between gap-3 px-5 pt-1.5 pb-3 flex-shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors"
                          :class="tx.type === 'income' ? 'bg-emerald-50 text-emerald-700' : (tx.type === 'refund' ? 'bg-amber-50 text-amber-700' : 'bg-accent text-primary')">
                        <i class="bi text-[10px]" :class="txMode === 'edit' ? 'bi-pencil' : (txIsRecurring ? 'bi-arrow-repeat' : (tx.type === 'income' ? 'bi-arrow-down-left' : 'bi-arrow-up-right'))"></i>
                        <span x-text="txMode === 'edit' ? @js(__('admin.fin_edit_tx')) : (txIsRecurring ? @js(__('admin.fin_record_recurring')) : (tx.type === 'income' ? @js(__('admin.fin_record_income')) : @js(__('admin.fin_record_expense'))))"></span>
                    </span>
                    <button type="button" @click="txOpen = false" aria-label="{{ __('admin.cancel') }}"
                            class="w-8 h-8 -me-1.5 rounded-full grid place-items-center text-muted-foreground hover:bg-muted transition-colors flex-shrink-0">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pb-5">

                    {{-- Amount — a tinted slab, the only coloured surface in the sheet.
                         The step chips are here because cash entries are almost always
                         round numbers, and tapping beats typing on a phone. --}}
                    <div class="rounded-2xl p-4 transition-colors"
                         :class="tx.type === 'income' ? 'bg-emerald-50/70' : (tx.type === 'refund' ? 'bg-amber-50/70' : 'bg-accent/50')">
                        <label class="block cursor-text">
                            <span class="text-[11px] font-semibold uppercase tracking-wide"
                                  :class="tx.type === 'income' ? 'text-emerald-700/70' : (tx.type === 'refund' ? 'text-amber-700/70' : 'text-primary/70')">{{ __('admin.fin_amount') }}</span>
                            <span class="mt-0.5 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold"
                                      :class="tx.type === 'income' ? 'text-emerald-600' : (tx.type === 'refund' ? 'text-amber-600' : 'text-primary')"
                                      x-text="tx.type === 'income' ? '+' : '−'"></span>
                                <input type="number" x-model="tx.amount" step="0.01" min="0" inputmode="decimal" required
                                       name="amount" placeholder="0.00"
                                       class="min-w-0 flex-1 bg-transparent border-0 p-0 text-[2.25rem] leading-none font-black tabular-nums text-foreground placeholder:text-black/15 focus:outline-none focus:ring-0">
                                <span class="text-xs font-bold flex-shrink-0"
                                      :class="tx.type === 'income' ? 'text-emerald-700/70' : (tx.type === 'refund' ? 'text-amber-700/70' : 'text-primary/70')">{{ $cur }}</span>
                            </span>
                        </label>

                        <div class="mt-3 flex items-center gap-1.5">
                            @foreach([5, 10, 25, 50] as $step)
                                <button type="button" @click="tx.amount = ((Number(tx.amount) || 0) + {{ $step }}).toFixed(2)"
                                        class="m-press flex-1 py-1.5 rounded-lg bg-white/70 text-[11px] font-bold text-foreground/70 hover:bg-white transition-colors">
                                    +{{ $step }}
                                </button>
                            @endforeach
                            <button type="button" @click="tx.amount = ''" x-show="Number(tx.amount) > 0" x-cloak
                                    class="m-press w-8 py-1.5 rounded-lg bg-white/70 text-[11px] font-bold text-muted-foreground hover:bg-white transition-colors"
                                    aria-label="{{ __('admin.fin_clear') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>

                    {{-- Paid by — the enrolled member this income belongs to (read-only; derived
                         from the linked subscription, not editable here). --}}
                    <div x-show="txMember" x-cloak class="mt-3 flex items-center gap-2.5 rounded-2xl bg-primary/5 border border-primary/10 px-3.5 py-2.5">
                        <span class="w-8 h-8 rounded-full bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-fill text-sm"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-primary/60">{{ __('admin.fin_paid_by') }}</span>
                            <span class="block text-sm font-semibold text-foreground truncate" x-text="txMember"></span>
                        </span>
                    </div>

                    {{-- Items sold — the products behind a shop-sale income (read-only snapshot from
                         the order; shows name, variant, qty and line total so the code isn't opaque). --}}
                    <div x-show="txItems.length" x-cloak class="mt-3 rounded-2xl bg-emerald-50/60 border border-emerald-100 overflow-hidden">
                        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-emerald-100/70">
                            <i class="bi bi-bag-check-fill text-emerald-600 text-sm"></i>
                            <span class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">{{ __('admin.fin_items_sold') }}</span>
                            <span class="ms-auto text-[11px] font-semibold text-emerald-700/70" x-text="txItems.length + ' {{ __('admin.fin_items_count_suffix') }}'"></span>
                        </div>
                        <div class="divide-y divide-emerald-100/70">
                            <template x-for="(it, i) in txItems" :key="i">
                                <div class="flex items-center gap-2.5 px-3.5 py-2">
                                    <span class="w-5 text-center text-xs font-bold text-emerald-700/80 flex-shrink-0" x-text="'×' + it.qty"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-foreground truncate" x-text="it.name"></span>
                                        <span class="block text-[11px] text-muted-foreground truncate" x-show="it.variant" x-text="it.variant"></span>
                                    </span>
                                    <span class="text-xs font-bold text-foreground tabular-nums flex-shrink-0" x-text="it.line_total"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100">

                    {{-- Type (edit only) --}}
                    <div x-show="txMode === 'edit'" x-cloak class="py-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_type') }}</span>
                        <div class="mt-1.5">
                            <x-select-menu model="tx.type" name="type" :options="[
                                ['value' => 'income',  'label' => __('admin.fin_income')],
                                ['value' => 'expense', 'label' => __('admin.fin_expense')],
                                ['value' => 'refund',  'label' => __('admin.fin_refund')],
                            ]" />
                        </div>
                    </div>

                    {{-- What it was --}}
                    <div class="py-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_description') }} <span class="text-red-500">*</span></span>
                        <input type="text" name="description" x-model="tx.description" required
                               placeholder="{{ __('admin.fin_description_ph') }}"
                               class="mt-1 w-full px-0 py-1 bg-transparent border-0 text-[15px] font-medium text-foreground placeholder:text-muted-foreground/60 placeholder:font-normal focus:outline-none focus:ring-0">
                    </div>

                    {{-- Category — one swipeable row, never a wrapping pile (Design Rule #4: no native select) --}}
                    <div x-show="txCats.length" x-cloak class="py-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_category') }}</span>
                        <input type="hidden" name="category" :value="tx.category">
                        <div class="mt-2 -mx-5 px-5 flex gap-2 overflow-x-auto scrollbar-hide">
                            <template x-for="c in txCats" :key="c.v">
                                <button type="button" @click="tx.category = (tx.category === c.v ? '' : c.v)"
                                        class="m-press flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                                        :class="tx.category === c.v
                                            ? 'border-primary/30 bg-accent text-primary'
                                            : 'border-gray-200 bg-white text-muted-foreground'">
                                    <i class="bi text-[11px]" :class="c.i"></i><span x-text="c.l"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Recurring --}}
                    <div x-show="txMode === 'expense'" x-cloak class="py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground">{{ __('admin.fin_recurring') }}</p>
                            <p class="text-[11px] text-muted-foreground leading-snug">{{ __('admin.fin_recurring_help') }}</p>
                        </div>
                        <button type="button" role="switch" :aria-checked="tx.recurring.toString()" @click="tx.recurring = !tx.recurring"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors"
                                :class="tx.recurring ? 'bg-primary' : 'bg-gray-200'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform" :class="tx.recurring ? 'translate-x-6' : 'translate-x-1'"></span>
                        </button>
                    </div>

                    {{-- When --}}
                    <div class="py-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
                                  x-text="txIsRecurring ? @js(__('admin.fin_recurring_day')) : @js(__('admin.fin_date'))"></span>
                            <button type="button" x-show="! txIsRecurring && tx.transaction_date !== '{{ $today }}'" x-cloak
                                    @click="tx.transaction_date = '{{ $today }}'"
                                    class="text-[11px] font-semibold text-primary">{{ __('admin.fin_today') }}</button>
                        </div>
                        <div class="mt-1.5">
                            {{-- Custom calendar (Design Rule #4). A recurring rule may point at a day
                                 later this month, so the "no future dates" cap is for one-offs only. --}}
                            <x-date-picker model="tx.transaction_date"
                                           max-expr="txIsRecurring ? null : '{{ $today }}'"
                                           name-expr="txIsRecurring ? 'recurring_date' : 'transaction_date'" />
                        </div>
                        <p x-show="txIsRecurring" x-cloak class="mt-2 text-[11px] text-muted-foreground flex items-start gap-1.5">
                            <i class="bi bi-info-circle mt-px"></i><span>{{ __('admin.fin_recurring_day_help') }}</span>
                        </p>
                    </div>

                    {{-- How it moved — segmented, on one track --}}
                    <div class="py-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_payment_method') }}</span>
                        <input type="hidden" name="payment_method" :value="tx.payment_method">
                        <div class="mt-2 grid grid-cols-4 gap-1 p-1 rounded-2xl bg-muted/60">
                            @foreach(['cash' => 'bi-cash-stack', 'bank_transfer' => 'bi-bank', 'card' => 'bi-credit-card', 'other' => 'bi-three-dots'] as $pm => $icon)
                                <button type="button" @click="tx.payment_method = '{{ $pm }}'"
                                        :class="tx.payment_method === '{{ $pm }}' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'"
                                        class="flex flex-col items-center gap-0.5 py-2 rounded-xl text-[11px] font-semibold transition-all">
                                    <i class="bi {{ $icon }} text-base"></i>{{ __('admin.fin_pm_' . ($pm === 'bank_transfer' ? 'bank' : $pm)) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Anything else --}}
                    <div class="py-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.fin_notes') }}</span>
                        <textarea :name="txIsRecurring ? 'notes' : 'reference_number'" x-model="tx.reference_number" rows="2"
                                  placeholder="{{ __('admin.fin_notes_ph') }}"
                                  class="mt-1 w-full px-0 py-1 bg-transparent border-0 text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none focus:ring-0 resize-none"></textarea>
                    </div>
                    </div>
                </div>
                </form>

                <div class="px-5 py-3 border-t border-gray-100 flex-shrink-0"
                     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                    <button type="button" @click="$refs.txForm.requestSubmit()"
                            class="m-press w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-1.5 transition-colors"
                            :class="tx.type === 'income' ? 'bg-emerald-600' : (tx.type === 'refund' ? 'bg-amber-600' : 'bg-primary')">
                        <span x-text="txMode === 'edit' ? @js(__('admin.fin_save')) : (txIsRecurring ? @js(__('admin.fin_record_recurring')) : @js(__('admin.fin_record')))"></span>
                        <span x-show="Number(tx.amount) > 0" x-cloak class="tabular-nums opacity-90"
                              x-text="fmt(tx.amount) + ' {{ $cur }}'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ═══════════ Delete confirm ═══════════ --}}
    <template x-teleport="body">
    <div x-show="delOpen" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="delOpen = false"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.fin_delete_title') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="delOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" :action="`{{ url('admin/club/' . $club->slug . '/financials') }}/${delId}`">
                    @csrf
                    @method('DELETE')
                    <div class="px-5 py-4">
                        <p class="mb-1 text-sm">{{ __('admin.fin_delete_msg') }}</p>
                        <p class="font-semibold text-sm" x-text="delRef"></p>
                        <div class="rounded-xl bg-red-50 border border-red-100 mt-3 p-3 text-xs text-red-700"><i class="bi bi-exclamation-triangle mr-1"></i>{{ __('admin.fin_delete_warn') }}</div>
                    </div>
                    <div class="border-t px-5 py-4 flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="delOpen = false">{{ __('admin.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1"><i class="bi bi-trash"></i>{{ __('admin.fin_delete') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </template>

    {{-- ═══════════ Refund sheet ═══════════ --}}
    <template x-teleport="body">
    <div x-show="rfOpen" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="rfOpen = false"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-md relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h5 class="font-semibold flex items-center text-foreground"><i class="bi bi-arrow-counterclockwise text-amber-600 mr-2"></i>{{ __('admin.fin_refund_title') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="rfOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">{{ __('admin.fin_refund_amount') }}</p>
                        <p class="text-2xl font-bold text-amber-700 mt-0.5" x-text="'{{ $cur }} ' + Number(rfTarget?.amount_paid || 0).toFixed(2)"></p>
                        <p class="text-xs text-amber-600 mt-1 truncate" x-text="rfTarget?.description || ''"></p>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.fin_refund_proof') }}</label>
                        <input type="file" accept="image/*" class="hidden" x-ref="rfProofInput" @change="onRefundProof($event)">
                        <button type="button" @click="$refs.rfProofInput.click()" class="m-press w-full rounded-xl border border-dashed border-gray-300 py-3 text-sm font-medium text-muted-foreground bg-white flex items-center justify-center gap-2">
                            <i class="bi bi-upload"></i><span x-text="rfProofName || @js(__('admin.fin_refund_choose'))"></span>
                        </button>
                    </div>

                    <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700">
                        <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i><span>{{ __('admin.fin_refund_warning') }}</span>
                    </div>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="rfOpen = false" :disabled="rfBusy">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-amber-600 text-white flex items-center gap-1" @click="processRefund()" :disabled="rfBusy">
                        <span x-show="!rfBusy"><i class="bi bi-arrow-counterclockwise mr-1"></i>{{ __('admin.fin_refund_confirm') }}</span>
                        <span x-show="rfBusy"><span class="inline-block animate-spin mr-1">↻</span>{{ __('admin.fin_processing') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

<script>
function financialsHub() {
    return {
        // ── hub / panels ──
        panel: null,
        title: '',
        titles: {
            ledger:    @js(__('admin.fin_ledger')),
            collect:   @js(__('admin.fin_cash_to_collect')),
            trends:    @js(__('admin.fin_trends')),
            expenses:  @js(__('admin.fin_by_category')),
            recurring: @js(__('admin.fin_recurring')),
            reports:   @js(__('admin.fin_reports')),
        },

        // ── live KPI state (patched in place, never reloaded) ──
        sum: {
            net:      {{ $netVal }},
            income:   {{ $incomeVal }},
            expenses: {{ $expensesVal }},
            refunds:  {{ $refundsVal }},
            collect:  {{ $collectVal }},
        },
        trend: @js($trendData),
        trendIdx: null,

        fmt(n)  { return (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fmt0(n) { return Math.round(Number(n) || 0).toLocaleString(); },

        init() {
            const hash = (window.location.hash || '').replace('#', '');
            if (hash && this.titles[hash]) this.open(hash);
        },
        open(part) {
            this.panel = part;
            this.title = this.titles[part] || '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        close() { this.panel = null; window.scrollTo({ top: 0, behavior: 'smooth' }); },

        // ── transaction sheet (income / expense / edit) ──
        txOpen: false, txMode: 'income', txId: null, txMember: '', txItems: [],
        tx: { type: 'income', description: '', amount: '', transaction_date: '{{ $today }}', category: '', payment_method: 'cash', reference_number: '', recurring: false },
        incomeCats: [
            { v: 'subscription', l: @js(__('admin.fin_cat_subscription')) , i: 'bi-arrow-repeat' },
            { v: 'event',        l: @js(__('admin.fin_cat_event')) , i: 'bi-calendar-event' },
            { v: 'product_sale', l: @js(__('admin.fin_cat_product_sale')) , i: 'bi-bag' },
            { v: 'sponsorship',  l: @js(__('admin.fin_cat_sponsorship')) , i: 'bi-megaphone' },
            { v: 'donation',     l: @js(__('admin.fin_cat_donation')) , i: 'bi-heart' },
            { v: 'other',        l: @js(__('admin.fin_cat_other')) , i: 'bi-three-dots' },
        ],
        expenseCats: [
            { v: 'rent',        l: @js(__('admin.fin_cat_rent')) , i: 'bi-house' },
            { v: 'utilities',   l: @js(__('admin.fin_cat_utilities')) , i: 'bi-lightning-charge' },
            { v: 'equipment',   l: @js(__('admin.fin_cat_equipment')) , i: 'bi-tools' },
            { v: 'salaries',    l: @js(__('admin.fin_cat_salaries')) , i: 'bi-people' },
            { v: 'maintenance', l: @js(__('admin.fin_cat_maintenance')) , i: 'bi-wrench-adjustable' },
            { v: 'marketing',   l: @js(__('admin.fin_cat_marketing')) , i: 'bi-badge-ad' },
            { v: 'insurance',   l: @js(__('admin.fin_cat_insurance')) , i: 'bi-shield-check' },
            { v: 'other',       l: @js(__('admin.fin_cat_other')) , i: 'bi-three-dots' },
        ],
        get txCats() { return this.tx.type === 'expense' ? this.expenseCats : (this.tx.type === 'income' ? this.incomeCats : []); },
        get txIsRecurring() { return this.txMode === 'expense' && this.tx.recurring; },
        get txAction() {
            if (this.txMode === 'edit') return `{{ url('admin/club/' . $club->slug . '/financials') }}/${this.txId}`;
            if (this.txIsRecurring) return '{{ route('admin.club.financials.recurring.store', $club->slug) }}';
            return this.txMode === 'expense' ? '{{ route('admin.club.financials.expense', $club->slug) }}' : '{{ route('admin.club.financials.income', $club->slug) }}';
        },
        resetTx() { this.tx = { type: 'income', description: '', amount: '', transaction_date: '{{ $today }}', category: '', payment_method: 'cash', reference_number: '', recurring: false }; },
        openIncome()  { this.txMode = 'income';  this.txId = null; this.txMember = ''; this.txItems = []; this.resetTx(); this.tx.type = 'income';  this.txOpen = true; },
        openExpense() { this.txMode = 'expense'; this.txId = null; this.txMember = ''; this.txItems = []; this.resetTx(); this.tx.type = 'expense'; this.txOpen = true; },
        openEdit(t)   { this.txMode = 'edit'; this.txId = t.id; this.txMember = t.member || ''; this.txItems = t.items || []; this.tx = { type: t.type, description: t.description || '', amount: t.amount, transaction_date: t.transaction_date, category: t.category || '', payment_method: t.payment_method || 'cash', reference_number: t.reference_number || '', recurring: false }; this.txOpen = true; },
        submitTx() {
            if (!this.tx.description.trim()) { window.showToast('warning', @js(__('admin.fin_err_desc'))); return; }
            if (!(parseFloat(this.tx.amount) >= 0) || this.tx.amount === '') { window.showToast('warning', @js(__('admin.fin_err_amount'))); return; }
            if (!this.tx.transaction_date) { window.showToast('warning', @js(__('admin.fin_err_date'))); return; }
            this.$refs.txForm.submit();
        },

        goMembers() { window.location.href = '{{ route('admin.club.members', $club->slug) }}'; },
        openMemberPage(r) { window.location.href = r.member_url || '{{ route('admin.club.members', $club->slug) }}'; },

        /** Approving does NOT create a transaction (the income row already exists from
         *  registration) — so the pending row simply leaves the ledger. Patch in place. */
        async markPaid(r) {
            if (!r.subscription_id) return;
            const ok = await window.confirmAction({ title: @js(__('admin.fin_mark_paid')), message: @js(__('admin.fin_mark_paid_confirm')), type: 'success', confirmText: @js(__('admin.fin_mark_paid')) });
            if (!ok) return;
            try {
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${r.subscription_id}/approve-payment`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (res.ok && d.success) {
                    const i = this.ledger.findIndex(x => x.id === r.id);
                    if (i > -1) this.ledger.splice(i, 1);
                    this.applyFinancials(d.financials);
                    window.showToast('success', d.message || @js(__('admin.fin_mark_paid_done')));
                } else {
                    window.showToast('error', d.message || @js(__('admin.fin_mark_paid_fail')));
                }
            } catch (e) { window.showToast('error', @js(__('admin.fin_mark_paid_fail'))); }
        },

        // ── delete confirm ──
        delOpen: false, delId: null, delRef: '',
        openDelete(id, ref) { this.delId = id; this.delRef = ref || ''; this.delOpen = true; },

        // ── refund ──
        rfOpen: false, rfTarget: null, rfBusy: false, rfProof: '', rfProofName: '',
        openRefund(t) { this.rfTarget = t; this.rfProof = ''; this.rfProofName = ''; this.rfOpen = true; },
        onRefundProof(e) {
            const f = e.target.files[0];
            if (!f) { this.rfProof = ''; this.rfProofName = ''; return; }
            this.rfProofName = f.name;
            const r = new FileReader();
            r.onload = ev => this.rfProof = ev.target.result;
            r.readAsDataURL(f);
        },
        async processRefund() {
            if (this.rfBusy || !this.rfTarget) return;
            this.rfBusy = true;
            try {
                const fd = new FormData();
                if (this.rfProof) fd.append('refund_proof_base64', this.rfProof);
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${this.rfTarget.subscription_id}/refund`, {
                    method: 'POST', body: fd,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (res.ok && d.success) {
                    // Prepend the refund transaction the server just created, drop the
                    // source row's refundable flag, and repaint the KPIs — no reload.
                    if (d.transaction) this.ledger.unshift(this.toRow(d.transaction));
                    const src = this.ledger.find(x => x.id === this.rfTarget.id);
                    if (src) src.refundable = false;
                    this.applyFinancials(d.financials);
                    window.showToast('success', d.message || @js(__('admin.fin_refund_done')));
                    this.rfOpen = false;
                } else {
                    window.showToast('error', d.message || @js(__('admin.fin_refund_fail')));
                }
            } catch (e) { window.showToast('error', @js(__('admin.fin_refund_fail'))); }
            finally { this.rfBusy = false; }
        },

        // ── recurring expenses ──
        async toggleRecurring(id) {
            try {
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/financials/recurring') }}/${id}/toggle`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ _method: 'PATCH' }),
                });
                const d = await res.json();
                if (res.ok && d.success) { window.showToast('success', d.message); return !!d.is_active; }
                window.showToast('error', d.message || @js(__('admin.club_facilities_add_unexpected_error')));
            } catch (e) { window.showToast('error', @js(__('admin.club_facilities_add_unexpected_error'))); }
            return null;
        },
        async deleteRecurring(id, label) {
            const ok = await window.confirmAction({ title: @js(__('admin.fin_delete_title')), message: label || '', type: 'danger', confirmText: @js(__('admin.fin_delete')) });
            if (!ok) return;
            try {
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/financials/recurring') }}/${id}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                const d = await res.json();
                if (res.ok && d.success) {
                    document.getElementById('recur-' + id)?.remove();
                    window.showToast('success', d.message);
                } else {
                    window.showToast('error', d.message || @js(__('admin.club_facilities_add_unexpected_error')));
                }
            } catch (e) { window.showToast('error', @js(__('admin.club_facilities_add_unexpected_error'))); }
        },

        /** Repaint every KPI from the server's recomputed summary. */
        applyFinancials(f) {
            const s = f && f.summary;
            if (!s) return;
            this.sum.net      = Number(s.net_profit ?? this.sum.net);
            this.sum.income   = Number(s.total_income ?? this.sum.income);
            this.sum.expenses = Number(s.total_expenses ?? this.sum.expenses);
            this.sum.refunds  = Number(s.refunds ?? this.sum.refunds);
            this.sum.collect  = Number(s.pending ?? this.sum.collect);
        },

        /** Server transaction payload → ledger row. */
        toRow(t) {
            const amt = Number(t.amount) || 0;
            const pmKey = t.payment_method ? (t.payment_method === 'bank_transfer' ? 'bank' : t.payment_method) : '';
            const label = t.description || (t.category || t.type);
            return {
                id: t.id, type: t.type, label: label, amount: amt,
                amount_fmt: '{{ $cur }} ' + amt.toFixed(2),
                month: (t.transaction_date || '').slice(0, 7) || this.currentMonth,
                items: [], items_summary: '',
                date_label: t.transaction_date || '', pm_label: pmKey ? (this.pmLabels[pmKey] || '') : '',
                search: String(label + ' ' + (t.category || '') + ' ' + t.type + ' ' + amt.toFixed(2)).toLowerCase(),
                description: t.description || '', transaction_date: null,
                category: t.category || '', payment_method: t.payment_method || 'cash',
                reference_number: t.reference_number || '',
                refundable: false, subscription_id: null, amount_paid: 0,
                ref: t.reference_number || label,
            };
        },
        pmLabels: {
            cash:  @js(__('admin.fin_pm_cash')),
            bank:  @js(__('admin.fin_pm_bank')),
            card:  @js(__('admin.fin_pm_card')),
            other: @js(__('admin.fin_pm_other')),
        },

        // ── month scope (hero + ledger both read the selected month) ──
        selectedMonth: @js($currentMonth),          // 'YYYY-MM'
        currentMonth: @js($currentMonth),           // this calendar month — the cap for "next"
        /** Rows dated within the selected month (pending rows carry their enrolment month). */
        get monthLedger() { return this.ledger.filter(r => r.month === this.selectedMonth); },
        /** Hero figures, recomputed live from the selected month's rows. */
        get monthSum() {
            let income = 0, expenses = 0, refunds = 0;
            this.monthLedger.forEach(r => {
                if (r.type === 'income')  income   += Number(r.amount) || 0;
                else if (r.type === 'expense') expenses += Number(r.amount) || 0;
                else if (r.type === 'refund')  refunds  += Number(r.amount) || 0;
            });
            const flow = income + expenses;
            return {
                income, expenses, refunds,
                net: income - expenses - refunds,
                incomeShare: flow > 0 ? Math.round(income / flow * 1000) / 10 : 0,
            };
        },
        /** 'YYYY-MM' → localised 'Month YYYY' for the hero stepper. */
        get monthLabel() {
            const [y, m] = this.selectedMonth.split('-').map(Number);
            return new Date(y, (m || 1) - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },
        get canGoNextMonth() { return this.selectedMonth < this.currentMonth; },
        shiftMonth(delta) {
            const [y, m] = this.selectedMonth.split('-').map(Number);
            const d = new Date(y, (m - 1) + delta, 1);
            const next = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            if (next > this.currentMonth) return;   // never step into the future
            this.selectedMonth = next;
            this.txFilter = 'all';                  // avoid landing on an empty filter for the new month
        },

        // ── ledger filter / search ──
        txFilter: 'all',
        txSearch: '',
        ledger: @js($ledger),
        get pendingRows() { return this.ledger.filter(r => r.type === 'pending'); },
        get counts() {
            const c = { all: this.monthLedger.length, pending: 0, income: 0, expense: 0, refund: 0 };
            this.monthLedger.forEach(r => { if (c[r.type] !== undefined) c[r.type]++; });
            return c;
        },
        get filteredLedger() {
            const q = this.txSearch.trim().toLowerCase();
            return this.monthLedger.filter(r =>
                (this.txFilter === 'all' || this.txFilter === r.type) &&
                (q === '' || r.search.includes(q))
            );
        },
    };
}
</script>
</div>
@endsection
