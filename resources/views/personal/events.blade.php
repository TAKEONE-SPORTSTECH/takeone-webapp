@extends('layouts.personal-mobile')

@section('title', 'Events')

{{--
    Events — mobile events hub. NOTE: currently rendered with curated DUMMY
    content (hero + featured spotlight + segmented filter + event cards) so the
    page looks alive before the real $events feed is wired in. Reuses the shared
    mobile motion vocabulary (m-hero, m-float, m-card, m-press, m-bar-fill) and
    design tokens. Swap $demo for the real $events collection when ready.
--}}
@php
    // $demo comes from PersonalEventController@index (shared shape with detail page).
    $demoList = array_values($demo);
    // Featured = the soonest UPCOMING event (never a finished one).
    $feat = collect($demoList)->firstWhere('ended', false);
    $upcomingCount = collect($demoList)->where('ended', false)->count();
    $pastCount = collect($demoList)->where('ended', true)->count();
    $joinedCount = collect($demoList)->where('joined', true)->where('ended', false)->count();
    $clubCount = collect($demoList)->pluck('club')->unique()->count();
@endphp

@section('personal-content')
<div x-data="{ seg: 'upcoming' }" class="-mx-4 -mt-4 pb-6">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Your clubs</p>
                <h1 class="text-2xl font-black mt-0.5">Events</h1>
            </div>
            <a href="{{ route('me.events.create') }}" data-shell-link data-route="me.events"
               class="m-press w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Create event">
                <i class="bi bi-plus-lg text-xl"></i>
            </a>
        </div>

        {{-- mini stats --}}
        <div class="flex gap-2 mt-5">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $upcomingCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Upcoming</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $joinedCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Joined</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $clubCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Clubs</p>
            </div>
        </div>
    </header>

    @if(empty($demoList))
        <div class="px-4 mt-6">
            <div class="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">
                <i class="bi bi-calendar-x text-4xl text-gray-300 m-float"></i>
                <p class="text-sm font-bold text-foreground mt-3">No events yet</p>
                <p class="text-xs text-muted-foreground mt-1">Create one for your club or check back soon.</p>
                <a href="{{ route('me.events.create') }}" data-shell-link data-route="me.events"
                   class="m-press inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold">
                    <i class="bi bi-plus-lg"></i> Create event
                </a>
            </div>
        </div>
    @else

    {{-- ===== Featured spotlight (soonest upcoming; hidden on Past tab) ===== --}}
    @if($feat)
    <div class="px-4 -mt-7 relative z-10" x-show="seg==='upcoming'" x-transition>
        <a href="{{ route('me.events.show', $feat['key']) }}" data-shell-link data-route="me.events"
           class="block m-press rounded-3xl overflow-hidden shadow-lg border border-gray-100 text-white relative"
           style="background: linear-gradient(135deg, {{ $feat['color'] }}, {{ $feat['color'] }}cc);">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="absolute -right-2 bottom-2 w-20 h-20 rounded-full bg-white/10"></div>
            <div class="relative p-5">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-stars"></i> Featured
                </span>
                <h2 class="text-xl font-black mt-3 leading-tight">{{ $feat['title'] }}</h2>
                <p class="text-sm text-white/85 mt-1 flex items-center gap-1.5">
                    <i class="bi bi-geo-alt-fill text-xs"></i>{{ $feat['location'] }}
                </p>
                <div class="flex items-center gap-3 mt-4 text-xs font-medium">
                    <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar3"></i>{{ $feat['wday'] }} {{ $feat['day'] }} {{ $feat['mon'] }}</span>
                    <span class="inline-flex items-center gap-1.5"><i class="bi bi-clock-fill"></i>{{ $feat['time'] }}</span>
                </div>

                {{-- capacity bar --}}
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] text-white/80 mb-1.5">
                        <span>{{ $feat['going'] }} going</span>
                        <span>{{ $feat['cap'] - $feat['going'] }} spots left</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-white/25 overflow-hidden">
                        <div class="m-bar-fill h-full rounded-full bg-white" style="width: {{ round($feat['going'] / $feat['cap'] * 100) }}%"></div>
                    </div>
                </div>

                <span class="m-press mt-4 w-full py-2.5 rounded-xl bg-white text-foreground font-bold text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-right-circle"></i> View details
                </span>
            </div>
        </a>
    </div>
    @endif

    {{-- ===== Segmented filter ===== --}}
    @php $segCounts = ['upcoming' => $upcomingCount, 'joined' => $joinedCount, 'past' => $pastCount]; @endphp
    <div class="px-4 mt-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex">
            @foreach(['upcoming'=>'Upcoming', 'joined'=>'Joined', 'past'=>'Past'] as $key=>$label)
                <button type="button" @click="seg='{{ $key }}'"
                        class="m-press flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                        :class="seg==='{{ $key }}' ? 'bg-primary text-white' : 'text-muted-foreground'">
                    {{ $label }}<span class="opacity-70">@if($segCounts[$key]) ({{ $segCounts[$key] }})@endif</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ===== Event list ===== --}}
    <div class="px-4 mt-4 mobile-stagger space-y-3">
        @foreach($demoList as $e)
            @php $isEnded = $e['ended'] ? 'true' : 'false'; $isJoined = $e['joined'] ? 'true' : 'false'; @endphp
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               x-show="(seg==='upcoming' && !{{ $isEnded }}) || (seg==='joined' && {{ $isJoined }} && !{{ $isEnded }}) || (seg==='past' && {{ $isEnded }})"
               x-transition
               class="block m-card m-press rounded-2xl p-3.5 flex items-start gap-3.5 {{ $e['ended'] ? 'opacity-75' : '' }}">
                {{-- date chip --}}
                <div class="flex flex-col items-center justify-center w-14 h-16 rounded-2xl text-white flex-shrink-0 relative"
                     style="background: linear-gradient(160deg, {{ $e['color'] }}, {{ $e['color'] }}d0);">
                    <span class="text-[9px] uppercase tracking-wide opacity-80">{{ $e['wday'] }}</span>
                    <span class="text-xl font-black leading-none">{{ $e['day'] }}</span>
                    <span class="text-[9px] uppercase tracking-wide opacity-80">{{ $e['mon'] }}</span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                              style="background: {{ $e['color'] }}1a; color: {{ $e['color'] }};">
                            <i class="bi {{ $e['icon'] }}"></i> {{ $e['tag'] }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground">{{ $e['level'] }}</span>
                        @if($e['ended'])
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500"><i class="bi bi-check2-circle"></i> Finished</span>
                        @endif
                    </div>
                    <h3 class="font-bold text-foreground mt-1.5 truncate">{{ $e['title'] }}</h3>
                    <p class="text-xs text-muted-foreground mt-0.5 truncate flex items-center gap-1.5">
                        <i class="bi bi-geo-alt text-[11px]"></i>{{ $e['location'] }}
                    </p>

                    {{-- fee / ticket pills --}}
                    @php $pFree = str_contains(strtolower($e['participant_fee']), 'free') || str_contains(strtolower($e['participant_fee']), 'qualified'); @endphp
                    <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $pFree ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600' }}">
                            <i class="bi bi-person-check"></i> {{ ($pFree || $e['ended']) ? $e['participant_fee'] : 'Join · '.$e['participant_fee'] }}
                        </span>
                        @if($e['spectator'])
                            @php $sFree = str_contains(strtolower($e['spectator']['fee']), 'free'); @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sFree ? 'bg-sky-50 text-sky-600' : 'bg-purple-50 text-primary' }}">
                                <i class="bi bi-ticket-perforated"></i> {{ $sFree ? 'Watch free' : 'Ticket · '.$e['spectator']['fee'] }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between mt-2">
                        <span class="inline-flex items-center -space-x-1.5">
                            @foreach(array_slice($e['participants'], 0, 4) as $i => $pp)
                                @php $ini = collect(explode(' ', $pp['name']))->map(fn($x)=>mb_substr($x,0,1))->take(2)->implode(''); @endphp
                                <span class="w-5 h-5 rounded-full grid place-items-center text-white text-[8px] font-bold border border-white" style="background: hsl({{ ($i*70)%360 }} 55% 60%);">{{ $ini }}</span>
                            @endforeach
                            <span class="text-[11px] text-muted-foreground pl-2.5">{{ $e['going'] }} joined</span>
                        </span>
                        <span class="m-press text-[11px] font-bold text-primary px-2.5 py-1 rounded-lg bg-accent inline-flex items-center gap-1">
                            View <i class="bi bi-chevron-right text-[9px]"></i>
                        </span>
                    </div>
                </div>
            </a>
        @endforeach

        {{-- per-segment empty states --}}
        @if($upcomingCount === 0)
            <div x-show="seg==='upcoming'" x-transition class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                <i class="bi bi-calendar-x text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">No upcoming events.</p>
            </div>
        @endif
        @if($joinedCount === 0)
            <div x-show="seg==='joined'" x-transition class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                <i class="bi bi-bookmark-x text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">You haven't joined any upcoming events.</p>
            </div>
        @endif
        @if($pastCount === 0)
            <div x-show="seg==='past'" x-transition class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                <i class="bi bi-clock-history text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">No past events yet.</p>
            </div>
        @endif
    </div>

    {{-- ===== Footer hint ===== --}}
    <p class="text-center text-[11px] text-muted-foreground mt-6 px-8 leading-relaxed" x-show="seg==='upcoming'">
        <i class="bi bi-info-circle"></i>
        New events from your clubs will appear here.
    </p>
    @endif

</div>
@endsection
