@extends('layouts.app')

@section('title', $name)
@section('hide-navbar', '1')

@php
    $rtl = $locale === 'ar';
    $img = $activity->picture_url ? asset('storage/'.$activity->picture_url).'?v='.optional($activity->updated_at)->timestamp : null;
    $variants = $activity->variants ?: [];
@endphp

@section('content')
<div class="ax">
    <div class="ax-progress"><span id="axBar"></span></div>

    <div class="ax-mini" id="axMini">
        <a href="{{ url()->previous() }}" id="axBackMini" onclick="if(history.length>1){event.preventDefault();history.back();}" class="ax-mini-back"><i class="bi bi-arrow-left"></i></a>
        <span class="ax-mini-title">{{ $name }}</span>
        <div class="ax-mini-actions">
            @if(count($content) > 1)
                <div class="ax-langtoggle" id="axLangMini" role="group" aria-label="Language">
                    @foreach($content as $code => $c)
                        <button type="button" data-lang="{{ $code }}" class="ax-langbtn {{ $code === $defaultLang ? 'active' : '' }}">{{ strtoupper($code) }}</button>
                    @endforeach
                </div>
            @endif
            <button type="button" class="ax-mini-btn" onclick="axShare()" title="{{ $rtl ? 'مشاركة' : 'Share' }}"><i class="bi bi-share"></i></button>
            <x-qr-code :url="route('activity.show', $activity)" :title="$name" label="" icon="bi-qr-code" buttonClass="ax-mini-btn" :size="248" :caption="$rtl ? 'امسح للفتح على الجوال' : 'Scan to open on your phone'" />
        </div>
    </div>

    {{-- ===== Hero ===== --}}
    <header class="ax-hero">
        @if($img)
            <div class="ax-hero-bg" style="background-image:url('{{ $img }}')"></div>
            <div class="ax-hero-frame"><img class="ax-hero-img" src="{{ $img }}" alt="{{ $name }}"></div>
        @else
            <div class="ax-hero-bg ax-hero-bg--plain"></div>
            <div class="ax-hero-icon"><i class="bi {{ $activity->icon ?: 'bi-activity' }}"></i></div>
        @endif
        <div class="ax-hero-grain"></div>
        <div class="ax-hero-scrim"></div>

        <div class="ax-topbar">
            <a href="{{ url()->previous() }}" id="axBack" onclick="if(history.length>1){event.preventDefault();history.back();}" class="ax-ctrl" title="{{ $rtl ? 'رجوع' : 'Back' }}"><i class="bi bi-arrow-left"></i></a>
            <div class="ax-topbar-right">
                <button type="button" class="ax-ctrl" onclick="axShare()" title="{{ $rtl ? 'مشاركة' : 'Share' }}"><i class="bi bi-share"></i></button>
                <x-qr-code :url="route('activity.show', $activity)" :title="$name" label="" icon="bi-qr-code" buttonClass="ax-ctrl" :size="248" :caption="$rtl ? 'امسح للفتح على الجوال' : 'Scan to open on your phone'" />
            </div>
        </div>

        <div class="ax-hero-inner">
            <div class="ax-eyebrow ax-rise" style="--d:.05s"><span class="ax-dot"></span> {{ $rtl ? 'دليل تيك ون للأنشطة' : 'THE TAKEONE ALMANAC' }}</div>
            <h1 class="ax-title ax-rise" style="--d:.13s">{{ $name }}</h1>
            @if(count($variants))
                <div class="ax-chips ax-rise" style="--d:.22s">
                    @foreach($variants as $v)<span class="ax-chip">{{ $rtl ? ($v['name_ar'] ?? $v['name']) : $v['name'] }}</span>@endforeach
                </div>
            @endif
            <div class="ax-scrollcue ax-rise" style="--d:.4s"><span></span></div>
        </div>
    </header>

    {{-- ===== Body ===== --}}
    <div class="ax-shell">
        <aside class="ax-rail">
            <div class="ax-facts ax-rise" style="--d:.1s" id="axFacts" hidden>
                <div class="ax-fact"><i class="bi bi-clock-history"></i><span><b id="axReadTime">—</b> {{ $rtl ? 'دقيقة قراءة' : 'min read' }}</span></div>
                <div class="ax-fact"><i class="bi bi-collection"></i><span><b id="axSecCount">—</b> {{ $rtl ? 'فصول' : 'chapters' }}</span></div>
                @if(count($variants))<div class="ax-fact"><i class="bi bi-award"></i><span><b id="axStyleCount">{{ count($variants) }}</b> {{ $rtl ? 'أنماط' : 'styles' }}</span></div>@endif
                <div class="ax-fact"><i class="bi bi-link-45deg"></i><span><b id="axSrcCount">—</b> {{ $rtl ? 'مصادر' : 'sources' }}</span></div>
            </div>
            <nav class="ax-index" id="axIndex" hidden>
                <div class="ax-index-label">{{ $rtl ? 'الفهرس' : 'CONTENTS' }}</div>
                <ol id="axIndexList"></ol>
            </nav>
        </aside>

        <main class="ax-article">
            @if(count($content) > 1)
                <div class="ax-langbar">
                    <div class="ax-langtoggle ax-rise" style="--d:.05s" id="axLang" role="group" aria-label="Language">
                        @foreach($content as $code => $c)
                            <button type="button" data-lang="{{ $code }}" class="ax-langbtn {{ $code === $defaultLang ? 'active' : '' }}">{{ strtoupper($code) }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div id="axChapters"></div>

            @guest
                <div class="ax-cta" id="axCta">
                    <div class="ax-cta-ic"><i class="bi bi-stars"></i></div>
                    <div class="ax-cta-body">
                        <h3 data-cta-title>{{ $rtl ? 'انضم إلى تيك ون' : 'Join TAKEONE' }}</h3>
                        <p data-cta-desc>{{ $rtl ? 'سجّل الدخول أو أنشئ حسابًا مجانيًا للانضمام إلى الأندية ومتابعة تدريبك والمزيد.' : 'Sign in or create a free account to join clubs, track your training and more.' }}</p>
                    </div>
                    <div class="ax-cta-btns">
                        <a href="{{ route('login') }}" class="ax-cta-btn ax-cta-btn--primary" data-cta-login>{{ $rtl ? 'تسجيل الدخول' : 'Log in' }}</a>
                        <a href="{{ route('register') }}" class="ax-cta-btn" data-cta-register>{{ $rtl ? 'إنشاء حساب' : 'Create account' }}</a>
                    </div>
                </div>
            @endguest

            <p class="ax-foot"><i class="bi bi-collection"></i> {{ $rtl ? 'من دليل الأنشطة العالمي في تيك ون' : 'From the TAKEONE global activity directory' }}</p>
        </main>
    </div>
</div>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,900&display=swap" rel="stylesheet">
<style>
    :root { --ax: hsl(250 66% 62%); --ax-deep: hsl(250 68% 52%); --ax-ink: hsl(230 32% 13%); --ax-soft: hsl(230 14% 42%);
            --ax-line: hsl(230 20% 90%); --ax-display: 'Fraunces', ui-serif, Georgia, serif; }
    .ax { --pad: clamp(1.25rem, 5vw, 4rem);
        background: radial-gradient(1300px 700px at 50% -280px, hsl(250 62% 95%), transparent 60%), hsl(230 24% 97%);
        min-height: 100vh; padding-bottom: 5rem; }

    .ax-progress { position: fixed; inset-inline: 0; top: 0; height: 3px; z-index: 60; }
    .ax-progress span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--ax), hsl(288 72% 62%)); box-shadow: 0 0 12px hsl(250 70% 60% / .7); transition: width .1s linear; }

    .ax-mini { position: fixed; inset-inline: 0; top: 0; z-index: 55; height: 60px; display: flex; align-items: center; gap: .75rem;
        padding: 0 var(--pad); background: hsl(230 34% 99% / .82); backdrop-filter: blur(16px); border-bottom: 1px solid var(--ax-line);
        transform: translateY(-102%); transition: transform .35s cubic-bezier(.16,.84,.28,1); }
    .ax-mini.show { transform: none; }
    .ax-mini-back { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; color: var(--ax-ink); background: hsl(230 22% 94%); text-decoration: none; flex-shrink: 0; }
    .ax-mini-title { font-family: var(--ax-display); font-weight: 700; color: var(--ax-ink); font-size: 1.06rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .ax-mini-actions { display: flex; gap: .4rem; flex-shrink: 0; }
    .ax-mini-btn { width: 36px; height: 36px; border-radius: 11px; display: inline-grid; place-items: center; color: var(--ax-ink); background: hsl(230 22% 94%); border: 0; cursor: pointer; }
    .ax-mini-btn:hover { background: hsl(250 60% 92%); color: var(--ax-deep); }

    /* Language toggle (page-only content switch) */
    .ax-langtoggle { display: inline-flex; align-items: stretch; height: 36px; border-radius: 999px; overflow: hidden; background: hsl(230 22% 94%); border: 1px solid var(--ax-line); }
    .ax-langbtn { padding: 0 .8rem; color: var(--ax-soft); font-size: .82rem; font-weight: 700; letter-spacing: .02em; background: transparent; border: 0; cursor: pointer; transition: background .2s, color .2s; }
    .ax-langbtn.active { background: var(--ax-deep); color: #fff; }
    .ax-langbar { display: flex; justify-content: flex-end; margin-bottom: 1.25rem; }

    /* ── Hero ── */
    .ax-hero { position: relative; width: 100%; aspect-ratio: 16/9; max-height: 86vh; min-height: 340px; overflow: hidden; background: #08080f; }
    .ax-hero-bg { position: absolute; inset: -8%; background-size: cover; background-position: center; filter: blur(40px) brightness(.46) saturate(1.25); transform: scale(1.14); animation: axZoom 28s ease-in-out infinite alternate; }
    .ax-hero-bg--plain { filter: none; animation: none; transform: none; background: radial-gradient(760px 440px at 28% 18%, hsl(250 66% 44%), transparent 70%), linear-gradient(135deg, hsl(252 56% 30%), hsl(250 48% 12%)); }
    .ax-hero-frame { position: absolute; inset: 0; z-index: 1; }
    .ax-hero-img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; animation: axImgIn 1.3s cubic-bezier(.16,.84,.28,1) both; }
    .ax-hero-icon { position: absolute; inset: 0; display: grid; place-items: center; z-index: 1; color: rgba(255,255,255,.9); font-size: 9rem; animation: axImgIn 1.1s ease both; }
    .ax-hero-scrim { position: absolute; inset: 0; z-index: 2; pointer-events: none; background: linear-gradient(to top, rgba(6,6,12,.96), rgba(6,6,12,.5) 24%, rgba(6,6,12,.05) 50%, transparent 68%), radial-gradient(120% 80% at 50% 120%, rgba(0,0,0,.5), transparent 60%); }
    .ax-hero-grain { position: absolute; inset: 0; z-index: 2; pointer-events: none; opacity: .45; mix-blend-mode: overlay; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.5'/%3E%3C/svg%3E"); }

    .ax-topbar { position: absolute; top: 0; inset-inline: 0; z-index: 6; display: flex; align-items: center; justify-content: space-between; padding: clamp(14px, 2vw, 22px) var(--pad); animation: axFade .8s ease both; }
    .ax-topbar-right { display: flex; align-items: center; gap: .6rem; }
    .ax-ctrl { width: 46px; height: 46px; border-radius: 999px; display: inline-grid; place-items: center; color: #fff; font-size: 1.05rem; line-height: 1; text-decoration: none; background: rgba(255,255,255,.13); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.22); cursor: pointer; transition: transform .25s, background .25s; }
    .ax-ctrl:hover { background: rgba(255,255,255,.28); transform: translateY(-2px) scale(1.06); }

    /* Guest sign-in call to action */
    .ax-cta { margin-top: 2.5rem; padding: clamp(1.5rem, 3vw, 2.25rem); border-radius: 22px; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
        background: linear-gradient(155deg, hsl(250 72% 96%), hsl(250 66% 92%)); border: 1px solid hsl(250 58% 87%); }
    .ax-cta-ic { width: 60px; height: 60px; border-radius: 18px; display: grid; place-items: center; flex-shrink: 0; font-size: 1.75rem; color: #fff;
        background: linear-gradient(145deg, var(--ax), var(--ax-deep)); box-shadow: 0 12px 26px -12px hsl(250 60% 50% / .7); }
    .ax-cta-body { flex: 1 1 240px; min-width: 0; }
    .ax-cta-body h3 { font-family: var(--ax-display); font-weight: 700; font-size: 1.35rem; color: var(--ax-ink); margin: 0; }
    .ax-cta-body p { font-size: .95rem; line-height: 1.55; color: var(--ax-soft); margin: .35rem 0 0; }
    .ax-cta-btns { display: flex; gap: .6rem; flex-shrink: 0; flex-wrap: wrap; }
    .ax-cta-btn { display: inline-flex; align-items: center; padding: .7rem 1.3rem; border-radius: 13px; font-weight: 700; font-size: .92rem; text-decoration: none;
        background: #fff; color: var(--ax-deep); border: 1px solid hsl(250 58% 85%); transition: transform .15s, box-shadow .2s; }
    .ax-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -16px rgba(45,35,85,.5); }
    .ax-cta-btn--primary { background: linear-gradient(145deg, var(--ax), var(--ax-deep)); color: #fff; border: 0; box-shadow: 0 12px 26px -14px hsl(250 60% 50% / .8); }

    .ax-hero-inner { position: absolute; inset-inline: 0; bottom: 0; z-index: 4; padding: 0 var(--pad) clamp(1.75rem, 4vw, 3.25rem); max-width: 1200px; margin: 0 auto; }
    .ax-eyebrow { display: inline-flex; align-items: center; gap: .55rem; color: hsl(250 100% 90%); font-size: .74rem; font-weight: 700; letter-spacing: .26em; text-transform: uppercase; margin-bottom: .85rem; }
    .ax-dot { width: 7px; height: 7px; border-radius: 999px; background: var(--ax); box-shadow: 0 0 14px 2px var(--ax); animation: axPulse 2.4s ease-in-out infinite; }
    .ax-title { font-family: var(--ax-display); color: #fff; font-weight: 900; letter-spacing: -.015em; line-height: .96; font-size: clamp(2.6rem, 6.2vw, 5.6rem); text-shadow: 0 6px 50px rgba(0,0,0,.55); margin: 0; }
    .ax-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.15rem; }
    .ax-chip { padding: .42rem .95rem; border-radius: 999px; font-size: .8rem; font-weight: 600; color: #fff; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24); backdrop-filter: blur(6px); }
    .ax-scrollcue { width: 26px; height: 42px; border-radius: 999px; border: 2px solid rgba(255,255,255,.4); margin-top: 1.5rem; position: relative; }
    .ax-scrollcue span { position: absolute; top: 8px; left: 50%; width: 4px; height: 8px; border-radius: 999px; background: #fff; transform: translateX(-50%); animation: axCue 1.7s ease-in-out infinite; }

    /* ── Shell / rail ── */
    .ax-shell { max-width: 1200px; margin: 2.25rem auto 0; padding: 0 var(--pad); display: grid; grid-template-columns: 1fr; gap: 2.75rem; }
    @media (min-width: 1040px) { .ax-shell { grid-template-columns: 250px minmax(0,1fr); align-items: start; } }
    .ax-rail { display: none; position: sticky; top: 88px; align-self: start; }
    @media (min-width: 1040px) { .ax-rail { display: block; } }

    .ax-facts { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.75rem; }
    .ax-fact { display: flex; align-items: center; gap: .6rem; color: var(--ax-soft); font-size: .86rem; }
    .ax-fact i { color: var(--ax-deep); font-size: 1.05rem; width: 1.2rem; text-align: center; }
    .ax-fact b { font-family: var(--ax-display); font-weight: 700; color: var(--ax-ink); }

    .ax-index-label { font-size: .68rem; font-weight: 800; letter-spacing: .2em; color: var(--ax-soft); margin-bottom: .9rem; }
    #axIndexList { list-style: none; margin: 0; padding: 0; counter-reset: idx; }
    .ax-idx { counter-increment: idx; }
    .ax-idx a { display: flex; align-items: baseline; gap: .7rem; padding: .5rem 0; color: var(--ax-soft); font-size: .9rem; font-weight: 600; text-decoration: none; line-height: 1.3; transition: color .2s; }
    .ax-idx a::before { content: counter(idx, decimal-leading-zero); font-family: var(--ax-display); font-size: .78rem; font-weight: 700; color: hsl(230 18% 78%); flex-shrink: 0; transition: color .2s; width: 1.5rem; }
    .ax-idx a:hover { color: var(--ax-ink); }
    .ax-idx.active a { color: var(--ax-deep); }
    .ax-idx.active a::before { color: var(--ax-deep); }

    /* ── Chapters ── */
    .ax-article { min-width: 0; }
    .ax-chapter { position: relative; margin-bottom: 1.4rem; }
    .ax-chapter { scroll-margin-top: 88px; }
    .ax-ch-head { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.15rem; }
    .ax-ch-num { font-family: var(--ax-display); font-weight: 900; font-size: clamp(2.4rem, 5vw, 3.4rem); line-height: .8; color: transparent;
        -webkit-text-stroke: 1.5px hsl(250 40% 78%); letter-spacing: -.03em; flex-shrink: 0; }
    .ax-ch-badge { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; font-size: 1.55rem; flex-shrink: 0;
        background: linear-gradient(145deg, hsl(250 82% 96%), hsl(250 72% 91%)); border: 1px solid hsl(250 58% 87%); box-shadow: inset 0 1px 0 #fff, 0 8px 20px -12px hsl(250 60% 55% / .5); }
    .ax-ch-meta { min-width: 0; }
    .ax-ch-kicker { font-size: .68rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--ax-deep); }
    .ax-ch-title { font-family: var(--ax-display); font-size: clamp(1.5rem, 2.6vw, 1.95rem); font-weight: 700; color: var(--ax-ink); letter-spacing: -.015em; line-height: 1.1; margin: .1rem 0 0; }

    .ax-card { background: #fff; border: 1px solid var(--ax-line); border-radius: 22px; padding: clamp(1.5rem, 2.8vw, 2.25rem); box-shadow: 0 30px 60px -46px rgba(45,35,85,.55); }

    /* prose body */
    .ax-prose { color: hsl(230 16% 26%); font-size: 1.075rem; line-height: 1.92; }
    .ax-prose > :first-child { margin-top: 0; }
    .ax-prose p { margin: 0 0 1.1rem; }
    .ax-prose p:last-child { margin-bottom: 0; }
    .ax-prose strong { color: var(--ax-ink); font-weight: 700; }
    .ax-prose em { font-style: italic; }
    .ax-lede { display: block; }
    .ax-dropcap::first-letter { font-family: var(--ax-display); font-weight: 900; float: inline-start; font-size: 3.4em; line-height: .82; margin: .04em .12em 0 0; color: var(--ax-deep); }
    [dir="rtl"] .ax-dropcap::first-letter { margin: .04em 0 0 .12em; }

    /* benefits / limitations item grids */
    .ax-items { display: grid; gap: .8rem; }
    @media (min-width: 640px) { .ax-items { grid-template-columns: 1fr 1fr; } }
    .ax-item { display: flex; gap: .85rem; padding: 1.05rem 1.1rem; border-radius: 16px; background: hsl(230 30% 98%); border: 1px solid var(--ax-line); transition: transform .25s, box-shadow .25s, border-color .25s; }
    .ax-item:hover { transform: translateY(-3px); box-shadow: 0 18px 34px -22px rgba(45,35,85,.4); border-color: hsl(250 50% 86%); }
    .ax-item-ic { flex-shrink: 0; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem; background: #fff; border: 1px solid var(--ax-line); }
    .ax-item-tx { font-size: .96rem; line-height: 1.55; color: hsl(230 16% 30%); }
    .ax-item-tx strong { color: var(--ax-ink); font-weight: 700; display: block; margin-bottom: .1rem; }
    .ax-chapter--warn .ax-item { background: hsl(28 90% 97%); border-color: hsl(32 80% 88%); }
    .ax-chapter--warn .ax-item-ic { border-color: hsl(32 80% 85%); color: hsl(28 80% 45%); }

    /* rules → numbered steps */
    .ax-steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }
    .ax-step { counter-increment: step; position: relative; padding: 0 0 1.15rem; padding-inline-start: 3.25rem; }
    .ax-step:last-child { padding-bottom: 0; }
    .ax-step::before { content: counter(step); position: absolute; inset-inline-start: 0; top: -.1em; width: 2.15rem; height: 2.15rem; border-radius: 999px; display: grid; place-items: center; font-family: var(--ax-display); font-weight: 700; font-size: .92rem; color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); box-shadow: 0 8px 18px -8px hsl(250 60% 50% / .6); z-index: 1; }
    .ax-step::after { content: ""; position: absolute; inset-inline-start: 1.07rem; top: 2.15rem; bottom: 0; width: 2px; background: linear-gradient(hsl(250 50% 86%), transparent); }
    .ax-step:last-child::after { display: none; }
    .ax-step-tx { font-size: 1rem; line-height: 1.6; color: hsl(230 16% 28%); padding-top: .18em; }
    .ax-step-tx strong { color: var(--ax-ink); }
    .ax-step-em { display: inline-flex; margin-inline-end: .35rem; }

    /* resources → link cards */
    .ax-links { display: grid; gap: .6rem; }
    @media (min-width: 640px) { .ax-links { grid-template-columns: 1fr 1fr; } }
    .ax-link { display: flex; align-items: center; gap: .75rem; min-width: 0; padding: .85rem 1rem; border-radius: 14px; background: #fff; border: 1px solid var(--ax-line); color: var(--ax-ink); text-decoration: none; font-weight: 600; font-size: .95rem; transition: transform .2s, border-color .2s, box-shadow .2s; }
    .ax-link:hover { transform: translateY(-2px); border-color: hsl(250 55% 82%); box-shadow: 0 16px 30px -20px rgba(45,35,85,.4); color: var(--ax-deep); }
    .ax-link-ic { flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: hsl(250 72% 95%); color: var(--ax-deep); }
    .ax-link > span:nth-child(2) { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; word-break: break-word; line-height: 1.35; }
    .ax-link .bi-box-arrow-up-right { flex-shrink: 0; color: hsl(230 18% 70%); font-size: .85rem; }

    .ax-foot { text-align: center; color: var(--ax-soft); font-size: .82rem; margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: .4rem; }

    /* motion */
    .ax-rise { opacity: 0; transform: translateY(24px); animation: axRise .9s cubic-bezier(.16,.84,.28,1) forwards; animation-delay: var(--d, 0s); }
    .reveal { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s cubic-bezier(.16,.84,.28,1); }
    .reveal.in { opacity: 1; transform: none; }
    @keyframes axZoom { from { transform: scale(1.14); } to { transform: scale(1.26) translateY(-1.5%); } }
    @keyframes axImgIn { from { opacity: 0; transform: scale(1.09); } to { opacity: 1; transform: none; } }
    @keyframes axRise { to { opacity: 1; transform: none; } }
    @keyframes axFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes axPulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
    @keyframes axCue { 0% { opacity: 0; transform: translate(-50%, 0); } 40% { opacity: 1; } 80%,100% { opacity: 0; transform: translate(-50%, 14px); } }
    @media (prefers-reduced-motion: reduce) { .ax-hero-bg, .ax-hero-img, .ax-hero-icon, .ax-rise, .ax-dot, .ax-scrollcue span, .reveal { animation: none !important; transition: none !important; opacity: 1 !important; transform: none !important; } }
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
// Hide the back arrow(s) when there's no in-app history (opened via QR / direct link).
if (history.length <= 1) { ['axBack', 'axBackMini'].forEach(id => { const b = document.getElementById(id); if (b) b.style.display = 'none'; }); }
(function () {
    // All available languages for THIS activity — the EN/AR toggle switches the
    // article content in place (page-only; never changes the saved preference).
    const AX = @js($content);
    const CH = { en: 'Chapter', ar: 'الفصل' };
    // Page-chrome strings per language — kept in sync with the server markup so
    // the on-page toggle updates them too (page-only; preference is untouched).
    const LABELS = @js([
        'en' => ['almanac' => 'THE TAKEONE ALMANAC', 'minread' => 'min read', 'chapters' => 'chapters', 'styles' => 'styles', 'sources' => 'sources', 'contents' => 'CONTENTS', 'foot' => 'From the TAKEONE global activity directory',
                 'join_title' => 'Join TAKEONE', 'join_desc' => 'Sign in or create a free account to join clubs, track your training and more.', 'login' => 'Log in', 'register' => 'Create account'],
        'ar' => ['almanac' => 'دليل تيك ون للأنشطة', 'minread' => 'دقيقة قراءة', 'chapters' => 'فصول', 'styles' => 'أنماط', 'sources' => 'مصادر', 'contents' => 'الفهرس', 'foot' => 'من دليل الأنشطة العالمي في تيك ون',
                 'join_title' => 'انضم إلى تيك ون', 'join_desc' => 'سجّل الدخول أو أنشئ حسابًا مجانيًا للانضمام إلى الأندية ومتابعة تدريبك والمزيد.', 'login' => 'تسجيل الدخول', 'register' => 'إنشاء حساب'],
    ]);
    const VARIANTS = @js(array_values($variants));
    const out = document.getElementById('axChapters');
    const idxList = document.getElementById('axIndexList');
    const titleEl = document.querySelector('.ax-title');
    const eyebrowEl = document.querySelector('.ax-eyebrow');
    const chipsEl = document.querySelector('.ax-chips');
    const footEl = document.querySelector('.ax-foot');
    if (!out) return;

    const setFactLabel = (bId, text) => { const b = document.getElementById(bId); if (b && b.nextSibling && b.nextSibling.nodeType === 3 && text) b.nextSibling.textContent = ' ' + text; };

    const splitEmoji = (t) => { const m = t.match(/^\s*([\p{Extended_Pictographic}️‍]+)\s*(.*)$/u); return m ? [m[1], m[2].trim()] : ['', t.trim()]; };
    const popLeadEmoji = (el) => {
        const first = el.firstChild;
        if (first && first.nodeType === 3) {
            const m = first.nodeValue.match(/^\s*([\p{Extended_Pictographic}️‍]+)\s*/u);
            if (m) { first.nodeValue = first.nodeValue.slice(m[0].length); return m[1]; }
        }
        return '';
    };
    const typeOf = (emoji, title) => {
        if (/💪/.test(emoji) || /benefit|فوائد|مزايا/i.test(title)) return 'benefits';
        if (/⚠️|⚠/.test(emoji) || /limitation|قيود|عيوب/i.test(title)) return 'limits';
        if (/📋|📏/.test(emoji) || /rule|قوانين|قواعد/i.test(title)) return 'rules';
        if (/🔗/.test(emoji) || /resource|مصادر|روابط/i.test(title)) return 'links';
        return 'prose';
    };

    const bar = document.getElementById('axBar'), mini = document.getElementById('axMini');
    const onScroll = () => { const h = document.documentElement, max = h.scrollHeight - h.clientHeight; const p = max > 0 ? (h.scrollTop / max) * 100 : 0; if (bar) bar.style.width = p + '%'; if (mini) mini.classList.toggle('show', h.scrollTop > window.innerHeight * 0.6); };
    let revObs = null, actObs = null, scrollBound = false;

    function render(code) {
        const dataL = AX[code]; if (!dataL) return;
        if (revObs) revObs.disconnect();
        if (actObs) actObs.disconnect();
        out.innerHTML = ''; if (idxList) idxList.innerHTML = '';
        // Flip the whole page direction to the chosen language (page-only — a
        // fresh load restores it from the saved preference). Re-aligns the hero,
        // facts rail, index and mini-bar to match.
        const dir = dataL.dir || 'ltr';
        document.documentElement.setAttribute('dir', dir);
        out.setAttribute('dir', dir);
        if (titleEl) { titleEl.textContent = dataL.name; titleEl.setAttribute('dir', dir); }
        const miniTitle = document.querySelector('.ax-mini-title');
        if (miniTitle) { miniTitle.textContent = dataL.name; miniTitle.setAttribute('dir', dir); }

        // Localize the page chrome to the chosen language.
        const L = LABELS[code] || LABELS.en || {};
        if (eyebrowEl && L.almanac) eyebrowEl.innerHTML = '<span class="ax-dot"></span> ' + L.almanac;
        setFactLabel('axReadTime', L.minread);
        setFactLabel('axSecCount', L.chapters);
        setFactLabel('axStyleCount', L.styles);
        setFactLabel('axSrcCount', L.sources);
        const idxLabel = document.querySelector('.ax-index-label');
        if (idxLabel && L.contents) idxLabel.textContent = L.contents;
        if (footEl && L.foot) footEl.innerHTML = '<i class="bi bi-collection"></i> ' + L.foot;
        const cta = document.getElementById('axCta');
        if (cta) {
            const q = (sel) => cta.querySelector(sel);
            if (q('[data-cta-title]') && L.join_title) q('[data-cta-title]').textContent = L.join_title;
            if (q('[data-cta-desc]') && L.join_desc) q('[data-cta-desc]').textContent = L.join_desc;
            if (q('[data-cta-login]') && L.login) q('[data-cta-login]').textContent = L.login;
            if (q('[data-cta-register]') && L.register) q('[data-cta-register]').textContent = L.register;
        }
        if (chipsEl) {
            chipsEl.innerHTML = '';
            VARIANTS.forEach(v => { const s = document.createElement('span'); s.className = 'ax-chip'; s.textContent = (code !== 'en' && v['name_' + code]) ? v['name_' + code] : (v.name || ''); chipsEl.appendChild(s); });
        }

        const chLabel = CH[code] || 'Chapter';

        const prose = document.createElement('div'); prose.innerHTML = dataL.description || '';
        const rootEl = prose.querySelector(':scope > div') || prose;
        const nodes = Array.from(rootEl.children);
        const lead = [], sections = []; let cur = null;
        nodes.forEach(n => { if (n.tagName === 'H3') { cur = { title: n.textContent.trim(), body: [] }; sections.push(cur); } else if (cur) cur.body.push(n); else lead.push(n); });

        const frag = document.createDocumentFragment();
        sections.forEach((s, i) => {
            const [emoji, title] = splitEmoji(s.title);
            const kind = typeOf(emoji, title);

            const ch = document.createElement('section');
            ch.className = 'ax-chapter reveal' + (kind === 'limits' ? ' ax-chapter--warn' : '');
            ch.id = 'ax-ch-' + i;

            // head
            const head = document.createElement('div'); head.className = 'ax-ch-head';
            const num = document.createElement('div'); num.className = 'ax-ch-num'; num.textContent = String(i + 1).padStart(2, '0');
            const badge = document.createElement('div'); badge.className = 'ax-ch-badge'; badge.textContent = emoji || '§';
            const meta = document.createElement('div'); meta.className = 'ax-ch-meta';
            meta.innerHTML = '<div class="ax-ch-kicker"></div><h2 class="ax-ch-title"></h2>';
            meta.querySelector('.ax-ch-kicker').textContent = chLabel + ' ' + String(i + 1).padStart(2, '0');
            meta.querySelector('.ax-ch-title').textContent = title;
            head.appendChild(num); head.appendChild(badge); head.appendChild(meta);
            ch.appendChild(head);

            // body
            const card = document.createElement('div'); card.className = 'ax-card';
            const ul = s.body.find(n => n.tagName === 'UL' || n.tagName === 'OL');

            if ((kind === 'benefits' || kind === 'limits') && ul) {
                const grid = document.createElement('div'); grid.className = 'ax-items';
                Array.from(ul.children).forEach(li => {
                    const e = popLeadEmoji(li);
                    const item = document.createElement('div'); item.className = 'ax-item';
                    item.innerHTML = '<div class="ax-item-ic">' + (e || (kind === 'limits' ? '⚠️' : '✔')) + '</div><div class="ax-item-tx"></div>';
                    item.querySelector('.ax-item-tx').innerHTML = li.innerHTML;
                    grid.appendChild(item);
                });
                card.appendChild(grid);
            } else if (kind === 'rules' && ul) {
                const ol = document.createElement('ol'); ol.className = 'ax-steps';
                Array.from(ul.children).forEach(li => {
                    const e = popLeadEmoji(li);
                    const step = document.createElement('li'); step.className = 'ax-step';
                    step.innerHTML = '<div class="ax-step-tx">' + (e ? '<span class="ax-step-em">' + e + '</span>' : '') + li.innerHTML + '</div>';
                    ol.appendChild(step);
                });
                card.appendChild(ol);
            } else if (kind === 'links' && ul) {
                const grid = document.createElement('div'); grid.className = 'ax-links';
                Array.from(ul.children).forEach(li => {
                    const a = li.querySelector('a'); if (!a) return;
                    const link = document.createElement('a'); link.href = a.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.className = 'ax-link';
                    link.innerHTML = '<span class="ax-link-ic"><i class="bi bi-globe2"></i></span><span></span><i class="bi bi-box-arrow-up-right"></i>';
                    link.querySelector('span:nth-child(2)').textContent = a.textContent;
                    grid.appendChild(link);
                });
                card.appendChild(grid);
            } else {
                const body = document.createElement('div'); body.className = 'ax-prose';
                if (i === 0 && lead.length) { const ld = document.createElement('div'); ld.className = 'ax-lede'; lead.forEach(n => ld.appendChild(n.cloneNode(true))); body.appendChild(ld); }
                s.body.forEach(n => body.appendChild(n.cloneNode(true)));
                const firstP = body.querySelector('.ax-lede') ? body.querySelector('.ax-lede ~ p') : body.querySelector('p');
                if (dir === 'ltr' && firstP && !/^\s*[\p{Extended_Pictographic}]/u.test(firstP.textContent)) firstP.classList.add('ax-dropcap');
                card.appendChild(body);
            }
            ch.appendChild(card);
            frag.appendChild(ch);

            // index
            if (idxList) {
                const li = document.createElement('li'); li.className = 'ax-idx'; li.dataset.target = ch.id;
                const a = document.createElement('a'); a.href = '#' + ch.id; a.textContent = title;
                a.addEventListener('click', ev => { ev.preventDefault(); document.getElementById(ch.id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
                li.appendChild(a); idxList.appendChild(li);
            }
        });

        out.appendChild(frag);

        // facts
        const words = (out.textContent || '').trim().split(/\s+/).length;
        const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        set('axReadTime', Math.max(1, Math.round(words / 200))); set('axSecCount', sections.length);
        set('axSrcCount', out.querySelectorAll('.ax-link').length || '—');
        const f = document.getElementById('axFacts'); if (f) f.hidden = false;
        const idxWrap = document.getElementById('axIndex'); if (idxWrap) idxWrap.hidden = !sections.length;

        // reveal + active index
        const chapters = Array.from(out.querySelectorAll('.ax-chapter'));
        const idxItems = idxList ? Array.from(idxList.children) : [];
        if ('IntersectionObserver' in window) {
            revObs = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revObs.unobserve(e.target); } }), { threshold: .05, rootMargin: '0px 0px -6% 0px' });
            chapters.forEach((c, i) => { c.style.transitionDelay = Math.min(i, 3) * 40 + 'ms'; revObs.observe(c); });
            actObs = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) idxItems.forEach(li => li.classList.toggle('active', li.dataset.target === e.target.id)); }), { rootMargin: '-45% 0px -50% 0px' });
            chapters.forEach(c => actObs.observe(c));
        } else chapters.forEach(c => c.classList.add('in'));

        if (!scrollBound) { window.addEventListener('scroll', onScroll, { passive: true }); scrollBound = true; }
        onScroll();
    }

    // Language toggle — page-only content switch (hero bar + sticky mini-bar stay in sync).
    const groups = ['axLang', 'axLangMini'].map(id => document.getElementById(id)).filter(Boolean);
    function setLang(code) {
        if (!AX[code]) return;
        groups.forEach(g => g.querySelectorAll('[data-lang]').forEach(b => b.classList.toggle('active', b.dataset.lang === code)));
        render(code);
    }
    groups.forEach(g => g.querySelectorAll('[data-lang]').forEach(btn => {
        btn.addEventListener('click', () => { setLang(btn.dataset.lang); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }));

    render(@js($defaultLang));
})();
</script>
@endpush
