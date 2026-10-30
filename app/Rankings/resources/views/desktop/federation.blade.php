@extends('layouts.app')

@section('title', __('rankings::messages.title'))

@section('content')
@php
    use App\Rankings\Support\Standings;
    $color = \App\Support\Palette::safe($federation->color);
@endphp
<div class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-bar-chart-steps"></i> {{ __('rankings::messages.chip_national') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">{{ __('rankings::messages.chip_formula') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-people"></i> {{ __('rankings::messages.athletes_count', ['count' => $athletes]) }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('rankings::messages.title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                @if($logo = Standings::logo($federation))<span class="w-5 h-5 flex-shrink-0"><img src="{{ $logo }}" alt="" class="w-full h-full object-contain"></span>@else<i class="bi bi-building"></i>@endif
                {{ $federation->name }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 space-y-6">
            @forelse($groups as $group => $categories)
                <section>
                    <h2 class="text-xs font-bold uppercase tracking-wide text-muted-foreground mb-3">{{ $group !== '' ? $group : __('rankings::messages.other') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($categories as $c)
                            <a href="{{ route('me.rankings.category', [$federation, $c['key']]) }}"
                               class="group bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 hover:shadow-md hover:border-primary/30 transition-all">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $c['discipline'] === 'kata' ? 'bg-amber-50 text-amber-700' : 'bg-accent text-primary' }}">
                                    <i class="bi {{ $c['discipline'] === 'kata' ? 'bi-person-arms-up' : 'bi-lightning-charge-fill' }}"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-foreground break-words">{{ Standings::shortName($c['name'], $group) }}</span>
                                    <span class="block text-xs text-muted-foreground mt-0.5 break-words">
                                        @if($c['leader_name'])<x-medal-icon :place="1" :size="11" /> <bdi>{{ $c['leader_name'] }}</bdi> · {{ Standings::format($c['leader_points']) }} {{ __('rankings::messages.pts') }}@endif
                                    </span>
                                </span>
                                <span class="text-xs font-semibold text-muted-foreground inline-flex items-center gap-1 flex-shrink-0"><i class="bi bi-people"></i>{{ $c['athletes'] }}</span>
                                <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground group-hover:text-primary transition-colors"></i>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                    <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center"><i class="bi bi-bar-chart-steps text-3xl"></i></span>
                    <p class="font-bold text-foreground mt-4">{{ __('rankings::messages.empty_title') }}</p>
                    <p class="text-sm text-muted-foreground mt-1">{{ __('rankings::messages.empty') }}</p>
                </div>
            @endforelse
        </div>

        <aside class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 grid grid-cols-2">
                <div class="p-4 text-center">
                    <p class="text-2xl font-black text-foreground">{{ number_format($athletes) }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mt-0.5">{{ __('rankings::messages.athletes') }}</p>
                </div>
                <div class="p-4 text-center border-s border-gray-100">
                    <p class="text-2xl font-black text-foreground">{{ number_format($categoryCount) }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mt-0.5">{{ __('rankings::messages.categories') }}</p>
                </div>
            </div>

            @if($mine->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <h2 class="px-4 pt-4 pb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('rankings::messages.your_rankings') }}</h2>
                    <div class="divide-y divide-gray-100">
                        @foreach($mine as $m)
                            <a href="{{ route('me.rankings.category', [$federation, $m['key']]) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-muted/60 transition-colors">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 font-black text-white text-sm" style="background: {{ $color }};">#{{ $m['position'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-foreground break-words">{{ $m['name'] }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ __('rankings::messages.position_of', ['position' => $m['position'], 'of' => $m['of']]) }}</span>
                                </span>
                                <span class="text-sm font-black text-foreground">{{ Standings::format($m['points']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @include('rankings::partials.how', ['factor' => $factor, 'color' => $color])
        </aside>
    </div>
</div>
@endsection
