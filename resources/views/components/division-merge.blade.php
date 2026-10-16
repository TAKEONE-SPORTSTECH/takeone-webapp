@props([
    'event',                    // the event's uuid — REQUIRED
    'color' => '#7c6bf5',       // the event's colour, for the row tile and the sheet's band
])

{{--
    Merging small divisions — one row on the divisions page, opening a sheet.

    The engine is App\Events\Support\DivisionMerge: it finds the groups too small
    to be a fair competition, grades every possible merge green (safe), amber
    (only with written guardian consent) or red (never), and says why in words.
    This component only SHOWS those answers and sends the organiser's choice;
    the merge endpoint judges again and refuses anything the rules do not allow,
    so nothing here is a boundary.

    Same tool for every host — person, club or federation (the federation
    dashboard's Divisions tab draws the same answers natively; keep the two in
    step: app/Federations/resources/patches/div-manage.js → dmMg*).

    After a merge it dispatches `division-merge-done` {event, into, removed};
    <x-event-divisions> re-reads its list on it. Rendered only while
    config('events.division_merge.enabled') is on.
--}}

@php
    $on = \App\Events\Support\DivisionMerge::enabled() && $event;
    $c = \App\Support\Palette::safe($color ?? '#7c6bf5');
    $mergeUrl = $on ? route('me.events.divisions.merge', $event) : null;
    $handbookUrl = $on && \App\Events\Support\MergeHandbookPdf::available() ? route('me.events.divisions.merge.handbook', $event) : null;
    $words = [
        'safe' => __('events::events.merge_ui_safe'),
        'consent' => __('events::events.merge_ui_consent'),
        'unsafe' => __('events::events.merge_ui_unsafe'),
        'into' => __('events::events.merge_ui_into'),
        'moving' => __('events::events.merge_ui_moving'),
        'merge_into' => __('events::events.merge_ui_merge_into'),
        'merging' => __('events::events.merge_ui_merging'),
        'failed' => __('events::events.merge_ui_failed'),
        'limits' => __('events::events.merge_ui_limits'),
    ];
@endphp

