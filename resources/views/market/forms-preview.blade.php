@extends('layouts.app')

@section('title', 'Market — item creators')

@section('content')
{{--
    PREVIEW of the reusable market form components:
      <x-market.product-form />   <x-market.category-form />
    Form UI only (no DB yet). On (demo) save, the components dispatch a
    CustomEvent carrying the captured payload — shown in the panel on the right
    so you can confirm exactly what data the form produces.
--}}
<div x-data="{ tab: 'product', lastProduct: null, lastCategory: null }"
     @market-product-saved.window="lastProduct = $event.detail"
     @market-category-saved.window="lastCategory = $event.detail"
     @market-form-cancel.window="window.showToast && window.showToast('info', 'Cancelled')"
     class="px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">Market</p>
            <h1 class="text-2xl font-bold text-gray-900">Create market items</h1>
            <p class="text-sm text-muted-foreground mt-1">Reusable forms for products and categories — drop them into the club admin, a seller area, or the mobile shell.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
            <i class="bi bi-info-circle"></i> Preview · form UI only (not saved yet)
        </span>
    </div>

    {{-- Tabs --}}
    <div class="inline-flex p-1 bg-muted rounded-xl mb-6">
        <button type="button" @click="tab='product'"
                class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                :class="tab==='product' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">
            <i class="bi bi-bag mr-1.5"></i> Product
        </button>
        <button type="button" @click="tab='category'"
                class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                :class="tab==='category' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">
            <i class="bi bi-grid-1x2 mr-1.5"></i> Category
        </button>
    </div>

    <div class="grid lg:grid-cols-[1fr_320px] gap-6 items-start">
        {{-- Form --}}
        <div>
            <div x-show="tab==='product'">
                <x-market.product-form />
            </div>
            <div x-show="tab==='category'" x-cloak>
                <div class="bg-white rounded-xl border border-gray-100 p-5 max-w-md">
                    <x-market.category-form />
                </div>
            </div>
        </div>

        {{-- Captured payload (proves the form works without a backend) --}}
        <aside class="lg:sticky lg:top-4">
            <div class="bg-gray-900 rounded-xl p-4 text-gray-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 flex items-center gap-1.5 mb-2">
                    <i class="bi bi-braces"></i> Captured on save
                </p>
                <template x-if="tab==='product'">
                    <div>
                        <template x-if="lastProduct"><pre class="text-[11px] leading-relaxed overflow-x-auto whitespace-pre-wrap" x-text="JSON.stringify(lastProduct, null, 2)"></pre></template>
                        <template x-if="!lastProduct"><p class="text-xs text-gray-500">Fill the form and hit “Publish product”. The exact data it captures appears here — ready to POST to a real endpoint later.</p></template>
                    </div>
                </template>
                <template x-if="tab==='category'">
                    <div>
                        <template x-if="lastCategory"><pre class="text-[11px] leading-relaxed overflow-x-auto whitespace-pre-wrap" x-text="JSON.stringify(lastCategory, null, 2)"></pre></template>
                        <template x-if="!lastCategory"><p class="text-xs text-gray-500">Add a category to see the captured data.</p></template>
                    </div>
                </template>
            </div>
        </aside>
    </div>
</div>
@endsection
