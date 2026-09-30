{{--
    The arranging canvas: the live preview, with one box over each element of the
    certificate. Drag a box to move it, pull its round handle to make it bigger or
    smaller, pull the side handle to make a text wider or narrower. The toolbar
    beneath changes the selected element's size, alignment, colour and visibility.

    Lives inside certDesign() (partials/script); shared by the mobile and desktop
    design pages, which pass `$compact` for the phone's tighter toolbar. Pointer
    events, so a finger and a mouse drag the same way; arrow keys move the
    selected box by 1 mm (Shift: 5 mm). Nothing here decides anything — the PDF is
    drawn by the server from the same numbers (Support\Layout) after each change.
--}}
@php
    $compact = $compact ?? false;
    $swatches = ['#1f1a2e', '#ffffff', '#b8892b', '#6b6478'];
@endphp

<div class="space-y-3">
    {{-- the page --}}
    <div data-cert-stage class="relative rounded-lg overflow-hidden border border-gray-100 bg-muted/40 select-none" style="aspect-ratio: 297 / 210;"
         @pointermove.window="onDrag($event)" @pointerup.window="endDrag()" @pointercancel.window="endDrag()">
        <img x-show="preview.url" :src="preview.url" alt="{{ __('cert::messages.preview') }}" class="absolute inset-0 w-full h-full object-contain pointer-events-none" draggable="false">

        <template x-for="k in keys" :key="k">
            <div x-show="! box(k).hidden" class="absolute" :style="boxStyle(k) + 'touch-action:none; cursor:move;'"
                 tabindex="0" role="button" :aria-label="element(k).label" :aria-pressed="sel === k"
                 @pointerdown="startDrag($event, k, 'move')" @focus="pick(k)"
                 @keydown.arrow-left.prevent="nudge(k, $event.shiftKey ? -5 : -1, 0)"
                 @keydown.arrow-right.prevent="nudge(k, $event.shiftKey ? 5 : 1, 0)"
                 @keydown.arrow-up.prevent="nudge(k, 0, $event.shiftKey ? -5 : -1)"
                 @keydown.arrow-down.prevent="nudge(k, 0, $event.shiftKey ? 5 : 1)">
                <span class="absolute inset-0 rounded-sm" :style="liftStyle(k)"></span>
                <span class="absolute inset-0 rounded-sm border transition-colors"
                      :class="sel === k ? 'border-primary bg-primary/10' : 'border-transparent hover:border-primary hover:bg-primary/5'"
                      :style="sel === k ? '' : 'border-style: dashed;'"></span>
                <span x-show="sel === k" class="absolute left-0 -top-5 px-1.5 py-0.5 rounded bg-primary text-white text-[10px] font-bold whitespace-nowrap" x-text="element(k).label"></span>
                {{-- size: the corner --}}
                <span x-show="sel === k" @pointerdown.stop="startDrag($event, k, 'resize')"
                      class="absolute rounded-full bg-primary border-2 border-white shadow"
                      style="width: 16px; height: 16px; right: -8px; bottom: -8px; cursor: nwse-resize; touch-action: none;"
                      :aria-label="@js(__('cert::messages.handle_size'))"></span>
                {{-- width only: the side, for text --}}
                <span x-show="sel === k && ['text', 'para', 'block'].includes(element(k).kind)" @pointerdown.stop="startDrag($event, k, 'width')"
                      class="absolute rounded-full bg-white border-2 border-primary shadow"
                      style="width: 12px; height: 22px; right: -6px; top: 50%; margin-top: -11px; cursor: ew-resize; touch-action: none;"
                      :aria-label="@js(__('cert::messages.handle_width'))"></span>
            </div>
        </template>

        <div x-show="guide" class="absolute top-0 bottom-0 bg-rose-500" style="left: 50%; width: 1px;"></div>
        <div x-show="preview.loading" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/80 grid place-items-center pointer-events-none"><i class="bi bi-arrow-repeat animate-spin text-primary"></i></div>
    </div>

    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.arrange_hint') }}</p>

    {{-- every element, including hidden ones, so a hidden one can come back --}}
    <div class="flex flex-wrap gap-1.5">
        <template x-for="k in keys" :key="'chip' + k">
            <button type="button" @click="pick(k)"
                    class="{{ $compact ? 'h-9' : 'h-8' }} px-3 rounded-full border text-xs font-semibold inline-flex items-center gap-1.5 transition-colors"
                    :class="sel === k ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-600 hover:border-primary'">
                <i class="bi" :class="box(k).hidden ? 'bi-eye-slash' : 'bi-dot'"></i><span x-text="element(k).label"></span>
            </button>
        </template>
    </div>

    {{-- the selected element --}}
    <div x-show="sel" x-transition class="rounded-xl border border-gray-100 bg-white p-3 space-y-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-bold text-foreground" x-text="sel ? element(sel).label : ''"></p>
            <button type="button" @click="sel = null" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted/60" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                <button type="button" @click="grow(sel, 1 / 1.1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center hover:bg-muted/60" aria-label="{{ __('cert::messages.smaller') }}"><i class="bi bi-dash-lg"></i></button>
                <span class="px-2 grid place-items-center text-xs font-bold text-gray-600 border-x border-gray-200" x-text="sel ? Math.round((box(sel).scale || 1) * 100) + '%' : ''"></span>
                <button type="button" @click="grow(sel, 1.1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center hover:bg-muted/60" aria-label="{{ __('cert::messages.bigger') }}"><i class="bi bi-plus-lg"></i></button>
            </div>

            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden" x-show="sel && ['text', 'para', 'block'].includes(element(sel).kind)">
                @foreach(['start' => 'bi-text-left', 'center' => 'bi-text-center', 'end' => 'bi-text-right'] as $a => $icon)
                    <button type="button" @click="align(sel, '{{ $a }}')" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center transition-colors"
                            :class="sel && box(sel).align === '{{ $a }}' ? 'bg-primary text-white' : 'hover:bg-muted/60'" aria-label="{{ __('cert::messages.align_'.$a) }}"><i class="bi {{ $icon }}"></i></button>
                @endforeach
            </div>

            <button type="button" @click="toggle(sel)" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-gray-200 text-xs font-bold inline-flex items-center gap-1.5 hover:bg-muted/60">
                <i class="bi" :class="sel && box(sel).hidden ? 'bi-eye' : 'bi-eye-slash'"></i>
                <span x-text="sel && box(sel).hidden ? @js(__('cert::messages.show')) : @js(__('cert::messages.hide'))"></span>
            </button>
            <button type="button" @click="resetOne(sel)" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-gray-200 text-xs font-bold inline-flex items-center gap-1.5 hover:bg-muted/60">
                <i class="bi bi-arrow-counterclockwise"></i>{{ __('cert::messages.reset_element') }}
            </button>
        </div>

        {{-- colour: automatic reads the background under the element and picks ink that stands out --}}
        <div x-show="sel && ! ['logo_start', 'logo_end', 'photo', 'medal', 'qr'].includes(sel)">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('cert::messages.colour') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="colour(sel, null)" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border-2 text-xs font-bold inline-flex items-center gap-1.5"
                        :class="sel && ! box(sel).color ? 'border-primary text-primary' : 'border-gray-200 text-gray-600'">
                    <i class="bi bi-magic"></i>{{ __('cert::messages.colour_auto') }}
                </button>
                @foreach($swatches as $sw)
                    <button type="button" @click="colour(sel, '{{ $sw }}')" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border-2 transition-transform"
                            :class="sel && box(sel).color === '{{ $sw }}' ? 'border-primary scale-110' : 'border-gray-200'"
                            style="background: {{ $sw }};" aria-label="{{ $sw }}"></button>
                @endforeach
                <button type="button" @click="colour(sel, state.accent)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border-2"
                        :class="sel && box(sel).color === state.accent ? 'border-primary' : 'border-gray-200'"
                        :style="'background:' + state.accent" aria-label="{{ __('cert::messages.colour_event') }}"></button>
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                    <span class="sr-only">{{ __('cert::messages.colour_hex') }}</span>
                    <input type="text" maxlength="7" placeholder="#rrggbb" :value="sel ? (box(sel).color || '') : ''"
                           @change="/^#[0-9a-fA-F]{6}$/.test($event.target.value) ? colour(sel, $event.target.value.toLowerCase()) : window.showToast('warning', @js(__('cert::messages.colour_invalid')))"
                           class="w-24 px-2.5 {{ $compact ? 'py-2.5' : 'py-1.5' }} border border-gray-200 rounded-lg text-xs font-mono focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </label>
            </div>
            <p x-show="sel === 'panel'" class="text-[12px] text-muted-foreground mt-2">{{ __('cert::messages.panel_hint') }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-show="box('panel').hidden" @click="panelOn()" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-primary text-primary text-xs font-bold inline-flex items-center gap-1.5 hover:bg-primary hover:text-white transition-colors">
            <i class="bi bi-square-half"></i>{{ __('cert::messages.add_panel') }}
        </button>
        <button type="button" @click="resetAll()" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-gray-200 text-xs font-bold text-gray-600 inline-flex items-center gap-1.5 hover:bg-muted/60">
            <i class="bi bi-arrow-counterclockwise"></i>{{ __('cert::messages.reset_layout') }}
        </button>
    </div>
</div>
