{{--
    Which way up the certificate prints — landscape or portrait. Two selection cards,
    each drawing the sheet's own shape, so the choice reads before the label does.
    Lives inside certDesign() (partials/script); `$compact` is the phone's version.
    Each way up keeps its own arrangement (Support\Layout), so turning the page and
    turning it back loses nothing.
--}}
@php $compact = $compact ?? false; @endphp
{{-- The page's size (1.4): a standard size, or any width and height in mm, cm or inches.
     Changing it carries the arrangement along in proportion (certDesign.setPageSize). --}}
<div class="mb-4">
    <span class="block text-sm font-bold text-foreground mb-1.5">{{ __('cert::messages.page_size') }}</span>
    <x-fields.select model="sizePick" options-expr="sizeOptions" placeholder="{{ __('cert::messages.size_custom') }}" />
    <div class="mt-2.5 flex items-end gap-2 flex-wrap" x-show="sizePick === 'custom'" x-cloak>
        <label class="flex-1 min-w-[90px]">
            <span class="block text-[12px] font-semibold text-muted-foreground mb-1">{{ __('cert::messages.size_width') }}</span>
            <input type="text" inputmode="decimal" :value="sizeW" x-ref="sizeW" @change="typeSize($refs.sizeW.value, $refs.sizeH.value)" @keydown.enter.prevent="typeSize($refs.sizeW.value, $refs.sizeH.value)"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent" aria-label="{{ __('cert::messages.size_width') }}">
        </label>
        <span class="pb-2.5 text-muted-foreground">×</span>
        <label class="flex-1 min-w-[90px]">
            <span class="block text-[12px] font-semibold text-muted-foreground mb-1">{{ __('cert::messages.size_height') }}</span>
            <input type="text" inputmode="decimal" :value="sizeH" x-ref="sizeH" @change="typeSize($refs.sizeW.value, $refs.sizeH.value)" @keydown.enter.prevent="typeSize($refs.sizeW.value, $refs.sizeH.value)"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent" aria-label="{{ __('cert::messages.size_height') }}">
        </label>
    </div>
    <div class="mt-2.5 inline-flex p-0.5 rounded-full bg-gray-100" role="group" aria-label="{{ __('cert::messages.size_unit') }}">
        @foreach(['mm' => 'mm', 'cm' => 'cm', 'in' => 'in'] as $u => $l)
            <button type="button" class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
                    :class="sizeUnit === '{{ $u }}' ? 'bg-white shadow-sm text-foreground' : 'text-gray-500'" :aria-pressed="sizeUnit === '{{ $u }}'"
                    @click="sizeUnit = '{{ $u }}'">{{ $l }}</button>
        @endforeach
    </div>
    <p class="mt-1.5 text-[12px] text-muted-foreground" x-text="@js(__('cert::messages.size_now')).replace(':w', sizeW).replace(':h', sizeH).replace(':unit', sizeUnit)"></p>
</div>

<div class="grid {{ $compact ? 'grid-cols-1' : 'grid-cols-2' }} gap-2" role="radiogroup" aria-label="{{ __('cert::messages.page') }}">
    @foreach(['landscape' => [30, 21], 'portrait' => [21, 30]] as $o => [$w, $h])
        <button type="button" @click="setOrientation('{{ $o }}')" role="radio"
                :aria-checked="(form.orientation || 'landscape') === '{{ $o }}'"
                class="{{ $compact ? 'm-press rounded-2xl min-h-[60px]' : 'rounded-xl hover:border-gray-200' }} text-start border-2 p-3 flex items-center gap-3 transition-colors"
                :class="(form.orientation || 'landscape') === '{{ $o }}' ? 'border-primary bg-primary/5' : 'border-gray-100'">
            <span class="w-10 h-10 rounded-xl bg-muted/60 grid place-items-center flex-shrink-0">
                <span class="block rounded-sm border-2 transition-colors"
                      :class="(form.orientation || 'landscape') === '{{ $o }}' ? 'border-primary bg-white' : 'border-gray-300 bg-white'"
                      style="width: {{ $w }}px; height: {{ $h }}px;"></span>
            </span>
            <span class="flex-1 text-sm font-bold text-foreground">{{ __('cert::messages.page_'.$o) }}</span>
            <i class="bi" :class="(form.orientation || 'landscape') === '{{ $o }}' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300'"></i>
        </button>
    @endforeach
</div>

{{-- Printing on paper that already carries its own design: the print copy is the words only. --}}
<label class="mt-3 flex items-start gap-3 cursor-pointer {{ $compact ? 'rounded-2xl border border-gray-100 p-3' : '' }}">
    <input type="checkbox" x-model="form.print_bare" @change="if (! form.print_bare) preview.print = false; touch()"
           class="mt-0.5 w-[18px] h-[18px] rounded border-gray-300 text-primary focus:ring-primary">
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-gray-700">{{ __('cert::messages.print_bare') }}</span>
        <span class="block text-[12px] text-muted-foreground leading-relaxed">{{ __('cert::messages.blank_note') }}</span>
    </span>
</label>
<div x-show="form.print_bare" x-transition class="mt-3 rounded-xl border border-gray-100 p-3 space-y-2">
    <p class="text-sm font-bold text-foreground">{{ __('cert::messages.line_up') }}</p>
    @foreach(['y' => 'cert::messages.move_down', 'x' => 'cert::messages.move_right'] as $axis => $label)
        <div class="flex items-center gap-3">
            <span class="flex-1 text-sm text-gray-700">{{ __($label) }}</span>
            <button type="button" @click="nudgePrint('{{ $axis }}', -1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border border-gray-200 font-bold hover:bg-muted/60" aria-label="−">−</button>
            <span class="w-16 text-center text-sm font-bold" x-text="(form.print_offset_{{ $axis }} || 0) + ' mm'"></span>
            <button type="button" @click="nudgePrint('{{ $axis }}', 1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border border-gray-200 font-bold hover:bg-muted/60" aria-label="+">+</button>
        </div>
    @endforeach
</div>
