{{--
    <x-member-merge-sheet /> — "Merge with another account" (super-admin).

    One person, two accounts: find the duplicate (likely matches by phone,
    email, name or birthday are offered first; any member can be searched),
    see the ONE account the two become — every detail either of them holds,
    lists joined — and decide only where they disagree (two different
    birthdays, two photos). Type MERGE, done — with an Undo right there.
    The account they sign in with is the one that carries on; when both do,
    the admin picks the sign-in email.

    The work is App\Members\Support\AccountMerger behind the four
    admin.platform.members.merge* routes, which re-check super-admin on every
    call. This sheet decides nothing.

    Opened with:
        window.dispatchEvent(new CustomEvent('open-member-merge', {detail: {id: <user id>, name: '<name>'}}))
    or, straight onto one pair (the duplicate scan), with both summaries:
        … {detail: {id, name, self: <summary>, other: <summary>}}
    Dispatches `member-merged` {kept, merged, uuid} and `member-merge-undone` {kept, merged},
    plus `members-changed` after either, which a members list re-reads on.

    Standalone: own state, teleported to <body>, Design Rule #8 band. Every
    server value is drawn with x-text / :src — never innerHTML.
--}}
@php
    $urls = [
        'candidates' => route('admin.platform.members.merge-candidates', ['id' => '__ID__']),
        'preview' => route('admin.platform.members.merge-preview'),
        'merge' => route('admin.platform.members.merge'),
        'undo' => route('admin.platform.members.merge-undo', ['uuid' => '__UUID__']),
    ];
