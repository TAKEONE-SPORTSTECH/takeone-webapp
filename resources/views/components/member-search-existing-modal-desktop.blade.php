{{--
    "Search Existing Member" — desktop modal. Same search/select/link flow as
    the mobile sheet, styled to match <x-profile-modal>'s desktop chrome.
--}}
<div x-data="memberSearchExistingModal()" x-show="open" x-cloak
     class="fixed inset-0 z-[60] overflow-y-auto"
     x-on:open-member-search-sheet.window="openModal($event.detail)"
     @keydown.escape.window="close()">

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="close()"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-lg shadow-xl w-full max-w-lg" @click.stop>

            <div class="flex items-center justify-between p-4 bg-primary text-white rounded-t-lg">
                <h5 class="text-lg font-medium flex items-center">
                    <i class="bi bi-search me-2"></i>{{ __('member.search_existing_title') }}
                </h5>
                <button type="button" @click="close()" class="text-white hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            <div class="p-5" style="max-height: 60vh; overflow-y: auto;">
                <template x-if="!selected">
                    <div>
                        <div class="relative mb-3">
                            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" x-model="q" @input="onSearchInput()"
                                   placeholder="{{ __('member.search_existing_placeholder') }}"
                                   class="w-full ps-10 pe-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
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
                                        class="w-full text-start flex items-center gap-3 rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
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

                <template x-if="selected">
                    <div>
                        <div class="flex items-center gap-3 rounded-lg border border-sky-100 bg-sky-50 p-3 mb-4">
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

                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('member.relationship_to_you') }}</label>
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <button type="button" @click="type = 'parent'"
                                    class="py-2.5 rounded-lg text-xs font-bold border transition-colors"
                                    :class="type==='parent' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                                {{ __('member.rel_parent') }}
                            </button>
                            <button type="button" @click="type = 'child'"
                                    class="py-2.5 rounded-lg text-xs font-bold border transition-colors"
                                    :class="type==='child' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                                {{ __('member.rel_child') }}
                            </button>
                            <button type="button" @click="type = 'spouse'"
                                    class="py-2.5 rounded-lg text-xs font-bold border transition-colors"
                                    :class="type==='spouse' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">
                                {{ __('member.rel_spouse') }}
                            </button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">{{ __('member.link_notice') }}</p>
                    </div>
                </template>
            </div>

            <div class="flex justify-between items-center p-4 bg-gray-50 border-t rounded-b-lg">
                <button type="button" class="btn btn-secondary" @click="close()">{{ __('member.cancel') }}</button>
                <button type="button" class="btn btn-primary" @click="link()" :disabled="!selected || !type || linking">
                    <span x-show="!linking"><i class="bi bi-link-45deg me-1"></i>{{ __('member.link_as_family') }}</span>
                    <span x-show="linking"><span class="inline-block animate-spin me-1">↻</span>{{ __('member.linking') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function memberSearchExistingModal() {
    return {
        open: false,
        q: '',
        results: [],
        searching: false,
        selected: null,
        type: '',
        linking: false,
        searchTimer: null,

        openModal(detail) {
            this.open = true;
            this.q = '';
            this.results = [];
            this.type = '';
            this.selected = (detail && detail.preselect) ? detail.preselect : null;
        },
        close() { this.open = false; },

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
