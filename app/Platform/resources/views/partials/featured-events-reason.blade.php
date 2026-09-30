{{-- The reason sheet for rejecting / removing a featured-event request.
     Lives inside the page's featured-events-state scope (sheet, decide()). --}}
<template x-teleport="body" data-teleport-template="true">
    <div x-show="sheet.open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
         @keydown.escape.window="sheet.open = false" style="display:none;">
        <div x-show="sheet.open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="sheet.open = false"></div>

        <div x-show="sheet.open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full sm:opacity-0"
             class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">

            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #e11d48, #e11d48b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi text-xl" :class="sheet.mode === 'revoke' ? 'bi-star-half' : 'bi-x-octagon'"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight"
                            x-text="sheet.mode === 'revoke' ? @js(__('platform.featured_revoke_title')) : @js(__('platform.featured_reject_title'))"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate" x-text="sheet.req ? sheet.req.title : ''"></p>
                    </div>
                    <button type="button" @click="sheet.open = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.featured_reason') }}</label>
                <textarea x-model="sheet.reason" rows="4" maxlength="500"
                          placeholder="{{ __('platform.featured_reason_placeholder') }}"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"></textarea>
            </div>

            <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="decide(sheet.req, sheet.mode, sheet.reason)"
                        :disabled="! sheet.reason.trim() || busy"
                        class="m-press w-full py-3 rounded-xl bg-destructive text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                    <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('platform.featured_confirm') }}
                </button>
            </div>
        </div>
    </div>
</template>
