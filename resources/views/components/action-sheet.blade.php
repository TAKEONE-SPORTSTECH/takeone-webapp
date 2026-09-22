@props([
    /* ── REQUIRED ─────────────────────────────────────────────────────────
       A short, stable name for this sheet. Every id inside is built from it
       (`{id}-sheet`, `{id}-name`, …) and it is the handle the JS API takes, so
       a page may carry several sheets without them reaching into each other.
       Letters, digits and dashes; anything else is refused below, because it
       lands in an id and in a querySelector. */
    'id',

    /* The subject's own colour for the band — a club's, an event's, a
       member's. Hex only: the band is `#hex → #hex + b0`, and the `b0` alpha
       suffix is hex-only (Design Rule #8's first non-negotiable, which has
       shipped broken once as `hsl(…)b0`). Whitelisted below. */
    'color' => null,

    /* The tile at the head of the band. `icon` is the glyph it shows; pass
       `photo` when the subject has a face, and the tile becomes a portrait —
       3:4, never square, so a crop the member chose is what the reader sees
       (CLAUDE.md → Profile Pictures Are Portrait 3:4). Either may also be set
       at OPEN time, which is the usual case for a sheet opened over a list. */
    'icon' => 'bi-person',
    'photo' => null,

    /* The first paint of the two text lines. Both are normally replaced when
       the sheet opens — `ActionSheet.open(id, {title, subtitle})` — so these
       are what it says before anything has opened it. */
    'title' => '',
    'subtitle' => '',

    /* ── THE ROWS ─────────────────────────────────────────────────────────
       The sheet's whole purpose, as data:

         [['icon' => 'bi-clipboard2-check-fill',
           'label' => 'Verification desk',
           'hint'  => 'Weigh-in and payment for this entry',
           'onclick' => 'onVerify()',            // or 'click' => an Alpine expr
           'tone'  => 'danger',                  // optional; red row
           'id'    => 'row-verify'], …]

       Given as data rather than as a slot because a row is a SHAPE, not a
       decision: every one of them is an icon tile, a label, a hint and a
       chevron, and a host that writes its own markup for one writes the next
       one slightly differently. A row with neither `onclick` nor `click` is
       inert — useful for a line that is only there to be read. */
    'actions' => [],

    /* Show the drag handle. On by default: it is what says "this is a sheet"
       and every sheet that rises from the bottom edge owes the reader one. */
    'handle' => true,

    /* ── WHICH LAYER IT RISES INTO ────────────────────────────────────────

       Normally nothing: the stylesheet puts the sheet at 50/51, which is above
       an ordinary page and below the app's own chrome, and that is right for
       every sheet opened over a scrolling list.

       It is NOT right inside a host that has already claimed a layer of its
       own. `x-draw-stage` is `fixed inset-0 z-[60]` with an opaque background,
       so a sheet at 50 opens BEHIND it — the backdrop dims nothing, the panel
       is painted under an opaque div, and tapping the thing that opens it looks
       for all the world like a control that does nothing. That is exactly how
       it shipped, and it is why the stage's other two sheets were each written
       at z-[70] by hand.

       So a host that lives in a raised layer says so, and the backdrop takes
       this value while the panel takes one more. Absent — every existing
       caller — nothing is written at all and the stylesheet stands. */
    'layer' => null,
])

