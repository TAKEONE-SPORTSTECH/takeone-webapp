@extends('layouts.app')

@section('title', __('nav.tab_challenge'))

@php
    $list  = array_values($challenges);
    $feat  = collect($list)->firstWhere('status', 'active') ?? ($list[0] ?? null);

    $duelList   = array_values($duels);
    $incoming   = collect($duelList)->where('status', 'invite_incoming')->values();
    $activeDuels= collect($duelList)->whereIn('status', ['active', 'reported'])->values();
    $sent       = collect($duelList)->where('status', 'invite_sent')->values();

    $wins   = collect($duelList)->where('status', 'completed')->where('result', 'win')->count();
    $losses = collect($duelList)->where('status', 'completed')->where('result', 'loss')->count();
    $record = "{$wins}W · {$losses}L";
    $totalPoints = collect($list)->where('completed', true)->sum('points')
                 + collect($duelList)->where('status', 'completed')->where('result', 'win')->sum('points_earned');
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{ seg: 'active' }">

    @include('partials.personal-desktop-subnav')

    {{-- ===== Hero stat bar ===== --}}
    <div class="rounded-2xl shadow-sm p-6 text-white relative overflow-hidden mb-6" style="background: linear-gradient(135deg, #7c3aed, #ef4444);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="relative flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-white/70">{{ __('challenge.title') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('challenge.subtitle') }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-4 py-2.5 text-center min-w-[84px]">
                    <p class="text-lg font-black leading-none">{{ $totalPoints }}</p>
                    <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_points') }}</p>
                </div>
                <div class="rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-4 py-2.5 text-center min-w-[84px]">
                    <p class="text-lg font-black leading-none">{{ $record }}</p>
                    <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_record') }}</p>
                </div>
                <div class="rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-4 py-2.5 text-center min-w-[84px]">
                    <p class="text-lg font-black leading-none">{{ $activeDuels->count() }}</p>
                    <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_duels') }}</p>
                </div>
                <a href="{{ route('me.challenge.history') }}"
                   class="px-4 h-[52px] rounded-2xl bg-white/15 border border-white/25 backdrop-blur flex items-center gap-1.5 text-xs font-semibold hover:bg-white/25 transition-colors">
                    <i class="bi bi-clock-history"></i> {{ __('challenge.personal_challenge_history') }}
                </a>
            </div>
        </div>
    </div>

    {{-- ===== Two-column arena: Versus | Solo (desktop shows both at once) ===== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- ============ VERSUS column ============ --}}
        <div class="space-y-4">
            <h2 class="text-sm font-black text-foreground flex items-center gap-2 px-1">
                <i class="bi bi-fire text-primary"></i>{{ __('challenge.personal_challenge_versus') }}
            </h2>

            <a href="{{ route('me.challenge.create') }}"
               class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow"
               style="background: linear-gradient(135deg, #7c3aed, #ef4444);">
                <div class="absolute -end-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-plus-lg text-2xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ __('challenge.personal_challenge_challenge_someone') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5">{{ __('challenge.personal_challenge_invite_subtitle') }}</p>
                    </div>
                    <i class="bi bi-chevron-right text-white/80"></i>
                </div>
            </a>

            @if($incoming->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between mb-2.5 px-1">
                        <h3 class="text-xs font-black text-foreground flex items-center gap-2">
                            <i class="bi bi-envelope-paper-heart text-primary"></i> {{ __('challenge.personal_challenge_invitations') }}
                        </h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-600 animate-pulse">{{ $incoming->count() }} {{ __('challenge.personal_challenge_new') }}</span>
                    </div>
                    <div class="space-y-3">
                        @foreach($incoming as $d)
                            @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'incoming'])
                        @endforeach
                    </div>
                </div>
            @endif

            @if($activeDuels->isNotEmpty())
                <div>
                    <h3 class="text-xs font-black text-foreground flex items-center gap-2 mb-2.5 px-1">
                        <i class="bi bi-lightning-charge-fill text-primary"></i> {{ __('challenge.personal_challenge_live_duels') }}
                    </h3>
                    <div class="space-y-3">
                        @foreach($activeDuels as $d)
                            @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'active'])
                        @endforeach
                    </div>
                </div>
            @endif

            @if($sent->isNotEmpty())
                <div>
                    <h3 class="text-xs font-black text-foreground flex items-center gap-2 mb-2.5 px-1">
                        <i class="bi bi-send text-primary"></i> {{ __('challenge.personal_challenge_awaiting_reply') }}
                    </h3>
                    <div class="space-y-3">
                        @foreach($sent as $d)
                            @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'sent'])
                        @endforeach
                    </div>
                </div>
            @endif

            @if($incoming->isEmpty() && $activeDuels->isEmpty() && $sent->isEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">
                    <i class="bi bi-fire text-3xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('challenge.personal_challenge_awaiting_reply') }}</p>
                </div>
            @endif
        </div>

        {{-- ============ SOLO column ============ --}}
        <div class="space-y-4">
            <h2 class="text-sm font-black text-foreground flex items-center gap-2 px-1">
                <i class="bi bi-person-arms-up text-primary"></i>{{ __('challenge.personal_challenge_solo') }}
            </h2>

            @if($feat)
            <a href="{{ route('me.challenge.show', $feat['id']) }}"
               class="block rounded-2xl overflow-hidden shadow-md hover:shadow-lg transition-shadow border border-gray-100 text-white relative"
               style="background: linear-gradient(135deg, {{ $feat['color'] }}, {{ $feat['color'] }}cc);">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative p-5">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                            <i class="bi bi-fire"></i> {{ $feat['streak'] }} {{ __('challenge.personal_challenge_day_streak') }}
                        </span>
                        <span class="text-[11px] font-semibold text-white/85">{{ $feat['days_left'] }}{{ __('challenge.personal_challenge_d_left') }}</span>
                    </div>
                    <h2 class="text-xl font-black mt-3 leading-tight">{{ $feat['title'] }}</h2>
                    <p class="text-sm text-white/85 mt-1 flex items-center gap-1.5">
                        <i class="bi {{ $feat['icon'] }} text-xs"></i>{{ $feat['tag'] }} · {{ $feat['points'] }} {{ __('challenge.personal_challenge_pts') }}
                    </p>
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-[11px] text-white/80 mb-1.5">
                            <span>{{ number_format($feat['current']) }}{{ $feat['unit'] }} / {{ number_format($feat['goal']) }}{{ $feat['unit'] }} {{ $feat['metric'] }}</span>
                            <span>{{ $feat['progress'] }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/25 overflow-hidden">
                            <div class="h-full rounded-full bg-white" style="width: {{ $feat['progress'] }}%"></div>
                        </div>
                    </div>
                    <span class="mt-4 w-full py-2.5 rounded-xl bg-white text-foreground font-bold text-sm flex items-center justify-center gap-2">
                        <i class="bi bi-graph-up-arrow"></i> {{ __('challenge.personal_challenge_view_progress') }}
                    </span>
                </div>
            </a>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex">
                @foreach(['active'=>__('challenge.seg_active'), 'upcoming'=>__('challenge.seg_upcoming'), 'completed'=>__('challenge.seg_completed')] as $key=>$label)
                    <button type="button" @click="seg='{{ $key }}'"
                            class="flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                            :class="seg==='{{ $key }}' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">{{ $label }}</button>
                @endforeach
            </div>

            <div class="space-y-3">
                @foreach($list as $c)
                    <a href="{{ route('me.challenge.show', $c['id']) }}"
                       x-show="seg === '{{ $c['status'] }}'" x-transition
                       class="block bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-primary/30 transition-colors p-3.5">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0"
                                 style="background: linear-gradient(160deg, {{ $c['color'] }}, {{ $c['color'] }}d0);">
                                <i class="bi {{ $c['icon'] }} text-xl"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" style="background: {{ $c['color'] }}1a; color: {{ $c['color'] }};">{{ $c['tag'] }}</span>
                                    @if($c['status'] === 'active')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground">{{ $c['days_left'] }}{{ __('challenge.personal_challenge_d_left') }}</span>
                                    @elseif($c['status'] === 'upcoming')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-600">{{ __('challenge.personal_challenge_upcoming') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-50 text-green-600"><i class="bi bi-check2"></i> {{ __('challenge.personal_challenge_done') }}</span>
                                    @endif
                                </div>
                                <h3 class="font-bold text-foreground mt-1.5 truncate">{{ $c['title'] }}</h3>
                                <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5">
                                    <i class="bi bi-star-fill text-[10px] text-amber-400"></i>{{ $c['points'] }} {{ __('challenge.personal_challenge_pts') }}
                                    <span class="mx-1 text-gray-300">•</span>
                                    <i class="bi bi-people text-[11px]"></i>{{ $c['participants'] }}
                                </p>
                            </div>
                            <i class="bi bi-chevron-right text-gray-300 mt-1"></i>
                        </div>
                        @if($c['status'] !== 'upcoming')
                            <div class="mt-3">
                                <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                    <div class="h-full rounded-full" style="width: {{ $c['progress'] }}%; background: {{ $c['color'] }};"></div>
                                </div>
                            </div>
                        @endif
                    </a>
                @endforeach

                @foreach(['active','upcoming','completed'] as $st)
                    @if(collect($list)->where('status', $st)->isEmpty())
                        <div x-show="seg === '{{ $st }}'" x-transition class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                            <i class="bi bi-flag text-3xl text-gray-300"></i>
                            <p class="text-sm text-muted-foreground mt-3">{{ __('challenge.personal_challenge_no_challenges', ['status' => $st]) }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Live duel notifications (best-effort, over MQTT via realtime.js). --}}
    <script>
        (function () {
            if (window.__challengeRealtime) return;
            window.__challengeRealtime = true;
            window.addEventListener('realtime:challenges', function (e) {
                var d = (e && e.detail) || {};
                var map = {
                    'duel:new': (d.actor || '{{ __("challenge.personal_challenge_js_someone") }}') + '{{ __("challenge.personal_challenge_js_challenged") }}' + (d.discipline || ''),
                    'duel:accepted': (d.actor || '{{ __("challenge.personal_challenge_js_your_rival") }}') + '{{ __("challenge.personal_challenge_js_accepted") }}',
                    'duel:declined': (d.actor || '{{ __("challenge.personal_challenge_js_your_rival") }}') + '{{ __("challenge.personal_challenge_js_declined") }}',
                    'duel:result': (d.actor || '{{ __("challenge.personal_challenge_js_your_rival") }}') + '{{ __("challenge.personal_challenge_js_result") }}',
                };
                var msg = map[d.action];
                if (msg && window.showToast) window.showToast('info', msg);
            });
        })();
    </script>
</div>
@endsection
