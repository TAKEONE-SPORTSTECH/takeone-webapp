{{--
    The running-order board's two sheets — putting divisions on the day, and
    adding a part that has no bouts (a ceremony, a break, anything else).

    Shared by the mobile and desktop boards so the two can never drift; both
    already run the one `runOrderBoard` Alpine component, and these read its
    state directly.

    Teleported to <body>: the mobile shell leaves a transform on every direct
    child of #shell-content, which would make a `position: fixed` sheet resolve
    its `bottom-0` against that wrapper instead of the viewport and clip the
    form. z-[60] so they sit above the bottom tab bar.

    Each opens with the gradient header band every sheet on the platform opens
    with (Design Rule #8) — ⚠️ `{{ $roColor }}b0` is a HEX alpha suffix and only
    works because the colour is hex; an hsl() there silently voids the gradient.
--}}
@props([])

{{-- ===== Divisions onto the day ===== --}}
<template x-teleport="body">
    <div x-show="addOpen" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="addOpen = false">
        <div x-show="addOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="addOpen = false"></div>

        <div x-show="addOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden">

            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-plus-lg text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight">{{ __('events::events.run_order_add_all') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.run_order_bench') }}</p>
                    </div>
                    <button type="button" @click="addOpen = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                {{-- Medals: each division its own ceremony (its Awarding part), or
                     ONE ceremony for everyone at the end of the day. --}}
                <button type="button" @click="together = ! together; chosenParts = {}"
                        class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-start bg-white border"
                        :style="together ? `border-color: {{ $roColor }};` : 'border-color:#e5e7eb;'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" style="background:#fef3c7;color:#b45309;"><i class="bi bi-award-fill"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-bold text-foreground">{{ __('events::events.run_order_together') }}</span>
                        <span class="block text-[11.5px] text-muted-foreground">{{ __('events::events.run_order_together_hint') }}</span>
                    </span>
                    {{-- A switch, drawn: the track and the knob. --}}
                    <span class="relative flex-none w-11 h-6 rounded-full transition-colors" :style="together ? `background: {{ $roColor }};` : 'background:#d1d5db;'">
                        <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all" :style="together ? 'left:22px' : 'left:2px'"></span>
                    </span>
                </button>

                {{-- Select all — nothing is ticked until the organiser says so. --}}
                <button type="button" @click="toggleAll()"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-start bg-white border border-gray-200">
                    <span class="flex-none w-5 h-5 rounded-md border-2 grid place-items-center"
                          :style="allChosen ? `border-color: {{ $roColor }}; background: {{ $roColor }};` : 'border-color:#d1d5db;'">
                        <i class="bi bi-check text-white text-[11px]" x-show="allChosen"></i>
                    </span>
                    <span class="flex-1 text-[13px] font-bold text-foreground"
                          x-text="allChosen ? @js(__('events::events.run_order_clear_all')) : @js(__('events::events.run_order_select_all'))"></span>
                    <span class="text-[12px] font-bold text-muted-foreground"><span x-text="chosen.length"></span> / <span x-text="bench.length"></span></span>
                </button>

                <template x-for="c in bench" :key="c.id">
                    <div class="rounded-xl border bg-white"
                         :style="isChosen(c.id) ? `border-color: {{ $roColor }}; background: {{ $roColor }}0d;` : 'border-color:#e5e7eb;'">
                        <button type="button" @click="toggleChosen(c.id)" class="w-full flex items-center gap-3 p-3 text-start">
                            <span class="flex-none w-5 h-5 rounded-md border-2 grid place-items-center"
                                  :style="isChosen(c.id) ? `border-color: {{ $roColor }}; background: {{ $roColor }};` : 'border-color:#d1d5db;'">
                                <i class="bi bi-check text-white text-[11px]" x-show="isChosen(c.id)"></i>
                            </span>
                            <span class="mq min-w-0 flex-1 text-[13.5px] font-bold text-foreground"><span dir="auto" x-text="c.name"></span></span>
                            {{-- Part of it already on the day: say what is left. --}}
                            <span x-show="partsOf(c).length && c.remaining.length < c.parts.length" x-cloak
                                  class="flex-none text-[10.5px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('events::events.run_order_parts_left') }}</span>
                        </button>
                        {{-- Which parts go on the day in this slot: All, or some of
                             what is left. Shown once the division is ticked. --}}
                        {{-- Nothing to split: say why, rather than show nothing. --}}
                        <p x-show="isChosen(c.id) && partsOf(c).length <= 1" x-cloak class="px-3 pb-3 ps-11 text-[12px] font-semibold text-muted-foreground flex items-center gap-1.5">
                            <i class="bi bi-info-circle"></i>
                            <span x-text="! (c.parts || []).length ? @js(__('events::events.run_order_part_nodraw')) : @js(__('events::events.run_order_part_single')).replace(':part', partLabel((c.remaining || c.parts)[0]))"></span>
                        </p>
                        <div x-show="isChosen(c.id) && partsOf(c).length > 1" x-cloak class="flex flex-wrap gap-1.5 px-3 pb-3 ps-11">
                            <button type="button" @click="setAllParts(c)"
                                    class="h-9 px-3 rounded-lg text-[12.5px] font-bold border"
                                    :style="isAllParts(c) ? `background: {{ $roColor }}; color:#fff; border-color: {{ $roColor }};` : 'background:#fff; color:#374151; border-color:#e5e7eb;'">
                                {{ __('events::events.run_order_parts_all') }}
                            </button>
                            <template x-for="p in partsOf(c)" :key="p">
                                <button type="button" @click="togglePart(c, p)"
                                        class="h-9 px-3 rounded-lg text-[12.5px] font-bold border inline-flex items-center gap-1.5"
                                        :style="hasPart(c, p) && ! isAllParts(c) ? `background: {{ $roColor }}1a; color: {{ $roColor }}; border-color: {{ $roColor }};` : 'background:#fff; color:#374151; border-color:#e5e7eb;'">
                                    <i class="bi" :class="hasPart(c, p) && ! isAllParts(c) ? 'bi-check-square-fill' : 'bi-square'"></i>
                                    <span x-text="partLabel(p)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="addDivisions()" :disabled="busy"
                        class="w-full h-12 rounded-xl text-white font-bold text-[15px] disabled:opacity-60"
                        style="background: {{ $roColor }};">
                    <span x-text="chosen.length"></span> &middot; {{ __('events::events.run_order_add_chosen') }}
                </button>
            </div>
        </div>
    </div>
