@extends('layouts.personal-mobile')

@section('title', $c['title'])

{{--
    Challenge detail — mobile. DUMMY content from PersonalMobileController@challengeShow.
    Stylish, animated single-challenge page: gradient cover, progress ring/bar,
    join/leave + log-progress actions, rules, rewards, leaderboard. Reuses the
    shared mobile motion vocabulary (m-hero, m-card, m-press, m-bar-fill, m-float)
    and design tokens. Wire actions to real endpoints when a Challenge model lands.
--}}
@php
    $isCompleted = ($c['status'] ?? '') === 'completed';
    $isUpcoming  = ($c['status'] ?? '') === 'upcoming';
@endphp

@section('personal-content')
<div x-data="{
        joined: {{ ($c['joined'] ?? false) ? 'true' : 'false' }},
        progress: {{ $c['progress'] }},
        current: {{ $c['current'] }},
        goal: {{ $c['goal'] }},
        completed: {{ ($c['completed'] ?? false) ? 'true' : 'false' }},
        busy: false,
        async req(url) {
            if (this.busy) return null;
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __("challenge.personal_challenge_show_action_failed") }}');
                return data;
            } catch (e) {
                window.showToast('error', e.message);
                return null;
            } finally {
                this.busy = false;
            }
        },
        async toggleJoin() {
            const url = this.joined ? '{{ route('me.challenge.leave', $c['id']) }}' : '{{ route('me.challenge.join', $c['id']) }}';
            const d = await this.req(url);
            if (!d) return;
            this.joined = !!d.joined;
            window.showToast(this.joined ? 'success' : 'info', d.message);
        },
        async logProgress() {
            if (this.completed || !this.joined) return;
            const d = await this.req('{{ route('me.challenge.progress', $c['id']) }}');
            if (!d) return;
            this.current = d.current;
            this.progress = d.progress;
            this.completed = !!d.completed;
            window.showToast('success', d.message);
        }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-20 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $c['color'] }}, {{ $c['color'] }}b0);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute end-8 bottom-10 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-10">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.challenge') }}')"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-lg"></i>
            </button>
            <button type="button" @click="if(navigator.share){navigator.share({title:'{{ addslashes($c['title']) }}'}).catch(()=>{});}else{window.showToast('success','{{ __("challenge.personal_challenge_show_link_copied") }}')}"
                    class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('challenge.personal_challenge_show_share') }}">
                <i class="bi bi-share text-base"></i>
            </button>
        </div>

        <div class="relative z-10 mt-5 flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi {{ $c['icon'] }}"></i> {{ $c['tag'] }}
            </span>
            @if($isCompleted)
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur"><i class="bi bi-check2-circle"></i> {{ __('challenge.personal_challenge_show_completed') }}</span>
            @elseif($isUpcoming)
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/20 backdrop-blur"><i class="bi bi-hourglass-split"></i> {{ $c['starts_in'] ?? __('challenge.personal_challenge_show_upcoming') }}</span>
            @endif
        </div>
        <h1 class="text-2xl font-black mt-3 leading-tight relative z-10">{{ $c['title'] }}</h1>
        <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5 relative z-10">
            <i class="bi bi-building"></i>{{ $c['club'] }}
        </p>
    </header>

    {{-- ===== Progress ring card (overlaps cover) ===== --}}
    <div class="px-4 -mt-12 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-5">
            <div class="flex items-center gap-5">
                {{-- ring --}}
                <div class="relative w-24 h-24 flex-shrink-0">
                    <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="hsl(220 15% 92%)" stroke-width="3.2"></circle>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $c['color'] }}" stroke-width="3.2"
                                stroke-linecap="round" stroke-dasharray="100"
                                :stroke-dashoffset="100 - progress"
                                style="stroke-dashoffset: {{ 100 - $c['progress'] }}; transition: stroke-dashoffset .6s cubic-bezier(.22,.61,.36,1);"></circle>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center">
                        <div class="text-center">
                            <p class="text-xl font-black text-foreground leading-none"><span x-text="progress">{{ $c['progress'] }}</span>%</p>
                            <p class="text-[9px] text-muted-foreground uppercase tracking-wide mt-0.5">{{ __('shared.done') }}</p>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs text-muted-foreground">{{ __('challenge.personal_challenge_show_your_progress') }}</p>
                    <p class="text-lg font-black text-foreground leading-tight">
                        <span x-text="current.toLocaleString()">{{ number_format($c['current']) }}</span><span class="text-sm">{{ $c['unit'] }}</span>
                        <span class="text-sm font-medium text-muted-foreground">/ {{ number_format($c['goal']) }}{{ $c['unit'] }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ $c['metric'] }}</p>

                    <button type="button" @click="logProgress()" x-show="joined && !completed"
                            class="m-press mt-3 px-3 py-1.5 rounded-lg text-xs font-bold text-white inline-flex items-center gap-1.5"
                            style="background: {{ $c['color'] }};">
                        <i class="bi bi-plus-lg"></i> {{ __('challenge.personal_challenge_show_log_progress') }}
                    </button>
                    <p x-show="completed" class="mt-3 text-xs font-bold text-green-600 inline-flex items-center gap-1.5"><i class="bi bi-check2-circle"></i> {{ __('challenge.personal_challenge_show_goal_reached') }}</p>
                </div>
            </div>

            {{-- secondary stats --}}
            <div class="grid grid-cols-3 gap-2 mt-5 text-center">
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <p class="text-sm font-black text-foreground">{{ $c['points'] }}</p>
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('challenge.personal_challenge_show_points') }}</p>
                </div>
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <p class="text-sm font-black text-foreground">{{ $c['rank'] ? '#'.$c['rank'] : '—' }}</p>
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('challenge.personal_challenge_show_rank') }}</p>
                </div>
                <div class="rounded-xl bg-muted/60 py-2.5">
                    <p class="text-sm font-black text-foreground">{{ $isUpcoming ? $c['days_left'].'d' : ($isCompleted ? __('shared.done') : $c['days_left'].'d') }}</p>
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ $isUpcoming ? __('challenge.personal_challenge_show_to_start') : __('challenge.personal_challenge_show_left') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== About ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-info-circle text-primary"></i> {{ __('challenge.personal_challenge_show_about') }}</h2>
            <p class="text-sm text-muted-foreground leading-relaxed mt-2">{{ $c['about'] }}</p>
        </div>
    </div>

    {{-- ===== Rules ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> {{ __('challenge.personal_challenge_show_how_it_works') }}</h2>
            <ul class="mt-3 space-y-2.5">
                @foreach($c['rules'] as $rule)
                    <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                        <span class="w-5 h-5 rounded-full grid place-items-center flex-shrink-0 mt-0.5 text-white text-[10px]" style="background: {{ $c['color'] }};"><i class="bi bi-check-lg"></i></span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ===== Rewards ===== --}}
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-gift text-primary"></i> {{ __('challenge.personal_challenge_show_rewards') }}</h2>
            <div class="mt-3 space-y-2.5">
                @foreach($c['rewards'] as $r)
                    <div class="flex items-center gap-3 rounded-xl bg-muted/50 p-2.5">
                        <div class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0" style="background: {{ $c['color'] }}1a; color: {{ $c['color'] }};">
                            <i class="bi {{ $r['icon'] }} text-lg"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-foreground truncate">{{ $r['label'] }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ $r['sub'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Leaderboard ===== --}}
    @if(!empty($c['leaders']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-bar-chart-line text-primary"></i> {{ __('challenge.personal_challenge_show_leaderboard') }}</h2>
                    <span class="text-[11px] text-muted-foreground">{{ __('challenge.personal_challenge_show_participants_in', ['count' => $c['participants']]) }}</span>
                </div>
                <div class="mt-3 space-y-1.5">
                    @foreach($c['leaders'] as $i => $l)
                        <div class="flex items-center gap-3 rounded-xl px-2.5 py-2 {{ ($l['me'] ?? false) ? 'border' : '' }}"
                             style="{{ ($l['me'] ?? false) ? 'background: '.$c['color'].'0d; border-color: '.$c['color'].'33;' : '' }}">
                            <span class="w-6 text-center text-sm font-black {{ $i < 3 ? '' : 'text-muted-foreground' }}"
                                  style="{{ $i < 3 ? 'color: '.$c['color'].';' : '' }}">{{ $i + 1 }}</span>
                            @php $initials = collect(explode(' ', $l['name']))->map(fn($p)=>mb_substr($p,0,1))->take(2)->implode(''); @endphp
                            @if(!empty($l['avatar']))
                                <img src="{{ $l['avatar'] }}" alt="{{ $l['name'] }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full grid place-items-center text-white text-[10px] font-bold flex-shrink-0"
                                     style="background: hsl({{ ($i * 67) % 360 }} 55% 60%);">{{ $initials }}</div>
                            @endif
                            <p class="flex-1 text-sm font-semibold text-foreground truncate">{{ $l['name'] }}@if($l['me'] ?? false)<span class="text-[10px] font-bold ms-1" style="color: {{ $c['color'] }};">{{ __('challenge.personal_challenge_show_you') }}</span>@endif</p>
                            <div class="text-end">
                                <p class="text-xs font-bold text-foreground">{{ $l['val'] }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ __('challenge.personal_challenge_show_pts', ['count' => $l['pts']]) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Join / leave action ===== --}}
    @unless($isCompleted)
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4 flex items-center gap-3">
                <div class="leading-tight">
                    <p class="text-[10px] text-muted-foreground uppercase tracking-wide">{{ __('challenge.personal_challenge_show_reward') }}</p>
                    <p class="text-base font-black text-foreground">{{ __('challenge.personal_challenge_show_pts', ['count' => $c['points']]) }}</p>
                </div>
                <button type="button" @click="toggleJoin()"
                        class="m-press flex-1 py-3 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 transition-colors"
                        :class="joined ? 'bg-green-50 text-green-700 border border-green-200' : 'text-white'"
                        :style="joined ? '' : 'background: {{ $c['color'] }}'">
                    <i class="bi" :class="joined ? 'bi-check2-circle' : 'bi-plus-circle'"></i>
                    <span x-text="joined ? ('{{ $isUpcoming ? __('challenge.personal_challenge_show_joined_notify') : __('challenge.personal_challenge_show_joined') }}') : ('{{ $isUpcoming ? __('challenge.personal_challenge_show_join_when_starts') : __('challenge.personal_challenge_show_join_challenge') }}')"></span>
                </button>
            </div>
        </div>
    @else
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white m-float" style="background: {{ $c['color'] }};"><i class="bi bi-trophy-fill text-2xl"></i></div>
                <p class="text-sm font-bold text-foreground mt-3">{{ __('challenge.personal_challenge_show_challenge_completed') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ __('challenge.personal_challenge_show_you_earned_points', ['points' => $c['points']]) }}{{ $c['rank'] ? __('challenge.personal_challenge_show_finished_rank', ['rank' => $c['rank']]) : '' }}.</p>
            </div>
        </div>
    @endunless

</div>
@endsection
