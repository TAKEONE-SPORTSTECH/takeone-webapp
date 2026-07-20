@extends('layouts.app')

@section('title', $name)
@section('hide-navbar', '1')

@php
    $rtl = $locale === 'ar';
    $img = $activity->picture_url ? asset('storage/'.$activity->picture_url).'?v='.optional($activity->updated_at)->timestamp : null;
    $variants = $activity->variants ?: [];
@endphp

@section('content')
<div class="axm">
    <div class="axm-progress"><span id="axmBar"></span></div>

    {{-- Hero --}}
    <div class="axm-hero">
        @if($img)
            <div class="axm-hero-bg" style="background-image:url('{{ $img }}')"></div>
            <img class="axm-hero-img" src="{{ $img }}" alt="{{ $name }}">
        @else
            <div class="axm-hero-bg axm-hero-bg--plain"></div>
            <div class="axm-hero-icon"><i class="bi {{ $activity->icon ?: 'bi-activity' }}"></i></div>
        @endif
        <div class="axm-hero-scrim"></div>
        <div class="axm-topbar">
            <a href="{{ url()->previous() }}" id="axmBack" onclick="if(history.length>1){event.preventDefault();history.back();}" class="axm-ctrl" title="{{ $rtl ? 'رجوع' : 'Back' }}"><i class="bi bi-arrow-left"></i></a>
            <div class="axm-topbar-right">
                <button type="button" class="axm-ctrl" onclick="axShare()" title="{{ $rtl ? 'مشاركة' : 'Share' }}"><i class="bi bi-share"></i></button>
                <x-qr-code :url="route('activity.show', $activity)" :title="$name" label="" icon="bi-qr-code" buttonClass="axm-ctrl" :size="240" :caption="$rtl ? 'امسح للفتح على الجوال' : 'Scan to open on your phone'" />
            </div>
        </div>
        <div class="axm-hero-cap">
            <div class="axm-eyebrow"><span class="axm-dot"></span> {{ $rtl ? 'دليل تيك ون' : 'THE TAKEONE ALMANAC' }}</div>
            <h1 class="axm-title">{{ $name }}</h1>
        </div>
    </div>

    {{-- Sheet --}}
    <div class="axm-sheet">
        <div class="axm-grip"></div>

        @if(count($variants))
            <div class="axm-chips">@foreach($variants as $v)<span class="axm-chip">{{ $rtl ? ($v['name_ar'] ?? $v['name']) : $v['name'] }}</span>@endforeach</div>
        @endif

        <div class="axm-factsrow">
            <div class="axm-facts" id="axmFacts" hidden>
                <div class="axm-fact"><i class="bi bi-clock-history"></i><b id="axmRead">—</b> <span>{{ $rtl ? 'دقيقة' : 'min' }}</span></div>
                <div class="axm-fact"><i class="bi bi-collection"></i><b id="axmSecs">—</b> <span>{{ $rtl ? 'فصول' : 'chapters' }}</span></div>
                <div class="axm-fact"><i class="bi bi-link-45deg"></i><b id="axmSrc">—</b> <span>{{ $rtl ? 'مصادر' : 'sources' }}</span></div>
            </div>
            @if(count($content) > 1)
                <div class="axm-langtoggle" id="axmLang" role="group" aria-label="Language">
                    @foreach($content as $code => $c)
                        <button type="button" data-lang="{{ $code }}" class="axm-langbtn {{ $code === $defaultLang ? 'active' : '' }}">{{ strtoupper($code) }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- sticky chapter navigator --}}
        <nav class="axm-chapnav" id="axmNav" hidden></nav>

        <div id="axmChapters"></div>

        @guest
            <div class="axm-cta" id="axmCta">
                <div class="axm-cta-ic"><i class="bi bi-stars"></i></div>
                <h3 data-cta-title>{{ $rtl ? 'انضم إلى تيك ون' : 'Join TAKEONE' }}</h3>
                <p data-cta-desc>{{ $rtl ? 'سجّل الدخول أو أنشئ حسابًا مجانيًا للانضمام إلى الأندية ومتابعة تدريبك والمزيد.' : 'Sign in or create a free account to join clubs, track your training and more.' }}</p>
                <div class="axm-cta-btns">
                    <a href="{{ route('login') }}" class="axm-cta-btn axm-cta-btn--primary" data-cta-login>{{ $rtl ? 'تسجيل الدخول' : 'Log in' }}</a>
                    <a href="{{ route('register') }}" class="axm-cta-btn" data-cta-register>{{ $rtl ? 'إنشاء حساب' : 'Create account' }}</a>
                </div>
            </div>
        @endguest

        <p class="axm-foot"><i class="bi bi-collection"></i> {{ $rtl ? 'من دليل تيك ون العالمي' : 'TAKEONE global directory' }}</p>
    </div>
