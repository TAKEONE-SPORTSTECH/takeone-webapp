{{--
    Sparring console — the behaviour, shared by both layouts.

    One Alpine component for the phone and the laptop, because the console is
    the same loop on both and two copies would drift. Included INLINE by the
    mobile console (the mobile shell re-runs inline scripts on every AJAX
    navigation and would never run a pushed stack) and pushed to `scripts` by
    the desktop one.

    Expects, from the including view: $e (the event view model) and $sp (the
    package's own `sparring` view data).
--}}
@php
    // Precomputed on purpose. Blade's bracket-matcher cannot parse an array
    // literal inside @json(...) (see CLAUDE.md), and these all carry one — a
    // :n placeholder the component fills in per count, and two routes built
    // with parameters.
    $spEventKey   = $e['key'];
    $spActionBase = \Illuminate\Support\Str::beforeLast(route('me.events.action', [$spEventKey, 'x'], false), 'x');
    $spStateUrl   = route('me.events.sparring', $spEventKey, false);
    $spMatCount   = __('event-sparring::messages.console_mat_count', ['n' => ':n']);
    $spBoutsToday = __('event-sparring::messages.console_bouts_today', ['n' => ':n']);
    $spTable      = __('event-sparring::messages.console_open_table');
    $spAddLabel   = __('event-sparring::messages.console_add_selected', ['n' => ':n']);
    $spEndTitle   = __('event-sparring::messages.console_end_session');
    $spEndConfirm = __('event-sparring::messages.console_end_confirm');
    $spFailed     = __('events.action_unsupported');
@endphp

<script>
function sparringConsole() {
    return {
        // Server truth, patched in place by every write below.
        mats: @json($sp['mats']),
        entrants: @json($sp['entrants']),
        bouts: @json($sp['bouts']),
        members: @json($sp['club_members']),
        controlUrls: @json($sp['control_urls']),
        readyMats: @json($sp['ready_mats'] ?? []),
        closed: @json((bool) $sp['closed']),
        mat: @json($sp['mats'][0] ?? 'Mat 1'),
        aka: null,
        ao: null,
        peopleSheet: false,
        search: '',
        chosen: [],
        busy: false,
        actionBase: @json($spActionBase),

        init() {
            // Another coach queued something, or a mat committed a result.
            // A refresh signal, not a payload: what each console may see
            // differs, so each one re-reads its own page.
            this.onRealtime = (ev) => {
                if ((ev.detail?.action === 'sparring' || ev.detail?.action === 'mat')
                    && ev.detail?.event === @json($spEventKey)) {
                    this.reload();
                }
            };
            window.removeEventListener('realtime:events', window.__sparringRT);
            window.__sparringRT = this.onRealtime;
            window.addEventListener('realtime:events', window.__sparringRT);
        },

        get queued() { return this.bouts.filter(b => b.mat === this.mat && ! b.done); },
        get fought() { return this.bouts.filter(b => b.done); },
        get matCountLabel() { return @json($spMatCount).replace(':n', this.mats.length); },
        get boutsLabel() { return @json($spBoutsToday).replace(':n', this.fought.length); },
        get tableLabel() { return @json($spTable) + ' · ' + this.mat; },

        /**
         * A mat's scoring table opens only once that mat has a bout — the
         * sport's console builds its mat list from the bouts on it and 404s
         * otherwise. So the button waits, and says what it is waiting for.
         */
        get tableReady() { return this.readyMats.includes(this.mat); },
        get addLabel() { return @json($spAddLabel).replace(':n', this.chosen.length); },

        get candidates() {
            const on = new Set(this.entrants.map(e => e.name));
            const q = this.search.trim().toLowerCase();
            return this.members
                .filter(m => ! on.has(m.name))
                .filter(m => ! q || (m.name || '').toLowerCase().includes(q))
                .slice(0, 60);
        },

        cornerOf(id) {
            if (this.aka && this.aka.id === id) return 'aka';
            if (this.ao && this.ao.id === id) return 'ao';
            return null;
        },

        /** Red first, then blue; tapping a chosen face releases it. */
        pick(p) {
            if (this.closed) return;
            const corner = this.cornerOf(p.id);
            if (corner === 'aka') { this.aka = null; return; }
            if (corner === 'ao') { this.ao = null; return; }
            if (! this.aka) { this.aka = p; return; }
            if (! this.ao) { this.ao = p; return; }
            // Both taken — the newest tap replaces red and pushes it to blue,
            // so a coach correcting themselves never has to clear first.
            this.ao = this.aka;
            this.aka = p;
        },

        clearPick() { this.aka = this.ao = null; },
        toggleCandidate(id) {
            this.chosen = this.chosen.includes(id) ? this.chosen.filter(i => i !== id) : [...this.chosen, id];
        },

        async act(action, payload) {
            if (this.busy) return null;
            this.busy = true;
            try {
                const res = await fetch(this.actionBase + action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload || {}),
                });
                const data = await res.json().catch(() => ({}));
                if (! data.success) {
                    window.showToast('error', data.message || 'Could not do that.');
                    return null;
                }
                if (data.entrants) this.entrants = data.entrants;
                if (data.bouts) this.bouts = data.bouts;
                if (data.mats) this.mats = data.mats;
                if (data.ready_mats) this.readyMats = data.ready_mats;
                if (data.message) window.showToast('success', data.message);
                return data;
            } catch (e) {
                window.showToast('error', 'Network error.');
                return null;
            } finally {
                this.busy = false;
            }
        },

        async queueBout() {
            if (! (this.aka && this.ao)) return;
            const done = await this.act('queue_bout', { aka: this.aka.id, ao: this.ao.id, mat: this.mat });
            if (done) this.clearPick();
        },

        async unqueue(b) { await this.act('unqueue_bout', { match_id: b.id }); },

        async addPeople() {
            const done = await this.act('add_entrants', { user_ids: this.chosen });
            if (done) { this.chosen = []; this.peopleSheet = false; }
        },

        async addMat() {
            const done = await this.act('set_mats', { mats: this.mats.length + 1 });
            if (done) this.mat = this.mats[this.mats.length - 1];
        },

        async endSession() {
            const ok = await window.confirmAction({
                title: @json($spEndTitle),
                message: @json($spEndConfirm),
                type: 'danger',
            });
            if (! ok) return;
            const done = await this.act('close_session', {});
            if (done) this.closed = true;
        },

        /**
         * Re-read the session from the server.
         *
         * A refresh signal rather than a patch, because the nudge that brought
         * us here may have come from another coach's console or from a mat
         * committing a result — and what THIS viewer may see is decided
         * server-side, not by whatever the sender happened to be holding.
         */
        async reload() {
            try {
                const res = await fetch(@json($spStateUrl), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (! data.success) return;
                this.mats = data.mats;
                this.entrants = data.entrants;
                this.bouts = data.bouts;
                this.readyMats = data.ready_mats ?? this.readyMats;
                this.closed = data.closed;
                if (! this.mats.includes(this.mat)) this.mat = this.mats[0];
            } catch (e) { /* a console that missed one refresh gets the next */ }
        },
    };
}
</script>
