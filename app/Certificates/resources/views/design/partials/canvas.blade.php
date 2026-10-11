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

<div class="space-y-3" @keydown.window="onKey($event)" @resize.window="stageTick++">
    {{-- undo / redo, as at the top of Canva --}}
    <div class="flex items-center gap-1">
        <button type="button" @click="undo()" :disabled="! canUndo" title="{{ __('cert::messages.undo') }} (Ctrl+Z)" aria-label="{{ __('cert::messages.undo') }}"
                class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border border-gray-200 grid place-items-center text-gray-700 hover:bg-muted/60 disabled:opacity-40 transition-colors"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button type="button" @click="redo()" :disabled="! canRedo" title="{{ __('cert::messages.redo') }} (Ctrl+Shift+Z)" aria-label="{{ __('cert::messages.redo') }}"
                class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} rounded-lg border border-gray-200 grid place-items-center text-gray-700 hover:bg-muted/60 disabled:opacity-40 transition-colors"><i class="bi bi-arrow-clockwise"></i></button>
        <span class="text-[12px] text-muted-foreground ms-2" x-show="! sel">{{ __('cert::messages.select_hint') }}</span>
    </div>

    {{-- the page --}}
    {{-- The page's own shape: portrait stands upright, held to the screen's height so it never runs off it. --}}
    <div data-cert-stage x-ref="stage" @pointerdown.self="sel = null; addMenu = false" class="relative rounded-lg overflow-hidden border border-gray-100 bg-muted/40 select-none" style="aspect-ratio: 297 / 210;"
         :style="portrait ? 'aspect-ratio: ' + pageW + ' / ' + pageH + '; max-width: calc(78vh * ' + pageW + ' / ' + pageH + '); margin-inline: auto;' : 'aspect-ratio: ' + pageW + ' / ' + pageH + ';'"
         @pointermove.window="onDrag($event)" @pointerup.window="endDrag()" @pointercancel.window="endDrag()">
        <img x-show="preview.url" :src="preview.url" alt="{{ __('cert::messages.preview') }}" class="absolute inset-0 w-full h-full object-contain pointer-events-none" draggable="false">

        <template x-for="k in onPageKeys" :key="k">
            <div class="absolute" :style="boxStyle(k) + 'touch-action:none; cursor:move;'"
                 tabindex="0" role="button" :aria-label="element(k).label" :aria-pressed="sel === k"
                 @pointerdown="startDrag($event, k, 'move')" @focus="pick(k)"
                 @keydown.arrow-left.prevent="nudge(k, $event.shiftKey ? -5 : -1, 0)"
                 @keydown.arrow-right.prevent="nudge(k, $event.shiftKey ? 5 : 1, 0)"
                 @keydown.arrow-up.prevent="nudge(k, 0, $event.shiftKey ? -5 : -1)"
                 @keydown.arrow-down.prevent="nudge(k, 0, $event.shiftKey ? 5 : 1)">
                <span class="absolute inset-0 rounded-sm" :style="liftStyle(k)"></span>
                <span class="absolute inset-0 rounded-sm border transition-colors"
                      :class="sel === k ? 'border-primary bg-primary/10' : (isErase(k) ? 'border-red-300 hover:border-primary hover:bg-primary/5' : 'border-transparent hover:border-primary hover:bg-primary/5')"
                      :style="sel === k ? '' : 'border-style: dashed;'"></span>
                {{-- corners: scale, the opposite corner held still --}}
                @foreach(['nw' => 'left: -7px; top: -7px; cursor: nwse-resize;', 'ne' => 'right: -7px; top: -7px; cursor: nesw-resize;', 'sw' => 'left: -7px; bottom: -7px; cursor: nesw-resize;', 'se' => 'right: -7px; bottom: -7px; cursor: nwse-resize;'] as $c => $pos)
                    <span x-show="sel === k && ! drag" @pointerdown.stop="startDrag($event, k, 'resize', '{{ $c }}')"
                          class="absolute rounded-full bg-white border border-gray-300 shadow"
                          style="width: 14px; height: 14px; {{ $pos }} touch-action: none;"
                          :aria-label="@js(__('cert::messages.handle_size'))"></span>
                @endforeach
                {{-- sides: width for words, and height too for an erased area or the panel --}}
                @foreach(['l' => 'left: -4px; top: 50%; margin-top: -10px; width: 8px; height: 20px; cursor: ew-resize;', 'r' => 'right: -4px; top: 50%; margin-top: -10px; width: 8px; height: 20px; cursor: ew-resize;'] as $sd => $pos)
                    <span x-show="sel === k && ! drag && (['text', 'para', 'block', 'erase'].includes(element(k).kind) || k === 'panel')" @pointerdown.stop="startDrag($event, k, 'width', '{{ $sd }}')"
                          class="absolute rounded-full bg-white border border-gray-300 shadow"
                          style="{{ $pos }} touch-action: none;" :aria-label="@js(__('cert::messages.handle_width'))"></span>
                @endforeach
                @foreach(['t' => 'top: -4px; left: 50%; margin-left: -10px; width: 20px; height: 8px; cursor: ns-resize;', 'b' => 'bottom: -4px; left: 50%; margin-left: -10px; width: 20px; height: 8px; cursor: ns-resize;'] as $sd => $pos)
                    <span x-show="sel === k && ! drag && (isErase(k) || valignable(k) || k === 'panel')" @pointerdown.stop="startDrag($event, k, 'width', '{{ $sd }}')"
                          class="absolute rounded-full bg-white border border-gray-300 shadow"
                          style="{{ $pos }} touch-action: none;" :aria-label="@js(__('cert::messages.handle_height'))"></span>
                @endforeach
            </div>
        </template>

        {{-- the floating toolbar on the selected element: its name, duplicate, delete, more --}}
        <div x-show="sel && ! drag && ! addMenu && (boxKeys.includes(sel) ? ! box(sel).hidden : (isPage(sel) && sel !== 'photo'))" x-transition.opacity.duration.150ms
             @pointerdown.stop class="absolute z-20 flex items-center gap-1 rounded-xl bg-white shadow-lg border border-gray-100 p-1" :style="toolbarStyle()">
            <span class="px-2 text-[11px] font-bold text-gray-700 truncate" style="max-width: 130px;" x-text="sel ? element(sel).label : ''"></span>
            <span class="w-px h-5 bg-gray-200"></span>
            <button type="button" x-show="sel && canDuplicate(sel)" @click="duplicate(sel)" :disabled="locked"
                    title="{{ __('cert::messages.duplicate') }} (Ctrl+D)" aria-label="{{ __('cert::messages.duplicate') }}"
                    class="w-8 h-8 rounded-lg grid place-items-center text-gray-700 hover:bg-muted/60 disabled:opacity-40 transition-colors"><i class="bi bi-copy"></i></button>
            <button type="button" @click="removeEl(sel)" :disabled="locked"
                    title="{{ __('cert::messages.delete') }} (Delete)" aria-label="{{ __('cert::messages.delete') }}"
                    class="w-8 h-8 rounded-lg grid place-items-center text-red-600 hover:bg-red-50 disabled:opacity-40 transition-colors"><i class="bi bi-trash3"></i></button>
            <button type="button" @click="moreOptions()" title="{{ __('cert::messages.element_settings') }}" aria-label="{{ __('cert::messages.element_settings') }}"
                    class="w-8 h-8 rounded-lg grid place-items-center text-gray-700 hover:bg-muted/60 transition-colors"><i class="bi bi-three-dots"></i></button>
        </div>

        <div x-show="guide" class="absolute top-0 bottom-0 bg-rose-500" style="left: 50%; width: 1px;"></div>
        <div x-show="guideY" class="absolute left-0 right-0 bg-rose-500" style="top: 50%; height: 1px;"></div>
        <div x-show="preview.loading" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/80 grid place-items-center pointer-events-none"><i class="bi bi-arrow-repeat animate-spin text-primary"></i></div>
    </div>

    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.arrange_hint') }}</p>

    {{-- what is on the page; Add element brings back anything removed, or a new text --}}
    <div class="flex flex-wrap gap-1.5">
        <template x-for="k in shown" :key="'chip' + k">
            <button type="button" @click="pick(k); addMenu = false"
                    class="{{ $compact ? 'h-9' : 'h-8' }} px-3 rounded-full border text-xs font-semibold inline-flex items-center gap-1.5 transition-colors"
                    :class="sel === k ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-600 hover:border-primary'">
                <i class="bi" :class="isCustom(k) ? 'bi-fonts' : (isErase(k) ? 'bi-eraser' : (isPage(k) ? 'bi-layers' : 'bi-dot'))"></i><span x-text="element(k).label"></span>
            </button>
        </template>
        <button type="button" @click="addMenu = ! addMenu; sel = null" :aria-expanded="addMenu"
                class="{{ $compact ? 'h-9' : 'h-8' }} px-3 rounded-full border-2 border-dashed text-xs font-bold inline-flex items-center gap-1.5 transition-colors"
                :class="addMenu ? 'border-primary bg-primary/5 text-primary' : 'border-purple-200 text-primary hover:border-primary'">
            <i class="bi bi-plus-lg"></i>{{ __('cert::messages.add_element') }}
        </button>
    </div>

    {{-- the Add element menu: in the flow, never a pop-over a scroll could clip --}}
    <div x-show="addMenu" x-transition class="rounded-xl border border-gray-100 bg-white p-3 space-y-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-bold text-foreground">{{ __('cert::messages.add_element') }}</p>
            <button type="button" @click="addMenu = false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted/60" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
        </div>
        <label class="w-full text-start rounded-xl border-2 border-primary/30 bg-primary/5 hover:border-primary p-3 flex items-center gap-3 transition-colors cursor-pointer {{ $compact ? 'm-press min-h-[60px]' : '' }}"
               :class="locked || uploading ? 'opacity-50 pointer-events-none' : ''">
            <span class="w-10 h-10 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0"><i class="bi" :class="uploading === 'artwork' ? 'bi-arrow-repeat animate-spin' : 'bi-image'"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-foreground" x-text="t && t.has_artwork ? @js(__('cert::messages.replace_background_picture')) : @js(__('cert::messages.upload_background_picture'))"></span>
                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.upload_background_hint') }}</span>
            </span>
            <i class="bi bi-upload text-primary"></i>
            <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="pickArtwork($event.target)">
        </label>
        <button type="button" @click="addText(); addMenu = false" :disabled="textCount >= state.max_custom"
                class="w-full text-start rounded-xl border-2 border-gray-100 hover:border-primary p-3 flex items-center gap-3 transition-colors disabled:opacity-50 {{ $compact ? 'm-press min-h-[60px]' : '' }}">
            <span class="w-10 h-10 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-fonts"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.add_text') }}</span>
                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.add_text_hint') }}</span>
            </span>
            <i class="bi bi-plus-circle text-primary"></i>
        </button>
        <button type="button" @click="addErase(); addMenu = false" :disabled="eraseCount >= state.max_erase"
                class="w-full text-start rounded-xl border-2 border-gray-100 hover:border-primary p-3 flex items-center gap-3 transition-colors disabled:opacity-50 {{ $compact ? 'm-press min-h-[60px]' : '' }}">
            <span class="w-10 h-10 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-eraser"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.add_erase') }}</span>
                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.add_erase_hint') }}</span>
            </span>
            <i class="bi bi-plus-circle text-primary"></i>
        </button>
        <template x-if="removed.length">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('cert::messages.add_back') }}</p>
                <div class="grid {{ $compact ? 'grid-cols-1' : 'grid-cols-2' }} gap-1.5">
                    <template x-for="k in removed" :key="'add' + k">
                        <button type="button" @click="add(k)"
                                class="text-start rounded-lg border border-gray-200 hover:border-primary px-3 {{ $compact ? 'h-11' : 'h-9' }} text-xs font-semibold text-gray-700 inline-flex items-center gap-2 transition-colors">
                            <i class="bi bi-plus-lg text-primary"></i><span class="flex-1 truncate" x-text="element(k).label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- the selected element --}}
    <div x-show="sel" x-ref="selPanel" x-transition class="rounded-xl border border-gray-100 bg-white p-3 space-y-3">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-bold text-foreground" x-text="sel ? element(sel).label : ''"></p>
            <button type="button" @click="sel = null" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted/60" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
        </div>

        @include('cert::design.partials.element-options', ['compact' => $compact])

        <div class="flex flex-wrap items-center gap-2" x-show="sel && element(sel).kind !== 'page'">
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                <button type="button" @click="grow(sel, 1 / 1.1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center hover:bg-muted/60" aria-label="{{ __('cert::messages.smaller') }}"><i class="bi bi-dash-lg"></i></button>
                <span x-show="sel && ! isErase(sel)" class="px-2 grid place-items-center text-xs font-bold text-gray-600 border-x border-gray-200" x-text="sel ? Math.round((box(sel).scale || 1) * 100) + '%' : ''"></span>
                <button type="button" @click="grow(sel, 1.1)" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center hover:bg-muted/60" aria-label="{{ __('cert::messages.bigger') }}"><i class="bi bi-plus-lg"></i></button>
            </div>

            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden" x-show="sel && ['text', 'para', 'block'].includes(element(sel).kind)">
                @foreach(['start' => 'bi-text-left', 'center' => 'bi-text-center', 'end' => 'bi-text-right'] as $a => $icon)
                    <button type="button" @click="align(sel, '{{ $a }}')" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center transition-colors"
                            :class="sel && box(sel).align === '{{ $a }}' ? 'bg-primary text-white' : 'hover:bg-muted/60'" aria-label="{{ __('cert::messages.align_'.$a) }}"><i class="bi {{ $icon }}"></i></button>
                @endforeach
            </div>

            {{-- where the words sit inside their own box, top to bottom --}}
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden" x-show="valignable(sel)">
                @foreach(['top' => 'bi-align-top', 'middle' => 'bi-align-middle', 'bottom' => 'bi-align-bottom'] as $a => $icon)
                    <button type="button" @click="valign(sel, '{{ $a }}')" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center transition-colors"
                            :class="sel && (box(sel).valign || 'top') === '{{ $a }}' ? 'bg-primary text-white' : 'hover:bg-muted/60'"
                            title="{{ __('cert::messages.valign_'.$a) }}" aria-label="{{ __('cert::messages.valign_'.$a) }}"><i class="bi {{ $icon }}"></i></button>
                @endforeach
            </div>

 <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                @foreach(['x' => 'bi-align-center', 'y' => 'bi-align-middle', 'both' => 'bi-bullseye'] as $ax => $icon)
                    <button type="button" @click="centre(sel, '{{ $ax }}')" class="{{ $compact ? 'w-11 h-11' : 'w-9 h-9' }} grid place-items-center hover:bg-muted/60"
                            title="{{ __('cert::messages.centre_'.$ax) }}" aria-label="{{ __('cert::messages.centre_'.$ax) }}"><i class="bi {{ $icon }}"></i></button>
                @endforeach
            </div>

            <button type="button" x-show="sel && ! isErase(sel)" @click="resetOne(sel)" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-gray-200 text-xs font-bold inline-flex items-center gap-1.5 hover:bg-muted/60">
                <i class="bi bi-arrow-counterclockwise"></i>{{ __('cert::messages.reset_element') }}
            </button>
        </div>

        {{-- colour: automatic reads the background under the element and picks ink that stands out --}}
        <div x-show="sel && ! ['page', 'erase'].includes(element(sel).kind) && ! ['logo_start', 'logo_end', 'photo', 'medal', 'qr'].includes(sel)">
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

        <div class="pt-3 border-t border-gray-100 flex justify-end">
            <button type="button" @click="removeEl(sel)" :disabled="locked"
                    class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-xs font-bold inline-flex items-center gap-1.5 disabled:opacity-50">
                <i class="bi bi-trash3"></i>{{ __('cert::messages.remove_element') }}
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="resetAll()" class="{{ $compact ? 'h-11' : 'h-9' }} px-3 rounded-lg border border-gray-200 text-xs font-bold text-gray-600 inline-flex items-center gap-1.5 hover:bg-muted/60">
            <i class="bi bi-arrow-counterclockwise"></i>{{ __('cert::messages.reset_layout') }}
        </button>
    </div>
</div>
