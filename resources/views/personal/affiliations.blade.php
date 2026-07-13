{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('nav.affiliations'))

@section('personal-content')
<div class="-mx-4 -mt-4">

    {{-- ===== Hero summary ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('nav.affiliations') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ $active->count() === 1 ? __('personal.active_club') : __('personal.active_clubs') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('clubs.explore') }}" class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('nav.explore_clubs') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </a>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-diagram-3 text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $active->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.active_clubs') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $left->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.aff_left') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $active->count() + $left->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.aff_total') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-5 mobile-stagger">

        {{-- ===== Active clubs ===== --}}
        <div>
            <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('personal.active_clubs') }}</p>
            @forelse($active as $a)
                @php
                    $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country)
                        ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug])
                        : null;
                    $tag = $clubUrl ? 'a' : 'div';
                @endphp
                <{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif class="block m-card {{ $clubUrl ? 'm-press' : '' }} bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5">
                    <div class="flex items-center gap-3">
                        <span class="w-12 h-12 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0">
                            @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-buildings text-muted-foreground text-lg"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground truncate">{{ $a->club_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ __('personal.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}@if($a->location) · {{ $a->location }}@endif</p>
                        </div>
                        @if($clubUrl)<i class="bi bi-chevron-right text-muted-foreground/50 shrink-0"></i>@endif
                        <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('personal.aff_active_badge') }}
                        </span>
                    </div>
                    @if($a->skillAcquisitions->count())
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            @foreach($a->skillAcquisitions->take(8) as $s)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $s->skill_name }}</span>
                            @endforeach
                        </div>
                    @endif
                </{{ $tag }}>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <i class="bi bi-diagram-3 text-2xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('personal.not_active_any') }}</p>
                </div>
            @endforelse
        </div>

        {{-- ===== History — clubs left ===== --}}
        @if($left->count())
            <div>
                <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('personal.clubs_you_left') }}</p>
                @foreach($left as $a)
                    @php
                        $span = ($a->start_date && $a->end_date) ? (int) $a->start_date->diffInMonths($a->end_date) : null;
                        $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country)
                            ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug])
                            : null;
                        $tag = $clubUrl ? 'a' : 'div';
                    @endphp
                    <{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif class="block m-card {{ $clubUrl ? 'm-press' : '' }} bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5 opacity-75">
                        <div class="flex items-center gap-3">
                            <span class="w-12 h-12 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0 grayscale">
                                @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-buildings text-muted-foreground text-lg"></i>@endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-foreground truncate">{{ $a->club_name }}</p>
                                <p class="text-xs text-muted-foreground truncate">
                                    {{ optional($a->start_date)->format('M Y') ?: '—' }} – {{ optional($a->end_date)->format('M Y') }}@if($span !== null) · {{ max(1, $span) }} {{ max(1, $span) === 1 ? __('personal.month_one') : __('personal.months_many') }}@endif
                                </p>
                            </div>
                            @if($clubUrl)<i class="bi bi-chevron-right text-muted-foreground/50 shrink-0"></i>@endif
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">{{ __('personal.aff_left') }}</span>
                        </div>
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif

        @if($active->isEmpty() && $left->isEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-14 text-center">
                <i class="bi bi-diagram-3 text-4xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm font-semibold text-foreground mt-3">{{ __('personal.no_affiliations') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ __('personal.no_affiliations_hint') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
