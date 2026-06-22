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
        async cancel() { const d = await this.post('{{ route('me.challenge.duel.cancel', $d['id']) }}'); if (d) { this.status='cancelled'; window.showToast('info', d.message); } },
        async submitResult(winner) {
            this._body = { winner };
            const d = await this.post('{{ route('me.challenge.duel.report', $d['id']) }}');
            if (d) { this.status = d.status || 'reported'; this.reportedByMe = true; this.reportOpen = false; window.showToast('success', d.message); }
        },
        async confirmResult() {
            const d = await this.post('{{ route('me.challenge.duel.confirm', $d['id']) }}');
            if (d) { this.status = 'completed'; this.won = !!d.won; window.showToast('success', d.message); }
        },
        async disputeResult() {
            const d = await this.post('{{ route('me.challenge.duel.dispute', $d['id']) }}');
            if (d) { this.status = 'active'; window.showToast('info', d.message); }
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

        <h1 class="text-xl font-black mt-4 text-center relative z-10">{{ $d['discipline'] }}</h1>

        {{-- VS row --}}
        <div class="flex items-center justify-center gap-4 mt-5 relative z-10">
            <div class="flex flex-col items-center w-28">
                <div class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black border-2 border-white/60 shadow-lg" style="background: hsl(250 55% 60%);">{{ $d['me']['initials'] }}</div>
                <p class="text-sm font-bold mt-2">You</p>
                <p class="text-[11px] text-white/70">{{ $d['me']['record'] }}</p>
                @if(isset($d['me']['score']))<p class="text-lg font-black mt-1">{{ $d['me']['score'] }}</p>@endif
            </div>

            <div class="flex flex-col items-center">
                <div class="w-12 h-12 rounded-full grid place-items-center text-white font-black shadow-lg m-float bg-white/15 border border-white/30 backdrop-blur">VS</div>
            </div>

            <div class="flex flex-col items-center w-28">
                <div class="w-20 h-20 rounded-full grid place-items-center text-white text-2xl font-black border-2 border-white/60 shadow-lg" style="background: hsl(8 60% 58%);">{{ $d['opponent']['initials'] }}</div>
                <p class="text-sm font-bold mt-2 truncate max-w-full">{{ $d['opponent']['name'] }}</p>
                <p class="text-[11px] text-white/70">{{ $d['opponent']['record'] }}</p>
                @if(isset($d['opponent']['score']))<p class="text-lg font-black mt-1">{{ $d['opponent']['score'] }}</p>@endif
            </div>
        </div>
    </header>

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
            <div class="grid grid-cols-3 gap-2 text-center {{ $status === 'completed' ? 'mt-3' : '' }}">
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <i class="bi bi-flag text-primary"></i>
                    <p class="text-[11px] font-bold text-foreground mt-1 leading-tight">{{ $d['metric'] }}</p>
                </div>
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <i class="bi bi-star-fill text-amber-400"></i>
                    <p class="text-[11px] font-bold text-foreground mt-1 leading-tight">{{ $d['stake'] }}</p>
                </div>
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <i class="bi bi-geo-alt text-primary"></i>
                    <p class="text-[11px] font-bold text-foreground mt-1 leading-tight">{{ $d['location'] ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Message ===== --}}
    @if(!empty($d['message']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: hsl(8 60% 58%);">{{ $d['opponent']['initials'] }}</div>
                <div>
                    <p class="text-xs font-bold text-foreground">{{ $d['opponent']['name'] }} <span class="text-muted-foreground font-normal">· {{ $d['when'] ?? '' }}</span></p>
                    <p class="text-sm text-muted-foreground mt-0.5 italic">“{{ $d['message'] }}”</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Head-to-head ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-bar-chart-line text-primary"></i> Head to head</h2>
            <div class="mt-3 space-y-2.5 text-sm">
                @foreach([['Total duels','4','2'],['Win rate','70%','64%'],['Best discipline',$d['type']==='fight'?'Boxing':'Sprint','Rowing']] as $row)
                    <div class="flex items-center">
                        <span class="w-14 text-right font-bold" style="color: {{ $d['color'] }};">{{ $row[1] }}</span>
                        <span class="flex-1 text-center text-[11px] text-muted-foreground">{{ $row[0] }}</span>
                        <span class="w-14 text-left font-bold text-muted-foreground">{{ $row[2] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

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
                <div x-show="reportOpen" x-cloak class="m-card rounded-2xl p-4">
                    <p class="text-sm font-bold text-foreground text-center mb-3">Who won this duel?</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="submitResult('rival')" :disabled="busy"
                                class="m-press flex-1 py-3 rounded-2xl border border-gray-200 text-muted-foreground text-sm font-bold disabled:opacity-50">{{ $d['opponent']['name'] }}</button>
                        <button type="button" @click="submitResult('me')" :disabled="busy"
                                class="m-press flex-1 py-3 rounded-2xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};">
                            <i class="bi bi-trophy"></i> I won
                        </button>
                    </div>
                    <button type="button" @click="reportOpen=false" class="m-press w-full mt-2 py-2 text-xs font-semibold text-muted-foreground">Cancel</button>
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
                <button type="button" @click="cancel()" class="m-press px-3 py-1.5 rounded-lg border border-red-200 text-red-600 text-xs font-bold">Cancel</button>
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
            <div class="m-card rounded-2xl p-4 text-center text-sm font-bold text-muted-foreground"><i class="bi bi-x-circle"></i> <span x-text="status==='declined' ? 'Duel declined' : 'Invitation cancelled'"></span></div>
        </template>

        {{-- completed --}}
        @if($status === 'completed')
            <a href="{{ route('me.challenge.create') }}" data-shell-link data-route="me.challenge"
               class="m-press w-full py-3 rounded-2xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $d['color'] }};">
                <i class="bi bi-arrow-repeat"></i> Rematch
            </a>
        @endif
    </div>

</div>
@endsection
