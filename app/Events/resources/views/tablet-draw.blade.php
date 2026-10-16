{{--
    The draw on a SCORING TABLET — /tablet/{token}/draw (TabletDrawController).

    The same <x-draw-stage> and the same board every other draw page uses, fed
    through the tablet's own door: read-only on the pairing, editable for a
    few minutes after the event's tablet PIN (App\Events\Support\TabletDraw).

    Its own document rather than a layout, like the scoring console it opens
    from: a paired device loads none of the platform shell. The head is the
    event surface's own (entry/layout) so the board looks exactly the same.

    ── Unattended-device rules (CLAUDE.md) ──────────────────────────────────
    · A dead token never reaches this view — the controller sends it to /screen.
    · The board's data door answers 404 for a dead identity; the poll below
      reloads this page on that answer, which lands in the pairing room. A
      timeout or a 500 changes nothing.
    · Back always goes to this tablet's own console.
--}}
@php
    $c = \App\Support\Palette::safe($color);
    $rtl = app()->getLocale() === 'ar';
    /* Every address the page calls. The tablet's own doors by default; the
       organiser's draw desk (DrawDeskController) hands its own set, so ONE
       page serves both. `_ID_` is replaced by the bout/entry id in JS. */
    $u = $urls ?? [
        'data' => route('tablet.draw.data', $token),
        'arrange' => route('tablet.draw.arrange', $token),
        'admit' => route('tablet.draw.admit', $token),
        'transfer' => route('tablet.draw.transfer', $token),
        'outcome' => str_replace('999999999', '__ID__', route('tablet.draw.outcome', [$token, 999999999])),
        'entrant' => str_replace('999999999', '__ID__', route('tablet.draw.entrant', [$token, 999999999])),
        'candidates' => route('tablet.draw.candidates', $token),
        'clubs' => route('tablet.draw.clubs', $token),
        'unnamed' => route('tablet.draw.unnamed', $token),
        'number' => route('tablet.draw.number', [$token, 999999999]),
        'numberRelease' => route('tablet.draw.number.release', [$token, 999999999]),
        'renumber' => route('tablet.draw.renumber', $token),
        'shuffle' => route('tablet.draw.shuffle', $token),
        'renumberAll' => route('tablet.draw.renumber-all', $token),
        'unlock' => route('tablet.draw.unlock', $token),
        'lock' => route('tablet.draw.lock', $token),
    ];
    $backLabel = $backLabel ?? __('events::events.tablet_back_console');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    {{-- Light only, like the app layout: a browser's or extension's automatic
         dark mode (Dark Reader, Android force-dark) repainted this page and its
         division menu in inverted colours. darkreader-lock is that extension's
         own documented opt-out. --}}
    <meta name="color-scheme" content="light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>
    <title>{{ __('events::events.tablet_draw_button') }} · {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('images/flags/flag-icons.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', system-ui, 'Noto Sans Arabic', sans-serif; background: #0b132b; }
        .td-ctl { width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center;
                  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; backdrop-filter: blur(4px);
                  transition: background-color .15s, transform .08s; flex: 0 0 auto; }
        .td-ctl:hover { background: rgba(255,255,255,.25); }
        .td-ctl:active, .td-pill:active { transform: scale(.95); }
        .td-pill { display: inline-flex; align-items: center; gap: .4rem; height: 2.25rem; padding: 0 .9rem; border-radius: .75rem;
                   font-size: .75rem; font-weight: 700; white-space: nowrap; flex: 0 0 auto; transition: background-color .15s, transform .08s; }
        .td-pill.is-light { background: #fff; color: #1e2c4f; }
        .td-pill.is-ghost { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; }
        .td-pill.is-gold { background: linear-gradient(100deg,#edcd85,#d0a854); color: #1e2c4f; }
        .td-seg { display: inline-flex; padding: 3px; gap: 2px; border-radius: .8rem; background: rgba(255,255,255,.15);
                  border: 1px solid rgba(255,255,255,.25); flex: 0 0 auto; }
        .td-seg button { display: inline-flex; align-items: center; gap: .35rem; height: 1.85rem; padding: 0 .7rem; border-radius: .6rem;
                         font-size: .72rem; font-weight: 700; color: #fff; white-space: nowrap; transition: background-color .15s, color .15s; }
        .td-seg button.on { background: #fff; color: #1e2c4f; }
        .td-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .6rem; border-radius: 9999px;
                   background: rgba(255,255,255,.2); font-size: .65rem; font-weight: 800; white-space: nowrap; }

        /* The keypad — fat targets for a finger at a scoring table. */
        .td-key { height: 4rem; border-radius: 1rem; background: #f3f5f9; color: #1e2c4f; font-size: 1.6rem; font-weight: 800;
                  display: flex; align-items: center; justify-content: center; transition: background-color .12s, transform .08s; }
        .td-key:active { transform: scale(.94); background: #e6e9f0; }
        .td-dot { width: .9rem; height: .9rem; border-radius: 9999px; border: 2px solid #c9d0db; transition: background-color .12s, border-color .12s; }
        .td-dot.on { background: #1e2c4f; border-color: #1e2c4f; }
        @keyframes td-shake { 10%,90%{transform:translateX(-2px)} 20%,80%{transform:translateX(4px)} 30%,50%,70%{transform:translateX(-8px)} 40%,60%{transform:translateX(8px)} }
        .td-shake { animation: td-shake .45s; }
        @media (prefers-reduced-motion: reduce) { .td-shake { animation: none; } }

        /* The division editor docks top-left beside the board, so the holding
           basket (bottom-right) and the bracket stay in reach; on a phone it
           takes the top of the screen and the basket keeps the bottom. Plain
           CSS: the Tailwind bundle is prebuilt and has no sm:w-[360px]. */
        .td-ed { top: calc(64px + env(safe-area-inset-top, 0px)); inset-inline-start: 12px; width: 360px;
                 max-width: calc(100vw - 24px); max-height: calc(100vh - 150px); }
        @media (max-width: 639px) { .td-ed { top: calc(112px + env(safe-area-inset-top, 0px)); inset-inline: 8px; width: auto; max-height: 50vh; }
                                    .td-ed-help { display: none; } }
        .td-menu { width: min(300px, calc(100vw - 24px)); }
        /* A confirmation sheet: a bottom sheet on a phone, a centred card on a desk. */
        @media (min-width: 640px) { .td-sheet { inset: auto !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%);
                                                 width: 520px; border-radius: 1.5rem !important; } }
        .td-g-female { border-color: #ec4899 !important; background: #fdf2f8 !important; color: #be185d !important; }
    </style>
</head>
<body>
<div x-data="tabletDraw()">

    <x-draw-stage
        id="tdraw"
        :event="$event->uuid"
        :title="$title"
        :color="$c"
        :can-manage="false"
        :can-arrange="$editing"
        :data-url="$u['data']"
        :arrange-url="$u['arrange']"
        :clear-url="false"
        :admit-url="$u['admit']"
        :transfer-url="$u['transfer']"
        :pull-url="$editorManages ? ($u['pull'] ?? null) : null"
        :board-can-manage="$editorManages"
        :outcome-url="$u['outcome']"
        :entrant-url="$u['entrant']"
        :division="$division ?? null">

        <x-slot:controls>
            <a href="{{ $consoleUrl }}" class="td-ctl" title="{{ $backLabel }}"
               aria-label="{{ $backLabel }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>

            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] leading-none text-white/85">
                    <i class="bi bi-diagram-3-fill bracket-icon"></i> {{ __('events::events.tablet_draw_eyebrow') }}
                </p>
                <h1 class="text-sm font-bold text-white truncate leading-tight mt-0.5">{{ $title }}</h1>
            </div>

            @if(! empty($u['pdf']))
                {{-- Download the bracket on show as a PDF (owner, 2026-10-16) — next
                     to Edit. The board's own download (drawStagePdf): fetched, so a
                     failure is a toast here, and the screen's layout rides along. --}}
                {{-- A menu since 2026-10-16 (owner): this division or all of them,
                     downloaded or shared. --}}
                <button type="button" class="td-pill is-ghost" x-ref="pdfBtn" @click="openPdfMenu()"
                        title="{{ __('events::events.tablet_download_hint') }}"
                        :aria-expanded="pdfMenu.open ? 'true' : 'false'">
                    <i class="bi bi-download"></i><span>{{ __('events::events.tablet_download') }}</span><i class="bi bi-chevron-down text-[10px]"></i>
                </button>
            @endif

            @if($editing)
                {{-- Without a PIN nothing locks by itself, so the table says when it
                     has finished: Done closes the board to edits (a view, not a
                     permission — every edit is still authorised server-side) and
                     Edit opens it again. --}}
                @unless($pinRequired)
                    <button type="button" class="td-pill is-gold" x-show="viewOnly" x-cloak @click="resume()">
                        <i class="bi bi-pencil-square"></i><span>{{ __('events::events.tablet_edit_again') }}</span>
                    </button>
                @endunless
                <template x-if="! viewOnly"><span style="display:contents">
                {{-- The "Editing as <name>" chip was removed (owner, 2026-10-16).
                     With a PIN the grant's countdown still shows, on its own. --}}
                @if($pinRequired)
                <span class="td-chip hidden md:inline-flex" title="{{ __('events::events.tablet_editing_as', ['name' => $editorName]) }}">
                    <i class="bi bi-stopwatch"></i><span x-text="clock()"></span>
                </span>
                @endif
                @if($editorManages)
                    {{-- The board cannot both drag and decide: a tap in arrange
                         mode is the start of a move. So the two jobs are a
                         switch, and the switch says which one a tap does. --}}
                    {{-- One pill since 2026-10-16 (owner): it names the tool a tap is
                         using now, and opens the menu of all five. --}}
                    <button type="button" class="td-pill is-light" x-ref="modeBtn" @click="openModeMenu()"
                            :aria-expanded="modeMenu.open ? 'true' : 'false'">
                        <span x-show="mode === 'move'" class="inline-flex items-center gap-1.5"><i class="bi bi-arrows-move"></i><span>{{ __('events::events.tablet_mode_move') }}</span></span>
                        <span x-show="mode === 'winner'" x-cloak class="inline-flex items-center gap-1.5"><i class="bi bi-trophy-fill"></i><span>{{ __('events::events.tablet_mode_winner') }}</span></span>
                        <span x-show="mode === 'unwin'" x-cloak class="inline-flex items-center gap-1.5"><i class="bi bi-arrow-counterclockwise"></i><span>{{ __('events::events.tablet_mode_unwin') }}</span></span>
                        <span x-show="mode === 'numbers'" x-cloak class="inline-flex items-center gap-1.5"><i class="bi bi-123"></i><span>{{ __('events::events.tablet_mode_numbers') }}</span></span>
                        <span x-show="mode === 'names'" x-cloak class="inline-flex items-center gap-1.5"><i class="bi bi-pencil-fill"></i><span>{{ __('events::events.tablet_names') }}</span></span>
                        <i class="bi bi-chevron-down text-[10px]"></i>
                    </button>
                @endif
                @if($editorManages && (! empty($u['shuffle']) || ! empty($u['renumberAll'])))
                    {{-- ONE Arrange control (owner, 2026-10-16): Shuffle and Renumber
                         bouts, which used to be two pills, now live in one menu. --}}
                    <button type="button" class="td-pill is-gold" x-ref="arrBtn" @click="openArrMenu()" :disabled="busy"
                            :aria-expanded="arrMenu.open ? 'true' : 'false'">
                        <i class="bi bi-sliders2"></i><span>{{ __('events::events.tablet_arrange_menu') }}</span><i class="bi bi-chevron-down text-[10px]"></i>
                    </button>
                @endif
                @if($editorManages && ! empty($u['divisions']))
                    {{-- ONE Division control (owner, 2026-10-16: "combine Add, Delete
                         and Edit"): a small menu — add, edit the one on show, delete
                         it, and the event's one-division-per-person switch. --}}
                    <button type="button" class="td-pill is-ghost" x-ref="divBtn" @click="openDivMenu()" :disabled="busy"
                            :aria-expanded="divMenu.open ? 'true' : 'false'">
                        <i class="bi bi-collection"></i><span>{{ __('events::events.tablet_div_menu') }}</span><i class="bi bi-chevron-down text-[10px]"></i>
                    </button>
                @endif
                {{-- In edit: bring anybody from the participants list into the
                     bracket on show (owner, 2026-10-15) — labelled always, not
                     an icon on its own. The window's server side (LateEntry)
                     fills a bye first and never touches a fought bout. --}}
                <button type="button" class="td-pill is-gold" @click="$dispatch('bracket-add:open')"
                        title="{{ __('events::events.tablet_add_athlete_hint') }}">
                    <i class="bi bi-person-plus-fill"></i><span>{{ __('events::events.tablet_add_athlete') }}</span>
                </button>
                @if($pinRequired)
                <button type="button" class="td-pill is-ghost" @click="lock()" :disabled="busy">
                    <i class="bi bi-lock-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_lock') }}</span>
                </button>
                @else
                <button type="button" class="td-pill is-ghost" @click="done()">
                    <i class="bi bi-check2-circle"></i><span>{{ __('events::events.tablet_done') }}</span>
                </button>
                @endif
                </span></template>
            @else
                {{-- Both ask for the PIN first; "Late entry" then opens the
                     add window by itself once the board is unlocked. --}}
                <button type="button" class="td-pill is-ghost" @click="openPad('edit')">
                    <i class="bi bi-pencil-square"></i><span>{{ __('events::events.tablet_edit_bout') }}</span>
                </button>
                <button type="button" class="td-pill is-gold" @click="openPad('add')">
                    <i class="bi bi-person-plus-fill"></i><span>{{ __('events::events.tablet_late_entry') }}</span>
                </button>
            @endif
        </x-slot:controls>

        @if($editing)
            <x-slot:overlay>
                {{-- How to edit is said as a TOAST when editing starts, then it goes
                     (owner, 2026-10-07) — never a card left over the draw, and never
                     in view mode. --}}
                <x-bracket-add-competitor
                    :event="$event->uuid"
                    id="tdadd"
                    :candidates-url="$u['candidates']"
                    :clubs-url="$u['clubs']"
                    :unnamed-url="$u['unnamed']"
                    :clubs-link="false" />
            </x-slot:overlay>
        @endif
    </x-draw-stage>

    @if($editing && $editorManages)
    {{-- ===== The tool menu (Move · Winner · Remove winner · Bout no. · Names) ===== --}}
    <template x-teleport="body">
        <div x-show="modeMenu.open" x-cloak class="fixed inset-0 z-[75]" @click="modeMenu.open = false" @keydown.escape.window="modeMenu.open = false">
            <div class="fixed w-[min(300px,calc(100vw-1.5rem))] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden pb-1" role="menu"
                 :style="modeMenu.style" @click.stop
                 x-show="modeMenu.open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                <p class="px-4 pt-3 pb-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-gray-400">{{ __('events::events.tablet_mode_head') }}</p>
                <button type="button" role="menuitemradio" :aria-checked="mode === 'move' ? 'true' : 'false'" @click="modeMenu.open = false; setMode('move')"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-start hover:bg-muted/60"
                        :class="mode === 'move' ? 'text-primary' : 'text-gray-800'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" :class="mode === 'move' ? 'bg-primary text-white' : 'bg-muted text-gray-600'"><i class="bi bi-arrows-move"></i></span>
                    <span class="flex-1">{{ __('events::events.tablet_mode_move') }}</span>
                    <i class="bi bi-check-lg" x-show="mode === 'move'"></i>
                </button>
                <button type="button" role="menuitemradio" :aria-checked="mode === 'winner' ? 'true' : 'false'" @click="modeMenu.open = false; setMode('winner')"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-start hover:bg-muted/60"
                        :class="mode === 'winner' ? 'text-primary' : 'text-gray-800'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" :class="mode === 'winner' ? 'bg-primary text-white' : 'bg-muted text-gray-600'"><i class="bi bi-trophy-fill"></i></span>
                    <span class="flex-1">{{ __('events::events.tablet_mode_winner') }}</span>
                    <i class="bi bi-check-lg" x-show="mode === 'winner'"></i>
                </button>
                <button type="button" role="menuitemradio" :aria-checked="mode === 'unwin' ? 'true' : 'false'" @click="modeMenu.open = false; setMode('unwin')"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-start hover:bg-muted/60"
                        :class="mode === 'unwin' ? 'text-primary' : 'text-gray-800'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" :class="mode === 'unwin' ? 'bg-primary text-white' : 'bg-muted text-gray-600'"><i class="bi bi-arrow-counterclockwise"></i></span>
                    <span class="flex-1">{{ __('events::events.tablet_mode_unwin') }}</span>
                    <i class="bi bi-check-lg" x-show="mode === 'unwin'"></i>
                </button>
                <button type="button" role="menuitemradio" :aria-checked="mode === 'numbers' ? 'true' : 'false'" @click="modeMenu.open = false; setMode('numbers')"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-start hover:bg-muted/60"
                        :class="mode === 'numbers' ? 'text-primary' : 'text-gray-800'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" :class="mode === 'numbers' ? 'bg-primary text-white' : 'bg-muted text-gray-600'"><i class="bi bi-123"></i></span>
                    <span class="flex-1">{{ __('events::events.tablet_mode_numbers') }}</span>
                    <i class="bi bi-check-lg" x-show="mode === 'numbers'"></i>
                </button>
                <button type="button" role="menuitemradio" :aria-checked="mode === 'names' ? 'true' : 'false'" @click="modeMenu.open = false; setMode('names')"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-start hover:bg-muted/60"
                        :class="mode === 'names' ? 'text-primary' : 'text-gray-800'">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" :class="mode === 'names' ? 'bg-primary text-white' : 'bg-muted text-gray-600'"><i class="bi bi-pencil-fill"></i></span>
                    <span class="flex-1">{{ __('events::events.tablet_names') }}</span>
                    <i class="bi bi-check-lg" x-show="mode === 'names'"></i>
                </button>
            </div>
        </div>
    </template>
    @endif

    @if(! empty($u['pdf']))
    {{-- ===== The Download menu (teleported, like the menus below) ===== --}}
    <template x-teleport="body">
        <div x-show="pdfMenu.open" x-cloak class="fixed inset-0 z-[75]" @click="pdfMenu.open = false" @keydown.escape.window="pdfMenu.open = false">
            <div class="fixed w-[min(300px,calc(100vw-1.5rem))] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden pb-1"
                 :style="pdfMenu.style" @click.stop
                 x-show="pdfMenu.open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                <p class="px-4 pt-3 pb-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-gray-400">{{ __('events::events.bracket_pdf_head') }}</p>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start" @click="pdfMenu.open = false; window.drawStagePdf && window.drawStagePdf(@js($u['pdf']), true)">
                    <span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-file-earmark-pdf"></i></span>
                    <span>{{ __('events::events.bracket_pdf_this') }}</span>
                </button>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start" @click="pdfMenu.open = false; window.drawStagePdf && window.drawStagePdf(@js($u['pdf']), false)">
                    <span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-files"></i></span>
                    <span>{{ __('events::events.bracket_pdf_all') }}</span>
                </button>
                <div class="border-t border-gray-100 mt-1"></div>
                <p class="px-4 pt-3 pb-1 text-[10px] font-extrabold uppercase tracking-[0.14em] text-gray-400">{{ __('events::events.bracket_pdf_share_head') }}</p>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start" @click="pdfMenu.open = false; window.drawStagePdf && window.drawStagePdf(@js($u['pdf']), true, true)">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-share"></i></span>
                    <span>{{ __('events::events.bracket_pdf_this') }}</span>
                </button>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start" @click="pdfMenu.open = false; window.drawStagePdf && window.drawStagePdf(@js($u['pdf']), false, true)">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-share-fill"></i></span>
                    <span>{{ __('events::events.bracket_pdf_all') }}</span>
                </button>
            </div>
        </div>
    </template>
    @endif

    @if($editorManages && (! empty($u['shuffle']) || ! empty($u['renumberAll'])))
    {{-- ===== The Arrange menu (teleported, like the Division menu below) ===== --}}
    <template x-teleport="body">
        <div x-show="arrMenu.open" x-cloak class="fixed inset-0 z-[75]" @click="arrMenu.open = false" @keydown.escape.window="arrMenu.open = false">
            <div class="td-menu fixed bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
                 :style="arrMenu.style" @click.stop
                 x-show="arrMenu.open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                @if(! empty($u['shuffle']))
                    {{-- A fair random re-cut of the division on show (FairDraw):
                         belts seeded apart, clubs split, the rest at random.
                         Before the start only — the server refuses otherwise. --}}
                    <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start disabled:opacity-40" @click="arrMenu.open = false; shuffle()" :disabled="busy">
                        <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-shuffle"></i></span>
                        <span>{{ __('events::events.action_shuffle_draw') }}</span>
                    </button>
                @endif
                @if(! empty($u['renumberAll']))
                    {{-- Every bout numbered again from the day's running order (BoutNumbering). --}}
                    <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start disabled:opacity-40" @click="arrMenu.open = false; renumberAll()" :disabled="busy">
                        <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-123"></i></span>
                        <span>{{ __('events::events.renumber_button') }}</span>
                    </button>
                @endif
            </div>
        </div>
    </template>
    @endif

    @if($editorManages && ! empty($u['divisions']))
    {{-- ===== The Division menu (teleported: the bar scrolls sideways and would clip it) ===== --}}
    <template x-teleport="body">
        <div x-show="divMenu.open" x-cloak class="fixed inset-0 z-[75]" @click="divMenu.open = false" @keydown.escape.window="divMenu.open = false">
            <div class="fixed w-[min(300px,calc(100vw-1.5rem))] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
                 :style="divMenu.style" @click.stop
                 x-show="divMenu.open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start" @click="addDivision()">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-plus-square"></i></span>
                    <span>{{ __('events::events.tablet_div_add') }}</span>
                </button>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-muted/60 text-start disabled:opacity-40" @click="editDivision()" :disabled="! curDiv()">
                    <span class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 grid place-items-center flex-shrink-0"><i class="bi bi-pencil-square"></i></span>
                    <span class="min-w-0"><span class="block">{{ __('events::events.tablet_div_edit') }}</span><span class="block text-[11px] font-medium text-gray-500 break-words" x-text="curDiv() ? curDiv().name : ''"></span></span>
                </button>
                <button type="button" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 text-start disabled:opacity-40" @click="divMenu.open = false; openDelDiv()" :disabled="! curDiv()">
                    <span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-trash3"></i></span>
                    <span>{{ __('events::events.tablet_div_delete') }}</span>
                </button>
                <div class="border-t border-gray-100"></div>
                {{-- One division per person — the event's own switch (OneDivisionRule). --}}
                <button type="button" role="switch" :aria-checked="oneDivision ? 'true' : 'false'" @click="toggleOneDivision()" :disabled="busy"
                        class="w-full flex items-start gap-3 px-4 py-3 text-start hover:bg-muted/60">
                    <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-person-lock"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-gray-800">{{ __('events::events.one_division_label') }}</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">{{ __('events::events.one_division_hint') }}</span>
                    </span>
                    <span class="mt-1 w-10 h-6 rounded-full flex-shrink-0 relative transition-colors" :class="oneDivision ? 'bg-primary' : 'bg-gray-300'">
                        <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all" :style="oneDivision ? 'inset-inline-start:18px' : 'inset-inline-start:2px'"></span>
                    </span>
                </button>
            </div>
        </div>
    </template>

    {{-- ===== The division editor — docked, NOT modal: the board and its holding
         basket stay in reach, because the whole point is to see who fits the
         range being typed and drag them straight onto the bracket. ===== --}}
    <template x-teleport="body">
        <div x-show="ed.open" x-cloak role="dialog" aria-label="{{ __('events::events.tablet_div_edit_title') }}"
             class="td-ed fixed z-[70] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex-shrink-0 px-4 pt-3 pb-3 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #1e2c4f, #1e2c4fb0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi text-lg" :class="ed.created ? 'bi-plus-square' : 'bi-pencil-square'"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-black leading-tight" x-text="ed.created ? @js(__('events::events.tablet_div_new_title')) : @js(__('events::events.tablet_div_edit_title'))"></h3>
                        <p class="text-[11px] text-white/85 mt-0.5 break-words" x-text="ed.name || edPlaceholder()"></p>
                    </div>
                    <button type="button" @click="ed.min = ! ed.min" class="w-8 h-8 rounded-full bg-white/15 border border-white/25 grid place-items-center flex-shrink-0 sm:hidden" :aria-label="ed.min ? '+' : '–'">
                        <i class="bi" :class="ed.min ? 'bi-chevron-down' : 'bi-chevron-up'"></i>
                    </button>
                    <button type="button" @click="closeEditor()" aria-label="{{ __('shared.close') }}"
                            class="w-8 h-8 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3" x-show="! ed.min">
                <p class="td-ed-help text-[11px] text-gray-500">{{ __('events::events.tablet_div_edit_sub') }}</p>
                {{-- Jump to another division without leaving the editor (owner,
                     2026-10-16) — the same rows as the board's division menu
                     (DrawDivisionLook). The list opens IN the flow, never as a
                     pop-over, so the scrolling panel cannot clip it. --}}
                <div>
                    <button type="button" @click="edPick = ! edPick" :aria-expanded="edPick ? 'true' : 'false'" aria-haspopup="listbox"
                            class="w-full flex items-center gap-2 ps-2.5 pe-2 py-2 rounded-xl text-xs font-bold shadow-sm text-start"
                            :style="dLook('style', curDiv(), false)">
                        <i class="bi shrink-0" :class="dLook('icon', curDiv())" :style="dLook('ink', curDiv())"></i>
                        <span class="flex-1 min-w-0 break-words leading-snug" :style="dLook('ink', curDiv())">
                            <span x-text="curDiv() ? curDiv().name : ''"></span><span x-show="dLook('line', curDiv())" x-text="' · ' + dLook('line', curDiv())"></span>
                        </span>
                        <span class="px-1.5 py-0.5 rounded-full font-extrabold shrink-0" style="background:rgba(255,255,255,.75);font-size:.6rem" x-text="curDiv() ? curDiv().entrants : 0"></span>
                        <i class="bi bi-chevron-down shrink-0 transition-transform" :class="edPick && 'rotate-180'"></i>
                    </button>
                    <div x-show="edPick" x-cloak x-transition.opacity.duration.120ms role="listbox"
                         class="mt-1.5 rounded-xl border border-gray-200 bg-white py-1 overflow-y-auto overscroll-contain"
                         style="max-height: 16rem; -webkit-overflow-scrolling: touch;">
                        <template x-for="d in edDivisions()" :key="d.id">
                            <button type="button" role="option" :aria-selected="d.id === ed.id ? 'true' : 'false'" @click="switchEditor(d.id)" :disabled="busy"
                                    class="flex items-start gap-2.5 px-3 py-2 text-start text-xs font-bold transition-shadow hover:shadow-md"
                                    :style="'display:flex; width:calc(100% - .75rem); margin:.25rem .375rem; border-radius:.6rem;' + dLook('style', d, d.id === ed.id)">
                                <i class="bi shrink-0" :class="dLook('icon', d)" :style="'margin-top:.1rem;' + dLook('ink', d)"></i>
                                <span class="flex-1 min-w-0 break-words leading-snug" :style="dLook('ink', d)">
                                    <span x-text="d.name"></span><span x-show="dLook('line', d)" x-text="' · ' + dLook('line', d)"></span>
                                </span>
                                <span class="px-1.5 py-0.5 rounded-full font-extrabold shrink-0" style="background:rgba(255,255,255,.75);font-size:.6rem" x-text="d.entrants"></span>
                                <i class="bi bi-check-lg shrink-0" x-show="d.id === ed.id"></i>
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('events::events.tablet_div_name') }}</label>
                    <input type="text" x-model="ed.name" maxlength="80" :placeholder="edPlaceholder()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-700 mb-1">{{ __('events::events.tablet_div_gender') }}</span>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="g in [['', @js(__('events::events.tablet_div_mixed')), 'bi-gender-ambiguous'], ['Male', @js(__('events::events.tablet_div_male')), 'bi-gender-male'], ['Female', @js(__('events::events.tablet_div_female')), 'bi-gender-female']]" :key="g[0]">
                            <button type="button" @click="ed.gender = g[0]; edChanged()"
                                    class="h-10 rounded-xl text-xs font-bold border transition-colors inline-flex items-center justify-center gap-1"
                                    :class="ed.gender === g[0] ? (g[0] === 'Male' ? 'border-blue-500 bg-blue-50 text-blue-700' : g[0] === 'Female' ? 'td-g-female' : 'border-primary bg-primary/5 text-primary') : 'border-gray-200 text-gray-600'">
                                <i class="bi" :class="g[2]"></i><span x-text="g[1]"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="block text-xs font-semibold text-gray-700 mb-1">{{ __('events::events.tablet_div_ages') }}</span>
                        <div class="flex items-center gap-1.5">
                            <input type="number" inputmode="numeric" step="1" min="2" max="100" x-model="ed.minAge" @input="edChanged()" placeholder="{{ __('events::events.tablet_div_min') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm">
                            <span class="text-gray-400">–</span>
                            <input type="number" inputmode="numeric" step="1" min="2" max="100" x-model="ed.maxAge" @input="edChanged()" placeholder="{{ __('events::events.tablet_div_max') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm">
                        </div>
                    </div>
                    <div>
                        {{-- Decimals to the hundredth: a 22.75 kg class filters exactly. --}}
                        <span class="block text-xs font-semibold text-gray-700 mb-1">{{ __('events::events.tablet_div_weights') }}</span>
                        <div class="flex items-center gap-1.5">
                            <input type="number" inputmode="decimal" step="any" min="10" max="300" x-model="ed.minW" @input="edChanged()" placeholder="{{ __('events::events.tablet_div_min') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm">
                            <span class="text-gray-400">–</span>
                            <input type="number" inputmode="decimal" step="any" min="10" max="300" x-model="ed.maxW" @input="edChanged()" placeholder="{{ __('events::events.tablet_div_max') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm">
                        </div>
                    </div>
                </div>
                <p class="text-xs font-semibold flex items-center gap-1.5" :class="ed.count === null ? 'text-gray-400' : 'text-emerald-600'">
                    <i class="bi" :class="ed.loading ? 'bi-arrow-repeat animate-spin' : 'bi-basket2-fill'"></i>
                    <span x-text="edCountText()"></span>
                </p>
                <p class="text-sm text-red-600" x-show="ed.error" x-text="ed.error"></p>
            </div>
            <div class="flex-shrink-0 flex justify-end gap-2 px-4 pt-2.5 border-t border-gray-100" x-show="! ed.min" style="padding-bottom: calc(0.65rem + env(safe-area-inset-bottom));">
                <button type="button" x-show="ed.created" @click="discardDivision()" :disabled="busy"
                        class="me-auto px-3 py-2 rounded-xl text-xs font-semibold border border-red-200 text-red-600">{{ __('events::events.tablet_div_discard') }}</button>
                <button type="button" @click="closeEditor()" class="px-3 py-2 rounded-xl text-xs font-semibold border border-gray-200 text-gray-600">{{ __('events::events.tablet_div_cancel') }}</button>
                <button type="button" @click="saveDivision(false)" :disabled="busy" class="px-4 py-2 rounded-xl text-xs font-bold bg-primary text-white disabled:opacity-60">{{ __('events::events.tablet_div_save') }}</button>
            </div>
        </div>
    </template>

    {{-- ===== Delete the division on show — typed confirmation ===== --}}
    <template x-teleport="body">
        <div x-show="delDiv.open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" @keydown.escape.window="delDiv.open = false">
            <div class="absolute inset-0 bg-black/50" x-show="delDiv.open" x-transition.opacity @click="delDiv.open = false"></div>
            <div x-show="delDiv.open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 class="td-sheet absolute inset-x-0 bottom-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-[520px] max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden" style="background: linear-gradient(150deg, #b4483c, #b4483cb0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-trash3 text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_div_del_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 break-words" x-text="delDiv.name"></p>
                        </div>
                        <button type="button" @click="delDiv.open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <p class="text-sm text-gray-700" x-text="delDivBody()"></p>
                    <label class="block text-sm font-medium text-gray-700">{{ __('events::events.tablet_div_del_type', ['word' => 'DELETE']) }}</label>
                    <input type="text" x-model="delDiv.typed" autocomplete="off" spellcheck="false" placeholder="DELETE"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-bold tracking-widest uppercase focus:ring-2 focus:ring-red-400 focus:border-transparent">
                    <p class="text-sm text-red-600" x-show="delDiv.error" x-text="delDiv.error"></p>
                </div>
                <div class="flex-shrink-0 flex justify-end gap-2 px-5 pt-3 border-t border-gray-100" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="delDiv.open = false" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-gray-200 text-gray-600">{{ __('events::events.tablet_div_cancel') }}</button>
                    <button type="button" @click="deleteDiv()" :disabled="busy || delDiv.typed.trim().toUpperCase() !== 'DELETE'"
                            class="px-5 py-2.5 rounded-xl text-sm font-bold bg-red-600 text-white disabled:opacity-40">{{ __('events::events.tablet_div_delete') }}</button>
                </div>
            </div>
        </div>
    </template>
    @endif

    @if($editorManages)
    {{-- ===== Bout number (Design Rule #8 band) — a bout's number is its place
         on its mat, so saving is the running-order board's own move. ===== --}}
    <template x-teleport="body">
        <div x-show="num.open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true"
             @keydown.escape.window="num.open = false">
            <div class="absolute inset-0 bg-black/50" x-show="num.open" x-transition.opacity @click="num.open = false"></div>

            <div x-show="num.open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-123 text-2xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_number_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.tablet_number_sub') }}</p>
                        </div>
                        <button type="button" @click="num.open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 pt-5 pb-2">
                    <div x-show="num.loading" class="py-10 grid place-items-center text-gray-400">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                    </div>

                    <template x-if="! num.loading && num.match">
                        <div>
                            <p x-show="num.match.decided" class="mb-4 text-sm text-amber-800 bg-amber-50 rounded-xl px-3.5 py-3 flex items-start gap-2">
                                <i class="bi bi-info-circle-fill mt-0.5"></i>{{ __('events::events.order_bout_decided') }}
                            </p>

                            <div x-show="! num.match.decided" class="mb-4 rounded-xl px-3.5 py-2.5 text-sm font-semibold flex items-center gap-2"
                                 :class="num.match.pinned ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
                                <i class="bi" :class="num.match.pinned ? 'bi-pin-angle-fill' : 'bi-magic'"></i>
                                <span class="flex-1" x-text="num.match.pinned ? @js(__('events::events.tablet_number_pinned')) : @js(__('events::events.tablet_number_auto'))"></span>
                                <button type="button" x-show="num.match.pinned" @click="releaseNumber()" :disabled="busy"
                                        class="flex-shrink-0 px-3 py-1.5 rounded-full border border-primary text-primary text-xs font-bold hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                                    {{ __('events::events.tablet_number_release') }}
                                </button>
                            </div>

                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('events::events.tablet_number_mat') }}</p>
                            <div class="flex flex-wrap gap-2 mb-5">
                                <template x-for="m in num.mats" :key="m.court">
                                    <button type="button" @click="pickMat(m.court)" :disabled="num.match.decided"
                                            class="h-11 px-4 rounded-xl border text-sm font-bold transition-colors disabled:opacity-50"
                                            :class="num.court === m.court ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                                            x-text="m.court"></button>
                                </template>
                            </div>

                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('events::events.tablet_number_label') }}</p>
                            <div class="flex items-center justify-center gap-4">
                                <button type="button" class="td-key w-16" @click="step(-1)" :disabled="num.match.decided || num.no <= 1" aria-label="−">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <div class="text-center" style="min-width:6rem">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3" autocomplete="off"
                                           class="w-24 text-center text-5xl font-black text-gray-900 bg-transparent border-0 focus:ring-0 p-0"
                                           :value="num.no" :disabled="num.match.decided"
                                           @input="num.no = clampNo(parseInt($event.target.value.replace(/\D/g, ''), 10) || 1); $event.target.value = num.no">
                                    <p class="text-xs text-muted-foreground mt-1" x-text="@js(__('events::events.tablet_number_of', ['n' => '__N__'])).replace('__N__', maxNo())"></p>
                                </div>
                                <button type="button" class="td-key w-16" @click="step(1)" :disabled="num.match.decided || num.no >= maxNo()" aria-label="+">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveNumber()" :disabled="busy || num.loading || ! num.match || num.match.decided || ! num.court"
                            class="w-full h-12 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                        <i class="bi bi-check2-circle"></i>{{ __('events::events.tablet_number_save') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- The PIN keypad only exists while the PIN does (events.tablet_draw_pin). --}}
    @if($pinRequired)
    {{-- ===== The PIN keypad (Design Rule #8 band) ===== --}}
    <template x-teleport="body">
        <div x-show="pad" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true"
             @keydown.window="key($event)">
            <div class="absolute inset-0 bg-black/50" x-show="pad" x-transition.opacity @click="pad = false"></div>

            <div x-show="pad"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-shield-lock-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_pin_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.tablet_pin_sub') }}</p>
                        </div>
                        <button type="button" @click="pad = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 pt-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                    @unless($hasPin)
                        <p class="mb-4 text-sm text-amber-800 bg-amber-50 rounded-xl px-3.5 py-3 flex items-start gap-2">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>{{ __('events::events.tablet_pin_none') }}
                        </p>
                    @endunless

                    <div class="flex justify-center gap-3 mb-2" :class="shake && 'td-shake'">
                        <template x-for="i in 8" :key="i">
                            <span class="td-dot" :class="i <= pin.length && 'on'" x-show="i <= Math.max(4, pin.length)"></span>
                        </template>
                    </div>
                    <p class="h-5 mb-3 text-center text-sm font-semibold text-red-600" x-text="error"></p>

                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="d in ['1','2','3','4','5','6','7','8','9']" :key="d">
                            <button type="button" class="td-key" @click="press(d)" x-text="d"></button>
                        </template>
                        <button type="button" class="td-key" style="font-size:1rem" @click="pin = ''; error = ''">C</button>
                        <button type="button" class="td-key" @click="press('0')">0</button>
                        <button type="button" class="td-key" @click="back()" aria-label="⌫"><i class="bi bi-backspace"></i></button>
                    </div>

                    <button type="button" @click="unlock()" :disabled="busy || pin.length < 4"
                            class="mt-4 w-full h-12 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                        <i class="bi bi-unlock-fill"></i>{{ __('events::events.tablet_unlock') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif
</div>

@include('partials.toast-host')
<x-confirm-dialog />
{{-- Stacks AFTER the body: this is a page, not a layout, so a stack printed
     in <head> would be emptied before the components below pushed into it. --}}
@stack('styles')
@stack('scripts')

<script>
function tabletDraw() {
    return {
        num: { open: false, loading: false, match: null, mats: [], court: null, no: 1 },
        // Add / delete a division (owner, 2026-10-16). `divs` and `cur` follow the board.
        divs: [], cur: null,
        // The one Division control (owner, 2026-10-16): menu, docked editor, switch.
        divMenu: { open: false, style: '' },
        arrMenu: { open: false, style: '' },
        pdfMenu: { open: false, style: '' },
        modeMenu: { open: false, style: '' },
        ed: { open: false, created: false, id: null, letter: null, name: '', gender: '', minAge: '', maxAge: '', minW: '', maxW: '',
              error: '', loading: false, count: null, min: false, timer: null, seq: 0 },
        oneDivision: @json((bool) ($oneDivisionOn ?? false)),
        edPick: false,
        delDiv: { open: false, id: null, name: '', entrants: 0, typed: '', error: '' },
        pad: false, pin: '', error: '', busy: false, shake: false, hint: true, edit: false, intent: null, seen: null, mode: 'move',
        left: {{ (int) $secondsLeft }},
        editing: @json($editing),
        // No-PIN mode (owner, 2026-10-07): the draw always OPENS in view mode.
        // Edit unlocks it; Done locks it back. Every change is saved the moment
        // it is made, so Done has nothing left to save — it only locks.
        viewOnly: ! @json($pinRequired),
        editHint: @json($editorManages ? __('events::events.tablet_hint_manage') : __('events::events.tablet_hint_arrange')),
        sayHint() { if (window.showToast) window.showToast('info', this.editHint); },

        init() {
            const vp = document.getElementById('tdraw-bracket-viewport');

            // Which divisions the board holds, and which is on show — for the
            // add / delete sheets (the board says so on every load and change).
            if (vp) {
                const follow = (e) => { const d = e.detail || {}; if (Array.isArray(d.divisions)) this.divs = d.divisions; if (d.division !== undefined) this.cur = d.division; };
                vp.addEventListener('bracket:loaded', follow);
                vp.addEventListener('bracket:state', follow);
                // A candidate went onto the board (or somebody came off it):
                // the basket's list is re-read, so nobody shows twice.
                vp.addEventListener('bracket:pulled', () => { if (this.ed.open) this.fetchCandidates(); });
                // Walking to another division closes the editor — it was for that one.
                vp.addEventListener('bracket:state', (e) => {
                    const d = e.detail || {};
                    // The board's own picker moved: the editor follows (saving first).
                    if (this.ed.open && d.division !== undefined && d.division !== this.ed.id && ! this.busy) this.followBoard(d.division);
                });
            }

            // Unlocked by PIN means the board opens ready to drag — the PIN WAS
            // the padlock, a second one would just be another tap.
            if (this.editing && vp) {
                vp.addEventListener('bracket:loaded', () => {
                    window.BracketBoard && window.BracketBoard.setUnlocked && window.BracketBoard.setUnlocked(! this.viewOnly);
                    if (! this.viewOnly) this.sayHint();
                    // "Late entry" was what they asked for before the PIN.
                    let after = null;
                    try { after = sessionStorage.getItem('tdraw-after'); sessionStorage.removeItem('tdraw-after'); } catch (e) {}
                    if (after === 'add') window.dispatchEvent(new CustomEvent('bracket-add:open'));
                }, { once: true });
                vp.addEventListener('bracket:state', (e) => {
                    const d = e.detail || {};
                    this.edit = !! d.edit;
                    if (this.mode === 'names' && ! d.edit) this.mode = d.arrange ? 'move' : 'winner';
                });
            }

            // Only a PIN unlock runs out; without a PIN there is nothing to count down.
            if (this.editing && @json($pinRequired)) {
                setInterval(() => {
                    this.left = Math.max(0, this.left - 1);
                    if (this.left === 0) window.location.reload();   // the grant ran out
                }, 1000);
            }

            // "Bout no." mode: a tap on a bout opens its number sheet. Caught on
            // the way DOWN (capture), so the board never also reads it as a
            // winner tap; a press that wandered was a pan, not a tap.
            if (this.editing && @json($editorManages) && ! window.__tdrawNumTap) {
                // The board captures the pointer while it is pressed (to pan), so
                // the click that follows is addressed to the VIEWPORT, not to the
                // card. The card is therefore read where the press began.
                const SEL = '#tdraw-bracket-viewport .bk-match[data-match-id]';
                let downAt = null, downCard = null;
                document.addEventListener('pointerdown', (e) => {
                    downAt = [e.clientX, e.clientY];
                    downCard = e.target.closest ? e.target.closest(SEL) : null;
                }, true);
                window.__tdrawNumTap = (e) => {
                    if (this.mode !== 'numbers' || this.viewOnly) return;
                    const card = (e.target.closest && e.target.closest(SEL)) || downCard;
                    if (! card) return;
                    e.stopPropagation(); e.preventDefault();
                    if (downAt && Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) > 8) return;
                    this.openNumber(Number(card.dataset.matchId));
                };
                document.addEventListener('click', window.__tdrawNumTap, true);
            }

            // Keep a READING tablet current, and notice a tablet that has been
            // unpaired: 404 is the only answer that means "this identity is
            // gone" — reload, and the page sends it to the pairing room.
            setInterval(async () => {
                try {
                    const r = await fetch(@js($u['data']), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (r.status === 404) { window.location.reload(); return; }
                    if (! r.ok) return;
                    // Redraw only when the draw actually CHANGED: a redraw replays
                    // the cards' entrance, and every 30 seconds that read as the
                    // page refreshing itself. Never while somebody is editing.
                    const text = await r.text();
                    if (this.seen !== null && text !== this.seen && (! this.editing || this.viewOnly) && window.BracketBoard) {
                        window.BracketBoard.reload(true);
                    }
                    this.seen = text;
                } catch (e) { /* the wifi, not the tablet — leave it alone */ }
            }, 30000);
        },

        /* move: drag competitors (the board's arrange mode)
           winner: a tap on a competitor sets the bout's winner / score
           names: a tap on a name opens it for correction */
        setMode(m) {
            const BB = window.BracketBoard;
            if (! BB) return;
            this.mode = m;
            // Remove winner is Winner with every tap meaning "take it back".
            if (BB.setDecideMode) BB.setDecideMode(m === 'unwin' ? 'clear' : 'set');
            if (m === 'unwin') m = 'winner';
            // How to set — and take back — a winner, said once when the mode opens.
            if (this.mode === 'winner' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_winner')));
            if (this.mode === 'unwin' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_unwin')));
            if (this.mode === 'numbers' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_numbers')));
            if (m === 'names') { if (! this.edit) BB.toggleEdit(); return; }
            if (this.edit) BB.toggleEdit();
            BB.toggleArrange(m === 'move');
        },

        async openNumber(id) {
            if (! id) return;
            this.num = { open: true, loading: true, match: null, mats: [], court: null, no: 1 };
            try {
                const r = await fetch(@js($u['number']).replace('999999999', id),
                    { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (! r.ok || ! d.success) {
                    this.num.open = false;
                    if (d.locked) { window.location.reload(); return; }
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.match = d.match;
                this.num.mats = d.mats || [];
                this.num.court = d.match.court || (this.num.mats[0] && this.num.mats[0].court) || null;
                this.num.no = this.clampNo(d.match.no || this.maxNo());
            } catch (e) {
                this.num.open = false;
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.num.loading = false; }
        },
        /* The highest number this bout can take on the chosen mat: that mat's
           bouts, plus one when the bout is joining it from somewhere else. */
        maxNo() {
            const m = this.num.mats.find(x => x.court === this.num.court);
            const n = m ? m.count : 0;
            return Math.max(1, this.num.match && this.num.match.court === this.num.court ? n : n + 1);
        },
        clampNo(n) { return Math.max(1, Math.min(this.maxNo(), n || 1)); },
        step(d) { this.num.no = this.clampNo(this.num.no + d); },
        pickMat(court) { this.num.court = court; this.num.no = this.clampNo(this.num.no); },
        async releaseNumber() {
            if (this.busy || ! this.num.match) return;
            this.busy = true;
            try {
                const r = await fetch(@js($u['numberRelease']).replace('999999999', this.num.match.id), {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (d.locked) { window.location.reload(); return; }
                if (! r.ok || ! d.success) {
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.open = false;
                window.showToast && window.showToast('success', d.message || @js(__('events::events.order_saved')));
                window.BracketBoard && window.BracketBoard.reload(true);
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },
        async saveNumber() {
            if (this.busy || ! this.num.match || ! this.num.court) return;
            this.busy = true;
            try {
                const r = await fetch(@js($u['renumber']), {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ match: this.num.match.id, court: this.num.court, no: this.num.no, day: this.num.match.day }),
                });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (d.locked) { window.location.reload(); return; }
                if (! r.ok || ! d.success) {
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.open = false;
                window.showToast && window.showToast('success', d.message || @js(__('events::events.order_saved')));
                window.BracketBoard && window.BracketBoard.reload(true);
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },

        done() {
            if (this.ed.open) this.closeEditor();
            const BB = window.BracketBoard;
            if (BB && BB.setDecideMode) BB.setDecideMode('set');
            if (BB && BB.setUnlocked) BB.setUnlocked(false);
            this.viewOnly = true; this.mode = 'move';
        },
        resume() {
            const BB = window.BracketBoard;
            this.viewOnly = false; this.mode = 'move';
            if (BB && BB.setUnlocked) BB.setUnlocked(true);
            this.sayHint();
        },

        clock() {
            const m = Math.floor(this.left / 60), s = this.left % 60;
            return m + ':' + String(s).padStart(2, '0');
        },

        openPad(intent) { this.intent = intent || null; this.pin = ''; this.error = ''; this.pad = true; },
        press(d) { if (this.pin.length < 8) { this.pin += d; this.error = ''; } },
        back() { this.pin = this.pin.slice(0, -1); },
        key(e) {
            if (! this.pad) return;
            if (/^\d$/.test(e.key)) this.press(e.key);
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Enter') this.unlock();
        },

        /* ── The tool control (what a tap does) ─────────────────────────── */
        openModeMenu() {
            const r = this.$refs.modeBtn ? this.$refs.modeBtn.getBoundingClientRect() : { bottom: 60, left: 12, right: 12 };
            const w = Math.min(300, window.innerWidth - 24);
            const rtl = document.documentElement.dir === 'rtl';
            let x = rtl ? r.right - w : r.left;
            x = Math.max(12, Math.min(x, window.innerWidth - w - 12));
            this.modeMenu = { open: true, style: 'top:' + (r.bottom + 8) + 'px;left:' + x + 'px;' };
        },

        /* ── The Download control (this / all · download / share) ───────── */
        openPdfMenu() {
            const r = this.$refs.pdfBtn ? this.$refs.pdfBtn.getBoundingClientRect() : { bottom: 60, left: 12, right: 12 };
            const w = Math.min(300, window.innerWidth - 24);
            const rtl = document.documentElement.dir === 'rtl';
            let x = rtl ? r.right - w : r.left;
            x = Math.max(12, Math.min(x, window.innerWidth - w - 12));
            this.pdfMenu = { open: true, style: 'top:' + (r.bottom + 8) + 'px;left:' + x + 'px;' };
        },

        /* ── The Arrange control (Shuffle · Renumber bouts) ─────────────── */
        openArrMenu() {
            const r = this.$refs.arrBtn ? this.$refs.arrBtn.getBoundingClientRect() : { bottom: 60, left: 12, right: 12 };
            const w = Math.min(300, window.innerWidth - 24);
            const rtl = document.documentElement.dir === 'rtl';
            let x = rtl ? r.right - w : r.left;
            x = Math.max(12, Math.min(x, window.innerWidth - w - 12));
            this.arrMenu = { open: true, style: 'top:' + (r.bottom + 8) + 'px;left:' + x + 'px;' };
        },

        /* ── The Division control ───────────────────────────────────────── */
        curDiv() { return this.divs.find((x) => x.id === this.cur && ! x.is_heading) || null; },
        openDivMenu() {
            const r = this.$refs.divBtn ? this.$refs.divBtn.getBoundingClientRect() : { bottom: 60, left: 12, right: 12 };
            const w = Math.min(300, window.innerWidth - 24);
            const rtl = document.documentElement.dir === 'rtl';
            let x = rtl ? r.right - w : r.left;
            x = Math.max(12, Math.min(x, window.innerWidth - w - 12));
            this.divMenu = { open: true, style: 'top:' + (r.bottom + 8) + 'px;left:' + x + 'px;' };
        },
        divHeaders() {
            return { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                     'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content };
        },
        async divSend(method, url, body) {
            const r = await fetch(url, { method, credentials: 'same-origin', headers: this.divHeaders(), body: body ? JSON.stringify(body) : undefined });
            const data = await r.json().catch(() => ({}));
            return { ok: r.ok && data.success !== false, data };
        },
        divError(data) {
            const first = data && data.errors ? Object.values(data.errors)[0] : null;
            return (Array.isArray(first) ? first[0] : first) || (data && data.message) || @js(__('events::events.action_failed'));
        },
        /* The editor needs the board in Move with its basket out. */
        boardForEditing() {
            if (this.mode !== 'move') this.setMode('move');
            const BB = window.BracketBoard;
            if (BB && BB.state && ! BB.state().arrange && BB.toggleArrange) BB.toggleArrange(true);
        },
        fillEditor(d, created) {
            const r = (d && d.range) || {};
            const n = (v) => (v === null || v === undefined) ? '' : String(+Number(v).toFixed(2));
            const m = /^Group ([A-Z])(?: : .+)?$/.exec(String((d && d.name) || '').trim());
            this.ed = Object.assign(this.ed, {
                open: true, created: !! created, id: d.id, letter: m ? m[1] : null,
                // A generated name is re-made from the range on save; a typed one is kept.
                name: m ? '' : (d.name || ''),
                gender: r.gender === 'male' ? 'Male' : r.gender === 'female' ? 'Female' : '',
                minAge: n(r.min_age), maxAge: n(r.max_age), minW: n(r.min_weight), maxW: n(r.max_weight),
                error: '', min: false,
            });
            this.ed.snap = this.edSnap();
            this.edPick = false;
            this.boardForEditing();
            this.fetchCandidates();
        },
        /* The board's own division look (blue / pink / mixed, ages · weights). */
        dLook(fn, d) { const L = window.DrawDivisionLook; return (L && d) ? L[fn](d, arguments[2]) : ''; },
        edDivisions() { return window.DrawDivisionLook ? window.DrawDivisionLook.sort(this.divs) : this.divs.filter((d) => ! d.is_heading); },
        edSnap() { return JSON.stringify([this.ed.name, this.ed.gender, this.ed.minAge, this.ed.maxAge, this.ed.minW, this.ed.maxW]); },
        edDirty() { return this.ed.open && this.ed.snap !== undefined && this.edSnap() !== this.ed.snap; },
        /* Move the editor (and the board) to another division. Unsaved changes
           to the one being left are SAVED first — the editor's whole point is
           speed, and a jump that threw typing away would be a trap. */
        async switchEditor(id) {
            this.edPick = false;
            if (! id || id === this.ed.id) return;
            if (this.edDirty() && ! (await this.saveDivision(true))) return;
            const d = this.divs.find((x) => x.id === id);
            if (d) this.fillEditor(d, false);
            if (window.BracketBoard) window.BracketBoard.show(id);
        },
        async addDivision() {
            this.divMenu.open = false;
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await this.divSend('POST', @js($u['divisions'] ?? ''), { name: '', auto_name: true, is_heading: false, format: 'knockout' });
                if (! res.ok) { window.showToast && window.showToast('error', this.divError(res.data)); return; }
                const d = res.data.division || {};
                const BB = window.BracketBoard;
                if (BB) { await Promise.resolve(BB.reload(true)); if (d.id) BB.show(d.id); }
                this.fillEditor({ id: d.id, name: d.name, range: {} }, true);
            } finally { this.busy = false; }
        },
        editDivision() {
            this.divMenu.open = false;
            const d = this.curDiv();
            if (d) this.fillEditor(d, false);
        },
        num2(v) { const x = String(v ?? '').trim(); return x === '' || isNaN(Number(x)) ? null : Number(x); },
        edRange() {
            const a1 = this.num2(this.ed.minAge), a2 = this.num2(this.ed.maxAge), w1 = this.num2(this.ed.minW), w2 = this.num2(this.ed.maxW);
            return { a1: a1 === null ? null : Math.trunc(a1), a2: a2 === null ? null : Math.trunc(a2), w1, w2 };
        },
        edPlaceholder() {
            const { a1, a2, w1, w2 } = this.edRange(), num = (v) => String(+Number(v).toFixed(2));
            const bits = [];
            if (a1 !== null || a2 !== null) bits.push(a1 !== null && a2 !== null ? (a1 === a2 ? num(a1) : num(a1) + '-' + num(a2)) + 'y' : a1 !== null ? num(a1) + 'y+' : '≤' + num(a2) + 'y');
            if (w1 !== null || w2 !== null) bits.push(w1 !== null && w2 !== null ? (w1 === w2 ? num(w1) : num(w1) + '-' + num(w2)) + 'kg' : w1 !== null ? '+' + num(w1) + 'kg' : '-' + num(w2) + 'kg');
            const head = this.ed.letter ? 'Group ' + this.ed.letter : @js(__('events::events.tablet_div_auto'));
            return head + (bits.length ? ' : ' + bits.join(', ') : '');
        },
        edCountText() {
            if (this.ed.count === null) return @js(__('events::events.tablet_div_fits_range'));
            return @js(__('events::events.tablet_div_fits')) + ': ' + this.ed.count;
        },
        edChanged() {
            clearTimeout(this.ed.timer);
            this.ed.timer = setTimeout(() => this.fetchCandidates(), 250);
        },
        /* Whoever fits the range, into the board's basket (BracketBoard.setCandidates).
           Exact to the hundredth of a kilo: the server compares the stored weight. */
        async fetchCandidates() {
            const BB = window.BracketBoard;
            if (! this.ed.open || ! BB || ! BB.setCandidates) return;
            const { a1, a2, w1, w2 } = this.edRange();
            if (! this.ed.gender && a1 === null && a2 === null && w1 === null && w2 === null) {
                this.ed.count = null; BB.setCandidates(false); return;
            }
            const q = new URLSearchParams({ basket: '1' });
            if (this.ed.id) q.set('category_id', this.ed.id);
            if (this.ed.gender) q.set('gender', this.ed.gender);
            if (a1 !== null) q.set('min_age', a1);
            if (a2 !== null) q.set('max_age', a2);
            if (w1 !== null) q.set('min_weight', w1);
            if (w2 !== null) q.set('max_weight', w2);
            const seq = ++this.ed.seq;
            this.ed.loading = true;
            try {
                const r = await fetch(@js($u['candidates']) + '?' + q.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await r.json().catch(() => ({}));
                if (seq !== this.ed.seq || ! this.ed.open) return;
                if (! r.ok || ! data.success) { this.ed.error = this.divError(data); return; }
                this.ed.error = '';
                // Their own basket already shows this division's entrants.
                const rows = (data.candidates || []).filter((c) => ! c.in_this_division);
                this.ed.count = rows.length;
                BB.setCandidates(rows);
            } catch (e) {
                if (seq === this.ed.seq) this.ed.error = @js(__('events::events.action_failed'));
            } finally { if (seq === this.ed.seq) this.ed.loading = false; }
        },
        async saveDivision(keepOpen) {
            if (this.busy || ! this.ed.id) return false;
            const { a1, a2, w1, w2 } = this.edRange();
            const name = this.ed.name.trim() || this.edPlaceholder().replace(@js(__('events::events.tablet_div_auto')), 'Group ' + (this.ed.letter || 'A'));
            this.busy = true; this.ed.error = '';
            try {
                const res = await this.divSend('PATCH', @js($u['divisions'] ?? '') + '/' + this.ed.id,
                    { name, gender: this.ed.gender || null, min_age: a1, max_age: a2, min_weight: w1, max_weight: w2 });
                if (! res.ok) { this.ed.error = this.divError(res.data); return false; }
                window.showToast && window.showToast('success', name + ' — ' + @js(__('events::events.tablet_div_saved')));
                this.ed.created = false;
                this.ed.snap = this.edSnap();
                const BB = window.BracketBoard;
                if (BB) await Promise.resolve(BB.reload(true));
                if (keepOpen !== true) this.closeEditor();
                return true;
            } finally { this.busy = false; }
        },
        async followBoard(id) {
            const from = this.ed.id;
            if (this.edDirty() && ! (await this.saveDivision(true))) { if (window.BracketBoard) window.BracketBoard.show(from); return; }
            const d = this.divs.find((x) => x.id === id);
            if (d) this.fillEditor(d, false); else this.closeEditor();
        },
        closeEditor() {
            clearTimeout(this.ed.timer);
            this.ed.open = false; this.ed.seq++;
            window.BracketBoard && window.BracketBoard.setCandidates && window.BracketBoard.setCandidates(null);
        },
        /* A division just added and still empty can be thrown away. */
        async discardDivision() {
            if (this.busy || ! this.ed.id) return;
            this.busy = true;
            try {
                const res = await this.divSend('DELETE', @js($u['divisions'] ?? '') + '/' + this.ed.id, { release: true });
                if (! res.ok) { this.ed.error = this.divError(res.data); return; }
                this.closeEditor();
                if (window.BracketBoard) await Promise.resolve(window.BracketBoard.reload(false));
            } finally { this.busy = false; }
        },
        async toggleOneDivision() {
            if (this.busy || ! @js($u['oneDivision'] ?? null)) return;
            this.busy = true;
            try {
                const res = await this.divSend('PUT', @js($u['oneDivision'] ?? ''), { on: ! this.oneDivision });
                if (! res.ok) { window.showToast && window.showToast('error', this.divError(res.data)); return; }
                this.oneDivision = !! res.data.one_division;
                window.showToast && window.showToast('success', res.data.message);
                if (this.ed.open) this.fetchCandidates();
            } finally { this.busy = false; }
        },

        /* ── Delete the division on show — typed confirmation ─────────── */
        openDelDiv() {
            const d = this.divs.find((x) => x.id === this.cur);
            if (! d) return;
            this.delDiv = { open: true, id: d.id, name: d.name, entrants: d.entrants || 0, typed: '', error: '' };
        },
        delDivBody() {
            return @js(__('events::events.tablet_div_del_body')).replace(':n', this.delDiv.entrants);
        },
        async deleteDiv() {
            if (this.busy || this.delDiv.typed.trim().toUpperCase() !== 'DELETE' || ! this.delDiv.id) return;
            this.busy = true; this.delDiv.error = '';
            try {
                const r = await fetch(@js($u['divisions'] ?? '') + '/' + this.delDiv.id, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ release: true }),
                });
                const data = await r.json().catch(() => ({}));
                if (! r.ok || data.success === false) { this.delDiv.error = data.message || @js(__('events::events.action_failed')); return; }
                const name = this.delDiv.name;
                this.delDiv.open = false;
                window.showToast && window.showToast('success', name + ' — ' + @js(__('events::events.tablet_div_deleted')));
                if (window.BracketBoard) await Promise.resolve(window.BracketBoard.reload(false));
            } catch (e) {
                this.delDiv.error = @js(__('events::events.action_failed'));
            } finally { this.busy = false; }
        },

        async post(url, body) {
            const r = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                           'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body || {}),
            });
            if (r.status === 404) { window.location.reload(); return null; }
            return { ok: r.ok, data: await r.json().catch(() => ({})) };
        },

        async unlock() {
            if (this.busy || this.pin.length < 4) return;
            this.busy = true;
            try {
                const res = await this.post(@js($u['unlock']), { pin: this.pin });
                if (! res) return;
                if (res.ok && res.data.success) {
                    try { if (this.intent === 'add') sessionStorage.setItem('tdraw-after', 'add'); } catch (e) {}
                    window.location.reload();
                    return;
                }
                this.error = res.data.message || @js(__('events::events.tablet_pin_wrong'));
                this.pin = '';
                this.shake = true; setTimeout(() => this.shake = false, 500);
                navigator.vibrate && navigator.vibrate(120);
            } catch (e) {
                this.error = @js(__('events::events.action_failed'));
            } finally { this.busy = false; }
        },

        /* Shuffle the division on show — asked first, because it replaces
           every hand move in that division. */
        async shuffle() {
            const BB = window.BracketBoard, division = BB && BB.division ? BB.division() : null;
            if (this.busy || ! division) return;
            const ok = window.confirmAction ? await window.confirmAction({
                title: @js(__('events::events.shuffle_confirm_title')),
                message: @js(__('events::events.shuffle_confirm_body')),
                confirmText: @js(__('events::events.shuffle_confirm')), type: 'warning',
            }) : true;
            if (! ok) return;
            this.busy = true;
            try {
                const res = await this.post(@js($u['shuffle'] ?? ''), { category_id: division });
                if (! res) return;
                if (res.data.locked) { window.location.reload(); return; }
                if (res.ok && res.data.success) {
                    window.showToast && window.showToast('success', res.data.message || @js(__('events::events.draw_shuffled')));
                    BB.reload(true);
                } else {
                    window.showToast && window.showToast('error', res.data.message || @js(__('events::events.action_failed')));
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },

        /* "Renumber bouts" — every bout numbered again from the running order. */
        async renumberAll() {
            if (this.busy) return;
            const ok = window.confirmAction ? await window.confirmAction({
                title: @js(__('events::events.renumber_confirm_title')),
                message: @js(__('events::events.renumber_confirm_body')),
                confirmText: @js(__('events::events.renumber_confirm')), type: 'warning',
            }) : true;
            if (! ok) return;
            this.busy = true;
            try {
                const res = await this.post(@js($u['renumberAll'] ?? ''));
                if (! res) return;
                if (res.data.locked) { window.location.reload(); return; }
                window.showToast && window.showToast(res.ok && res.data.success ? 'success' : 'error',
                    res.data.message || @js(__('events::events.action_failed')));
                if (res.ok && res.data.success && window.BracketBoard) window.BracketBoard.reload(true);
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },

        async lock() {
            this.busy = true;
            try { await this.post(@js($u['lock'])); } catch (e) {}
            window.location.reload();
        },
    };
}
</script>
</body>
</html>
