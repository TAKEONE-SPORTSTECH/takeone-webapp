{{-- New UI Challenge hub — shared by challenge/desktop and challenge/mobile.
     Same data as the classic hub (ChallengeController@index: $challenges,
     $duels) and the same endpoints: accept/decline post to the duel routes,
     everything else opens the existing duel / challenge pages. --}}
@php
    $list = array_values($challenges);
    $duelList = array_values($duels);
    $incoming = collect($duelList)->where('status', 'invite_incoming')->values();
    $activeDuels = collect($duelList)->whereIn('status', ['active', 'reported'])->values();
    $sent = collect($duelList)->where('status', 'invite_sent')->values();
    $wins = collect($duelList)->where('status', 'completed')->where('result', 'win')->count();
    $losses = collect($duelList)->where('status', 'completed')->where('result', 'loss')->count();
    $totalPoints = collect($list)->where('completed', true)->sum('points')
        + collect($duelList)->where('status', 'completed')->where('result', 'win')->sum('points_earned');
    $joined = collect($list)->where('joined', true)->where('status', 'active')->count();
    $feat = collect($list)->firstWhere('status', 'active') ?? ($list[0] ?? null);
@endphp
<div class="t1 t1-page" x-data="{ seg: 'active' }">
    <div class="t1-bleed">
        <header class="t1-band gold">
            <span class="t1-c1"></span><span class="t1-c2"></span>
            <div class="t1-band-in">
                <span class="t1-chipw"><i class="bi bi-trophy"></i>{{ __('nav.tab_challenge') }}</span>
                <h2>{{ __('ui::challenge.your_record') }}</h2>
                <div class="t1-rec">
                    <div><b>{{ number_format($totalPoints) }}</b><span>{{ __('ui::challenge.points') }}</span></div>
                    <div><b>{{ $wins }}–{{ $losses }}</b><span>{{ __('ui::challenge.record') }}</span></div>
                    <div><b>{{ $activeDuels->count() }}</b><span>{{ __('ui::challenge.live') }}</span></div>
                    <div><b>{{ $joined }}</b><span>{{ __('ui::challenge.joined') }}</span></div>
                </div>
                <div class="t1-cact">
                    <a class="w" href="{{ route('me.challenge.create') }}"><i class="bi bi-plus-lg"></i>{{ __('ui::challenge.challenge_someone') }}</a>
                    <a class="g" href="{{ route('me.challenge.history') }}"><i class="bi bi-clock-history"></i>{{ __('ui::challenge.history') }}</a>
                </div>
            </div>
        </header>
    </div>

    <div class="t1-cwrap t1-after">
        <div class="t1-cmain">
            {{-- Invitations --}}
            @if($incoming->isNotEmpty())
                <div class="t1-secth">{{ __('ui::challenge.invitations') }} <span class="t1-n">{{ $incoming->count() }}</span></div>
                @foreach($incoming as $d)
                    <div class="t1-invite" x-data="{ resolved: '', busy: false,
                            async act(kind) {
                                if (this.busy) return; this.busy = true;
                                const urls = { accept: @js(route('me.challenge.duel.accept', $d['id'])), decline: @js(route('me.challenge.duel.decline', $d['id'])) };
                                try {
                                    const res = await fetch(urls[kind], { method: 'POST', credentials: 'same-origin',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' } });
                                    const data = await res.json().catch(() => ({}));
                                    if (!res.ok || !data.success) throw new Error(data.message || @js(__('challenge.partials_duel_card_action_failed')));
                                    this.resolved = kind;
                                    window.showToast(kind === 'accept' ? 'success' : 'info', data.message);
                                } catch (e) { window.showToast('error', e.message); } finally { this.busy = false; }
                            } }">
                        <div class="t1-itop">@include('ui::challenge.partials.person', ['p' => $d['opponent'], 'size' => 's'])
                            <span class="t1-tx"><b>{{ $d['opponent']['name'] }}</b> {{ __('ui::challenge.challenged_you') }}</span></div>
                        <h5><a href="{{ route('me.challenge.duel', $d['id']) }}">{{ $d['discipline'] }}</a></h5>
                        <div class="t1-terms">
                            <span><i class="bi {{ $d['type'] === 'fight' ? 'bi-trophy' : 'bi-lightning-charge' }}"></i>{{ $d['type'] === 'fight' ? __('challenge.partials_duel_card_type_fight') : __('challenge.partials_duel_card_type_athletic') }}</span>
                            @if(!empty($d['deadline']))<span><i class="bi bi-clock"></i>{{ $d['deadline'] }}</span>@endif
                            @if(!empty($d['stake']))<span><i class="bi bi-gift"></i>{{ $d['stake'] }}</span>@endif
                            @if(!empty($d['location']) && trim($d['location']) !== '—')<span><i class="bi bi-geo-alt"></i>{{ $d['location'] }}</span>@endif
                        </div>
                        <div class="t1-two" x-show="!resolved">
                            <button type="button" class="y" :disabled="busy" @click="act('accept')">{{ __('ui::challenge.accept') }}</button>
                            <button type="button" class="n" :disabled="busy" @click="act('decline')">{{ __('ui::challenge.decline') }}</button>
                        </div>
                        <div class="t1-terms" x-show="resolved" x-cloak><span class="ok" x-text="resolved === 'accept' ? @js(__('ui::challenge.accepted')) : @js(__('ui::challenge.declined'))"></span></div>
                    </div>
                @endforeach
            @endif

            {{-- Live duels --}}
            <div class="t1-secth"><span class="t1-live"></span>{{ __('ui::challenge.live_duels') }} <span class="t1-n">{{ $activeDuels->count() }}</span></div>
            @if($activeDuels->isEmpty())
                <div class="t1-box t1-empty"><b>{{ __('ui::challenge.none') }}</b>{{ __('ui::challenge.none_duels') }}</div>
            @else
                <div class="t1-duels">
                    @foreach($activeDuels as $d)
                        @php
                            $ms = $d['me']['score'] ?? null; $os = $d['opponent']['score'] ?? null;
                            $hasScore = $ms !== null && $os !== null && is_numeric($ms) && is_numeric($os);
                            $mePct = $hasScore && ($ms + $os) > 0 ? round($ms / ($ms + $os) * 100) : 50;
                        @endphp
                        <div class="t1-duel" style="--c:{{ $d['color'] }}">
                            <div class="t1-dtop"><span class="t1-ty">{{ $d['type'] === 'fight' ? __('challenge.partials_duel_card_type_fight') : __('challenge.partials_duel_card_type_athletic') }}</span><span><i class="bi bi-clock"></i> {{ $d['when'] ?? $d['deadline'] ?? '' }}</span></div>
                            <h5>{{ $d['discipline'] }}</h5>
                            <div class="t1-vs">
                                <span class="t1-side">@include('ui::challenge.partials.person', ['p' => $d['me']]){{ __('ui::challenge.you') }}
                                    <span class="t1-sc">{{ $ms ?? '–' }}</span>
                                    @if($hasScore && $ms != $os)<span class="t1-lead {{ $ms > $os ? '' : 'behind' }}">{{ $ms > $os ? __('challenge.partials_duel_card_leading') : __('challenge.partials_duel_card_behind') }}</span>@endif
                                </span>
                                <span class="t1-mid">{{ __('ui::challenge.vs') }}</span>
                                <span class="t1-side">@include('ui::challenge.partials.person', ['p' => $d['opponent']]){{ $d['opponent']['name'] }}
                                    <span class="t1-sc">{{ $os ?? '–' }}</span>
                                </span>
                            </div>
                            <div class="t1-bar2"><i style="width:{{ $mePct }}%{{ $hasScore ? '' : ';opacity:.35' }}"></i><i style="width:{{ 100 - $mePct }}%{{ $hasScore ? '' : ';opacity:.35' }}"></i></div>
                            <div class="t1-dfoot">
                                <small>@if($d['status'] === 'reported')<i class="bi bi-hourglass-split"></i> {{ __('ui::challenge.reported') }}@elseif(!empty($d['location']) && trim($d['location']) !== '—')<i class="bi bi-geo-alt"></i> {{ $d['location'] }}@else<i class="bi bi-eye"></i> {{ count($d['witnesses'] ?? []) }}@endif</small>
                                <a href="{{ route('me.challenge.duel', $d['id']) }}">{{ $d['status'] === 'active' ? __('ui::challenge.log_result') : __('ui::challenge.open') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Sent, awaiting reply --}}
            @if($sent->isNotEmpty())
                <div class="t1-box">
                    <h4><i class="bi bi-hourglass-split"></i>{{ __('ui::challenge.awaiting') }}</h4>
                    @foreach($sent as $d)
                        <a class="t1-row" href="{{ route('me.challenge.duel', $d['id']) }}">@include('ui::challenge.partials.person', ['p' => $d['opponent'], 'size' => 's'])
                            <span class="t1-tx"><b>{{ $d['opponent']['name'] }}</b><small>{{ $d['discipline'] }}</small></span><i class="bi bi-chevron-right rtl:rotate-180"></i></a>
                    @endforeach
                </div>
            @endif

            {{-- Challenges --}}
            <div class="t1-secth">{{ __('ui::challenge.challenges') }}</div>
            <div class="t1-segs" role="group">
                @foreach(['active', 'upcoming', 'completed'] as $st)
                    <button type="button" @click="seg='{{ $st }}'" :class="{ on: seg==='{{ $st }}' }">{{ __('ui::challenge.'.$st) }}</button>
                @endforeach
            </div>
            @foreach(['active', 'upcoming', 'completed'] as $st)
                @php $rows = collect($list)->where('status', $st)->values(); @endphp
                <div class="t1-cgrid" x-show="seg==='{{ $st }}'" @if($st !== 'active') x-cloak @endif>
                    @forelse($rows as $c)
                        <a class="t1-ch" href="{{ route('me.challenge.show', $c['id']) }}" style="--c:{{ $c['color'] ?? '#7c3aed' }}">
                            <span class="t1-prog" style="--p:{{ (int) ($c['progress'] ?? 0) }}"><span>{{ (int) ($c['progress'] ?? 0) }}%</span></span>
                            <span style="min-width:0">
                                <h5>{{ $c['title'] }}</h5>
                                <span class="t1-sub2">{{ collect([$c['club'] ?? null, isset($c['goal']) ? ($c['current'] ?? 0).' / '.$c['goal'].' '.($c['unit'] ?? '') : null, isset($c['days_left']) && $st === 'active' ? __('ui::challenge.days_left', ['n' => $c['days_left']]) : null])->filter()->implode(' · ') }}</span>
                                @if(!empty($c['leaders']))
                                    <span class="t1-lb">@foreach(array_slice($c['leaders'], 0, 3) as $l)@include('ui::challenge.partials.person', ['p' => $l, 'size' => 'xs'])@endforeach<small>{{ __('ui::challenge.leaderboard') }}</small></span>
                                @endif
                            </span>
                            <span class="t1-reward"><span><i class="bi bi-gift"></i> {{ __('ui::challenge.pts', ['n' => $c['points'] ?? 0]) }}</span><span class="t1-pill">{{ ($c['joined'] ?? false) || $st === 'completed' ? __('ui::challenge.view') : __('ui::challenge.join') }}</span></span>
                        </a>
                    @empty
                        <div class="t1-box t1-empty" style="grid-column:1/-1"><b>{{ __('ui::challenge.none') }}</b></div>
                    @endforelse
                </div>
            @endforeach
        </div>

        <aside class="t1-cside">
            @if($feat && !empty($feat['leaders']))
                <div class="t1-box t1-board">
                    <h4><i class="bi bi-trophy"></i>{{ $feat['title'] }}</h4>
                    @foreach($feat['leaders'] as $i => $l)
                        <div class="t1-row {{ !empty($l['me']) ? 'me' : '' }}"><span class="t1-rk">{{ $i + 1 }}</span>@include('ui::challenge.partials.person', ['p' => $l, 'size' => 'xs'])
                            <span class="t1-tx"><b>{{ $l['name'] }}</b></span><b class="t1-pts">{{ $l['pts'] ?? $l['val'] ?? '' }}</b></div>
                    @endforeach
                </div>
            @endif
            <div class="t1-box">
                <h4><i class="bi bi-clock-history"></i>{{ __('ui::challenge.history') }}</h4>
                <div class="t1-row"><span class="t1-tx"><b>{{ $wins }}–{{ $losses }}</b><small>{{ __('ui::challenge.record') }}</small></span>
                    <a class="t1-sbtn" href="{{ route('me.challenge.history') }}">{{ __('ui::challenge.see_history') }}</a></div>
            </div>
        </aside>
    </div>

    {{-- Live duel notifications — same messages as the classic hub. --}}
    <script>
        (function () {
            if (window.__challengeRealtime) return;
            window.__challengeRealtime = true;
            window.addEventListener('realtime:challenges', function (e) {
                var d = (e && e.detail) || {};
                var map = {
                    'duel:new': (d.actor || @js(__('challenge.personal_challenge_js_someone'))) + @js(__('challenge.personal_challenge_js_challenged')) + (d.discipline || ''),
                    'duel:accepted': (d.actor || @js(__('challenge.personal_challenge_js_your_rival'))) + @js(__('challenge.personal_challenge_js_accepted')),
                    'duel:declined': (d.actor || @js(__('challenge.personal_challenge_js_your_rival'))) + @js(__('challenge.personal_challenge_js_declined')),
                    'duel:result': (d.actor || @js(__('challenge.personal_challenge_js_your_rival'))) + @js(__('challenge.personal_challenge_js_result')),
                };
                var msg = map[d.action];
                if (msg && window.showToast) window.showToast('info', msg);
            });
        })();
    </script>
</div>
