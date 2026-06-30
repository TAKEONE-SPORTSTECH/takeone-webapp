@extends('layouts.personal-mobile')

@section('title', $d['discipline'])

{{--
    Duel detail — 1v1 head-to-head. DUMMY content from PersonalMobileController@duelShow.
    Big VS hero, head-to-head stats, terms, opponent message, and status-aware
    actions: accept/decline (incoming), log result (active), cancel (sent),
    or final result (completed). Reuses the shared mobile motion vocabulary.
--}}
@php
    $typeLabel = $d['type'] === 'fight' ? 'Fight challenge' : 'Athletic challenge';
    $status    = $d['status'];
@endphp

@section('personal-content')
<div x-data="{
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Action failed');
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
            if (!r.length) { window.showToast('warning', 'Log at least one round'); return; }
            const me = r.filter(x => x === 'me').length, rv = r.length - me;
            if (me === rv) { window.showToast('warning', 'Rounds are tied — log a deciding round'); return; }
            this._body = { rounds: r }; await this._report();
        },
        async submitScores() {
            if (this.myScore === '' || this.oppScore === '') { window.showToast('warning', 'Enter both scores'); return; }
            if (Number(this.myScore) === Number(this.oppScore)) { window.showToast('warning', 'Scores are tied — a duel needs a winner'); return; }
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
            formatLabel: @js($d['format_label'] ?? 'Single match'),
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
                else throw new Error(data.message || 'Could not open chat');
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
            if (!this.form.discipline.trim()) { window.showToast('warning', 'Discipline is required'); return; }
            this.busy = true;
            try {
                const res = await fetch('{{ route('me.challenge.duel.update', $d['id']) }}', {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Update failed');
                const dv = data.duel || {};
                this.disp.discipline = dv.discipline ?? this.disp.discipline;
                this.disp.formatLabel = dv.format_label ?? this.disp.formatLabel;
                this.disp.metric = dv.metric ?? this.disp.metric;
                this.disp.stake = dv.stake ?? this.disp.stake;
                this.disp.message = (dv.message ?? '');
                this.format = this.form.format;          // keep report UI in sync with the new format
                this.maxRounds = this.form.format === 'bo5' ? 5 : 3;
                this.editOpen = false;
                window.showToast('success', data.message || 'Duel updated');
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
            if (!this.linkUrl.trim()) { window.showToast('warning', 'Paste a video link first'); return; }
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Upload failed');
                this.media.unshift(data.media);
                window.showToast('success', data.message);
                return true;
            } catch (e) { window.showToast('error', e.message); return false; } finally { this.mediaBusy = false; }
        },
        async removeMedia(m) {
            if (!await window.confirmAction({ title: 'Remove media', message: 'Delete this item?', type: 'danger', confirmText: 'Delete' })) return;
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/media') }}/' + m.id, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Delete failed');
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Failed');
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Failed');
                this.witnesses.unshift(data.witness);
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); } finally { this.witnessBusy = false; }
        },
        async removeWitness(w) {
            if (!await window.confirmAction({ title: 'Remove witness', message: 'Remove this witness?', type: 'danger', confirmText: 'Remove' })) return;
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/witnesses') }}/' + w.id, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Failed');
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
            if (!this.wEdit.rating) { window.showToast('warning', 'Tap a star to rate'); return; }
            try {
                const res = await fetch('{{ url('/me/challenge/duel/'.$d['id'].'/witnesses') }}/' + id + '/feedback', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rating: this.wEdit.rating, comment: this.wEdit.comment }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Failed');
                const i = this.witnesses.findIndex(x => x.id === id);
                if (i >= 0) this.witnesses[i] = data.witness;
                this.cancelWitnessEdit();
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        // ----- Super-admin: delete the whole challenge -----
        async deleteDuel() {
            if (!await window.confirmAction({ title: 'Delete challenge', message: 'Permanently delete this challenge for everyone? This cannot be undone.', type: 'danger', confirmText: 'Delete' })) return;
            try {
                const res = await fetch('{{ route('me.challenge.duel.destroy', $d['id']) }}', {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Delete failed');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || '{{ route('me.challenge') }}'; }, 600);
            } catch (e) { window.showToast('error', e.message); }
        }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== VS hero ===== --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $d['color'] }}, #1f2937);">
        <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-10">
            <a href="{{ route('me.challenge') }}" data-shell-link data-route="me.challenge"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <span class="px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur inline-flex items-center gap-1.5">
                <i class="bi {{ $d['icon'] }}"></i> {{ $typeLabel }}
            </span>
        </div>

        <h1 class="text-xl font-black mt-4 text-center relative z-10" x-text="disp.discipline">{{ $d['discipline'] }}</h1>
        <p class="text-center text-[11px] font-semibold text-white/80 mt-1 relative z-10">
            <i class="bi bi-trophy"></i> <span x-text="disp.formatLabel">{{ $d['format_label'] ?? 'Single match' }}</span>
        </p>
        @if(!empty($d['event']))
            <p class="text-center text-[11px] text-white/80 mt-1 relative z-10">
                <a href="{{ route('me.events.show', $d['event']['uuid']) }}" data-shell-link class="inline-flex items-center gap-1 underline decoration-white/40">
                    <i class="bi bi-calendar-event"></i> Part of {{ $d['event']['title'] }}
                </a>
            </p>
        @endif

        {{-- VS row --}}
        <div class="flex items-center justify-center gap-4 mt-5 relative z-10">
            <a href="{{ route('wall.legacy', auth()->id()) }}" class="m-press flex flex-col items-center w-28">
                @if(!empty($d['me']['avatar']))
                    <img src="{{ $d['me']['avatar'] }}" alt="You" class="w-20 h-20 rounded-full object-cover border-2 border-white/60 shadow-lg">
                @else
                    <div class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black border-2 border-white/60 shadow-lg" style="background: hsl(250 55% 60%);">{{ $d['me']['initials'] }}</div>
                @endif
                <p class="text-sm font-bold mt-2">You</p>
                <p class="text-[11px] text-white/70">{{ $d['me']['record'] }}</p>
                @if(isset($d['me']['score']))<p class="text-lg font-black mt-1">{{ $d['me']['score'] }}</p>@endif
            </a>

            <div class="flex flex-col items-center">
                <div class="w-12 h-12 rounded-full grid place-items-center text-white font-black shadow-lg m-float bg-white/15 border border-white/30 backdrop-blur">VS</div>
            </div>

            @php $oppHref = !empty($d['opponent_user_id']) ? route('wall.legacy', $d['opponent_user_id']) : null; @endphp
            <{{ $oppHref ? 'a' : 'div' }} @if($oppHref) href="{{ $oppHref }}" @endif class="m-press flex flex-col items-center w-28">
                @if(!empty($d['opponent']['avatar']))
                    <img src="{{ $d['opponent']['avatar'] }}" alt="{{ $d['opponent']['name'] }}" class="w-20 h-20 rounded-full object-cover border-2 border-white/60 shadow-lg">
                @else
                    <div class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black border-2 border-white/60 shadow-lg" style="background: hsl(8 60% 58%);">{{ $d['opponent']['initials'] }}</div>
                @endif
                <p class="text-sm font-bold mt-2 truncate max-w-full">{{ $d['opponent']['name'] }}</p>
                <p class="text-[11px] text-white/70">{{ $d['opponent']['record'] }}</p>
                @if(isset($d['opponent']['score']))<p class="text-lg font-black mt-1">{{ $d['opponent']['score'] }}</p>@endif
            </{{ $oppHref ? 'a' : 'div' }}>
        </div>
    </header>

    {{-- ===== Witness invitation banner (you've been asked to witness) ===== --}}
    @if(!empty($d['my_witness']))
        <div class="px-4 mt-4" x-show="myWitness && myWitness.status==='invited'" x-cloak>
            <div class="m-card rounded-2xl p-4 border-2 border-amber-300 bg-amber-50/40">
                <p class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-person-raised-hand text-amber-500"></i> You've been asked to witness this duel</p>
                <p class="text-[11px] text-muted-foreground mt-0.5">If you attend, you can cover it with photos/video and rate the result.</p>
                <div class="flex items-center gap-2 mt-3">
                    <button type="button" @click="respondWitness('declined')" class="m-press flex-1 py-2.5 rounded-xl border border-gray-200 text-muted-foreground text-sm font-bold">Can't attend</button>
                    <button type="button" @click="respondWitness('accepted')" class="m-press flex-1 py-2.5 rounded-xl text-white text-sm font-bold" style="background: {{ $d['color'] }};"><i class="bi bi-check2-circle"></i> I'll attend</button>
                </div>
            </div>
        </div>
        <div class="px-4 mt-4" x-show="myWitness && myWitness.status==='accepted'" x-cloak>
            <div class="m-card rounded-2xl p-3 flex items-center justify-between">
                <span class="text-xs font-bold text-green-600 inline-flex items-center gap-1.5"><i class="bi bi-patch-check-fill"></i> You're witnessing this duel</span>
                <button type="button" @click="respondWitness('declined')" class="text-[11px] font-semibold text-muted-foreground">Withdraw</button>
            </div>
        </div>
    @endif

    {{-- ===== Status banner ===== --}}
    <div class="px-4 -mt-8 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            {{-- live score bar for active --}}
            @if($status === 'active' && isset($d['me']['pct']))
                <div class="flex items-center justify-between text-[11px] mb-1.5">
                    <span class="font-bold" style="color: {{ $d['color'] }};">You</span>
                    <span class="text-muted-foreground">{{ $d['deadline'] }}</span>
                    <span class="font-bold text-muted-foreground">{{ $d['opponent']['name'] }}</span>
                </div>
                <div class="h-2.5 rounded-full bg-muted overflow-hidden flex mb-4">
                    <div class="m-bar-fill h-full" style="width: {{ ($d['me']['pct']) / max(($d['me']['pct']) + ($d['opponent']['pct'] ?? 1), 1) * 100 }}%; background: {{ $d['color'] }};"></div>
                    <div class="h-full bg-gray-300 flex-1"></div>
                </div>
            @endif

            {{-- final result for completed --}}
            @if($status === 'completed')
                @php $win = ($d['result'] ?? '') === 'win'; @endphp
                <div class="text-center py-2">
                    <div class="w-16 h-16 mx-auto rounded-2xl grid place-items-center text-white m-float" style="background: {{ $win ? '#10b981' : '#94a3b8' }};">
                        <i class="bi {{ $win ? 'bi-trophy-fill' : 'bi-emoji-neutral' }} text-2xl"></i>
                    </div>
                    <p class="text-lg font-black mt-2 {{ $win ? 'text-green-600' : 'text-muted-foreground' }}">{{ $win ? 'Victory' : 'Defeat' }}</p>
                    <p class="text-sm font-bold text-foreground mt-0.5">Final · {{ $d['final'] }}</p>
                    @if($win)<p class="text-xs text-muted-foreground mt-1">+{{ $d['points_earned'] }} points earned</p>@endif
                </div>
            @endif

            {{-- terms grid --}}
            <div class="grid grid-cols-2 gap-2 text-center {{ $status === 'completed' ? 'mt-3' : '' }}">
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <i class="bi bi-star-fill text-amber-400"></i>
                    <p class="text-[11px] font-bold text-foreground mt-1 leading-tight" x-text="disp.stake">{{ $d['stake'] }}</p>
                </div>
                @php
                    $locHref = (!empty($d['location_url']) && \Illuminate\Support\Str::startsWith($d['location_url'], ['http://', 'https://']))
                        ? $d['location_url']
                        : (!empty($d['location']) && $d['location'] !== '—' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($d['location']) : null);
                @endphp
                @if($locHref)
                    <a href="{{ $locHref }}" target="_blank" rel="noopener" class="rounded-xl bg-muted/60 py-2.5 block hover:bg-muted transition-colors">
                        <i class="bi bi-geo-alt-fill text-primary"></i>
                        <p class="text-[11px] font-bold text-primary mt-1 leading-tight truncate px-1">{{ $d['location'] !== '—' ? $d['location'] : 'View map' }} <i class="bi bi-box-arrow-up-right text-[9px]"></i></p>
                    </a>
                @else
                    <div class="rounded-xl bg-muted/60 py-2.5">
                        <i class="bi bi-geo-alt text-primary"></i>
                        <p class="text-[11px] font-bold text-foreground mt-1 leading-tight truncate px-1">{{ $d['location'] ?? '—' }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Challenge time + 30-min arrival rule ===== --}}
    @if(!empty($d['challenge_time']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4 flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl grid place-items-center bg-accent text-primary flex-shrink-0"><i class="bi bi-calendar-event text-lg"></i></span>
                <div class="min-w-0">
                    <p class="text-[11px] text-muted-foreground">Challenge time</p>
                    <p class="text-sm font-bold text-foreground">{{ $d['challenge_time'] }}</p>
                    <p class="text-[11px] text-amber-600 font-semibold mt-1.5 leading-snug"><i class="bi bi-alarm-fill"></i> Be at the venue by <span class="font-bold">{{ $d['arrival_by'] }}</span> — players, supporters &amp; coaches must arrive at least 30 minutes early.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Owner / chat actions ===== --}}
    @if(!empty($d['can_edit']) || !empty($d['opponent_user_id']))
        <div class="px-4 mt-4 flex items-center gap-2">
            @if(!empty($d['can_edit']))
                <button type="button" @click="editOpen = true"
                        class="m-press flex-1 py-2.5 rounded-xl border border-border text-foreground text-sm font-bold inline-flex items-center justify-center gap-1.5">
                    <i class="bi bi-pencil-square"></i> Edit duel
                </button>
            @endif
            @if(!empty($d['opponent_user_id']))
                <button type="button" @click="messageOpponent()" :disabled="busy"
                        class="m-press flex-1 py-2.5 rounded-xl text-white text-sm font-bold inline-flex items-center justify-center gap-1.5 disabled:opacity-50" style="background: {{ $d['color'] }};">
                    <i class="bi bi-chat-dots-fill"></i> Message {{ \Illuminate\Support\Str::of($d['opponent']['name'])->explode(' ')->first() }}
                </button>
            @endif
        </div>
    @endif

    {{-- ===== Message ===== --}}
    @if(!empty($d['message']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4 flex items-start gap-3">
                @if(!empty($d['opponent']['avatar']))
                    <img src="{{ $d['opponent']['avatar'] }}" alt="{{ $d['opponent']['name'] }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl(8 60% 58%);">{{ $d['opponent']['initials'] }}</div>
                @endif
                <div>
                    <p class="text-xs font-bold text-foreground">{{ $d['opponent']['name'] }} <span class="text-muted-foreground font-normal">· {{ $d['when'] ?? '' }}</span></p>
                    <p class="text-sm text-muted-foreground mt-0.5 italic" x-text="'“' + disp.message + '”'">“{{ $d['message'] }}”</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Head-to-head ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-bar-chart-line text-primary"></i> Head to head</h2>
            <div class="flex items-center justify-between text-[11px] font-bold mt-2 mb-1">
                <span style="color: {{ $d['color'] }};">You</span>
                <span class="text-muted-foreground truncate max-w-[40%]">{{ $d['opponent']['name'] }}</span>
            </div>
            <div class="mt-1 space-y-2.5 text-sm">
                @foreach([
                    ['Total duels', $d['stats']['me']['total'], $d['stats']['opp']['total']],
                    ['Win rate', $d['stats']['me']['win_rate'], $d['stats']['opp']['win_rate']],
                    ['Best discipline', $d['stats']['me']['best'], $d['stats']['opp']['best']],
                ] as $row)
                    <div class="flex items-center">
                        <span class="w-16 text-right font-bold truncate" style="color: {{ $d['color'] }};">{{ $row[1] }}</span>
                        <span class="flex-1 text-center text-[11px] text-muted-foreground">{{ $row[0] }}</span>
                        <span class="w-16 text-left font-bold text-muted-foreground truncate">{{ $row[2] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Witnesses — only when the duel isn't part of an event ===== --}}
    @empty($d['event'])
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-people-fill text-primary"></i> Witnesses</h2>
                    <p class="text-[11px] text-muted-foreground mt-0.5">People who can vouch for the result.</p>
                </div>
                <span class="text-[11px] font-semibold text-muted-foreground flex-shrink-0 mt-0.5" x-show="witnesses.length" x-cloak x-text="witnesses.length"></span>
            </div>

            <div class="mt-3 space-y-2" x-show="witnesses.length" x-cloak>
                <template x-for="w in witnesses" :key="w.id">
                    <div class="rounded-xl bg-muted/50 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <img :src="w.avatar" x-show="w.avatar" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">
                            <span class="w-7 h-7 rounded-full bg-accent text-primary grid place-items-center text-[11px] font-bold flex-shrink-0" x-show="!w.avatar" x-text="(w.name || '?').slice(0,1).toUpperCase()"></span>
                            <span class="text-sm font-semibold text-foreground truncate" x-text="w.name"></span>
                            <i class="bi bi-patch-check-fill text-primary text-xs flex-shrink-0" title="Platform member"></i>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0"
                                  :class="w.status==='accepted' ? 'bg-green-100 text-green-700' : (w.status==='declined' ? 'bg-gray-200 text-gray-500' : 'bg-amber-100 text-amber-700')"
                                  x-text="w.status==='accepted' ? 'Attending' : (w.status==='declined' ? 'Declined' : 'Invited')"></span>
                            {{-- inline rating (when given and not editing) --}}
                            <span class="flex items-center gap-0.5 ml-auto flex-shrink-0" x-show="w.rating && wEdit.id !== w.id">
                                <template x-for="n in 5" :key="n"><i class="bi text-[11px]" :class="n <= w.rating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i></template>
                            </span>
                            {{-- rate/edit (only the witness themselves) --}}
                            <button type="button" x-show="w.is_me && wEdit.id !== w.id" @click="startWitnessEdit(w)"
                                    class="m-press ml-auto text-[11px] font-bold text-primary flex-shrink-0" x-text="w.rating ? 'Edit' : 'Rate'"></button>
                            <button type="button" x-show="w.mine && wEdit.id !== w.id" @click="removeWitness(w)" class="m-press w-6 h-6 rounded-full text-muted-foreground hover:text-red-500 grid place-items-center flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>

                        {{-- comment (when given, read-only) --}}
                        <p class="text-[11px] text-muted-foreground italic mt-1 pl-9" x-show="w.comment && wEdit.id !== w.id" x-text="'“' + (w.comment || '') + '”'"></p>

                        {{-- edit form — shown to the witness when rating --}}
                        <div x-show="wEdit.id === w.id" x-cloak class="mt-2 pl-9">
                            <div class="flex items-center gap-1">
                                <template x-for="n in 5" :key="n">
                                    <button type="button" @click="wEdit.rating = n" class="m-press">
                                        <i class="bi text-lg" :class="n <= wEdit.rating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                    </button>
                                </template>
                            </div>
                            <textarea x-model="wEdit.comment" rows="2" maxlength="500" placeholder="Add a comment (optional)…"
                                      class="w-full mt-2 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="cancelWitnessEdit()" class="m-press flex-1 py-2 rounded-lg border border-border text-muted-foreground text-xs font-bold">Cancel</button>
                                <button type="button" @click="saveWitnessFeedback()" class="m-press flex-1 py-2 rounded-lg text-white text-xs font-bold" style="background: {{ $d['color'] }};">Save</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <p x-show="!witnesses.length" x-cloak class="text-[11px] text-muted-foreground mt-3">No witnesses added yet.</p>

            {{-- search platform members --}}
            <div class="relative mt-3" @click.outside="wopen=false">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input x-model="wq" @input.debounce.300ms="searchWitness()" @focus="wopen = wresults.length > 0" type="text"
                       placeholder="Search by name, phone or email…"
                       class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                <div x-show="wopen && wresults.length" x-cloak
                     class="absolute z-20 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden max-h-56 overflow-y-auto">
                    <template x-for="u in wresults" :key="u.id">
                        <button type="button" @click="addWitness(u.id)" :disabled="witnessBusy"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-muted/60 flex items-center gap-2 disabled:opacity-50">
                            <img :src="u.avatar" x-show="u.avatar" class="w-7 h-7 rounded-full object-cover flex-shrink-0" alt="">
                            <span class="w-7 h-7 rounded-full bg-accent text-primary grid place-items-center text-[11px] font-bold flex-shrink-0" x-show="!u.avatar" x-text="(u.name || '?').slice(0,1).toUpperCase()"></span>
                            <span class="font-semibold text-foreground truncate" x-text="u.name"></span>
                        </button>
                    </template>
                </div>
                <p x-show="wq.length >= 2 && !wsearching && !wresults.length && wopen" x-cloak class="text-[11px] text-muted-foreground mt-1.5 px-1">No members found.</p>
            </div>
        </div>
    </div>
    @endempty

    {{-- ===== Status-aware actions ===== --}}
    <div class="px-4 mt-4">
        {{-- incoming invite --}}
        <template x-if="status==='invite_incoming'">
            <div class="flex items-center gap-2">
                <button type="button" @click="decline()" class="m-press flex-1 py-3 rounded-2xl border border-gray-200 text-muted-foreground text-sm font-bold">Decline</button>
                <button type="button" @click="accept()" class="m-press flex-1 py-3 rounded-2xl text-white text-sm font-bold" style="background: {{ $d['color'] }};"><i class="bi bi-check2-circle"></i> Accept duel</button>
            </div>
        </template>

        {{-- active --}}
        <template x-if="status==='active'">
            <div>
                <button type="button" x-show="!reportOpen" @click="reportOpen=true"
                        class="m-press w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $d['color'] }};">
                    <i class="bi bi-clipboard-data"></i> Log result
                </button>
                <button type="button" x-show="!reportOpen" @click="cancelOpen=true"
                        class="m-press w-full mt-2 py-2.5 rounded-2xl border border-red-200 text-red-600 text-sm font-bold flex items-center justify-center gap-2">
                    <i class="bi bi-x-circle"></i> Cancel challenge
                </button>
                <div x-show="reportOpen" x-cloak class="m-card rounded-2xl p-4">
                    {{-- Single match: pick the winner --}}
                    <template x-if="format==='single'">
                        <div>
                            <p class="text-sm font-bold text-foreground text-center mb-3">Who won this duel?</p>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="submitSingle('rival')" :disabled="busy"
                                        class="m-press flex-1 py-3 rounded-2xl border border-gray-200 text-muted-foreground text-sm font-bold disabled:opacity-50" x-text="oppName"></button>
                                <button type="button" @click="submitSingle('me')" :disabled="busy"
                                        class="m-press flex-1 py-3 rounded-2xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};">
                                    <i class="bi bi-trophy"></i> I won
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Best of N: log each round's winner --}}
                    <template x-if="format==='bo3' || format==='bo5'">
                        <div>
                            <p class="text-sm font-bold text-foreground text-center mb-1">Log each round's winner</p>
                            <p class="text-[11px] text-muted-foreground text-center mb-3">
                                You <span class="font-bold text-foreground" x-text="roundTally('me')"></span>
                                · <span x-text="oppName"></span> <span class="font-bold text-foreground" x-text="roundTally('rival')"></span>
                            </p>
                            <div class="space-y-2">
                                <template x-for="i in maxRounds" :key="i">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-bold text-muted-foreground w-14">Round <span x-text="i"></span></span>
                                        <button type="button" @click="setRound(i-1,'me')"
                                                class="m-press flex-1 py-2 rounded-lg text-xs font-bold border-2 transition-colors"
                                                :class="roundWinners[i-1]==='me' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'">You</button>
                                        <button type="button" @click="setRound(i-1,'rival')"
                                                class="m-press flex-1 py-2 rounded-lg text-xs font-bold border-2 transition-colors"
                                                :class="roundWinners[i-1]==='rival' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'" x-text="oppName"></button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="submitRounds()" :disabled="busy"
                                    class="m-press w-full mt-3 py-3 rounded-2xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};"><i class="bi bi-check2-circle"></i> Submit result</button>
                        </div>
                    </template>

                    {{-- Points / time: enter a number each --}}
                    <template x-if="format==='points' || format==='time'">
                        <div>
                            <p class="text-sm font-bold text-foreground text-center mb-3" x-text="format==='time' ? 'Enter each time (lowest wins)' : 'Enter each score (highest wins)'"></p>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-[10px] text-muted-foreground mb-0.5 text-center">You</label>
                                    <input x-model="myScore" type="number" step="any" inputmode="decimal"
                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                </div>
                                <span class="text-muted-foreground font-black pb-2.5">vs</span>
                                <div class="flex-1">
                                    <label class="block text-[10px] text-muted-foreground mb-0.5 text-center truncate" x-text="oppName"></label>
                                    <input x-model="oppScore" type="number" step="any" inputmode="decimal"
                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                </div>
                            </div>
                            <button type="button" @click="submitScores()" :disabled="busy"
                                    class="m-press w-full mt-3 py-3 rounded-2xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};"><i class="bi bi-check2-circle"></i> Submit result</button>
                        </div>
                    </template>

                    <button type="button" @click="reportOpen=false" class="m-press w-full mt-3 py-2 text-xs font-semibold text-muted-foreground">Cancel</button>
                </div>
            </div>
        </template>

        {{-- reported — awaiting confirmation (two-party result) --}}
        <template x-if="status==='reported'">
            <div>
                <div x-show="reportedByMe" class="m-card rounded-2xl p-4 text-center">
                    <i class="bi bi-hourglass-split text-2xl text-amber-500"></i>
                    <p class="text-sm font-bold text-foreground mt-1">Awaiting confirmation</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ $d['opponent']['name'] }} needs to confirm the result you submitted.</p>
                </div>
                <div x-show="!reportedByMe" class="m-card rounded-2xl p-4">
                    <p class="text-sm font-bold text-foreground text-center">{{ $d['opponent']['name'] }} reported a result</p>
                    <p class="text-xs text-muted-foreground text-center mt-0.5">Winner: <span class="font-bold text-foreground">{{ $d['proposed_winner'] ?? '—' }}</span></p>
                    <div class="flex items-center gap-2 mt-3">
                        <button type="button" @click="disputeResult()" :disabled="busy"
                                class="m-press flex-1 py-3 rounded-2xl border border-red-200 text-red-600 text-sm font-bold disabled:opacity-50">Dispute</button>
                        <button type="button" @click="confirmResult()" :disabled="busy"
                                class="m-press flex-1 py-3 rounded-2xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};">
                            <i class="bi bi-check2-circle"></i> Confirm
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- sent invite --}}
        <template x-if="status==='invite_sent'">
            <div class="m-card rounded-2xl p-4 flex items-center justify-between">
                <span class="text-sm text-muted-foreground inline-flex items-center gap-2"><i class="bi bi-hourglass-split text-amber-500"></i> Waiting for {{ $d['opponent']['name'] }}</span>
                <button type="button" @click="cancelOpen=true" class="m-press px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-bold">Cancel</button>
            </div>
        </template>

        {{-- runtime-completed (after reporting a result without reload) --}}
        @if($status !== 'completed')
            <template x-if="status==='completed'">
                <div class="m-card rounded-2xl p-4 text-center">
                    <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white m-float" :style="won ? 'background:#10b981' : 'background:#94a3b8'">
                        <i class="bi text-2xl" :class="won ? 'bi-trophy-fill' : 'bi-emoji-neutral'"></i>
                    </div>
                    <p class="text-sm font-black mt-2" :class="won ? 'text-green-600' : 'text-muted-foreground'" x-text="won ? 'You won 🏆' : 'Result saved'"></p>
                    <p class="text-xs text-muted-foreground mt-0.5">Recorded to your duel history.</p>
                </div>
            </template>
        @endif
        <template x-if="status==='declined' || status==='cancelled'">
            <div class="m-card rounded-2xl p-4 text-center">
                <p class="text-sm font-bold text-muted-foreground"><i class="bi bi-x-circle"></i> <span x-text="status==='declined' ? 'Duel declined' : 'Challenge cancelled'"></span></p>
                <p x-show="status==='cancelled' && cancelReason" x-cloak class="text-xs text-muted-foreground mt-1 italic" x-text="'“' + cancelReason + '”'"></p>
            </div>
        </template>

        {{-- completed --}}
        @if($status === 'completed')
            <a href="{{ route('me.challenge.create') }}" data-shell-link data-route="me.challenge"
               class="m-press w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $d['color'] }};">
                <i class="bi bi-arrow-repeat"></i> Rematch
            </a>
        @endif
    </div>

    {{-- ===== Challenge media — participants & witnesses cover the duel / attach result proof ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            @include('personal.partials.duel-media')
        </div>
    </div>

    {{-- ===== Super-admin moderation ===== --}}
    @if(auth()->user()->isSuperAdmin())
        <div class="px-4 mt-6">
            <button type="button" @click="deleteDuel()"
                    class="m-press w-full py-2.5 rounded-2xl border border-red-300 text-red-600 text-xs font-bold inline-flex items-center justify-center gap-1.5">
                <i class="bi bi-shield-lock"></i> Delete challenge (admin)
            </button>
        </div>
    @endif

    {{-- ===== Edit duel — bottom-sheet (teleported so it escapes the staggered transform) ===== --}}
    @if(!empty($d['can_edit']))
    <template x-teleport="body">
        <div>
            <div x-show="editOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="editOpen=false"></div>
            <div x-show="editOpen" x-cloak
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl text-white" style="background: linear-gradient(160deg, {{ $d['color'] }}, {{ $d['color'] }}cc);">
                    <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
                    <div class="flex items-center justify-between mt-3">
                        <h2 class="text-base font-black">Edit duel</h2>
                        <button type="button" @click="editOpen=false" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Discipline</label>
                        <input x-model="form.discipline" type="text" placeholder="e.g. 100m sprint"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Scoring format</label>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="o in [{v:'single',l:'Single'},{v:'bo3',l:'Best of 3'},{v:'bo5',l:'Best of 5'},{v:'points',l:'Points'},{v:'time',l:'Time'}]" :key="o.v">
                                <button type="button" @click="form.format=o.v"
                                        class="m-press py-2 rounded-lg text-xs font-bold border-2 transition-colors"
                                        :class="form.format===o.v ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'" x-text="o.l"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stake (points)</label>
                        <input x-model.number="form.stake" type="number" min="0" max="100000"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Trash talk <span class="text-muted-foreground font-normal">(optional)</span></label>
                        <textarea x-model="form.message" rows="2" placeholder="Say something…"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
                    </div>
                    <p class="text-[11px] text-muted-foreground">Location, event and opponent are set when the duel is created.</p>
                </div>
                <div class="flex-shrink-0 px-4 pt-3 border-t border-border bg-background" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveEdit()" :disabled="busy"
                            class="m-press w-full py-3 rounded-2xl text-white font-black text-sm flex items-center justify-center gap-2 disabled:opacity-50" style="background: {{ $d['color'] }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i> Save changes
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- ===== Cancel challenge — reason bottom-sheet (teleported) ===== --}}
    <template x-teleport="body">
        <div>
            <div x-show="cancelOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="cancelOpen=false"></div>
            <div x-show="cancelOpen" x-cloak
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl text-white" style="background: linear-gradient(160deg, #ef4444, #b91c1c);">
                    <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
                    <div class="flex items-center justify-between mt-3">
                        <h2 class="text-base font-black">Cancel challenge</h2>
                        <button type="button" @click="cancelOpen=false" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    <p class="text-sm text-muted-foreground">This ends the duel for both of you. Let your opponent know why.</p>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Reason <span class="text-muted-foreground font-normal">(optional)</span></label>
                        <textarea x-model="cancelReason" rows="3" maxlength="300" placeholder="e.g. Injured, schedule clash, agreed to call it off…"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
                    </div>
                </div>
                <div class="flex-shrink-0 px-4 pt-3 border-t border-border bg-background" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="cancelOpen=false" class="m-press flex-1 py-3 rounded-2xl border border-border text-foreground font-bold text-sm">Keep it</button>
                        <button type="button" @click="confirmCancel()" :disabled="busy"
                                class="m-press flex-1 py-3 rounded-2xl bg-destructive text-white font-black text-sm flex items-center justify-center gap-2 disabled:opacity-50">
                            <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i> Cancel challenge
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection
