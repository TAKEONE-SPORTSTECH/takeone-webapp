{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('family.title'))

@section('personal-content')
<div x-data="{ addOpen: false }" class="-mx-4 -mt-4">

    @php
        $minorCount = $dependents->filter(fn ($r) => ! is_null(optional($r->dependent)->age) && $r->dependent->age < 18)->count();
        $adultCount = $dependents->count() - $minorCount;
    @endphp

    {{-- ===== Hero summary ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('family.title') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('family.my_family') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                {{-- Dispatched on window so the listener doesn't depend on this button's Alpine scope. --}}
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-member-create-modal'))"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('family.add_member') }}">
                    <i class="bi bi-person-plus text-xl"></i>
                </button>
                <a href="{{ route('me.family') }}"
                   class="m-press w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('nav.family_tree') }}">
                    <i class="bi bi-diagram-3 text-xl m-float"></i>
                </a>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" data-countup>{{ $dependents->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('family.members') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $adultCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('family.adults') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $minorCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('family.minors') }}</p>
            </div>
        </div>
    </header>

    {{-- ===== Members ===== --}}
    <div class="px-4 pt-5 relative z-10 space-y-3 mobile-stagger">

        @forelse($dependents as $relationship)
            @php
                $dependent = $relationship->dependent;
                $rel = $relationship->relationship_type;
                $relLabel = $rel === 'spouse' ? 'Spouse' : ucfirst(str_replace('_', ' ', (string) $rel));
                $age = $dependent->age;
                $avatar = $dependent->profile_picture
                    ? asset('storage/'.$dependent->profile_picture).'?v='.optional($dependent->updated_at)->timestamp
                    : null;
            @endphp
            <a href="{{ route('member.show', $dependent->uuid) }}"
               class="m-card m-press flex items-center gap-3.5 bg-white rounded-2xl p-3 shadow-sm border border-gray-100">
                <span class="w-14 h-14 rounded-2xl bg-muted flex items-center justify-center overflow-hidden flex-shrink-0 ring-1 ring-black/5">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="" class="w-14 h-14 object-cover">
                    @else
                        <i class="bi bi-person text-2xl text-muted-foreground"></i>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-[15px] text-foreground truncate">{{ $dependent->full_name }}</p>
                    <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-accent text-primary">{{ $relLabel }}</span>
                        @if($age !== null)
                            <span class="text-[11px] text-muted-foreground flex items-center gap-1"><i class="bi bi-cake2"></i>{{ $age }} {{ __('family.years') }}</span>
                        @endif
                    </div>
                </div>
                <i class="bi bi-chevron-right text-muted-foreground flex-shrink-0"></i>
            </a>
        @empty
            <div class="bg-white rounded-2xl px-6 py-14 text-center shadow-sm border border-gray-100">
                <i class="bi bi-people text-5xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm font-semibold text-foreground mt-4">{{ __('family.no_members_yet') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ __('family.empty_subtitle') }}</p>
            </div>
        @endforelse

        {{-- Add member --}}
        <button type="button" @click="$dispatch('open-member-create-modal')"
                class="m-press w-full flex items-center justify-center gap-2 bg-white rounded-2xl border-2 border-dashed border-primary/30 text-primary py-4 font-semibold hover:bg-accent/40 transition-colors">
            <i class="bi bi-plus-circle text-lg"></i> {{ __('family.add_family_member') }}
        </button>
    </div>

    {{-- Add Family Member — mobile-native bottom sheet (not the desktop 4-tab modal) --}}
    <x-member-create-sheet-mobile :formAction="route('family.store')" />
</div>
@endsection
