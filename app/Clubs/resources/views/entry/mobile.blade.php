{{-- /club — the manager's front door (mobile). Only reached with 2+ clubs, or as a
     super-admin; one club redirects straight to its dashboard. --}}
@extends('members::layouts.personal-mobile')

@section('title', __('clubs::club.entry_title'))

@php
    $roleChip = [
        'owner' => ['bi-key-fill', __('clubs::club.entry_role_owner')],
        'admin' => ['bi-shield-check', __('clubs::club.entry_role_admin')],
        'chain' => ['bi-buildings', __('clubs::club.entry_role_chain')],
    ];
@endphp

@section('personal-content')

{{-- Hero band — Design Rule #6. A top-level hub: shared m-hero mesh, no back control. --}}
<header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden">
    <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
    <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

    <div class="relative z-10 mt-2">
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
</header>

<div class="-mt-10 relative z-10 space-y-3 pb-6">

    @if($clubs->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-building text-3xl text-gray-300 m-float inline-block"></i>
            <p class="font-bold text-foreground mt-3">{{ __('clubs::club.entry_empty_title') }}</p>
            <p class="text-sm text-muted-foreground mt-1">{{ __('clubs::club.entry_empty_desc') }}</p>
        </div>
    @endif

    <div class="mobile-stagger space-y-3">
        @foreach($clubs as $club)
            @php [$roleIcon, $roleLabel] = $roleChip[$club['role']]; @endphp
            <a href="{{ route('admin.club.dashboard', $club['slug']) }}"
               class="block m-card m-press p-4 relative overflow-hidden no-underline">
                <div class="flex items-center gap-3 relative">
                    {{-- Logo: bare transparent image on a sizing box (Design Rule #5) --}}
                    <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                        @if($club['logo'])
                            <img src="{{ file_url($club['logo']) }}" alt="" class="w-full h-full object-contain">
                        @else
                            <span class="w-12 h-12 rounded-2xl bg-accent grid place-items-center">
                                <i class="bi bi-building text-primary text-xl"></i>
                            </span>
                        @endif
                    </span>

                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-foreground leading-tight">{{ $club['name'] }}</p>
                        <div class="flex items-center gap-1.5 flex-wrap mt-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-accent text-primary">
                                <i class="bi {{ $roleIcon }}"></i> {{ $roleLabel }}
                            </span>
                            @if($club['pending'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700">
                                    <i class="bi bi-hourglass-split"></i> {{ __('clubs::club.entry_pending') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-muted-foreground mt-1.5 flex items-center gap-1.5">
                            @if($club['country'])<x-flag :code="$club['country']" :h="10" />@endif
                            <i class="bi bi-people"></i>{{ __('clubs::club.entry_members', ['count' => number_format($club['members'])]) }}
                        </p>
                    </div>

                    <span class="w-9 h-9 rounded-full bg-primary text-white grid place-items-center flex-shrink-0">
                        <i class="bi bi-chevron-right rtl:rotate-180"></i>
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    @if($isSuperAdmin)
        <a href="{{ route('admin.platform.clubs') }}" class="block m-card m-press p-4 no-underline">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-2xl bg-gray-900 text-white grid place-items-center flex-shrink-0">
                    <i class="bi bi-globe2 text-xl"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-foreground">{{ __('clubs::club.entry_all_clubs') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('clubs::club.entry_all_clubs_hint') }}</p>
                </div>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
            </div>
        </a>
    @endif
</div>
@endsection
