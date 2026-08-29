@extends($isMobile ? 'layouts.personal-mobile' : 'layouts.app')

@section('title', __('event-open_mat::messages.label'))

{{--
    The one screen /openmat can show instead of a mat.

    An open mat has to hang on a club, because `club_events.tenant_id` is NOT
    NULL and every event in the product has a host. That is the schema's
    business rather than the user's, so it is never asked about anywhere — but
    somebody who belongs to no club at all has nothing to hang a mat on, and
    this says so once and points at the fix. It is the same wall the personal
    event form already puts up, worded for this door.
--}}

@section($isMobile ? 'personal-content' : 'content')
@php $omColor = '#F97316'; @endphp

<div class="{{ $isMobile ? '-mx-4 -mt-4' : '' }}">
    <header class="{{ $isMobile ? 'm-hero px-5 pt-6 pb-16' : '-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-8 pt-6 pb-20' }} text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $omColor }}, {{ $omColor }}b0);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute end-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                <i class="bi bi-fire"></i>{{ __('event-open_mat::messages.label') }}
            </span>
            <h1 class="{{ $isMobile ? 'text-2xl' : 'text-3xl' }} font-black mt-3 leading-tight">{{ __('event-open_mat::messages.tagline') }}</h1>
        </div>
    </header>

    <div class="{{ $isMobile ? 'px-4' : '' }} -mt-10 relative z-10 pb-16">
        <section class="{{ $isMobile ? 'm-card rounded-3xl p-6' : 'bg-white rounded-xl shadow-sm border border-gray-100 p-10' }} text-center">
            <span class="w-14 h-14 rounded-2xl bg-muted grid place-items-center mx-auto text-muted-foreground">
                <i class="bi bi-building text-2xl"></i>
            </span>
            <p class="text-sm font-bold text-foreground mt-3">{{ __('event-open_mat::messages.no_club_title') }}</p>
            <p class="text-xs text-muted-foreground mt-1.5 leading-relaxed max-w-sm mx-auto">{{ __('event-open_mat::messages.no_club_desc') }}</p>
            <a href="{{ route('clubs.explore') }}" class="m-press inline-flex items-center gap-2 mt-5 h-11 px-5 rounded-2xl bg-primary text-white font-bold text-sm">
                <i class="bi bi-compass"></i>{{ __('personal.explore_clubs') }}
            </a>
        </section>
    </div>
</div>
@endsection
