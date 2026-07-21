@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_orders'))

@section('club-admin-content')
@php
    $ordersJs = $orders->map(fn ($o) => [
        'id' => $o->id, 'reference' => $o->reference, 'status' => $o->status,
        'total' => (float) $o->total, 'currency' => $o->currency, 'date' => $o->created_at->format('M j · g:i A'),
        'month' => $o->created_at->format('Y-m'),
        'new' => ($lastSeen ?? null) ? $o->created_at->gt(\Illuminate\Support\Carbon::parse($lastSeen)) : true,
        'customer' => $o->user->full_name ?? __('admin.fin_member'), 'hasDropship' => (bool) $o->has_dropship,
        'proof' => $o->paymentProofUrl(),
        'items' => $o->items->map(fn ($it) => ['name' => $it->name, 'qty' => $it->qty, 'image' => $it->image_path ? asset('storage/'.$it->image_path) : null])->values(),
    ])->values();
    $cur = $club->currency ?: 'BHD';
    $currentMonth = date('Y-m');   // hero stats + list default to this month; the stepper changes it
@endphp
<div x-data="clubOrdersM({{ Illuminate\Support\Js::from($ordersJs) }})" class="-mx-4 -mt-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_orders') }}</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-bag-check-fill text-xl m-float"></i>
            </div>
        </div>

        {{-- Month scope — the stats + list read the selected month; step back to review past
             months, capped at the current one (no future). --}}
        <div class="flex items-center justify-between gap-2 mt-4 relative z-10">
            <span class="text-[11px] uppercase tracking-wide text-white/70 flex items-center gap-1.5"><i class="bi bi-calendar3"></i>{{ __('admin.fin_reports') }}</span>
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

        <div class="flex gap-2 mt-3 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" x-text="monthStats.pending"></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_pending') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" x-text="monthStats.fulfilled"></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_fulfilled') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none"><span x-text="fmt0(monthStats.revenue)"></span><span class="text-[10px] font-bold ml-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_revenue') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 -mt-6 relative z-10 space-y-4 mobile-stagger">

    {{-- Filter (segmented) --}}
    <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex">
        <template x-for="f in filters" :key="f">
            <button type="button" @click="setFilter(f)"
                    class="m-press relative flex-1 py-2.5 rounded-xl text-xs font-bold transition-colors"
                    :class="filter === f ? 'bg-primary text-white' : 'text-muted-foreground'">
                <span x-text="label(f)"></span>
                <span x-show="unseenFor(f) > 0" x-cloak x-text="unseenFor(f)"
                      class="absolute -top-1 -end-1 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-black leading-none inline-flex items-center justify-center shadow-sm ring-2 ring-white"></span>
            </button>
        </template>
    </div>

    <template x-if="shown.length === 0">
        <div class="m-card p-8 text-center"><i class="bi bi-bag-check text-3xl text-gray-300 m-float"></i><p class="text-sm text-muted-foreground mt-2">{{ __('market.no_orders_club') }}</p></div>
    </template>

    <template x-for="o in visible" :key="o.id">
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-black text-foreground text-sm">#<span x-text="o.reference"></span></p>
                    <p class="text-[11px] text-muted-foreground truncate"><span x-text="o.customer"></span> · <span x-text="o.date"></span></p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex-shrink-0" :class="badgeClass(o.status)" x-text="label(o.status)"></span>
            </div>

            <div class="mt-2.5 space-y-1.5">
                <template x-for="(it, idx) in o.items" :key="idx">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg grid place-items-center overflow-hidden bg-muted flex-shrink-0">
                            <template x-if="it.image"><img :src="it.image" alt="" class="w-8 h-8 object-cover"></template>
                            <template x-if="!it.image"><i class="bi bi-bag text-muted-foreground text-sm"></i></template>
                        </span>
                        <p class="text-[12px] text-foreground truncate flex-1"><span x-text="it.name"></span> <span class="text-muted-foreground" x-text="'×' + it.qty"></span></p>
                    </div>
                </template>
            </div>

            {{-- payment proof --}}
            <button type="button" x-show="o.proof" @click="openProof(o.proof)" class="mt-2.5 flex items-center gap-2 w-full text-left">
                <span class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0"><img :src="o.proof" alt="" class="w-full h-full object-cover"></span>
                <span class="text-[11px]"><span class="font-semibold text-foreground flex items-center gap-1"><i class="bi bi-receipt text-primary"></i> {{ __('market.payment_proof') }}</span><span class="text-primary">{{ __('market.view_proof') }}</span></span>
            </button>
            <p class="mt-2 text-[11px] text-muted-foreground flex items-center gap-1" x-show="!o.proof"><i class="bi bi-exclamation-circle"></i> {{ __('market.no_proof') }}</p>

            <div class="mt-2.5 pt-2.5 border-t border-gray-100 flex items-center justify-between">
                <span class="text-base font-black text-foreground" x-text="o.currency + ' ' + o.total.toFixed(2)"></span>
                <div class="flex gap-2 items-center">
                    <button type="button" x-show="o.status === 'pending' || o.status === 'confirmed'" @click="confirmFulfill(o)" class="m-press px-3 py-1.5 rounded-lg text-[11px] font-bold bg-green-600 text-white">{{ __('market.mark_fulfilled') }}</button>
                    <button type="button" x-show="o.status === 'pending' || o.status === 'confirmed'" @click="setStatus(o,'cancelled')" class="m-press px-3 py-1.5 rounded-lg text-[11px] font-bold border border-red-200 text-red-600">{{ __('shared.cancel') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Show more --}}
    <button type="button" x-show="shown.length > limit" @click="limit += perPage"
            class="m-press w-full flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 text-primary text-sm font-semibold py-2.5">
        <span x-text="'{{ __('market.show_more') }}' + ' (' + (shown.length - limit) + ')'"></span><i class="bi bi-chevron-down"></i>
    </button>
    </div>{{-- /content --}}

    {{-- Proof viewer (teleported so the fixed overlay escapes the shell transform) --}}
    <template x-teleport="body">
        <div x-show="proofView" x-cloak class="fixed inset-0 z-[80] flex flex-col" @keydown.escape.window="closeProof()"
             style="display:none; background: rgba(8,6,18,.94); backdrop-filter: blur(4px);"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
                <span class="text-sm font-semibold">{{ __('market.payment_proof') }}</span>
                <button type="button" @click="closeProof()" class="w-9 h-9 rounded-full grid place-items-center hover:bg-white/10"><i class="bi bi-x-lg text-xl"></i></button>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.self="closeProof()">
                <img :src="proofView" alt="" class="max-w-full max-h-full object-contain rounded-lg">
            </div>
        </div>
    </template>
