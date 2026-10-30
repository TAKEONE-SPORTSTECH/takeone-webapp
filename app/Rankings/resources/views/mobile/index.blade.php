@extends('members::layouts.personal-mobile')

@section('title', __('rankings::messages.title'))

@section('personal-content')
<div class="-mx-4 -mt-4">
    {{-- A top-level destination with no subject colour: the shared mesh, no back control (Design Rule #6). --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative z-10 mt-2">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-bar-chart-steps"></i> {{ __('rankings::messages.chip_formula') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('rankings::messages.title') }}</h1>
        </div>
    </header>

    <div class="-mt-10 relative z-10 px-4 pb-8 space-y-3 mobile-stagger">
        @forelse($federations as $f)
            @php $color = \App\Support\Palette::safe($f['federation']->color); @endphp
            <a href="{{ route('me.rankings.federation', $f['federation']) }}" class="m-card m-press flex items-center gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5">
                @if($logo = \App\Rankings\Support\Standings::logo($f['federation']))
                    <span class="w-12 h-12 flex-shrink-0"><img src="{{ $logo }}" alt="" class="w-full h-full object-contain"></span>
                @else
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $color }};"><i class="bi bi-building text-lg"></i></span>
                @endif
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground break-words"><bdi>{{ $f['federation']->name }}</bdi></span>
                    <span class="block text-[12px] text-muted-foreground mt-0.5">{{ __('rankings::messages.athletes_count', ['count' => $f['athletes']]) }} · {{ $f['categories'] }} {{ __('rankings::messages.categories') }}</span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground/60"></i>
            </a>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-accent text-primary grid place-items-center m-float"><i class="bi bi-bar-chart-steps text-3xl"></i></span>
                <p class="font-bold text-foreground mt-4">{{ __('rankings::messages.empty_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('rankings::messages.empty') }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
