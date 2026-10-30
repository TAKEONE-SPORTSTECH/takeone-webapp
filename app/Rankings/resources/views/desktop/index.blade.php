@extends('layouts.app')

@section('title', __('rankings::messages.title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">
    {{-- A top-level destination with no subject colour: the shared mesh, no back control (Design Rule #6). --}}
    <div class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-bar-chart-steps"></i> {{ __('rankings::messages.chip_formula') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('rankings::messages.title') }}</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($federations as $f)
            @php $color = \App\Support\Palette::safe($f['federation']->color); @endphp
            <a href="{{ route('me.rankings.federation', $f['federation']) }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 hover:shadow-md hover:border-primary/30 transition-all">
                @if($logo = \App\Rankings\Support\Standings::logo($f['federation']))
                    <span class="w-12 h-12 flex-shrink-0"><img src="{{ $logo }}" alt="" class="w-full h-full object-contain"></span>
                @else
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $color }};"><i class="bi bi-building text-lg"></i></span>
                @endif
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-foreground break-words"><bdi>{{ $f['federation']->name }}</bdi></span>
                    <span class="block text-xs text-muted-foreground mt-0.5">{{ __('rankings::messages.athletes_count', ['count' => $f['athletes']]) }} · {{ $f['categories'] }} {{ __('rankings::messages.categories') }}</span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground"></i>
            </a>
        @empty
            <div class="md:col-span-2 lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center"><i class="bi bi-bar-chart-steps text-3xl"></i></span>
                <p class="font-bold text-foreground mt-4">{{ __('rankings::messages.empty_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('rankings::messages.empty') }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
