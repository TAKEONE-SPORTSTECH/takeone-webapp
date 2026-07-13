@extends('layouts.app')

@section('title', __('Family Tree'))

@section('content')
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"
     x-data="ftAddRelativeData({ addUrl: '{{ route('me.family.relative') }}', csrf: '{{ csrf_token() }}' })"
     @ft:add.window="openFor($event.detail)">

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-diagram-3 text-primary"></i>{{ __('Family Tree') }}
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                {{ __('Tap any relative to explore their branch — the tree reaches as far as your family goes.') }}
            </p>
        </div>
        <button type="button" @click="openFor({ focusId: {{ $rootPersonId }}, focusName: '{{ __('you') }}' })"
            class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium flex items-center gap-2">
            <i class="bi bi-person-plus"></i>{{ __('Add relative') }}
        </button>
    </div>

    {{-- Tree card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden relative">
        <div id="ft-viewport" class="w-full" style="height: calc(100vh - 16rem); min-height: 460px;"></div>

        {{-- Controls --}}
        <div class="absolute right-4 top-4 flex flex-col gap-2">
            <button type="button" @click="window.FamilyTree.zoomIn()"
                class="w-10 h-10 rounded-lg bg-white shadow border border-gray-100 text-gray-700 flex items-center justify-center hover:bg-accent transition">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" @click="window.FamilyTree.zoomOut()"
                class="w-10 h-10 rounded-lg bg-white shadow border border-gray-100 text-gray-700 flex items-center justify-center hover:bg-accent transition">
                <i class="bi bi-dash-lg"></i>
            </button>
            <button type="button" @click="window.FamilyTree.home()"
                class="w-10 h-10 rounded-lg bg-white shadow border border-gray-100 text-gray-700 flex items-center justify-center hover:bg-accent transition"
                title="{{ __('Back to you') }}">
                <i class="bi bi-house-heart"></i>
            </button>
        </div>
    </div>

    {{-- Add-relative modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" @keydown.escape.window="close()">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             class="relative w-full max-w-lg max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl">

            <div class="flex-shrink-0 px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">{{ __('Add a relative') }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ __('Related to') }} <span class="font-semibold text-primary" x-text="focusName"></span>
                    </p>
                </div>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                @include('family.partials.add-relative-fields')
            </div>

            <div class="flex-shrink-0 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" @click="close()"
                    class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition">
                    {{ __('Cancel') }}
                </button>
                <button type="button" @click="submit()" :disabled="submitting"
                    class="px-5 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary/90 transition disabled:opacity-60 flex items-center gap-2">
                    <span x-show="!submitting"><i class="bi bi-check-lg mr-1"></i>{{ __('Add relative') }}</span>
                    <span x-show="submitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Saving…') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@include('family.partials.tree-runtime')

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.FamilyTree.mount({!! json_encode($ftConfig) !!});
    });
</script>
@endpush
@endsection
