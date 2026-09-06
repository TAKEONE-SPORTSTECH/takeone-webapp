@php
    /* No folding here: this page exists to BE the entry list, so hiding
       most of it behind a button would be hiding the page. $pFold stays
       defined because the markup below asks for it. */
    $pRows = $e['participants']['rows'] ?? [];
    $pFold = false;
@endphp
{{-- This section lays out its OWN surface — section-mobile hands it the page
     gutters and no panel. The tab tray is a card, the entrants are cards; they
     are siblings rather than one nested in the other.

     Two tabs over one roster, the same pair the member's roster carries
     (personal/event-people): the competitors, and the clubs they came from.
     Same segmented tray, same search box, same client-side filtering — a
     stranger reading the entry list and a competitor reading it are looking at
     the same thing, so they should be looking at the same screen. --}}
@php $pClubs = $e['participants']['club_rows'] ?? []; @endphp
<div class="pb-4"
     x-data="{
        tab: 'athletes',
        q: '',
        match(...fields) {
            const s = this.q.trim().toLowerCase();
            return !s || fields.some(f => (f || '').toLowerCase().includes(s));
        },
        {{-- A list can only say 'nothing matched' if it knows every name on it.
             Filtering is client-side because every row is already on the page —
             typing narrows it with no round trip, and it can only ever hide rows
             the server already decided to publish. --}}
        names: {
            athletes: @js(collect($pRows)->map(fn ($p) => trim(($p['name'] ?? '').' '.($p['club'] ?? '')))->values()),
            clubs: @js(collect($pClubs)->pluck('name')->values()),
        },
        get empty() {
            return this.q.trim() !== '' && !(this.names[this.tab] || []).some(n => this.match(n));
        },
        {{-- The fold is off (see the top of this file), but the entrant rows
             still ask for `all`. --}}
        all: true,

        {{-- ===== The organiser's two doors =====
             A competitor's card leads somewhere different depending on who is
             reading it, and the organiser is the one reader for whom there are
             TWO somewheres: the member's real profile, which is theirs to see
             because they run the event, and the public one, which is what
             everybody else is looking at. Offered as a choice rather than
             guessed at, because "what does this look like to the world?" is a
             question an organiser asks constantly and had no way to answer
             without signing out. --}}
     }">

    {{-- ===== Tabs ===== A segmented tray, not an underline: it reads as a
         switch between two views of one roster, which is what it is. --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-1.5 flex gap-1.5" role="tablist">
        @foreach([
            ['key' => 'athletes', 'icon' => 'bi-person-arms-up', 'label' => __('personal.event_people_tab_athletes'), 'n' => $e['participants']['count'] ?? 0],
            ['key' => 'clubs', 'icon' => 'bi-buildings', 'label' => __('personal.event_people_tab_clubs'), 'n' => count($pClubs)],
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

    {{-- ===== Search ===== One box over both tabs. On the athletes tab it also
         matches the club name, because "who from Emperor is here" is the same
         question asked from the other end. --}}
    @if(count($pRows))
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

        {{-- ===== The entry list =====
             The platform's mobile people-card, as /admin/members draws it —
             admin/platform/members/_results-mobile, element for element: the
             gender accent rail, the portrait filling the card's full height on
             the leading edge, the name with its flag, the age-group chip beside
             the gender icon and the age, and the third line under them.

             ⚠️ The third line is the CLUB, where the members list prints a phone
             number. That list is behind the super-admin's door; this page is
             open to anybody holding the link, and an entry list is not a place
             to publish two hundred competitors' phone numbers — a great many of
             them minors. Everything else is the same card.

             The face is governed by the athlete's own `profile_picture_is_public`
             (App\Events\Support\PublicEvent::participants), exactly as the draw
             on this same page governs it. No picture is not a bug, and the
             absence is silent. --}}
    <div x-show="tab === 'athletes'" x-cloak class="mt-2.5 space-y-2.5">
        {{-- No profile-URL branching here any more: every card links to
             route('people.show'), which SealEventPage rewrites to the sealed
             mirror when the reader is inside the event app, so the white-label
             surface is never left. --}}

        @forelse($pRows as $i => $person)
                @php
                    /* Where this card goes, decided on the SERVER.
                       · organiser  → a choice of two profiles (the sheet below)
                       · signed in  → the safe public profile
                       · a stranger → the same, but only when it will actually
                                      open for them (PublicEvent::participants
                                      answers the anonymous rule) — a card that
                                      leads to a 404 is a dead end, and plenty of
                                      entrants are minors or came through the
                                      public door with discovery off. */
                    $pUuid = $person['uuid'] ?? null;
                    $pOpens = ($signedIn ?? false) || ($person['public_profile'] ?? false);
                    /* Straight to the PUBLIC profile, for everybody including an
                       organiser. This used to hand a manager a sheet asking
                       which of two profiles they meant — but this is the public
                       entry list, the same page a stranger reads, and a card on
                       it means one thing. The organiser's own screens are where
                       the full profile belongs.

                       `from=participants` so the profile's back control returns
                       HERE rather than to the organiser's entry list. */
                    $pHref = ($pUuid && $pOpens)
                        ? route('people.show', $pUuid).'?from=participants'
                        : null;

                    /* ⚠️ EVERY value the component tag uses is computed HERE, as
                       a plain variable. Blade's component-tag parser is not the
                       Blade compiler: it gives up on a directive or a literal
                       array inside an attribute list and prints the whole tag as
                       text on the page. Both mistakes were made getting this
                       card shared, and both look like "the component silently
                       did nothing". */
                    $pName = $person['name'];
                    $pPhoto = $person['photo'] ?? null;
                    $pGender = $person['gender'] ?? null;
                    $pCountry = $person['country'] ?? null;
                    $pAge = $person['age'] ?? null;
                    $pDivisions = array_filter([$person['division'] ?? null]);
                    $pClub = $person['club'] ?? null;
                    /* The crest, not just the club's name. `PublicEvent::participants`
                       has published it all along and this card was the one reader that
                       never asked for it, so all 27 entrants showed the generic
                       buildings glyph — including the 16 who compete for a club with a
                       logo on file. The component draws it bare (Design Rule #5). */
                    $pClubLogo = $person['club_logo'] ?? null;
                    $pChevron = (bool) $pHref;
                    $pLink = $pHref;
                    $pRun = null;

                    $pShow = 'match('.json_encode($pName).', '.json_encode($pClub ?? '').')'
                        .(($pFold && $i >= 12) ? ' && all' : '');

                @endphp

                {{-- The shared entrant card — the same component the organiser's
                     own "who's joined" screen renders, so the roster looks
                     identical wherever it is read. Only the behaviour differs:
                     a door for whoever may open it, a plain card otherwise. --}}
                <x-entrant-card :name="$pName" :photo="$pPhoto" :gender="$pGender"
                                :country="$pCountry" :age="$pAge" :divisions="$pDivisions"
                                :club-name="$pClub" :club-logo="$pClubLogo" :chevron="$pChevron"
                                :belt="$person['belt'] ?? null"
                                :href="$pLink" :pick="$pRun" :show="$pShow" />
        @empty
            {{-- The empty state is a card of its own now, because the list it
                 stands in for is a stack of cards on the page ground rather
                 than a block inside a panel. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('entry.public.partials.empty', [
                    'ev' => $ev, 'icon' => 'bi-people',
                    'title' => __('events.public_participants_empty_title'),
                ])
            </div>
        @endforelse

        {{-- Search found nothing. Distinct from an empty list: one means "try
             another spelling", the other means "nobody has entered". --}}
        <div x-show="empty" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100">
            @include('entry.public.partials.empty', [
                'ev' => $ev, 'icon' => 'bi-search',
                'title' => __('personal.event_people_no_matches'),
            ])
        </div>
    </div>

    {{-- ===== Clubs ===== Crest-forward: at a championship the crest is how a
         club is recognised across a hall, so it leads the row at a size worth
         reading. Biggest squad first — PublicEvent::participants sorts it.
         Not links: nothing on this public surface opens a club page. --}}
    <div x-show="tab === 'clubs'" x-cloak class="mt-2.5 space-y-2.5">
        @forelse($pClubs as $club)
            <div x-show="match(@js($club['name']))" x-cloak
                 class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3.5">

                {{-- Sizing box only — no fill, no ring, no padding tile
                     (Design Rule #5). --}}
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
                        @if($flagClass($club['country']))
                            <span class="{{ $flagClass($club['country']) }} w-4 h-3 rounded-[2px] shrink-0"></span>
                        @endif
                        <span class="inline-flex items-center gap-1">
                            <i class="bi bi-person-arms-up"></i>
                            {{ trans_choice('personal.event_people_athletes', $club['athletes'], ['count' => $club['athletes']]) }}
                        </span>
                    </p>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('entry.public.partials.empty', [
                    'ev' => $ev, 'icon' => 'bi-buildings',
                    'title' => __('personal.event_people_none_clubs'),
                ])
            </div>
        @endforelse
    </div>

    {{-- The "which profile?" sheet was removed 2026-09-05. On the public
         entry list a card leads to the public profile, for everyone. --}}

    @if($pFold)
        <button type="button" @click="all = !all"
                class="m-press w-full mt-3 h-11 rounded-xl border border-gray-200 bg-white text-xs font-bold
                       flex items-center justify-center gap-2"
                style="color: {{ $ev }};">
            <span x-show="!all">{{ __('events.public_show_all', ['n' => count($pRows)]) }}</span>
            <span x-show="all" x-cloak>{{ __('events.public_show_fewer') }}</span>
            <i class="bi" :class="all ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>
    @endif
</div>
