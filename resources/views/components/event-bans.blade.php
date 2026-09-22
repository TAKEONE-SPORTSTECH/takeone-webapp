@props([
    'event',                 // event uuid
    'count' => 0,            // how many people are barred, for the row's chip
    'color' => '#7c3aed',
    'title' => '',
    'club' => null,          // the host club's name, for the club-wide sentence
])

{{--
    Who is barred from this event — one row of the console, opening a sheet.

    ── Why this exists ──────────────────────────────────────────────────────

    Moderation had no screen. `me.events.ban.lift` and the ban rows survived the
    verification desk's removal on 2026-09-03; the only surface that ever drew
    them was that desk's third tab, so from that day nobody — organiser included
    — could see who was barred or let anybody back in. The names went on being
    serialised into the roster's search index for a tab the page no longer
    rendered, which is disclosure without a feature (both fixed 2026-09-15).

    ── What it shows that nothing ever has ──────────────────────────────────

    The REASON. `moderateParticipant` has recorded one since the beginning and no
    screen has ever printed it, so "why is this person barred?" was unanswerable
    three weeks later — by the organiser who decided it. Who barred them and when
    are the same story.

    ── Standalone ───────────────────────────────────────────────────────────

    Own Alpine state, own fetch, own sheet, own in-place patching (No-Reload).
    Drop it into either console with no page glue; it needs only the uuid. The
    list loads when the sheet opens, never on page load, so a barred member's
    name reaches a browser only when somebody entitled to it asks.
--}}

