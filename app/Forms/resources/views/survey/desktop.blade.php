<!DOCTYPE html>
<html lang="en">
<head>
@include('forms::survey.partials.head')
<style>
body{font-size:14px}
.d-hero{background:linear-gradient(150deg,#8b7cf0,#8b7cf0b0);color:#fff;position:relative;overflow:hidden}
.d-hero .c1{position:absolute;right:-40px;top:-40px;width:176px;height:176px;border-radius:50%;background:rgba(255,255,255,.1)}
.d-hero .c2{position:absolute;right:24px;bottom:32px;width:96px;height:96px;border-radius:50%;background:rgba(255,255,255,.1)}
.d-hero .in{position:relative;max-width:1180px;margin:0 auto;padding:28px 32px 32px}
.d-hero .row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:18px}
.d-hero.art{background:#1b1d29;display:flex;align-items:flex-end;transition:min-height .4s ease}
.d-hero.art .art-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;animation:artIn 1.1s ease-out both}
.d-hero.art::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(15,12,40,.35) 0%,rgba(15,12,40,.05) 35%,rgba(15,12,40,.78) 100%);pointer-events:none}
.d-hero.art .in{position:relative;z-index:2;width:100%;min-height:inherit;display:flex;flex-direction:column}
.d-hero.art .in .row{margin-bottom:auto}
.d-hero.art h1{font-size:34px;text-shadow:0 2px 18px rgba(0,0,0,.35)}
@keyframes artIn{from{transform:scale(1.05);opacity:.4}}
.brand{font-weight:900;letter-spacing:.14em;font-size:13px}
.d-hero h1{font-size:28px;font-weight:900;line-height:1.2;margin-top:12px}
.d-hero .sub{font-size:14px;opacity:.85;margin-top:6px}
.d-hero .pbar{height:6px;border-radius:99px;background:rgba(255,255,255,.25);margin-top:20px;overflow:hidden;max-width:520px}
.d-hero .pbar i{display:block;height:100%;background:#fff;border-radius:99px;transition:width .3s}
.shell{max-width:1180px;margin:0 auto;padding:28px 32px 80px;display:grid;grid-template-columns:270px 1fr;gap:28px;align-items:start}
.rail{position:sticky;top:20px;background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow);overflow:hidden}
.rail button{display:flex;align-items:center;gap:12px;width:100%;text-align:start;padding:11px 14px;border-top:1px solid var(--line);transition:background .12s}
.rail button:first-child{border-top:0}
.rail button:hover{background:var(--muted)}
.rail button.on{background:hsl(250 65% 65% / .08)}
.rail .n{width:30px;height:30px;border-radius:10px;background:var(--muted);display:grid;place-items:center;font-weight:900;font-size:12px;color:var(--ink-3);flex:0 0 auto}
.rail button.on .n{background:var(--primary);color:#fff}
.rail .tt{flex:1;min-width:0}
.rail .tt b{display:block;font-size:13px;font-weight:700}
.rail .tt span{font-size:11px;color:var(--ink-3)}
.rail .dot{width:8px;height:8px;border-radius:50%;background:var(--border);flex:0 0 auto}
.rail .dot.ok{background:var(--ok)}
.main{min-width:0}
.ph{background:var(--card);border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}
.ph-top{padding:22px 26px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,hsl(250 65% 65% / .06),transparent)}
.ph-top .eb{font-size:11px;font-weight:800;color:var(--primary);letter-spacing:.1em;text-transform:uppercase}
.ar .ph-top .eb{letter-spacing:0;font-size:12px}
.ph-top h2{font-size:22px;font-weight:900;margin-top:4px}
.ph-top p{color:var(--ink-2);margin-top:4px}
.qs>div{padding:22px 26px;border-top:1px solid var(--line)}
.qs>div:first-child{border-top:0}
.qs .qlabel{font-size:17px}
.qs .opts{flex-direction:row;flex-wrap:wrap}
.qs .opt{width:auto;min-height:44px;padding:9px 14px}
.qs .scale{max-width:420px}
.qs .nums{grid-template-columns:repeat(3,1fr);max-width:620px}
.qs .grow .row{display:grid;grid-template-columns:1fr 300px;align-items:center;gap:12px;padding:8px 12px}
.qs .grow .pills{margin-top:0}
.qs .rank{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.nav{display:flex;justify-content:space-between;gap:12px;padding:18px 26px;border-top:1px solid var(--line);background:var(--bg)}
.nav .big-btn{width:auto;padding:13px 26px;font-size:15px}
.start{max-width:760px}
.flash{background:hsl(0 80% 50% / .08);color:var(--bad);border-radius:14px;padding:10px 12px;font-size:13px;font-weight:700;margin:0 26px 16px}
.banner{background:hsl(38 92% 50% / .1);color:hsl(32 80% 32%);border-radius:14px;padding:10px 12px;font-size:13px;font-weight:600;margin-top:14px}
</style>
</head>
<body>
<div x-data="survey(@js($surveyCfg))">
  <header class="d-hero" :class="cfgCover && 'art'" :style="cfgCover && !started && !done ? 'min-height:440px' : ''">
    <template x-if="cfgCover"><img class="art-img" :src="cfgCover" alt="" fetchpriority="high"></template>
    <span class="c1" x-show="!cfgCover"></span><span class="c2" x-show="!cfgCover"></span>
    <div class="in">
      <div class="row">
        <span class="brand" x-text="lang === 'ar' ? 'تيك ون' : 'TAKEONE'"></span>
        <div class="lang" role="group" aria-label="Language"><button type="button" :class="lang==='en' && 'on'" @click="setLang('en')">EN</button><button type="button" :class="lang==='ar' && 'on'" @click="setLang('ar')">عربي</button></div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="chip" x-text="lang==='ar' ? 'استبيان' : 'Survey'"></span>
        @if($visit)<span class="chip" x-text="u('visitorMode')"></span>@endif
        <span class="saved" x-show="started && !done" x-cloak>● <span x-text="u('saved')"></span></span>
      </div>
      <h1 x-text="t(copy,'title')"></h1>
      <p class="sub" dir="auto" x-text="alt(copy,'title')"></p>
      <div class="pbar" x-show="started && !done" x-cloak><i :style="'width:' + pageProgress + '%'"></i></div>
    </div>
  </header>

  <div class="shell">
    <nav class="rail" aria-label="Pages">
      <template x-for="(p, i) in pages" :key="p.id">
        <button type="button" :class="started && page === i && !done && 'on'" @click="goPage(i)">
          <span class="n num" x-text="i + 1"></span>
          <span class="tt"><b x-text="t(p,'title')"></b><span class="num" x-text="p.fields.length + ' ' + u('questions')"></span></span>
          <span class="dot" :class="p.fields.some(f => has(f)) && 'ok'"></span>
        </button>
      </template>
    </nav>

    <main class="main">
      <template x-if="!started && !done">
        <div class="ph start rise">
          <div class="ph-top"><div class="eb" x-text="lang==='ar' ? 'قبل أن تبدأ' : 'Before you start'"></div>
            <h2 x-text="t(copy,'title')"></h2><p x-text="t(copy,'description')"></p></div>
          <div style="padding:22px 26px">
            <div class="facts">
              <div><b class="num" x-text="total"></b><span x-text="u('questions')"></span></div>
              <div><b class="num" x-text="minutes"></b><span x-text="u('minutes')"></span></div>
              <div><b>2</b><span x-text="u('languages')"></span></div>
            </div>
            <p class="banner" x-show="resumed" x-cloak x-text="u('resume')"></p>
            @if($alreadyDone)<p class="banner">{{ __('shared.forms_show_already_submitted') }}</p>@endif
          </div>
          <div class="nav" style="justify-content:flex-end"><button type="button" class="big-btn" @click="begin()" x-text="u('start')"></button></div>
        </div>
      </template>

      <template x-if="started && !done">
        <div class="ph">
          <template x-for="pg in [pages[page]]" :key="page">
            <div class="rise">
              <div class="ph-top"><div class="eb num" x-text="u('page') + ' ' + (page + 1) + ' / ' + pages.length"></div>
                <h2 x-text="t(pg,'title')"></h2><p x-text="t(pg,'intro')"></p></div>
              <div class="qs">
                <template x-for="f in pg.fields" :key="f.key">
                  <div>@include('forms::survey.partials.field')</div>
                </template>
              </div>
            </div>
          </template>
          <p class="flash" x-show="flashErr" x-cloak x-text="flashErr"></p>
          <div class="nav">
            <button type="button" class="big-btn ghost" @click="backPage()" x-text="u('back')"></button>
            <button type="button" class="big-btn" @click="nextPage()" :disabled="busy"
                    x-text="busy ? u('sending') : (page === pages.length - 1 ? u('send') : u('next'))"></button>
          </div>
        </div>
      </template>

      <template x-if="done">
        <div class="ph" style="text-align:center;padding:48px 26px">
          <div class="done-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17 19 7"/></svg></div>
          <h2 style="font-size:24px;font-weight:900;margin-top:18px" x-text="u('doneTitle')"></h2>
          <p style="color:var(--ink-2);margin-top:6px" x-text="doneMsg"></p>
          @if($visit)<div style="max-width:320px;margin:22px auto 0"><button type="button" class="big-btn" @click="again()" x-text="u('another')"></button></div>@endif
        </div>
      </template>
    </main>
  </div>
</div>
</body>
</html>