@php
    /* ⚠️ THE ID REACHES A DOM ID AND A SELECTOR. Anything that is not a plain
       name is refused rather than escaped — there is no legitimate sheet
       called `x"><script>`, and a component that quietly accepts one is a
       component that will eventually be handed a request value. */
    $sid = preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', (string) $id) ? $id : 'sheet';

    /* ⚠️ It lands in a `style` attribute, so it is a NUMBER or it is nothing —
       never the caller's string. Out-of-range is treated as absent rather than
       clamped: a host asking for a layer this component does not offer has
       made a mistake worth seeing, and the stylesheet's own answer is a safe
       one to fall back to. */
    $asLayer = (is_int($layer) || (is_string($layer) && ctype_digit($layer)))
        && (int) $layer >= 1 && (int) $layer <= 9998
            ? (int) $layer
            : null;

    /* Hex or the platform's own purple. `Palette::safe` is where every colour
       that reaches a `style` attribute goes first — it is caller-supplied text
       and a `style` attribute is an injection sink. */
    $asColor = \App\Support\Palette::safe($color);

    /* Bootstrap Icons only, both here and per row. The name lands in a class
       attribute, and `bi-diagram-3` is rotated for a bracket by
       App\Support\Icon (Design Rule #7), which is why it goes through there
       rather than being concatenated by hand. */
    $tileIcon = \App\Support\Icon::bi($icon, 'text-xl');
@endphp

{{--
    A BOTTOM SHEET, AS A COMPONENT. `x-action-sheet`

    The one sheet shape Design Rule #8 describes, built once: a full-width panel
    rising from the bottom edge, opening with a gradient band that carries the
    drag handle, a tile, the subject's name over a sub-line, and the ✕ — then a
    scrolling list of actions and a safe-area footer.

    It was the participant card's own markup until 2026-09-17, when the owner
    asked for it to be reusable. Nothing about the design changed in the move;
    what changed is that the next sheet does not have to be typed out again and
    cannot drift from this one.

    ── Standalone (CLAUDE.md → Components Are Standalone) ────────────────────

    Drop it anywhere. It brings its own stylesheet and its own open/close
    runtime, each emitted `@once` however many sheets a page draws, and it
    teleports nothing and needs nothing teleported: it is `position: fixed` on
    a root-level element, so it never meets the transformed-ancestor trap the
    mobile shell sets for sheets nested inside `#shell-content`.

    Its ids are namespaced by `id`, so several sheets coexist.

    ── The JS API ────────────────────────────────────────────────────────────

      ActionSheet.open(id, { title, subtitle, photo })
          Open it, setting the band. `photo` may be a URL, or null for the
          glyph. Any key left out leaves that line as it was.

      ActionSheet.close(id)
      ActionSheet.page(id, name)      show a page other than the action list
      ActionSheet.actions(id)         back to the action list
      ActionSheet.isOpen(id)

    Escape closes whichever sheet is open; so does a tap on the backdrop.

    ── Slots ─────────────────────────────────────────────────────────────────

      controls   extra round controls in the band, before the ✕ (an edit
                 pencil, a share). Same 36px round control as the close, so
                 they read as one pair.
      pages      further faces of the same sheet — a second step, a form, a
                 confirmation. Each is `<div data-sheet-page="name">`, hidden
                 until `ActionSheet.page(id, 'name')`. The action list is the
                 page named `actions` and is built for you from `actions`.
      default    anything to append below the action rows, inside the same
                 scrolling page.

    ── Where it is used ──────────────────────────────────────────────────────

      `x-participant-card`  — the organiser's sheet over an entry list.
--}}

<div class="as-backdrop" id="{{ $sid }}-backdrop" data-sheet-close="{{ $sid }}"
     @if($asLayer !== null) style="z-index: {{ $asLayer }};" @endif></div>