@if($on)
<div {{ $attributes }} x-data="divisionMerge({ event: @js($event), url: @js($mergeUrl), words: @js($words) })"
     @division-merge-open.window="openSheet()">
    <button type="button" @click="openSheet()"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $c }}">
            <i class="bi bi-intersect text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.merge_ui_title') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.merge_ui_row_sub') }}</span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="if (open && ! busy) open = false">
            <div class="absolute inset-0 bg-black/40" x-show="open" x-transition.opacity @click="if (! busy) open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 role="dialog" aria-modal="true" aria-label="{{ __('events::events.merge_ui_title') }}"
                 class="fixed inset-x-0 bottom-0 mx-auto w-full max-w-2xl max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

                {{-- Design Rule #8 band --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-intersect text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.merge_ui_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="limitsLine()"></p>
                        </div>
                        <button type="button" @click="if (! busy) open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 pt-4 space-y-5" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    @if($handbookUrl)
                        {{-- The officials' handbook, with this event's own chapter, as the same PDF set as the draw and entry lists. --}}
                        <button type="button" onclick="window.participantsPdf(@js($handbookUrl))"
                                class="m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $c }}"><i class="bi bi-journal-text"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('events::events.merge_handbook_pdf') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('events::events.merge_handbook_pdf_sub') }}</span>
                            </span>
                            <i class="bi bi-download text-muted-foreground"></i>
                        </button>
                    @endif

                    <p x-show="loading" class="text-sm text-muted-foreground text-center py-6">{{ __('events::events.merge_ui_checking') }}</p>

                    {{-- Suggested --}}
                    <section x-show="! loading" x-cloak>
                        <h4 class="text-[11px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2">{{ __('events::events.merge_ui_suggested') }}</h4>
                        <p x-show="data && ! (data.suggestions || []).length" class="text-sm text-muted-foreground bg-white rounded-2xl border border-gray-100 p-3.5">{{ __('events::events.merge_ui_none') }}</p>
                        <div class="space-y-2.5">
                            <template x-for="(j, i) in (data ? data.suggestions : [])" :key="'s' + i">
                                <div x-data="{ k: 's' + i }" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 space-y-2.5" :style="'border-inline-start:4px solid ' + tone(j.grade).ink">
                                    @include('components.partials.division-merge-card', ['j' => 'j'])
                                </div>
                            </template>
                        </div>
                    </section>

                    {{-- Alone --}}
                    <section x-show="data && (data.alone || []).length" x-cloak>
                        <h4 class="text-[11px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2">{{ __('events::events.merge_ui_alone') }}</h4>
                        <div class="bg-white rounded-2xl border border-gray-100 divide-y divide-gray-100">
                            <template x-for="a in (data ? data.alone : [])" :key="'a' + a.id">
                                <div class="px-3.5 py-2.5">
                                    <p class="text-sm font-bold text-foreground" x-text="a.name"></p>
                                    <p class="text-[12px] mt-0.5" style="color:#b4483c" x-text="a.why"></p>
                                </div>
                            </template>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('events::events.merge_ui_alone_hint') }}</p>
                    </section>

                    {{-- Already wide --}}
                    <section x-show="data && (data.wide || []).length" x-cloak>
                        <h4 class="text-[11px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2">{{ __('events::events.merge_ui_wide') }}</h4>
                        <div class="bg-white rounded-2xl border border-gray-100 divide-y divide-gray-100">
                            <template x-for="w in (data ? data.wide : [])" :key="'w' + w.id">
                                <div class="px-3.5 py-2.5 flex items-center justify-between gap-3 flex-wrap">
                                    <span class="text-sm font-semibold text-foreground" x-text="w.name"></span>
                                    <span class="text-[12px]" style="color:#b45309" x-text="w.light + ' – ' + w.heavy + ' kg · ' + w.pct + '%'"></span>
                                </div>
                            </template>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('events::events.merge_ui_wide_hint') }}</p>
                    </section>

                    {{-- Pick yourself --}}
                    <section x-show="! loading" x-cloak>
                        <h4 class="text-[11px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2">{{ __('events::events.merge_ui_pick') }}</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="d in (data ? data.divisions : [])" :key="'d' + d.id">
                                <button type="button" @click="pick(d.id)"
                                        class="m-press min-h-[36px] px-3 py-1.5 rounded-xl text-[12px] font-semibold text-start border transition-colors"
                                        :class="picked[d.id] ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'"
                                        x-text="d.name + ' · ' + d.count"></button>
                            </template>
                        </div>
                        <p x-show="pickedIds().length < 2" class="text-[11px] text-muted-foreground mt-2">{{ __('events::events.merge_ui_pick_hint') }}</p>

                        <div x-show="pickedIds().length >= 2" x-cloak class="mt-3">
                            <p class="text-[11px] font-semibold text-muted-foreground mb-1.5">{{ __('events::events.merge_ui_keep_name') }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="d in pickedRows()" :key="'t' + d.id">
                                    <button type="button" @click="setInto(d.id)"
                                            class="m-press min-h-[32px] px-2.5 py-1 rounded-lg text-[12px] font-semibold border"
                                            :class="(judge ? judge.into === d.id : into === d.id) ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-muted-foreground bg-white'"
                                            x-text="d.name"></button>
                                </template>
                            </div>
                        </div>

                        <p x-show="judging" class="text-[12px] text-muted-foreground mt-3">{{ __('events::events.merge_ui_checking') }}</p>
                        <template x-if="judge && pickedIds().length >= 2">
                            <div class="mt-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 space-y-2.5" :style="'border-inline-start:4px solid ' + tone(judge.grade).ink">
                                <div x-data="{ k: 'pick' }" class="space-y-2.5">
                                    @include('components.partials.division-merge-card', ['j' => 'judge'])
                                </div>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </div>
    </template>
</div>

@if($handbookUrl)
    @include('events::partials.pdf-download-script')
@endif

@once
@push('scripts')
<script>
/*
 * The division-merge component behaviour. Registered (not inline) because the sheet is
 * teleported, and on both paths because the mobile shell may swap this markup
 * in after Alpine has started.
 */
(function () {
    const register = (Alpine) => Alpine.data('divisionMerge', (cfg) => ({
        event: cfg.event, url: cfg.url, words: cfg.words || {},
        open: false, loading: false, data: null,
        picked: {}, into: null, judge: null, judging: false, seq: 0,
        consent: {}, busy: '',

        openSheet() { this.open = true; this.picked = {}; this.into = null; this.judge = null; this.consent = {}; this.load(); },

        tone(g) {
            return ({ green: { ink: '#2f7a4f', bg: '#e8f5ec', label: this.words.safe },
                      amber: { ink: '#b45309', bg: '#fff6e6', label: this.words.consent } })[g]
                || { ink: '#b4483c', bg: '#fcefed', label: this.words.unsafe };
        },
        dot(level) { return ({ green: '#2f7a4f', amber: '#d97706', red: '#c0392b' })[level] || '#97a1b4'; },

        limitsLine() {
            const l = this.data && this.data.limits;
            if (! l) return '';
            return this.words.limits.replace(':below', l.below).replace(':consent', l.consent_gap).replace(':max', l.max_gap).replace(':months', l.max_months);
        },

        async load() {
            this.loading = true;
            const d = await this.request('GET', this.url, null);
            this.loading = false;
            if (d) this.data = d;
        },

        pickedIds() { return Object.keys(this.picked).map(Number); },
        pickedRows() { return ((this.data && this.data.divisions) || []).filter((d) => this.picked[d.id]); },

        pick(id) {
            if (this.picked[id]) delete this.picked[id]; else this.picked[id] = true;
            this.picked = { ...this.picked };
            if (! this.picked[this.into]) this.into = null;
            this.judge = null;
            if (this.pickedIds().length >= 2) this.check();
        },
        setInto(id) { this.into = id; this.judge = null; this.check(); },

        async check() {
            const seq = ++this.seq;
            this.judging = true;
            const d = await this.request('POST', this.url + '/check', { ids: this.pickedIds(), into: this.into });
            if (seq !== this.seq) return;
            this.judging = false;
            this.judge = d ? d.judgement : null;
        },

        canMerge(j, k) { return j && j.grade !== 'red' && (j.grade !== 'amber' || !! this.consent[k]) && ! this.busy; },

        async merge(j, k) {
            if (! this.canMerge(j, k)) return;
            this.busy = k;
            const d = await this.request('POST', this.url, { ids: [j.into].concat(j.from || []), into: j.into, consent: !! this.consent[k] });
            this.busy = '';
            if (! d) return;
            window.showToast('success', d.message);
            window.dispatchEvent(new CustomEvent('division-merge-done', { detail: { event: this.event, into: d.into, removed: d.removed || [] } }));
            this.picked = {}; this.into = null; this.judge = null; this.consent = {};
            this.load();
        },

        async request(method, url, body) {
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: body === null ? undefined : JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                if (! res.ok || data.success === false) {
                    window.showToast('error', data.message || this.words.failed);
                    return null;
                }
                return data;
            } catch (e) {
                window.showToast('error', this.words.failed);
                return null;
            }
        },
    }));

    if (window.Alpine) register(window.Alpine);
    else document.addEventListener('alpine:init', () => register(window.Alpine));
})();
</script>
@endpush
@endonce
@endif
