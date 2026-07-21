@props([
    'monthlyData'         => [],
    'transactions'        => collect(),
    'cashToCollect'       => collect(),
    'currency'            => 'BHD',
    'canvasId'            => 'financialChart',
    'maintainAspectRatio' => true,   // kept for backwards-compat; the chart always uses a fixed-height box
    'canvasHeightAttr'    => null,
    'containerClass'      => '',
])

@php
    $wrapperId  = 'financialChartWrap_' . $canvasId;
    $clickEvent = 'financialChartClick_' . $canvasId;

    $col = fn ($key) => array_map(fn ($d) => $d[$key] ?? 0, $monthlyData);

    $chartLabels   = array_map(fn ($d) => $d['month'] ?? '', $monthlyData);
    $chartDatasets = [
        ['label' => 'Income',          'data' => $col('income'),          'color' => '#10b981', 'fill' => true],
        ['label' => 'Expenses',        'data' => $col('expenses'),        'color' => '#ef4444', 'fill' => true],
        ['label' => 'Profit',          'data' => $col('profit'),          'color' => '#8b5cf6', 'fill' => true, 'borderWidth' => 3],
        ['label' => 'Refunds',         'data' => $col('refunds'),         'color' => '#f97316', 'dashed' => true, 'hidden' => true],
        ['label' => 'Cash to Collect', 'data' => $col('cash_to_collect'), 'color' => '#f59e0b', 'dashed' => true, 'hidden' => true],
    ];
@endphp

<div id="{{ $wrapperId }}">

    {{-- Reusable chart component does all the rendering --}}
    <x-chart
        :id="$canvasId"
        type="line"
        :labels="$chartLabels"
        :datasets="$chartDatasets"
        :height="$canvasHeightAttr !== null ? (int) $canvasHeightAttr : 320"
        :value-prefix="$currency.' '"
        :legend="true"
        :click-event="$clickEvent"
        title="Financial Overview"
        subtitle="{{ now()->year }} · income, expenses &amp; profit"
        badge="{{ now()->year }}"
        hint="Click any month on the chart to view its transactions"
        :container-class="$containerClass"
    />

    {{-- Reusable transactions modal (opened from the chart click below) --}}
    @once
        <x-transactions-modal :currency="$currency" />
    @endonce
</div>

@push('scripts')
<script>
(function () {
    const txData = {};
    @foreach($transactions as $t)
    txData[{{ $t->id }}] = {
        id: {{ $t->id }},
        type: @json($t->type),
        description: @json($t->description ?? ''),
        amount: {{ $t->amount }},
        transaction_date: @json($t->transaction_date ? $t->transaction_date->format('M d, Y') : ''),
        category: @json($t->category ?? ''),
        payment_method: @json($t->payment_method ?? ''),
        member_name: @json($t->subscription?->user?->full_name ?? $t->subscription?->user?->name ?? $t->user?->full_name ?? $t->user?->name ?? ''),
        member_avatar: @json(($t->subscription?->user?->profile_picture ?? $t->user?->profile_picture) ? asset('storage/' . ($t->subscription?->user?->profile_picture ?? $t->user->profile_picture)) : ''),
    };
    @endforeach

    // Cash to Collect — unpaid / pending subscriptions (not real transactions, surfaced here for the modal)
    @foreach($cashToCollect as $s)
    txData["ctc-{{ $s->id }}"] = {
        id: "ctc-{{ $s->id }}",
        type: 'cash_to_collect',
        description: @json($s->package?->name ? $s->package->name . ' — outstanding' : 'Outstanding balance'),
        amount: {{ (float) $s->amount_due }},
        transaction_date: @json($s->start_date ? \Illuminate\Support\Carbon::parse($s->start_date)->format('M d, Y') : ''),
        category: 'subscription',
        payment_method: '',
        member_name: @json($s->user?->full_name ?? $s->user?->name ?? ''),
        member_avatar: @json($s->user?->profile_picture ? asset('storage/' . $s->user->profile_picture) : ''),
    };
    @endforeach

    const monthlyData = @json($monthlyData);
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // When a month is clicked on the chart, open the transactions modal for that month.
    window.addEventListener(@json($clickEvent), function (e) {
        if (!e.detail || e.detail.id !== @json($canvasId)) return;
        const md = monthlyData[e.detail.index];
        if (!md || !md.year_month) return;
        const [year, mon] = md.year_month.split('-');
        const shortMonth = monthNames[parseInt(mon) - 1];
        const txns = Object.values(txData).filter(t =>
            t.transaction_date && t.transaction_date.startsWith(shortMonth) && t.transaction_date.endsWith(year)
        );
        window.dispatchEvent(new CustomEvent('open-transactions-modal', {
            detail: { label: shortMonth + ' ' + year, transactions: txns, currency: @json($currency) }
        }));
    });
})();
</script>
@endpush
