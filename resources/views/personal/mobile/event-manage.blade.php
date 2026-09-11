{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('personal.event_manage_title').' · '.$e['title'])

{{--
    Event console — mobile. The organiser's screen, and the appointed
    official's; personal/mobile/event-show is everybody else's.

    Shape: a mobile-first hub. A status band that says where the event stands,
    then grouped rows — one per job, each carrying what is waiting inside it, so
    "what still needs me?" is answerable without opening anything. Work with a
    screen of its own (roster, entries, draw, officials, board, verification) is
    a link; work small enough to finish here (preparations, documents, winners,
    the P&L) opens in place.

    Every row is gated: an official is appointed to a job, not handed the event.
    The endpoints behind each row re-check the same rule server-side — this page
    decides only what to OFFER.

    It runs on the SAME Alpine root as the public event page
    (partials/event-show-script), so cancel/delete/edit, the winners editor and
    the P&L are the implementations that already existed, not second copies.
--}}
@section('personal-content')
@php
    $mgColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
    $started = (bool) ($e['started'] ?? false);
    // The shared event Alpine root (included below) is the public page's root too,
    // so it reads the same locals. The console has no join flow, but the root is
    // one object — give it the values rather than fork it.
    /* An event can now be priced entirely out of OPTIONS, with no base fee at
       all ("Gi 15 / No-Gi 15"). Reading the base line alone then says "free"
       and the money sections of this page disappear, so the payload's own
       option list has a say too. */
    $pPaid   = (! str_contains(strtolower($e['participant_fee'] ?? ''), 'free') && ! str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified')) || ! empty($e['fees']['options']);
    $byQual  = str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified');
    $hasTicket = ! empty($e['spectator']);
    $ticketPaid = ($hasTicket && ! str_contains(strtolower($e['spectator']['fee'] ?? ''), 'free')) || ! empty($e['fees']['spectator_options']);
    $banned = false;                 // an organiser is never barred from their own console
    $eligReason = null;
    $whyNot = null;
    $cancelled = (bool) ($e['cancelled'] ?? false);

    // Jobs that live on a screen of their own. Rendered in the order the day
    // runs: who is coming, who is entered, how they are drawn, who officiates.
    $links = [];
    $drawTile = null;

    /* ===== The hall's own surfaces =====

       The run-day board and the scoring table are not console pages — they are
       the WALL and the TABLE, the same family as the screens and cameras that
       get paired to a mat. They used to sit in the console's tile column, two
       rows above the panel that manages exactly those devices.

       So they ride inside that panel now. Built here rather than in the
       component because only this page knows which of them exists: the board is
       organiser-only, and the scoring table is gated on `canScore` and on the
       sport HAVING a mat at all. */
    $hallShortcuts = [];

    if ($canManage) {
        $hallShortcuts[] = [
            'icon' => 'bi-tv-fill', 'tone' => 'bg-slate-100 text-slate-600',
            'label' => __('personal.event_manage_board'),
            'sub' => __('personal.event_manage_board_sub'),
            'href' => route('me.events.board', $e['key']),
            'external' => false,
        ];
    }

    // Gated on canScore, not canManage: an appointed official scores a mat they
    // cannot otherwise manage. It opens a full-screen console, so it leaves the
    // shell rather than swapping content inside it.
    if (! empty($scoringUrl)) {
        $hallShortcuts[] = [
            'icon' => 'bi-stopwatch-fill', 'tone' => 'bg-rose-50 text-rose-600',
            'label' => __('personal.event_manage_scoring'),
            'sub' => __('personal.event_manage_scoring_sub'),
            'href' => $scoringUrl,
            'external' => true,
        ];
    }

    /* ⚠️ NO verification tile, and no verification desk to send anybody to.
       The desk's own sheet — the weight, the payment, the receipt — now opens
       from a person's card on "Who's joined", which is where an official is
       already standing when they need it. Two doors to one job was one too
       many (removed 2026-09-03 at the user's request). */

    if ($canManage) {
        // Who is on the list. The console had no door to the roster at all:
        // the Entries row opens the ENTERING flow, and reading down the
        // entrants — which is where an organiser strikes names off — was
        // reachable only from the public event page.
        $links[] = [
            'icon' => 'bi-people-fill', 'tone' => 'bg-amber-50 text-amber-600',
            'label' => __('personal.event_show_whos_joined'),
            'sub' => trans_choice('personal.event_manage_entrants', $counts['entrants'], ['count' => $counts['entrants']]),
            'href' => route('me.events.people', $e['key']),
        ];
        /* ===== "Enter your athletes" — HIDDEN until it exists =====
         *
         * This tile is not another door to the roster. It is for a CLUB OWNER
         * who was invited by the organiser: they sign in, see their own club's
         * athletes, and enter a batch of them into the competition in one go.
         * That screen has not been built, and the tile had been quietly
         * repointed at the entry list, which is a different job — so it
         * promised something the tap could not deliver.
         *
         * Hidden on 2026-09-03 at the user's request ("for now this is not
         * needed, we will work on it later"). The strings it used
         * (`personal.event_manage_entries*`) are still in the language files,
         * waiting for the real screen.
         */
        /* The DRAW is not a run-day screen — it is cut from the divisions and
           arranged days before anybody arrives, which is why it now sits in
           "Setting it up" directly under the weight classes it comes out of
           (asked for 2026-09-06: "the weight classes must be with the draw in
           one group"). Built here so the lock wording stays with the rest of
           the tile logic; rendered in that group below. */
        $drawTile = [
            'icon' => 'bi-diagram-3-fill', 'tone' => 'bg-purple-50 text-purple-600',
            'label' => __('personal.event_manage_draw'),
            'sub' => $started ? __('personal.event_manage_draw_locked') : __('personal.event_manage_draw_sub'),
            'href' => route('me.events.bracket.manage', $e['key']),
        ];
        /* Officials moved to the "Setting it up" group (2026-09-06): choosing
           who runs the competition is preparation, not a run-day screen, and
           having it in both places meant two tiles to the one page. */
    }

    // The scoring table. Gated on canScore rather than canManage — an appointed
    // official scores a mat they cannot otherwise manage — and rendered only for
    // a sport that HAS a mat, so a type with no scoreboard shows no row.
    // Deliberately last: it is the run-day surface, and it opens a full-screen
    // console rather than a page inside the shell.
@endphp

{{-- The band is full-bleed, so the page wrapper's padding is cancelled here and
     restored by the cards below it. --}}
<div @include('partials.event-show-script') class="pb-4">

    {{-- ===== Header =====
         The page-header pattern (see CLAUDE.md → Page Headers): full-bleed
         m-hero band, event colour to colour+b0, two soft circles, a control row
         on top, then chips · title · owner beneath. Never a small rounded card
         with the title squeezed beside a back arrow. --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-8 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($mgColor, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline flex-shrink-0"
               aria-label="{{ __('personal.event_manage_back_to_page') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div class="flex items-center gap-2">
                {{-- ⚠️ "View public page" must open THE PUBLIC PAGE.
                     It said that and opened the MEMBER page (`me.events.show`).
                     The sealed branch was fixed; the platform branch was left
                     doing exactly what the note said was wrong, so an organiser
                     on /me/events/{uuid}/manage tapped "view public page" and
                     got their own admin view back. And it rendered with no
                     guard at all, so on a members-only event it offered a
                     preview of a page that does not exist. Both halves found by
                     a navigation audit, 2026-09-08.

                     One address now, for both surfaces — `$publicUrl` IS the
                     poster — shown only when there is a poster to show. On the
                     platform it opens BESIDE the console (the organiser is
                     mid-job and wants to keep their place); inside the event app
                     the poster is the app's own home, so it opens in place. --}}
                @if(($isPublic ?? false) && ! empty($publicUrl))
                    <a href="{{ $publicUrl }}"
                       @unless($sealed ?? false) target="_blank" rel="noopener" @endunless
                       title="{{ __('personal.event_manage_view_public') }}"
                       aria-label="{{ __('personal.event_manage_view_public') }}"
                       class="m-press ev-ico w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center no-underline">
                        <i class="bi bi-eye"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-sliders"></i> {{ __('personal.event_manage_title') }}
                </span>
                <span x-show="cancelled" x-cloak class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
                </span>
                @if($started)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-play-circle-fill"></i> {{ __('personal.event_start_running') }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-calendar-event"></i>{{ trim(($e['wday'] ?? '').' '.($e['day'] ?? '').' '.($e['mon'] ?? '')) }}
            </p>
        </div>
    </header>

    {{-- The cards start BELOW the band, never over it. They are controls — a
         button clipped by the header reads as broken, and half a tile sitting on
         the colour looks like a mistake rather than depth. The band's own bottom
         padding is sized to its content instead. --}}
    <div class="mt-4 relative z-10 space-y-4">

    {{-- ===== Preparations — the component brings its own button and sheet ===== --}}
    @if($canOfficiate)
        <x-event-checklist :event="$e['key']"
                           :items="$checklist ?? []"
                           :can-manage="$canManage"
                           :can-check="true"
                           :started="$started"
                           :overridden="$e['start_overridden'] ?? false"
                           :color="$mgColor" />
    @endif

        {{-- ===== Setting it up =====

             What decides WHO competes and under what: the divisions, the draw
             cut from them, and the clubs standing behind the event. Each has a
             screen of its own; the console is the door, not the room.

             They used to sit in "The event itself" beside the public link and
             the cover, which made that group a drawer of anything left over. --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_group_setup') }}</p>
            <div class="space-y-2">
                <a href="{{ route('me.events.divisions', $e['key']) }}" data-shell-link
                   class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3 no-underline">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-diagram-3 bracket-icon text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ __('events.divisions_page_title') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('events.divisions_console_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                </a>
                @if($drawTile)
                    {{-- The draw, immediately under the divisions it is cut from. --}}
                    <a href="{{ $drawTile['href'] }}" data-shell-link data-route="me.events"
                       class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3 no-underline">
                        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 {{ $drawTile['tone'] }}">
                            <i class="{{ \App\Support\Icon::bi($drawTile['icon'], 'text-lg') }}"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground truncate">{{ $drawTile['label'] }}</span>
                            <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ $drawTile['sub'] }}</span>
                        </span>
                        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                    </a>
                @endif
                {{-- Putting somebody on the list by hand. Organiser (and the
                     platform team) only — every other door into the entry list
                     belongs to somebody else: the athlete, their coach, or a
                     stranger following the public link. --}}
                <x-event-add-person :event="$e['key']"
                                    :divisions="$entryDivisions ?? []"
                                    :fees="$e['fees'] ?? []"
                                    :color="$mgColor"
                                    :title="$e['title']" />
                <a href="{{ route('me.events.clubs', $e['key']) }}" data-shell-link
                   class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3 no-underline">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-buildings-fill text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ __('events.clubs_title') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('events.clubs_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                </a>
            </div>
        </div>

    {{-- ===== On the day =====
         The package's own run-day screens — the draw, the roster, the weigh-in
         desk. It knows what it needs; the console only lists what it names. --}}
    @if(count($links))
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_group_onday') }}</p>
            <div class="space-y-2">
            @foreach($links as $row)
                <a href="{{ $row['href'] }}"
                   @if(empty($row['external'])) data-shell-link data-route="me.events" @endif
                   class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 {{ $row['tone'] }}">
                        <i class="{{ \App\Support\Icon::bi($row['icon'], 'text-lg') }}"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ $row['label'] }}</span>
                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $row['sub'] }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </a>
            @endforeach
            </div>
        </div>
    @endif

    {{-- ===== The hall's wiring — one row, opening the whole panel as a sheet.
              Screens for a type that drives any, and the cameras filming the
              mats either way: an event with no wall boards can still be filmed,
              so the panel opens for either fleet and simply omits the half that
              is not there. ===== --}}
    @if($canManage && (! empty($screens) || ! empty($cameras)))
        <x-court-screens :shortcuts="$hallShortcuts" :event="$e['key']"
                         :mats="$screens['mats'] ?? ($cameras['mats'] ?? [])"
                         :screens="$screens['screens'] ?? []"
                         :surfaces="$screenSurfaces ?? []"
                         :new-url="$screenNewUrl ?? null"
                         :cameras="$cameras['cameras'] ?? []"
                         :camera-max="$cameras['max'] ?? 4"
                         :color="$mgColor"
                         :sheet="true" />

        {{-- What those screens PLAY. Renders nothing on the page — only the sheet
             the panel's gear opens. --}}
        <x-event-screen-audio :event="$e['key']"
                              :media="$screenAudio ?? []"
                              :audio-url="$screenAudioUrls ?? []"
                              :color="$mgColor" />
    @endif



    @if($canManage)
        {{-- ===== Afterwards =====
             What is settled once the mats are packed away: the podium, the
             money, and the paperwork the event leaves behind. --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_group_after') }}</p>
            <div class="space-y-2">
            {{-- ===== Winners =====

                 Two different things behind one tile, decided by the TYPE and
                 not by this view: a type that allows a typed-in podium
                 (`manual_results`) opens the editor; a bracketed championship
                 derives its medals from the bouts and opens the read-only
                 podium instead — per division, gold/silver/bronze/bronze.
                 setResults() refuses hand-entry for those types server-side, so
                 offering the form there was offering a form the endpoint would
                 reject (2026-09-04). --}}
            <button type="button" @click="openResults()"
                    class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-trophy-fill text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground truncate">{{ __('personal.event_podium_title') }}</span>
                    @if($manual_results ?? true)
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate" x-text="results.length ? @js(__('personal.event_manage_results_recorded')) : @js(__('personal.event_manage_results_sub'))"></span>
                    @else
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">
                            {{ count($e['divisions'] ?? [])
                                ? __('personal.event_podium_decided', ['done' => count($e['bracket_results'] ?? []), 'total' => count($e['divisions'])])
                                : __('personal.event_podium_sub') }}
                        </span>
                    @endif
                </span>
                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </button>

            <button type="button" @click="financeOpen=true"
                    class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-green-50 text-green-600"><i class="bi bi-cash-stack text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground truncate">{{ __('personal.event_show_finance') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5 truncate" x-text="money(profit)"></span>
                </span>
                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </button>

            {{-- Documents — one row like the others, opening a sheet. The list and
                 its uploader used to sit open on the page, which put a file field
                 and a dashed drop box in the middle of a column of doors. --}}
            <x-event-documents :event="$e['key']" :documents="$documents ?? []"
                               :can-manage="true" :color="$mgColor" :sheet="true" />
            </div>
        </div>


        {{-- ===== The event itself ===== --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_the_event') }}</p>
            <div class="space-y-2">
                {{-- The page anybody may open, and the switch that publishes it.
                     The component owns the toggle, the link and the QR. --}}
                <x-event-public-link :event="$e['key']" :is-public="$isPublic ?? false"
                                     :url="$publicUrl" :color="$mgColor" :title="$e['title']" :auto-accept="$autoAccept ?? false" />

                {{-- The picture that page opens onto. Beside the switch on purpose:
                     there is no point choosing a face for a page nobody may open. --}}
                <x-event-cover :event="$e['key']" :photo="$coverPhoto ?? null"
                               :color="$mgColor" :title="$e['title']" :inline="true" />

                {{-- Strangers waiting to be let in. Renders nothing when nobody
                     is (EVENTS-PUBLIC-ENTRY.md, Phase C). --}}
                <x-event-entry-review :event="$e['key']" :entries="$publicEntries ?? []"
                                      :color="$mgColor" :title="$e['title']" />

                {{-- When the draw stops being the organiser's working copy and
                     becomes the sheet on the wall. Only for a type that draws
                     one — an event with no divisions has nothing to reveal. --}}
                @if(! empty($e['categories']))
                    <x-event-draw-visibility :event="$e['key']" :reveal="$drawReveal ?? 'always'"
                                             :date="$drawRevealDate ?? null"
                                             :color="$mgColor" :title="$e['title']" />
                @endif




                {{-- Every language this event is read in, and the organiser's
                     power to correct any of it. It appears here rather than in
                     the edit form because a translation is not a field of the
                     event — it is a copy of the whole thing, and it arrives on
                     its own when a visitor asks for it. --}}
                <x-event-languages :event="$e['key']" :color="$mgColor" :title="$e['title']"
                                   :source-locale="$sourceLocale ?? null" />

                <button type="button" @click="goEdit()"
                        class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-pencil-fill text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ __('personal.event_show_edit_event') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('personal.event_manage_edit_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </button>

                {{-- Package actions. They come from the event type, so a new type
                     brings its own without editing this page — and an action the
                     package does not offer today simply is not here.

                     ⚠️ EXCEPT the draw's own actions (build it, arrange it,
                     clear it). Those moved onto the draw screen on 2026-09-03:
                     pressing "create the draw" here left the organiser three
                     screens from the thing they had just created. The console
                     still has a tile that OPENS the draw; the verbs live with
                     the board. --}}
                @foreach(collect($actions)->reject(fn ($a) => str_contains($a['action'] ?? '', 'draw')) as $action)
                    <form method="POST" action="{{ route('me.events.action', [$e['key'], $action['action']]) }}"
                          data-event-action data-failed="{{ __('events.action_failed') }}">
                        @csrf
                        <button type="submit"
                                class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-primary/10 text-primary"><i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, 'text-lg', 'bi-lightning-charge') }}"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground truncate">{{ $action['label'] }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                        </button>
                    </form>
                @endforeach

                {{-- Who runs it. Moved here from "Setting it up" on 2026-09-06 at
                     the user's request: appointing officials is a property of the
                     event, like its public page and who may read the draw, rather
                     than a step in preparing the competition. Last in the group,
                     because it is the one that opens a screen of its own. --}}
                <a href="{{ route('me.events.officiating', $e['key']) }}" data-shell-link
                   class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3 no-underline">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-person-badge text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ __('personal.event_manage_officials') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('personal.event_manage_officials_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                </a>
            </div>
        </div>

        {{-- ===== Ending it ===== --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_danger') }}</p>
            <div class="space-y-2">
                <button type="button" x-show="! cancelled" @click="cancelEvent()"
                        class="m-press w-full text-start bg-white rounded-2xl border border-amber-200 p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-slash-circle text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-amber-700">{{ __('personal.event_show_cancel_event') }}</span>
                        <span class="block text-[11px] text-amber-600/80 mt-0.5">{{ __('personal.event_manage_cancel_sub') }}</span>
                    </span>
                </button>
                <button type="button" @click="deleteEvent()"
                        class="m-press w-full text-start bg-white rounded-2xl border border-red-200 p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-red-50 text-red-600"><i class="bi bi-trash text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-red-700">{{ __('personal.event_show_delete_event') }}</span>
                        <span class="block text-[11px] text-red-500/80 mt-0.5">{{ __('personal.event_manage_delete_sub') }}</span>
                    </span>
                </button>
            </div>
        </div>

        {{-- The two sheets, exactly as the public page used to carry them --}}
        @include(($manual_results ?? true) ? 'partials.event-results-sheet' : 'partials.event-podium-sheet')
        @include('partials.event-finance-sheet')
    @endif
    </div>{{-- /cards --}}
</div>
@include('partials.event-action-runner')
@endsection
