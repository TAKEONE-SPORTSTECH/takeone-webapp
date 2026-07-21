@extends('layouts.app')

{{-- ─────────────────────────────────────────────────────────────────────────
     LAB / TEST PAGE — full Aikido activity page + a real "Watch" module.
     The article text is the REAL directory content (all 6 chapters, verbatim
     from ActivityCatalog uuid 7c4afd25…), rendered in the real `axm-*` design
     language. ONE video module ("See It in Motion") sits after the intro
     chapters, carrying REAL Aikido clips (verified via YouTube oEmbed).
     Videos use a click-to-load facade — no third-party player loads until
     tapped. Static mock, no writes. Route: /lab/activity-video
     ───────────────────────────────────────────────────────────────────────── --}}

@section('title', 'Aikido — video lab')
@section('hide-navbar', '1')

@php
    $hero = optional($activity)->picture_url ? asset('storage/'.$activity->picture_url) : null;

    // ── REAL article content (verbatim from the directory entry) ──
    $chapters = [
        ['n' => '01', 'emoji' => '📜', 'title' => 'Origins & Story', 'kind' => 'prose', 'dropcap' => true, 'body' => [
            "Aikido is a modern Japanese martial art developed in the early 20th century by <strong>Morihei Ueshiba</strong>, also known as O-Sensei (Great Teacher). Ueshiba created Aikido as a synthesis of his martial training, spiritual beliefs, and philosophy of non-resistance. The art was initially called <em>ai ki do</em>, meaning &lsquo;the way of unifying life energy&rsquo;.",
            "Ueshiba&rsquo;s teachings were shaped by his early life experiences, including his exposure to the spiritual practices of the <em>Shinto</em> religion and his training in traditional Japanese martial arts like <em>jujutsu</em>. He began teaching Aikido in the 1920s, and by the 1940s it had evolved into a distinct martial art with its own principles and techniques. After World War II, Aikido spread internationally through the efforts of his students.",
            "Modern Aikido is practiced worldwide, with various schools and styles, including the <em>traditional</em> and <em>competitive</em> forms. The art emphasizes harmony, non-aggression, and the redirection of an opponent&rsquo;s energy rather than confrontation. It is often described as a &lsquo;soft&rsquo; martial art, focusing on blending with an attacker&rsquo;s force and using it against them.",
        ]],
        ['n' => '02', 'emoji' => '🎯', 'title' => 'What It Focuses On', 'kind' => 'prose', 'body' => [
            "Aikido centers on the principle of <em>ai</em> (harmony) and <em>ki</em> (life energy). The core techniques involve <em>joint locks</em>, <em>throws</em>, and <em>restraining techniques</em> that redirect an opponent&rsquo;s force. Unlike many martial arts that rely on brute strength, Aikido emphasizes <em>timing</em>, <em>balance</em>, and <em>efficiency</em> in movement.",
            "The philosophy of Aikido is deeply rooted in the concept of <em>non-resistance</em> and <em>mutual benefit</em>. Practitioners aim to neutralize aggression without causing harm, using the attacker&rsquo;s energy against them. This approach is not only physical but also mental and spiritual, promoting self-awareness, discipline, and inner peace.",
        ]],
        ['n' => '03', 'emoji' => '💪', 'title' => 'Benefits', 'kind' => 'benefits', 'items' => [
            'Improves balance and coordination through fluid, flowing movements',
            'Enhances mental focus and stress relief through meditation and mindful practice',
            'Develops physical strength and flexibility without relying on brute force',
            'Builds confidence and self-defense skills in a non-aggressive manner',
            'Encourages social interaction and community building within the dojo',
            'Research shows Aikido can reduce anxiety and improve overall well-being',
        ]],
        ['n' => '04', 'emoji' => '⚠️', 'title' => 'Limitations', 'kind' => 'limits', 'items' => [
            'Not suited to competitive sport or combat, given its non-aggressive nature',
            'Techniques can be difficult to apply in real-life self-defense scenarios',
            'Requires long-term commitment and patience to master',
            'May be less effective against much larger or stronger opponents',
            'Limited scientific research on its physical and mental health benefits',
        ]],
        ['n' => '05', 'emoji' => '📋', 'title' => 'Rules in Brief', 'kind' => 'rules', 'items' => [
            'Practice is typically non-competitive and focuses on technique and harmony',
            'Participants must avoid causing injury to their partner',
            'Throws and joint locks are performed with control and precision',
            'Sessions are usually held in a <em>dojo</em> or training hall',
            'There is no single governing body, though various federations exist',
            'Training emphasizes mutual respect and the principles of Aikido',
            'Technique is judged on form, control, and harmony',
            'Participants wear an appropriate <em>gi</em> (uniform) and follow dojo etiquette',
        ]],
        ['n' => '06', 'emoji' => '🔗', 'title' => 'Trusted Resources', 'kind' => 'links', 'links' => [
            ['t' => 'Aikido.org', 'u' => 'https://www.aikido.org'],
            ['t' => 'Wikipedia — Aikido', 'u' => 'https://en.wikipedia.org/wiki/Aikido'],
            ['t' => 'Encyclopaedia Britannica — Aikido', 'u' => 'https://www.britannica.com/topic/Aikido'],
            ['t' => 'Aikido Journal', 'u' => 'https://aikidojournal.com'],
            ['t' => 'Aikido.com', 'u' => 'https://www.aikido.com'],
        ]],
    ];

    // ── REAL Aikido videos (ids + titles/channels verified via YouTube oEmbed) ──
    $featured = ['id' => '30Sa0PLquFg', 'title' => 'O-Sensei — Rare Demonstration (1957)', 'src' => 'Aikido · Guillaume Erard'];
    $rail = [
        ['id' => 'uhPu6iNV7jE', 'title' => 'Complete All-in-One Aikido Tutorial', 'src' => 'Rokas Leo'],
        ['id' => 'SVdY3AwlH_w', 'title' => 'How to Do Ikkyo', 'src' => 'Howcast'],
        ['id' => 'll1GCzl4Bxg', 'title' => 'How to Do Kotegaeshi', 'src' => 'Howcast'],
        ['id' => '-w_8WDE-i8U', 'title' => 'Aikido Basics — Pins &amp; Throws', 'src' => 'Aikido Silverdale'],
        ['id' => 'qJo_AVcl-A8', 'title' => '"Divine Techniques" — Founder Archive', 'src' => 'Aikido Journal'],
    ];
    $thumb = fn ($id) => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
