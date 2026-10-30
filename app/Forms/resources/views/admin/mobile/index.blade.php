@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('forms::admin.title'))

@section('content')
@include('forms::admin.partials.style')
<div class="fa min-h-screen bg-background pb-24 px-4 pt-4" x-data="{ q: '' }">
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('admin.platform.index') }}" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white"><i class="bi bi-chevron-left rtl:rotate-180"></i></a>
        </div>
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ui-checks-grid"></i> {{ __('forms::admin.eyebrow') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur num">{{ $totals['responses'] }} {{ __('forms::admin.answers') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('forms::admin.title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ __('forms::admin.subtitle') }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-3 mobile-stagger">
        @if($forms->isEmpty())
            <div class="fa-card fa-empty"><div class="ic"><i class="bi bi-ui-checks-grid"></i></div>
                <h3>{{ __('forms::admin.empty_title') }}</h3><p>{{ __('forms::admin.empty_body') }}</p></div>
        @else
            <div class="fa-card" style="padding:8px 12px;display:flex;align-items:center;gap:8px">
                <i class="bi bi-search" style="color:var(--ink3)"></i>
                <input type="search" x-model="q" placeholder="{{ __('forms::admin.search') }}" style="flex:1;border:0;outline:none;font-size:16px;padding:6px 0;background:transparent">
            </div>
            @foreach($forms as $f)
                <div x-show="!q || @js(mb_strtolower($f['title'].' '.$f['title_ar'])).includes(q.toLowerCase())">
                    @include('forms::admin.partials.form-card')
                </div>
            @endforeach
        @endif
    </div>

    {{-- teleported: the mobile shell's stagger leaves a transform on this page's
         wrapper, which would pin a fixed button to the wrapper, not the screen --}}
    <template x-teleport="body">
    <a href="{{ route('admin.forms.create') }}" class="m-press fixed z-40 flex items-center gap-2 px-5 h-12 rounded-full bg-primary text-white font-bold shadow-lg"
       style="inset-inline-end:16px;bottom:calc(20px + env(safe-area-inset-bottom))"><i class="bi bi-plus-lg"></i>{{ __('forms::admin.new_form') }}</a>
    </template>
</div>
@endsection
