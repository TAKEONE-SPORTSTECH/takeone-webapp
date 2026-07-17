@extends('layouts.personal-mobile')

@section('title', __('nav.tab_challenge'))

{{--
    Challenge — mobile challenges hub. NOTE: currently rendered with curated
    DUMMY content ($challenges + $duels from PersonalMobileController@challenge).
    Two modes via the top toggle:
      • Solo  — club/self challenges (progress-based), filtered Active/Upcoming/Completed.
      • Versus — 1v1 duels (fight / athletic): incoming invitations (accept/decline),
                 active duels (live VS score), and sent invitations (pending).
    "Create challenge" → invite-a-challenger form. "History" → results page.
    Reuses the shared mobile motion vocabulary (m-hero, m-float, m-card, m-press, m-bar-fill).
--}}
@php
    $list  = array_values($challenges);
    $feat  = collect($list)->firstWhere('status', 'active') ?? ($list[0] ?? null);

    $duelList   = array_values($duels);
    $incoming   = collect($duelList)->where('status', 'invite_incoming')->values();
    $activeDuels= collect($duelList)->whereIn('status', ['active', 'reported'])->values();
    $sent       = collect($duelList)->where('status', 'invite_sent')->values();

    // Real stats.
    $wins   = collect($duelList)->where('status', 'completed')->where('result', 'win')->count();
    $losses = collect($duelList)->where('status', 'completed')->where('result', 'loss')->count();
    $record = "{$wins}W · {$losses}L";
    $totalPoints = collect($list)->where('completed', true)->sum('points')
                 + collect($duelList)->where('status', 'completed')->where('result', 'win')->sum('points_earned');
@endphp