</template>

{{-- ===== The event's break: its times ===== --}}
<template x-teleport="body">
    <div x-show="breakOpen" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="breakOpen = false">
        <div x-show="breakOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="breakOpen = false"></div>
        <div x-show="breakOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden sm:max-w-lg sm:mx-auto">
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-cup-hot-fill text-xl"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight">{{ __('events::events.run_order_event_break') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.run_order_break_sub') }}</p>
                    </div>
                    <button type="button" @click="breakOpen = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                @foreach (['start' => __('events::events.run_order_break_from'), 'end' => __('events::events.run_order_break_to')] as $which => $whichLabel)
                    <div>
                        <p class="block text-sm font-medium text-gray-700 mb-1">{{ $whichLabel }}</p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="stepTime('{{ $which }}', -5)" aria-label="-5"
                                    class="w-12 h-12 rounded-xl border border-gray-200 bg-white grid place-items-center text-gray-600 flex-shrink-0 active:scale-95"><i class="bi bi-dash-lg"></i></button>
                            <div class="flex-1 h-12 rounded-xl border border-gray-200 bg-white grid place-items-center text-lg font-black tabular-nums"
                                 x-text="clock12({{ $which === 'start' ? 'breakStart' : 'breakEnd' }})"></div>
                            <button type="button" @click="stepTime('{{ $which }}', 5)" aria-label="+5"
                                    class="w-12 h-12 rounded-xl border border-gray-200 bg-white grid place-items-center text-gray-600 flex-shrink-0 active:scale-95"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>
                @endforeach
                <p class="text-[12.5px] font-semibold" :class="breakMinutes > 0 ? 'text-muted-foreground' : 'text-red-600'"
                   x-text="breakMinutes > 0 ? breakMinutes + ' min' : @js(__('events::events.run_order_break_bad'))"></p>
            </div>
            <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="saveBreak()" :disabled="busy || breakMinutes <= 0"
                        class="w-full h-12 rounded-xl text-white font-bold text-[15px] disabled:opacity-60"
                        style="background: {{ $roColor }};">{{ __('events::events.run_order_break_save') }}</button>
            </div>
        </div>
    </div>
</template>

{{-- ===== A part with no bouts ===== --}}
<template x-teleport="body">
    <div x-show="blockOpen" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="blockOpen = false">
        <div x-show="blockOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="blockOpen = false"></div>

        <div x-show="blockOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden">

            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi text-xl"
                           :class="blockKind === 'break' ? 'bi-cup-hot-fill' : (blockKind === 'ceremony' ? 'bi-award-fill' : 'bi-bookmark-fill')"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight"
                            x-text="blockKind === 'break' ? @js(__('events::events.run_order_add_break')) : (blockKind === 'ceremony' ? @js(__('events::events.run_order_add_ceremony')) : @js(__('events::events.run_order_add_custom')))"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.run_order_no_bouts') }}</p>
                    </div>
                    <button type="button" @click="blockOpen = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div>
                    <label for="ro-block-label" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.run_order_label_placeholder') }}</label>
                    <input id="ro-block-label" type="text" x-model="blockLabel" maxlength="160"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="{{ __('events::events.run_order_label_placeholder') }}">
                </div>

                <div>
                    <label for="ro-block-duration" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events::events.run_order_duration') }}</label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="blockDuration = Math.max(1, (parseInt(blockDuration,10)||0) - 5)"
                                aria-label="-5" class="w-11 h-11 rounded-xl border border-gray-200 grid place-items-center text-gray-600 flex-shrink-0">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <input id="ro-block-duration" type="number" min="1" max="600" x-model="blockDuration"
                               class="flex-1 min-w-0 px-3 py-2.5 border border-gray-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <button type="button" @click="blockDuration = Math.min(600, (parseInt(blockDuration,10)||0) + 5)"
                                aria-label="+5" class="w-11 h-11 rounded-xl border border-gray-200 grid place-items-center text-gray-600 flex-shrink-0">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="addBlock()" :disabled="busy"
                        class="w-full h-12 rounded-xl text-white font-bold text-[15px] disabled:opacity-60"
                        style="background: {{ $roColor }};">{{ __('shared.save') }}</button>
            </div>
        </div>
    </div>
</template>
