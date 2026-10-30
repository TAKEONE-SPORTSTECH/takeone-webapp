@extends('members::layouts.personal-mobile')

@section('title', __('rankings::messages.title'))

@section('personal-content')
@php
    use App\Rankings\Support\Standings;
    $color = \App\Support\Palette::safe($federation->color);
@endphp
<div class="-mx-4 -mt-4">
    {{-- The federation's own colour: this is ITS ranking. A top-level destination, so no back control. --}}
    <header class="px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative z-10 mt-2">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-bar-chart-steps"></i> {{ __('rankings::messages.chip_national') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">{{ __('rankings::messages.chip_formula') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('rankings::messages.title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                @if($logo = Standings::logo($federation))<span class="w-5 h-5 flex-shrink-0"><img src="{{ $logo }}" alt="" class="w-full h-full object-contain"></span>@else<i class="bi bi-building"></i>@endif
                {{ $federation->name }}
            </p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 px-4 pb-8 space-y-4 mobile-stagger">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 grid grid-cols-2">
            <div class="p-4 text-center">
                <p class="text-2xl font-black text-foreground">{{ number_format($athletes) }}</p>
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mt-0.5">{{ __('rankings::messages.athletes') }}</p>
            </div>
            <div class="p-4 text-center border-s border-gray-100">
                <p class="text-2xl font-black text-foreground">{{ number_format($categoryCount) }}</p>
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mt-0.5">{{ __('rankings::messages.categories') }}</p>
            </div>
        </div>

        @if($mine->isNotEmpty())
            <section>
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground px-1 mb-2">{{ __('rankings::messages.your_rankings') }}</p>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    @foreach($mine as $m)
                        <a href="{{ route('me.rankings.category', [$federation, $m['key']]) }}" class="m-press flex items-center gap-3 p-3.5">
                            <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 font-black text-white text-sm" style="background: {{ $color }};">#{{ $m['position'] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground leading-snug break-words">{{ $m['name'] }}</span>
                                <span class="block text-[12px] text-muted-foreground mt-0.5">{{ __('rankings::messages.position_of', ['position' => $m['position'], 'of' => $m['of']]) }}</span>
                            </span>
                            <span class="text-end flex-shrink-0">
                                <span class="block text-base font-black text-foreground">{{ Standings::format($m['points']) }}</span>
                                <span class="block text-[10px] font-bold uppercase text-muted-foreground">{{ __('rankings::messages.pts') }}</span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground/60"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @forelse($groups as $group => $categories)
            <section>
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground px-1 mb-2">{{ $group !== '' ? $group : __('rankings::messages.other') }}</p>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                    @foreach($categories as $c)
                        <a href="{{ route('me.rankings.category', [$federation, $c['key']]) }}" class="m-press flex items-center gap-3 p-3.5">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $c['discipline'] === 'kata' ? 'bg-amber-50 text-amber-700' : 'bg-accent text-primary' }}">
                                <i class="bi {{ $c['discipline'] === 'kata' ? 'bi-person-arms-up' : 'bi-lightning-charge-fill' }}"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground leading-snug break-words">{{ Standings::shortName($c['name'], $group) }}</span>
                                <span class="block text-[12px] text-muted-foreground mt-0.5 break-words">
                                    @if($c['leader_name'])<x-medal-icon :place="1" :size="11" /> <bdi>{{ $c['leader_name'] }}</bdi> · {{ Standings::format($c['leader_points']) }} {{ __('rankings::messages.pts') }}@endif
                                </span>
                            </span>
                            <span class="text-[11px] font-bold text-muted-foreground flex-shrink-0 inline-flex items-center gap-1"><i class="bi bi-people"></i>{{ $c['athletes'] }}</span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground/60"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center m-float"><i class="bi bi-bar-chart-steps text-3xl"></i></span>
                <p class="font-bold text-foreground mt-4">{{ __('rankings::messages.empty_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('rankings::messages.empty') }}</p>
            </div>
        @endforelse

        @include('rankings::partials.how', ['factor' => $factor, 'color' => $color])
    </div>
</div>
@endsection
