{{-- Shared duel-show Alpine data — powers both mobile and desktop duel-show
     pages identically. Expects $status, $d in scope. --}}
x-data="{
        status: '{{ $status }}',
        busy: false,
        reportOpen: false,
        won: false,
        reportedByMe: {{ ($d['reported_by_me'] ?? false) ? 'true' : 'false' }},
        format: @js($d['format'] ?? 'single'),
        maxRounds: {{ ($d['format'] ?? '') === 'bo5' ? 5 : 3 }},
        oppName: @js($d['opponent']['name']),
        roundWinners: [],
        myScore: '',
        oppScore: '',
        setRound(i, w) { const a = [...this.roundWinners]; a[i] = w; this.roundWinners = a; },
        roundTally(side) { return this.roundWinners.filter(x => x === side).length; },
        async post(url) {
            if (this.busy) return null;
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(this._body || {}),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_action_failed') }}');
                return data;
            } catch (e) {
                window.showToast('error', e.message);
                return null;
            } finally {
                this.busy = false;
                this._body = null;
            }
        },
        async accept() { const d = await this.post('{{ route('me.challenge.duel.accept', $d['id']) }}'); if (d) { this.status='active'; window.showToast('success', d.message); } },
        async decline() { const d = await this.post('{{ route('me.challenge.duel.decline', $d['id']) }}'); if (d) { this.status='declined'; window.showToast('info', d.message); } },
        cancelOpen: false,
        cancelReason: @js($d['cancel_reason'] ?? ''),
        async confirmCancel() {
            this._body = { reason: this.cancelReason };
            const d = await this.post('{{ route('me.challenge.duel.cancel', $d['id']) }}');
            if (d) { this.status = 'cancelled'; this.cancelOpen = false; window.showToast('info', d.message); }
        },
        async _report() {
            const d = await this.post('{{ route('me.challenge.duel.report', $d['id']) }}');
            if (d) { this.status = d.status || 'reported'; this.reportedByMe = true; this.reportOpen = false; window.showToast('success', d.message); }
        },
        async submitSingle(winner) { this._body = { winner }; await this._report(); },
        async submitRounds() {
            const r = this.roundWinners.filter(Boolean);
            if (!r.length) { window.showToast('warning', '{{ __('challenge.personal_duel_show_log_one_round') }}'); return; }
            const me = r.filter(x => x === 'me').length, rv = r.length - me;
            if (me === rv) { window.showToast('warning', '{{ __('challenge.personal_duel_show_rounds_tied') }}'); return; }
            this._body = { rounds: r }; await this._report();
        },
        async submitScores() {
            if (this.myScore === '' || this.oppScore === '') { window.showToast('warning', '{{ __('challenge.personal_duel_show_enter_both_scores') }}'); return; }
            if (Number(this.myScore) === Number(this.oppScore)) { window.showToast('warning', '{{ __('challenge.personal_duel_show_scores_tied') }}'); return; }
            this._body = { my_score: this.myScore, opp_score: this.oppScore }; await this._report();
        },
        async confirmResult() {
            const d = await this.post('{{ route('me.challenge.duel.confirm', $d['id']) }}');
            if (d) { this.status = 'completed'; this.won = !!d.won; window.showToast('success', d.message); }
        },
        async disputeResult() {
            const d = await this.post('{{ route('me.challenge.duel.dispute', $d['id']) }}');
            if (d) { this.status = 'active'; window.showToast('info', d.message); }
        },

        // ----- Live-updatable display fields (patched in place after an edit) -----
        disp: {
            discipline: @js($d['discipline']),
            formatLabel: @js($d['format_label'] ?? __('challenge.personal_duel_show_single_match')),
            metric: @js($d['metric']),
            stake: @js($d['stake']),
            message: @js($d['message'] ?? ''),
        },

        // ----- Chat with the opponent (reuses Messenger DMs) -----
        oppUserId: {{ $d['opponent_user_id'] ?? 'null' }},
        async messageOpponent() {
            if (!this.oppUserId || this.busy) return;
            this.busy = true;
            try {
                const res = await fetch('{{ url('/messages/start') }}/' + this.oppUserId, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.conversation_id) { window.location.href = '{{ url('/messages') }}/' + data.conversation_id; }
                else throw new Error(data.message || '{{ __('challenge.personal_duel_show_could_not_open_chat') }}');
            } catch (e) { window.showToast('error', e.message); } finally { this.busy = false; }
        },

        // ----- Owner edit -----
        editOpen: false,
        form: {
            discipline: @js($d['edit']['discipline'] ?? ''),
            format: @js($d['edit']['format'] ?? 'single'),
            stake: {{ (int) ($d['edit']['stake'] ?? 0) }},
            deadline: @js($d['edit']['deadline'] ?? ''),
            message: @js($d['edit']['message'] ?? ''),
        },
        async saveEdit() {
            if (this.busy) return;
            if (!this.form.discipline.trim()) { window.showToast('warning', '{{ __('challenge.personal_duel_show_discipline_required') }}'); return; }
            this.busy = true;
            try {
                const res = await fetch('{{ route('me.challenge.duel.update', $d['id']) }}', {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_update_failed') }}');
                const dv = data.duel || {};
                this.disp.discipline = dv.discipline ?? this.disp.discipline;
                this.disp.formatLabel = dv.format_label ?? this.disp.formatLabel;
                this.disp.metric = dv.metric ?? this.disp.metric;
                this.disp.stake = dv.stake ?? this.disp.stake;
                this.disp.message = (dv.message ?? '');
                this.format = this.form.format;          // keep report UI in sync with the new format
                this.maxRounds = this.form.format === 'bo5' ? 5 : 3;
                this.editOpen = false;
                window.showToast('success', data.message || '{{ __('challenge.personal_duel_show_duel_updated') }}');
            } catch (e) { window.showToast('error', e.message); } finally { this.busy = false; }
        },

        // ----- Media (photos / videos / links) -----
        media: @js($d['media'] ?? []),
        mediaBusy: false,
        showLink: false,
        linkUrl: '', linkCaption: '',
        async uploadMedia(kind, ev) {
            const file = ev.target.files?.[0]; if (!file) return;
            const fd = new FormData(); fd.append('type', kind); fd.append('file', file);
            await this._postMedia(fd); ev.target.value = '';
        },
        async addLink() {
            if (!this.linkUrl.trim()) { window.showToast('warning', '{{ __('challenge.personal_duel_show_paste_link_first') }}'); return; }
            const fd = new FormData(); fd.append('type', 'link'); fd.append('url', this.linkUrl.trim()); fd.append('caption', this.linkCaption);
            if (await this._postMedia(fd)) { this.linkUrl = ''; this.linkCaption = ''; }
        },
        async _postMedia(fd) {
            if (this.mediaBusy) return false;
            this.mediaBusy = true;
            try {
                const res = await fetch('{{ route('me.challenge.duel.media.add', $d['id']) }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_upload_failed') }}');
                this.media.unshift(data.media);
                window.showToast('success', data.message);
                return true;
            } catch (e) { window.showToast('error', e.message); return false; } finally { this.mediaBusy = false; }
        },
        async removeMedia(m) {
            if (!await window.confirmAction({ title: '{{ __('challenge.personal_duel_show_remove_media_title') }}', message: '{{ __('challenge.personal_duel_show_delete_item_confirm') }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' })) return;
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/media') }}/' + m.id, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_delete_failed') }}');
                this.media = this.media.filter(x => x.id !== m.id);
                window.showToast('info', data.message);
            } catch (e) { window.showToast('error', e.message); }
        },

        // ----- Witnesses (platform members; only when the duel isn't part of an event) -----
        witnesses: @js($d['witnesses'] ?? []),
        myWitness: @js($d['my_witness']),
        witnessBusy: false,
        async respondWitness(status) {
            if (!this.myWitness) return;
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/witnesses') }}/' + this.myWitness.id + '/respond', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_failed') }}');
                this.myWitness.status = status;
                const i = this.witnesses.findIndex(x => x.id === this.myWitness.id);
                if (i >= 0 && data.witness) this.witnesses[i] = data.witness;
                window.showToast(status === 'accepted' ? 'success' : 'info', data.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        wq: '', wresults: [], wopen: false, wsearching: false,
        async searchWitness() {
            const q = this.wq.trim();
            if (q.length < 2) { this.wresults = []; this.wopen = false; return; }
            this.wsearching = true;
            try {
                const res = await fetch('{{ route('me.challenge.duel.witness.search', $d['id']) }}?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                this.wresults = data.users || [];
                this.wopen = true;
            } catch (e) { /* ignore */ } finally { this.wsearching = false; }
        },
        async addWitness(uid) {
            if (!uid || this.witnessBusy) return;
            this.witnessBusy = true; this.wopen = false; this.wq = ''; this.wresults = [];
            try {
                const res = await fetch('{{ route('me.challenge.duel.witness.add', $d['id']) }}', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: uid }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_failed') }}');
                this.witnesses.unshift(data.witness);
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); } finally { this.witnessBusy = false; }
        },
        async removeWitness(w) {
            if (!await window.confirmAction({ title: '{{ __('challenge.personal_duel_show_remove_witness_title') }}', message: '{{ __('challenge.personal_duel_show_remove_witness_confirm') }}', type: 'danger', confirmText: '{{ __('challenge.personal_duel_show_remove') }}' })) return;
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/witnesses') }}/' + w.id, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_failed') }}');
                this.witnesses = this.witnesses.filter(x => x.id !== w.id);
                window.showToast('info', data.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        // ----- Witness feedback (rating + comment, by the witness themselves) -----
        wEdit: { id: null, rating: 0, comment: '' },
        startWitnessEdit(w) { this.wEdit = { id: w.id, rating: w.rating || 0, comment: w.comment || '' }; },
        cancelWitnessEdit() { this.wEdit = { id: null, rating: 0, comment: '' }; },
        async saveWitnessFeedback() {
            const id = this.wEdit.id;
            if (!id) return;
            if (!this.wEdit.rating) { window.showToast('warning', '{{ __('challenge.personal_duel_show_tap_star_rate') }}'); return; }
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/witnesses') }}/' + id + '/feedback', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rating: this.wEdit.rating, comment: this.wEdit.comment }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_failed') }}');
                const i = this.witnesses.findIndex(x => x.id === id);
                if (i >= 0) this.witnesses[i] = data.witness;
                this.cancelWitnessEdit();
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        // ----- Super-admin: delete the whole challenge -----
        async deleteDuel() {
            if (!await window.confirmAction({ title: '{{ __('challenge.personal_duel_show_delete_challenge_title') }}', message: '{{ __('challenge.personal_duel_show_delete_challenge_confirm') }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' })) return;
            try {
                const res = await fetch('{{ route('me.challenge.duel.destroy', $d['id']) }}', {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('challenge.personal_duel_show_delete_failed') }}');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || '{{ route('me.challenge') }}'; }, 600);
            } catch (e) { window.showToast('error', e.message); }
        }
     }"
