@props([
    'categories' => null,                       // [['key'=>..,'label'=>..], ..]
    'action'     => '',                          // POST url; demo-only if empty
    'eventName'  => 'market-product-saved',     // dispatched on publish
])

@php
    $cats = $categories ?? [
        ['key' => 'gear',      'label' => 'Gear'],
        ['key' => 'equipment', 'label' => 'Equipment'],
        ['key' => 'nutrition', 'label' => 'Nutrition'],
        ['key' => 'apparel',   'label' => 'Apparel'],
        ['key' => 'passes',    'label' => 'Passes'],
    ];
    $iconChoices  = ['bi-bag','bi-bicycle','bi-cup-hot','bi-ticket-perforated','bi-person-arms-up','bi-trophy','bi-lightning-charge-fill','bi-droplet-half','bi-basket','bi-box-seam','bi-heart-pulse','bi-stars'];
    $colorChoices = ['#7c3aed','#6d28d9','#ec4899','#ef4444','#f59e0b','#10b981','#0ea5e9','#111827'];
    $badgeChoices = ['','Sale','New','Best value','Limited'];
    $stockChoices = ['In stock','Digital','Made to order'];
@endphp

{{-- Mobile-only creative multi-step product wizard. Opens on `open-product-wizard`,
     dispatches `market-product-saved` on publish. Teleported to <body> so its
     fixed overlay isn't trapped by the shell's transformed/animated container. --}}
<div x-data="marketProductWizard({{ Illuminate\Support\Js::from([
        'cats' => $cats, 'event' => $eventName, 'action' => $action,
        'icons' => $iconChoices, 'colors' => $colorChoices,
        'badges' => $badgeChoices, 'stocks' => $stockChoices,
     ]) }})"
     @open-product-wizard.window="open()">