@endphp

@section('content')
<div class="axm">
    <div class="axm-progress"><span id="axmBar"></span></div>

    {{-- Hero — real activity image --}}
    <div class="axm-hero">
        @if($hero)
            <div class="axm-hero-bg" style="background-image:url('{{ $hero }}')"></div>
            <img class="axm-hero-img" src="{{ $hero }}" alt="Aikido">
        @else
            <div class="axm-hero-bg axm-hero-bg--plain"></div>
            <div class="axm-hero-icon"><i class="bi bi-collection-play"></i></div>
        @endif
        <div class="axm-hero-topfade"></div>
        <div class="axm-topbar">
            <a href="{{ url('/lab/activity-video') }}" class="axm-ctrl"><i class="bi bi-arrow-left"></i></a>
            <div class="axm-topbar-right">
                <button type="button" class="axm-ctrl"><i class="bi bi-share"></i></button>
                <button type="button" class="axm-ctrl"><i class="bi bi-qr-code"></i></button>
            </div>
        </div>
    </div>

    <div class="axm-sheet">
        <div class="axm-band">
            <div class="axm-eyebrow"><span class="axm-dot"></span> THE TAKEONE ALMANAC · LAB</div>
            <h1 class="axm-title">Aikido</h1>
        </div>

        <div class="axm-factsrow">
            <div class="axm-facts">
                <div class="axm-fact"><i class="bi bi-clock-history"></i><b>3</b> <span>min</span></div>
                <div class="axm-fact"><i class="bi bi-collection"></i><b>6</b> <span>chapters</span></div>
                <div class="axm-fact"><i class="bi bi-play-btn"></i><b>{{ count($rail) + 1 }}</b> <span>videos</span></div>
            </div>
            <div class="axm-langtoggle">
                <button type="button" class="axm-langbtn active">EN</button>
                <button type="button" class="axm-langbtn">AR</button>
            </div>
        </div>

        {{-- sticky chapter nav — sits above the video lead --}}
        <nav class="axm-chapnav" id="axmNav">
            <button type="button" class="axm-navchip axm-navchip--watch" data-target="axm-watch"><i class="bi bi-play-fill"></i> Watch</button>
            @foreach($chapters as $c)
                <button type="button" class="axm-navchip" data-target="axm-ch-{{ $c['n'] }}"><b>{{ $c['n'] }}</b> {{ $c['emoji'] }}</button>
            @endforeach
        </nav>

        {{-- ══════════ VIDEO-FIRST lead — the artistic "press play" opener ══════════
             People discover with video, then read if hooked. So this comes before
             the article, as an immersive band rather than a plain card. --}}
        <section class="axv-lead reveal in" id="axm-watch">
            <div class="axv-lead-eyebrow"><span class="axv-lead-pulse"><i class="bi bi-play-fill"></i></span> Press play · watch first</div>

            {{-- Featured player — cinematic, glowing, title overlaid --}}
            <button type="button" class="axv-stage axv-video" data-vid="{{ $featured['id'] }}" aria-label="Play {{ $featured['title'] }}">
                <img class="axv-stage-img" src="{{ $thumb($featured['id']) }}" alt="" loading="lazy" onerror="this.style.display='none'">
                <span class="axv-stage-scrim"></span>
                <span class="axv-play axv-play--xl"><span class="axv-play-ring"></span><i class="bi bi-play-fill"></i></span>
                <span class="axv-stage-meta">
                    <span class="axv-stage-src"><i class="bi bi-youtube"></i> {{ $featured['src'] }}</span>
                    <span class="axv-stage-title">{!! $featured['title'] !!}</span>
                </span>
            </button>

            {{-- Supporting clips rail --}}
            <div class="axv-railhead">
                <h3>Techniques &amp; archive</h3>
                <span class="axv-railhint">Swipe →</span>
            </div>
            <div class="axv-rail" id="axvRail">
                @foreach($rail as $v)
                    <button type="button" class="axv-railcard axv-video" data-vid="{{ $v['id'] }}" aria-label="Play {{ strip_tags($v['title']) }}">
                        <span class="axv-railthumb">
                            <img src="{{ $thumb($v['id']) }}" alt="" loading="lazy" onerror="this.style.display='none'">
                            <span class="axv-play axv-play--sm"><i class="bi bi-play-fill"></i></span>
                        </span>
                        <span class="axv-railcap">{!! $v['title'] !!}</span>
                        <span class="axv-railsrc"><i class="bi bi-youtube"></i> {{ $v['src'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        @foreach($chapters as $c)
            <section class="axm-chapter reveal {{ $c['kind'] === 'limits' ? 'axm-chapter--warn' : '' }}" id="axm-ch-{{ $c['n'] }}">
                <div class="axm-ch-head">
                    <div class="axm-ch-num">{{ $c['n'] }}</div>
                    <div class="axm-ch-badge">{{ $c['emoji'] }}</div>
                    <div>
                        <div class="axm-ch-kicker">Chapter {{ $c['n'] }}</div>
                        <h2 class="axm-ch-title">{{ $c['title'] }}</h2>
                    </div>
                </div>

                <div class="axm-card">
                    @switch($c['kind'])
                        @case('prose')
                            <div class="axm-prose">
                                @foreach($c['body'] as $i => $p)
                                    <p class="{{ ($c['dropcap'] ?? false) && $i === 0 ? 'axm-dropcap' : '' }}">{!! $p !!}</p>
                                @endforeach
                            </div>
                            @break

                        @case('benefits')
                            <div class="axm-items">
                                @foreach($c['items'] as $it)
                                    <div class="axm-item"><div class="axm-item-ic">✔</div><div class="axm-item-tx">{!! $it !!}</div></div>
                                @endforeach
                            </div>
                            @break

                        @case('limits')
                            <div class="axm-items">
                                @foreach($c['items'] as $it)
                                    <div class="axm-item"><div class="axm-item-ic">⚠️</div><div class="axm-item-tx">{!! $it !!}</div></div>
                                @endforeach
                            </div>
                            @break

                        @case('rules')
                            <ol class="axm-steps">
                                @foreach($c['items'] as $it)
                                    <li class="axm-step"><div class="axm-step-tx">{!! $it !!}</div></li>
                                @endforeach
                            </ol>
                            @break

                        @case('links')
                            <div class="axm-links">
                                @foreach($c['links'] as $l)
                                    <a href="{{ $l['u'] }}" target="_blank" rel="noopener noreferrer nofollow" class="axm-link">
                                        <span class="axm-link-ic"><i class="bi bi-globe2"></i></span>
                                        <span>{{ $l['t'] }}</span>
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endforeach
                            </div>
                            @break
                    @endswitch
                </div>
            </section>

        @endforeach

        <p class="axm-foot"><i class="bi bi-collection"></i> TAKEONE global directory · lab</p>
    </div>
</div>

{{-- Lightbox player — delegated + fixed overlay --}}
<div class="axv-lightbox" id="axvLightbox" hidden>
    <button type="button" class="axv-lb-close" id="axvLbClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <div class="axv-lb-frame" id="axvLbFrame"></div>
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

    /* hero */
    .axm-hero { position: relative; width: 100%; aspect-ratio: 16/9; max-height: 52vh; overflow: hidden; background: #08080f; }
    .axm-hero-bg { position: absolute; inset: -8%; background-size: cover; background-position: center; filter: blur(28px) brightness(.44) saturate(1.15); transform: scale(1.16); }
    .axm-hero-bg--plain { position: absolute; inset: 0; background: radial-gradient(500px 320px at 30% 18%, hsl(250 66% 46%), transparent 70%), linear-gradient(135deg, hsl(252 56% 30%), hsl(250 48% 12%)); filter: none; transform: none; }
    .axm-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; animation: axmImgIn 1.05s cubic-bezier(.16,.84,.28,1) both; }
    .axm-hero-icon { position: absolute; inset: 0; display: grid; place-items: center; color: rgba(255,255,255,.9); font-size: 4.5rem; z-index: 1; }
    .axm-hero-topfade { position: absolute; top: 0; inset-inline: 0; height: 92px; z-index: 2; pointer-events: none; background: linear-gradient(to bottom, rgba(8,8,16,.36), transparent); }
    .axm-topbar { position: absolute; top: calc(10px + env(safe-area-inset-top)); inset-inline: 12px; z-index: 6; display: flex; align-items: center; justify-content: space-between; }
    .axm-topbar-right { display: flex; align-items: center; gap: .5rem; }
    .axm-ctrl { width: 42px; height: 42px; border-radius: 999px; display: inline-grid; place-items: center; color: #fff; font-size: 1rem; text-decoration: none; cursor: pointer; background: rgba(255,255,255,.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.24); }
    .axm-ctrl:active { transform: scale(.9); }

    .axm-sheet { position: relative; z-index: 3; background: hsl(230 24% 97%); padding: .4rem 1rem 3.5rem; min-height: 60vh; box-shadow: 0 -14px 44px -20px rgba(45,35,85,.28); }
    .axm-band { padding: .35rem .15rem .15rem; }
    .axm-eyebrow { display: inline-flex; align-items: center; gap: .5rem; color: var(--ax-deep); font-size: .62rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
    .axm-dot { width: 6px; height: 6px; border-radius: 999px; background: var(--ax); box-shadow: 0 0 10px 1px var(--ax); animation: axmPulse 2.4s ease-in-out infinite; }
    .axm-title { font-family: var(--ax-display); font-size: clamp(1.85rem, 8.5vw, 2.6rem); font-weight: 900; letter-spacing: -.02em; line-height: 1.02; color: var(--ax-ink); margin: .4rem 0 0; }
    .axm-factsrow { display: flex; align-items: center; gap: 1rem; margin: .9rem 0 0; }
    .axm-facts { display: flex; gap: 1.1rem; padding: .2rem 0; }
    .axm-fact { display: flex; align-items: center; gap: .35rem; color: var(--ax-soft); font-size: .82rem; }
    .axm-fact i { color: var(--ax-deep); }
    .axm-fact b { font-family: var(--ax-display); font-weight: 700; color: var(--ax-ink); }
    .axm-langtoggle { display: inline-flex; margin-inline-start: auto; height: 28px; border-radius: 999px; overflow: hidden; background: #fff; border: 1px solid var(--ax-line); }
    .axm-langbtn { padding: 0 .6rem; color: var(--ax-soft); font-size: .7rem; font-weight: 800; background: transparent; border: 0; cursor: pointer; }
    .axm-langbtn.active { background: var(--ax-deep); color: #fff; }

    .axm-chapnav { position: sticky; top: 0; z-index: 20; display: flex; gap: .45rem; margin: 1rem -1rem .25rem; padding: .6rem 1rem; overflow-x: auto; scrollbar-width: none; background: hsl(230 24% 97% / .9); backdrop-filter: blur(10px); }
    .axm-chapnav::-webkit-scrollbar { display: none; }
    .axm-navchip { flex-shrink: 0; display: inline-flex; align-items: center; gap: .4rem; padding: .42rem .8rem; border-radius: 999px; font-size: .8rem; font-weight: 600; color: var(--ax-soft); background: #fff; border: 1px solid var(--ax-line); cursor: pointer; white-space: nowrap; transition: all .2s; }
    .axm-navchip.active { color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); border-color: transparent; box-shadow: 0 8px 18px -10px hsl(250 60% 50% / .7); }
    .axm-navchip b { font-family: var(--ax-display); }
    .axm-navchip--watch { color: var(--ax-deep); border-color: hsl(250 58% 84%); background: hsl(250 72% 96%); }
    .axm-navchip--watch.active { color: #fff; background: linear-gradient(145deg, hsl(288 62% 58%), hsl(288 66% 46%)); }

    .axm-chapter { scroll-margin-top: 62px; margin-bottom: 1.1rem; }
    .axm-ch-head { display: flex; align-items: center; gap: .75rem; margin: 1.4rem .15rem .8rem; }
    .axm-ch-num { font-family: var(--ax-display); font-weight: 900; font-size: 2.3rem; line-height: .8; color: transparent; -webkit-text-stroke: 1.3px hsl(250 42% 78%); flex-shrink: 0; }
    .axm-ch-numicon { -webkit-text-stroke: 0; color: hsl(250 42% 74%); font-size: 1.7rem; }
    .axm-ch-badge { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; font-size: 1.25rem; flex-shrink: 0; background: linear-gradient(145deg, hsl(250 82% 96%), hsl(250 72% 91%)); border: 1px solid hsl(250 58% 87%); }
    .axm-ch-kicker { font-size: .62rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: var(--ax-deep); }
    .axm-ch-title { font-family: var(--ax-display); font-size: 1.28rem; font-weight: 700; color: var(--ax-ink); line-height: 1.12; margin: .05rem 0 0; }
    .axm-card { background: #fff; border: 1px solid var(--ax-line); border-radius: 18px; padding: 1.15rem; box-shadow: 0 18px 40px -34px rgba(45,35,85,.5); }

    /* prose / items / steps / links (mirror the real page) */
    .axm-prose { color: hsl(230 16% 26%); font-size: 1rem; line-height: 1.85; }
    .axm-prose p { margin: 0 0 1rem; } .axm-prose p:last-child { margin: 0; }
    .axm-prose strong { color: var(--ax-ink); font-weight: 700; }
    .axm-dropcap::first-letter { font-family: var(--ax-display); font-weight: 900; float: inline-start; font-size: 3em; line-height: .82; margin: .05em .1em 0 0; color: var(--ax-deep); }
    .axm-items { display: grid; gap: .65rem; }
    .axm-item { display: flex; gap: .75rem; padding: .85rem .9rem; border-radius: 14px; background: hsl(230 30% 98%); border: 1px solid var(--ax-line); }
    .axm-item-ic { flex-shrink: 0; width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; font-size: 1.1rem; background: #fff; border: 1px solid var(--ax-line); }
    .axm-item-tx { font-size: .93rem; line-height: 1.5; color: hsl(230 16% 30%); align-self: center; }
    .axm-chapter--warn .axm-item { background: hsl(28 90% 97%); border-color: hsl(32 80% 88%); }
    .axm-chapter--warn .axm-item-ic { border-color: hsl(32 80% 85%); }
    .axm-steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }
    .axm-step { counter-increment: step; position: relative; padding: 0 0 1rem; padding-inline-start: 2.9rem; }
    .axm-step:last-child { padding-bottom: 0; }
    .axm-step::before { content: counter(step); position: absolute; inset-inline-start: 0; top: -.05em; width: 1.95rem; height: 1.95rem; border-radius: 999px; display: grid; place-items: center; font-family: var(--ax-display); font-weight: 700; font-size: .85rem; color: #fff; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); z-index: 1; }
    .axm-step::after { content: ""; position: absolute; inset-inline-start: .95rem; top: 1.95rem; bottom: 0; width: 2px; background: linear-gradient(hsl(250 50% 86%), transparent); }
    .axm-step:last-child::after { display: none; }
    .axm-step-tx { font-size: .96rem; line-height: 1.55; color: hsl(230 16% 28%); padding-top: .12em; }
    .axm-links { display: grid; gap: .5rem; }
    .axm-link { display: flex; align-items: center; gap: .65rem; min-width: 0; padding: .75rem .85rem; border-radius: 13px; background: #fff; border: 1px solid var(--ax-line); color: var(--ax-ink); text-decoration: none; font-weight: 600; font-size: .92rem; }
    .axm-link:active { transform: scale(.99); }
    .axm-link-ic { flex-shrink: 0; width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: hsl(250 72% 95%); color: var(--ax-deep); }
    .axm-link > span:nth-child(2) { flex: 1 1 auto; min-width: 0; overflow-wrap: anywhere; line-height: 1.35; }
    .axm-link .bi-box-arrow-up-right { flex-shrink: 0; color: hsl(230 18% 70%); font-size: .8rem; }
    .axm-foot { text-align: center; color: var(--ax-soft); font-size: .76rem; margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .35rem; }

    /* ── Watch module ── */
    .axv-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 56px; height: 56px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: 1.5rem; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); box-shadow: 0 12px 30px -8px hsl(250 70% 45% / .8), inset 0 0 0 4px rgba(255,255,255,.18); transition: transform .18s ease; z-index: 3; }
    .axv-play i { transform: translateX(1px); }
    .axv-play--lg { width: 68px; height: 68px; font-size: 1.9rem; }
    .axv-play--sm { width: 32px; height: 32px; font-size: .9rem; box-shadow: 0 6px 16px -6px hsl(250 70% 45% / .8); }
    .axv-video { -webkit-tap-highlight-color: transparent; }
    .axv-video:active .axv-play { transform: translate(-50%,-50%) scale(.9); }
    .axv-card { padding-bottom: 1.25rem; }
    .axv-feature { position: relative; display: block; width: 100%; aspect-ratio: 16/10; padding: 0; border: 0; border-radius: 14px; overflow: hidden; cursor: pointer; background: linear-gradient(145deg, hsl(250 30% 22%), hsl(250 40% 10%)); }
    .axv-feature-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .axv-feature-scrim { position: absolute; inset: 0; background: radial-gradient(120% 90% at 50% 45%, transparent 40%, rgba(8,6,20,.55) 100%); }
    .axv-feature-badge { position: absolute; bottom: .6rem; inset-inline-start: .6rem; z-index: 3; display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .55rem; border-radius: 8px; font-size: .72rem; font-weight: 700; color: #fff; background: rgba(8,6,20,.62); backdrop-filter: blur(6px); }
    .axv-feature-badge .bi-youtube { color: #ff4b4b; }
    .axv-feature-cap { margin: .7rem 0 0; font-family: var(--ax-display); font-weight: 700; font-size: 1.02rem; color: var(--ax-ink); line-height: 1.2; }
    .axv-lede { margin: .5rem 0 0; color: hsl(230 16% 30%); font-size: .92rem; line-height: 1.65; }
    .axv-railhead { display: flex; align-items: baseline; justify-content: space-between; margin: 1.15rem 0 .7rem; }
    .axv-railhead h3 { font-family: var(--ax-display); font-size: .98rem; font-weight: 700; color: var(--ax-ink); margin: 0; }
    .axv-railhint { font-size: .72rem; font-weight: 600; color: var(--ax-soft); }
    /* rail stays within the card padding — first card aligns with the featured
       video/prose above; the card end-padding lets the last thumb reach the edge
       while the next card peeks in for the swipe affordance. */
    .axv-rail { display: flex; gap: .7rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin: 0; padding: 0 0 .3rem; scroll-padding-inline: 0; }
    .axv-rail::-webkit-scrollbar { display: none; }
    .axv-railcard { flex: 0 0 46%; scroll-snap-align: start; display: block; padding: 0; border: 0; background: transparent; cursor: pointer; text-align: start; }
    .axv-railcard:last-child { margin-inline-end: .15rem; }
    .axv-railthumb { position: relative; display: block; width: 100%; aspect-ratio: 16/11; border-radius: 13px; overflow: hidden; background: linear-gradient(145deg, hsl(250 30% 22%), hsl(250 40% 10%)); }
    .axv-railthumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    /* fixed 2-line caption → every card is the same height, so thumbs, captions
       and source rows all sit on the same level; longer titles are ellipsed. */
    .axv-railcap { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: .45rem; font-size: .82rem; font-weight: 600; line-height: 1.28; color: var(--ax-ink); min-height: calc(2 * 1.28 * .82rem); }
    .axv-railsrc { display: inline-flex; align-items: center; gap: .25rem; margin-top: .15rem; font-size: .7rem; color: var(--ax-soft); }
    .axv-railsrc .bi-youtube { color: #ff4b4b; }

    /* ── VIDEO-FIRST artistic lead (top of page) ── */
    .axv-lead { position: relative; margin: .7rem -1rem .2rem; padding: .9rem 1rem 1.5rem;
        background:
            radial-gradient(140% 90% at 18% -10%, hsl(250 80% 95% / .9), transparent 55%),
            radial-gradient(120% 80% at 100% 0%, hsl(288 70% 95% / .8), transparent 50%);
        border-bottom: 1px solid hsl(250 40% 91%); }
    .axv-lead-eyebrow { display: inline-flex; align-items: center; gap: .5rem; margin: 0 0 .8rem .1rem; font-size: .66rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--ax-deep); }
    .axv-lead-pulse { position: relative; width: 22px; height: 22px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: .7rem; background: linear-gradient(145deg, var(--ax), var(--ax-deep)); box-shadow: 0 0 0 0 hsl(250 66% 60% / .55); animation: axvPulseRing 2.2s ease-out infinite; }
    .axv-lead-pulse i { transform: translateX(.5px); }

    .axv-stage { position: relative; display: block; width: 100%; aspect-ratio: 16/9; padding: 0; border: 0; border-radius: 22px; overflow: hidden; cursor: pointer;
        background: linear-gradient(145deg, hsl(250 30% 22%), hsl(250 40% 10%));
        box-shadow: 0 34px 70px -28px hsl(250 66% 42% / .62), 0 0 0 1px hsl(250 40% 88%); }
    .axv-stage-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .axv-stage-scrim { position: absolute; inset: 0; background:
        radial-gradient(120% 90% at 50% 42%, transparent 34%, rgba(8,6,20,.5) 100%),
        linear-gradient(to top, rgba(8,6,20,.9) 2%, rgba(8,6,20,.15) 42%, transparent 66%); }
    .axv-stage-meta { position: absolute; inset-inline: 0; bottom: 0; z-index: 3; padding: .9rem 1rem 1rem; text-align: start; }
    .axv-stage-src { display: inline-flex; align-items: center; gap: .3rem; padding: .18rem .5rem; border-radius: 999px; font-size: .68rem; font-weight: 700; color: #fff; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(6px); }
    .axv-stage-src .bi-youtube { color: #ff5b5b; }
    .axv-stage-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: .4rem; font-family: var(--ax-display); font-weight: 700; font-size: 1.24rem; line-height: 1.14; color: #fff; text-shadow: 0 2px 18px rgba(0,0,0,.5); max-width: 92%; }

    .axv-play--xl { width: 80px; height: 80px; font-size: 2.1rem; }
    .axv-play-ring { position: absolute; inset: -7px; border-radius: 999px; border: 2px solid rgba(255,255,255,.55); animation: axvPlayRing 2.4s ease-out infinite; }

    /* the lead is the target of the "Watch" chip too */
    .axv-lead { scroll-margin-top: 62px; }

    @keyframes axvPulseRing { 0% { box-shadow: 0 0 0 0 hsl(250 66% 60% / .5); } 70%,100% { box-shadow: 0 0 0 10px hsl(250 66% 60% / 0); } }
    @keyframes axvPlayRing { 0% { transform: scale(1); opacity: .8; } 100% { transform: scale(1.5); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .axv-lead-pulse, .axv-play-ring { animation: none !important; } .axv-play-ring { opacity: .5; } }

    /* lightbox */
    .axv-lightbox { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 1rem; background: rgba(6,4,16,.9); backdrop-filter: blur(10px); animation: axvFade .2s ease; }
    .axv-lb-frame { position: relative; width: 100%; max-width: 900px; aspect-ratio: 16/9; border-radius: 14px; overflow: hidden; box-shadow: 0 30px 80px -20px rgba(0,0,0,.7); }
    .axv-lb-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .axv-lb-close { position: absolute; top: calc(12px + env(safe-area-inset-top)); inset-inline-end: 14px; width: 42px; height: 42px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: 1.1rem; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); cursor: pointer; }

    .reveal { opacity: 0; transform: translateY(20px); transition: opacity .6s ease, transform .6s cubic-bezier(.16,.84,.28,1); }
    .reveal.in { opacity: 1; transform: none; }
    @keyframes axmImgIn { from { opacity: 0; transform: scale(1.09); } to { opacity: 1; transform: none; } }
    @keyframes axmPulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
    @keyframes axvFade { from { opacity: 0; } to { opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .axm-hero-img, .axm-dot, .reveal, .axv-lightbox, .axv-play { animation: none !important; transition: none !important; opacity: 1 !important; transform: none !important; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ── click-to-load video facade ──
    const lb = document.getElementById('axvLightbox');
    const frame = document.getElementById('axvLbFrame');
    const VALID = /^[A-Za-z0-9_-]{11}$/; // YouTube ids only — never inject arbitrary input into src
    function openVid(id) {
        if (!lb || !frame || !VALID.test(id)) return;
        frame.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + id +
            '?autoplay=1&rel=0&modestbranding=1" title="Video" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
        lb.hidden = false; document.body.style.overflow = 'hidden';
    }
    function closeVid() { if (!lb) return; lb.hidden = true; frame.innerHTML = ''; document.body.style.overflow = ''; }
    document.addEventListener('click', function (e) {
        const card = e.target.closest('.axv-video');
        if (card) { openVid(card.dataset.vid); return; }
        if (e.target.closest('#axvLbClose') || e.target === lb) closeVid();
        const chip = e.target.closest('.axm-navchip');
        if (chip && chip.dataset.target) document.getElementById(chip.dataset.target)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && lb && !lb.hidden) closeVid(); });

    // ── scroll progress + reveal + active chapter chip ──
    const bar = document.getElementById('axmBar');
    const onScroll = () => { const h = document.documentElement, max = h.scrollHeight - h.clientHeight; if (bar) bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%'; };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

    const sections = Array.from(document.querySelectorAll('.axm-chapter'));
    const chips = Array.from(document.querySelectorAll('.axm-navchip'));
    if ('IntersectionObserver' in window) {
        const rev = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); rev.unobserve(e.target); } }), { threshold: .05, rootMargin: '0px 0px -5% 0px' });
        sections.forEach(s => rev.observe(s));
        const act = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) chips.forEach(c => { const on = c.dataset.target === e.target.id; c.classList.toggle('active', on); if (on) c.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }); }); }), { rootMargin: '-40% 0px -55% 0px' });
        sections.forEach(s => act.observe(s));
    } else sections.forEach(s => s.classList.add('in'));
})();
</script>
@endpush
