@props([
    'videos' => [],      // ordered list from ActivityCatalog::sanitizedVideos() — [0] = featured
    'rtl' => false,
])

{{--
    Video-first "Watch" lead for an activity page. Discovery-oriented: people
    watch, then read if hooked — so this renders above the article. Standalone &
    reusable across the mobile and desktop activity views (Component-First):
    brings its own scoped `axv-*` styles + a delegated click-to-load player (one
    lightbox per page). No third-party player loads until a card is tapped, and
    only validated YouTube ids are ever embedded (ids are re-checked here even
    though the model already sanitized them — defense in depth).

    Anchor id `axm-watch` is the scroll target for the pages' "Watch" nav chip.
--}}

@php
    $vids = collect($videos)
        ->filter(fn ($v) => is_array($v) && preg_match(\App\Models\ActivityCatalog::YOUTUBE_ID, (string) ($v['id'] ?? '')))
        ->values();
@endphp

@if($vids->isNotEmpty())
    @php
        $featured = $vids->first();
        $rail = $vids->slice(1)->values();
        $titleOf = fn ($v) => $rtl && filled($v['title_ar'] ?? null) ? $v['title_ar'] : ($v['title'] ?? 'Video');
        $thumb = fn ($id) => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
        $L = [
            'eyebrow' => $rtl ? 'شغّل الفيديو · شاهد أولاً' : 'Press play · watch first',
            'more' => $rtl ? 'تقنيات وأرشيف' : 'Techniques & archive',
            'swipe' => $rtl ? '← اسحب' : 'Swipe →',
        ];
    @endphp

    <section class="axv-lead" id="axm-watch" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
        {{-- Featured player — cinematic, glowing, title overlaid --}}
        <button type="button" class="axv-stage axv-video" data-vid="{{ $featured['id'] }}" aria-label="{{ $titleOf($featured) }}">
            <img class="axv-stage-img" src="{{ $thumb($featured['id']) }}" alt="" loading="lazy" onerror="this.style.display='none'">
            <span class="axv-stage-scrim"></span>
            <span class="axv-play axv-play--xl"><span class="axv-play-ring"></span><i class="bi bi-play-fill"></i></span>
            <span class="axv-stage-meta">
                <span class="axv-stage-title">{{ $titleOf($featured) }}</span>
            </span>
        </button>

        @if($rail->isNotEmpty())
            <div class="axv-railhead">
                <h3>{{ $L['more'] }}</h3>
                <span class="axv-railhint">{{ $L['swipe'] }}</span>
            </div>
            <div class="axv-rail">
                @foreach($rail as $v)
                    <button type="button" class="axv-railcard axv-video" data-vid="{{ $v['id'] }}" aria-label="{{ $titleOf($v) }}">
                        <span class="axv-railthumb">
                            <img src="{{ $thumb($v['id']) }}" alt="" loading="lazy" onerror="this.style.display='none'">
                            <span class="axv-play axv-play--sm"><i class="bi bi-play-fill"></i></span>
                        </span>
                        <span class="axv-railcap">{{ $titleOf($v) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    @once
        @push('modals')
            <div class="axv-lightbox" id="axvLightbox" hidden>
                <button type="button" class="axv-lb-close" id="axvLbClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
                <div class="axv-lb-frame" id="axvLbFrame"></div>
            </div>
        @endpush

        @push('styles')
        <style>
            .axv-lead { --axv: var(--ax, hsl(250 66% 62%)); --axv-deep: var(--ax-deep, hsl(250 68% 52%)); --axv-ink: var(--ax-ink, hsl(230 32% 13%)); --axv-soft: var(--ax-soft, hsl(230 14% 42%)); --axv-display: var(--ax-display, 'Fraunces', ui-serif, Georgia, serif);
                position: relative; margin: .7rem calc(-1 * var(--axv-pad, 1rem)) .2rem; padding: .9rem var(--axv-pad, 1rem) 1.5rem;
                background: radial-gradient(140% 90% at 18% -10%, hsl(250 80% 95% / .9), transparent 55%), radial-gradient(120% 80% at 100% 0%, hsl(288 70% 95% / .8), transparent 50%);
                border-bottom: 1px solid hsl(250 40% 91%); scroll-margin-top: 62px; }
            .axv-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); border-radius: 999px; display: grid; place-items: center; color: #fff; background: linear-gradient(145deg, var(--axv), var(--axv-deep)); box-shadow: 0 12px 30px -8px hsl(250 70% 45% / .8), inset 0 0 0 4px rgba(255,255,255,.18); transition: transform .18s ease; z-index: 3; width: 56px; height: 56px; font-size: 1.5rem; }
            .axv-play i { transform: translateX(1px); }
            .axv-play--xl { width: 80px; height: 80px; font-size: 2.1rem; }
            .axv-play--sm { width: 32px; height: 32px; font-size: .9rem; box-shadow: 0 6px 16px -6px hsl(250 70% 45% / .8); }
            .axv-play-ring { position: absolute; inset: -7px; border-radius: 999px; border: 2px solid rgba(255,255,255,.55); animation: axvPlayRing 2.4s ease-out infinite; }
            .axv-video { -webkit-tap-highlight-color: transparent; }
            .axv-video:active .axv-play { transform: translate(-50%,-50%) scale(.9); }

            .axv-stage { position: relative; display: block; width: 100%; aspect-ratio: 16/9; padding: 0; border: 0; border-radius: 22px; overflow: hidden; cursor: pointer; background: linear-gradient(145deg, hsl(250 30% 22%), hsl(250 40% 10%)); box-shadow: 0 34px 70px -28px hsl(250 66% 42% / .62), 0 0 0 1px hsl(250 40% 88%); }
            .axv-stage-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .axv-stage-scrim { position: absolute; inset: 0; background: radial-gradient(120% 90% at 50% 42%, transparent 34%, rgba(8,6,20,.5) 100%), linear-gradient(to top, rgba(8,6,20,.9) 2%, rgba(8,6,20,.15) 42%, transparent 66%); }
            .axv-stage-meta { position: absolute; inset-inline: 0; bottom: 0; z-index: 3; padding: .9rem 1rem 1rem; text-align: start; }
            .axv-stage-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: .4rem; font-family: var(--axv-display); font-weight: 700; font-size: 1.24rem; line-height: 1.14; color: #fff; text-shadow: 0 2px 18px rgba(0,0,0,.5); max-width: 92%; }

            .axv-railhead { display: flex; align-items: baseline; justify-content: space-between; margin: 1.15rem 0 .7rem; }
            .axv-railhead h3 { font-family: var(--axv-display); font-size: .98rem; font-weight: 700; color: var(--axv-ink); margin: 0; }
            .axv-railhint { font-size: .72rem; font-weight: 600; color: var(--axv-soft); }
            .axv-rail { display: flex; gap: .7rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding: 0 0 .3rem; }
            .axv-rail::-webkit-scrollbar { display: none; }
            .axv-railcard { flex: 0 0 min(46%, 230px); scroll-snap-align: start; display: block; padding: 0; border: 0; background: transparent; cursor: pointer; text-align: start; }
            .axv-railcard:last-child { margin-inline-end: .15rem; }
            .axv-railthumb { position: relative; display: block; width: 100%; aspect-ratio: 16/11; border-radius: 13px; overflow: hidden; background: linear-gradient(145deg, hsl(250 30% 22%), hsl(250 40% 10%)); }
            .axv-railthumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .axv-railcap { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: .45rem; font-size: .82rem; font-weight: 600; line-height: 1.28; color: var(--axv-ink); min-height: calc(2 * 1.28 * .82rem); }

            /* Full-bleed player: the 16:9 frame spans the full viewport width
               (clamped so it still fits the height on wide screens), edge to edge. */
            .axv-lightbox { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 0; background: #000; animation: axvFade .2s ease; }
            .axv-lb-frame { position: relative; width: min(100vw, calc(100dvh * 16 / 9)); aspect-ratio: 16/9; overflow: hidden; }
            .axv-lb-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
            .axv-lb-close { position: absolute; top: calc(10px + env(safe-area-inset-top)); inset-inline-end: 12px; z-index: 5; width: 40px; height: 40px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: 1rem; background: rgba(0,0,0,.45); border: 1px solid rgba(255,255,255,.2); cursor: pointer; }

            @keyframes axvPlayRing { 0% { transform: scale(1); opacity: .8; } 100% { transform: scale(1.5); opacity: 0; } }
            @keyframes axvFade { from { opacity: 0; } to { opacity: 1; } }

            /* Desktop: wider stage + roomier rail cards */
            @media (min-width: 900px) {
                .axv-lead { margin-inline: 0; padding-inline: 0; border-bottom: 0; background: none; }
                .axv-stage { border-radius: 26px; }
                .axv-stage-title { font-size: 1.5rem; }
                .axv-railcard { flex-basis: 210px; }
            }
            @media (prefers-reduced-motion: reduce) { .axv-play-ring, .axv-lightbox { animation: none !important; } .axv-play-ring { opacity: .5; } }
        </style>
        @endpush

        @push('scripts')
        <script>
        (function () {
            if (window.__axvInit) return; window.__axvInit = true;
            const VALID = /^[A-Za-z0-9_-]{11}$/; // only ever embed a real YouTube id
            function frame() { return document.getElementById('axvLbFrame'); }
            function box() { return document.getElementById('axvLightbox'); }
            function openVid(id) {
                const f = frame(), b = box(); if (!f || !b || !VALID.test(id)) return;
                // Minimal chrome: no related videos, no annotations, reduced branding.
                // (YouTube has no "progress-bar-only" mode — controls=1 keeps the
                // timeline; controls=0 would remove the progress bar too.)
                f.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + id +
                    '?autoplay=1&controls=1&rel=0&modestbranding=1&iv_load_policy=3&playsinline=1&color=white" title="Video" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
                b.hidden = false; document.body.style.overflow = 'hidden';
            }
            function closeVid() { const f = frame(), b = box(); if (!b) return; b.hidden = true; if (f) f.innerHTML = ''; document.body.style.overflow = ''; }
            // Delegated off document — survives shell swaps / teleport / lang re-render.
            document.addEventListener('click', function (e) {
                const card = e.target.closest('.axv-video');
                if (card) { openVid(card.dataset.vid); return; }
                if (e.target.closest('#axvLbClose') || e.target === box()) closeVid();
            });
            document.addEventListener('keydown', (e) => { const b = box(); if (e.key === 'Escape' && b && !b.hidden) closeVid(); });
        })();
        </script>
        @endpush
    @endonce
@endif
