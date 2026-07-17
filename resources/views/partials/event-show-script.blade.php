{{-- Shared event-show Alpine data — powers both mobile and desktop pages identically. Expects $e, $canManage, $isTkd, $finance, $banned, $eligReason in scope. --}}
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
        // Registration is FINAL — joining is one-way, no self-cancel.
        async toggleGoing() {
            if (this.byQual) { window.showToast('info','{{ __("personal.event_show_entry_by_qualification") }}'); return; }
            if (this.registered || this.busy) return;
            const ok = await window.confirmAction({
                title: '{{ __("personal.event_show_confirm_spot_title") }}',
                message: @js($pPaid ? __('personal.event_show_confirm_spot_paid', ['fee' => $e['participant_fee']]) : __('personal.event_show_confirm_spot_free')),
                type: 'primary', confirmText: @js($pPaid ? __('personal.event_show_join_owe_fee') : __('personal.event_show_im_in')),
            });
            if (!ok) return;

            this.busy = true;
            let res = null, d = {};
            try {
                res = await fetch('{{ route('me.events.register', $e['key']) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    credentials: 'same-origin',
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
            const d = await this.req('{{ route('me.events.ticket', $e['key']) }}', 'POST');
            if (!d) return;
            this.watching = true;
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
            const d = await this.req('{{ url('me/events/'.$e['key'].'/participants') }}/' + id + '/moderate', 'POST', { action });
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
            const d = await this.req('{{ url('me/events/'.$e['key'].'/bans') }}/' + id, 'DELETE');
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
            window.location.href = '{{ route('me.events.edit', $e['key']) }}';
        },
        async cancelEvent() {
            this.manageOpen = false;
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_cancel_title") }}', message: '{{ __("personal.event_show_cancel_msg") }}', type: 'danger', confirmText: '{{ __("personal.event_show_cancel_event") }}' });
            if (!ok) return;
            const d = await this.req('{{ route('me.events.cancel-event', $e['key']) }}', 'PATCH');
            if (d) { this.cancelled = true; window.showToast('info', d.message); }
        },
        async deleteEvent() {
            this.manageOpen = false;
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_delete_title") }}', message: '{{ __("personal.event_show_delete_msg") }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' });
            if (!ok) return;
            const d = await this.req('{{ route('me.events.destroy', $e['key']) }}', 'DELETE');
            if (d) { window.showToast('success', d.message); setTimeout(() => { window.location.href = d.redirect || '{{ route('me.events') }}'; }, 500); }
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
            const d = await this.req('{{ route('me.events.expenses.add', $e['key']) }}', 'POST', { label: this.newExpLabel.trim(), amount: amt });
            if (d && d.expense) { this.fin.expenses.unshift(d.expense); this.newExpLabel = ''; this.newExpAmount = ''; }
        },
        async removeExpense(id) {
            const ok = await window.confirmAction({ title: '{{ __("personal.event_show_remove_expense_title") }}', message: '{{ __("personal.event_show_remove_expense_msg") }}', type: 'danger', confirmText: '{{ __("personal.event_show_remove_btn") }}' });
            if (!ok) return;
            const d = await this.req('{{ url('me/events/'.$e['key'].'/expenses') }}/' + id, 'DELETE');
            if (d) this.fin.expenses = this.fin.expenses.filter(x => x.id !== id);
        },
        winners: [],
        openResults() {
            this.manageOpen = false;
            this.winners = this.results.length
                ? this.results.map(r => ({ place: r.place, name: r.name, prize: r.prize || '' }))
                : [{ place: 1, name: '', prize: @js($e['prize'] ?? '') }, { place: 2, name: '', prize: '' }, { place: 3, name: '', prize: '' }];
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
                const res = await fetch('{{ route('me.events.results', $e['key']) }}', {
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
        get pct() { return Math.round(this.goingCount / this.cap * 100); }
     }"
