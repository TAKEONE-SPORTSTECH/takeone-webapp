{{--
    "Add to your home screen" — the only route to a genuinely chromeless page.

    Why it exists: fullscreen on the web is gated on a user gesture, and iPhone
    Safari has no `requestFullscreen` at all (see the note in entry/layout).
    An INSTALLED link has no address bar on any platform, opens under the
    event's own name and icon, and the manifest for that is already served
    (`events.public.manifest`, display: standalone). This is the invitation.

    Two platforms, two mechanisms, one card:

      · Android / Chromium — the browser fires `beforeinstallprompt`. We
        `preventDefault()` it to keep Chrome's own mini-infobar out of the way
        and show this instead; tapping Add calls the REAL native prompt. The
        event is captured at the bottom of <body> because it can fire before
        Alpine has started.
      · iOS Safari — no API exists. The card becomes an instruction ("Share →
        Add to Home Screen") because that is genuinely all a visitor needs, and
        a button that cannot work would be worse than a sentence.

    It must never nag:
      · Hidden outright if the page is ALREADY installed (`display-mode:
        standalone`, or `navigator.standalone` on iOS).
      · "Not now" is remembered in localStorage — permanently, per device, not
        per tab. Somebody who declined once declined.
      · It waits for the cover to be gone before appearing, and it never covers
        the entry CTA — it sits above the page's own bottom edge and can be
        dismissed with one tap.

    ⚠️ Styling is a `<style>` block with its own class names, NOT Tailwind
    arbitrary utilities: the bundle in public/build is compiled and a class
    nobody used before has no CSS at all. That mistake broke this page's cover
    once already.

    Teleported to <body>: the layout's <main> carries `.mobile-stagger`, whose
    animation leaves a transform on each child, and a transformed ancestor
    becomes the containing block for `position: fixed`.
--}}
@php
    $a2hsColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#1677FF';
    $a2hsIcon  = $e['icons'][192] ?? null;
@endphp

<div x-data="installPrompt()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-8 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-8 opacity-0"
             class="a2hs-wrap" role="dialog" aria-live="polite"
             aria-label="{{ __('events.a2hs_title') }}">

            <div class="a2hs-card">
                <span class="a2hs-mark" style="background: {{ $a2hsColor }}1f; border-color: {{ $a2hsColor }}55;">
                    @if($a2hsIcon)
                        <img src="{{ $a2hsIcon }}" alt="" width="30" height="30" style="border-radius:8px;">
                    @else
                        <i class="bi bi-phone" style="font-size:17px; color: {{ $a2hsColor }};"></i>
                    @endif
                </span>

                <span class="a2hs-text">
                    <span class="a2hs-title">{{ __('events.a2hs_title') }}</span>
                    <span class="a2hs-body" x-text="ios
                        ? @js(__('events.a2hs_ios_body'))
                        : @js(__('events.a2hs_body'))"></span>
                </span>

                {{-- Android: the real prompt. iOS: nothing to call, so the card
                     is an instruction and this is just an acknowledgement. --}}
                <span class="a2hs-actions">
                    <button type="button" x-show="! ios" @click="install()" class="a2hs-add"
                            style="background: {{ $a2hsColor }}; box-shadow: 0 10px 22px -12px {{ $a2hsColor }};">
                        <i class="bi bi-plus-lg" style="font-size:12px;"></i>{{ __('events.a2hs_add') }}
                    </button>
                    <button type="button" @click="dismiss()" class="a2hs-later"
                            x-text="ios ? @js(__('events.a2hs_ios_got_it')) : @js(__('events.a2hs_later'))"></button>
                </span>
            </div>
        </div>
    </template>
</div>

@once
@push('styles')
<style>
    .a2hs-wrap {
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 70;                     /* under the cover (80), over the page */
        padding: 12px 14px calc(14px + env(safe-area-inset-bottom));
        display: flex;
        justify-content: center;
        pointer-events: none;
    }
    .a2hs-card {
        pointer-events: auto;
        width: 100%;
        max-width: 460px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-radius: 18px;
        color: #fff;
        background: rgba(14, 19, 32, .92);
        border: 1px solid rgba(255, 255, 255, .14);
        -webkit-backdrop-filter: blur(16px);
        backdrop-filter: blur(16px);
        box-shadow: 0 24px 50px -20px rgba(0, 0, 0, .75);
    }
    .a2hs-mark {
        flex: none; width: 42px; height: 42px; border-radius: 12px;
        display: grid; place-items: center; border: 1px solid transparent;
    }
    .a2hs-text { min-width: 0; flex: 1 1 auto; display: block; }
    .a2hs-title { display: block; font-size: 13.5px; font-weight: 800; line-height: 1.25; }
    .a2hs-body  { display: block; font-size: 11px; line-height: 1.35; margin-top: 2px; color: rgba(255,255,255,.62); }
    .a2hs-actions { flex: none; display: flex; align-items: center; gap: 6px; }
    .a2hs-add {
        display: inline-flex; align-items: center; gap: 5px;
        height: 34px; padding: 0 13px; border-radius: 11px;
        font-size: 12.5px; font-weight: 800; color: #fff;
        transition: transform .18s ease;
    }
    .a2hs-add:active { transform: scale(.96); }
    .a2hs-later {
        height: 34px; padding: 0 9px; border-radius: 11px;
        font-size: 11.5px; font-weight: 700; color: rgba(255,255,255,.55);
    }
    @media (hover: hover) { .a2hs-later:hover { color: #fff; } }
    @media (max-width: 380px) {
        .a2hs-card { gap: 9px; padding: 10px; }
        .a2hs-body { display: none; }     /* the title alone still says it */
    }
</style>
@endpush

@push('scripts')
<script>
    /* Captured HERE, at the bottom of <body>, because `beforeinstallprompt` can
       fire before Alpine has started — and the event is only useful if we kept
       a reference to it. */
    window.__takeoneInstallEvent = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();          // suppress Chrome's own bar; we show ours
        window.__takeoneInstallEvent = e;
        window.dispatchEvent(new CustomEvent('takeone:installable'));
    });

    (function () {
        var register = function () {
            if (! window.Alpine || window.__installPromptRegistered) return;
            window.__installPromptRegistered = true;

            window.Alpine.data('installPrompt', function () {
                return {
                    open: false,
                    ios: false,
                    KEY: 'takeone:a2hs-dismissed',
                    timer: null,

                    init() {
                        // Already installed? Then there is nothing to offer.
                        var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                            || window.navigator.standalone === true;
                        if (standalone) return;

                        try { if (localStorage.getItem(this.KEY) === '1') return; } catch (e) { /* cannot remember; still fine to ask */ }

                        var ua = window.navigator.userAgent || '';
                        var isIOS = /iPad|iPhone|iPod/.test(ua)
                            || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);   // iPadOS lies
                        var isSafari = /Safari/.test(ua) && ! /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

                        // iOS Safari has no install API — the card becomes the
                        // instruction. Any other iOS browser cannot install at
                        // all, so it is not asked to.
                        if (isIOS) {
                            if (! isSafari) return;
                            this.ios = true;
                            this.armWhenClear();

                            return;
                        }

                        // Chromium: only ever when the browser says it is installable.
                        if (window.__takeoneInstallEvent) this.armWhenClear();
                        window.addEventListener('takeone:installable', () => this.armWhenClear());
                    },

                    /* Wait for the cover to be gone. The cover locks the page by
                       setting overflow:hidden on <html>, which is the honest
                       signal that a full-screen overlay is up; appearing behind
                       it would spend the invitation on nobody. Gives up after
                       ~40s rather than polling forever. */
                    armWhenClear() {
                        if (this.open || this.timer) return;

                        var tries = 0;
                        var clear = () => document.documentElement.style.overflow !== 'hidden';

                        this.timer = setInterval(() => {
                            tries++;
                            if (clear()) {
                                clearInterval(this.timer); this.timer = null;
                                setTimeout(() => { this.open = true; }, 1200);
                            } else if (tries > 20) {
                                clearInterval(this.timer); this.timer = null;
                            }
                        }, 2000);
                    },

                    async install() {
                        var e = window.__takeoneInstallEvent;
                        if (! e) { this.dismiss(); return; }

                        this.open = false;
                        try {
                            e.prompt();
                            await e.userChoice;          // accepted or not, the browser will not offer this event again
                        } catch (err) { /* dismissed at the OS level; nothing to do */ }

                        window.__takeoneInstallEvent = null;
                        this.remember();
                    },

                    dismiss() {
                        this.open = false;
                        this.remember();
                    },

                    /* Per DEVICE, not per tab: declining once is an answer, and
                       being asked again on every visit is what makes these
                       banners hated. */
                    remember() {
                        try { localStorage.setItem(this.KEY, '1'); } catch (e) { /* nothing to remember */ }
                    },
                };
            });
        };

        if (window.Alpine) { register(); }
        document.addEventListener('alpine:init', register);
    })();
</script>
@endpush
@endonce
