{{--
    The tablet console's stylesheet — the fonts, the tokens and the thumb-sized
    vocabulary this instrument is built from.

    Extracted so the Blade document and the React island (Phase M3, behind
    `features.react_scoreboard`) draw from ONE file, for the same reason the
    board and the laptop console do: a flag that is meant to be reversible
    mid-event cannot have two stylesheets behind it.

    Included by:
      · bjj/scoreboard/mobile/control.blade.php          the hand-written document
      · bjj/scoreboard/react/control-mobile.blade.php    the island's shell
--}}
{{-- The hall design's two faces, self-hosted beside the package that draws
     with them: a tablet at a mat is on venue wifi, and a console that falls
     back to a system face stops looking like the board it is driving. --}}
<style>
@php
    $faces = [
        ['Anton', 400, 'anton-400'],
        ['Barlow Condensed', 400, 'barlow-condensed-400'],
        ['Barlow Condensed', 600, 'barlow-condensed-600'],
        ['Barlow Condensed', 700, 'barlow-condensed-700'],
    ];
    $ranges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@foreach ($ranges as $subset => $range)
@font-face { font-family: '{{ $family }}'; font-style: normal; font-weight: {{ $weight }}; font-display: swap;
  src: url("{{ route('bjj-screen.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }}; }
@endforeach
@endforeach
:root {
  /* The hall design's palette, under this console's own token names: the two
     surfaces at a mat are one instrument and must not drift into separate
     colour schemes. Names unchanged on purpose — every rule below still reads
     the same variable it always did. */
  --surface-base:#0a0b10; --surface-panel:#111320; --surface-raised:#1f2230;
  --surface-divider:#2a2e40; --surface-overlay:rgba(5,5,7,.72);
  --text-primary:#e8eaf2; --text-secondary:#7d8296; --text-muted:#5c6175; --text-inverse:#0a0b10;
  --corner-blue:#1362d1; --corner-blue-accent:#8ab4ff;
  --corner-white:#e6ebf2; --corner-white-muted:#aab6c6;
  --status-advantage:#ffd666; --status-penalty:#ff3b47; --status-confirmed:#ffe135; --status-review:#c4b5fd;
  --s1:4px; --s2:8px; --s3:12px; --s4:16px; --s5:24px; --s6:32px;
}
*, *::before, *::after { box-sizing:border-box; }
html, body { margin:0; padding:0; height:100%; background:var(--surface-base); color:var(--text-primary);
  font-family:'Barlow Condensed', system-ui, sans-serif; overflow:hidden; touch-action:manipulation; }
.num { font-family:'Anton', Impact, sans-serif; }
[hidden] { display:none !important; }
button { font-family:inherit; color:inherit; cursor:pointer; }

/* ── A slim head: who is on, and how the mat is doing ──────────────────── */
#top { height:56px; display:flex; align-items:center; gap:var(--s3); padding:0 var(--s4);
  border-bottom:1px solid var(--surface-divider); background:var(--surface-panel); }
#top .m { font-size:13px; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#liveBadge { margin-inline-start:auto; display:flex; align-items:center; gap:var(--s2); font-size:13px;
  font-weight:700; letter-spacing:.22em; text-transform:uppercase; }
#liveDot { width:9px; height:9px; border-radius:50%; background:var(--text-muted); }
#liveDot.on { background:var(--status-confirmed); animation:p7blink 1.6s ease-in-out infinite; }
#btnSheet { min-height:40px; padding:0 var(--s3); }

/* ── The two corners, under the thumbs ─────────────────────────────────── */
#corners { height:calc(100% - 56px - 168px); display:grid; grid-template-columns:1fr 1fr;
  gap:var(--s2); padding:var(--s2); min-height:0; }
.side { display:flex; flex-direction:column; gap:var(--s2); min-height:0; overflow:auto;
  background:var(--surface-panel); border:1px solid var(--surface-divider); border-radius:14px; padding:var(--s2); }
.side.blue  { border-top:3px solid var(--corner-blue); }
.side.white { border-top:3px solid var(--corner-white-muted); }

.head { display:flex; align-items:baseline; justify-content:space-between; gap:var(--s2); padding:0 var(--s1); }
.chip { font-size:12px; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--text-secondary); }
.who { font-size:17px; font-weight:700; text-transform:uppercase; overflow:hidden;
  text-overflow:ellipsis; white-space:nowrap; }
