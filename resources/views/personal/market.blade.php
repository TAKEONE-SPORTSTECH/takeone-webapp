@extends('layouts.personal-mobile')

@section('title', __('nav.tab_market'))

{{--
    Market — mobile marketplace. NOTE: currently rendered with curated DUMMY
    content ($products, $categories from PersonalMobileController@market). Hero
    with a live cart, search, category chips, a featured carousel, a flash-deal
    strip with countdown, and a filterable product grid. Cards link to the product
    detail page; "add to cart" updates a live badge + slide-up cart drawer.
    Reuses the shared mobile motion vocabulary.
--}}
@php
    $list     = array_values($products);
    $featured = collect($list)->where('featured', true)->values();
    $deals    = collect($list)->whereNotNull('old')->values();
    $meta     = collect($list)->map(fn ($p) => ['cat' => $p['cat'], 'name' => strtolower($p['name'].' '.$p['brand'])])->values();
@endphp

@section('personal-content')
@once
<style>
    /* Add-to-cart micro-interactions */
    @keyframes cart-bump { 0%,100% { transform: none } 25% { transform: scale(1.3) rotate(-10deg) } 55% { transform: scale(.92) rotate(7deg) } 80% { transform: scale(1.06) } }
    .cart-bump { animation: cart-bump .6s cubic-bezier(.2,.8,.3,1.2); transform-origin: center; }
    @keyframes cart-pop { 0%,100% { transform: none } 40% { transform: scale(1.5) } }
    .cart-pop { animation: cart-pop .5s ease; }
    @keyframes cart-plus { 0% { opacity: 0; transform: translateY(2px) scale(.6) } 25% { opacity: 1 } 100% { opacity: 0; transform: translateY(-28px) scale(1.15) } }
    .cart-plus { position: absolute; top: -4px; right: 2px; font-size: 12px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.3); pointer-events: none; animation: cart-plus .75s ease forwards; }
    @media (prefers-reduced-motion: reduce) { .cart-bump,.cart-pop,.cart-plus { animation: none !important } }
