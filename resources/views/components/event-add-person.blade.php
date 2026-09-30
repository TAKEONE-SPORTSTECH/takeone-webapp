@props([
    'event',                // event uuid
    'sport' => null,        // the event's sport key — decides the belt ladder; looked up from `event` when absent
    'fees' => [],           // $e['fees'] — what this event SELLS (Gi / No-Gi / …)
    /* The clubs this person may be entered FOR — EntryEditor::clubOptions(),
       the same list and the same two key shapes (a tenant slug, or an event
       club's uuid for a visiting team with no account) the roster's own club
       picker offers. Empty simply hides the question. */
    'clubs' => [],
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Adding somebody to the entry list by hand — the organiser's own door.

    WHY IT EXISTS. Every other way into an event is somebody else's act: an
    athlete enters themselves, a coach enters their club's squad, a stranger
    asks through the public page. The person actually running the competition
    had no way to put a name on the list at all unless they happened to
    administer the club that athlete belongs to — so the desk's answer to "he's
    standing right here, he has an account" was to type his name again and mint
    a SECOND, unclaimed person beside the account he already had.

    TWO HALVES OF ONE JOB, which is why they are two tabs of one sheet rather
    than two buttons:
      · they have a TAKEONE account → search for it and enter THAT person, so
        their profile, their club and their record follow them into the draw;
      · they have none → a name is committed immediately and a single-use link
        lets them fill in the rest (Documentation/EVENTS-PUBLIC-ENTRY.md, Door B).

    WHO SEES IT. Rendered only where the console already decided the viewer may
    manage the event — and both endpoints behind it re-check that server-side
    (PersonalEventController::entrySearch / storeEntries, EntryService::enter).
    This decides what to OFFER, never what is allowed.

    Standalone per the component contract: its own Alpine state, its own
    requests, its own sheet, its own in-place patching. Drop it into either
    console — mobile or desktop — with no page glue. Dispatches
    `event-people-added` with the entered rows so a list on the page can patch
    itself without a reload.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';

    /* One id for the photo flow, unique per event so two consoles on one page
       could never answer each other's events. Letters and digits only — it is
       also the cropper's element id. */
    $photoId = 'addPerson'.substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $event), 0, 12);

    /*
        What this event SELLS, straight from the page's own payload
        ($e['fees'], built by EventFee) — the same list, in the same order, at
        the same prices the public enrolment form offers. One price list, read
        once (Shared Stays Shared).

        Empty for an event that sells nothing, and every block below is
        guarded, so such an event's sheet is exactly what it was.
    */
    $feeOptions = $fees['options'] ?? [];
    $feeBase = (float) ($fees['base'] ?? 0);
    $feeCurrency = $fees['currency'] ?? '';
    $feeLate = (bool) ($fees['late_active'] ?? false);
    $feeLateAmount = (float) ($fees['late_amount'] ?? 0);
    // Radio or checkbox, straight from the same payload — one answer for every
    // door into this event (EventFee::selection).
    $feeSingle = ($fees['selection'] ?? 'multiple') === 'single';
@endphp

