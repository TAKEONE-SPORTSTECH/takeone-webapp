{{--
    "Start the competition?" — the one prompt, for every sport.

    A console refuses a result when the day has not been started, and that
    refusal has to carry a way FORWARD: the official is at a scoring table with
    a finished bout and a hall waiting, and the button that would clear it is on
    somebody else's screen. Refusing with no offer is what cost a championship
    its flow on 2026-09-18.

    The RULE is shared (App\Events\Support\ResultFiling), so the prompt is too —
    one wording, one look, one behaviour across jiu-jitsu, karate and taekwondo.
    Writing it into each console would be three copies of a sentence that has to
    stay identical, which is how the hall-screen layer already diverged.

    Standalone: it brings its own markup, styles and behaviour, needs no Alpine
    and no layout, and every id is namespaced. A console includes it once and
    calls:

        StartDayPrompt.ask(function () { … })

    ⚠️ These consoles do NOT extend a layout, so window.confirmAction and
    window.showToast are both out of reach — hence its own furniture rather
    than the platform's.
--}}
@once
<div id="tkStartDayScrim" hidden role="dialog" aria-modal="true" aria-labelledby="tkStartDayTitle">
    <div id="tkStartDayCard">
        <h2 id="tkStartDayTitle">{{ __('events::events.start_day_title') }}</h2>
        <p id="tkStartDayBody">{{ __('events::events.start_day_body') }}</p>
        <div id="tkStartDayActions">
            <button type="button" id="tkStartDayCancel">{{ __('shared.cancel') }}</button>
            <button type="button" id="tkStartDayOk">{{ __('events::events.start_day_confirm') }}</button>
        </div>
    </div>
</div>

<style>
    /* Sized for arm's length at the edge of a mat, not for a desk. */
    #tkStartDayScrim{position:fixed;inset:0;z-index:90;display:flex;align-items:center;
        justify-content:center;background:rgba(0,0,0,.72);padding:24px}
    #tkStartDayScrim[hidden]{display:none!important}
    #tkStartDayCard{width:100%;max-width:560px;background:#14161c;color:#fff;border-radius:20px;
        border:1px solid rgba(255,255,255,.14);padding:26px 26px 20px;
        box-shadow:0 24px 60px rgba(0,0,0,.6)}
    #tkStartDayTitle{margin:0 0 10px;font-size:26px;font-weight:800;letter-spacing:.01em}
    #tkStartDayBody{margin:0 0 22px;font-size:17px;line-height:1.5;color:rgba(255,255,255,.78)}
    #tkStartDayActions{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap}
    #tkStartDayActions button{min-height:52px;padding:0 22px;border-radius:12px;font-size:17px;
        font-weight:700;border:0;cursor:pointer}
    #tkStartDayCancel{background:rgba(255,255,255,.12);color:#fff}
    #tkStartDayOk{background:#1f7a3d;color:#fff}
    @media (max-width:420px){#tkStartDayActions button{flex:1 1 100%}}
</style>

<script>
window.StartDayPrompt = window.StartDayPrompt || (function () {
    var scrim, ok, cancel, pending = null;

    function grab() {
        scrim  = document.getElementById('tkStartDayScrim');
        ok     = document.getElementById('tkStartDayOk');
        cancel = document.getElementById('tkStartDayCancel');
        return !!scrim;
    }

    function close() { if (scrim) scrim.hidden = true; pending = null; }

    function ask(onConfirm) {
        if (!grab()) { onConfirm(); return; }   // no furniture: do not block the official
        pending = onConfirm;
        scrim.hidden = false;
        ok.focus();
    }

    document.addEventListener('click', function (e) {
        if (!grab()) return;
        if (e.target === ok)     { var run = pending; close(); if (run) run(); }
        else if (e.target === cancel || e.target === scrim) { close(); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && scrim && !scrim.hidden) close();
    });

    return { ask: ask, close: close };
})();
</script>
@endonce
