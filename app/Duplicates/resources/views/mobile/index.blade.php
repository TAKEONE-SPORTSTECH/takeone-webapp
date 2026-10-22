@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('duplicates::messages.title'))

@section('content')
<div x-data="@include('duplicates::partials.state')" class="min-h-screen bg-background pb-16 px-4 pt-4">

    {{-- ===== Hero band (Design Rule #6) ===== --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('admin.platform.index') }}" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-intersect"></i> {{ __('duplicates::messages.eyebrow') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi" :class="watch ? 'bi-eye' : 'bi-eye-slash'"></i> <span x-text="watch ? T.watch_on : T.watch_off"></span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi" :class="autofix ? 'bi-magic' : 'bi-pause-circle'"></i> <span x-text="autofix ? T.autofix_on : T.autofix_off"></span>
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('duplicates::messages.title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 leading-snug" x-text="lastRun ? fill(T.last_run, { when: when(lastRun) }) : T.never_run"></p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-4 mobile-stagger">

        {{-- What is left to decide, and what has been resolved: a two-way switch, not a tab strip. --}}
        <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 grid grid-cols-2 gap-1.5">
            <template x-for="t in ['open', 'resolved']" :key="t">
                <button type="button" @click="tab = t"
                        class="m-press h-11 rounded-xl text-sm font-bold inline-flex items-center justify-center gap-2 transition-colors"
                        :class="tab === t ? 'bg-primary text-white' : 'text-gray-600'">
                    <span x-text="t === 'open' ? T.tab_open : T.tab_resolved"></span>
                    <span class="px-2 rounded-full text-[11px]" :class="tab === t ? 'bg-white/25' : 'bg-muted'" x-text="count('all', t)"></span>
                </button>
            </template>
        </div>

        <div x-show="! watch" x-cloak class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-[13px] flex items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-0.5"></i><span x-text="T.watch_is_off"></span>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <template x-for="k in kinds" :key="k">
                <button type="button" @click="kind = k"
                        class="m-press inline-flex items-center gap-1.5 h-9 px-3 rounded-full text-xs font-semibold border transition-colors"
                        :class="kind === k ? 'border-primary bg-primary text-white' : 'border-gray-200 bg-white text-gray-600'">
                    <i class="bi" :class="icons[k]"></i>
                    <span x-text="T['kind_' + k]"></span>
                    <span class="px-1.5 rounded-full text-[10px]" :class="kind === k ? 'bg-white/25' : 'bg-muted'" x-text="count(k)"></span>
                </button>
            </template>
        </div>

        @include('duplicates::partials.pair-styles')
        <div class="space-y-3">
            <template x-for="f in shown" :key="f.id">
                @include('duplicates::partials.pair-card')
            </template>
        </div>

        <div x-show="! shown.length" x-cloak class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
            <span class="w-14 h-14 mx-auto rounded-2xl grid place-items-center bg-accent text-primary"><i class="bi bi-check2-all text-2xl"></i></span>
            <p class="text-sm text-muted-foreground mt-3" x-text="tab === 'open' ? T.empty_open : T.empty_resolved"></p>
        </div>
    </div>
</div>
@endsection
