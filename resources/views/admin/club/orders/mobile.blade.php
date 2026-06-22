@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_orders'))

@section('club-admin-content')
@php
    $ordersJs = $orders->map(fn ($o) => [
        'id' => $o->id, 'reference' => $o->reference, 'status' => $o->status,
        'total' => (float) $o->total, 'currency' => $o->currency, 'date' => $o->created_at->format('M j · g:i A'),
        'customer' => $o->user->full_name ?? __('admin.fin_member'), 'hasDropship' => (bool) $o->has_dropship,
        'proof' => $o->paymentProofUrl(),
        'items' => $o->items->map(fn ($it) => ['name' => $it->name, 'qty' => $it->qty, 'image' => $it->image_path ? asset('storage/'.$it->image_path) : null])->values(),
    ])->values();
    $cur = $club->currency ?: 'BHD';
@endphp
<div x-data="clubOrdersM({{ Illuminate\Support\Js::from($ordersJs) }})" class="space-y-4 mobile-stagger">

    {{-- Stat chips --}}
    <div class="grid grid-cols-4 gap-2">
        <div class="m-card rounded-2xl p-2.5 text-center"><p class="text-lg font-black text-amber-600">{{ $stats['pending'] }}</p><p class="text-[9px] text-muted-foreground">{{ __('market.stat_pending') }}</p></div>
        <div class="m-card rounded-2xl p-2.5 text-center"><p class="text-lg font-black text-blue-600">{{ $stats['confirmed'] }}</p><p class="text-[9px] text-muted-foreground">{{ __('market.stat_confirmed') }}</p></div>
        <div class="m-card rounded-2xl p-2.5 text-center"><p class="text-lg font-black text-green-600">{{ $stats['fulfilled'] }}</p><p class="text-[9px] text-muted-foreground">{{ __('market.stat_fulfilled') }}</p></div>
        <div class="m-card rounded-2xl p-2.5 text-center"><p class="text-sm font-black text-foreground mt-1">{{ number_format($stats['revenue'], 0) }}</p><p class="text-[9px] text-muted-foreground">{{ $cur }}</p></div>
    </div>

    {{-- Filter --}}
    <div class="flex gap-2 overflow-x-auto scrollbar-hide">
        <template x-for="f in filters" :key="f">
            <button type="button" @click="filter = f"
                    class="m-press px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors"
                    :class="filter === f ? 'bg-primary text-white' : 'bg-white border border-gray-100 text-muted-foreground'"
                    x-text="label(f)"></button>
        </template>
    </div>

    <template x-if="shown.length === 0">
        <div class="m-card p-8 text-center"><i class="bi bi-bag-check text-3xl text-gray-300 m-float"></i><p class="text-sm text-muted-foreground mt-2">{{ __('market.no_orders_club') }}</p></div>
    </template>

    <template x-for="o in shown" :key="o.id">
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
                    <button type="button" x-show="o.status !== 'fulfilled' && o.status !== 'cancelled'" @click="confirmFulfill(o)" class="m-press px-3 py-1.5 rounded-lg text-[11px] font-bold bg-green-600 text-white">{{ __('market.mark_fulfilled') }}</button>
                    <button type="button" x-show="o.status !== 'cancelled' && o.status !== 'fulfilled'" @click="setStatus(o,'cancelled')" class="m-press px-3 py-1.5 rounded-lg text-[11px] font-bold border border-red-200 text-red-600">{{ __('shared.cancel') }}</button>
                </div>
            </div>
        </div>
    </template>

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
        orders: orders, filter: 'all',
        filters: ['all', 'pending', 'confirmed', 'fulfilled', 'cancelled'],
        statusBase: @js(url('/admin/club/'.$club->slug.'/orders')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        labels: { all: @js(__('admin.all')), pending: @js(__('market.status_pending')), confirmed: @js(__('market.status_confirmed')), fulfilled: @js(__('market.status_fulfilled')), cancelled: @js(__('market.status_cancelled')) },
        label(s) { return this.labels[s] || s; },
        get shown() { return this.filter === 'all' ? this.orders : this.orders.filter(o => o.status === this.filter); },
        proofView: null,
        openProof(url) { this.proofView = url; },
        closeProof() { this.proofView = null; },
        badgeClass(s) { return ({ pending: 'bg-amber-50 text-amber-700', confirmed: 'bg-blue-50 text-blue-700', fulfilled: 'bg-green-50 text-green-700', cancelled: 'bg-gray-100 text-gray-500' })[s] || 'bg-muted'; },
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
