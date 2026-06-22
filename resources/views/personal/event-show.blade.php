@extends('layouts.personal-mobile')

@section('title', $e['title'])

{{--
    Event detail — mobile. DUMMY content driven by PersonalMobileController@eventShow.
    Stylish, animated single-event page: gradient cover, going/like actions,
    capacity, agenda timeline, attendees, location, share. Reuses the shared
    mobile motion vocabulary (m-hero, m-card, m-press, m-bar-fill, m-float) and
    design tokens. Wire the action buttons to real endpoints when ready.
--}}
@section('personal-content')
@php
    $pPaid   = !str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified');
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = $hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free');
@endphp
<div x-data="{
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Action failed');
                return data;
            } catch (e) { window.showToast('error', e.message); return null; }
            finally { this.busy = false; }
        },
        get registered() { return this.going || this.watching; },
        // Registration is FINAL — joining is one-way, no self-cancel.
        async toggleGoing() {
            if (this.byQual) { window.showToast('info','Entry is by qualification only'); return; }
            if (this.registered || this.busy) return;
            const ok = await window.confirmAction({
                title: 'Confirm your spot?',
                message: @js($pPaid ? 'Once you join, your place is final and the '.$e['participant_fee'].' fee is due at the club.' : "Once you join, your place is final — you can't cancel later."),
                type: 'primary', confirmText: @js($pPaid ? 'Join & owe fee' : "I'm in"),
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
                        title: 'Not in a competing category',
                        message: (d.message || '') + ' Get a spectator ticket to watch the matches?',
                        type: 'primary', confirmText: 'Get spectator ticket',
                    });
                    if (watch) this.toggleWatch(true);
                    return;
                }
                window.showToast('error', d.message || 'Couldn’t join');
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
                    title: 'Get your ticket?',
                    message: @js($ticketPaid ? 'Tickets are final — the '.$e['spectator']['fee'].' fee is due at the door.' : 'Add yourself to the guest list?'),
                    type: 'primary', confirmText: @js($ticketPaid ? 'Book ticket' : 'Get pass'),
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
                remove:    { t: 'Remove ' + name + '?', m: 'They’ll be taken out of this event but can join again later.', c: 'Remove', ty: 'danger' },
                block:     { t: 'Block ' + name + '?', m: 'They’ll be removed and barred from re-joining THIS event (competing or watching).', c: 'Block', ty: 'danger' },
                blacklist: { t: 'Blacklist ' + name + '?', m: 'They’ll be removed and barred from ALL your club’s events (competing or watching).', c: 'Blacklist', ty: 'danger' },
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
            const scope = u.scope === 'club' ? 'Blacklisted · all club events' : 'Blocked · this event';
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
            btn.appendChild(document.createTextNode(' Unblock'));
            btn.addEventListener('click', () => this.unblock(u.id));
            row.appendChild(btn);
            list.appendChild(row);
        },
        async unblock(id) {
            const ok = await window.confirmAction({ title: 'Unblock?', message: 'They’ll be allowed to join this event again.', type: 'primary', confirmText: 'Unblock' });
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
            const ok = await window.confirmAction({ title: 'Cancel this event?', message: 'It stays visible but is flagged cancelled for everyone.', type: 'danger', confirmText: 'Cancel event' });
            if (!ok) return;
            const d = await this.req('{{ route('me.events.cancel-event', $e['key']) }}', 'PATCH');
            if (d) { this.cancelled = true; window.showToast('info', d.message); }
        },
        async deleteEvent() {
            this.manageOpen = false;
            const ok = await window.confirmAction({ title: 'Delete this event?', message: 'This permanently removes the event and all its registrations.', type: 'danger', confirmText: 'Delete' });
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
            const ok = await window.confirmAction({ title: 'Remove expense?', message: 'This expense will be deleted from the event.', type: 'danger', confirmText: 'Remove' });
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
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not save winners');
                this.results = data.results || payload.results;
                this.resultsOpen = false;
                window.showToast('success', data.message);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        medal(place) { return ({1:'#f59e0b',2:'#9ca3af',3:'#b45309'})[place] || '{{ $e['color'] }}'; },
        get pct() { return Math.round(this.goingCount / this.cap * 100); }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- top bar (z-50 so the manage dropdown paints above the title block below) --}}
        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events') }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('share-event')"
                        class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Share">
                    <i class="bi bi-share text-base"></i>
                </button>
                @if($canManage ?? false)
                    <div class="relative" @click.outside="manageOpen=false">
                        <button type="button" @click="manageOpen=!manageOpen"
                                class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Manage">
                            <i class="bi bi-three-dots-vertical text-base"></i>
                        </button>
                        <div x-show="manageOpen" x-cloak
                             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 top-12 z-40 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1 text-foreground"
                             style="transform-origin: top right;">
                            <button type="button" @click="goEdit()"
                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors">
                                <i class="bi bi-pencil"></i> Edit event
                            </button>
                            @if(!($isTkd ?? false))
                                <button type="button" @click="openResults()"
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors">
                                    <i class="bi bi-trophy"></i> Set winners
                                </button>
                            @endif
                            <button type="button" @click="cancelEvent()" x-show="!cancelled"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-amber-600 hover:bg-amber-50 transition-colors">
                                <i class="bi bi-slash-circle"></i> Cancel event
                            </button>
                            <button type="button" @click="deleteEvent()"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <i class="bi bi-trash"></i> Delete event
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- cancelled banner --}}
        <div x-show="cancelled" x-cloak class="relative z-10 mt-4 -mb-2 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i> This event has been cancelled.
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $e['icon'] }}"></i> {{ $e['type'] }}
                </span>
                @if(!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                @endif
                @if(($e['scope'] ?? 'internal') !== 'internal' && !empty($e['scope_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-broadcast"></i> {{ $e['scope_label'] }}</span>
                @endif
                @if($pPaid || $byQual)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-cash-coin"></i> Paid entry</span>
                @endif
                @if($ticketPaid)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ticket-perforated"></i> Ticketed</span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </header>

    {{-- ===== Quick facts card (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['wday'] }} {{ $e['day'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $e['mon'] }}</p>
                </div>
                <div class="border-x border-gray-100">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-clock"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['time'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $e['duration'] }}</p>
                </div>
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $e['participant_fee'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ $byQual ? 'Entry' : 'To join' }}</p>
                </div>
            </div>

            {{-- capacity --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[11px] mb-1.5">
                    <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> going</span>
                    <span class="text-muted-foreground"><span x-text="cap - goingCount">{{ $e['cap'] - $e['going'] }}</span> spots left</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                    <div class="m-bar-fill h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Like / interest row ===== --}}
    <div class="px-4 mt-3">
        <div class="flex items-center gap-2">
            <button type="button" @click="toggleLike()"
                    class="m-press flex-1 py-2.5 rounded-2xl border text-sm font-semibold flex items-center justify-center gap-2 transition-colors"
                    :class="liked ? 'bg-red-50 border-red-200 text-red-600' : 'bg-white border-gray-100 text-muted-foreground'">
                <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i>
                <span x-text="likes">36</span>
            </button>
            <button type="button" @click="window.showToast('info','Reminder set 1h before')"
                    class="m-press flex-1 py-2.5 rounded-2xl border border-gray-100 bg-white text-muted-foreground text-sm font-semibold flex items-center justify-center gap-2">
                <i class="bi bi-bell"></i> Remind
            </button>
            <button type="button" @click="$dispatch('share-event')"
                    class="m-press flex-1 py-2.5 rounded-2xl border border-gray-100 bg-white text-muted-foreground text-sm font-semibold flex items-center justify-center gap-2">
                <i class="bi bi-share"></i> Share
            </button>
        </div>
    </div>

    {{-- ===== Winners / results ===== --}}
    <div class="px-4 mt-4" x-show="results.length > 0" x-cloak>
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> Winners</h2>
                @if($canManage ?? false)
                    <button type="button" @click="openResults()" class="m-press text-[11px] font-bold text-primary px-2 py-1 rounded-lg bg-accent"><i class="bi bi-pencil"></i> Edit</button>
                @endif
            </div>
            <div class="mt-3 space-y-2">
                <template x-for="w in results" :key="w.place + '-' + w.name">
                    <div class="flex items-center gap-3 rounded-xl p-2.5" :style="`background:${medal(w.place)}12`">
                        <div class="w-9 h-9 rounded-full grid place-items-center text-white flex-shrink-0 font-black text-xs" :style="`background:${medal(w.place)}`">
                            <i class="bi" :class="w.place===1 ? 'bi-trophy-fill' : (w.place<=3 ? 'bi-award-fill' : 'bi-award')" x-show="w.place<=3"></i>
                            <span x-show="w.place>3" x-text="w.place"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground truncate" x-text="w.name"></p>
                            <p class="text-[11px] text-muted-foreground" x-text="w.place===1 ? 'Champion' : (w.place===2 ? 'Runner-up' : (w.place===3 ? '3rd place' : ('#' + w.place)))"></p>
                        </div>
                        <span class="text-[11px] font-black flex-shrink-0" :style="`color:${medal(w.place)}`" x-text="w.prize"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ===== Manager: record winners (finished events) ===== --}}
    {{-- Taekwondo championships derive the podium from the brackets (wins/losses) — no manual entry. --}}
    @if(($canManage ?? false) && !($isTkd ?? false))
        <div class="px-4 mt-4" x-show="results.length === 0">
            <button type="button" @click="openResults()"
                    class="m-press w-full py-3 rounded-2xl border-2 border-dashed border-gray-200 text-sm font-bold text-foreground flex items-center justify-center gap-2">
                <i class="bi bi-trophy"></i> Record winners / results
            </button>
        </div>
    @endif

    {{-- ===== Show results (everyone, when finals decided) + Finance (owner) ===== --}}
    @if(!empty($e['bracket_results']) || ($finance ?? false))
        <div class="px-4 mt-4 flex gap-2">
            @if(!empty($e['bracket_results']))
                <button type="button" @click="showResultsOpen=true"
                        class="m-press flex-1 py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $e['color'] }};">
                    <i class="bi bi-trophy-fill"></i> Show results
                </button>
            @endif
            @if($finance ?? false)
                <button type="button" @click="financeOpen=true"
                        class="m-press flex-1 py-3 rounded-2xl border-2 text-sm font-bold flex items-center justify-center gap-2"
                        style="border-color: {{ $e['color'] }}; color: {{ $e['color'] }};">
                    <i class="bi bi-cash-stack"></i> Finance
                </button>
            @endif
        </div>
    @endif

    {{-- ===== About ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-info-circle text-primary"></i> About this event</h2>
            <p class="text-sm text-muted-foreground leading-relaxed mt-2">{{ $e['about'] }}</p>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($e['tags'] as $t)
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-muted-foreground">#{{ $t }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Prize & divisions (tournaments / championships) ===== --}}
    @if(!empty($e['prize']) || !empty($e['divisions']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy text-primary"></i> Prize &amp; divisions</h2>
                @if(!empty($e['prize']))
                    <div class="mt-3 rounded-xl p-3 flex items-center gap-3" style="background: {{ $e['color'] }}0d; border: 1px solid {{ $e['color'] }}26;">
                        <div class="w-10 h-10 rounded-xl grid place-items-center text-white flex-shrink-0" style="background: {{ $e['color'] }};"><i class="bi bi-award-fill text-lg"></i></div>
                        <div>
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Prize pool</p>
                            <p class="text-sm font-black text-foreground">{{ $e['prize'] }}</p>
                        </div>
                    </div>
                @endif
                @if(!empty($e['divisions']))
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($e['divisions'] as $d)
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted text-foreground">{{ $d }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== League: standings + fixtures ===== --}}
    @if(!empty($e['league']))
        @php $lg = $e['league']; @endphp
        @if(!empty($lg['standings']))
            <div class="px-4 mt-4">
                <div class="m-card rounded-2xl p-4">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-table text-primary"></i> Standings</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="text-muted-foreground text-[10px] uppercase tracking-wide">
                                    <th class="text-left font-semibold pb-2 pl-1">#</th>
                                    <th class="text-left font-semibold pb-2">Team</th>
                                    <th class="font-semibold pb-2 w-7">P</th>
                                    <th class="font-semibold pb-2 w-7">W</th>
                                    <th class="font-semibold pb-2 w-7">D</th>
                                    <th class="font-semibold pb-2 w-7">L</th>
                                    <th class="font-semibold pb-2 w-9">GD</th>
                                    <th class="font-semibold pb-2 w-9 text-right pr-1">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lg['standings'] as $i => $row)
                                    <tr class="border-t border-gray-50 {{ $i < 3 ? 'font-semibold' : '' }}">
                                        <td class="py-2 pl-1 text-muted-foreground">{{ $i + 1 }}</td>
                                        <td class="py-2 text-foreground truncate max-w-[120px]">{{ $row['team'] }}</td>
                                        <td class="py-2 text-center text-muted-foreground">{{ $row['p'] }}</td>
                                        <td class="py-2 text-center text-muted-foreground">{{ $row['w'] }}</td>
                                        <td class="py-2 text-center text-muted-foreground">{{ $row['d'] }}</td>
                                        <td class="py-2 text-center text-muted-foreground">{{ $row['l'] }}</td>
                                        <td class="py-2 text-center text-muted-foreground">{{ $row['gd'] > 0 ? '+' : '' }}{{ $row['gd'] }}</td>
                                        <td class="py-2 text-right pr-1 font-black" style="color: {{ $e['color'] }};">{{ $row['pts'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if(!empty($lg['fixtures']))
            <div class="px-4 mt-4">
                <div class="m-card rounded-2xl p-4">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-calendar2-week text-primary"></i> Fixtures</h2>
                    <div class="space-y-2">
                        @foreach($lg['fixtures'] as $f)
                            @php $played = $f['home_score'] !== null && $f['away_score'] !== null; @endphp
                            <div class="flex items-center gap-2 rounded-xl bg-muted/40 px-3 py-2">
                                <span class="flex-1 text-right text-sm font-semibold text-foreground truncate">{{ $f['home'] }}</span>
                                @if($played)
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-black text-white" style="background: {{ $e['color'] }};">{{ $f['home_score'] }} – {{ $f['away_score'] }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-white text-muted-foreground border border-gray-100">{{ $f['date'] ?: 'vs' }}</span>
                                @endif
                                <span class="flex-1 text-left text-sm font-semibold text-foreground truncate">{{ $f['away'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ===== Requirements (belt tests / gradings) ===== --}}
    @if(!empty($e['requirements']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> Requirements</h2>
                <ul class="mt-3 space-y-2.5">
                    @foreach($e['requirements'] as $req)
                        <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                            <span class="w-5 h-5 rounded-full grid place-items-center flex-shrink-0 mt-0.5 text-white text-[10px]" style="background: {{ $e['color'] }};"><i class="bi bi-check-lg"></i></span>
                            <span>{{ $req }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ===== Pricing & tickets ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-tag text-primary"></i> Entry &amp; tickets</h2>

            {{-- Participant fee --}}
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $pPaid ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600' }}"><i class="bi bi-person-check text-lg"></i></div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">{{ $byQual ? 'Take part' : 'Join as participant' }}</p>
                    <p class="text-[11px] text-muted-foreground">
                        @if(!($canCompete ?? true)) <span class="text-amber-600 font-semibold">Not eligible to compete · spectators only</span>
                        @elseif($byQual) Reserved for qualified finalists
                        @elseif($pPaid) Fee paid to the club (proof at reception)
                        @else Free for members @endif
                    </p>
                </div>
                <span class="text-sm font-black flex-shrink-0 {{ $pPaid ? 'text-amber-600' : 'text-foreground' }}">{{ $e['participant_fee'] }}</span>
            </div>

            {{-- Spectator ticket --}}
            @if($hasTicket)
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                    <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $ticketPaid ? 'bg-purple-50 text-primary' : 'bg-sky-50 text-sky-600' }}"><i class="bi bi-ticket-perforated text-lg"></i></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">Spectator ticket</p>
                        <p class="text-[11px] text-muted-foreground"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> watching{{ $ticketPaid ? ' · entry to watch the matches' : ' · free to watch' }}</p>
                    </div>
                    <span class="text-sm font-black flex-shrink-0 {{ $ticketPaid ? 'text-primary' : 'text-sky-600' }}">{{ $e['spectator']['fee'] }}</span>
                </div>
                <button type="button" @click="toggleWatch()" :disabled="registered || {{ ($banned ?? false) ? 'true' : 'false' }}"
                        class="m-press mt-3 w-full py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors border disabled:cursor-not-allowed disabled:opacity-60"
                        :class="watching ? 'bg-green-50 text-green-700 border-green-200' : (going ? 'bg-muted text-muted-foreground border-gray-100' : 'border-gray-200 text-foreground')">
                    <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    <span x-text="watching ? 'Ticket booked' : (going ? 'You’re a participant' : '{{ ($banned ?? false) ? 'Not available' : ($ticketPaid ? 'Buy ticket to watch · '.$e['spectator']['fee'] : 'Get free spectator pass') }}')"></span>
                </button>
            @endif
        </div>
    </div>

    {{-- ===== Tournament lifecycle timeline (phases) ===== --}}
    @if(!empty($e['phases']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-signpost-split text-primary"></i> Tournament timeline</h2>
                <div class="mt-3">
                    @foreach($e['phases'] as $i => $ph)
                        @php
                            // Status is derived from the date — past = done, today = now, future = upcoming.
                            $pdate  = !empty($ph['date']) ? rescue(fn () => \Carbon\Carbon::parse($ph['date']), null, false) : null;
                            $today  = \Carbon\Carbon::today();
                            $done   = $pdate && $pdate->lt($today);
                            $active = $pdate && $pdate->isSameDay($today);
                            $dot = $done ? '#10b981' : ($active ? $e['color'] : '#d1d5db');
                        @endphp
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[11px] flex-shrink-0" style="background: {{ $dot }};">
                                    <i class="bi {{ $done ? 'bi-check-lg' : $ph['icon'] }}"></i>
                                </span>
                                @if(!$loop->last)<span class="w-0.5 flex-1 my-1" style="background: {{ $done ? '#10b981' : '#e5e7eb' }};"></span>@endif
                            </div>
                            <div class="pb-4 -mt-0.5 min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-bold {{ $active ? '' : 'text-foreground' }}" style="{{ $active ? 'color:'.$e['color'] : '' }}">{{ $ph['label'] }}</p>
                                    <span class="text-[11px] font-semibold text-muted-foreground flex-shrink-0">{{ $pdate ? $pdate->format('M j') : '' }}</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground">{{ $ph['note'] }}</p>
                                @if($active)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white" style="background: {{ $e['color'] }};">NOW</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Brackets & draws entry ===== --}}
    @if(!empty($e['categories']))
        @php
            $catCount = count($e['categories']);
            $athleteTotal = collect($e['categories'])->sum('joined');
        @endphp
        <div class="px-4 mt-4">
            <a href="{{ route('me.events.bracket', $e['key']) }}" data-shell-link data-route="me.events"
               class="block m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg"
               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-diagram-3-fill text-2xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">Brackets &amp; draws</h3>
                        <p class="text-xs text-white/85 mt-0.5">{{ $catCount }} {{ \Illuminate\Support\Str::plural(strtolower($e['division_label'] ?? 'category'), $catCount) }} · {{ $athleteTotal }} entrants · live results</p>
                    </div>
                    <i class="bi bi-chevron-right text-white/80"></i>
                </div>
            </a>
        </div>
    @endif

    {{-- ===== Agenda timeline ===== --}}
    @if(!empty($e['agenda']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> Schedule</h2>
            <div class="mt-3 space-y-0">
                @foreach($e['agenda'] as $i => $a)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $e['color'] }};"></span>
                            @if(!$loop->last)<span class="w-0.5 flex-1 bg-gray-100 my-1"></span>@endif
                        </div>
                        <div class="pb-4 -mt-1">
                            @php $at = !empty($a['t']) ? rescue(fn () => \Carbon\Carbon::parse($a['t']), null, false) : null; @endphp
                            <p class="text-xs font-bold text-foreground">{{ $at ? $at->format('M j · g:i A') : $a['t'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $a['d'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Participants (people who already joined) ===== --}}
    <div class="px-4 mt-4">
        @php $showTabs = $hasTicket || ($canManage ?? false); @endphp
        <div class="m-card rounded-2xl p-4" x-data="{ rtab: 'participants' }">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <i class="bi bi-people text-primary"></i> {{ $byQual ? 'Finalists' : 'Who’s joined' }}
                </h2>
                @unless($showTabs)
                    <span class="text-[11px] font-semibold text-primary" x-text="`${goingCount} in`">{{ $e['participants_total'] ?? $e['going'] }} in</span>
                @endunless
            </div>

            @if($showTabs)
                {{-- Tabs: competitors · spectators · (manager) blocked --}}
                <div class="flex gap-2 mt-3 overflow-x-auto">
                    <button type="button" @click="rtab='participants'"
                            class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                            :class="rtab==='participants' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'">
                        <i class="bi bi-person-arms-up"></i> Participants
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="goingCount">{{ $e['participants_total'] }}</span>
                    </button>
                    @if($hasTicket)
                        <button type="button" @click="rtab='spectators'"
                                class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                                :class="rtab==='spectators' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'">
                            <i class="bi bi-eye"></i> Spectators
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="spectators">{{ $e['spectators_total'] }}</span>
                        </button>
                    @endif
                    @if($canManage ?? false)
                        <button type="button" @click="rtab='blocked'"
                                class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"
                                :class="rtab==='blocked' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'">
                            <i class="bi bi-shield-x"></i> Blocked
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary" x-text="blockedCount">{{ count($e['bans_list'] ?? []) }}</span>
                        </button>
                    @endif
                </div>
            @endif

            {{-- Participants (competitors only) --}}
            <div class="mt-3 space-y-2.5" @if($showTabs) x-show="rtab==='participants'" x-transition @endif>
                @forelse($e['participants'] as $i => $pp)
                    @php $initials = collect(explode(' ', $pp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                    <div class="flex items-center gap-3" @if($pp['id'] ?? false) id="prow-{{ $pp['id'] }}" @endif>
                        <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                             style="background: hsl({{ ($i * 67) % 360 }} 55% 58%);">{{ $initials }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $pp['name'] }}</p>
                            @php
                                $bits = array_filter([
                                    $pp['gender'] ?? null,
                                    $pp['category'] ?? null,
                                    $pp['weight_class'] ?? null,
                                ]);
                            @endphp
                            <p class="text-[11px] text-muted-foreground truncate">{{ $bits ? implode(' · ', $bits) : $pp['meta'] }}</p>
                        </div>
                        @if(($pp['paid'] ?? true))
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 flex-shrink-0"><i class="bi bi-check2"></i> Joined</span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> Pending</span>
                        @endif
                        @if(($canManage ?? false) && ($pp['id'] ?? false))
                            <x-event-moderate-menu :id="$pp['id']" :name="$pp['name']" />
                        @endif
                    </div>
                @empty
                    <p class="text-[11px] text-muted-foreground text-center py-3">No competitors yet.</p>
                @endforelse
                @php $more = max(($e['participants_total'] ?? count($e['participants'])) - count($e['participants']), 0); @endphp
                @if($more > 0)
                    <p class="text-[11px] text-muted-foreground text-center pt-1">+ {{ $more }} more</p>
                @endif
            </div>

            @if($hasTicket)
                {{-- Spectators (ticket holders) --}}
                <div class="mt-3 space-y-2.5" x-show="rtab==='spectators'" x-cloak x-transition>
                    @forelse($e['spectators_list'] as $i => $sp)
                        @php $sinitials = collect(explode(' ', $sp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                        <div class="flex items-center gap-3" @if($sp['id'] ?? false) id="srow-{{ $sp['id'] }}" @endif>
                            <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                                 style="background: hsl({{ (($i + 3) * 53) % 360 }} 45% 60%);">{{ $sinitials }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate">{{ $sp['name'] }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">Spectator{{ str_contains(strtolower($e['spectator']['fee']),'free') ? '' : ' · '.$e['spectator']['fee'] }}</p>
                            </div>
                            @if(($sp['paid'] ?? true))
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 flex-shrink-0"><i class="bi bi-ticket-perforated"></i> {{ str_contains(strtolower($e['spectator']['fee']),'free') ? 'Pass' : 'Ticket' }}</span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> Pending</span>
                            @endif
                            @if(($canManage ?? false) && ($sp['id'] ?? false))
                                <x-event-moderate-menu :id="$sp['id']" :name="$sp['name']" />
                            @endif
                        </div>
                    @empty
                        <p class="text-[11px] text-muted-foreground text-center py-3">No spectators yet.</p>
                    @endforelse
                    @php $smore = max(($e['spectators_total'] ?? 0) - count($e['spectators_list'] ?? []), 0); @endphp
                    @if($smore > 0)
                        <p class="text-[11px] text-muted-foreground text-center pt-1">+ {{ $smore }} more</p>
                    @endif
                </div>
            @endif

            @if($canManage ?? false)
                {{-- Blocked / blacklisted (manager only) --}}
                <div class="mt-3" x-show="rtab==='blocked'" x-cloak x-transition>
                    <div id="blocked-list" class="space-y-2.5">
                        @foreach($e['bans_list'] ?? [] as $bn)
                            @php $binit = collect(explode(' ', $bn['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                            <div id="brow-{{ $bn['id'] }}" class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0 bg-gray-400">{{ $binit }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $bn['name'] }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $bn['scope'] === 'club' ? 'Blacklisted · all club events' : 'Blocked · this event' }}</p>
                                </div>
                                <button type="button" @click="unblock({{ $bn['id'] }})"
                                        class="m-press text-[10px] font-bold px-2.5 py-1 rounded-full border border-gray-200 text-foreground hover:bg-muted flex-shrink-0">
                                    <i class="bi bi-arrow-counterclockwise"></i> Unblock
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <p id="blocked-empty" class="text-[11px] text-muted-foreground text-center py-3" @if(count($e['bans_list'] ?? [])) style="display:none" @endif>No one is blocked.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Location ===== --}}
    @php
        // Only ever allow http(s) URLs into an href (defense-in-depth vs javascript: URIs).
        $locUrl = (is_string($e['location_url'] ?? null) && preg_match('#^https?://#i', $e['location_url'])) ? $e['location_url'] : null;
    @endphp
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl overflow-hidden">
            @if(!empty($e['lat']) && !empty($e['lng']))
                {{-- Live location map (our shared map component, read-only display) --}}
                <x-location-map
                    :id="'evtmap'.$e['id']"
                    :lat="$e['lat']" :lng="$e['lng']"
                    :draggable="false" :readonly="true" :show-address="false" :show-coords="false" :show-labels="false"
                    height="7rem" :zoom="15" map-class="bg-muted/30" />
                <script>
                    (function () {
                        var id = 'evtmap{{ $e['id'] }}', lat = {{ $e['lat'] }}, lng = {{ $e['lng'] }}, tries = 0;
                        (function go() {
                            if (window.LocationMap) {
                                window.LocationMap.create({ id: id, defaultLat: lat, defaultLng: lng, zoom: 15, draggable: false, readonly: true });
                            } else if (tries++ < 60) {
                                setTimeout(go, 100);
                            }
                        })();
                    })();
                </script>
            @elseif($locUrl)
                <a href="{{ $locUrl }}" target="_blank" rel="noopener"
                   class="h-28 flex flex-col items-center justify-center gap-1" style="background: linear-gradient(135deg, {{ $e['color'] }}22, {{ $e['color'] }}11);">
                    <i class="bi bi-geo-alt-fill text-3xl m-float" style="color: {{ $e['color'] }};"></i>
                    <span class="text-xs font-bold text-primary">Open in Google Maps</span>
                </a>
            @else
                <div class="h-28 relative grid place-items-center"
                     style="background: linear-gradient(135deg, {{ $e['color'] }}22, {{ $e['color'] }}11);">
                    <i class="bi bi-geo-alt-fill text-3xl m-float" style="color: {{ $e['color'] }};"></i>
                </div>
            @endif
            <div class="p-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-foreground">{{ $e['location'] }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ $e['address'] }}</p>
                </div>
                @php
                    // Google Maps turn-by-turn directions to the venue (coords > pasted link > place name).
                    $dirHref = (!empty($e['lat']) && !empty($e['lng']))
                        ? 'https://www.google.com/maps/dir/?api=1&destination=' . $e['lat'] . ',' . $e['lng']
                        : ($locUrl
                            ?: ($e['location'] && $e['location'] !== 'TBA' ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($e['location']) : null));
                @endphp
                @if($dirHref)
                    <a href="{{ $dirHref }}" target="_blank" rel="noopener"
                       class="m-press flex-shrink-0 px-3 py-1.5 rounded-lg bg-accent text-primary text-xs font-bold flex items-center gap-1.5">
                        <i class="bi bi-cursor"></i> Directions
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Join action ===== --}}
    <div class="px-4 mt-4">
        @if($e['ended'] ?? false)
            {{-- Event finished — view only. --}}
            <div class="m-card rounded-2xl p-4 flex items-center gap-3 text-muted-foreground">
                <i class="bi bi-flag-fill text-lg"></i>
                <div class="leading-tight">
                    <p class="text-sm font-bold text-foreground">This event has ended</p>
                    <p class="text-[11px]">Registration is closed — view only.</p>
                </div>
            </div>
        @elseif($banned ?? false)
            {{-- Removed/blocked by the organiser — no join, no ticket. --}}
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <i class="bi bi-shield-x text-lg text-red-500"></i>
                <div class="leading-tight">
                    <p class="text-sm font-bold text-foreground">You can’t join this event</p>
                    <p class="text-[11px] text-muted-foreground">{{ $eligReason ?? 'The organiser has removed you from this event.' }}</p>
                </div>
            </div>
        @elseif($byQual)
            {{-- Spectator-first event: participation is by qualification, so the
                 primary CTA is the ticket to watch. --}}
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Ticket</p>
                    <p class="text-base font-black text-foreground">{{ $hasTicket ? $e['spectator']['fee'] : '—' }}</p>
                </div>
                <button type="button" @click="toggleWatch()" :disabled="registered"
                        class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed"
                        :class="watching ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                        :style="watching ? '' : 'background: {{ $e['color'] }}'">
                    <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    <span x-text="watching ? 'Ticket booked' : 'Buy ticket to watch'"></span>
                </button>
            </div>
        @elseif(!($canCompete ?? true))
            {{-- Not eligible to COMPETE (e.g. wrong age/weight category) — spectating only. --}}
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-info-circle-fill text-base mt-0.5" style="color: {{ $e['color'] }};"></i>
                    <p class="text-[12px] text-muted-foreground leading-snug">{{ $eligReason ?? 'You’re not eligible to compete in this event.' }}</p>
                </div>
                @if($hasTicket)
                    <div class="mt-3 flex items-center gap-3">
                        <div class="leading-tight">
                            <p class="text-[10px] text-muted-foreground uppercase tracking-wide">Ticket</p>
                            <p class="text-base font-black text-foreground">{{ $e['spectator']['fee'] }}</p>
                        </div>
                        <button type="button" @click="toggleWatch()" :disabled="registered"
                                class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed"
                                :class="watching ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                                :style="watching ? '' : 'background: {{ $e['color'] }}'">
                            <i class="bi" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                            <span x-text="watching ? 'Ticket booked' : 'Join as spectator{{ $ticketPaid ? ' · '.$e['spectator']['fee'] : '' }}'"></span>
                        </button>
                    </div>
                @else
                    <div class="mt-3 w-full py-3 rounded-2xl bg-muted text-muted-foreground text-sm font-bold flex items-center justify-center gap-2">
                        <i class="bi bi-lock"></i> Spectating not available
                    </div>
                @endif
            </div>
        @else
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ $pPaid ? 'Entry fee' : 'Entry' }}</p>
                    <p class="text-base font-black text-foreground">{{ $e['participant_fee'] }}</p>
                </div>
                <button type="button" @click="toggleGoing()" :disabled="registered"
                        class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors disabled:cursor-not-allowed"
                        :class="going ? 'bg-green-50 text-green-700 border border-green-200' : (watching ? 'bg-muted text-muted-foreground' : 'text-white')"
                        :style="(going || watching) ? '' : 'background: {{ $e['color'] }}'">
                    <i class="bi" :class="going ? 'bi-check2-circle' : (watching ? 'bi-ticket-perforated' : 'bi-plus-circle')"></i>
                    <span x-text="going ? 'You\'re going · spot confirmed' : (watching ? 'You\'re watching' : '{{ $pPaid ? 'Register · '.$e['participant_fee'] : 'Join event' }}')"></span>
                </button>
            </div>
        @endif
    </div>

    {{-- In-place division confirmation (taekwondo) --}}
    <div x-show="joinedDivision" x-cloak class="px-4 mt-2">
        <div class="m-card rounded-2xl p-3 flex items-center gap-2 text-[12px]">
            <i class="bi bi-diagram-3 text-primary"></i>
            <span class="text-muted-foreground">You're placed in <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
        </div>
    </div>

    {{-- Share handler (dummy) --}}
    <div x-init="$el.addEventListener('share-event-fired', () => {})"
         @share-event.window="
            if (navigator.share) { navigator.share({ title: '{{ addslashes($e['title']) }}', text: 'Join me at {{ addslashes($e['title']) }}!' }).catch(()=>{}); }
            else { window.showToast('success', 'Event link copied'); }
         "></div>

    {{-- ===== Set-winners modal (managers) — teleported to body so the fixed
             overlay anchors to the viewport, not the transformed shell content. --}}
    @if($canManage ?? false)
        <template x-teleport="body">
        <div x-show="resultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="resultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[85vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> Winners &amp; results</h3>
                    <button type="button" @click="resultsOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                {{-- list of participant names for autocomplete --}}
                <datalist id="event-participants">
                    @foreach($e['participants'] as $pp)
                        <option value="{{ $pp['name'] }}"></option>
                    @endforeach
                </datalist>

                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <p class="text-xs text-muted-foreground">Add the podium finishers. Pick from registered participants or type a name. Leave the prize blank if there isn't one.</p>
                    <template x-for="(w, i) in winners" :key="i">
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full text-white" :style="`background:${medal(w.place)}`"
                                      x-text="w.place===1 ? '🥇 1st' : (w.place===2 ? '🥈 2nd' : (w.place===3 ? '🥉 3rd' : '#' + w.place))"></span>
                                <button type="button" @click="removeWinner(i)" class="m-press text-[11px] text-red-500 font-semibold"><i class="bi bi-trash"></i> Remove</button>
                            </div>
                            <input type="text" list="event-participants" x-model="w.name" placeholder="Winner name"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm mb-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" x-model="w.place" placeholder="Place"
                                       class="w-20 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <input type="text" x-model="w.prize" placeholder="Prize (optional) — e.g. BHD 200"
                                       class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="addWinner()" class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                        <i class="bi bi-plus-lg"></i> Add place
                    </button>
                </div>

                <div class="p-4 border-t border-gray-100">
                    <button type="button" @click="saveResults()" :disabled="busy"
                            class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $e['color'] }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="busy ? 'Saving…' : 'Save winners'"></span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif

    {{-- ===== Results sheet (combat — computed from brackets) ===== --}}
    @if(!empty($e['bracket_results']))
        <template x-teleport="body">
        <div x-show="showResultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="showResultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[88vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> Results &amp; medals</h3>
                    <button type="button" @click="showResultsOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    @foreach($e['bracket_results'] as $r)
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <p class="text-sm font-bold text-foreground mb-2 flex items-center gap-2"><i class="bi bi-diagram-3 text-primary"></i> {{ $r['division'] }}</p>
                            <div class="space-y-1.5">
                                @foreach($r['medals'] as $m)
                                    @php $medal = [1 => ['🥇', '#f59e0b', 'Champion'], 2 => ['🥈', '#9ca3af', 'Runner-up'], 3 => ['🥉', '#b45309', '3rd place']][$m['place']]; @endphp
                                    <div class="flex items-center gap-3 rounded-xl p-2" style="background: {{ $medal[1] }}12;">
                                        <span class="text-2xl flex-shrink-0 leading-none">{{ $medal[0] }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-foreground truncate">{{ $m['name'] }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ $medal[2] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        </template>
    @endif

    {{-- ===== Finance sheet (owner only) ===== --}}
    @if($finance ?? false)
        <template x-teleport="body">
        <div x-show="financeOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="financeOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-cash-stack text-green-600"></i> Event finance</h3>
                    <button type="button" @click="financeOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- Revenue --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">Money collected</p>
                        <div class="flex items-center justify-between text-sm py-1">
                            <span class="text-muted-foreground"><span x-text="fin.paid_participants"></span> paid entries × <span x-text="money(fin.participant_fee)"></span></span>
                            <span class="font-bold text-foreground" x-text="money(fin.participant_revenue)"></span>
                        </div>
                        <template x-if="fin.spectator_enabled">
                            <div class="flex items-center justify-between text-sm py-1">
                                <span class="text-muted-foreground"><span x-text="fin.paid_spectators"></span> tickets × <span x-text="money(fin.spectator_fee)"></span></span>
                                <span class="font-bold text-foreground" x-text="money(fin.spectator_revenue)"></span>
                            </div>
                        </template>
                        <div class="flex items-center justify-between text-sm pt-2 mt-1 border-t border-gray-100">
                            <span class="font-bold text-foreground">Total revenue</span>
                            <span class="font-black text-green-600" x-text="money(fin.revenue)"></span>
                        </div>
                    </div>

                    {{-- Expenses --}}
                    <div class="rounded-2xl border border-gray-100 p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">Expenses</p>
                        <div class="space-y-1.5">
                            <template x-for="x in fin.expenses" :key="x.id">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="flex-1 min-w-0 truncate text-foreground" x-text="x.label"></span>
                                    <span class="font-bold text-red-600" x-text="'− ' + money(x.amount)"></span>
                                    <button type="button" @click="removeExpense(x.id)" class="m-press w-7 h-7 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-[10px]"></i></button>
                                </div>
                            </template>
                            <p x-show="!fin.expenses.length" class="text-[11px] text-muted-foreground text-center py-1">No expenses yet.</p>
                        </div>
                        {{-- Add expense --}}
                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                            <input x-model="newExpLabel" type="text" placeholder="Expense — e.g. Mats rental"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            <div class="relative w-28 flex-shrink-0">
                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="fin.currency"></span>
                                <input x-model="newExpAmount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0"
                                       class="w-full pl-12 pr-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                            </div>
                            <button type="button" @click="addExpense()" :disabled="busy" class="m-press w-9 h-9 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0 disabled:opacity-50"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-gray-100">
                            <span class="font-bold text-foreground">Total expenses</span>
                            <span class="font-black text-red-600" x-text="'− ' + money(expensesTotal)"></span>
                        </div>
                    </div>

                    {{-- Profit --}}
                    <div class="rounded-2xl p-4 flex items-center justify-between" :class="profit >= 0 ? 'bg-green-50' : 'bg-red-50'">
                        <span class="text-sm font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'">Profit</span>
                        <span class="text-lg font-black" :class="profit >= 0 ? 'text-green-700' : 'text-red-700'" x-text="money(profit)"></span>
                    </div>
                </div>
            </div>
        </div>
        </template>
    @endif

</div>
@endsection