<div class="as-sheet" id="{{ $sid }}-sheet" role="dialog" aria-modal="true"
     aria-labelledby="{{ $sid }}-name"
     @if($asLayer !== null) style="z-index: {{ $asLayer + 1 }};" @endif>
    <div class="as-panel rounded-t-3xl bg-background">

        {{-- ===== The band ===== --}}
        <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
             style="background: linear-gradient(150deg, {{ $asColor }}, {{ $asColor }}b0);">
            <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
            @if($handle)
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
            @endif

            <div class="relative flex items-start gap-3">
                {{-- The tile. 36×48 is 3:4 — a face is never shown square on
                     this platform, because a square silently crops the top of
                     somebody's head. The glyph stands in when there is no
                     picture, and the runtime swaps between the two. --}}
                <span class="w-9 h-12 rounded-xl bg-white/20 overflow-hidden flex-shrink-0 grid place-items-center">
                    <img id="{{ $sid }}-photo" src="{{ $photo ?: '' }}" alt=""
                         class="w-full h-full object-cover" @if(! $photo) style="display:none" @endif>
                    <i id="{{ $sid }}-photo-fallback" class="{{ $tileIcon }}"
                       @if($photo) style="display:none" @endif></i>
                </span>

                <div class="min-w-0 flex-1">
                    <h3 id="{{ $sid }}-name" class="text-lg font-black leading-tight truncate">{{ $title }}</h3>
                    <p id="{{ $sid }}-sub" class="text-[12px] text-white/85 mt-0.5 truncate">{{ $subtitle }}</p>
                </div>

                {{-- The host's own header controls, before the ✕. Operations ON
                     the subject the band is naming — correcting them, sharing
                     them — belong here; the rows below are places to GO. --}}
                {{ $controls ?? '' }}

                <button type="button" data-sheet-close="{{ $sid }}" aria-label="{{ __('shared.close') }}"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- ===== The action list ===== --}}
        <div data-sheet-page="actions" class="flex-1 overflow-y-auto px-4 pt-4 space-y-2.5"
             style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

            @foreach($actions as $a)
                @php
                    $danger = ($a['tone'] ?? null) === 'danger';
                    $aIcon = \App\Support\Icon::bi($a['icon'] ?? 'bi-dot', 'text-lg');
                @endphp
                <button type="button"
                        @if($a['id'] ?? null) id="{{ $a['id'] }}" @endif
                        @if($a['onclick'] ?? null) onclick="{{ $a['onclick'] }}" @endif
                        @if($a['click'] ?? null) x-on:click="{{ $a['click'] }}" @endif
                        @if($a['show'] ?? null) x-show="{{ $a['show'] }}" x-cloak @endif
                        class="m-press m-card w-full rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start bg-white border {{ $danger ? 'border-red-100' : 'border-gray-100' }}">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 {{ $danger ? 'bg-red-50 text-red-600' : 'bg-accent text-primary' }}">
                        <i class="{{ $aIcon }}"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[14px] font-black {{ $danger ? 'text-red-600' : 'text-foreground' }}">{{ $a['label'] ?? '' }}</span>
                        @if($a['hint'] ?? null)
                            <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ $a['hint'] }}</span>
                        @endif
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs"></i>
                </button>
            @endforeach

            {{ $slot }}
        </div>

        {{-- ===== The host's other pages ===== --}}
        {{ $pages ?? '' }}
    </div>
</div>

@once
@push('styles')
<style>
/* ===========================================================================
   `x-action-sheet` — the shell only.

   Deliberately plain CSS rather than utilities: the platform's Tailwind bundle
   is PREBUILT, so a class nobody used before has no rule in public/build and
   renders as nothing — and on a height or a z-index that does not fail loudly,
   it fails as a panel that grows past the screen and takes its own footer with
   it. A rule in a page stylesheet cannot do that (CLAUDE.md → the
   prebuilt-bundle note).

   The LOOK of the band and the rows is Tailwind on the markup above, and those
   classes are all in the bundle — checked.
   =========================================================================== */
.as-backdrop{position:fixed;inset:0;z-index:50;background:rgba(15,23,42,.45);display:none}
.as-backdrop.open{display:block}
.as-sheet{position:fixed;left:0;right:0;bottom:0;z-index:51;display:none;justify-content:center}
.as-sheet.open{display:flex}
/* Full WIDTH, capped HEIGHT (owner's call, 2026-09-17). No max-width: a 500px
   card floating in the middle of a tablet at the weigh-in desk was the whole
   complaint. `overflow:hidden` here and `overflow-y:auto` on the page inside,
   so the band never scrolls away and the rounded top actually clips. */
.as-panel{width:100%;display:flex;flex-direction:column;max-height:92vh;overflow:hidden;animation:asSheetUp .22s ease}
@keyframes asSheetUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
/* A page that is not the open one. `display:none` rather than visibility, so a
   hidden page costs no layout on a list of two hundred rows. */
.as-panel [data-sheet-page]{display:none}
.as-panel [data-sheet-page].is-open{display:block}
/* The action list is the one page that must also be the flex child that grows. */
.as-panel [data-sheet-page].is-open{flex:1 1 auto;overflow-y:auto}
@media (prefers-reduced-motion: reduce){ .as-panel{animation:none} }
</style>
@endpush

@push('scripts')
<script>
/* ===========================================================================
   The sheet's runtime. ONE copy however many sheets a page draws.

   Plain functions on `window` rather than an Alpine component, on purpose:
   this sheet is opened from ordinary markup (`onclick="…"`), from Alpine, and
   from a card's own delegated handler, and a component that can only be opened
   from one of those is not reusable. Nothing here holds state beyond which
   page is showing, which lives in the DOM.
   =========================================================================== */
(function () {
    if (window.ActionSheet) { return; }

    function el(id, suffix) { return document.getElementById(id + suffix); }

    window.ActionSheet = {
        open: function (id, o) {
            o = o || {};
            var sheet = el(id, '-sheet');
            if (! sheet) { return; }

            if (Object.prototype.hasOwnProperty.call(o, 'photo')) {
                var img = el(id, '-photo'), fb = el(id, '-photo-fallback');
                if (o.photo) {
                    if (img) { img.src = o.photo; img.style.display = ''; }
                    if (fb) { fb.style.display = 'none'; }
                } else {
                    /* The src is REMOVED, not blanked: an empty src is a
                       request for the current page, which some browsers draw
                       as a broken image. */
                    if (img) { img.removeAttribute('src'); img.style.display = 'none'; }
                    if (fb) { fb.style.display = ''; }
                }
            }

            /* textContent, never innerHTML: a name is somebody's own words and
               is never markup (CLAUDE.md → escape by default). */
            if (o.title !== undefined) { var n = el(id, '-name'); if (n) n.textContent = o.title || ''; }
            if (o.subtitle !== undefined) { var s = el(id, '-sub'); if (s) s.textContent = o.subtitle || ''; }

            this.actions(id);
            var b = el(id, '-backdrop');
            if (b) b.classList.add('open');
            sheet.classList.add('open');
        },

        close: function (id) {
            var b = el(id, '-backdrop'), s = el(id, '-sheet');
            if (b) b.classList.remove('open');
            if (s) s.classList.remove('open');
        },

        isOpen: function (id) {
            var s = el(id, '-sheet');
            return !! (s && s.classList.contains('open'));
        },

        /* Show one face of the sheet. An unknown name shows nothing rather
           than throwing — a page that was removed must not take the sheet
           with it. */
        page: function (id, name) {
            var panel = el(id, '-sheet');
            if (! panel) { return; }
            panel.querySelectorAll('[data-sheet-page]').forEach(function (p) {
                p.classList.toggle('is-open', p.getAttribute('data-sheet-page') === name);
            });
        },

        actions: function (id) { this.page(id, 'actions'); },
    };

    /* Closing, delegated off the document so it works for a sheet added to the
       page after this ran — and so a sheet inside content the mobile shell
       swapped keeps working without rebinding (CLAUDE.md → dedup listeners;
       one delegated handler cannot stack). */
    document.addEventListener('click', function (ev) {
        var t = ev.target.closest('[data-sheet-close]');
        if (t) { window.ActionSheet.close(t.getAttribute('data-sheet-close')); }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') { return; }
        document.querySelectorAll('.as-sheet.open').forEach(function (s) {
            window.ActionSheet.close(s.id.replace(/-sheet$/, ''));
        });
    });
})();
</script>
@endpush
@endonce
