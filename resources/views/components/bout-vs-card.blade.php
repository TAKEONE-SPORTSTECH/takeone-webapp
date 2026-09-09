@props([
    /* The arena payload from App\Media\BoutArena — unchanged contract. */
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
    /*
     * The delete endpoint for this bout's footage, or null for no menu.
     *
     * Null by default and passed only where the viewer may actually use it:
     * `me.events.bout.video.destroy` asks EventAccess::canManage and enforces
     * that itself, so rendering the menu for anybody else would be a control
     * that exists to return 403 (Navigation Integrity — no dead ends).
     */
    'deleteUrl' => null,
])

{{--
    The match video card.

    ⚠️ THE CSS AND THE SCRIPT BELOW ARE THE STANDALONE TEMPLATE, VERBATIM.
    They are copied byte-for-byte from `drafts/Match Video Card — Standalone.html`
    and must stay that way: the VS intro, the explode-on-hover, the scorebar fit
    and the live-scoring feed are a single choreography, and editing one
    keyframe or one selector to "tidy" it is how that choreography breaks in a
    way nobody notices until a card is on a screen in front of a hall.

    What this file adds is ONLY the data. Every literal in the template — the
    fighters, the clubs, the flags, the stage, the scoring — is replaced by the
    value from `$arena` and nothing else moves. Empty values are emitted as
    empty strings on purpose: the template already hides them
    (`.vsm-nullable:empty { display:none }`), which is why a bout with no club
    logo or no referee still composes correctly.

    Two deliberate omissions from the copy, both outside the card:
      · the template's `.page` block — a dark backdrop and a grid that existed
        only to preview the card on its own. The gallery supplies its own grid.
      · `<html>/<body>` — this is a component, not a document.

    Data → template, for anyone reading both side by side:
      $arena['event'|'stage'|'weight'|'match_no'|'court'|'referee']
      $arena['red'|'blue'] → name, tag, country (iso2), club, club_logo, photo, score
      $arena['scoring']    → data-sbm-state {rounds, points, sport}
--}}

@php
    $red  = $arena['red'] ?? [];
    $blue = $arena['blue'] ?? [];

    /* The template draws a 2-letter flag sprite; anything else is dropped
       rather than rendered as a broken tile (BoutArena::iso already narrows
       this, so this is belt and braces for a hand-built payload). */
    $iso = static function ($v) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $v), 0, 2));

        return strlen($c) === 2 ? $c : null;
    };

    $redFlag  = $iso($red['country'] ?? null);
    $blueFlag = $iso($blue['country'] ?? null);

    /* An Open Mat's event, stage and division are often the same words, and a
       card reading "Open Mat / Open mat / Open Mat" is the data model talking to
       itself. Say each thing once, in the order the eye reads them. Carried over
       from the previous card because it is a property of OUR data, not of the
       template. */
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

    $mins = intdiv((int) $duration, 60);
    $secs = str_pad((string) ((int) $duration % 60), 2, '0', STR_PAD_LEFT);

    /* The scorebar reads this attribute and nothing else. Null when the bout has
       no officiating log, in which case the template simply never animates it. */
    $sbm = $arena['scoring'] ?? null;
@endphp

@once
@push('styles')
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow+Condensed:wght@400;600;700;800&family=Zen+Old+Mincho:wght@400;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

<!-- Flag icons (CDN — swap for self-host if desired) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">
<style>
@verbatim
/* ══════════════════════════════════════════════════════════════════════
   BASE VIDEO CARD  (shared with generic / music / match)
════════════════════════════════════════════════════════════════════════ */
.yt-video-card { cursor: pointer; display: flex; flex-direction: column; }
.yt-video-card a { color: inherit; text-decoration: none; }

.yt-video-card .yt-video-thumb {
    position: relative; aspect-ratio: 16/9;
    border-radius: 12px; overflow: hidden; background: #1a1a1a;
}
.yt-video-card .yt-video-thumb::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, #1a1a1a 25%, #2a2a2a 50%, #1a1a1a 75%);
    background-size: 200% 100%;
    animation: thumb-shimmer 1.4s ease infinite;
    z-index: 0; border-radius: inherit;
}
@keyframes thumb-shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.yt-video-card .yt-video-thumb.loaded::before { animation: none; opacity: 0; }

.yt-video-card .yt-video-thumb img {
    width: 100%; height: 100%; object-fit: cover;
    position: absolute; top: 0; left: 0; opacity: 0;
    transition: opacity 0.3s ease, transform 0.2s ease;
    z-index: 1;
}
.yt-video-card .yt-video-thumb img.loaded { opacity: 1; }
.yt-video-card:hover .yt-video-thumb img.loaded { transform: scale(1.03); }

.yt-video-card .yt-video-thumb video {
    width: 100%; height: 100%; object-fit: cover;
    position: absolute; top: 0; left: 0; opacity: 0;
    transition: opacity 0.3s ease; background: #000; z-index: 2;
}
.yt-video-card .yt-video-thumb video.active { opacity: 1; }

.yt-video-card .yt-video-duration {
    position: absolute; bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.8); color: white;
    padding: 3px 6px; border-radius: 4px;
    font-size: 12px; font-weight: 500; z-index: 3;
}

