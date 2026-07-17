{{--
    "Search Existing Member" — mobile bottom sheet. Search the platform by
    name, guardian name/phone, or the person's own phone/email, pick a match,
    choose how they relate to you (Parent / Child / Spouse — the only
    relationships the family-tree graph can represent as a real edge), and
    link them as family instead of registering a duplicate account.
--}}
<div x-data="memberSearchExistingSheet()" x-cloak
     x-on:open-member-search-sheet.window="openSheet($event.detail)"
     @keydown.escape.window="close()">

    <template x-teleport="body">
    <div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] bg-black/50" @click="close()" style="display:none;"></div>

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-250" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-[61] flex flex-col bg-background rounded-t-3xl shadow-2xl max-h-[94vh] h-[94vh]"
         style="display:none;" @click.stop>

        <div class="flex-shrink-0 rounded-t-3xl bg-white border-b border-border">
            <div class="flex justify-center pt-2.5 pb-1">
                <span class="h-1.5 w-10 rounded-full bg-gray-300"></span>
            </div>
            <div class="flex items-center gap-3 px-4 pb-3">
                <span class="w-10 h-10 rounded-2xl bg-accent flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-search text-primary text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-foreground leading-tight">{{ __('member.search_existing_title') }}</p>
                    <p class="text-[11px] text-muted-foreground leading-tight">{{ __('member.search_existing_sheet_subtitle') }}</p>
                </div>
                <button type="button" @click="close()" class="m-press w-9 h-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted" aria-label="{{ __('member.close') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-4">

            {{-- Step 1: search + pick a candidate --}}
            <template x-if="!selected">
                <div>
                    <div class="relative mb-3">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" x-model="q" @input="onSearchInput()"
                               placeholder="{{ __('member.search_existing_placeholder') }}"
                               class="w-full ps-10 pe-3 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <template x-if="searching">
                        <p class="text-center text-xs text-muted-foreground py-6">{{ __('member.searching') }}</p>
                    </template>

                    <template x-if="!searching && q.length > 0 && results.length === 0">
                        <p class="text-center text-xs text-muted-foreground py-6">{{ __('member.no_results') }}</p>
                    </template>

                    <div class="space-y-2">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="selected = r"
                                    class="m-press w-full text-start flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                                <span class="w-10 h-10 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                    <template x-if="r.avatar"><img :src="r.avatar" alt="" class="w-10 h-10 object-cover"></template>
                                    <template x-if="!r.avatar"><i class="bi bi-person-fill text-muted-foreground"></i></template>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-foreground truncate" x-text="r.name"></span>
                                    <span class="block text-[11px] text-muted-foreground truncate" x-text="r.matched_via"></span>
                                </span>
                                <i class="bi bi-chevron-right text-gray-300 flex-shrink-0"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Step 2: chosen candidate — pick relationship, confirm --}}
            <template x-if="selected">
                <div>
                    <div class="flex items-center gap-3 rounded-xl border border-sky-100 bg-sky-50 p-3 mb-4">
                        <span class="w-11 h-11 rounded-full overflow-hidden bg-white grid place-items-center flex-shrink-0 border border-sky-100">
                            <template x-if="selected.avatar"><img :src="selected.avatar" alt="" class="w-11 h-11 object-cover"></template>
                            <template x-if="!selected.avatar"><i class="bi bi-person-fill text-sky-400"></i></template>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground truncate" x-text="selected.name"></span>
                            <span class="block text-[11px] text-sky-700" x-text="selected.matched_via"></span>
                        </span>
                        <button type="button" @click="selected = null" class="text-xs font-semibold text-sky-700 flex-shrink-0">{{ __('member.change') }}</button>
                    </div>

                    <label class="tf-label mb-2 block">{{ __('member.relationship_to_you') }}</label>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <button type="button" @click="type = 'parent'"
                                class="m-press py-3 rounded-xl text-xs font-bold border transition-colors"
                                :class="type==='parent' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                            {{ __('member.rel_parent') }}
                        </button>
                        <button type="button" @click="type = 'child'"
                                class="m-press py-3 rounded-xl text-xs font-bold border transition-colors"
                                :class="type==='child' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                            {{ __('member.rel_child') }}
                        </button>
                        <button type="button" @click="type = 'spouse'"
                                class="m-press py-3 rounded-xl text-xs font-bold border transition-colors"
                                :class="type==='spouse' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                            {{ __('member.rel_spouse') }}
                        </button>
                    </div>

                    <p class="text-[11px] text-muted-foreground">{{ __('member.link_notice') }}</p>
                </div>
            </template>
        </div>

        <div class="flex-shrink-0 bg-white border-t border-border px-4 py-3 flex items-center gap-3"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button type="button" @click="close()"
                    class="m-press px-5 py-3 rounded-xl text-sm font-semibold text-foreground bg-muted hover:bg-gray-200 transition-colors">
                {{ __('member.cancel') }}
            </button>
            <button type="button" @click="link()" :disabled="!selected || !type || linking"
                    class="m-press flex-1 px-5 py-3 rounded-xl text-sm font-bold text-white bg-primary hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 disabled:opacity-60">
                <span x-show="!linking" class="flex items-center gap-2"><i class="bi bi-link-45deg"></i> {{ __('member.link_as_family') }}</span>
                <span x-show="linking" class="flex items-center gap-2"><span class="inline-block animate-spin">↻</span> {{ __('member.linking') }}</span>
            </button>
        </div>
    </div>
    </div>
    </template>
</div>

@push('scripts')
<script>
function memberSearchExistingSheet() {
    return {
        open: false,
        q: '',
        results: [],
        searching: false,
        selected: null,
        type: '',
        linking: false,
        searchTimer: null,

        openSheet(detail) {
            this.open = true;
            this.q = '';
            this.results = [];
            this.type = '';
            this.selected = (detail && detail.preselect) ? detail.preselect : null;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        onSearchInput() {
            clearTimeout(this.searchTimer);
            const q = this.q.trim();
            if (q.length < 2) { this.results = []; return; }
            this.searchTimer = setTimeout(async () => {
                this.searching = true;
                try {
                    const res = await fetch('{{ route('family.search-existing') }}?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    this.results = (data.success && Array.isArray(data.results)) ? data.results : [];
                } catch (e) { this.results = []; }
                this.searching = false;
            }, 300);
        },

        async link() {
            if (!this.selected || !this.type || this.linking) return;
            this.linking = true;
            try {
                const res = await fetch('{{ route('family.link-existing') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ user_id: this.selected.id, type: this.type }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (window.showToast) window.showToast('success', data.message);
                    window.dispatchEvent(new CustomEvent('family-member-linked'));
                    this.close();
                } else if (window.showToast) {
                    window.showToast('error', data.message || '{{ __('member.js_could_not_add') }}');
                }
            } catch (e) {
                if (window.showToast) window.showToast('error', '{{ __('member.js_went_wrong') }}');
            } finally {
                this.linking = false;
            }
        },
    };
}
</script>
@endpush