@section('personal-content')
<div x-data="{ mode: 'versus', seg: 'active' }" class="-mx-4 -mt-4 pb-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('challenge.title') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('challenge.subtitle') }}</h1>
            </div>
            <a href="{{ route('me.challenge.history') }}" data-shell-link data-route="me.challenge"
               class="m-press px-3 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur flex items-center gap-1.5 text-xs font-semibold">
                <i class="bi bi-clock-history"></i> {{ __('challenge.personal_challenge_history') }}
            </a>
        </div>

        {{-- mini stats --}}
        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" data-countup="{{ $totalPoints }}">0</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_points') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $record }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_record') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $activeDuels->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('challenge.personal_challenge_duels') }}</p>
            </div>
        </div>
    </header>

    {{-- ===== Mode toggle (overlaps hero) ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex">
            <button type="button" @click="mode='versus'"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2"
                    :class="mode==='versus' ? 'bg-primary text-white' : 'text-muted-foreground'">
                <i class="bi bi-fire"></i> {{ __('challenge.personal_challenge_versus') }}
            </button>
            <button type="button" @click="mode='solo'"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2"
                    :class="mode==='solo' ? 'bg-primary text-white' : 'text-muted-foreground'">
                <i class="bi bi-person-arms-up"></i> {{ __('challenge.personal_challenge_solo') }}
            </button>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- VERSUS (1v1 duels)                                            --}}
    {{-- ============================================================= --}}
    <div x-show="mode==='versus'" x-transition class="mt-4">

        {{-- Create challenge CTA --}}
        <div class="px-4">
            <a href="{{ route('me.challenge.create') }}" data-shell-link data-route="me.challenge"
               class="block m-press rounded-3xl p-4 text-white relative overflow-hidden shadow-lg"
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
        </div>

        {{-- Incoming invitations --}}
        @if($incoming->isNotEmpty())
            <div class="px-4 mt-6">
                <div class="flex items-center justify-between mb-2.5">
                    <h2 class="text-sm font-black text-foreground flex items-center gap-2">
                        <i class="bi bi-envelope-paper-heart text-primary"></i> {{ __('challenge.personal_challenge_invitations') }}
                    </h2>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-600 animate-pulse">{{ $incoming->count() }} {{ __('challenge.personal_challenge_new') }}</span>
                </div>
                <div class="space-y-3">
                    @foreach($incoming as $d)
                        @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'incoming'])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Active duels --}}
        @if($activeDuels->isNotEmpty())
            <div class="px-4 mt-6">
                <h2 class="text-sm font-black text-foreground flex items-center gap-2 mb-2.5">
                    <i class="bi bi-lightning-charge-fill text-primary"></i> {{ __('challenge.personal_challenge_live_duels') }}
                </h2>
                <div class="space-y-3">
                    @foreach($activeDuels as $d)
                        @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'active'])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Sent invitations --}}
        @if($sent->isNotEmpty())
            <div class="px-4 mt-6">
                <h2 class="text-sm font-black text-foreground flex items-center gap-2 mb-2.5">
                    <i class="bi bi-send text-primary"></i> {{ __('challenge.personal_challenge_awaiting_reply') }}
                </h2>
                <div class="space-y-3">
                    @foreach($sent as $d)
                        @include('personal.partials.duel-card', ['d' => $d, 'variant' => 'sent'])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Results link --}}
        <div class="px-4 mt-6">
            <a href="{{ route('me.challenge.history') }}" data-shell-link data-route="me.challenge"
               class="m-press flex items-center justify-center gap-2 w-full py-3 rounded-2xl bg-white border border-gray-100 text-sm font-bold text-primary">
                <i class="bi bi-trophy"></i> {{ __('challenge.personal_challenge_see_history') }}
            </a>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- SOLO (club / self challenges)                                 --}}
    {{-- ============================================================= --}}
    <div x-show="mode==='solo'" x-transition class="mt-4">

        {{-- Featured active challenge --}}
        @if($feat)
        <div class="px-4">
            <a href="{{ route('me.challenge.show', $feat['id']) }}" data-shell-link data-route="me.challenge"
               class="block m-press rounded-3xl overflow-hidden shadow-lg border border-gray-100 text-white relative"
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
                            <div class="m-bar-fill h-full rounded-full bg-white" style="width: {{ $feat['progress'] }}%"></div>
                        </div>
                    </div>
                    <span class="m-press mt-4 w-full py-2.5 rounded-xl bg-white text-foreground font-bold text-sm flex items-center justify-center gap-2">
                        <i class="bi bi-graph-up-arrow"></i> {{ __('challenge.personal_challenge_view_progress') }}
                    </span>
                </div>
            </a>
        </div>
        @endif

        {{-- Sub-filter --}}
        <div class="px-4 mt-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex">
                @foreach(['active'=>__('challenge.seg_active'), 'upcoming'=>__('challenge.seg_upcoming'), 'completed'=>__('challenge.seg_completed')] as $key=>$label)
                    <button type="button" @click="seg='{{ $key }}'"
                            class="m-press flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                            :class="seg==='{{ $key }}' ? 'bg-primary text-white' : 'text-muted-foreground'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Solo list --}}
        <div class="px-4 mt-4 space-y-3">
            @foreach($list as $c)
                <a href="{{ route('me.challenge.show', $c['id']) }}" data-shell-link data-route="me.challenge"
                   x-show="seg === '{{ $c['status'] }}'" x-transition
                   class="block m-card m-press rounded-2xl p-3.5">
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
                                <div class="m-bar-fill h-full rounded-full" style="width: {{ $c['progress'] }}%; background: {{ $c['color'] }};"></div>
                            </div>
                        </div>
                    @endif
                </a>
            @endforeach

            @foreach(['active','upcoming','completed'] as $st)
                @if(collect($list)->where('status', $st)->isEmpty())
                    <div x-show="seg === '{{ $st }}'" x-transition class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                        <i class="bi bi-flag text-3xl text-gray-300 m-float"></i>
                        <p class="text-sm text-muted-foreground mt-3">{{ __('challenge.personal_challenge_no_challenges', ['status' => $st]) }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Live duel notifications (best-effort, over MQTT via realtime.js). The DB
         is the source of truth; this just nudges the user to refresh the hub. --}}
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