.yt-video-card .yt-video-info {
    display: flex; margin-top: 12px; gap: 12px;
    height: 76px; overflow: hidden;
}
.yt-video-card .yt-channel-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: #555; flex-shrink: 0; overflow: hidden;
    background-size: cover; background-position: center;
}
.yt-video-card .yt-video-details { min-width: 0; flex: 1; }
.yt-video-card .yt-video-title {
    font-size: 16px; font-weight: 500; color: #fff;
    margin: 0 0 4px; line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.yt-video-card .yt-video-title a { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.yt-video-card .yt-video-title a > * { vertical-align: middle; }
.yt-video-card .yt-channel-name,
.yt-video-card .yt-video-meta {
    color: #aaa; font-size: 14px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 22px; height: 22px;
}
.yt-type-label {
    font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px;
}
.yt-type-match { color: #60a5fa; }

/* ══════════════════════════════════════════════════════════════════════
   VS MINI — arena intro that plays inside the thumb
════════════════════════════════════════════════════════════════════════ */
.yt-video-thumb .vs-mini {
    position: absolute; inset: 0; z-index: 1;
    background: #050507;
    font-family: 'Barlow Condensed', sans-serif;
    color: #e8e6e0; overflow: hidden; pointer-events: none;
    --vs-mini-scale: 0.5;
    --vs-mini-w: 1920px; --vs-mini-h: 1080px;
}
.yt-video-card:hover .yt-video-thumb .vs-mini {
    animation: vsmFadeOut .55s .25s ease forwards;
}
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-panel-red   { animation: vsmExplodeL .55s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-panel-blue  { animation: vsmExplodeR .55s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-info-red    { animation: vsmExplodeInfoL .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-info-blue   { animation: vsmExplodeInfoR .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-top         { animation: vsmExplodeTop .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-bottom      { animation: vsmExplodeBot .5s cubic-bezier(.55,0,.7,.2) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-center      { animation: vsmExplodeVS  .6s cubic-bezier(.34,1.56,.64,1) forwards !important; }
.yt-video-card:hover .yt-video-thumb .vs-mini .vs-mini-divider     { animation: vsmDividerOut .35s ease forwards !important; }

.yt-video-thumb:has(.vs-mini) video { z-index: 5 !important; background: transparent !important; }

.vs-mini .vs-mini-stage {
    position: absolute; left: 50%; top: 50%;
    width: var(--vs-mini-w); height: var(--vs-mini-h);
    transform: translate(-50%, -50%) scale(var(--vs-mini-scale));
    transform-origin: center center;
    background: radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%);
    overflow: hidden; will-change: transform; visibility: hidden;
}
.vs-mini.vs-mini-fit .vs-mini-stage { visibility: visible; }

@media (hover: none) {
    .vs-mini, .vs-mini .vs-mini-panel, .vs-mini .vs-mini-info,
    .vs-mini .vs-mini-top, .vs-mini .vs-mini-center, .vs-mini .vs-mini-bottom {
        animation: none !important; opacity: 1 !important; transform: none !important;
    }
    .vs-mini .vs-mini-panel-red  { transform: translateX(0) !important; }
    .vs-mini .vs-mini-panel-blue { transform: translateX(0) !important; }
    .vs-mini .vs-mini-top        { transform: translateX(-50%) !important; }
    .vs-mini .vs-mini-bottom     { transform: translateX(-50%) !important; }
    .vs-mini .vs-mini-center     { transform: translate(-50%, -52%) !important; }
    .vs-mini .vs-mini-photo, .vs-mini .vs-mini-word, .vs-mini .vs-mini-shine { animation: none !important; }
}

/* ── Panels ── */
.vs-mini .vs-mini-panel { position: absolute; overflow: hidden; opacity: 0; }
.vs-mini .vs-mini-panel-red {
    inset: 0 auto 0 0; width: 56%;
    background: oklch(0.28 0.09 25);
    clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);
}
.vs-mini .vs-mini-panel-blue {
    inset: 0 0 0 auto; width: 56%;
    background: oklch(0.28 0.09 255);
    clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);
}
.vs-mini.vs-mini-run .vs-mini-panel-red  { animation: vsmPanelL .9s cubic-bezier(.22,1,.36,1) both; }
.vs-mini.vs-mini-run .vs-mini-panel-blue { animation: vsmPanelR .9s cubic-bezier(.22,1,.36,1) both; }

.vs-mini .vs-mini-photo {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    animation: vsmDrift 18s ease-in-out infinite;
}
.vs-mini .vs-mini-photo-rev { animation-direction: reverse; }
.vs-mini .vs-mini-scrim-red  { position: absolute; inset: 0;
    background: linear-gradient(115deg, oklch(0.45 0.18 25 / 0.55) 0%, transparent 55%); }
.vs-mini .vs-mini-scrim-blue { position: absolute; inset: 0;
    background: linear-gradient(245deg, oklch(0.45 0.15 255 / 0.55) 0%, transparent 55%); }
.vs-mini .vs-mini-fade {
    position: absolute; inset: 0;
    background:
        linear-gradient(to top, rgba(5,5,7,.95) 0%, rgba(5,5,7,.72) 22%, rgba(5,5,7,.30) 42%, transparent 62%),
        linear-gradient(to bottom, rgba(5,5,7,.82) 0%, rgba(5,5,7,.35) 18%, transparent 32%);
}

/* Divider */
.vs-mini .vs-mini-divider {
    position: absolute; top: -6%; bottom: -6%; left: 50%;
    width: 3px; margin-left: -1.5px;
    transform: rotate(10.15deg);
    background: linear-gradient(to bottom, transparent, oklch(0.85 0.16 85 / .9) 20%, oklch(0.85 0.16 85 / .9) 80%, transparent);
    pointer-events: none; filter: blur(1px);
}

/* Fighter identity */
.vs-mini .vs-mini-info {
    position: absolute; z-index: 6; max-width: 44%;
    display: flex; flex-direction: column; gap: 13px; opacity: 0;
}
.vs-mini .vs-mini-info-red  { left: 43.2px; bottom: 118.8px; align-items: flex-start; }
.vs-mini .vs-mini-info-blue { right: 43.2px; bottom: 118.8px; align-items: flex-end; text-align: right; }
.vs-mini.vs-mini-run .vs-mini-info-red  { animation: vsmRiseUp .8s .7s  cubic-bezier(.22,1,.36,1) both; }
.vs-mini.vs-mini-run .vs-mini-info-blue { animation: vsmRiseUp .8s .85s cubic-bezier(.22,1,.36,1) both; }

.vs-mini .vs-mini-tag {
    font: 800 21.6px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .35em; color: #fff;
    padding: 5.4px 15.1px 5.4px 18.9px;
}
.vs-mini .vs-mini-tag-red  { background: oklch(0.55 0.20 25); }
.vs-mini .vs-mini-tag-blue { background: oklch(0.50 0.16 255); }

.vs-mini .vs-mini-flag-row   { display: flex; align-items: center; gap: 15.1px; }
.vs-mini .vs-mini-flag-row-r { flex-direction: row-reverse; }
.vs-mini .vs-mini-flag {
    width: 56.2px; height: auto; aspect-ratio: 4/3;
    background-size: cover !important; background-position: center !important;
    border: 1px solid rgba(255,255,255,.35);
    box-shadow: 0 4px 18px rgba(0,0,0,.6);
    display: inline-block; line-height: 0;
}
.vs-mini .vs-mini-country      { font: 700 32.4px/1 'Barlow Condensed', sans-serif; letter-spacing: .28em; }
.vs-mini .vs-mini-country-red  { color: oklch(0.85 0.05 25); }
.vs-mini .vs-mini-country-blue { color: oklch(0.85 0.05 255); }

.vs-mini .vs-mini-name {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 71.3px; line-height: .95;
    text-transform: uppercase; color: #fff;
    text-shadow: 0 6px 30px rgba(0,0,0,.8);
}

.vs-mini .vs-mini-club-row   { display: flex; align-items: center; gap: 13px; margin-top: 4.3px; }
.vs-mini .vs-mini-club-row-r { flex-direction: row-reverse; }
.vs-mini .vs-mini-logo {
    width: 69.1px; height: 69.1px; border-radius: 50%;
    background: rgba(255,255,255,.06);
    background-size: cover; background-position: center;
    border: 1px solid rgba(255,255,255,.2);
}
.vs-mini .vs-mini-club {
    font: 600 30.2px/1.1 'Barlow Condensed', sans-serif;
    letter-spacing: .12em; text-transform: uppercase;
    color: rgba(232,230,224,.9);
}

.vs-mini .vs-mini-chips-row { display: flex; flex-wrap: wrap; gap: 9.7px; margin-top: 5.4px; }
.vs-mini .vs-mini-info-blue .vs-mini-chips-row { justify-content: flex-end; }
.vs-mini .vs-mini-chip-fighter {
    font: 700 23.8px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .12em; text-transform: uppercase;
    padding: 5.4px 14px;
    background: rgba(10,10,14,.62);
    border: 1px solid rgba(255,255,255,.25);
    color: #fff;
}
.vs-mini .vs-mini-chip-fighter-gold {
    border-color: oklch(0.85 0.16 85 / .6);
    color: oklch(0.87 0.14 85);
}

.vs-mini .vsm-nullable:empty { display: none; }
.vs-mini .vsm-nullable-hide  { display: none; }
.vs-mini .vs-mini-chip.vsm-nullable-chip[data-vsm-empty="1"] { display: none; }

/* Top block */
.vs-mini .vs-mini-top {
    position: absolute; top: 34.6px; left: 50%; transform: translateX(-50%);
    display: flex; flex-direction: column; align-items: center; gap: 10.8px;
    z-index: 8; width: 92%; pointer-events: none; opacity: 0;
}
.vs-mini.vs-mini-run .vs-mini-top { animation: vsmDropIn .8s .5s cubic-bezier(.22,1,.36,1) both; }
.vs-mini .vs-mini-top-event {
    font: 700 30.2px/1.05 'Barlow Condensed', sans-serif;
    letter-spacing: .42em; text-transform: uppercase;
    color: rgba(232,230,224,.92); text-align: center;
    text-shadow: 0 2px 14px rgba(0,0,0,.9);
}
.vs-mini .vs-mini-top-stage-row { display: flex; align-items: center; gap: 17.3px; }
.vs-mini .vs-mini-top-line      { height: 2px; width: 64.8px; }
.vs-mini .vs-mini-top-line-l { background: linear-gradient(to left,  oklch(0.85 0.16 85), transparent); }
.vs-mini .vs-mini-top-line-r { background: linear-gradient(to right, oklch(0.85 0.16 85), transparent); }
.vs-mini .vs-mini-top-stage {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 38.9px; letter-spacing: .30em; padding-left: .3em;
    color: oklch(0.85 0.16 85); text-transform: uppercase;
}
.vs-mini .vs-mini-top-weight {
    font: 700 42px/1.1 'Anton', 'Barlow Condensed', sans-serif;
    letter-spacing: .28em; padding-left: .28em; text-transform: uppercase;
    color: #fff; text-shadow: 0 2px 14px rgba(0,0,0,.9);
}

/* Center VS */
.vs-mini .vs-mini-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -52%);
    z-index: 7; pointer-events: none; opacity: 0;
    display: flex; align-items: center; justify-content: center;
}
.vs-mini.vs-mini-run .vs-mini-center { animation: vsmSlam .7s 1.1s cubic-bezier(.22,1,.36,1) both; }
.vs-mini .vs-mini-word {
    position: relative;
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 183.6px; font-style: italic;
    color: #fffdf5; line-height: 1;
    -webkit-text-stroke: 2px oklch(0.85 0.16 85 / 0.6);
    animation: vsmPulse 2.4s ease-in-out infinite;
    overflow: visible;
}
.vs-mini .vs-mini-shine-wrap { position: absolute; inset: -10% -20%; overflow: hidden; pointer-events: none; }
.vs-mini .vs-mini-shine {
    position: absolute; top: 0; bottom: 0; width: 34%;
    background: linear-gradient(to right, transparent, rgba(255,255,255,.16), transparent);
    animation: vsmShine 5s ease-in-out infinite;
}

/* Bottom chips */
.vs-mini .vs-mini-bottom {
    position: absolute; bottom: 32.4px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 17.3px; z-index: 8; align-items: center;
    flex-wrap: wrap; justify-content: center; max-width: 94%; opacity: 0;
}
.vs-mini.vs-mini-run .vs-mini-bottom { animation: vsmRiseC .8s 1.3s cubic-bezier(.22,1,.36,1) both; }
.vs-mini .vs-mini-chip {
    display: flex; align-items: baseline; gap: 8.6px;
    background: rgba(10,10,14,.72);
    border: 1px solid oklch(0.85 0.16 85 / .45);
    padding: 10.8px 23.8px; backdrop-filter: blur(6px);
}
.vs-mini .vs-mini-chip-lbl {
    font: 600 23.8px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .30em; text-transform: uppercase;
    color: rgba(232,230,224,.65);
}
.vs-mini .vs-mini-chip-val {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 34.6px; color: #fff;
}
.vs-mini .vs-mini-diamond {
    width: 6px; height: 6px; transform: rotate(45deg);
    background: oklch(0.85 0.16 85);
}

.yt-video-thumb:has(.vs-mini)::before { display: none; }

/* VS-mini animations */
@keyframes vsmPulse {
    0%,100% { text-shadow: 0 0 30px rgba(255,215,120,.55), 0 0 90px rgba(255,170,60,.3); transform: scale(1); }
    50%     { text-shadow: 0 0 65px rgba(255,220,130,1),   0 0 160px rgba(255,170,60,.7); transform: scale(1.045); }
}
@keyframes vsmShine  { 0% { transform: translateX(-130%) skewX(-18deg); } 60%,100% { transform: translateX(230%) skewX(-18deg); } }
@keyframes vsmDrift  { 0% { transform: translate3d(0,0,0) scale(1.02); } 50% { transform: translate3d(0,-1.2%,0) scale(1.05); } 100% { transform: translate3d(0,0,0) scale(1.02); } }
@keyframes vsmPanelL { from { opacity: 1; transform: translateX(-105%); } to { opacity: 1; transform: translateX(0); } }
@keyframes vsmPanelR { from { opacity: 1; transform: translateX( 105%); } to { opacity: 1; transform: translateX(0); } }
@keyframes vsmRiseUp { from { opacity: 0; transform: translateY(43.2px); } to { opacity: 1; transform: translateY(0); } }
@keyframes vsmRiseC  { from { opacity: 0; transform: translate(-50%, 43.2px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsmDropIn { from { opacity: 0; transform: translate(-50%, -32.4px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsmSlam {
    0%   { opacity: 0; transform: translate(-50%,-52%) scale(3.4) rotate(-6deg); }
    60%  { opacity: 1; transform: translate(-50%,-52%) scale(.92) rotate(1deg); }
    80%  {              transform: translate(-50%,-52%) scale(1.06); }
    100% { opacity: 1; transform: translate(-50%,-52%) scale(1) rotate(0deg); }
}
@keyframes vsmFadeOut      { to { opacity: 0; } }
@keyframes vsmExplodeL     { from { opacity: 1; transform: translateX(0)      scale(1); } to { opacity: 0; transform: translateX(-140%) scale(1.05); } }
@keyframes vsmExplodeR     { from { opacity: 1; transform: translateX(0)      scale(1); } to { opacity: 0; transform: translateX( 140%) scale(1.05); } }
@keyframes vsmExplodeInfoL { from { opacity: 1; transform: translate(0,0)    scale(1); } to { opacity: 0; transform: translate(-70%, 40%) scale(.85); } }
@keyframes vsmExplodeInfoR { from { opacity: 1; transform: translate(0,0)    scale(1); } to { opacity: 0; transform: translate( 70%, 40%) scale(.85); } }
@keyframes vsmExplodeTop   { from { opacity: 1; transform: translate(-50%,0)    scale(1); } to { opacity: 0; transform: translate(-50%,-120%) scale(.9); } }
@keyframes vsmExplodeBot   { from { opacity: 1; transform: translate(-50%,0)    scale(1); } to { opacity: 0; transform: translate(-50%, 120%) scale(.9); } }
@keyframes vsmExplodeVS    {
    0%   { opacity: 1; transform: translate(-50%,-52%) scale(1)  rotate(0deg); filter: blur(0); }
    40%  { opacity: 1; transform: translate(-50%,-52%) scale(1.3) rotate(-2deg); filter: blur(1px); }
    100% { opacity: 0; transform: translate(-50%,-52%) scale(4)  rotate(6deg); filter: blur(8px); }
}
@keyframes vsmDividerOut   { to { opacity: 0; transform: rotate(10.15deg) scaleY(0); } }

/* ══════════════════════════════════════════════════════════════════════
   SCOREBAR MINI — appears while hover-preview video is playing
════════════════════════════════════════════════════════════════════════ */
.yt-video-thumb .sbm {
    position: absolute; inset: 0; z-index: 6;
    font-family: 'Barlow Condensed', sans-serif;
    color: #efe9e0; pointer-events: none;
    opacity: 0; transition: opacity .25s ease .05s;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
    --sbm-scale: 0.4;
}
.sbm * { box-sizing: border-box; }
.yt-video-thumb:has(video.active) .sbm { opacity: 1; }

.sbm .sbm-scale {
    position: absolute; bottom: 0; left: 0;
    width: 1150px; height: 100px;
    transform-origin: bottom left;
    transform: scale(var(--sbm-scale));
}
.sbm .sbm-feed-scale {
    position: absolute; top: 0; left: 0;
    width: 1150px; height: 100%;
    transform-origin: top left;
    transform: scale(var(--sbm-scale));
    pointer-events: none;
}
.sbm .sbm-feed {
    position: absolute; top: 22px; right: 24px; width: 262px;
    display: flex; flex-direction: column; gap: 7px;
}
.sbm .sbm-feed-hdr {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    font-size: 12px; letter-spacing: .3em; color: #d5cfc7; text-transform: uppercase;
    text-shadow: 0 1px 6px rgba(0,0,0,.9);
}
.sbm .sbm-feed-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #e8534a; animation: sbmPulse 1.6s infinite;
}
.sbm .sbm-feed-list { display: flex; flex-direction: column; gap: 7px; }
.sbm .sbm-feed-entry {
    display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    padding: 7px 10px;
    background: rgba(10,10,12,.62); backdrop-filter: blur(6px);
    border-right: 3px solid transparent;
    animation: sbmRiseIn .45s ease both;
}
.sbm .sbm-feed-entry .ts   { font-size: 12px; line-height: 1; letter-spacing: .16em; color: #79736c; }
.sbm .sbm-feed-entry .name { font-size: 17px; line-height: 1; letter-spacing: .12em; font-weight: 700; color: #efe9e0; text-transform: uppercase; }
.sbm .sbm-feed-entry .pts  { font-family: 'Zen Old Mincho', serif; font-size: 19px; line-height: 1; font-weight: 700; }
.sbm.sbm-small .sbm-feed { display: none; }

@keyframes sbmPulse  { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
@keyframes sbmRiseIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
@endverbatim

/* ══════════════════════════════════════════════════════════════════════
   THEME — the card's text, on THIS platform's pages
   ─────────────────────────────────────────────────────────────────────
   Appended, never edited into the block above: everything before this line
   is the standalone template byte-for-byte, and keeping the seam visible is
   what lets the template be re-copied when it changes.

   Only colours below the thumbnail move. The template was drawn on a #0f0f0f
   preview page, so its title is #fff and its meta #aaa — correct there, and
   invisible on a light page. The THUMBNAIL keeps every colour it was given:
   the VS intro, the scorebar and the video are a broadcast surface and are
   meant to be dark whatever surrounds them.
════════════════════════════════════════════════════════════════════════ */
.yt-video-card .yt-video-title       { color: var(--color-foreground, hsl(215 25% 27%)); }
.yt-video-card .yt-video-title a     { color: inherit; }
.yt-video-card .yt-channel-name,
.yt-video-card .yt-video-meta        { color: var(--color-muted-foreground, hsl(215 15% 50%)); }
.yt-video-card .yt-type-match        { color: var(--color-primary, hsl(250 65% 65%)); }
.yt-video-card .yt-channel-icon      { background: var(--color-muted, hsl(220 15% 94%)); }

/* The fighters' names in the title keep their corner colours, but at a weight
   that reads on white rather than on black. */
.yt-video-card .yt-video-title .yt-corner-red  { color: #dc2626; }
.yt-video-card .yt-video-title .yt-corner-blue { color: #1d4ed8; }

/* ── The title says the whole thing ─────────────────────────────────────
   The template clamps it to one line — `nowrap` + ellipsis inside a fixed
   76px block — which suits a video site where the title is a sentence and
   the first few words carry it. Here the title is TWO PEOPLE'S NAMES, and
   an ellipsis lands squarely on the second one: "Match 10 – Zakaria
   Shuwaiter vs Abdulla…" tells you half a fixture. So it wraps.

   Two lines is the normal case and the block is sized for it; a third is
   allowed rather than truncated, because a long Arabic name on a narrow
   phone should still be readable in full. `balance` keeps a two-line title
   from leaving one orphaned word on the second line. */
.yt-video-card .yt-video-info  { height: auto; min-height: 76px; }
.yt-video-card .yt-video-title {
    font-size: 15px; line-height: 1.35; margin-bottom: 6px;
    white-space: normal; overflow: visible; text-overflow: clip;
    text-wrap: balance;
}
.yt-video-card .yt-video-title a {
    display: block; white-space: normal; overflow: visible; text-overflow: clip;
}
/* A name is one thing: it may move to the next line, never split across two. */
.yt-video-card .yt-video-title .yt-corner-red,
.yt-video-card .yt-video-title .yt-corner-blue { white-space: nowrap; }

/* ── The context line, above the names ──────────────────────────────────
   Deliberately quiet: small, spaced, upper-case, muted. It is there to be
   found when looked for and to be skipped when not, which is the whole
   point of putting the two fighters on their own line beneath it.

   It wraps rather than truncating — on a narrow phone "Round Robin ·
   Group 1 · Match 10" needs two lines, and half of it is worth nothing. */
.yt-video-card .yt-bout-context {
    font-size: 11px; font-weight: 700; line-height: 1.45;
    letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--color-muted-foreground, hsl(215 15% 50%));
    margin-bottom: 3px;
    display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 6px;
}
.yt-video-card .yt-bout-dot { opacity: 0.45; }

/* "vs" is the join, not a word to read: it steps back so the eye lands on
   the two names either side of it. */
.yt-video-card .yt-bout-vs {
    font-weight: 400;
    color: var(--color-muted-foreground, hsl(215 15% 50%));
}
.yt-video-card .yt-video-title { font-size: 16px; font-weight: 600; }

/* ── The card's own menu ────────────────────────────────────────────────
   On the context row, hard right, sitting on the same baseline as "Round
   Robin · Group 1 · Match 10".

   Not over the artwork: the thumbnail is the one part of the card doing
   work — the VS intro plays there and the preview takes over on hover — and
   a button parked on top of it competes with both. Down here it is beside
   the text it acts on, and it costs the artwork nothing.

   Muted rather than hidden, because it no longer has a dark thumbnail to
   hide against, and a control that only appears on hover cannot be found on
   a touch screen at all. */
.yt-video-card  { position: relative; }
.yt-bout-head   { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 3px; }
.yt-bout-head .yt-bout-context { flex: 1 1 auto; min-width: 0; margin-bottom: 0; }
.yt-card-menu   { position: relative; flex: 0 0 auto; margin-top: -3px; }

.yt-card-menu-btn {
    width: 26px; height: 26px; border: 0; padding: 0; cursor: pointer;
    display: grid; place-items: center;
    border-radius: 50%; font-size: 14px; line-height: 1;
    background: transparent;
    color: var(--color-muted-foreground, hsl(215 15% 50%));
    transition: background .15s ease, color .15s ease;
}
.yt-card-menu-btn:hover,
.yt-card-menu-btn:focus-visible,
.yt-card-menu-btn.is-open {
    background: var(--color-muted, hsl(220 15% 94%));
    color: var(--color-foreground, hsl(215 25% 27%));
}

.yt-card-menu-panel {
    position: absolute; top: 30px; right: 0; min-width: 168px; z-index: 6;
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border, hsl(210 14% 80%));
    border-radius: 12px; padding: 4px; overflow: hidden;
    box-shadow: 0 12px 28px rgba(0,0,0,.18);
}
.yt-card-menu-item {
    width: 100%; border: 0; background: none; cursor: pointer;
    display: flex; align-items: center; gap: 9px;
    padding: 9px 10px; border-radius: 9px;
    font-size: 13px; font-weight: 600; text-align: start;
    color: var(--color-foreground, hsl(215 25% 27%));
}
.yt-card-menu-item:hover:not(:disabled) { background: var(--color-muted, hsl(220 15% 94%)); }
.yt-card-menu-item:disabled { opacity: .55; cursor: default; }
.yt-card-menu-item.is-danger { color: #dc2626; }
.yt-card-menu-item.is-danger:hover:not(:disabled) { background: rgba(220,38,38,.08); }

/* The card leaves the shelf rather than blinking out of it. */
.yt-video-card.is-going {
    opacity: 0; transform: scale(.96);
    transition: opacity .25s ease, transform .25s ease;
}
.yt-spin { display: inline-block; animation: yt-spin 1s linear infinite; }
@keyframes yt-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .yt-card-menu-btn, .yt-video-card.is-going { transition: none; }
    .yt-spin { animation: none; }
}
</style>
@endpush
@endonce

<div class="yt-video-card" data-bout-card>
    <a href="{{ $href }}" @if ($shellLink) data-shell-link @endif>
        <div class="yt-video-thumb" onmouseenter="playVideo(this)" onmouseleave="stopVideo(this)">
            @if ($poster)
                <img src="{{ $poster }}" alt="{{ trim(($red['name'] ?? '').' vs '.($blue['name'] ?? '')) }}" loading="lazy"
                     onload="this.classList.add('loaded');this.closest('.yt-video-thumb').classList.add('loaded')">
            @endif

            <!-- ── VS MINI intro ── -->
            <div class="vs-mini vs-mini-run" data-vs-mini>
                <div class="vs-mini-stage">

                    <div class="vs-mini-panel vs-mini-panel-red">
                        <div class="vs-mini-photo" @if (!empty($red['photo'])) style="background-image:url('{{ $red['photo'] }}')" @endif></div>
                        <div class="vs-mini-scrim vs-mini-scrim-red"></div>
                        <div class="vs-mini-fade"></div>
                    </div>
                    <div class="vs-mini-panel vs-mini-panel-blue">
                        <div class="vs-mini-photo vs-mini-photo-rev" @if (!empty($blue['photo'])) style="background-image:url('{{ $blue['photo'] }}')" @endif></div>
                        <div class="vs-mini-scrim vs-mini-scrim-blue"></div>
                        <div class="vs-mini-fade"></div>
                    </div>

                    <div class="vs-mini-divider"></div>

                    <!-- RED -->
                    <div class="vs-mini-info vs-mini-info-red">
                        <div class="vs-mini-tag vs-mini-tag-red">{{ $red['tag'] ?? '' }}</div>
                        <div class="vs-mini-flag-row">
                            @if ($redFlag)<span class="vs-mini-flag fi fi-{{ $redFlag }}"></span>@endif
                            <div class="vs-mini-country vs-mini-country-red vsm-nullable">{{ $redFlag ? strtoupper($redFlag) : '' }}</div>
                        </div>
                        <div class="vs-mini-name vsm-nullable">{{ $red['name'] ?? '' }}</div>
                        <div class="vs-mini-club-row">
                            <div class="vs-mini-logo" @if (!empty($red['club_logo'])) style="background-image:url('{{ $red['club_logo'] }}')" @endif></div>
                            <div class="vs-mini-club vsm-nullable">{{ $red['club'] ?? '' }}</div>
                        </div>
                        <div class="vs-mini-chips-row">
                            <div class="vs-mini-chip-fighter vsm-nullable">{{ $red['score'] ?? '' }}</div>
                        </div>
                    </div>

                    <!-- BLUE -->
                    <div class="vs-mini-info vs-mini-info-blue">
                        <div class="vs-mini-tag vs-mini-tag-blue">{{ $blue['tag'] ?? '' }}</div>
                        <div class="vs-mini-flag-row vs-mini-flag-row-r">
                            @if ($blueFlag)<span class="vs-mini-flag fi fi-{{ $blueFlag }}"></span>@endif
                            <div class="vs-mini-country vs-mini-country-blue vsm-nullable">{{ $blueFlag ? strtoupper($blueFlag) : '' }}</div>
                        </div>
                        <div class="vs-mini-name vsm-nullable">{{ $blue['name'] ?? '' }}</div>
                        <div class="vs-mini-club-row vs-mini-club-row-r">
                            <div class="vs-mini-logo" @if (!empty($blue['club_logo'])) style="background-image:url('{{ $blue['club_logo'] }}')" @endif></div>
                            <div class="vs-mini-club vsm-nullable">{{ $blue['club'] ?? '' }}</div>
                        </div>
                        <div class="vs-mini-chips-row">
                            <div class="vs-mini-chip-fighter vsm-nullable">{{ $blue['score'] ?? '' }}</div>
                        </div>
                    </div>

                    <!-- TOP -->
                    <div class="vs-mini-top">
                        <div class="vs-mini-top-event vsm-nullable">{{ $topEvent }}</div>
                        <div class="vs-mini-top-stage-row">
                            <div class="vs-mini-top-line vs-mini-top-line-l"></div>
                            <div class="vs-mini-top-stage">{{ $topStage }}</div>
                            <div class="vs-mini-top-line vs-mini-top-line-r"></div>
                        </div>
                        <div class="vs-mini-top-weight vsm-nullable">{{ $topWeight }}</div>
                    </div>

                    <!-- CENTER VS -->
                    <div class="vs-mini-center">
                        <div class="vs-mini-word">VS
                            <div class="vs-mini-shine-wrap"><div class="vs-mini-shine"></div></div>
                        </div>
                    </div>

                    <!-- BOTTOM chips -->
                    <div class="vs-mini-bottom">
                        <div class="vs-mini-chip vsm-nullable-chip" data-vsm-empty="{{ filled($arena['match_no'] ?? null) ? '0' : '1' }}">
                            <span class="vs-mini-chip-lbl">{{ __('events.bout_card_match') }}</span><span class="vs-mini-chip-val vsm-nullable">{{ $arena['match_no'] ?? '' }}</span>
                        </div>
                        <div class="vs-mini-diamond vs-mini-diamond-mc"></div>
                        <div class="vs-mini-chip vsm-nullable-chip" data-vsm-empty="{{ filled($arena['court'] ?? null) ? '0' : '1' }}">
                            <span class="vs-mini-chip-lbl">{{ __('events.bout_card_court') }}</span><span class="vs-mini-chip-val vsm-nullable">{{ $arena['court'] ?? '' }}</span>
                        </div>
                        <div class="vs-mini-diamond vs-mini-diamond-cr"></div>
                        <div class="vs-mini-chip vsm-nullable-chip" data-vsm-empty="{{ filled($arena['referee'] ?? null) ? '0' : '1' }}">
                            <span class="vs-mini-chip-lbl">{{ __('events.bout_card_referee') }}</span><span class="vs-mini-chip-val vsm-nullable">{{ $arena['referee'] ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── HOVER-PREVIEW VIDEO ── -->
            @if ($preview)
                <video preload="metadata" playsinline muted loop>
                    <source src="{{ $preview }}" type="application/vnd.apple.mpegurl">
                </video>
            @endif

            <!-- ── SCOREBAR MINI ── -->
            @if ($sbm)
            <div class="sbm" data-sbm data-sbm-state='@json($sbm)'>
                <div class="sbm-feed-scale">
                    <div class="sbm-feed" data-sbm-feed>
                        <div class="sbm-feed-hdr"><span class="sbm-feed-dot"></span><span>{{ __('events.bout_card_live_scoring') }}</span></div>
                        <div class="sbm-feed-list" data-sbm-feed-list></div>
                    </div>
                </div>

                <div class="sbm-scale">
                    <div style="position:absolute;left:0;right:0;bottom:14px;padding:0 26px;display:flex;align-items:stretch;gap:0;height:84px;pointer-events:none">
                      <!-- RED -->
                      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(122,26,22,.94), rgba(180,52,44,.9));border-bottom:3px solid #ff6a5e">
                        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
                          <div style="width:46px;height:46px;flex:none;background:rgba(0,0,0,.32);border:1px solid rgba(255,255,255,.24);background-size:cover;background-position:center;@if (!empty($red['club_logo']))background-image:url('{{ $red['club_logo'] }}')@endif"></div>
                          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px">
                            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                              @if ($redFlag)<span class="fi fi-{{ $redFlag }}" style="width:26px;height:17px;flex:none;box-shadow:0 0 0 1px rgba(255,255,255,.3);background-size:cover !important;background-position:center !important;display:inline-block"></span>@endif
                              <span style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $red['name'] ?? '' }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;min-width:0">
                              <span style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(255,232,228,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $red['club'] ?? '' }}</span>
                            </div>
                          </div>
                          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:5px">
                            <span style="font-size:11px;letter-spacing:.24em;color:rgba(255,236,232,.7)">{{ $red['tag'] ?? '' }}</span>
                            <span data-sbm-red-score style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">0</span>
                          </div>
                        </div>
                      </div>
                      <!-- CENTER -->
                      <div style="width:118px;flex:none;transform:skewX(-9deg);background:rgba(9,9,11,.9);backdrop-filter:blur(8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-bottom:3px solid #2c2a28">
                        <div data-sbm-round style="transform:skewX(9deg);font-size:11px;letter-spacing:.3em;color:#8f8a83;text-transform:uppercase">&nbsp;</div>
                        <div data-sbm-clock style="transform:skewX(9deg);font-size:34px;line-height:1;font-weight:800;color:#efe9e0;font-variant-numeric:tabular-nums">0:00</div>
                        <div style="transform:skewX(9deg);display:flex;gap:4px">
                          <span style="width:20px;height:3px;background:#e8534a"></span>
                          <span style="width:20px;height:3px;background:#e8534a"></span>
                          <span style="width:20px;height:3px;background:#3a3734"></span>
                        </div>
                      </div>
                      <!-- BLUE -->
                      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(30,72,140,.9), rgba(18,44,92,.94));border-bottom:3px solid #6aa6ff">
                        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
                          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-start;gap:5px">
                            <span style="font-size:11px;letter-spacing:.24em;color:rgba(226,238,255,.7)">{{ $blue['tag'] ?? '' }}</span>
                            <span data-sbm-blue-score style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">0</span>
                          </div>
                          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px;align-items:flex-end;text-align:right">
                            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                              <span style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $blue['name'] ?? '' }}</span>
                              @if ($blueFlag)<span class="fi fi-{{ $blueFlag }}" style="width:26px;height:17px;flex:none;box-shadow:0 0 0 1px rgba(255,255,255,.3);background-size:cover !important;background-position:center !important;display:inline-block"></span>@endif
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;min-width:0;justify-content:flex-end">
                              <span style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(226,238,255,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:right">{{ $blue['club'] ?? '' }}</span>
                            </div>
                          </div>
                          <div style="width:46px;height:46px;flex:none;background:rgba(0,0,0,.32);border:1px solid rgba(255,255,255,.24);background-size:cover;background-position:center;@if (!empty($blue['club_logo']))background-image:url('{{ $blue['club_logo'] }}')@endif"></div>
                        </div>
                      </div>
                    </div>
                </div>
            </div>
            @endif

            @if ((int) $duration > 0)
                <span class="yt-video-duration">{{ $mins }}:{{ $secs }}</span>
            @endif
        </div>
    </a>

    <div class="yt-video-info">
        <div class="yt-video-details">
            {{-- The context, then the fixture.

                 The two people are the headline — they are what a coach scans a
                 shelf of thirty tiles for — so they get the title line to
                 themselves. Everything that LOCATES the bout (its phase, its
                 division, its number) is one quiet line above, in the order a
                 person says it: "Round Robin, Group 1, match 10". The event's
                 own name is deliberately still absent; this card is only ever
                 seen inside that event's gallery. --}}
            <div class="yt-bout-head">
            <div class="yt-bout-context">
                @php
                    $context = array_values(array_filter([
                        $topStage,
                        $topWeight,
                        filled($arena['match_no'] ?? null)
                            ? __('events.bout_card_match').' '.$arena['match_no']
                            : null,
                        (int) $angles > 1
                            ? trans_choice('events.bout_card_angles', (int) $angles, ['count' => (int) $angles])
                            : null,
                    ]));
                @endphp
                @foreach ($context as $i => $bit)
                    @if ($i)<span class="yt-bout-dot">·</span>@endif<span>{{ $bit }}</span>
                @endforeach
            </div>
                @if ($deleteUrl)
                    {{-- Outside the <a>, deliberately: a menu nested inside the card's link
                         would open the bout on every press, and stopping that with
                         preventDefault leaves a control that behaves differently from every
                         other link on the page. It is positioned over the thumbnail's corner
                         instead, which is where a reader looks for it. --}}
                    <div class="yt-card-menu" x-data="boutCardMenu(@js($deleteUrl))" @keydown.escape.window="open = false">
                        <button type="button" class="yt-card-menu-btn" :class="open && 'is-open'"
                                @click="open = !open" @click.outside="open = false"
                                :aria-expanded="open ? 'true' : 'false'"
                                aria-label="{{ __('events.bout_card_menu') }}">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>

                        <div class="yt-card-menu-panel" x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                            <button type="button" class="yt-card-menu-item is-danger"
                                    :disabled="busy" @click="destroy()">
                                <i class="bi" :class="busy ? 'bi-arrow-repeat yt-spin' : 'bi-trash3'"></i>
                                <span>{{ __('events.bout_card_delete') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <h3 class="yt-video-title">
                <a href="{{ $href }}" @if ($shellLink) data-shell-link @endif>
                    <span class="yt-corner-red" style="font-weight:600;">@if ($redFlag)<span class="fi fi-{{ $redFlag }}" style="width:16px;height:12px;border-radius:2px;display:inline-block;vertical-align:middle;margin-right:4px;"></span>@endif{{ $red['name'] ?? '' }}</span>
                    <span class="yt-bout-vs"> vs </span>
                    <span class="yt-corner-blue" style="font-weight:600;">@if ($blueFlag)<span class="fi fi-{{ $blueFlag }}" style="width:16px;height:12px;border-radius:2px;display:inline-block;vertical-align:middle;margin-right:4px;"></span>@endif{{ $blue['name'] ?? '' }}</span>
                </a>
            </h3>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
/*
 * The card's menu. Ours, not the template's — kept in its own block above the
 * verbatim copy so the two never get confused for one another.
 */
window.boutCardMenu = function (url) {
    return {
        open: false,
        busy: false,

        async destroy() {
            // Never a native confirm (project rule), and always a confirm: the
            // footage cannot be filmed again, so this is the one control on the
            // page with nothing behind it.
            this.open = false;

            const sure = await window.confirmAction({
                title: @js(__('events.bout_card_delete_title')),
                message: @js(__('events.bout_card_delete_body')),
                type: 'danger',
                confirmText: @js(__('events.bout_card_delete')),
            });

            if (!sure) return;

            this.busy = true;

            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok || data.success === false) {
                    window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                    this.busy = false;

                    return;
                }

                window.showToast && window.showToast('success', data.message || @js(__('events.bout_card_deleted')));

                // In place, no reload (No Page Reload rule). The card fades and
                // the shelf closes over it; the wrapper goes too, or the grid is
                // left holding an empty cell.
                const card = this.$el.closest('[data-bout-card]');

                if (card) {
                    card.classList.add('is-going');
                    setTimeout(() => (card.parentElement?.hasAttribute('x-show')
                        ? card.parentElement : card).remove(), 260);
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                this.busy = false;
            }
        },
    };
};
</script>

<script>
@verbatim
/* ══════════════════════════════════════════════════════════════════════
   HOVER PREVIEW — play/stop the sample video on mouse enter/leave
════════════════════════════════════════════════════════════════════════ */
function playVideo(element) {
    const video = element.querySelector('video');
    if (!video) return;
    video.currentTime = 0;
    video.volume = 0.5;
    video.play().catch(function() {});
    video.classList.add('active');
}
function stopVideo(element) {
    const video = element.querySelector('video');
    if (!video) return;
    video.pause();
    video.currentTime = 0;
    video.classList.remove('active');
}

/* ══════════════════════════════════════════════════════════════════════
   VS-MINI — fit the 1920×1080 canvas to any thumb size, and restart
   the intro animation on mouseleave so the next hover replays it.
════════════════════════════════════════════════════════════════════════ */
(function () {
    function fit(el) {
        const host = el.parentElement;
        if (!host) return;
        const w = host.clientWidth, h = host.clientHeight;
        if (!w || !h) return;
        const portrait = h > w * 1.05;
        el.classList.toggle('vs-mini-portrait', portrait);
        const sw = portrait ? 1080 : 1920;
        const sh = portrait ? 1920 : 1080;
        el.style.setProperty('--vs-mini-scale', Math.min(w / sw, h / sh));
        el.classList.add('vs-mini-fit');
    }
    const ro = ('ResizeObserver' in window) ? new ResizeObserver(entries => {
        for (const e of entries) {
            const el = e.target.querySelector(':scope > .vs-mini');
            if (el) fit(el);
        }
    }) : null;
    function register(el) {
        fit(el);
        if (ro) ro.observe(el.parentElement);
    }
    document.querySelectorAll('.vs-mini').forEach(register);

    const hasHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
    if (hasHover) {
        document.addEventListener('mouseleave', (e) => {
            const thumb = e.target?.classList?.contains('yt-video-thumb') ? e.target : null;
            if (!thumb) return;
            const vsm = thumb.querySelector(':scope > .vs-mini');
            if (!vsm) return;
            vsm.classList.remove('vs-mini-run');
            void vsm.offsetWidth;
            vsm.classList.add('vs-mini-run');
        }, true);
    }
})();

/* ══════════════════════════════════════════════════════════════════════
   SCOREBAR MINI — fit the 1150 px canvas, and sync score/round/clock
   + live scoring feed to the sibling <video>'s currentTime.
════════════════════════════════════════════════════════════════════════ */
(function () {
    function fit(el) {
        const host = el.parentElement;
        if (!host) return;
        const w = host.clientWidth;
        if (!w) return;
        el.style.setProperty('--sbm-scale', (w / 1150));
        el.classList.toggle('sbm-small', w < 340);
    }
    function fmtClock(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
    }
    function currentRound(rounds, t) {
        if (!rounds || !rounds.length) return { n: 1, name: '', start: 0 };
        let cur = rounds[0];
        for (const r of rounds) { if (t >= (r.start || 0)) cur = r; }
        return cur;
    }
    function lastPoint(points, t) {
        if (!points || !points.length) return null;
        let last = null;
        for (const p of points) { if (p.t <= t + 0.001) last = p; else break; }
        return last;
    }
    function pointLabel(p, sport) {
        sport = (sport || '').toLowerCase();
        if (sport.startsWith('taekwondo')) {
            if (p.pts >= 4) return 'Turning body';
            if (p.pts === 3) return 'Head kick';
            if (p.pts === 2) return 'Body kick';
            if (p.pts === 1) return 'Punch';
        }
        if (p.pts === 3) return 'Ippon';
        if (p.pts === 2) return 'Waza-ari';
        if (p.pts === 1) return 'Yuko';
        return p.action || 'Point';
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    const feedColor = (side) => side === 'red' ? '#ff6a5e' : '#6aa6ff';

    function bindLive(sbm) {
        let state = null;
        try { state = JSON.parse(sbm.getAttribute('data-sbm-state') || 'null'); }
        catch (e) { return; }
        if (!state) return;
        const thumb = sbm.parentElement;
        const video = thumb && thumb.querySelector(':scope > video');
        if (!video) return;

        const redEl   = sbm.querySelector('[data-sbm-red-score]');
        const blueEl  = sbm.querySelector('[data-sbm-blue-score]');
        const roundEl = sbm.querySelector('[data-sbm-round]');
        const clockEl = sbm.querySelector('[data-sbm-clock]');
        const feedEl  = sbm.querySelector('[data-sbm-feed-list]');
        let lastR = null, lastB = null, lastRoundTxt = null, lastClk = null, lastFeedSig = null;

        const tick = () => {
            const t = video.currentTime || 0;
            const p = lastPoint(state.points, t);
            const r = currentRound(state.rounds, t);

            const sr = p ? p.sr : 0, sb = p ? p.sb : 0;
            if (sr !== lastR) { if (redEl)  redEl.textContent  = sr; lastR = sr; }
            if (sb !== lastB) { if (blueEl) blueEl.textContent = sb; lastB = sb; }

            const roundTxt = r.name || ('Round ' + r.n);
            if (roundTxt !== lastRoundTxt) { if (roundEl) roundEl.textContent = roundTxt; lastRoundTxt = roundTxt; }

            const clk = fmtClock(Math.max(0, t - (r.start || 0)));
            if (clk !== lastClk) { if (clockEl) clockEl.textContent = clk; lastClk = clk; }

            if (feedEl) {
                const seen = (state.points || []).filter(pt => pt.t <= t + 0.001);
                const last4 = seen.slice(-4).reverse();
                const sig = last4.map(pt => pt.t + ':' + pt.side + ':' + pt.pts).join(',');
                if (sig !== lastFeedSig) {
                    feedEl.innerHTML = last4.map(pt => {
                        const col = feedColor(pt.side);
                        return '<div class="sbm-feed-entry" style="border-color:' + col + '">'
                            +    '<span class="ts">' + fmtClock(pt.t) + '</span>'
                            +    '<span class="name">' + esc(pointLabel(pt, state.sport)) + '</span>'
                            +    '<span class="pts" style="color:' + col + '">+' + pt.pts + '</span>'
                            +  '</div>';
                    }).join('');
                    lastFeedSig = sig;
                }
            }
        };
        video.addEventListener('timeupdate',     tick);
        video.addEventListener('seeked',         tick);
        video.addEventListener('loadedmetadata', tick);
        video.addEventListener('pause',    tick);
        video.addEventListener('emptied',  tick);
        tick();
    }

    const ro = ('ResizeObserver' in window) ? new ResizeObserver(entries => {
        for (const e of entries) {
            const el = e.target.querySelector(':scope > .sbm');
            if (el) fit(el);
        }
    }) : null;
    function register(el) {
        fit(el);
        if (ro) ro.observe(el.parentElement);
        if (!el._sbmBound) { el._sbmBound = true; bindLive(el); }
    }
    document.querySelectorAll('.sbm').forEach(register);
})();
@endverbatim
</script>
@endpush
@endonce
