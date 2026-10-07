@props([
    /* ── REQUIRED ─────────────────────────────────────────────────────────
       The event, as a uuid. Every address the stage builds names it. */
    'event',

    /* What the top bar says it is showing, and where its back arrow goes. A
       stage with nowhere to go back to simply draws no arrow. */
    'title' => '',
    'backUrl' => null,
    'backLabel' => null,

    /* The event's own colour, for the sheets the group tools open. Hex only
       and whitelisted below — it is organiser-supplied and lands in a `style`. */
    'color' => null,

    /* ── WHO IS READING, AND WHAT THEY MAY DO ─────────────────────────────

       Two permissions, not one, because they are two different powers:

         canManage  — build the draw, clear it, make and delete GROUPS. The
                      organiser's. Draws the whole three-dot menu; false means
                      the menu is absent, not disabled.
         canArrange — move competitors between slots on the board. An appointed
                      official has this without canManage, and a draw locks it
                      the moment the event starts.

       BOTH FALSE IS THE ORDINARY CASE, not a degraded one: a competitor
       looking for their own bout and a visitor reading the wall sheet get this
       same stage, the same board, the same division menu — and no controls.
       That is the whole reason this is a component (owner's instruction,
       2026-09-17).

       ⚠️ Neither is a security boundary on its own. Every endpoint behind the
       board and the menu re-authorises for itself; these decide what is
       OFFERED, which is a different job (CLAUDE.md → authorise every sensitive
       action server-side, and never trust what the UI chose to draw). */
    'canManage' => false,
    'canArrange' => false,

    /* The draw's own verbs for the menu — `[['action' =>, 'label' =>, 'icon'
       =>], …]` as the event type's availableActions() produces them. The
       package decides whether each may run today; this is only where they are
       offered, and they are dropped entirely for a reader who may not manage. */
    'drawActions' => [],

    /* This reader's own entries, so the board can mark their bouts. Safe for
       everybody: they are the reader's own. */
    'myCompetitorIds' => [],

    /* Open on one division — the deep link an organiser or a division chip
       arrives with. Null opens on whatever the board decides. */
    'division' => null,

    /* ── WHICH DOOR THE BOARD READS THROUGH ───────────────────────────────

       Default: the member's own feed, behind auth, which hands an organiser
       their working view (the entrants bench, the arrange permission).

       The PUBLIC event page passes its own door instead — the wall sheet, which
       is read-only by construction, carries no bench and no faces and nobody's
       payment state, and answers a reader who is not signed in at all. That is
       why this is a prop and not a route call inside the component: the two
       doors exist precisely because they must return different things, and a
       stage that could only read one of them could only ever serve one
       audience. Both re-check the draw's visibility for themselves. */
    'dataUrl' => null,

    /* ── WHICH DOOR THE BOARD WRITES THROUGH ─────────────────────────────
       Null (the default) is the member routes, exactly as before. A host on
       ANOTHER door — the scoring tablet (App\Events\Support\TabletDraw),
       whose writes are authorised by a PIN rather than a session — passes its
       own. `clearUrl` false hands the board no clear address at all. */
    'arrangeUrl' => null,
    'clearUrl' => null,
    'admitUrl' => null,
    'outcomeUrl' => null,
    'entrantUrl' => null,
    /* Whether the BOARD offers the organiser's bout tools (decide a winner,
       a score, correct a name) — separately from the stage's own editing menu,
       which `canManage` draws. Null follows `canManage`, as before; the scoring
       tablet turns the board's tools on under a PIN without the menu, whose
       verbs run on member routes it cannot use. */
    'boardCanManage' => null,

    /* A short, stable name, so two stages on one page cannot collide over an
       element id. Only one is ever needed in practice. */
    'id' => 'draw',
])

@php
    $ev = $event;

    /* ⚠️ The id reaches a DOM id AND a JS string. Anything that is not a plain
       name is refused rather than escaped. */
    $sid = preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', (string) $id) ? $id : 'draw';

    /* Hex or the platform's purple — every colour that reaches a `style`
       attribute goes through here first. */
    $dsColor = \App\Support\Palette::safe($color);

    /* Arranging without the right to manage is legitimate (an appointed
       official), and managing without arranging is legitimate too (an organiser
       who arrives before there is a draw to move). Only the verbs list is
       conditional on both. */
    $drawActions = $canManage ? $drawActions : [];

    $backLabel = $backLabel ?: __('personal.event_manage_title');
@endphp

