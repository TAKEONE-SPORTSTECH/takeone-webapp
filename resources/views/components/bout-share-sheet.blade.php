@props([
    /* The event's uuid and the bout's number — what the three share doors
       (me.events.bout.video.share[.request|.file]) are addressed by. */
    'event',
    'matchNo',

    /* The event's colour for the band. Hex only — the band is `#hex → #hexb0`
       and that alpha suffix is hex-only (Design Rule #8). Whitelisted below. */
    'color' => '#7c3aed',

    /* The band's headline: "Red vs Blue". */
    'heading' => '',

    /* The footage's length in seconds, for "about N min" while it is made. */
    'duration' => 0,

    /* 'row' draws this component's own tappable row where it is placed (the
       mobile Match tab); 'none' draws nothing, and the host opens the sheet
       from its own button with `BoutShareSheet.open()` (the desktop actions
       row, whose buttons have their own look). */
    'trigger' => 'row',
])
{{--
    Send this bout — to WhatsApp and every other app (App\Media\BoutShare).

    Two cards: the whole bout, and its highlights. Each says where it stands
    (not made · being made · ready · failed) and does the one next thing:
    make it, wait for it, or SEND it. Sending hands the finished file to the
    phone's own share sheet together with the points as text, because WhatsApp
    and most other apps ignore chapters. Where a browser cannot share a file
    (a desktop, the Android app's WebView) the file is saved instead and the
    points are copied, ready to paste beside it.

    Standalone: its own stylesheet, icons (the self-hosted Bootstrap Icons),
    runtime and trigger. The bout pages are their own documents with no app
    bundle and no style/script stacks, so nothing here leans on either. The
    sheet moves itself to <body> so no transformed ancestor can trap it.

    Decides nothing about permission: every door re-checks that the viewer may
    watch this bout, and a highlights card whose door answers 404 (a bout with
    no scoring moments) is simply not shown. Every server value is set with
    textContent / an attribute, never innerHTML.

    Being told it is ready: these pages carry no realtime client, so while a
    file is being made AND the sheet is open, the sheet re-reads the status
    door every few seconds and stops the moment it settles or the sheet
    closes. The person is also NOTIFIED when it is ready (RenderBoutShare),
    which reaches them wherever they are; that link reopens this sheet.
--}}
@php
    $sheetColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? strtolower($color) : '#7c3aed';
    $doors = [];
    foreach (\App\Media\BoutShare::KINDS as $k) {
        $p = ['event' => $event, 'matchNo' => (int) $matchNo, 'kind' => $k];
        $doors[$k] = [
            'status' => route('me.events.bout.video.share', $p),
            'request' => route('me.events.bout.video.share.request', $p),
        ];
    }
    $minutes = max(1, (int) ceil(((int) $duration) / 60));
    $t = fn (string $key, array $r = []) => __('events::events.'.$key, $r);
    // Pre-assigned: Blade's bracket matcher cannot take a literal array inside @json().
    $sheetLabels = [
        'ready' => $t('bout_share_ready'),
        'making' => $t('bout_share_making'),
        'failed' => $t('bout_share_failed_short'),
        'send' => $t('bout_share_send'),
        'preparing' => $t('bout_share_preparing'),
        'copied' => $t('bout_share_copied'),
        'copy' => $t('bout_share_copy'),
        'saved' => $t('bout_share_saved'),
        'error' => $t('bout_share_error'),
    ];
@endphp

@if ($trigger === 'row')
<button type="button" class="bss-row" onclick="window.BoutShareSheet && window.BoutShareSheet.open()">
    <span class="bss-row-tile" style="background: linear-gradient(150deg, {{ $sheetColor }}, {{ $sheetColor }}b0);"><i class="bi bi-send-fill"></i></span>
    <span class="bss-row-text">
        <span class="bss-row-label">{{ $t('bout_share_button') }}</span>
        <span class="bss-row-hint">{{ $t('bout_share_row_hint') }}</span>
    </span>
    <i class="bi bi-chevron-right bss-row-chev"></i>
</button>
@endif

<div class="bss" id="boutShareSheet" hidden
     data-doors='@json($doors)'
     data-csrf="{{ csrf_token() }}"
     aria-modal="true" role="dialog" aria-labelledby="bssTitle">
    <div class="bss-backdrop" data-bss-close></div>

    <div class="bss-panel">
        {{-- Header band — Design Rule #8 --}}
        <div class="bss-band" style="background: linear-gradient(150deg, {{ $sheetColor }}, {{ $sheetColor }}b0);">
            <div class="bss-band-circle"></div>
            <div class="bss-handle"></div>
            <div class="bss-band-row">
                <span class="bss-band-tile"><i class="bi bi-send-fill"></i></span>
                <div class="bss-band-text">
                    <h3 class="bss-title" id="bssTitle">{{ $t('bout_share_title') }}</h3>
                    <p class="bss-sub">{{ $heading }}</p>
                </div>
                <button type="button" class="bss-x" data-bss-close aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
            </div>
            <p class="bss-band-note">{{ $t('bout_share_sub') }}</p>
        </div>

        <div class="bss-body">
            @foreach (\App\Media\BoutShare::KINDS as $k)
            <section class="bss-card" data-bss-kind="{{ $k }}" data-state="loading">
                <div class="bss-card-head">
                    <span class="bss-card-tile"><i class="bi {{ $k === 'full' ? 'bi-film' : 'bi-stars' }}"></i></span>
                    <div class="bss-card-text">
                        <span class="bss-card-label">{{ $t($k === 'full' ? 'bout_share_full' : 'bout_share_highlights') }}</span>
                        <span class="bss-card-hint">{{ $t($k === 'full' ? 'bout_share_full_hint' : 'bout_share_highlights_hint') }}</span>
                    </div>
                    <span class="bss-pill" data-bss-pill></span>
                </div>

                {{-- loading --}}
                <div class="bss-face" data-face="loading"><span class="bss-skeleton"></span></div>

                {{-- idle --}}
                <div class="bss-face" data-face="idle">
                    <button type="button" class="bss-btn bss-btn-primary" data-bss-make>
                        <i class="bi bi-magic"></i>{{ $t('bout_share_make') }}
                    </button>
                </div>

                {{-- working --}}
                <div class="bss-face" data-face="working">
                    <div class="bss-progress"><span></span></div>
                    <p class="bss-note">{{ $t('bout_share_making_hint', ['min' => $minutes]) }}</p>
                </div>

                {{-- ready --}}
                <div class="bss-face" data-face="ready">
                    <div class="bss-actions">
                        <button type="button" class="bss-btn bss-btn-primary" data-bss-send>
                            <i class="bi bi-send-fill"></i><span data-bss-send-label>{{ $t('bout_share_send') }}</span>
                        </button>
                        <a class="bss-btn bss-btn-ghost" data-bss-download href="#" download>
                            <i class="bi bi-download"></i>{{ $t('bout_share_download') }}
                        </a>
                    </div>
                </div>

                {{-- failed --}}
                <div class="bss-face" data-face="failed">
                    <p class="bss-note bss-note-bad">{{ $t('bout_share_failed') }}</p>
                    <button type="button" class="bss-btn bss-btn-ghost" data-bss-make>
                        <i class="bi bi-arrow-clockwise"></i>{{ $t('bout_share_retry') }}
                    </button>
                </div>
            </section>
            @endforeach

            <section class="bss-points" data-bss-points hidden>
                <div class="bss-points-head">
                    <span>{{ $t('bout_share_points') }}</span>
                    <button type="button" class="bss-copy" data-bss-copy>
                        <i class="bi bi-copy"></i><span data-bss-copy-label>{{ $t('bout_share_copy') }}</span>
                    </button>
                </div>
                <pre class="bss-points-text" data-bss-message></pre>
            </section>

            <p class="bss-status" data-bss-status role="status" aria-live="polite"></p>
        </div>
    </div>
</div>

@once
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<style>
/* ── The trigger row (mobile Match tab) ─────────────────────────────────── */
.bss-row { display: flex; align-items: center; gap: 12px; width: calc(100% - 32px); margin: 14px 16px 4px; padding: 12px 14px;
    background: #12151b; border: 1px solid #1c212a; border-radius: 14px; color: #e8eaed; text-align: start;
    font-family: inherit; cursor: pointer; -webkit-tap-highlight-color: transparent; transition: transform .12s ease, background .15s ease; }
.bss-row:active { transform: scale(.98); background: #161a21; }
.bss-row-tile { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 17px; flex: none; }
.bss-row-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
.bss-row-label { font-size: 15px; font-weight: 700; }
.bss-row-hint { font-size: 12px; color: #8a93a1; }
.bss-row-chev { color: #6b7482; }
[dir="rtl"] .bss-row-chev { transform: rotate(180deg); }

/* ── The sheet ──────────────────────────────────────────────────────────── */
.bss[hidden] { display: none !important; }
.bss { position: fixed; inset: 0; z-index: 9000; display: flex; align-items: flex-end; justify-content: center;
    font-family: 'Archivo', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #e8eaed; }
.bss-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.6); opacity: 0; transition: opacity .2s ease; }
.bss.is-open .bss-backdrop { opacity: 1; }
.bss-panel { position: relative; width: 100%; max-width: 520px; max-height: 92vh; display: flex; flex-direction: column;
    background: #0f1217; border: 1px solid #1c212a; border-bottom: 0; border-radius: 24px 24px 0 0; overflow: hidden;
    transform: translateY(100%); transition: transform .28s cubic-bezier(.2,.8,.2,1); box-shadow: 0 -20px 60px rgba(0,0,0,.5); }
.bss.is-open .bss-panel { transform: none; }
@media (min-width: 768px) {
    .bss { align-items: center; }
    .bss-panel { border-radius: 24px; border-bottom: 1px solid #1c212a; transform: translateY(24px) scale(.98); opacity: 0;
        transition: transform .22s ease, opacity .22s ease; }
    .bss.is-open .bss-panel { transform: none; opacity: 1; }
    .bss-handle { display: none; }
}

.bss-band { flex-shrink: 0; position: relative; overflow: hidden; padding: 12px 20px 16px; color: #fff; }
.bss-band-circle { position: absolute; right: -32px; top: -40px; width: 144px; height: 144px; border-radius: 50%; background: rgba(255,255,255,.1); }
.bss-handle { position: relative; margin: 0 auto 12px; width: 40px; height: 4px; border-radius: 99px; background: rgba(255,255,255,.4); }
.bss-band-row { position: relative; display: flex; align-items: flex-start; gap: 12px; }
.bss-band-tile { width: 48px; height: 48px; border-radius: 16px; background: rgba(255,255,255,.2); display: grid; place-items: center; font-size: 20px; flex: none; }
.bss-band-text { min-width: 0; flex: 1; }
.bss-title { margin: 0; font-size: 18px; font-weight: 900; line-height: 1.15; }
.bss-sub { margin: 3px 0 0; font-size: 12px; color: rgba(255,255,255,.85); }
.bss-x { width: 36px; height: 36px; border-radius: 50%; flex: none; display: grid; place-items: center; color: #fff; cursor: pointer;
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); backdrop-filter: blur(8px); transition: transform .12s ease; }
.bss-x:active { transform: scale(.9); }
.bss-band-note { position: relative; margin: 12px 0 0; font-size: 12px; line-height: 1.4; color: rgba(255,255,255,.88); }

.bss-body { flex: 1; overflow-y: auto; padding: 16px 16px calc(16px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 12px; }

.bss-card { background: #12151b; border: 1px solid #1c212a; border-radius: 16px; padding: 14px; display: flex; flex-direction: column; gap: 12px;
    animation: bssIn .3s ease both; }
.bss-card + .bss-card { animation-delay: .05s; }
.bss-card[hidden] { display: none; }
.bss-card[data-state="ready"] { border-color: rgba(74,222,128,.35); }
.bss-card-head { display: flex; align-items: center; gap: 12px; }
.bss-card-tile { width: 40px; height: 40px; border-radius: 12px; background: #1a1f28; color: #c9cfd8; display: grid; place-items: center; font-size: 18px; flex: none; }
.bss-card-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
.bss-card-label { font-size: 15px; font-weight: 700; }
.bss-card-hint { font-size: 12px; color: #8a93a1; line-height: 1.35; }
.bss-pill { font-size: 11px; font-weight: 700; letter-spacing: .04em; padding: 4px 9px; border-radius: 99px; background: #1a1f28; color: #8a93a1; white-space: nowrap; }
.bss-pill:empty { display: none; }
.bss-card[data-state="ready"] .bss-pill { background: rgba(74,222,128,.14); color: #4ade80; }
.bss-card[data-state="working"] .bss-pill { background: rgba(251,191,36,.14); color: #fbbf24; }
.bss-card[data-state="failed"] .bss-pill { background: rgba(248,113,113,.14); color: #f87171; }

.bss-face { display: none; }
.bss-card[data-state="loading"] [data-face="loading"],
.bss-card[data-state="idle"] [data-face="idle"],
.bss-card[data-state="working"] [data-face="working"],
.bss-card[data-state="ready"] [data-face="ready"],
.bss-card[data-state="failed"] [data-face="failed"] { display: flex; flex-direction: column; gap: 10px; animation: bssIn .22s ease both; }

.bss-actions { display: flex; gap: 8px; }
.bss-actions .bss-btn-primary { flex: 1; }
.bss-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 16px; border-radius: 12px;
    font-family: inherit; font-size: 14px; font-weight: 700; line-height: 1; cursor: pointer; text-decoration: none;
    transition: transform .12s ease, filter .15s ease, opacity .15s; }
.bss-btn:active { transform: scale(.97); }
.bss-btn[disabled] { opacity: .55; cursor: default; }
.bss-btn-primary { background: hsl(250 65% 65%); color: #fff; border: 0; }
.bss-btn-primary:hover { filter: brightness(1.06); }
.bss-btn-ghost { background: transparent; color: #e8eaed; border: 1px solid #2a303b; }
.bss-btn-ghost:hover { background: #161a21; }

.bss-progress { height: 6px; border-radius: 99px; background: #1a1f28; overflow: hidden; }
.bss-progress span { display: block; width: 40%; height: 100%; border-radius: 99px; background: linear-gradient(90deg, transparent, #fbbf24, transparent); animation: bssSweep 1.4s ease-in-out infinite; }
.bss-note { margin: 0; font-size: 12px; line-height: 1.45; color: #8a93a1; }
.bss-note-bad { color: #f87171; }
.bss-skeleton { display: block; height: 44px; border-radius: 12px; background: linear-gradient(90deg, #161a21, #1d222b, #161a21); background-size: 200% 100%; animation: bssShimmer 1.2s linear infinite; }

.bss-points { background: #12151b; border: 1px solid #1c212a; border-radius: 16px; padding: 12px 14px; }
.bss-points[hidden] { display: none; }
.bss-points-head { display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #8a93a1; }
.bss-copy { display: inline-flex; align-items: center; gap: 6px; background: none; border: 0; color: hsl(250 75% 78%); font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; padding: 6px 4px; }
.bss-points-text { margin: 8px 0 0; white-space: pre-wrap; word-break: break-word; font: 500 12.5px/1.6 ui-monospace, 'JetBrains Mono', monospace; color: #c9cfd8; max-height: 180px; overflow-y: auto; }
.bss-status { margin: 0; min-height: 1em; font-size: 12px; text-align: center; color: #8a93a1; }

@keyframes bssIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
@keyframes bssSweep { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
@keyframes bssShimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
@media (prefers-reduced-motion: reduce) {
    .bss-panel, .bss-backdrop { transition: none; }
    .bss-card, .bss-face, .bss-progress span, .bss-skeleton { animation: none !important; }
}
</style>
<script>
(function () {
    if (window.BoutShareSheet) { return; }

    var POLL_MS = 5000;
    var L = @json($sheetLabels);

    var root = null, doors = {}, csrf = '', state = {}, timer = null, open = false, prepared = {};

    function el(sel, scope) { return (scope || root).querySelector(sel); }
    function card(kind) { return root.querySelector('[data-bss-kind="' + kind + '"]'); }
    function setStatus(text) { var s = el('[data-bss-status]'); if (s) { s.textContent = text || ''; } }

    function size(bytes) {
        if (!bytes) { return ''; }
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(bytes >= 104857600 ? 0 : 1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    // Paint one card from what its door said. null = this cut does not exist.
    function paint(kind, data) {
        var c = card(kind);
        if (!c) { return; }
        if (data === null) { c.hidden = true; delete state[kind]; return; }

        c.hidden = false;
        state[kind] = data;
        c.setAttribute('data-state', data.state);

        el('[data-bss-pill]', c).textContent = data.state === 'ready' ? (L.ready + (data.bytes ? ' · ' + size(data.bytes) : ''))
            : data.state === 'working' ? L.making
            : data.state === 'failed' ? L.failed : '';

        if (data.state === 'ready' && data.file) {
            var dl = el('[data-bss-download]', c);
            dl.setAttribute('href', data.file);
            dl.setAttribute('download', data.filename || 'bout.mp4');
            prepare(kind);
        }

        if (data.message) {
            el('[data-bss-message]').textContent = data.message;
            el('[data-bss-points]').hidden = false;
        }

        schedule();
    }

    function getJson(url, opts) {
        return fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, opts || {}))
            .then(function (r) {
                if (r.status === 404) { return null; }
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            });
    }

    function refresh(kind) {
        return getJson(doors[kind].status).then(function (d) { paint(kind, d); })
            .catch(function () { setStatus(L.error); });
    }

    // Re-read only while something is being made and somebody is looking.
    function schedule() {
        clearTimeout(timer);
        if (!open) { return; }
        var waiting = Object.keys(state).filter(function (k) { return state[k] && state[k].state === 'working'; });
        if (!waiting.length) { return; }
        timer = setTimeout(function () { waiting.forEach(refresh); }, POLL_MS);
    }

    function make(kind) {
        var c = card(kind);
        c.setAttribute('data-state', 'working');
        setStatus('');
        getJson(doors[kind].request, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf } })
            .then(function (d) { paint(kind, d); })
            .catch(function () { c.setAttribute('data-state', 'idle'); setStatus(L.error); });
    }

    /*
     * Sharing a FILE needs the file in hand at the moment of the tap — a
     * browser only opens the share sheet straight off a gesture, and a 40 MB
     * download in between uses that up. So a ready file is fetched as soon as
     * it is ready, and Send hands over what is already here. Only where the
     * browser can share files at all; everywhere else Send saves instead.
     */
    function canShareFiles() {
        try { return !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'x.mp4', { type: 'video/mp4' })] })); }
        catch (e) { return false; }
    }

    function prepare(kind) {
        if (prepared[kind] || !canShareFiles()) { return; }
        var c = card(kind), btn = el('[data-bss-send]', c), label = el('[data-bss-send-label]', c);
        btn.disabled = true;
        label.textContent = L.preparing;
        prepared[kind] = fetch(state[kind].file, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.blob(); })
            .then(function (b) { return new File([b], state[kind].filename || 'bout.mp4', { type: 'video/mp4' }); })
            .catch(function () { return null; })
            .then(function (file) { btn.disabled = false; label.textContent = L.send; return file; });
    }

    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return false; });
        }
        try {
            var t = document.createElement('textarea');
            t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            var ok = document.execCommand('copy');
            t.remove();
            return Promise.resolve(ok);
        } catch (e) { return Promise.resolve(false); }
    }

    function saveInstead(kind) {
        el('[data-bss-download]', card(kind)).click();
        copy((state[kind] || {}).message || '').then(function () { setStatus(L.saved); });
    }

    function send(kind) {
        var d = state[kind];
        if (!d || d.state !== 'ready') { return; }
        if (!prepared[kind]) { saveInstead(kind); return; }

        prepared[kind].then(function (file) {
            if (!file || !navigator.canShare({ files: [file] })) { saveInstead(kind); return; }
            navigator.share({ files: [file], title: (d.message || '').split('\n')[0], text: d.message || '' })
                .catch(function (e) { if (e && e.name !== 'AbortError') { saveInstead(kind); } });
        });
    }

    function show() {
        root.hidden = false;
        open = true;
        requestAnimationFrame(function () { root.classList.add('is-open'); });
        document.documentElement.style.overflow = 'hidden';
        Object.keys(doors).forEach(refresh);
    }

    function hide() {
        open = false;
        clearTimeout(timer);
        root.classList.remove('is-open');
        document.documentElement.style.overflow = '';
        setTimeout(function () { if (!open) { root.hidden = true; } }, 260);
    }

    function init() {
        root = document.getElementById('boutShareSheet');
        if (!root) { return; }

        // Out from under any transformed ancestor: a fixed sheet inside one is
        // positioned against that ancestor, not the screen.
        if (root.parentNode !== document.body) { document.body.appendChild(root); }

        try { doors = JSON.parse(root.getAttribute('data-doors') || '{}'); } catch (e) { doors = {}; }
        csrf = root.getAttribute('data-csrf') || '';

        root.addEventListener('click', function (e) {
            if (e.target.closest('[data-bss-close]')) { hide(); return; }

            if (e.target.closest('[data-bss-copy]')) {
                copy(el('[data-bss-message]').textContent).then(function (ok) {
                    if (!ok) { return; }
                    var lab = el('[data-bss-copy-label]');
                    lab.textContent = L.copied;
                    setTimeout(function () { lab.textContent = L.copy; }, 1800);
                });
                return;
            }

            var c = e.target.closest('[data-bss-kind]');
            if (!c) { return; }
            var kind = c.getAttribute('data-bss-kind');

            if (e.target.closest('[data-bss-make]')) { make(kind); }
            else if (e.target.closest('[data-bss-send]')) { send(kind); }
        });

        document.addEventListener('keydown', function (e) { if (open && e.key === 'Escape') { hide(); } });

        // If the page ever carries the realtime client, its signal is the
        // faster path than the next re-read.
        window.addEventListener('realtime:events', function (e) {
            var d = e.detail || {};
            if (open && d.action === 'bout-share' && doors[d.kind]) { refresh(d.kind); }
        });

        // Arriving from the "ready" notification opens the sheet.
        if (/[?&]send=(full|highlights)\b/.test(window.location.search)) { show(); }
    }

    window.BoutShareSheet = {
        open: function () { if (root) { show(); } },
        close: function () { if (root) { hide(); } },
    };

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
@endonce
