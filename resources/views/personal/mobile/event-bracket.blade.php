{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('personal.personal_event_bracket_title'))

{{--
    Tournament brackets — mobile. DUMMY content from PersonalMobileController@eventBracket.
    Per weight category: enrollment state (joined / open slots), the single-elim
    bracket rendered as stacked rounds with full match details (athletes, seeds,
    countries, scores, winners, court & time), and podium + prizes for finished
    categories. Reuses the shared mobile motion vocabulary and design tokens.
--}}
@php
    $color = $e['color'];
    // The division to open on: whatever the link asked for (a bout's "View draw"
    // names its own), else the first. Drives the initial view mode too.
    $first = $initialCategory ?? (collect($categories)->first()['key'] ?? '');
    // helpers
    $ini = fn ($n) => collect(explode(' ', $n))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

    // flag-icons needs a lowercase ISO alpha-2 class. Normalised the same way the
    // board runtime does it (strip non-letters, lowercase, first two), so a stray
    // code can never emit a broken `fi fi-` class. Returns '' when unusable.
    $flag = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? '<span class="fi fi-'.$c.' rounded-sm"></span>' : '';
    };
@endphp

@php
    // Manager editor payload: keyed by category id.
    $editorCats = collect($categories)->mapWithKeys(fn ($c) => [$c['id'] => [
        'id' => $c['id'], 'name' => $c['name'], 'status' => $c['status'], 'note' => $c['note'],
        'matches' => $c['matches_flat'], 'podium' => $c['podium'], 'roster' => $c['roster_names'],
    ]])->all();
@endphp
@section('personal-content')
@php
    // Division stats, keyed the same way the selector is, so the header can show
    // the capacity of whichever one is picked without duplicating the card.
    $catStats = collect($categories)->mapWithKeys(fn ($c) => [$c['key'] => [
        'id' => $c['id'],
        'name' => $c['name'],
        'class' => $c['class'],
        'status' => $c['status'],
        'joined' => $c['joined'],
        'cap' => $c['cap'],
        'open' => $c['open'],
        'pct' => $c['cap'] ? min(100, (int) round($c['joined'] / $c['cap'] * 100)) : 0,
        'rounds' => count($c['rounds'] ?? []),
    ]])->all();
