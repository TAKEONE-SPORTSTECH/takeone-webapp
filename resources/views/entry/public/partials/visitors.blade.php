{{--
    "How many people have opened this page" — the organiser's own counter.

    ⚠️ TWO AUDIENCES, and the line between them is drawn in the CONTROLLER,
    never here:

      everybody     the single number. It is the page's own social proof — a
                    competition nobody has looked at reads as one nobody should
                    enter — and `$visitors` arrives holding `people` and nothing
                    else, so there is no participant tally, no returning count
                    and no excluded figure in the HTML to read out of the source.

      the organiser the same number, tappable, plus the breakdown and the list
                    of who they were. `$visitorsCanDrill` says which, and the
                    detail is fetched from an endpoint that 404s for anybody
                    else — so this is not a client-side hide either way.

    ⚠️ EVERY dimension is an inline `style`, like the rest of entry/public/*.
    The Tailwind bundle in public/build is COMPILED, so a class nobody has used
    before has no CSS and renders as nothing. See cover-actions, which learned
    that the hard way.

    The sheet is teleported to <body>: it is `position: fixed`, and this page's
    <main> is a transformed ancestor that would otherwise become its containing
    block and size the sheet to a wrapper instead of the screen.
--}}
@php
    $vColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
@endphp

@php
    /* The organiser's counter is a DOOR and says so; everybody else's is a
       fact. A chevron on a plate that does not open, or a cursor that suggests
       it might, is the one thing a public counter must not do. */
    $vDrill = (bool) ($visitorsCanDrill ?? false);
    $vTag = $vDrill ? 'button' : 'div';

    /* The number, as DIGITS, rendered server-side so the counter is right
       before a line of JavaScript runs.
       Padded to three, which is the aesthetic as much as the alignment: a
       mechanical counter has a fixed number of wheels, and "004" is the shape
       everybody recognises from the back of a turnstile. */
    $vCount = max(0, (int) ($visitors['people'] ?? 0));
    $vDigits = str_split(str_pad((string) $vCount, 3, '0', STR_PAD_LEFT));
@endphp

<div x-data="eventVisitors(@js($e['key']), @js($visitors), @js($vDrill))" style="text-align:center;">

    {{-- A MECHANICAL COUNTER: fixed wheels behind a slot, each one rolling up
         from zero to its digit when the page settles, the units wheel landing
         last. The hairline across the middle of every slot is the tell — it is
         what makes a row of numbers read as a machine that has been counting
         rather than a number that was typed.

         Drawn from the odometer pattern the web has settled on: one
         `overflow:hidden` slot per digit, a vertical strip of 0-9 inside it
         moved by `translateY`, and `font-variant-numeric: tabular-nums` so the
         wheels never change width as they turn. --}}
    <{{ $vTag }} @if ($vDrill) type="button" @click="open()" @endif class="vo-wrap"
            aria-label="{{ trans_choice('events.visitors_aria', $vCount, ['count' => $vCount]) }}"
            style="{{ $vDrill ? 'cursor:pointer;' : '' }}">

        <span class="vo" data-vo aria-hidden="true">
            @foreach ($vDigits as $i => $d)
                <span class="vo-d">
                    <span class="vo-s" data-vo-d="{{ $d }}" style="transition-delay:{{ $i * 90 }}ms;">
                        @for ($n = 0; $n <= 9; $n++)<span>{{ $n }}</span>@endfor
                    </span>
                </span>
            @endforeach
        </span>

        <span class="vo-label">
            {{-- The pulse says the number is still moving, which it is. It is
                 the only thing on this footer that animates, so it is small. --}}
            <span class="vo-dot" aria-hidden="true"></span>{{ __('events.visitors_label') }}
        </span>
    </{{ $vTag }}>

    @if ($vDrill)
    {{-- ⚠️ THE CARD IS PINNED, NOT FLEXED.
         Built to `partials/language-sheet`, which is this surface's own sheet
         and the pattern to match: a `fixed inset-0` wrapper, a scrim at
         `absolute inset-0`, and the card at `absolute inset-x-0 bottom-0`.

         This first used a flex wrapper with `align-items:flex-end`, and the
         sheet opened floating in the middle of the page (reported 2026-09-10).
         `x-show` toggles that same element's `style.display` — so the inline
         `display:flex` it needs is exactly the property Alpine is writing to,
         and the moment it reverted to a block the alignment it depended on
         meant nothing. An absolutely-positioned card cannot be moved by
         anything the wrapper's `display` does.

         `max-width:520px; margin:0 auto` is the surface's other rule: this app
         is a 520px column even on a monitor, and a sheet that ran the whole
         width of the glass would not belong to the page it rose from.

         Teleported to <body> because it is `position: fixed` and this page's
         <main> is a transformed ancestor, which would otherwise become its
         containing block. --}}
    <template x-teleport="body">
        <div x-show="showing" x-cloak
             @keydown.escape.window="showing = false"
             class="fixed inset-0 vo-sheet" style="z-index:1200;">

            <div x-show="showing"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="showing = false"
                 class="absolute inset-0"
                 style="background:rgba(7,11,20,.72); -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px);"></div>

            {{-- The same entrance the language sheet uses — `translate-y-full`,
                 which is in the compiled bundle BECAUSE that sheet uses it. A
                 utility nobody has used before has no CSS on this surface and
                 the sheet would simply appear. --}}
            <div x-show="showing"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 flex flex-col"
                 {{-- ⚠️ THE RADIUS AND `overflow:hidden` BELONG HERE, on the
                      card. The band inside it draws its own rounded top, but
                      the card's own background is a square rectangle painted
                      behind it — so the two top corners showed as plain squares
                      either side of the curve (reported 2026-09-10). Clipping
                      the card is what makes the corner actually round, whatever
                      any child paints. --}}
                 style="max-height:92vh; max-width:520px; margin:0 auto; background:#f6f8fb;
                        border-radius:24px 24px 0 0; overflow:hidden;">

                {{-- The gradient header band every sheet on the platform opens
                     with (CLAUDE.md Design Rule #8). ⚠️ `#hex + b0` — the alpha
                     suffix is hex-only. --}}
                <div style="flex:none; padding:12px 20px 18px; border-radius:24px 24px 0 0; color:#fff; position:relative; overflow:hidden; background:linear-gradient(150deg, {{ $vColor }}, {{ $vColor }}b0);">
                    <div style="position:absolute; right:-32px; top:-40px; width:144px; height:144px; border-radius:50%; background:rgba(255,255,255,.1);"></div>
                    <div style="margin:0 auto 12px; width:40px; height:4px; border-radius:9999px; background:rgba(255,255,255,.40);"></div>

                    <div style="position:relative; display:flex; align-items:flex-start; gap:12px;">
                        <span style="width:48px; height:48px; border-radius:16px; flex:none; display:grid; place-items:center; background:rgba(255,255,255,.2);">
                            <i class="bi bi-people-fill" style="font-size:20px;"></i>
                        </span>
                        <div style="min-width:0; flex:1;">
                            <h3 style="margin:0; font-size:18px; font-weight:900; line-height:1.15;"
                                x-text="picked ? title(picked) : @js(__('events.visitors_title'))"></h3>
                            <p style="margin:2px 0 0; font-size:12px; color:rgba(255,255,255,.85);"
                               x-text="picked ? roleLabel(picked) : @js(__('events.visitors_sub'))"></p>
                        </div>
                        {{-- Inside a visitor, the expected exit is back to the
                             list — not out of the sheet. --}}
                        <button type="button" @click="picked ? (picked = null) : (showing = false)"
                                :aria-label="picked ? @js(__('shared.back')) : @js(__('shared.close'))"
                                style="width:36px; height:36px; border-radius:999px; flex:none; display:grid; place-items:center; color:#fff; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); cursor:pointer;">
                            <i class="bi" :class="picked ? 'bi-chevron-left' : 'bi-x-lg'" style="font-size:13px;"></i>
                        </button>
                    </div>

                    {{-- The three kinds, as chips. Participants first: it is the
                         one an organiser is actually looking for. --}}
                    {{-- The three kinds, and each one FILTERS the list — the
                         numbers were already there and reading them without
                         being able to act on them is the frustrating half of a
                         summary. Tapping the active one clears it, so there is
                         no separate All chip competing for the row. --}}
                    <div x-show="! picked" class="vo-chips" style="position:relative; margin-top:14px;">
                        <template x-for="c in chips()" :key="c.key">
                            {{-- ⚠️ `c.role && filter === c.role`, not `filter === c.role`.
                                 The machine chip carries no role — it filters
                                 nothing — so its role is null, and with no
                                 filter set `null === null` is TRUE: it rendered
                                 solid white and announced itself as pressed the
                                 moment the sheet opened. --}}
                            <button type="button" class="vo-chip"
                                    :class="{ on: c.role && filter === c.role }"
                                    :disabled="! c.role || c.count === 0"
                                    @click="toggle(c.role)"
                                    :aria-pressed="c.role ? filter === c.role : null">
                                {{-- The count, then the group it belongs to. Two
                                     spans rather than one sentence, which is
                                     also what keeps these out of the plural
                                     trap: "1 · Visitors" is a group with a
                                     number against it, where "1 visitors" is
                                     just wrong. --}}
                                <i class="bi" :class="c.icon"></i><span
                                    style="font-weight:900;" x-text="c.count"></span><span
                                    style="opacity:.75;" x-text="c.word"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- The body scrolls; the band never does. --}}
                <div style="flex:1; overflow-y:auto; padding:14px 16px calc(1rem + env(safe-area-inset-bottom));">

                    <p x-show="loading" style="margin:0; padding:34px 0; text-align:center; font-size:13px; color:#6b7385;">…</p>

                    {{-- Two empty states, because they are two different
                         situations: nobody has come, and nobody of THIS kind
                         has. The second offers the way out of it. --}}
                    <template x-if="! loading && ! picked && shown.length === 0">
                        <div style="border:2px dashed #dfe5ee; border-radius:18px; padding:30px 18px; text-align:center;">
                            <i class="bi" :class="filter ? 'bi-funnel' : 'bi-people'" style="font-size:22px; color:#aab2c0;"></i>
                            <p style="margin:10px 0 0; font-size:13.5px; font-weight:800; color:#0b1220;"
                               x-text="filter ? @js(__('events.visitors_none_of_kind')) : @js(__('events.visitors_none'))"></p>
                            <p x-show="! filter" style="margin:4px 0 0; font-size:11.5px; color:#6b7385; line-height:1.45;">{{ __('events.visitors_none_note') }}</p>
                            <button x-show="filter" type="button" @click="filter = null"
                                    style="margin:10px auto 0; display:block; padding:7px 14px; border:0; border-radius:10px; font-size:11.5px; font-weight:800; cursor:pointer; color:{{ $vColor }}; background:{{ $vColor }}14;">
                                {{ __('events.visitors_show_all') }}
                            </button>
                        </div>
                    </template>

                    {{-- ── THE LIST ─────────────────────────────────────────
                         Each row is a DOOR now: an organiser asked to be able
                         to open a visitor and see their visits, which is the
                         difference between a count and a log.

                         And each row is DISTINCT. Two anonymous rows that both
                         read "Visitor" are two rows nobody can tell apart,
                         refer to, or follow down a list — so every visitor
                         carries a short stable handle and a colour derived from
                         the same digest. The same person is the same badge
                         every time this is opened. --}}
                    <div x-show="! picked" class="vo-col">
                        <template x-for="p in shown" :key="p.id">
                            <button type="button" @click="picked = p"
                                    style="width:100%; text-align:start; display:flex; align-items:center; gap:11px; background:#fff; border:1px solid #e8ecf3; border-inline-start-width:3px; border-radius:15px; padding:10px 12px; cursor:pointer;"
                                    {{-- ⚠️ AN OBJECT, never a string. Alpine's
                                         `:style` with a string REPLACES the
                                         element's style attribute — it wiped the
                                         flex, the padding and the background off
                                         every card, so the list rendered as bare
                                         text with a coloured edge and read as
                                         missing entirely (reported 2026-09-10).
                                         An object merges property by property
                                         and leaves the static style alone. --}}
                                    :style="{ borderInlineStartColor: accent(p) }">

                                {{-- Their face where they publish one; otherwise
                                     the handle, in the handle's own colour — a
                                     mark, not a stand-in for a name nobody
                                     recorded. --}}
                                <template x-if="p.photo">
                                    <span style="width:30px; height:40px; border-radius:8px; flex:none; overflow:hidden; background:#eef1f6;">
                                        <img :src="p.photo" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    </span>
                                </template>
                                <template x-if="! p.photo">
                                    <span style="width:30px; height:40px; border-radius:8px; flex:none; display:grid; place-items:center; font-size:10px; font-weight:900; letter-spacing:.02em;"
                                          :style="{ background: tint(p), color: accent(p) }"
                                          x-text="p.tag"></span>
                                </template>

                                <span style="min-width:0; flex:1;">
                                    <span style="display:block; font-size:13.5px; font-weight:800; color:#0b1220; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                          x-text="title(p)"></span>
                                    <span style="display:block; font-size:11px; color:#6b7385; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                          x-text="line(p)"></span>
                                </span>

                                <span style="flex:none; display:flex; flex-direction:column; align-items:flex-end; gap:3px;">
                                    <span style="display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:999px; font-size:9.5px; font-weight:900; text-transform:uppercase; letter-spacing:.05em;"
                                          :style="badge(p.role)" x-text="roleLabel(p)"></span>
                                    <span x-show="p.visits > 1" style="font-size:10px; color:#8b93a4;"
                                          x-text="@js(__('events.visitors_times', ['count' => ':n'])).replace(':n', p.visits)"></span>
                                </span>

                                <i class="bi bi-chevron-right rtl:rotate-180" style="font-size:11px; color:#c3c9d4; flex:none;"></i>
                            </button>
                        </template>
                    </div>

                    {{-- ── ONE VISITOR ──────────────────────────────────────
                         Their visits, newest first. Capped upstream
                         (EventVisitors::REMEMBER_VISITS) — an organiser wants to
                         know somebody came back three times, and nobody needs a
                         complete attendance record of a stranger reading a
                         poster, so the older ones are gone and the total says so
                         instead of pretending. --}}
                    <div x-show="picked" x-cloak class="vo-col">
                        <template x-if="picked">
                            <div style="background:#fff; border:1px solid #e8ecf3; border-radius:16px; padding:14px;">

                                <div style="display:flex; align-items:center; gap:12px;">
                                    <template x-if="picked.photo">
                                        <span style="width:39px; height:52px; border-radius:9px; flex:none; overflow:hidden; background:#eef1f6;">
                                            <img :src="picked.photo" alt="" style="width:100%; height:100%; object-fit:cover;">
                                        </span>
                                    </template>
                                    <template x-if="! picked.photo">
                                        <span style="width:39px; height:52px; border-radius:9px; flex:none; display:grid; place-items:center; font-size:12px; font-weight:900;"
                                              :style="{ background: tint(picked), color: accent(picked) }"
                                              x-text="picked.tag"></span>
                                    </template>
                                    <div style="min-width:0; flex:1;">
                                        <p style="margin:0; font-size:15px; font-weight:900; color:#0b1220;" x-text="title(picked)"></p>
                                        <p style="margin:2px 0 0; font-size:11px; color:#6b7385;" x-text="roleLabel(picked)"></p>
                                    </div>
                                    {{-- A member has a page; an anonymous
                                         visitor is not a person we can look up,
                                         so there is nothing to link to. --}}
                                    <template x-if="picked.uuid">
                                        <a :href="'/people/' + picked.uuid" target="_blank" rel="noopener"
                                           style="flex:none; padding:7px 11px; border-radius:10px; font-size:11px; font-weight:800; text-decoration:none; color:{{ $vColor }}; background:{{ $vColor }}14;">
                                            {{ __('events.visitors_open_profile') }}
                                        </a>
                                    </template>
                                </div>

                                {{-- The three numbers, then the trail. --}}
                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(70px, 1fr)); gap:8px; margin-top:14px;">
                                    <template x-for="f in facts(picked)" :key="f.k">
                                        <div style="background:#f6f8fb; border-radius:11px; padding:9px 8px; text-align:center;">
                                            <p style="margin:0; font-size:14px; font-weight:900; color:#0b1220;" x-text="f.v"></p>
                                            <p style="margin:1px 0 0; font-size:9px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#8b93a4;" x-text="f.k"></p>
                                        </div>
                                    </template>
                                </div>

                                <p style="margin:16px 0 8px; font-size:9.5px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:#8b93a4;">{{ __('events.visitors_each_visit') }}</p>

                                {{-- A dotted spine down the visits, so a return
                                     reads as a second occasion rather than
                                     another row of text. --}}
                                <div style="display:flex; flex-direction:column;">
                                    <template x-for="(t, i) in (picked.trail || [])" :key="i">
                                        <div style="display:flex; gap:11px;">
                                            <span style="flex:none; display:flex; flex-direction:column; align-items:center; width:9px;">
                                                <span style="width:9px; height:9px; border-radius:50%; margin-top:4px;"
                                                      :style="{ background: i === 0 ? accent(picked) : '#d7dce6' }"></span>
                                                <span x-show="i < (picked.trail || []).length - 1"
                                                      style="flex:1; width:1px; background:#e3e8f0; margin:2px 0;"></span>
                                            </span>
                                            <div style="padding-bottom:12px; min-width:0;">
                                                <p style="margin:0; font-size:12.5px; font-weight:700; color:#0b1220;" x-text="when(t.at)"></p>
                                                <p style="margin:1px 0 0; font-size:11px; color:#8b93a4;" x-text="trailLine(t)"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <p x-show="(picked.trail || []).length === 0"
                                   style="margin:0; font-size:11.5px; color:#8b93a4;">{{ __('events.visitors_no_trail') }}</p>
                                <p x-show="picked.visits > (picked.trail || []).length"
                                   style="margin:2px 0 0; font-size:10.5px; color:#aab2c0;"
                                   x-text="@js(__('events.visitors_trail_capped', ['count' => ':n'])).replace(':n', picked.visits - (picked.trail || []).length)"></p>
                            </div>
                        </template>
                    </div>

                    {{-- ⚠️ NOTHING BELOW THE LIST.
                         The excluded-machines line and the privacy note used to
                         sit here and were removed at the user's request
                         (2026-09-10): a panel read to answer "who came" ends at
                         the last person, and two paragraphs of small print under
                         a scroll are read by nobody and shorten the list for
                         everybody. What they said is not lost — the machine
                         count is a chip in the header, and how the counting
                         works is stated on the closed row's own sub-line. --}}
                </div>
            </div>
        </div>
    </template>
    @endif
</div>

@once
{{-- ⚠️ An inline <style>, NOT `@push('styles')`.
     This partial renders from `entry/partials/footer`, which the layout
     includes at line 161 — long after it printed `@stack('styles')` in the
     head at line 133. A push that arrives after its stack has been rendered is
     silently dropped, and it was: the wheels turned up as bare numerals in a
     row with no drums, no seams and no colour. So the partial carries its own
     CSS, which also makes it standalone: markup, behaviour and appearance in
     one file that can be included from anywhere on this surface.
     `@once` keeps it to a single copy however many times that happens. --}}
<style>
/*  The mechanical counter.
    Real rules, not Tailwind utilities: the bundle in public/build is COMPILED,
    so a class nobody has used before has no CSS at all and renders as nothing.
    Everything here takes its colour from the ground tokens the page sets
    (`--ev`, `--on-pg`, `--on-pg-line`), so the same counter reads correctly on
    the black cover and on the white sections without knowing which it is on. */
.vo-wrap{display:inline-flex;flex-direction:column;align-items:center;gap:8px;
         background:none;border:0;padding:0;font:inherit;color:inherit;}

.vo{display:inline-flex;align-items:center;gap:3px;}

/*  One wheel behind one slot. The inset shadow is the curve of the drum and
    the ::after hairline is the seam the digits turn past — together they are
    what makes this read as a machine rather than as text. */
.vo-d{position:relative;width:23px;height:34px;overflow:hidden;border-radius:7px;
      background:var(--pg,#fff);border:1px solid var(--on-pg-line);
      box-shadow:inset 0 7px 9px -9px rgba(30,44,79,.5),
                 inset 0 -7px 9px -9px rgba(30,44,79,.5),
                 0 1px 2px rgba(30,44,79,.06);}
.vo-d::after{content:'';position:absolute;left:0;right:0;top:50%;height:1px;
      background:var(--on-pg-line);opacity:.55;pointer-events:none;}

.vo-s{position:absolute;left:0;right:0;top:0;display:flex;flex-direction:column;
      transform:translateY(0);
      /* Long, and almost all of it in the tail: a drum that decelerates into
         its digit is the whole pleasure of the thing. */
      transition:transform 1.3s cubic-bezier(.16,1,.3,1);}
.vo-s>span{height:34px;line-height:34px;text-align:center;
      font-size:20px;font-weight:800;letter-spacing:-.01em;
      font-variant-numeric:tabular-nums;color:var(--ev);}

.vo-label{display:inline-flex;align-items:center;gap:6px;
      font-size:9.5px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
      color:var(--on-pg);}

/*  The one thing in this footer that moves, so it is 5px across. */
.vo-dot{width:5px;height:5px;border-radius:50%;background:var(--ev);flex:none;
      animation:voPulse 2.4s ease-in-out infinite;}
@keyframes voPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.8)}}

