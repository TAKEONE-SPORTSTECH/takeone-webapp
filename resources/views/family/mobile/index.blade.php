@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('family.title'))

@section('content')
<div x-data="{ addOpen: false }" class="min-h-screen bg-background pb-16">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.profile') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('family.title') }}</p>
            <button type="button" @click="$dispatch('open-member-create-modal')"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="{{ __('family.add_member') }}">
                <i class="bi bi-person-plus text-xl"></i>
            </button>
        </div>
    </header>

    {{-- ===== Hero summary ===== --}}
    <div class="px-4 pt-4">
        <div class="m-hero relative overflow-hidden rounded-3xl p-5 text-white shadow-sm">
            <div class="relative z-10">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/80">{{ __('family.my_family') }}</p>
                <p class="mt-1 text-3xl font-extrabold leading-none" data-countup>{{ $dependents->count() }}</p>
                <p class="mt-1 text-sm text-white/85">{{ $dependents->count() === 1 ? __('family.member') : __('family.members') }} {{ __('family.under_your_care') }}</p>
            </div>
            <i class="bi bi-people-fill absolute -right-3 -bottom-3 text-[7rem] text-white/15 m-float"></i>
        </div>
    </div>

    {{-- ===== Members ===== --}}
    <div class="px-4 mt-5 space-y-3 mobile-stagger">

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
