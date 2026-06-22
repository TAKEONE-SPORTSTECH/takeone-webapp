@extends('layouts.personal-mobile')

@section('title', 'Schedule')

{{--
    Schedule — mobile training schedule. NOTE: currently rendered with curated
    DUMMY content ($weekDays, $sessions, $members from PersonalMobileController@schedule).
    Me/Family toggle, a horizontal week-day strip, and per-day session cards that
    link to the workout detail page. The real subscription-projection logic lives
    in scheduleLegacy(). Reuses the shared mobile motion vocabulary.
--}}
@php
    $sessionsByDay = $sessions->groupBy('day');
    $weekCount = $sessions->count();
    $doneCount = $sessions->where('status', 'done')->count();
    $totalMins = $sessions->sum(fn ($s) => (int) filter_var($s['duration'], FILTER_SANITIZE_NUMBER_INT));
    $hours = round($totalMins / 60, 1);
@endphp

@section('personal-content')
<div x-data="{ who: 'all', day: '{{ $todayKey }}' }" class="-mx-4 -mt-4 pb-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">This week</p>
                <h1 class="text-2xl font-black mt-0.5">Training Plan</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-calendar2-week-fill text-xl m-float"></i>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $weekCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Sessions</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $doneCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Done</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $hours }}h</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Volume</p>
            </div>
        </div>
    </header>

    {{-- ===== Me / Family toggle (overlaps hero) ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex">
            <button type="button" @click="who='all'"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2"
                    :class="who==='all' ? 'bg-primary text-white' : 'text-muted-foreground'">
                <i class="bi bi-people-fill"></i> Family
            </button>
            <button type="button" @click="who='me'"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2"
                    :class="who==='me' ? 'bg-primary text-white' : 'text-muted-foreground'">
                <i class="bi bi-person-fill"></i> Just me
            </button>
        </div>
    </div>

    {{-- ===== Family avatars ===== --}}
    <div class="px-4 mt-4 overflow-x-auto" x-show="who==='all'" x-transition>
        <div class="flex gap-2 w-max">
            @foreach($members as $m)
                <div class="flex items-center gap-2 rounded-full bg-white border border-gray-100 pl-1.5 pr-3 py-1.5 shadow-sm">
                    <div class="w-7 h-7 rounded-full grid place-items-center text-white text-[10px] font-bold" style="background: {{ $m['color'] }};">{{ $m['initials'] }}</div>
                    <span class="text-xs font-semibold text-foreground">{{ $m['name'] }}</span>
                    <span class="text-[10px] text-muted-foreground">{{ $sessions->where('who', $m['key'])->count() }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== Week-day strip ===== --}}
    <div class="px-4 mt-4">
        <div class="flex gap-1">
            @foreach($weekDays as $wd)
                @php $count = $sessionsByDay->get($wd['key'])?->count() ?? 0; @endphp
                <button type="button" @click="day='{{ $wd['key'] }}'"
                        class="m-press flex-1 min-w-0 flex flex-col items-center justify-start pt-2 pb-1.5 rounded-xl border transition-colors"
                        :class="day==='{{ $wd['key'] }}' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-100 text-foreground'">
                    <span class="text-[10px] uppercase tracking-wide leading-none"
                          :class="day==='{{ $wd['key'] }}' ? 'text-white/80' : 'text-muted-foreground'">{{ $wd['short'] }}</span>
                    <span class="text-lg font-black leading-none mt-1.5">{{ $wd['d'] }}</span>
                    {{-- dot row — fixed height so every day aligns --}}
                    <span class="mt-1.5 h-1 flex items-center justify-center gap-0.5">
                        @for($i = 0; $i < min($count, 3); $i++)
                            <span class="w-1 h-1 rounded-full" :class="day==='{{ $wd['key'] }}' ? 'bg-white' : 'bg-primary'"></span>
                        @endfor
                    </span>
                    {{-- today line — always reserved (mt-auto pins it to the bottom) so heights match --}}
                    <span class="mt-auto h-3 flex items-center text-[8px] font-bold leading-none">
                        @if($wd['isToday'])<span :class="day==='{{ $wd['key'] }}' ? 'text-white' : 'text-primary'">TODAY</span>@endif
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ===== Sessions for the selected day ===== --}}
    <div class="px-4 mt-5">
        @foreach($weekDays as $wd)
            @php $daySessions = $sessionsByDay->get($wd['key']) ?? collect(); @endphp
            <div x-show="day==='{{ $wd['key'] }}'" x-transition class="space-y-3">
                @if($daySessions->isEmpty())
                    <div class="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">
                        <div class="w-16 h-16 mx-auto rounded-3xl bg-accent text-primary grid place-items-center"><i class="bi bi-cup-hot text-2xl m-float"></i></div>
                        <p class="text-sm font-bold text-foreground mt-3">Rest day</p>
                        <p class="text-xs text-muted-foreground mt-1">No training scheduled. Recover well.</p>
                    </div>
                @else
                    @foreach($daySessions as $s)
                        @php $m = $members[$s['who']]; @endphp
                        <a href="{{ route('me.schedule.show', $s['id']) }}" data-shell-link data-route="me.schedule"
                           x-show="who==='all' || '{{ $s['who'] }}'==='me'" x-transition
                           class="block m-card m-press rounded-2xl overflow-hidden">
                            <div class="flex">
                                <div class="w-1.5 flex-shrink-0" style="background: {{ $s['color'] }};"></div>
                                <div class="flex-1 p-3.5">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0"
                                             style="background: linear-gradient(160deg, {{ $s['color'] }}, {{ $s['color'] }}d0);">
                                            <i class="bi {{ $s['icon'] }} text-xl"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-bold text-foreground">{{ $s['start'] }}</span>
                                                <span class="text-[10px] text-muted-foreground">· {{ $s['duration'] }}</span>
                                                @if($s['status'] === 'done')
                                                    <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600"><i class="bi bi-check2"></i> Done</span>
                                                @elseif($s['status'] === 'today')
                                                    <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary text-white">Today</span>
                                                @endif
                                            </div>
                                            <h3 class="font-bold text-foreground mt-0.5 truncate">{{ $s['title'] }}</h3>
                                            <p class="text-xs text-muted-foreground mt-0.5 truncate flex items-center gap-1.5">
                                                <i class="bi bi-geo-alt text-[11px]"></i>{{ $s['location'] }}
                                                <span class="text-gray-300">·</span>{{ $s['coach'] }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between mt-2.5">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-5 h-5 rounded-full grid place-items-center text-white text-[8px] font-bold" style="background: {{ $m['color'] }};">{{ $m['initials'] }}</span>
                                            <span class="text-[11px] font-medium text-muted-foreground">{{ $m['relation'] }}</span>
                                        </span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                              style="background: {{ $s['color'] }}1a; color: {{ $s['color'] }};">{{ $s['intensity'] }} intensity</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach

                    {{-- "just me" empty fallback for this day --}}
                    <div x-show="who==='me' && {{ $daySessions->where('who', 'me')->count() }}===0" x-transition
                         class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                        <i class="bi bi-cup-hot text-2xl text-gray-300 m-float"></i>
                        <p class="text-sm text-muted-foreground mt-2">No personal training this day.</p>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</div>
@endsection
