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

        <div class="flex justify-center pt-2.5 pb-1">
            <span class="h-1.5 w-10 rounded-full bg-gray-300"></span>
        </div>
        <div class="flex items-center gap-3 px-4 pb-3 pt-1">
            <span class="w-10 h-10 rounded-2xl bg-accent flex items-center justify-center flex-shrink-0">
                <i class="bi bi-person-plus text-primary text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-foreground leading-tight">{{ __('member.add_family_member') }}</p>
                <p class="text-[11px] text-muted-foreground leading-tight">{{ __('member.add_member_chooser_subtitle') }}</p>
            </div>
            <button type="button" @click="open = false" class="m-press w-9 h-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted" aria-label="{{ __('member.close') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="px-4 pb-2 space-y-3">
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
