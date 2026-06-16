@props([
    'currency'  => 'BHD',
    'eventName' => 'open-transactions-modal',
])

{{--
    Reusable transactions modal.
    Open it from anywhere with:
        window.dispatchEvent(new CustomEvent('open-transactions-modal', {
            detail: { label: 'Mar 2026', transactions: [...], currency: 'BHD' }
        }));
    Each transaction: { id, type(income|expense|refund), description, amount,
                        transaction_date, category, payment_method, member_name, member_avatar }
    Include once per page: <x-transactions-modal :currency="$currency" />
--}}
<div x-data="transactionsModal(@js($currency))"
     x-on:{{ $eventName }}.window="open($event.detail)"
     x-show="show" x-cloak
     class="fixed inset-0 z-[80] overflow-y-auto"
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @keydown.escape.window="close()">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="close()"></div>

    <div class="flex min-h-full items-center justify-center p-3 sm:p-4">
        <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]"
             @click.stop
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            {{-- ── Header: gradient band with month + live summary ── --}}
            <div class="relative px-5 sm:px-6 pt-5 pb-4 text-white shrink-0"
                 style="background-image: linear-gradient(135deg, hsl(250 65% 60%) 0%, hsl(258 60% 52%) 100%);">
                <div class="pointer-events-none absolute inset-0 opacity-[0.08]"
                     style="background-image:radial-gradient(circle at 1px 1px,#fff 1px,transparent 0);background-size:18px 18px;"></div>

                <div class="relative flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-white/70 flex items-center gap-1.5">
                            <i class="bi bi-calendar3"></i> Transactions
                        </p>
                        <h3 class="text-xl sm:text-2xl font-bold tracking-tight mt-0.5" x-text="label"></h3>
                        <p class="text-xs text-white/70 mt-0.5">
                            <span x-text="filtered.length"></span> of <span x-text="items.length"></span>
                            <span x-text="items.length === 1 ? 'transaction' : 'transactions'"></span>
                        </p>
                    </div>
                    <button type="button" @click="close()"
                            class="w-9 h-9 -mr-1 rounded-full flex items-center justify-center text-white/80 hover:text-white hover:bg-white/15 transition-colors">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Summary tiles --}}
                <div class="relative grid grid-cols-3 gap-2 mt-4">
                    <div class="rounded-xl bg-white/12 backdrop-blur px-3 py-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">In</p>
                        <p class="text-sm font-bold tabular-nums truncate"><span x-text="cur"></span> <span x-text="fmt(totals.income)"></span></p>
                    </div>
                    <div class="rounded-xl bg-white/12 backdrop-blur px-3 py-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-white/65">Out</p>
                        <p class="text-sm font-bold tabular-nums truncate"><span x-text="cur"></span> <span x-text="fmt(totals.outflow)"></span></p>
                    </div>
                    <div class="rounded-xl px-3 py-2"
                         :class="totals.net >= 0 ? 'bg-emerald-400/25' : 'bg-rose-500/30'">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-white/75">Net</p>
                        <p class="text-sm font-bold tabular-nums truncate">
                            <span x-text="(totals.net < 0 ? '−' : '') + cur"></span> <span x-text="fmt(Math.abs(totals.net))"></span>
                        </p>
                    </div>
                </div>
            </div>

            {{-- ── Filter pills ── --}}
            <div class="px-5 sm:px-6 py-3 border-b border-gray-100 flex items-center gap-2 overflow-x-auto shrink-0">
                <template x-for="seg in segments" :key="seg.key">
                    <button type="button" @click="activeType = seg.key"
                            x-show="seg.key === 'all' || seg.always || counts[seg.key] > 0"
                            :class="activeType === seg.key ? seg.activeClass : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors">
                        <span x-text="seg.label"></span>
                        <span class="px-1.5 rounded-full bg-black/10 text-[10px]" x-text="seg.key === 'all' ? items.length : counts[seg.key]"></span>
                    </button>
                </template>
            </div>

            {{-- ── List ── --}}
            <div class="overflow-y-auto px-3 sm:px-4 py-3 flex-1">
                {{-- Empty --}}
                <template x-if="filtered.length === 0">
                    <div class="text-center py-12 text-muted-foreground">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gray-100 flex items-center justify-center">
                            <i class="bi bi-receipt text-2xl text-gray-300"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-500">No transactions to show</p>
                    </div>
                </template>

                <div class="space-y-1.5">
                    <template x-for="(t, i) in filtered" :key="t.id">
                        <div class="group flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors"
                             :style="`animation: txRow .35s cubic-bezier(.22,1,.36,1) both; animation-delay:${i * 0.03}s`">

                            {{-- Type glyph --}}
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="meta(t.type).bg">
                                <i class="bi text-base" :class="meta(t.type).icon + ' ' + meta(t.type).text"></i>
                            </div>

                            {{-- Body --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <template x-if="t.member_avatar">
                                        <img :src="t.member_avatar" class="w-5 h-5 rounded-full object-cover shrink-0">
                                    </template>
                                    <p class="font-medium text-sm text-gray-900 truncate" x-text="t.member_name || t.description || '—'"></p>
                                </div>
                                <p class="text-xs text-muted-foreground truncate mt-0.5">
                                    <span x-show="t.member_name" x-text="(t.description || '—')"></span>
                                    <span x-show="t.member_name" class="text-gray-300">·</span>
                                    <span x-text="t.transaction_date"></span>
                                    <template x-if="t.category">
                                        <span><span class="text-gray-300">·</span> <span class="capitalize" x-text="t.category"></span></span>
                                    </template>
                                    <template x-if="t.payment_method">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="text-gray-300">·</span>
                                            <i class="bi text-[10px]" :class="payIcon(t.payment_method)"></i>
                                            <span class="capitalize" x-text="t.payment_method.replace('_',' ')"></span>
                                        </span>
                                    </template>
                                </p>
                            </div>

                            {{-- Amount --}}
                            <div class="text-right shrink-0">
                                <p class="font-bold text-sm tabular-nums whitespace-nowrap" :class="meta(t.type).text"
                                   x-text="meta(t.type).sign + ' ' + cur + ' ' + fmt(t.amount)"></p>
                                <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold capitalize"
                                      :class="meta(t.type).chip" x-text="meta(t.type).label"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ── Footer ── --}}
            <div class="px-5 sm:px-6 py-3 border-t border-gray-100 flex items-center justify-between gap-3 bg-gray-50/60 shrink-0">
                <p class="text-xs text-muted-foreground">
                    Net for period:
                    <span class="font-bold" :class="totals.net >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        <span x-text="(totals.net < 0 ? '−' : '') + cur"></span> <span x-text="fmt(Math.abs(totals.net))"></span>
                    </span>
                </p>
                <button type="button" @click="close()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    @keyframes txRow { from { opacity:0; transform: translateY(6px); } to { opacity:1; transform: translateY(0); } }
