{{--
    One sandbox event.

    Shows the three things being tried, in the order they matter on the day: the
    variants and what each costs, then the start list — every entrant with the
    club they NAMED (real or provisional), what they entered, and what they owe.

    Nothing on this page is a platform member. That is the point, and the badges
    say so out loud rather than leaving a reader to assume otherwise.
--}}
@extends('layouts.app')

@section('title', $event->title.' · sandbox')

@section('content')
@php
    $color = '#7c3aed';
    $currency = $event->fee_currency;
@endphp

<div class="px-4 sm:px-6 lg:px-8 py-4">

    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-4 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('testcode.home') }}"
                   aria-label="Back to the sandbox" title="Back to the sandbox"
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white hover:bg-white/25 transition-colors">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-beaker"></i> Sandbox copy
                </span>
                @if($event->weigh_in_at)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-clock"></i> Weigh-in {{ $event->weigh_in_at->format('d M, H:i') }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $event->title }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-people"></i>{{ $entrants->count() }} entrants · none of them are members
            </p>
        </div>
    </div>

    {{-- 3. What can be entered, and what each costs --}}
    <div class="mb-6">
        <h2 class="text-sm font-bold text-foreground mb-1">What can be entered</h2>
        <p class="text-xs text-muted-foreground mb-3">
            One event, several competitions. An athlete may enter more than one and the fees add up.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($event->variants as $variant)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                        <i class="bi bi-award text-lg"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">{{ $variant->name }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ $event->entries()->whereHas('selections', fn($q) => $q->where('lab_variant_id', $variant->id))->count() }} entered
                        </p>
                    </div>
                    <span class="text-sm font-black text-foreground">{{ $currency }} {{ rtrim(rtrim(number_format($variant->fee(), 3, '.', ''), '0'), '.') }}</span>
                </div>
            @endforeach
        </div>

        @if($event->late_entry_from)
            <div class="mt-3 bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600">
                    <i class="bi bi-hourglass-split text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">Late entry penalty</p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Added to any entry made from {{ $event->late_entry_from->format('d M Y, H:i') }}
                        @if($event->isLateNow()) — <span class="text-amber-600 font-semibold">in force now</span> @endif
                    </p>
                </div>
                <span class="text-sm font-black text-foreground">{{ $currency }} {{ rtrim(rtrim(number_format($event->lateFee(), 3, '.', ''), '0'), '.') }}</span>
            </div>
        @endif
    </div>

    {{-- 1 + 2. The start list: people who are not members, clubs that may not exist --}}
    <div>
        <h2 class="text-sm font-bold text-foreground mb-1">Start list</h2>
        <p class="text-xs text-muted-foreground mb-3">
            Nobody here has a platform account. After the competition, the ones who really took part get promoted.
        </p>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @foreach($entrants as $entrant)
                <div class="p-4 flex items-center gap-3 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                    <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted grid place-items-center">
                        @if($entrant->photo)
                            <img src="{{ route('file.show', ['path' => $entrant->photo]) }}"
                                 alt="" class="w-full h-full object-cover">
                        @else
                            <i class="bi bi-person text-muted-foreground"></i>
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground truncate">
                            {{ $entrant->full_name }}
                            @if($entrant->duplicate_of_id)
                                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">duplicate</span>
                            @endif
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            @if($entrant->club)
                                {{ $entrant->club->name }}
                                @unless($entrant->club->isReal())
                                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-accent text-primary">not on platform</span>
                                @endunless
                            @else
                                Unattached
                            @endif
                        </p>
                    </div>

                    <div class="text-right flex-shrink-0">
                        <p class="text-xs text-muted-foreground">
                            @foreach($entrant->entry?->selections ?? [] as $selection)
                                <span class="inline-block px-2 py-0.5 rounded-full bg-muted text-foreground font-semibold mb-0.5">{{ $selection->variant?->name }}</span>
                            @endforeach
                        </p>
                        @if($entrant->entry)
                            <p class="text-sm font-black text-foreground mt-0.5">
                                {{ $currency }} {{ rtrim(rtrim(number_format((float) $entrant->entry->fee_total, 3, '.', ''), '0'), '.') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
