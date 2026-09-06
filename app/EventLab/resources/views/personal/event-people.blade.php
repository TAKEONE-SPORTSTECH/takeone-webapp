{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('personal.event_show_whos_joined'))

{{--
    Who's joined.

    Two tabs over one roster: the competitors, and the clubs they came from.
    Reading only for everybody EXCEPT whoever runs the event, who gets one thing
    here: select names and take them off the list. It belongs on this screen and
    not on a form of its own, because striking somebody off is a decision made
    while LOOKING at the list — the organiser is reading down the entrants
    finding the three who never paid. The officials' gates still live on their
    own screen (event-verification); this is not that.

    The service refuses what it must (an event underway, an athlete who has
    already fought) — see App\Events\Support\EntryService::remove(). This page
    decides only what to OFFER.

    Every card is a door to somewhere that already exists: an athlete to their
    public profile, a club to its club page. The page invents no destinations and
    discloses nothing those pages do not.

    Expects $e (the event view) plus $participants and $clubs from
    App\Events\Support\RosterPeople.
--}}

@php
    $flagClass = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? 'fi fi-'.$c : null;
    };
@endphp

@section($contentSection ?? 'content')
{{-- ⚠️ The hero band below is full-bleed (Design Rule #6), so the page wrapper's
     `px-4 py-4` has to be cancelled — but ONLY where there is one to cancel.
     This page fills `content`, which on the member path replaces
     layouts.personal-mobile's own section and lands in an UNPADDED <main>;
     inside the sealed event shell (entry/shell) it fills `personal-content` and
     lands in a padded one. Cancelling unconditionally would fix the sealed page
     and push the member page 16px past both screen edges, so the cancellation
     follows the shell. --}}
<div class="{{ isset($shell) ? '-mx-4 -mt-4' : '' }}" x-data="{
        tab: 'athletes',
        q: '',
        match(...fields) {
            const s = this.q.trim().toLowerCase();
            return !s || fields.some(f => (f || '').toLowerCase().includes(s));
        },
        {{-- A list can only say 'nothing matched' if it knows every name on it.
             Filtering is client-side because every row is already on the page —
             typing narrows it with no round trip, and it can only ever hide rows
             the server already decided this viewer may see. --}}
        names: {
            athletes: @js(collect($participants)->map(fn ($p) => trim(($p['name'] ?? '').' '.($p['club']['name'] ?? '')))->values()),
            clubs: @js(collect($clubs)->pluck('name')->values()),
        },
        get empty() {
            return this.q.trim() !== '' && !(this.names[this.tab] || []).some(n => this.match(n));
        },

        /* ===== Taking names off the list — organiser only =====
           Rendered only when the server said this viewer may manage; the
           endpoint re-checks that regardless, so this decides what to OFFER. */
        selecting: false,
        picked: [],
        busy: false,
        athletes: {{ count($participants) }},

        startSelecting() { this.selecting = true; this.picked = []; },
        stopSelecting()  { this.selecting = false; this.picked = []; },

        /* ===== One entry, four things you can do with it =====

           Tapping a card used to open that person's profile, which is one of
           the four and not the most likely: an organiser standing at a desk
           wants the PICTURE, the weigh-in, or the entry gone. So the card asks
           instead, and the two controls that used to sit on the photograph
           (a camera badge and a ✕) are gone from the card entirely — they were
           two small targets on a thumbnail, in front of the picture they edit. */
        actions: null,

        openActions(entrant) {
            if (this.selecting) return;      // selecting owns the tap
            this.actions = entrant;
        },
        closeActions() { this.actions = null; },

        /* The card holds its own live picture (competitorPhoto), so the upload
           writes back into THAT card's scope rather than reloading the list. */
        cardScope(reg) {
            const el = document.getElementById('entrant-' + reg);
            return (el && window.Alpine) ? window.Alpine.$data(el) : null;
        },

        /* `changePicture()` / `uploadPicture()` went with that row. The one
           remaining photo path is the fix sheet's: pick -> crop -> stage ->
           Save (see fixPicture / fixCropped). */

        /* Removing ONE reuses the many-at-once path: same confirmation, same
           endpoint, same in-place patching, same refusals said out loud. */
        async removeOne() {
            if (! this.actions) return;
            const reg = this.actions.reg;
            this.closeActions();
            this.picked = [reg];
            await this.removePicked();
        },

        goTo(url) {
            if (url) window.location.href = url;
        },

        pick(id) {
            if (! id) return;
            const i = this.picked.indexOf(id);
            i === -1 ? this.picked.push(id) : this.picked.splice(i, 1);
        },
        isPicked(id) { return this.picked.indexOf(id) !== -1; },

        async removePicked() {
            if (this.busy || ! this.picked.length) return;

            /* No native confirm() anywhere in this project — and the wording
               says the two things a delete button must never leave implied:
               how many, and that no money moves. */
            const ok = await window.confirmAction({
                title: @js(__('personal.event_people_remove_title')),
                message: @js(__('personal.event_people_remove_message')).replace(':count', this.picked.length),
                type: 'danger',
                confirmText: @js(__('personal.event_people_remove_confirm')),
            });
            if (! ok) return;

            this.busy = true;
            try {
                const res = await fetch(@js(route('testcode.me.events.entries.destroy', $e['key'])), {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ registration_ids: this.picked }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                /* Patch in place (No-Reload): drop the rows the server actually
                   removed — never the ones it refused, which stay on the list
                   with the reason said out loud. */
                (d.removed || []).forEach(r => {
                    document.getElementById('entrant-' + r.registration_id)?.remove();
                });
                if (typeof d.going === 'number') this.athletes = d.going;
                if (d.going_label) {
                    const el = document.getElementById('people-athlete-count');
                    if (el) el.textContent = d.going_label;
                }

                window.showToast((d.rejected || []).length ? 'warning' : 'success', d.message);
                (d.rejected || []).forEach(r => window.showToast('error', r.message));

                this.stopSelecting();
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.busy = false; }
        },

        /* ===== Fixing one entry — the organiser's twin of the athlete's panel =====

           Opened from the action sheet above. It ALWAYS re-reads the entry as it
           opens (me.events.entrant) rather than trusting the row the page was
           rendered with: a desk two metres away may have weighed this person
           since, and the first thing an editor must not do is show a stale
           number as if it were current.

           It saves a SUBSET — only fields the server said are editable, and only
           the ones actually changed. A locked field is drawn locked WITH its
           reason and never sent; the server refuses one with a 422 naming it,
           which is the right answer for a client that lies but the wrong thing
           to make an organiser discover. Absent is not blank (CLAUDE.md): a key
           that is not in the body leaves what is on file alone. */
        fix: null,

        entrantUrl(reg) { return `{{ url('testcode/me/events') }}/{{ $e['key'] }}/entrants/${reg}`; },

        blankFixForm() { return { weight: '', belt_colour: '', belt_grade: '', birthdate: '', gender: '', club: '', nationality: '' }; },

        async openFix(entrant) {
            const from = entrant || this.actions;
            const reg = from && from.reg;
            if (! reg) return;

            this.closeActions();
            this.fix = {
                reg, name: (from.name || ''), club: (from.club || ''),
                entry: null, perms: null, form: this.blankFixForm(),
                loading: true, busy: false,
            };
            await this.loadFix();
        },
        closeFix() { this.fix = null; },

        /* `silent` is the realtime path: a background re-read must never toast
           or close the sheet the organiser is typing into. */
        async loadFix(silent) {
            const f = this.fix;
            if (! f) return;
            if (! silent) f.loading = true;

            try {
                const res = await fetch(this.entrantUrl(f.reg), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.seedFix(d.entry, d.permissions);
            } catch (e) {
                if (! silent) { window.showToast('error', e.message); this.closeFix(); }
            } finally {
                if (this.fix) this.fix.loading = false;
            }
        },

        seedFix(entry, perms) {
            const f = this.fix;
            if (! f || ! entry) return;
            f.entry = entry;
            if (perms) f.perms = perms;
            f.name = entry.name || f.name;
            f.form = {
                name: entry.name || '',
                weight: entry.weight === null || entry.weight === undefined ? '' : String(entry.weight),
                belt_colour: entry.belt_colour || '',
                belt_grade: entry.belt_grade || '',
                birthdate: entry.birthdate || '',
                gender: entry.gender || '',
                /* The club the entry COMPETES FOR, as a slug — an empty string
                   is the real answer (unattached), not a missing value.
                   ⚠️ NO DOUBLE QUOTES anywhere in this block: it is the value
                   of an x-data attribute, and a single one of them ends the
                   attribute and spills the rest of the script onto the page as
                   visible text. */
                club: (entry.club && entry.club.slug) || '',
                nationality: entry.nationality || '',
                // A crop the organiser has staged but not saved. Preserved
                // across a realtime re-read (`loadFix(silent)`), which would
                // otherwise wipe work in progress; empty on a fresh open.
                photo: (f.form && f.form.photo) || null,
            };
        },

        /* The cropper finished. Stage it — nothing is written until Save. */
        fixCropped(base64) {
            if (! this.fix || ! base64) return;
            this.fix.form.photo = base64;
            window.showToast('info', @js(__('personal.event_photo_staged')));
        },

        /** Throw away a staged crop without touching what is on file. */
        fixDropStaged() {
            if (this.fix) this.fix.form.photo = null;
        },

        fixEditable(field) {
            const fields = this.fix && this.fix.perms && this.fix.perms.fields;
            return !! (fields && fields[field] && fields[field].editable);
        },
        fixReason(field) {
            const fields = this.fix && this.fix.perms && this.fix.perms.fields;
            return (fields && fields[field] && fields[field].reason) || '';
        },

        fixPickBelt(value) {
            if (! this.fixEditable('belt_colour')) return;
            this.fix.form.belt_colour = (this.fix.form.belt_colour === value ? '' : value);
        },

        /* The picture goes through the page's EXISTING upload — same file input,
           same endpoint, same in-place patch of the card. There is one picture
           flow on this screen, not two. */
        /*
         * The fix sheet's photograph is STAGED, not uploaded.
         *
         * It used to share the action sheet's uploader, which POSTs the picked
         * file the instant it is chosen: no crop, no Save, and Cancel could not
         * put the old picture back because it was already gone. An organiser
         * who tapped the camera by accident had replaced somebody's photograph
         * before they could think about it.
         *
         * Now the file goes to the ONE cropper (3:4, as every face on this
         * platform is), the crop lands in `fix.form.photo` as a data URI, and
         * nothing reaches the server until Save — which sends it through the
         * same entrant endpoint as every other field. Closing the sheet throws
         * it away.
         */
        fixPicture() {
            if (! this.fix) return;
            this.$refs.fixPhotoInput.click();
        },

        /** A file was picked for the fix sheet — hand it to the cropper. */
        fixPhotoPicked(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (! file) return;

            // Let the same file be picked twice in a row: without this, a
            // second identical pick fires no change event at all.
            ev.target.value = '';

            if (file.size > 10 * 1024 * 1024) {
                window.showToast('error', @js(__('personal.event_photo_too_big')));
                return;
            }

            const target = document.getElementById('input_fixPhoto');
            if (! target) return;

            const dt = new DataTransfer();
            dt.items.add(file);
            target.files = dt.files;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        },

        fixPayload() {
            const f = this.fix, e = f.entry, out = {};
            const raw = String(f.form.weight === null || f.form.weight === undefined ? '' : f.form.weight).trim();
            const weight = raw === '' ? null : parseFloat(raw);

            if (this.fixEditable('weight') && ! (weight === null && (e.weight === null || e.weight === undefined))
                && weight !== e.weight) {
                out.weight = weight;
            }
            if (this.fixEditable('belt_colour') && (f.form.belt_colour || '') !== (e.belt_colour || '')) {
                out.belt_colour = f.form.belt_colour || null;
            }
            if (this.fixEditable('belt_grade') && (f.form.belt_grade || '').trim() !== (e.belt_grade || '')) {
                out.belt_grade = (f.form.belt_grade || '').trim() || null;
            }
            /* The name. Trimmed and collapsed the way the server does, and
               never sent blank — a competitor with no name breaks every
               listing, card and search result on the platform, so the server
               ignores an empty one and this does not bother sending it. */
            if (this.fixEditable('name')) {
                const name = (f.form.name || '').trim().replace(/\s+/g, ' ');
                if (name.length >= 2 && name !== (e.name || '')) out.name = name;
            }
            // Only when a NEW crop is staged. An untouched sheet must not
            // re-send the photograph that is already on file.
            if (this.fixEditable('photo') && f.form.photo) {
                out.photo = f.form.photo;
            }
            if (this.fixEditable('birthdate') && (f.form.birthdate || '') !== (e.birthdate || '')) {
                out.birthdate = f.form.birthdate || null;
            }
            if (this.fixEditable('gender') && (f.form.gender || '') !== (e.gender || '')) {
                out.gender = f.form.gender || null;
            }
            /* '' is a VALUE here — competes for nobody — so this compares
               against the entry's current slug rather than testing truthiness. */
            if (this.fixEditable('club') && (f.form.club || '') !== ((e.club && e.club.slug) || '')) {
                out.club = f.form.club || '';
            }
            if (this.fixEditable('nationality') && (f.form.nationality || '') !== (e.nationality || '')) {
                out.nationality = f.form.nationality || null;
            }
            return out;
        },

        async saveFix() {
            const f = this.fix;
            if (! f || f.busy || ! f.entry) return;

            const body = this.fixPayload();
            if (! Object.keys(body).length) {
                window.showToast('info', @js(__('events.entry_edit_nothing')));
                return;
            }

            f.busy = true;
            try {
                const res = await fetch(this.entrantUrl(f.reg), {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                if (d.entry) this.seedFix(d.entry);
                window.showToast('success', d.message);
                this.closeFix();
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                if (this.fix) this.fix.busy = false;
            }
        },

        /* ===== The withdrawal queue =====

           An athlete asks to come out; the organiser decides. Granting can
           legitimately be REFUSED by the server (the competition is under way,
           or they have already fought a decided bout), so nothing here assumes
           the answer — the 422's own message is what the organiser reads. */
        withdrawals: [],
        wBusy: null,
        wResponse: {},
        {{-- Which card belongs to which athlete, so a granted withdrawal can
             drop the row it took off the list without a reload. --}}
        regByAthlete: @js(collect($participants)->filter(fn ($p) => ! empty($p['uuid']) && ! empty($p['registration']))->mapWithKeys(fn ($p) => [$p['uuid'] => $p['registration']])),

        async loadWithdrawals() {
            try {
                const res = await fetch(@js(route('testcode.me.events.withdrawals', $e['key'])), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (res.ok && d.success) this.withdrawals = d.requests || [];
            } catch (e) {
                /* A dropped fetch leaves the last good queue on screen rather
                   than emptying it — an empty queue means: nobody asked. */
            }
        },

        whenAsked(r) {
            if (! r.asked_at) return '';
            try { return new Date(r.asked_at).toLocaleString(); } catch (e) { return ''; }
        },

        async decide(r, decision) {
            if (this.wBusy) return;
            this.wBusy = r.uuid;

            const note = (this.wResponse[r.uuid] || '').trim();

            try {
                const res = await fetch(`{{ url('testcode/me/events') }}/{{ $e['key'] }}/withdrawals/${r.uuid}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ decision, response: note || null }),
                });
                const d = await res.json().catch(() => ({}));

                /* The refreshed queue comes back with BOTH answers, including a
                   refusal — patch from it either way. */
                if (Array.isArray(d.pending)) this.withdrawals = d.pending;
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                delete this.wResponse[r.uuid];

                if (decision === 'grant') {
                    const reg = r.athlete && this.regByAthlete[r.athlete.uuid];
                    if (reg) {
                        {{-- The row is the card PLUS the readiness badges that
                             straddle its top edge, and those live on the wrapper
                             around it — dropping only the card would leave two
                             pills floating over the next one. --}}
                        const card = document.getElementById('entrant-' + reg);
                        const row = (card && card.parentElement && card.parentElement.classList.contains('relative'))
                            ? card.parentElement : card;
                        row?.remove();
                        if (this.athletes > 0) this.athletes--;
                    }
                }

                window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.wBusy = null; }
        },

        /* ===== Live =====

           Somebody else's save must land here without a reload. Both signals
           are REFRESH signals: this screen re-reads what it is allowed to see
           rather than trusting a pushed payload built for another viewer.

           Deduped on window: this content is shell-swapped, so the inline
           script runs again on every AJAX navigation and the listeners would
           otherwise stack. */
        init() {
            /* The cropper reports its result globally; take only ours.
               Deduped on `window.__eventPeopleCropped` because this content is
               shell-swapped and listeners would otherwise stack. */
            if (window.__eventPeopleCropped) {
                document.removeEventListener('cropperCropped', window.__eventPeopleCropped);
            }
            window.__eventPeopleCropped = (ev) => {
                if (ev.detail && ev.detail.id === 'fixPhoto' && ev.detail.base64) {
                    this.fixCropped(ev.detail.base64);
                }
            };
            document.addEventListener('cropperCropped', window.__eventPeopleCropped);

            @if($canManage)
            this.loadWithdrawals();

            if (window.__eventPeopleRealtime) {
                window.removeEventListener('realtime:events', window.__eventPeopleRealtime);
            }
            window.__eventPeopleRealtime = (ev) => {
                const d = ev.detail || {};
                if (d.event && d.event !== @js($e['key'])) return;
                if (d.action === 'withdrawal') this.loadWithdrawals();
                if (d.action === 'entry-updated') this.onEntryUpdated(d);
            };
            window.addEventListener('realtime:events', window.__eventPeopleRealtime);
            @endif
        },

        async onEntryUpdated(d) {
            if (! d.registration) return;

            {{-- The sheet, if it is open on this very entry. --}}
            if (this.fix && String(this.fix.reg) === String(d.registration)) {
                await this.loadFix(true);
            }

            {{-- And the row's picture, which is the one part of a card bound
                 live. The rest of the card is server-rendered text and has no
                 partial to re-render from — see the note in the report. --}}
            if ((d.fields || []).includes('photo')) {
                const scope = this.cardScope(d.registration);
                if (! scope) return;
                try {
                    const res = await fetch(this.entrantUrl(d.registration), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.success) {
                        scope.photo = j.entry.photo ? j.entry.photo + '?v=' + Date.now() : null;
                        scope.owned = !! j.entry.photo;
                    }
                } catch (e) { /* the last good picture stays */ }
            }
        },
     }">

    {{-- ===== Header ===== Design Rule #6: a full-bleed hero band, the tab tray
         riding its bottom edge. pb-12 leaves exactly the tray's half-height of
         colour beneath the counts, so nothing sits on a strip of empty gradient. --}}
    <header class="m-hero px-5 pt-5 pb-12 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-3 relative z-50">
            {{-- Inside the sealed event app, back from a sub-screen means the
                     CONSOLE — the screen it was opened from. On the platform it
                     still means the event page. Same pill, honest label either
                     way (the audit: "'Event' means two different pages"). --}}
                <a href="{{ isset($shell) ? url('/e/'.$e['key'].'/admin/manage') : route('testcode.me.events.show', $e['key']) }}" data-shell-link data-route="testcode.me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline flex-shrink-0"
               aria-label="{{ $e['title'] }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            {{-- The one control on this page, and only for whoever runs the
                 event. Off by default: the roster is a reading screen, and a
                 checkbox beside every name turns reading it into a form. --}}
            @if($canManage)
                <button type="button" x-show="tab === 'athletes'"
                        @click="selecting ? stopSelecting() : startSelecting()"
                        class="m-press inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold flex-shrink-0">
                    <i class="bi" :class="selecting ? 'bi-x-lg' : 'bi-check2-square'"></i>
                    <span x-text="selecting ? @js(__('shared.cancel')) : @js(__('personal.event_people_select'))"></span>
                </button>
            @endif
        </div>

        {{-- Identity on the leading edge, the money gauge on the trailing one.
             `items-end` so the ring sits on the same baseline as the counts
             line rather than floating above the title. --}}
        <div class="relative z-10 mt-5 flex items-end justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70 truncate">{{ $e['title'] }}</p>
            <h1 class="text-2xl font-black mt-1 leading-tight">{{ __('personal.event_show_whos_joined') }}</h1>
            <p class="text-[13px] font-medium text-white/85 mt-1.5 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    {{-- Patched in place after a removal, from a phrase the SERVER
                         renders: "12 athletes" pluralises differently in the two
                         languages this platform speaks (Arabic has five forms),
                         and rebuilding it in JS would get one of them wrong. --}}
                    <i class="bi bi-person-arms-up text-white/60"></i><span id="people-athlete-count">{{ trans_choice('personal.event_people_athletes', count($participants), ['count' => count($participants)]) }}</span>
                </span>
                <span class="w-1 h-1 rounded-full bg-white/40"></span>
                <span class="inline-flex items-center gap-1.5">
                    <i class="bi bi-buildings text-white/60"></i>{{ trans_choice('personal.event_people_clubs', count($clubs), ['count' => count($clubs)]) }}
                </span>
            </p>
        </div>

        @if($money)
            @php
                /* A ring, not a bar: it reads as a proportion at a glance from
                   across a desk, and it fits in the band's spare corner without
                   taking a line from the identity block.

                   Plain SVG with a stroke-dasharray — no chart library for one
                   circle (CLAUDE.md: never add a dependency for a trivial
                   feature), and it renders identically in the WebView. */
                $r = 26;
                $circ = 2 * M_PI * $r;
                $done = $circ * min(100, max(0, $money['percent'])) / 100;
            @endphp

            <div class="flex-shrink-0 text-center" role="img"
                 aria-label="{{ __('personal.event_people_paid_of', ['paid' => $money['paid'], 'total' => $money['total']]) }}">
                <div class="relative" style="width:68px; height:68px;">
                    <svg viewBox="0 0 68 68" style="width:68px; height:68px; transform: rotate(-90deg);" aria-hidden="true">
                        <circle cx="34" cy="34" r="{{ $r }}" fill="none" stroke="rgba(255,255,255,.22)" stroke-width="7"></circle>
                        <circle cx="34" cy="34" r="{{ $r }}" fill="none" stroke="#ffffff" stroke-width="7"
                                stroke-linecap="round"
                                stroke-dasharray="{{ round($done, 2) }} {{ round($circ - $done, 2) }}"></circle>
                    </svg>
                    {{-- The percentage sits in the ring; the real numbers under
                         it. Both, because a percentage alone hides how big the
                         field is and a count alone hides how far along it is. --}}
                    <span class="absolute inset-0 grid place-items-center">
                        <span class="text-[17px] font-black leading-none">{{ $money['percent'] }}<span class="text-[10px] font-bold">%</span></span>
                    </span>
                </div>

                {{-- One line, not two: the count and what it counts belong in
                     the same breath, and two stacked lines under a ring made
                     the whole thing read taller than the identity block beside
                     it. --}}
                {{-- One line, not two: the count and what it counts belong in
                     the same breath.

                     ⚠️ `<bdi dir="ltr">` around the numbers. A slash is a
                     BIDI-NEUTRAL character, so inside an Arabic (right-to-left)
                     line the run "15 / 29" is reordered for display and comes
                     out as "29 / 15" — the paid figure and the total swapped,
                     which is not a cosmetic problem on a page about money.
                     Isolating the fraction pins it left-to-right while the
                     label beside it still reads in its own direction. --}}
                <p class="text-[11px] font-bold text-white/90 mt-1.5 leading-none whitespace-nowrap">
                    <bdi dir="ltr">{{ $money['paid'] }}<span class="text-white/55"> / {{ $money['total'] }}</span></bdi>
                    <span class="text-white/60"> {{ __('personal.event_people_paid_label') }}</span>
                </p>
            </div>
        @endif
        </div>
    </header>

    <div class="px-4 -mt-6 relative z-10 pb-8">

        {{-- ===== Tabs ===== A segmented tray floating on the band's edge. Two
             destinations, so a segmented control rather than an underline: it
             reads as a switch between two views of one roster, which is what it
             is. --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-1.5 flex gap-1.5" role="tablist">
            @foreach([
                ['key' => 'athletes', 'icon' => 'bi-person-arms-up', 'label' => __('personal.event_people_tab_athletes'), 'n' => count($participants)],
                ['key' => 'clubs', 'icon' => 'bi-buildings', 'label' => __('personal.event_people_tab_clubs'), 'n' => count($clubs)],
            ] as $t)
                <button type="button" role="tab" @click="tab = '{{ $t['key'] }}'; q = ''"
                        :aria-selected="tab === '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}'
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-muted-foreground hover:bg-muted/60'"
                        class="m-press flex-1 rounded-xl py-2.5 px-3 text-sm font-bold transition-colors flex items-center justify-center gap-2">
                    <i class="bi {{ $t['icon'] }}"></i>
                    <span>{{ $t['label'] }}</span>
                    <span class="text-[11px] font-black tabular-nums px-1.5 py-0.5 rounded-full"
                          :class="tab === '{{ $t['key'] }}' ? 'bg-white/20' : 'bg-muted'"
                          @if($t['key'] === 'athletes') x-text="athletes" @endif>{{ $t['n'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- ===== Search ===== One box over both tabs. On the athletes tab it
             also matches the club name, because "who from Emperor is here" is
             the same question asked from the other end. --}}
        @if(count($participants))
            <div class="relative mt-3">
                <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                <input type="search" x-model="q"
                       :placeholder="tab === 'athletes'
                            ? '{{ __('personal.event_people_search_athletes') }}'
                            : '{{ __('personal.event_people_search_clubs') }}'"
                       class="w-full ps-10 pe-10 py-2.5 text-sm bg-white border border-gray-100 rounded-xl shadow-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                <button type="button" x-show="q" x-cloak @click="q = ''"
                        class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="{{ __('personal.event_people_search_clear') }}">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        @endif

        {{-- ===== Asking to come out =====

             Only ever on screen when somebody is actually waiting on an answer:
             an empty section on a busy list is noise, and "nobody has asked" is
             not news. Loaded on open and refreshed over MQTT, so a request made
             while the organiser is reading this page appears without a reload.

             Granting can legitimately FAIL — the competition has started, or
             that athlete has already fought a decided bout — so nothing here
             assumes it worked: the refusal comes back as the server's own
             sentence and is read out as an error. --}}
        @if($canManage)
            <div x-show="withdrawals.length" x-cloak
                 class="mt-4 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                <div class="px-3.5 py-3 flex items-center gap-2.5 border-b border-gray-100">
                    <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 grid place-items-center flex-shrink-0">
                        <i class="bi bi-box-arrow-left"></i>
                    </span>
                    <p class="min-w-0 flex-1 text-[14px] font-black text-foreground">{{ __('events.withdraw_queue_title') }}</p>
                    <span class="text-[11px] font-black tabular-nums px-2 py-0.5 rounded-full bg-amber-50 text-amber-700"
                          x-text="withdrawals.length"></span>
                </div>

                <template x-for="r in withdrawals" :key="r.uuid">
                    <div class="p-3.5 border-b border-gray-100 last:border-0">
                        <div class="flex items-start gap-3">
                            {{-- 3:4, like every face on this platform. --}}
                            <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0 relative grid place-items-center">
                                <template x-if="r.athlete && r.athlete.photo">
                                    <img :src="r.athlete.photo" alt="" class="absolute inset-0 w-full h-full object-cover">
                                </template>
                                <template x-if="! (r.athlete && r.athlete.photo)">
                                    <span class="absolute inset-0">
                                        <span x-show="! r.athlete || r.athlete.gender !== 'Female'" class="absolute inset-0">
                                            <x-gender-avatar gender="Male" bg="hsl(250 55% 60%)" sizes="36px" class="w-full h-full" />
                                        </span>
                                        <span x-show="r.athlete && r.athlete.gender === 'Female'" class="absolute inset-0">
                                            <x-gender-avatar gender="Female" bg="#ec4899" sizes="36px" class="w-full h-full" />
                                        </span>
                                    </span>
                                </template>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-black text-foreground truncate"
                                   x-text="(r.athlete && r.athlete.name) || ''"></p>
                                {{-- Their words, when they gave any. --}}
                                <p x-show="r.reason" x-cloak class="text-[12px] text-muted-foreground mt-0.5" x-text="r.reason"></p>
                                <p class="text-[11px] text-muted-foreground mt-1" x-text="whenAsked(r)"></p>
                            </div>
                        </div>

                        <input type="text" maxlength="300" x-model="wResponse[r.uuid]"
                               placeholder="{{ __('events.withdraw_response_label') }}"
                               class="mt-2.5 w-full px-3 py-2.5 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">

                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" @click="decide(r, 'refuse')" :disabled="wBusy === r.uuid"
                                    class="m-press h-11 rounded-xl bg-muted text-foreground text-[13px] font-black disabled:opacity-50">
                                {{ __('events.withdraw_refuse') }}
                            </button>
                            <button type="button" @click="decide(r, 'grant')" :disabled="wBusy === r.uuid"
                                    class="m-press h-11 rounded-xl bg-destructive text-white text-[13px] font-black flex items-center justify-center gap-2 disabled:opacity-50">
                                <i class="bi" :class="wBusy === r.uuid ? 'bi-hourglass-split' : 'bi-box-arrow-left'"></i>
                                {{ __('events.withdraw_grant') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        @endif

        {{-- ===== Athletes ===== Each competitor is their own card, so the list
             reads as a stack of people rather than one slab. The whole card is
             the link — a name-sized tap target on a phone is not one. --}}
        {{-- `space-y-5` rather than the 10px the other lists use: every card
             here wears two readiness badges that hang 12px above its top edge,
             and at the tighter rhythm they touched the card above (2026-09-04).
             `mt-6` is the same clearance at the TOP of the list, where the
             first card's badges were touching the search box. Both gaps are the
             badge's overhang, so the three are set together. --}}
        <div x-show="tab === 'athletes'" x-cloak class="mt-6 space-y-5 mobile-stagger">
            @forelse($participants as $p)
                @php
                    /* ONE division chip, not two. The category is named for the
                       range it covers — Group D (80+) — so printing the weight
                       class beside it said the same thing twice, and the actual
                       scale reading is already on this card. The weight class is
                       kept only as a FALLBACK, for an event whose categories are
                       unnamed: better one chip than none. */
                    $division = array_filter([$p['category'] ?? $p['weight_class'] ?? null]);

                    /* Every value the component tag needs, as a plain variable:
                       Blade's component-tag compiler prints the whole tag as
                       TEXT if it meets a directive or a literal array inside the
                       attribute list, with no error to tell you. */
                    $pName = $p['name'];
                    $pUuid = $p['uuid'] ?? null;
                    $pReg = $p['registration'] ?? null;
                    $pGender = $p['gender'] ?? null;
                    $pCountry = $p['country'] ?? null;
                    $pAge = $p['age'] ?? null;
                    $pClubName = $p['club']['name'] ?? null;
                    $pClubLogo = $p['club']['logo'] ?? null;
                    $pManage = $canManage && $pReg;
                    $pHref = $pUuid ? route('people.show', $pUuid) : null;

                    /* The two readiness badges on the card's top edge. Drawn
                       only for a row the server allowed to show its status
                       (PersonalEventController::attachReadiness) — for anybody
                       else the pair is ABSENT, because a row of grey badges
                       would itself be saying something about another family's
                       fee and their child's weight. */
                    $pShowStatus = (bool) ($p['show_status'] ?? false);
                    $pFeePaid = (bool) ($p['paid'] ?? false);
                    $pWeighState = $p['weigh_state'] ?? 'none';
                    /* The reading itself, printed on the card. Same gate as the
                       badges: withheld rows carry no weight to print. */
                    $pWeight = $pShowStatus ? ($p['weight'] ?? null) : null;

                    /* What the sheet needs to know about this entry. The profile
                       URL travels with it so the sheet never has to build one. */
                    $pSheet = json_encode([
                        'reg' => $pReg,
                        'name' => $pName,
                        'club' => $pClubName,
                        'href' => $pHref,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $pShow = 'match('.json_encode($pName).', '.json_encode($p['club']['name'] ?? '').')';

                    /* Alpine expressions, composed here so the tag carries only
                       literal attributes and echoed values. */
                    $pRegJs = json_encode($pReg);
                    $pTapWhileSelecting = "if (selecting) { \$event.preventDefault(); \$event.stopPropagation(); pick({$pRegJs}); }";
                    $pRing = "selecting && isPicked({$pRegJs}) ? 'ring-2 ring-primary border-primary bg-primary/[0.06]' : ''";
                @endphp

                {{-- ===== The SHARED entrant card =====

                     The very same <x-entrant-card> the public participants page
                     renders. It used to be a card of its own here — a 48px
                     square portrait on a squat row — and the organiser, the one
                     person who actually runs the competition, got the worse of
                     the two. Now the shape is the component's and only the
                     ORGANISER'S POWERS are added on top, through its slots:
                     a selection checkbox and the put-a-face-on-this-entry
                     controls.

                     While selecting, the card stops being a door and becomes a
                     checkbox: the tap is swallowed with `capture`, BEFORE any
                     control inside can act on it, and the href is left in place
                     so it is a link again the instant selection ends. An entry
                     with no registration id cannot be picked — there is nothing
                     to send.

                     ⚠️ Alpine's `:class` / `:role` / `:aria-checked` are written
                     `::class` etc. here: inside a component tag a single colon
                     means a BOUND PHP PROP, and Blade would try to evaluate the
                     Alpine expression as PHP. --}}
                {{-- ⚠️ TWO tags, one component. Not `@if` inside a single tag:
                     a directive in a component's attribute list compiles to
                     broken PHP ("unexpected endif"), and this version of Blade
                     cannot spread an attribute bag either. So the branch is
                     OUTSIDE the tag, and the card's SHAPE is still defined in
                     exactly one place. --}}
                {{-- ===== The row's wrapper, and why it exists =====

                     The two readiness badges STRADDLE the card's top edge —
                     half of each pill above it, half below (asked for on
                     2026-09-04). <x-entrant-card> clips its own overflow, so a
                     badge hung inside it would be sliced in half; this wrapper
                     is unclipped and is what the pills are positioned against.

                     It also carries the search's `x-show`, so a row that does
                     not match hides its badges with it — the filter is on the
                     wrapper rather than on the card for exactly that reason.
                     The card keeps its own `x-show` too: harmless, and it means
                     the card is still filterable on its own wherever else it is
                     used. --}}
                <div class="relative" x-show="{{ $pShow }}" x-cloak>
                    @if($pShowStatus)
                        {{-- Your OWN row shows its badges to you as well:
                             seeing that your fee is outstanding is the one
                             status a competitor is entitled to. Everybody
                             else's row has no badges AT ALL rather than grey
                             ones — see attachReadiness(). --}}
                        <x-entrant-readiness-badges :paid="$pFeePaid" :weigh="$pWeighState" />
                    @endif

                @if($pManage)
                    <x-entrant-card
                        :name="$pName" :gender="$pGender" :country="$pCountry" :age="$pAge"
                        :divisions="$division" :weight="$pWeight" :weight-tone="$pWeighState"
                        :belt="$p['belt'] ?? null" :belt-grade="$p['belt_grade'] ?? null"
                        :club-name="$pClubName" :club-logo="$pClubLogo"
                        :show="$pShow" :chevron="true"
                        chevron-show="! selecting"
                        :id="'entrant-'.$pReg"
                        :pick="'openActions('.$pSheet.')'"
                        @click.capture="{{ $pTapWhileSelecting }}"
                        ::class="{{ $pRing }}"
                        ::aria-checked="selecting ? isPicked({{ $pRegJs }}) : null"
                        ::role="selecting ? 'checkbox' : null"
                        x-data="competitorPhoto({ event: {{ json_encode($e['key']) }}, registration: {{ $pRegJs }}, photo: {{ json_encode($p['photo']) }}, owned: {{ json_encode((bool) $p['has_entry_photo']) }} })">

                        {{-- The checkbox. A real square box rather than a tick
                             that only exists once chosen: an empty box is what
                             says "this row can be picked". Leading the card,
                             because a list of checkboxes is read down its
                             leading edge. --}}
                        {{-- The checkbox sits on the TRAILING edge: right in
                             English, left in Arabic — asked for on 2026-09-03.
                             It reads as the row's answer rather than its bullet,
                             and it takes the chevron's place, so a card is a
                             door OR a checkbox and never both at once. --}}
                        <x-slot:trailing>
                            <span x-show="selecting" x-cloak
                                  x-transition:enter="transition ease-out duration-150"
                                  x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                  class="w-6 h-6 rounded-md border-2 grid place-items-center flex-shrink-0 self-center transition-colors"
                                  :class="isPicked({{ $pRegJs }}) ? 'bg-primary border-primary text-white' : 'border-gray-300 bg-white'">
                                <i class="bi bi-check-lg text-[13px] font-black" x-show="isPicked({{ $pRegJs }})" x-cloak></i>
                            </span>
                        </x-slot:trailing>

                        {{-- The picture is live: an organiser can add or clear it
                             without leaving the page. --}}
                        <x-slot:picture>
                            {{-- `x-on:error`, NOT `@error` — Blade would eat that as
                                 its validation directive. A path whose FILE has gone
                                 (an entry photo deleted while three tables still named
                                 it) used to paint the browser's broken-image glyph on
                                 a competitor card; dropping `photo` hands the portrait
                                 to the drawn stand-in below, which is what an absent
                                 picture is supposed to look like. --}}
                            <template x-if="photo">
                                <img :src="photo" alt="" x-on:error="photo = null"
                                     class="absolute inset-0 w-full h-full object-cover">
                            </template>
                            <template x-if="! photo">
                                <span class="absolute inset-0">
                                    <x-gender-avatar :gender="$pGender"
                                                     :bg="($pGender ?? 'Male') === 'Male' ? 'hsl(250 55% 60%)' : '#ec4899'"
                                                     sizes="66px" class="w-full h-full" />
                                </span>
                            </template>
                        </x-slot:picture>

{{-- ⚠️ NOTHING on the photograph. There used to be a camera badge
                             and a ✕ pinned to the thumbnail — two 24px targets in
                             front of the picture they edit, on a card that is
                             also a tap target. Both moved into the sheet the card
                             now opens (asked for on 2026-09-03). --}}
                    </x-entrant-card>
                @else
                    <x-entrant-card
                        :name="$pName" :photo="$p['photo']" :gender="$pGender"
                        :country="$pCountry" :age="$pAge" :divisions="$division"
                        :weight="$pWeight" :weight-tone="$pWeighState"
                        :club-name="$pClubName" :club-logo="$pClubLogo"
                        :href="$pHref" :show="$pShow" :chevron="(bool) $pUuid" />
                @endif
                </div>
            @empty
                @include('eventlab::personal.partials.event-people-empty', [
                    'icon' => 'bi-person-arms-up',
                    'title' => __('personal.event_people_none_athletes'),
                    'body' => __('personal.event_people_none_athletes_sub'),
                ])
            @endforelse

            {{-- ===== One entry's actions =====

                 A bottom sheet on the band every sheet in this project opens
                 with (Design Rule #8): the subject's own colour, the drag
                 handle that says "this is a sheet", an icon tile, the person as
                 the headline and their club beneath it, and the ✕ as the only
                 close control — no footer, because there is nothing here to
                 submit.

                 Teleported to <body>: the list above carries `mobile-stagger`,
                 whose entrance animation leaves a transform on every child, and
                 a transformed ancestor becomes the containing block for
                 anything `position: fixed` — the sheet would resolve `bottom-0`
                 against a card instead of the viewport and be clipped. --}}
            @if($canManage)
                {{-- The fix sheet's own door. Separate from the action sheet's
                     because the two behave differently on purpose: that one
                     uploads immediately (it is a one-tap "change picture"),
                     this one CROPS and stages until Save. --}}
                <input x-ref="fixPhotoInput" type="file" accept="image/*" class="hidden"
                       @change="fixPhotoPicked($event)">

                {{-- The one cropper. Inline (the mobile bottom sheet) per
                     CLAUDE.md "One Cropper Everywhere", and 600x800 because
                     every face on this platform is portrait 3:4. `mode="form"`
                     so it hands the crop back as a data URI instead of
                     uploading it — the staging this whole change is about. --}}
                <div class="hidden">
                    <x-takeone-cropper
                        id="fixPhoto" mode="form" :inline="true"
                        :width="600" :height="800" shape="rectangle" :canvasHeight="360"
                        folder="temp" filename="entrant" inputName="photo"
                        sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
                        :showControls="false" :showCancel="false"
                        saveText="{{ __('shared.save') }}" />
                </div>

                <template x-teleport="body">
                    <div x-show="actions" x-cloak class="fixed inset-0 z-[60]"
                         @keydown.escape.window="closeActions()" style="display:none;">
                        <div x-show="actions" x-transition.opacity @click="closeActions()"
                             class="absolute inset-0 bg-black/50"></div>

                        <div x-show="actions"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                            {{-- The band. ⚠️ `#hex + b0` — the alpha suffix is
                                 hex-only, and an `hsl()` here would drop the
                                 whole gradient (Design Rule #8). --}}
                            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                                 style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
                                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                                <div class="relative flex items-start gap-3">
                                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                        <i class="bi bi-person-arms-up text-xl"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-lg font-black leading-tight truncate" x-text="actions?.name"></h3>
                                        <p class="text-[12px] text-white/85 mt-0.5 truncate"
                                           x-text="actions?.club || @js(__('personal.event_people_actions_hint'))"></p>
                                    </div>
                                    <button type="button" @click="closeActions()" aria-label="{{ __('shared.close') }}"
                                            class="ev-ico w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto px-4 pt-4 space-y-2.5"
                                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                                {{-- 1 · the picture, which is what a desk asks for most --}}
                                {{-- "Change picture" was removed 2026-09-05. It uploaded the
                                     file the instant it was picked — no crop, no Save, and
                                     Cancel could not undo it. The photograph now lives in
                                     "Edit info", where it is cropped and staged like every
                                     other field and only written when Save is pressed. --}}
                                {{-- 2 · their profile, when they have one to open --}}
                                <button type="button" x-show="actions?.href" @click="goTo(actions.href)"
                                        class="m-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border border-gray-100">
                                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                                        <i class="bi bi-person-vcard text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-black text-foreground">{{ __('personal.event_people_action_edit') }}</span>
                                        <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('personal.event_people_action_edit_hint') }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs"></i>
                                </button>

                                {{-- 3 · the verification desk, on this entry --}}
                                <button type="button" x-show="@js((bool) (($canWeigh ?? false) || ($canPay ?? false)))"
                                        @click="const r = actions.reg; closeActions(); $nextTick(() => window.dispatchEvent(new CustomEvent('open-verification', { detail: { entry: r } })))"
                                        class="m-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border border-gray-100">
                                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                                        <i class="bi bi-clipboard2-check-fill text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-black text-foreground">{{ __('personal.event_people_action_verify') }}</span>
                                        <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('personal.event_people_action_verify_hint') }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs"></i>
                                </button>

                                {{-- 4 · correcting the entry itself. Everything the
                                     athlete would fix on their own panel, from the
                                     side of the desk that has to run the draw. --}}
                                <button type="button" @click="openFix(actions)"
                                        class="m-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border border-gray-100">
                                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                                        <i class="bi bi-pencil-square text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-black text-foreground">{{ __('events.entry_edit_title_theirs') }}</span>
                                        <span class="block text-[12px] text-muted-foreground mt-0.5">{{ __('events.entry_edit_action_hint') }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs"></i>
                                </button>

                                {{-- 5 · taking them off the list, last and in red --}}
                                <button type="button" @click="removeOne()" :disabled="busy"
                                        class="m-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border border-red-100">
                                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-red-50 text-red-600">
                                        <i class="bi bi-person-dash-fill text-lg"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[14px] font-black text-red-600">{{ __('personal.event_people_action_remove') }}</span>
                                        <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('personal.event_people_action_remove_hint') }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- ===== Fixing one entry =====

                     The organiser's twin of the athlete's own panel: the same
                     editor, the same rules, a different voice. Design Rule #8's
                     band (`#hex + b0` — hex only), teleported to <body> for the
                     same transform reason as the sheet above, a scrolling body
                     and one sticky Save on the safe area.

                     Date of birth and gender are the PERSON, not the entry, so
                     for a real member they come back locked with the reason
                     written beside them. They are drawn locked rather than
                     hidden on purpose: an organiser who cannot find the field
                     assumes the screen is broken, where one that says whose it
                     is to change gets a phone call to the right person. --}}
                <template x-teleport="body">
                    <div x-show="fix" x-cloak class="fixed inset-0 z-[60]"
                         @keydown.escape.window="closeFix()" style="display:none;">
                        <div x-show="fix" x-transition.opacity @click="closeFix()"
                             class="absolute inset-0 bg-black/50"></div>

                        <div x-show="fix"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                                 style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
                                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                                <div class="relative flex items-start gap-3">
                                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                        <i class="bi bi-pencil-square text-xl"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-lg font-black leading-tight">{{ __('events.entry_edit_title_theirs') }}</h3>
                                        <p class="text-[12px] text-white/85 mt-0.5 truncate"
                                           x-text="(fix && fix.name) ? fix.name : @js(__('events.entry_edit_subtitle_theirs'))"></p>
                                    </div>
                                    <button type="button" @click="closeFix()" aria-label="{{ __('shared.close') }}"
                                            class="ev-ico w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto px-4 pt-4 pb-4 space-y-3">

                                <div x-show="fix && fix.loading" class="py-12 text-center text-muted-foreground">
                                    <i class="bi bi-hourglass-split text-2xl"></i>
                                </div>

                                {{-- x-if rather than x-show: nothing inside may
                                     evaluate `fix.entry.…` before there is one. --}}
                                <template x-if="fix && fix.entry && ! fix.loading">
                                    <div class="space-y-3">

                                        {{-- The picture. Same flow as the card's own
                                             "Change picture" — one uploader on this
                                             screen, not two. --}}
                                        {{-- The NAME, first. It is what the draw sheet, the hall
                                             screen and the medal are engraved from, and a misspelling
                                             is the correction an organiser makes most. Editable by an
                                             organiser deliberately: unlike a date of birth it moves
                                             nothing — no age group, no weight class, no safeguarding
                                             rule — it is just the person's name spelled right. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground">{{ __('events.entry_field_name') }}</label>

                                            <template x-if="fixEditable('name')">
                                                <input type="text" x-model="fix.form.name" maxlength="120"
                                                       class="mt-2 w-full px-3 py-2.5 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                                            </template>

                                            <template x-if="! fixEditable('name')">
                                                <div class="flex items-start gap-2 mt-2">
                                                    <span class="text-[13px] font-black text-foreground flex-1" x-text="fix.entry.name || '—'"></span>
                                                    <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-black text-muted-foreground flex-shrink-0">
                                                        <i class="bi bi-lock-fill"></i> {{ __('events.entry_edit_locked') }}
                                                    </span>
                                                </div>
                                            </template>

                                            <p x-show="fixEditable('name') && (fix.form.name || '').trim().length < 2" x-cloak
                                               class="text-[12px] text-amber-700 mt-2">{{ __('events.entry_name_min') }}</p>
                                        </div>

                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5 flex items-center gap-3">
                                            <span class="w-12 h-16 rounded-xl overflow-hidden bg-muted flex-shrink-0 relative grid place-items-center">
                                                {{-- A crop staged but not yet saved wins the preview, so the
                                                     organiser sees what Save will write — not what is still
                                                     on file. --}}
                                                <template x-if="fix.form.photo">
                                                    <img :src="fix.form.photo" alt="" class="absolute inset-0 w-full h-full object-cover">
                                                </template>
                                                <template x-if="! fix.form.photo && fix.entry.photo">
                                                    <img :src="fix.entry.photo" alt="" class="absolute inset-0 w-full h-full object-cover">
                                                </template>
                                                <template x-if="! fix.form.photo && ! fix.entry.photo">
                                                    <span class="absolute inset-0">
                                                        <span x-show="fix.entry.gender !== 'Female'" class="absolute inset-0">
                                                            <x-gender-avatar gender="Male" bg="hsl(250 55% 60%)" sizes="48px" class="w-full h-full" />
                                                        </span>
                                                        <span x-show="fix.entry.gender === 'Female'" class="absolute inset-0">
                                                            <x-gender-avatar gender="Female" bg="#ec4899" sizes="48px" class="w-full h-full" />
                                                        </span>
                                                    </span>
                                                </template>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[13px] font-black text-foreground">{{ __('events.entry_field_photo') }}</p>
                                                <p class="text-[12px] text-muted-foreground mt-0.5">{{ __('personal.event_people_action_photo_hint') }}</p>
                                            
                                                {{-- Pending until Save, and reversible. --}}
                                                <p x-show="fix.form.photo" x-cloak class="text-[12px] font-bold mt-1" style="color:#b45309;">
                                                    <i class="bi bi-clock-history"></i>
                                                    {{ __('personal.event_photo_staged') }}
                                                    <button type="button" @click="fixDropStaged()" class="underline font-black ms-1">{{ __('shared.clear') }}</button>
                                                </p>
                                            </div>
                                            <button type="button" @click="fixPicture()"
                                                    class="m-press h-9 px-3 rounded-xl bg-accent text-primary text-[12px] font-black flex-shrink-0">
                                                <i class="bi bi-camera-fill"></i>
                                            </button>
                                        </div>

                                        {{-- Weight, and which kind of weight it is:
                                             a figure the athlete typed is not the
                                             same fact as one an official signed. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground">{{ __('events.entry_field_weight') }}</label>
                                            <p class="text-[12px] text-muted-foreground mt-0.5"
                                               x-text="fix.entry.weighed_in ? @js(__('events.entry_weighed_in')) : @js(__('events.entry_self_declared'))"></p>
                                            <div class="relative mt-2">
                                                <input type="number" inputmode="decimal" step="0.1" min="15" max="250"
                                                       x-model="fix.form.weight" :disabled="! fixEditable('weight')"
                                                       class="w-full px-3 py-2.5 pe-12 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-60">
                                                <span class="absolute end-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">{{ __('personal.division_kg') }}</span>
                                            </div>
                                            <p x-show="! fixEditable('weight')" x-cloak
                                               class="text-[12px] text-amber-700 mt-2" x-text="fixReason('weight')"></p>
                                        </div>

                                        {{-- Belt: the one ladder, as chips, plus the
                                             free-text grade the ladder cannot hold. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground">{{ __('events.entry_field_belt') }}</label>
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <template x-for="c in (fix.entry.belts || [])" :key="c.value">
                                                    {{-- The chosen belt has to be UNMISTAKABLE.

                                                         It used to be marked with `border-current` alone: a 2px
                                                         border in the chip's own text colour. On a white belt —
                                                         which most entrants are — that is a dark hairline round a
                                                         near-white pill sitting beside pills at 70% opacity, and
                                                         organisers read it as nothing being selected at all. The
                                                         value WAS being seeded and saving changed nothing; it
                                                         simply could not be seen.

                                                         Now: a white-then-dark halo that reads on any chip colour
                                                         (a plain ring vanishes on whichever belt matches it), full
                                                         opacity, a slight lift, and a tick — so it does not depend
                                                         on colour alone. --}}
                                                    <button type="button" @click="fixPickBelt(c.value)"
                                                            :disabled="! fixEditable('belt_colour')"
                                                            :style="`background:${c.bg}; color:${c.fg};` + (fix.form.belt_colour === c.value
                                                                ? ' box-shadow: 0 0 0 2px #fff, 0 0 0 4px ' + c.bg + ', 0 6px 14px -6px rgba(15,23,42,.5); transform: translateY(-1px);'
                                                                : '')"
                                                            :class="fix.form.belt_colour === c.value ? 'opacity-100' : 'opacity-60'"
                                                            :aria-pressed="fix.form.belt_colour === c.value"
                                                            class="m-press inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-black disabled:opacity-50 transition-transform">
                                                        <i class="bi bi-check-lg" x-show="fix.form.belt_colour === c.value" x-cloak></i>
                                                        <span x-text="c.label"></span>
                                                    </button>
                                                </template>
                                            </div>
                                            <input type="text" x-model="fix.form.belt_grade" maxlength="32"
                                                   :disabled="! fixEditable('belt_grade')"
                                                   placeholder="{{ __('events.entry_field_grade') }}"
                                                   class="mt-2.5 w-full px-3 py-2.5 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent disabled:opacity-60">
                                        </div>

                                        {{-- The DIVISION stays read-only: it is derived
                                             from the draw screen, which owns it. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <div class="flex items-center gap-2">
                                                <i class="bi bi-diagram-3 bracket-icon text-muted-foreground"></i>
                                                <span class="text-[12px] font-bold text-muted-foreground flex-1">{{ __('events.entry_field_division') }}</span>
                                                <span class="text-[13px] font-black text-foreground text-end"
                                                      x-text="fix.entry.division || @js(__('events.entry_no_division_yet'))"></span>
                                            </div>
                                        </div>

                                        {{-- The CLUB this entry competes for. `EntryEditor::applyClub`
                                             has always accepted it; this sheet was the one place that
                                             showed it as a fact instead of a choice. The list is the
                                             entry's current club, the athlete's own clubs and the clubs
                                             already at this event — not every club on the platform,
                                             which is a search rather than a picker.

                                             "Unattached" is a real answer, not an empty one: plenty of
                                             entrants compete for nobody. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground mb-2">{{ __('events.entry_field_club') }}</label>

                                            <template x-if="fixEditable('club')">
                                                <div class="space-y-1.5">
                                                    <button type="button" @click="fix.form.club = ''"
                                                            class="m-press w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 text-start transition-colors"
                                                            :class="! fix.form.club ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                                        <i class="bi bi-person-x text-muted-foreground"></i>
                                                        <span class="text-[13px] font-bold text-foreground flex-1">{{ __('events.entry_no_club') }}</span>
                                                        <i class="bi bi-check-lg text-primary" x-show="! fix.form.club"></i>
                                                    </button>

                                                    <template x-for="c in (fix.entry.clubs || [])" :key="c.slug">
                                                        <button type="button" @click="fix.form.club = c.slug"
                                                                class="m-press w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 text-start transition-colors"
                                                                :class="fix.form.club === c.slug ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                                            {{-- Design Rule #5: the bare mark, never a white tile. --}}
                                                            <span class="w-5 h-5 flex-shrink-0 grid place-items-center">
                                                                <template x-if="c.logo">
                                                                    <img :src="c.logo" alt="" class="w-full h-full object-contain">
                                                                </template>
                                                                <template x-if="! c.logo">
                                                                    <i class="bi bi-buildings text-muted-foreground"></i>
                                                                </template>
                                                            </span>
                                                            <span class="text-[13px] font-bold text-foreground flex-1 truncate" x-text="c.name"></span>
                                                            <i class="bi bi-check-lg text-primary" x-show="fix.form.club === c.slug"></i>
                                                        </button>
                                                    </template>
                                                </div>
                                            </template>

                                            <template x-if="! fixEditable('club')">
                                                <div class="flex items-start gap-2">
                                                    <span class="text-[13px] font-black text-foreground flex-1"
                                                          x-text="(fix.entry.club && fix.entry.club.name) || @js(__('events.entry_no_club'))"></span>
                                                    <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-black text-muted-foreground flex-shrink-0">
                                                        <i class="bi bi-lock-fill"></i> {{ __('events.entry_edit_locked') }}
                                                    </span>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Date of birth. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground mb-2">{{ __('events.entry_field_birthdate') }}</label>

                                            {{-- The platform's own date-of-birth control: day ·
                                                 month · year with the live age, horoscope and
                                                 age-group badges under it. Bound to the sheet's
                                                 Alpine state rather than posting a hidden input
                                                 (its `model` mode), and its `maxAge`/`minAge`
                                                 window is what keeps a competitor's year sane. --}}
                                            <template x-if="fixEditable('birthdate')">
                                                <div>
                                                    <x-birthdate-dropdown model="fix.form.birthdate"
                                                                          id="fixBirthdate"
                                                                          label=""
                                                                          :min-age="3" :max-age="99" />

                                                    {{-- 13 of this event's 29 entrants have no date on
                                                         file at all — a birthdate is never required of
                                                         anyone (CLAUDE.md). Three empty boxes read as a
                                                         control that failed to load, so say which it is. --}}
                                                    <p x-show="! fix.entry.birthdate" x-cloak
                                                       class="text-[12px] text-muted-foreground mt-1.5">
                                                        <i class="bi bi-info-circle"></i>
                                                        {{ __('events.entry_birthdate_not_on_file') }}
                                                    </p>
                                                </div>
                                            </template>

                                            <template x-if="! fixEditable('birthdate')">
                                                <div class="flex items-start gap-2">
                                                    <span class="text-[13px] font-black text-foreground flex-1"
                                                          x-text="fix.entry.birthdate || '—'"></span>
                                                    <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-black text-muted-foreground flex-shrink-0">
                                                        <i class="bi bi-lock-fill"></i> {{ __('events.entry_edit_locked') }}
                                                    </span>
                                                </div>
                                            </template>

                                            <p x-show="! fixEditable('birthdate')" x-cloak
                                               class="text-[12px] text-muted-foreground mt-2" x-text="fixReason('birthdate')"></p>
                                        </div>

                                        {{-- Gender. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground mb-2">{{ __('events.entry_field_gender') }}</label>

                                            {{-- The platform's own gender selector.
                                                 ⚠️ It replaces a gender-toggle whose labels were
                                                 passed as `male-label="@ js(__('…'))"` — Blade does NOT
                                                 compile a directive inside a component-tag attribute
                                                 (the trap this file already documents), so the two
                                                 buttons shipped with the literal expression as their
                                                 x-text and rendered with no words at all. --}}
                                            <template x-if="fixEditable('gender')">
                                                <div>
                                                    <x-gender-dropdown model="fix.form.gender"
                                                                       id="fixGender"
                                                                       label="" />
                                                </div>
                                            </template>

                                            <template x-if="! fixEditable('gender')">
                                                <div class="flex items-start gap-2">
                                                    <span class="text-[13px] font-black text-foreground flex-1"
                                                          x-text="fix.entry.gender || '—'"></span>
                                                    <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-black text-muted-foreground flex-shrink-0">
                                                        <i class="bi bi-lock-fill"></i> {{ __('events.entry_edit_locked') }}
                                                    </span>
                                                </div>
                                            </template>

                                            <p x-show="! fixEditable('gender')" x-cloak
                                               class="text-[12px] text-muted-foreground mt-2" x-text="fixReason('gender')"></p>
                                        </div>

                                        {{-- Nationality. `EntryEditor` has always accepted it
                                             (FIELDS, and an ISO-2 validated server-side) and
                                             `present()` has always returned it — there was simply
                                             no control, so the answer could be read on a card and
                                             not corrected anywhere.

                                             The platform's searchable country picker, in its
                                             inline two-way mode: 200 rows need a search, and the
                                             value arrives with the fetched record after the
                                             control has mounted. --}}
                                        <div class="bg-white rounded-2xl border border-gray-100 p-3.5">
                                            <label class="block text-[13px] font-black text-foreground mb-2">{{ __('events.entry_field_nationality') }}</label>

                                            <template x-if="fixEditable('nationality')">
                                                <div>
                                                    <x-country-dropdown model="fix.form.nationality"
                                                                        :inline="true"
                                                                        id="fixNationality"
                                                                        label=""
                                                                        wrapper-class="" />
                                                </div>
                                            </template>

                                            <template x-if="! fixEditable('nationality')">
                                                <div class="flex items-start gap-2">
                                                    <span class="text-[13px] font-black text-foreground flex-1"
                                                          x-text="fix.entry.nationality || '—'"></span>
                                                    <span class="px-2 py-0.5 rounded-full bg-muted text-[11px] font-black text-muted-foreground flex-shrink-0">
                                                        <i class="bi bi-lock-fill"></i> {{ __('events.entry_edit_locked') }}
                                                    </span>
                                                </div>
                                            </template>

                                            <p x-show="! fixEditable('nationality')" x-cloak
                                               class="text-[12px] text-muted-foreground mt-2" x-text="fixReason('nationality')"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- One thing to submit, so one footer. --}}
                            <div class="flex-shrink-0 px-4 pt-3 bg-white border-t border-gray-100"
                                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                                <button type="button" @click="saveFix()"
                                        :disabled="! fix || fix.busy || fix.loading || ! fix.entry"
                                        class="m-press w-full h-12 rounded-xl bg-primary text-white text-sm font-black flex items-center justify-center gap-2 disabled:opacity-50">
                                    <i class="bi" :class="(fix && fix.busy) ? 'bi-hourglass-split' : 'bi-check2'"></i>
                                    {{ __('shared.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif

            {{-- ===== The verification sheet, in place =====

                 The SAME sheet the verification desk opens — included from the
                 same partial in `sheetOnly` mode rather than copied, so the
                 weigh-in, the payment and the receipt have one implementation
                 and two places they can be opened from. Tapping a card here and
                 choosing the desk opens it over this list; nobody is sent to
                 another screen to sign one weight.

                 Wrapped in `partials.event-show-script` because that is the
                 scope the sheet's moderation actions live in — the same wrapper
                 the verification page gives it. --}}
            @if(($canWeigh ?? false) || ($canPay ?? false))
                @php
                    /* `partials.event-show-script` is the shared event-show Alpine
                       state, and it is the scope the sheet's moderation actions
                       live in. It is written for the event DETAIL page, so the
                       handful of scalars that page passes it are defaulted here
                       rather than the partial being loosened — this list only
                       needs the sheet, not the join flow. */
                    $hasTicket = $hasTicket ?? false;
                    $byQual = $byQual ?? false;
                    $banned = $banned ?? false;
                    $eligReason = $eligReason ?? null;
                    $finance = $finance ?? null;
                    $entriesOpen = $entriesOpen ?? false;
                    $entriesNote = $entriesNote ?? null;
                    $manual_results = $manual_results ?? false;
                    $stage = $stage ?? null;
                    $representing = $representing ?? null;
                    $pPaid = $pPaid ?? false;
                    $ticketPaid = $ticketPaid ?? false;
                    $whyNot = $whyNot ?? null;
                @endphp
                <div @include('eventlab::partials.event-show-script')>
                    @include('eventlab::personal.partials.event-people', [
                        'sheetOnly' => true,
                        'hasTicket' => false,
                        'byQual' => [],
                    ])
                </div>
            @endif

            {{-- Search found nothing. Distinct from an empty roster: one means
                 "try another spelling", the other means "nobody has entered". --}}
            <div x-show="empty && tab === 'athletes'" x-cloak>
                @include('eventlab::personal.partials.event-people-empty', [
                    'icon' => 'bi-search',
                    'title' => __('personal.event_people_no_matches'),
                    'body' => __('personal.event_people_no_matches_sub'),
                ])
            </div>
        </div>

        {{-- ===== Clubs ===== Crest-forward: at a championship the crest is how
             a club is recognised across a hall, so it leads the row at a size
             worth reading. Biggest squad first — RosterPeople sorts it. --}}
        <div x-show="tab === 'clubs'" x-cloak class="mt-3 space-y-2.5 mobile-stagger">
            @forelse($clubs as $club)
                @php $cFlag = $flagClass($club['country']); @endphp

                <a @if($club['href']) href="{{ $club['href'] }}" @endif
                    x-show="match(@js($club['name']))"
                    class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3.5 {{ $club['href'] ? 'hover:shadow-md transition-shadow' : '' }}">

                    {{-- Sizing box only — no fill, no ring, no padding tile. --}}
                    <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                        @if($club['logo'])
                            <img src="{{ $club['logo'] }}" alt="" class="w-full h-full object-contain">
                        @else
                            <i class="bi bi-buildings text-2xl text-muted-foreground"></i>
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-black text-[15px] text-foreground leading-tight truncate">{{ $club['name'] }}</p>
                        <p class="text-xs text-muted-foreground mt-1 flex items-center gap-2">
                            @if($cFlag)
                                <span class="{{ $cFlag }} w-4 h-3 rounded-[2px] shrink-0"></span>
                            @endif
                            <span class="inline-flex items-center gap-1">
                                <i class="bi bi-person-arms-up"></i>
                                {{ trans_choice('personal.event_people_athletes', $club['athletes'], ['count' => $club['athletes']]) }}
                            </span>
                        </p>
                    </div>

                    @if($club['href'])
                        <i class="bi bi-chevron-right text-muted-foreground flex-shrink-0"></i>
                    @endif
                </a>
            @empty
                @include('eventlab::personal.partials.event-people-empty', [
                    'icon' => 'bi-buildings',
                    'title' => __('personal.event_people_none_clubs'),
                    'body' => __('personal.event_people_none_clubs_sub'),
                ])
            @endforelse

            <div x-show="empty && tab === 'clubs'" x-cloak>
                @include('eventlab::personal.partials.event-people-empty', [
                    'icon' => 'bi-search',
                    'title' => __('personal.event_people_no_matches'),
                    'body' => __('personal.event_people_no_matches_sub'),
                ])
            </div>
        </div>
    </div>

@if($canManage)
    {{-- ===== The action bar =====
         Teleported to <body>: the mobile shell's #shell-content carries
         .mobile-stagger, whose animation leaves a transform on every direct
         child — and a transformed ancestor becomes the containing block for a
         `fixed` descendant, so an untelported bar would pin itself to a wrapper
         instead of the viewport. z-[60] clears the bottom tab bar (z-40), and
         the padding carries the safe area.

         It appears only when something is picked. A bar that is present and
         disabled is a bar the thumb learns to ignore. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="selecting && picked.length" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="fixed inset-x-0 bottom-0 z-[60] bg-white border-t border-gray-100 shadow-2xl px-4 pt-3"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <div class="mx-auto w-full max-w-lg flex items-center gap-3">
                <span class="min-w-0 flex-1 text-sm font-bold text-foreground"
                      x-text="@js(__('personal.event_people_selected')).replace(':count', picked.length)"></span>

                <button type="button" @click="stopSelecting()" :disabled="busy"
                        class="m-press h-11 px-4 rounded-xl text-sm font-bold text-muted-foreground flex-shrink-0 disabled:opacity-50">
                    {{ __('shared.cancel') }}
                </button>

                <button type="button" @click="removePicked()" :disabled="busy"
                        class="m-press h-11 px-5 rounded-xl bg-destructive text-white text-sm font-black flex items-center gap-2 flex-shrink-0 disabled:opacity-50">
                    <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-person-dash-fill'"></i>
                    {{ __('personal.event_people_remove') }}
                </button>
            </div>
        </div>
    </template>
@endif
</div>
@endsection

@push('scripts')
<script>
/**
 * A face for one entry.
 *
 * Reads the chosen file in the browser and posts it as a data URI, which is the
 * shape every image endpoint in this project takes — the server sniffs the real
 * bytes and assigns the extension itself, so nothing here decides what the file
 * is. Patches its own row on success; no reload.
 */
function competitorPhoto(config) {
    return {
        photo: config.photo,
        owned: config.owned,
        busy: false,

        async upload(input) {
            const file = input.files && input.files[0];
            if (!file) return;

            // Ten megabytes of camera photo is a phone's full-resolution shot;
            // refused here so the upload does not travel before being rejected.
            if (file.size > 10 * 1024 * 1024) {
                window.showToast && window.showToast('error', @js(__('personal.event_photo_too_big')));
                input.value = '';
                return;
            }

            this.busy = true;

            try {
                const dataUrl = await new Promise((resolve, reject) => {
                    const r = new FileReader();
                    r.onload = () => resolve(r.result);
                    r.onerror = () => reject(new Error('read failed'));
                    r.readAsDataURL(file);
                });

                const res = await fetch(`/testcode/me/events/${config.event}/competitors/${config.registration}/photo`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ image: dataUrl }),
                });
                const data = await res.json().catch(() => ({}));

                if (!data.success) throw new Error(data.message || 'Upload failed');

                // Cache-busted: the path changes per upload, but a replaced photo
                // at the same size would otherwise look unchanged behind a proxy.
                this.photo = data.photo + '?v=' + Date.now();
                this.owned = true;
                window.showToast && window.showToast('success', data.message);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.busy = false;
                input.value = '';
            }
        },

        async clear() {
            if (window.confirmAction && !(await window.confirmAction({
                title: @js(__('personal.event_photo_remove')),
                message: @js(__('personal.event_photo_remove_confirm')),
                type: 'danger',
            }))) return;

            this.busy = true;

            try {
                const res = await fetch(`/testcode/me/events/${config.event}/competitors/${config.registration}/photo`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));

                if (!data.success) throw new Error(data.message || 'Failed');

                // Back to whatever their profile allows — which is usually the
                // silhouette, and that is the honest answer.
                this.photo = null;
                this.owned = false;
                window.showToast && window.showToast('success', data.message);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