.big { font-size:52px; line-height:1; }
.blue .big { color:var(--corner-blue-accent); }
.white .big { color:var(--corner-white); }

.grid { display:grid; grid-template-columns:1fr 1fr; gap:var(--s2); }
.btn { background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  min-height:56px; padding:0 var(--s3); font-size:15px; font-weight:600;
  transition:background .12s, border-color .12s, transform .08s; }
.btn:active:not(:disabled) { transform:scale(.97); border-width:2px; border-color:var(--corner-blue-accent); }
.btn:focus-visible { outline:3px solid var(--status-advantage); outline-offset:0; }
.btn:disabled { opacity:.45; }
/* Scoring is the biggest thing on the screen, because it is what gets pressed
   while nobody is looking at it. */
.btn.score { min-height:88px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; }
.btn.score .v { font-family:'Anton', Impact, sans-serif; font-size:30px; line-height:1; }
.btn.score .l { font-size:12px; font-weight:700; letter-spacing:.10em; text-transform:uppercase;
  color:rgba(255,255,255,.82); text-align:center; line-height:1.15; }
/* Each corner's scoring buttons carry that corner's colour, as they do on the
   laptop console and on the wall: the thumb finds the side before the eye
   reads the label, and a referee holding this is looking at the fight. */
.side.blue .btn.score  { background:var(--corner-blue); border-color:transparent; color:#fff; }
/* The WHITE corner is white — the gi, not a grey stand-in for it. Matched to
   the laptop console (2026-09-06), or the same corner reads as two colours
   depending on which screen an official happens to be holding. */
.side.white .btn.score { background:#fff; border-color:transparent; color:#000; }
.side.white .btn.score .l { color:rgba(0,0,0,.72); }
.btn.adv { min-height:88px; border-color:var(--status-advantage); color:var(--status-advantage); }
.btn.pen { min-height:88px; border-color:var(--status-penalty); color:var(--status-penalty); }
.btn.ok { background:var(--status-confirmed); color:var(--text-inverse); border-color:var(--status-confirmed); font-weight:700; }
.btn.danger { background:transparent; border-color:var(--status-penalty); color:var(--status-penalty); }

.counters { display:flex; gap:var(--s2); }
.counters .c { flex:1; display:flex; align-items:center; justify-content:space-between;
  border:1px solid var(--surface-divider); border-radius:10px; padding:var(--s1) var(--s2); }
.counters .c .l { font-size:12px; font-weight:700; letter-spacing:.18em; }
.counters .c.a .l { color:var(--status-advantage); }
.counters .c.p .l { color:var(--status-penalty); }
.counters .c .n { font-family:'Anton', Impact, sans-serif; font-size:22px; line-height:1; }
.warnDq { font-size:12px; font-weight:700; letter-spacing:.10em; text-transform:uppercase;
  color:var(--status-penalty); text-align:center; }

/* ── The fixed foot: the clock and the transport, thumb-height ──────────── */
#foot { height:168px; border-top:1px solid var(--surface-divider); background:var(--surface-panel);
  padding:var(--s3) var(--s3) calc(var(--s3) + env(safe-area-inset-bottom)); display:flex;
  flex-direction:column; gap:var(--s2); }
#clockRow { display:flex; align-items:center; gap:var(--s3); }
#clockVal { font-size:56px; line-height:1; letter-spacing:.03em; }
#clockVal.warn { color:var(--status-penalty); animation:p7glow 1s ease-in-out infinite; }
#clockState { font-size:13px; font-weight:700; letter-spacing:.28em; text-transform:uppercase; color:var(--text-secondary); }
#transport { display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:var(--s2); }
/* The destructive row is kept a clear gap away from the transport above it. */
#endRow { display:grid; grid-template-columns:1fr 1fr; gap:var(--s2); margin-top:var(--s5); }

/* ── The sheet: everything that is read rather than pressed ─────────────── */
#sheetScrim { position:fixed; inset:0; background:var(--surface-overlay); z-index:40; }
#sheet { position:fixed; inset-inline:0; bottom:0; max-height:88vh; z-index:41;
  background:var(--surface-panel); border-top:1px solid var(--surface-divider);
  border-radius:20px 20px 0 0; display:flex; flex-direction:column; }
#sheetHandle { width:40px; height:4px; border-radius:999px; background:var(--surface-divider);
  margin:var(--s3) auto; flex:0 0 auto; }
