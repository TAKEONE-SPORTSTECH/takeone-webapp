<!DOCTYPE html>
<html lang="en">
<head>
@include('forms::survey.partials.head')
<style>
.m-hero{background:linear-gradient(150deg,#8b7cf0,#8b7cf0b0);color:#fff;position:relative;overflow:hidden;padding:calc(14px + env(safe-area-inset-top)) 18px 56px}
.m-hero .c1{position:absolute;inset-inline-end:-40px;top:-40px;width:176px;height:176px;border-radius:50%;background:rgba(255,255,255,.1)}
.m-hero .c2{position:absolute;inset-inline-end:24px;bottom:32px;width:96px;height:96px;border-radius:50%;background:rgba(255,255,255,.1)}
.m-hero .row{display:flex;align-items:center;justify-content:space-between;gap:10px;position:relative;z-index:2}
.m-hero.art{padding-top:calc(14px + env(safe-area-inset-top))}
/* The artwork is shown WHOLE: a 16:9 strip across the top, never cropped at the
   sides. The controls sit on it; the title sits on the purple band below. */
.m-hero.art .art-img{position:absolute;top:0;left:0;width:100%;aspect-ratio:16/9;height:auto;object-fit:cover;animation:artIn 1.1s ease-out both}
.m-hero.art::after{content:"";position:absolute;top:0;left:0;width:100%;aspect-ratio:16/9;background:linear-gradient(180deg,rgba(15,12,40,.38) 0%,rgba(15,12,40,0) 30%,rgba(15,12,40,0) 82%,rgba(139,124,240,.55) 100%);pointer-events:none}
.m-hero.art>*:not(.art-img){position:relative;z-index:2}
.m-hero.art .row{margin-bottom:calc(56.25vw - 62px - env(safe-area-inset-top))}
@keyframes artIn{from{transform:scale(1.06);opacity:.4}}
.brand{font-weight:800;letter-spacing:.16em;font-size:11.5px;opacity:.95}
.m-hero h1{font-size:22px;font-weight:800;line-height:1.25;margin-top:10px;position:relative;letter-spacing:-.01em}
.m-hero .sub{font-size:13px;opacity:.85;margin-top:6px;position:relative}
/* One screen = a frame exactly as tall as what the person can SEE (--app-h,
   kept by the viewport guard; 100dvh until it has measured). The middle
   scrolls; the button bar is part of the layout, never position:fixed — so
   neither a too-tall app WebView nor a browser toolbar can hide it. */
html,body{height:100%;overflow:hidden}
.frame{height:var(--app-h,100dvh);display:flex;flex-direction:column}
.scroll{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
.wrap{padding:0 16px 28px}
.lift{margin-top:-40px;position:relative;z-index:3}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:16px}
.foot{position:relative;flex-shrink:0;z-index:20;background:rgba(255,255,255,.96);border-top:1px solid var(--line);padding:10px 16px calc(10px + env(safe-area-inset-bottom));display:flex;gap:8px}
.foot .big-btn{flex:1}
.foot .big-btn.ghost{flex:0 0 auto;width:auto;padding-inline:18px}
.q-head{flex-shrink:0;position:relative;z-index:15;background:linear-gradient(150deg,#8b7cf0,#8b7cf0e0);color:#fff;padding:calc(8px + env(safe-area-inset-top)) 14px 12px;overflow:hidden}
.q-head .c1{position:absolute;inset-inline-end:-30px;top:-40px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.1)}
.q-head .row{display:flex;align-items:center;gap:10px;position:relative}
.q-head .ttl{flex:1;min-width:0}
.q-head .ttl b{display:block;font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.q-head .ttl span{font-size:11px;opacity:.85;font-weight:600}
.pbar{height:4px;border-radius:99px;background:rgba(255,255,255,.25);margin-top:10px;overflow:hidden;position:relative}
.pbar i{display:block;height:100%;background:#fff;border-radius:99px;transition:width .3s ease}
.intro{background:var(--accent);border-radius:14px;padding:11px 13px;margin-top:14px}
.intro b{font-size:11px;font-weight:700;color:var(--primary-deep)}
.intro p{font-size:13px;color:var(--ink-2);margin-top:2px}
.qcard{margin-top:16px;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:16px;box-shadow:var(--shadow)}
.banner{background:hsl(38 92% 50% / .1);color:hsl(32 80% 32%);border-radius:14px;padding:10px 12px;font-size:13px;font-weight:600;margin-top:14px}
.toc-h{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);margin:22px 4px 8px}
.ar .toc-h{letter-spacing:0;font-size:12px}
.toc{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden}
.toc-row{display:flex;width:100%;text-align:start;align-items:center;gap:12px;padding:11px 14px;border-top:1px solid var(--line);font-size:13.5px}
.toc-row:first-child{border-top:0}
.toc-row:active{background:var(--muted)}
.toc-n{width:24px;height:24px;border-radius:8px;background:var(--accent);color:var(--primary-deep);display:grid;place-items:center;font-size:11px;font-weight:700;flex:0 0 auto}
.toc-t{flex:1;min-width:0;font-weight:500}
.toc-c{font-size:12px;color:var(--ink-3)}
.flash{background:hsl(0 80% 50% / .08);color:var(--bad);border-radius:14px;padding:10px 12px;font-size:13px;font-weight:700;margin-top:14px}
</style>
</head>
<body>
<div x-data="survey(@js($surveyCfg))">

  {{-- ── Start ───────────────────────────────────────────── --}}
  <template x-if="!started && !done">
    <div class="frame"><div class="scroll" data-scroll>
      <header class="m-hero" :class="cfgCover && 'art'">
        <template x-if="cfgCover"><img class="art-img" :src="cfgCover" alt="" fetchpriority="high"></template>
        <span class="c1" x-show="!cfgCover"></span><span class="c2" x-show="!cfgCover"></span>
        <div class="row">
          <span class="brand" x-text="lang === 'ar' ? 'تيك ون' : 'TAKEONE'"></span>
          <div class="lang" role="group" aria-label="Language"><button type="button" :class="lang==='en' && 'on'" @click="setLang('en')">EN</button><button type="button" :class="lang==='ar' && 'on'" @click="setLang('ar')">عربي</button></div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:24px;position:relative">
          <span class="chip" x-text="lang==='ar' ? 'استبيان' : 'Survey'"></span>
          @if($visit)<span class="chip" x-text="u('visitorMode')"></span>@endif
        </div>
        <h1 x-text="t(copy,'title')"></h1>
        <p class="sub" dir="auto" x-text="alt(copy,'title')"></p>
      </header>
      <main class="wrap">
        <div class="lift card rise">
          <p style="font-size:14px;color:var(--ink-2);line-height:1.6" x-text="t(copy,'description')"></p>
          <div class="facts" style="margin-top:16px">
            <div><b class="num" x-text="total"></b><span x-text="u('questions')"></span></div>
            <div><b class="num" x-text="minutes"></b><span x-text="u('minutes')"></span></div>
            <div><b>2</b><span x-text="u('languages')"></span></div>
          </div>
          <p class="banner" x-show="resumed" x-cloak x-text="u('resume')"></p>
          <button type="button" class="big-btn" style="margin-top:16px" @click="begin()"><span x-text="u('start')"></span>
            <svg class="flip" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg></button>
        </div>
        <p class="toc-h" x-text="lang==='ar' ? 'ما في الاستبيان' : 'What’s inside'"></p>
        <div class="toc rise" style="animation-delay:.06s">
          <template x-for="(p, i) in pages" :key="p.id">
            <button type="button" class="toc-row" @click="goSection(i)"><span class="toc-n num" x-text="i + 1"></span>
              <span class="toc-t" x-text="t(p,'title')"></span>
              <span class="toc-c num" x-text="p.fields.length"></span>
              <svg class="flip" width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="var(--ink-3)" stroke-width="2.2" stroke-linecap="round"><path d="m6 3 5 5-5 5"/></svg></button>
          </template>
        </div>
        @if($alreadyDone)
          <p class="banner">{{ __('shared.forms_show_already_submitted') }}</p>
        @endif
      </main>
    </div></div>
  </template>

  {{-- ── One question per screen ─────────────────────────── --}}
  <template x-if="started && !done && cur">
    <div class="frame">
      <header class="q-head">
        <span class="c1"></span>
        <div class="row">
          <button type="button" class="rc" @click="back()" :aria-label="u('back')"><svg class="flip" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M10 3 5 8l5 5"/></svg></button>
          <div class="ttl"><b x-text="t(curPage,'title')"></b><span class="num" x-text="(step + 1) + ' ' + u('step') + ' ' + total"></span></div>
          <div class="lang"><button type="button" :class="lang==='en' && 'on'" @click="setLang('en')">EN</button><button type="button" :class="lang==='ar' && 'on'" @click="setLang('ar')">ع</button></div>
        </div>
        <div class="pbar"><i :style="'width:' + progress + '%'"></i></div>
      </header>
      <main class="wrap scroll" data-scroll>
        <div class="intro rise" x-show="step === 0 || steps[step - 1].pi !== cur.pi" :key="'i'+cur.pi">
          <b class="num" x-text="u('page') + ' ' + (cur.pi + 1) + ' / ' + pages.length"></b>
          <p x-text="t(curPage,'intro')"></p>
        </div>
        <template x-for="s in [cur]" :key="step">
          <div class="qcard rise" x-data="{ f: s.f }">
            @include('forms::survey.partials.field')
          </div>
        </template>
        <p class="flash" x-show="flashErr" x-cloak x-text="flashErr"></p>
      </main>
      <div class="foot">
        <button type="button" class="big-btn ghost" @click="back()" x-text="u('back')"></button>
        <button type="button" class="big-btn" @click="next()" :disabled="busy"
                x-text="busy ? u('sending') : (step === total - 1 ? u('send') : u('next'))"></button>
      </div>
    </div>
  </template>

  {{-- ── Done ────────────────────────────────────────────── --}}
  <template x-if="done">
    <div class="frame"><div class="scroll" data-scroll>
      <header class="m-hero" style="padding-bottom:80px"><span class="c1"></span><span class="c2"></span>
        <div class="row"><span class="brand" x-text="lang === 'ar' ? 'تيك ون' : 'TAKEONE'"></span></div>
        <h1 x-text="t(copy,'title')"></h1></header>
      <main class="wrap">
        <div class="lift card" style="text-align:center;padding:28px 18px">
          <div class="done-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17 19 7"/></svg></div>
          <h2 style="font-size:22px;font-weight:900;margin-top:18px" x-text="u('doneTitle')"></h2>
          <p style="color:var(--ink-2);margin-top:6px" x-text="doneMsg"></p>
        </div>
      </main>
      </div>
      @if($visit)
        <div class="foot"><button type="button" class="big-btn" @click="again()" x-text="u('another')"></button></div>
      @endif
    </div>
  </template>
</div>
</body>
</html>
