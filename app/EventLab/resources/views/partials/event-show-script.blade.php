{{-- Shared event-show Alpine data — powers both mobile and desktop pages identically. Expects $e, $canManage, $finance, $banned, $eligReason in scope, plus the
    owning event-type package's viewData() keys ($manual_results, $stage, …). --}}
x-data="{
        going: {{ ($e['joined'] ?? false) ? 'true' : 'false' }},
        watching: {{ ($e['watching'] ?? false) ? 'true' : 'false' }},
        liked: false,
        likes: 36,
        busy: false,
        goingCount: {{ $e['participants_total'] ?? $e['going'] }},
        spectators: {{ $hasTicket ? $e['spectator']['count'] : 0 }},
        blockedCount: {{ ($canManage ?? false) ? count($e['bans_list'] ?? []) : 0 }},
        cap: {{ $e['cap'] }},
        byQual: {{ $byQual ? 'true' : 'false' }},
        joinedDivision: '',
        // ----- Payment proof (paid participant events, manual proof-of-payment) -----
        paymentPending: {{ ($e['payment_pending'] ?? false) ? 'true' : 'false' }},
        // Registered, fee not settled. Drives the fee-due wording so a place held
        // on pay-later never claims to be paid for.
        // NB: this whole file is the VALUE of the x-data attribute, so a double
        // quote anywhere in it — even inside a comment — closes that attribute
        // early and dumps the rest of the file onto the page as visible text.
        // Use single quotes here, always.
        feeDue: {{ ($e['fee_due'] ?? false) ? 'true' : 'false' }},
        proofOpen: false,
        proofData: null,
        proofPreview: null,
        proofSubmitting: false,
        openProof() {
            this.proofData = null; this.proofPreview = null; this.proofOpen = true;
        },
        closeProof() { this.proofOpen = false; },
        pickProof(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!f.type.startsWith('image/')) { window.showToast('error', '{{ __("personal.event_show_proof_pick_image") }}'); e.target.value = ''; return; }
            const r = new FileReader();
            r.onload = () => { this.proofData = r.result; this.proofPreview = r.result; };
            r.readAsDataURL(f);
        },
        // Re-calls register() with the proof attached — updateOrCreate keeps it idempotent
        // and re-runs every eligibility/capacity check. paid stays false (awaiting approval).
        async submitProof() {
            if (!this.proofData || this.proofSubmitting) return;
            this.proofSubmitting = true;
            let res = null, d = {};
            try {
                res = await fetch('{{ route('testcode.me.events.register', $e['key']) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ payment_proof: this.proofData }),
                });
                d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || '{{ __("personal.event_show_action_failed") }}');
            } catch (e) { this.proofSubmitting = false; window.showToast('error', e.message); return; }
            this.proofSubmitting = false;
            this.going = true;
            this.goingCount = d.going ?? this.goingCount;
            if (d.division) this.joinedDivision = d.division;
            this.paymentPending = true;
            this.proofOpen = false;
            window.showToast('success', d.message);
        },
        async req(url, method, body) {
            if (this.busy) return null;
            this.busy = true;
            try {
                const headers = { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' };
                if (body) headers['Content-Type'] = 'application/json';
                const res = await fetch(url, {
                    method: method || 'POST',
                    headers,
                    credentials: 'same-origin',
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __("personal.event_show_action_failed") }}');
                return data;
            } catch (e) { window.showToast('error', e.message); return null; }
            finally { this.busy = false; }
        },
        get registered() { return this.going || this.watching; },

        /* ---------------- Competing for a club (individual entry) ----------------
         * The athlete CLAIMS a club; nobody approves it. The server re-checks the
         * claim against their own active memberships, so this list is a
         * convenience, never the authorisation.
         */
        // Their claim if they already have one, otherwise the club the server
        // suggests — where they last practised THIS event's sport.
        representing: {{ ($representing['claim'] ?? 0) ?: (($representing['default'] ?? null) ?: 'null') }},

        /* ===== Multi-pricing =====

           `fees` is the price list the server rendered with; `chosenOptions`
           holds the UUIDs of what has been ticked. Only the UUIDs are ever
           posted — the total below is for the person reading the sheet, and the
           server re-prices from its own rows before charging anybody, so a
           reader editing these numbers in their console changes what their
           screen says and nothing else. */
        fees: @js($e['fees'] ?? ['currency' => '', 'base' => 0, 'options' => [], 'spectator_base' => 0, 'spectator_options' => [], 'late_active' => false, 'late_amount' => 0, 'late_from' => null]),
        chosenOptions: [],

        /** The list for whichever door the sheet is open on. */
        feeOptions() {
            return (this.joinRole === 'spectator' ? this.fees.spectator_options : this.fees.options) || [];
        },

        toggleFeeOption(key) {
            const i = this.chosenOptions.indexOf(key);
            if (i === -1) this.chosenOptions.push(key); else this.chosenOptions.splice(i, 1);
        },

        /** Trailing zeros trimmed, the way EventFee::display() writes a number. */
        feeMoney(n) {
            return this.fees.currency + ' ' + Number(n || 0).toFixed(3).replace(/\.?0+$/, '');
        },

        /**
         * Base + everything ticked + the penalty when it is live.
         *
         * Mirrors EventFee::quote() deliberately: the sheet must show the number
         * the server is about to arrive at, or the person is agreeing to one
         * price and being charged another.
         */
        joinTotal() {
            const spectator = this.joinRole === 'spectator';
            let total = spectator ? (this.fees.spectator_base || 0) : (this.fees.base || 0);

            for (const opt of this.feeOptions()) {
                if (this.chosenOptions.includes(opt.key)) total += (opt.amount || 0);
            }

            if (! spectator && this.fees.late_active) total += (this.fees.late_amount || 0);

            return total > 0 ? this.feeMoney(total) : this.joinFee;
        },

        /** The total taken apart, so nobody has to trust it. */
        feeBreakdown() {
            const spectator = this.joinRole === 'spectator';
            const parts = [];
            const base = spectator ? (this.fees.spectator_base || 0) : (this.fees.base || 0);

            if (base > 0) parts.push('{{ __('events.fee_base_entry') }} ' + this.feeMoney(base));

            for (const opt of this.feeOptions()) {
                if (this.chosenOptions.includes(opt.key)) parts.push(opt.label + ' ' + opt.display);
            }

            if (! spectator && this.fees.late_active && this.fees.late_amount > 0) {
                parts.push('{{ __('events.fee_line_late') }} ' + this.feeMoney(this.fees.late_amount));
            }

            return parts.length > 1 ? parts.join(' + ') : '';
        },

        lateFeeNote() {
            return '{{ __('events.fee_line_late') }} · ' + this.feeMoney(this.fees.late_amount);
        },

        representingDisowned: {{ ($representing['disowned'] ?? false) ? 'true' : 'false' }},
        async setRepresenting(id) {
            const previous = this.representing;
            this.representing = id;

            // Before joining there is nothing to save — the choice rides along
            // with the registration. After joining it is a change to a record,
            // and a change that silently did not save would be the worst kind.
            if (! this.going) return;

            const d = await this.req('{{ route('testcode.me.events.register', $e['key']) }}', 'POST', { representing_tenant_id: id });
            if (! d) { this.representing = previous; return; }
            this.representingDisowned = false;
            window.showToast('success', '{{ __('personal.event_show_representing_saved') }}');
        },

        /* ---------------- Entering a squad (club channel) ----------------
         * Open to whoever holds their club's entry grant — the coach who knows
         * who is fighting, not only the owner.
         */
        squadOpen: false,
        squadTab: 'roster',
        squadLoading: false,
        squadSaving: false,
        athletes: [],
        claims: [],
        picked: [],
        // The roster is a PAGE of a club's members, not all of them, so finding
        // someone is a search rather than a scroll. The server matches name,
        // email and phone against that coach's own members — the athletes list
        // itself never carries anyone's contact details.
        squadQuery: '',
        squadTotal: 0,
        squadShown: 0,
        _squadSearch: null,
        async openSquad() {
            this.squadOpen = true;
            this.squadTab = 'roster';
            if (this.athletes.length || this.claims.length) return;
            await this.loadSquad();
        },
        // Typing a letter must not fire a request per keystroke.
        searchSquad() {
            clearTimeout(this._squadSearch);
            this._squadSearch = setTimeout(() => this.loadSquad(true), 280);
        },
        clearSquadSearch() {
            if (! this.squadQuery) return;
            this.squadQuery = '';
            clearTimeout(this._squadSearch);
            this.loadSquad(true);
        },
        /**
         * Load the roster (and, the first time, the claims beside it).
         *
         * A search only reloads the roster: the claims list does not narrow, and
         * re-fetching it on every keystroke would be work nobody asked for.
         * Selections survive a search — picking three people, searching for a
         * fourth and losing the first three would be its own bug.
         */
        async loadSquad(searchOnly = false) {
            this.squadLoading = true;
            try {
                const url = '{{ route('testcode.me.events.entry-roster', $e['key']) }}'
                    + (this.squadQuery ? ('?q=' + encodeURIComponent(this.squadQuery)) : '');
                const requests = [fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json())];
                if (! searchOnly) {
                    requests.push(fetch('{{ route('testcode.me.events.claims', $e['key']) }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json()));
                }
                const [roster, claims] = await Promise.all(requests);
                this.athletes = roster.athletes || [];
                this.squadTotal = roster.total || 0;
                this.squadShown = roster.shown || 0;
                if (claims) this.claims = claims.claims || [];
            } catch (e) {
                window.showToast('error', '{{ __('personal.event_show_action_failed') }}');
            } finally { this.squadLoading = false; }
        },
        togglePick(id) {
            const i = this.picked.indexOf(id);
            if (i === -1) this.picked.push(id); else this.picked.splice(i, 1);
        },
        // Acts on what is ON SCREEN. With a search active that means the
        // matches, and it must not silently drop people picked before the
        // search — so it adds to the selection, or takes exactly these back out.
        pickAllEnterable() {
            const open = this.athletes.filter(a => a.can_enter && ! a.entered).map(a => a.id);
            if (! open.length) return;
            const allPicked = open.every(id => this.picked.includes(id));
            this.picked = allPicked
                ? this.picked.filter(id => ! open.includes(id))
                : [...new Set(this.picked.concat(open))];
        },
        get enterableCount() { return this.athletes.filter(a => a.can_enter && ! a.entered).length; },
        async submitEntries() {
            if (! this.picked.length || this.squadSaving) return;
            this.squadSaving = true;
            let d = null;
            try {
                const res = await fetch('{{ route('testcode.me.events.entries', $e['key']) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ user_ids: this.picked }),
                });
                d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || '{{ __('personal.event_show_action_failed') }}');
            } catch (e) { this.squadSaving = false; window.showToast('error', e.message); return; }
            this.squadSaving = false;

            // Partial success is the NORMAL outcome, so say both halves and
            // leave the refused ones on screen with the server's own reason.
            this.goingCount = d.going ?? this.goingCount;
            (d.entered || []).forEach(row => {
                const a = this.athletes.find(x => x.id === row.user_id);
                if (a) { a.entered = true; a.division = row.division || a.division; }
            });
            (d.rejected || []).forEach(row => {
                const a = this.athletes.find(x => x.id === row.user_id);
                if (a) { a.can_enter = false; a.reason = row.message; }
            });
            this.picked = [];
            window.showToast((d.rejected || []).length ? 'info' : 'success', d.message);
        },
        /* ---------------- Entering someone not listed (Door B) ----------------
         * A NAME commits the entry; a single-use link lets the athlete supply
         * what the coach could only have guessed at. See
         * Documentation/EVENTS-PUBLIC-ENTRY.md.
         */
        byName: { full_name: '', contact_email: '', contact_phone: '' },
        byNameContact: false,
        claimSaving: false,
        // The link just minted, shown ONCE. A claim link is a credential: the
        // listing below never carries one, so it lives here and nowhere else.
        freshClaim: null,
        pendingClaims: [],
        _claimsLoaded: false,

        async openByName() {
            this.squadTab = 'byname';
            if (this._claimsLoaded) return;
            await this.loadClaimLinks();
        },

        get waitingCount() {
            return this.pendingClaims.filter(c => c.entry_state !== 'complete' && c.state === 'live').length;
        },

        claimStateLabel(c) {
            return ({
                live: @js(__('personal.event_show_byname_state_live')),
                expired: @js(__('personal.event_show_byname_state_expired')),
                revoked: @js(__('personal.event_show_byname_state_revoked')),
                claimed: @js(__('personal.event_show_byname_state_claimed')),
            })[c.state] || '';
        },

        async loadClaimLinks() {
            try {
                const res = await fetch('{{ route('testcode.me.events.entry-links', $e['key']) }}',
                    { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await res.json();
                this.pendingClaims = d.pending || [];
                this._claimsLoaded = true;
            } catch (e) { /* the panel simply stays empty */ }
        },

        async issueClaim() {
            const name = (this.byName.full_name || '').trim();
            if (! name || this.claimSaving) return;
            this.claimSaving = true;

            const d = await this.req('{{ route('testcode.me.events.entries.unnamed', $e['key']) }}', 'POST', {
                full_name: name,
                contact_email: this.byName.contact_email || null,
                contact_phone: this.byName.contact_phone || null,
            });
            this.claimSaving = false;
            if (! d) return;

            this.freshClaim = d.claim;
            this.pendingClaims.unshift(d.claim);
            this.byName = { full_name: '', contact_email: '', contact_phone: '' };
            this.byNameContact = false;
            this.goingCount = d.going ?? this.goingCount;
            window.showToast('success', d.message);
        },

        async copyClaim() {
            if (! this.freshClaim?.url) return;
            try {
                await navigator.clipboard.writeText(this.freshClaim.url);
                window.showToast('success', @js(__('personal.event_show_byname_copied')));
            } catch (e) {
                window.showToast('info', @js(__('personal.event_show_byname_copy_manual')));
            }
        },

        async shareClaim() {
            if (! this.freshClaim?.url) return;
            // The share sheet where the device has one; the clipboard is the
            // fallback, because an Android WebView may offer neither.
            if (navigator.share) {
                try { await navigator.share({ title: this.freshClaim.name, url: this.freshClaim.url }); return; } catch (e) { return; }
            }
            this.copyClaim();
        },

        async relinkClaim(c) {
            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/entry-links') }}/' + c.uuid + '/relink', 'POST');
            if (! d) return;
            this.freshClaim = d.claim;
            const i = this.pendingClaims.findIndex(x => x.uuid === c.uuid);
            if (i !== -1) this.pendingClaims.splice(i, 1, d.claim);
            window.showToast('success', d.message);
        },

        async revokeClaim(c) {
            const ok = await window.confirmAction({
                title: @js(__('personal.event_show_byname_withdraw')),
                message: @js(__('personal.event_show_byname_withdraw_msg')),
                type: 'danger',
                confirmText: @js(__('personal.event_show_byname_withdraw')),
            });
            if (! ok) return;

            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/entry-links') }}/' + c.uuid, 'DELETE');
            if (! d) return;
            this.pendingClaims = this.pendingClaims.filter(x => x.uuid !== c.uuid);
            if (this.freshClaim?.uuid === c.uuid) this.freshClaim = null;
            this.goingCount = d.going ?? this.goingCount;
            window.showToast('success', d.message);
        },

        /** Reject a claim on the club's name. The athlete keeps their place. */
        async disownClaim(userId, name) {
            const ok = await window.confirmAction({
                title: @js(__('personal.event_show_disown_title')),
                message: @js(__('personal.event_show_disown_msg')),
                type: 'danger',
                confirmText: @js(__('personal.event_show_disown_btn')),
            });
            if (! ok) return;
            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/claims') }}/' + userId + '/disown', 'POST');
            if (! d) return;
            const c = this.claims.find(x => x.user_id === userId);
            if (c) c.disowned = true;
            window.showToast('success', d.message);
        },

        /* ---------------- Join sheet ---------------- */
        joinOpen: false,
        joinRole: 'participant',
        joinFee: '',
        // 'join'   — deciding whether to take a place
        // 'settle' — place already held, coming back to pay
        joinMode: 'join',
        // null until they pick: 'online' (transfer, then upload a receipt) or
        // 'cash' (hand it over at the club). There is no gateway — online means
        // a transfer the club verifies, not a card charge.
        payMethod: null,

        /**
         * Entry point for both Participate and Spectate.
         *
         * A free place needs no explanation, so it goes straight to the existing
         * confirm. A paid one opens the sheet: an athlete deciding whether to
         * join needs the amount and the account number, which a one-line dialog
         * cannot carry.
         */
        // Whether a place can still be taken, and the reason when it cannot —
        // not open yet, closed on a date, started, or over. Computed once on the
        // server (PersonalEventController::entriesState) so the button, this
        // guard and the coach's card can never disagree.
        //
        // Defaulted open for the screens that reuse this data without asking the
        // question (the manage console, the officials' desk): they render no
        // join button, so the flag has nothing to gate there.
        entriesOpen: {{ ($entriesOpen ?? true) ? 'true' : 'false' }},
        entriesNote: @js($entriesNote ?? null),

        startJoin(role) {
            if (this.busy) return;

            // Anyone already holding a place still gets through here — this is
            // also the way they settle an outstanding fee.
            if (! this.entriesOpen && ! this.registered) {
                window.showToast('info', this.entriesNote || @js(__('events.entry_closed_generic')));
                return;
            }

            // Already holding this place with the fee outstanding — reopen the
            // sheet to settle it. A place you have not paid for should never be
            // a dead button; that is the one moment someone wants to pay.
            if (this.registered && this.feeDue) {
                return this.openSheet(this.going ? 'participant' : 'spectator', 'settle');
            }
            if (this.registered) return;

            if (role === 'participant' && this.byQual) {
                window.showToast('info', '{{ __("personal.event_show_entry_by_qualification") }}');
                return;
            }

            const paid = role === 'spectator' ? {{ $ticketPaid ? 'true' : 'false' }} : {{ $pPaid ? 'true' : 'false' }};

            // A free place normally needs no sheet — except for a competitor who
            // has a club to represent, because entering as your club is part of
            // entering, not an afterthought. The sheet then carries only that
            // question and a confirm.
            const asksClub = role === 'participant' && {{ ($representing['ask'] ?? false) ? 'true' : 'false' }};

            // A free event that nonetheless SELLS something still needs the
            // sheet: skipping it would enter them with nothing chosen and no
            // chance to say otherwise.
            const asksOptions = ((role === 'spectator' ? this.fees.spectator_options : this.fees.options) || []).length > 0;

            if (! paid && ! asksClub && ! asksOptions) {
                return role === 'spectator' ? this.toggleWatch() : this.toggleGoing();
            }

            this.openSheet(role, 'join');
        },

        openSheet(role, mode) {
            this.joinRole = role;
            this.joinMode = mode;
            this.payMethod = null;
            // A fresh sheet starts with nothing ticked: carrying a previous
            // selection over would quietly re-enter somebody into something
            // they had backed out of.
            this.chosenOptions = [];
            // Empty when there is nothing to pay — the sheet keys its money
            // sections off this, and the word 'Free' is not an amount.
            this.joinFee = role === 'spectator'
                ? ({{ $ticketPaid ? 'true' : 'false' }} ? @js($hasTicket ? $e['spectator']['fee'] : '') : '')
                : ({{ $pPaid ? 'true' : 'false' }} ? @js($e['participant_fee']) : '');
            this.joinOpen = true;
        },

        closeJoin() { if (! this.busy) this.joinOpen = false; },

        /**
         * Register, then either open the proof sheet or leave the fee outstanding.
         *
         * `payNow` does NOT mean money moved — it means the person says they have
         * paid and wants to upload a receipt. The registration is identical
         * either way; an official still has to verify it.
         */
        async finishJoin(payNow) {
            if (this.busy) return;

            // Settling an existing place: no second registration, just the
            // receipt step (or nothing at all, for cash at the club).
            if (this.joinMode !== 'settle') {
                // skipConfirm: the sheet WAS the confirmation.
                if (this.joinRole === 'spectator') {
                    await this.toggleWatch(true);
                } else {
                    await this.toggleGoing(true);
                }
                if (! this.registered) return;   // join failed — keep the sheet open
            }

            this.joinOpen = false;

            if (payNow) {
                this.openProof();
            } else if (this.payMethod === 'cash') {
                window.showToast('info', '{{ __("personal.event_show_join_cash_noted") }}');
            }
        },

        /** Copy a bank detail — a hand-retyped IBAN is an IBAN typed wrong. */
        async copyValue(key) {
            const el = document.getElementById('pay-' + key);
            if (! el) return;
            try {
                await navigator.clipboard.writeText(el.textContent.trim());
                window.showToast('success', '{{ __("personal.event_show_join_copied") }}');
            } catch (e) {
                window.showToast('error', '{{ __("personal.event_show_join_copy_failed") }}');
            }
        },

        /**
         * Why this person cannot enter as a competitor.
         *
         * The row stays pressable when they are not eligible — a dead button
         * tells you nothing. Pressing it says sorry and gives the SERVER's own
         * reason (wrong age group, no weight on file, blocked, qualification
         * only), not a generic refusal.
         */
        async explainIneligible() {
            await window.confirmAction({
                title: @js(__('personal.event_show_cant_join_title')),
                message: @js($whyNot ?? __('personal.event_show_not_eligible_default')),
                type: 'warning',
                confirmText: @js(__('personal.event_show_cta_understood')),
                hideCancel: true,
            });
        },
        // Registration is FINAL — joining is one-way, no self-cancel.
        // skipConfirm is passed by the join sheet, which has already shown the
        // fee and the payment steps — asking again would be asking twice.
        async toggleGoing(skipConfirm = false) {
            if (this.byQual) { window.showToast('info','{{ __("personal.event_show_entry_by_qualification") }}'); return; }
            if (this.registered || this.busy) return;
            if (skipConfirm !== true) {
                const ok = await window.confirmAction({
                    title: '{{ __("personal.event_show_confirm_spot_title") }}',
                    message: @js($pPaid ? __('personal.event_show_confirm_spot_paid', ['fee' => $e['participant_fee']]) : __('personal.event_show_confirm_spot_free')),
                    type: 'primary', confirmText: @js($pPaid ? __('personal.event_show_join_owe_fee') : __('personal.event_show_im_in')),
                });
                if (!ok) return;
            }

            this.busy = true;
            let res = null, d = {};
            try {
                res = await fetch('{{ route('testcode.me.events.register', $e['key']) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    // The club they chose to compete for, if they were asked,
                    // and WHAT they are entering. Keys only — never a price.
                    body: JSON.stringify({
                        representing_tenant_id: this.representing,
                        fee_options: this.chosenOptions,
                    }),
                });
                d = await res.json().catch(() => ({}));
            } catch (e) { this.busy = false; window.showToast('error', e.message); return; }
            this.busy = false;

            if (!res.ok || !d.success) {
                // Not in a competing weight category → offer the spectator ticket.
                if (d.code === 'no_division' && d.spectator) {
                    const watch = await window.confirmAction({
                        title: '{{ __("personal.event_show_no_division_title") }}',
                        message: (d.message || '') + '{{ __("personal.event_show_get_ticket_prompt") }}',
                        type: 'primary', confirmText: '{{ __("personal.event_show_get_spectator_ticket") }}',
                    });
                    if (watch) this.toggleWatch(true);
                    return;
                }
                window.showToast('error', d.message || '{{ __("personal.event_show_couldnt_join") }}');
                return;
            }

            this.going = true;
            // register() settles a FREE entry instantly and leaves a paid one owing.
            this.feeDue = {{ $pPaid ? 'true' : 'false' }};
            this.goingCount = d.going ?? this.goingCount + 1;
            this.joinedDivision = d.division || '';
            window.showToast('success', d.message);
        },
        async toggleWatch(skipConfirm = false) {
            if (this.registered) return;
            if (skipConfirm !== true) {
                const ok = await window.confirmAction({
                    title: '{{ __("personal.event_show_get_ticket_title") }}',
                    message: @js($ticketPaid ? __('personal.event_show_ticket_final_fee', ['fee' => $e['spectator']['fee']]) : __('personal.event_show_ticket_guest_list')),
                    type: 'primary', confirmText: @js($ticketPaid ? __('personal.event_show_book_ticket') : __('personal.event_show_get_pass')),
                });
                if (!ok) return;
            }
            const d = await this.req('{{ route('testcode.me.events.ticket', $e['key']) }}', 'POST');
            if (!d) return;
            this.watching = true;
            this.feeDue = {{ $ticketPaid ? 'true' : 'false' }};
            this.spectators = d.spectators ?? this.spectators + 1;
            window.showToast('success', d.message);
        },
        // ----- Owner moderation (remove / block / blacklist) -----
        async moderate(id, name, action) {
            if (this.busy) return;
            const copy = {
                remove:    { t: '{{ __("personal.event_show_remove_confirm") }}'.replace(':name', name), m: '{{ __("personal.event_show_remove_msg") }}', c: '{{ __("personal.event_show_remove_btn") }}', ty: 'danger' },
                block:     { t: '{{ __("personal.event_show_block_confirm") }}'.replace(':name', name), m: '{{ __("personal.event_show_block_msg") }}', c: '{{ __("personal.event_show_block_btn") }}', ty: 'danger' },
                blacklist: { t: '{{ __("personal.event_show_blacklist_confirm") }}'.replace(':name', name), m: '{{ __("personal.event_show_blacklist_msg") }}', c: '{{ __("personal.event_show_blacklist_btn") }}', ty: 'danger' },
            }[action];
            if (!copy) return;
            const ok = await window.confirmAction({ title: copy.t, message: copy.m, type: copy.ty, confirmText: copy.c });
            if (!ok) return;
            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/participants') }}/' + id + '/moderate', 'POST', { action });
            if (!d) return;
            document.getElementById('prow-' + id)?.remove();
            document.getElementById('srow-' + id)?.remove();
            this.goingCount = d.going ?? this.goingCount;
            this.spectators = d.spectators ?? this.spectators;
            if (d.banned && d.user) { this.addBlockedRow(d.user); this.blockedCount++; }
            window.showToast('success', d.message);
        },
        addBlockedRow(u) {
            const list = document.getElementById('blocked-list');
            if (!list || document.getElementById('brow-' + u.id)) return;
            const empty = document.getElementById('blocked-empty');
            if (empty) empty.style.display = 'none';
            const initials = (u.name || '?').trim().split(/\s+/).map(s => s[0]).slice(0, 2).join('');
            const scope = u.scope === 'club' ? '{{ __("personal.event_show_blacklisted_scope") }}' : '{{ __("personal.event_show_blocked_scope") }}';
            // Build with DOM APIs only — no quoted HTML strings (they would break the x-data attribute).
            const make = (tag, cls, text) => { const el = document.createElement(tag); if (cls) el.className = cls; if (text != null) el.textContent = text; return el; };
            const row = make('div', 'flex items-center gap-3');
            row.id = 'brow-' + u.id;
            row.appendChild(make('div', 'w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0 bg-gray-400', initials));
            const mid = make('div', 'min-w-0 flex-1');
            mid.appendChild(make('p', 'text-sm font-semibold text-foreground truncate', u.name));
            mid.appendChild(make('p', 'text-[11px] text-muted-foreground truncate', scope));
            row.appendChild(mid);
            const btn = make('button', 'm-press text-[10px] font-bold px-2.5 py-1 rounded-full border border-gray-200 text-foreground hover:bg-muted flex-shrink-0');
            btn.type = 'button';
            btn.appendChild(make('i', 'bi bi-arrow-counterclockwise'));
            btn.appendChild(document.createTextNode(' {{ __("personal.event_show_unblock") }}'));
            btn.addEventListener('click', () => this.unblock(u.id));
            row.appendChild(btn);
            list.appendChild(row);
        },
        async unblock(id) {
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_unblock_title") }}', message: '{{ __("personal.event_show_unblock_msg") }}', type: 'primary', confirmText: '{{ __("personal.event_show_unblock") }}' });
            if (!ok) return;
            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/bans') }}/' + id, 'DELETE');
            if (!d) return;
            document.getElementById('brow-' + id)?.remove();
            this.blockedCount = Math.max(0, this.blockedCount - 1);
            if (this.blockedCount === 0) { const em = document.getElementById('blocked-empty'); if (em) em.style.display = ''; }
            window.showToast('success', d.message);
        },
        toggleLike() {
            this.liked = !this.liked;
            this.likes += this.liked ? 1 : -1;
        },
        manageOpen: false,
        cancelled: {{ ($e['cancelled'] ?? false) ? 'true' : 'false' }},
        goEdit() {
            this.manageOpen = false;
            window.location.href = '{{ route('testcode.me.events.edit', $e['key']) }}';
        },
        async cancelEvent() {
            this.manageOpen = false;
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_cancel_title") }}', message: '{{ __("personal.event_show_cancel_msg") }}', type: 'danger', confirmText: '{{ __("personal.event_show_cancel_event") }}' });
            if (!ok) return;
            const d = await this.req('{{ route('testcode.me.events.cancel-event', $e['key']) }}', 'PATCH');
            if (d) { this.cancelled = true; window.showToast('info', d.message); }
        },
        async deleteEvent() {
            this.manageOpen = false;
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_delete_title") }}', message: '{{ __("personal.event_show_delete_msg") }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' });
            if (!ok) return;
            {{-- Same trap as the edit form's PUT: a bare quoted
                 `/testcode/me/events/{uuid}` is rewritten to the public poster inside
                 the sealed app, and the poster answers GET only — so the
                 DELETE has to name the mirrored admin root itself. See the
                 note in personal/event-create.blade.php. --}}
            const d = await this.req('{{ isset($shell) ? url('/e/'.$e['key'].'/admin') : route('testcode.me.events.destroy', $e['key']) }}', 'DELETE');
            if (d) { window.showToast('success', d.message); setTimeout(() => { window.location.href = d.redirect || '{{ route('testcode.me.events') }}'; }, 500); }
        },
        // ----- Results / winners -----
        results: @js($e['results'] ?? []),
        resultsOpen: false,
        showResultsOpen: false,
        // ----- Finance (owner) -----
        financeOpen: false,
        fin: @js($finance),
        newExpLabel: '',
        newExpAmount: '',
        money(n) { return (this.fin ? this.fin.currency : '') + ' ' + (parseFloat(n) || 0).toFixed(3); },
        get expensesTotal() { return (this.fin?.expenses || []).reduce((s, x) => s + (parseFloat(x.amount) || 0), 0); },
        get profit() { return ((this.fin?.revenue) || 0) - this.expensesTotal; },
        async addExpense() {
            const amt = parseFloat(this.newExpAmount);
            if (!(this.newExpLabel || '').trim() || !amt || amt <= 0) return;
            const d = await this.req('{{ route('testcode.me.events.expenses.add', $e['key']) }}', 'POST', { label: this.newExpLabel.trim(), amount: amt });
            if (d && d.expense) { this.fin.expenses.unshift(d.expense); this.newExpLabel = ''; this.newExpAmount = ''; }
        },
        async removeExpense(id) {
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_remove_expense_title") }}', message: '{{ __("personal.event_show_remove_expense_msg") }}', type: 'danger', confirmText: '{{ __("personal.event_show_remove_btn") }}' });
            if (!ok) return;
            const d = await this.req('{{ url('testcode/me/events/'.$e['key'].'/expenses') }}/' + id, 'DELETE');
            if (d) this.fin.expenses = this.fin.expenses.filter(x => x.id !== id);
        },
        winners: [],
        openResults() {
            this.manageOpen = false;
            /* Only a type that ALLOWS a typed-in podium needs the editor's
               working copy. A bracketed championship opens the read-only
               podium (partials/event-podium-sheet), which is rendered from the
               server's own derived result and has nothing to seed. */
            @if($manual_results ?? true)
            this.winners = this.results.length
                ? this.results.map(r => ({ place: r.place, name: r.name, prize: r.prize || '' }))
                : [{ place: 1, name: '', prize: @js($e['prize'] ?? '') }, { place: 2, name: '', prize: '' }, { place: 3, name: '', prize: '' }];
            @endif
            this.resultsOpen = true;
        },
        addWinner() { this.winners.push({ place: this.winners.length + 1, name: '', prize: '' }); },
        removeWinner(i) { this.winners.splice(i, 1); },
        async saveResults() {
            if (this.busy) return;
            this.busy = true;
            try {
                const payload = { results: this.winners
                    .filter(w => (w.name || '').trim())
                    .map((w, i) => ({ place: parseInt(w.place, 10) || (i + 1), name: (w.name || '').trim(), prize: (w.prize || '').trim() })) };
                const res = await fetch('{{ route('testcode.me.events.results', $e['key']) }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin', body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __("personal.event_show_save_winners_failed") }}');
                this.results = data.results || payload.results;
                this.resultsOpen = false;
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        medal(place) { return ({1:'#f59e0b',2:'#9ca3af',3:'#b45309'})[place] || '{{ $e['color'] }}'; },
        get pct() { return Math.round(this.goingCount / this.cap * 100); },

        /*
         * Take the reader from a summary chip to the thing it summarises.
         *
         * The quick-facts card at the top answers when / how much / where in
         * three words each. Every one of those answers has a fuller version
         * further down the page, and scrolling to it by hand is the reader
         * doing the linking work. So the chip does it: scroll there, then
         * pulse the exact element — the scroll only gets you to the region,
         * the pulse says which row.
         *
         * Falls back through a list of ids so a page that does not render the
         * richer section (no timeline, say) still lands somewhere sensible,
         * and does nothing at all if none of them exist — a chip that silently
         * scrolls to the top would read as broken.
         *
         * NOTE: this whole partial is rendered INSIDE the x-data attribute of a
         * div. A literal double quote anywhere in here, even in a comment,
         * closes that attribute early and the rest of the file spills onto the
         * page as visible text. Keep every quote single, prose included.
         */
        jump(ids, block = 'center') {
            const el = (Array.isArray(ids) ? ids : [ids])
                .map(id => document.getElementById(id)).find(Boolean);
            if (! el) return;

            const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            el.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block });

            // Re-tapping the same chip should pulse again: drop the class and
            // force a reflow before re-adding, or the animation never restarts.
            el.classList.remove('m-attn');
            void el.offsetWidth;
            el.classList.add('m-attn');
            clearTimeout(this._attn);
            this._attn = setTimeout(() => el.classList.remove('m-attn'), 3600);
        },
        _attn: null,
     }"
