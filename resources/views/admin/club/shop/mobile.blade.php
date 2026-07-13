@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_shop'))

@section('club-admin-content')
{{-- Club Shop (mobile). UI only — create products (inventory or dropship).
     Reuses <x-market.product-form> + <x-market.category-form>. --}}
<div class="-mx-4 -mt-4"
     x-data="{
        categorySheet: false,
        editSheet: false,
        products: {{ Illuminate\Support\Js::from($products) }},
        manageCategories: {{ Illuminate\Support\Js::from($manageCategories) }},
        productBase: @js(url('/admin/club/'.$club->slug.'/shop/products')),
        categoryBase: @js(url('/admin/club/'.$club->slug.'/shop/categories')),
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
        openCategories() { this.categorySheet = true; this.$nextTick(() => this.addCategory()); },
        addCategory() { window.dispatchEvent(new CustomEvent('market-category-edit-open', { detail: { category: null } })); },
        editCategory(c) { window.dispatchEvent(new CustomEvent('market-category-edit-open', { detail: { category: c, action: `${this.categoryBase}/${c.id}` } })); },
        upsertCategory(cat) {
            if (!cat || !cat.id) return;
            const i = this.manageCategories.findIndex(x => x.id === cat.id);
            if (i === -1) this.manageCategories.push(cat); else this.manageCategories[i] = cat;
        },
        async deleteCategory(c) {
            const ok = await window.confirmAction({ title: @js(__('market.delete_category')), message: @js(__('market.delete_category_confirm')), type: 'danger', confirmText: @js(__('shared.delete')) });
            if (!ok) return;
            try {
                const res = await fetch(`${this.categoryBase}/${c.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.manageCategories = this.manageCategories.filter(x => x.id !== c.id);
                window.showToast && window.showToast('success', d.message || @js(__('shared.deleted')));
            } catch (e) { window.showToast && window.showToast('error', e.message); }
        },
     }"
     @market-product-saved.window="products.unshift($event.detail)"
     @market-product-updated.window="products = products.map(x => x.id === $event.detail.id ? $event.detail : x); editSheet = false"
     @market-category-saved.window="upsertCategory($event.detail)"
     @market-form-cancel.window="categorySheet = false; editSheet = false">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_shop') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-product-wizard')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('market.add_product') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-shop text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" x-text="products.length"></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_products') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" x-text="products.filter(p => p.fulfillment !== 'dropship').length"></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_in_stock') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" x-text="products.filter(p => p.fulfillment === 'dropship').length"></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_dropship') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-4 mobile-stagger">

    {{-- Secondary actions --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.club.orders', $club) }}"
           class="m-press flex-1 flex items-center justify-center gap-2 rounded-2xl border border-primary text-primary py-2.5 text-sm font-semibold">
            <i class="bi bi-receipt"></i>{{ __('market.view_orders') }}
        </a>
        <button type="button" @click="openCategories()"
                class="m-press flex-1 flex items-center justify-center gap-2 rounded-2xl border border-primary text-primary py-2.5 text-sm font-semibold">
            <i class="bi bi-grid-1x2"></i>{{ __('market.manage_categories') }}
        </button>
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
    </div>{{-- /content --}}

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

    {{-- ===== Manage categories sheet (teleported to <body> so the fixed overlay
            isn't trapped by the shell's transformed container) ===== --}}
    <template x-teleport="body">
        <div x-show="categorySheet" x-cloak class="fixed inset-0 z-[80] flex items-end" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="categorySheet = false"></div>
            <div class="relative w-full bg-white rounded-t-3xl p-5 max-h-[88vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-4"></div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-gray-900"><i class="bi bi-grid-1x2 text-primary mr-2"></i>{{ __('market.manage_categories') }}</h3>
                    <button @click="categorySheet = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                </div>

                {{-- Existing categories --}}
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('market.your_categories') }}</p>
                <template x-if="manageCategories.length === 0">
                    <p class="text-sm text-muted-foreground bg-muted/50 rounded-xl px-3 py-3 mb-4">{{ __('market.no_categories') }}</p>
                </template>
                <div x-show="manageCategories.length" class="space-y-2 mb-5">
                    <template x-for="c in manageCategories" :key="c.id">
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 px-3 py-2.5">
                            <span class="w-8 h-8 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi" :class="c.icon"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate" x-text="c.label"></p>
                                <p class="text-[11px] text-muted-foreground font-mono truncate" x-text="c.key"></p>
                            </div>
                            <button type="button" @click="editCategory(c)" class="m-press w-8 h-8 rounded-lg grid place-items-center text-gray-400 hover:bg-accent hover:text-primary" :aria-label="@js(__('market.edit_category'))"><i class="bi bi-pencil text-sm"></i></button>
                            <button type="button" @click="deleteCategory(c)" class="m-press w-8 h-8 rounded-lg grid place-items-center text-gray-400 hover:bg-red-50 hover:text-red-600" :aria-label="@js(__('market.delete_category'))"><i class="bi bi-trash text-sm"></i></button>
                        </div>
                    </template>
                </div>

                {{-- Add / edit form --}}
                <div class="border-t border-gray-100 pt-4">
                    <x-market.category-form
                        :action="route('admin.club.shop.categories.store', $club)"
                        :store-url="route('admin.club.shop.categories.store', $club)"
                        edit-event="market-category-edit-open" />
                </div>
            </div>
        </div>
    </template>

</div>
@endsection
