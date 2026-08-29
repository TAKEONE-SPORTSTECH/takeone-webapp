{{--
    First-step chooser for "Add Family Member" (mobile). Owns the existing
    `open-member-create-modal` trigger; routes to either the manual entry
    sheet (`open-member-manual-sheet`) or the search-existing sheet
    (`open-member-search-sheet`).
--}}
<div x-data="{ open: false }" x-cloak
     x-on:open-member-create-modal.window="open = true"
     @keydown.escape.window="open = false">

    <template x-teleport="body">
    <div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] bg-black/50" @click="open = false" style="display:none;"></div>

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-250" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-[61] flex flex-col bg-background rounded-t-3xl shadow-2xl"
         style="display:none; padding-bottom: calc(1rem + env(safe-area-inset-bottom));" @click.stop>

        <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
             style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
            <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
            <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

            <div class="relative flex items-start gap-3">
                <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                    <i class="bi bi-person-plus text-xl"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-black leading-tight">{{ __('member.add_family_member') }}</h3>
                    <p class="text-[12px] text-white/85 mt-0.5">{{ __('member.add_member_chooser_subtitle') }}</p>
                </div>
                <button type="button" @click="open = false" aria-label="{{ __('member.close') }}"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="px-4 pt-4 pb-2 space-y-3">
            <button type="button"
                    @click="open = false; window.dispatchEvent(new CustomEvent('open-member-search-sheet'))"
                    class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border">
                <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-sky-500"><i class="bi bi-search text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('member.search_existing_title') }}</span>
                    <span class="block text-[11px] text-muted-foreground">{{ __('member.search_existing_desc') }}</span>
                </span>
                <i class="bi bi-chevron-right text-gray-300"></i>
            </button>

            <button type="button"
                    @click="open = false; window.dispatchEvent(new CustomEvent('open-member-manual-sheet'))"
                    class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border">
                <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-primary"><i class="bi bi-person-plus text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('member.add_new_title') }}</span>
                    <span class="block text-[11px] text-muted-foreground">{{ __('member.add_new_desc') }}</span>
                </span>
                <i class="bi bi-chevron-right text-gray-300"></i>
            </button>
        </div>
    </div>
    </div>
    </template>
</div>