</div>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,900&display=swap" rel="stylesheet">
<style>
    :root { --ax: hsl(250 66% 62%); --ax-deep: hsl(250 68% 52%); --ax-ink: hsl(230 32% 13%); --ax-soft: hsl(230 14% 42%); --ax-line: hsl(230 20% 90%); --ax-display: 'Fraunces', ui-serif, Georgia, serif; }
    .axm { background: hsl(230 24% 97%); min-height: 100vh; }
    .axm-progress { position: fixed; inset-inline: 0; top: 0; height: 3px; z-index: 60; }
    .axm-progress span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--ax), hsl(288 72% 62%)); box-shadow: 0 0 10px hsl(250 70% 60% / .7); transition: width .1s linear; }

    .axm-hero { position: relative; width: 100%; aspect-ratio: 16/9; max-height: 54vh; overflow: hidden; background: #08080f; }
    .axm-hero-bg { position: absolute; inset: -8%; background-size: cover; background-position: center; filter: blur(28px) brightness(.44) saturate(1.15); transform: scale(1.16); animation: axmZoom 24s ease-in-out infinite alternate; }
    .axm-hero-bg--plain { filter: none; animation: none; transform: none; background: radial-gradient(500px 320px at 30% 18%, hsl(250 66% 46%), transparent 70%), linear-gradient(135deg, hsl(252 56% 30%), hsl(250 48% 12%)); }
    .axm-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center; z-index: 1; display: block; animation: axmImgIn 1.05s cubic-bezier(.16,.84,.28,1) both; }
    .axm-hero-icon { position: absolute; inset: 0; display: grid; place-items: center; color: rgba(255,255,255,.9); font-size: 5.5rem; z-index: 1; animation: axmImgIn 1s ease both; }
    .axm-hero-scrim { position: absolute; inset: 0; z-index: 2; background: linear-gradient(to top, rgba(6,6,12,.94), rgba(6,6,12,.35) 45%, rgba(6,6,12,.15) 70%, transparent 100%); }
    .axm-topbar { position: absolute; top: calc(10px + env(safe-area-inset-top)); inset-inline: 12px; z-index: 6; display: flex; align-items: center; justify-content: space-between; }
    .axm-topbar-right { display: flex; align-items: center; gap: .5rem; }
    .axm-factsrow { display: flex; align-items: center; gap: 1rem; margin: .9rem 0 0; }
    .axm-factsrow .axm-facts { margin: 0; }
    .axm-langtoggle { display: inline-flex; align-items: stretch; margin-inline-start: auto; flex-shrink: 0; height: 28px; border-radius: 999px; overflow: hidden; background: #fff; border: 1px solid var(--ax-line); box-shadow: 0 4px 14px -10px rgba(45,35,85,.5); }
    .axm-langbtn { padding: 0 .6rem; color: var(--ax-soft); font-size: .7rem; font-weight: 800; letter-spacing: .02em; background: transparent; border: 0; cursor: pointer; transition: background .2s, color .2s; }
    .axm-langbtn.active { background: var(--ax-deep); color: #fff; }
    .axm-ctrl { width: 42px; height: 42px; border-radius: 999px; display: inline-grid; place-items: center; color: #fff; font-size: 1rem; line-height: 1; text-decoration: none; cursor: pointer; background: rgba(255,255,255,.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.24); }
    .axm-ctrl:active { transform: scale(.9); }

    /* Guest sign-in call to action */
    .axm-cta { margin: 1.4rem 0 .5rem; padding: 1.5rem 1.25rem; border-radius: 20px; text-align: center;
        background: linear-gradient(155deg, hsl(250 72% 96%), hsl(250 66% 92%)); border: 1px solid hsl(250 58% 87%); }
    .axm-cta-ic { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; margin: 0 auto .8rem;
        font-size: 1.5rem; color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); box-shadow: 0 10px 22px -10px hsl(250 60% 50% / .7); }
    .axm-cta h3 { font-family: var(--ax-display); font-weight: 700; font-size: 1.25rem; color: var(--ax-ink); margin: 0; }
    .axm-cta p { font-size: .9rem; line-height: 1.5; color: var(--ax-soft); margin: .4rem 0 1.1rem; }
    .axm-cta-btns { display: flex; flex-direction: column; gap: .6rem; }
    .axm-cta-btn { display: block; padding: .8rem 1rem; border-radius: 13px; font-weight: 700; font-size: .92rem; text-decoration: none;
        background: #fff; color: var(--ax-deep); border: 1px solid hsl(250 58% 85%); transition: transform .15s; }
    .axm-cta-btn:active { transform: scale(.98); }
    .axm-cta-btn--primary { background: linear-gradient(145deg, var(--ax), var(--ax-deep)); color: #fff; border: 0; box-shadow: 0 10px 22px -12px hsl(250 60% 50% / .8); }
    .axm-hero-cap { position: absolute; inset-inline: 0; bottom: 0; z-index: 4; padding: 0 1.25rem 1.4rem; }
    .axm-eyebrow { display: inline-flex; align-items: center; gap: .5rem; color: hsl(250 100% 90%); font-size: .62rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
    .axm-dot { width: 6px; height: 6px; border-radius: 999px; background: var(--ax); box-shadow: 0 0 10px 1px var(--ax); animation: axmPulse 2.4s ease-in-out infinite; }
    .axm-title { font-family: var(--ax-display); font-size: clamp(1.85rem, 8.5vw, 2.6rem); font-weight: 900; letter-spacing: -.02em; line-height: 1.02; color: #fff; margin: .45rem 0 0; text-shadow: 0 4px 30px rgba(0,0,0,.5); }

    .axm-sheet { position: relative; z-index: 3; margin-top: 0; background: hsl(230 24% 97%); padding: .4rem 1rem 3.5rem; min-height: 60vh; box-shadow: 0 -14px 44px -20px rgba(45,35,85,.28); }
    .axm-grip { width: 42px; height: 4px; border-radius: 999px; background: hsl(230 14% 84%); margin: .6rem auto .9rem; }
    .axm-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin: .2rem 0 .3rem; }
    .axm-chip { padding: .34rem .8rem; border-radius: 999px; font-size: .74rem; font-weight: 600; color: var(--ax-deep); background: hsl(250 72% 95%); border: 1px solid hsl(250 60% 88%); }
    .axm-facts { display: flex; gap: 1.1rem; margin: .9rem 0 0; padding: .2rem 0; }
    .axm-fact { display: flex; align-items: center; gap: .35rem; color: var(--ax-soft); font-size: .82rem; }
    .axm-fact i { color: var(--ax-deep); }
    .axm-fact b { font-family: var(--ax-display); font-weight: 700; color: var(--ax-ink); }

    /* sticky chapter nav */
    .axm-chapnav { position: sticky; top: 0; z-index: 20; display: flex; gap: .45rem; margin: 1rem -1rem .25rem; padding: .6rem 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
        background: hsl(230 24% 97% / .9); backdrop-filter: blur(10px); }
    .axm-chapnav::-webkit-scrollbar { display: none; }
    .axm-navchip { flex-shrink: 0; display: inline-flex; align-items: center; gap: .4rem; padding: .42rem .8rem; border-radius: 999px; font-size: .8rem; font-weight: 600; color: var(--ax-soft); background: #fff; border: 1px solid var(--ax-line); cursor: pointer; white-space: nowrap; transition: all .2s; }
    .axm-navchip.active { color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); border-color: transparent; box-shadow: 0 8px 18px -10px hsl(250 60% 50% / .7); }
    .axm-navchip b { font-family: var(--ax-display); }

    /* chapters */
    .axm-chapter { scroll-margin-top: 62px; margin-bottom: 1.1rem; }
    .axm-ch-head { display: flex; align-items: center; gap: .75rem; margin: 1.4rem .15rem .8rem; }
    .axm-ch-num { font-family: var(--ax-display); font-weight: 900; font-size: 2.3rem; line-height: .8; color: transparent; -webkit-text-stroke: 1.3px hsl(250 42% 78%); flex-shrink: 0; }
    .axm-ch-badge { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; font-size: 1.25rem; flex-shrink: 0; background: linear-gradient(145deg, hsl(250 82% 96%), hsl(250 72% 91%)); border: 1px solid hsl(250 58% 87%); }
    .axm-ch-kicker { font-size: .62rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--ax-deep); }
    .axm-ch-title { font-family: var(--ax-display); font-size: 1.28rem; font-weight: 700; color: var(--ax-ink); letter-spacing: -.01em; line-height: 1.12; margin: .05rem 0 0; }

    .axm-card { background: #fff; border: 1px solid var(--ax-line); border-radius: 18px; padding: 1.15rem 1.15rem 1.2rem; box-shadow: 0 18px 40px -34px rgba(45,35,85,.5); }

    .axm-prose { color: hsl(230 16% 26%); font-size: 1rem; line-height: 1.85; }
    .axm-prose > :first-child { margin-top: 0; }
    .axm-prose p { margin: 0 0 1rem; } .axm-prose p:last-child { margin: 0; }
    .axm-prose strong { color: var(--ax-ink); font-weight: 700; }
    .axm-lede { display: block; }
    .axm-dropcap::first-letter { font-family: var(--ax-display); font-weight: 900; float: inline-start; font-size: 3em; line-height: .82; margin: .05em .1em 0 0; color: var(--ax-deep); }
    [dir="rtl"] .axm-dropcap::first-letter { margin: .05em 0 0 .1em; }

    .axm-items { display: grid; gap: .65rem; }
    .axm-item { display: flex; gap: .75rem; padding: .85rem .9rem; border-radius: 14px; background: hsl(230 30% 98%); border: 1px solid var(--ax-line); }
    .axm-item-ic { flex-shrink: 0; width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; font-size: 1.1rem; background: #fff; border: 1px solid var(--ax-line); }
    .axm-item-tx { font-size: .93rem; line-height: 1.5; color: hsl(230 16% 30%); }
    .axm-item-tx strong { color: var(--ax-ink); display: block; margin-bottom: .1rem; }
    .axm-chapter--warn .axm-item { background: hsl(28 90% 97%); border-color: hsl(32 80% 88%); }
    .axm-chapter--warn .axm-item-ic { border-color: hsl(32 80% 85%); }

    .axm-steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }
    .axm-step { counter-increment: step; position: relative; padding: 0 0 1rem; padding-inline-start: 2.9rem; }
    .axm-step:last-child { padding-bottom: 0; }
    .axm-step::before { content: counter(step); position: absolute; inset-inline-start: 0; top: -.05em; width: 1.95rem; height: 1.95rem; border-radius: 999px; display: grid; place-items: center; font-family: var(--ax-display); font-weight: 700; font-size: .85rem; color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); z-index: 1; }
    .axm-step::after { content: ""; position: absolute; inset-inline-start: .95rem; top: 1.95rem; bottom: 0; width: 2px; background: linear-gradient(hsl(250 50% 86%), transparent); }
    .axm-step:last-child::after { display: none; }
    .axm-step-tx { font-size: .96rem; line-height: 1.55; color: hsl(230 16% 28%); padding-top: .12em; }
    .axm-step-em { margin-inline-end: .3rem; }

    .axm-links { display: grid; gap: .5rem; }
    .axm-link { display: flex; align-items: center; gap: .65rem; min-width: 0; padding: .75rem .85rem; border-radius: 13px; background: #fff; border: 1px solid var(--ax-line); color: var(--ax-ink); text-decoration: none; font-weight: 600; font-size: .92rem; }
    .axm-link:active { transform: scale(.99); }
    .axm-link-ic { flex-shrink: 0; width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: hsl(250 72% 95%); color: var(--ax-deep); }
    .axm-link > span:nth-child(2) { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; word-break: break-word; line-height: 1.35; }
    .axm-link .bi-box-arrow-up-right { flex-shrink: 0; color: hsl(230 18% 70%); font-size: .8rem; }

    .axm-foot { text-align: center; color: var(--ax-soft); font-size: .76rem; margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .35rem; }

    .reveal { opacity: 0; transform: translateY(20px); transition: opacity .6s ease, transform .6s cubic-bezier(.16,.84,.28,1); }
    .reveal.in { opacity: 1; transform: none; }
    @keyframes axmZoom { from { transform: scale(1.16); } to { transform: scale(1.3) translateY(-2%); } }
    @keyframes axmImgIn { from { opacity: 0; transform: scale(1.09); } to { opacity: 1; transform: none; } }
    @keyframes axmPulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
    @media (prefers-reduced-motion: reduce) { .axm-hero-bg, .axm-hero-img, .axm-dot, .reveal { animation: none !important; transition: none !important; opacity: 1 !important; transform: none !important; } }
</style>
@endpush

@push('scripts')
<script>
window.axShare = async function () {
    const data = { title: document.title, url: location.href };
    if (navigator.share) { try { await navigator.share(data); return; } catch (e) { if (e && e.name === 'AbortError') return; } }
    try { await navigator.clipboard.writeText(location.href); window.showToast && window.showToast('success', @js($rtl ? 'تم نسخ الرابط' : 'Link copied to clipboard')); }
    catch (e) { window.showToast && window.showToast('info', location.href); }
};
// Hide the back arrow when there's no in-app history (opened via QR / direct link).
if (history.length <= 1) { const b = document.getElementById('axmBack'); if (b) b.style.display = 'none'; }
(function () {
    // All available languages for THIS activity — the EN/AR toggle switches the
    // article content in place (page-only; never changes the saved preference).
    const AX = @js($content);
    const CH = { en: 'Chapter', ar: 'الفصل' };
    // Page-chrome strings per language — kept in sync with the server markup so
    // the on-page toggle updates them too (page-only; preference is untouched).
    const LABELS = @js([
        'en' => ['almanac' => 'THE TAKEONE ALMANAC', 'min' => 'min', 'chapters' => 'chapters', 'sources' => 'sources', 'foot' => 'TAKEONE global directory',
                 'join_title' => 'Join TAKEONE', 'join_desc' => 'Sign in or create a free account to join clubs, track your training and more.', 'login' => 'Log in', 'register' => 'Create account'],
        'ar' => ['almanac' => 'دليل تيك ون', 'min' => 'دقيقة', 'chapters' => 'فصول', 'sources' => 'مصادر', 'foot' => 'من دليل تيك ون العالمي',
                 'join_title' => 'انضم إلى تيك ون', 'join_desc' => 'سجّل الدخول أو أنشئ حسابًا مجانيًا للانضمام إلى الأندية ومتابعة تدريبك والمزيد.', 'login' => 'تسجيل الدخول', 'register' => 'إنشاء حساب'],
    ]);
    const VARIANTS = @js(array_values($variants));
    const out = document.getElementById('axmChapters');
    const nav = document.getElementById('axmNav');
    const titleEl = document.querySelector('.axm-title');
    const eyebrowEl = document.querySelector('.axm-eyebrow');
    const chipsEl = document.querySelector('.axm-chips');
    const footEl = document.querySelector('.axm-foot');
    if (!out) return;

    const splitEmoji = (t) => { const m = t.match(/^\s*([\p{Extended_Pictographic}️‍]+)\s*(.*)$/u); return m ? [m[1], m[2].trim()] : ['', t.trim()]; };
    const popLeadEmoji = (el) => { const f = el.firstChild; if (f && f.nodeType === 3) { const m = f.nodeValue.match(/^\s*([\p{Extended_Pictographic}️‍]+)\s*/u); if (m) { f.nodeValue = f.nodeValue.slice(m[0].length); return m[1]; } } return ''; };
    const typeOf = (e, t) => { if (/💪/.test(e) || /benefit|فوائد/i.test(t)) return 'benefits'; if (/⚠️|⚠/.test(e) || /limitation|قيود/i.test(t)) return 'limits'; if (/📋|📏/.test(e) || /rule|قوانين|قواعد/i.test(t)) return 'rules'; if (/🔗/.test(e) || /resource|مصادر/i.test(t)) return 'links'; return 'prose'; };

    const bar = document.getElementById('axmBar');
    const onScroll = () => { const h = document.documentElement, max = h.scrollHeight - h.clientHeight; if (bar) bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%'; };
    let revObs = null, actObs = null, scrollBound = false;

    function render(code) {
        const data = AX[code]; if (!data) return;
        if (revObs) revObs.disconnect();
        if (actObs) actObs.disconnect();
        out.innerHTML = ''; if (nav) nav.innerHTML = '';
        // Flip the whole page direction to the chosen language (page-only — a
        // fresh load restores it from the saved preference). This re-aligns the
        // facts row, chapter nav, eyebrow and hero to match.
        const dir = data.dir || 'ltr';
        document.documentElement.setAttribute('dir', dir);
        out.setAttribute('dir', dir);
        if (titleEl) { titleEl.textContent = data.name; titleEl.setAttribute('dir', dir); }

        // Localize the page chrome to the chosen language.
        const L = LABELS[code] || LABELS.en || {};
        if (eyebrowEl && L.almanac) eyebrowEl.innerHTML = '<span class="axm-dot"></span> ' + L.almanac;
        const factSpans = document.querySelectorAll('#axmFacts .axm-fact > span');
        if (factSpans[0] && L.min) factSpans[0].textContent = L.min;
        if (factSpans[1] && L.chapters) factSpans[1].textContent = L.chapters;
        if (factSpans[2] && L.sources) factSpans[2].textContent = L.sources;
        if (footEl && L.foot) footEl.innerHTML = '<i class="bi bi-collection"></i> ' + L.foot;
        if (chipsEl) {
            chipsEl.innerHTML = '';
            VARIANTS.forEach(v => { const s = document.createElement('span'); s.className = 'axm-chip'; s.textContent = (code !== 'en' && v['name_' + code]) ? v['name_' + code] : (v.name || ''); chipsEl.appendChild(s); });
        }
        const cta = document.getElementById('axmCta');
        if (cta) {
            const q = (sel) => cta.querySelector(sel);
            if (q('[data-cta-title]') && L.join_title) q('[data-cta-title]').textContent = L.join_title;
            if (q('[data-cta-desc]') && L.join_desc) q('[data-cta-desc]').textContent = L.join_desc;
            if (q('[data-cta-login]') && L.login) q('[data-cta-login]').textContent = L.login;
            if (q('[data-cta-register]') && L.register) q('[data-cta-register]').textContent = L.register;
        }

        const chLabel = CH[code] || 'Chapter';

        const prose = document.createElement('div'); prose.innerHTML = data.description || '';
        const rootEl = prose.querySelector(':scope > div') || prose;
        const nodes = Array.from(rootEl.children);
        const lead = [], sections = []; let cur = null;
        nodes.forEach(n => { if (n.tagName === 'H3') { cur = { title: n.textContent.trim(), body: [] }; sections.push(cur); } else if (cur) cur.body.push(n); else lead.push(n); });

        const frag = document.createDocumentFragment();
        sections.forEach((s, i) => {
            const [emoji, title] = splitEmoji(s.title); const kind = typeOf(emoji, title);
            const ch = document.createElement('section'); ch.className = 'axm-chapter reveal' + (kind === 'limits' ? ' axm-chapter--warn' : ''); ch.id = 'axm-ch-' + i;
            const head = document.createElement('div'); head.className = 'axm-ch-head';
            head.innerHTML = '<div class="axm-ch-num">' + String(i + 1).padStart(2, '0') + '</div><div class="axm-ch-badge">' + (emoji || '§') + '</div><div><div class="axm-ch-kicker"></div><h2 class="axm-ch-title"></h2></div>';
            head.querySelector('.axm-ch-kicker').textContent = chLabel + ' ' + String(i + 1).padStart(2, '0');
            head.querySelector('.axm-ch-title').textContent = title; ch.appendChild(head);

            const card = document.createElement('div'); card.className = 'axm-card';
            const ul = s.body.find(n => n.tagName === 'UL' || n.tagName === 'OL');
            if ((kind === 'benefits' || kind === 'limits') && ul) {
                const g = document.createElement('div'); g.className = 'axm-items';
                Array.from(ul.children).forEach(li => { const e = popLeadEmoji(li); const it = document.createElement('div'); it.className = 'axm-item'; it.innerHTML = '<div class="axm-item-ic">' + (e || (kind === 'limits' ? '⚠️' : '✔')) + '</div><div class="axm-item-tx"></div>'; it.querySelector('.axm-item-tx').innerHTML = li.innerHTML; g.appendChild(it); });
                card.appendChild(g);
            } else if (kind === 'rules' && ul) {
                const ol = document.createElement('ol'); ol.className = 'axm-steps';
                Array.from(ul.children).forEach(li => { const e = popLeadEmoji(li); const st = document.createElement('li'); st.className = 'axm-step'; st.innerHTML = '<div class="axm-step-tx">' + (e ? '<span class="axm-step-em">' + e + '</span>' : '') + li.innerHTML + '</div>'; ol.appendChild(st); });
                card.appendChild(ol);
            } else if (kind === 'links' && ul) {
                const g = document.createElement('div'); g.className = 'axm-links';
                Array.from(ul.children).forEach(li => { const a = li.querySelector('a'); if (!a) return; const l = document.createElement('a'); l.href = a.href; l.target = '_blank'; l.rel = 'noopener noreferrer'; l.className = 'axm-link'; l.innerHTML = '<span class="axm-link-ic"><i class="bi bi-globe2"></i></span><span></span><i class="bi bi-box-arrow-up-right"></i>'; l.querySelector('span:nth-child(2)').textContent = a.textContent; g.appendChild(l); });
                card.appendChild(g);
            } else {
                const b = document.createElement('div'); b.className = 'axm-prose';
                if (i === 0 && lead.length) { const ld = document.createElement('div'); ld.className = 'axm-lede'; lead.forEach(n => ld.appendChild(n.cloneNode(true))); b.appendChild(ld); }
                s.body.forEach(n => b.appendChild(n.cloneNode(true)));
                const fp = b.querySelector('.axm-lede') ? b.querySelector('.axm-lede ~ p') : b.querySelector('p');
                if (dir === 'ltr' && fp && !/^\s*[\p{Extended_Pictographic}]/u.test(fp.textContent)) fp.classList.add('axm-dropcap');
                card.appendChild(b);
            }
            ch.appendChild(card); frag.appendChild(ch);

            if (nav) { const chip = document.createElement('button'); chip.type = 'button'; chip.className = 'axm-navchip'; chip.dataset.target = ch.id; chip.innerHTML = '<b>' + String(i + 1).padStart(2, '0') + '</b> ' + (emoji || ''); chip.title = title;
                chip.addEventListener('click', () => document.getElementById(ch.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })); nav.appendChild(chip); }
        });

        out.appendChild(frag);

        const words = (out.textContent || '').trim().split(/\s+/).length;
        const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        set('axmRead', Math.max(1, Math.round(words / 200))); set('axmSecs', sections.length); set('axmSrc', out.querySelectorAll('.axm-link').length || '—');
        const f = document.getElementById('axmFacts'); if (f) f.hidden = false;
        if (nav) nav.hidden = !sections.length;

        const chapters = Array.from(out.querySelectorAll('.axm-chapter'));
        const chips = nav ? Array.from(nav.children) : [];
        if ('IntersectionObserver' in window) {
            revObs = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObs.unobserve(e.target); } }), { threshold: .05, rootMargin: '0px 0px -5% 0px' });
            chapters.forEach(c => revObs.observe(c));
            actObs = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { chips.forEach(c => { const on = c.dataset.target === e.target.id; c.classList.toggle('active', on); if (on) c.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }); }); } }), { rootMargin: '-40% 0px -55% 0px' });
            chapters.forEach(c => actObs.observe(c));
        } else chapters.forEach(c => c.classList.add('in'));

        if (!scrollBound) { window.addEventListener('scroll', onScroll, { passive: true }); scrollBound = true; }
        onScroll();
    }

    // Language toggle — page-only content switch.
    const langWrap = document.getElementById('axmLang');
    if (langWrap) {
        langWrap.querySelectorAll('[data-lang]').forEach(btn => {
            btn.addEventListener('click', () => {
                const code = btn.dataset.lang; if (!AX[code]) return;
                langWrap.querySelectorAll('[data-lang]').forEach(b => b.classList.toggle('active', b === btn));
                render(code);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    render(@js($defaultLang));
})();
</script>
@endpush
