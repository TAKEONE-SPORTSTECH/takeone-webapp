@extends('layouts.personal-mobile')

@section('title', 'Brackets')

{{--
    Tournament brackets — mobile. DUMMY content from PersonalMobileController@eventBracket.
    Per weight category: enrollment state (joined / open slots), the single-elim
    bracket rendered as stacked rounds with full match details (athletes, seeds,
    countries, scores, winners, court & time), and podium + prizes for finished
    categories. Reuses the shared mobile motion vocabulary and design tokens.
--}}
@php
    $color = $e['color'];
    $first = collect($categories)->first()['key'] ?? '';
    // helpers
    $ini = fn ($n) => collect(explode(' ', $n))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

@php
    // Manager editor payload: keyed by category id.
    $editorCats = collect($categories)->mapWithKeys(fn ($c) => [$c['id'] => [
        'id' => $c['id'], 'name' => $c['name'], 'status' => $c['status'], 'note' => $c['note'],
        'matches' => $c['matches_flat'], 'podium' => $c['podium'], 'roster' => $c['roster_names'],
    ]])->all();
@endphp
@section('personal-content')
<div x-data="{
        cat: '{{ $first }}',
        canManage: {{ ($canManage ?? false) ? 'true' : 'false' }},
        editorCats: @js($editorCats),
        saveUrlBase: '{{ url('me/events/'.$e['key'].'/categories') }}',
        editing: null, busy: false,
        editName: '', editStatus: 'enrolling', editNote: '', editMatches: [], editPodium: [], editRoster: [],
        openEditor(id) {
            const c = this.editorCats[id]; if (!c) return;
            this.editing = id; this.editName = c.name; this.editStatus = c.status; this.editNote = c.note || '';
            this.editMatches = (c.matches || []).map(m => ({ ...m }));
            this.editPodium = (c.podium || []).map(p => ({ ...p }));
            this.editRoster = c.roster || [];
        },
        addMatch() { this.editMatches.push({ round: 'Quarter-finals', a_name: '', a_seed: '', a_score: '', b_name: '', b_seed: '', b_score: '', winner: '', court: '', time: '', status: 'upcoming' }); },
        removeMatch(i) { this.editMatches.splice(i, 1); },
        addPodium() { const p = this.editPodium.length + 1; this.editPodium.push({ place: p, name: '', country: '', prize: '' }); },
        removePodium(i) { this.editPodium.splice(i, 1); },
        generateDraw() {
            const r = (this.editRoster || []).filter(Boolean);
            if (r.length < 2) { window.showToast('warning', 'Add entrants to the division first'); return; }
            const size = r.length;
            const roundName = size > 8 ? 'Round of ' + size : (size > 4 ? 'Quarter-finals' : (size > 2 ? 'Semi-finals' : 'Final'));
            const ms = [];
            for (let i = 0; i < r.length; i += 2) {
                ms.push({ round: roundName, a_name: r[i] || '', a_seed: i + 1, a_score: '', b_name: r[i + 1] || 'Bye', b_seed: i + 2, b_score: '', winner: '', court: '', time: '', status: 'upcoming' });
            }
            this.editMatches = ms;
            window.showToast('success', 'Round drawn from ' + r.length + ' entrants — fill the rest as it progresses');
        },
        async saveDraw() {
            if (this.busy) return; this.busy = true;
            try {
                const payload = {
                    status: this.editStatus, note: this.editNote || null,
                    matches: this.editMatches.filter(m => (m.a_name||'').trim() || (m.b_name||'').trim())
                        .map(m => ({ ...m, a_seed: m.a_seed || null, b_seed: m.b_seed || null })),
                    podium: this.editPodium.filter(p => (p.name||'').trim()),
                };
                const res = await fetch(this.saveUrlBase + '/' + this.editing, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin', body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not save draw');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || window.location.href; }, 500);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        // Server-side auto-draw: (re)builds every division's bracket + match numbers.
        async generateNewDraw() {
            if (this.busy) return;
            const ok = await window.confirmAction({
                title: 'Generate draw?',
                message: 'Rebuilds the brackets and match numbers for every weight class from the current entrants. Before weigh-in this is the provisional (imaginary) draw; once the event has started it locks the final draw (paid + weighed-in only).',
                type: 'primary', confirmText: 'Generate',
            });
            if (!ok) return;
            this.busy = true;
            try {
                const res = await fetch('{{ route('me.events.generate-draw', $e['key']) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not generate draw');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || window.location.href; }, 500);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        }
     }" class="-mx-4 -mt-4 pb-6">

    {{-- ===== Header ===== --}}
    <header class="m-hero px-5 pt-5 pb-12 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, #1f2937);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <span class="px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur inline-flex items-center gap-1.5">
                <i class="bi bi-diagram-3-fill"></i> Brackets
            </span>
        </div>
        <div class="relative z-10 mt-4">
            <h1 class="text-xl font-black leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1 flex items-center gap-1.5"><i class="bi bi-diagram-3"></i> {{ count($categories) }} weight categories</p>
        </div>
    </header>

    {{-- ===== Category selector ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2">
            <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                @foreach($categories as $c)
                    <button type="button" @click="cat='{{ $c['key'] }}'"
                            class="m-press flex-shrink-0 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5"
                            :class="cat==='{{ $c['key'] }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                        {{ $c['name'] }}
                        @if($c['status'] === 'live')<span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                        @elseif($c['status'] === 'completed')<i class="bi bi-check-circle-fill text-[10px]"></i>@endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Manager: generate draw (pre-start only; nothing once the event is over) ===== --}}
    @if(($canManage ?? false) && !($e['ended'] ?? false))
        <div class="px-4 mt-3">
            @if($e['started'] ?? false)
                <div class="rounded-xl border border-gray-200 bg-muted/40 p-3 flex items-center gap-2 text-[12px] text-muted-foreground">
                    <i class="bi bi-lock-fill text-foreground"></i>
                    <span><span class="font-bold text-foreground">Draw is final.</span> The event has started — the bracket is locked and can’t be regenerated.</span>
                </div>
            @else
                <button type="button" @click="generateNewDraw()" :disabled="busy"
                        class="m-press w-full py-2.5 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }};">
                    <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-shuffle'"></i>
                    <span>Generate draw &amp; match numbers</span>
                </button>
                <p class="text-[11px] text-muted-foreground text-center mt-1.5">Provisional (imaginary) draw — regenerate freely until the event starts, then it locks.</p>
            @endif
        </div>
    @endif

    {{-- ===== Category panels ===== --}}
    @foreach($categories as $c)
        <div x-show="cat==='{{ $c['key'] }}'" x-transition class="px-4 mt-4 space-y-4">

            {{-- status / enrolment summary — hidden once the event is over --}}
            @if(!($e['ended'] ?? false))
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-foreground">{{ $c['name'] }}</h2>
                        <p class="text-[11px] text-muted-foreground">{{ $c['class'] }}</p>
                    </div>
                    @php
                        $badge = match($c['status']) {
                            'live' => ['Live now', 'bg-red-50 text-red-600'],
                            'completed' => ['Completed', 'bg-green-50 text-green-600'],
                            default => ['Enrolling', 'bg-amber-50 text-amber-600'],
                        };
                    @endphp
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $badge[1] }}">{{ $badge[0] }}</span>
                </div>

                {{-- joined / open slots --}}
                <div class="flex items-center justify-between text-[11px] mt-3 mb-1.5">
                    <span class="font-semibold text-foreground">{{ $c['joined'] }} joined</span>
                    <span class="text-muted-foreground">{{ is_null($c['cap']) ? 'No cap' : ($c['open'] . ' ' . ($c['open'] === 1 ? 'slot' : 'slots') . ' open') }}</span>
                </div>
                @if(!is_null($c['cap']) && $c['cap'] > 0)
                    <div class="h-2 rounded-full bg-muted overflow-hidden flex">
                        <div class="m-bar-fill h-full" style="width: {{ round($c['joined'] / $c['cap'] * 100) }}%; background: {{ $color }};"></div>
                    </div>
                @endif
                @if($c['note'])
                    <p class="text-[11px] text-muted-foreground mt-2 flex items-center gap-1.5"><i class="bi bi-info-circle"></i> {{ $c['note'] }}</p>
                @endif

                {{-- Manager: set / manage the draw — hidden once the event is over --}}
                @if(($canManage ?? false) && !($e['ended'] ?? false))
                    <button type="button" @click="openEditor({{ $c['id'] }})"
                            class="m-press mt-3 w-full py-2.5 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2" style="background: {{ $color }};">
                        <i class="bi bi-diagram-3-fill"></i> {{ empty($c['matches_flat']) ? 'Set the draw / bracket' : 'Manage draw' }}
                    </button>
                @endif
            </div>
            @endif

            {{-- Provisional-draw notice --}}
            @if(!empty($c['provisional']) && ($c['unpaid_count'] ?? 0) > 0)
                <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-3 flex items-start gap-2">
                    <i class="bi bi-hourglass-split text-amber-500 mt-0.5"></i>
                    <p class="text-[11px] text-amber-700 leading-relaxed">
                        <span class="font-bold">Provisional draw.</span>
                        {{ $c['unpaid_count'] }} {{ \Illuminate\Support\Str::plural('entry', $c['unpaid_count']) }} {{ $c['unpaid_count'] === 1 ? 'is' : 'are' }} held as a placeholder. When the event starts, anyone <span class="font-semibold">unpaid or not weighed in</span> is removed and the bracket is re-drawn from confirmed athletes only.
                    </p>
                </div>
            @endif

            {{-- ===== Completed → podium & prizes ===== --}}
            @if($c['status'] === 'completed' && !empty($c['podium']))
                <div class="m-card rounded-2xl p-4">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-award-fill text-amber-500"></i> Podium &amp; prizes</h3>
                    <div class="space-y-2">
                        @foreach($c['podium'] as $p)
                            @php $medal = [1 => ['#f59e0b','🥇'], 2 => ['#9ca3af','🥈'], 3 => ['#b45309','🥉']][$p['place']]; @endphp
                            <div class="flex items-center gap-3 rounded-xl p-2.5" style="background: {{ $medal[0] }}12;">
                                <div class="w-9 h-9 grid place-items-center text-2xl flex-shrink-0 leading-none">{{ $medal[1] }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-foreground truncate">{{ $p['name'] }} <span class="text-[10px] font-semibold text-muted-foreground">{{ $p['country'] }}</span></p>
                                    <p class="text-[11px] text-muted-foreground">{{ $p['place'] === 1 ? 'Champion' : ($p['place'] === 2 ? 'Runner-up' : '3rd place') }}</p>
                                </div>
                                <span class="text-[11px] font-black flex-shrink-0" style="color: {{ $medal[0] }};">{{ $p['prize'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Bracket (rounds) ===== --}}
            @if(!empty($c['rounds']))
                @foreach($c['rounds'] as $round)
                    <div class="m-card rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                                <i class="bi bi-diagram-2 text-primary"></i> {{ $round['name'] }}
                                @if($round['name'] === 'Final')
                                    <span class="text-lg leading-none" title="Decides gold &amp; silver">🥇 🥈</span>
                                @elseif($round['name'] === 'Semifinal')
                                    <span class="text-lg leading-none" title="Decides the two bronze medals">🥉 🥉</span>
                                @endif
                            </h3>
                            <span class="text-[11px] text-muted-foreground">{{ count($round['matches']) }} {{ count($round['matches']) === 1 ? 'bout' : 'bouts' }}</span>
                        </div>
                        <div class="space-y-3">
                            @foreach($round['matches'] as $m)
                                <div class="rounded-xl border border-gray-100 overflow-hidden">
                                    @foreach(['a', 'b'] as $side)
                                        @php
                                            $ath = $m[$side];
                                            $win = $m['winner'] === $side;
                                            $lose = $m['winner'] && $m['winner'] !== $side;
                                            $prov = !empty($ath['provisional']);
                                            $nm = $ath['name'] ?: null;
                                            $placeholder = $m['winner'] ? 'Bye' : 'TBD';
                                        @endphp
                                        <div class="flex items-center gap-2.5 px-3 py-2.5 {{ $side === 'a' ? 'border-b border-gray-50' : '' }}"
                                             style="{{ $win ? 'background: '.$color.'0d;' : '' }}">
                                            @if($nm)
                                                <div class="w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold flex-shrink-0 {{ ($lose || $prov) ? 'opacity-50' : '' }}"
                                                     style="background: hsl({{ (crc32($nm) % 360) }} 55% 58%); {{ $prov ? 'filter: grayscale(.4);' : '' }}">{{ $ini($nm) }}</div>
                                            @else
                                                <div class="w-8 h-8 rounded-full grid place-items-center text-gray-300 border-2 border-dashed border-gray-200 flex-shrink-0 text-[10px]"><i class="bi bi-dash"></i></div>
                                            @endif
                                            <div class="min-w-0 flex-1 {{ $lose ? 'opacity-60' : '' }}">
                                                <p class="text-sm font-bold {{ $nm ? 'text-foreground' : 'text-muted-foreground' }} truncate flex items-center gap-1.5 {{ $prov ? 'italic' : '' }}">
                                                    {{ $nm ?? $placeholder }}
                                                    @if($win)<i class="bi bi-check-circle-fill text-[11px]" style="color: {{ $color }};"></i>@endif
                                                    @if($prov)<span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-600 not-italic flex-shrink-0"><i class="bi bi-hourglass-split"></i> unpaid</span>@endif
                                                </p>
                                                <p class="text-[10px] text-muted-foreground">
                                                    @if($prov)Provisional — removed at start if unpaid / not weighed in@elseif($ath['country']){{ $ath['country'] }}@endif
                                                    @if($ath['seed']) · #{{ $ath['seed'] }} seed @endif
                                                </p>
                                            </div>
                                            <span class="text-base font-black flex-shrink-0 {{ $win ? '' : 'text-muted-foreground' }}" style="{{ $win ? 'color: '.$color : '' }}">{{ $ath['score'] }}</span>
                                        </div>
                                    @endforeach
                                    {{-- match meta --}}
                                    <div class="flex items-center justify-between px-3 py-1.5 bg-muted/40 text-[10px] text-muted-foreground">
                                        <span class="flex items-center gap-2 flex-wrap">
                                            @if(!empty($m['code']))
                                                <span class="font-bold text-foreground bg-white border border-gray-200 rounded px-1.5 py-0.5" title="{{ $m['court'] }} · Bout {{ $m['no'] }}">
                                                    <i class="bi bi-hash"></i>{{ $m['code'] }}
                                                </span>
                                            @endif
                                            @if(!empty($m['date']))
                                                <span class="flex items-center gap-1"><i class="bi bi-calendar3"></i> {{ $m['date'] }}@if($m['time']) · {{ $m['time'] }}@endif</span>
                                            @endif
                                            @if(!empty($m['court']))
                                                <span class="flex items-center gap-1"><i class="bi bi-geo-alt"></i> {{ $m['court'] }}</span>
                                            @endif
                                        </span>
                                        @if($m['status'] === 'done')
                                            <span class="font-bold text-green-600"><i class="bi bi-check2"></i> Final</span>
                                        @elseif($m['status'] === 'live')
                                            <span class="font-bold text-red-600 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> LIVE</span>
                                        @else
                                            <span class="font-semibold">Upcoming</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- ===== Enrolling → roster + open slots ===== --}}
            @if($c['status'] === 'enrolling')
                <div class="m-card rounded-2xl p-4" x-data="{
                        joined: {{ ($c['mine'] ?? false) ? 'true' : 'false' }}, busy: false,
                        async enter() {
                            if (this.busy || this.joined) return;
                            this.busy = true;
                            try {
                                const res = await fetch('{{ route('me.events.register', $e['key']) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ category_id: {{ $c['id'] }} }),
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.success) throw new Error(data.message || 'Could not enter');
                                this.joined = true;
                                window.showToast('success', data.message);
                            } catch (e) { window.showToast('error', e.message); }
                            finally { this.busy = false; }
                        }
                     }">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-1"><i class="bi bi-people text-primary"></i> Registered athletes</h3>
                    <p class="text-[11px] text-muted-foreground mb-3"><i class="bi bi-clock-history"></i> Bracket &amp; seeding generated after the weigh-in.</p>
                    <div class="space-y-2">
                        @foreach($c['roster'] as $i => $r)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0" style="background: hsl({{ ($i*67)%360 }} 55% 58%);">{{ $ini($r['name']) }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $r['name'] }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ $r['country'] }}</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600"><i class="bi bi-check2"></i> In</span>
                            </div>
                        @endforeach

                        {{-- open slots (yet to join) --}}
                        @for($s = 0; $s < $c['open']; $s++)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-gray-300 border-2 border-dashed border-gray-200 flex-shrink-0"><i class="bi bi-person-plus"></i></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-muted-foreground">Open slot</p>
                                    <p class="text-[10px] text-gray-400">Awaiting entry</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-muted text-muted-foreground">Open</span>
                            </div>
                        @endfor
                    </div>

                    @if(!($e['ended'] ?? false))
                        <button type="button" x-show="!joined" @click="enter()" :disabled="busy"
                                class="m-press mt-4 w-full py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }};">
                            <i class="bi bi-plus-circle"></i> Enter this category · {{ $e['participant_fee'] }}
                        </button>
                        <div x-show="joined" x-cloak class="mt-4 rounded-2xl bg-green-50 text-green-700 py-3 text-center text-sm font-bold"><i class="bi bi-check2-circle"></i> You're entered — see you at weigh-in</div>
                    @endif
                </div>
            @endif

        </div>
    @endforeach

    {{-- ===== Draw editor (managers) — teleported to body ===== --}}
    @if($canManage ?? false)
        <template x-teleport="body">
        <div x-show="editing !== null" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="editing=null" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="min-w-0">
                        <h3 class="font-black text-foreground flex items-center gap-2 truncate"><i class="bi bi-diagram-3-fill" style="color: {{ $color }};"></i> <span x-text="editName">Draw</span></h3>
                        <p class="text-[11px] text-muted-foreground">Set the bracket, results &amp; podium</p>
                    </div>
                    <button type="button" @click="editing=null" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- status --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stage</label>
                        <select x-model="editStatus" class="app-select w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <option value="enrolling">Enrolling — bracket not drawn yet</option>
                            <option value="live">Live — matches in progress</option>
                            <option value="completed">Completed — results final</option>
                        </select>
                        <input x-model="editNote" type="text" placeholder="Note — e.g. Semi-finals on Mat 1"
                               class="w-full mt-2 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>

                    {{-- matches --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-bold text-foreground">Matches</p>
                            <button type="button" @click="generateDraw()" class="m-press text-[11px] font-bold text-primary"><i class="bi bi-magic"></i> Draw from entrants</button>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(m, i) in editMatches" :key="i">
                                <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input x-model="m.round" type="text" placeholder="Round — e.g. Final" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <select x-model="m.status" class="app-select w-28 px-2 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 outline-none">
                                            <option value="upcoming">Upcoming</option><option value="live">Live</option><option value="done">Done</option>
                                        </select>
                                        <button type="button" @click="removeMatch(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-trash text-xs"></i></button>
                                    </div>
                                    {{-- competitor A --}}
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="m.winner = m.winner==='a' ? '' : 'a'" class="m-press w-7 h-7 rounded-full grid place-items-center flex-shrink-0 border-2" :class="m.winner==='a' ? 'text-white' : 'text-gray-300 border-gray-200'" :style="m.winner==='a' ? 'background:{{ $color }};border-color:{{ $color }}' : ''"><i class="bi bi-check-lg text-xs"></i></button>
                                        <input x-model="m.a_name" type="text" placeholder="Competitor A" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.a_seed" type="number" min="1" placeholder="#" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.a_score" type="text" placeholder="Score" class="w-16 px-2 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    {{-- competitor B --}}
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="m.winner = m.winner==='b' ? '' : 'b'" class="m-press w-7 h-7 rounded-full grid place-items-center flex-shrink-0 border-2" :class="m.winner==='b' ? 'text-white' : 'text-gray-300 border-gray-200'" :style="m.winner==='b' ? 'background:{{ $color }};border-color:{{ $color }}' : ''"><i class="bi bi-check-lg text-xs"></i></button>
                                        <input x-model="m.b_name" type="text" placeholder="Competitor B" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.b_seed" type="number" min="1" placeholder="#" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.b_score" type="text" placeholder="Score" class="w-16 px-2 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input x-model="m.court" type="text" placeholder="Court / mat" class="flex-1 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.time" type="text" placeholder="Time" class="w-24 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    <p class="text-[10px] text-muted-foreground">Tap the ✓ next to a competitor to mark them the winner.</p>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addMatch()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> Add match</button>
                    </div>

                    {{-- podium --}}
                    <div>
                        <p class="text-sm font-bold text-foreground mb-2"><i class="bi bi-award-fill text-amber-500"></i> Podium &amp; prizes</p>
                        <div class="space-y-2">
                            <template x-for="(p, i) in editPodium" :key="i">
                                <div class="flex items-center gap-2">
                                    <input x-model="p.place" type="number" min="1" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.name" type="text" placeholder="Name" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.country" type="text" placeholder="Ctry" class="w-14 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.prize" type="text" placeholder="Prize" class="w-24 px-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <button type="button" @click="removePodium(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addPodium()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> Add place</button>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-100">
                    <button type="button" @click="saveDraw()" :disabled="busy"
                            class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="busy ? 'Saving…' : 'Save draw'"></span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif

</div>
@endsection
