@extends('layouts.admin')

@section('admin-content')
@php
    $ar = app()->getLocale() === 'ar';
    $title = $ar && ! empty($form->settings['title_ar']) ? $form->settings['title_ar'] : $form->title;
@endphp
@include('forms::admin.partials.style')
<div class="fa space-y-5" data-form="{{ $form->uuid }}"
     x-data="{ tab: (['results','decisions','responses','share'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'results'), go(t) { this.tab = t; history.replaceState(null, '', '#' + t); } }">

    <x-admin-hero :eyebrow="__('forms::admin.eyebrow')" :title="$title" icon="bi-ui-checks-grid"
                  subtitle="{{ count($form->fields()) }} {{ __('forms::admin.questions') }} · {{ count($form->schema['pages'] ?? []) }} {{ __('forms::admin.pages') }} · {{ $form->tenant?->club_name ?? __('forms::admin.platform') }}">
        <x-slot:actions>
            <a href="{{ route('admin.forms.index') }}" data-shell-link class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white"><i class="bi bi-chevron-left rtl:rotate-180"></i>{{ __('forms::admin.back') }}</a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white num">{{ $stats['n'] }} {{ __('forms::admin.answers') }}</span>
            @unless($example)<span class="fa-live text-white px-3 py-1.5 rounded-full bg-white/10"><i></i>{{ __('forms::admin.live') }}</span>@endunless
        </x-slot:actions>
    </x-admin-hero>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div class="border-b border-gray-200 overflow-x-auto" style="flex:1;min-width:0">
            <nav class="nav -mb-px flex gap-8">
                @foreach(['results' => 'bi-bar-chart-line', 'decisions' => 'bi-signpost-split', 'responses' => 'bi-people', 'share' => 'bi-qr-code'] as $t => $icon)
                    <button type="button" @click="go('{{ $t }}')" class="border-b-2 py-3 font-medium text-sm whitespace-nowrap transition-colors inline-flex items-center gap-2"
                            :class="tab === '{{ $t }}' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                        <i class="bi {{ $icon }}"></i>{{ __('forms::admin.tab_'.$t) }}
                        @if($t === 'responses')<span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600 num">{{ count($responses) }}</span>@endif
                    </button>
                @endforeach
            </nav>
        </div>
        @include('forms::admin.partials.actions', ['btn' => 'fa-btn ghost'])
    </div>

    @if($canExample && ! $example && $realCount === 0)
        <div class="fa-example"><i class="bi bi-magic"></i><span class="flex-1">{{ __('forms::admin.no_results_body') }}</span>
            <a href="?example=1" class="fa-btn" style="padding:6px 10px">{{ __('forms::admin.example_show') }}</a></div>
    @endif

    @include('forms::admin.partials.panels')
    <div x-show="tab === 'share'" x-cloak>@include('forms::admin.partials.share')</div>
</div>
@endsection

@push('scripts')
@include('forms::admin.partials.live')
@endpush