@endphp
<div x-data="{
        cat: '{{ $first }}',
        {{-- 'board' = the zoomable draw, 'table' = the same bouts round by round.
             The board owns no switcher here (showDivisions=false); this page's
             pill tray drives it through window.BracketBoard.show(). --}}
        view: '{{ ($catStats[$first]['rounds'] ?? 0) ? 'board' : 'table' }}',
        stats: @js($catStats),
        get stat() { return this.stats[this.cat] || {}; },
        pickCat(key) {
            this.cat = key;
            {{-- Nothing drawn yet in this division: the board would be an empty
                 frame, so fall back to the list. --}}
            if (! (this.stats[key] && this.stats[key].rounds)) this.view = 'table';
            const id = this.stats[key] ? this.stats[key].id : null;
            if (this.view === 'board' && id && window.BracketBoard) window.BracketBoard.show(id);
        },
        setView(v) {
            this.view = v;
            {{-- The board only lays out once it is visible. --}}
            if (v === 'board') this.$nextTick(() => {
                const id = this.stat.id;
                if (id && window.BracketBoard) window.BracketBoard.show(id);
            });
        },
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
            if (r.length < 2) { window.showToast('warning', '{{ __('personal.personal_event_bracket_add_entrants') }}'); return; }
            const size = r.length;
            const roundName = size > 8 ? 'Round of ' + size : (size > 4 ? 'Quarter-finals' : (size > 2 ? 'Semi-finals' : 'Final'));
            const ms = [];
            for (let i = 0; i < r.length; i += 2) {
                ms.push({ round: roundName, a_name: r[i] || '', a_seed: i + 1, a_score: '', b_name: r[i + 1] || 'Bye', b_seed: i + 2, b_score: '', winner: '', court: '', time: '', status: 'upcoming' });
            }
            this.editMatches = ms;
            window.showToast('success', '{{ __('personal.personal_event_bracket_round_drawn') }}'.replace(':count', r.length));
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
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('personal.personal_event_bracket_could_not_save') }}');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || window.location.href; }, 500);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        // Server-side auto-draw: (re)builds every division's bracket + match numbers.
        async generateNewDraw() {
            if (this.busy) return;
            const ok = await window.confirmAction({
                title: '{{ __('personal.personal_event_bracket_generate_draw_title') }}',
                message: '{{ __('personal.personal_event_bracket_generate_draw_message') }}',
                type: 'primary', confirmText: '{{ __('personal.personal_event_bracket_generate_confirm') }}',
            });
            if (!ok) return;
            this.busy = true;
            try {
                const res = await fetch('{{ route('me.events.action', [$e['key'], 'generate_draw']) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('personal.personal_event_bracket_could_not_generate') }}');
                window.showToast('success', data.message);
                setTimeout(() => { window.location.href = data.redirect || window.location.href; }, 500);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        }
     }" class="-mx-4 -mt-4 pb-6">

    {{-- ===== Header ===== The standard event hero band (Design Rule #6): the
         subject's own colour lightened to +b0 (never faded to charcoal), two soft
         circles, a labelled back pill on the left with round 40px actions on the
         right, then chips → title → who it belongs to. The content below rides up
         over its tail. --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($color, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- Control row. z-50 so any dropdown paints above the title block. --}}
        <div class="flex items-center justify-between gap-2 relative z-50">
            {{-- Inside the sealed event app, back from a sub-screen means the
                     CONSOLE — the screen it was opened from. On the platform it
                     still means the event page. Same pill, honest label either
                     way (the audit: "'Event' means two different pages"). --}}
                <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}" title="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            <div class="flex items-center gap-2">
                @if($canManage ?? false)
                    <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events"
                       class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center"
                       aria-label="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders text-base"></i>
                    </a>
                @endif
                <x-qr-code
                    :url="route('me.events.show', ['event' => $e['key']])"
                    :title="$e['title'] . ' — ' . __('personal.event_show_event')"
                    caption="{{ __('personal.event_show_qr_caption') }}"
                    :filename="'qr-event-' . $e['key']"
                    label=""
                    icon="bi-qr-code"
                    :poster-url="route('qr.event', ['event' => $e['key']])"
                    button-class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white" />
            </div>
        </div>

        {{-- Identity: chips, the title, then who it belongs to. --}}
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-diagram-3-fill bracket-icon"></i> {{ __('personal.personal_event_bracket_title') }}
                </span>
                @if(!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                @endif
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-people-fill"></i> <span x-text="stat.name">{{ count($categories) }} {{ __('personal.personal_event_bracket_weight_categories') }}</span>
                </span>
            </div>

            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>

            {{-- How full the picked division is. It was a bar inside a card below;
                 the header is where "where does this stand" belongs. --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-[11px] font-medium text-white/85">
                    <span><span x-text="stat.joined">0</span> {{ __('personal.personal_event_bracket_joined') }}</span>
                    <span class="text-white/70"
                          x-text="stat.cap
                                    ? (stat.open + ' ' + (stat.open === 1 ? @js(__('personal.personal_event_bracket_slot')) : @js(__('personal.personal_event_bracket_slots'))) + ' ' + @js(__('personal.personal_event_bracket_slots_open_suffix')))
                                    : @js(__('personal.personal_event_bracket_no_cap'))"></span>
                </div>
                <div class="h-2 rounded-full bg-white/20 overflow-hidden mt-1.5" x-show="stat.cap" x-cloak>
                    <div class="m-bar-fill h-full bg-white/80 transition-all duration-500" :style="`width: ${stat.pct}%`"></div>
                </div>
            </div>
        </div>
    </header>

    {{-- A draw the organiser has not let out yet: the page is the veil and
         nothing else — not merely the board, because this page also reads the
         same bouts out as a list (PersonalEventController::bracket empties
         $categories to match). --}}
    @if($drawHidden ?? null)
        <div class="px-4 mt-4">
            <x-draw-veil :message="$drawHidden" :color="$color" />
        </div>
    @else

    {{-- ===== Category selector ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2">
            <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                @foreach($categories as $c)
                    {{-- Filters the round cards below. It no longer drives a board:
                         that moved to the full-screen "Manage draw" page. --}}
                    <button type="button" @click="pickCat('{{ $c['key'] }}')"
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

    {{-- ===== How to read the draw: the board, or the same bouts as a list =====
         Only appears once the picked division HAS a draw — there is nothing to
         switch between while a division is still enrolling. --}}
    <div class="px-4 mt-3" x-show="stat.rounds" x-cloak>
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2">
            <div class="flex gap-2">
                <button type="button" @click="setView('board')"
                        :aria-pressed="view === 'board'"
                        class="m-press flex-1 min-w-0 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5"
                        :class="view === 'board' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                    <i class="bi bi-diagram-3-fill bracket-icon"></i>{{ __('personal.personal_event_bracket_view_board') }}
                </button>
                <button type="button" @click="setView('table')"
                        :aria-pressed="view === 'table'"
                        class="m-press flex-1 min-w-0 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5"
                        :class="view === 'table' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                    <i class="bi bi-list-ol"></i>{{ __('personal.personal_event_bracket_view_table') }}
                </button>
            </div>
        </div>
    </div>

    {{-- The zoomable board. Read-only here — arranging lives in the console — and
         with its own division switcher off, because this page already has one. --}}
    <div class="px-4 mt-3" x-show="view === 'board' && stat.rounds" x-cloak>
        <x-tournament-bracket
            id="event-bracket-mobile"
            :data-url="route('me.events.bracket.data', $e['key'])"
            :event-uuid="$e['key']"
            :can-arrange="false"
            {{-- Open on the division this page opened on, not the first. --}}
            :initial-division="$catStats[$first]['id'] ?? null"
            :show-divisions="false"
            :my-competitor-ids="$myCompetitorIds ?? []"
            height="62vh" />
    </div>

    {{-- ===== Manager: draw locked =====
         Event-wide and only once the event has started. The Generate button and
         the provisional-draw note both moved into each division card, next to
         the action they describe. --}}
    @if(($canManage ?? false) && !($e['ended'] ?? false) && ($e['started'] ?? false))
        <div class="px-4 mt-3">
            <div class="rounded-xl border border-gray-200 bg-muted/40 p-3 flex items-center gap-2 text-[12px] text-muted-foreground">
                <i class="bi bi-lock-fill text-foreground"></i>
                <span><span class="font-bold text-foreground">{{ __('personal.personal_event_bracket_draw_is_final') }}</span> {{ __('personal.personal_event_bracket_draw_locked') }}</span>
            </div>
        </div>
    @endif

    {{-- ===== Category panels ===== --}}
    @foreach($categories as $c)
        <div x-show="cat==='{{ $c['key'] }}'" x-transition class="px-4 mt-4 space-y-4">

            {{-- The division summary card (name, status, joined/open, capacity bar)
                 used to sit here. Its numbers moved into the header, where they
                 describe whichever division is picked, and the note it carried
                 rides with the provisional notice below. --}}
            @if(!($e['ended'] ?? false) && $c['note'])
                <p class="text-[11px] text-muted-foreground flex items-center gap-1.5 px-1">
                    <i class="bi bi-info-circle"></i>{{ $c['note'] }}
                </p>
            @endif

            {{-- Provisional-draw notice --}}
            @if(!empty($c['provisional']) && ($c['unpaid_count'] ?? 0) > 0)
                <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-3 flex items-start gap-2">
                    <i class="bi bi-hourglass-split text-amber-500 mt-0.5"></i>
                    <p class="text-[11px] text-amber-700 leading-relaxed">
                        <span class="font-bold">{{ __('personal.personal_event_bracket_provisional_draw') }}</span>
                        {{ $c['unpaid_count'] }} {{ $c['unpaid_count'] === 1 ? __('personal.personal_event_bracket_entry') : __('personal.personal_event_bracket_entries') }} {{ $c['unpaid_count'] === 1 ? __('personal.personal_event_bracket_is') : __('personal.personal_event_bracket_are') }} {{ __('personal.personal_event_bracket_held_placeholder') }} <span class="font-semibold">{{ __('personal.personal_event_bracket_unpaid_or_not_weighed') }}</span> {{ __('personal.personal_event_bracket_removed_redrawn') }}
                    </p>
                </div>
            @endif

            {{-- ===== Completed → podium & prizes ===== --}}
            @if($c['status'] === 'completed' && !empty($c['podium']))
                <div class="m-card rounded-2xl p-4">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3"><i class="bi bi-award-fill text-amber-500"></i> {{ __('personal.personal_event_bracket_podium_prizes') }}</h3>
                    <div class="space-y-2">
                        @foreach($c['podium'] as $p)
                            @php $medal = [1 => ['#f59e0b','🥇'], 2 => ['#9ca3af','🥈'], 3 => ['#b45309','🥉']][$p['place']]; @endphp
                            <div class="flex items-center gap-3 rounded-xl p-2.5" style="background: {{ $medal[0] }}12;">
                                <div class="w-9 h-9 grid place-items-center text-2xl flex-shrink-0 leading-none">{{ $medal[1] }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-foreground truncate">{{ $p['name'] }} {!! $flag($p['country']) !!} <span class="text-[10px] font-semibold text-muted-foreground">{{ $p['country'] }}</span></p>
                                    <p class="text-[11px] text-muted-foreground">{{ $p['place'] === 1 ? __('personal.personal_event_bracket_champion') : ($p['place'] === 2 ? __('personal.personal_event_bracket_runner_up') : __('personal.personal_event_bracket_third_place')) }}</p>
                                </div>
                                <span class="text-[11px] font-black flex-shrink-0" style="color: {{ $medal[0] }};">{{ $p['prize'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Bracket (rounds) — the TABLE reading of the draw =====
                 The board reading of the same bouts is mounted once above; only
                 these cards swap with the toggle. Everything else on the panel —
                 the podium, the roster, the entry button — belongs to the
                 division, not to how you are looking at its draw. --}}
            @if(!empty($c['rounds']))
                <template x-if="view === 'table'"><div class="space-y-4">
                @foreach($c['rounds'] as $round)
                    <div class="m-card rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                                <i class="bi bi-diagram-2 text-primary"></i> {{ $round['name'] }}
                                @if($round['name'] === 'Final')
                                    <span class="text-lg leading-none" title="{{ __('personal.personal_event_bracket_decides_gold_silver') }}">🥇 🥈</span>
                                @elseif($round['name'] === 'Semifinal')
                                    <span class="text-lg leading-none" title="{{ __('personal.personal_event_bracket_decides_bronze') }}">🥉 🥉</span>
                                @endif
                            </h3>
                            <span class="text-[11px] text-muted-foreground">{{ count($round['matches']) }} {{ count($round['matches']) === 1 ? __('personal.personal_event_bracket_bout') : __('personal.personal_event_bracket_bouts') }}</span>
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
                                            $placeholder = $m['winner'] ? __('personal.personal_event_bracket_bye') : __('personal.personal_event_bracket_tbd');
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
                                                    @if($prov)<span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-600 not-italic flex-shrink-0"><i class="bi bi-hourglass-split"></i> {{ __('personal.personal_event_bracket_unpaid') }}</span>@endif
                                                </p>
                                                <p class="text-[10px] text-muted-foreground flex items-center gap-1">
                                                    @if($prov)
                                                        <span>{{ __('personal.personal_event_bracket_provisional_removed') }}</span>
                                                    @elseif($ath['country'])
                                                        {!! $flag($ath['country']) !!}<span>{{ $ath['country'] }}</span>
                                                    @endif
                                                    @if($ath['seed'])<span>· #{{ $ath['seed'] }} {{ __('personal.personal_event_bracket_seed') }}</span>@endif
                                                </p>
                                            </div>
                                            <span class="text-base font-black flex-shrink-0 {{ $win ? '' : 'text-muted-foreground' }}" style="{{ $win ? 'color: '.$color : '' }}">{{ $ath['score'] }}</span>
                                        </div>
                                    @endforeach
                                    {{-- match meta --}}
                                    <div class="flex items-center justify-between px-3 py-1.5 bg-muted/40 text-[10px] text-muted-foreground">
                                        <span class="flex items-center gap-2 flex-wrap">
                                            @if(!empty($m['code']))
                                                <span class="font-bold text-foreground bg-white border border-gray-200 rounded px-1.5 py-0.5" title="{{ $m['court'] }} · {{ __('personal.personal_event_bracket_bout_word') }} {{ $m['no'] }}">
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
                                            <span class="font-bold text-green-600"><i class="bi bi-check2"></i> {{ __('personal.personal_event_bracket_final_status') }}</span>
                                        @elseif($m['status'] === 'live')
                                            <span class="font-bold text-red-600 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> {{ __('personal.personal_event_bracket_live_status') }}</span>
                                        @else
                                            <span class="font-semibold">{{ __('personal.personal_event_bracket_upcoming') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                </div></template>
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
                                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('personal.personal_event_bracket_could_not_enter') }}');
                                this.joined = true;
                                window.showToast('success', data.message);
                            } catch (e) { window.showToast('error', e.message); }
                            finally { this.busy = false; }
                        }
                     }">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-1"><i class="bi bi-people text-primary"></i> {{ __('personal.personal_event_bracket_registered_athletes') }}</h3>
                    <p class="text-[11px] text-muted-foreground mb-3"><i class="bi bi-clock-history"></i> {{ __('personal.personal_event_bracket_bracket_seeding_after_weighin') }}</p>
                    <div class="space-y-2">
                        @forelse($c['roster'] as $i => $r)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0" style="background: hsl({{ ($i*67)%360 }} 55% 58%);">{{ $ini($r['name']) }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $r['name'] }}</p>
                                    <p class="text-[10px] text-muted-foreground flex items-center gap-1">
                                        {!! $flag($r['country']) !!}<span>{{ $r['country'] }}</span>
                                    </p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600"><i class="bi bi-check2"></i> {{ __('personal.personal_event_bracket_in_badge') }}</span>
                            </div>
                        @empty
                            {{-- The placeholder rows used to fill this space; without
                                 them an unentered division needs to say so itself. --}}
                            <p class="text-[11px] text-muted-foreground text-center py-2">
                                {{ __('personal.personal_event_bracket_no_entrants_yet') }}
                            </p>
                        @endforelse

                        {{-- No placeholder rows for unfilled slots: a division with 6
                             entrants and a cap of 16 listed ten "Open slot" rows,
                             burying the real athletes. The remaining capacity is
                             already stated in the enrolment summary above. --}}
                    </div>

                    @if(!($e['ended'] ?? false))
                        <button type="button" x-show="!joined" @click="enter()" :disabled="busy"
                                class="m-press mt-4 w-full py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }};">
                            <i class="bi bi-plus-circle"></i> {{ __('personal.personal_event_bracket_enter_category') }} · {{ $e['participant_fee'] }}
                        </button>
                        <div x-show="joined" x-cloak class="mt-4 rounded-2xl bg-green-50 text-green-700 py-3 text-center text-sm font-bold"><i class="bi bi-check2-circle"></i> {{ __('personal.personal_event_bracket_youre_entered') }}</div>
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
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-diagram-3-fill bracket-icon text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight truncate"><span x-text="editName">{{ __('personal.personal_event_bracket_draw_fallback') }}</span></h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('personal.personal_event_bracket_set_bracket_results_podium') }}</p>
                        </div>
                        <button type="button" @click="editing=null" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- status --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_bracket_stage') }}</label>
                        <x-select-menu model="editStatus"
                                       :options="[['value' => 'enrolling', 'label' => __('personal.personal_event_bracket_opt_enrolling')], ['value' => 'live', 'label' => __('personal.personal_event_bracket_opt_live')], ['value' => 'completed', 'label' => __('personal.personal_event_bracket_opt_completed')]]" />
                        <input x-model="editNote" type="text" placeholder="{{ __('personal.personal_event_bracket_note_placeholder') }}"
                               class="w-full mt-2 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>

                    {{-- matches --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-bold text-foreground">{{ __('personal.personal_event_bracket_matches') }}</p>
                            <button type="button" @click="generateDraw()" class="m-press text-[11px] font-bold text-primary"><i class="bi bi-magic"></i> {{ __('personal.personal_event_bracket_draw_from_entrants') }}</button>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(m, i) in editMatches" :key="i">
                                <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input x-model="m.round" type="text" placeholder="{{ __('personal.personal_event_bracket_round_placeholder') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <div class="w-28 flex-shrink-0">
                                            <x-select-menu model="m.status"
                                                           :options="[['value' => 'upcoming', 'label' => __('personal.personal_event_bracket_upcoming')], ['value' => 'live', 'label' => __('personal.personal_event_bracket_status_live')], ['value' => 'done', 'label' => __('personal.personal_event_bracket_status_done')]]" />
                                        </div>
                                        <button type="button" @click="removeMatch(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-trash text-xs"></i></button>
                                    </div>
                                    {{-- competitor A --}}
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="m.winner = m.winner==='a' ? '' : 'a'" class="m-press w-7 h-7 rounded-full grid place-items-center flex-shrink-0 border-2" :class="m.winner==='a' ? 'text-white' : 'text-gray-300 border-gray-200'" :style="m.winner==='a' ? 'background:{{ $color }};border-color:{{ $color }}' : ''"><i class="bi bi-check-lg text-xs"></i></button>
                                        <input x-model="m.a_name" type="text" placeholder="{{ __('personal.personal_event_bracket_competitor_a') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.a_seed" type="number" min="1" placeholder="#" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.a_score" type="text" placeholder="{{ __('personal.personal_event_bracket_score') }}" class="w-16 px-2 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    {{-- competitor B --}}
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="m.winner = m.winner==='b' ? '' : 'b'" class="m-press w-7 h-7 rounded-full grid place-items-center flex-shrink-0 border-2" :class="m.winner==='b' ? 'text-white' : 'text-gray-300 border-gray-200'" :style="m.winner==='b' ? 'background:{{ $color }};border-color:{{ $color }}' : ''"><i class="bi bi-check-lg text-xs"></i></button>
                                        <input x-model="m.b_name" type="text" placeholder="{{ __('personal.personal_event_bracket_competitor_b') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.b_seed" type="number" min="1" placeholder="#" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.b_score" type="text" placeholder="{{ __('personal.personal_event_bracket_score') }}" class="w-16 px-2 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input x-model="m.court" type="text" placeholder="{{ __('personal.personal_event_bracket_court_mat') }}" class="flex-1 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input x-model="m.time" type="text" placeholder="{{ __('personal.personal_event_bracket_time') }}" class="w-24 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    </div>
                                    <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_event_bracket_tap_check_winner') }}</p>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addMatch()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_bracket_add_match') }}</button>
                    </div>

                    {{-- podium --}}
                    <div>
                        <p class="text-sm font-bold text-foreground mb-2"><i class="bi bi-award-fill text-amber-500"></i> {{ __('personal.personal_event_bracket_podium_prizes') }}</p>
                        <div class="space-y-2">
                            <template x-for="(p, i) in editPodium" :key="i">
                                <div class="flex items-center gap-2">
                                    <input x-model="p.place" type="number" min="1" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.name" type="text" placeholder="{{ __('personal.personal_event_bracket_name') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.country" type="text" placeholder="{{ __('personal.personal_event_bracket_ctry') }}" class="w-14 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <input x-model="p.prize" type="text" placeholder="{{ __('personal.personal_event_bracket_prize') }}" class="w-24 px-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                                    <button type="button" @click="removePodium(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addPodium()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_bracket_add_place') }}</button>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-100">
                    <button type="button" @click="saveDraw()" :disabled="busy"
                            class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="busy ? '{{ __('personal.personal_event_bracket_saving') }}' : '{{ __('personal.personal_event_bracket_save_draw') }}'"></span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif

    @endif {{-- the draw-withheld veil --}}
</div>
@endsection
