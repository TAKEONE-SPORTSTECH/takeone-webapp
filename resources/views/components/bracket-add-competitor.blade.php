@props([
    /* The event, as a uuid — every address this window builds names it. */
    'event',
    /* The division it opens on. The board keeps this in step as the reader
       moves between divisions; this is only the starting value. */
    'division' => null,
    /* A stable prefix, so two windows on one page cannot collide over an id. */
    'id' => 'bkadd',
    /* The three addresses it calls. Null is the member routes, as before; the
       scoring tablet (App\Events\Support\TabletDraw) passes its own PIN-gated
       door. Every one re-authorises server-side either way. */
    'candidatesUrl' => null,
    'clubsUrl' => null,
    'unnamedUrl' => null,
    /* The link to the clubs page — a signed-in organiser page, so a device
       host turns it off. */
    'clubsLink' => true,
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
    /* A modal in the middle of the screen, over a dimmed backdrop (owner,
       2026-10-07); a bottom sheet on a phone, below. */
    .bkadd-scrim{position:fixed;inset:0;z-index:109;background:rgba(9,14,30,.55);}
    .bkadd-win{position:fixed;inset:0;margin:auto;height:fit-content;z-index:110;width:720px;max-width:calc(100vw - 24px);max-height:calc(100vh - 40px);
        background:#fff;color:#1e2c4f;border-radius:14px;overflow:hidden;
        box-shadow:0 24px 60px rgba(0,0,0,.55);display:flex;flex-direction:column;
        max-height:calc(100dvh - 40px);}
    .bkadd-bar{display:flex;align-items:center;gap:10px;padding:10px 14px;flex:0 0 auto;
        background:#1e2c4f;color:#fff;user-select:none;}
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
    /* The walk-in form: two columns, a wide field across both. */
    .bkadd-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 14px;align-items:start;}
    .bkadd-span2{grid-column:1 / -1;}
    .bkadd-seg{display:inline-flex;border:1px solid #d5dae3;border-radius:999px;overflow:hidden;}
    .bkadd-seg button{font:inherit;font-size:.68rem;font-weight:700;padding:3px 10px;background:#fff;color:#5d6880;border:0;cursor:pointer;}
    .bkadd-seg button.is-on{background:#1e2c4f;color:#fff;}
    .bkadd-sec{display:flex;align-items:center;gap:8px;margin:0 0 10px;font-size:.72rem;font-weight:700;
        letter-spacing:.14em;text-transform:uppercase;color:#8a6d20;}
    .bkadd-sec i{font-size:.8rem;}
    .bkadd-pic{width:90px;height:120px;flex:0 0 auto;border:1.5px dashed #c9cfdb;border-radius:14px;background:#f7f8fb;
        display:grid;place-items:center;color:#8a93a8;font-size:1.3rem;cursor:pointer;overflow:hidden;padding:0;
        transition:border-color .15s,background .15s;}
    .bkadd-pic:hover{border-color:#a78bfa;background:#f5f3ff;}
    .bkadd-pic img{width:100%;height:100%;object-fit:cover;}
    .bkadd-pic-logo{width:46px;height:46px;border-radius:12px;font-size:1rem;}
    .bkadd-pic-logo img{object-fit:contain;}

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
        .bkadd-win{position:fixed;inset-inline:0;bottom:0;top:auto;left:0!important;right:0;margin:0;
            width:auto;max-width:none;border-radius:18px 18px 0 0;max-height:92dvh;}
        .bkadd-bar{cursor:default;}
        .bkadd-body{padding-bottom:calc(0.75rem + env(safe-area-inset-bottom));}
        .bkadd-fields > *{flex:1 1 100%!important;width:auto!important;}
        .bkadd-grid{grid-template-columns:1fr;}
    }
</style>
@endpush
@endonce

@php
    // The cropper names JavaScript functions after its id, so it must be a
    // plain identifier — the draw stage's id ("public-draw…") is not.
    $cropId = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $id);
@endphp
<div x-data="{
        open: false, tab: 'known', busy: false,
        division: @js($division ? (int) $division : null),
        q: '', rows: [], loading: false, seq: 0,
        name: '', club: '', clubs: [], clubsLoaded: false, clubOpen: false,
        /* What the desk knows about a walk-in — all optional but the name. */
        gender: '', birthMode: 'dob', dob: '', age: '', weight: '', nationality: '',
        newClub: false, clubName: '', clubLogo: '', photo: '', cropFor: null,
        get chosenClub() { return this.clubs.find(c => c.uuid === this.club) || null; },
        /* The shared dropdown's options: none, the event's clubs, then a new one. */
        get clubOptions() {
            return [{ value: '', label: '—' }]
                .concat(this.clubs.map(c => ({ value: c.uuid, label: c.name })))
                .concat([{ value: '__new', label: @js('＋ '.__('events::events.bracket_add_new_club')) }]);
        },
        x: 120, y: 96,

        /* The board tells everybody which division is on screen; this window
           acts on THAT one, because it is the draw the reader is looking at. */
        init() {
            /* The cropper hands its result back as a data URL; which of the two
               it was for is the widget's id. */
            const key = '__bkaddCrop_{{ $id }}';
            if (window[key]) document.removeEventListener('cropperCropped', window[key]);
            window[key] = (e) => {
                const d = e.detail || {};
                if (d.id === @js($cropId.'Photo')) this.photo = d.base64 || '';
                if (d.id === @js($cropId.'Logo')) this.clubLogo = d.base64 || '';
            };
            document.addEventListener('cropperCropped', window[key]);

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
                const url = new URL(@js($candidatesUrl ?: route('me.events.bracket.candidates', $event)), window.location.origin);
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
                const res = await fetch(@js($clubsUrl ?: route('me.events.clubs.list', $event)), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
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

        /* Choose a picture, then hand it to the shared cropper (its own
           bottom sheet) for the face or the logo. */
        pick(which) { this.cropFor = which; this.$refs.pickFile && this.$refs.pickFile.click(); },
        picked(ev) {
            const f = ev.target.files && ev.target.files[0];
            ev.target.value = '';
            if (! f || ! this.cropFor) return;
            const input = document.getElementById('input_' + (this.cropFor === 'logo' ? @js($cropId.'Logo') : @js($cropId.'Photo')));
            if (! input) return;
            const dt = new DataTransfer(); dt.items.add(f);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
        resetNew() {
            this.name = ''; this.club = ''; this.gender = ''; this.birthMode = 'dob'; this.dob = ''; this.age = '';
            this.weight = ''; this.nationality = ''; this.newClub = false; this.clubName = ''; this.clubLogo = ''; this.photo = '';
        },
        /* Write a walk-in down, then place them by the same rules. */
        async create() {
            const name = this.name.trim();
            if (!name || this.busy || !this.division) return;
            this.busy = true;
            try {
                const res = await fetch(@js($unnamedUrl ?: route('me.events.entries.unnamed', $event)), {
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
                        ...(this.newClub && this.clubName.trim() ? { club_name: this.clubName.trim() } : {}),
                        ...(this.newClub && this.clubName.trim() && this.clubLogo ? { club_logo: this.clubLogo } : {}),
                        ...(this.gender ? { gender: this.gender } : {}),
                        ...(this.birthMode === 'dob' && this.dob ? { birthdate: this.dob } : {}),
                        ...(this.birthMode === 'age' && this.age ? { age: parseInt(this.age, 10) } : {}),
                        ...(this.weight ? { weight: parseFloat(String(this.weight).replace(',', '.')) } : {}),
                        ...(this.nationality ? { nationality: this.nationality } : {}),
                        ...(this.photo ? { photo: this.photo } : {}),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    window.BracketBoard?.notice(data.message || @js(__('events::events.bracket_move_failed')));
                    return;
                }
                window.BracketBoard?.notice(data.warning || data.message);
                if (this.newClub) { this.clubsLoaded = false; this.loadClubs(); }
                this.resetNew();
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

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="bkadd-scrim" @click="open = false"></div>
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="bkadd-win"
         role="dialog" aria-modal="true"
         :aria-label="@js(__('events::events.bracket_add_fighter'))">

        <div class="bkadd-bar">
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

            {{-- ===== Somebody who has just walked up to the desk =====
                 Name, then what the desk happens to know (owner, 2026-10-07):
                 gender, date of birth OR age, weight, club (the event's own, or
                 a new one with its logo), nationality and a photograph. Only
                 the name is required — "Who Fills The Form Decides What It
                 Demands" — and every control is the shared one, or drawn in its
                 exact style. Two sections: the athlete, then who they compete for. --}}
            @php
                $fld = 'w-full px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent';
                $lbl = 'block text-sm font-medium text-gray-700 mb-1';
            @endphp
            <div x-show="tab === 'new'" x-cloak class="space-y-5">

                <section>
                    <h4 class="bkadd-sec"><i class="bi bi-person-fill"></i>{{ __('events::events.bracket_add_sec_athlete') }}</h4>
                    <div class="flex gap-4 items-start">
                        {{-- The face: portrait 3:4, the platform's one shape for a person. Optional. --}}
                        <div class="flex-none text-center">
                            <button type="button" class="bkadd-pic" @click="pick('photo')"
                                    :aria-label="@js(__('events::events.bracket_add_photo'))">
                                <template x-if="photo"><img :src="photo" alt=""></template>
                                <template x-if="! photo"><span class="flex flex-col items-center gap-1"><i class="bi bi-camera"></i><span class="text-[10px] font-semibold">{{ __('events::events.bracket_add_photo') }}</span></span></template>
                            </button>
                            <button type="button" x-show="photo" x-cloak class="mt-1 text-[11px] font-semibold text-gray-500 hover:text-red-600"
                                    @click="photo = ''">{{ __('events::events.bracket_add_remove') }}</button>
                        </div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <label class="{{ $lbl }}" for="{{ $id }}Name">{{ __('events::events.bracket_add_name') }} <span class="text-red-500">*</span></label>
                                <input id="{{ $id }}Name" type="text" class="{{ $fld }}" x-model="name" maxlength="120"
                                       @keydown.enter.prevent="create()">
                            </div>
                            <div>
                                <span class="{{ $lbl }}">{{ __('events::events.bracket_add_gender') }}</span>
                                <x-fields.gender-toggle model="gender" />
                            </div>
                        </div>
                    </div>

                    {{-- Date of birth OR age. An age is kept as an age — never
                         turned into a birthdate. --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="block text-sm font-medium text-gray-700">{{ __('events::events.bracket_add_born') }}</span>
                            <span class="inline-flex p-0.5 rounded-full bg-gray-100">
                                <button type="button" class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
                                        :class="birthMode === 'dob' ? 'bg-white shadow-sm text-foreground' : 'text-gray-500'" @click="birthMode = 'dob'">{{ __('events::events.bracket_add_dob') }}</button>
                                <button type="button" class="px-3 py-1 rounded-full text-xs font-semibold transition-colors"
                                        :class="birthMode === 'age' ? 'bg-white shadow-sm text-foreground' : 'text-gray-500'" @click="birthMode = 'age'">{{ __('events::events.bracket_add_age') }}</button>
                            </span>
                        </div>
                        <div x-show="birthMode === 'dob'">
                            <x-fields.date-of-birth model="dob" name="{{ $id }}_dob" id="{{ $id }}Dob" label="" :minAge="3" :maxAge="99" />
                        </div>
                        <div x-show="birthMode === 'age'" x-cloak class="flex items-center gap-2">
                            <input type="number" class="{{ $fld }}" style="max-width:120px" x-model="age" min="3" max="99" inputmode="numeric"
                                   aria-label="{{ __('events::events.bracket_add_age') }}">
                            <span class="text-sm text-gray-500">{{ __('events::events.bracket_add_years') }}</span>
                        </div>
                    </div>
                </section>

                <section>
                    <h4 class="bkadd-sec"><i class="bi bi-shield-fill"></i>{{ __('events::events.bracket_add_sec_competing') }}</h4>

                    {{-- The club: the shared dropdown, fed the event's own clubs as
                         they arrive, with "New club…" at the end. --}}
                    <div x-show="! newClub">
                        <span class="{{ $lbl }}">{{ __('events::events.bracket_add_club') }}</span>
                        <x-fields.select model="club" options-expr="clubOptions"
                            placeholder="{{ __('events::events.bracket_add_club') }}"
                            change="if (club === '__new') { club = ''; newClub = true; $nextTick(() => $refs.clubName && $refs.clubName.focus()); }" />
                    </div>

                    {{-- A new club: its logo (optional) and its name. --}}
                    <div x-show="newClub" x-cloak>
                        <div class="flex items-center justify-between mb-1">
                            <span class="block text-sm font-medium text-gray-700">{{ __('events::events.bracket_add_new_club_title') }}</span>
                            <button type="button" class="text-xs font-semibold text-primary hover:underline"
                                    @click="newClub = false; clubName = ''; clubLogo = ''">{{ __('events::events.bracket_add_pick_club') }}</button>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="bkadd-pic bkadd-pic-logo" @click="pick('logo')"
                                    :aria-label="@js(__('events::events.bracket_add_logo'))" :title="@js(__('events::events.bracket_add_logo'))">
                                <template x-if="clubLogo"><img :src="clubLogo" alt=""></template>
                                <template x-if="! clubLogo"><span class="flex flex-col items-center"><i class="bi bi-image"></i><span class="text-[9px] font-semibold">{{ __('events::events.bracket_add_logo_short') }}</span></span></template>
                            </button>
                            <input type="text" class="{{ $fld }}" x-ref="clubName" x-model="clubName" maxlength="120"
                                   placeholder="{{ __('events::events.bracket_add_club_name') }}" aria-label="{{ __('events::events.bracket_add_club_name') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                        <div>
                            <span class="{{ $lbl }}">{{ __('events::events.bracket_add_nationality') }}</span>
                            <x-fields.country model="nationality" :inline="true" name="{{ $id }}_nationality" id="{{ $id }}Nat" label="" />
                        </div>
                        <div>
                            <label class="{{ $lbl }}" for="{{ $id }}Weight">{{ __('events::events.bracket_add_weight') }}</label>
                            <div class="relative">
                                <input id="{{ $id }}Weight" type="number" class="{{ $fld }}" style="padding-right:2.5rem" x-model="weight" min="10" max="300" step="0.1" inputmode="decimal">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 pointer-events-none">kg</span>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between gap-3 pt-3 border-t border-gray-100">
                    <p class="bkadd-note" style="margin:0">{{ __('events::events.bracket_add_rules') }}</p>
                    <button type="button" class="bkadd-submit" style="flex:0 0 auto"
                            :disabled="busy || ! name.trim()"
                            @click="create()">{{ __('events::events.bracket_add_submit') }}</button>
                </div>

                {{-- The project's one cropper, never uploading on its own: the
                     crop comes back as a data URL (cropperCropped) and rides the
                     submit, where the server checks the real bytes. The face is
                     portrait 3:4; the club logo square, round or wide. --}}
                <input type="file" x-ref="pickFile" accept="image/*" class="hidden" style="display:none" @change="picked($event)">
                <div class="hidden" style="display:none">
                    <x-takeone-cropper id="{{ $cropId }}Photo" mode="form" :inline="true"
                        :width="600" :height="800" shape="rectangle" :canvasHeight="360"
                        folder="temp" filename="late-entry" inputName="{{ $id }}_photo"
                        sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
                        :showControls="false" :showCancel="false" saveText="{{ __('shared.save') }}" />
                    <x-takeone-cropper id="{{ $cropId }}Logo" mode="form" :inline="true"
                        :width="640" :height="640" shape="rectangle" :canvasHeight="360" shapes="square,circle,landscape"
                        folder="temp" filename="late-entry-logo" inputName="{{ $id }}_logo"
                        sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
                        :showControls="false" :showCancel="false" saveText="{{ __('shared.save') }}" />
                </div>
            </div>
        </div>
    </div>
</div>