.vo-wrap[type="button"]:active .vo{transform:scale(.97);}
.vo-wrap .vo{transition:transform .12s;}

/*  ⚠️ ANYTHING `x-show` CONTROLS TAKES ITS `display` FROM A CLASS, NEVER FROM
    AN INLINE STYLE.

    Alpine's x-show hides with `el.style.display = 'none'` and shows with
    `el.style.removeProperty('display')` — it does not restore what was there,
    it DELETES the property. So an inline `display:flex` on an x-show element is
    gone the first time it is shown, the element falls back to `display:block`,
    and every `gap` and `align-items` on it silently stops meaning anything.

    That one behaviour was three separate bug reports: the sheet opening in the
    middle of the page, no gaps between the visitor cards, and no gaps between
    the header badges. A class survives it, because removing the inline property
    is exactly what lets the class apply. */
.vo-col{display:flex;flex-direction:column;gap:12px;}
/*  ONE ROW, NEVER WRAPPED.
    Four chips wrapped to a second line pushed the list down and made the band
    two different heights depending on whether an event had had any automated
    traffic — so the row is `nowrap`.

    `flex:0 0 auto` on the chips rather than letting them shrink: a chip that
    shrinks ellipsises its own label, and "2 · Memb…" is worse than a row that
    can be pushed sideways. In English the four fit inside the 520px card with
    room to spare; a longer language or a four-digit count stays on one line and
    scrolls, with the bar hidden because this is a strip you push with a thumb,
    not a scroller anybody should see. */
.vo-chips{display:flex;flex-wrap:nowrap;gap:8px;overflow-x:auto;
     -webkit-overflow-scrolling:touch;scrollbar-width:none;}
.vo-chips::-webkit-scrollbar{display:none;}

/*  A filter chip in the sheet's header. Off is the translucent plate every
    other chip on this surface uses; on is solid, so which one is active is
    readable at a glance rather than by comparing two alphas. */
.vo-chip{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;padding:6px 10px;border-radius:9999px;
     white-space:nowrap;
     background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.001);
     font-size:11px;font-weight:800;color:#fff;cursor:pointer;
     transition:background-color .15s, color .15s, border-color .15s;}
.vo-chip:hover{background:rgba(255,255,255,.28);}
.vo-chip.on{background:#fff;color:#0b1220;border-color:#fff;}
.vo-chip[disabled]{opacity:.4;cursor:default;}

/*  ⚠️ THE MAP EMBED PAINTS OVER THE SHEET.
    The venue section carries an <iframe> (OpenStreetMap). An iframe is a
    replaced element that browsers composite in a layer of their own, and it
    came up THROUGH this sheet while the page was scrolled behind it — z-index
    alone does not reliably settle a fight with an iframe's own layer, which is
    why the sheet is at 1200 AND the embed is stood down while the sheet is
    open.

    `visibility` rather than `display`, so the iframe is not reloaded when the
    sheet closes — a map that re-fetches its tiles every time somebody checks
    the visitor list is a bill and a flicker. The map is a decorative preview
    with `pointer-events:none`; nothing is lost by it being invisible for as
    long as a panel is over the top of it. */
body:has(.vo-sheet:not([style*="display: none"])) iframe{visibility:hidden;}

@media (prefers-reduced-motion: reduce){
    .vo-s{transition:none !important;}
    .vo-dot{animation:none;}
}
</style>
@endonce

@once
@push('scripts')
<script>
    /* Registered through Alpine.data(), NOT as a bare function: this partial is
       teleported, and a script inside a teleported <template> is inert. Both
       registration paths are covered because the page may be swapped in after
       Alpine has already started. See reference_alpine_state_must_be_registered. */
    (function () {
        const define = (Alpine) => Alpine.data('eventVisitors', (uuid, summary, canDrill) => ({
            /* The COUNT arrives with the page — it is one query and the
               organiser sees it without opening anything. The list is fetched
               on open, because a hundred rows of people is not worth putting on
               a poster that mostly nobody expands. */
            s: summary || { people: 0, participants: 0, members: 0, anonymous: 0, excluded: 0, returning: 0, views: 0 },
            showing: false,
            loading: false,
            people: [],
            /* The visitor being read, or null for the list. One level down, like
               every other hub-and-spoke sheet in the product. */
            picked: null,
            /* Which kind of visitor the list is narrowed to, or null for all
               of them. A header chip sets it; tapping the active chip clears
               it, so there is no All chip competing for the row. */
            filter: null,
            _loaded: false,

            canDrill: !! canDrill,

            /* The organiser's sub-line breaks the number down; everybody else's
               says what the number MEANS, which is the part that is easy to get
               wrong — a visitor count that quietly included link previews and
               crawlers would be a boast rather than a fact. */
            sub() {
                if (! this.canDrill) return @js(__('events.visitors_public_note'));

                const bits = [];
                if (this.s.participants) bits.push(@js(__('events.visitors_n_participants', ['count' => ':n'])).replace(':n', this.s.participants));
                if (this.s.returning) bits.push(@js(__('events.visitors_n_returning', ['count' => ':n'])).replace(':n', this.s.returning));
                if (this.s.excluded) bits.push(@js(__('events.visitors_n_excluded', ['count' => ':n'])).replace(':n', this.s.excluded));

                return bits.length ? bits.join(' · ') : @js(__('events.visitors_tap_for_detail'));
            },

            /* The three kinds. `role` is what each one filters BY and matches
               the role the server sends on every person, so the chip and the
               list can never disagree about what a word means. The machine
               count joins them as a fourth, unclickable — it is the number that
               was left OUT, and it is here because the note that used to say so
               under the list was removed. */
            chips() {
                return [
                    { key: 'p', role: 'participant', count: this.s.participants, icon: 'bi-trophy-fill',
                      word: @js(__('events.visitors_group_entered')) },
                    { key: 'm', role: 'member', count: this.s.members, icon: 'bi-person-badge',
                      word: @js(__('events.visitors_group_members')) },
                    { key: 'a', role: 'visitor', count: this.s.anonymous, icon: 'bi-person',
                      word: @js(__('events.visitors_group_visitors')) },
                ].concat(this.s.excluded ? [
                    // Not a filter: the number that was left OUT. It is here
                    // because the note that used to say so under the list was
                    // removed, and it is the one chip that does nothing.
                    { key: 'x', role: null, count: this.s.excluded, icon: 'bi-robot',
                      word: @js(__('events.visitors_group_automated')) },
                ] : []);
            },

            toggle(role) {
                if (! role) return;   // the machine chip is a fact, not a door

                this.filter = this.filter === role ? null : role;
            },

            /* What the list actually draws. */
            get shown() {
                return this.filter ? this.people.filter(p => p.role === this.filter) : this.people;
            },

            roleLabel(p) {
                if (p.role === 'participant') return p.entry_role && p.entry_role !== 'participant'
                    ? p.entry_role
                    : @js(__('events.visitors_role_participant'));
                if (p.role === 'member') return @js(__('events.visitors_role_member'));

                return @js(__('events.visitors_role_visitor'));
            },

            /* An OBJECT, for the reason spelled out on the row above: a
               string handed to `:style` replaces the element's own style, and
               this chip's size and radius live there. */
            badge(role) {
                if (role === 'participant') return { background: '#e7f7ec', color: '#137a3a' };
                if (role === 'member') return { background: '#eef2ff', color: '#3a48a8' };

                return { background: '#f1f3f7', color: '#6b7385' };
            },

            /* One line of the honest facts about a visitor we have no name
               for: when they last came, on what kind of screen, in what
               language, from where. Devices and languages are SETS now — one
               person folded across every browser they signed in from — so a
               member who used a phone and a laptop reads as "mobile, desktop"
               rather than as two people. */
            line(p) {
                const bits = [];
                if (p.last_seen) bits.push(new Date(p.last_seen).toLocaleString(undefined,
                    { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }));
                if (p.devices && p.devices.length) bits.push(p.devices.join(', '));
                if (p.locales && p.locales.length) bits.push(p.locales.join('/').toUpperCase());
                if (p.from) bits.push(p.from);

                return bits.join(' · ');
            },

            open() {
                // Guarded as well as un-rendered: the door does not exist for a
                // public viewer, and if some future edit puts it back, the
                // endpoint behind it 404s for them anyway.
                if (! this.canDrill) return;

                this.showing = true;
                this.picked = null;
                this.filter = null;
                if (! this._loaded) this.load();
            },

            /* Who this row IS. A name where there is one; otherwise the handle,
               which is a mark rather than a stand-in for a name nobody
               recorded — "Visitor 4C1", not a second row reading "Visitor". */
            title(p) {
                if (! p) return '';

                return p.name || (@js(__('events.visitors_anonymous')) + ' ' + p.tag);
            },

            /* The colour is derived from the visitor's own digest upstream, so a
               person keeps their badge between two openings of this panel. An
               entrant overrides it: "which of my competitors have looked at
               this" is the question being asked, and it must not depend on
               noticing a hue. */
            accent(p) {
                if (! p) return '#8b93a4';
                if (p.role === 'participant') return '#137a3a';

                return 'hsl(' + (p.hue ?? 210) + ' 62% 42%)';
            },

            tint(p) {
                if (! p) return '#eef1f6';
                if (p.role === 'participant') return '#e7f7ec';

                return 'hsl(' + (p.hue ?? 210) + ' 62% 95%)';
            },

            facts(p) {
                if (! p) return [];

                const out = [
                    { k: @js(__('events.visitors_fact_visits')), v: p.visits },
                    { k: @js(__('events.visitors_fact_first')), v: this.day(p.first_seen) },
                    { k: @js(__('events.visitors_fact_last')), v: this.day(p.last_seen) },
                ];

                /* Only when there IS more than one — "1 device" is noise, and
                   the whole point of folding was that a person is a person
                   whatever they opened it on. */
                if ((p.browsers || 1) > 1) {
                    /* BROWSERS, not devices — which is the accurate word and
                       the one the number actually counts. Two browsers on one
                       laptop is two; the KINDS of screen they used are on the
                       row's own line, where "mobile, desktop" belongs. */
                    out.push({ k: @js(__('events.visitors_fact_browsers')), v: p.browsers });
                }

                return out;
            },

            day(iso) {
                if (! iso) return '—';

                return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
            },

            when(iso) {
                if (! iso) return '—';

                return new Date(iso).toLocaleString(undefined,
                    { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
            },

            trailLine(t) {
                return [t.device, t.locale ? t.locale.toUpperCase() : null, t.from]
                    .filter(Boolean).join(' · ');
            },

            async load() {
                this.loading = true;
                try {
                    const res = await fetch(@js(route('events.public.visitors', ['event' => $e['key']])), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (! res.ok) throw new Error();
                    const d = await res.json();
                    this.people = d.people || [];
                    if (d.summary) this.s = d.summary;
                    this._loaded = true;
                } catch (e) {
                    this.people = [];
                } finally { this.loading = false; }
            },
        }));

        window.Alpine ? define(window.Alpine) : define ? document.addEventListener('alpine:init', () => define(window.Alpine)) : null;
    })();

    /*  Roll the wheels.
        Every strip is rendered showing ZERO and is turned to its real digit one
        frame later, so the counter always arrives by counting up — and a
        browser with JavaScript off still shows a plausible 000 rather than a
        broken row. Two frames, because a transform set in the same frame the
        element was painted in is not a transition, it is a jump. */
    (function () {
        function roll(root) {
            (root || document).querySelectorAll('.vo-s[data-vo-d]').forEach(function (strip) {
                var d = parseInt(strip.getAttribute('data-vo-d'), 10);
                if (isNaN(d)) return;
                // The slot's own height, so the CSS above stays the one place
                // the wheel is sized.
                var h = strip.firstElementChild ? strip.firstElementChild.offsetHeight : 34;
                strip.style.transform = 'translateY(-' + (d * h) + 'px)';
            });
        }

        function start() {
            requestAnimationFrame(function () { requestAnimationFrame(function () { roll(document); }); });
        }

        document.addEventListener('DOMContentLoaded', start);
        if (document.readyState !== 'loading') start();
        // The poster's footer is inside the app shell, which the cover hides
        // until a language is picked — a wheel turned while display:none has
        // no height to turn by, so it is rolled again when the page is shown.
        window.addEventListener('cover-dismiss', start);
    })();
</script>
@endpush
@endonce
