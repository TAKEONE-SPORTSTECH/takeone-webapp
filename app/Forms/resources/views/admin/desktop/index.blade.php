@extends('layouts.admin')

@section('admin-content')
@include('forms::admin.partials.style')
<div class="fa space-y-5" x-data="{ q: '' }">
    <x-admin-hero :eyebrow="__('forms::admin.eyebrow')" :title="__('forms::admin.title')" icon="bi-ui-checks-grid"
                  :subtitle="__('forms::admin.subtitle')">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white num">{{ $totals['forms'] }} {{ __('forms::admin.forms') }} · {{ $totals['active'] }} {{ __('forms::admin.open') }}</span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white num">{{ $totals['responses'] }} {{ __('forms::admin.answers') }}</span>
            <a href="{{ route('admin.forms.create') }}" data-no-shell class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-bold bg-white text-primary shadow-sm"><i class="bi bi-plus-lg"></i>{{ __('forms::admin.new_form') }}</a>
        </x-slot:actions>
    </x-admin-hero>

    @if($forms->isEmpty())
        <div class="fa-card fa-empty"><div class="ic"><i class="bi bi-ui-checks-grid"></i></div>
            <h3>{{ __('forms::admin.empty_title') }}</h3><p>{{ __('forms::admin.empty_body') }}</p>
            <a href="{{ route('admin.forms.create') }}" data-no-shell class="fa-btn solid" style="margin-top:16px"><i class="bi bi-plus-lg"></i>{{ __('forms::admin.new_form') }}</a></div>
    @else
        <div class="relative" style="max-width:420px">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" x-model="q" placeholder="{{ __('forms::admin.search') }}" class="w-full pl-10 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach($forms as $f)
                <div x-show="!q || @js(mb_strtolower($f['title'].' '.$f['title_ar'])).includes(q.toLowerCase())">
                    @include('forms::admin.partials.form-card', ['shell' => true])
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