</style>
@endpush
@push('scripts')
<script>
    function transactionsModal(defaultCurrency) {
        return {
            show: false,
            label: '',
            cur: defaultCurrency || 'BHD',
            items: [],
            activeType: 'all',

            segments: [
                { key: 'all',             label: 'All',             activeClass: 'bg-primary text-white' },
                { key: 'income',          label: 'Income',          activeClass: 'bg-emerald-500 text-white' },
                { key: 'expense',         label: 'Expenses',        activeClass: 'bg-red-500 text-white' },
                { key: 'refund',          label: 'Refunds',         activeClass: 'bg-orange-500 text-white', always: true },
                { key: 'cash_to_collect', label: 'Cash to Collect', activeClass: 'bg-amber-500 text-white', always: true },
            ],

            open(detail) {
                detail = detail || {};
                this.label = detail.label || 'Transactions';
                this.items = Array.isArray(detail.transactions) ? detail.transactions : [];
                if (detail.currency) this.cur = detail.currency;
                this.activeType = 'all';
                this.show = true;
                document.body.style.overflow = 'hidden';
            },
            close() {
                this.show = false;
                document.body.style.overflow = '';
            },

            get filtered() {
                return this.activeType === 'all'
                    ? this.items
                    : this.items.filter(t => t.type === this.activeType);
            },
            get counts() {
                return {
                    income:          this.items.filter(t => t.type === 'income').length,
                    expense:         this.items.filter(t => t.type === 'expense').length,
                    refund:          this.items.filter(t => t.type === 'refund').length,
                    cash_to_collect: this.items.filter(t => t.type === 'cash_to_collect').length,
                };
            },
            get totals() {
                let income = 0, expense = 0, refund = 0;
                for (const t of this.items) {
                    const a = parseFloat(t.amount) || 0;
                    if (t.type === 'income')  income  += a;
                    else if (t.type === 'expense') expense += a;
                    else if (t.type === 'refund')  refund  += a;
                }
                return { income, expense, refund, outflow: expense + refund, net: income - expense - refund };
            },

            fmt(n) {
                return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            meta(type) {
                const m = {
                    income:          { label: 'Income',          icon: 'bi-arrow-down-left',       text: 'text-emerald-600', bg: 'bg-emerald-50', chip: 'bg-emerald-100 text-emerald-700', sign: '+' },
                    expense:         { label: 'Expense',         icon: 'bi-arrow-up-right',         text: 'text-red-600',     bg: 'bg-red-50',     chip: 'bg-red-100 text-red-700',         sign: '−' },
                    refund:          { label: 'Refund',          icon: 'bi-arrow-counterclockwise', text: 'text-orange-600',  bg: 'bg-orange-50',  chip: 'bg-orange-100 text-orange-700',   sign: '−' },
                    cash_to_collect: { label: 'Cash to Collect', icon: 'bi-hourglass-split',        text: 'text-amber-600',   bg: 'bg-amber-50',   chip: 'bg-amber-100 text-amber-700',     sign: '' },
                };
                return m[type] || m.expense;
            },
            payIcon(method) {
                const m = { cash: 'bi-cash-stack', bank_transfer: 'bi-bank', card: 'bi-credit-card', online: 'bi-globe' };
                return m[method] || 'bi-three-dots';
            },
        };
    }
</script>
@endpush
@endonce
