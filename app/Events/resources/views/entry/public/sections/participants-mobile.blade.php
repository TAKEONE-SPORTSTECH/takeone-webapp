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

             The PARTICIPANT CARD, ported from drafts/participant-card.html and
             used as drawn — the markup, the class names and the data-*
             contract are the draft's, and nothing of the card that stood here
             before it survives (the user's instruction, 2026-09-16). The
             previous card was <x-entrant-card>. The organiser's own roster
             drew that one too until 2026-09-17, when it moved onto THIS
             component — so there is one entrant card on the platform again,
             and the roster adds its powers through the component's seams
             (can-edit, can-remove, the trailing slot) rather than by keeping
             a second card alive.

             ⚠️ THE CARDS DO NOT LEAVE THE EVENT. `events.public.section` is not
             one of the mirrored `me/events/…` routes, so SealEventPage never
             rewrites this response and a `/people/{uuid}` link here would drop
             a reader into the full platform — one tap out of the event app, and
             the hole that was closed on 2026-09-08. Every destination below is
             therefore built from a SEALED route (`sealed.people.show`, the
             event's own draw page) or it is not offered at all. --}}
    <div x-show="tab === 'athletes'" x-cloak class="mt-2.5 space-y-2.5">

        @forelse($pRows as $i => $person)
            @php
                /*
                 * Where a card goes, decided on the SERVER.
                 *
                 * The public profile inside this event's own skin, and only
                 * where it will actually open: `sealed.people.show` answers a
                 * stranger with 404 for a member who is not discoverable or is a
                 * minor (PeopleController), and a card that leads to a 404 is a
                 * dead end — plenty of entrants are one or the other. A signed-in
                 * reader gets the wider rule, so the flag is only consulted for a
                 * guest.
                 */
                $pUuid = $person['uuid'] ?? null;
                /* `from=participants` so the profile's own back control returns
                   to THIS list rather than to the organiser's entry list or the
                   poster — PeopleController::sealedBackUrl reads the key. It is
                   a KEY, never a URL: a destination taken from the query string
                   is an open redirect with extra steps. */
                $pHref = ($pUuid && ($signedIn || ($person['public_profile'] ?? false)))
                    ? route('sealed.people.show', ['event' => $e['key'], 'uuid' => $pUuid]).'?from=participants'
                    : null;

                /* The same client-side filter the search box drives. Every row
                   is already on the page, so typing narrows it with no round
                   trip, and it can only ever hide what the server published. */
                $pShow = 'match('.json_encode($person['name']).', '.json_encode($person['club'] ?? '').')';

                $pReady = $readiness[$pUuid] ?? null;
                $pEntry = $deskEntries[$pUuid] ?? null;
                /* Where a division chip goes, or null when the draw is not out
                   and the chip should say so instead. */
                $pDrawUrl = $drawOpen
                    ? $addr->section('draw')
                    : null;
            @endphp

            {{-- ⚠️ EVERY value on this tag is a plain variable computed above.
                 Blade's component-tag parser is not the Blade compiler: it
                 gives up on a directive or a literal array inside an attribute
                 list and prints the whole tag as text on the page. --}}
            <x-participant-card :person="$person" :ready="$pReady" :href="$pHref"
                                :manage="$canManage" :event="$e['key']" :entry="$pEntry"
                                :draw-url="$pDrawUrl" :draw-note="$drawNote"
                                :color="$ev"
                                :show="$pShow" />
        @empty
            {{-- The empty state is a card of its own now, because the list it
                 stands in for is a stack of cards on the page ground rather
                 than a block inside a panel. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                @include('events::entry.public.partials.empty', [
                    'ev' => $ev, 'icon' => 'bi-people',
                    'title' => __('events::events.public_participants_empty_title'),
                ])
            </div>
        @endforelse

        {{-- Search found nothing. Distinct from an empty list: one means "try
             another spelling", the other means "nobody has entered". --}}
        <div x-show="empty" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100">
            @include('events::entry.public.partials.empty', [
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
                @include('events::entry.public.partials.empty', [
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
            <span x-show="!all">{{ __('events::events.public_show_all', ['n' => count($pRows)]) }}</span>
            <span x-show="all" x-cloak>{{ __('events::events.public_show_fewer') }}</span>
            <i class="bi" :class="all ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>
    @endif
</div>

{{-- ===== The verification desk =====
     Rendered for an official or the organiser, and for nobody else. It is the
     SHEET only: the list on this page is the participant cards above, and the
     desk opens over the card that was tapped (see onVerify). --}}
@if($desk)
    @include('events::entry.public.sections.partials.verification-desk', $desk)
@endif

