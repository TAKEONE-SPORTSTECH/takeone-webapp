{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the platform layout exactly as before. See entry/shell.

     ⚠️ It matters MORE here than on the other event screens: this page carried
     the platform's whole top bar and account drawer into the sealed surface —
     five links to /explore, the member menu, and a POST sign-out form that
     actually signed the organiser out mid-event and left them on the platform
     with no way back into an installed home-screen app. --}}
@extends($shell ?? 'layouts.app')

{{-- The board is sport-neutral, so its title comes from the shared event
     vocabulary. It used to read the TAEKWONDO package's messages file, which
     BJJ has no equivalent of — a borrowed string that would vanish the day
     that package moved or was deleted. --}}
@section('title', __('events.board_title', ['event' => $e['title']]))

{{-- A venue board is a DISPLAY, not an app page: no member search, no
     notifications, no account drawer, and above all no sign-out form within
     reach of a screen propped on the officials' table. The sealed shell already
     sets this; setting it here gives the platform route the same bare surface.
     `layouts.app` reads the section to decide whether to draw the top bar. --}}
@section('hide-navbar', true)

{{--
    Venue board — a hall screen, not an app page.

    ONE view rather than a mobile/desktop pair: a board is a single display
    surface that scales, not two diverging layouts. A phone propped on the
    officials' table and a 55" panel above the mats show the same thing at
    different sizes.

    Design brief it answers: readable across a sports hall. Dark, so it does not
    glare; huge type; the red/blue corners of Taekwondo carrying the meaning
    instead of decoration; on-deck bouts visibly quieter than the live one.

    Refreshes on the realtime channel AND polls slowly, because arena wifi drops
    and a board that silently freezes is worse than one a few seconds stale.
--}}
@section($contentSection ?? 'content')
<div x-data="matBoard(@js($mats), '{{ route('me.events.board', $e['key']) }}{{ request('mat') ? '?mat='.urlencode(request('mat')) : '' }}')"
     x-init="start()"
     {{-- Full-bleed on BOTH shells, without ever growing wider than the
          viewport. `layouts.app` yields into a bare, unpadded <main>, so the
          board is already edge to edge there — the old
          `-mx-4 sm:-mx-6 lg:-mx-8 -my-6` had no padding to cancel and simply
          pushed the surface 4rem past the screen, which in RTL clipped the
          title, the mat plate and the console button off the right edge. The
          sealed shell DOES pad (`px-4 py-4`), so there the negative margin is
          real and stays. --}}
     class="vb {{ isset($shell) ? '-mx-4 -mt-4' : '' }}"
     {{-- The board's payload is the LEAN eventView (no `full:`), which carries
          no colour — so it falls back to the platform's own accent rather than
          exploding. Guarded by Palette either way: it lands in a style
          attribute. --}}
     style="--ev: {{ \App\Support\Palette::safe($e['color'] ?? '#7c3aed') }};">

    {{-- ===== The board's own styling =====

         Real CSS, not utilities: the Tailwind bundle is PREBUILT, so an
         arbitrary value nobody has used before has no rule at all — and this
         screen needs `clamp()` above everything, because ONE view has to be
         readable on a phone propped against the officials' table and on a 55"
         panel above the mats. Sizes scale with the viewport instead of the
         layout forking in two.

         The palette is the EVENT's, taken from the poster: its colour as the
         only accent, the same gradient band, the same soft circles for depth.
         The ground is that colour taken almost to black rather than a flat
         grey, so the board reads as this competition's board and not as a
         generic dark theme. --}}
    <style>
        .vb {
            min-height: 100vh;
            color: #fff;
            background:
                radial-gradient(120% 80% at 12% -10%, color-mix(in srgb, var(--ev) 26%, transparent), transparent 60%),
                radial-gradient(90% 70% at 105% 8%, color-mix(in srgb, var(--ev) 18%, transparent), transparent 55%),
                #070a12;
        }
        /* The poster's band, at hall scale. */
        .vb-hero {
            position: relative; overflow: hidden;
            padding: clamp(14px, 2.2vw, 26px) clamp(16px, 2.6vw, 34px) clamp(16px, 2.6vw, 30px);
            background: linear-gradient(150deg, var(--ev), color-mix(in srgb, var(--ev) 69%, transparent));
        }
        .vb-circle { position: absolute; border-radius: 50%; background: rgba(255,255,255,.10); pointer-events: none; }
        .vb-eyebrow { font-size: clamp(10px, .8vw, 13px); font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: rgba(255,255,255,.72); }
        .vb-title { font-size: clamp(22px, 2.6vw, 44px); font-weight: 900; letter-spacing: -.02em; line-height: 1.05; margin-top: 6px; }

        /* Live / stale, as a pill rather than a stray dot and word. */
        .vb-state { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px;
                    background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.24);
                    font-size: clamp(10px, .82vw, 13px); font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .vb-pulse { width: 9px; height: 9px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 0 rgba(52,211,153,.65); animation: vbPulse 2s ease-out infinite; }
        .vb-pulse.is-stale { background: #fbbf24; animation: none; }
        @keyframes vbPulse { 70% { box-shadow: 0 0 0 10px rgba(52,211,153,0); } 100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); } }

        /* The mats. A grid that fills whatever it is given — two up on a
           laptop, three on a wall, one on a phone — without a breakpoint per
           screen. */
        .vb-mats { display: grid; gap: clamp(12px, 1.4vw, 22px);
                   padding: clamp(14px, 1.8vw, 26px) clamp(14px, 2.6vw, 34px) clamp(26px, 3vw, 46px);
                   grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr)); }
        .vb-mat { border-radius: clamp(18px, 1.6vw, 28px); overflow: hidden;
                  background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.1);
                  box-shadow: 0 30px 60px -40px rgba(0,0,0,.9);
                  animation: vbRise .5s cubic-bezier(.22,.61,.36,1) both; }
        @keyframes vbRise { from { opacity: 0; transform: translateY(10px); } }
        .vb-mat-head { display: flex; align-items: center; gap: 12px;
                       padding: clamp(10px, 1vw, 16px) clamp(14px, 1.4vw, 22px);
                       background: rgba(255,255,255,.06); border-bottom: 1px solid rgba(255,255,255,.07); }
        /* The mat PLATE: how a mat is known across a hall. */
        .vb-plate { display: grid; place-items: center; flex-shrink: 0;
                    min-width: clamp(40px, 3.4vw, 62px); height: clamp(40px, 3.4vw, 62px);
                    padding: 0 10px; border-radius: clamp(12px, 1vw, 18px);
                    background: color-mix(in srgb, var(--ev) 28%, transparent);
                    border: 1px solid color-mix(in srgb, var(--ev) 55%, transparent);
                    font-size: clamp(14px, 1.3vw, 22px); font-weight: 900; letter-spacing: -.01em; }
        .vb-code { font-variant-numeric: tabular-nums; font-size: clamp(11px, .95vw, 15px); font-weight: 700; color: rgba(255,255,255,.45); }

        .vb-body { padding: clamp(12px, 1.4vw, 22px) clamp(14px, 1.4vw, 22px); }
        .vb-round { font-size: clamp(10px, .85vw, 14px); font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
                    color: color-mix(in srgb, var(--ev) 72%, #fff); margin-bottom: clamp(8px, .9vw, 14px); }

        /* A corner is a RAIL plus a name — the colour carries the meaning, so it
           runs the full height of the row rather than sitting in a chip. */
        .vb-corner { position: relative; display: flex; align-items: center; gap: clamp(10px, 1vw, 16px);
                     border-radius: clamp(12px, 1.1vw, 20px); overflow: hidden;
                     padding: clamp(10px, 1.1vw, 18px) clamp(12px, 1.2vw, 20px);
                     background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); }
        .vb-corner + .vb-corner { margin-top: clamp(6px, .7vw, 12px); }
        .vb-corner::before { content: ''; position: absolute; inset-inline-start: 0; top: 0; bottom: 0; width: clamp(5px, .5vw, 9px); }
        .vb-corner.red    { background: rgba(239,68,68,.14);  border-color: rgba(239,68,68,.32); }
        .vb-corner.red::before { background: #ef4444; }
        .vb-corner.blue   { background: rgba(59,130,246,.14); border-color: rgba(59,130,246,.32); }
        .vb-corner.blue::before { background: #3b82f6; }
        .vb-name { font-size: clamp(19px, 2.1vw, 38px); font-weight: 900; letter-spacing: -.02em; line-height: 1.1;
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-inline-start: clamp(6px, .6vw, 12px); }

        /* On deck: quieter, and numbered — "you are third" is the question it
           answers. */
        .vb-deck { padding: 0 clamp(14px, 1.4vw, 22px) clamp(14px, 1.4vw, 22px); }
        .vb-deck-label { font-size: clamp(10px, .8vw, 13px); font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
                         color: rgba(255,255,255,.35); margin-bottom: clamp(6px, .7vw, 12px); }
        .vb-next { display: flex; align-items: center; gap: clamp(8px, .9vw, 14px);
                   padding: clamp(6px, .7vw, 11px) clamp(8px, .8vw, 12px); border-radius: 12px; }
        .vb-next + .vb-next { margin-top: 2px; }
        .vb-next.is-first { background: rgba(255,255,255,.06); }
        .vb-num { display: grid; place-items: center; flex-shrink: 0;
                  width: clamp(22px, 1.7vw, 30px); height: clamp(22px, 1.7vw, 30px); border-radius: 9px;
                  background: rgba(255,255,255,.08); font-variant-numeric: tabular-nums;
                  font-size: clamp(11px, .9vw, 15px); font-weight: 900; color: rgba(255,255,255,.6); }
        .vb-next.is-first .vb-num { background: color-mix(in srgb, var(--ev) 35%, transparent); color: #fff; }
        .vb-pair { min-width: 0; font-size: clamp(13px, 1.05vw, 19px); font-weight: 700; color: rgba(255,255,255,.62);
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .vb-next.is-first .vb-pair { color: rgba(255,255,255,.92); }
        .vb-v { color: rgba(255,255,255,.3); font-weight: 800; padding: 0 .35em; }

        .vb-idle { display: grid; place-items: center; text-align: center; padding: clamp(60px, 12vh, 160px) 24px; color: rgba(255,255,255,.35); }
        .vb-idle i { font-size: clamp(38px, 4vw, 68px); color: color-mix(in srgb, var(--ev) 60%, #fff); }

        @media (prefers-reduced-motion: reduce) {
            .vb-mat { animation: none; }
            .vb-pulse { animation: none; }
        }
    </style>

    {{-- ⚠️ A WAY OUT. This page had none — no back, no home — and it is reached
         from a console tile on a phone. In an installed home-screen app, with
         no address bar and no reliable back gesture, the only exit was
         force-quitting (the *Unattended Devices* rule: a screen must never
         reach a state it cannot leave).

         ⚠️ And the way out must be a door THIS reader can open. It used to go
         to the console unconditionally, on the assumption — written here — that
         "everybody who reaches this one is a signed-in organiser". They are
         not: `board()` is `assertVisible` only, so any member who can see the
         event can open it, and for them the console answers 403, which the
         handler turns into a bounce to the platform home. One control, and it
         threw them off the event. So the destination follows the PERMISSION:
         the console for somebody who runs the event, the event page for
         everybody else — and the label follows the destination, because a
         control that says "Console" and lands on the event page is its own
         small lie. --}}
    @php
        $boardCanManage = $canManage ?? false;

        $boardBack = ($sealed ?? false)
            ? ($boardCanManage ? url('/e/'.$e['key'].'/admin/manage') : url('/e/'.$e['key']))
            : ($boardCanManage ? route('me.events.manage', $e['key']) : route('me.events.show', $e['key']));

        $boardBackLabel = $boardCanManage
            ? __('personal.event_manage_title')
            : __('personal.event_show_event');
    @endphp

    {{-- ===== The band ===== the poster's, at hall scale --}}
    <header class="vb-hero">
        <span class="vb-circle" style="right:-70px; top:-90px; width:230px; height:230px;"></span>
        <span class="vb-circle" style="right:60px; bottom:-40px; width:120px; height:120px;"></span>

        <div class="relative flex items-center justify-between gap-3">
            <a href="{{ $boardBack }}"
               class="ev-ico inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline flex-shrink-0 hover:bg-white/25 transition-colors"
           aria-label="{{ $boardBackLabel }}" title="{{ $boardBackLabel }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            <span class="vb-state">
                <span class="vb-pulse" :class="stale && 'is-stale'"></span>
                <span x-text="stale ? @js(__('event-taekwondo_tournament::messages.board_stale')) : @js(__('event-taekwondo_tournament::messages.board_live'))"></span>
            </span>
        </div>

        <div class="relative" style="margin-top: clamp(14px, 1.8vw, 26px);">
            <p class="vb-eyebrow">{{ $e['title'] }}</p>
            <h1 class="vb-title">{{ __('event-taekwondo_tournament::messages.board_heading') }}</h1>
        </div>
    </header>

    <template x-if="!mats.length">
        <div class="vb-idle">
            <i class="bi bi-pause-circle"></i>
            <p style="margin-top:14px; font-size:clamp(14px,1.2vw,22px); font-weight:700;">{{ __('event-taekwondo_tournament::messages.board_idle') }}</p>
        </div>
    </template>

    <div class="vb-mats">
        <template x-for="(mat, m) in mats" :key="mat.court">
            <section class="vb-mat" :style="`animation-delay: ${m * 70}ms`">

                <div class="vb-mat-head">
                    <span class="vb-plate" x-text="mat.court"></span>
                    <span class="min-w-0 flex-1"></span>
                    <span class="vb-code" x-show="mat.now" x-text="mat.now?.code"></span>
                </div>

                {{-- The bout on the mat right now --}}
                <template x-if="mat.now">
                    <div class="vb-body">
                        <p class="vb-round" x-text="[mat.now.round, mat.now.division].filter(Boolean).join(' · ')"></p>

                        <div class="vb-corner red">
                            <span class="vb-name" x-text="mat.now.red || '—'"></span>
                        </div>
                        <div class="vb-corner blue">
                            <span class="vb-name" x-text="mat.now.blue || '—'"></span>
                        </div>
                    </div>
                </template>

                {{-- What follows it. Numbered, because the question a competitor
                     asks the board is "how many before me". --}}
                <div class="vb-deck" x-show="mat.on_deck.length">
                    <p class="vb-deck-label">{{ __('event-taekwondo_tournament::messages.board_on_deck') }}</p>
                    <template x-for="(b, i) in mat.on_deck" :key="b.code || i">
                        <div class="vb-next" :class="i === 0 && 'is-first'">
                            <span class="vb-num" x-text="i + 1"></span>
                            <span class="vb-pair">
                                <span x-text="b.red || '—'"></span><span class="vb-v">v</span><span x-text="b.blue || '—'"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </section>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function matBoard(mats, url) {
        return {
            mats, url, stale: false, timer: null,

            async refresh() {
                try {
                    const res = await fetch(this.url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (data.success) { this.mats = data.mats || []; this.stale = false; }
                } catch (e) {
                    // Arena wifi dropped. Keep showing the last good board and
                    // flag it, rather than blanking the screen above the mats.
                    this.stale = true;
                }
            },

            start() {
                // Live channel first — a result lands within a second.
                if (window.__boardHandler) {
                    window.removeEventListener('realtime:events', window.__boardHandler);
                }
                window.__boardHandler = (ev) => {
                    if (['outcome', 'draw', 'entrants'].includes(ev.detail?.action)) this.refresh();
                };
                window.addEventListener('realtime:events', window.__boardHandler);

                // Slow poll as the safety net: a hall screen has nobody to press
                // reload, so it must recover from a dropped socket on its own.
                clearInterval(this.timer);
                this.timer = setInterval(() => this.refresh(), 30000);
            },
        };
    }
</script>
@endpush