</div>

<script>
function clubOrdersM(orders) {
    return {
        orders: orders, filter: 'pending',
        filters: ['pending', 'confirmed', 'fulfilled', 'cancelled'],
        perPage: 8, limit: 8,
        setFilter(f) { this.filter = f; this.limit = this.perPage; },
        statusBase: @js(url('/admin/club/'.$club->slug.'/orders')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',

        // ── month scope (stats + list read the selected month) ──
        selectedMonth: @js($currentMonth),
        currentMonth: @js($currentMonth),
        get monthOrders() { return this.orders.filter(o => o.month === this.selectedMonth); },
        get monthStats() {
            let pending = 0, fulfilled = 0, revenue = 0;
            this.monthOrders.forEach(o => {
                if (o.status === 'pending') pending++;
                if (o.status === 'fulfilled' || o.status === 'received') fulfilled++;
                if (o.status === 'confirmed' || o.status === 'fulfilled' || o.status === 'received') revenue += Number(o.total) || 0;
            });
            return { pending, fulfilled, revenue };
        },
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
            this.limit = this.perPage;
        },
        fmt0(n) { return Math.round(Number(n) || 0).toLocaleString(); },

        labels: { all: @js(__('admin.all')), pending: @js(__('market.status_pending')), confirmed: @js(__('market.status_confirmed')), fulfilled: @js(__('market.status_fulfilled')), received: @js(__('market.status_received')), cancelled: @js(__('market.status_cancelled')) },
        label(s) { return this.labels[s] || s; },
        {{-- Scoped to the selected month. "Fulfilled" (sold) also covers orders the buyer has
             since confirmed received — receipt is a customer-side step, not a separate seller stage. --}}
        get shown() { return this.monthOrders.filter(o => this.filter === 'fulfilled' ? (o.status === 'fulfilled' || o.status === 'received') : o.status === this.filter); },
        get visible() { return this.shown.slice(0, this.limit); },
        {{-- Unseen count per tab = orders in that status this month that arrived since the
             owner last opened this page (server-flagged o.new). Cleared on the next visit. --}}
        unseenFor(f) {
            return this.monthOrders.filter(o => o.new
                && (f === 'fulfilled' ? (o.status === 'fulfilled' || o.status === 'received') : o.status === f)).length;
        },
        proofView: null,
        openProof(url) { this.proofView = url; },
        closeProof() { this.proofView = null; },
        badgeClass(s) { return ({ pending: 'bg-amber-50 text-amber-700', confirmed: 'bg-blue-50 text-blue-700', fulfilled: 'bg-green-50 text-green-700', received: 'bg-green-50 text-green-700', cancelled: 'bg-gray-100 text-gray-500' })[s] || 'bg-muted'; },
        async confirmFulfill(o) {
            const ok = await window.confirmAction({
                title: @js(__('market.confirm_fulfill_title')),
                message: @js(__('market.confirm_fulfill_message')),
                type: 'info',
                confirmText: @js(__('market.confirm_fulfill_yes')),
            });
            if (ok) this.setStatus(o, 'fulfilled');
        },
        async setStatus(o, status) {
            const prev = o.status; o.status = status;
            try {
                const res = await fetch(`${this.statusBase}/${o.id}/status`, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ status }) });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                window.showToast && window.showToast('success', d.message || @js(__('market.order_status_updated')));
            } catch (e) { o.status = prev; window.showToast && window.showToast('error', e.message); }
        },
    };
}
</script>
@endsection
