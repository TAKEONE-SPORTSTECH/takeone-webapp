@props([
    'monthlyData'         => [],
    'transactions'        => collect(),
    'currency'            => 'BHD',
    'canvasId'            => 'financialChart',
    'maintainAspectRatio' => true,
    'canvasHeightAttr'    => null,
    'containerClass'      => '',
])

@php $wrapperId = 'financialChartWrap_' . $canvasId; @endphp

<div id="{{ $wrapperId }}" x-data="{
    showMonthModal: false,
    monthModalLabel: '',
    monthModalTransactions: [],
    openMonthModal(label, transactions) {
        this.monthModalLabel = label;
        this.monthModalTransactions = transactions;
        this.showMonthModal = true;
    }
}">

    {{-- Chart Card --}}
    <div class="card border-0 shadow-sm mb-6">
        <div class="card-header bg-white border-0">
            <h5 class="card-title mb-0 font-semibold">Financial Overview (Last 12 Months)</h5>
            <p class="text-sm text-muted-foreground mb-0">Monthly income, expenses, and profit trends</p>
        </div>
        <div class="card-body">
            @if($containerClass)
            <div class="{{ $containerClass }}">
                <canvas id="{{ $canvasId }}"{{ $canvasHeightAttr !== null ? ' height="'.$canvasHeightAttr.'"' : '' }}></canvas>
            </div>
            @else
            <canvas id="{{ $canvasId }}"{{ $canvasHeightAttr !== null ? ' height="'.$canvasHeightAttr.'"' : '' }}></canvas>
            @endif
        </div>
    </div>

    {{-- Month Transactions Modal --}}
    <div x-show="showMonthModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showMonthModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-3xl relative rounded-lg overflow-hidden" @click.stop>
                <div class="modal-header border-b px-6 py-4">
                    <div>
                        <h5 class="modal-title font-bold flex items-center gap-2">
                            <i class="bi bi-calendar3 text-primary"></i>
                            Transactions — <span x-text="monthModalLabel"></span>
                        </h5>
                        <p class="text-sm text-muted-foreground mb-0" x-text="monthModalTransactions.length + ' transaction(s) found'"></p>
                    </div>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showMonthModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body px-6 py-4">
                    <template x-if="monthModalTransactions.length === 0">
                        <div class="text-center py-10 text-muted-foreground">
                            <i class="bi bi-receipt text-5xl mb-3 block"></i>
                            <p>No transactions recorded for this month.</p>
                        </div>
                    </template>
                    <template x-if="monthModalTransactions.length > 0">
                        <div class="overflow-x-auto">
                            <table class="table table-hover mb-0">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th class="text-right">Amount</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="t in monthModalTransactions" :key="t.id">
                                        <tr>
                                            <td class="whitespace-nowrap text-sm" x-text="t.transaction_date"></td>
                                            <td>
                                                <span x-show="t.type === 'income'" class="badge bg-green-100 text-green-700">Income</span>
                                                <span x-show="t.type === 'expense'" class="badge bg-red-100 text-red-700">Expense</span>
                                                <span x-show="t.type === 'refund'" class="badge bg-orange-100 text-orange-700">Refund</span>
                                            </td>
                                            <td>
                                                <template x-if="t.member_name">
                                                    <div class="flex items-center gap-2">
                                                        <template x-if="t.member_avatar">
                                                            <img :src="t.member_avatar" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                                        </template>
                                                        <template x-if="!t.member_avatar">
                                                            <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                                <i class="bi bi-person text-xs text-primary"></i>
                                                            </div>
                                                        </template>
                                                        <div>
                                                            <div class="text-xs text-muted-foreground leading-tight" x-text="t.member_name"></div>
                                                            <div x-text="t.description || '—'"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!t.member_name">
                                                    <span x-text="t.description || '—'"></span>
                                                </template>
                                            </td>
                                            <td class="text-sm text-muted-foreground" x-text="t.category || '—'"></td>
                                            <td class="text-right font-semibold whitespace-nowrap"
                                                :class="t.type === 'income' ? 'text-green-600' : 'text-red-600'"
                                                x-text="(t.type === 'income' ? '+' : '-') + ' {{ $currency }} ' + parseFloat(t.amount).toFixed(2)">
                                            </td>
                                            <td class="text-sm text-muted-foreground" x-text="t.payment_method ? t.payment_method.replace('_', ' ') : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div class="modal-footer px-6 py-3 border-t flex justify-end">
                    <button type="button" class="btn btn-outline-secondary" @click="showMonthModal = false">Close</button>
                </div>
            </div>
        </div>
    </div>

</div>

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
@endonce

@push('scripts')
<script>
(function() {
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
        member_name: @json($t->subscription?->user?->full_name ?? $t->subscription?->user?->name ?? ''),
        member_avatar: @json($t->subscription?->user?->profile_picture ? asset('storage/' . $t->subscription->user->profile_picture) : ''),
    };
    @endforeach

    document.addEventListener('DOMContentLoaded', function() {
        const chartEl = document.getElementById('{{ $canvasId }}');
        if (!chartEl) return;
        const monthlyData = @json($monthlyData);
        new Chart(chartEl, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'Income',
                        data: monthlyData.map(d => d.income),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Expenses',
                        data: monthlyData.map(d => d.expenses),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Refunds',
                        data: monthlyData.map(d => d.refunds ?? 0),
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Profit',
                        data: monthlyData.map(d => d.profit),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Cash to Collect',
                        data: monthlyData.map(d => d.cash_to_collect ?? 0),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: {{ $maintainAspectRatio ? 'true' : 'false' }},
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': {{ $currency }} ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '{{ $currency }} ' + value.toLocaleString();
                            }
                        }
                    }
                },
                onClick: function(event, elements, chart) {
                    const points = chart.getElementsAtEventForMode(event.native, 'index', { intersect: false }, true);
                    if (points.length === 0) return;
                    const index = points[0].index;
                    const { year_month } = monthlyData[index];
                    const [year, mon] = year_month.split('-');
                    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const shortMonth = monthNames[parseInt(mon) - 1];
                    const txns = Object.values(txData).filter(t => {
                        if (!t.transaction_date) return false;
                        return t.transaction_date.startsWith(shortMonth) && t.transaction_date.endsWith(year);
                    });
                    const wrapper = document.getElementById('{{ $wrapperId }}');
                    Alpine.$data(wrapper).openMonthModal(shortMonth + ' ' + year, txns);
                }
            }
        });
    });
})();
</script>
@endpush
