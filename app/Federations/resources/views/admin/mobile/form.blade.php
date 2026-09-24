@extends('layouts.app')

@section('hide-navbar', true)
{{-- The map's runtime first: the component pushes its own @once, which can land after its create() call. --}}
@include('components.fields.support.location-map-runtime')

@section('title', $federation ? $federation->name : __('federations::messages.admin_create_title'))

@section('content')
@include('federations::admin.partials.styles')
{{-- Inline, before the x-data it feeds: the mobile shell re-runs inline scripts on every swap. --}}
@include('federations::admin.partials.script')
@php
    $cfg = \App\Federations\Controllers\FederationAdminController::jsConfig(get_defined_vars(), true);
    $in = 'w-full px-3 py-3 border border-gray-200 rounded-xl text-[15px] bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent';
    $lb = 'block text-sm font-medium text-gray-700 mb-1';
@endphp
<div class="fedx min-h-screen bg-background px-4 pt-4" style="padding-bottom: calc(6rem + env(safe-area-inset-bottom))" x-data="fedAdminForm(@js($cfg))">

    {{-- ============================== HUB ============================== --}}
    <div x-show="!panel">
        <header class="-mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden" :style="gradient()">
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
            <div class="flex items-center justify-between relative z-50">
                <a href="{{ route('admin.federations.index') }}" aria-label="{{ __('federations::messages.admin_back') }}" title="{{ __('federations::messages.admin_back') }}"
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white m-press">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </div>
            <div class="relative z-10 mt-6 flex items-end gap-3">
                <template x-if="logoPreview"><span class="fedx-crest" style="width:3.5rem;height:3.5rem"><img :src="logoPreview" alt=""></span></template>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                            <i class="bi bi-bank2"></i> <span x-text="v.short_name || @js(__('federations::messages.admin_nav'))"></span>
                        </span>
                    </div>
                    <h1 class="text-2xl font-black mt-3 leading-tight" x-text="v.name || @js(__('federations::messages.admin_create_title'))"></h1>
                    @if ($values['owner_name'])
                        <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-person"></i>{{ $values['owner_name'] }}</p>
                    @endif
                </div>
            </div>
        </header>

        <div class="-mt-10 relative z-10 space-y-4 mobile-stagger">
            @if ($federation)
                @include('federations::admin.partials.nudge')
            @else
                @include('federations::admin.partials.quick-create')
            @endif

            <div x-show="full" x-cloak class="space-y-4">
            {{-- Seals --}}
            <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['open' => 'bi-door-open-fill', 'payments' => 'bi-bank', 'verified' => 'bi-patch-check-fill'] as $stage => $icon)
                        <div class="text-center" :class="stage('{{ $stage }}').complete && 'is-done'">
                            <span class="fedx-seal-icon mx-auto" :style="stage('{{ $stage }}').complete ? 'background:hsl(250 65% 65%);color:#fff' : ''">
                                <svg viewBox="0 0 48 48" aria-hidden="true"><circle class="trk" cx="24" cy="24" r="22"/><circle class="arc" cx="24" cy="24" r="22" :style="arc('{{ $stage }}')"/></svg>
                                <i class="bi {{ $icon }}"></i>
                            </span>
                            <p class="text-[11px] font-bold text-gray-900 mt-2 mb-0 leading-tight">{{ __('federations::messages.stage_'.$stage) }}</p>
                            <p class="text-[10px] text-muted-foreground mb-0" x-text="stage('{{ $stage }}').done + '/' + stage('{{ $stage }}').total"></p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Hub rows --}}
            <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
                @foreach ($sections as $key => [$icon, $required])
                    <button type="button" @click="go('{{ $key }}')" class="m-press w-full flex items-center gap-3 px-4 py-3.5 text-start">
                        <span class="w-10 h-10 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi {{ $icon }}"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-semibold text-gray-900 leading-tight">{{ __('federations::messages.sec_'.$key) }}</span>
                            <span class="block text-xs text-muted-foreground truncate mt-0.5">{{ __('federations::messages.sec_'.$key.'_sub') }}</span>
                        </span>
                        <i class="bi bi-exclamation-circle-fill text-red-500 text-sm" x-show="sectionErr('{{ $key }}')" x-cloak></i>
                        <span class="fedx-dot" :class="sectionState('{{ $key }}')"></span>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300"></i>
                    </button>
                @endforeach
            </div>

            <button type="button" @click="submit()" :disabled="saving"
                    class="m-press w-full bg-primary text-white py-3.5 rounded-xl font-semibold text-[15px] disabled:opacity-60 flex items-center justify-center gap-2">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                <span x-text="saving ? @js(__('federations::messages.admin_saving')) : @js($federation ? __('federations::messages.admin_save') : __('federations::messages.admin_create'))"></span>
            </button>
            @if ($federation)
                @include('federations::admin.partials.danger', ['dangerClass' => 'mt-2'])
            @endif
            </div>{{-- /full --}}
        </div>
    </div>

    {{-- ============================== PANELS ============================== --}}
    {{-- One form around every panel: hidden panels still post, so one save sends it all. --}}
    <form x-ref="form" @submit.prevent="submit()" enctype="multipart/form-data" novalidate>
        @foreach ($sections as $key => [$icon, $required])
            <section x-show="panel === '{{ $key }}'" x-cloak class="m-panel-in -mx-4 -mt-4">
                <div class="sticky top-0 z-30 bg-white border-b border-gray-100 flex items-center gap-2 px-3 h-14" style="top: env(safe-area-inset-top, 0px)">
                    <button type="button" @click="back()" class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                        <i class="bi bi-chevron-left rtl:rotate-180 text-xl"></i>
                    </button>
                    <p class="flex-1 min-w-0 text-base font-bold text-gray-900 truncate mb-0">{{ __('federations::messages.sec_'.$key) }}</p>
                    <button type="button" @click="submit()" :disabled="saving" class="m-press px-4 h-9 rounded-full bg-primary text-white text-sm font-semibold disabled:opacity-60">
                        {{ __('federations::messages.m_save') }}
                    </button>
                </div>
                <div class="px-4 pt-4 space-y-4">
                    <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.sec_'.$key.'_sub') }}
                        · <span class="{{ $required ? 'text-primary font-semibold' : '' }}">{{ $required ? __('federations::messages.admin_required') : __('federations::messages.admin_optional') }}</span></p>
                    @include('federations::admin.partials.sections.'.$key)
                    <button type="button" @click="submit().then(() => { if (!Object.keys(errors).length) back(); })" :disabled="saving"
                            class="m-press w-full bg-primary text-white py-3.5 rounded-xl font-semibold text-[15px] disabled:opacity-60 mt-2">
                        {{ __('federations::messages.m_done') }}
                    </button>
                </div>
            </section>
        @endforeach
    </form>
</div>
@endsection
