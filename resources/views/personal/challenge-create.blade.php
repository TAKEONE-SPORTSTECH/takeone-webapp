@extends('layouts.personal-mobile')

@section('title', 'New Challenge')

{{--
    Create challenge & invite a challenger — DUMMY form. Pick type (athletic/fight),
    choose an opponent from club members, set discipline + terms (metric, stake,
    deadline) and an optional trash-talk message, then "send invite" (dummy → toast
    + back to hub). Reuses the shared mobile motion vocabulary and design tokens.
--}}
@section('personal-content')
<div x-data="{
        step: 1,
        sending: false,
        type: 'athletic',
        source: 'club',          // club | discover | invite
        opponent: null,
        invite: '',              // @handle / email / phone for external people
        discipline: '',
        format: 'single',
        stake: '150',
        deadline: '',
        timeVal: '18:00',
        get timeOpts() {
            const out = [];
            for (let h = 0; h < 24; h++) for (const m of [0, 30]) {
                const v = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
                const ap = h < 12 ? 'AM' : 'PM'; const h12 = (h % 12) || 12;
                out.push({ v, l: h12 + ':' + String(m).padStart(2,'0') + ' ' + ap });
            }
            return out;
        },
        get timeLabel() { const o = this.timeOpts.find(o => o.v === this.timeVal); return o ? o.l : this.timeVal; },
        facilities: @js($facilities ?? []),
        locMode: @js(($facilities ?? []) ? 'facility' : 'text'),
        location: '',
        location_url: '',
        gps_lat: '',
        gps_long: '',
        facilityId: '',
        pickFacility(f) { this.facilityId = f.id; this.location = f.name + (f.club ? ' · ' + f.club : ''); this.location_url = f.url || ''; this.gps_lat = f.lat || ''; this.gps_long = f.lng || ''; },
        events: @js($events ?? []),
        eventId: '',
        eventLabel: '',
        pickEvent(e) { this.eventId = e.id; this.eventLabel = e.title + (e.club ? ' · ' + e.club : ''); this.location = e.location || e.title; this.location_url = e.url || ''; this.gps_lat = e.lat || ''; this.gps_long = e.lng || ''; },
        clearEvent() { this.eventId = ''; this.eventLabel = ''; this.location = ''; this.location_url = ''; this.gps_lat = ''; this.gps_long = ''; },
        message: '',
        get color() { return this.type === 'fight' ? '#ef4444' : '#7c3aed'; },
        get link() { return 'https://takeone.bh/c/' + (this.type==='fight'?'f':'a') + '-' + Math.random().toString(36).slice(2,8).toUpperCase(); },
        validInvite() { const v=this.invite.trim(); return v.length>2 && (v.includes('@')||/^[+0-9 ]{6,}$/.test(v)); },
        targetReady() {
            if (this.source==='invite') return this.validInvite();
            return !!this.opponent;
        },
        canSend() { return this.targetReady() && this.discipline.trim().length > 1; },
        pick(o) { this.opponent = o; },
        get hasRival() { return this.source==='invite' ? this.validInvite() : !!this.opponent; },
        get rivalName() {
            if (this.source==='invite') { const v=this.invite.trim().replace(/^@/,''); return v ? (v.split('@')[0] || 'Invitee') : 'Rival'; }
            return this.opponent ? this.opponent.name.split(' ')[0] : 'Rival';
        },
        get rivalInitials() {
            if (this.source==='invite') { const v=this.invite.trim().replace(/^@/,''); return v ? v.slice(0,2).toUpperCase() : '?'; }
            return this.opponent ? this.opponent.initials : '?';
        },
        get rivalAvatar() {
            return (this.source!=='invite' && this.opponent) ? (this.opponent.avatar || null) : null;
        },
        copyLink() {
            const l = this.link;
            (navigator.clipboard?.writeText(l) || Promise.reject())
                .then(() => window.showToast('success','Challenge link copied — share it anywhere'))
                .catch(() => window.showToast('info', l));
        },
        async send() {
            if (!this.canSend()) { window.showToast('warning','Pick someone to challenge and a discipline first'); return; }
            if (this.sending) return;
            this.sending = true;
            try {
                const payload = {
                    type: this.type,
                    discipline: this.discipline.trim(),
                    format: this.format,
                    event_id: this.eventId || null,
                    stake: parseInt(this.stake, 10),
                    deadline: this.deadline ? (this.deadline + ' ' + (this.timeVal || '18:00') + ':00') : null,
                    message: this.message || null,
                    location: (this.location || '').trim() || (this.locMode === 'map' ? (document.getElementById('duelLocMapAddress')?.value || '').trim() : '') || null,
                    location_url: this.locMode === 'url' ? (this.location_url || null) : (this.location_url || null),
                    gps_lat: (this.locMode === 'map' || this.locMode === 'facility') && this.gps_lat ? parseFloat(this.gps_lat) : null,
                    gps_long: (this.locMode === 'map' || this.locMode === 'facility') && this.gps_long ? parseFloat(this.gps_long) : null,
                };
                if (this.source === 'invite') payload.invite = this.invite.trim();
                else payload.opponent_id = this.opponent.id;

                const res = await fetch('{{ route('me.challenge.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not send challenge');

                window.showToast('success', data.message || 'Challenge sent 🔥');
                setTimeout(() => {
                    const a = document.querySelector('a[data-route=\'me.challenge\'][href$=\'/me/challenge\']');
                    if (a) { a.click(); } else { window.location.href = data.redirect || '{{ route('me.challenge') }}'; }
                }, 600);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.sending = false;
            }
        }
     }"
     class="-mx-4 -mt-4 pb-6">

    {{-- ===== Header ===== --}}
    <header class="m-hero px-5 pt-5 pb-10 text-white relative overflow-hidden"
            :style="`background: linear-gradient(150deg, ${color}, #1f2937)`">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="flex items-center gap-3 relative z-10">
            <a href="{{ route('me.challenge') }}" data-shell-link data-route="me.challenge"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Versus 1v1</p>
                <h1 class="text-xl font-black">Challenge a rival</h1>
            </div>
        </div>
    </header>

    <div class="px-4 -mt-5 relative z-10 space-y-4">

        {{-- ===== 1 · Type ===== --}}
        <div class="m-card rounded-2xl p-4">
            <p class="text-sm font-bold text-foreground mb-3"><span class="text-primary">1.</span> Challenge type</p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" @click="type='athletic'"
                        class="m-press rounded-2xl p-4 border-2 text-center transition-colors"
                        :class="type==='athletic' ? 'border-primary bg-accent' : 'border-gray-100 bg-white'">
                    <div class="w-12 h-12 mx-auto rounded-2xl grid place-items-center text-white" style="background: #7c3aed;"><i class="bi bi-lightning-charge-fill text-xl"></i></div>
                    <p class="text-sm font-bold text-foreground mt-2">Athletic</p>
                    <p class="text-[11px] text-muted-foreground">Sprint, row, swim…</p>
                </button>
                <button type="button" @click="type='fight'"
                        class="m-press rounded-2xl p-4 border-2 text-center transition-colors"
                        :class="type==='fight' ? 'border-red-400 bg-red-50' : 'border-gray-100 bg-white'">
                    <div class="w-12 h-12 mx-auto rounded-2xl grid place-items-center text-white" style="background: #ef4444;"><i class="bi bi-trophy text-xl"></i></div>
                    <p class="text-sm font-bold text-foreground mt-2">Fight</p>
                    <p class="text-[11px] text-muted-foreground">Spar, grapple, bout…</p>
                </button>
            </div>
        </div>

        {{-- ===== 2 · Opponent ===== --}}
        <div class="m-card rounded-2xl p-4" x-data="{ q: '', qd: '' }">
            <p class="text-sm font-bold text-foreground mb-3"><span class="text-primary">2.</span> Who are you challenging?</p>

            {{-- source toggle: My Club · Discover (platform-wide) · Invite link --}}
            <div class="bg-muted/60 rounded-xl p-1 flex mb-3">
                <button type="button" @click="source='club'; opponent=null"
                        class="m-press flex-1 py-1.5 rounded-lg text-[11px] font-bold transition-colors"
                        :class="source==='club' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">
                    <i class="bi bi-buildings"></i> My Club
                </button>
                <button type="button" @click="source='discover'; opponent=null"
                        class="m-press flex-1 py-1.5 rounded-lg text-[11px] font-bold transition-colors"
                        :class="source==='discover' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">
                    <i class="bi bi-globe2"></i> Discover
                </button>
                <button type="button" @click="source='invite'; opponent=null"
                        class="m-press flex-1 py-1.5 rounded-lg text-[11px] font-bold transition-colors"
                        :class="source==='invite' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">
                    <i class="bi bi-link-45deg"></i> Invite
                </button>
            </div>

            {{-- selected opponent (club/discover) --}}
            <template x-if="opponent && source!=='invite'">
                <div class="flex items-center gap-3 rounded-2xl p-3" :style="`background:${color}0d; border:1px solid ${color}33`">
                    <template x-if="opponent.avatar">
                        <img :src="opponent.avatar" :alt="opponent.name" class="w-11 h-11 rounded-full object-cover">
                    </template>
                    <template x-if="!opponent.avatar">
                        <div class="w-11 h-11 rounded-full grid place-items-center text-white font-bold" style="background: hsl(8 60% 58%);" x-text="opponent.initials"></div>
                    </template>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground truncate flex items-center gap-1">
                            <span x-text="opponent.name"></span>
                            <i class="bi bi-patch-check-fill text-[11px] text-sky-500" x-show="opponent.verified"></i>
                        </p>
                        <p class="text-[11px] text-muted-foreground" x-text="opponent.record + (opponent.club ? ' · ' + opponent.club : '')"></p>
                    </div>
                    <button type="button" @click="opponent=null" class="m-press w-8 h-8 rounded-full bg-white grid place-items-center text-muted-foreground"><i class="bi bi-x-lg text-xs"></i></button>
                </div>
            </template>

            {{-- MY CLUB list --}}
            <div x-show="source==='club' && !opponent">
                <div class="relative mb-3">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input x-model="q" type="text" placeholder="Search club members…"
                           class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($opponents as $o)
                        <button type="button"
                                @click="pick({{ Illuminate\Support\Js::from($o) }})"
                                x-show="'{{ strtolower($o['name']) }}'.includes(q.toLowerCase())"
                                class="m-press w-full flex items-center gap-3 rounded-xl p-2.5 hover:bg-muted/50 transition-colors text-start">
                            @if(!empty($o['avatar']))
                                <img src="{{ $o['avatar'] }}" alt="{{ $o['name'] }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl(8 60% 58%);">{{ $o['initials'] }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate">{{ $o['name'] }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ $o['record'] }} · {{ $o['tag'] }}</p>
                            </div>
                            <i class="bi bi-plus-circle text-primary"></i>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- DISCOVER (platform-wide) list --}}
            <div x-show="source==='discover' && !opponent">
                <div class="relative mb-2">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input x-model="qd" type="text" placeholder="Search athletes everywhere…"
                           class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <p class="text-[11px] text-muted-foreground mb-2 flex items-center gap-1"><i class="bi bi-globe2"></i> Anyone on TAKEONE — other clubs &amp; cities</p>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($athletes as $a)
                        <button type="button"
                                @click="pick({{ Illuminate\Support\Js::from($a) }})"
                                x-show="'{{ strtolower($a['name'].' '.$a['club'].' '.$a['city']) }}'.includes(qd.toLowerCase())"
                                class="m-press w-full flex items-center gap-3 rounded-xl p-2.5 hover:bg-muted/50 transition-colors text-start">
                            @if(!empty($a['avatar']))
                                <img src="{{ $a['avatar'] }}" alt="{{ $a['name'] }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl({{ (crc32($a['name']) % 360) }} 55% 58%);">{{ $a['initials'] }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate flex items-center gap-1">
                                    {{ $a['name'] }}
                                    @if($a['verified'])<i class="bi bi-patch-check-fill text-[11px] text-sky-500"></i>@endif
                                </p>
                                <p class="text-[11px] text-muted-foreground truncate"><i class="bi bi-geo-alt"></i> {{ $a['city'] }} · {{ $a['club'] }}</p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-accent text-primary flex-shrink-0">{{ $a['tag'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- INVITE external person (handle / email / phone + shareable link) --}}
            <div x-show="source==='invite'">
                <p class="text-[11px] text-muted-foreground mb-2">Challenge anyone — even if they're not in your club yet. Invite them by handle, email or phone.</p>
                <div class="relative mb-3">
                    <i class="bi bi-at absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input x-model="invite" type="text" placeholder="@username, email or phone"
                           class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>

                <div class="rounded-xl border border-dashed border-gray-200 p-3">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-foreground">Or share a challenge link</p>
                            <p class="text-[11px] text-muted-foreground">Anyone with the link can accept</p>
                        </div>
                        <button type="button" @click="copyLink()" class="m-press flex-shrink-0 px-3 py-1.5 rounded-lg text-white text-xs font-bold flex items-center gap-1.5" :style="`background:${color}`">
                            <i class="bi bi-clipboard"></i> Copy link
                        </button>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <button type="button" @click="window.showToast('info','Opening WhatsApp share…')" class="m-press flex-1 py-2 rounded-lg bg-muted text-xs font-semibold text-foreground flex items-center justify-center gap-1.5"><i class="bi bi-whatsapp text-green-500"></i> WhatsApp</button>
                        <button type="button" @click="window.showToast('info','Opening share sheet…')" class="m-press flex-1 py-2 rounded-lg bg-muted text-xs font-semibold text-foreground flex items-center justify-center gap-1.5"><i class="bi bi-share"></i> More</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== 3 · Terms ===== --}}
        <div class="m-card rounded-2xl p-4 space-y-4">
            <p class="text-sm font-bold text-foreground"><span class="text-primary">3.</span> Set the terms</p>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Discipline <span class="text-red-500">*</span></label>
                <input x-model="discipline" type="text" :placeholder="type==='fight' ? 'e.g. Boxing spar — 3 rounds' : 'e.g. 100m sprint'"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Stake (points) <span class="text-red-500">*</span></label>
                <div class="relative" :style="open ? 'z-index:1100' : ''" x-data="{ open: false, opts: ['100','150','200','300'] }" @click.outside="open=false" @keydown.escape="open=false">
                        <button type="button" @click="open=!open"
                                class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center justify-between gap-2 outline-none transition-colors"
                                :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                            <span class="truncate" x-text="stake + ' pts'"></span>
                            <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1">
                            <template x-for="opt in opts" :key="opt">
                                <button type="button" @click="stake=opt; open=false"
                                        class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                        :class="stake===opt ? 'text-primary font-semibold bg-muted/40' : 'text-foreground'">
                                    <span x-text="opt + ' pts'"></span>
                                    <i class="bi bi-check-lg text-primary text-xs" x-show="stake===opt"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

            {{-- Scoring format --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Scoring format <span class="text-red-500">*</span></label>
                <div class="relative" :style="open ? 'z-index:1100' : ''" x-data="{ open:false, opts:[
                        {v:'single',l:'Single match'},{v:'bo3',l:'Best of 3'},{v:'bo5',l:'Best of 5'},
                        {v:'points',l:'Points — highest wins'},{v:'time',l:'Fastest time wins'} ] }"
                     @click.outside="open=false" @keydown.escape="open=false">
                    <button type="button" @click="open=!open"
                            class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center justify-between gap-2 outline-none transition-colors"
                            :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                        <span class="truncate" x-text="(opts.find(o=>o.v===format)||{}).l || 'Single match'"></span>
                        <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1">
                        <template x-for="o in opts" :key="o.v">
                            <button type="button" @click="format=o.v; open=false"
                                    class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                    :class="format===o.v ? 'text-primary font-semibold bg-muted/40' : 'text-foreground'">
                                <span x-text="o.l"></span>
                                <i class="bi bi-check-lg text-primary text-xs" x-show="format===o.v"></i>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-[11px] text-muted-foreground mt-1" x-show="format==='bo3'||format==='bo5'" x-cloak>You'll log each round's winner — majority takes the duel.</p>
                <p class="text-[11px] text-muted-foreground mt-1" x-show="format==='points'||format==='time'" x-cloak>Each player enters a number; the winner is computed automatically.</p>
            </div>

            {{-- Part of an event? — when chosen, the duel inherits the event's location --}}
            <div x-show="events.length" x-cloak>
                <label class="block text-xs font-medium text-gray-600 mb-1">Part of an event? <span class="text-muted-foreground font-normal">(optional)</span></label>
                <div class="relative" :style="open ? 'z-index:1100' : ''" x-data="{ open:false }" @click.outside="open=false" @keydown.escape="open=false">
                    <button type="button" @click="open=!open"
                            class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center justify-between gap-2 outline-none transition-colors"
                            :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                        <span class="truncate" :class="eventId ? 'text-foreground' : 'text-gray-400'" x-text="eventId ? eventLabel : 'Not part of an event'"></span>
                        <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1 max-h-60 overflow-y-auto">
                        <button type="button" @click="clearEvent(); open=false"
                                class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                :class="!eventId ? 'text-primary font-semibold bg-muted/40' : 'text-foreground'">
                            <span>Not part of an event</span>
                            <i class="bi bi-check-lg text-primary text-xs" x-show="!eventId"></i>
                        </button>
                        <template x-for="e in events" :key="e.id">
                            <button type="button" @click="pickEvent(e); open=false"
                                    class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                    :class="eventId===e.id ? 'bg-muted/40' : ''">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-foreground" x-text="e.title"></span>
                                    <span class="block text-[10px] text-muted-foreground truncate"><span x-text="e.date"></span><span x-show="e.location"> · </span><span x-text="e.location || e.club"></span></span>
                                </span>
                                <i class="bi bi-check-lg text-primary text-xs flex-shrink-0" x-show="eventId===e.id"></i>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-[11px] text-muted-foreground mt-1" x-show="eventId" x-cloak><i class="bi bi-geo-alt-fill text-primary"></i> Takes place at the event location: <span class="font-semibold text-foreground" x-text="location || '—'"></span></p>
            </div>

            {{-- Location (hidden when the duel is attached to an event — it uses the event's location) --}}
            <div x-show="!eventId">
                <label class="block text-xs font-medium text-gray-600 mb-1">Location <span class="text-muted-foreground font-normal">(optional)</span></label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-if="facilities.length">
                        <button type="button" @click="locMode='facility'" class="m-press flex-1 min-w-[70px] py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='facility' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-building"></i> Facility</button>
                    </template>
                    <button type="button" @click="locMode='map'" class="m-press flex-1 min-w-[70px] py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='map' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-geo-alt"></i> Map</button>
                    <button type="button" @click="locMode='url'" class="m-press flex-1 min-w-[70px] py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='url' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-link-45deg"></i> Link</button>
                    <button type="button" @click="locMode='text'" class="m-press flex-1 min-w-[70px] py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='text' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-pencil"></i> Type</button>
                </div>

                {{-- Facility dropdown --}}
                <div x-show="locMode==='facility'" x-cloak>
                    <div class="relative" :style="open ? 'z-index:1100' : ''" x-data="{ open:false }" @click.outside="open=false" @keydown.escape="open=false">
                        <button type="button" @click="open=!open"
                                class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center justify-between gap-2 outline-none transition-colors"
                                :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                            <span class="truncate" :class="facilityId ? 'text-foreground' : 'text-gray-400'" x-text="facilityId ? location : 'Choose a facility…'"></span>
                            <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1 max-h-56 overflow-y-auto">
                            <template x-for="f in facilities" :key="f.id">
                                <button type="button" @click="pickFacility(f); open=false"
                                        class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                        :class="facilityId===f.id ? 'bg-muted/40' : ''">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-foreground" x-text="f.name"></span>
                                        <span class="block text-[10px] text-muted-foreground truncate" x-text="f.club"></span>
                                    </span>
                                    <i class="bi bi-check-lg text-primary text-xs flex-shrink-0" x-show="facilityId===f.id"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Pin on map --}}
                <div x-show="locMode==='map'" x-cloak class="space-y-2" @location-changed="gps_lat = $event.detail.lat; gps_long = $event.detail.lng">
                    <input x-model="location" type="text" placeholder="Place title (optional) — e.g. City Sports Hall"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    <x-location-map id="duelLocMap" height="10rem" :zoom="13" :show-labels="false" />
                </div>

                {{-- Maps link --}}
                <div x-show="locMode==='url'" x-cloak class="space-y-2">
                    <input x-model="location_url" type="url" placeholder="https://maps.google.com/…"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    <input x-model="location" type="text" placeholder="Place name (optional)"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>

                {{-- Type a place --}}
                <div x-show="locMode==='text'" x-cloak>
                    <input x-model="location" type="text" placeholder="e.g. Juffair Park, near the corniche"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>
            <script>
                (function () {
                    var id = 'duelLocMap', tries = 0;
                    (function go() {
                        if (window.LocationMap) { window.LocationMap.create({ id: id, draggable: true, zoom: 13 }); }
                        else if (tries++ < 60) { setTimeout(go, 100); }
                    })();
                })();
            </script>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Challenge time <span class="text-muted-foreground font-normal">(optional)</span></label>
                <div class="relative" :style="open ? 'z-index:1100' : ''" x-data="{
                        open: false,
                        view: { y: (new Date()).getFullYear(), m: (new Date()).getMonth() },
                        months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
                        dows: ['Su','Mo','Tu','We','Th','Fr','Sa'],
                        get grid() {
                            const start = new Date(this.view.y, this.view.m, 1).getDay();
                            const days  = new Date(this.view.y, this.view.m + 1, 0).getDate();
                            const cells = [];
                            for (let i = 0; i < start; i++) cells.push(null);
                            for (let d = 1; d <= days; d++) cells.push(d);
                            return cells;
                        },
                        iso(d) { return this.view.y + '-' + String(this.view.m + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0'); },
                        todayIso() { const t = new Date(); return t.getFullYear() + '-' + String(t.getMonth()+1).padStart(2,'0') + '-' + String(t.getDate()).padStart(2,'0'); },
                        isPast(d) { if (!d) return false; const t = new Date(); t.setHours(0,0,0,0); return new Date(this.view.y, this.view.m, d) < t; },
                        isToday(d) { const t = new Date(); return d && this.view.y===t.getFullYear() && this.view.m===t.getMonth() && d===t.getDate(); },
                        prev() { this.view = this.view.m === 0 ? { y: this.view.y - 1, m: 11 } : { y: this.view.y, m: this.view.m - 1 }; },
                        next() { this.view = this.view.m === 11 ? { y: this.view.y + 1, m: 0 } : { y: this.view.y, m: this.view.m + 1 }; },
                        fmt(val) { if (!val) return ''; const d = new Date(val + 'T00:00:00'); return d.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', year:'numeric' }); }
                     }"
                     x-init="if (deadline) { const d = new Date(deadline + 'T00:00:00'); view = { y: d.getFullYear(), m: d.getMonth() }; }"
                     @click.outside="open=false" @keydown.escape="open=false">
                    <button type="button" @click="open=!open"
                            class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center gap-2 outline-none transition-colors"
                            :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                        <i class="bi bi-calendar-event text-gray-400 flex-shrink-0"></i>
                        <span class="flex-1 truncate" :class="deadline ? 'text-foreground' : 'text-gray-400'" x-text="deadline ? fmt(deadline) : 'Pick a date'"></span>
                        <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden p-3">
                        <div class="flex items-center justify-between mb-2">
                            <button type="button" @click="prev()" class="m-press w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-left text-sm"></i></button>
                            <p class="text-sm font-bold text-foreground" x-text="months[view.m] + ' ' + view.y"></p>
                            <button type="button" @click="next()" class="m-press w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-right text-sm"></i></button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 mb-1">
                            <template x-for="dw in dows" :key="dw"><span class="text-[10px] font-bold text-muted-foreground text-center py-1" x-text="dw"></span></template>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(d, i) in grid" :key="i">
                                <button type="button" :disabled="!d || isPast(d)"
                                        @click="if (d && !isPast(d)) { deadline = iso(d); open=false }"
                                        class="h-9 rounded-lg text-sm grid place-items-center transition-colors"
                                        :class="!d ? 'invisible' : (iso(d)===deadline ? 'bg-primary text-white font-bold' : (isPast(d) ? 'text-gray-300 cursor-not-allowed' : (isToday(d) ? 'text-primary font-bold ring-1 ring-primary/40 hover:bg-muted/60' : 'text-foreground hover:bg-muted/60')))"
                                        x-text="d"></button>
                            </template>
                        </div>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                            <button type="button" @click="deadline=''; open=false" class="text-[11px] font-semibold text-muted-foreground hover:text-foreground transition-colors">Clear</button>
                            <button type="button" @click="const t=new Date(); view={y:t.getFullYear(),m:t.getMonth()}; deadline=todayIso(); open=false" class="text-[11px] font-semibold text-primary">Today</button>
                        </div>
                    </div>
                </div>

                {{-- time of day --}}
                <div class="relative mt-2" :style="open ? 'z-index:1100' : ''" x-data="{ open: false }" @click.outside="open=false" @keydown.escape="open=false" x-show="deadline" x-cloak>
                    <button type="button" @click="open=!open"
                            class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center gap-2 outline-none transition-colors"
                            :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                        <i class="bi bi-clock text-gray-400 flex-shrink-0"></i>
                        <span class="flex-1 truncate text-foreground" x-text="timeLabel"></span>
                        <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1 max-h-56 overflow-y-auto">
                        <template x-for="o in timeOpts" :key="o.v">
                            <button type="button" @click="timeVal=o.v; open=false"
                                    class="w-full text-left px-3 py-2 text-sm transition-colors flex items-center justify-between gap-2 hover:bg-muted/60"
                                    :class="timeVal===o.v ? 'text-primary font-semibold bg-muted/40' : 'text-foreground'">
                                <span x-text="o.l"></span>
                                <i class="bi bi-check-lg text-primary text-xs" x-show="timeVal===o.v"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Trash talk <span class="text-muted-foreground font-normal">(optional)</span></label>
                <textarea x-model="message" rows="2" placeholder="Say something to fire them up… 🔥"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
            </div>
        </div>

        {{-- ===== Live preview ===== --}}
        <div class="rounded-2xl overflow-hidden shadow-lg border border-gray-100" :style="`background: linear-gradient(135deg, ${color}, ${color}cc)`">
            <div class="px-4 py-2.5 text-white text-[11px] font-bold flex items-center gap-1.5">
                <i class="bi" :class="type==='fight' ? 'bi-trophy' : 'bi-lightning-charge-fill'"></i>
                <span x-text="(type==='fight' ? 'Fight' : 'Athletic') + ' · ' + (discipline || 'Your challenge')"></span>
            </div>
            <div class="bg-white px-4 py-4">
                <div class="flex items-center justify-around">
                    <div class="flex flex-col items-center">
                        @if(!empty($myAvatar))
                            <img src="{{ $myAvatar }}" alt="You" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full grid place-items-center text-white font-bold" style="background: hsl(250 55% 60%);">YO</div>
                        @endif
                        <p class="text-[11px] font-bold text-foreground mt-1">You</p>
                    </div>
                    <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-black" :style="`background:${color}`">VS</div>
                    <div class="flex flex-col items-center">
                        <template x-if="rivalAvatar">
                            <img :src="rivalAvatar" :alt="rivalName" class="w-12 h-12 rounded-full object-cover">
                        </template>
                        <template x-if="!rivalAvatar">
                            <div class="w-12 h-12 rounded-full grid place-items-center font-bold"
                                 :class="hasRival ? 'text-white' : 'bg-muted text-muted-foreground'"
                                 :style="hasRival ? 'background: hsl(8 60% 58%)' : ''"
                                 x-text="rivalInitials"></div>
                        </template>
                        <p class="text-[11px] font-bold text-foreground mt-1" x-text="rivalName"></p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-3 mt-3 text-[11px] text-muted-foreground">
                    <span class="inline-flex items-center gap-1"><i class="bi bi-flag"></i><span x-text="metric"></span></span>
                    <span class="inline-flex items-center gap-1"><i class="bi bi-star-fill text-amber-400"></i><span x-text="stake + ' pts'"></span></span>
                </div>
            </div>
        </div>

        {{-- ===== Send ===== --}}
        <button type="button" @click="send()" :disabled="!canSend() || sending"
                class="m-press w-full py-3.5 rounded-2xl text-white font-black text-sm flex items-center justify-center gap-2 transition-opacity"
                :class="(canSend() && !sending) ? '' : 'opacity-50'" :style="`background:${color}`">
            <i class="bi" :class="sending ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i>
            <span x-text="sending ? 'Sending…' : 'Send challenge invite'"></span>
        </button>
    </div>

</div>
@endsection