#sheetBody { flex:1; overflow-y:auto; padding:0 var(--s4); }
#sheetFoot { flex:0 0 auto; padding:var(--s3) var(--s4) calc(var(--s3) + env(safe-area-inset-bottom)); }
.sheetTabs { display:flex; gap:var(--s2); padding:0 var(--s4) var(--s3); }
.sheetTabs .btn { flex:1; min-height:44px; font-size:14px; }
.sheetTabs .btn.on { border-color:var(--status-advantage); color:var(--status-advantage); }

#queueList { display:flex; flex-direction:column; gap:var(--s2); }
.qItem { display:flex; align-items:center; gap:var(--s3); padding:var(--s3);
  border:1px solid var(--surface-divider); border-radius:12px; background:var(--surface-raised); }
.qItem .n { font-family:'Anton', Impact, sans-serif; font-size:20px; min-width:40px; }
.qItem .who { flex:1; font-size:15px; font-weight:600; white-space:normal; }
.qItem .who .m { color:var(--text-muted); font-size:12px; letter-spacing:.16em; text-transform:uppercase; }

.logRow { display:flex; align-items:center; gap:var(--s2); padding:var(--s2) 0;
  border-bottom:1px solid var(--surface-divider); font-size:14px; }
.logRow.rev { opacity:.5; text-decoration:line-through; }
.logRow .ts { font-family:'Anton', Impact, sans-serif; font-size:16px; min-width:48px; color:var(--text-secondary); }
.logChip { padding:2px 8px; border-radius:8px; font-size:12px; font-weight:700; letter-spacing:.12em;
  text-transform:uppercase; border:1px solid currentColor; }
.logChip.point { color:var(--corner-blue-accent); }
.logChip.advantage { color:var(--status-advantage); }
.logChip.penalty { color:var(--status-penalty); }
.logChip.reverse { color:var(--text-muted); }
.logChip.other { color:var(--text-secondary); }
.logRow .d { flex:1; color:var(--text-secondary); }
.logRow .undo { position:relative; overflow:hidden; min-height:36px; padding:0 var(--s2); }
.logRow .undo .sweep { position:absolute; inset:0; background:var(--status-advantage); opacity:.35;
  transform:scaleX(0); transform-origin:left; }
.logRow .undo.holding .sweep { transform:scaleX(1); transition:transform 1s linear; }

#stallCount { font-family:'Anton', Impact, sans-serif; font-size:40px; color:var(--status-penalty); }

/* ── Modal and toast: the same furniture as the desktop console ─────────── */
#scrim { position:fixed; inset:0; background:var(--surface-overlay); display:grid; place-items:end center; z-index:50; }
#modal { width:100%; background:var(--surface-panel); border-top:1px solid var(--surface-divider);
  border-radius:20px 20px 0 0; padding:var(--s5) var(--s4) calc(var(--s5) + env(safe-area-inset-bottom));
  max-height:88vh; overflow-y:auto; }
#modalTitle { font-size:20px; font-weight:700; }
#modalBody { margin-top:var(--s4); display:flex; flex-direction:column; gap:var(--s3); }
#modalBody label { font-size:13px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); }
#modalBody input { width:100%; background:var(--surface-raised); color:inherit; border:1px solid var(--surface-divider);
  border-radius:10px; padding:14px; font:inherit; font-size:16px; }
#modalActions { display:grid; grid-template-columns:1fr 1fr; gap:var(--s3); margin-top:var(--s5); }
.choiceRow { display:flex; flex-wrap:wrap; gap:var(--s2); }
.choice { border:1px solid var(--surface-divider); border-radius:10px; padding:12px 14px;
  background:var(--surface-raised); font-size:15px; font-weight:600; min-height:48px; }
.choice.on { border-color:var(--status-advantage); color:var(--status-advantage); }

#toast { position:fixed; inset-inline:var(--s4); bottom:calc(180px + env(safe-area-inset-bottom));
  background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  padding:var(--s3); display:flex; align-items:center; justify-content:space-between; gap:var(--s3);
  z-index:60; font-size:15px; }
#toast[hidden] { display:none; }

@keyframes p7blink { 0%,100% { opacity:1; } 50% { opacity:.2; } }
@keyframes p7glow  { 0%,100% { opacity:1; } 50% { opacity:.5; } }
@media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; } }
</style>
