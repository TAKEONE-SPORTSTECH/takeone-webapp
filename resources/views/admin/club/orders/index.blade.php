@extends('layouts.admin-club')

@section('club-admin-content')
@php
    $ordersJs = $orders->map(fn ($o) => [
        'id' => $o->id, 'reference' => $o->reference, 'status' => $o->status,
        'total' => (float) $o->total, 'currency' => $o->currency,
        'time' => $o->created_at->diffForHumans(), 'date' => $o->created_at->format('M j, Y · g:i A'),
        'new' => ($lastSeen ?? null) ? $o->created_at->gt(\Illuminate\Support\Carbon::parse($lastSeen)) : true,
        'customer' => $o->user->full_name ?? __('admin.fin_member'),
        'hasDropship' => (bool) $o->has_dropship,
        'proof' => $o->paymentProofUrl(),
        'items' => $o->items->map(fn ($it) => [
            'name' => $it->name, 'qty' => $it->qty, 'price' => (float) $it->price, 'fulfillment' => $it->fulfillment,
            'image' => $it->image_path ? asset('storage/'.$it->image_path) : null,
        ])->values(),
    ])->values();
    $cur = $club->currency ?: 'BHD';
@endphp
<div x-data="clubOrders({{ Illuminate\Support\Js::from($ordersJs) }})"
     class="space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="tf-section-title">{{ __('market.orders_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('market.orders_subtitle') }}</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"><p class="text-xs text-muted-foreground">{{ __('market.stat_pending') }}</p><p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['pending'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"><p class="text-xs text-muted-foreground">{{ __('market.stat_confirmed') }}</p><p class="text-2xl font-bold text-blue-600 mt-1">{{ $stats['confirmed'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"><p class="text-xs text-muted-foreground">{{ __('market.stat_fulfilled') }}</p><p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['fulfilled'] }}</p></div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"><p class="text-xs text-muted-foreground">{{ __('market.stat_revenue') }}</p><p class="text-2xl font-bold text-gray-900 mt-1">{{ $cur }} {{ number_format($stats['revenue'], 2) }}</p></div>
    </div>

    {{-- Filter (segmented) --}}
    <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex max-w-md">
        <template x-for="f in filters" :key="f">
            <button type="button" @click="setFilter(f)"
                    class="relative flex-1 py-2 rounded-xl text-xs font-bold transition-colors"
                    :class="filter === f ? 'bg-primary text-white' : 'text-muted-foreground hover:text-foreground'">
                <span x-text="label(f)"></span>
                <span x-show="unseenFor(f) > 0" x-cloak x-text="unseenFor(f)"
                      class="absolute -top-1 -end-1 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-black leading-none inline-flex items-center justify-center shadow-sm ring-2 ring-white"></span>
            </button>
        </template>
    </div>

    {{-- Empty --}}
    <template x-if="shown.length === 0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-16 text-center">
            <i class="bi bi-bag-check text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('market.no_orders_club') }}</p>
        </div>
    </template>

    {{-- Orders --}}
    <div class="space-y-3" x-show="shown.length">
        <template x-for="o in visible" :key="o.id">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-black text-gray-900">#<span x-text="o.reference"></span></p>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="badgeClass(o.status)" x-text="label(o.status)"></span>
                            <template x-if="o.hasDropship"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-600 inline-flex items-center gap-1"><i class="bi bi-truck"></i> {{ __('market.dropship_badge') }}</span></template>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ __('market.order_by', ['name' => '']) }}<span class="font-medium text-foreground" x-text="o.customer"></span> · <span x-text="o.date"></span></p>
                    </div>
                    <p class="text-lg font-black text-gray-900 flex-shrink-0" x-text="o.currency + ' ' + o.total.toFixed(2)"></p>
                </div>

                {{-- items --}}
                <div class="mt-3 flex flex-wrap gap-3">
                    <template x-for="(it, idx) in o.items" :key="idx">
                        <div class="flex items-center gap-2 bg-muted/60 rounded-lg pr-3">
                            <span class="w-9 h-9 rounded-lg grid place-items-center overflow-hidden bg-white">
                                <template x-if="it.image"><img :src="it.image" alt="" class="w-9 h-9 object-cover"></template>
                                <template x-if="!it.image"><i class="bi bi-bag text-muted-foreground"></i></template>
                            </span>
                            <span class="text-xs">
                                <span class="font-semibold text-foreground" x-text="it.name"></span>
                                <span class="block text-muted-foreground" x-text="'×' + it.qty"></span>
                            </span>
                        </div>
                    </template>
                </div>

                {{-- payment proof --}}
                <div class="mt-3 flex items-center gap-3" x-show="o.proof">
                    <button type="button" @click="openProof(o.proof)" class="group relative w-16 h-16 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0">
                        <img :src="o.proof" alt="" class="w-full h-full object-cover">
                        <span class="absolute inset-0 bg-black/0 group-hover:bg-black/30 grid place-items-center transition-colors">
                            <i class="bi bi-zoom-in text-white opacity-0 group-hover:opacity-100"></i>
                        </span>
                    </button>
                    <div class="text-xs">
                        <p class="font-semibold text-foreground flex items-center gap-1"><i class="bi bi-receipt text-primary"></i> {{ __('market.payment_proof') }}</p>
                        <button type="button" @click="openProof(o.proof)" class="text-primary hover:underline">{{ __('market.view_proof') }}</button>
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground flex items-center gap-1" x-show="!o.proof"><i class="bi bi-exclamation-circle"></i> {{ __('market.no_proof') }}</p>

                {{-- actions --}}
                <div class="mt-3 pt-3 border-t border-gray-50 flex flex-wrap gap-2 items-center">
                    <button type="button" x-show="o.status === 'pending' || o.status === 'confirmed'" @click="confirmFulfill(o)"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-green-600 text-white hover:bg-green-700 transition-colors">
                        <i class="bi bi-bag-check mr-1"></i>{{ __('market.mark_fulfilled') }}
                    </button>
                    <button type="button" x-show="o.status === 'pending' || o.status === 'confirmed'" @click="setStatus(o, 'cancelled')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                        {{ __('market.mark_cancelled') }}
                    </button>
                </div>
            </div>
        </template>

        {{-- Show more --}}
        <button type="button" x-show="shown.length > limit" @click="limit += perPage"
                class="w-full flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 text-primary text-sm font-semibold py-2.5 hover:bg-primary/5 transition-colors">
            <span x-text="'{{ __('market.show_more') }}' + ' (' + (shown.length - limit) + ')'"></span><i class="bi bi-chevron-down"></i>
        </button>
    </div>

    {{-- Proof viewer (teleported to <body> so the dark backdrop covers the whole
         screen and the receipt never blends with the page behind it) --}}
    <template x-teleport="body">
        <div x-show="proofView" x-cloak class="fixed inset-0 z-[80] flex flex-col"
             @keydown.escape.window="closeProof()" style="display:none; background: rgba(8,6,18,.94); backdrop-filter: blur(4px);"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="flex items-center justify-between px-5 h-14 text-white flex-shrink-0">
                <span class="text-sm font-semibold flex items-center gap-2"><i class="bi bi-receipt"></i> {{ __('market.payment_proof') }}</span>
                <button type="button" @click="closeProof()" class="w-10 h-10 rounded-full grid place-items-center text-white hover:bg-white/10"><i class="bi bi-x-lg text-xl"></i></button>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.self="closeProof()">
                <img :src="proofView" alt="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
            </div>
            <div class="p-4 text-center">
                <a :href="proofView" target="_blank" class="text-white/70 text-sm hover:underline no-underline"><i class="bi bi-box-arrow-up-right mr-1"></i>{{ __('market.view_proof') }}</a>
            </div>
        </div>
    </template>
