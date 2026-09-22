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
                        <h3 class="text-lg font-black leading-tight">{{ __('events.run_order_add_all') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.run_order_bench') }}</p>
                    </div>
                    <button type="button" @click="addOpen = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                <template x-for="c in bench" :key="c.id">
                    <button type="button" @click="toggleChosen(c.id)"
                            class="w-full flex items-center gap-3 p-3 rounded-xl border text-start"
                            :style="isChosen(c.id) ? `border-color: {{ $roColor }}; background: {{ $roColor }}0d;` : 'border-color:#e5e7eb;'">
                        <span class="flex-none w-5 h-5 rounded-full border-2 grid place-items-center"
                              :style="isChosen(c.id) ? `border-color: {{ $roColor }}; background: {{ $roColor }};` : 'border-color:#d1d5db;'">
                            <i class="bi bi-check text-white text-[11px]" x-show="isChosen(c.id)"></i>
                        </span>
                        <span class="mq min-w-0 flex-1 text-[13.5px] font-bold text-foreground"><span dir="auto" x-text="c.name"></span></span>
                    </button>
                </template>
            </div>

            <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="addDivisions()" :disabled="busy"
                        class="w-full h-12 rounded-xl text-white font-bold text-[15px] disabled:opacity-60"
                        style="background: {{ $roColor }};">
                    <span x-text="chosen.length"></span> &middot; {{ __('events.run_order_add_all') }}
                </button>
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
                            x-text="blockKind === 'break' ? @js(__('events.run_order_add_break')) : (blockKind === 'ceremony' ? @js(__('events.run_order_add_ceremony')) : @js(__('events.run_order_add_custom')))"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.run_order_no_bouts') }}</p>
                    </div>
                    <button type="button" @click="blockOpen = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div>
                    <label for="ro-block-label" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.run_order_label_placeholder') }}</label>
                    <input id="ro-block-label" type="text" x-model="blockLabel" maxlength="160"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="{{ __('events.run_order_label_placeholder') }}">
                </div>

                <div>
                    <label for="ro-block-duration" class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.run_order_duration') }}</label>
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
