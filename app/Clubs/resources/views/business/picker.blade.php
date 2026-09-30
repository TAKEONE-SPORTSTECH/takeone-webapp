@extends('layouts.app')

@section('title', __('clubs::business.pick_title'))

@section('content')
{{-- /business for a super-admin: every approved business, one tap to its dashboard. --}}
<div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6">

    <header class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 px-5 sm:px-8 pt-6 pb-16 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi bi-buildings"></i> {{ __('clubs::business.chain') }}
            </span>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('clubs::business.pick_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ __('clubs::business.pick_sub') }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($businesses as $b)
            <a href="{{ route('business.dashboard', ['b' => $b->slug]) }}"
               class="m-card m-press group bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4 hover:shadow-md hover:border-primary/30 transition-all">
                <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                    @if($b->logo)
                        <img src="{{ file_url($b->logo) }}" alt="" class="w-full h-full object-contain">
                    @else
                        <span class="w-14 h-14 rounded-2xl bg-accent grid place-items-center"><i class="bi bi-buildings text-primary text-2xl"></i></span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-foreground">{{ $b->name }}</span>
                    <span class="block text-xs text-muted-foreground mt-0.5">{{ $b->owner?->full_name }}</span>
                    <span class="inline-flex items-center gap-1 mt-2 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-accent text-primary">
                        {{ $b->clubs_count }} {{ $b->clubs_count == 1 ? __('clubs::business.club_one') : __('clubs::business.club_many') }}
                    </span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground group-hover:text-primary transition-colors"></i>
            </a>
        @empty
            <div class="md:col-span-2 lg:col-span-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-accent grid place-items-center"><i class="bi bi-buildings text-primary text-2xl"></i></span>
                <p class="text-sm text-muted-foreground mt-3">{{ __('clubs::business.pick_none') }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
