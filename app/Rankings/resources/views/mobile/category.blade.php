@extends('members::layouts.personal-mobile')

@section('title', $category['name'] ?? __('rankings::messages.title'))

@section('personal-content')
@php
    use App\Rankings\Support\Standings;
    $color = \App\Support\Palette::safe($federation->color);
    $backUrl = route('me.rankings.federation', $federation);
@endphp
<div class="-mx-4 -mt-4">
    <header class="px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ $backUrl }}" aria-label="{{ __('rankings::messages.back') }}" title="{{ __('rankings::messages.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ ($category['discipline'] ?? '') === 'kata' ? 'bi-person-arms-up' : 'bi-lightning-charge-fill' }}"></i>
                    {{ __('rankings::messages.'.(($category['discipline'] ?? '') === 'kata' ? 'kata' : 'kumite')) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-hourglass-split"></i> {{ __('rankings::messages.chip_window') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $category['name'] ?? '' }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $federation->name }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 px-4 pb-8 space-y-4 mobile-stagger">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
            @foreach($table as $row)
                @php $isMe = $row['user_id'] === $me; @endphp
                <div class="flex items-center gap-3 p-3 {{ $isMe ? 'bg-primary/5' : '' }}">
                    <span class="w-8 flex-shrink-0 grid place-items-center">
                        @if($row['position'] <= 3)
                            <x-medal-icon :place="$row['position']" :size="24" />
                        @else
                            <span class="w-7 h-7 rounded-full bg-muted grid place-items-center text-xs font-black text-muted-foreground">{{ $row['position'] }}</span>
                        @endif
                    </span>
                    <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted">
                        @if($row['photo'])
                            <img src="{{ $row['photo'] }}" alt="" loading="lazy" class="w-full h-full object-cover">
                        @else
                            <x-gender-avatar :gender="$row['gender']" class="w-full h-full" />
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground leading-snug break-words">
                            <bdi>{{ $row['name'] }}</bdi>
                            @if($isMe)<span class="ms-1 px-1.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-bold align-middle">{{ __('rankings::messages.you') }}</span>@endif
                        </span>
                        <span class="block text-[12px] text-muted-foreground mt-0.5 break-words">
                            @if($row['country'])<x-flag :code="$row['country']" :h="10" /> @endif{{ $row['club'] }}
                        </span>
                        <span class="flex items-center gap-2 mt-1 text-[11px] font-bold text-muted-foreground">
                            @foreach(['gold' => 1, 'silver' => 2, 'bronze' => 3] as $metal => $place)
                                @if($row[$metal])<span class="inline-flex items-center gap-0.5"><x-medal-icon :place="$place" :size="11" :numeral="false" />{{ $row[$metal] }}</span>@endif
                            @endforeach
                            <span class="inline-flex items-center gap-0.5"><i class="bi bi-lightning-charge"></i>{{ $row['wins'] }}</span>
                        </span>
                    </span>
                    <span class="text-end flex-shrink-0">
                        <span class="block text-base font-black text-foreground">{{ Standings::format($row['points']) }}</span>
                        <span class="block text-[10px] font-bold uppercase text-muted-foreground">{{ __('rankings::messages.pts') }}</span>
                    </span>
                </div>
            @endforeach
        </div>

        @include('rankings::partials.how', ['factor' => $factor, 'color' => $color])
    </div>
</div>
@endsection
