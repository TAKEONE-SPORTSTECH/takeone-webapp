{{--
    First-step chooser for "Add Family Member" (desktop). Owns the existing
    `open-member-create-modal` trigger; routes to either the manual entry
    modal (`<x-profile-modal eventName="open-member-manual-sheet">`, itself
    untouched) or the search-existing modal (`open-member-search-sheet`).
--}}
<div x-data="{ open: false }" x-show="open" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-on:open-member-create-modal.window="open = true"
     @keydown.escape.window="open = false">

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-lg shadow-xl w-full max-w-md" @click.stop>

            <div class="flex items-center justify-between p-4 bg-primary text-white rounded-t-lg">
                <h5 class="text-lg font-medium flex items-center">
                    <i class="bi bi-person-plus me-2"></i>{{ __('member.add_family_member') }}
                </h5>
                <button type="button" @click="open = false" class="text-white hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            <div class="p-5 space-y-3">
                <p class="text-sm text-muted-foreground mb-1">{{ __('member.add_member_chooser_subtitle') }}</p>

                <button type="button"
                        @click="open = false; window.dispatchEvent(new CustomEvent('open-member-search-sheet'))"
                        class="w-full text-start rounded-xl p-4 flex items-center gap-3 bg-white border border-gray-200 hover:border-sky-300 hover:bg-sky-50 transition-colors">
                    <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-sky-500"><i class="bi bi-search text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('member.search_existing_title') }}</span>
                        <span class="block text-xs text-muted-foreground">{{ __('member.search_existing_desc') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-gray-300"></i>
                </button>

                <button type="button"
                        @click="open = false; window.dispatchEvent(new CustomEvent('open-member-manual-sheet'))"
                        class="w-full text-start rounded-xl p-4 flex items-center gap-3 bg-white border border-gray-200 hover:border-primary/40 hover:bg-accent transition-colors">
                    <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-primary"><i class="bi bi-person-plus text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('member.add_new_title') }}</span>
                        <span class="block text-xs text-muted-foreground">{{ __('member.add_new_desc') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-gray-300"></i>
                </button>
            </div>
        </div>
    </div>
</div>
