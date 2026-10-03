@php
    $oRows = $e['officials']['rows'] ?? [];
    $oClubs = $e['officials']['club_rows'] ?? [];
@endphp
{{-- The officiating sheet, drawn like the entry list (2026-10-02, the owner's
     request): the same segmented tray, the same search box, the same card.

     Two tabs over one sheet — the officials, and the clubs they came with —
     exactly as the entry list has athletes and clubs. Each official is an
     <x-participant-card>, so a referee and a competitor are the same object on
     this event; the card's chip carries the JOB instead of a division. Read-only
     for everybody: no href (an official has no public profile to open) and no
     organiser sheet. What the cards may show — no contact, no fee, a face only
     where it may be shown — is decided in PublicEvent::officials(). --}}
<div class="pb-4"
     x-data="{
        tab: 'officials',
        q: '',
        match(...fields) {
            const s = this.q.trim().toLowerCase();
            return !s || fields.some(f => (f || '').toLowerCase().includes(s));
        },
        names: {
            officials: @js(collect($oRows)->map(fn ($p) => trim(($p['name'] ?? '').' '.($p['job'] ?? '').' '.($p['club'] ?? '')))->values()),
            clubs: @js(collect($oClubs)->pluck('name')->values()),
        },
        get empty() {
            return this.q.trim() !== '' && !(this.names[this.tab] || []).some(n => this.match(n));
        },
     }">

    {{-- ===== Tabs ===== --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-1.5 flex gap-1.5" role="tablist">
        @foreach([
            ['key' => 'officials', 'icon' => 'bi-person-badge', 'label' => __('events::events.public_officials_tab'), 'n' => count($oRows)],
            ['key' => 'clubs', 'icon' => 'bi-buildings', 'label' => __('personal.event_people_tab_clubs'), 'n' => count($oClubs)],
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

    {{-- ===== Search ===== matches the name, the job and the club. --}}
    @if(count($oRows))
        <div class="relative mt-3">
            <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
            <input type="search" x-model="q"
                   :placeholder="tab === 'officials'
                        ? @js(__('events::events.public_officials_search'))
                        : @js(__('personal.event_people_search_clubs'))"
                   class="w-full ps-10 pe-10 py-2.5 text-sm bg-white border border-gray-100 rounded-xl shadow-sm focus:ring-2 focus:ring-primary focus:border-transparent">
            <button type="button" x-show="q" x-cloak @click="q = ''"
                    class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="{{ __('personal.event_people_search_clear') }}">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>
    @endif

    {{-- ===== Officials ===== --}}
    <div x-show="tab === 'officials'" x-cloak class="mt-2.5 space-y-2.5">
        @forelse($oRows as $person)
            @php
                // A plain variable: the component-tag parser gives up on
                // anything but plain attributes.
                $oShow = 'match('.json_encode($person['name']).', '.json_encode($person['job'] ?? '').', '.json_encode($person['club'] ?? '').')';
            @endphp
            <x-participant-card :person="$person" :color="$ev" :show="$oShow" chip-icon="bi-person-badge" />
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('events::entry.public.partials.empty', [
                    'ev' => $ev, 'icon' => 'bi-person-badge',
                    'title' => __('events::events.public_officials_empty_title'),
                ])
            </div>
        @endforelse

        <div x-show="empty" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100">
            @include('events::entry.public.partials.empty', [
                'ev' => $ev, 'icon' => 'bi-search',
                'title' => __('personal.event_people_no_matches'),
            ])
        </div>
    </div>

    {{-- ===== Clubs ===== the same crest-forward row as the entry list's. --}}
    <div x-show="tab === 'clubs'" x-cloak class="mt-2.5 space-y-2.5">
        @forelse($oClubs as $club)
            <div x-show="match(@js($club['name']))" x-cloak
                 class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3.5">
                {{-- Sizing box only — no fill, no ring (Design Rule #5). --}}
                <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                    @if($club['logo'])
                        <img src="{{ $club['logo'] }}" alt="" class="w-full h-full object-contain">
                    @else
                        <i class="bi bi-buildings text-2xl text-muted-foreground"></i>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-black text-[15px] text-foreground leading-tight">{{ $club['name'] }}</p>
                    <p class="text-xs text-muted-foreground mt-1 inline-flex items-center gap-1">
                        <i class="bi bi-person-badge"></i>
                        {{ trans_choice('events::events.public_officials_n', $club['officials'], ['count' => $club['officials']]) }}
                    </p>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('events::entry.public.partials.empty', [
                    'ev' => $ev, 'icon' => 'bi-buildings',
                    'title' => __('personal.event_people_none_clubs'),
                ])
            </div>
        @endforelse
    </div>
</div>
