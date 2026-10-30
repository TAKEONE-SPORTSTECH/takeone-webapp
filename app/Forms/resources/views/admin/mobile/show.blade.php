@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $form->title)

@section('content')
@php
    $ar = app()->getLocale() === 'ar';
    $title = $ar && ! empty($form->settings['title_ar']) ? $form->settings['title_ar'] : $form->title;
@endphp
@include('forms::admin.partials.style')
<div class="fa min-h-screen bg-background pb-16 px-4 pt-4" data-form="{{ $form->uuid }}"
     x-data="{ tab: (['results','decisions','responses','share'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'results'), go(t) { this.tab = t; history.replaceState(null, '', '#' + t); window.scrollTo({ top: 0, behavior: 'smooth' }); } }">

    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('admin.forms.index') }}" aria-label="{{ __('forms::admin.back') }}" title="{{ __('forms::admin.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white"><i class="bi bi-chevron-left rtl:rotate-180"></i></a>
            @include('forms::admin.partials.actions', ['btn' => 'm-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white', 'iconOnly' => true])
        </div>
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ui-checks-grid"></i> {{ __('forms::admin.type_'.$form->type) }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur num">{{ $stats['n'] }} {{ __('forms::admin.answers') }}</span>
                @unless($example)<span class="fa-live px-2.5 py-1 rounded-full bg-white/10"><i></i>{{ __('forms::admin.live') }}</span>@endunless
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $title }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $form->tenant?->club_name ?? __('forms::admin.platform') }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-4">
        <div class="fa-card grid grid-cols-4 gap-1" style="padding:5px;position:sticky;top:calc(8px + env(safe-area-inset-top));z-index:30">
            @foreach(['results' => 'bi-bar-chart-line', 'decisions' => 'bi-signpost-split', 'responses' => 'bi-people', 'share' => 'bi-qr-code'] as $t => $icon)
                <button type="button" @click="go('{{ $t }}')" class="m-press rounded-xl flex flex-col items-center gap-0.5 py-2 text-[11px] font-bold transition-colors"
                        :class="tab === '{{ $t }}' ? 'bg-primary text-white' : 'text-gray-500'">
                    <i class="bi {{ $icon }} text-base"></i>{{ __('forms::admin.tab_'.$t) }}</button>
            @endforeach
        </div>

        @if($canExample && ! $example && $realCount === 0)
            <a href="?example=1" class="fa-example" style="text-decoration:none"><i class="bi bi-magic"></i><span class="flex-1">{{ __('forms::admin.example_show') }}</span><i class="bi bi-chevron-right rtl:rotate-180"></i></a>
        @endif

        @include('forms::admin.partials.panels')
        <div x-show="tab === 'share'" x-cloak>@include('forms::admin.partials.share')</div>
    </div>
</div>
@endsection

@push('scripts')
@include('forms::admin.partials.live')
@endpush
