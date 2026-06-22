@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_shop'))

@section('club-admin-content')
{{-- Club Shop (mobile). UI only — create products (inventory or dropship).
     Reuses <x-market.product-form> + <x-market.category-form>. --}}
<div class="space-y-4 mobile-stagger"
     x-data="{
        categorySheet: false,
        editSheet: false,
        products: {{ Illuminate\Support\Js::from($products) }},
        productBase: @js(url('/admin/club/'.$club->slug.'/shop/products')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        openEdit(p) {
            this.editSheet = true;
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('market-edit-open', {
                detail: { product: p, action: `${this.productBase}/${p.id}` }
            })));
        },
        async deleteProduct(p) {
            const ok = await window.confirmAction({ title: @js(__('market.delete_product')), message: @js(__('market.delete_product_confirm')), type: 'danger', confirmText: @js(__('shared.delete')) });
            if (!ok) return;
            try {
                const res = await fetch(`${this.productBase}/${p.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error();
                this.products = this.products.filter(x => x.id !== p.id);
                window.showToast && window.showToast('success', @js(__('shared.deleted')));
            } catch (e) { window.showToast && window.showToast('error', @js(__('shared.error'))); }
        },
     }"
     @market-product-saved.window="products.unshift($event.detail)"
     @market-product-updated.window="products = products.map(x => x.id === $event.detail.id ? $event.detail : x); editSheet = false"
     @market-category-saved.window="categorySheet = false"
     @market-form-cancel.window="categorySheet = false; editSheet = false">

    {{-- Add actions --}}
    <div class="flex items-center gap-2">
        <button type="button" @click="$dispatch('open-product-wizard')"
                class="m-press flex-1 flex items-center justify-center gap-2 rounded-2xl bg-primary text-white py-3.5 font-semibold shadow-sm">
            <i class="bi bi-plus-lg text-lg"></i>{{ __('market.add_product') }}
        </button>
        <a href="{{ route('admin.club.orders', $club) }}"
           class="m-press w-12 h-12 flex-shrink-0 rounded-2xl border border-primary text-primary grid place-items-center" aria-label="{{ __('market.view_orders') }}">
            <i class="bi bi-receipt text-lg"></i>
        </a>
        <button type="button" @click="categorySheet = true"
                class="m-press w-12 h-12 flex-shrink-0 rounded-2xl border border-primary text-primary grid place-items-center" aria-label="{{ __('market.add_category') }}">
            <i class="bi bi-grid-1x2 text-lg"></i>
        </button>
    </div>

    {{-- Stat chips --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="m-card rounded-2xl p-3 text-center">
            <p class="text-xl font-black text-foreground" x-text="products.length"></p>
            <p class="text-[10px] text-muted-foreground">{{ __('market.stat_products') }}</p>
        </div>
        <div class="m-card rounded-2xl p-3 text-center">
            <p class="text-xl font-black text-foreground" x-text="products.filter(p => p.fulfillment !== 'dropship').length"></p>
            <p class="text-[10px] text-muted-foreground">{{ __('market.stat_in_stock') }}</p>
        </div>
        <div class="m-card rounded-2xl p-3 text-center">
            <p class="text-xl font-black text-foreground" x-text="products.filter(p => p.fulfillment === 'dropship').length"></p>
            <p class="text-[10px] text-muted-foreground">{{ __('market.stat_dropship') }}</p>
        </div>
    </div>

    {{-- Empty state --}}
    <template x-if="products.length === 0">
        <div class="m-card p-8 text-center">
            <i class="bi bi-shop text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('market.shop_empty') }}</p>
        </div>
    </template>

    {{-- Products grid --}}
    <div class="grid grid-cols-2 gap-3" x-show="products.length">
        <template x-for="p in products" :key="p.id">
            <div @click="openEdit(p)" role="button"
                 class="m-card m-press rounded-2xl overflow-hidden flex flex-col cursor-pointer">
                <div class="relative aspect-square grid place-items-center"
                     :style="p.image ? '' : `background: linear-gradient(160deg, ${p.color}18, ${p.color}08)`">
                    <template x-if="p.image"><img :src="p.image" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!p.image"><i class="bi text-4xl" :class="p.icon" :style="`color:${p.color}`"></i></template>
                    <template x-if="p.badge">
                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                              :style="`background:${p.badge==='Sale' ? '#ef4444' : (p.badge==='New' ? '#10b981' : p.color)}`" x-text="p.badge"></span>
                    </template>
                    <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded-full text-[9px] font-semibold flex items-center gap-0.5"
                          :class="p.fulfillment === 'dropship' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-700'">
                        <i class="bi" :class="p.fulfillment === 'dropship' ? 'bi-truck' : 'bi-box-seam'"></i>
                        <span x-text="p.fulfillment === 'dropship' ? @js(__('market.dropship_mode')) : (p.quantity != null ? p.quantity : '✓')"></span>
                    </span>
                </div>
                <div class="p-2.5 flex flex-col flex-1">
                    <p class="text-[9px] text-muted-foreground uppercase tracking-wide truncate" x-text="p.brand"></p>
                    <p class="font-bold text-foreground text-xs leading-tight mt-0.5 line-clamp-2" x-text="p.name"></p>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-sm font-black text-foreground">BHD <span x-text="(parseFloat(p.price)||0).toFixed(2)"></span></p>
                        <button @click.stop="deleteProduct(p)" class="w-7 h-7 -mr-1 rounded-lg grid place-items-center text-gray-400 hover:text-red-600" :aria-label="@js(__('shared.delete'))">
                            <i class="bi bi-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ===== Add product — creative full-screen wizard (mobile) ===== --}}
    <x-market.product-wizard :categories="$categories"
        :action="route('admin.club.shop.products.store', $club)" />

    {{-- ===== Edit product sheet (teleported so the overlay escapes the shell's
            transformed container) ===== --}}
    <template x-teleport="body">
        <div x-show="editSheet" x-cloak class="fixed inset-0 z-[80] flex items-end" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="editSheet = false"></div>
            <div class="relative w-full bg-background rounded-t-3xl p-5 max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-4"></div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-gray-900"><i class="bi bi-pencil-square text-primary mr-2"></i>{{ __('market.edit_product') }}</h3>
                    <button @click="editSheet = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                </div>
                <x-market.product-form mode="edit" method="PUT" :categories="$categories" :show-fulfillment="true"
                    event-name="market-product-updated" edit-event="market-edit-open" />
            </div>
        </div>
    </template>

    {{-- ===== Add category sheet (teleported to <body> so the fixed overlay
            isn't trapped by the shell's transformed container) ===== --}}
    <template x-teleport="body">
        <div x-show="categorySheet" x-cloak class="fixed inset-0 z-[80] flex items-end" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="categorySheet = false"></div>
            <div class="relative w-full bg-white rounded-t-3xl p-5 max-h-[85vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-4"></div>
                <x-market.category-form :action="route('admin.club.shop.categories.store', $club)" />
            </div>
        </div>
    </template>

</div>
@endsection
