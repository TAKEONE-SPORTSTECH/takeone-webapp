@extends('layouts.admin')

@section('title', __('federations::messages.admin_title'))

@push('styles')
    @include('federations::admin.partials.styles')
@endpush

@section('admin-content')
<div class="fedx" x-data="{ q: '' }">
    <x-admin-hero :eyebrow="__('federations::messages.admin_nav')" icon="bi-bank2" :title="__('federations::messages.admin_title')"
                  :subtitle="__('federations::messages.admin_subtitle')" :count="$cards->count()" :countLabel="__('federations::messages.admin_count')">
        <x-slot:actions>
            <a href="{{ route('admin.federations.create') }}" data-no-shell
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white text-primary font-semibold text-sm shadow-sm hover:bg-white/90 transition-colors">
                <i class="bi bi-plus-lg"></i>{{ __('federations::messages.admin_new') }}
            </a>
        </x-slot:actions>
    </x-admin-hero>

    @if ($cards->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center fedx-card-in">
            <span class="w-16 h-16 rounded-2xl bg-accent text-primary grid place-items-center mx-auto mb-4"><i class="bi bi-bank2 text-2xl"></i></span>
            <h2 class="text-lg font-bold text-gray-900">{{ __('federations::messages.admin_empty_title') }}</h2>
            <p class="text-sm text-muted-foreground max-w-md mx-auto mt-1">{{ __('federations::messages.admin_empty_body') }}</p>
            <a href="{{ route('admin.federations.create') }}" data-no-shell class="inline-flex items-center gap-2 mt-5 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm">
                <i class="bi bi-plus-lg"></i>{{ __('federations::messages.admin_new') }}
            </a>
        </div>
    @else
        <div class="relative mb-4">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" x-model="q" placeholder="{{ __('federations::messages.admin_search') }}"
                   class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach ($cards as $c)
                <a href="{{ route('admin.federations.edit', $c['uuid']) }}" data-no-shell
                   x-show="!q || @js(mb_strtolower($c['name'].' '.$c['short'].' '.implode(' ', $c['sports']))).includes(q.toLowerCase())"
                   class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow fedx-card-in block"
                   style="animation-delay: {{ min($loop->index, 8) * 50 }}ms">
                    <div class="relative px-5 pt-5 pb-10 text-white overflow-hidden" style="background: linear-gradient(150deg, {{ $c['color'] }}, {{ $c['color'] }}b0);">
                        <div class="absolute -right-8 -top-10 w-32 h-32 rounded-full bg-white/10"></div>
                        <div class="relative flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20">
                                <x-flag :code="$c['country']" :h="10" /> {{ $c['short'] }}
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20">
                                <i class="bi {{ ['verified' => 'bi-patch-check-fill', 'payments' => 'bi-bank', 'open' => 'bi-door-open-fill', 'draft' => 'bi-pencil'][$c['stage']] }}"></i>
                                {{ $c['stage'] === 'draft' ? __('federations::messages.stage_draft') : __('federations::messages.stage_'.$c['stage']) }}
                            </span>
                        </div>
                    </div>
                    <div class="px-5 pb-5 -mt-7 relative">
                        <div class="flex items-end gap-3">
                            @if ($c['logo'])
                                <span class="w-14 h-14 flex-shrink-0"><img src="{{ $c['logo'] }}" alt="" class="w-full h-full object-contain"></span>
                            @else
                                <span class="w-14 h-14 rounded-2xl grid place-items-center text-white font-black flex-shrink-0 shadow-sm" style="background: {{ $c['color'] }}">{{ mb_substr($c['short'] ?: $c['name'], 0, 3) }}</span>
                            @endif
                        </div>
                        <h3 class="text-base font-bold text-gray-900 leading-tight mt-3 group-hover:text-primary transition-colors">{{ $c['name'] }}</h3>
                        <p class="text-xs text-muted-foreground mt-1 mb-0"><i class="bi bi-person me-1"></i>{{ $c['owner'] ?: '—' }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            @foreach (array_slice($c['sports'], 0, 4) as $s)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-muted text-gray-700">{{ $s }}</span>
                            @endforeach
                        </div>
                        {{-- Readiness, three stages side by side --}}
                        <div class="grid grid-cols-3 gap-2 mt-4">
                            @foreach (['open', 'payments', 'verified'] as $st)
                                @php $r = $c['readiness'][$st]; @endphp
                                <div title="{{ __('federations::messages.stage_'.$st) }}">
                                    <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full {{ $r['complete'] ? 'bg-primary' : 'bg-amber-400' }}" style="width: {{ round($r['done'] / $r['total'] * 100) }}%"></div>
                                    </div>
                                    <p class="text-[10px] text-muted-foreground mt-1 mb-0 truncate">{{ __('federations::messages.stage_'.$st) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @include('federations::admin.partials.deleted-list')
</div>
@endsection
