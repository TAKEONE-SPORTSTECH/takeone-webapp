@php
    /* No folding here: this page exists to BE the entry list, so hiding
       most of it behind a button would be hiding the page. $pFold stays
       defined because the markup below asks for it. */
    $pRows = $e['participants']['rows'] ?? [];
    $pFold = false;
@endphp
<div x-data="{ all: true }">
    <div class="px-5 pt-2.5 pb-5">
        <p class="text-[13px] text-muted-foreground leading-relaxed">{{ ($e['participants']['count'] ?? 0) > 0 ? __('events.public_participants_lead') : __('events.public_participants_empty_body') }}</p>

        <div class="flex flex-wrap gap-1.5 mt-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold"
                  style="color: {{ $ev }}; background: {{ $evSoft }};">
                <i class="bi bi-people"></i>{{ trans_choice('events.public_draw_competitors', $e['participants']['count'] ?? 0, ['n' => $e['participants']['count'] ?? 0]) }}
            </span>
            @if($e['participants']['clubs'] > 0)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-muted text-muted-foreground">
                    <i class="bi bi-building"></i>{{ trans_choice('events.public_participants_clubs', $e['participants']['clubs'], ['n' => $e['participants']['clubs']]) }}
                </span>
            @endif
        </div>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
            @forelse($pRows as $i => $person)
                @php
                    /* The row's leading edge is the GENDER — the same blue/pink
                       the entrant card carries, so the two lists of the same
                       people read alike (asked for on 2026-09-06). An unknown
                       gender paints blue rather than nothing: most entrants are
                       put in by staff who are asked for a name and nothing else
                       (CLAUDE.md, "Who Fills The Form Decides"), so a blank is
                       the normal case, and an unpainted edge on two thirds of a
                       list would say nothing at all. */
                    $pIsMale = ($person['gender'] ?? 'Male') === 'Male';
                @endphp
                <div @if($pFold && $i >= 18) x-show="all" x-cloak @endif
                     class="relative overflow-hidden flex items-center gap-2.5 ps-4 pe-3 py-2 rounded-xl bg-muted/40">
                    {{-- `start-0` rather than `left-0`: read in Arabic too. --}}
                    <span class="absolute start-0 top-0 bottom-0 w-1.5"
                          style="background: {{ $pIsMale ? '#3b82f6' : '#ec4899' }};"
                          title="{{ $pIsMale ? __('club.gender_male') : __('club.gender_female') }}"
                          aria-label="{{ $pIsMale ? __('club.gender_male') : __('club.gender_female') }}"
                          role="img"></span>

                    @if($person['club_logo'])
                        {{-- A club mark is a transparent PNG of its
                             own shape: a sizing box, never a filled
                             tile (Design Rule #5). --}}
                        <span class="w-7 h-7 flex-shrink-0">
                            <img src="{{ $person['club_logo'] }}" alt="" class="w-full h-full object-contain">
                        </span>
                    @else
                        <span class="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 text-[11px] font-bold"
                              style="color: {{ $ev }}; background: {{ $evSoft }};">{{ Str::upper(Str::substr($person['name'], 0, 1)) }}</span>
                    @endif

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold text-foreground">{{ $person['name'] }}</span>
                        {{-- The rank leads the line and does not shrink: the
                             club name beside it truncates, and a half-written
                             rank is worse than a half-written club. --}}
                        <span class="flex items-center gap-1.5 text-[10.5px] text-muted-foreground">
                            <x-belt-chip :belt="$person['belt'] ?? null" class="flex-shrink-0" />
                            <span class="truncate">{{ $person['club'] ?: __('events.public_unattached') }}@if($person['division']) · {{ $person['division'] }}@endif</span>
                        </span>
                    </span>

                    @if($flagClass($person['country']))
                        <span class="{{ $flagClass($person['country']) }} flex-shrink-0"
                              style="width:20px; height:15px; border-radius:3px;" title="{{ $person['country'] }}"></span>
                    @endif
                </div>
            @empty
                <div class="sm:col-span-2">
                    @include('eventlab::entry.public.partials.empty', [
                        'ev' => $ev, 'icon' => 'bi-people',
                        'title' => __('events.public_participants_empty_title'),
                        'flush' => true,
                    ])
                </div>
            @endforelse
        </div>

        @if($pFold)
            <button type="button" @click="all = !all"
                    class="w-full mt-3 h-11 rounded-xl border border-gray-200 bg-white text-xs font-bold
                           flex items-center justify-center gap-2 hover:bg-muted/50 transition-colors"
                    style="color: {{ $ev }};">
                <span x-show="!all">{{ __('events.public_show_all', ['n' => count($pRows)]) }}</span>
                <span x-show="all" x-cloak>{{ __('events.public_show_fewer') }}</span>
                <i class="bi" :class="all ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>
        @endif
    </div>
</div>
