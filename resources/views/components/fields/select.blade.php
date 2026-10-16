@props([
    'model'       => null,          // Alpine state path in a PARENT scope (e.g. "cat"). Omit for a standalone form control.
    'value'       => null,          // initial value when standalone (server-rendered forms)
    'options'     => [],            // [['value'=>..,'label'=>..], ..] | [['key'=>..,'label'=>..], ..] | ['a','b']
    'placeholder' => 'Select…',
    'name'        => null,          // hidden input name so it posts with a native form
    'error'       => null,          // validation message to show below
    'change'      => null,          // optional Alpine expression to run after a pick
    'panelClass'  => '',            // extra classes on the options panel (e.g. bottom-full for drop-up)
    // Optional: an Alpine expression in the PARENT scope that yields the options
    // ([{value, label}, …]) — for a list that arrives over the wire or changes
    // while the control is open. Omit it and `options` is used, exactly as before.
    'optionsExpr' => null,
    // Opt-in: a search box at the top of the panel, filtering by label (typed
    // anywhere in it, any case). Enter picks the first match.
    'searchable'  => false,
    'searchPlaceholder' => 'Search…',
    // Opt-in: each option may carry a `logo` (an image URL); it is drawn
    // bare and contained, never on a tile (Design Rule #5), with a neutral
    // shield where an option has none. Labels then wrap instead of truncating.
    'logos'       => false,
])
@php
    $opts = collect($options)->map(fn ($o) => is_array($o)
        ? ['value' => $o['value'] ?? $o['key'] ?? '', 'label' => $o['label'] ?? ($o['value'] ?? '')] + (isset($o['logo']) ? ['logo' => $o['logo']] : [])
        : ['value' => $o, 'label' => $o])->values();
    // Standalone (no parent Alpine model) manages its own `sel` state.
    $selfManaged = $model === null;
    $expr = $model ?? 'sel';
@endphp
{{-- Custom rounded dropdown (Design Rule #4 — never a native <select>). With a
     `model` it reads/writes that parent-Alpine property; otherwise it is a
     self-contained form control seeded from `value` and posted via `name`. --}}
<div x-data="{ open: false, @if($searchable) q: '', get shown() { const q = this.q.trim().toLowerCase(); return q ? this.opts.filter(o => String(o.label).toLowerCase().includes(q)) : this.opts; }, @endif @if($optionsExpr) get opts() { return ({{ $optionsExpr }}) || []; }, @else opts: {{ Illuminate\Support\Js::from($opts) }}, @endif @if($selfManaged) sel: {{ Illuminate\Support\Js::from((string) ($value ?? '')) }} @endif }"
     class="relative"
     @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" @click="open = !open{{ $searchable ? '; if (open) { q = \'\'; $nextTick(() => $refs.q && $refs.q.focus()); }' : '' }}"
            class="w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-white border rounded-xl text-sm text-left transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent {{ $error ? 'border-red-400' : 'border-gray-200' }}"
            :class="open ? 'ring-2 ring-purple-500 border-transparent' : ''">
        @if($logos)
        <span class="w-6 h-6 flex-shrink-0 grid place-items-center" x-show="opts.find(o => String(o.value) === String({{ $expr }}))">
            <template x-if="opts.find(o => String(o.value) === String({{ $expr }}))?.logo"><img :src="opts.find(o => String(o.value) === String({{ $expr }})).logo" alt="" class="w-full h-full object-contain" x-on:error="$el.replaceWith(Object.assign(document.createElement('i'), { className: 'bi bi-shield text-muted-foreground' }))"></template>
            <template x-if="! opts.find(o => String(o.value) === String({{ $expr }}))?.logo"><i class="bi bi-shield text-muted-foreground"></i></template>
        </span>
        @endif
        <span class="{{ $logos ? 'flex-1 min-w-0 break-words' : 'truncate' }}" :class="(opts.find(o => String(o.value) === String({{ $expr }})) ? 'text-foreground' : 'text-muted-foreground')"
              x-text="(opts.find(o => String(o.value) === String({{ $expr }}))?.label) || @js($placeholder)"></span>
        <i class="bi bi-chevron-down text-muted-foreground transition-transform shrink-0" :class="open && 'rotate-180'"></i>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute z-30 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden max-h-60 overflow-y-auto {{ $panelClass }}"
         style="display:none;">
        @if($searchable)
        <div class="sticky top-0 z-10 bg-white p-2 border-b border-gray-100">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="text" x-ref="q" x-model="q" placeholder="{{ $searchPlaceholder }}" autocomplete="off" spellcheck="false"
                       aria-label="{{ $searchPlaceholder }}"
                       @keydown.enter.prevent="if (shown.length) { {{ $expr }} = shown[0].value; open = false;{{ $change ? ' '.$change.';' : '' }} }"
                       class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>
        <p x-show="! shown.length" class="px-3 py-3 text-sm text-muted-foreground">{{ __('No matches.') }}</p>
        @endif
        <template x-for="opt in {{ $searchable ? 'shown' : 'opts' }}" :key="String(opt.value)">
            <button type="button" @click="{{ $expr }} = opt.value; open = false;{{ $change ? ' '.$change.';' : '' }}"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm text-left transition-colors hover:bg-muted/60"
                    :class="String({{ $expr }}) === String(opt.value) ? 'text-primary font-medium bg-accent/40' : 'text-foreground'">
                @if($logos)
                <span class="w-7 h-7 flex-shrink-0 grid place-items-center">
                    <template x-if="opt.logo"><img :src="opt.logo" alt="" loading="lazy" class="w-full h-full object-contain" x-on:error="$el.replaceWith(Object.assign(document.createElement('i'), { className: 'bi bi-shield text-muted-foreground' }))"></template>
                    <template x-if="! opt.logo"><i class="bi bi-shield text-muted-foreground"></i></template>
                </span>
                <span class="flex-1 min-w-0 break-words" x-text="opt.label"></span>
                @else
                <span class="truncate" x-text="opt.label"></span>
                @endif
                <i class="bi bi-check-lg text-primary shrink-0" x-show="String({{ $expr }}) === String(opt.value)"></i>
            </button>
        </template>
    </div>

    @if($name)<input type="hidden" name="{{ $name }}" :value="{{ $expr }}">@endif
    @if($error)<p class="mt-1 text-xs text-red-500">{{ $error }}</p>@endif
</div>
