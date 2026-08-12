@extends('layouts.personal-mobile')

@section('title', __('personal.event_show_whos_joined'))

{{--
    Who's joined — reading only.

    Two tabs over one roster: the competitors, and the clubs they came from.
    There is nothing to act on here for anybody. An organiser sees exactly what a
    first-time competitor sees, which is what lets the screen be described in one
    sentence; the officials' gates live on their own screen (event-verification).

    Every card is a door to somewhere that already exists: an athlete to their
    public profile, a club to its club page. The page invents no destinations and
    discloses nothing those pages do not.

    Expects $e (the event view) plus $participants and $clubs from
    App\Events\Support\RosterPeople.
--}}

@php
    $flagClass = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? 'fi fi-'.$c : null;
    };
@endphp

@section('content')
<div x-data="{
        tab: 'athletes',
        q: '',
        match(...fields) {
            const s = this.q.trim().toLowerCase();
            return !s || fields.some(f => (f || '').toLowerCase().includes(s));
        },
        {{-- A list can only say 'nothing matched' if it knows every name on it.
             Filtering is client-side because every row is already on the page —
             typing narrows it with no round trip, and it can only ever hide rows
             the server already decided this viewer may see. --}}
        names: {
            athletes: @js(collect($participants)->map(fn ($p) => trim(($p['name'] ?? '').' '.($p['club']['name'] ?? '')))->values()),
            clubs: @js(collect($clubs)->pluck('name')->values()),
        },
        get empty() {
            return this.q.trim() !== '' && !(this.names[this.tab] || []).some(n => this.match(n));
        },
     }">

    {{-- ===== Header ===== Design Rule #6: a full-bleed hero band, the tab tray
         riding its bottom edge. pb-12 leaves exactly the tray's half-height of
         colour beneath the counts, so nothing sits on a strip of empty gradient. --}}
    <header class="m-hero px-5 pt-5 pb-12 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-3 relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
               aria-label="{{ $e['title'] }}">
                <i class="bi bi-arrow-left text-lg rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-5">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70 truncate">{{ $e['title'] }}</p>
            <h1 class="text-2xl font-black mt-1 leading-tight">{{ __('personal.event_show_whos_joined') }}</h1>
            <p class="text-[13px] font-medium text-white/85 mt-1.5 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <i class="bi bi-person-arms-up text-white/60"></i>{{ trans_choice('personal.event_people_athletes', count($participants), ['count' => count($participants)]) }}
                </span>
                <span class="w-1 h-1 rounded-full bg-white/40"></span>
                <span class="inline-flex items-center gap-1.5">
                    <i class="bi bi-buildings text-white/60"></i>{{ trans_choice('personal.event_people_clubs', count($clubs), ['count' => count($clubs)]) }}
                </span>
            </p>
        </div>
    </header>

    <div class="px-4 -mt-6 relative z-10 pb-8">

        {{-- ===== Tabs ===== A segmented tray floating on the band's edge. Two
             destinations, so a segmented control rather than an underline: it
             reads as a switch between two views of one roster, which is what it
             is. --}}
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-1.5 flex gap-1.5" role="tablist">
            @foreach([
                ['key' => 'athletes', 'icon' => 'bi-person-arms-up', 'label' => __('personal.event_people_tab_athletes'), 'n' => count($participants)],
                ['key' => 'clubs', 'icon' => 'bi-buildings', 'label' => __('personal.event_people_tab_clubs'), 'n' => count($clubs)],
            ] as $t)
                <button type="button" role="tab" @click="tab = '{{ $t['key'] }}'; q = ''"
                        :aria-selected="tab === '{{ $t['key'] }}'"
                        :class="tab === '{{ $t['key'] }}'
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-muted-foreground hover:bg-muted/60'"
                        class="m-press flex-1 rounded-xl py-2.5 px-3 text-sm font-bold transition-colors flex items-center justify-center gap-2">
                    <i class="bi {{ $t['icon'] }}"></i>
                    <span>{{ $t['label'] }}</span>
                    <span class="text-[11px] font-black tabular-nums px-1.5 py-0.5 rounded-full"
                          :class="tab === '{{ $t['key'] }}' ? 'bg-white/20' : 'bg-muted'">{{ $t['n'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- ===== Search ===== One box over both tabs. On the athletes tab it
             also matches the club name, because "who from Emperor is here" is
             the same question asked from the other end. --}}
        @if(count($participants))
            <div class="relative mt-3">
                <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                <input type="search" x-model="q"
                       :placeholder="tab === 'athletes'
                            ? '{{ __('personal.event_people_search_athletes') }}'
                            : '{{ __('personal.event_people_search_clubs') }}'"
                       class="w-full ps-10 pe-10 py-2.5 text-sm bg-white border border-gray-100 rounded-xl shadow-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                <button type="button" x-show="q" x-cloak @click="q = ''"
                        class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="{{ __('personal.event_people_search_clear') }}">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        @endif

        {{-- ===== Athletes ===== Each competitor is their own card, so the list
             reads as a stack of people rather than one slab. The whole card is
             the link — a name-sized tap target on a phone is not one. --}}
        <div x-show="tab === 'athletes'" x-cloak class="mt-3 space-y-2.5 mobile-stagger">
            @forelse($participants as $p)
                @php
                    $division = array_filter([$p['category'] ?? null, $p['weight_class'] ?? null]);
                    $flag = $flagClass($p['country']);
                @endphp

                {{-- An <a> with no href when the athlete has no public profile:
                     still a card, just not a door. Cheaper than branching the
                     whole element, and valid HTML. --}}
                <a @if($p['uuid']) href="{{ route('people.show', $p['uuid']) }}" @endif
                    x-show="match(@js($p['name']), @js($p['club']['name'] ?? ''))"
                    class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3 flex items-center gap-3 {{ $p['uuid'] ? 'hover:shadow-md transition-shadow' : '' }}">

                    {{-- Portrait, or the silhouette when they have not published
                         a picture. No initial-letter crest stand-in: invented
                         detail about a person reads as fact. --}}
                    <div class="relative flex-shrink-0">
                        @if($p['photo'])
                            <img src="{{ $p['photo'] }}" alt=""
                                 class="w-12 h-12 rounded-2xl object-cover border border-gray-100">
                        @else
                            <x-gender-avatar :gender="$p['gender']" class="w-12 h-12 rounded-2xl border border-gray-100" />
                        @endif

                        @if($flag)
                            <span class="{{ $flag }} absolute -bottom-0.5 -end-0.5 w-5 h-4 rounded-[3px] ring-2 ring-white shadow-sm"></span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-sm text-foreground truncate">{{ $p['name'] }}</p>

                        @if($division)
                            <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                @foreach($division as $token)
                                    <span class="px-2 py-0.5 rounded-full bg-accent text-primary text-[10px] font-bold">{{ $token }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if($p['club'])
                            <p class="text-xs text-muted-foreground mt-1 flex items-center gap-1.5 truncate">
                                @if($p['club']['logo'])
                                    {{-- Design Rule #5: the bare mark, never a white tile behind it. --}}
                                    <span class="w-4 h-4 flex-shrink-0">
                                        <img src="{{ $p['club']['logo'] }}" alt="" class="w-full h-full object-contain">
                                    </span>
                                @else
                                    <i class="bi bi-buildings text-[11px]"></i>
                                @endif
                                <span class="truncate">{{ $p['club']['name'] }}</span>
                            </p>
                        @endif
                    </div>

                    @if($p['uuid'])
                        <i class="bi bi-chevron-right text-muted-foreground rtl:rotate-180 flex-shrink-0"></i>
                    @endif
                </a>
            @empty
                @include('personal.partials.event-people-empty', [
                    'icon' => 'bi-person-arms-up',
                    'title' => __('personal.event_people_none_athletes'),
                    'body' => __('personal.event_people_none_athletes_sub'),
                ])
            @endforelse

            {{-- Search found nothing. Distinct from an empty roster: one means
                 "try another spelling", the other means "nobody has entered". --}}
            <div x-show="empty && tab === 'athletes'" x-cloak>
                @include('personal.partials.event-people-empty', [
                    'icon' => 'bi-search',
                    'title' => __('personal.event_people_no_matches'),
                    'body' => __('personal.event_people_no_matches_sub'),
                ])
            </div>
        </div>

        {{-- ===== Clubs ===== Crest-forward: at a championship the crest is how
             a club is recognised across a hall, so it leads the row at a size
             worth reading. Biggest squad first — RosterPeople sorts it. --}}
        <div x-show="tab === 'clubs'" x-cloak class="mt-3 space-y-2.5 mobile-stagger">
            @forelse($clubs as $club)
                @php $cFlag = $flagClass($club['country']); @endphp

                <a @if($club['href']) href="{{ $club['href'] }}" @endif
                    x-show="match(@js($club['name']))"
                    class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3.5 {{ $club['href'] ? 'hover:shadow-md transition-shadow' : '' }}">

                    {{-- Sizing box only — no fill, no ring, no padding tile. --}}
                    <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                        @if($club['logo'])
                            <img src="{{ $club['logo'] }}" alt="" class="w-full h-full object-contain">
                        @else
                            <i class="bi bi-buildings text-2xl text-muted-foreground"></i>
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="font-black text-[15px] text-foreground leading-tight truncate">{{ $club['name'] }}</p>
                        <p class="text-xs text-muted-foreground mt-1 flex items-center gap-2">
                            @if($cFlag)
                                <span class="{{ $cFlag }} w-4 h-3 rounded-[2px] shrink-0"></span>
                            @endif
                            <span class="inline-flex items-center gap-1">
                                <i class="bi bi-person-arms-up"></i>
                                {{ trans_choice('personal.event_people_athletes', $club['athletes'], ['count' => $club['athletes']]) }}
                            </span>
                        </p>
                    </div>

                    @if($club['href'])
                        <i class="bi bi-chevron-right text-muted-foreground rtl:rotate-180 flex-shrink-0"></i>
                    @endif
                </a>
            @empty
                @include('personal.partials.event-people-empty', [
                    'icon' => 'bi-buildings',
                    'title' => __('personal.event_people_none_clubs'),
                    'body' => __('personal.event_people_none_clubs_sub'),
                ])
            @endforelse

            <div x-show="empty && tab === 'clubs'" x-cloak>
                @include('personal.partials.event-people-empty', [
                    'icon' => 'bi-search',
                    'title' => __('personal.event_people_no_matches'),
                    'body' => __('personal.event_people_no_matches_sub'),
                ])
            </div>
        </div>
    </div>
</div>
@endsection
