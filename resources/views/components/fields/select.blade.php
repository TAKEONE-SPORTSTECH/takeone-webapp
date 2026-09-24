@props([
    'model'       => null,          // Alpine state path in a PARENT scope (e.g. "cat"). Omit for a standalone form control.
    'value'       => null,          // initial value when standalone (server-rendered forms)
    'options'     => [],            // [['value'=>..,'label'=>..], ..] | [['key'=>..,'label'=>..], ..] | ['a','b']
    'placeholder' => 'Select…',
    'name'        => null,          // hidden input name so it posts with a native form
    'error'       => null,          // validation message to show below
    'change'      => null,          // optional Alpine expression to run after a pick
    'panelClass'  => '',            // extra classes on the options panel (e.g. bottom-full for drop-up)
])
@php
    $opts = collect($options)->map(fn ($o) => is_array($o)
        ? ['value' => $o['value'] ?? $o['key'] ?? '', 'label' => $o['label'] ?? ($o['value'] ?? '')]
        : ['value' => $o, 'label' => $o])->values();
    // Standalone (no parent Alpine model) manages its own `sel` state.
    $selfManaged = $model === null;
    $expr = $model ?? 'sel';
@endphp
{{-- Custom rounded dropdown (Design Rule #4 — never a native <select>). With a
     `model` it reads/writes that parent-Alpine property; otherwise it is a
     self-contained form control seeded from `value` and posted via `name`. --}}
<div x-data="{ open: false, opts: {{ Illuminate\Support\Js::from($opts) }}@if($selfManaged), sel: {{ Illuminate\Support\Js::from((string) ($value ?? '')) }}@endif }"
     class="relative"
     @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-white border rounded-xl text-sm text-left transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent {{ $error ? 'border-red-400' : 'border-gray-200' }}"
            :class="open ? 'ring-2 ring-purple-500 border-transparent' : ''">
        <span class="truncate" :class="(opts.find(o => String(o.value) === String({{ $expr }})) ? 'text-foreground' : 'text-muted-foreground')"
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
        <template x-for="opt in opts" :key="String(opt.value)">
            <button type="button" @click="{{ $expr }} = opt.value; open = false;{{ $change ? ' '.$change.';' : '' }}"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm text-left transition-colors hover:bg-muted/60"
                    :class="String({{ $expr }}) === String(opt.value) ? 'text-primary font-medium bg-accent/40' : 'text-foreground'">
                <span class="truncate" x-text="opt.label"></span>
                <i class="bi bi-check-lg text-primary shrink-0" x-show="String({{ $expr }}) === String(opt.value)"></i>
            </button>
        </template>
    </div>

    @if($name)<input type="hidden" name="{{ $name }}" :value="{{ $expr }}">@endif
    @if($error)<p class="mt-1 text-xs text-red-500">{{ $error }}</p>@endif
</div>