<div x-data="{
        open: false,
        /* Opens on the WALK-IN. The desk's common case is somebody standing
           there with no account — an athlete who has one is usually entered by
           themselves or by their coach long before the morning. */
        tab: 'new',

        /* ---------------- which division ----------------
           Nobody's to choose. A division is decided by what it is FOR — the
           weight, the age, the rank and the part of the event somebody
           entered — and the package's own gate works that out from the entry
           (EntryService / DivisionRange). A picker here offered the desk a
           twelfth answer to a question the filters had already answered, and
           any answer it gave that disagreed with them was wrong.

           So this sheet commits the PERSON and what they bought, and where
           they are drawn follows from that. Moving somebody deliberately is
           still possible — on the division's own sheet, where the range that
           makes it an exception is visible. */

        /* ---------------- what they are entering ----------------
           The event's priced options, exactly as the public enrolment form
           asks for them — Gi, No-Gi, Gi + No-Gi. One choice applied to
           everybody being added here, because this sheet is for the handful of
           people an organiser adds at the desk.

           ⚠️ Only KEYS ever leave the browser. Every total drawn below is for
           the organiser to read: EventFee::quote() re-prices each entry from
           the event's own rows server-side, so editing these numbers in a
           console changes what one screen says and nothing anybody is charged. */
        feeOptions: @js($feeOptions),
        feeBase: @js($feeBase),
        feeLate: @js($feeLate),
        feeLateAmount: @js($feeLateAmount),
        feeCurrency: @js($feeCurrency),
        feeSingle: @js($feeSingle),
        feeChosen: [],

        toggleFee(key) {
            /* A RADIO where the event sells one option (fee_selection =
               single): picking replaces, and a pick cannot be undone by
               pressing it again — that is what a radio is, and it is how an
               event with a combined Gi-plus-No-Gi option has to behave, or
               the two can be bought separately for more than the combined
               price.

               The server enforces the same rule in EventFee::quote(); this only
               saves the entrant from choosing something it would refuse. */
            if (this.feeSingle) { this.feeChosen = [key]; return; }

            const i = this.feeChosen.indexOf(key);
            if (i === -1) this.feeChosen.push(key); else this.feeChosen.splice(i, 1);
        },
        hasFee(key) { return this.feeChosen.indexOf(key) !== -1; },

        /* Mirrors EventFee::quote(): base + what is ticked + the late penalty
           when the SERVER says it is live. */
        get feeTotal() {
            let total = this.feeBase;
            this.feeOptions.forEach(o => { if (this.hasFee(o.key)) total += (o.amount || 0); });
            if (this.feeLate) total += this.feeLateAmount;
            return total;
        },
        get feeTotalText() {
            return (this.feeCurrency ? this.feeCurrency + ' ' : '') + this.feeTotal.toFixed(3);
        },

        /* ---------------- the SECOND door: somebody with an account ---------------- */
        q: '',
        results: [],
        picked: [],
        total: 0,
        searching: false,
        searched: false,
        entering: false,
        _debounce: null,

        onQuery() {
            clearTimeout(this._debounce);
            /* Debounced, and never fired for a query the server would refuse
               anyway — a live search is exactly the endpoint somebody hammers. */
            if (this.q.trim().length < 2) {
                this.results = []; this.searched = false; this.searching = false;
                return;
            }
            this._debounce = setTimeout(() => this.search(), 300);
        },

        async search() {
            const term = this.q.trim();
            if (term.length < 2) return;
            this.searching = true;
            try {
                const res = await fetch(@js(route('me.events.entry-search', $event)) + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                /* Only the query that is still in the box wins — a slow answer
                   to an older search must not overwrite a newer one. */
                if (term !== this.q.trim()) return;
                this.results = d.athletes || [];
                this.total = d.total || 0;
                this.searched = true;
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.searching = false; }
        },

        toggle(row) {
            if (! row.can_enter) return;
            const i = this.picked.indexOf(row.id);
            if (i === -1) this.picked.push(row.id); else this.picked.splice(i, 1);
        },
        isPicked(id) { return this.picked.indexOf(id) !== -1; },
        initial(name) { return (name || '?').trim().charAt(0).toUpperCase(); },

        async addPicked() {
            if (! this.picked.length || this.entering) return;
            this.entering = true;

            /* The endpoint takes the options PER athlete (a real squad is
               mixed); one choice here simply fills the map for everybody
               picked, which is what the coach's sheet's apply-to-all does. */
            const fee_options = {};
            if (this.feeChosen.length) this.picked.forEach(id => { fee_options[id] = this.feeChosen.slice(); });

            let d = null;
            try {
                const res = await fetch(@js(route('me.events.entries', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    // No `divisions`: the gate places them. See the note above.
                    body: JSON.stringify({ user_ids: this.picked, fee_options }),
                });
                d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
            } catch (e) {
                this.entering = false;
                window.showToast('error', e.message);
                return;
            }
            this.entering = false;

            /* Partial success is the normal outcome: say both halves, and leave
               a refusal on the row with the server's own reason rather than
               losing it in a toast. */
            (d.entered || []).forEach(row => {
                const a = this.results.find(x => x.id === row.user_id);
                if (! a) return;
                a.entered = true;
                a.can_enter = false;
                a.divisions = [...new Set((a.divisions || []).concat(row.division ? [row.division] : []))];
            });
            (d.rejected || []).forEach(row => {
                const a = this.results.find(x => x.id === row.user_id);
                if (a) { a.can_enter = false; a.reason = row.message; }
            });
            this.picked = [];
            window.showToast((d.rejected || []).length ? 'info' : 'success', d.message);
            window.dispatchEvent(new CustomEvent('event-people-added', {
                detail: { event: @js($event), entered: d.entered || [], going: d.going },
            }));
        },

        /* ---------------- the FIRST door: somebody with no account ---------------- */
        person: { full_name: '', contact_email: '', contact_phone: '', contact_code: '+973', gender: '', birthdate: '', weight: '', belt_colour: '', belt_grade: '', club: '' },

        /* The club picker, as a dropdown rather than a stack of cards: an event
           gathers clubs as it fills, and nine of them (and counting) pushed
           every field under them off the screen. The list itself is the same
           one, from the same authority. */
        clubOpen: false,
        clubList: @js(array_values(array_map(fn ($c) => [
            'slug' => (string) ($c['slug'] ?? ''),
            'name' => (string) ($c['name'] ?? ''),
            'logo' => $c['logo'] ?? null,
        ], $clubs))),
        clubChosen() { return this.clubList.find(c => c.slug === this.person.club) || null; },
        creating: false,
        claim: null,          // the link just minted — shown ONCE, never listed

        /* ===== Their face =====

           A competitor entered off a paper sheet has no account and therefore
           no profile picture, and the person who knows what they look like is
           the one standing in front of them at the desk. The photograph is
           what the hall screens, the entry list and the officials' sheets show
           — so it is taken HERE, not chased afterwards.

           STAGED, never uploaded on pick: there is no entry to attach it to
           until the name is committed, and an organiser who tapped the camera
           by mistake should be able to walk away. The crop sits in `photo` as
           a data URI and is posted the moment the entry exists. */
        photo: null,          // the staged crop, as a data URI
        photoSaving: false,

        photoId: @js($photoId),

        init() {
            /* The crop comes back on the photo flow's own event. Deduped on a
               window key because this console is shell-swapped and the
               listeners would otherwise stack up (Realtime rule §6). */
            if (window.__addPersonCropped) {
                window.removeEventListener('photo-capture:cropped', window.__addPersonCropped);
            }
            window.__addPersonCropped = (ev) => {
                if (ev.detail && ev.detail.id === this.photoId) {
                    this.photo = ev.detail.base64 || null;
                }
            };
            window.addEventListener('photo-capture:cropped', window.__addPersonCropped);
        },

        /* The photo flow owns the camera, the gallery and the crop; this only
           asks for it and keeps what comes back. */
        pickPhoto() {
            window.dispatchEvent(new CustomEvent('photo-capture:open', { detail: { id: this.photoId } }));
        },

        dropPhoto() { this.photo = null; },

        /* Attach the staged crop to an entry that now exists. Its own step, so
           a photograph that fails to store never costs the ENTRY — the person
           is on the list either way, and the picture can be retried from the
           card the organiser just created. */
        async savePhoto(registrationId) {
            if (! this.photo || ! registrationId || this.photoSaving) return false;
            this.photoSaving = true;
            try {
                const res = await fetch(@js(url('me/events/'.$event.'/competitors')) + '/' + registrationId + '/photo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ image: this.photo }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_photo_rejected')));
                if (this.claim) this.claim.photo = d.photo;
                this.photo = null;
                return true;
            } catch (e) {
                window.showToast('error', e.message);
                return false;
            } finally { this.photoSaving = false; }
        },

        async createPerson() {
            const name = (this.person.full_name || '').trim();
            if (name.length < 2 || this.creating) return;
            this.creating = true;

            /* Everything but the name is optional and stays optional — an
               invented birthdate is worse than a blank, because it drives age
               groups and the minor safeguards (CLAUDE.md → 'Who Fills The Form
               Decides What It Demands'). Blanks are dropped rather than sent
               as empty strings, which the format rules would refuse. */
            const body = { full_name: name };
            ['contact_email', 'gender', 'birthdate', 'belt_colour', 'belt_grade', 'club'].forEach(k => {
                const v = (this.person[k] || '').trim();
                if (v) body[k] = v;
            });

            /* One dialable string. The code is only worth storing when there IS
               a number, and a number already typed in full international form
               is left exactly as the person at the desk wrote it. */
            const phone = (this.person.contact_phone || '').trim();
            if (phone) {
                body.contact_phone = phone.startsWith('+')
                    ? phone
                    : ((this.person.contact_code || '') + ' ' + phone).trim();
            }
            if ((this.person.weight || '').toString().trim()) body.weight = Number(this.person.weight);
            if (this.feeChosen.length) body.options = this.feeChosen.slice();

            let d = null;
            try {
                const res = await fetch(@js(route('me.events.entries.unnamed', $event)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
            } catch (e) {
                this.creating = false;
                window.showToast('error', e.message);
                return;
            }
            this.creating = false;

            this.claim = d.claim || null;
            this.person = { full_name: '', contact_email: '', contact_phone: '', contact_code: '+973', gender: '', birthdate: '', weight: '', belt_colour: '', belt_grade: '', club: '' };
            window.showToast('success', d.message);

            /* The entry carries its registration id back (EntryClaim::present),
               which is exactly what the photo endpoint is keyed on. */
            if (this.photo && this.claim?.competitor_id) {
                await this.savePhoto(this.claim.competitor_id);
            }

            window.dispatchEvent(new CustomEvent('event-people-added', {
                detail: { event: @js($event), claim: d.claim || null, going: d.going },
            }));
        },

        async copyClaim() {
            if (! this.claim?.url) return;
            try {
                await navigator.clipboard.writeText(this.claim.url);
                window.showToast('success', @js(__('personal.event_show_byname_copied')));
            } catch (e) {
                window.showToast('info', @js(__('personal.event_show_byname_copy_manual')));
            }
        },
        async shareClaim() {
            if (! this.claim?.url) return;
            /* The device's share sheet where there is one; an Android WebView
               may offer neither, so the clipboard is the fallback. */
            if (navigator.share) {
                try { await navigator.share({ title: this.claim.name, url: this.claim.url }); } catch (e) {}
                return;
            }
            this.copyClaim();
        },
     }">

    {{-- The row in the console. --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white"
              style="background: {{ $c }};">
            <i class="bi bi-person-plus-fill text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.add_person') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('events::events.add_person_sub') }}</span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
    </button>

    {{-- THE photo flow — the self-serve entrant's, not a lesser copy.

         `<x-photo-capture>` is the sheet (camera tile · gallery tile), the
         camera screen and the one cropper, exactly as somebody completing
         their own entry gets them. It saves nothing: the crop comes back on
         `photo-capture:cropped` and this sheet stages it until the name is
         committed (see createPerson).

         Outside the teleported sheet below on purpose, so one teleport is
         never nested inside another. --}}
    <x-photo-capture :id="$photoId"
                     :color="$c"
                     :title="__('events::events.add_person_photo')"
                     :hint="__('events::events.add_person_photo_sheet_hint')"
                     {{-- Above THIS sheet (z-[70]) and below the cropper's own
                          editor (z-[80]): opened from inside a sheet, the photo
                          sheet's own default of 60 puts it underneath. --}}
                     :z="74" :camera-z="76" />

    {{-- Teleported: the mobile shell leaves a transform on its children, which
         would make a fixed sheet resolve against a wrapper instead of the
         viewport and clip the form.

         SHAPE AND WIDTH ARE THE ENTRY LIST'S, copied rather than invented: the
         `fixed inset-0` room, the `bg-black/50` backdrop, and a panel that is
         `absolute inset-x-0 bottom-0 max-h-[92vh] rounded-t-3xl overflow-hidden
         bg-background` — full width, on the bottom edge, no `sm:max-w-*` cap
         (reference: the action and fix sheets in personal/event-people).

         Two things that followed from that, both asked for on 2026-09-11: it is
         a bottom sheet at EVERY width (centred above 640px it read as a box
         floating in the middle of the screen), and it is FULL width, so the
         cropper it opens — which is `sheetMaxWidth="100%"` — stacks flush with
         it instead of being wider than the sheet it came from. Do not
         reintroduce the centred, capped variant here. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70]"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity @click="open = false"
                 class="absolute inset-0 bg-black/50"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                {{-- Gradient header band (Design Rule #8). --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.add_person') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    {{-- The two doors, as a segmented control inside the band. --}}
                    <div class="relative mt-3 flex items-center gap-1 p-1 rounded-full bg-white/15 backdrop-blur">
                        <button type="button" @click="tab = 'new'"
                                class="flex-1 h-8 rounded-full text-[12px] font-bold transition-colors"
                                :class="tab === 'new' ? 'bg-white/90 text-gray-900' : 'text-white/85'">
                            <i class="bi bi-pencil-fill me-1"></i>{{ __('events::events.add_person_tab_new') }}
                        </button>
                        <button type="button" @click="tab = 'member'"
                                class="flex-1 h-8 rounded-full text-[12px] font-bold transition-colors"
                                :class="tab === 'member' ? 'bg-white/90 text-gray-900' : 'text-white/85'">
                            <i class="bi bi-person-check-fill me-1"></i>{{ __('events::events.add_person_tab_member') }}
                        </button>
                    </div>
                </div>

                {{-- Body scrolls; the footer holding the action stays reachable.

                     ONE rhythm, owned here: 24px between the sheet's major
                     blocks (the open tab, what they are buying, the footnote).
                     Nothing inside a block sets its own bottom margin to hold
                     the next one off — that is how a gap disappears the day the
                     block above it is removed, which is exactly what happened
                     when the division picker went. --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">

                    {{-- ============ Second tab — has an account ============ --}}
                    <div x-show="tab === 'member'" x-cloak>
                        <div class="relative">
                            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                            <input type="search" x-model="q" @input="onQuery()" @keydown.enter.prevent="search()"
                                   placeholder="{{ __('events::events.add_person_search') }}"
                                   class="w-full ps-9 pe-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5 px-1"
                           x-show="q.trim().length < 2" x-cloak>{{ __('events::events.add_person_search_hint') }}</p>

                        <div class="mt-3 space-y-2">
                            <p class="text-[12px] text-muted-foreground py-6 text-center" x-show="searching" x-cloak>
                                <i class="bi bi-arrow-repeat"></i> {{ __('events::events.add_person_searching') }}
                            </p>

                            <template x-for="row in results" :key="row.id">
                                <button type="button" @click="toggle(row)" :disabled="! row.can_enter"
                                        class="m-press w-full text-start rounded-2xl border-2 p-3 transition-colors flex items-center gap-3 disabled:opacity-60"
                                        :class="isPicked(row.id) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                    <span class="w-9 h-12 rounded-lg bg-muted text-muted-foreground grid place-items-center flex-shrink-0 text-sm font-black"
                                          x-text="initial(row.name)"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-foreground truncate" x-text="row.name"></span>
                                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5"
                                              x-show="row.entered" x-cloak>
                                            <i class="bi bi-check-circle-fill text-green-600"></i>
                                            {{ __('events::events.add_person_already_in') }}<span x-show="row.divisions && row.divisions.length" x-text="' · ' + (row.divisions || []).join(', ')"></span>
                                        </span>
                                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5"
                                              x-show="! row.entered && ! row.can_enter && row.reason" x-cloak x-text="row.reason"></span>
                                    </span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 transition-colors"
                                          x-show="row.can_enter"
                                          :class="isPicked(row.id) ? 'border-transparent text-white' : 'border-gray-300'"
                                          :style="isPicked(row.id) ? 'background: {{ $c }}' : ''">
                                        <i class="bi bi-check-lg text-[11px]" x-show="isPicked(row.id)" x-cloak></i>
                                    </span>
                                </button>
                            </template>

                            <p class="text-[12px] text-muted-foreground py-6 text-center"
                               x-show="searched && ! searching && ! results.length" x-cloak>{{ __('events::events.add_person_no_results') }}</p>
                            <p class="text-[11px] text-muted-foreground text-center pt-1"
                               x-show="total > results.length" x-cloak
                               x-text="@js(__('events::events.add_person_more')).replace(':count', total - results.length)"></p>
                        </div>
                    </div>

                    {{-- ============ First tab, and the default — no account ============ --}}
                    {{-- 20px between the pane's own sections; a section's
                         controls sit 12px apart (space-y-3 below). --}}
                    <div x-show="tab === 'new'" x-cloak class="space-y-5">
                        {{-- What was just minted, shown once. A claim link is a
                             credential, so it lives here and is never listed. --}}
                        <div x-show="claim" x-cloak class="rounded-2xl border-2 border-green-200 bg-green-50 p-4">
                            <p class="text-sm font-black text-foreground flex items-center gap-2">
                                <i class="bi bi-check-circle-fill text-green-600"></i>
                                <span x-text="claim ? claim.name : ''"></span>
                            </p>
                            <p class="text-[11.5px] text-muted-foreground leading-snug mt-1">{{ __('events::events.add_person_link_sub') }}</p>

                            {{-- The photograph, if one went with them. A crop
                                 still sitting here means the upload failed —
                                 the entry is safe, so offer the retry rather
                                 than losing the picture. --}}
                            <div class="flex items-center gap-3 mt-3" x-show="claim && (claim.photo || photo)" x-cloak>
                                <span class="w-12 h-16 rounded-lg overflow-hidden bg-muted flex-shrink-0 block">
                                    <img :src="claim && claim.photo ? claim.photo : photo" alt="" class="w-full h-full object-cover">
                                </span>
                                <button type="button" x-show="photo" x-cloak :disabled="photoSaving"
                                        @click="savePhoto(claim && claim.competitor_id)"
                                        class="m-press h-9 px-3 rounded-lg bg-white border border-gray-200 text-[12px] font-semibold text-foreground disabled:opacity-50">
                                    <i class="bi bi-arrow-clockwise me-1"></i>{{ __('events::events.add_person_photo_retry') }}
                                </button>
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <button type="button" @click="copyClaim()"
                                        class="flex-1 h-10 rounded-lg bg-white border border-gray-200 text-sm font-semibold text-foreground m-press">
                                    <i class="bi bi-clipboard me-1"></i>{{ __('events::events.add_person_copy') }}
                                </button>
                                <button type="button" @click="shareClaim()"
                                        class="flex-1 h-10 rounded-lg text-white text-sm font-semibold m-press"
                                        style="background: {{ $c }};">
                                    <i class="bi bi-share-fill me-1"></i>{{ __('events::events.add_person_share') }}
                                </button>
                            </div>
                            <button type="button" @click="claim = null"
                                    class="w-full text-center text-[11px] text-muted-foreground mt-3">{{ __('events::events.add_person_done') }}</button>
                        </div>

                        {{-- Their face. Portrait 3:4 like every profile picture on
                             the platform, and the fallback is a silhouette, not
                             an icon — this is a person, and the box should look
                             like it is waiting for one. --}}
                        <div class="flex items-center gap-4">
                            <button type="button" @click="pickPhoto()"
                                    {{-- ⚠️ `border-solid` is NOT in the prebuilt Tailwind
                                         bundle, so the dashed edge is dropped by
                                         swapping the whole binding rather than
                                         overriding it. --}}
                                    class="m-press w-24 h-32 rounded-2xl overflow-hidden border-2 border-gray-200 bg-muted grid place-items-center flex-shrink-0 relative"
                                    :class="photo ? '' : 'border-dashed'"
                                    aria-label="{{ __('events::events.add_person_photo_add') }}">
                                <template x-if="photo">
                                    <img :src="photo" alt="" class="absolute inset-0 w-full h-full object-cover">
                                </template>
                                <template x-if="! photo">
                                    <span class="text-muted-foreground text-center">
                                        <i class="bi bi-person-bounding-box text-2xl"></i>
                                    </span>
                                </template>
                            </button>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground">{{ __('events::events.add_person_photo') }}</p>
                                <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ __('events::events.add_person_photo_hint') }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <button type="button" @click="pickPhoto()"
                                            class="m-press h-9 px-3 rounded-lg bg-white border border-gray-200 text-[12px] font-semibold text-foreground">
                                        <i class="bi bi-camera-fill me-1"></i><span x-text="photo ? @js(__('events::events.add_person_photo_change')) : @js(__('events::events.add_person_photo_add'))"></span>
                                    </button>
                                    <button type="button" @click="dropPhoto()" x-show="photo" x-cloak
                                            class="m-press h-9 px-3 rounded-lg border border-red-200 text-red-600 text-[12px] font-semibold">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- The only field that is required, and the only one
                             standing on its own. --}}
                        <section>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.add_person_name') }}</label>
                            <input type="text" x-model="person.full_name" maxlength="120"
                                   placeholder="{{ __('events::events.add_person_name_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </section>

                        <section>
                            {{-- Eyebrow, its sub-line 2px under it, then 12px of
                                 air before the first control: the heading and
                                 its explanation read as ONE object, set apart
                                 from the fields they introduce. --}}
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('events::events.add_person_details') }}</p>
                            <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ __('events::events.add_person_details_sub') }}</p>

                            <div class="space-y-3 mt-3">
                                {{-- The shared two-button selector, not a native select
                                     (Design Rule #4) and not a second copy of it. --}}
                                <x-fields.gender-toggle model="person.gender"
                                                 :male-label="(string) Illuminate\Support\Js::from(__('events::events.add_person_male'))"
                                                 :female-label="(string) Illuminate\Support\Js::from(__('events::events.add_person_female'))" />

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.add_person_birthdate') }}</label>
                                    {{-- The DROPDOWN variant — Day / Month / Year, with a
                                         typeable year — because a birthdate is decades
                                         back and paging a month grid there is absurd.
                                         The same control, in the same variant, that the
                                         self-serve entrant is asked with
                                         (entry/public/my-entry). Never a native date
                                         input (Design Rule #4). --}}
                                    <x-fields.date variant="dropdown" model="person.birthdate"
                                                   max="{{ now()->subDay()->toDateString() }}" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.add_person_weight') }}</label>
                                    <input type="number" inputmode="decimal" step="0.1" min="10" max="300" x-model="person.weight"
                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>

                                {{-- THE RANK.

                                     The desk knows it — the athlete is standing
                                     there in the belt — and it decides which
                                     division they land in, so asking for it here
                                     is the difference between placing them now
                                     and chasing them later. It was the one thing
                                     the entry list showed that this sheet could
                                     not record (reported 2026-09-13).

                                     Drawn as the belt itself rather than a
                                     coloured dot: the picker is answered by
                                     looking at somebody's waist, so it should
                                     look like what the answerer is looking at.
                                     The ladder is App\Sports\Combat\BeltRank's
                                     — the one every other surface reads. --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('events::events.entry_field_belt') }}</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        @php $addEvent = $event ? \App\Events\Models\ClubEvent::where('uuid', $event)->first() : null;
                                             $addLadder = $addEvent ? app(\App\Events\Support\CategorySystems::class)->ladderFor($addEvent) : \App\Sports\Combat\BeltRank::ladder($sport ?? null); @endphp
                                        @foreach($addLadder as $belt)
                                            <button type="button"
                                                    @click="person.belt_colour = person.belt_colour === @js($belt['value']) ? '' : @js($belt['value'])"
                                                    :aria-pressed="person.belt_colour === @js($belt['value'])"
                                                    :class="person.belt_colour === @js($belt['value']) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                                    class="m-press flex items-center gap-1.5 px-2 py-2 rounded-xl border-2 transition-colors min-w-0">
                                                <x-belt-icon :color="$belt['value']" :title="false" class="w-6 h-6 flex-shrink-0" />
                                                {{-- Wraps rather than truncates: a two-tone belt's name
                                                     ("Orange / Black") is longer than a tile, and a name is
                                                     never cut short. --}}
                                                <span class="text-[11.5px] font-bold text-foreground leading-tight text-start break-words min-w-0">{{ $belt['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- The degree, in the federation's own words.
                                         Only once a belt is chosen: a grade with no
                                         belt is not a rank anybody announces. --}}
                                    <input type="text" x-show="person.belt_colour" x-cloak
                                           x-model="person.belt_grade" maxlength="40"
                                           placeholder="{{ __('events::events.entry_field_grade') }}"
                                           class="mt-2 w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                        </section>

                        {{-- WHICH CLUB they compete for.

                             Per entry, not per person: an athlete competes for one
                             club this weekend and another next year. The list is the
                             clubs already at this event, so it is a picker at a mat
                             rather than a search of the platform — and "not
                             representing a club" is a real answer, not an empty one.

                             Absent entirely when the event has no clubs on it yet,
                             because a picker with one option that means "nobody" is
                             a question not worth asking. --}}
                        @if(! empty($clubs))
                            <section>
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('events::events.entry_field_club') }}</p>

                                {{-- A DROPDOWN, not a stack of selection cards.

                                     Nine clubs and counting is past the point
                                     where cards help: they pushed the fee list
                                     and the Add button off the bottom of the
                                     sheet, which is the one thing a mobile form
                                     may never do.

                                     ⚠️ It expands IN THE NORMAL FLOW, never
                                     `position: absolute` — this sheet's body is
                                     `overflow-y-auto`, and an absolute panel is
                                     clipped by its own scrolling container
                                     (Mobile Pattern Language; the same reason
                                     <x-fields.date> opens in flow). That also
                                     rules out <x-fields.select> here, whose panel
                                     is absolute — and it could not carry the
                                     crests anyway. --}}
                                <div class="mt-3" @click.outside="clubOpen = false" @keydown.escape.stop="clubOpen = false">
                                    <button type="button" @click="clubOpen = ! clubOpen"
                                            :aria-expanded="clubOpen"
                                            :class="clubOpen ? 'ring-2 ring-primary border-transparent' : 'border-gray-200'"
                                            class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-white border rounded-xl text-start transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        {{-- What is chosen, drawn the way the row that chose it was.
                                             Design Rule #5: the bare crest, never a white tile. --}}
                                        <template x-if="clubChosen() && clubChosen().logo">
                                            <span class="w-5 h-5 flex-shrink-0 grid place-items-center">
                                                <img :src="clubChosen().logo" alt="" class="w-full h-full object-contain">
                                            </span>
                                        </template>
                                        <template x-if="! clubChosen() || ! clubChosen().logo">
                                            <i class="bi flex-shrink-0 text-muted-foreground"
                                               :class="clubChosen() ? 'bi-buildings' : 'bi-person-x'"></i>
                                        </template>

                                        <span class="text-[13px] font-bold flex-1 truncate"
                                              :class="clubChosen() ? 'text-foreground' : 'text-muted-foreground'"
                                              x-text="clubChosen() ? clubChosen().name : @js(__('events::events.entry_no_club'))"></span>

                                        <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform flex-shrink-0"
                                           :class="clubOpen && 'rotate-180'"></i>
                                    </button>

                                    <div x-show="clubOpen" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 -translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         class="mt-1 rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden max-h-64 overflow-y-auto">
                                        <button type="button" @click="person.club = ''; clubOpen = false"
                                                :class="! person.club ? 'bg-primary/5 text-primary' : 'text-foreground hover:bg-muted/60'"
                                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-start transition-colors">
                                            <i class="bi bi-person-x flex-shrink-0 text-muted-foreground"></i>
                                            <span class="text-[13px] font-bold flex-1">{{ __('events::events.entry_no_club') }}</span>
                                            <i class="bi bi-check-lg flex-shrink-0" x-show="! person.club"></i>
                                        </button>

                                        @foreach($clubs as $club)
                                            <button type="button" @click="person.club = @js($club['slug']); clubOpen = false"
                                                    :class="person.club === @js($club['slug']) ? 'bg-primary/5 text-primary' : 'text-foreground hover:bg-muted/60'"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-start transition-colors border-t border-gray-100">
                                                <span class="w-5 h-5 flex-shrink-0 grid place-items-center">
                                                    @if(! empty($club['logo']))
                                                        <img src="{{ $club['logo'] }}" alt="" class="w-full h-full object-contain">
                                                    @else
                                                        <i class="bi bi-buildings text-muted-foreground"></i>
                                                    @endif
                                                </span>
                                                <span class="text-[13px] font-bold flex-1 truncate">{{ $club['name'] }}</span>
                                                <i class="bi bi-check-lg flex-shrink-0" x-show="person.club === @js($club['slug'])"></i>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif

                        <section>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('events::events.add_person_contact') }}</p>
                            <div class="space-y-2 mt-3">
                                <input type="email" x-model="person.contact_email" maxlength="190"
                                       placeholder="{{ __('events::events.add_person_email') }}"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                {{-- The platform's phone control, not a bare text
                                     box: a dial code picked from a list beats one
                                     typed four different ways, and it is the same
                                     control the register form and the member sheet
                                     ask with (Component-First).

                                     `model` mirrors the code into this sheet's own
                                     Alpine scope — createPerson() joins it to the
                                     number, so what is stored is one dialable
                                     string. Its default `tf-input-group` styling is
                                     deliberate: the focus-within utilities a
                                     hand-rolled group would need are NOT in the
                                     prebuilt Tailwind bundle and would silently do
                                     nothing. --}}
                                <x-fields.phone-code
                                    name="contact_code"
                                    :id="$photoId.'Dial'"
                                    model="person.contact_code"
                                    value="+973">
                                    <input type="tel" x-model="person.contact_phone" maxlength="32"
                                           autocomplete="tel"
                                           placeholder="{{ __('events::events.add_person_phone') }}"
                                           class="w-full px-3 py-2.5 bg-transparent text-sm border-0 outline-none focus:ring-0">
                                </x-fields.phone-code>
                            </div>
                        </section>
                    </div>

                    {{-- ===== What they are entering — LAST, under the button =====

                         Immediately above the control that commits it (owner's
                         call, 2026-09-11): the sheet asks who this person is,
                         and the choice that decides the MONEY is the thing
                         under the reader's eye when they press Add — not
                         something scrolled past on the way in.

                         Shared by both tabs.

                         The SAME question the public enrolment form asks, in
                         the same words and at the same prices — an athlete
                         entered at the desk is buying what an athlete who
                         entered themselves buys (owner's instruction,
                         2026-09-11). Reference: entry/public/enrol.

                         Only UUIDs are posted; the running total is a courtesy
                         to the person at the desk, never an authority. --}}
                    @if (count($feeOptions))
                    <section>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-3">{{ __('events::events.fee_select_options') }}</p>
                        <div class="space-y-2">
                            @foreach ($feeOptions as $opt)
                                <button type="button" @click="toggleFee(@js($opt['key']))"
                                        class="m-press w-full text-start rounded-2xl border-2 p-3 transition-colors flex items-center gap-3"
                                        :class="hasFee(@js($opt['key'])) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                        :aria-pressed="hasFee(@js($opt['key']))">
                                    <span class="w-5 h-5 {{ $feeSingle ? 'rounded-full' : 'rounded-md' }} border-2 grid place-items-center flex-shrink-0 transition-colors"
                                          :class="hasFee(@js($opt['key'])) ? 'border-transparent text-white' : 'border-gray-300'"
                                          :style="hasFee(@js($opt['key'])) ? 'background: {{ $c }}' : ''">
                                        <i class="bi bi-check text-[11px]" x-show="hasFee(@js($opt['key']))" x-cloak></i>
                                    </span>
                                    <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground">{{ $opt['label'] }}</span>
                                    <span class="text-[12px] font-black flex-shrink-0" style="color: {{ $c }};">{{ $opt['display'] ?? '' }}</span>
                                </button>
                            @endforeach
                        </div>

                        @if ($feeLate)
                            {{-- Said plainly, and said BEFORE the total: a charge
                                 discovered afterwards is the one disputed at the desk. --}}
                            <div class="rounded-2xl bg-amber-50 border border-amber-200 px-3 py-2.5 flex items-start gap-2 mt-3">
                                <i class="bi bi-clock-history text-amber-600 mt-0.5"></i>
                                <p class="text-[11.5px] text-muted-foreground leading-snug">
                                    {{ __('events::events.fee_late_applies', ['amount' => trim(($feeCurrency ? $feeCurrency.' ' : '').number_format($feeLateAmount, 3))]) }}
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                            <span class="text-[12px] font-bold text-muted-foreground">{{ __('events::events.fee_total') }}</span>
                            <span class="text-[15px] font-black text-foreground" x-text="feeTotalText"></span>
                        </div>
                    </section>
                    @endif

                    <p class="text-[11px] text-muted-foreground leading-snug flex items-start gap-1.5">
                        <i class="bi bi-shield-lock mt-0.5 flex-shrink-0"></i>{{ __('events::events.add_person_hint') }}
                    </p>
                </div>

                {{-- Sticky footer, safe-area padded, so the action is always reachable. --}}
                <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 bg-white"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" x-show="tab === 'member'" @click="addPicked()"
                            :disabled="! picked.length || entering"
                            class="m-press w-full h-12 rounded-xl text-white text-sm font-bold disabled:opacity-50 transition-opacity"
                            style="background: {{ $c }};">
                        <template x-if="! entering">
                            <span>
                                <i class="bi bi-person-plus-fill me-1"></i>{{ __('events::events.add_person_submit') }}<span x-show="picked.length" x-text="' (' + picked.length + ')'"></span>
                            </span>
                        </template>
                        <template x-if="entering"><span><i class="bi bi-arrow-repeat"></i></span></template>
                    </button>

                    <button type="button" x-show="tab === 'new'" x-cloak @click="createPerson()"
                            :disabled="person.full_name.trim().length < 2 || creating"
                            class="m-press w-full h-12 rounded-xl text-white text-sm font-bold disabled:opacity-50 transition-opacity"
                            style="background: {{ $c }};">
                        <template x-if="! creating"><span><i class="bi bi-person-plus-fill me-1"></i>{{ __('events::events.add_person_create') }}</span></template>
                        <template x-if="creating"><span><i class="bi bi-arrow-repeat"></i></span></template>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
