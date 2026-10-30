{{-- The paged survey's own stylesheet. Written out rather than taken from the
     Tailwind bundle, which is PREBUILT: classes it never saw are not in it.
     Tokens are the platform's (primary 250 65% 65%, the muted greys). Fonts
     are the self-hosted copies in public/fonts/designer — no font CDN. --}}
<style>
@font-face{font-family:"T1 Inter";src:url("{{ asset('fonts/designer/inter/Inter-Regular.ttf') }}") format("truetype");font-weight:400;font-display:swap}
@font-face{font-family:"T1 Inter";src:url("{{ asset('fonts/designer/inter/Inter-SemiBold.ttf') }}") format("truetype");font-weight:600;font-display:swap}
@font-face{font-family:"T1 Inter";src:url("{{ asset('fonts/designer/inter/Inter-Bold.ttf') }}") format("truetype");font-weight:700 900;font-display:swap}
@font-face{font-family:"T1 Arabic";src:url("{{ asset('fonts/designer/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf') }}") format("truetype");font-weight:400 500;font-display:swap}
@font-face{font-family:"T1 Arabic";src:url("{{ asset('fonts/designer/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.ttf') }}") format("truetype");font-weight:600 900;font-display:swap}
:root{
  --primary:hsl(250 65% 65%);--primary-deep:hsl(250 62% 56%);--accent:hsl(250 60% 94%);
  --bg:hsl(220 15% 97%);--card:#fff;--muted:hsl(220 15% 94%);--border:hsl(220 14% 87%);--line:hsl(220 14% 92%);
  --ink:hsl(232 21% 14%);--ink-2:hsl(229 13% 35%);--ink-3:hsl(229 11% 57%);
  --ok:#16a34a;--warn:#d97706;--bad:#dc2626;
  --f:"T1 Inter",Inter,system-ui,-apple-system,"Segoe UI",sans-serif;--f-ar:"T1 Arabic","IBM Plex Sans Arabic",Tahoma,sans-serif;
  --shadow:0 1px 2px rgba(27,29,41,.05),0 8px 24px rgba(27,29,41,.07);
  color-scheme:only light;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
[x-cloak]{display:none!important}
html,body{margin:0;background:var(--bg);color:var(--ink)}
body{font-family:var(--f);font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
body.ar{font-family:var(--f-ar)}
button,input,textarea{font:inherit;color:inherit}
button{background:none;border:0;cursor:pointer;padding:0}
h1,h2,h3{margin:0;text-wrap:balance}
p{margin:0}
:focus-visible{outline:2px solid var(--primary);outline-offset:2px}
.num{font-variant-numeric:tabular-nums}

/* the round 40px control of every hero band */
.rc{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);display:grid;place-items:center;color:#fff;flex:0 0 auto;transition:background .15s,transform .12s}
.rc:hover{background:rgba(255,255,255,.25)}.rc:active{transform:scale(.92)}
.rc svg{width:18px;height:18px}
.ar .flip{transform:scaleX(-1)}
.chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;background:#fff;color:var(--primary-deep);box-shadow:0 2px 8px rgba(23,19,58,.18)}
.ar .chip{letter-spacing:0;font-size:11px}
.lang{display:flex;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:3px}
.lang button{color:#fff;font-size:11.5px;font-weight:700;padding:5px 11px;border-radius:999px;min-width:40px}
.lang button.on{background:#fff;color:var(--primary-deep)}
.lang button:last-child{font-family:var(--f-ar)}

/* question */
.qlabel{font-size:17px;font-weight:700;line-height:1.4;letter-spacing:-.01em}
.qother{font-size:12.5px;color:var(--ink-3);margin-top:3px}
[dir="ltr"] .qother,[dir="ltr"] .sub{text-align:left}
[dir="rtl"] .qother,[dir="rtl"] .sub{text-align:right}
.qhelp{font-size:13px;color:var(--ink-2);margin-top:6px}
.req{color:var(--bad)}
.hint{font-size:11.5px;font-weight:600;color:var(--primary);margin-top:8px}
.err{margin-top:10px;font-size:13px;font-weight:600;color:var(--bad)}
.opts{display:flex;flex-direction:column;gap:6px;margin-top:12px}
.opt{display:flex;align-items:center;gap:12px;width:100%;text-align:start;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:9px 12px;font-weight:500;font-size:14px;min-height:42px;transition:border-color .12s,background .12s,transform .1s}
.opt:active{transform:scale(.985)}
.box{width:18px;height:18px;border-radius:6px;border:2px solid var(--border);flex:0 0 auto;display:grid;place-items:center;transition:all .12s}
.box.round{border-radius:50%}
.box svg{width:11px;height:11px;color:#fff;opacity:0}
.on>.box{background:var(--primary);border-color:var(--primary)}
.on>.box svg{opacity:1}
.opt.on{border-color:var(--primary);background:hsl(250 65% 65% / .07)}
.inp{width:100%;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:10px 12px;font-size:16px;outline:none;transition:border-color .12s,box-shadow .12s}
.inp:focus{border-color:var(--primary);box-shadow:0 0 0 4px hsl(250 65% 65% / .15)}
textarea.inp{resize:vertical;min-height:88px;line-height:1.55}
.small{font-size:12px;color:var(--ink-3);font-weight:600;margin:12px 0 6px;display:block}
.scale{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:14px}
.scale button{height:44px;border-radius:12px;border:1.5px solid var(--border);background:var(--card);font-size:15px;font-weight:700;color:var(--ink-2);transition:all .12s}
.scale button.on{background:var(--primary);border-color:var(--primary);color:#fff;box-shadow:0 6px 14px hsl(250 65% 65% / .3)}
.ends{display:flex;justify-content:space-between;gap:12px;font-size:11.5px;font-weight:600;color:var(--ink-3);margin-top:8px}
.nums{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:14px}
.nums label{background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:8px 11px;display:flex;flex-direction:column;gap:2px}
.nums span{font-size:11.5px;font-weight:600;color:var(--ink-3)}
.nums input{border:0;outline:none;font-size:18px;font-weight:700;width:100%;background:transparent;padding:0}
.nums label:focus-within{border-color:var(--primary)}
.check{display:flex;gap:12px;align-items:flex-start;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:12px;margin-top:12px;text-align:start;width:100%;font-size:13.5px;line-height:1.5}
.check.on{border-color:var(--primary);background:hsl(250 65% 65% / .06)}
.grow{display:flex;flex-direction:column;gap:6px;margin-top:12px}
.grow .row{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:9px 10px;transition:border-color .12s}
.grow .row.miss{border-color:var(--bad)}
.grow .row b{display:block;font-size:13px;font-weight:600}
.grow .pills{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;margin-top:7px;background:var(--muted);border-radius:10px;padding:3px}
.grow .pills button{font-size:12px;font-weight:600;padding:6px 0;border-radius:8px;background:transparent;color:var(--ink-2);transition:all .12s}
.grow .pills button.on.c0{background:var(--primary);color:#fff}
.grow .pills button.on.c1{background:hsl(250 70% 88%);color:var(--primary-deep)}
.grow .pills button.on.c2{background:var(--ink-2);color:#fff}
.gcount{display:flex;align-items:center;gap:10px;margin-top:12px;font-size:12px;font-weight:700;color:var(--ink-3)}
.gcount .bar{flex:1;height:6px;border-radius:99px;background:var(--muted);overflow:hidden}
.gcount .bar i{display:block;height:100%;background:var(--primary);border-radius:99px;transition:width .2s}
.rank{display:flex;flex-direction:column;gap:6px;margin-top:12px}
.rank button{display:flex;align-items:center;gap:12px;width:100%;text-align:start;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:8px 10px;font-weight:500;font-size:13.5px}
.rank .pos{width:26px;height:26px;border-radius:8px;font-size:12px;background:var(--muted);display:grid;place-items:center;font-weight:900;color:var(--ink-3);flex:0 0 auto}
.rank button.on{border-color:var(--primary)}
.rank button.on .pos{background:var(--primary);color:#fff}
.rank button:disabled{opacity:.45;cursor:default}
.notebox{margin-top:12px;background:hsl(38 92% 50% / .07);border:1.5px dashed hsl(38 80% 55% / .5);border-radius:14px;padding:12px}
.notebox .who{font-size:11px;font-weight:800;color:var(--warn);text-transform:uppercase;letter-spacing:.06em}
.ar .notebox .who{letter-spacing:0;font-size:12px}
.notebox p{font-size:13px;color:var(--ink-2);margin-top:4px}
.notebox textarea{margin-top:8px;background:#fff}
.privtag{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:800;color:var(--warn);background:hsl(38 92% 50% / .12);padding:3px 8px;border-radius:999px}

/* start & done */
.big-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 16px;border-radius:12px;background:var(--primary);color:#fff;font-weight:700;font-size:15px;box-shadow:0 6px 16px hsl(250 65% 65% / .3);transition:transform .12s,opacity .12s}
.big-btn:active{transform:scale(.98)}
.big-btn:disabled{opacity:.6}
.big-btn.ghost{background:var(--muted);color:var(--ink-2);box-shadow:none}
.facts{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.facts div{background:var(--bg);border-radius:12px;padding:10px;text-align:center}
.facts b{display:block;font-size:17px;font-weight:800}
.facts span{font-size:11px;color:var(--ink-3);font-weight:600}
.done-mark{width:64px;height:64px;border-radius:20px;background:var(--ok);display:grid;place-items:center;color:#fff;margin:0 auto;animation:pop .5s cubic-bezier(.2,1.4,.4,1)}
.done-mark svg{width:30px;height:30px;stroke-dasharray:40;stroke-dashoffset:40;animation:draw .5s .2s forwards}
@keyframes pop{from{transform:scale(.3);opacity:0}}
@keyframes draw{to{stroke-dashoffset:0}}
@keyframes rise{from{opacity:0;transform:translateY(14px)}}
.rise{animation:rise .32s ease-out both}
.saved{font-size:11px;font-weight:600;color:rgba(255,255,255,.85)}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
