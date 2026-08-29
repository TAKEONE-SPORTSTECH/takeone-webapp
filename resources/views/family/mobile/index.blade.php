{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('family.title'))

{{-- Hoisted into the shared shell header (#shell-actions) instead of the page's own hero. --}}
@push('header-actions')
    {{-- Dispatched on window so the listener doesn't depend on this button's Alpine scope. --}}
    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-member-create-modal'))"
            class="m-press w-9 h-9 rounded-xl bg-black/5 dark:bg-white/10 grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('family.add_member') }}">
        <i class="bi bi-person-plus text-lg"></i>
    </button>
    <a href="{{ route('me.family') }}"
       class="m-press w-9 h-9 rounded-xl bg-black/5 dark:bg-white/10 grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('nav.family_tree') }}">
        <i class="bi bi-diagram-3 text-lg"></i>
    </a>
@endpush

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
            <div class="relative">
                <a href="{{ route('member.show', $dependent->uuid) }}"
                   class="m-card m-press flex items-center gap-3.5 bg-white rounded-2xl p-3 shadow-sm border border-gray-100">
                    <span class="w-14 h-14 rounded-2xl bg-muted flex items-center justify-center overflow-hidden flex-shrink-0 ring-1 ring-black/5">
                        @if($avatar)
                            <img src="{{ $avatar }}" alt="" class="w-[42px] h-14 object-cover">
                        @else
                            <i class="bi bi-person text-2xl text-muted-foreground"></i>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-[15px] text-foreground truncate pe-8">{{ $dependent->full_name }}</p>
                        <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-accent text-primary">{{ $relLabel }}</span>
                            @if($age !== null)
                                <span class="text-[11px] text-muted-foreground flex items-center gap-1"><i class="bi bi-cake2"></i>{{ $age }} {{ __('family.years') }}</span>
                            @endif
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted-foreground flex-shrink-0"></i>
                </a>
                @if(!empty($relationship->edges))
                    <button type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.dispatchEvent(new CustomEvent('ft:manage', { detail: {{ Illuminate\Support\Js::from(['personId' => $relationship->person_id, 'personName' => $dependent->full_name, 'edges' => $relationship->edges]) }} }));"
                            class="absolute top-2.5 end-2.5 w-8 h-8 rounded-full bg-white/90 backdrop-blur border border-gray-100 flex items-center justify-center text-muted-foreground active:scale-95 transition-transform z-10"
                            aria-label="{{ __('member.manage_relationships') }}">
                        <i class="bi bi-three-dots text-sm"></i>
                    </button>
                @endif
            </div>
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

    {{-- Add Family Member — chooser (New / Search Existing), then the matching sheet --}}
    <x-member-add-chooser-mobile />
    <x-member-create-sheet-mobile :formAction="route('family.store')" />
    <x-member-search-existing-sheet-mobile />

    {{-- Manage-relationships bottom sheet — remove a mistaken/duplicate family
         link directly from this list (same action + component the Family Tree uses). --}}
    <div x-data="ftManageData({ removeUrl: '{{ route('me.family.relative.remove') }}', csrf: '{{ csrf_token() }}' })"
         @ft:manage.window="openFor($event.detail)">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="close()">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-people-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('member.manage_relationships') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5"><span x-text="personName"></span></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    @include('family.partials.manage-relative-fields')
                </div>
            </div>
        </div>
    </template>
    </div>
</div>

@include('family.partials.tree-runtime')

<script>
    window.addEventListener('family-member-linked', function () {
        window.location.reload();
    });
    window.addEventListener('family-relative-removed', function () {
        window.location.reload();
    });
</script>
@endsection
