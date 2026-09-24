@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('federations::messages.admin_title'))

@section('content')
@include('federations::admin.partials.styles')
<div class="fedx min-h-screen bg-background pb-20 px-4 pt-4" x-data="{ q: '' }">
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('admin.platform.index') }}" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white m-press">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
            <a href="{{ route('admin.federations.create') }}" aria-label="{{ __('federations::messages.admin_new') }}" title="{{ __('federations::messages.admin_new') }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center m-press">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-bank2"></i> {{ $cards->count() }} {{ __('federations::messages.admin_count') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('federations::messages.admin_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ __('federations::messages.admin_subtitle') }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-3 mobile-stagger">
        @if ($cards->isEmpty())
            <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <span class="w-14 h-14 rounded-2xl bg-accent text-primary grid place-items-center mx-auto mb-3"><i class="bi bi-bank2 text-xl"></i></span>
                <p class="text-base font-bold text-gray-900 mb-1">{{ __('federations::messages.admin_empty_title') }}</p>
                <p class="text-sm text-muted-foreground mb-4">{{ __('federations::messages.admin_empty_body') }}</p>
                <a href="{{ route('admin.federations.create') }}" class="m-press inline-flex items-center gap-2 bg-primary text-white px-4 py-3 rounded-xl font-medium text-sm">
                    <i class="bi bi-plus-lg"></i>{{ __('federations::messages.admin_new') }}
                </a>
            </div>
        @else
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" x-model="q" placeholder="{{ __('federations::messages.admin_search') }}"
                       class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl text-sm bg-white shadow-sm focus:ring-2 focus:ring-primary/40 focus:border-transparent">
            </div>
            @foreach ($cards as $c)
                <a href="{{ route('admin.federations.edit', $c['uuid']) }}"
                   x-show="!q || @js(mb_strtolower($c['name'].' '.$c['short'].' '.implode(' ', $c['sports']))).includes(q.toLowerCase())"
                   class="m-card m-press flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-3">
                    @if ($c['logo'])
                        <span class="w-12 h-12 flex-shrink-0"><img src="{{ $c['logo'] }}" alt="" class="w-full h-full object-contain"></span>
                    @else
                        <span class="w-12 h-12 rounded-xl grid place-items-center text-white font-black text-sm flex-shrink-0" style="background: {{ $c['color'] }}">{{ mb_substr($c['short'] ?: $c['name'], 0, 3) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-gray-900 mb-0 leading-tight">{{ $c['name'] }}</p>
                        <p class="text-xs text-muted-foreground mb-0 mt-0.5 flex items-center gap-1.5"><x-flag :code="$c['country']" :h="10" /> {{ $c['short'] }} · {{ implode(', ', array_slice($c['sports'], 0, 2)) }}</p>
                        <div class="flex gap-1 mt-2">
                            @foreach (['open', 'payments', 'verified'] as $st)
                                @php $r = $c['readiness'][$st]; @endphp
                                <span class="h-1 flex-1 rounded-full bg-muted overflow-hidden"><span class="block h-full rounded-full {{ $r['complete'] ? 'bg-primary' : 'bg-amber-400' }}" style="width: {{ round($r['done'] / $r['total'] * 100) }}%"></span></span>
                            @endforeach
                        </div>
                    </div>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300"></i>
                </a>
            @endforeach
        @endif

        @include('federations::admin.partials.deleted-list', ['deletedClass' => 'mt-4'])
    </div>
</div>
@endsection
