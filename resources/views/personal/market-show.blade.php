@extends('layouts.personal-mobile')

@section('title', $p['name'])

{{--
    Product detail — mobile. DUMMY content from PersonalMobileController@marketShow.
    Gradient product hero, color picker, quantity stepper, specs, rating breakdown,
    sample reviews, related products, and a sticky add-to-cart bar. Reuses the
    shared mobile motion vocabulary and design tokens.
--}}
@php
    $off = $p['old'] ? round(($p['old'] - $p['price']) / $p['old'] * 100) : 0;
@endphp

@section('personal-content')
<style>
    @keyframes cart-pop { 0% { transform: scale(1); } 40% { transform: scale(1.18); } 100% { transform: scale(1); } }
    .cart-pop { animation: cart-pop .4s ease; }
    @media (prefers-reduced-motion: reduce) { .cart-pop { animation: none; } }
</style>
<div x-data="{
        qty: 1,
        color: @js($p['colors'][0] ?? $p['color']),
        liked: false,
        placing: false,
        paySheet: false,
        proof: null,
        pid: {{ (int) $p['id'] }},
        pname: @js($p['name']),
        basePrice: {{ $p['price'] }},
        productDesc: @js($p['desc'] ?? ''),
        hasVariants: {{ !empty($p['hasVariants']) ? 'true' : 'false' }},
        variants: @js(collect($p['variants'] ?? [])->where('is_active', true)->values()->all()),
        attributes: @js($p['attributes'] ?? []),
        selected: {},   // { AttrName: chosenValue }
        placeRoute: @js(route('me.orders.store')),
        ordersRoute: @js(route('me.orders')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        cart: [],
        justAdded: false,
        init() { this.syncCart(); },
        syncCart() { try { this.cart = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) { this.cart = []; } },
        // Is the currently-selected variant already in the cart?
        get inCart() {
            const vId = this.sel ? this.sel.id : null;
            return (this.cart || []).some(i => i.id === this.pid && (i.variantId || null) === (vId || null));
        },
        get cartQty() {
            const vId = this.sel ? this.sel.id : null;
            const l = (this.cart || []).find(i => i.id === this.pid && (i.variantId || null) === (vId || null));
            return l ? l.qty : 0;
        },
        // The product's variant dimensions (name + values). Only dimensions with
        // ≥1 value are offered as selectors.
        dims() { return (this.attributes || []).filter(a => a && a.values && a.values.length); },
        // A variant's value for an attribute — from its options map, falling back
        // to the legacy brand/color/size columns for un-migrated rows.
        vopt(v, name) {
            if (v.options && v.options[name] != null) return v.options[name];
            const legacy = v[String(name).toLowerCase()];
            return legacy != null ? legacy : null;
        },
        // Effective choice for a dimension: the picked value, or the sole value of a
        // single-option dimension (so the buyer need only pick the dimensions that vary).
        effVal(name) {
            if (this.selected[name] != null) return this.selected[name];
            const a = this.dims().find(d => d.name === name);
            return (a && a.values.length === 1) ? a.values[0] : null;
        },
        // Price range across variants — shown until a full combination is picked.
        priceMin() { return this.variants.length ? Math.min(...this.variants.map(v => v.price)) : this.basePrice; },
        priceMax() { return this.variants.length ? Math.max(...this.variants.map(v => v.price)) : this.basePrice; },
        priceVaries() { return this.hasVariants && this.priceMin() !== this.priceMax(); },
        // The variant matching every dimension the product uses.
        get sel() {
            if (!this.hasVariants) return null;
            const dims = this.dims();
            if (!dims.length) return null;
            return this.variants.find(v => dims.every(d => {
                const ev = this.effVal(d.name);
                return ev != null && this.vopt(v, d.name) === ev;
            })) || null;
        },
        get unitPrice() { return this.sel ? this.sel.price : this.basePrice; },
        pickOpt(name, val) { this.selected = { ...this.selected, [name]: val }; },
        optSelected(name, val) { return this.selected[name] === val; },
        // Does variant v match every OTHER chosen dimension (used for price/availability)?
        _matchesExcept(v, exceptName) {
            return this.dims().every(d => {
                if (d.name === exceptName) return true;
                const ev = this.effVal(d.name);
                return ev == null || this.vopt(v, d.name) === ev;
            });
        },
        // Cheapest price reachable for a value given the other choices (per-tile price).
        optPrice(name, val) {
            const m = this.variants.filter(v => this.vopt(v, name) === val && this._matchesExcept(v, name));
            return m.length ? Math.min(...m.map(v => v.price)) : null;
        },
        // A value is available if some in-stock variant has it with the other chosen dims.
        optAvailable(name, val) {
            return this.variants.some(v => v.in_stock && this.vopt(v, name) === val && this._matchesExcept(v, name));
        },
        // Swatch colour for a value, if any variant carries a hex for it (Colour dims).
        dimHex(name, val) {
            const v = this.variants.find(x => this.vopt(x, name) === val && x.color_hex);
            return v ? v.color_hex : null;
        },
        // Description shown: the chosen variation's own, else the product's, else the
        // first variation that has one.
        descText() {
            if (this.sel && (this.sel.description || '').trim()) return this.sel.description;
            if ((this.productDesc || '').trim()) return this.productDesc;
            const withDesc = this.variants.find(v => (v.description || '').trim());
            return withDesc ? withDesc.description : '';
        },
        inc() { this.qty++; },
        dec() { if (this.qty > 1) this.qty--; },
        get line() { return (this.unitPrice * this.qty); },
        addToCart() {
            if (this.hasVariants && !this.sel) { window.showToast('error', @js(__('market.select_variant'))); return; }
            if (this.sel && !this.sel.in_stock) { window.showToast('error', @js(__('market.out_of_stock'))); return; }
            let cart = [];
            try { cart = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) {}
            const vId = this.sel ? this.sel.id : null;
            const ex = cart.find(i => i.id === this.pid && (i.variantId || null) === (vId || null) && (i.color || null) === (this.color || null));
            if (ex) ex.qty += this.qty;
            else cart.push({ id: this.pid, name: this.pname, price: this.unitPrice, color: this.sel ? this.sel.color_hex : this.color, icon: @js($p['icon']), qty: this.qty, variantId: vId, variantLabel: this.sel ? this.sel.label : null });
            try { localStorage.setItem('takeone_cart', JSON.stringify(cart)); } catch (e) {}
            this.syncCart();
            this.justAdded = true; setTimeout(() => { this.justAdded = false; }, 700);
            window.showToast('success', @js(__('market.added_to_cart')).replace(':name', this.pname));
        },
        buyNow() {
            if (this.hasVariants && !this.sel) { window.showToast('error', @js(__('market.select_variant'))); return; }
            if (this.sel && !this.sel.in_stock) { window.showToast('error', @js(__('market.out_of_stock'))); return; }
            this.proof = null; this.paySheet = true;
        },
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
        async placeOrder() {
            if (this.placing) return;
            if (!this.proof) { window.showToast('error', @js(__('market.proof_required'))); return; }
            this.placing = true;
            try {
                const res = await fetch(this.placeRoute, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ items: [{ id: this.pid, qty: this.qty, color: this.sel ? this.sel.color_hex : this.color, variant_id: this.sel ? this.sel.id : null }], proof: this.proof }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.paySheet = false;
                window.showToast('success', d.message || @js(__('market.order_placed')));
                setTimeout(() => { window.location.href = d.redirect || this.ordersRoute; }, 700);
            } catch (e) { window.showToast('error', e.message); } finally { this.placing = false; }
        }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Product hero ===== --}}
    <header class="px-5 pt-5 pb-8 relative overflow-hidden" style="background: linear-gradient(160deg, {{ $p['color'] }}1f, {{ $p['color'] }}08);">
        <div class="flex items-center justify-between relative z-10">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.market') }}')"
               class="m-press w-10 h-10 rounded-full bg-white shadow-sm grid place-items-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-lg"></i>
            </button>
            <button type="button" @click="liked=!liked"
                    class="m-press w-10 h-10 rounded-full bg-white shadow-sm grid place-items-center"
                    :class="liked ? 'text-red-500' : 'text-muted-foreground'">
                <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i>
            </button>
        </div>

        <div class="grid place-items-center py-6 relative">
            @if(!empty($p['image']))
                <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" class="relative w-48 h-48 object-contain m-float drop-shadow-md">
            @else
                <div class="absolute w-44 h-44 rounded-full" style="background: {{ $p['color'] }}14;"></div>
                <i class="bi {{ $p['icon'] }} text-8xl m-float relative" style="color: {{ $p['color'] }};"></i>
            @endif
        </div>

        @if($p['badge'])
            <span class="absolute top-20 start-5 px-2.5 py-1 rounded-full text-[10px] font-bold text-white"
                  style="background: {{ $p['badge']==='Sale' ? '#ef4444' : ($p['badge']==='New' ? '#10b981' : $p['color']) }};">{{ $p['badge'] }}</span>
        @endif
    </header>

    {{-- ===== Info card (compact) ===== --}}
    <div class="px-4 -mt-5 relative z-10">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-4">
            {{-- Title + price on one line --}}
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    @if($p['brand'])<p class="text-[10px] text-muted-foreground uppercase tracking-wider truncate">{{ $p['brand'] }}</p>@endif
                    <h1 class="text-lg font-black text-foreground leading-tight">{{ $p['name'] }}</h1>
                    <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5 mt-1">
                        @if(($p['reviews'] ?? 0) > 0)
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-500"><i class="bi bi-star-fill text-[9px]"></i> {{ $p['rating'] }}</span>
                            <span class="text-[11px] text-muted-foreground">· {{ $p['reviews'] }} {{ __('market.personal_market_show_reviews_label') }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-green-600"><i class="bi bi-check-circle-fill text-[9px]"></i> {{ $p['stock'] }}</span>
                    </div>
                </div>
                <div class="text-end flex-shrink-0">
                    {{-- Exact price once the combination is picked (or no variants) --}}
                    <p class="text-xl font-black text-foreground whitespace-nowrap leading-none" x-show="!hasVariants || sel">BHD <span x-text="unitPrice.toFixed(2)">{{ number_format($p['price'], 2) }}</span></p>
                    {{-- 'From' price until every option is chosen --}}
                    <p class="text-xl font-black text-foreground whitespace-nowrap leading-none" x-show="hasVariants && !sel" x-cloak>BHD <span x-text="priceMin().toFixed(2)"></span><template x-if="priceVaries()"><span class="text-sm font-bold text-muted-foreground">+</span></template></p>
                    @if($p['old'])
                        <p class="mt-1 flex items-center justify-end gap-1.5">
                            <span class="text-xs text-muted-foreground line-through">BHD {{ number_format($p['old'], 2) }}</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-50 text-red-500">-{{ $off }}%</span>
                        </p>
                    @endif
                </div>
            </div>

            {{-- variants: one compact selector block per attribute (Brand / Model / Size…) --}}
            @if(!empty($p['hasVariants']))
                <div class="mt-3 space-y-2.5">
                    <template x-for="a in dims()" :key="a.name">
                        <div>
                            <p class="text-[11px] font-semibold text-muted-foreground mb-1.5" x-text="a.name"></p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="val in a.values" :key="val">
                                    <button type="button" @click="pickOpt(a.name, val)" :disabled="!optAvailable(a.name, val)"
                                            class="m-press px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors inline-flex items-center gap-1.5 leading-tight"
                                            :class="optSelected(a.name, val) ? 'border-primary bg-accent/50 text-primary' : 'border-gray-200 text-foreground'"
                                            :style="!optAvailable(a.name, val) ? 'opacity:.4;text-decoration:line-through;cursor:not-allowed' : ''">
                                        <template x-if="dimHex(a.name, val)">
                                            <span class="w-3 h-3 rounded-full border border-gray-200" :style="`background:${dimHex(a.name, val)}`"></span>
                                        </template>
                                        <span x-text="val"></span>
                                        <span x-show="priceVaries() && optPrice(a.name, val) !== null" class="text-[10px] font-normal opacity-70" x-text="optPrice(a.name, val).toFixed(2)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p x-show="!sel" class="text-[11px] text-red-500">{{ __('market.select_variant') }}</p>
                    <p x-show="sel && !sel.in_stock" class="text-[11px] text-red-500">{{ __('market.out_of_stock') }}</p>
                    {{-- Dropshipped variation: shows its ships-in estimate --}}
                    <p x-show="sel && sel.fulfillment === 'dropship'" x-cloak class="text-[11px] text-muted-foreground flex items-center gap-1.5">
                        <i class="bi bi-truck text-primary"></i>
                        <span x-text="sel && sel.ships_in ? ('{{ __('market.ships_in') }}: ' + sel.ships_in) : '{{ __('market.dropship_desc') }}'"></span>
                    </p>
                </div>
            @elseif(count($p['colors']) > 1)
                {{-- colours --}}
                <div class="mt-3">
                    <p class="text-[11px] font-semibold text-muted-foreground mb-1.5">{{ __('market.personal_market_show_colour') }}</p>
                    <div class="flex gap-2.5">
                        @foreach($p['colors'] as $col)
                            <button type="button" @click="color='{{ $col }}'"
                                    class="m-press w-7 h-7 rounded-full border-2 grid place-items-center transition-transform"
                                    :class="color==='{{ $col }}' ? 'scale-110' : 'border-transparent'"
                                    :style="color==='{{ $col }}' ? 'border-color: {{ $col }}' : ''">
                                <span class="w-5 h-5 rounded-full" style="background: {{ $col }};"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- quantity — compact pill stepper --}}
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                <span class="text-xs font-semibold text-muted-foreground">{{ __('market.personal_market_show_quantity') }}</span>
                <div class="flex items-center gap-0.5 bg-muted rounded-xl p-0.5">
                    <button type="button" @click="dec()" class="m-press w-8 h-8 rounded-lg bg-white shadow-sm grid place-items-center text-foreground disabled:opacity-40" :disabled="qty <= 1"><i class="bi bi-dash text-sm"></i></button>
                    <span class="w-7 text-center text-sm font-black tabular-nums" x-text="qty">1</span>
                    <button type="button" @click="inc()" class="m-press w-8 h-8 rounded-lg text-white shadow-sm grid place-items-center" style="background: {{ $p['color'] }};"><i class="bi bi-plus text-sm"></i></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Description (variation's own, falling back to the product's) ===== --}}
    <div class="px-4 mt-4" x-show="descText()" x-cloak>
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-card-text text-primary"></i> {{ __('market.personal_market_show_description') }}</h2>
            <p class="text-sm text-muted-foreground leading-relaxed mt-2 whitespace-pre-line" x-text="descText()"></p>
        </div>
    </div>

    {{-- ===== Specs (hidden when the product has none) ===== --}}
    @php
        $specs = collect($p['specs'] ?? [])
            ->filter(fn ($s) => trim($s[0] ?? '') !== '' || trim($s[1] ?? '') !== '')
            ->values();
    @endphp
    @if($specs->isNotEmpty())
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-ul text-primary"></i> {{ __('market.personal_market_show_specifications') }}</h2>
            <div class="mt-3 divide-y divide-gray-50">
                @foreach($specs as $spec)
                    <div class="flex items-center justify-between py-2">
                        <span class="text-xs text-muted-foreground">{{ $spec[0] }}</span>
                        <span class="text-xs font-bold text-foreground">{{ $spec[1] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Ratings &amp; reviews (real) — hidden until the product has reviews ===== --}}
    @if(($p['reviews'] ?? 0) > 0)
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-star text-primary"></i> {{ __('market.ratings_reviews') }}</h2>

                <div class="flex items-center gap-4 mt-3">
                    <div class="text-center">
                        <p class="text-3xl font-black text-foreground">{{ number_format($p['rating'], 1) }}</p>
                        <div class="flex gap-0.5 mt-1 text-amber-400 text-xs">
                            @for($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= round($p['rating']) ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor
                        </div>
                        <p class="text-[10px] text-muted-foreground mt-1">{{ $p['reviews'] }} {{ $p['reviews'] == 1 ? __('market.review_one') : __('market.reviews_many') }}</p>
                    </div>
                    <div class="flex-1 space-y-1">
                        @foreach(($breakdown ?? []) as $star => $pct)
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-muted-foreground w-2">{{ $star }}</span>
                                <div class="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div class="m-bar-fill h-full rounded-full bg-amber-400" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- real reviews --}}
                <div class="mt-4 space-y-3 pt-3 border-t border-gray-50">
                    @foreach($reviews as $rev)
                        <div class="flex items-start gap-3">
                            @if($rev['avatar'])
                                <img src="{{ $rev['avatar'] }}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold flex-shrink-0" style="background: hsl({{ ($loop->index * 70) % 360 }} 55% 60%);">{{ $rev['initials'] ?: '🙂' }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="text-xs font-bold text-foreground truncate">{{ $rev['name'] }}</p>
                                    <span class="flex gap-0.5 text-amber-400 text-[9px]">@for($i=1;$i<=5;$i++)<i class="bi {{ $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</span>
                                    <span class="text-[10px] text-muted-foreground ms-auto flex-shrink-0">{{ $rev['time'] }}</span>
                                </div>
                                @if(!empty($rev['comment']))
                                    <p class="text-xs text-muted-foreground mt-0.5">{{ $rev['comment'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
        </div>
    </div>
    @endif

    {{-- ===== Related ===== --}}
    @if(!empty($related))
        <div class="mt-5">
            <h2 class="px-4 text-sm font-black text-foreground flex items-center gap-2 mb-3"><i class="bi bi-collection text-primary"></i> {{ __('market.personal_market_show_you_might_also_like') }}</h2>
            <div class="flex gap-3 overflow-x-auto scrollbar-hide px-4 pb-1">
                @foreach($related as $r)
                    <a href="{{ route('me.market.show', $r['id']) }}" data-shell-link data-route="me.market"
                       class="m-press flex-shrink-0 w-36 m-card rounded-2xl overflow-hidden">
                        @if(!empty($r['image']))
                            <div class="aspect-square">
                                <img src="{{ $r['image'] }}" alt="{{ $r['name'] }}" loading="lazy" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="aspect-square grid place-items-center" style="background: linear-gradient(160deg, {{ $r['color'] }}18, {{ $r['color'] }}08);">
                                <i class="bi {{ $r['icon'] }} text-4xl" style="color: {{ $r['color'] }};"></i>
                            </div>
                        @endif
                        <div class="p-2.5">
                            <p class="text-xs font-bold text-foreground truncate">{{ $r['name'] }}</p>
                            <p class="text-sm font-black text-foreground mt-0.5">BHD {{ number_format($r['price'], 2) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Add-to-cart action ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-3 flex items-center gap-2.5">
            <div class="leading-tight pe-0.5">
                <p class="text-[9px] text-muted-foreground uppercase tracking-wide">{{ __('market.personal_market_show_total') }}</p>
                <p class="text-base font-black text-foreground whitespace-nowrap">BHD <span x-text="line.toFixed(2)">{{ number_format($p['price'], 2) }}</span></p>
            </div>
            {{-- Add to cart — switches to an 'Added' state once this variant is in the cart --}}
            <button type="button" @click="addToCart()"
                    class="m-press flex-1 py-3 rounded-2xl border-2 font-bold text-sm flex items-center justify-center gap-1.5 transition-all duration-300"
                    :class="inCart ? 'cart-added border-transparent bg-green-500 text-white' : ''"
                    :style="inCart ? '' : 'border-color: {{ $p['color'] }}; color: {{ $p['color'] }};'">
                <template x-if="!inCart">
                    <span class="flex items-center gap-1.5"><i class="bi bi-bag-plus"></i> {{ __('market.add_to_cart') }}</span>
                </template>
                <template x-if="inCart">
                    <span class="flex items-center gap-1.5" :class="justAdded ? 'cart-pop' : ''">
                        <i class="bi bi-bag-check-fill"></i> {{ __('market.in_cart') }}
                        <span class="min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-white/25 text-[11px] grid place-items-center tabular-nums" x-text="cartQty"></span>
                    </span>
                </template>
            </button>
            <button type="button" @click="buyNow()"
                    class="m-press flex-1 py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-1.5"
                    style="background: {{ $p['color'] }};">
                <i class="bi bi-lightning-charge-fill"></i> {{ __('market.buy_now') }}
            </button>
        </div>
    </div>

    {{-- ===== Payment sheet (teleported) — proof of payment required ===== --}}
    <template x-teleport="body">
        <div x-show="paySheet" x-cloak class="fixed inset-0 z-[80] flex items-end"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="absolute inset-0 bg-black/50" @click="paySheet=false"></div>
            <div class="relative w-full bg-white rounded-t-3xl max-h-[88vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mt-2.5"></div>
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-shield-lock"></i> {{ __('market.pay_title') }}</h3>
                    <button type="button" @click="paySheet=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <input type="file" x-ref="proofInput" accept="image/*" class="hidden" @change="pickProof($event)">
                    <div class="flex gap-2 text-[13px] text-muted-foreground bg-muted rounded-xl p-3">
                        <i class="bi bi-info-circle mt-0.5 text-primary"></i><span>{{ __('market.pay_instructions') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground"><span x-text="qty"></span> × {{ $p['name'] }}<span x-show="sel" x-text="sel ? (' — ' + sel.label) : ''"></span></span>
                        <span class="font-black text-foreground">BHD <span x-text="line.toFixed(2)"></span></span>
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

                <div class="p-4 border-t border-gray-100 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <button type="button" @click="placeOrder()" :disabled="!proof || placing"
                            class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-50">
                        <i class="bi" :class="placing ? 'bi-arrow-repeat animate-spin' : 'bi-bag-check'"></i>
                        <span x-text="placing ? @js(__('market.placing')) : @js(__('market.place_order'))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection
