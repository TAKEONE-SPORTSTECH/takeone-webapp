@props([
    /* The arena payload from App\Media\BoutArena. */
    'arena' => [],
    /* Where the card goes when clicked — the review page. */
    'href' => null,
    /* Poster + a light source for the hover preview (from BoutFilm). */
    'poster' => null,
    'preview' => null,
    'duration' => 0,
    'angles' => 1,
    /* Open in place inside the mobile shell. */
    'shellLink' => false,
])

@php
    $red = $arena['red'] ?? [];
    $blue = $arena['blue'] ?? [];

    /*
     * An Open Mat's event, stage and division are often the same words, and a
     * card that reads "Open Mat / Open mat / Open Mat" is the data model talking
     * to itself. Say each thing once, in the order the eye reads them.
     */
    $said = [];
    $once = function (?string $v) use (&$said) {
        $key = mb_strtolower(trim((string) $v));
        if ($key === '' || in_array($key, $said, true)) {
            return null;
        }
        $said[] = $key;

        return $v;
    };

    $topEvent  = $once($arena['event'] ?? null);
    $topStage  = $once($arena['stage'] ?? null);
    $topWeight = $once($arena['weight'] ?? null);
@endphp

{{--
    A bout, as a broadcast card.

    Three layers stacked on one thumbnail, in this order:

      1. the poster frame,
      2. the VS ARENA — a fixed 1920×1080 canvas, transform-scaled to whatever
         size the tile resolves to, so every element can be positioned in
         absolute pixels and the whole thing shrinks as one piece,
      3. the hover PREVIEW video, with a live SCOREBAR whose clock and score are
         driven off the preview's own currentTime.

    On hover the arena explodes outward — the inverse of its entrance — clearing
    the stage for the video. On a touch device there is no hover, so the intro
    and the explosion are both skipped and the composed frame is shown at rest;
    a phone user is scrolling a list, not waiting for a title sequence.

    Self-contained: its own markup, styles, scaling and playback. It needs
    nothing from the page but the payload.
--}}
<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @if ($shellLink) data-shell-link data-route="me.events" @endif @endif
    class="tk-bout-card group/card block no-underline rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm hover:shadow-xl transition-shadow"
    @if ($preview) data-preview="{{ $preview }}" @endif
    {{ $attributes }}>

    <div class="tk-bout-thumb relative aspect-video bg-[#050507] overflow-hidden"
         x-data="takeoneBoutCard(@js($arena['scoring'] ?? null), @js($preview))"
         x-init="mount()"
         @mouseenter="enter()" @mouseleave="leave()">

        @if ($poster)
            <img src="{{ $poster }}" alt="" loading="lazy" decoding="async"
                 class="absolute inset-0 w-full h-full object-cover">
        @endif

        {{-- ── The arena ─────────────────────────────────────────────── --}}
        <div class="vsa" x-ref="arena" :class="{ 'vsa-run': intro, 'vsa-out': hovering }">
            <div class="vsa-stage" x-ref="stage">

                <div class="vsa-panel vsa-panel-red">
                    @if (!empty($red['photo']))
                        <div class="vsa-photo" style="background-image:url('{{ $red['photo'] }}')"></div>
                    @endif
                    <div class="vsa-scrim vsa-scrim-red"></div>
                    <div class="vsa-fade"></div>
                </div>

                <div class="vsa-panel vsa-panel-blue">
                    @if (!empty($blue['photo']))
                        <div class="vsa-photo vsa-photo-rev" style="background-image:url('{{ $blue['photo'] }}')"></div>
                    @endif
                    <div class="vsa-scrim vsa-scrim-blue"></div>
                    <div class="vsa-fade"></div>
                </div>

                <div class="vsa-divider"></div>

                {{-- Top: the competition, the stage, the weight --}}
                <div class="vsa-top">
                    @if ($topEvent)
                        <div class="vsa-event">{{ $topEvent }}</div>
                    @endif
                    @if ($topStage)
                        <div class="vsa-stage-row">
                            <span class="vsa-rule vsa-rule-l"></span>
                            <span class="vsa-stage-txt">{{ $topStage }}</span>
                            <span class="vsa-rule vsa-rule-r"></span>
                        </div>
                    @endif
                    @if ($topWeight)
                        <div class="vsa-weight">{{ $topWeight }}</div>
                    @endif
                </div>

                {{-- The two identities --}}
                @foreach (['red' => $red, 'blue' => $blue] as $colour => $c)
                    <div class="vsa-info vsa-info-{{ $colour }}">
                        <div class="vsa-tag vsa-tag-{{ $colour }}">{{ $c['tag'] ?? strtoupper($colour) }}</div>

                        @if (!empty($c['country']))
                            <div class="vsa-flag-row {{ $colour === 'blue' ? 'vsa-rev' : '' }}">
                                <span class="vsa-flag fi fi-{{ $c['country'] }}"></span>
                            </div>
                        @endif

                        <div class="vsa-name">{{ $c['name'] ?: '—' }}</div>

                        @if (!empty($c['club']))
                            <div class="vsa-club-row {{ $colour === 'blue' ? 'vsa-rev' : '' }}">
                                @if (!empty($c['club_logo']))
                                    <span class="vsa-logo" style="background-image:url('{{ $c['club_logo'] }}')"></span>
                                @endif
                                <span class="vsa-club">{{ $c['club'] }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="vsa-center">
                    <div class="vsa-word">VS<span class="vsa-shine-wrap"><span class="vsa-shine"></span></span></div>
                </div>

                {{-- Bottom: mat, bout number, referee. Absent facts are absent. --}}
                <div class="vsa-bottom">
                    @php
                        $chips = array_filter([
                            ['lbl' => __('events.bout_card_match'), 'val' => $arena['match_no'] ?? null],
                            ['lbl' => __('events.bout_card_court'), 'val' => $arena['court'] ?? null],
                            ['lbl' => __('events.bout_card_referee'), 'val' => $arena['referee'] ?? null],
                        ], fn ($c) => filled($c['val']));
                    @endphp
                    @foreach ($chips as $i => $chip)
                        @if ($i > 0)<span class="vsa-diamond"></span>@endif
                        <span class="vsa-chip">
                            <span class="vsa-chip-lbl">{{ $chip['lbl'] }}</span>
                            <span class="vsa-chip-val">{{ $chip['val'] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Hover preview ─────────────────────────────────────────── --}}
        @if ($preview)
            <video x-ref="video" muted playsinline preload="none"
                   class="tk-bout-video absolute inset-0 w-full h-full object-cover"
                   :class="playing ? 'opacity-100' : 'opacity-0'"></video>
        @endif

        {{-- ── Live scorebar, over the preview ───────────────────────── --}}
        @if (!empty($arena['scoring']))
            <div class="sba" x-ref="sba" :class="playing ? 'sba-on' : ''">
                <div class="sba-scale" x-ref="sbaScale">
                    <div class="sba-bar">
                        <div class="sba-side sba-side-red">
                            <div class="sba-id">
                                @if (!empty($red['country']))<span class="sba-flag fi fi-{{ $red['country'] }}"></span>@endif
                                <span class="sba-name">{{ $red['name'] ?: '—' }}</span>
                            </div>
                            <div class="sba-tag">{{ $red['tag'] ?? 'RED' }}</div>
                            <div class="sba-score" x-text="red">0</div>
                        </div>

                        <div class="sba-plate">
                            <div class="sba-round" x-text="roundName">&nbsp;</div>
                            <div class="sba-clock" x-text="clock">0:00</div>
                        </div>

                        <div class="sba-side sba-side-blue">
                            <div class="sba-score" x-text="blue">0</div>
                            <div class="sba-tag">{{ $blue['tag'] ?? 'BLUE' }}</div>
                            <div class="sba-id sba-id-r">
                                <span class="sba-name">{{ $blue['name'] ?: '—' }}</span>
                                @if (!empty($blue['country']))<span class="sba-flag fi fi-{{ $blue['country'] }}"></span>@endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- The ticker: the last few scoring moments, newest first. --}}
                <div class="sba-feed-scale">
                    <div class="sba-feed">
                        <div class="sba-feed-hdr"><span class="sba-dot"></span>{{ __('events.bout_card_live_scoring') }}</div>
                        <template x-for="f in feed" :key="f.t + f.side">
                            <div class="sba-feed-row" :style="`border-inline-end-color:${f.side === 'red' ? '#ff6a5e' : '#6aa6ff'}`">
                                <span class="sba-feed-ts" x-text="mmss(f.t)"></span>
                                <span class="sba-feed-lbl" x-text="f.label"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- Resting badges: duration, angle count. Hidden while previewing. --}}
        <div class="absolute bottom-2 inset-x-2 flex items-end justify-between pointer-events-none transition-opacity"
             :class="playing ? 'opacity-0' : 'opacity-100'">
            @if ($duration > 0)
                <span class="px-1.5 py-0.5 rounded-md bg-black/70 text-white text-[10px] font-black tabular-nums">
                    {{ sprintf('%d:%02d', intdiv($duration, 60), $duration % 60) }}
                </span>
            @else
                <span></span>
            @endif
            @if ($angles > 1)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-black/70 text-white text-[10px] font-bold">
                    <i class="bi bi-camera-reels-fill"></i>{{ $angles }}
                </span>
            @endif
        </div>
    </div>

    {{-- The line under the picture, in the platform's own voice. --}}
    <div class="p-3">
        <p class="text-[13px] font-bold text-foreground leading-tight truncate">
            {{ $red['name'] ?: '—' }}
            <span class="text-muted-foreground/60 font-semibold">vs</span>
            {{ $blue['name'] ?: '—' }}
        </p>
        <p class="text-[11px] text-muted-foreground truncate mt-1">
            {{ collect([$arena['weight'] ?? null, $arena['stage'] ?? null, $arena['event'] ?? null])
                ->filter()
                ->unique(fn ($v) => mb_strtolower(trim($v)))
                ->implode(' · ') }}
        </p>
    </div>
</{{ $href ? 'a' : 'div' }}>

@once
@push('styles')
{{-- The two display faces the arena is drawn in. Loaded here rather than in the
     layout so a page with no bout cards pays nothing for them; the preconnects
     are already in the layout for Inter. --}}
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow+Condensed:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══ VS ARENA — a 1920×1080 canvas scaled into a card thumbnail.
      Every child is positioned in absolute pixels on that canvas, so one
      transform scales the whole composition and nothing has to be re-sized
      individually. ══ */
.tk-bout-thumb .vsa {
    position: absolute; inset: 0; z-index: 1; overflow: hidden;
    pointer-events: none;
    font-family: 'Barlow Condensed', 'Inter', sans-serif;
    color: #e8e6e0;
    --vsa-scale: .5;
}
.tk-bout-thumb .vsa-stage {
    position: absolute; left: 50%; top: 50%;
    width: 1920px; height: 1080px;
    transform: translate(-50%, -50%) scale(var(--vsa-scale));
    transform-origin: center center;
    background: radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%);
    overflow: hidden;
    /* Hidden until the fit has measured the tile: a card whose parent had no
       height yet would otherwise paint at the fallback scale and show only its
       middle, which reads as a broken image. */
    visibility: hidden;
}
.tk-bout-thumb .vsa.vsa-fit .vsa-stage { visibility: visible; }

/* ── Panels ── */
.vsa-panel { position: absolute; overflow: hidden; }
.vsa-panel-red  { inset: 0 auto 0 0; width: 56%; background: oklch(.28 .09 25);
                  clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%); }
.vsa-panel-blue { inset: 0 0 0 auto; width: 56%; background: oklch(.28 .09 255);
                  clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%); }
.vsa-photo { position: absolute; inset: 0; background-size: cover; background-position: center top; }
.vsa-scrim-red  { position: absolute; inset: 0; background: linear-gradient(115deg, oklch(.45 .18 25 / .55), transparent 55%); }
.vsa-scrim-blue { position: absolute; inset: 0; background: linear-gradient(245deg, oklch(.45 .15 255 / .55), transparent 55%); }
.vsa-fade {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(5,5,7,.95) 0%, rgba(5,5,7,.72) 22%, rgba(5,5,7,.30) 42%, transparent 62%),
                linear-gradient(to bottom, rgba(5,5,7,.82) 0%, rgba(5,5,7,.35) 18%, transparent 32%);
}
.vsa-divider {
    position: absolute; top: -6%; bottom: -6%; left: 50%; width: 3px; margin-left: -1.5px;
    transform: rotate(10.15deg); filter: blur(1px);
    background: linear-gradient(to bottom, transparent, oklch(.85 .16 85 / .9) 20%, oklch(.85 .16 85 / .9) 80%, transparent);
}

/* ── Identities ── */
.vsa-info { position: absolute; z-index: 6; max-width: 44%; display: flex; flex-direction: column; gap: 13px; }
.vsa-info-red  { left: 43px; bottom: 119px; align-items: flex-start; }
.vsa-info-blue { right: 43px; bottom: 119px; align-items: flex-end; text-align: right; }
.vsa-rev { flex-direction: row-reverse; }
.vsa-tag { font-size: 22px; font-weight: 800; letter-spacing: .35em; color: #fff; padding: 5px 15px 5px 19px; line-height: 1; }
.vsa-tag-red  { background: oklch(.55 .20 25); }
.vsa-tag-blue { background: oklch(.50 .16 255); }
.vsa-flag-row { display: flex; align-items: center; gap: 15px; }
.vsa-flag {
    width: 56px; aspect-ratio: 4/3; display: inline-block; line-height: 0;
    border: 1px solid rgba(255,255,255,.35); box-shadow: 0 4px 18px rgba(0,0,0,.6);
    background-size: cover !important; background-position: center !important;
}
.vsa-name {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 71px; line-height: .95; text-transform: uppercase; color: #fff;
    text-shadow: 0 6px 30px rgba(0,0,0,.8);
}
.vsa-club-row { display: flex; align-items: center; gap: 13px; margin-top: 4px; }
.vsa-logo {
    width: 69px; height: 69px; border-radius: 50%;
    background: rgba(255,255,255,.06) center/cover;
    border: 1px solid rgba(255,255,255,.2);
}
.vsa-club { font-size: 30px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: rgba(232,230,224,.9); }

/* ── Top ── */
.vsa-top {
    position: absolute; top: 35px; left: 50%; transform: translateX(-50%);
    display: flex; flex-direction: column; align-items: center; gap: 11px;
    z-index: 8; width: 92%;
}
.vsa-event { font-size: 30px; font-weight: 700; letter-spacing: .42em; text-transform: uppercase;
             color: rgba(232,230,224,.92); text-align: center; text-shadow: 0 2px 14px rgba(0,0,0,.9); }
.vsa-stage-row { display: flex; align-items: center; gap: 17px; }
.vsa-rule { height: 2px; width: 65px; display: block; }
.vsa-rule-l { background: linear-gradient(to left,  oklch(.85 .16 85), transparent); }
.vsa-rule-r { background: linear-gradient(to right, oklch(.85 .16 85), transparent); }
.vsa-stage-txt { font-family: 'Anton', sans-serif; font-size: 39px; letter-spacing: .30em;
                 padding-left: .3em; color: oklch(.85 .16 85); text-transform: uppercase; }
.vsa-weight { font-family: 'Anton', sans-serif; font-size: 42px; letter-spacing: .28em;
              padding-left: .28em; text-transform: uppercase; color: #fff; text-shadow: 0 2px 14px rgba(0,0,0,.9); }

/* ── Centre ── */
.vsa-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -52%); z-index: 7; }
.vsa-word {
    position: relative; font-family: 'Anton', sans-serif; font-size: 184px; font-style: italic;
    color: #fffdf5; line-height: 1; -webkit-text-stroke: 2px oklch(.85 .16 85 / .6);
    animation: vsaPulse 2.4s ease-in-out infinite;
}
.vsa-shine-wrap { position: absolute; inset: -10% -20%; overflow: hidden; }
.vsa-shine { position: absolute; top: 0; bottom: 0; width: 34%;
             background: linear-gradient(to right, transparent, rgba(255,255,255,.16), transparent);
             animation: vsaShine 5s ease-in-out infinite; }

