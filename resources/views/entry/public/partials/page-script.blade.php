{{--
    The two behaviours every public event page needs, and nothing else.

    These pages sit OUTSIDE the app shell, so they cannot borrow its helpers:
    no window.showToast, no shell navigator, no toast container. One copy here,
    included by the event page and by every sub-page, rather than four.

    Expects $e (the payload from App\Events\Support\PublicEvent).
--}}
<script>
    function publicEvent() {
        return {
            /* The quick-facts chips are doors to the sections below them, and a
               chip that does nothing reads as broken. */
            jump(id) {
                const ids = Array.isArray(id) ? id : [id];
                for (const one of ids) {
                    const el = document.getElementById(one);
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
                }
            },
            share() {
                const data = { title: @js($e['title']), url: window.location.href };
                if (navigator.share) { navigator.share(data).catch(() => {}); return; }
                navigator.clipboard?.writeText(data.url).then(
                    () => notice(@js(__('events.public_share_copied'))),
                    () => notice(data.url),
                );
            },
        };
    }

    /* window.showToast belongs to the app shell, which is not loaded here. One
       small on-palette notice instead — never a native dialog. */
    function notice(msg) {
        const n = document.createElement('div');
        n.textContent = msg;
        n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(1.5rem + env(safe-area-inset-bottom));z-index:60;'
            + 'background:#111827;color:#fff;font-size:12.5px;padding:.85rem 1rem;border-radius:1rem;text-align:center;'
            + 'max-width:26rem;margin:0 auto;'
            + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.5);transition:opacity .3s;opacity:0';
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.opacity = '1');
        setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 320); }, 2600);
    }
</script>
