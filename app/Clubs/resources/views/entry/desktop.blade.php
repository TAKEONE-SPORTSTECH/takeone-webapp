{{-- /club — the manager's front door (desktop). Only reached with 2+ clubs, or as a
     super-admin; one club redirects straight to its dashboard. --}}
@extends('layouts.app')

@section('title', __('clubs::club.entry_title'))

@php
    $roleChip = [
        'owner' => ['bi-key-fill', __('clubs::club.entry_role_owner')],
        'admin' => ['bi-shield-check', __('clubs::club.entry_role_admin')],
        'chain' => ['bi-buildings', __('clubs::club.entry_role_chain')],
    ];
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">

    {{-- Hero band — Design Rule #6 (desktop). A top-level hub: shared m-hero mesh, no back control. --}}
    <div class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-sliders"></i> {{ __('clubs::club.entry_eyebrow') }}
                </span>
                @if($clubs->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-building"></i> {{ __('clubs::club.entry_count', ['count' => $clubs->count()]) }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('clubs::club.entry_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ __('clubs::club.entry_subtitle') }}</p>
        </div>
    </div>

    @if($clubs->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center mb-6">
            <i class="bi bi-building text-4xl text-gray-300"></i>
            <p class="font-bold text-foreground mt-3">{{ __('clubs::club.entry_empty_title') }}</p>
            <p class="text-sm text-muted-foreground mt-1">{{ __('clubs::club.entry_empty_desc') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($clubs as $club)
            @php [$roleIcon, $roleLabel] = $roleChip[$club['role']]; @endphp
            <a href="{{ route('admin.club.dashboard', $club['slug']) }}"
               class="group bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col no-underline hover:shadow-lg hover:border-primary transition-all">
                <div class="flex items-start justify-between gap-3">
                    {{-- Logo: bare transparent image on a sizing box (Design Rule #5) --}}
                    <span class="w-16 h-16 flex-shrink-0 grid place-items-center">
                        @if($club['logo'])
                            <img src="{{ file_url($club['logo']) }}" alt="" class="w-full h-full object-contain">
                        @else
                            <span class="w-14 h-14 rounded-2xl bg-accent grid place-items-center">
                                <i class="bi bi-building text-primary text-2xl"></i>
                            </span>
                        @endif
                    </span>
                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-accent text-primary">
                            <i class="bi {{ $roleIcon }}"></i> {{ $roleLabel }}
                        </span>
                        @if($club['pending'])
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700">
                                <i class="bi bi-hourglass-split"></i> {{ __('clubs::club.entry_pending') }}
                            </span>
                        @endif
                    </div>
                </div>

                <p class="text-lg font-bold text-foreground leading-tight mt-4">{{ $club['name'] }}</p>
                <p class="text-xs text-muted-foreground mt-1.5 flex items-center gap-1.5">
                    @if($club['country'])<x-flag :code="$club['country']" :h="10" />@endif
                    <i class="bi bi-people"></i>{{ __('clubs::club.entry_members', ['count' => number_format($club['members'])]) }}
                </p>

                <div class="mt-auto pt-5">
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
                        {{ __('clubs::club.entry_open') }}
                        <i class="bi bi-chevron-right rtl:rotate-180"></i>
                    </span>
                </div>
            </a>
        @endforeach

        @if($isSuperAdmin)
            <a href="{{ route('admin.platform.clubs') }}"
               class="group rounded-xl border-2 border-dashed border-border p-6 flex flex-col justify-center no-underline hover:border-primary hover:bg-accent transition-all">
                <span class="w-14 h-14 rounded-2xl bg-gray-900 text-white grid place-items-center">
                    <i class="bi bi-globe2 text-2xl"></i>
                </span>
                <p class="text-lg font-bold text-foreground leading-tight mt-4">{{ __('clubs::club.entry_all_clubs') }}</p>
                <p class="text-xs text-muted-foreground mt-1.5">{{ __('clubs::club.entry_all_clubs_hint') }}</p>
            </a>
        @endif
    </div>
</div>
@endsection
