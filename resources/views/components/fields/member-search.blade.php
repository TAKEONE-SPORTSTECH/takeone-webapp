@props([
    'searchUrl',               // GET ?q= → {people: [{name, email, photo, …}]}
    'placeholder' => null,
    'inputClass' => 'w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent',
    'autofocus' => false,
])
{{--
    Find a TakeOne member — by name, phone, email or TakeOne ID. A search box
    and a result list IN the flow (never an absolute panel a scrolling sheet
    would clip). Picking one fires a bubbling `member-picked` event whose
    detail is that result, exactly as the endpoint sent it; the host decides
    what a pick means: `<div @member-picked="choose($event.detail)">`.

    Standalone: inline x-data (survives shell content swaps), debounced and
    abortable, every value drawn with x-text / :src — never as HTML. The
    endpoint behind `search-url` decides who may search and who can be found
    (App\Members\Support\MemberLookup is the one search the platform uses).
--}}
<div x-data="{
        q: '', results: [], loading: false, searched: false, ctl: null, timer: null,
        find() {
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (q.length < 2) { this.ctl?.abort(); this.results = []; this.searched = false; this.loading = false; return; }
            this.timer = setTimeout(async () => {
                this.ctl?.abort(); this.ctl = new AbortController(); this.loading = true;
                try {
                    const r = await fetch(@js($searchUrl) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: this.ctl.signal });
                    const d = r.ok ? await r.json() : { people: [] };
                    this.results = Array.isArray(d.people) ? d.people : [];
                    this.searched = true;
                } catch (e) { if (e.name !== 'AbortError') { this.results = []; this.searched = true; } }
                finally { this.loading = false; }
            }, 300);
        },
        pick(p) { this.$dispatch('member-picked', p); this.results = []; this.q = ''; this.searched = false; },
     }" {{ $attributes }}>
    <div class="relative">
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input x-ref="q" type="search" x-model="q" @input="find()" @keydown.enter.prevent maxlength="80"
               @if ($autofocus) x-init="$nextTick(() => $el.focus())" @endif
               class="{{ $inputClass }} pl-10" placeholder="{{ $placeholder ?? __('shared.person_picker_search') }}" autocomplete="off">
        <i x-show="loading" class="bi bi-arrow-repeat animate-spin absolute right-3 top-1/2 -translate-y-1/2 text-primary"></i>
    </div>
    <div x-show="results.length" class="mt-2 rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden divide-y divide-gray-50">
        <template x-for="(p, i) in results" :key="i + ':' + p.email">
            <button type="button" @click="pick(p)" class="w-full flex items-center gap-3 px-3 py-2 text-start hover:bg-muted/60 transition-colors">
                <span class="w-6 h-8 rounded overflow-hidden flex-shrink-0 bg-muted"><img :src="p.photo" alt="" class="w-full h-full object-cover"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-900 break-words" x-text="p.name"></span>
                    <span class="block text-xs text-muted-foreground break-all" x-text="p.email + (p.mobile ? ' · ' + p.mobile : '')"></span>
                </span>
                <i class="bi bi-plus-circle text-primary"></i>
            </button>
        </template>
    </div>
    <p x-show="searched && !loading && !results.length" class="text-xs text-muted-foreground mt-2">{{ __('shared.person_picker_none') }}</p>
</div>
