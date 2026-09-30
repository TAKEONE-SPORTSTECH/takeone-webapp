{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.app')

@section('title', ($canManage || $canArrange)
    ? __('personal.personal_event_bracket_manage_draw')
    : __('events::events.public_draw'))

{{--
    THE DRAW — the Combat Sports Bracket, running as itself.

    ⚠️ THE BOARD ON THIS PAGE IS NOT A RE-IMPLEMENTATION OF THE DESIGN FILE.
    It IS the design file. `drafts/Combat Sports Bracket.html` ships React 18
    UMD, a `dc-runtime` that parses its root `x-dc` element, twelve Poppins
    woff2 faces and one `class Component extends DCLogic`. All of it was
    unpacked out of that file and installed verbatim:

        public/bracket/react.production.min.js        (from the file)
        public/bracket/react-dom.production.min.js    (from the file)
        public/bracket/dc-runtime.js                  (from the file)
        public/bracket/fonts/*.woff2                  (from the file, 12 faces)
        resources/views/personal/partials/bracket-board.html
                                                      (its x-dc template and
                                                       its Component class)

    Two edits were made to that last file, and nothing else was touched:

      1. The font `url(…)` references, which the bundler wrote as bare uuids,
         now point at the installed woff2 files.
      2. Its two round-name tables and two height tables carried 4 / 8 / 16
         only. Real divisions are cut to 32 and 64 as well, and the board threw
         on the first one — so those four tables gained the missing entries.
         Additive: every value the file already had is unchanged.

    A third block is APPENDED to the end of its script (below the closing brace
    of the class, touching not one line of it) which hands the mounted instance
    to /bracket/takeone-bridge.js. That bridge is what makes the board real: it
    replaces the file's demo roster with this event's actual draw and posts
    every change back. The mapping is documented in the bridge.

    ── Why the markup is included rather than written here ───────────────────

    Blade's component-tag compiler runs over the WHOLE template before
    anything else — before comments are stripped, and without caring whether it
    is looking at markup, a script or a CSS comment. The draft's root element
    is an x-dc tag, so a Blade file containing one (even in a comment, even in
    this one) compiles to a component call for a component that does not exist
    and the page 500s. That is why the markup is read off disk and emitted raw,
    and why no line in this file spells that tag out.
--}}

@push('styles')
{{-- The draft covers the viewport by itself (`position:fixed;inset:0`), so the
     shell behind it has nothing to show and its scrollbar is only in the way. --}}
<style>
    /* The board covers the viewport by itself, so the page behind it has
       nothing to show and its scrollbar is only in the way. `z-index` clears
       the mobile shell's bottom tab bar (40) and its top bar. */
    html:has(#takeone-bracket-root) body { overflow: hidden; }
    #takeone-bracket-root { position: fixed; inset: 0; z-index: 70; }
    /* The design file draws its own 150px header hard against the top edge —
       nothing of ours may sit above it. */
    #takeone-bracket-root > * { margin: 0; }
</style>
@endpush

@section($contentSection ?? 'content')
@php
    /* ⚠️ EVERY BRACKET ASSET CARRIES ITS OWN MTIME.

       These four files live in public/ and are served by the web server, not
       by Vite — so nothing puts a hash in their names and a browser that has
       one keeps it. An edit to the bridge then reaches nobody: the page is
       new, the script is last week's, and the two disagree in ways that look
       like the feature was never built. Cost is one stat() per file. */
    $brk = function (string $file) {
        $path = public_path('bracket/'.$file);

        return asset('bracket/'.$file).'?v='.(is_file($path) ? filemtime($path) : 0);
    };

    /* ⚠️ Back goes where you CAME FROM: the console. Inside the sealed event
       app that is /e/{uuid}/admin/manage; on the platform it is the member
       console. Rendered whole rather than assembled, so the sealed app's
       rewriter can map it. */
    $drawBack = ($sealed ?? false)
        ? url('/e/'.$e['key'].'/admin/manage')
        : route('me.events.manage', $e['key']);

    /* WHICH division the board is showing. The draft draws one bracket — it
       has no division switcher and inventing one would mean editing its
       template — so the division travels in the URL and the bracket page's own
       list links in here per division. */
    $drawDivision = $division;

    /* ⚠️ Pre-assigned, never inlined into @json().
    
       Blade's @json() directive matches brackets itself and chokes on a
       nested call inside a ternary — the compiled view came out as a PHP
       syntax error (CLAUDE.md records the same trap for
       `@json($arr['key'] ?? [...])`). One variable each, then one plain
       @json() per line.
    
       The two WRITE addresses are built only for a viewer the server says may
       manage or arrange. They re-authorise on every call regardless; this
       keeps an address nobody may use out of a stranger's page source.
       `__ID__` is substituted by the bridge, and the template comes from a
       real route so a rename moves with it (Navigation Integrity). */
    $drawOutcomeUrl = $canManage
        ? str_replace('999999999', '__ID__', route('me.events.outcome', [$e['key'], 999999999]))
        : null;
    $drawArrangeUrl = ($canManage || $canArrange)
        ? route('me.events.bracket.arrange', $e['key'])
        : null;
    $drawDataUrl = route('me.events.bracket.data', $e['key']);

    /* The play-off switch is the ORGANISER's — an appointed official who may
       arrange a draw does not decide how many medals the championship awards.
       Built only for a viewer the server says may manage, like the other two
       write addresses above; the endpoint re-authorises regardless. */
    $drawThirdPlaceUrl = $canManage
        ? route('me.events.bracket.third-place', $e['key'])
        : null;
@endphp

<div id="takeone-bracket-root">
    {!! file_get_contents(base_path('app/Events/resources/views/personal/partials/bracket-board.html')) !!}
</div>

{{-- ===== WHICH DIVISION IS ON THE BOARD =====

     The draft draws ONE bracket and has no switcher of its own — giving it one
     would mean editing its template, which this page does not do. So the
     picker lives beside it, over the board rather than in it: the bridge says
     which divisions the payload holds (`bracket:divisions`) and
     `TakeOneBracket.show(id)` redraws from the payload already in hand, with
     no second request and no page load.

     THE SAME CONTROL AS THE DRAW STAGE'S, class for class. One picker, read
     the same way wherever a draw is shown — only the call behind a row differs
     (that board answers to BracketBoard, this one to TakeOneBracket).

     WHERE IT SITS: the top-right corner of the CANVAS — the panning area the
     draw is drawn in — and not the board's header, which is the draft's own
     150px band carrying the back arrow, the title, the padlock and Actions.
     The picker belongs to what it changes. That corner cannot be a constant:
     the board is laid out at 1920×1080 and then zoomed to whatever screen it
     is on, so the header's height on screen differs on every window. The
     canvas carries `data-bk-canvas` and is MEASURED instead, on resize and
     whenever the divisions change.

     ⚠️ It must hang off <body>. The board root does the same dance a few
     lines down and for the same reason — a `fixed` element resolves against
     the nearest TRANSFORMED ancestor, and the shell wrapper has one, so a
     picker left inside it is laid out in a box the size of the wrapper and
     never appears on screen at all. `lift()` moves both. --}}
<div x-data="{
        divisions: [], division: null, open: false, boardMenu: false,
        get selected() { return this.divisions.find(d => d.id === this.division) ?? null; },

        /* Gender is not a column on a division — it lives in the NAME, which
           AbstractCombatSport::genderWord() generates ('Women', else 'Men').
           Matched with a word boundary because 'men' is inside 'women'. This
           mirrors the draw stage's picker; if one changes, both do. */
        genderOf(name) { return /\bwomen\b/i.test(name || '') ? 'f' : 'm'; },
        genderIcon(name) { return this.genderOf(name) === 'f' ? 'bi-gender-female' : 'bi-gender-male'; },
        genderColor(name) { return this.genderOf(name) === 'f' ? 'text-pink-500' : 'text-blue-500'; },

        /* The board's own Actions menu has just opened or closed. It is drawn
           inside #takeone-bracket-root, a stacking context this picker sits
           OUTSIDE of (it has to, to escape the shell's transform), so the
           menu can never paint above it however high its z-index. The picker
           stands aside instead — and closes its own panel on the way, because
           two open menus over one board is nobody's idea of an answer. */
        onBoardMenu(d) {
            this.boardMenu = !! d.open;
            if (this.boardMenu) { this.open = false; }
        },

        onDivisions(d) {
            this.divisions = d.divisions || [];
            this.division = d.division;
            this.place();
        },

        /* The canvas's top-right corner, in screen coordinates, with the same
           gap down as in. A board that has not mounted yet leaves the picker
           where it is rather than throwing it into a corner of the window. */
        place() {
            this.$nextTick(() => {
                const box = document.querySelector('[data-bk-canvas]');
                if (! box) return;

                const r = box.getBoundingClientRect();
                if (! r.width || ! r.height) return;

                const GAP = 8;
                this.$el.style.top = Math.round(r.top + GAP) + 'px';
                this.$el.style.right = Math.round(window.innerWidth - r.right + GAP) + 'px';
            });
        },

        init() {
            if (window.__takeoneDivisions) this.onDivisions(window.__takeoneDivisions);

            /* The board re-fits on resize, so the canvas moves with the window.
               Kept on the component so a shell re-running this page's scripts
               replaces the listener instead of stacking another on top. */
            this._place = () => this.place();
            window.addEventListener('resize', this._place);
            this.place();
        },

        destroy() { window.removeEventListener('resize', this._place); },
     }"
     @bracket:divisions.window="onDivisions($event.detail)"
     @bracket:menu.window="onBoardMenu($event.detail)"
     @keydown.escape.window="open = false"
     @click.outside="open = false"
     x-show="divisions.length > 1 && ! boardMenu" x-cloak
     x-transition.opacity.duration.120ms
     id="draw-division-picker"
     class="fixed z-[80] transition-all">

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
         class="absolute end-0 mt-2 w-80 max-h-[60vh] overflow-y-auto rounded-xl border border-gray-200
                bg-white shadow-xl z-50 py-1">
        <template x-for="d in divisions" :key="d.id">
            <button type="button" role="option" :aria-selected="d.id === division"
                    @click="window.TakeOneBracket?.show(d.id); open = false"
                    :class="d.id === division ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                    class="w-full flex items-start gap-2.5 px-3 py-2 text-start text-[11px] font-bold transition-colors">
                <i class="bi shrink-0" :class="genderIcon(d.name) + ' ' + genderColor(d.name)"></i>
                {{-- A division's name is its whole identity — belt, age band,
                     weight, Gi or No-Gi. Cut it off and two rows read the
                     same, so it wraps instead. --}}
                <span class="flex-1 min-w-0 break-words leading-snug" x-text="d.name"></span>
                <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold shrink-0"
                      x-text="d.entrants"></span>
                <i class="bi bi-check-lg shrink-0" x-show="d.id === division"></i>
            </button>
        </template>
    </div>
</div>

{{-- ⚠️ ORDER MATTERS. dc-runtime checks for `window.React` and only fetches it
     from a CDN when it is absent — so loading the two UMD bundles we shipped
     first keeps this page entirely on our own origin (CLAUDE.md → supply
     chain: no third-party script at runtime). --}}
<script src="{{ $brk('react.production.min.js') }}"></script>
<script src="{{ $brk('react-dom.production.min.js') }}"></script>

<script>
    /* Everything the bridge needs, and nothing it does not. Each value is a
       plain variable — see the note beside them above. */
    window.TAKEONE_BRACKET = {
        event: @json($e['key']),
        division: @json($drawDivision),
        dataUrl: @json($drawDataUrl),
        outcomeUrl: @json($drawOutcomeUrl),
        arrangeUrl: @json($drawArrangeUrl),
        thirdPlaceUrl: @json($drawThirdPlaceUrl),
        backUrl: @json($drawBack),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        text: {
            failed: @json(__('events::events.bracket_move_failed')),
            readonly: @json(__('events::events.bracket_locked')),
            noDraw: @json(__('events::events.bracket_not_drawn')),
        },
    };
</script>

<script src="{{ $brk('takeone-bridge.js') }}"></script>
<script src="{{ $brk('dc-runtime.js') }}"></script>

<script>
/* dc-runtime boots itself on DOMContentLoaded. The mobile and admin shells
   swap page content in place and re-run inline scripts WITHOUT firing a real
   DOMContentLoaded of their own, so a board arrived at through one would never
   mount. It exposes `__dcBoot()` for exactly this; calling it twice is safe
   (it re-reads the x-dc block and re-renders). */
(function () {
    /* ⚠️ THE BOARD MUST HANG OFF <body>, NOT OFF THE SHELL.

       The design file's stage is `position:fixed;inset:0`, and a fixed
       element resolves against the nearest TRANSFORMED ancestor rather than
       the viewport. The mobile shell's #shell-content carries `.mobile-stagger`,
       whose entrance animation leaves a `transform` on every direct child — so
       the stage was being laid out inside that wrapper and came to rest below
       the shell's own chrome, with a band of the page showing above its header
       (reported 2026-09-20: "there must be no gap, it must be sticking the top
       most"). The same trap CLAUDE.md records for bottom sheets, and the same
       answer: get out of the transformed ancestor.

       Moved BEFORE the runtime boots, so React mounts into a node that is
       already in its final home and never re-parents a live tree. */
    var lift = function () {
        var root = document.getElementById('takeone-bracket-root');
        if (root && root.parentNode !== document.body) document.body.appendChild(root);

        /* The division picker is fixed over the board and falls into the very
           same trap — it was laid out inside the transformed wrapper and never
           appeared on screen at all. It travels with the board. Alpine holds
           its scope on the element, so moving the node keeps it working. */
        var pick = document.getElementById('draw-division-picker');
        if (pick && pick.parentNode !== document.body) document.body.appendChild(pick);

        return root;
    };

    var boot = function () {
        var root = lift();
        if (window.__dcBoot && root && root.querySelector('[data-screen-label]')) {
            window.__dcBoot();
        }
    };

    lift();

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else setTimeout(boot, 0);
})();
</script>

@include('events::partials.event-action-runner')

{{-- Anybody in this competition twice? A warning over the board while any
     likely pair is unsettled — this is where the draw is built. Organiser
     only; renders nothing when there are none (EventDuplicates). --}}
@if($canManage)
    <x-event-duplicate-check :event="$e['key']" :color="$e['color'] ?? '#7c3aed'" :title="$e['title']" variant="chip" />
@endif
@endsection
