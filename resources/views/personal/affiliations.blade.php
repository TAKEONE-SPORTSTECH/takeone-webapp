@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('nav.affiliations'))

@section('content')
<div class="min-h-screen bg-background pb-16">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.profile') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('nav.affiliations') }}</p>
        </div>
    </header>

    {{-- ===== Hero summary ===== --}}
    <div class="px-4 pt-4">
        <div class="m-hero relative overflow-hidden rounded-3xl p-5 text-white shadow-sm">
            <div class="relative z-10 flex items-end gap-5">
                <div>
                    <p class="text-3xl font-extrabold leading-none">{{ $active->count() }}</p>
                    <p class="text-[12px] text-white/85 mt-1">{{ $active->count() === 1 ? __('personal.active_club') : __('personal.active_clubs') }}</p>
                </div>
                @if($left->count())
                    <div class="pl-5 border-l border-white/25">
                        <p class="text-2xl font-bold leading-none text-white/90">{{ $left->count() }}</p>
                        <p class="text-[12px] text-white/75 mt-1">{{ __('personal.aff_left') }}</p>
                    </div>
                @endif
            </div>
            <i class="bi bi-diagram-3 absolute -right-4 -bottom-4 text-[7rem] text-white/15 m-float"></i>
        </div>
    </div>

    <div class="px-4 mt-5 space-y-5 mobile-stagger">

        {{-- ===== Active clubs ===== --}}
        <div>
            <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('personal.active_clubs') }}</p>
            @forelse($active as $a)
                <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5">
                    <div class="flex items-center gap-3">
                        <span class="w-12 h-12 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0">
                            @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-buildings text-muted-foreground text-lg"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground truncate">{{ $a->club_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ __('personal.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}@if($a->location) · {{ $a->location }}@endif</p>
                        </div>
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
                </div>
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
                    @php $span = ($a->start_date && $a->end_date) ? (int) $a->start_date->diffInMonths($a->end_date) : null; @endphp
                    <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5 opacity-75">
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
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">{{ __('personal.aff_left') }}</span>
                        </div>
                    </div>
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
