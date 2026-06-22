@props([
    'mode'            => 'create',                 // create | edit
    'action'         => '',                        // POST/PUT url; form UI only if empty
    'method'         => 'POST',                     // POST (create) | PUT (edit)
    'eventName'      => 'market-product-saved',    // CustomEvent dispatched on save
    'categories'     => null,                       // [['key'=>..,'label'=>..], ..]
    'product'        => null,                        // existing product (edit prefill)
    'showFulfillment' => false,                      // club shop: stock vs dropship section
    'editEvent'      => null,                         // window event that loads a product into this form for editing
])

@php
    // Standalone-friendly defaults so the form works without a backend yet.
    $cats = $categories ?? [
        ['key' => 'gear',      'label' => 'Gear'],
        ['key' => 'equipment', 'label' => 'Equipment'],
        ['key' => 'nutrition', 'label' => 'Nutrition'],
        ['key' => 'passes',    'label' => 'Passes'],
        ['key' => 'apparel',   'label' => 'Apparel'],
    ];

    $init = [
        'name'     => $product['name']     ?? '',
        'brand'    => $product['brand']    ?? '',
        'cat'      => $product['cat']      ?? ($cats[0]['key'] ?? 'gear'),
        'price'    => $product['price']    ?? '',
        'old'      => $product['old']      ?? '',
        'cost'        => $product['cost']        ?? '',
        'marginType'  => $product['marginType']  ?? 'fixed',
        'marginValue' => $product['marginValue'] ?? '',
        'badge'    => $product['badge']    ?? '',
        'stock'    => $product['stock']    ?? 'In stock',
        'featured' => $product['featured'] ?? false,
        'color'    => $product['color']    ?? '#7c3aed',
        'icon'     => $product['icon']     ?? 'bi-bag',
        'desc'     => $product['desc']     ?? '',
        'image'    => $product['image']    ?? null,
        'colors'   => $product['colors']   ?? [],
        'specs'    => isset($product['specs'])
            ? collect($product['specs'])->map(fn ($s) => ['label' => $s[0] ?? '', 'value' => $s[1] ?? ''])->values()->all()
            : [['label' => '', 'value' => '']],
        // Fulfillment (club shop only)
        'fulfillment' => $product['fulfillment'] ?? 'stock',   // stock | dropship
        'quantity'    => $product['quantity']    ?? '',
        'lowStock'    => $product['lowStock']    ?? '',
        'supplier'    => $product['supplier']    ?? '',
        'supplierUrl' => $product['supplierUrl'] ?? '',
        'shipsIn'     => $product['shipsIn']     ?? '',
    ];

    $iconChoices = ['bi-bag','bi-bicycle','bi-cup-hot','bi-ticket-perforated','bi-person-arms-up','bi-trophy','bi-lightning-charge-fill','bi-grid','bi-droplet-half','bi-heart-pulse','bi-stopwatch','bi-basket','bi-box-seam','bi-water','bi-shield-check','bi-stars'];
    $colorChoices = ['#7c3aed','#6d28d9','#ec4899','#ef4444','#f59e0b','#10b981','#0ea5e9','#111827'];
    $badgeChoices = ['','Sale','New','Best value','Limited'];
    $stockChoices = ['In stock','Digital','Made to order','Out of stock'];
@endphp