@endphp
<div x-data="{
        urls: @js($urls),
        open: false, step: 'find', busy: false, err: '',
        selfId: null, self: null, q: '', results: [], searching: false, timer: null,
        keepId: null, mergeId: null, other: null, preview: null, typed: '', done: null, picks: {},
        get keep() { return this.preview ? this.preview.keep : null; },
        get gone() { return this.preview ? this.preview.merge : null; },
        get canMerge() { return this.preview && !this.preview.blockers.length && this.typed.trim().toUpperCase() === 'MERGE' && !this.busy; },
        get rows() { return this.preview ? this.preview.combined.filter(r => r.from !== 'none') : []; },
        get conflicts() { return this.rows.filter(r => r.conflict); },
        get bothSignIn() { return !!(this.keep && this.gone && this.keep.signs_in && this.gone.signs_in); },
        // A pick is WHOSE value (a user id), so it survives a swap.
        pickFor(r) { return this.picks[r.field] || this.keepId; },
        valueOf(r) { return this.pickFor(r) === this.keepId ? r.keep : r.merge; },
        pick(r, id) { this.picks = {...this.picks, [r.field]: id}; },
        sourceOf(r) {
            if (r.kind === 'list') return r.from === 'both' ? '{{ __('from A + B') }}' : (r.from === 'merge' ? '{{ __('from B') }}' : '');
            if (r.conflict) return '';
            return r.from === 'merge' ? '{{ __('from B') }}' : '';
        },
        // The same person usually has the same name twice: A and B tell the accounts apart.
        tag(id) { return this.keep && id === this.keep.id ? 'A' : 'B'; },
        signInWith(id) { if (id !== this.keepId) this.swap(); },
        headers(json) { const h = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''}; if (json) h['Content-Type'] = 'application/json'; return h; },
        async getJson(url) { const r = await fetch(url, {credentials: 'same-origin', headers: this.headers(false)}); const j = await r.json().catch(() => ({})); if (!r.ok || !j.success) throw new Error(j.message || '{{ __('That did not work. Try again.') }}'); return j; },
        openFor(d) {
            Object.assign(this, {selfId: d.id, self: {name: d.name || ''}, q: '', results: [], step: 'find', err: '', busy: false, preview: null, typed: '', done: null, other: null, picks: {}});
            this.open = true;
            if (d.self && d.other) { this.self = d.self; this.choose(d.other); } else { this.load(); }
        },
        async load() {
            this.searching = true; this.err = '';
            try {
                const j = await this.getJson(this.urls.candidates.replace('__ID__', this.selfId) + (this.q.trim() ? '?q=' + encodeURIComponent(this.q.trim()) : ''));
                this.self = j.self; this.results = j.results;
            } catch (e) { this.err = e.message; }
            this.searching = false;
        },
        typing() { clearTimeout(this.timer); this.timer = setTimeout(() => this.load(), 300); },
        choose(r) {
            this.other = r;
            // Keep the account they sign in with; otherwise the one already open.
            const keepOther = r.signs_in && !this.self.signs_in;
            this.keepId = keepOther ? r.id : this.selfId; this.mergeId = keepOther ? this.selfId : r.id;
            this.step = 'compare'; this.typed = ''; this.picks = {}; this.fetchPreview();
        },
        swap() { [this.keepId, this.mergeId] = [this.mergeId, this.keepId]; this.typed = ''; this.fetchPreview(); },
        async fetchPreview() {
            this.preview = null; this.busy = true; this.err = '';
            try { this.preview = await this.getJson(this.urls.preview + '?keep=' + this.keepId + '&merge=' + this.mergeId); }
            catch (e) { this.err = e.message; }
            this.busy = false;
        },
        async doMerge() {
            if (!this.canMerge) return;
            this.busy = true; this.err = '';
            try {
                const r = await fetch(this.urls.merge, {method: 'POST', credentials: 'same-origin', headers: this.headers(true), body: JSON.stringify({keep: this.keepId, merge: this.mergeId, confirm: this.typed.trim().toUpperCase(), take: this.conflicts.filter(r => this.pickFor(r) === this.mergeId).map(r => r.field)})});
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) { this.err = j.message || (j.errors ? Object.values(j.errors)[0][0] : '{{ __('That did not work. Try again.') }}'); this.busy = false; return; }
                this.done = j.merge; this.step = 'done';
                window.showToast && window.showToast('success', j.message);
                window.dispatchEvent(new CustomEvent('member-merged', {detail: {kept: j.merge.kept, merged: j.merge.merged, uuid: j.merge.uuid}}));
                window.dispatchEvent(new CustomEvent('members-changed'));
            } catch (e) { this.err = '{{ __('Could not reach the server. Try again.') }}'; }
            this.busy = false;
        },
        async undo() {
            if (!this.done || this.busy) return;
            this.busy = true; this.err = '';
            try {
                const r = await fetch(this.urls.undo.replace('__UUID__', this.done.uuid), {method: 'POST', credentials: 'same-origin', headers: this.headers(true), body: '{}'});
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) { this.err = j.message || '{{ __('That did not work. Try again.') }}'; this.busy = false; return; }
                window.showToast && window.showToast('success', j.message);
                window.dispatchEvent(new CustomEvent('member-merge-undone', {detail: {kept: j.merge.kept, merged: j.merge.merged}}));
                window.dispatchEvent(new CustomEvent('members-changed'));
                this.open = false;
            } catch (e) { this.err = '{{ __('Could not reach the server. Try again.') }}'; }
            this.busy = false;
        }
     }"
     @open-member-merge.window="openFor($event.detail || {})"
     @keydown.escape.window="if (!busy) open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/50" @click="if (!busy) open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden"
                 role="dialog" aria-modal="true" aria-label="{{ __('Merge accounts') }}">

                {{-- Band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <button type="button" x-show="step === 'compare'" @click="step = 'find'; err = ''; if (!results.length) load()" aria-label="{{ __('Back') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-chevron-left rtl:rotate-180"></i></button>
                        <span x-show="step !== 'compare'" class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-union text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight"
                                x-text="step === 'find' ? '{{ __('Merge with another account') }}' : (step === 'compare' ? '{{ __('Combine into one account') }}' : '{{ __('Accounts merged') }}')"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate"
                               x-text="step === 'find' ? '{{ __('Find the duplicate of') }} ' + (self ? self.name : '') : (step === 'compare' ? '{{ __('Everything from both ends up in one') }}' : '{{ __('One person, one account') }}')"></p>
                        </div>
                        <button type="button" @click="if (!busy) open = false" aria-label="{{ __('Close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">

                    {{-- ① Find the duplicate --}}
                    <div x-show="step === 'find'" class="space-y-3">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" x-model="q" @input="typing()" placeholder="{{ __('Search by name, phone, email or TakeOne ID') }}"
                                   class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground"
                           x-text="q.trim() ? '{{ __('Search results') }}' : '{{ __('Likely duplicates') }}'"></p>

                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="choose(r)"
                                    class="m-press w-full flex items-center gap-3 p-3 rounded-2xl border border-gray-100 hover:border-primary/40 hover:bg-primary/5 text-start transition-colors">
                                <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted"><img :src="r.photo" alt="" class="w-full h-full object-cover object-top"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold text-gray-900 truncate" x-text="r.name"></span>
                                    <span class="block text-[11px] text-muted-foreground truncate" x-text="[r.email, r.mobile, r.birthdate].filter(Boolean).join(' · ') || '{{ __('No contact details') }}'"></span>
                                    <span class="flex flex-wrap gap-1 mt-1">
                                        <template x-for="why in r.reasons" :key="why"><span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-bold" x-text="why"></span></template>
                                        <span x-show="r.signs_in" class="px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-bold"><i class="bi bi-key-fill"></i> {{ __('Signs in') }}</span>
                                        <template x-for="c in r.clubs" :key="c"><span class="px-2 py-0.5 rounded-full bg-muted text-gray-600 text-[10px] font-semibold" x-text="c"></span></template>
                                    </span>
                                </span>
                                <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300"></i>
                            </button>
                        </template>

                        <div x-show="searching" class="py-6 grid place-items-center"><div class="w-7 h-7 border-2 border-primary border-t-transparent rounded-full animate-spin"></div></div>
                        <div x-show="!searching && !results.length" class="py-8 text-center">
                            <i class="bi bi-person-check text-3xl text-gray-300"></i>
                            <p class="text-sm text-muted-foreground mt-1" x-text="q.trim() ? '{{ __('Nobody matches that.') }}' : '{{ __('No likely duplicates. Search for the other account above.') }}'"></p>
                        </div>
                    </div>

                    {{-- ② Compare, choose, confirm --}}
                    <div x-show="step === 'compare'" class="space-y-4">
                        <div x-show="!preview && busy" class="py-10 grid place-items-center"><div class="w-7 h-7 border-2 border-primary border-t-transparent rounded-full animate-spin"></div></div>

                        <template x-if="preview">
                            <div class="space-y-4">
                                {{-- The two accounts going in --}}
                                <div class="flex items-center justify-center gap-3">
                                    <template x-for="(p, n) in [keep, gone]" :key="p.id">
                                        <div class="contents">
                                            <span x-show="n === 1" class="w-8 h-8 rounded-full bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-plus-lg"></i></span>
                                            <div class="flex items-center gap-2 min-w-0 flex-1" :style="n === 1 ? 'flex-direction: row-reverse; text-align: end' : ''">
                                                <span class="relative w-9 h-12 flex-shrink-0">
                                                    <span class="block w-full h-full rounded-lg overflow-hidden bg-muted"><img :src="p.photo" alt="" class="w-full h-full object-cover object-top"></span>
                                                    <span class="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full bg-primary text-white text-[10px] font-black grid place-items-center ring-2 ring-white" x-text="tag(p.id)"></span>
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block text-xs font-black text-gray-900 leading-tight break-words" x-text="p.name"></span>
                                                    <span class="block text-[10px] text-muted-foreground truncate" x-text="p.email || p.mobile || ((p.clubs.join(', ') || '{{ __('No club') }}') + ' · ' + '{{ __('Joined') }} ' + (p.joined || '—'))"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Both sign in: one email carries on --}}
                                <div x-show="bothSignIn" class="rounded-2xl border border-gray-100 p-3">
                                    <p class="text-xs font-bold text-gray-800 mb-2"><i class="bi bi-key-fill text-primary"></i> {{ __('Signs in with') }}</p>
                                    <div class="grid gap-2">
                                        <template x-for="p in [keep, gone]" :key="'s' + p.id">
                                            <button type="button" @click="signInWith(p.id)" :disabled="busy"
                                                    class="w-full flex items-center gap-2 p-2.5 rounded-xl border text-start transition-colors"
                                                    :class="p.id === keepId ? 'border-primary bg-primary/5' : 'border-gray-100 hover:bg-muted/60'">
                                                <span class="w-4 h-4 rounded-full border-2 grid place-items-center flex-shrink-0" :class="p.id === keepId ? 'border-primary' : 'border-gray-300'"><span x-show="p.id === keepId" class="w-2 h-2 rounded-full bg-primary"></span></span>
                                                <span class="text-xs text-gray-800 truncate" x-text="tag(p.id) + ' · ' + p.email"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- The one account they become --}}
                                <div class="rounded-2xl border border-primary/30 bg-primary/5 overflow-hidden">
                                    <div class="px-3 pt-3 pb-2 flex items-center gap-2">
                                        <i class="bi bi-person-check-fill text-primary"></i>
                                        <p class="text-xs font-black text-gray-900 uppercase tracking-wide">{{ __('The combined account') }}</p>
                                        <span x-show="conflicts.length" class="ms-auto px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold" x-text="conflicts.length + ' {{ __('to check') }}'"></span>
                                    </div>
                                    <div class="bg-white divide-y divide-gray-100">
                                        <template x-for="r in rows" :key="r.field">
                                            <div class="px-3 py-2.5">
                                                <div class="flex items-baseline gap-2">
                                                    <span class="w-28 flex-shrink-0 text-[11px] text-muted-foreground" x-text="r.label"></span>
                                                    <template x-if="r.kind === 'text' && !r.conflict">
                                                        <span class="flex-1 min-w-0 text-xs font-semibold text-gray-900 break-words" x-text="r.from === 'merge' ? r.merge : r.keep"></span>
                                                    </template>
                                                    <template x-if="r.kind === 'list'">
                                                        <span class="flex-1 min-w-0 text-xs font-semibold text-gray-900" x-text="r.total"></span>
                                                    </template>
                                                    <template x-if="r.kind === 'photo' && !r.conflict">
                                                        <span class="flex-1"><span class="block w-9 h-12 rounded-lg overflow-hidden bg-muted"><img :src="r.from === 'merge' ? r.merge : r.keep" alt="" class="w-full h-full object-cover object-top"></span></span>
                                                    </template>
                                                    <span x-show="r.conflict" class="flex-1 text-[11px] font-semibold text-amber-700">{{ __('Different on each — pick one') }}</span>
                                                    <span x-show="sourceOf(r)" class="flex-shrink-0 px-1.5 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-bold truncate max-w-[45%]" x-text="sourceOf(r)"></span>
                                                </div>
                                                <template x-if="r.conflict">
                                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                                        <template x-for="p in [keep, gone]" :key="r.field + p.id">
                                                            <button type="button" @click="pick(r, p.id)"
                                                                    class="m-press p-2 rounded-xl border-2 text-start transition-colors min-w-0"
                                                                    :class="pickFor(r) === p.id ? 'border-primary bg-primary/5' : 'border-gray-100 hover:bg-muted/60'">
                                                                <template x-if="r.kind === 'photo'">
                                                                    <span class="block w-12 h-16 rounded-lg overflow-hidden bg-muted"><img :src="p.id === keepId ? r.keep : r.merge" alt="" class="w-full h-full object-cover object-top"></span>
                                                                </template>
                                                                <template x-if="r.kind !== 'photo'">
                                                                    <span class="block text-xs font-bold text-gray-900 break-words" x-text="p.id === keepId ? r.keep : r.merge"></span>
                                                                </template>
                                                                <span class="flex items-center gap-1 mt-1 text-[10px]" :class="pickFor(r) === p.id ? 'text-primary font-bold' : 'text-muted-foreground'">
                                                                    <i class="bi" :class="pickFor(r) === p.id ? 'bi-check-circle-fill' : 'bi-circle'"></i><span class="truncate" x-text="'{{ __('Account') }} ' + tag(p.id)"></span>
                                                                </span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <div x-show="!rows.length" class="px-3 py-3 text-xs text-muted-foreground">{{ __('Neither account has profile details yet.') }}</div>
                                    </div>
                                </div>

                                <template x-for="b in preview.blockers" :key="b">
                                    <div class="rounded-xl bg-red-50 border border-red-100 px-3 py-2 text-xs text-red-700 flex gap-2"><i class="bi bi-slash-circle"></i><span x-text="b"></span></div>
                                </template>
                                <template x-for="w in preview.warnings" :key="w">
                                    <div class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2 text-xs text-amber-800 flex gap-2"><i class="bi bi-exclamation-triangle"></i><span x-text="w"></span></div>
                                </template>

                                <div x-show="!preview.blockers.length" class="rounded-2xl border border-gray-100 p-3">
                                    <p class="text-xs font-bold text-gray-800">
                                        <span x-text="preview.total"></span> {{ __('records from both accounts come together') }}
                                    </p>
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        <template x-for="c in preview.counts" :key="c.label">
                                            <span class="px-2 py-1 rounded-full bg-muted text-[10px] font-semibold text-gray-700"><span x-text="c.label"></span> · <span class="tabular-nums" x-text="c.count"></span></span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground mt-2 leading-relaxed">{{ __('Clubs, events, results, payments, family and photos from both accounts all end up on the one account. You can undo straight after.') }}</p>
                                </div>

                                <div x-show="!preview.blockers.length">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type MERGE to confirm') }}</label>
                                    <input type="text" x-model="typed" autocomplete="off" autocapitalize="characters" placeholder="MERGE"
                                           @keydown.enter.prevent="doMerge()"
                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-mono tracking-widest uppercase focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ③ Done, with undo --}}
                    <div x-show="step === 'done'" class="py-6 text-center">
                        <span class="mx-auto w-16 h-16 rounded-full bg-green-50 text-green-600 grid place-items-center m-float"><i class="bi bi-check2-circle text-3xl"></i></span>
                        <p class="text-base font-black text-gray-900 mt-3" x-text="keep ? keep.name : ''"></p>
                        <p class="text-xs text-muted-foreground mt-1" x-show="done">
                            <span x-text="done ? done.rows : 0"></span> {{ __('records moved') }}<span x-show="done && done.removed"> · <span x-text="done ? done.removed : 0"></span> {{ __('duplicates removed') }}</span>
                        </p>
                    </div>

                    <p x-show="err" x-cloak class="mt-3 text-sm text-red-600" x-text="err"></p>
                </div>

                <div x-show="step !== 'find'" class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <template x-if="step === 'compare'">
                        <button type="button" @click="doMerge()" :disabled="!canMerge"
                                class="flex-1 bg-destructive text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-opacity disabled:opacity-40 inline-flex items-center justify-center gap-2">
                            <i class="bi bi-union"></i><span x-text="busy ? '{{ __('Merging…') }}' : '{{ __('Merge accounts') }}'"></span>
                        </button>
                    </template>
                    <template x-if="step === 'done'">
                        <div class="flex-1 flex gap-2">
                            <button type="button" @click="undo()" :disabled="busy" class="flex-1 border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-muted/60 inline-flex items-center justify-center gap-2 disabled:opacity-50">
                                <i class="bi bi-arrow-counterclockwise"></i><span x-text="busy ? '{{ __('Undoing…') }}' : '{{ __('Undo') }}'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