/* ── Bottom chips ── */
.vsa-bottom {
    position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 17px; z-index: 8; align-items: center; flex-wrap: wrap;
    justify-content: center; max-width: 94%;
}
.vsa-chip { display: flex; align-items: baseline; gap: 9px; background: rgba(10,10,14,.72);
            border: 1px solid oklch(.85 .16 85 / .45); padding: 11px 24px; }
.vsa-chip-lbl { font-size: 24px; font-weight: 600; letter-spacing: .30em; text-transform: uppercase; color: rgba(232,230,224,.65); }
.vsa-chip-val { font-family: 'Anton', sans-serif; font-size: 35px; color: #fff; }
.vsa-diamond { width: 6px; height: 6px; transform: rotate(45deg); background: oklch(.85 .16 85); display: block; }

/* ── Entrance, and the exit that clears the stage for the video ── */
.vsa.vsa-run .vsa-panel-red  { animation: vsaPanelL .9s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-panel-blue { animation: vsaPanelR .9s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-info-red   { animation: vsaRise .8s .55s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-info-blue  { animation: vsaRise .8s .68s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-top        { animation: vsaDrop .8s .4s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-center     { animation: vsaSlam .7s .85s cubic-bezier(.22,1,.36,1) both; }
.vsa.vsa-run .vsa-bottom     { animation: vsaRiseC .8s 1s cubic-bezier(.22,1,.36,1) both; }

.vsa.vsa-out                 { animation: vsaFadeOut .5s .2s ease forwards; }
.vsa.vsa-out .vsa-panel-red  { animation: vsaOutL .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-panel-blue { animation: vsaOutR .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-info-red   { animation: vsaOutIL .45s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-info-blue  { animation: vsaOutIR .45s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-top        { animation: vsaOutT .45s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-bottom     { animation: vsaOutB .45s cubic-bezier(.55,0,.7,.2) forwards !important; }
.vsa.vsa-out .vsa-center     { animation: vsaOutVS .55s cubic-bezier(.34,1.56,.64,1) forwards !important; }
.vsa.vsa-out .vsa-divider    { animation: vsaOutDiv .3s ease forwards !important; }

@keyframes vsaPulse { 0%,100% { text-shadow: 0 0 30px rgba(255,215,120,.55), 0 0 90px rgba(255,170,60,.3); transform: scale(1); }
                      50%     { text-shadow: 0 0 65px rgba(255,220,130,1), 0 0 160px rgba(255,170,60,.7); transform: scale(1.045); } }
@keyframes vsaShine { 0% { transform: translateX(-130%) skewX(-18deg); } 60%,100% { transform: translateX(230%) skewX(-18deg); } }
@keyframes vsaPanelL { from { transform: translateX(-105%); } to { transform: translateX(0); } }
@keyframes vsaPanelR { from { transform: translateX(105%); }  to { transform: translateX(0); } }
@keyframes vsaRise   { from { opacity: 0; transform: translateY(43px); } to { opacity: 1; transform: none; } }
@keyframes vsaRiseC  { from { opacity: 0; transform: translate(-50%, 43px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsaDrop   { from { opacity: 0; transform: translate(-50%, -32px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsaSlam   { 0% { opacity: 0; transform: translate(-50%,-52%) scale(3.4) rotate(-6deg); }
                       60% { opacity: 1; transform: translate(-50%,-52%) scale(.92) rotate(1deg); }
                       100% { opacity: 1; transform: translate(-50%,-52%) scale(1); } }
@keyframes vsaFadeOut { to { opacity: 0; } }
@keyframes vsaOutL   { to { opacity: 0; transform: translateX(-140%) scale(1.05); } }
@keyframes vsaOutR   { to { opacity: 0; transform: translateX(140%) scale(1.05); } }
@keyframes vsaOutIL  { to { opacity: 0; transform: translate(-70%, 40%) scale(.85); } }
@keyframes vsaOutIR  { to { opacity: 0; transform: translate(70%, 40%) scale(.85); } }
@keyframes vsaOutT   { to { opacity: 0; transform: translate(-50%,-120%) scale(.9); } }
@keyframes vsaOutB   { to { opacity: 0; transform: translate(-50%,120%) scale(.9); } }
@keyframes vsaOutVS  { 0% { opacity: 1; transform: translate(-50%,-52%) scale(1); }
                       40% { opacity: 1; transform: translate(-50%,-52%) scale(1.3) rotate(-2deg); filter: blur(1px); }
                       100% { opacity: 0; transform: translate(-50%,-52%) scale(4) rotate(6deg); filter: blur(8px); } }
@keyframes vsaOutDiv { to { opacity: 0; transform: rotate(10.15deg) scaleY(0); } }

/* The preview sits above the arena; the scorebar above both. */
.tk-bout-video { z-index: 5; transition: opacity .3s ease; }
.tk-bout-thumb .sba { position: absolute; inset: 0; z-index: 6; pointer-events: none;
                      opacity: 0; transition: opacity .25s ease .05s;
                      font-family: 'Barlow Condensed', 'Inter', sans-serif; }
.tk-bout-thumb .sba.sba-on { opacity: 1; }

.sba-scale { position: absolute; bottom: 0; left: 0; width: 1150px; height: 100px;
             transform-origin: bottom left; transform: scale(var(--sba-scale, .4)); }
.sba-bar { position: absolute; left: 0; right: 0; bottom: 14px; padding: 0 26px;
           display: flex; align-items: stretch; height: 84px; }
.sba-side { flex: 1 1 0; min-width: 0; transform: skewX(-9deg); overflow: hidden;
            display: flex; align-items: center; gap: 12px; padding: 0 16px; }
.sba-side > * { transform: skewX(9deg); }
.sba-side-red  { background: linear-gradient(90deg, rgba(122,26,22,.94), rgba(180,52,44,.9)); border-bottom: 3px solid #ff6a5e; }
.sba-side-blue { background: linear-gradient(90deg, rgba(30,72,140,.9), rgba(18,44,92,.94)); border-bottom: 3px solid #6aa6ff;
                 flex-direction: row-reverse; }
.sba-id { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 10px; }
.sba-id-r { flex-direction: row-reverse; }
.sba-flag { width: 26px; height: 17px; flex: none; display: inline-block;
            box-shadow: 0 0 0 1px rgba(255,255,255,.3);
            background-size: cover !important; background-position: center !important; }
.sba-name { flex: 1 1 auto; min-width: 0; font-size: 25px; line-height: 1; font-weight: 800; color: #fff;
            text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sba-tag { font-size: 11px; letter-spacing: .24em; color: rgba(255,236,232,.7); flex: none; }
.sba-score { font-size: 46px; line-height: .8; font-weight: 700; color: #fff;
             text-shadow: 0 6px 20px rgba(0,0,0,.5); flex: none; font-variant-numeric: tabular-nums; }
.sba-plate { width: 118px; flex: none; transform: skewX(-9deg); background: rgba(9,9,11,.9);
             display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
             border-bottom: 3px solid #2c2a28; }
.sba-plate > * { transform: skewX(9deg); }
.sba-round { font-size: 11px; letter-spacing: .3em; color: #8f8a83; text-transform: uppercase; }
.sba-clock { font-size: 34px; line-height: 1; font-weight: 800; color: #efe9e0; font-variant-numeric: tabular-nums; }

.sba-feed-scale { position: absolute; top: 0; left: 0; width: 1150px; height: 100%;
                  transform-origin: top left; transform: scale(var(--sba-scale, .4)); }
.sba-feed { position: absolute; top: 22px; right: 24px; width: 262px;
            display: flex; flex-direction: column; gap: 7px; }
.sba-feed-hdr { display: flex; align-items: center; justify-content: flex-end; gap: 8px;
                font-size: 12px; letter-spacing: .3em; color: #d5cfc7; text-transform: uppercase;
                text-shadow: 0 1px 6px rgba(0,0,0,.9); }
.sba-dot { width: 7px; height: 7px; border-radius: 50%; background: #e8534a; animation: sbaPulse 1.6s infinite; }
.sba-feed-row { display: flex; align-items: center; justify-content: flex-end; gap: 10px;
                padding: 7px 10px; background: rgba(10,10,12,.62);
                border-inline-end: 3px solid transparent; animation: sbaRise .45s ease both; }
.sba-feed-ts  { font-size: 12px; letter-spacing: .16em; color: #79736c; }
.sba-feed-lbl { font-size: 17px; letter-spacing: .12em; font-weight: 700; color: #efe9e0; text-transform: uppercase; }
.sba.sba-small .sba-feed { display: none; }

@keyframes sbaPulse { 0%,100% { opacity: 1; } 50% { opacity: .25; } }
@keyframes sbaRise  { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

/* ══ Touch, and anyone who asked for less motion: show the composed frame at
      rest. The intro is ~1.3s of staged delays and is worth nothing to someone
      scrolling a list — and half-finished it leaves elements at opacity 0. ══ */
@media (hover: none), (prefers-reduced-motion: reduce) {
    .vsa, .vsa .vsa-panel, .vsa .vsa-info, .vsa .vsa-top, .vsa .vsa-center, .vsa .vsa-bottom {
        animation: none !important; opacity: 1 !important; transform: none !important;
    }
    .vsa .vsa-top    { transform: translateX(-50%) !important; }
    .vsa .vsa-bottom { transform: translateX(-50%) !important; }
    .vsa .vsa-center { transform: translate(-50%, -52%) !important; }
    .vsa .vsa-word, .vsa .vsa-shine { animation: none !important; }
}
</style>
@endpush

@push('scripts')
<script>
window.takeoneBoutCard = function (scoring, preview) {
    return {
        scoring: scoring, preview: preview,
        intro: false, hovering: false, playing: false,
        red: 0, blue: 0, clock: '0:00', roundName: '', feed: [],
        _hls: null, _fitObs: null,

        mount() {
            this.fit();
            // One observer per card: the tile's size is decided by a responsive
            // grid, and the arena has to be re-scaled whenever that changes.
            if ('ResizeObserver' in window) {
                this._fitObs = new ResizeObserver(() => this.fit());
                this._fitObs.observe(this.$el);
            }

            // The intro only plays where there is a pointer to play it for.
            if (window.matchMedia('(hover: hover)').matches
                && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.intro = true;
            }
        },

        fit() {
            const w = this.$el.clientWidth, h = this.$el.clientHeight;
            if (!w || !h) return;
            if (this.$refs.arena) {
                this.$refs.arena.style.setProperty('--vsa-scale', Math.min(w / 1920, h / 1080));
                this.$refs.arena.classList.add('vsa-fit');
            }
            if (this.$refs.sba) {
                this.$refs.sba.style.setProperty('--sba-scale', w / 1150);
                this.$refs.sba.classList.toggle('sba-small', w < 340);
            }
        },

        enter() {
            if (!this.preview || !window.matchMedia('(hover: hover)').matches) return;
            this.hovering = true;
            this.start();
        },

        leave() {
            this.hovering = false;
            this.stop();
            // Replay the entrance next time, so the card is never left mid-exit.
            this.intro = false;
            this.$nextTick(() => {
                if (window.matchMedia('(hover: hover)').matches
                    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.intro = true;
                }
            });
        },

        /*
         * The preview.
         *
         * Always the LADDER, pinned to its lowest rung — a card is a thumbnail,
         * and a bout's original file can be a gigabyte. hls.js is fetched on
         * demand and shared with the player and the lightbox.
         */
        start() {
            const v = this.$refs.video;
            if (!v) return;

            const play = () => { v.currentTime = 0; v.play().then(() => { this.playing = true; }).catch(() => {}); };

            if (v.src || this._hls) { play(); return; }

            if (v.canPlayType('application/vnd.apple.mpegurl')) {
                v.src = this.preview;
                v.addEventListener('loadedmetadata', play, { once: true });
                return;
            }

            if (!window.__tkHlsPromise) {
                window.__tkHlsPromise = new Promise((resolve) => {
                    const tag = document.createElement('script');
                    tag.src = @js(asset('vendor/hls/hls.min.js'));
                    tag.onload = () => resolve(window.Hls);
                    tag.onerror = () => resolve(null);
                    document.head.appendChild(tag);
                });
            }

            window.__tkHlsPromise.then((Hls) => {
                if (!Hls || !Hls.isSupported() || !this.hovering) return;
                this._hls = new Hls({ startLevel: 0, capLevelToPlayerSize: true, maxBufferLength: 10 });
                this._hls.loadSource(this.preview);
                this._hls.attachMedia(v);
                this._hls.on(Hls.Events.MANIFEST_PARSED, play);
            });

            if (!this._tick) {
                this._tick = () => this.sync();
                v.addEventListener('timeupdate', this._tick);
            }
        },

        stop() {
            const v = this.$refs.video;
            this.playing = false;
            if (!v) return;
            try { v.pause(); } catch (e) {}
            this.red = 0; this.blue = 0; this.clock = '0:00'; this.feed = [];
        },

        /* The scorebar follows the preview's own clock. */
        sync() {
            if (!this.scoring) return;
            const t = this.$refs.video?.currentTime || 0;

            let round = (this.scoring.rounds || [])[0] || { name: '', start: 0 };
            for (const r of (this.scoring.rounds || [])) { if (t >= (r.start || 0)) round = r; }
            this.roundName = round.name || '';
            this.clock = this.mmss(Math.max(0, t - (round.start || 0)));

            const seen = (this.scoring.points || []).filter((p) => p.t <= t + 0.001);
            const last = seen[seen.length - 1];
            this.red = last ? last.sr : 0;
            this.blue = last ? last.sb : 0;
            this.feed = seen.slice(-4).reverse();
        },

        mmss(s) {
            s = Math.max(0, Math.floor(s || 0));
            return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        },
    };
};
</script>
@endpush
@endonce
