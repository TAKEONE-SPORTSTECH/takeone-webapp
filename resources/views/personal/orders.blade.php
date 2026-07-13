@extends('layouts.personal-mobile')

@section('title', __('market.my_orders'))

@section('personal-content')
@php
    $statusStyle = [
        'pending'   => ['bg-amber-50 text-amber-700', 'bi-hourglass-split'],
        'confirmed' => ['bg-blue-50 text-blue-700', 'bi-check-circle'],
        'fulfilled' => ['bg-green-50 text-green-700', 'bi-bag-check-fill'],
        'received'  => ['bg-primary/10 text-primary', 'bi-patch-check-fill'],
        'cancelled' => ['bg-gray-100 text-gray-500', 'bi-x-circle'],
    ];
@endphp
<div class="-mx-4 -mt-4 pb-6" x-data="myOrders()">

    {{-- Hero --}}
    <header class="m-hero px-5 pt-6 pb-8 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-center gap-3">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.market') }}')"
               class="m-press w-9 h-9 rounded-xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('nav.tab_market') }}</p>
                <h1 class="text-2xl font-black">{{ __('market.my_orders') }}</h1>
            </div>
        </div>
    </header>

    <div class="px-4 -mt-3 relative z-10 space-y-3 mobile-stagger">
        @forelse($orders as $o)
            @php [$badge, $sicon] = $statusStyle[$o->status] ?? $statusStyle['pending']; @endphp
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] text-muted-foreground">{{ $o->created_at->diffForHumans() }}</p>
                        <p class="font-black text-foreground text-sm">#{{ $o->reference }}</p>
                        <p class="text-[12px] text-muted-foreground">{{ __('market.order_from', ['club' => $o->tenant->club_name ?? __('personal.club')]) }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $badge }}">
                        <i class="bi {{ $sicon }}"></i> {{ __('market.status_'.$o->status) }}
                    </span>
                </div>

                {{-- items --}}
                <div class="mt-3 space-y-2">
                    @foreach($o->items as $it)
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center overflow-hidden flex-shrink-0 bg-muted">
                                @if($it->image_path)
                                    <img src="{{ asset('storage/'.$it->image_path) }}" alt="" class="w-10 h-10 object-cover">
                                @else
                                    <i class="bi bi-bag text-muted-foreground"></i>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-foreground truncate">{{ $it->name }}</p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ $it->qty }} × {{ $o->currency }} {{ number_format($it->price, 2) }}
                                    @if($it->fulfillment === 'dropship')
                                        <span class="ml-1 inline-flex items-center gap-0.5 text-blue-600"><i class="bi bi-truck"></i> {{ __('market.dropship_badge') }}</span>
                                    @endif
                                </p>
                            </div>
                            <span class="text-[13px] font-bold text-foreground">{{ number_format($it->line_total, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- payment proof you attached --}}
                @if($o->paymentProofUrl())
                    <button type="button" @click="proofView = @js($o->paymentProofUrl())" class="mt-3 flex items-center gap-2 w-full text-left">
                        <span class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0">
                            <img src="{{ $o->paymentProofUrl() }}" alt="" class="w-full h-full object-cover">
                        </span>
                        <span class="text-[11px] leading-tight">
                            <span class="font-semibold text-foreground flex items-center gap-1"><i class="bi bi-receipt text-primary"></i> {{ __('market.payment_proof') }}</span>
                            <span class="text-primary">{{ __('market.view_proof') }}</span>
                        </span>
                    </button>
                @endif

                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">{{ __('market.total') }}</span>
                    <span class="text-base font-black text-foreground">{{ $o->currency }} {{ number_format($o->total, 2) }}</span>
                </div>

                {{-- Goods received (fulfilled orders) --}}
                @if($o->status === 'fulfilled')
                    <button type="button"
                            @click="startReceive({ id: {{ $o->id }}, ref: @js($o->reference), club: @js($o->tenant->club_name ?? __('personal.club')), products: @js($o->items->whereNotNull('club_product_id')->unique('club_product_id')->map(fn ($it) => ['id' => $it->club_product_id, 'name' => $it->name, 'rating' => 0])->values()->all()) })"
                            class="m-press w-full mt-3 py-3 rounded-2xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2">
                        <i class="bi bi-patch-check"></i> {{ __('market.goods_received') }}
                    </button>
                @endif
            </div>
        @empty
            <div class="m-card rounded-2xl px-6 py-14 text-center">
                <i class="bi bi-bag text-4xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm text-muted-foreground mt-3">{{ __('market.no_orders') }}</p>
                <p class="text-[12px] text-gray-400 mt-1">{{ __('market.no_orders_hint') }}</p>
                <a href="{{ route('me.market') }}" data-shell-link data-route="me.market"
                   class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold">
                    <i class="bi bi-shop"></i> {{ __('nav.tab_market') }}
                </a>
            </div>
        @endforelse
    </div>

    {{-- Proof viewer (teleported so the dark backdrop covers the full screen) --}}
    <template x-teleport="body">
        <div x-show="proofView" x-cloak class="fixed inset-0 z-[80] flex flex-col" @keydown.escape.window="proofView = null"
             style="display:none; background: rgba(8,6,18,.94); backdrop-filter: blur(4px);"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
                <span class="text-sm font-semibold flex items-center gap-2"><i class="bi bi-receipt"></i> {{ __('market.payment_proof') }}</span>
                <button type="button" @click="proofView = null" class="w-9 h-9 rounded-full grid place-items-center hover:bg-white/10"><i class="bi bi-x-lg text-xl"></i></button>
            </div>
            <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.self="proofView = null">
                <img :src="proofView" alt="" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
            </div>
        </div>
    </template>

    {{-- Goods received + rating sheet (teleported) --}}
    <template x-teleport="body">
        <div x-show="recv" x-cloak class="fixed inset-0 z-[80] flex items-end" @keydown.escape.window="recv = null"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="absolute inset-0 bg-black/50" @click="recv = null"></div>
            <div class="relative w-full bg-white rounded-t-3xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mt-2.5"></div>
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-patch-check text-primary"></i> {{ __('market.confirm_receipt') }}</h3>
                    <button type="button" @click="recv = null" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-5" x-show="recv">
                    <p class="text-sm text-muted-foreground">{{ __('market.receipt_title') }} <span class="font-semibold text-foreground">#<span x-text="recv?.ref"></span></span>.</p>

                    {{-- Seller rating --}}
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ __('market.rate_seller') }}</p>
                        <p class="text-[11px] text-muted-foreground mb-1.5"><span x-text="recv?.club"></span> · {{ __('market.rating_optional') }}</p>
                        <div class="flex gap-1.5">
                            <template x-for="n in 5" :key="n">
                                <button type="button" @click="recv.sellerRating = n">
                                    <i class="bi text-3xl" :class="n <= recv.sellerRating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Product ratings --}}
                    <div x-show="recv && recv.products && recv.products.length">
                        <p class="text-sm font-semibold text-foreground mb-2">{{ __('market.rate_products') }}</p>
                        <div class="space-y-3">
                            <template x-for="(p, pi) in recv.products" :key="p.id">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[13px] text-foreground truncate flex-1" x-text="p.name"></span>
                                    <div class="flex gap-1 flex-shrink-0">
                                        <template x-for="n in 5" :key="n">
                                            <button type="button" @click="recv.products[pi].rating = n">
                                                <i class="bi text-xl" :class="n <= p.rating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Comment --}}
                    <div>
                        <textarea x-model="recv.comment" rows="2" maxlength="1000" placeholder="{{ __('market.leave_comment') }}"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"></textarea>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-100 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <button type="button" @click="confirmReceive()" :disabled="submitting"
                            class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60">
                        <i class="bi" :class="submitting ? 'bi-arrow-repeat animate-spin' : 'bi-patch-check-fill'"></i> {{ __('market.confirm_receipt') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function myOrders() {
    return {
        proofView: null,
        recv: null,
        submitting: false,
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        startReceive(o) { this.recv = { ...o, sellerRating: 0, comment: '' }; this.submitting = false; },
        async confirmReceive() {
            if (this.submitting || !this.recv) return;
            this.submitting = true;
            try {
                const body = {
                    seller_rating: this.recv.sellerRating || null,
                    comment: (this.recv.comment || '').trim() || null,
                    products: (this.recv.products || []).filter(p => p.rating > 0).map(p => ({ id: p.id, rating: p.rating })),
                };
                const res = await fetch(@js(url('/me/orders')) + '/' + this.recv.id + '/receive', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                window.showToast && window.showToast('success', d.message || @js(__('market.received_confirmed')));
                this.recv = null;
                setTimeout(() => window.location.reload(), 700);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
