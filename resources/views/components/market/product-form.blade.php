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
        // Variants (size / colour / brand — each its own price + stock).
        // Lead with variants in the club shop: ON by default for a new product
        // (still toggleable). Editing respects the product's saved state; the
        // general market (no fulfillment section) stays off.
        'useVariants' => ! empty($product['hasVariants']) || ($mode !== 'edit' && $showFulfillment),
        'variants'    => isset($product['variants'])
            ? collect($product['variants'])->map(fn ($v) => [
                'id'        => $v['id'] ?? null,
                'brand'     => $v['brand'] ?? '',
                'size'      => $v['size'] ?? '',
                'color'     => $v['color'] ?? '',
                'color_hex' => $v['color_hex'] ?? '#7c3aed',
                'price'     => $v['price'] ?? '',
                'old_price' => $v['old_price'] ?? '',
                'quantity'  => $v['quantity'] ?? '',
                'is_active' => array_key_exists('is_active', $v) ? (bool) $v['is_active'] : true,
            ])->values()->all()
            : [],
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
                        <p class="text-sm font-black text-foreground"><span x-show="useVariants && totalRows()" class="text-[9px] font-semibold text-muted-foreground">{{ __('market.from_price') }} </span>BHD <span x-text="displayPrice().toFixed(2)"></span></p>
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
                        <input type="number" x-model="price" name="price" min="0" step="0.01" :required="!useVariants" placeholder="0.00"
                               :readonly="autoPrice() || useVariants"
                               :class="(autoPrice() || useVariants) ? 'bg-muted font-semibold cursor-not-allowed pr-9' : ''"
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

        {{-- Variants: named variants (e.g. Adidas, Kwon), each with its OWN
             colours + sizes + price + stock — independent of the others. --}}
        @if($showFulfillment)
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-foreground flex items-center gap-2"><i class="bi bi-rulers text-primary"></i> {{ __('market.variants') }}</h3>
                    <p class="text-[11px] text-muted-foreground mt-1">{{ __('market.variants_hint') }}</p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer flex-shrink-0">
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" x-model="useVariants" class="sr-only peer">
                        <span class="w-10 h-6 rounded-full bg-gray-200 peer-checked:bg-primary transition-colors"></span>
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                    </span>
                </label>
            </div>

            <div x-show="useVariants" x-cloak class="space-y-4">
                {{-- Add a named variant --}}
                <div class="flex items-center gap-2">
                    <input type="text" x-model="newGroup" @keydown.enter.prevent="addGroup()" maxlength="80" placeholder="{{ __('market.variant_name_ph') }}"
                           class="flex-1 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    <button type="button" @click="addGroup()" class="px-3 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold flex items-center gap-1.5 whitespace-nowrap"><i class="bi bi-plus-lg"></i> {{ __('market.variant_add') }}</button>
                </div>

                <div x-show="groups.length === 0" class="text-sm text-muted-foreground italic">{{ __('market.variant_group_empty') }}</div>

                {{-- Bulk price across every variant row --}}
                <div x-show="totalRows() > 0" class="flex items-center justify-end gap-2">
                    <input type="number" x-model="bulk.price" min="0" step="0.01" placeholder="{{ __('market.opt_bulk_price') }}"
                           class="w-28 px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-xs">
                    <button type="button" @click="applyBulkPrice()" class="px-2.5 py-1.5 rounded-lg bg-muted text-gray-600 text-xs font-semibold hover:bg-accent">{{ __('market.opt_apply_all') }}</button>
                </div>

                {{-- One card per named variant: its own colours, sizes & price/stock grid --}}
                <template x-for="(g, gi) in groups" :key="gi">
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between gap-2 bg-muted/40 px-3 py-2.5 border-b border-gray-200">
                            <span class="font-semibold text-sm text-foreground flex items-center gap-1.5 min-w-0"><i class="bi bi-tag text-primary flex-shrink-0"></i> <span class="truncate" x-text="g.name"></span></span>
                            <button type="button" @click="removeGroup(gi)" class="w-7 h-7 rounded-lg grid place-items-center text-gray-400 hover:bg-red-50 hover:text-red-600 flex-shrink-0" :title="@js(__('market.variant_remove'))"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="p-3 space-y-3">
                            {{-- Colours (free text — not a colour picker) --}}
                            <div class="rounded-xl border border-gray-100 bg-muted/30 p-3">
                                <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('market.opt_colors') }}</p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <template x-for="(c, ci) in g.colors" :key="ci">
                                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-gray-200 text-xs font-medium text-gray-700">
                                            <span x-text="c"></span>
                                            <button type="button" @click="gRemoveColor(g, ci)" class="w-4 h-4 rounded-full grid place-items-center text-gray-400 hover:bg-red-50 hover:text-red-600"><i class="bi bi-x text-[11px]"></i></button>
                                        </span>
                                    </template>
                                    <input type="text" x-model="g.newColor" @keydown.enter.prevent="gAddColor(g)" maxlength="60" placeholder="{{ __('market.variant_color_ph') }}"
                                           class="flex-1 min-w-[120px] px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm bg-white">
                                    <button type="button" @click="gAddColor(g)" class="px-2.5 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-semibold">{{ __('market.opt_add') }}</button>
                                </div>
                            </div>
                            {{-- Sizes --}}
                            <div class="rounded-xl border border-gray-100 bg-muted/30 p-3">
                                <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('market.opt_sizes') }}</p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <template x-for="(s, si) in g.sizes" :key="si">
                                        <span class="inline-flex items-center gap-1 pl-2.5 pr-1 py-1 rounded-full bg-white border border-gray-200 text-xs font-medium text-gray-700">
                                            <span x-text="s"></span>
                                            <button type="button" @click="gRemoveSize(g, si)" class="w-4 h-4 rounded-full grid place-items-center text-gray-400 hover:bg-red-50 hover:text-red-600"><i class="bi bi-x text-[11px]"></i></button>
                                        </span>
                                    </template>
                                    <input type="text" x-model="g.newSize" @keydown.enter.prevent="gAddSize(g)" maxlength="40" placeholder="{{ __('market.variant_size_ph') }}"
                                           class="flex-1 min-w-[120px] px-2.5 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm bg-white">
                                    <button type="button" @click="gAddSize(g)" class="px-2.5 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-semibold">{{ __('market.opt_add') }}</button>
                                </div>
                            </div>

                            {{-- This variant's price/stock grid (colour × size) --}}
                            <div class="overflow-x-auto rounded-xl border border-gray-100">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-muted/50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                            <th class="px-3 py-2 font-semibold">{{ __('market.variants') }}</th>
                                            <th class="px-3 py-2 font-semibold w-28">{{ __('market.variant_price') }}</th>
                                            <th class="px-3 py-2 font-semibold w-24">{{ __('market.variant_qty') }}</th>
                                            <th class="px-2 py-2 w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(r, ri) in g.rows" :key="ri">
                                            <tr :class="r.is_active ? '' : 'opacity-50'">
                                                <td class="px-3 py-2"><span class="font-medium text-gray-700" x-text="rowLabel(g, r)"></span></td>
                                                <td class="px-3 py-2">
                                                    <input type="number" x-model="r.price" min="0" step="0.01" placeholder="0.00"
                                                           class="w-24 px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                                           :class="(parseFloat(r.price)||0) > 0 ? 'border-gray-200' : 'border-red-300'">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number" x-model="r.quantity" min="0" step="1" placeholder="{{ __('market.variant_qty_ph') }}"
                                                           class="w-20 px-2 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                                                </td>
                                                <td class="px-2 py-2 text-center">
                                                    <button type="button" @click="r.is_active = !r.is_active"
                                                            class="w-7 h-7 rounded-lg grid place-items-center transition-colors"
                                                            :class="r.is_active ? 'text-green-600 hover:bg-green-50' : 'text-gray-400 hover:bg-muted'"
                                                            :title="r.is_active ? @js(__('market.variant_active')) : @js(__('market.out_of_stock'))">
                                                        <i class="bi" :class="r.is_active ? 'bi-check-circle-fill' : 'bi-slash-circle'"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
        @endif

        {{-- Fulfillment (club shop): hold stock or dropship --}}
        @if($showFulfillment)
        <section class="bg-white rounded-xl border border-gray-100 p-4 sm:p-5 space-y-4" x-show="!useVariants" x-cloak>
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

        // Named variants. Each group = one variant (e.g. Adidas) with its OWN
        // colours + sizes, expanded into its own colour×size price/stock grid —
        // independent of every other variant. Flattened to `variants[]` on save.
        groups: [],
        newGroup: '',
        bulk: { price: '' },

        init() {
            // Edit modals reuse a single form instance: a window event hands it
            // the product to edit plus the per-product update URL.
            if (this._editEvent) {
                window.addEventListener(this._editEvent, (e) => this.loadProduct(e.detail.product, e.detail.action));
            }
            // Group the flat variant rows (init / edit prefill) back into
            // named-variant groups for the builder.
            this.groups = this.groupsFromVariants(this.variants);
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
            this.useVariants = !!p.hasVariants;
            this.groups = this.groupsFromVariants(Array.isArray(p.variants) ? p.variants : []);
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

        // ===== Named variants: groups → per-variant colour×size price/stock =====
        // Build builder groups from flat variant rows (grouped by variant name,
        // which lives in the `brand` column).
        groupsFromVariants(list) {
            const byName = {};
            const order = [];
            (list || []).forEach(v => {
                const name = (v.brand || '').trim();
                if (!byName[name]) { byName[name] = { name, colors: [], sizes: [], newColor: '', newSize: '', rows: [] }; order.push(name); }
                const g = byName[name];
                if (v.color && !g.colors.includes(v.color)) g.colors.push(v.color);
                if (v.size && !g.sizes.includes(v.size)) g.sizes.push(v.size);
                g.rows.push({
                    id: v.id || null, color: v.color || '', size: v.size || '',
                    price: v.price ?? '', old_price: v.old_price ?? '', quantity: v.quantity ?? '',
                    is_active: v.is_active !== false,
                });
            });
            return order.map(n => byName[n]);
        },

        addGroup() {
            const name = (this.newGroup || '').trim();
            if (!name) return;
            if (this.groups.some(g => g.name.toLowerCase() === name.toLowerCase())) { this.newGroup = ''; return; }
            const g = { name, colors: [], sizes: [], newColor: '', newSize: '', rows: [] };
            this.groups.push(g);
            this.newGroup = '';
            this.rebuildGroup(g);   // seed a single row so the variant is sellable even with no colour/size
        },
        removeGroup(i) { this.groups.splice(i, 1); },

        gAddColor(g) {
            const v = (g.newColor || '').trim();
            if (!v) return;
            if (!g.colors.some(c => c.toLowerCase() === v.toLowerCase())) g.colors.push(v);
            g.newColor = '';
            this.rebuildGroup(g);
        },
        gRemoveColor(g, i) { g.colors.splice(i, 1); this.rebuildGroup(g); },
        gAddSize(g) {
            const v = (g.newSize || '').trim();
            if (!v) return;
            if (!g.sizes.some(s => s.toLowerCase() === v.toLowerCase())) g.sizes.push(v);
            g.newSize = '';
            this.rebuildGroup(g);
        },
        gRemoveSize(g, i) { g.sizes.splice(i, 1); this.rebuildGroup(g); },

        // This variant's colour×size grid; preserve price/stock by colour|size.
        rebuildGroup(g) {
            const prev = {};
            (g.rows || []).forEach(r => { prev[`${r.color || ''}|${r.size || ''}`] = r; });
            const colors = g.colors.length ? g.colors : [''];
            const sizes  = g.sizes.length  ? g.sizes  : [''];
            const rows = [];
            colors.forEach(c => sizes.forEach(s => {
                const old = prev[`${c}|${s}`];
                rows.push({
                    id: old?.id || null, color: c, size: s,
                    price: old?.price ?? '', old_price: old?.old_price ?? '', quantity: old?.quantity ?? '',
                    is_active: old ? (old.is_active !== false) : true,
                });
            }));
            g.rows = rows;
        },

        rowLabel(g, r) { return [g.name, r.color, r.size].filter(Boolean).join(' · '); },

        applyBulkPrice() {
            const p = parseFloat(this.bulk.price);
            if (!(p > 0)) return;
            this.groups.forEach(g => g.rows.forEach(r => { r.price = p; }));
        },
        totalRows() { return this.groups.reduce((n, g) => n + g.rows.length, 0); },

        // Flatten groups → variant rows to save (variant name → `brand` column).
        flatVariants() {
            const out = [];
            this.groups.forEach(g => {
                const name = (g.name || '').trim();
                if (!name) return;
                g.rows.forEach(r => out.push({
                    id: r.id || null,
                    brand: name,
                    color: (r.color || '').trim() || null,
                    size: (r.size || '').trim() || null,
                    color_hex: null,
                    price: parseFloat(r.price) || 0,
                    old_price: (r.old_price !== '' && r.old_price != null) ? parseFloat(r.old_price) : null,
                    quantity: (r.quantity !== '' && r.quantity != null) ? (parseInt(r.quantity) || 0) : null,
                    is_active: r.is_active !== false,
                }));
            });
            return out;
        },
        // Save payload uses this (kept name for payload()).
        cleanVariants() { return this.flatVariants(); },

        // Lowest active-variant price, for the "from X" preview.
        displayPrice() {
            if (this.useVariants) {
                const ps = this.flatVariants().filter(v => v.is_active).map(v => v.price).filter(p => p > 0);
                if (ps.length) return Math.min(...ps);
            }
            return parseFloat(this.price) || 0;
        },

        canSave() {
            if (this.name.trim() === '') return false;
            if (this.useVariants) {
                const vs = this.flatVariants();
                // Every variant row must be priced before publishing.
                return vs.length > 0 && vs.every(v => v.price > 0);
            }
            return (parseFloat(this.price) || 0) > 0;
        },

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
            const variants = this.useVariants ? this.cleanVariants() : [];
            const useVariants = variants.length > 0;
            // With variants, the product base price is the lowest variant price
            // ("from X" display) and stock lives on the variants, not the product.
            const basePrice = useVariants
                ? Math.min(...variants.map(v => v.price).filter(p => p > 0))
                : (parseFloat(this.price) || 0);
            const fulfillment = useVariants ? 'stock' : this.fulfillment;

            return {
                name: this.name.trim(), brand: this.brand.trim(), cat: this.cat,
                price: basePrice,
                old: this.old ? parseFloat(this.old) : null,
                cost: this.cost !== '' && this.cost !== null ? parseFloat(this.cost) : null,
                marginType: this.marginType || 'fixed',
                marginValue: this.marginValue !== '' && this.marginValue !== null ? parseFloat(this.marginValue) : null,
                badge: this.badge || null, stock: this.stock, featured: !!this.featured,
                color: this.color, icon: this.icon, image: this.image,
                desc: this.desc.trim(), colors: this.colors.slice(),
                specs: this.specs.filter(s => s.label.trim() && s.value.trim()).map(s => [s.label.trim(), s.value.trim()]),
                useVariants: useVariants,
                variants: variants,
                fulfillment: fulfillment,
                quantity: (!useVariants && fulfillment === 'stock') ? (parseInt(this.quantity) || 0) : null,
                lowStock: (!useVariants && fulfillment === 'stock' && this.lowStock !== '') ? parseInt(this.lowStock) : null,
                supplier: fulfillment === 'dropship' ? this.supplier.trim() : null,
                supplierUrl: fulfillment === 'dropship' ? this.supplierUrl.trim() : null,
                shipsIn: fulfillment === 'dropship' ? this.shipsIn.trim() : null,
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