</div>

<script>
function clubOrders(orders) {
    return {
        orders: orders,
        filter: 'pending',
        filters: ['pending', 'confirmed', 'fulfilled', 'cancelled'],
        perPage: 10, limit: 10,
        setFilter(f) { this.filter = f; this.limit = this.perPage; },
        statusBase: @js(url('/admin/club/'.$club->slug.'/orders')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        labels: {
            all: @js(__('admin.all')),
            pending: @js(__('market.status_pending')), confirmed: @js(__('market.status_confirmed')),
            fulfilled: @js(__('market.status_fulfilled')), received: @js(__('market.status_received')),
            cancelled: @js(__('market.status_cancelled')),
        },
        label(s) { return this.labels[s] || s; },
        {{-- "Fulfilled" (sold) also covers orders the buyer has since confirmed
             received — receipt is a customer-side step, not a separate seller stage. --}}
        get shown() { return this.orders.filter(o => this.filter === 'fulfilled' ? (o.status === 'fulfilled' || o.status === 'received') : o.status === this.filter); },
        get visible() { return this.shown.slice(0, this.limit); },
        {{-- Unseen count per tab = orders in that status that arrived since the owner last
             opened this page (server-flagged o.new). Cleared on the next visit. --}}
        unseenFor(f) {
            return this.orders.filter(o => o.new
                && (f === 'fulfilled' ? (o.status === 'fulfilled' || o.status === 'received') : o.status === f)).length;
        },
        proofView: null,
        openProof(url) { this.proofView = url; },
        closeProof() { this.proofView = null; },
        badgeClass(s) {
            return ({ pending: 'bg-amber-50 text-amber-700', confirmed: 'bg-blue-50 text-blue-700', fulfilled: 'bg-green-50 text-green-700', received: 'bg-green-50 text-green-700', cancelled: 'bg-gray-100 text-gray-500' })[s] || 'bg-muted';
        },
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
                const res = await fetch(`${this.statusBase}/${o.id}/status`, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify({ status }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                window.showToast && window.showToast('success', d.message || @js(__('market.order_status_updated')));
            } catch (e) { o.status = prev; window.showToast && window.showToast('error', e.message); }
        },
    };
}
</script>
@endsection