{{--
    THE DRAW, FULL SCREEN. `x-draw-stage`

    The board with nothing else on the screen: a 56px working bar, the bracket
    filling every remaining pixel, and a division menu floating on the canvas.
    On a laptop that is the difference between seeing two rounds and seeing all
    of them.

    ── One stage, two readers ────────────────────────────────────────────────

    It was a page — personal/event-manage-draw — that only an organiser could
    open; a competitor asking "where is my bout" was sent to a different screen
    with a different shape. It is a component now (owner's instruction,
    2026-09-17) and the reader decides what is on it, not which URL they used:
    `canManage` draws the editing menu, `canArrange` turns the board's arrange
    mode on, and neither is the ordinary read-only stage.

    ── Standalone (CLAUDE.md → Components Are Standalone) ────────────────────

    It brings its own viewport-sizing script and leans on two components that
    bring their own everything: `x-tournament-bracket` (the board, its realtime,
    its arrange mode) and `x-division-groups` (the menu and both its sheets). It
    covers the app chrome entirely, so a host needs no layout of its own —
    only `@stack('styles')` and `@stack('scripts')`.

    One device, deliberately: the board is already responsive and arranging a
    draw is the same job on a phone as on a desktop, so there is no mobile
    twin to keep in step.

    ── Where it is used ──────────────────────────────────────────────────────

      personal/event-manage-draw          — /me/events/{uuid}/brackets/manage,
                                            and its sealed mirror
                                            /e/{uuid}/admin/brackets/manage.
      entry/public/sections/draw-mobile   — /e/{uuid}/draw, read-only, through
                                            the public door.
--}}

{{-- ⚠️ `height` is set TWICE on purpose, and the board below fills it.

     `fixed inset-0` (and `100vh`) resolve against the LARGE viewport on a
     phone — the one that assumes the browser's toolbar is retracted. With the
     toolbar showing, the bottom of this page sits underneath it, and what is
     pinned to that bottom edge (the zoom cluster, the legend) sinks out of
     reach (reported 2026-09-06). `100dvh` is the viewport as it actually is
     right now; the `100vh` line before it is the fallback for a browser that
     does not know `dvh`, and CSS drops the declaration it cannot parse. --}}
