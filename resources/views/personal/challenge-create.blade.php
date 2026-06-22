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
        metric: 'Best score',
        stake: '150',
        deadline: '',
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
                    metric: this.metric,
                    stake: parseInt(this.stake, 10),
                    deadline: this.deadline || null,
                    message: this.message || null,
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
                    <div class="w-11 h-11 rounded-full grid place-items-center text-white font-bold" style="background: hsl(8 60% 58%);" x-text="opponent.initials"></div>
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
                            <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl(8 60% 58%);">{{ $o['initials'] }}</div>
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
                            <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl({{ (crc32($a['name']) % 360) }} 55% 58%);">{{ $a['initials'] }}</div>
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
                <label class="block text-xs font-medium text-gray-600 mb-1">Discipline</label>
                <input x-model="discipline" type="text" :placeholder="type==='fight' ? 'e.g. Boxing spar — 3 rounds' : 'e.g. 100m sprint'"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Win condition</label>
                    <select x-model="metric" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <option>Best score</option>
                        <option>Best of 3</option>
                        <option>Fastest time</option>
                        <option>Most reps</option>
                        <option>First to finish</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Stake (points)</label>
                    <select x-model="stake" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <option value="100">100 pts</option>
                        <option value="150">150 pts</option>
                        <option value="200">200 pts</option>
                        <option value="300">300 pts</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Deadline</label>
                <input x-model="deadline" type="date"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
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
                        <div class="w-12 h-12 rounded-full grid place-items-center text-white font-bold" style="background: hsl(250 55% 60%);">YO</div>
                        <p class="text-[11px] font-bold text-foreground mt-1">You</p>
                    </div>
                    <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-black" :style="`background:${color}`">VS</div>
                    <div class="flex flex-col items-center">
                        <div class="w-12 h-12 rounded-full grid place-items-center font-bold"
                             :class="hasRival ? 'text-white' : 'bg-muted text-muted-foreground'"
                             :style="hasRival ? 'background: hsl(8 60% 58%)' : ''"
                             x-text="rivalInitials"></div>
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
