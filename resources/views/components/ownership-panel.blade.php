@props([
    'club',                       // App\Clubs\Models\Tenant
    'color' => '#7c6bf5',         // the sheet band's colour (platform action accent)
])
@php
    // Renders NOTHING unless the relationships layer is on (PARTIES_ENABLED).
    $enabled = (bool) config('parties.enabled');
    $color = \App\Support\Palette::safe($color, '#7c6bf5');
    $cfg = [
        'stateUrl' => route('admin.club.owners', $club->slug),
        'proposeUrl' => route('admin.club.owners.propose', $club->slug),
        'motionBase' => url('admin/club/'.$club->slug.'/owners/motions'),
        'searchUrl' => route('admin.club.members.search', $club->slug),
        'slug' => $club->slug,
        't' => [
            'total' => __('parties::parties.err_total'),
            'failed' => __('shared.something_went_wrong'),
        ],
    ];
@endphp
@if ($enabled)
{{--
    Owners & shares (App\Parties). Who owns the club and how much, the history,
    an open proposal with its vote, and "Change ownership" in a bottom sheet.
    The majority owner's change applies at once; anyone else's goes to a vote
    weighted by share — more than 50% passes (owner's rule, 2026-10-03).

    Standalone: ALL behaviour is inline x-data (no registration script), so it
    survives the mobile shell's content swaps and the teleported sheet. Every
    server value is drawn with x-text / :src — never as HTML. Other owners'
    screens refresh over realtime:ownership {action:'refresh'}.
--}}
<div x-data="{
        cfg: @js($cfg),
        loaded: false, busy: false,
        owners: [], history: [], motion: null, canPropose: false, iDecide: false,
        showHistory: false,
        sheet: false, rows: [], reason: '', q: '', results: [], searching: false, timer: null,
        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
        pct(bp) { return (Math.round(bp) / 100).toFixed(bp % 100 ? 2 : 0).replace(/\.?0+$/, '') + '%'; },
        async load() {
            try {
                const r = await fetch(this.cfg.stateUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) this.apply(await r.json());
            } catch (e) {}
            this.loaded = true;
        },
        apply(d) {
            this.owners = d.owners || []; this.history = d.history || []; this.motion = d.motion || null;
            this.canPropose = !! d.can_propose; this.iDecide = !! d.i_decide;
            this.$dispatch('ownership-updated', { owners: this.owners, motion: this.motion });
        },
        async post(url, body) {
            if (this.busy) return null;
            this.busy = true;
            try {
                const r = await fetch(url, { method: 'POST', body: JSON.stringify(body || {}), headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success) { this.apply(d); window.showToast?.('success', d.message); return d; }
                window.showToast?.('error', d.message || (d.errors ? Object.values(d.errors)[0][0] : this.cfg.t.failed));
            } catch (e) { window.showToast?.('error', this.cfg.t.failed); }
            finally { this.busy = false; }
            return null;
        },
        vote(approve) { return this.post(this.cfg.motionBase + '/' + this.motion.uuid + '/vote', { approve }); },
        withdraw() { return this.post(this.cfg.motionBase + '/' + this.motion.uuid + '/withdraw'); },
        openSheet() {
            this.rows = this.owners.map(o => ({ id: o.id, name: o.name, photo: o.photo, share: o.share_bp / 100 }));
            this.reason = ''; this.q = ''; this.results = []; this.sheet = true;
        },
        get total() { return Math.round(this.rows.reduce((s, r) => s + (parseFloat(r.share) || 0), 0) * 100) / 100; },
        find() {
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (q.length < 2) { this.results = []; return; }
            this.timer = setTimeout(async () => {
                this.searching = true;
                try {
                    const r = await fetch(this.cfg.searchUrl + '?query=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    const d = r.ok ? await r.json() : {};
                    this.results = (d.users || []).filter(u => ! this.rows.some(x => x.id === u.id)).slice(0, 8);
                } catch (e) { this.results = []; }
                this.searching = false;
            }, 300);
        },
        add(u) { this.rows.push({ id: u.id, name: u.name, photo: u.profile_picture, share: 0 }); this.q = ''; this.results = []; },
        async submit() {
            if (this.total !== 100) { window.showToast?.('error', this.cfg.t.total); return; }
            const d = await this.post(this.cfg.proposeUrl, {
                owners: this.rows.filter(r => parseFloat(r.share) > 0).map(r => ({ user_id: r.id, share: parseFloat(r.share) })),
                reason: this.reason || null,
            });
            if (d) this.sheet = false;
        },
     }"
     x-init="load()"
     @realtime:ownership.window="if ($event.detail?.action === 'refresh' && (! $event.detail.club || $event.detail.club === cfg.slug)) load()"
     {{ $attributes->merge(['class' => 'rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden']) }}>

    {{-- Header --}}
    <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-100">
        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 bg-primary/10 text-primary"><i class="bi bi-pie-chart-fill text-lg"></i></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground">{{ __('parties::parties.owners_title') }}</p>
            <p class="text-xs text-muted-foreground">{{ __('parties::parties.owners_sub') }}</p>
        </div>
        <button type="button" x-show="canPropose && ! motion" x-cloak @click="openSheet()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border border-primary text-primary hover:bg-primary hover:text-white transition-colors">
            <i class="bi bi-arrow-left-right"></i>{{ __('parties::parties.propose') }}
        </button>
    </div>

    {{-- Loading shimmer --}}
    <div x-show="! loaded" class="p-4 space-y-3">
        <div class="h-12 rounded-lg bg-muted animate-pulse"></div>
        <div class="h-12 rounded-lg bg-muted animate-pulse"></div>
    </div>

    {{-- Owners --}}
    <div x-show="loaded" x-cloak class="p-4 space-y-3">
        <p x-show="! owners.length" class="text-sm text-muted-foreground text-center py-2">{{ __('parties::parties.owners_none') }}</p>
        <template x-for="o in owners" :key="o.id">
            <div class="flex items-center gap-3">
                <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted grid place-items-center">
                    <template x-if="o.photo"><img :src="o.photo" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="! o.photo"><i class="bi bi-person-fill text-muted-foreground"></i></template>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-foreground break-words" x-text="o.name"></span>
                        <span x-show="o.share_bp > 5000" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent text-primary">{{ __('parties::parties.owners_majority') }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-muted overflow-hidden">
                        <div class="h-full rounded-full bg-primary transition-all duration-500" :style="'width:' + (o.share_bp / 100) + '%'"></div>
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1" x-text="o.since ? @js(__('parties::parties.owners_since', ['date' => '__D__'])).replace('__D__', o.since) : ''"></p>
                </div>
                <span class="text-sm font-bold text-foreground tabular-nums" x-text="pct(o.share_bp)"></span>
            </div>
        </template>
    </div>

    {{-- An open proposal and its vote --}}
    <template x-if="motion">
        <div class="mx-4 mb-4 rounded-xl border border-primary/30 bg-primary/5 p-3 space-y-3">
            <div class="flex items-start gap-2">
                <i class="bi bi-hourglass-split text-primary mt-0.5"></i>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground">{{ __('parties::parties.motion_open') }}</p>
                    <p class="text-xs text-muted-foreground" x-text="@js(__('parties::parties.motion_by', ['name' => '__N__'])).replace('__N__', motion.by)"></p>
                    <p x-show="motion.reason" class="text-xs text-foreground mt-1 break-words" x-text="motion.reason"></p>
                </div>
            </div>
            <div class="space-y-1">
                <template x-for="p in motion.proposal" :key="p.id">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-foreground break-words" x-text="p.name"></span>
                        <span class="font-semibold tabular-nums" x-text="pct(p.share_bp)"></span>
                    </div>
                </template>
            </div>
            {{-- The tally: for · against · not voted, as one bar --}}
            <div>
                <div class="h-2 rounded-full bg-muted overflow-hidden flex">
                    <div class="h-full bg-green-500 transition-all duration-500" :style="'width:' + (motion.tally.yes / 100) + '%'"></div>
                    <div class="h-full bg-red-400 transition-all duration-500" :style="'width:' + (motion.tally.no / 100) + '%'"></div>
                </div>
                <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                    <span><span class="text-green-600 font-semibold" x-text="pct(motion.tally.yes)"></span> {{ __('parties::parties.motion_yes') }}</span>
                    <span><span class="text-red-500 font-semibold" x-text="pct(motion.tally.no)"></span> {{ __('parties::parties.motion_no') }}</span>
                    <span><span x-text="pct(motion.tally.undecided)"></span> {{ __('parties::parties.motion_undecided') }}</span>
                </div>
                <p class="text-[11px] text-muted-foreground mt-1">{{ __('parties::parties.motion_needs') }}</p>
            </div>
            <p x-show="motion.my_vote === true" class="text-xs text-green-600"><i class="bi bi-check-circle me-1"></i>{{ __('parties::parties.motion_you_voted_for') }}</p>
            <p x-show="motion.my_vote === false" class="text-xs text-red-500"><i class="bi bi-x-circle me-1"></i>{{ __('parties::parties.motion_you_voted_against') }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-show="motion.can_vote" @click="vote(true)" :disabled="busy"
                        class="flex-1 min-w-[7rem] py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50">
                    <i class="bi bi-hand-thumbs-up me-1"></i>{{ __('parties::parties.motion_vote_for') }}
                </button>
                <button type="button" x-show="motion.can_vote" @click="vote(false)" :disabled="busy"
                        class="flex-1 min-w-[7rem] py-2 rounded-lg border border-red-300 text-red-600 text-sm font-semibold hover:bg-red-50 transition-colors disabled:opacity-50">
                    <i class="bi bi-hand-thumbs-down me-1"></i>{{ __('parties::parties.motion_vote_against') }}
                </button>
                <button type="button" x-show="motion.can_withdraw" @click="withdraw()" :disabled="busy"
                        class="px-3 py-2 rounded-lg text-sm text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors disabled:opacity-50">
                    {{ __('parties::parties.motion_withdraw') }}
                </button>
            </div>
        </div>
    </template>

    {{-- History, closed by default --}}
    <div x-show="loaded && history.length" x-cloak class="border-t border-gray-100">
        <button type="button" @click="showHistory = ! showHistory" class="w-full px-4 py-3 flex items-center justify-between text-sm text-foreground hover:bg-muted/40 transition-colors">
            <span class="flex items-center gap-2"><i class="bi bi-clock-history text-muted-foreground"></i>{{ __('parties::parties.owners_history') }}
                <span class="py-0.5 px-2 rounded-full text-xs font-medium bg-purple-100 text-purple-600" x-text="history.length"></span></span>
            <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="showHistory && 'rotate-180'"></i>
        </button>
        <div x-show="showHistory" x-transition class="px-4 pb-4 space-y-2">
            <template x-for="(h, i) in history" :key="i">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-foreground break-words" x-text="h.name"></span>
                    <span class="text-muted-foreground tabular-nums" x-text="pct(h.share_bp) + ' · ' + h.from + ' – ' + h.to"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Change ownership — the bottom sheet (Design Rule #8), teleported past the shell's transform --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="sheet = false">
            <div class="absolute inset-0 bg-black/50" x-show="sheet" x-transition.opacity @click="sheet = false"></div>
            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-pie-chart-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('parties::parties.propose_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('parties::parties.propose_sub') }}</p>
                        </div>
                        <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi" :class="iDecide ? 'bi-lightning-charge-fill' : 'bi-people-fill'"></i>
                            <span x-text="iDecide ? @js(__('parties::parties.propose_majority_note')) : @js(__('parties::parties.propose_vote_note'))"></span>
                        </span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <template x-for="(r, i) in rows" :key="r.id">
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-2.5">
                            <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted grid place-items-center">
                                <template x-if="r.photo"><img :src="r.photo" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="! r.photo"><i class="bi bi-person-fill text-muted-foreground"></i></template>
                            </span>
                            <span class="min-w-0 flex-1 text-sm font-medium text-foreground break-words" x-text="r.name"></span>
                            <label class="flex items-center gap-1">
                                <input type="number" min="0" max="100" step="0.01" x-model="r.share" inputmode="decimal"
                                       aria-label="{{ __('parties::parties.propose_share') }}"
                                       class="w-20 px-2 py-2 border border-gray-200 rounded-lg text-sm text-end focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <span class="text-sm text-muted-foreground">%</span>
                            </label>
                            <button type="button" @click="rows.splice(i, 1)" aria-label="{{ __('parties::parties.propose_remove') }}"
                                    class="w-9 h-9 rounded-lg grid place-items-center text-muted-foreground hover:text-red-600 hover:bg-red-50 transition-colors">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </template>

                    {{-- Total meter: must be exactly 100 --}}
                    <div class="rounded-xl p-3" :class="total === 100 ? 'bg-green-50' : 'bg-amber-50'">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ __('parties::parties.propose_total') }}</span>
                            <span class="font-bold tabular-nums" :class="total === 100 ? 'text-green-600' : 'text-amber-600'" x-text="total + '%'"></span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-white overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300" :class="total === 100 ? 'bg-green-500' : (total > 100 ? 'bg-red-400' : 'bg-amber-400')" :style="'width:' + Math.min(total, 100) + '%'"></div>
                        </div>
                    </div>

                    {{-- Add an owner --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('parties::parties.propose_add_person') }}</label>
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="search" x-model="q" @input="find()" maxlength="80" autocomplete="off"
                                   class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <i x-show="searching" class="bi bi-arrow-repeat animate-spin absolute right-3 top-1/2 -translate-y-1/2 text-primary"></i>
                        </div>
                        <div x-show="results.length" class="mt-2 rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden divide-y divide-gray-50">
                            <template x-for="u in results" :key="u.id">
                                <button type="button" @click="add(u)" class="w-full flex items-center gap-3 px-3 py-2 text-start hover:bg-muted/60 transition-colors">
                                    <span class="w-6 h-8 rounded overflow-hidden flex-shrink-0 bg-muted"><img x-show="u.profile_picture" :src="u.profile_picture" alt="" class="w-full h-full object-cover"></span>
                                    <span class="min-w-0 flex-1 text-sm text-gray-900 break-words" x-text="u.name"></span>
                                    <i class="bi bi-plus-circle text-primary"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('parties::parties.propose_reason') }}</label>
                        <input type="text" x-model="reason" maxlength="255"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submit()" :disabled="busy || total !== 100 || ! rows.length"
                            class="w-full py-3 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50">
                        <i class="bi bi-check-lg me-1"></i>{{ __('parties::parties.propose_submit') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