</style>
@endonce
<div x-data="marketHub({{ Illuminate\Support\Js::from($meta) }})" class="-mx-4 -mt-4 pb-6">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-6 pb-14 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('market.personal_market_eyebrow') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('market.personal_market_title') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('me.orders') }}" data-shell-link data-route="me.orders"
                   class="m-press w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('market.my_orders') }}">
                    <i class="bi bi-receipt text-lg"></i>
                </a>
                <button type="button" @click="cartOpen=true"
                        class="m-press relative w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-bag text-lg" :class="cartBump ? 'cart-bump' : ''"></i>
                    <span x-show="count>0" x-transition
                          class="absolute -top-1 -end-1 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center"
                          :class="cartBump ? 'cart-pop' : ''" x-text="count"></span>
                    {{-- floating +1 on add --}}
                    <span x-show="cartBump" x-cloak class="cart-plus">+1</span>
                </button>
            </div>
        </div>

        <div class="relative mt-5 z-10">
            <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-white/70 pointer-events-none"></i>
            <input x-model="q" type="search" placeholder="{{ __('market.personal_market_search_placeholder') }}"
                   class="w-full ps-10 pe-3 py-3 bg-white/15 border border-white/25 backdrop-blur rounded-2xl text-sm text-white placeholder-white/60 focus:outline-none focus:ring-2 focus:ring-white/40">
        </div>
    </header>

    {{-- ===== Category chips (overlap hero) ===== --}}
    <div class="px-4 -mt-7 relative z-10">
        <div class="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
            @foreach($categories as $c)
                <button type="button" @click="cat='{{ $c['key'] }}'"
                        class="m-press flex-shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-semibold border shadow-sm transition-colors"
                        :class="cat==='{{ $c['key'] }}' ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-100'">
                    <i class="bi {{ $c['icon'] }}"></i> {{ $c['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ===== Featured carousel (only when there are featured products) ===== --}}
    @if($featured->isNotEmpty())
    <div class="mt-5" x-show="cat==='all' && q===''" x-transition>
        <div class="px-4 mb-2.5">
            <h2 class="text-sm font-black text-foreground flex items-center gap-2"><i class="bi bi-stars text-primary"></i> {{ __('market.personal_market_featured') }}</h2>
        </div>
        <div class="flex gap-3 overflow-x-auto scrollbar-hide px-4 pb-1 snap-x snap-mandatory">
            @foreach($featured as $p)
                <a href="{{ route('me.market.show', $p['id']) }}" data-shell-link data-route="me.market"
                   class="m-press snap-start flex-shrink-0 w-64 rounded-3xl overflow-hidden shadow-lg text-white relative"
                   style="background: linear-gradient(135deg, {{ $p['color'] }}, {{ $p['color'] }}bb);">
                    @if(!empty($p['image']))
                        {{-- Real product photo fills the card; a bottom gradient keeps the text legible. --}}
                        <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/5"></div>
                    @else
                        <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                    @endif
                    <div class="relative p-4 h-40 flex flex-col">
                        @if($p['badge'])<span class="self-start px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur">{{ $p['badge'] }}</span>@endif
                        @if(empty($p['image']))
                            <i class="bi {{ $p['icon'] }} text-4xl mt-auto opacity-90 m-float"></i>
                        @else
                            <span class="mt-auto"></span>
                        @endif
                        <h3 class="font-black text-base mt-2 leading-tight drop-shadow-sm">{{ $p['name'] }}</h3>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-sm font-bold drop-shadow-sm">BHD {{ number_format($p['price'], 2) }}</span>
                            <span class="text-[11px] bg-white/20 backdrop-blur px-2 py-0.5 rounded-full"><i class="bi bi-star-fill text-[9px]"></i> {{ $p['rating'] }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ===== Flash deals strip (only when there are on-sale products) ===== --}}
    @if($deals->isNotEmpty())
    <div class="mt-6" x-show="cat==='all' && q===''" x-transition>
        <div class="mx-4 rounded-3xl p-4 text-white relative overflow-hidden" style="background: linear-gradient(135deg, #ef4444, #f59e0b);">
            <div class="absolute -right-8 -bottom-8 w-32 h-32 rounded-full bg-white/10"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide flex items-center gap-1.5"><i class="bi bi-lightning-charge-fill"></i> {{ __('market.personal_market_flash_deals') }}</p>
                    <h3 class="text-lg font-black mt-0.5">{{ __('market.personal_market_flash_subtitle') }}</h3>
                </div>
                <div class="text-end">
                    <p class="text-[10px] text-white/80 uppercase">{{ __('market.personal_market_ends_in') }}</p>
                    <p class="text-base font-black tabular-nums" x-text="countdown"></p>
                </div>
            </div>
            <div class="relative flex gap-2 mt-3 overflow-x-auto scrollbar-hide">
                @foreach($deals as $p)
                    @php $off = $p['old'] ? round(($p['old'] - $p['price']) / $p['old'] * 100) : 0; @endphp
                    <a href="{{ route('me.market.show', $p['id']) }}" data-shell-link data-route="me.market"
                       class="m-press flex-shrink-0 w-28 bg-white rounded-2xl p-2.5 text-foreground">
                        @if(!empty($p['image']))
                            <div class="aspect-square rounded-xl overflow-hidden mb-2">
                                <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="aspect-square rounded-xl grid place-items-center mb-2" style="background: {{ $p['color'] }}15;">
                                <i class="bi {{ $p['icon'] }} text-2xl" style="color: {{ $p['color'] }};"></i>
                            </div>
                        @endif
                        <p class="text-[11px] font-bold truncate">{{ $p['name'] }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-xs font-black text-foreground">BHD {{ number_format($p['price'], 2) }}</span>
                            <span class="text-[9px] font-bold text-red-500">-{{ $off }}%</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Product grid ===== --}}
    <div class="px-4 mt-6">
        <h2 class="text-sm font-black text-foreground flex items-center gap-2 mb-3">
            <i class="bi bi-shop text-primary"></i> <span x-text="cat==='all' ? '{{ __('market.personal_market_all_products') }}' : '{{ __('market.personal_market_products') }}'"></span>
        </h2>
        <div class="grid grid-cols-2 gap-3">
            @foreach($list as $p)
                <div x-show="(cat==='all' || cat==='{{ $p['cat'] }}') && '{{ strtolower($p['name'].' '.$p['brand']) }}'.includes(q.toLowerCase())"
                     x-transition class="m-card rounded-2xl overflow-hidden flex flex-col">
                    <a href="{{ route('me.market.show', $p['id']) }}" data-shell-link data-route="me.market" class="m-press block relative">
                        @if(!empty($p['image']))
                            <div class="aspect-square">
                                <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="aspect-square grid place-items-center" style="background: linear-gradient(160deg, {{ $p['color'] }}18, {{ $p['color'] }}08);">
                                <i class="bi {{ $p['icon'] }} text-5xl" style="color: {{ $p['color'] }};"></i>
                            </div>
                        @endif
                        @if($p['badge'])
                            <span class="absolute top-2 start-2 px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                                  style="background: {{ $p['badge']==='Sale' ? '#ef4444' : ($p['badge']==='New' ? '#10b981' : $p['color']) }};">{{ $p['badge'] }}</span>
                        @endif
                    </a>
                    <div class="p-2.5 flex flex-col flex-1">
                        {{-- Brand + rating share a row; name is one line — keeps the card short. --}}
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider truncate">{{ $p['brand'] }}</p>
                            @if((int) $p['reviews'] > 0)
                                <span class="shrink-0 inline-flex items-center gap-0.5 text-[10px] font-medium text-muted-foreground">
                                    <i class="bi bi-star-fill text-amber-400 text-[9px]"></i>{{ $p['rating'] }}
                                </span>
                            @endif
                        </div>
                        <a href="{{ route('me.market.show', $p['id']) }}" data-shell-link data-route="me.market"
                           class="block font-bold text-foreground text-[13px] leading-tight mt-0.5 truncate">{{ $p['name'] }}</a>
                        <div class="flex items-center justify-between gap-2 mt-2">
                            <p class="min-w-0 truncate text-[15px] font-black text-foreground leading-none">
                                @if(!empty($p['hasVariants']))<span class="text-[9px] font-semibold text-muted-foreground">{{ __('market.from_price') }} </span>@endif BHD {{ number_format($p['price'], 2) }}@if($p['old'])<span class="ms-1 text-[10px] font-medium text-muted-foreground line-through">{{ number_format($p['old'], 2) }}</span>@endif
                            </p>
                            @if(!empty($p['hasVariants']))
                                {{-- Variants need a choice — send the buyer to the detail page to pick. --}}
                                <a href="{{ route('me.market.show', $p['id']) }}" data-shell-link data-route="me.market"
                                   class="m-press w-9 h-9 shrink-0 rounded-xl grid place-items-center text-white active:scale-95 transition-transform" style="background: {{ $p['color'] }};" title="{{ __('market.choose_options') }}">
                                    <i class="bi bi-sliders text-sm"></i>
                                </a>
                            @else
                                <button type="button"
                                        @click="add({{ Illuminate\Support\Js::from(['id'=>$p['id'],'name'=>$p['name'],'price'=>$p['price'],'color'=>$p['color'],'icon'=>$p['icon']]) }})"
                                        class="m-press w-9 h-9 shrink-0 rounded-xl grid place-items-center text-white active:scale-95 transition-transform" style="background: {{ $p['color'] }};" aria-label="{{ __('market.add_to_cart') }}">
                                    <i class="bi bi-plus-lg text-sm"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="!hasResults()" x-cloak class="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">
            <i class="bi bi-search text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('market.personal_market_no_results') }}</p>
        </div>
    </div>

    {{-- ===== Cart drawer (teleported to <body> so the fixed overlay isn't
            trapped by the shell's transformed container) ===== --}}
    <template x-teleport="body">
    <div x-show="cartOpen" x-cloak class="fixed inset-0 z-[80]" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="cartOpen=false" x-transition.opacity></div>
        <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[85vh] flex flex-col"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mt-2.5"></div>
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-black text-foreground flex items-center gap-2">
                    <button type="button" x-show="cartStep==='pay'" @click="cartStep='cart'" class="m-press -ms-1 w-7 h-7 grid place-items-center" aria-label="{{ __('market.back') }}"><i class="bi bi-arrow-left"></i></button>
                    <i class="bi" :class="cartStep==='pay' ? 'bi-shield-lock' : 'bi-bag'"></i>
                    <span x-text="cartStep==='pay' ? @js(__('market.pay_title')) : @js(__('market.your_cart'))"></span>
                    <span class="text-muted-foreground font-medium" x-show="cartStep==='cart'" x-text="`(${count})`"></span>
                </h3>
                <button type="button" @click="cartOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
            </div>

            {{-- Step 1 · cart items --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" x-show="cartStep==='cart'">
                <template x-if="items.length===0">
                    <div class="text-center py-10">
                        <i class="bi bi-bag-x text-3xl text-gray-300"></i>
                        <p class="text-sm text-muted-foreground mt-2">{{ __('market.cart_empty') }}</p>
                    </div>
                </template>
                <template x-for="it in items" :key="(it.id) + ':' + (it.variantId || '')">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0" :style="`background:${it.color}18`">
                            <i class="bi text-xl" :class="it.icon" :style="`color:${it.color}`"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground truncate" x-text="it.name"></p>
                            <p x-show="it.variantLabel" class="text-[11px] text-muted-foreground truncate" x-text="it.variantLabel"></p>
                            <p class="text-xs text-muted-foreground">BHD <span x-text="it.price.toFixed(2)"></span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="dec(it)" class="m-press w-7 h-7 rounded-lg bg-muted grid place-items-center"><i class="bi bi-dash text-xs"></i></button>
                            <span class="w-5 text-center text-sm font-bold" x-text="it.qty"></span>
                            <button type="button" @click="inc(it)" class="m-press w-7 h-7 rounded-lg bg-muted grid place-items-center"><i class="bi bi-plus text-xs"></i></button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Step 2 · payment proof --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4" x-show="cartStep==='pay'" x-cloak>
                <input type="file" x-ref="proofInput" accept="image/*" class="hidden" @change="pickProof($event)">
                <div class="flex gap-2 text-[13px] text-muted-foreground bg-muted rounded-xl p-3">
                    <i class="bi bi-info-circle mt-0.5 text-primary"></i>
                    <span>{{ __('market.pay_instructions') }}</span>
                </div>
                <div>
                    <p class="text-sm font-semibold text-foreground mb-2">{{ __('market.upload_proof') }} <span class="text-red-500">*</span></p>
                    <button type="button" @click="$refs.proofInput.click()"
                            class="m-press w-full rounded-2xl border-2 border-dashed border-gray-200 overflow-hidden grid place-items-center min-h-[140px] hover:border-primary transition-colors">
                        <template x-if="proof"><img :src="proof" alt="" class="w-full max-h-60 object-contain"></template>
                        <template x-if="!proof">
                            <span class="text-center text-muted-foreground py-6">
                                <i class="bi bi-receipt-cutoff text-3xl text-primary"></i>
                                <span class="block text-sm font-medium mt-2">{{ __('market.upload_proof') }}</span>
                                <span class="block text-[11px] mt-0.5">{{ __('market.proof_hint') }}</span>
                            </span>
                        </template>
                    </button>
                    <button type="button" x-show="proof" @click="$refs.proofInput.click()" class="text-xs text-primary font-medium mt-1.5">{{ __('market.change_proof') }}</button>
                </div>
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-100" x-show="items.length>0">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm text-muted-foreground">{{ __('market.total') }}</span>
                    <span class="text-lg font-black text-foreground">BHD <span x-text="total.toFixed(2)"></span></span>
                </div>
                {{-- cart step → continue --}}
                <button type="button" x-show="cartStep==='cart'" @click="cartStep='pay'"
                        class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-shield-lock"></i> {{ __('market.continue_to_pay') }}
                </button>
                {{-- pay step → place order (needs proof) --}}
                <button type="button" x-show="cartStep==='pay'" @click="checkout()" :disabled="!proof || placing"
                        class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-50">
                    <i class="bi" :class="placing ? 'bi-arrow-repeat animate-spin' : 'bi-bag-check'"></i>
                    <span x-text="placing ? @js(__('market.placing')) : @js(__('market.place_order'))"></span>
                </button>
                <p class="text-[11px] text-muted-foreground text-center mt-2" x-show="cartStep==='pay' && !proof" x-cloak>{{ __('market.proof_required') }}</p>
            </div>
        </div>
    </div>
    </template>

</div>

<script>
function marketHub(meta) {
    return {
        meta: meta || [],
        q: '', cat: 'all', cartOpen: false, items: [], countdown: '12:00:00', _t: null,
        placing: false, cartBump: false, _actx: null,
        cartStep: 'cart', proof: null,
        routes: { place: @js(route('me.orders.store')), orders: @js(route('me.orders')) },
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        get count() { return this.items.reduce((n, i) => n + i.qty, 0); },
        get total() { return this.items.reduce((s, i) => s + i.price * i.qty, 0); },
        loadCart() { try { this.items = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) { this.items = []; } },
        saveCart() { try { localStorage.setItem('takeone_cart', JSON.stringify(this.items)); } catch (e) {} },
        sameLine(a, b) { return a.id === b.id && (a.variantId || null) === (b.variantId || null); },
        add(p) {
            const ex = this.items.find(i => this.sameLine(i, p));
            if (ex) ex.qty++; else this.items.push({ ...p, qty: 1 });
            this.saveCart();
            // Satisfying feedback: bag bumps, badge pops, a soft "pop" sound.
            this.cartBump = false;
            requestAnimationFrame(() => { this.cartBump = true; setTimeout(() => this.cartBump = false, 600); });
            this.playPop();
            window.showToast('success', @js(__('market.added_to_cart')).replace(':name', p.name));
        },
        playPop() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this._actx = this._actx || new Ctx();
                const ctx = this._actx;
                if (ctx.state === 'suspended') ctx.resume();
                const now = ctx.currentTime;
                const o = ctx.createOscillator(), g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'triangle';
                o.frequency.setValueAtTime(523, now);
                o.frequency.exponentialRampToValueAtTime(1046, now + 0.09);
                g.gain.setValueAtTime(0.0001, now);
                g.gain.exponentialRampToValueAtTime(0.18, now + 0.012);
                g.gain.exponentialRampToValueAtTime(0.0001, now + 0.2);
                o.start(now); o.stop(now + 0.22);
            } catch (e) {}
        },
        inc(it) { it.qty++; this.saveCart(); },
        dec(it) { it.qty--; if (it.qty <= 0) this.items = this.items.filter(i => !this.sameLine(i, it)); this.saveCart(); },
        async pickProof(e) {
            const f = (e.target.files || [])[0]; e.target.value = '';
            if (!f || !f.type.startsWith('image/')) return;
            // Shrink large phone photos before encoding so the upload is fast
            // (a multi-MB receipt photo would otherwise be sent as ~MBs of base64).
            let file = f;
            try {
                if (window.imageCompression && f.size > 400 * 1024) {
                    file = await window.imageCompression(f, { maxSizeMB: 0.6, maxWidthOrHeight: 1600, useWebWorker: true });
                }
            } catch (_) { file = f; }
            const r = new FileReader(); r.onload = () => { this.proof = r.result; }; r.readAsDataURL(file);
        },
        async checkout() {
            if (this.placing || !this.items.length) return;
            if (!this.proof) { window.showToast('error', @js(__('market.proof_required'))); return; }
            this.placing = true;
            try {
                const res = await fetch(this.routes.place, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        items: this.items.map(i => ({ id: i.id, qty: i.qty, color: i.color || null, variant_id: i.variantId || null })),
                        proof: this.proof,
                    }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.items = []; this.saveCart(); this.cartOpen = false; this.cartStep = 'cart'; this.proof = null;
                window.showToast('success', d.message || @js(__('market.order_placed')));
                setTimeout(() => { window.location.href = d.redirect || this.routes.orders; }, 700);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.placing = false;
            }
        },
        hasResults() {
            const q = this.q.toLowerCase();
            return this.meta.some(m => (this.cat === 'all' || m.cat === this.cat) && m.name.includes(q));
        },
        startTimer() {
            let s = 12 * 3600;
            const fmt = () => {
                const h = String(Math.floor(s / 3600)).padStart(2, '0');
                const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
                const sec = String(s % 60).padStart(2, '0');
                this.countdown = `${h}:${m}:${sec}`;
            };
            fmt();
            this._t = setInterval(() => { s = s > 0 ? s - 1 : 12 * 3600; fmt(); }, 1000);
        },
        init() { this.startTimer(); this.loadCart(); }
    };
}
</script>
@endsection
