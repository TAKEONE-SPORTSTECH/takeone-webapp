@extends('layouts.admin')

{{-- The map's runtime first: the component pushes its own @once, which can land after its create() call. --}}
@include('components.fields.support.location-map-runtime')

@section('title', $federation ? $federation->name : __('federations::messages.admin_create_title'))

@push('styles')
    @include('federations::admin.partials.styles')
@endpush

@section('admin-content')
@php
    $cfg = \App\Federations\Controllers\FederationAdminController::jsConfig(get_defined_vars(), false);
    $in = 'w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent';
    $lb = 'block text-sm font-medium text-gray-700 mb-1';
    $n = 0;
@endphp
<div class="fedx" x-data="fedAdminForm(@js($cfg))">

    <x-admin-hero :eyebrow="__('federations::messages.admin_nav')" icon="bi-bank2"
                  :title="$federation ? __('federations::messages.admin_edit') : __('federations::messages.admin_create_title')"
                  :subtitle="__('federations::messages.admin_subtitle')">
        <x-slot:actions>
            <a href="{{ route('admin.federations.index') }}" data-no-shell title="{{ __('federations::messages.admin_back') }}" aria-label="{{ __('federations::messages.admin_back') }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white hover:bg-white/25 transition-colors">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </x-slot:actions>
    </x-admin-hero>

    @if ($federation)
        @include('federations::admin.partials.nudge')
    @else
        @include('federations::admin.partials.quick-create')
    @endif

    {{-- The whole record: always for an existing federation, on request for a new one. --}}
    <div x-show="full" x-cloak>

    {{-- The three seals: what this record is ready for. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach (['open' => 'bi-door-open-fill', 'payments' => 'bi-bank', 'verified' => 'bi-patch-check-fill'] as $stage => $icon)
            <div class="fedx-seal fedx-card-in" style="animation-delay: {{ $loop->index * 80 }}ms" :class="stage('{{ $stage }}').complete && 'is-done'">
                <span class="fedx-seal-icon">
                    <svg viewBox="0 0 48 48" aria-hidden="true"><circle class="trk" cx="24" cy="24" r="22"/><circle class="arc" cx="24" cy="24" r="22" :style="arc('{{ $stage }}')"/></svg>
                    <i class="bi {{ $icon }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-bold text-gray-900 mb-0">{{ __('federations::messages.stage_'.$stage) }}</p>
                        <span class="text-xs font-semibold text-muted-foreground" x-text="stage('{{ $stage }}').done + '/' + stage('{{ $stage }}').total"></span>
                    </div>
                    <p class="text-xs text-muted-foreground mb-0 truncate" x-show="stage('{{ $stage }}').complete">{{ __('federations::messages.stage_'.$stage.'_hint') }}</p>
                    <p class="text-xs text-amber-600 mb-0 truncate" x-show="!stage('{{ $stage }}').complete" :title="missingText('{{ $stage }}')">
                        {{ __('federations::messages.stage_missing') }}: <span x-text="missingText('{{ $stage }}')"></span>
                    </p>
                </div>
                @unless ($loop->last)<span class="fedx-seal-link hidden md:block"></span>@endunless
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- Chapter rail --}}
        <aside class="lg:col-span-3 lg:sticky lg:top-4 space-y-4">
            {{-- Live identity: the federation as its dashboard will wear it. --}}
            <div class="rounded-2xl overflow-hidden shadow-sm text-white relative" :style="gradient()">
                <div class="absolute -right-10 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="relative p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-white/70 mb-3">{{ __('federations::messages.preview_hint') }}</p>
                    <div class="flex items-center gap-3">
                        <template x-if="logoPreview"><span class="fedx-crest"><img :src="logoPreview" alt=""></span></template>
                        <template x-if="!logoPreview"><span class="fedx-crest-fallback" x-text="initials()"></span></template>
                        <div class="min-w-0">
                            <p class="text-base font-black leading-tight mb-0" x-text="v.name || @js(__('federations::messages.f_name'))"></p>
                            <p class="text-xs text-white/80 mb-0 mt-1" x-text="v.short_name"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-4">
                        <template x-for="s in v.sports" :key="s">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20" x-text="s"></span>
                        </template>
                    </div>
                </div>
            </div>

            <nav class="bg-white rounded-xl shadow-sm border border-gray-100 p-2">
                @foreach ($sections as $key => [$icon, $required])
                    <button type="button" @click="reveal('{{ $key }}')"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-start hover:bg-muted/60 transition-colors">
                        <span class="fedx-chapter-no w-6">{{ sprintf('%02d', $loop->iteration) }}</span>
                        <span class="flex-1 text-sm font-medium text-gray-800 truncate">{{ __('federations::messages.sec_'.$key) }}</span>
                        <i class="bi bi-exclamation-circle-fill text-red-500 text-xs" x-show="sectionErr('{{ $key }}')" x-cloak></i>
                        <span class="fedx-dot" :class="sectionState('{{ $key }}')"></span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- The chapters --}}
        <form x-ref="form" class="fedx-main space-y-6" @submit.prevent="submit()" enctype="multipart/form-data" novalidate>
            @foreach ($sections as $key => [$icon, $required])
                <section id="sec-{{ $key }}" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden scroll-mt-4 fedx-card-in"
                         :class="sectionErr('{{ $key }}') && 'ring-2 ring-red-200'">
                    <header class="flex items-center gap-4 px-6 py-4 border-b border-gray-100">
                        <span class="w-11 h-11 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi {{ $icon }} text-lg"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="fedx-chapter-no mb-0">{{ sprintf('%02d', $loop->iteration) }}</p>
                            <h2 class="text-base font-bold text-gray-900 leading-tight mb-0">{{ __('federations::messages.sec_'.$key) }}</h2>
                            <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.sec_'.$key.'_sub') }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $required ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground' }}">
                            {{ $required ? __('federations::messages.admin_required') : __('federations::messages.admin_optional') }}
                        </span>
                    </header>
                    <div class="p-6">
                        @include('federations::admin.partials.sections.'.$key)
                    </div>
                </section>
            @endforeach

            {{-- Save bar --}}
            <div class="sticky bottom-4 z-10">
                <div class="bg-white/95 backdrop-blur rounded-xl shadow-lg border border-gray-100 px-4 py-3 flex items-center justify-between gap-4" style="padding-inline-end: 6rem">
                    <p class="text-xs text-muted-foreground mb-0 hidden sm:block">
                        <i class="bi bi-shield-lock me-1"></i>{{ __('federations::messages.doc_hint') }}
                    </p>
                    <button type="submit" :disabled="saving"
                            class="bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-60 flex items-center gap-2 ms-auto">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="saving ? @js(__('federations::messages.admin_saving')) : @js($federation ? __('federations::messages.admin_save') : __('federations::messages.admin_create'))"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    @if ($federation)
        @include('federations::admin.partials.danger', ['dangerClass' => 'mt-8'])
    @endif
    </div>{{-- /full --}}
</div>
@endsection

@push('scripts')
    @include('federations::admin.partials.script')
@endpush
