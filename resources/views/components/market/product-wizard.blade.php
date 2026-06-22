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
                        <p class="text-sm font-black">BHD <span x-text="(parseFloat(price)||0).toFixed(2)"></span></p>
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
                    <div>
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

        open() { this.reset(); this.isOpen = true; document.documentElement.style.overflow = 'hidden'; },
        close() { this.isOpen = false; document.documentElement.style.overflow = ''; },
        reset() {
            this.success=false; this.saving=false; this.step=1;
            this.name=''; this.brand=''; this.cat=(this.cats[0]&&this.cats[0].key)||'gear'; this.desc='';
            this.price=''; this.old=''; this.cost=''; this.marginType='fixed'; this.marginValue=''; this.badge=''; this.stock='In stock'; this.featured=false;
            this.color='#7c3aed'; this.icon='bi-bag'; this.image=null;
            this.fulfillment='stock'; this.quantity=''; this.lowStock=''; this.supplier=''; this.supplierUrl=''; this.shipsIn='';
        },
        pulse() { this.bump=true; setTimeout(()=>this.bump=false, 360); },
        pickPhoto(e) {
            const f=(e.target.files||[])[0]; e.target.value='';
            if(!f||!f.type.startsWith('image/')) return;
            const r=new FileReader(); r.onload=()=>{ this.image=r.result; this.pulse(); }; r.readAsDataURL(f);
        },
        canNext() {
            if (this.step===2) return this.name.trim().length>0;
            if (this.step===3) return (parseFloat(this.price)||0)>0;
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
            return {
                name:this.name.trim(), brand:this.brand.trim(), cat:this.cat,
                price:parseFloat(this.price)||0, old:this.old?parseFloat(this.old):null,
                cost:this.cost!==''&&this.cost!==null?parseFloat(this.cost):null,
                marginType:this.marginType||'fixed',
                marginValue:this.marginValue!==''&&this.marginValue!==null?parseFloat(this.marginValue):null,
                badge:this.badge||null, stock:this.stock, featured:!!this.featured,
                color:this.color, icon:this.icon, image:this.image, desc:this.desc.trim(),
                colors:[], specs:[],
                fulfillment:this.fulfillment,
                quantity:this.fulfillment==='stock'?(parseInt(this.quantity)||0):null,
                lowStock:this.fulfillment==='stock'&&this.lowStock!==''?parseInt(this.lowStock):null,
                supplier:this.fulfillment==='dropship'?this.supplier.trim():null,
                supplierUrl:this.fulfillment==='dropship'?this.supplierUrl.trim():null,
                shipsIn:this.fulfillment==='dropship'?this.shipsIn.trim():null,
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