<template x-teleport="body">
    <div x-show="isOpen" x-cloak class="mw fixed inset-0 z-[80] flex flex-col text-white"
         :style="`--c: ${color}`"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        {{-- Living colour backdrop (adopts the product's colour) --}}
        <div class="mw-bg" :style="`background: radial-gradient(120% 80% at 50% -10%, ${color}, #0e0a1f 78%)`"></div>
        <div class="mw-grain"></div>

        {{-- ============ HEADER ============ --}}
        <div class="relative z-10 flex items-center gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2">
            <button type="button" @click="close()" class="m-press w-9 h-9 rounded-full grid place-items-center bg-white/15 backdrop-blur" aria-label="{{ __('shared.cancel') }}">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-[.2em] text-white/60" x-text="success ? '' : @js(__('market.wiz_new_product'))"></p>
                <p class="text-sm font-bold leading-tight" x-text="success ? '' : steps[step-1]"></p>
            </div>
            <span class="text-[11px] font-semibold text-white/70 tabular-nums" x-show="!success" x-text="step + ' / ' + steps.length"></span>
        </div>

        {{-- progress segments --}}
        <div class="relative z-10 flex gap-1.5 px-4" x-show="!success">
            <template x-for="(s,i) in steps" :key="i">
                <div class="flex-1 h-1 rounded-full bg-white/20 overflow-hidden">
                    <div class="h-full bg-white transition-all duration-500" :style="`width: ${i < step ? 100 : 0}%`"></div>
                </div>
            </template>
        </div>

        {{-- ============ LIVE PREVIEW (persistent) ============ --}}
        <div class="relative z-10 flex justify-center pt-5 pb-3" x-show="!success">
            <div class="mw-card w-44 bg-white rounded-3xl overflow-hidden shadow-2xl text-gray-900"
                 :class="bump ? 'mw-bump' : ''">
                <div class="relative aspect-square grid place-items-center"
                     :style="image ? '' : `background: linear-gradient(160deg, ${color}22, ${color}0a)`">
                    <template x-if="image"><img :src="image" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!image"><i class="bi text-5xl mw-float" :class="icon" :style="`color:${color}`"></i></template>
                    <template x-if="badge">
                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                              :style="`background:${badge==='Sale' ? '#ef4444' : (badge==='New' ? '#10b981' : color)}`" x-text="badge"></span>
                    </template>
                </div>
                <div class="p-3">
                    <p class="text-[9px] text-gray-400 uppercase tracking-wide truncate" x-text="brand || @js(__('market.brand'))"></p>
                    <p class="font-bold text-[13px] leading-tight truncate" x-text="name || @js(__('market.product_name'))"></p>
                    <div class="flex items-end gap-1.5 mt-1">
                        <p class="text-sm font-black"><span x-show="useVariants && rows.length" class="text-[8px] font-semibold text-gray-400">{{ __('market.from_price') }} </span>BHD <span x-text="displayPrice().toFixed(2)"></span></p>
                        <template x-if="old"><p class="text-[10px] text-gray-400 line-through mb-0.5">BHD <span x-text="(parseFloat(old)||0).toFixed(2)"></span></p></template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STEP CONTENT ============ --}}
        <div class="relative z-10 flex-1 overflow-y-auto px-4 pb-4" x-show="!success">
            <div class="bg-white text-gray-900 rounded-3xl p-4 min-h-full" :key="step"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0">

                {{-- STEP 1 · Look --}}
                <div x-show="step===1" class="space-y-4">
                    <input type="file" x-ref="photo" accept="image/*" class="hidden" @change="pickPhoto($event)">
                    <button type="button" @click="$refs.photo.click()"
                            class="m-press w-full h-32 rounded-2xl border-2 border-dashed grid place-items-center overflow-hidden"
                            :style="image ? '' : `border-color:${color}55; background: linear-gradient(160deg, ${color}14, ${color}06)`">
                        <template x-if="image"><img :src="image" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!image">
                            <span class="text-center" :style="`color:${color}`">
                                <i class="bi bi-camera text-2xl"></i>
                                <span class="block text-xs font-semibold mt-1">{{ __('market.upload_photo') }}</span>
                            </span>
                        </template>
                    </button>
                    <button type="button" x-show="image" @click="image=null" class="text-xs text-red-600 font-medium">{{ __('market.remove_photo') }}</button>

                    <div x-show="!image">
                        <p class="text-xs font-semibold text-gray-500 mb-2">{{ __('market.icon') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="ic in iconChoices" :key="ic">
                                <button type="button" @click="icon=ic; pulse()"
                                        class="m-press w-11 h-11 rounded-xl grid place-items-center text-lg transition-all"
                                        :style="icon===ic ? `background:${color}; color:#fff` : `background:${color}12; color:${color}`">
                                    <i class="bi" :class="ic"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold text-gray-500 mb-2">{{ __('market.theme_color') }}</p>
                        <div class="flex flex-wrap gap-2.5">
                            <template x-for="c in colorChoices" :key="c">
                                <button type="button" @click="color=c; pulse()"
                                        class="m-press w-9 h-9 rounded-full transition-transform"
                                        :class="color===c ? 'scale-110 ring-2 ring-offset-2' : ''"
                                        :style="`background:${c}; --tw-ring-color:${c}`"></button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- STEP 2 · Details --}}
                <div x-show="step===2" x-cloak class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.product_name') }}</label>
                        <input type="text" x-model="name" maxlength="80" placeholder="{{ __('market.product_name_ph') }}"
                               class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                    {{-- Brand is a per-variation attribute for variant products; the single
                         product-level Brand only applies to simple (non-variant) products. --}}
                    <div x-show="!useVariants">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.brand') }}</label>
                        <input type="text" x-model="brand" maxlength="60" placeholder="{{ __('market.brand_ph') }}"
                               class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.category') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in cats" :key="c.key">
                                <button type="button" @click="cat=c.key"
                                        class="m-press px-3.5 py-2 rounded-full text-xs font-semibold transition-colors"
                                        :style="cat===c.key ? `background:${color}; color:#fff` : `background:${color}12; color:${color}`"
                                        x-text="c.label"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.description') }}</label>
                        <textarea x-model="desc" rows="3" maxlength="600" placeholder="{{ __('market.description_ph') }}"
                                  class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"></textarea>
                    </div>
                </div>

                {{-- STEP 3 · Price --}}
                <div x-show="step===3" x-cloak class="space-y-4">
                    {{-- Variants toggle: sell in different sizes/colours/brands --}}
                    <label class="flex items-center gap-3 rounded-2xl border-2 p-3"
                           :style="useVariants ? `border-color:${color}; background:${color}0d` : 'border-color:#e5e7eb'">
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" x-model="useVariants" class="sr-only peer">
                            <span class="w-11 h-6 rounded-full bg-gray-200 transition-colors" :style="useVariants ? `background:${color}` : ''"></span>
                            <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="useVariants ? 'translate-x-5' : ''"></span>
                        </span>
                        <span class="text-sm">
                            <span class="font-semibold text-gray-800">{{ __('market.has_variants') }}</span>
                            <span class="block text-[11px] text-gray-500">{{ __('market.variants_hint') }}</span>
                        </span>
                    </label>

                    {{-- Single price (only when there are no variants) --}}
                    <div x-show="!useVariants" class="space-y-4">
                    {{-- Cost + margin → price is derived & locked --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.cost_per_unit') }}</label>
                            <input type="number" x-model="cost" min="0" step="0.01" inputmode="decimal" placeholder="0.00" @input="recompute()"
                                   class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.profit_margin') }}</label>
                            <div class="flex gap-1.5">
                                <div class="inline-flex rounded-xl border border-gray-200 p-0.5 flex-shrink-0">
                                    <button type="button" @click="marginType='fixed'; recompute()"
                                            class="px-2 py-2 rounded-lg text-xs font-bold transition-colors"
                                            :class="marginType==='fixed' ? 'bg-primary text-white' : 'text-gray-500'">{{ __('market.margin_fixed') }}</button>
                                    <button type="button" @click="marginType='percent'; recompute()"
                                            class="px-2 py-2 rounded-lg text-xs font-bold transition-colors"
                                            :class="marginType==='percent' ? 'bg-primary text-white' : 'text-gray-500'">%</button>
                                </div>
                                <input type="number" x-model="marginValue" min="0" step="0.01" inputmode="decimal" @input="recompute()"
                                       :placeholder="marginType==='percent' ? '25' : '0.00'"
                                       class="flex-1 min-w-0 px-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.price') }} (BHD)</label>
                            <div class="relative">
                                <input type="number" x-model="price" min="0" step="0.01" inputmode="decimal" placeholder="0.00"
                                       :readonly="autoPrice()" :class="autoPrice() ? 'bg-gray-50 pr-9' : ''"
                                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base font-bold">
                                <i class="bi bi-lock-fill absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" x-show="autoPrice()"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.old_price') }}</label>
                            <input type="number" x-model="old" min="0" step="0.01" inputmode="decimal" placeholder="{{ __('market.old_price_ph') }}"
                                   class="w-full px-3.5 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                        </div>
                    </div>
                    <p class="text-[12px] font-semibold text-green-600 -mt-1" x-show="autoPrice()">
                        {{ __('market.margin_label') }}: BHD <span x-text="profitAmount()"></span>
                        <span x-show="marginType==='fixed'">(<span x-text="profitPct()"></span>%)</span>
                    </p>
                    <p class="text-[12px] font-semibold text-red-500 -mt-1" x-show="(parseFloat(old)||0) > (parseFloat(price)||0)"
                       x-text="'−' + Math.round(((old-price)/old)*100) + '% ' + @js(__('market.discount_shown'))"></p>
                    </div>{{-- /!useVariants single price --}}

                    {{-- Attributes (Brand / Model / Size…) + generated variations --}}
                    <div x-show="useVariants" x-cloak class="space-y-3">
                        {{-- Attribute cards --}}
                        <template x-for="(a, ai) in attributes" :key="ai">
                            <div class="rounded-2xl border border-gray-200 overflow-hidden">
                                <div class="flex items-center justify-between gap-2 bg-gray-50 px-3 py-2.5 border-b border-gray-200">
                                    <span class="font-bold text-sm text-gray-800 flex items-center gap-1.5 min-w-0"><i class="bi bi-tag flex-shrink-0" :style="`color:${color}`"></i> <span class="truncate" x-text="a.name"></span></span>
                                    <button type="button" @click="removeAttribute(ai)" class="w-7 h-7 rounded-lg grid place-items-center text-gray-400 flex-shrink-0"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="p-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <template x-for="(val, vi) in a.values" :key="vi">
                                            <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-gray-200 text-xs font-medium text-gray-700">
                                                <span x-text="val"></span>
                                                <button type="button" @click="removeValue(ai, vi)" class="w-4 h-4 rounded-full grid place-items-center text-gray-400"><i class="bi bi-x text-[11px]"></i></button>
                                            </span>
                                        </template>
                                        <input type="text" x-model="a.newValue" @keydown.enter.prevent="addValue(ai)" maxlength="80" placeholder="{{ __('market.attribute_value_ph') }}"
                                               class="flex-1 min-w-[100px] px-2.5 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm bg-white">
                                        <button type="button" @click="addValue(ai)" class="px-3 py-2 rounded-xl text-xs font-bold text-white" :style="`background:${color}`">{{ __('market.opt_add') }}</button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Add attribute --}}
                        <div class="flex items-center gap-2">
                            <input type="text" x-model="newAttrName" @keydown.enter.prevent="addAttribute()" maxlength="50" placeholder="{{ __('market.attribute_name_ph') }}"
                                   class="flex-1 min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm bg-white">
                            <button type="button" @click="addAttribute()" class="px-3 py-2.5 rounded-xl text-xs font-bold text-white flex items-center gap-1 whitespace-nowrap" :style="`background:${color}`"><i class="bi bi-plus-lg"></i> {{ __('market.add_attribute') }}</button>
                        </div>

                        <p x-show="attributes.length === 0" class="text-sm text-gray-400 italic">{{ __('market.no_attributes_yet') }}</p>

                        {{-- Generate + bulk price --}}
                        <div x-show="attributes.length > 0" class="flex items-center justify-between gap-2 flex-wrap">
                            <button type="button" @click="regenerateAll()" :disabled="variationCount() === 0"
                                    class="px-3 py-2 rounded-xl text-xs font-bold text-white inline-flex items-center gap-1.5 disabled:opacity-40" :style="`background:${color}`">
                                <i class="bi bi-grid-3x3-gap"></i>
                                <span x-text="rows.length ? '{{ __('market.regenerate_variations') }}' : '{{ __('market.generate_variations') }}'"></span>
                                <span x-show="variationCount() > 0" class="opacity-80 font-normal" x-text="'(' + variationCount() + ')'"></span>
                            </button>
                            <div x-show="rows.length > 0" class="flex items-center gap-1.5">
                                <input type="number" x-model="bulk.price" min="0" step="0.01" inputmode="decimal" placeholder="{{ __('market.opt_bulk_price') }}"
                                       class="w-20 px-2 py-1.5 border border-gray-200 rounded-lg text-xs">
                                <button type="button" @click="applyBulkPrice()" class="px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">{{ __('market.opt_apply_all') }}</button>
                            </div>
                        </div>

                        {{-- Generated variation rows (scrollable for large matrices) --}}
                        <div x-show="rows.length > 0" class="max-h-72 overflow-y-auto space-y-2 pr-0.5">
                            <template x-for="(r, ri) in rows" :key="r.key">
                                <div class="rounded-xl border border-gray-200 p-2.5 space-y-2" :class="r.is_active ? '' : 'opacity-50'">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-medium text-gray-700 truncate flex-1 min-w-0" x-text="rowLabel(r)"></span>
                                        <input type="number" x-model="r.price" min="0" step="0.01" inputmode="decimal" placeholder="0.00"
                                               class="w-14 px-2 py-1.5 border rounded-lg text-sm font-bold"
                                               :class="(parseFloat(r.price)||0) > 0 ? 'border-gray-200' : 'border-red-300'">
                                        {{-- Stock qty OR a Dropship pill --}}
                                        <template x-if="r.fulfillment !== 'dropship'">
                                            <input type="number" x-model="r.quantity" min="0" inputmode="numeric" placeholder="{{ __('market.variant_qty_ph') }}"
                                                   class="w-11 px-1.5 py-1.5 border border-gray-200 rounded-lg text-sm">
                                        </template>
                                        <template x-if="r.fulfillment === 'dropship'">
                                            <button type="button" @click="r._fulfillOpen = !r._fulfillOpen"
                                                    class="px-1.5 py-1.5 rounded-lg text-[10px] font-bold bg-accent/50 text-primary inline-flex items-center gap-0.5"><i class="bi bi-truck"></i></button>
                                        </template>
                                        <button type="button" @click="setFulfillment(r, r.fulfillment === 'dropship' ? 'stock' : 'dropship')"
                                                class="w-6 h-6 flex-shrink-0 rounded-md grid place-items-center text-gray-400"
                                                :title="r.fulfillment === 'dropship' ? @js(__('market.in_stock_mode')) : @js(__('market.dropship_mode'))">
                                            <i class="bi text-xs" :class="r.fulfillment === 'dropship' ? 'bi-box-seam' : 'bi-truck'"></i>
                                        </button>
                                        <button type="button" @click="r._descOpen = !r._descOpen"
                                                class="w-6 h-6 flex-shrink-0 rounded-md grid place-items-center"
                                                :class="(r.description || '').trim() ? 'text-primary' : 'text-gray-400'"
                                                :title="@js(__('market.variant_description'))">
                                            <i class="bi bi-card-text text-xs"></i>
                                        </button>
                                        <button type="button" @click="r.is_active = !r.is_active"
                                                class="w-6 h-6 flex-shrink-0 rounded-md grid place-items-center"
                                                :class="r.is_active ? 'text-green-600' : 'text-gray-400'">
                                            <i class="bi text-xs" :class="r.is_active ? 'bi-check-circle-fill' : 'bi-slash-circle'"></i>
                                        </button>
                                        <button type="button" @click="pruneRow(ri)" class="w-6 h-6 flex-shrink-0 rounded-md grid place-items-center text-gray-400"><i class="bi bi-x-lg text-xs"></i></button>
                                    </div>
                                    <textarea x-show="r._descOpen" x-cloak x-model="r.description" rows="2" maxlength="2000" placeholder="{{ __('market.variant_description_ph') }}"
                                              class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"></textarea>
                                    {{-- Dropship supplier + ships-in --}}
                                    <div x-show="r.fulfillment === 'dropship' && r._fulfillOpen" x-cloak class="grid grid-cols-2 gap-2">
                                        <input type="text" x-model="r.supplier" maxlength="120" placeholder="{{ __('market.supplier') }}"
                                               class="px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                                        <input type="text" x-model="r.ships_in" maxlength="60" placeholder="{{ __('market.ships_in') }}"
                                               class="px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.badge') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="b in badgeChoices" :key="b">
                                <button type="button" @click="badge=b"
                                        class="m-press px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                        :style="badge===b ? `background:${color}; color:#fff` : `background:${color}12; color:${color}`"
                                        x-text="b || @js(__('market.no_badge'))"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.availability') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in stockChoices" :key="s">
                                <button type="button" @click="stock=s"
                                        class="m-press px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                        :style="stock===s ? `background:${color}; color:#fff` : `background:${color}12; color:${color}`"
                                        x-text="s"></button>
                            </template>
                        </div>
                    </div>
                    <label class="flex items-center gap-3 pt-1">
                        <span class="relative inline-flex items-center">
                            <input type="checkbox" x-model="featured" class="sr-only peer">
                            <span class="w-11 h-6 rounded-full bg-gray-200 transition-colors" :style="featured ? `background:${color}` : ''"></span>
                            <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="featured ? 'translate-x-5' : ''"></span>
                        </span>
                        <span class="text-sm">
                            <span class="font-semibold text-gray-800">{{ __('market.featured') }}</span>
                            <span class="block text-[11px] text-gray-500">{{ __('market.featured_hint') }}</span>
                        </span>
                    </label>
                </div>

                {{-- STEP 4 · Stock / fulfilment --}}
                <div x-show="step===4" x-cloak class="space-y-4">
                    {{-- Variant products track stock per variant, set on the Price step. --}}
                    <div x-show="useVariants" class="flex gap-2 text-[13px] text-gray-600 bg-gray-50 rounded-2xl p-3.5">
                        <i class="bi bi-info-circle mt-0.5" :style="`color:${color}`"></i>
                        <span>{{ __('market.variants_stock_note') }}</span>
                    </div>
                  <div x-show="!useVariants" class="space-y-4">
                    <button type="button" @click="fulfillment='stock'"
                            class="m-press w-full text-left rounded-2xl border-2 p-4 transition-all"
                            :style="fulfillment==='stock' ? `border-color:${color}; background:${color}0d` : 'border-color:#e5e7eb'">
                        <span class="flex items-center justify-between">
                            <span class="flex items-center gap-2 font-bold text-gray-900"><i class="bi bi-box-seam" :style="`color:${color}`"></i> {{ __('market.in_stock_mode') }}</span>
                            <i class="bi bi-check-circle-fill" x-show="fulfillment==='stock'" :style="`color:${color}`"></i>
                        </span>
                        <span class="block text-xs text-gray-500 mt-1">{{ __('market.in_stock_desc') }}</span>
                    </button>
                    <div x-show="fulfillment==='stock'" x-collapse class="grid grid-cols-2 gap-3 pl-1">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('market.quantity_in_stock') }}</label>
                            <input type="number" x-model="quantity" min="0" inputmode="numeric" placeholder="0"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('market.low_stock_alert') }}</label>
                            <input type="number" x-model="lowStock" min="0" inputmode="numeric" placeholder="{{ __('market.low_stock_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        </div>
                    </div>

                    <button type="button" @click="fulfillment='dropship'"
                            class="m-press w-full text-left rounded-2xl border-2 p-4 transition-all"
                            :style="fulfillment==='dropship' ? `border-color:${color}; background:${color}0d` : 'border-color:#e5e7eb'">
                        <span class="flex items-center justify-between">
                            <span class="flex items-center gap-2 font-bold text-gray-900"><i class="bi bi-truck" :style="`color:${color}`"></i> {{ __('market.dropship_mode') }}</span>
                            <i class="bi bi-check-circle-fill" x-show="fulfillment==='dropship'" :style="`color:${color}`"></i>
                        </span>
                        <span class="block text-xs text-gray-500 mt-1">{{ __('market.dropship_desc') }}</span>
                    </button>
                    <div x-show="fulfillment==='dropship'" x-collapse class="space-y-3 pl-1">
                        <p class="text-[11px] text-gray-500 bg-gray-50 rounded-xl p-2.5 flex gap-2"><i class="bi bi-info-circle mt-0.5"></i> {{ __('market.dropship_note') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" x-model="supplier" maxlength="80" placeholder="{{ __('market.supplier_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                            <input type="text" x-model="shipsIn" maxlength="40" placeholder="{{ __('market.ships_in_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        </div>
                        <input type="url" x-model="supplierUrl" placeholder="https://…"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                  </div>{{-- /!useVariants fulfilment --}}
                </div>
            </div>
        </div>

        {{-- ============ SUCCESS ============ --}}
        <div x-show="success" x-cloak class="relative z-10 flex-1 flex flex-col items-center justify-center px-8 text-center">
            <template x-for="i in 14" :key="i">
                <span class="mw-spark" :style="`left:${(i*6.8)%100}%; animation-delay:${(i%7)*0.12}s; background:${i%2?'#fff':color}`"></span>
            </template>
            <div class="mw-pop w-24 h-24 rounded-full grid place-items-center bg-white shadow-2xl">
                <i class="bi bi-check-lg text-5xl" :style="`color:${color}`"></i>
            </div>
            <h2 class="text-2xl font-black mt-6">{{ __('market.wiz_published') }}</h2>
            <p class="text-white/70 text-sm mt-1" x-text="(name || @js(__('market.product_name'))) + ' ' + @js(__('market.wiz_is_live'))"></p>
            <button type="button" @click="close()" class="m-press mt-8 px-8 py-3 rounded-2xl bg-white font-bold text-sm" :style="`color:${color}`">
                {{ __('market.wiz_done') }}
            </button>
        </div>

        {{-- ============ FOOTER NAV ============ --}}
        <div class="relative z-10 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] flex items-center gap-3" x-show="!success">
            <button type="button" x-show="step>1" @click="back()" class="m-press w-12 h-12 rounded-2xl grid place-items-center bg-white/15 backdrop-blur" aria-label="{{ __('shared.cancel') }}">
                <i class="bi bi-arrow-left text-lg"></i>
            </button>
            <button type="button" x-show="step < steps.length" @click="next()" :disabled="!canNext()"
                    class="m-press flex-1 h-12 rounded-2xl bg-white font-bold text-sm grid place-items-center disabled:opacity-50" :style="`color:${color}`">
                <span class="flex items-center gap-2">{{ __('market.wiz_next') }} <i class="bi bi-arrow-right"></i></span>
            </button>
            <button type="button" x-show="step === steps.length" @click="publish()" :disabled="saving"
                    class="m-press flex-1 h-12 rounded-2xl bg-white font-bold text-sm grid place-items-center disabled:opacity-60" :style="`color:${color}`">
                <span class="flex items-center gap-2">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-rocket-takeoff-fill'"></i>
                    {{ __('market.wiz_publish') }}
                </span>
            </button>
        </div>
    </div>
</template>
</div>

@include('components.market._variant-matrix')
@once
<style>
    .mw-bg { position:absolute; inset:0; z-index:0; }
    .mw-grain { position:absolute; inset:0; z-index:0; opacity:.05; pointer-events:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
    .mw-card { animation: mw-rise .5s cubic-bezier(.2,.7,.2,1) both; }
    @keyframes mw-rise { from { transform: translateY(16px); opacity:0 } to { transform:none; opacity:1 } }
    .mw-float { animation: mw-fl 4s ease-in-out infinite; }
    @keyframes mw-fl { 0%,100%{ transform:translateY(0) rotate(-2deg) } 50%{ transform:translateY(-6px) rotate(2deg) } }
    .mw-bump { animation: mw-bump .35s ease; }
    @keyframes mw-bump { 0%{ transform:scale(1) } 40%{ transform:scale(1.05) } 100%{ transform:scale(1) } }
    .mw-pop { animation: mw-pop .5s cubic-bezier(.2,1.4,.4,1) both; }
    @keyframes mw-pop { from { transform:scale(.3); opacity:0 } to { transform:scale(1); opacity:1 } }
    .mw-spark { position:absolute; top:38%; width:8px; height:8px; border-radius:99px; opacity:0; animation: mw-spark 1.1s ease-out forwards; }
    @keyframes mw-spark { 0%{ transform:translateY(0) scale(.4); opacity:0 } 20%{ opacity:1 } 100%{ transform:translateY(-160px) scale(1); opacity:0 } }
    @media (prefers-reduced-motion: reduce) { .mw-card,.mw-float,.mw-bump,.mw-pop,.mw-spark { animation:none !important } }
</style>
<script>
window.marketProductWizard = function (cfg) {
    return {
        ...window.marketVariantMatrix(),   // attributes + variations engine
        isOpen: false, success: false, saving: false, bump: false,
        step: 1,
        steps: [@js(__('market.wiz_step_look')), @js(__('market.wiz_step_details')), @js(__('market.wiz_step_price')), @js(__('market.wiz_step_stock'))],
        cats: cfg.cats, iconChoices: cfg.icons, colorChoices: cfg.colors, badgeChoices: cfg.badges, stockChoices: cfg.stocks,
        _event: cfg.event, _action: cfg.action || '',
        // fields
        name:'', brand:'', cat: (cfg.cats[0] && cfg.cats[0].key) || 'gear', desc:'',
        price:'', old:'', cost:'', marginType:'fixed', marginValue:'', badge:'', stock:'In stock', featured:false,
        color:'#7c3aed', icon:'bi-bag', image:null,
        fulfillment:'stock', quantity:'', lowStock:'', supplier:'', supplierUrl:'', shipsIn:'',
        useVariants:true,

        open() { this.reset(); this.isOpen = true; document.documentElement.style.overflow = 'hidden'; },
        close() { this.isOpen = false; document.documentElement.style.overflow = ''; },
        reset() {
            this.success=false; this.saving=false; this.step=1;
            this.name=''; this.brand=''; this.cat=(this.cats[0]&&this.cats[0].key)||'gear'; this.desc='';
            this.price=''; this.old=''; this.cost=''; this.marginType='fixed'; this.marginValue=''; this.badge=''; this.stock='In stock'; this.featured=false;
            this.color='#7c3aed'; this.icon='bi-bag'; this.image=null;
            this.fulfillment='stock'; this.quantity=''; this.lowStock=''; this.supplier=''; this.supplierUrl=''; this.shipsIn='';
            this.useVariants=true;
            // Reset the attribute/variation matrix (provided by marketVariantMatrix).
            this.attributes=[]; this.rows=[]; this.newAttrName=''; this.bulk={ price:'' };
        },
        pulse() { this.bump=true; setTimeout(()=>this.bump=false, 360); },

        // Variant matrix (attributes + generated variations) is provided by the
        // shared marketVariantMatrix() mixin — see components/market/_variant-matrix.
        displayPrice() {
            if (this.useVariants && this.hasAttributes()) {
                const from = this.matrixFromPrice();
                if (from > 0) return from;
            }
            return parseFloat(this.price)||0;
        },
        pickPhoto(e) {
            const f=(e.target.files||[])[0]; e.target.value='';
            if(!f||!f.type.startsWith('image/')) return;
            const r=new FileReader(); r.onload=()=>{ this.image=r.result; this.pulse(); }; r.readAsDataURL(f);
        },
        canNext() {
            if (this.step===2) return this.name.trim().length>0;
            if (this.step===3) {
                if (this.useVariants && this.hasAttributes()) {
                    return this.matrixValid();
                }
                return (parseFloat(this.price)||0) > 0;
            }
            return true;
        },
        next() { if (this.canNext() && this.step<this.steps.length) this.step++; },
        back() { if (this.step>1) this.step--; },
        // Margin-based pricing: cost + margin lock the selling price.
        autoPrice() { return (parseFloat(this.cost)||0) > 0 && this.marginValue !== '' && this.marginValue !== null; },
        recompute() {
            if (!this.autoPrice()) return;
            const c=parseFloat(this.cost)||0, v=parseFloat(this.marginValue)||0;
            this.price = this.marginType==='percent' ? +(c*(1+v/100)).toFixed(2) : +(c+v).toFixed(2);
        },
        profitAmount() { return Math.max(0, (parseFloat(this.price)||0) - (parseFloat(this.cost)||0)).toFixed(2); },
        profitPct() { const c=parseFloat(this.cost)||0; return c>0 ? Math.round((((parseFloat(this.price)||0)-c)/c)*100) : 0; },
        payload() {
            const useVariants = this.useVariants && this.hasAttributes();
            const variants = useVariants ? this.matrixRows() : [];
            const attributes = useVariants ? this.matrixPayloadAttributes() : [];
            const hasVariants = variants.length > 0;
            const basePrice = hasVariants
                ? Math.min(...variants.map(v=>v.price).filter(p=>p>0))
                : (parseFloat(this.price)||0);
            const fulfillment = hasVariants ? 'stock' : this.fulfillment;
            return {
                name:this.name.trim(), brand:this.brand.trim(), cat:this.cat,
                price:basePrice, old:this.old?parseFloat(this.old):null,
                cost:this.cost!==''&&this.cost!==null?parseFloat(this.cost):null,
                marginType:this.marginType||'fixed',
                marginValue:this.marginValue!==''&&this.marginValue!==null?parseFloat(this.marginValue):null,
                badge:this.badge||null, stock:this.stock, featured:!!this.featured,
                color:this.color, icon:this.icon, image:this.image, desc:this.desc.trim(),
                colors:[], specs:[],
                attributes:attributes,
                useVariants:hasVariants, variants:variants,
                fulfillment:fulfillment,
                quantity:(!hasVariants && fulfillment==='stock')?(parseInt(this.quantity)||0):null,
                lowStock:(!hasVariants && fulfillment==='stock'&&this.lowStock!=='')?parseInt(this.lowStock):null,
                supplier:fulfillment==='dropship'?this.supplier.trim():null,
                supplierUrl:fulfillment==='dropship'?this.supplierUrl.trim():null,
                shipsIn:fulfillment==='dropship'?this.shipsIn.trim():null,
            };
        },
        async publish() {
            if (this.saving) return;
            this.saving = true;
            const data = this.payload();

            // No action → demo: celebrate after a short beat.
            if (!this._action) {
                setTimeout(() => { this.$dispatch(this._event, data); this.saving = false; this.success = true; }, 450);
                return;
            }
            try {
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                const res = await fetch(this._action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify(data),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.$dispatch(this._event, d.product || data);
                this.success = true;
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>
@endonce