<div x-data="marketProductForm({{ Illuminate\Support\Js::from($init) }}, {{ Illuminate\Support\Js::from(['action' => $action, 'method' => strtoupper($method), 'event' => $eventName, 'mode' => $mode, 'editEvent' => $editEvent]) }})"
     class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 items-start">

    {{-- ============ Signature: live product-card preview ============ --}}
    <div class="lg:sticky lg:top-4">
        <p class="text-xs font-medium text-muted-foreground mb-2 flex items-center gap-1.5">
            <i class="bi bi-eye"></i> {{ __('market.live_preview') }}
        </p>
        {{-- exact grid card from market.blade.php, bound live --}}
        <div class="w-full max-w-[180px] mx-auto lg:mx-0 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="relative">
                <div class="aspect-square grid place-items-center"
                     :style="image ? '' : `background: linear-gradient(160deg, ${color}18, ${color}08)`">
                    <template x-if="image"><img :src="image" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!image"><i class="bi text-5xl" :class="icon" :style="`color:${color}`"></i></template>
                </div>
                <template x-if="badge">
                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold text-white"
                          :style="`background:${badge==='Sale' ? '#ef4444' : (badge==='New' ? '#10b981' : color)}`" x-text="badge"></span>
                </template>
            </div>
            <div class="p-3 flex flex-col flex-1">
                <p class="text-[10px] text-muted-foreground uppercase tracking-wide truncate" x-text="brand || '{{ __('market.brand') }}'"></p>
                <p class="font-bold text-foreground text-sm leading-tight mt-0.5 line-clamp-2" x-text="name || '{{ __('market.product_name') }}'"></p>
                <div class="flex items-center gap-1 mt-1 text-[11px] text-muted-foreground">
                    <i class="bi bi-star-fill text-amber-400 text-[10px]"></i> 0.0 <span class="text-gray-300">·</span> 0
                </div>
                <div class="flex items-end justify-between mt-auto pt-2">
                    <div>
                        <p class="text-sm font-black text-foreground">BHD <span x-text="(parseFloat(price)||0).toFixed(2)"></span></p>
                        <template x-if="old"><p class="text-[10px] text-muted-foreground line-through">BHD <span x-text="(parseFloat(old)||0).toFixed(2)"></span></p></template>
                    </div>
                    <span class="w-9 h-9 rounded-xl grid place-items-center text-white" :style="`background:${color}`"><i class="bi bi-plus-lg"></i></span>
                </div>
            </div>
        </div>
        <p class="text-[11px] text-muted-foreground mt-2 text-center lg:text-left">{{ __('market.preview_hint') }}</p>
    </div>

    {{-- ============ Fields ============ --}}
    <form @submit.prevent="save()" class="space-y-6">

        {{-- Photo + appearance --}}
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-image text-primary"></i> {{ __('market.appearance') }}</h3>

            <input type="file" x-ref="photo" accept="image/*" class="hidden" @change="pickPhoto($event)">
            <div class="flex items-center gap-4">
                <button type="button" @click="$refs.photo.click()"
                        class="relative w-20 h-20 rounded-xl border-2 border-dashed border-gray-200 grid place-items-center overflow-hidden hover:border-primary transition-colors flex-shrink-0"
                        :style="image ? '' : `background: linear-gradient(160deg, ${color}18, ${color}08)`">
                    <template x-if="image"><img :src="image" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!image"><i class="bi text-2xl" :class="icon" :style="`color:${color}`"></i></template>
                </button>
                <div class="text-sm">
                    <button type="button" @click="$refs.photo.click()" class="font-medium text-primary hover:underline">{{ __('market.upload_photo') }}</button>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('market.photo_hint') }}</p>
                    <button type="button" x-show="image" @click="image=null" class="text-xs text-red-600 hover:underline mt-1">{{ __('market.remove_photo') }}</button>
                </div>
            </div>

            {{-- Icon (fallback when no photo) --}}
            <div x-show="!image" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.icon') }}</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($iconChoices as $ic)
                        <button type="button" @click="icon='{{ $ic }}'"
                                class="w-9 h-9 rounded-lg grid place-items-center transition-colors"
                                :class="icon==='{{ $ic }}' ? 'bg-accent text-primary ring-2 ring-primary' : 'bg-muted text-muted-foreground hover:bg-accent'">
                            <i class="bi {{ $ic }}"></i>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Theme colour --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.theme_color') }}</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($colorChoices as $col)
                        <button type="button" @click="color='{{ $col }}'"
                                class="w-8 h-8 rounded-full border-2 transition-transform"
                                :class="color==='{{ $col }}' ? 'border-foreground scale-110' : 'border-transparent'"
                                style="background: {{ $col }}" aria-label="{{ __('market.theme_color') }}"></button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Basics --}}
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-card-text text-primary"></i> {{ __('market.basics') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.product_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" x-model="name" name="name" required maxlength="80"
                           placeholder="{{ __('market.product_name_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.brand') }}</label>
                    <input type="text" x-model="brand" name="brand" maxlength="60"
                           placeholder="{{ __('market.brand_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.category') }}</label>
                <select x-model="cat" name="cat"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    @foreach($cats as $c)
                        <option value="{{ $c['key'] }}">{{ $c['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.description') }}</label>
                <textarea x-model="desc" name="desc" rows="3" maxlength="600"
                          placeholder="{{ __('market.description_ph') }}"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"></textarea>
            </div>
        </section>

        {{-- Pricing --}}
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-tag text-primary"></i> {{ __('market.pricing') }}</h3>
            {{-- Cost + profit margin → selling price is derived & locked --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.cost_per_unit') }}</label>
                    <input type="number" x-model="cost" name="cost" min="0" step="0.01" placeholder="0.00" @input="recompute()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <p class="text-[11px] text-muted-foreground mt-1">{{ __('market.cost_hint') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.profit_margin') }}</label>
                    <div class="flex gap-2">
                        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 flex-shrink-0">
                            <button type="button" @click="marginType='fixed'; recompute()"
                                    class="px-2.5 py-1.5 rounded-md text-xs font-semibold transition-colors"
                                    :class="marginType==='fixed' ? 'bg-primary text-white' : 'text-muted-foreground hover:text-foreground'">{{ __('market.margin_fixed') }}</button>
                            <button type="button" @click="marginType='percent'; recompute()"
                                    class="px-2.5 py-1.5 rounded-md text-xs font-semibold transition-colors"
                                    :class="marginType==='percent' ? 'bg-primary text-white' : 'text-muted-foreground hover:text-foreground'">%</button>
                        </div>
                        <input type="number" x-model="marginValue" min="0" step="0.01" @input="recompute()"
                               :placeholder="marginType==='percent' ? '25' : '0.00'"
                               class="flex-1 min-w-0 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1" x-show="autoPrice()">
                        {{ __('market.margin_label') }}: <span class="font-semibold text-green-600">BHD <span x-text="profitAmount()"></span></span>
                        <span x-show="marginType==='fixed'">(<span x-text="profitPct()"></span>%)</span>
                    </p>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.price') }} (BHD) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" x-model="price" name="price" min="0" step="0.01" required placeholder="0.00"
                               :readonly="autoPrice()"
                               :class="autoPrice() ? 'bg-muted font-semibold cursor-not-allowed pr-9' : ''"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        <i class="bi bi-lock-fill absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" x-show="autoPrice()"></i>
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1" x-show="autoPrice()"><i class="bi bi-info-circle mr-0.5"></i>{{ __('market.price_auto') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.old_price') }}</label>
                    <input type="number" x-model="old" name="old" min="0" step="0.01"
                           placeholder="{{ __('market.old_price_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <p class="text-[11px] text-muted-foreground mt-1" x-show="(parseFloat(old)||0) > (parseFloat(price)||0)">
                        <span class="text-red-500 font-semibold" x-text="'-' + Math.round(((old-price)/old)*100) + '%'"></span> {{ __('market.discount_shown') }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.badge') }}</label>
                    <select x-model="badge" name="badge"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        @foreach($badgeChoices as $b)
                            <option value="{{ $b }}">{{ $b === '' ? __('market.no_badge') : $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.availability') }}</label>
                    <select x-model="stock" name="stock"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                        @foreach($stockChoices as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-3 pt-1 cursor-pointer">
                <span class="relative inline-flex items-center">
                    <input type="checkbox" x-model="featured" name="featured" class="sr-only peer">
                    <span class="w-10 h-6 rounded-full bg-gray-200 peer-checked:bg-primary transition-colors"></span>
                    <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                </span>
                <span class="text-sm">
                    <span class="font-medium text-foreground">{{ __('market.featured') }}</span>
                    <span class="block text-xs text-muted-foreground">{{ __('market.featured_hint') }}</span>
                </span>
            </label>
        </section>

        {{-- Variants: colour options + specs --}}
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-5">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-sliders text-primary"></i> {{ __('market.options') }}</h3>

            {{-- Colour options (swatches shown on the detail page) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.color_options') }}</label>
                <div class="flex flex-wrap items-center gap-2">
                    <template x-for="(c, i) in colors" :key="i">
                        <span class="relative">
                            <span class="block w-8 h-8 rounded-full border border-gray-200" :style="`background:${c}`"></span>
                            <button type="button" @click="colors.splice(i,1)"
                                    class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-black/60 text-white grid place-items-center text-[9px]"><i class="bi bi-x"></i></button>
                        </span>
                    </template>
                    {{-- add a colour --}}
                    <input type="color" @change="addColor($event.target.value)" value="#7c3aed"
                           class="w-8 h-8 rounded-full border border-dashed border-gray-300 cursor-pointer p-0 bg-transparent" aria-label="{{ __('market.add_color') }}">
                </div>
                <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('market.color_options_hint') }}</p>
            </div>

            {{-- Specs (repeatable label/value) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.specifications') }}</label>
                <div class="space-y-2">
                    <template x-for="(s, i) in specs" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="text" x-model="s.label" maxlength="40" placeholder="{{ __('market.spec_label_ph') }}"
                                   class="flex-1 min-w-0 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                            <input type="text" x-model="s.value" maxlength="60" placeholder="{{ __('market.spec_value_ph') }}"
                                   class="flex-1 min-w-0 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                            <button type="button" x-show="specs.length > 1" @click="specs.splice(i,1)"
                                    class="w-8 h-8 flex-shrink-0 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted transition-colors"><i class="bi bi-x-lg text-sm"></i></button>
                        </div>
                    </template>
                </div>
                <button type="button" x-show="specs.length < 10" @click="specs.push({label:'',value:''})"
                        class="mt-2 inline-flex items-center gap-1.5 text-[13px] font-medium text-primary hover:opacity-80">
                    <i class="bi bi-plus-circle"></i> {{ __('market.add_spec') }}
                </button>
            </div>
        </section>

        {{-- Fulfillment (club shop): hold stock or dropship --}}
        @if($showFulfillment)
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-truck text-primary"></i> {{ __('market.fulfillment') }}</h3>

            {{-- mode toggle --}}
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="fulfillment='stock'"
                        class="text-left rounded-xl border-2 p-3 transition-colors"
                        :class="fulfillment==='stock' ? 'border-primary bg-accent/40' : 'border-gray-200 hover:border-gray-300'">
                    <span class="flex items-center gap-2 text-sm font-semibold text-foreground"><i class="bi bi-box-seam"></i> {{ __('market.in_stock_mode') }}</span>
                    <span class="block text-xs text-muted-foreground mt-0.5">{{ __('market.in_stock_desc') }}</span>
                </button>
                <button type="button" @click="fulfillment='dropship'"
                        class="text-left rounded-xl border-2 p-3 transition-colors"
                        :class="fulfillment==='dropship' ? 'border-primary bg-accent/40' : 'border-gray-200 hover:border-gray-300'">
                    <span class="flex items-center gap-2 text-sm font-semibold text-foreground"><i class="bi bi-truck"></i> {{ __('market.dropship_mode') }}</span>
                    <span class="block text-xs text-muted-foreground mt-0.5">{{ __('market.dropship_desc') }}</span>
                </button>
            </div>

            {{-- in-stock fields --}}
            <div x-show="fulfillment==='stock'" x-cloak class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.quantity_in_stock') }}</label>
                    <input type="number" x-model="quantity" min="0" step="1" placeholder="0"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.low_stock_alert') }}</label>
                    <input type="number" x-model="lowStock" min="0" step="1" placeholder="{{ __('market.low_stock_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
            </div>

            {{-- dropship fields --}}
            <div x-show="fulfillment==='dropship'" x-cloak class="space-y-4">
                <div class="flex items-start gap-2 text-xs text-muted-foreground bg-muted rounded-lg p-3">
                    <i class="bi bi-info-circle mt-0.5"></i>
                    <span>{{ __('market.dropship_note') }}</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.supplier') }}</label>
                        <input type="text" x-model="supplier" maxlength="80" placeholder="{{ __('market.supplier_ph') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.ships_in') }}</label>
                        <input type="text" x-model="shipsIn" maxlength="40" placeholder="{{ __('market.ships_in_ph') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.supplier_url') }}</label>
                    <input type="url" x-model="supplierUrl" placeholder="https://…"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
            </div>
        </section>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <button type="button" @click="$dispatch('market-form-cancel')"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors">{{ __('shared.cancel') }}</button>
            <button type="submit" :disabled="!canSave() || saving"
                    class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 inline-flex items-center gap-2">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : (mode==='edit' ? 'bi-check-lg' : 'bi-bag-plus')"></i>
                <span x-text="mode==='edit' ? @js(__('market.save_changes')) : @js(__('market.publish_product'))"></span>
            </button>
        </div>
    </form>
</div>

@once
<script>
window.marketProductForm = function (init, opts) {
    return {
        ...init,
        saving: false,
        mode: opts.mode || 'create',
        _action: opts.action || '',
        _method: opts.method || 'POST',
        _event: opts.event || 'market-product-saved',
        _editEvent: opts.editEvent || null,

        init() {
            // Edit modals reuse a single form instance: a window event hands it
            // the product to edit plus the per-product update URL.
            if (this._editEvent) {
                window.addEventListener(this._editEvent, (e) => this.loadProduct(e.detail.product, e.detail.action));
            }
        },

        loadProduct(p, action) {
            if (!p) return;
            this.mode = 'edit';
            this._method = 'PUT';
            this._action = action || '';
            this.name = p.name || '';
            this.brand = p.brand || '';
            this.cat = p.cat || this.cat;
            this.price = p.price ?? '';
            this.old = p.old ?? '';
            this.cost = p.cost ?? '';
            this.marginType = p.marginType || 'fixed';
            this.marginValue = (p.marginValue ?? '') === null ? '' : (p.marginValue ?? '');
            this.badge = p.badge || '';
            this.stock = p.stock || 'In stock';
            this.featured = !!p.featured;
            this.color = p.color || '#7c3aed';
            this.icon = p.icon || 'bi-bag';
            this.image = p.image || null;
            this.desc = p.desc || '';
            this.colors = Array.isArray(p.colors) ? p.colors.slice() : [];
            this.specs = (Array.isArray(p.specs) && p.specs.length)
                ? p.specs.map(s => ({ label: s[0] || '', value: s[1] || '' }))
                : [{ label: '', value: '' }];
            this.fulfillment = p.fulfillment || 'stock';
            this.quantity = p.quantity ?? '';
            this.lowStock = p.lowStock ?? '';
            this.supplier = p.supplier || '';
            this.supplierUrl = p.supplierUrl || '';
            this.shipsIn = p.shipsIn || '';
        },

        pickPhoto(e) {
            const f = (e.target.files || [])[0];
            e.target.value = '';
            if (!f || !f.type.startsWith('image/')) return;
            const r = new FileReader();
            r.onload = () => { this.image = r.result; };
            r.readAsDataURL(f);
        },
        addColor(c) { if (c && !this.colors.includes(c)) this.colors.push(c); },
        canSave() { return this.name.trim() !== '' && (parseFloat(this.price) || 0) > 0; },

        // Margin-based pricing: a cost + margin lock the selling price.
        autoPrice() { return (parseFloat(this.cost) || 0) > 0 && this.marginValue !== '' && this.marginValue !== null; },
        recompute() {
            if (!this.autoPrice()) return;
            const c = parseFloat(this.cost) || 0, v = parseFloat(this.marginValue) || 0;
            this.price = this.marginType === 'percent' ? +(c * (1 + v / 100)).toFixed(2) : +(c + v).toFixed(2);
        },
        profitAmount() { return Math.max(0, (parseFloat(this.price) || 0) - (parseFloat(this.cost) || 0)).toFixed(2); },
        profitPct() { const c = parseFloat(this.cost) || 0; return c > 0 ? Math.round((((parseFloat(this.price) || 0) - c) / c) * 100) : 0; },

        payload() {
            return {
                name: this.name.trim(), brand: this.brand.trim(), cat: this.cat,
                price: parseFloat(this.price) || 0,
                old: this.old ? parseFloat(this.old) : null,
                cost: this.cost !== '' && this.cost !== null ? parseFloat(this.cost) : null,
                marginType: this.marginType || 'fixed',
                marginValue: this.marginValue !== '' && this.marginValue !== null ? parseFloat(this.marginValue) : null,
                badge: this.badge || null, stock: this.stock, featured: !!this.featured,
                color: this.color, icon: this.icon, image: this.image,
                desc: this.desc.trim(), colors: this.colors.slice(),
                specs: this.specs.filter(s => s.label.trim() && s.value.trim()).map(s => [s.label.trim(), s.value.trim()]),
                fulfillment: this.fulfillment,
                quantity: this.fulfillment === 'stock' ? (parseInt(this.quantity) || 0) : null,
                lowStock: this.fulfillment === 'stock' && this.lowStock !== '' ? parseInt(this.lowStock) : null,
                supplier: this.fulfillment === 'dropship' ? this.supplier.trim() : null,
                supplierUrl: this.fulfillment === 'dropship' ? this.supplierUrl.trim() : null,
                shipsIn: this.fulfillment === 'dropship' ? this.shipsIn.trim() : null,
            };
        },

        async save() {
            if (!this.canSave() || this.saving) return;
            const data = this.payload();

            // No action wired → form UI only: just hand the data to listeners.
            if (!this._action) {
                this.$dispatch(this._event, data);
                window.showToast && window.showToast('success',
                    this.mode === 'edit' ? @js(__('market.product_updated')) : @js(__('market.product_published')));
                return;
            }

            this.saving = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                const res = await fetch(this._action, {
                    method: this._method,
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify(data),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.$dispatch(this._event, d.product || data);
                window.showToast && window.showToast('success', d.message ||
                    (this.mode === 'edit' ? @js(__('market.product_updated')) : @js(__('market.product_published'))));
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
