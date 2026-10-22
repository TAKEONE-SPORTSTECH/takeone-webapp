@extends('layouts.admin')

@section('admin-content')
<div x-data="@include('duplicates::partials.state')" class="space-y-6">
    <x-admin-hero eyebrow="{{ __('duplicates::messages.eyebrow') }}" title="{{ __('duplicates::messages.title') }}" icon="bi-intersect"
                  subtitle="{{ __('duplicates::messages.subtitle') }}">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white">
                <i class="bi" :class="watch ? 'bi-eye' : 'bi-eye-slash'"></i>
                <span x-text="watch ? T.watch_on : T.watch_off"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white">
                <i class="bi" :class="autofix ? 'bi-magic' : 'bi-pause-circle'"></i>
                <span x-text="autofix ? T.autofix_on : T.autofix_off"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-white/10 text-white/90"
                  x-text="lastRun ? fill(T.last_run, { when: when(lastRun) }) : T.never_run"></span>
        </x-slot:actions>
    </x-admin-hero>

    <div x-show="! watch" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm flex items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i><span x-text="T.watch_is_off"></span>
    </div>

    {{-- Tabs: what is left to decide, and what has been resolved. --}}
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="nav -mb-px flex gap-8">
            <template x-for="t in ['open', 'resolved']" :key="t">
                <button type="button" @click="tab = t"
                        class="border-b-2 py-3 font-medium text-sm whitespace-nowrap transition-colors"
                        :class="tab === t ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    <span x-text="t === 'open' ? T.tab_open : T.tab_resolved"></span>
                    <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" x-text="count('all', t)"></span>
                </button>
            </template>
        </nav>
    </div>

    {{-- Kind filter --}}
    <div class="flex flex-wrap gap-2">
        <template x-for="k in kinds" :key="k">
            <button type="button" @click="kind = k"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                    :class="kind === k ? 'border-primary bg-primary text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-primary/40'">
                <i class="bi" :class="icons[k]"></i>
                <span x-text="T['kind_' + k]"></span>
                <span class="px-1.5 rounded-full text-[10px]" :class="kind === k ? 'bg-white/25' : 'bg-muted'" x-text="count(k)"></span>
            </button>
        </template>
    </div>

    @include('duplicates::partials.pair-styles')
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <template x-for="f in shown" :key="f.id">
            @include('duplicates::partials.pair-card')
        </template>
    </div>

    <div x-show="! shown.length" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
        <span class="w-14 h-14 mx-auto rounded-2xl grid place-items-center bg-accent text-primary"><i class="bi bi-check2-all text-2xl"></i></span>
        <p class="text-sm text-muted-foreground mt-3" x-text="tab === 'open' ? T.empty_open : T.empty_resolved"></p>
    </div>
</div>
@endsection