@php
    // Organiser-supplied colour lands in a style attribute — whitelisted here,
    // exactly as <x-event-section-band> does it.
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="{
        open: false,
        loaded: false,
        loading: false,
        error: null,
        people: [],
        /* Which scopes to show. Only offered when both kinds are present —
           a filter over one thing is furniture, not a control. */
        filter: 'all',
        /* The one card currently asking to confirm. Inline rather than a
           dialog stacked over the sheet: letting somebody back in is reversible
           in two taps, and the question belongs on the card it is about. */
        asking: null,
        busy: null,
        club: @js($club),
        count: {{ (int) $count }},

        get shown() {
            return this.filter === 'all'
                ? this.people
                : this.people.filter(p => p.scope === this.filter);
        },
        get hasBoth() {
            return this.people.some(p => p.scope === 'event') && this.people.some(p => p.scope === 'club');
        },
        countOf(scope) { return this.people.filter(p => p.scope === scope).length; },

        async show() {
            this.open = true;
            if (this.loaded || this.loading) return;
            await this.load();
        },

        async load() {
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(@js(route('me.events.bans', $event)), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_bans_failed')));
                this.people = d.people || [];
                this.club = d.club || this.club;
                this.count = this.people.length;
                this.loaded = true;
            } catch (e) {
                this.error = e.message || @js(__('personal.event_bans_failed'));
            } finally { this.loading = false; }
        },

        /* The sentence the confirm step prints. A club-wide bar reaches every
           event the club runs, and liftBan() clears it wherever it reaches — so
           the card says that BEFORE the tap, not the toast afterwards. */
        blastRadius(p) {
            return p.scope === 'club'
                ? (this.club
                    ? @js(__('personal.event_bans_lift_ask_club', ['club' => ':club'])).replace(':club', this.club)
                    : @js(__('personal.event_bans_lift_ask_club_generic')))
                : @js(__('personal.event_bans_lift_ask_event'));
        },

        async lift(p) {
            if (this.busy) return;
            this.busy = p.id;
            try {
                const res = await fetch(@js(route('me.events.ban.lift', [$event, 'ID'])).replace('ID', p.id), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                this.people = this.people.filter(x => x.id !== p.id);
                this.count = this.people.length;
                this.asking = null;
                /* The filter can be left pointing at an empty half of a list
                   that still has people in it, which reads as though everybody
                   were back in. Fall back to the whole list. */
                if (this.filter !== 'all' && this.shown.length === 0) this.filter = 'all';
                window.showToast('success', d.message);
                window.dispatchEvent(new CustomEvent('event-bans-changed', {
                    detail: { event: @js($event), user: p.id, remaining: this.count },
                }));
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.busy = null; }
        },
     }"
     {{-- Another organiser barred or released somebody on their own console:
          re-read rather than guess, and only while the sheet is open. The push
          is a refresh signal because what each organiser may read differs. --}}
     @realtime:events.window="if (open && ($event.detail?.action === 'moderated' || $event.detail?.action === 'moderation')) load()">

    {{-- ── The row in the console ─────────────────────────────────────────── --}}
    <button type="button" @click="show()"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
              :class="count > 0 ? 'bg-rose-50 text-rose-600' : 'bg-muted text-muted-foreground'">
            <i class="bi text-lg" :class="count > 0 ? 'bi-slash-circle-fill' : 'bi-unlock'"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('personal.event_bans_title') }}</span>
            <span class="block text-[11px] mt-0.5 truncate"
                  :class="count > 0 ? 'text-rose-600 font-semibold' : 'text-muted-foreground'"
                  x-text="count > 0
                      ? @js(__('personal.event_bans_row_sub', ['count' => ':n'])).replace(':n', count)
                      : @js(__('personal.event_bans_row_sub_none'))"></span>
        </span>
        <span x-show="count > 0" x-cloak
              class="text-[11px] font-black tabular-nums px-2 py-0.5 rounded-full bg-rose-50 text-rose-600 flex-shrink-0"
              x-text="count"></span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children, which
         would make a fixed sheet resolve against a wrapper instead of the
         viewport and clip it. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false; asking = null"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 {{-- Full width at every breakpoint, like the rows either side
                      of it in the console: <x-event-entry-review> and the draw
                      switch both run edge to edge, and a sheet that stops short
                      of them reads as a different kind of thing. It had an
                      `sm:max-w-lg` cap, which is why it looked narrow on a
                      desktop console (reported 2026-09-16). --}}
                 class="relative w-full max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

                {{-- ── Gradient header band (Design Rule #8) ──────────────── --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-slash-circle-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_bans_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false; asking = null" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <p class="relative mt-3 text-[11.5px] text-white/85 leading-snug flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>{{ __('personal.event_bans_hint') }}
                    </p>

                    {{-- Filter chips: offered only when there are both kinds to
                         tell apart. --}}
                    <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="hasBoth" x-cloak>
                        <template x-for="chip in [
                                { key: 'all',   label: @js(__('personal.event_bans_filter_all')) },
                                { key: 'event', label: @js(__('personal.event_bans_filter_event')) },
                                { key: 'club',  label: @js(__('personal.event_bans_filter_club')) },
                            ]" :key="chip.key">
                            <button type="button" @click="filter = chip.key; asking = null"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors"
                                    :class="filter === chip.key ? 'bg-white text-gray-900' : 'bg-white/20 text-white'">
                                <span x-text="chip.label"></span>
                                <span class="tabular-nums opacity-70"
                                      x-text="chip.key === 'all' ? people.length : countOf(chip.key)"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- ── The list ───────────────────────────────────────────── --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2.5"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                    {{-- Reading --}}
                    <div x-show="loading" x-cloak class="py-10 text-center">
                        <div class="w-8 h-8 mx-auto rounded-full border-2 border-gray-200 border-t-gray-400 animate-spin"></div>
                        <p class="text-[11.5px] text-muted-foreground mt-3">{{ __('personal.event_bans_loading') }}</p>
                    </div>

                    {{-- Could not read it: say so, and offer the retry rather
                         than leaving an empty list that reads as "nobody". --}}
                    <div x-show="error" x-cloak class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-center">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl"></i>
                        <p class="text-[12px] text-rose-700 mt-1.5" x-text="error"></p>
                        <button type="button" @click="load()"
                                class="m-press mt-3 text-[11px] font-bold px-3 py-1.5 rounded-full bg-white border border-rose-200 text-rose-700">
                            <i class="bi bi-arrow-clockwise"></i> {{ __('personal.event_bans_retry') }}
                        </button>
                    </div>

                    {{-- Nobody is barred. A designed answer, not grey text: this
                         is the state an organiser WANTS to be in. --}}
                    <div x-show="loaded && ! loading && ! error && people.length === 0" x-cloak
                         class="py-10 text-center">
                        <span class="w-16 h-16 rounded-3xl bg-green-50 text-green-600 grid place-items-center mx-auto">
                            <i class="bi bi-unlock text-2xl"></i>
                        </span>
                        <p class="text-sm font-black text-foreground mt-3">{{ __('personal.event_bans_empty') }}</p>
                        <p class="text-[11.5px] text-muted-foreground mt-1 max-w-[16rem] mx-auto leading-snug">{{ __('personal.event_bans_empty_sub') }}</p>
                    </div>

                    {{-- One card per person. The left rail says how far the bar
                         reaches — amber for this event, rose for every event the
                         club runs — so two scopes that read identically in a
                         sub-line are one glance apart. --}}
                    <template x-for="p in shown" :key="p.id">
                        <div class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex">
                            <span class="w-1.5 flex-shrink-0"
                                  :class="p.scope === 'club' ? 'bg-rose-500' : 'bg-amber-400'"></span>

                            <div class="min-w-0 flex-1 p-3.5">
                                <div class="flex items-start gap-3">
                                    {{-- Portrait 3:4, and their own choice about
                                         their face is not suspended by being
                                         barred: no published picture falls back
                                         to the silhouette, silently. --}}
                                    <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted">
                                        <template x-if="p.photo">
                                            <img :src="p.photo" :alt="p.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="! p.photo && (p.gender === 'Female')">
                                            <x-gender-avatar gender="f" class="w-full h-full object-cover" sizes="36px" />
                                        </template>
                                        <template x-if="! p.photo && (p.gender !== 'Female')">
                                            <x-gender-avatar gender="m" class="w-full h-full object-cover" sizes="36px" />
                                        </template>
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-foreground truncate" x-text="p.name"></p>
                                        <p class="text-[11px] font-semibold mt-0.5 flex items-center gap-1.5"
                                           :class="p.scope === 'club' ? 'text-rose-600' : 'text-amber-600'">
                                            <i class="bi" :class="p.scope === 'club' ? 'bi-building-slash' : 'bi-calendar-x'"></i>
                                            <span x-text="p.scope === 'club'
                                                ? (club
                                                    ? @js(__('personal.event_bans_scope_club', ['club' => ':club'])).replace(':club', club)
                                                    : @js(__('personal.event_bans_scope_club_generic')))
                                                : @js(__('personal.event_bans_scope_event'))"></span>
                                        </p>

                                        {{-- Barred twice over: the sheet says so,
                                             because one tap clears both. --}}
                                        <p x-show="p.both" x-cloak
                                           class="text-[10.5px] text-muted-foreground mt-1 flex items-center gap-1">
                                            <i class="bi bi-layers-half"></i>{{ __('personal.event_bans_both') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- The reason — the whole point of the screen.
                                     Quoted, because these are somebody's own
                                     words about another member. --}}
                                <p x-show="p.reason" x-cloak
                                   class="mt-2.5 text-[12px] text-foreground/80 leading-snug italic border-s-2 border-gray-200 ps-2.5"
                                   x-text="'“' + (p.reason || '') + '”'"></p>
                                <p x-show="! p.reason" x-cloak
                                   class="mt-2.5 text-[11.5px] text-muted-foreground/70 leading-snug italic">{{ __('personal.event_bans_no_reason') }}</p>

                                <p class="mt-2 text-[10.5px] text-muted-foreground"
                                   x-text="p.by
                                       ? @js(__('personal.event_bans_by', ['name' => ':name', 'date' => ':date']))
                                           .replace(':name', p.by).replace(':date', p.at || '')
                                       : (p.at ? @js(__('personal.event_bans_on', ['date' => ':date'])).replace(':date', p.at) : '')"></p>

                                {{-- ── Let back in: two steps, on the card ──
                                     Reversible in two taps, so it asks here
                                     rather than stacking a dialog over the
                                     sheet — and the question carries how far the
                                     answer reaches. --}}
                                <div class="mt-3">
                                    <button type="button" x-show="asking !== p.id" x-cloak
                                            @click="asking = p.id"
                                            class="m-press text-[11px] font-bold px-3 py-1.5 rounded-full border border-gray-200 text-foreground hover:bg-muted inline-flex items-center gap-1.5">
                                        <i class="bi bi-arrow-counterclockwise"></i>{{ __('personal.event_bans_lift') }}
                                    </button>

                                    <div x-show="asking === p.id" x-cloak
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 -translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         class="rounded-xl bg-muted/60 p-3">
                                        <p class="text-[12px] font-bold text-foreground">{{ __('personal.event_bans_lift_ask') }}</p>
                                        <p class="text-[11px] text-muted-foreground leading-snug mt-0.5" x-text="blastRadius(p)"></p>
                                        <div class="flex items-center gap-2 mt-2.5">
                                            <button type="button" @click="lift(p)" :disabled="busy === p.id"
                                                    class="m-press text-[11px] font-bold px-3 py-1.5 rounded-full text-white disabled:opacity-60 inline-flex items-center gap-1.5"
                                                    style="background: {{ $c }};">
                                                <i class="bi" :class="busy === p.id ? 'bi-hourglass-split' : 'bi-door-open-fill'"></i>
                                                {{ __('personal.event_bans_lift_yes') }}
                                            </button>
                                            <button type="button" @click="asking = null" :disabled="busy === p.id"
                                                    class="m-press text-[11px] font-bold px-3 py-1.5 rounded-full border border-gray-200 text-muted-foreground">
                                                {{ __('personal.event_bans_keep_out') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- The filter matched nothing — distinct from "nobody is
                         barred", which has its own answer above. --}}
                    <p x-show="loaded && ! loading && people.length > 0 && shown.length === 0" x-cloak
                       class="text-[11.5px] text-muted-foreground text-center py-6">{{ __('personal.event_bans_filter_none') }}</p>
                </div>
            </div>
        </div>
    </template>
</div>
