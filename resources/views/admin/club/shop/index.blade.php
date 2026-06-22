@extends('layouts.admin-club')

@section('club-admin-content')
{{--
    Club Shop (desktop). UI only for now — create products to sell (holding
    inventory) or list dropship products (no stock; order shipped on purchase).
    Reuses the market form components: <x-market.product-form> + <x-market.category-form>.
--}}
<div x-data="{
        productModal: false,
        editModal: false,
        categoryModal: false,
        products: {{ Illuminate\Support\Js::from($products) }},
        productBase: @js(url('/admin/club/'.$club->slug.'/shop/products')),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        openEdit(p) {
            this.editModal = true;
            // Hand the product + its update URL to the edit form (next tick so it's mounted).
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
     @market-product-saved.window="products.unshift($event.detail); productModal = false"
     @market-product-updated.window="products = products.map(x => x.id === $event.detail.id ? $event.detail : x); editModal = false"
     @market-category-saved.window="categoryModal = false"
     @market-form-cancel.window="productModal = false; editModal = false; categoryModal = false">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="tf-section-title">{{ __('admin.nav_shop') }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ __('market.shop_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.club.orders', $club) }}"
               class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors inline-flex items-center">
                <i class="bi bi-receipt mr-1.5"></i>{{ __('market.view_orders') }}
            </a>
            <button class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors"
                    @click="categoryModal = true">
                <i class="bi bi-grid-1x2 mr-1.5"></i>{{ __('market.add_category') }}
            </button>
            <button class="btn btn-primary" @click="productModal = true">
                <i class="bi bi-plus-lg mr-2"></i>{{ __('market.add_product') }}
            </button>
        </div>
    </div>

    {{-- Stat row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-muted-foreground">{{ __('market.stat_products') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1" x-text="products.length"></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-muted-foreground">{{ __('market.stat_in_stock') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1" x-text="products.filter(p => p.fulfillment !== 'dropship').length"></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-muted-foreground">{{ __('market.stat_dropship') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1" x-text="products.filter(p => p.fulfillment === 'dropship').length"></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-muted-foreground">{{ __('market.stat_on_sale') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1" x-text="products.filter(p => p.old).length"></p>
        </div>
    </div>

    {{-- Products grid --}}
    <template x-if="products.length === 0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-6 py-16 text-center">
            <i class="bi bi-shop text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('market.shop_empty') }}</p>
            <button class="btn btn-primary mt-4" @click="productModal = true"><i class="bi bi-plus-lg mr-2"></i>{{ __('market.add_product') }}</button>
        </div>
    </template>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4" x-show="products.length">
        <template x-for="p in products" :key="p.id">
            <div @click="openEdit(p)" role="button" tabindex="0" @keydown.enter="openEdit(p)"
                 class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col cursor-pointer hover:shadow-md hover:border-primary/30 transition-all">
                <div class="relative aspect-square grid place-items-center"
                     :style="p.image ? '' : `background: linear-gradient(160deg, ${p.color}18, ${p.color}08)`">
                    <template x-if="p.image"><img :src="p.image" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!p.image"><i class="bi text-4xl" :class="p.icon" :style="`color:${p.color}`"></i></template>
                    <template x-if="p.badge">
                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                              :style="`background:${p.badge==='Sale' ? '#ef4444' : (p.badge==='New' ? '#10b981' : p.color)}`" x-text="p.badge"></span>
                    </template>
                    {{-- fulfillment chip --}}
                    <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-semibold flex items-center gap-1"
                          :class="p.fulfillment === 'dropship' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-700'">
                        <i class="bi" :class="p.fulfillment === 'dropship' ? 'bi-truck' : 'bi-box-seam'"></i>
                        <span x-text="p.fulfillment === 'dropship' ? @js(__('market.dropship_mode')) : (p.quantity != null ? p.quantity : @js(__('market.in_stock_mode')))"></span>
                    </span>
                </div>
                <div class="p-3 flex flex-col flex-1">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide truncate" x-text="p.brand"></p>
                    <p class="font-bold text-foreground text-sm leading-tight mt-0.5 line-clamp-2" x-text="p.name"></p>
                    <div class="flex items-end justify-between mt-auto pt-2">
                        <div>
                            <p class="text-sm font-black text-foreground">BHD <span x-text="(parseFloat(p.price)||0).toFixed(2)"></span></p>
                            <template x-if="p.old"><p class="text-[10px] text-muted-foreground line-through">BHD <span x-text="(parseFloat(p.old)||0).toFixed(2)"></span></p></template>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click.stop="openEdit(p)" class="w-8 h-8 rounded-lg grid place-items-center text-gray-400 hover:bg-accent hover:text-primary transition-colors" title="{{ __('shared.edit') }}">
                                <i class="bi bi-pencil text-sm"></i>
                            </button>
                            <button @click.stop="deleteProduct(p)" class="w-8 h-8 rounded-lg grid place-items-center text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors" title="{{ __('shared.delete') }}">
                                <i class="bi bi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ===== Add product modal ===== --}}
    <div x-show="productModal" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="productModal = false"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-4xl bg-background rounded-2xl shadow-xl my-4 max-h-[90vh] flex flex-col overflow-hidden"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-white rounded-t-2xl flex-shrink-0">
                    <h3 class="text-base font-bold text-gray-900"><i class="bi bi-bag-plus text-primary mr-2"></i>{{ __('market.add_product') }}</h3>
                    <button @click="productModal = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="p-5 overflow-y-auto flex-1">
                    <x-market.product-form :categories="$categories" :show-fulfillment="true"
                        :action="route('admin.club.shop.products.store', $club)" />
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Edit product modal ===== --}}
    <div x-show="editModal" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="editModal = false"></div>
        <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-4xl bg-background rounded-2xl shadow-xl my-4 max-h-[90vh] flex flex-col overflow-hidden"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-white rounded-t-2xl flex-shrink-0">
                    <h3 class="text-base font-bold text-gray-900"><i class="bi bi-pencil-square text-primary mr-2"></i>{{ __('market.edit_product') }}</h3>
                    <button @click="editModal = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="p-5 overflow-y-auto flex-1">
                    <x-market.product-form mode="edit" method="PUT" :categories="$categories" :show-fulfillment="true"
                        event-name="market-product-updated" edit-event="market-edit-open" />
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Add category modal ===== --}}
    <div x-show="categoryModal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="categoryModal = false"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl p-5"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900"><i class="bi bi-grid-1x2 text-primary mr-2"></i>{{ __('market.add_category') }}</h3>
                <button @click="categoryModal = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
            </div>
            <x-market.category-form :action="route('admin.club.shop.categories.store', $club)" />
        </div>
    </div>

</div>
@endsection
