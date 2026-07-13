@props([
    'searchUrl',
    'assignUrl',
    'removeUrl',
    'occurrenceDate',          // Y-m-d default date to cover
    'occurrenceLabel' => '',   // human label e.g. "Mon, Jun 23"
    'substitute' => null,      // ['name'=>..,'date'=>..] if one is already set
    'color' => '#0ea5e9',
    'listUrl',
    'triggerOnly' => false,    // when true: render only the sheet (no card); open via the
                               // 'open-substitute-sheet' / 'remove-substitute' window events
])

{{--
    Assign a substitute trainer for ONE dated occurrence of a club class.
    Searches platform users (inside OR outside the club) by id, name, email or
    phone, then assigns them for the chosen date. Used on the club-class detail
    by the assigned coach / club manager. Mobile bottom-sheet (teleported).
--}}
<div class="{{ $triggerOnly ? '' : 'px-4 mt-4' }}"
     x-data="substitutePicker({
        searchUrl: '{{ $searchUrl }}',
        assignUrl: '{{ $assignUrl }}',
        removeUrl: '{{ $removeUrl }}',
        csrf: '{{ csrf_token() }}',
        listUrl: '{{ $listUrl }}',
        date: '{{ $occurrenceDate }}',
        color: '{{ $color }}',
        current: {{ $substitute ? Illuminate\Support\Js::from($substitute) : 'null' }},
     })"
     @open-substitute-sheet.window="openSheet()"
     @remove-substitute.window="remove()">

    @unless($triggerOnly)
    <div class="rounded-2xl p-4 bg-white border border-border">
        <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
            <i class="bi bi-arrow-left-right text-primary"></i> {{ __('shared.components_substitute_picker_substitute_trainer') }}
        </p>

        {{-- Current substitute (if any) --}}
        <template x-if="current">
            <div class="mt-3 rounded-xl p-3 flex items-center gap-3 bg-amber-50 border border-amber-100">
                <div class="w-9 h-9 rounded-full bg-amber-500 text-white grid place-items-center text-xs font-bold flex-shrink-0">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground truncate" x-text="current.name"></p>
                    <p class="text-[11px] text-muted-foreground">{{ __('shared.components_substitute_picker_covering_on', ['date' => $occurrenceLabel]) }}</p>
                </div>
                <button type="button" @click="remove()" :disabled="saving"
                        class="m-press text-xs font-bold text-red-500 px-2 py-1 rounded-lg hover:bg-red-50">{{ __('shared.components_substitute_picker_remove') }}</button>
            </div>
        </template>

        <p class="text-sm text-muted-foreground mt-2" x-show="!current">
            {{ __('shared.components_substitute_picker_cant_make_it', ['date' => $occurrenceLabel]) }}
        </p>

        <button type="button" @click="openSheet()"
                class="m-press w-full mt-3 py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2"
                :style="`background:${color}`">
            <i class="bi" :class="current ? 'bi-arrow-repeat' : 'bi-person-plus'"></i>
            <span x-text="current ? '{{ __('shared.components_substitute_picker_change_substitute') }}' : '{{ __('shared.components_substitute_picker_assign_a_substitute') }}'"></span>
        </button>
    </div>
    @endunless

    {{-- Bottom-sheet picker (teleported to <body> so fixed positioning escapes the shell transform) --}}
    <template x-teleport="body">
    <div>
        <div x-show="open" x-transition.opacity x-cloak
             class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="open=false"></div>
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

            <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl text-white"
                 :style="`background: linear-gradient(160deg, ${color}, ${color}cc)`">
                <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
                <div class="flex items-center justify-between mt-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-black leading-tight truncate">{{ __('shared.components_substitute_picker_assign_a_substitute') }}</h2>
                        <p class="text-[11px] text-white/80 truncate">{{ __('shared.components_substitute_picker_search_by') }}</p>
                    </div>
                    <button type="button" @click="open=false" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                {{-- Date --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_substitute_picker_date_to_cover') }}</label>
                    <input type="date" x-model="form.date" :min="todayStr" @change="search()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                {{-- Search --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_substitute_picker_find_a_trainer') }}</label>
                    <div class="relative">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" x-model="q" @input.debounce.300ms="search()" placeholder="{{ __('shared.components_substitute_picker_search_placeholder') }}"
                               class="w-full ps-10 pe-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                {{-- Selected --}}
                <template x-if="selected">
                    <div class="rounded-xl p-3 flex items-center gap-3 border-2" :style="`border-color:${color}`">
                        <span class="w-9 h-9 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                            <template x-if="selected.avatar"><img :src="selected.avatar" alt="" class="w-9 h-9 object-cover"></template>
                            <template x-if="!selected.avatar"><span class="text-xs font-bold text-muted-foreground" x-text="selected.initials"></span></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground truncate" x-text="selected.name"></p>
                            <p class="text-[11px] text-muted-foreground truncate" x-html="ratingHtml(selected)"></p>
                        </div>
                        <button type="button" @click="selected=null" class="m-press text-xs font-semibold text-muted-foreground px-2 py-1">{{ __('shared.components_substitute_picker_change') }}</button>
                    </div>
                </template>

                {{-- Results --}}
                <div class="space-y-2" x-show="!selected">
                    <template x-for="u in results" :key="u.id">
                        <button type="button" @click="!u.busy && (selected=u)" :disabled="u.busy"
                                class="m-press w-full text-start rounded-xl p-3 flex items-center gap-3 bg-white border border-gray-100 transition-colors"
                                :class="u.busy ? 'opacity-60 cursor-not-allowed' : 'hover:border-primary'">
                            <span class="w-9 h-9 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                <template x-if="u.avatar"><img :src="u.avatar" alt="" class="w-9 h-9 object-cover"></template>
                                <template x-if="!u.avatar"><span class="text-xs font-bold text-muted-foreground" x-text="u.initials"></span></template>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground truncate" x-text="u.name"></p>
                                <p class="text-[11px] truncate" :class="u.busy ? 'text-red-500' : 'text-muted-foreground'"
                                   x-html="u.busy ? ('{{ __('shared.components_substitute_picker_busy_prefix') }}' + (u.busy_reason || '{{ __('shared.components_substitute_picker_has_a_session_then') }}')) : ratingHtml(u)"></p>
                            </div>
                            <span x-show="u.busy" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-500 flex-shrink-0">{{ __('shared.components_substitute_picker_busy') }}</span>
                            <i x-show="!u.busy" class="bi bi-chevron-right text-gray-300"></i>
                        </button>
                    </template>
                    <div x-show="q.length>0 && !searching && results.length===0" class="text-center py-8 text-sm text-muted-foreground">
                        <i class="bi bi-search text-2xl text-gray-300"></i>
                        <p class="mt-2">{{ __('shared.components_substitute_picker_no_one_found') }} “<span x-text="q"></span>”.</p>
                    </div>
                    <div x-show="searching" class="text-center py-6 text-sm text-muted-foreground"><i class="bi bi-arrow-repeat animate-spin"></i> {{ __('shared.components_substitute_picker_searching') }}</div>
                    <div x-show="q.length===0" class="text-center py-8 text-sm text-muted-foreground">
                        <i class="bi bi-people text-2xl text-gray-300"></i>
                        <p class="mt-2">{{ __('shared.components_substitute_picker_search_for_trainer') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex-shrink-0 px-4 py-3 border-t border-border bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="save()" :disabled="saving || !selected"
                        class="m-press w-full h-12 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                        :style="`background:${color}`">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i> {{ __('shared.components_substitute_picker_assign_substitute_btn') }}
                </button>
            </div>
        </div>
    </div>
    </template>

    <script>
    // Substitute picker — searches platform users and assigns one for a date.
    // Inline (inside shell-content) so it survives the mobile shell's AJAX swaps.
    function substitutePicker(cfg) {
        return {
            open: false, saving: false, searching: false,
            q: '', results: [], selected: null,
            current: cfg.current,
            color: cfg.color,
            todayStr: new Date().toISOString().slice(0, 10),
            form: { date: cfg.date },

            openSheet() { this.q=''; this.results=[]; this.selected=null; this.form.date = cfg.date; this.open = true; },

            // Star rating line for a candidate (★ 4.5 · 12 ratings), or "No ratings yet".
            ratingHtml(u) {
                if (!u || !u.rating) return '<span class="text-gray-400">{{ __('shared.components_substitute_picker_no_ratings_yet') }}</span>';
                const c = u.rating_count ? (' · ' + u.rating_count + (u.rating_count === 1 ? ' {{ __('shared.components_substitute_picker_rating_one') }}' : ' {{ __('shared.components_substitute_picker_rating_many') }}')) : '';
                return '<span class="text-amber-500"><i class="bi bi-star-fill"></i> ' + u.rating.toFixed(1) + '</span>'
                     + '<span class="text-muted-foreground">' + c + '</span>';
            },

            async search() {
                const q = this.q.trim();
                if (!q) { this.results = []; return; }
                this.searching = true;
                try {
                    const res = await fetch(cfg.searchUrl + '?q=' + encodeURIComponent(q) + '&date=' + encodeURIComponent(this.form.date || cfg.date), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({ results: [] }));
                    this.results = data.results || [];
                } catch (e) { this.results = []; }
                finally { this.searching = false; }
            },

            async save() {
                if (!this.selected) return;
                this.saving = true;
                try {
                    const res = await fetch(cfg.assignUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ substitute_user_id: this.selected.id, date: this.form.date }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __('shared.components_substitute_picker_could_not_assign') }}'); this.saving = false; return; }
                    window.showToast('success', data.message || '{{ __('shared.components_substitute_picker_substitute_assigned') }}');
                    this.open = false;
                    this._backToList();
                } catch (e) { window.showToast('error', '{{ __('shared.components_substitute_picker_network_error') }}'); }
                finally { this.saving = false; }
            },

            async remove() {
                if (!this.current) return;
                this.saving = true;
                try {
                    const res = await fetch(cfg.removeUrl, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ date: this.current.date || cfg.date }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __('shared.components_substitute_picker_could_not_remove') }}'); this.saving = false; return; }
                    window.showToast('success', data.message || '{{ __('shared.components_substitute_picker_substitute_removed') }}');
                    this._backToList();
                } catch (e) { window.showToast('error', '{{ __('shared.components_substitute_picker_network_error') }}'); }
                finally { this.saving = false; }
            },

            _backToList() {
                setTimeout(function () {
                    var a = document.querySelector('a[data-route="me.schedule"]');
                    if (a) a.click(); else window.location.href = cfg.listUrl;
                }, 400);
            },
        };
    }
    </script>
</div>
