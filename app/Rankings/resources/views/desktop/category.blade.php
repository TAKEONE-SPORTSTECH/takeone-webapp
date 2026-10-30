@extends('layouts.app')

@section('title', $category['name'] ?? __('rankings::messages.title'))

@section('content')
@php
    use App\Rankings\Support\Standings;
    $color = \App\Support\Palette::safe($federation->color);
    $kata = ($category['discipline'] ?? '') === 'kata';
@endphp
<div class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.rankings.federation', $federation) }}" aria-label="{{ __('rankings::messages.back') }}" title="{{ __('rankings::messages.back') }}"
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white hover:bg-white/25 transition-colors">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $kata ? 'bi-person-arms-up' : 'bi-lightning-charge-fill' }}"></i> {{ __('rankings::messages.'.($kata ? 'kata' : 'kumite')) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-hourglass-split"></i> {{ __('rankings::messages.chip_window') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-people"></i> {{ __('rankings::messages.athletes_count', ['count' => $table->count()]) }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $category['name'] ?? '' }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $federation->name }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground border-b border-gray-100">
                            <th class="px-4 py-3 text-start w-16">{{ __('rankings::messages.col_rank') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('rankings::messages.col_athlete') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('rankings::messages.col_medals') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('rankings::messages.col_wins') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('rankings::messages.col_events') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('rankings::messages.col_points') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($table as $row)
                            @php $isMe = $row['user_id'] === $me; @endphp
                            <tr class="{{ $isMe ? 'bg-primary/5' : 'hover:bg-muted/60' }} transition-colors">
                                <td class="px-4 py-3">
                                    @if($row['position'] <= 3)
                                        <x-medal-icon :place="$row['position']" :size="26" />
                                    @else
                                        <span class="w-7 h-7 rounded-full bg-muted inline-flex items-center justify-center text-xs font-black text-muted-foreground">{{ $row['position'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted">
                                            @if($row['photo'])
                                                <img src="{{ $row['photo'] }}" alt="" loading="lazy" class="w-full h-full object-cover">
                                            @else
                                                <x-gender-avatar :gender="$row['gender']" class="w-full h-full" />
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-foreground break-words">
                                                <bdi>{{ $row['name'] }}</bdi>
                                                @if($isMe)<span class="ms-1 px-1.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-bold align-middle">{{ __('rankings::messages.you') }}</span>@endif
                                            </p>
                                            <p class="text-xs text-muted-foreground break-words">@if($row['country'])<x-flag :code="$row['country']" :h="10" /> @endif{{ $row['club'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2 text-xs font-bold text-muted-foreground">
                                        @foreach(['gold' => 1, 'silver' => 2, 'bronze' => 3] as $metal => $place)
                                            @if($row[$metal])<span class="inline-flex items-center gap-0.5"><x-medal-icon :place="$place" :size="13" :numeral="false" />{{ $row[$metal] }}</span>@endif
                                        @endforeach
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold text-foreground">{{ $row['wins'] }}</td>
                                <td class="px-4 py-3 text-center text-muted-foreground">{{ $row['events'] }}</td>
                                <td class="px-4 py-3 text-end text-base font-black text-foreground">{{ Standings::format($row['points']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="space-y-4">
            @include('rankings::partials.how', ['factor' => $factor, 'color' => $color])
        </aside>
    </div>
</div>
@endsection
