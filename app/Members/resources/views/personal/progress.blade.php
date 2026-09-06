{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('nav.my_progress'))

@section('personal-content')
<div class="-mx-4 -mt-4">

    {{-- ===== Hero summary ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('nav.my_progress') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('personal.goals') }}</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-graph-up-arrow text-xl m-float"></i>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $goalStats['completed'] }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.achieved') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $goalStats['in_progress'] }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.in_progress') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $goalStats['pending'] }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.pending') }}</p>
            </div>
        </div>
    </header>

    {{-- Card-based mobile progress. --}}
    <div class="px-4 pt-5 relative z-10 space-y-3 mobile-stagger">

@forelse($goals as $g)
    @php
        $pct = (float) $g->progress_percentage;
        $isDone = $g->status === 'completed';
        $iconMap = ['dumbbell' => 'bi-dumbbell', 'clock' => 'bi-clock'];
        $icon = $iconMap[$g->icon_type] ?? 'bi-bullseye';
        $prio = $g->priority_level;
        $prioClass = $prio === 'high'
            ? 'bg-red-100 text-red-700'
            : ($prio === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600');
    @endphp
    <div class="m-card m-press p-4 mb-3">
        {{-- Header: icon tile + title/description + status chip --}}
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center {{ $isDone ? 'bg-green-100' : 'bg-accent' }}">
                <i class="bi {{ $isDone ? 'bi-check-circle-fill text-green-600' : $icon . ' text-primary' }} text-lg"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-foreground leading-tight truncate">{{ $g->title ?? $g->name ?? __('personal.goal') }}</h3>
                    <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $isDone ? 'bg-green-100 text-green-700' : 'bg-primary/10 text-primary' }}">
                        {{ ucfirst(str_replace('_', ' ', $g->status ?? '')) }}
                    </span>
                </div>
                @if($g->description)
                    <p class="text-xs text-muted-foreground mt-0.5 line-clamp-2">{{ $g->description }}</p>
                @endif
            </div>
        </div>

        {{-- Progress bar (only meaningful when a target is set) --}}
        @if($g->target_value > 0)
            <div class="mt-3.5">
                <div class="flex justify-between items-baseline mb-1.5">
                    <span class="text-[11px] text-muted-foreground">
                        {{ rtrim(rtrim(number_format($g->current_progress_value, 1), '0'), '.') }} /
                        {{ rtrim(rtrim(number_format($g->target_value, 1), '0'), '.') }}
                        @if($g->unit) {{ $g->unit }} @endif
                    </span>
                    <span class="text-xs font-bold {{ $isDone ? 'text-green-600' : 'text-primary' }}">{{ number_format($pct, 0) }}%</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                    <div class="h-full rounded-full m-bar-fill {{ $isDone ? 'bg-green-500' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        @endif

        {{-- Footer meta: priority + target date --}}
        <div class="mt-3.5 flex items-center gap-2 flex-wrap">
            @if($prio)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium {{ $prioClass }}">
                    <i class="bi bi-flag-fill text-[9px]"></i>{{ ucfirst($prio) }}
                </span>
            @endif
            @if($g->target_date)
                <span class="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                    <i class="bi bi-calendar-event"></i>{{ $g->target_date->format('M d, Y') }}
                </span>
            @endif
        </div>
    </div>
@empty
    <div class="m-card p-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-accent flex items-center justify-center mx-auto mb-4 m-float">
            <i class="bi bi-bullseye text-primary text-2xl"></i>
        </div>
        <p class="text-sm font-medium text-foreground">{{ __('personal.no_goals_yet') }}</p>
    </div>
@endforelse
    </div>
</div>
@endsection
