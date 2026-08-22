@extends('layouts.app')

@section('title', __('personal.personal_event_bracket_title'))

{{--
    Tournament brackets — desktop.

    The draw is the page: a full-width zoomable board (drag to pan, pinch or
    scroll to zoom — the same gestures as the family tree), with the readable
    detail of the same bouts underneath. Organisers get an Arrange mode on the
    board itself, so setting the first-round matchups is direct manipulation
    rather than a form describing the bracket.

    Device-split counterpart: personal/mobile/event-bracket.blade.php
--}}

@php
    $color = $e['color'];
    $decided = collect($categories)->filter(fn ($c) => !empty($c['podium']));
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6"
     x-data="{ cat: '{{ $initialCategory ?? (collect($categories)->first()['key'] ?? '') }}', busy: false,
        // Server-side auto-draw: (re)builds every division's bracket + numbers.
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
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('personal.personal_event_bracket_could_not_save') }}');
                window.showToast('success', data.message);
                // The board re-fetches itself; no reload.
                window.BracketBoard && window.BracketBoard.reload();
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
     }">

    @include('partials.personal-desktop-subnav')

    <a href="{{ route('me.events.show', $e['key']) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-primary transition-colors mb-4">
        <i class="bi bi-arrow-left rtl:rotate-180"></i> {{ $e['title'] }}
    </a>

    {{-- ===== Header band ===== --}}
    <div class="rounded-2xl overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full bg-white/10"></div>
        <div class="absolute right-10 bottom-6 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative p-6 sm:p-7 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-white/70 mb-1">
                    {{ __('personal.event_show_brackets_draws') }}
                </p>
                <h1 class="text-2xl font-black leading-tight">{{ $e['title'] }}</h1>
                <p class="text-sm text-white/80 mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="bi bi-diagram-3 bracket-icon"></i>
                        {{ count($categories) }} {{ __('personal.personal_event_bracket_divisions') }}
                    </span>
                    @if($e['started'] ?? false)
                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-lock-fill"></i> {{ __('events.bracket_locked') }}</span>
                    @endif
                </p>
            </div>

            {{-- No draw controls here. This page SHOWS the draw; arranging it and
                 re-cutting it are organiser work and live in the event console
                 (/manage → Draw and brackets), which opens the full-screen board.
                 A visitor came to read the bracket, not to run it. --}}
        </div>
    </div>

    {{-- ===== The board =====
         `bare`: the draw IS the page here, so the board runs edge to edge with
         no card chrome. The division switcher above it keeps the page gutters. --}}
    <x-tournament-bracket
        id="event-bracket"
        :data-url="route('me.events.bracket.data', $e['key'])"
        :event-uuid="$e['key']"
        {{-- Read-only board: no arrange mode, and no arrange/clear endpoints
             handed to the client at all. The console owns rearranging. --}}
        :can-arrange="false"
        {{-- Open on the division the link asked for (a bout's "View draw"),
             else the first — the board keeps its own switcher here. --}}
        :initial-division="collect($categories)->firstWhere('key', $initialCategory ?? null)['id'] ?? null"
        :my-competitor-ids="$myCompetitorIds ?? []"
        height="68vh"
        bare bleed />

    @if(count($categories))
        {{-- ===== Detail: the same bouts, readable, plus podium & entrants ===== --}}
        <div class="mt-8">
            <div class="border-b border-gray-200 mb-5">
                <nav class="-mb-px flex gap-6 overflow-x-auto">
                    @foreach($categories as $c)
                        <button type="button" @click="cat='{{ $c['key'] }}'"
                                class="border-b-2 pb-2.5 font-medium text-sm whitespace-nowrap transition-colors"
                                :class="cat==='{{ $c['key'] }}'
                                    ? 'border-purple-500 text-purple-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                            {{ $c['name'] }}
                            <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600">
                                {{ $c['joined'] }}
                            </span>
                        </button>
                    @endforeach
                </nav>
            </div>

            @foreach($categories as $c)
                <div x-show="cat==='{{ $c['key'] }}'" x-transition
                     class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- Bouts by round --}}
                    <div class="lg:col-span-2 space-y-4">
                        @forelse($c['rounds'] as $round)
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                                        <i class="bi bi-diagram-2 text-primary"></i> {{ $round['name'] }}
                                    </h3>
                                    <span class="text-xs text-muted-foreground">
                                        {{ count($round['matches']) }}
                                        {{ count($round['matches']) === 1 ? __('personal.personal_event_bracket_bout') : __('personal.personal_event_bracket_bouts') }}
                                    </span>
                                </div>

                                <div class="space-y-2.5">
                                    @foreach($round['matches'] as $m)
                                        <div class="rounded-xl border border-gray-100 overflow-hidden
                                                    {{ $m['status'] === 'live' ? 'ring-2 ring-amber-400/50' : '' }}">
                                            <div class="flex items-center gap-2 px-3 py-1.5 bg-muted/50 text-[11px] font-semibold text-muted-foreground">
                                                @if($m['no'])<span>#{{ $m['no'] }}</span>@endif
                                                @if($m['court'])<span>· {{ $m['court'] }}</span>@endif
                                                @if($m['time'])<span>· {{ $m['time'] }}</span>@endif
                                                @if($m['status'] === 'live')
                                                    <span class="ms-auto inline-flex items-center gap-1 text-amber-600">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        {{ __('personal.personal_event_bracket_live') }}
                                                    </span>
                                                @endif
                                            </div>
                                            @foreach(['a', 'b'] as $side)
                                                <div class="flex items-center gap-3 px-3 py-2 text-sm
                                                            {{ $m['winner'] === $side ? 'bg-primary/5 font-bold text-foreground' : 'text-muted-foreground' }}
                                                            {{ $side === 'b' ? 'border-t border-gray-100' : '' }}">
                                                    @if($m['winner'] === $side)
                                                        <i class="bi bi-caret-right-fill text-primary text-xs"></i>
                                                    @else
                                                        <span class="w-3"></span>
                                                    @endif
                                                    <span class="flex-1 truncate">
                                                        {{ $m[$side]['name'] ?: __('events.bracket_tbd') }}
                                                    </span>
                                                    @if($m[$side]['provisional'] ?? false)
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"
                                                              title="{{ __('events.bracket_legend_provisional') }}"></span>
                                                    @endif
                                                    <span class="font-mono text-xs">{{ $m[$side]['score'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                                <i class="bi bi-diagram-3 bracket-icon text-3xl text-muted-foreground/60"></i>
                                <p class="text-sm text-muted-foreground mt-2">{{ __('events.bracket_no_draw') }}</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Podium + entrants --}}
                    <div class="space-y-4">
                        @if(!empty($c['podium']))
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                                <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-4">
                                    <i class="bi bi-trophy-fill text-amber-500"></i>
                                    {{ __('personal.personal_event_bracket_podium') }}
                                </h3>
                                <div class="space-y-2">
                                    @foreach($c['podium'] as $p)
                                        <div class="flex items-center gap-3 p-2.5 rounded-lg bg-muted/40">
                                            <span class="text-lg leading-none">
                                                {{ ['1' => '🥇', '2' => '🥈', '3' => '🥉'][(string) ($p['place'] ?? '')] ?? '🏅' }}
                                            </span>
                                            <span class="text-sm font-semibold text-foreground truncate">{{ $p['name'] ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-1">
                                <i class="bi bi-people-fill text-primary"></i>
                                {{ __('events.bracket_bench') }}
                                <span class="ms-auto text-xs font-medium text-muted-foreground">{{ $c['joined'] }}</span>
                            </h3>
                            @if(($c['unpaid_count'] ?? 0) > 0)
                                <p class="text-[11px] text-amber-600 font-semibold mb-2">
                                    <i class="bi bi-exclamation-circle"></i>
                                    {{ $c['unpaid_count'] }} {{ __('events.bracket_legend_provisional') }}
                                </p>
                            @endif
                            <div class="mt-2 space-y-1 max-h-72 overflow-y-auto">
                                @forelse($c['roster'] ?? [] as $r)
                                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-muted/50 transition-colors">
                                        <span class="w-6 h-6 rounded-full bg-primary/10 text-primary grid place-items-center text-[10px] font-bold">
                                            {{ mb_substr($r['name'] ?? '?', 0, 1) }}
                                        </span>
                                        <span class="text-sm text-foreground truncate">{{ $r['name'] ?? '' }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-muted-foreground py-2">{{ __('personal.personal_event_bracket_no_entrants') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
