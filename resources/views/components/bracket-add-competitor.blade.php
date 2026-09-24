@props([
    /* The event, as a uuid — every address this window builds names it. */
    'event',
    /* The division it opens on. The board keeps this in step as the reader
       moves between divisions; this is only the starting value. */
    'division' => null,
    /* A stable prefix, so two windows on one page cannot collide over an id. */
    'id' => 'bkadd',
])

@php
    /* ⚠️ Reaches a DOM id and a JS string. Refused rather than escaped. */
    $wid = preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', (string) $id) ? $id : 'bkadd';
@endphp

{{--
    ADD A COMPETITOR TO A DRAW THAT IS ALREADY RUNNING. `x-bracket-add-competitor`

    The draft's "Add fighter" window, built for real entrants: a draggable
    panel with a navy title bar and two tabs — somebody already entered in this
    championship, or somebody who has just walked up to the desk.

    ── What each tab actually does ───────────────────────────────────────────

    · ENTERED COMPETITOR — searches this event's own entrants who are NOT yet
      on this division's board (me.events.bracket.candidates) and admits the
      one chosen (me.events.bracket.admit). The SERVER decides where they
      stand, and that is the whole point: App\Events\Support\Tournament\
      LateEntry fills an open bye first with no extra bout, and only if there
      is none grows the ladder a round at the front so the newcomer plays in.
      A bout that was fought is never touched. The draft's "Auto-place" is this
      rule, and the rule belongs on the server where it can be enforced.

    · NEW COMPETITOR — writes a walk-in down (me.events.entries.unnamed) into
      the division the board is showing, then looks them up in the candidate
      list and places them by the same rules.

    ── Two things the draft's form could not be copied verbatim, and why ─────

    1. NO SEPARATE COUNTRY FIELD. On this platform the flag beside a competitor
       is the CLUB's country, not a passport (ClubEventRegistration::
       countryCode()). A country picker here would be a field that changes
       nothing, which is worse than no field.
    2. NO FREE-TEXT "NEW CLUB". A club is either an account on the platform or
       a row the organiser wrote down for this event, and writing one down
       needs a crest — that is the clubs page (me.events.clubs), which this
       window links to. Typing a club name here would mint a team nobody
       curated and split the start list across two spellings of it.

    ── Standalone ────────────────────────────────────────────────────────────

    It owns its markup, its Alpine state, its three requests, its drag, and its
    own stylesheet (@once). It needs only to be a child of something that is
    not transformed, and it opens on the window event `bracket-add:open`.
    Everything it writes is re-authorised server-side; being able to open this
    is not permission to do anything in it.
--}}