<div id="{{ $sid }}-stage" class="ev-app-fixed fixed inset-0 z-[60] bg-background flex flex-col"
     style="height: 100vh; height: 100dvh;"
     x-data="{
        divisions: [], division: null, arrange: false, open: false,
        target: @js($division),

        /* What the Actions menu ticks. Read off the board's own events rather
           than kept here, so the menu can never disagree with the board about
           which layout is on (see bracket:state in the runtime). */
        layout: 'linear', third: 'match', view: 'auto', edit: false, background: false,
        unlocked: false, unlockable: false,
        settings(d) {
            if (d.unlocked !== undefined) this.unlocked = d.unlocked;
            if (d.unlockable !== undefined) this.unlockable = d.unlockable;
            if (d.layout !== undefined) this.layout = d.layout;
            if (d.third !== undefined) this.third = d.third;
            if (d.view !== undefined) this.view = d.view;
            if (d.edit !== undefined) this.edit = d.edit;
            if (d.background !== undefined) this.background = d.background;
        },
        get selected() { return this.divisions.find(d => d.id === this.division) ?? null; },

        // Gender is not a column on a division — it lives in the name, so it is
        // read back out here. This MIRRORS AbstractCombatSport::genderWord(),
        // which writes every division name:
        //
        //     'female' => 'Women', everything else => 'Men'
        //
        // Binary and total, exactly like the producer: a division is always one
        // gender or the other, never mixed. Names are generated (never typed by
        // hand) and genderWord() always emits English, so matching 'Women' is
        // enough — no locale variants to chase. Tested with a word boundary
        // because 'men' is a substring of 'women'.
        genderOf(name) {
            return /\bwomen\b/i.test(name || '') ? 'f' : 'm';
        },
        genderIcon(name) {
            return this.genderOf(name) === 'f' ? 'bi-gender-female' : 'bi-gender-male';
        },
        genderColor(name) {
            return this.genderOf(name) === 'f' ? 'text-pink-500' : 'text-blue-500';
        },

        onLoaded(d) {
            this.settings(d);
            this.divisions = d.divisions; this.division = d.division;
            // Deep-link: open on the division the organiser came from.
            if (this.target && d.divisions.some(x => String(x.id) === String(this.target))) {
                window.BracketBoard.show(this.target);
                this.target = null;
            }
        },
        onState(d) { this.settings(d); this.arrange = d.arrange; this.divisions = d.divisions; this.division = d.division; },
     }"
     @bracket:loaded="onLoaded($event.detail)"
     @bracket:state="onState($event.detail)">

    {{-- ===== Top bar: leave, and what you are arranging =====

         TWO SHAPES, decided by whether the host handed over a `controls` row.

         · WITHOUT one — the organiser's stage. A white working bar: back, what
           this screen is, the event, and the editing menu on the trailing edge.

         · WITH one — the public event's own band controls, which must go on
           over the board rather than under it (owner's instruction,
           2026-09-17). The stage covers the app chrome by design, so a row left
           in the band behind it is a row nobody can reach.

           The bar then wears the EVENT'S OWN GRADIENT, and that is not
           decoration: `.ev-ctl` is a white glyph on a translucent white circle,
           drawn for a coloured band (entry/partials/skin-style). On a white bar
           it is white on white — invisible. Colour is what makes the row
           legible, and it is the same gradient the band above it uses, so the
           reader sees the band they were already looking at rather than a
           second chrome.

           The stage's own back arrow and title step aside there: the row
           carries a back control of its own, and two of them in a 56px bar is
           the drift this component exists to prevent. --}}
    @php $dsBanded = isset($controls); @endphp
    {{-- ⚠️ THE WORKING BAR IS NAVY NOW (2026-09-20).

         The board below it is the Combat Sports Bracket's navy hall, and a
         white bar on top of it read as a strip of some other page. It wears
         the draft's own chrome instead: the ink gradient, a gold hairline
         under it, a gold eyebrow and a white title. The banded shape (the
         public event's own band controls) is untouched — that bar belongs to
         the EVENT's colour, which is a different answer to a different
         question. --}}
    <header class="h-14 shrink-0 flex items-center gap-3 px-3 sm:px-4 text-white {{ $dsBanded ? 'bk-stage-banded' : 'bk-stage-bar' }}"
            @if($dsBanded) style="background: linear-gradient(150deg, {{ $dsColor }}, {{ $dsColor }}b0);" @endif>

        @if($dsBanded)
            {{ $controls }}
        @else
        {{-- ⚠️ Back goes where you CAME FROM: the console. This screen is
             linked from exactly one place — the console's draw tile — and the
             arrow used to open `me.events.bracket`, the read-only board, which
             is neither where you were nor what you were doing.

             ICON ONLY here, and a tail-less chevron (asked for on 2026-09-04).
             Design Rule #6's labelled back pill governs the HERO BAND that
             opens a page; this is a 56px working bar on a full-screen board,
             where the words were competing with the event title beside them.
             The destination still says itself through `title` / `aria-label`.

             Inside the sealed event app the console is /e/{uuid}/admin/manage;
             on the platform it is the member console. --}}
        @if($backUrl)
            <a href="{{ $backUrl }}"
               class="bk-stage-back shrink-0 w-9 h-9 rounded-full border flex items-center justify-center
                      no-underline transition-colors"
               title="{{ $backLabel }}" aria-label="{{ $backLabel }}">
                <i class="bi bi-chevron-left rtl:rotate-180 text-base"></i>
            </a>
        @endif

        <div class="min-w-0">
            {{-- What this screen IS, and it is not the same sentence for
                 both readers: an organiser is managing the draw, everybody
                 else is reading it. --}}
            <p class="bk-stage-eyebrow text-[10px] font-extrabold uppercase tracking-[0.14em] leading-none">
                {{ $canManage || $canArrange
                    ? __('personal.personal_event_bracket_manage_draw')
                    : __('events::events.public_draw') }}
            </p>
            <h1 class="text-sm font-bold text-white truncate leading-tight mt-0.5">{{ $title }}</h1>
        </div>

        @endif

        {{-- ===== EDITING, AND ONLY FOR SOMEBODY WHO MAY =====

             Everything in this cluster writes: building the draw, clearing it,
             making and deleting groups. A participant or a visitor reading the
             board is handed none of it — not greyed, ABSENT, because a control
             that would refuse them is worse than no control, and the shape of
             an organiser's tools has no business in a stranger's page source.

             The endpoints behind every verb re-authorise for themselves; this
             only decides what is OFFERED. --}}
        @if($canManage)
            <div class="ms-auto shrink-0 flex items-center gap-2">

                {{-- ===== THE PADLOCK =====

                     This is the MANAGEMENT view of a draw, and management is
                     not the same as "everything is editable the moment you
                     open it" (owner's instruction, 2026-09-20). A draw on an
                     event morning is read far more often than it is changed,
                     and every editable thing on it — a draggable competitor, a
                     tappable corner, a score box — is something a sleeve or a
                     thumb alters by accident while somebody is only looking.

                     So the board opens LOCKED and this one control turns the
                     whole of it on: re-seeding the draw, declaring a winner,
                     typing a score, correcting a name. One switch, because
                     that is what the word means — a padlock that opened three
                     of four things would be a puzzle, not a catch.

                     ⚠️ It GUARDS, it does not GRANT. Every endpoint behind
                     every one of those acts asks the server for the same
                     authority it always did; this decides what may be touched
                     on screen, not what may be done. --}}
                @unless($dsBanded)
                <button type="button" x-show="unlockable" x-cloak
                        @click="window.BracketBoard.setUnlocked(! unlocked)"
                        :aria-pressed="unlocked"
                        :class="unlocked ? 'bk-lock is-open' : 'bk-lock'"
                        class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-bold
                               transition-all active:scale-95"
                        :title="unlocked ? @js(__('events::events.bracket_lock')) : @js(__('events::events.bracket_unlock'))">
                    <i class="bi" :class="unlocked ? 'bi-unlock-fill' : 'bi-lock-fill'"></i>
                    <span x-text="unlocked ? @js(__('events::events.bracket_unlocked')) : @js(__('events::events.bracket_locked_word'))"></span>
                </button>
                @endunless

                {{-- ===== ONE MENU FOR EVERYTHING THAT IS NOT THE PADLOCK =====

                     The group tools (make a division, rename one, fill one)
                     were here already; the draw's verbs and the board's own
                     settings joined them on 2026-09-20 at the owner's
                     instruction, so the bar carries exactly two controls: the
                     padlock, and this.

                     `x-division-groups` still owns its own rows, its two
                     sheets and every request it makes; the `actions` slot is
                     the host's half of the same menu and is where all of this
                     lives. --}}
                <x-division-groups :event="$ev" :can-manage="$canManage"
                                   :color="$dsColor" :as-menu="true">
                    <x-slot:actions>
                        <div class="bk-menu">

                            @if($dsBanded)
                                {{-- BANDED: the bar holds back, the title and
                                     this ONE menu (2026-10-02, the owner's
                                     request — four controls were eating the
                                     event's name). The padlock leads it,
                                     because everything below it waits on it. --}}
                                <button type="button" role="menuitem" class="bk-menu-row" x-show="unlockable" x-cloak
                                        @click="window.BracketBoard.setUnlocked(! unlocked)"
                                        :aria-pressed="unlocked">
                                    <i class="bi" :class="unlocked ? 'bi-unlock-fill' : 'bi-lock-fill'"></i>
                                    <span x-text="unlocked ? @js(__('events::events.bracket_lock')) : @js(__('events::events.bracket_unlock_short'))"></span>
                                </button>
                                <div class="bk-menu-rule" x-show="unlockable" x-cloak></div>
                            @endif

                            @if($canManage && ! empty($drawActions))
                                <p class="bk-menu-head">{{ __('events::events.bracket_the_draw') }}</p>
                                @foreach($drawActions as $action)
                                    {{-- Admitting a late athlete needs to know
                                         WHO, so it opens the window instead of
                                         posting. --}}
                                    @if(($action['action'] ?? null) === 'admit_late_entry')
                                        <button type="button" role="menuitem" class="bk-menu-row"
                                                @click="$dispatch('bracket-add:open')">
                                            <i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, '', 'bi-person-plus') }}"></i>
                                            <span>{{ $action['label'] }}</span>
                                        </button>
                                        @continue
                                    @endif
                                    <form method="POST" action="{{ route('me.events.action', [$ev, $action['action']]) }}"
                                          data-event-action data-after="bracket"
                                          data-failed="{{ __('events::events.action_failed') }}">
                                        @csrf
                                        {{-- WHICH division the verb acts on —
                                             kept in step with the board by the
                                             delegated listener at the foot of
                                             this file. --}}
                                        <input type="hidden" name="category_id"
                                               value="{{ $division }}" data-ds-division>
                                        <button type="submit" role="menuitem" class="bk-menu-row">
                                            <i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, '', 'bi-lightning-charge') }}"></i>
                                            <span>{{ $action['label'] }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            @endif


                            @if(\App\Events\Support\BracketPdf::available())
                                {{-- The draw on paper — the board itself, printed
                                     ink-light on A4 (App\Events\Support\BracketPdf).
                                     High in the menu on purpose: at the foot it
                                     sat below a phone's screen edge. The route
                                     re-authorises; this only decides what is
                                     offered. --}}
                                <p class="bk-menu-head">{{ __('events::events.bracket_pdf_head') }}</p>
                                <button type="button" role="menuitem" class="bk-menu-row"
                                        @click="window.drawStagePdf(@js(route('me.events.bracket.pdf', $ev)), true)">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <span>{{ __('events::events.bracket_pdf_this') }}</span>
                                </button>
                                <button type="button" role="menuitem" class="bk-menu-row"
                                        @click="window.drawStagePdf(@js(route('me.events.bracket.pdf', $ev)), false)">
                                    <i class="bi bi-files"></i>
                                    <span>{{ __('events::events.bracket_pdf_all') }}</span>
                                </button>
                            @endif

                            <p class="bk-menu-head">{{ __('events::events.bracket_view') }}</p>
                            <div class="bk-seg">
                                <button type="button" class="bk-seg-b" :class="view === 'auto' && 'is-on'"
                                        @click="window.BracketBoard.setView('auto')">{{ __('events::events.bracket_view_auto') }}</button>
                                <button type="button" class="bk-seg-b" :class="view === 'desktop' && 'is-on'"
                                        @click="window.BracketBoard.setView('desktop')">{{ __('events::events.bracket_view_desktop') }}</button>
                                <button type="button" class="bk-seg-b" :class="view === 'mobile' && 'is-on'"
                                        @click="window.BracketBoard.setView('mobile')">{{ __('events::events.bracket_view_mobile') }}</button>
                            </div>

                            <p class="bk-menu-head">{{ __('events::events.bracket_layout') }}</p>
                            <div class="bk-seg">
                                <button type="button" class="bk-seg-b" :class="layout === 'linear' && 'is-on'"
                                        @click="window.BracketBoard.setLayout('linear')">{{ __('events::events.bracket_layout_linear') }}</button>
                                <button type="button" class="bk-seg-b" :class="layout === 'split' && 'is-on'"
                                        @click="window.BracketBoard.setLayout('split')">{{ __('events::events.bracket_layout_split') }}</button>
                            </div>

                            <p class="bk-menu-head">{{ __('events::events.bracket_third') }}</p>
                            <div class="bk-seg">
                                <button type="button" class="bk-seg-b" :class="third === 'match' && 'is-on'"
                                        @click="window.BracketBoard.setThird('match')">{{ __('events::events.bracket_third_match') }}</button>
                                <button type="button" class="bk-seg-b" :class="third === 'shared' && 'is-on'"
                                        @click="window.BracketBoard.setThird('shared')">{{ __('events::events.bracket_third_shared') }}</button>
                            </div>

                            <div class="bk-menu-rule"></div>

                            @if($canManage)
                                {{-- The three rows that need the padlock open.
                                     Shown always and DISABLED while it is shut,
                                     rather than hidden: a control that appears
                                     and disappears teaches nobody where it is,
                                     and the padlock is right there. --}}
                                <button type="button" role="menuitem" class="bk-menu-row is-gold"
                                        :disabled="! unlocked" :title="unlocked ? '' : @js(__('events::events.bracket_unlock_first'))"
                                        @click="$dispatch('bracket-add:open')">
                                    <i class="bi bi-person-plus"></i>
                                    <span>{{ __('events::events.bracket_add_fighter') }}</span>
                                </button>

                                <button type="button" role="menuitem" class="bk-menu-row"
                                        :disabled="! unlocked" :title="unlocked ? '' : @js(__('events::events.bracket_unlock_first'))"
                                        @click="window.BracketBoard.toggleEdit()">
                                    <i class="bi bi-pencil"></i>
                                    <span x-text="edit ? @js(__('events::events.bracket_edit_done')) : @js(__('events::events.bracket_edit_names'))"></span>
                                </button>
                            @endif

                            <button type="button" role="menuitem" class="bk-menu-row"
                                    @click="window.BracketBoard.pickBackground()">
                                <i class="bi bi-image"></i>
                                <span>{{ __('events::events.bracket_background') }}</span>
                            </button>

                            <button type="button" role="menuitem" class="bk-menu-row" x-show="background" x-cloak
                                    @click="window.BracketBoard.clearBackground()">
                                <i class="bi bi-x-circle"></i>
                                <span>{{ __('events::events.bracket_background_clear') }}</span>
                            </button>

                            @if($canManage && \App\Events\Support\TabletDraw::enabled())
                                <button type="button" role="menuitem" class="bk-menu-row"
                                        @click="window.dispatchEvent(new CustomEvent('draw-pin:open'))">
                                    <i class="bi bi-tablet-landscape"></i>
                                    <span>{{ __('events::events.tablet_pin_menu') }}</span>
                                </button>
                            @endif


                            @isset($menuExtra)
                                {{-- The host's own rows — on the public event,
                                     what its band's gear and ⋯ menu held. --}}
                                <div class="bk-menu-rule"></div>
                                {{ $menuExtra }}
                            @endisset
                        </div>
                    </x-slot:actions>
                </x-division-groups>
            </div>
        @endif
    </header>

    {{-- ===== The board — every pixel that is not the top bar =====
         No padding anywhere: the canvas runs to all four edges. `bare` (not
         `bleed`) because this page has no gutters to escape from — bleeding here
         would hang the board off the viewport. It fills what the flex column
         leaves it, so the bar's height is nobody else's business. --}}
    <div class="flex-1 min-h-0 relative">
        <x-tournament-bracket
            :id="$sid.'-bracket'"
            :data-url="$dataUrl ?: route('me.events.bracket.data', $ev)"
            {{-- The two WRITE addresses, and only for an EDITOR.

                 A read-only stage is handed neither: the endpoints re-authorise
                 anyway, but an address nobody may use is an address that should
                 not be in their page source.

                 Gated on "may manage OR may arrange" rather than on canArrange
                 alone, deliberately — an organiser whose draw is locked had
                 both URLs before this was a component, and narrowing that to
                 canArrange would quietly change what a working screen hands its
                 board. The board decides what to DRAW from `can-arrange`; these
                 are only what it may reach for. --}}
            :arrange-url="($canManage || $canArrange) ? ($arrangeUrl ?: route('me.events.bracket.arrange', $ev)) : null"
            :clear-url="($canManage || $canArrange) && $clearUrl !== false ? ($clearUrl ?: route('me.events.bracket.clear', $ev)) : null"
            :admit-url="$admitUrl"
            :event-uuid="$ev"
            :can-arrange="$canArrange"
            {{-- Deciding a bout, typing a score and correcting a name on the
                 card are the ORGANISER's — a different permission from
                 arranging. The board re-reads the server's own answer before
                 it draws any of them; this is what the host knows. --}}
            :can-manage="$boardCanManage ?? $canManage"
            :outcome-url="$outcomeUrl"
            :entrant-url="$entrantUrl"
            :my-competitor-ids="$myCompetitorIds"
            :show-divisions="false"
            {{-- Fill the flex slot rather than computing a height from the
                 viewport: the wrapper above already IS the viewport, and a
                 calc() that has to know the bar's height is one edit away from
                 being wrong. --}}
            height="100%"
            :fill="true"
            bare />

        {{-- ⛳ TAP A BOUT, SAY WHO WON.

             Rendered only for somebody who may MANAGE the event — the same
             authority `me.events.outcome` asks for, so the sheet is offered to
             exactly the people the endpoint would answer. An appointed official
             who may arrange the draw but not manage the event does not get it,
             and neither does a competitor reading their own bracket.

             It listens for the board's own `bracket:match`, which has been
             dispatched since the board was written and had never had a
             listener. The board is unchanged by this. --}}
        @if($canManage)
            <x-bout-outcome-sheet :event="$ev" :color="$dsColor" :layer="70" />

            {{-- ADDING SOMEBODY TO A DRAW THAT IS ALREADY RUNNING.

                 The draft's own window — two tabs, the entered competitors on
                 one and a walk-in on the other — opened from the Actions menu
                 above (`bracket-add:open`). It is a child of the stage, which
                 carries no transform, so its `position:absolute` resolves
                 against the stage and it needs no teleport. On a phone its own
                 stylesheet turns it into a bottom sheet. --}}
            <x-bracket-add-competitor :event="$ev" :division="$division" :id="$sid.'add'" />

            {{-- The tablet PIN: lends a scoring tablet this organiser's power to
                 edit the draw in an emergency (App\Events\Support\TabletDraw). --}}
            @if(\App\Events\Support\TabletDraw::enabled())
                <x-draw-tablet-pin :event="$ev" :color="$dsColor" />
            @endif
        @endif

        {{-- Admitting a late athlete to a division that is already being fought.

             Offered to anyone who may ARRANGE — the same gate the endpoint
             applies — because an appointed official running a mat is exactly
             who is standing in front of the competitor when he turns up. The
             sheet is inert until something dispatches `late-entry:open`, so
             rendering it costs a reader nothing. --}}
        {{-- Member door only: a host on its own door (the scoring tablet passes
             `admitUrl`) brings its own window, and the member addresses inside
             this one have no business in a device's page source. --}}
        @if(($canManage || $canArrange) && ! $admitUrl)
            <x-late-entry :event="$ev" :color="$dsColor" :division="$division" />
        @endif

        {{-- The host's own windows that must position against the stage (no
             transform here, so `absolute` resolves to it) — e.g. the scoring
             tablet's add-competitor window on its own PIN-gated door. Empty
             for every other host. --}}
        @isset($overlay)
            {{ $overlay }}
        @endisset

        {{-- The podium is ON THE BOARD, not over it.

             It was `x-podium-table` pinned here with `absolute … z-40`, which
             kept its own size while the board grew and shrank underneath it.
             The owner's call on 2026-09-18: it belongs inside the drawing, so
             it pans and zooms with everything else. `renderPodium()` in the
             bracket runtime builds it in the layer, in the board's own
             coordinates, in the column after the final.

             `x-podium-table` is still the right component for a page that
             shows a podium OUTSIDE a board — a results list, a division card —
             which is where it is used. --}}

        {{-- Divisions — a menu floating on the canvas, stacked above the zoom
             cluster on the same edge. A menu rather than pills because a
             championship carries dozens of divisions (age × gender × weight),
             and scrolling a strip back and forth to find one is worse than
             opening a list you can read down.

             It sits OUTSIDE the board element (a sibling, not a child) on
             purpose: the board is overflow-hidden, which would clip the open
             menu. It drops to top-16 while arranging so it never sits under the
             component's arrange banner, which spans the full width at top-3.

             `end-3` rather than right-3 so it mirrors to the left in Arabic,
             like the rest of this page — and it keeps the trailing edge clear of
             the component's "Draw final" badge, which pins to the leading one. --}}
        <div x-show="divisions.length > 1" x-cloak
             class="absolute end-3 z-40 transition-all"
             :class="arrange ? 'top-16' : 'top-3'"
             @keydown.escape.window="open = false"
             @click.outside="open = false">

            <button type="button" @click="open = !open"
                    :aria-expanded="open" aria-haspopup="listbox"
                    class="flex items-center gap-2 ps-2.5 pe-2 py-1.5 rounded-xl border border-gray-200 bg-white
                           text-xs font-bold text-foreground shadow-sm hover:shadow-md hover:bg-muted/60
                           transition-all max-w-[16rem]">
                <i class="bi shrink-0" :class="genderIcon(selected?.name) + ' ' + genderColor(selected?.name)"></i>
                <span class="truncate" x-text="selected?.name ?? ''"></span>
                <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold shrink-0"
                      x-text="selected?.entrants ?? 0"></span>
                <i class="bi bi-chevron-down text-muted-foreground transition-transform shrink-0"
                   :class="open && 'rotate-180'"></i>
            </button>

            <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                 role="listbox"
                 class="absolute end-0 mt-2 w-72 max-h-[60vh] overflow-y-auto rounded-xl border border-gray-200
                        bg-white shadow-xl z-50 py-1">
                <template x-for="d in divisions" :key="d.id">
                    <button type="button" role="option" :aria-selected="d.id === division"
                            @click="window.BracketBoard.show(d.id); open = false"
                            :class="d.id === division ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-start text-xs font-bold transition-colors">
                        <i class="bi shrink-0" :class="genderIcon(d.name) + ' ' + genderColor(d.name)"></i>
                        <span class="flex-1 truncate" x-text="d.name"></span>
                        <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold shrink-0"
                              x-text="d.entrants"></span>
                        <i class="bi bi-check-lg shrink-0" x-show="d.id === division"></i>
                    </button>
                </template>
            </div>
        </div>

    </div>
</div>
{{-- ===== The stage is exactly as tall as the screen actually is =====

     Three answers to "how tall is the viewport?", in increasing order of truth:

       · `100vh` — the LARGE viewport, as if the browser's toolbar were
         retracted. Always too tall while the toolbar is showing, which pushed
         the board's bottom edge (and the zoom cluster and legend pinned to it)
         underneath it.
       · `100dvh` — the viewport as it is right now. Correct in every browser
         that has it, and the style above uses it.
       · `visualViewport.height` — what is actually on screen, asked of the
         browser rather than described to it. Samsung Internet keeps its bar at
         the BOTTOM of the screen, and that is the case that kept clipping the
         last button even with `dvh` in place, so this has the final word.

     Cheap (one style write per resize), guarded so the shells can re-run this
     script without stacking listeners, and it does nothing at all if the
     element is not on the page. --}}
{{-- ===== Which division the editing menu's verbs act on =====

     The menu is teleported to <body> by <x-division-groups>, so its forms sit
     OUTSIDE this stage's Alpine scope and cannot bind to its `division`. A
     delegated listener is the honest answer: the board's events bubble
     (`bubbles: true` in the bracket runtime), so document hears every division
     change and writes it into the hidden input the forms post.

     Guarded once for the whole document — several stages would otherwise each
     add a listener and all write the same inputs. --}}
<script>
(function () {
    if (window.__drawStageDivisionSync) return;
    window.__drawStageDivisionSync = true;

    const sync = (id) => {
        if (id === undefined || id === null) return;
        document.querySelectorAll('input[data-ds-division]')
            .forEach((input) => { input.value = id; });
    };

    ['bracket:loaded', 'bracket:state'].forEach((name) => {
        document.addEventListener(name, (e) => sync(e.detail && e.detail.division));
    });
})();
</script>

@if(\App\Events\Support\BracketPdf::available())
{{-- ===== Download the draw as a PDF =====

     Fetched, not navigated to: it takes a few seconds (a browser prints it on
     the server), and a failure must be a toast on THIS page rather than a
     redirect away from the board (No-Reload rule). The viewer's own layout
     and bronze settings ride along, so the paper matches their screen.

     Defined once per document — the shells re-run inline scripts. --}}
<script>
(function () {
    if (window.drawStagePdf) return;

    let busy = false;

    window.drawStagePdf = async function (url, oneDivision) {
        if (busy) return;
        busy = true;

        const st = (window.BracketBoard && window.BracketBoard.state) ? window.BracketBoard.state() : {};
        const q = new URLSearchParams();
        if (oneDivision && st.division) q.set('division', st.division);
        if (st.layout) q.set('layout', st.layout);
        if (st.third) q.set('third', st.third);

        window.showToast && window.showToast('info', @js(__('events::events.bracket_pdf_working')));

        try {
            const res = await fetch(url + '?' + q.toString(), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, application/pdf' },
            });
            const type = res.headers.get('Content-Type') || '';

            if (! res.ok || type.indexOf('application/pdf') === -1) {
                let msg = @js(__('events::events.bracket_pdf_failed'));
                try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (e) { /* not json */ }
                window.showToast && window.showToast('error', msg);
                return;
            }

            const name = ((res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'draw.pdf';
            const link = document.createElement('a');
            link.href = URL.createObjectURL(await res.blob());
            link.download = name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(link.href), 10000);
        } catch (e) {
            window.showToast && window.showToast('error', @js(__('events::events.bracket_pdf_failed')));
        } finally {
            busy = false;
        }
    };
})();
</script>
@endif

<script>
(function () {
    /* This stage's own element. Rendered in, not hunted for by class: two
       stages on one page would otherwise size each other. */
    const STAGE = @js($sid.'-stage');

    const size = () => {
        const el = document.getElementById(STAGE);
        if (! el) return;

        const h = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
        if (h > 0) el.style.height = Math.round(h) + 'px';
    };

    size();

    /* Guarded per stage, and deduped: the shells re-run a page's scripts on
       every in-place navigation, and a stacked resize listener sizes the same
       element three times a frame (CLAUDE.md → dedup listeners). */
    window.__drawStageSized = window.__drawStageSized || {};
    if (window.__drawStageSized[STAGE]) return;
    window.__drawStageSized[STAGE] = true;

    window.addEventListener('resize', size);
    window.addEventListener('orientationchange', size);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', size);
        // The bar retracting as the page scrolls is a viewport OFFSET change,
        // not a resize, in some browsers.
        window.visualViewport.addEventListener('scroll', size);
    }
    // The board measures itself to fit the draw, so it needs telling once the
    // stage has settled on its real height.
    window.addEventListener('load', size);

    /* ── The three-dot menu never runs off the screen ─────────────────────
       Its fixed cap (100dvh − 11rem) did not know how far down the menu
       starts: under the group rows it opens ~260px from the top, so on a
       phone its last rows — the PDF download, "Manage this event" — sat
       below the bottom edge where no scroll could reach them. Sized to the
       room actually left under its own top edge instead, it scrolls. */
    const fitMenu = () => {
        const el = document.getElementById(STAGE);
        if (! el) return;
        const h = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
        el.querySelectorAll('.bk-menu').forEach((m) => {
            if (! m.offsetParent) return;   // closed
            const top = m.getBoundingClientRect().top;
            m.style.maxHeight = Math.max(160, Math.floor(h - top - 12)) + 'px';
        });
    };
    // Alpine opens the menu after the click; measure once it has painted.
    document.addEventListener('click', () => requestAnimationFrame(() => requestAnimationFrame(fitMenu)));
    window.addEventListener('resize', fitMenu);
})();
</script>

{{-- ===== The bar's own chrome =====

     Written as plain CSS rather than as Tailwind arbitrary values, because
     this project's Tailwind bundle is PREBUILT: a class nobody has used before
     has no rule in public/build and renders as nothing.

     The last block reaches INTO <x-division-groups>'s trigger. That component
     draws a grey-on-white three-dot button, which is correct everywhere else
     it is used and invisible on navy. Scoped to this stage's own id, so the
     component is unchanged and every other page that renders it is untouched. --}}
<style>
    #{{ $sid }}-stage .bk-stage-bar{
        background:linear-gradient(160deg,#0b132b 0%,#1e2c4f 100%);
        border-bottom:1px solid rgba(216,178,95,.45);
    }
    #{{ $sid }}-stage .bk-stage-eyebrow{color:#d8b25f;}
    #{{ $sid }}-stage .bk-stage-back{
        background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.25);
        color:#fff;backdrop-filter:blur(4px);
    }
    #{{ $sid }}-stage .bk-stage-back:hover{background:rgba(255,255,255,.25);}

    #{{ $sid }}-stage .bk-stage-bar .ev-ico{
        background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.25);color:#fff;
    }
    #{{ $sid }}-stage .bk-stage-bar .ev-ico:hover{background:rgba(255,255,255,.25);}

    /* The same trigger on the BANDED bar (the event's own gradient), where it
       is the one menu: white, and the round 40px control the back arrow
       opposite it is (.ev-ctl), not a grey square. */
    #{{ $sid }}-stage .bk-stage-banded .ev-ico{
        width:40px;height:40px;border-radius:50%;
        background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.26);color:#fff;
        -webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);
    }
    #{{ $sid }}-stage .bk-stage-banded .ev-ico:hover{background:rgba(255,255,255,.25);}

    /* ── The padlock ───────────────────────────────────────────────────────
       Shut: a quiet translucent control, like the back arrow opposite it —
       the board is being READ and nothing is asking to be pressed. Open: the
       draft's gold, because something on this screen is now live. */
    #{{ $sid }}-stage .bk-lock{
        background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
        color:#fff;backdrop-filter:blur(4px);cursor:pointer;letter-spacing:.04em;
    }
    #{{ $sid }}-stage .bk-lock:hover{background:rgba(255,255,255,.25);}
    #{{ $sid }}-stage .bk-lock.is-open{
        background:linear-gradient(100deg,#edcd85,#d0a854);border-color:#d8b25f;color:#1e2c4f;
        box-shadow:0 10px 30px rgba(216,178,95,.4);
    }

    /* ── The host's half of the three-dot menu ─────────────────────────────
       ⚠️ Never write a literal component tag in here — Blade parses `x-…`
       tags inside a <style> block and inside CSS comments, and one in this
       comment compiled to a component call that swallowed the rest of the
       file (reference_blade_component_tag_in_js).

       Sits inside the division-groups menu's own panel, so it inherits that
       panel's width and border and brings only its own rhythm. Scrolls on its
       own: the settings make this menu taller than the bar it hangs from. */
    #{{ $sid }}-stage .bk-menu{padding:10px 12px 12px;max-height:calc(100dvh - 11rem);overflow-y:auto;}

    /* The event's name in the banded bar WRAPS to a second line rather than
       being cut off (names are never truncated). With the gear, the ⋯ and the
       padlock folded into the one menu there is room for it. */
    #{{ $sid }}-stage .ev-ctl-title{flex:1 1 auto;}
    #{{ $sid }}-stage .ev-ctl-name{white-space:normal;overflow:visible;font-size:13px;line-height:1.15;}
    #{{ $sid }}-stage .bk-menu-head{
        font-size:.62rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
        color:#97a1b4;margin:0 0 6px;
    }
    #{{ $sid }}-stage .bk-menu-rule{border-top:1px solid #eef1f6;margin:4px 0 6px;}
    #{{ $sid }}-stage .bk-menu-row{
        display:flex;align-items:center;gap:10px;width:100%;text-align:start;padding:9px 10px;
        border:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:.82rem;
        font-weight:500;background:transparent;color:#1e2c4f;
    }
    #{{ $sid }}-stage .bk-menu-row:hover{background:#eef1f6;}
    #{{ $sid }}-stage .bk-menu-row.is-gold{color:#8a6d20;font-weight:600;}
    #{{ $sid }}-stage .bk-menu-row.is-gold:hover{background:#fdf6e6;}
    #{{ $sid }}-stage .bk-menu-row[disabled]{opacity:.45;cursor:default;}
    #{{ $sid }}-stage .bk-menu-row[disabled]:hover{background:transparent;}
    #{{ $sid }}-stage .bk-menu form{margin:0;}
    #{{ $sid }}-stage .bk-seg{
        display:flex;border:1px solid #d5dae3;border-radius:8px;overflow:hidden;margin-bottom:12px;
    }
    #{{ $sid }}-stage .bk-seg-b{
        flex:1;padding:8px 0;border:none;cursor:pointer;font-family:inherit;font-size:.78rem;
        font-weight:600;background:#fff;color:#5d6880;
    }
    #{{ $sid }}-stage .bk-seg-b.is-on{background:#d8b25f;color:#1e2c4f;}

</style>
