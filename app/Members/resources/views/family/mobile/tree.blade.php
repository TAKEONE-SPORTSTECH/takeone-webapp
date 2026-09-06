@extends('layouts.personal-mobile')

@section('title', __('Family Tree'))

@section('personal-content')
@php
    $ftConfig = [
        'viewportId'   => 'ft-viewport',
        'dataUrl'      => route('me.family.data'),
        'addUrl'       => route('me.family.relative'),
        'respondUrl'   => route('me.family.respond'),
        'rootPersonId' => $rootPersonId,
        'csrf'         => csrf_token(),
    ];
@endphp

<div x-data="ftAddRelativeData({ addUrl: '{{ route('me.family.relative') }}', csrf: '{{ csrf_token() }}' })"
     @ft:add.window="openFor($event.detail)"
     class="-mx-4 -mt-4">

    <div class="relative">
        {{-- The tree canvas --}}
        <div id="ft-viewport" class="w-full" style="height: calc(100dvh - 8.5rem);"></div>

        {{-- Title / focus chip (top-left) --}}
        <div class="absolute top-3 left-3 flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/85 backdrop-blur shadow-sm border border-gray-100">
            <i class="bi bi-diagram-3 text-primary"></i>
            <span class="text-sm font-semibold text-gray-800">{{ __('Family Tree') }}</span>
        </div>

        {{-- Control stack (right) --}}
        <div class="absolute right-3 top-3 flex flex-col gap-2">
            <button type="button" @click="window.FamilyTree.zoomIn()"
                class="w-10 h-10 rounded-full bg-white shadow-md border border-gray-100 text-gray-700 flex items-center justify-center active:scale-95 transition">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" @click="window.FamilyTree.zoomOut()"
                class="w-10 h-10 rounded-full bg-white shadow-md border border-gray-100 text-gray-700 flex items-center justify-center active:scale-95 transition">
                <i class="bi bi-dash-lg"></i>
            </button>
            <button type="button" @click="window.FamilyTree.home()"
                class="w-10 h-10 rounded-full bg-white shadow-md border border-gray-100 text-gray-700 flex items-center justify-center active:scale-95 transition">
                <i class="bi bi-house-heart"></i>
            </button>
        </div>

        {{-- Hint (bottom, fades) --}}
        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-full bg-gray-900/70 text-white text-xs font-medium pointer-events-none"
             x-data="{ show: true }" x-init="setTimeout(() => show = false, 4200)" x-show="show" x-transition.opacity.duration.500ms>
            <i class="bi bi-hand-index-thumb mr-1"></i>{{ __('Tap a relative to explore · tap yourself to add') }}
        </div>
    </div>

    {{-- Add-relative bottom sheet (teleported so the shell transform can't clip it) --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="close()">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('Add a relative') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('Related to') }} <span x-text="focusName"></span></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    @include('members::family.partials.add-relative-fields')
                </div>

                {{-- Sticky footer --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="close()"
                        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="submit()" :disabled="submitting"
                        class="flex-1 py-3 rounded-xl bg-primary text-white font-semibold active:scale-[.98] transition disabled:opacity-60 flex items-center justify-center gap-2">
                        <span x-show="!submitting"><i class="bi bi-check-lg mr-1"></i>{{ __('Add') }}</span>
                        <span x-show="submitting" class="flex items-center gap-2">
                            <i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Saving…') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Manage-relationships bottom sheet (teleported so the shell transform can't clip it) --}}
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
                            <h3 class="text-lg font-black leading-tight">{{ __('Manage relationships') }}</h3>
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
                    @include('members::family.partials.manage-relative-fields')
                </div>
            </div>
        </div>
    </template>
    </div>
</div>

@include('members::family.partials.tree-runtime')

<script>
    (function () {
        window.FamilyTree.mount({!! json_encode($ftConfig) !!});
    })();
</script>
@endsection