@once
@push('styles')
<style>
    .bkadd-win{position:absolute;z-index:110;width:680px;max-width:calc(100vw - 24px);
        background:#fff;color:#1e2c4f;border-radius:14px;overflow:hidden;
        box-shadow:0 24px 60px rgba(0,0,0,.55);display:flex;flex-direction:column;
        max-height:calc(100dvh - 40px);}
    .bkadd-bar{display:flex;align-items:center;gap:10px;padding:10px 14px;flex:0 0 auto;
        background:#1e2c4f;color:#fff;cursor:move;user-select:none;}
    .bkadd-bar-t{flex:1;font-size:.72rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;}
    .bkadd-x{width:24px;height:24px;flex:none;border:none;border-radius:6px;cursor:pointer;
        background:transparent;color:#d8b25f;font-size:.85rem;font-weight:700;line-height:1;padding:0;}
    .bkadd-body{padding:16px 18px;overflow-y:auto;flex:1 1 auto;}

    .bkadd-tabs{display:flex;border:1px solid #d5dae3;border-radius:8px;overflow:hidden;
        width:fit-content;margin-bottom:14px;}
    .bkadd-tab{padding:8px 16px;border:none;cursor:pointer;font-family:inherit;font-size:.78rem;
        font-weight:600;background:#fff;color:#5d6880;}
    .bkadd-tab.is-on{background:#d8b25f;color:#1e2c4f;}

    .bkadd-in{width:100%;box-sizing:border-box;font-family:inherit;font-size:.82rem;
        border:1px solid #d5dae3;border-radius:8px;padding:9px 11px;color:#1e2c4f;background:#fff;}
    .bkadd-in:focus{outline:2px solid #caa14f;}
    .bkadd-label{font-size:.72rem;font-weight:500;margin-bottom:5px;}

    .bkadd-list{max-height:216px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-top:10px;}
    .bkadd-row{display:flex;align-items:center;gap:10px;border:1px solid #d5dae3;border-radius:10px;
        padding:8px 12px;background:#fff;}
    .bkadd-av{width:34px;height:34px;flex:none;border:2px solid #d5dae3;border-radius:50%;
        overflow:hidden;background:#1e2c4f;color:#fff;display:flex;align-items:center;
        justify-content:center;font-size:.6rem;font-weight:800;}
    .bkadd-av img{width:100%;height:100%;object-fit:cover;}
    .bkadd-name{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .bkadd-club{font-size:.62rem;color:#5d6880;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .bkadd-ab{width:14px;height:14px;flex:none;border-radius:50%;
        background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;font-size:.45rem;
        font-weight:700;display:grid;place-items:center;line-height:1;}
    .bkadd-flag{width:18px;height:13px;flex:none;border-radius:2px;overflow:hidden;box-shadow:0 0 0 1px #d5dae3;}
    .bkadd-flag .fi{display:block;width:100%;height:100%;background-size:cover;background-position:50%;}
    .bkadd-where{flex:none;font-size:.62rem;color:#97a1b4;}

    .bkadd-go{flex:none;padding:7px 12px;border:none;border-radius:7px;cursor:pointer;
        font-family:inherit;font-size:.72rem;font-weight:600;
        background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;}
    .bkadd-go[disabled]{opacity:.5;cursor:default;}
    .bkadd-submit{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-family:inherit;
        font-size:.82rem;font-weight:600;letter-spacing:.03em;
        background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;}
    .bkadd-submit[disabled]{opacity:.5;cursor:default;}
    .bkadd-note{margin-top:10px;font-size:.7rem;color:#5d6880;line-height:1.5;}
    .bkadd-empty{font-size:.78rem;color:#97a1b4;padding:8px 2px;}
    .bkadd-fields{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}

    .bkadd-trigger{display:flex;align-items:center;gap:8px;justify-content:space-between;
        text-align:start;cursor:pointer;}
    .bkadd-panel{position:absolute;top:calc(100% + 4px);inset-inline:0;max-height:224px;
        overflow-y:auto;background:#fff;border:1px solid #d5dae3;border-radius:10px;
        box-shadow:0 12px 28px rgba(11,19,43,.18);z-index:30;padding:2px;}
    .bkadd-opt{display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;cursor:pointer;
        font-size:.78rem;background:transparent;border:none;border-radius:8px;text-align:start;
        font-family:inherit;}
    .bkadd-opt:hover{background:#eef1f6;}

    /* ⚠️ On a phone the draft's floating window becomes a BOTTOM SHEET.
       A 680px panel dragged around by its title bar is a desk instrument; a
       phone has no room for it and no comfortable way to move it. Scrollable
       body, safe-area footer, full width — CLAUDE.md → Mobile Forms Must Be
       Mobile-Friendly, which is not optional. */
    @media (max-width: 639px){
        .bkadd-win{position:fixed;inset-inline:0;bottom:0;top:auto;left:0!important;right:0;
            width:auto;max-width:none;border-radius:18px 18px 0 0;max-height:92dvh;}
        .bkadd-bar{cursor:default;}
        .bkadd-body{padding-bottom:calc(0.75rem + env(safe-area-inset-bottom));}
        .bkadd-fields > *{flex:1 1 100%!important;width:auto!important;}
    }
</style>
@endpush
@endonce

<div x-data="{
        open: false, tab: 'known', busy: false,
        division: @js($division ? (int) $division : null),
        q: '', rows: [], loading: false, seq: 0,
        name: '', club: '', clubs: [], clubsLoaded: false, clubOpen: false,
        get chosenClub() { return this.clubs.find(c => c.uuid === this.club) || null; },
        x: 120, y: 96,

        /* The board tells everybody which division is on screen; this window
           acts on THAT one, because it is the draw the reader is looking at. */
        init() {
            const sync = (e) => {
                const id = e.detail && e.detail.division;
                if (id === undefined || id === null) return;
                if (id === this.division) return;
                this.division = id;
                if (this.open) this.search();
            };
            document.addEventListener('bracket:loaded', sync);
            document.addEventListener('bracket:state', sync);
        },

        show() {
            if (!this.division) { window.BracketBoard?.notice(@js(__('events::events.bracket_add_no_division'))); return; }
            this.open = true;
            this.search();
            this.loadClubs();
        },

        /* ── Who is entered but not on this board ─────────────────────────
           Debounced, and answers are dropped if a later keystroke has already
           been sent — a slow reply must never overwrite a newer list. */
        search() {
            clearTimeout(this._t);
            this._t = setTimeout(() => this.fetchRows(), 220);
        },
        async fetchRows() {
            if (!this.division) return;
            const mine = ++this.seq;
            this.loading = true;
            try {
                const url = new URL(@js(route('me.events.bracket.candidates', $event)), window.location.origin);
                url.searchParams.set('category_id', this.division);
                if (this.q.trim()) url.searchParams.set('q', this.q.trim());
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                if (mine !== this.seq) return;
                this.rows = (res.ok && data.success) ? (data.candidates || []) : [];
            } catch (e) {
                if (mine === this.seq) this.rows = [];
            } finally {
                if (mine === this.seq) this.loading = false;
            }
        },

        async loadClubs() {
            if (this.clubsLoaded) return;
            this.clubsLoaded = true;
            try {
                const res = await fetch(@js(route('me.events.clubs.list', $event)), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const data = await res.json().catch(() => ({}));
                this.clubs = (res.ok && data.success) ? (data.clubs || []) : [];
            } catch (e) { this.clubs = []; }
        },

        /* Place an entrant. The board owns the request, because the board owns
           the redraw and the notice that explains what the rules just did. */
        async place(id) {
            if (this.busy) return;
            this.busy = true;
            const ok = await window.BracketBoard?.admit(id);
            this.busy = false;
            if (ok) { this.rows = this.rows.filter(r => r.id !== id); }
        },

        /* Write a walk-in down, then place them by the same rules. */
        async create() {
            const name = this.name.trim();
            if (!name || this.busy || !this.division) return;
            this.busy = true;
            try {
                const res = await fetch(@js(route('me.events.entries.unnamed', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        full_name: name,
                        category_id: this.division,
                        ...(this.club ? { club: this.club } : {}),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    window.BracketBoard?.notice(data.message || @js(__('events::events.bracket_move_failed')));
                    return;
                }
                window.BracketBoard?.notice(data.message);
                this.name = ''; this.club = '';
                this.tab = 'known'; this.q = name;
                await this.fetchRows();
                // Exactly one match is unambiguous; anything else is the desk's
                // call, and the row is waiting for them with a Place button.
                const hit = this.rows.filter(r => (r.name || '').trim() === name);
                if (hit.length === 1) await this.place(hit[0].id);
            } catch (e) {
                window.BracketBoard?.notice(@js(__('events::events.bracket_move_failed')));
            } finally {
                this.busy = false;
            }
        },

        /* The draft's window is dragged by its title bar. Pointer events, so a
           pen and a mouse behave the same, and capture so a fast drag that
           leaves the bar does not drop it. */
        grab(e) {
            if (e.target.closest('button')) return;
            if (window.matchMedia('(max-width: 639px)').matches) return;
            e.preventDefault();
            const sx = e.clientX, sy = e.clientY, ox = this.x, oy = this.y;
            const move = (ev) => { this.x = ox + (ev.clientX - sx); this.y = Math.max(0, oy + (ev.clientY - sy)); };
            const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        },

        flagUrl(cc) {
            const c = String(cc || '').toLowerCase().replace(/[^a-z]/g, '').slice(0, 2);
            return c.length === 2 ? c : '';
        },
        initials(n) {
            return String(n || '').trim().split(/\s+/).slice(0, 2).map(p => p[0] || '').join('').toUpperCase();
        },
        /* The same two-letter club mark the board draws, and the same one
           App\Events\Support\BracketView::mark() produces server-side. */
        mark(n) { return this.initials(n); },
     }"
     @bracket-add:open.window="show()"
     @keydown.escape.window="open = false">

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="bkadd-win"
         :style="`left:${x}px; top:${y}px;`"
         role="dialog" aria-modal="false"
         :aria-label="@js(__('events::events.bracket_add_fighter'))">

        <div class="bkadd-bar" @pointerdown="grab($event)">
            <span class="bkadd-bar-t">{{ __('events::events.bracket_add_fighter') }}</span>
            <button type="button" class="bkadd-x" @click="open = false"
                    aria-label="{{ __('shared.close') }}">&#10005;</button>
        </div>

        <div class="bkadd-body">
            <div class="bkadd-tabs">
                <button type="button" class="bkadd-tab" :class="tab === 'known' && 'is-on'"
                        @click="tab = 'known'">{{ __('events::events.bracket_add_known') }}</button>
                <button type="button" class="bkadd-tab" :class="tab === 'new' && 'is-on'"
                        @click="tab = 'new'; loadClubs()">{{ __('events::events.bracket_add_new') }}</button>
            </div>

            {{-- ===== Somebody already entered in this championship ===== --}}
            <div x-show="tab === 'known'">
                <input type="text" class="bkadd-in" x-model="q" @input="search()"
                       placeholder="{{ __('events::events.bracket_add_search') }}"
                       aria-label="{{ __('events::events.bracket_add_search') }}">

                <div class="bkadd-list">
                    <template x-for="r in rows" :key="r.id">
                        <div class="bkadd-row">
                            <span class="bkadd-av">
                                <template x-if="r.photo"><img :src="r.photo" alt=""></template>
                                <template x-if="! r.photo"><span x-text="initials(r.name)"></span></template>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="flex items-center gap-1.5">
                                    <span class="bkadd-name" x-text="r.name"></span>
                                    <template x-if="flagUrl(r.country)">
                                        <span class="bkadd-flag"><span class="fi" :class="'fi-' + flagUrl(r.country)"></span></span>
                                    </template>
                                </span>
                                <span class="flex items-center gap-1.5 mt-0.5" x-show="r.club">
                                    <span class="bkadd-ab" x-text="r.club_ab"></span>
                                    <span class="bkadd-club" x-text="r.club"></span>
                                </span>
                            </span>
                            {{-- Where they stand today. A competitor sitting in
                                 no division at all is the case worth seeing. --}}
                            <span class="bkadd-where" x-text="r.division || ''"></span>
                            <button type="button" class="bkadd-go" :disabled="busy"
                                    @click="place(r.id)">{{ __('events::events.bracket_add_place') }}</button>
                        </div>
                    </template>

                    <div class="bkadd-empty" x-show="! loading && ! rows.length">
                        {{ __('events::events.bracket_add_none') }}
                    </div>
                </div>

                <p class="bkadd-note">{{ __('events::events.bracket_add_rules') }}</p>
            </div>

            {{-- ===== Somebody who has just walked up to the desk ===== --}}
            <div x-show="tab === 'new'" x-cloak>
                <div class="bkadd-fields">
                    <div style="flex:2;min-width:180px">
                        <div class="bkadd-label">{{ __('events::events.bracket_add_name') }}</div>
                        <input type="text" class="bkadd-in" x-model="name"
                               @keydown.enter.prevent="create()"
                               aria-label="{{ __('events::events.bracket_add_name') }}">
                    </div>

                    {{-- The event's own clubs — the ones with an account and the
                         ones the organiser wrote down. Not a free-text field:
                         see the note at the top of this file.

                         The draft draws its own dropdown here (a trigger, a
                         rounded panel, the gold club mark on every row) and so
                         does this: the list arrives over the wire, which is
                         exactly the case <x-fields.select> tells callers not to
                         use it for, and a native <select> is banned in styled
                         UI (Design Rule #4). One control satisfies both. --}}
                    <div style="flex:2;min-width:180px;position:relative"
                         @click.outside="clubOpen = false">
                        <div class="bkadd-label">{{ __('events::events.bracket_add_club') }}</div>

                        <button type="button" class="bkadd-in bkadd-trigger"
                                @click="clubOpen = ! clubOpen" :aria-expanded="clubOpen"
                                aria-haspopup="listbox">
                            <template x-if="chosenClub">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="bkadd-ab" x-text="mark(chosenClub.name)"></span>
                                    <span class="bkadd-club" style="color:#1e2c4f;font-size:.82rem"
                                          x-text="chosenClub.name"></span>
                                </span>
                            </template>
                            <template x-if="! chosenClub">
                                <span style="color:#97a1b4">{{ __('events::events.bracket_add_club') }}</span>
                            </template>
                            <i class="bi bi-chevron-down" style="color:#97a1b4;font-size:.6rem"
                               :class="clubOpen && 'rotate-180'"></i>
                        </button>

                        <div x-show="clubOpen" x-cloak x-transition.opacity.duration.120ms
                             role="listbox" class="bkadd-panel">
                            <button type="button" class="bkadd-opt" role="option"
                                    :aria-selected="! club"
                                    @click="club = ''; clubOpen = false">
                                <span style="color:#97a1b4">&mdash;</span>
                            </button>
                            <template x-for="c in clubs" :key="c.uuid">
                                <button type="button" class="bkadd-opt" role="option"
                                        :aria-selected="club === c.uuid"
                                        :style="club === c.uuid ? 'background:#fdf6e6' : ''"
                                        @click="club = c.uuid; clubOpen = false">
                                    <span class="bkadd-ab" x-text="mark(c.name)"></span>
                                    <span class="bkadd-club" style="color:#1e2c4f" x-text="c.name"></span>
                                </button>
                            </template>
                            <div class="bkadd-empty" x-show="! clubs.length" style="padding:10px 12px">
                                <a href="{{ route('me.events.clubs', $event) }}"
                                   style="color:#8a6d20;font-weight:600">{{ __('events::events.bracket_add_club') }}</a>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="bkadd-submit"
                            :disabled="busy || ! name.trim()"
                            @click="create()">{{ __('events::events.bracket_add_submit') }}</button>
                </div>

                <p class="bkadd-note">{{ __('events::events.bracket_add_rules') }}</p>
            </div>
        </div>
    </div>
</div>
