{{--
    Watching a bout back — the standalone "Match Video Page" design, used as it
    was handed over.

    GENERATED. The source of truth is the design file in `drafts/` plus the
    build script `drafts/build-bout-video-page.py`; edit those and re-run it
    rather than patching this file, or the next regeneration will discard the
    patch.

    What changed against the design: its data (Play's match, Play's comments,
    Play's recommendations) is now ours, and every URL that pointed at
    video.takeone.bh points at this platform instead — that host was
    disconnected on 2026-08-27 and nothing here may call it.
--}}
@php
    use Illuminate\Support\Str;

    $play      = $play ?? [];
    $video     = $play['video'] ?? [];
    $red       = $bout['a']['colour'] === 'red' ? $bout['a'] : $bout['b'];
    $blue      = $bout['a']['colour'] === 'blue' ? $bout['a'] : $bout['b'];
    $boutTitle = trim(($bout['a']['name'] ?? '').' vs '.($bout['b']['name'] ?? ''));
    $stage     = $bout['round'] ?: $boutTitle;

    // MM:SS for the highlight rows the design renders server-side.
    $clock = function ($seconds) {
        $whole = max(0, (int) floor((float) $seconds));
        return intdiv($whole, 60).':'.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT);
    };

    // M:SS:mmm — the same instant to the millisecond, for the scoring list.
    // A point is a frame, not a second: two exchanges inside the same second
    // are a different moment to anyone reviewing the tape, and MM:SS printed
    // them as the same time.
    $stamp = function ($seconds) {
        $t = max(0.0, (float) $seconds);
        $whole = (int) floor($t);
        return intdiv($whole, 60)
            .':'.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) ((int) round(($t - $whole) * 1000)), 3, '0', STR_PAD_LEFT);
    };
@endphp




<script>
/*
 * Match review (portrait Highlights) — tabs, seeking, clock.
 *
 * Seeking deliberately calls the page's OWN helpers (playPointReplay /
 * playReviewSlowmo) rather than setting currentTime directly, so a tap here
 * behaves exactly like a tap in the desktop pane: same replay, same slow-mo,
 * same coach-note overlay.
 */
(function () {
    if (window.__mrvInit) return;
    window.__mrvInit = true;

    function root() { return document.getElementById('matchReview'); }

    document.addEventListener('click', function (e) {
        // the fullscreen drawer's tabs
        const fsTab = e.target.closest('.fsh-tab');
        if (fsTab) {
            const r = document.getElementById('fsHighlights');
            if (r) {
                const want = fsTab.dataset.fshTab;
                r.querySelectorAll('.fsh-tab').forEach(t => t.classList.toggle('is-on', t === fsTab));
                r.querySelectorAll('.fsh-list').forEach(l => l.classList.toggle('is-on', l.dataset.fshPanel === want));
            }
            return;
        }

        const tab = e.target.closest('.mrv-tab');
        if (tab) {
            const r = root(); if (!r) return;
            const want = tab.dataset.mrvTab;
            r.querySelectorAll('.mrv-tab').forEach(t => t.classList.toggle('is-on', t === tab));
            r.querySelectorAll('.mrv-pane').forEach(p => p.classList.toggle('is-on', p.dataset.mrvPanel === want));
            return;
        }

        // Edit / delete live inside a seekable row — their own handlers must win.
        if (e.target.closest('.mrv-rowtools')) return;

        const seek = e.target.closest('[data-mrv-seek]');
        if (seek) {
            const t = parseFloat(seek.dataset.mrvSeek);
            if (!isFinite(t)) return;
            const v = document.querySelector('video');
            // A coach note is identified by its CARD, so tapping anywhere on the
            // card behaves the same as tapping the timestamp inside it.
            const card = seek.closest('[data-mrv-rev]');
            if (card) {
                // Hand it to the page's own coach-note player — same overlay,
                // same replay, same automatic clear-down as the desktop pane.
                const txt = card.querySelector('.mrv-note-text, .fsh-note-text')?.textContent?.trim();
                if (typeof window.playCoachReview === 'function') {
                    window.playCoachReview(card.dataset.mrvRev, txt, t);
                    return;
                }
                if (typeof window.showCoachNoteOverlay === 'function' && txt) window.showCoachNoteOverlay(txt, null);
                if (v) { try { v.currentTime = Math.max(0, t); v.play?.(); } catch (_) {} }
            } else if (typeof window.playPointReplay === 'function') {
                window.playPointReplay(t);
            } else if (v) {
                try { v.currentTime = Math.max(0, t); v.play?.(); } catch (_) {}
            }
        }
    });

})();
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $boutTitle }} | {{ $e['title'] }}</title>

    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
    <script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
    <style>
        /* TakeOne Cropper Modal — must be in the head, not in the component,
           because page-level uses render those styles inside #main and SPA
           navigation later wipes that scope. */
        .tc-overlay {
            display: none; position: fixed; inset: 0; z-index: 10100;
            background: rgba(0,0,0,.82); backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .tc-overlay.open { display: flex; animation: tcFadeIn .18s ease; }
        @keyframes tcFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .tc-modal {
            background: #141414; border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px; width: min(540px, 95vw);
            box-shadow: 0 24px 80px rgba(0,0,0,.75);
            overflow: hidden; animation: tcSlideUp .2s cubic-bezier(.34,1.3,.64,1);
        }
        @keyframes tcSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .tc-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px 14px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .tc-modal-title {
            font-size: 15px; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 8px;
        }
        .tc-modal-title i { color: #ef4444; }
        .tc-modal-close {
            background: none; border: none; color: rgba(255,255,255,.45);
            font-size: 20px; cursor: pointer; line-height: 1; padding: 4px 6px;
            border-radius: 6px; transition: color .15s, background .15s;
        }
        .tc-modal-close:hover { color: #fff; background: rgba(255,255,255,.08); }
        .tc-modal-body { padding: 16px 20px; }
        .tc-file-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .tc-file-label {
            display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
            height: 36px; padding: 0 14px; border-radius: 8px;
            background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
            color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
            transition: background .15s;
        }
        .tc-file-label:hover { background: rgba(255,255,255,.13); }
        .tc-file-name {
            font-size: 12px; color: rgba(255,255,255,.38); flex: 1;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .tc-canvas {
            width: 100%; height: 320px; background: #0d0d0d;
            border-radius: 10px; border: 1px solid rgba(255,255,255,.07);
            overflow: hidden; position: relative;
        }
        .tc-placeholder {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: rgba(255,255,255,.2); gap: 10px; pointer-events: none;
        }
        .tc-placeholder i { font-size: 42px; }
        .tc-placeholder span { font-size: 13px; }
        .tc-controls { display: flex; gap: 14px; margin-top: 14px; }
        .tc-control { flex: 1; }
        .tc-control-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
            color: rgba(255,255,255,.35); margin-bottom: 6px; display: flex; align-items: center; gap: 5px;
        }
        .tc-range {
            -webkit-appearance: none; appearance: none; width: 100%; height: 3px;
            background: rgba(255,255,255,.12); border-radius: 3px; outline: none; cursor: pointer;
        }
        .tc-range::-webkit-slider-thumb {
            -webkit-appearance: none; width: 15px; height: 15px;
            border-radius: 50%; background: #ef4444; cursor: pointer;
            box-shadow: 0 0 0 3px rgba(239,68,68,.2); transition: box-shadow .15s;
        }
        .tc-range::-webkit-slider-thumb:hover { box-shadow: 0 0 0 5px rgba(239,68,68,.3); }
        .tc-range::-moz-range-thumb {
            width: 15px; height: 15px; border: none;
            border-radius: 50%; background: #ef4444; cursor: pointer;
        }
        .tc-modal-footer {
            padding: 12px 20px 18px; display: flex; gap: 8px; justify-content: flex-end;
            border-top: 1px solid rgba(255,255,255,.07); flex-wrap: wrap;
        }
        .tc-btn {
            display: inline-flex; align-items: center; gap: 7px;
            height: 38px; padding: 0 18px; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; border: none; font-family: inherit;
            transition: background .15s, transform .1s, opacity .15s;
        }
        .tc-btn-ghost {
            background: rgba(255,255,255,.07); color: rgba(255,255,255,.75);
            border: 1px solid rgba(255,255,255,.12);
        }
        .tc-btn-ghost:hover { background: rgba(255,255,255,.13); color: #fff; }
        .tc-btn-as-is {
            background: rgba(255,255,255,.05); color: rgba(255,255,255,.55);
            border: 1px solid rgba(255,255,255,.09); margin-right: auto;
        }
        .tc-btn-as-is:hover { background: rgba(255,255,255,.1); color: rgba(255,255,255,.85); }
        .tc-btn-as-is:disabled { opacity: .3; cursor: not-allowed; }
        .tc-btn-primary { background: #ef4444; color: #fff; }
        .tc-btn-primary:hover:not(:disabled) { background: #dc2626; transform: translateY(-1px); }
        .tc-btn-primary:disabled { opacity: .45; cursor: not-allowed; }
    </style>
    <style>
        :root {
            --brand-red: #e61e1e;
            --bg-dark: #0f0f0f;
            --bg-secondary: #1e1e1e;
            --border-color: #303030;
            --text-primary: #f1f1f1;
            --text-secondary: #aaaaaa;
        }

        * { box-sizing: border-box; }

        html {
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: "Roboto", "Arial", sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            max-width: 100%;
        }

        /* Header */
        .yt-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--bg-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
        }

        .yt-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .yt-menu-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            background: transparent;
            border: none;
            color: var(--text-primary);
        }

        .yt-menu-btn:hover { background: var(--border-color); }

        .yt-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 4px;
        }

        .yt-logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -1px;
        }

/* Search */
        .yt-header-center {
            flex: 1;
            max-width: 640px;
            margin: 0 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .yt-search {
            flex: 1;
            display: flex;
            height: 40px;
            align-items: center;
            gap: 8px;
        }

        .yt-search-form {
            flex: 1;
            display: flex;
            height: 40px;
        }

        .yt-search-input {
            flex: 1;
            background: #121212;
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: 20px 0 0 20px;
            padding: 0 16px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .yt-search-input:focus {
            outline: none;
            border-color: #1c62b9;
        }

        .yt-search-btn {
            width: 64px;
            background: #222;
            border: 1px solid var(--border-color);
            border-radius: 0 20px 20px 0;
            color: var(--text-primary);
            cursor: pointer;
        }

        .yt-search-btn:hover { background: #303030; }

        .yt-search-mic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3f3f3f;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .yt-search-mic:hover { background: #555; }

        /* Header Right */
        .yt-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .yt-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.2rem;
        }

        .yt-icon-btn:hover { background: var(--border-color); }

        .yt-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #555;
            cursor: pointer;
        }

        /* Sidebar */
        .yt-sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            bottom: 0;
            width: 240px;
            background: var(--bg-dark);
            overflow-y: auto;
            overflow-x: hidden;
            padding: 8px 0;
            transition: transform 0.25s ease, width 0.25s ease;
            z-index: 999;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .yt-sidebar-section {
            padding: 0 8px 4px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 4px;
        }
        .yt-sidebar-section:last-child { border-bottom: none; }

        .yt-sidebar-link {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 0 12px;
            height: 40px;
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
        }

        .yt-sidebar-link i {
            font-size: 18px;
            flex-shrink: 0;
            width: 22px;
            text-align: center;
        }

        .yt-sidebar-link:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .yt-sidebar-link.active {
            background: rgba(230, 30, 30, 0.12);
            color: var(--brand-red);
            font-weight: 500;
        }

        .yt-sidebar-link.active i { color: var(--brand-red); }

        /* Sidebar section label */
        .yt-sidebar-section-header {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 12px 4px;
        }

        /* ══════════════════════════════════════════
           GLOBAL BUTTON SYSTEM
           All UI buttons must use one of these classes.
           border-radius: 8px, padding: 8px 14px, flex icon+text.
        ══════════════════════════════════════════ */
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s, border-color .15s, color .15s;
            font-family: inherit;
            line-height: 1;
        }
        .action-btn:hover {
            background: var(--border-color);
            color: var(--text-primary);
            text-decoration: none;
        }
        .action-btn:focus { outline: none; box-shadow: 0 0 0 2px rgba(255,255,255,.15); }
        .action-btn:disabled, .action-btn[disabled] { opacity: .5; cursor: not-allowed; }

        /* Primary variant — brand red */
        .action-btn-primary, .action-btn.primary {
            background: var(--brand-red);
            border-color: var(--brand-red);
            color: #fff;
        }
        .action-btn-primary:hover, .action-btn.primary:hover {
            background: #cc1a1a;
            border-color: #cc1a1a;
            color: #fff;
        }

        /* Danger variant — destructive actions */
        .action-btn-danger, .action-btn.danger {
            border-color: #c53030;
            color: #fc8181;
            background: transparent;
        }
        .action-btn-danger:hover, .action-btn.danger:hover {
            background: rgba(197,48,48,.15);
            color: #fc8181;
        }

        /* Icon-only variant (no text label) */
        .action-btn.icon-only { padding: 8px; }

        /* Filter chip bar */
        .yt-filter-bar-wrap {
            position: sticky;
            top: 56px;
            z-index: 90;
            margin: -24px -24px 20px;
            background: var(--bg-dark);
            border-bottom: 1px solid var(--border-color);
        }
        .yt-filter-bar {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 12px 24px;
            scroll-behavior: smooth;
        }
        .yt-filter-bar::-webkit-scrollbar { display: none; }

        /* Left / right scroll buttons — visible only when the bar overflows */
        .yt-filter-nav {
            position: absolute; top: 0; bottom: 0;
            display: none; align-items: center;
            width: 48px;
            background: transparent;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            z-index: 2;
            padding: 0;
            font-size: 20px;
            justify-content: center;
        }
        .yt-filter-nav.visible { display: flex; }
        .yt-filter-nav.left  { left: 0;  background: linear-gradient(to right, var(--bg-dark) 40%, transparent); justify-content: flex-start; padding-left: 6px; }
        .yt-filter-nav.right { right: 0; background: linear-gradient(to left,  var(--bg-dark) 40%, transparent); justify-content: flex-end;   padding-right: 6px; }
        .yt-filter-nav i {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--bg-secondary);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.35);
        }
        .yt-filter-nav:hover i { background: #555; }

        .yt-chip {
            flex-shrink: 0;
            background: #3f3f3f;
            color: var(--text-primary);
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .yt-chip:hover {
            background: #555;
            color: var(--text-primary);
            text-decoration: none;
        }
        .yt-chip.active {
            background: var(--text-primary);
            color: #0f0f0f;
        }

        /* Mobile sidebar overlay */
        .yt-sidebar-overlay {
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 998;
            display: none;
        }

        .yt-sidebar-overlay.show { display: block; }

        /* Impersonation banner */
        .impersonate-bar {
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            z-index: 999;
            height: 40px;
            background: #7a4f00;
            border-bottom: 1px solid #c47f00;
            color: #ffd166;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            padding: 0 16px;
        }
        .impersonate-bar i { font-size: 15px; }
        .impersonate-exit-btn {
            margin-left: 16px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,209,102,.5);
            color: #ffd166;
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background .15s;
        }
        .impersonate-exit-btn:hover { background: rgba(255,255,255,.2); }
        body.has-impersonate-bar .yt-main { margin-top: calc(56px + 40px); }

        /* Main Content */
        .yt-main {
            margin-top: 56px;
            margin-left: 240px;
            padding: 24px;
            min-height: calc(100vh - 56px);
            transition: margin-left 0.3s;
        }

        /* Upload Button */
        .yt-upload-btn {
            background: var(--brand-red);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
        }

        .yt-upload-btn:hover { background: #cc1a1a; }

        /* Go Live — same shape as the Create button, outlined so it reads as
           the secondary action rather than competing with it. */
        .yt-golive-btn {
            display: inline-flex; align-items: center; gap: 7px;
            height: 36px; padding: 0 14px; margin-left: 8px;
            border: 1px solid rgba(255, 255, 255, .25); border-radius: 18px;
            background: transparent; color: inherit;
            font-size: 14px; font-weight: 500; text-decoration: none;
            white-space: nowrap; cursor: pointer;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .yt-golive-btn i { color: var(--brand-red, #ef4444); font-size: 15px; }
        .yt-golive-btn:hover {
            background: var(--brand-red, #ef4444); border-color: transparent; color: #fff;
        }
        .yt-golive-btn:hover i { color: #fff; }

        /* ── Header user dropdown (custom, no Bootstrap) ───────────────── */
        /* ── Notification bell ── */
        @keyframes bell-ring {
            0%   { transform: rotate(0)        scale(1); }
            8%   { transform: rotate(-22deg)   scale(1.15); }
            20%  { transform: rotate(20deg)    scale(1.15); }
            32%  { transform: rotate(-16deg)   scale(1.1); }
            44%  { transform: rotate(12deg)    scale(1.05); }
            56%  { transform: rotate(-8deg)    scale(1); }
            68%  { transform: rotate(5deg)     scale(1); }
            80%  { transform: rotate(-3deg)    scale(1); }
            100% { transform: rotate(0)        scale(1); }
        }
        @keyframes bell-sway {
            0%, 100% { transform: rotate(0); }
            30%      { transform: rotate(-10deg); }
            70%      { transform: rotate(10deg); }
        }
        #notifBtn {
            transform-origin: center top;
            transition: transform 0.1s;
        }
        #notifBtn.bell-ring {
            animation: bell-ring 0.85s cubic-bezier(.36,.07,.19,.97) both;
        }
        #notifBtn.bell-has-unread:not(.bell-ring) {
            animation: bell-sway 2s ease-in-out infinite;
        }
        .yt-notif-wrap { position: relative; }
        .yt-notif-badge {
            position: absolute; top: 4px; right: 4px;
            min-width: 16px; height: 16px; border-radius: 8px;
            background: var(--brand-red); color: #fff;
            font-size: 10px; font-weight: 700; line-height: 16px;
            text-align: center; padding: 0 3px;
            pointer-events: none;
        }
        .yt-notif-panel {
            position: fixed;
            width: 420px;
            max-height: 600px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,.6);
            z-index: 10001;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .yt-notif-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px 14px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .yt-notif-title { font-size: 16px; font-weight: 700; color: var(--text-primary); }
        .yt-notif-mark-all {
            font-size: 12px; color: var(--brand-red); background: none;
            border: none; cursor: pointer; padding: 0; font-weight: 500;
        }
        .yt-notif-mark-all:hover { text-decoration: underline; }
        .yt-notif-list { overflow-y: auto; flex: 1; min-height: 0; }
        .yt-notif-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 16px; cursor: pointer; text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,.04);
            transition: background .12s;
        }
        .yt-notif-item:hover { background: rgba(255,255,255,.05); }
        .yt-notif-item.unread { background: rgba(230,30,30,.06); }
        .yt-notif-item.unread:hover { background: rgba(230,30,30,.1); }
        .yt-notif-thumb {
            width: 96px; height: 54px; border-radius: 6px; object-fit: cover;
            flex-shrink: 0; background: var(--bg-dark); display: block;
        }
        .yt-notif-thumb-placeholder {
            width: 96px; height: 54px; border-radius: 6px; flex-shrink: 0;
            background: var(--border-color); display: flex; align-items: center;
            justify-content: center; color: var(--text-secondary); font-size: 20px;
        }
        .yt-notif-body { flex: 1; min-width: 0; }
        .yt-notif-text {
            font-size: 13px; color: var(--text-primary); line-height: 1.45;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .yt-notif-text strong { font-weight: 600; }
        .yt-notif-preview { font-style: italic; opacity: .75; }
        .yt-notif-time { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }
        .yt-notif-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--brand-red); flex-shrink: 0; margin-top: 8px;
        }
        .yt-notif-empty {
            padding: 48px 16px; text-align: center;
            font-size: 13px; color: var(--text-secondary);
        }
        @media (max-width: 480px) {
            .yt-notif-panel { width: calc(100vw - 16px); }
        }

        .yt-user-dropdown { position: relative; }

        .yt-user-panel {
            display: none;
            position: fixed;
            width: 224px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,.55);
            z-index: 10001;
            overflow: hidden;
        }
        .yt-user-panel.open { display: block; }

        .yt-panel-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .yt-panel-info img {
            width: 36px; height: 36px;
            border-radius: 50%; flex-shrink: 0;
            object-fit: cover;
        }
        .yt-panel-name  { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .yt-panel-email {
            font-size: 11px; color: var(--text-secondary); margin-top: 1px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 145px;
        }

        .yt-panel-links { padding: 6px 0; }

        .yt-drop-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.12s;
            width: 100%;
            background: transparent;
            border: none;
            text-align: left;
            font-family: inherit;
            line-height: 1;
        }
        .yt-drop-item:hover {
            background: var(--border-color);
            color: var(--text-primary);
            text-decoration: none;
        }
        .yt-drop-item i { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; }
        .yt-drop-item.danger  { color: #fc8181; }
        .yt-drop-item.danger:hover { background: rgba(197,48,48,.15); color: #fc8181; }

        .yt-panel-divider { height: 1px; background: var(--border-color); margin: 4px 0; }

        /* Mobile circle button */
        @media (max-width: 576px) {
            .yt-upload-btn {
                width: 40px;
                height: 40px;
                padding: 0;
                border-radius: 50%;
                justify-content: center;
            }
            .yt-upload-btn span {
                display: none;
            }

            /* Show text on larger mobile */
            @media (min-width: 400px) {
                .yt-upload-btn {
                    width: auto;
                    border-radius: 20px;
                    padding: 8px 16px;
                }
                .yt-upload-btn span {
                    display: inline;
                }
            }
        }

        /* Responsive */
        /* ─── Desktop: mini-guide (collapsed = 72 px stacked icon+label) ─── */
        @media (min-width: 992px) {
            .yt-sidebar.collapsed {
                width: 72px;
                padding: 8px 0;
                overflow: hidden;
            }
            .yt-sidebar.collapsed .yt-sidebar-link {
                flex-direction: column;
                height: auto;
                padding: 10px 4px;
                gap: 4px;
                border-radius: 12px;
                align-items: center;
                text-align: center;
                margin: 0 4px;
            }
            .yt-sidebar.collapsed .yt-sidebar-link i {
                font-size: 20px;
                width: auto;
            }
            .yt-sidebar.collapsed .yt-sidebar-link span {
                font-size: 10px;
                line-height: 1.2;
                white-space: nowrap;
            }
            .yt-sidebar.collapsed .yt-sidebar-section {
                border-bottom: none;
                padding-bottom: 4px;
                margin-bottom: 0;
            }
            .yt-sidebar.collapsed .yt-sidebar-section-header { display: none; }
            .yt-main.collapsed { margin-left: 72px; }
        }

        @media (max-width: 991px) {
            .yt-sidebar {
                transform: translateX(-100%);
            }

            .yt-sidebar.open {
                transform: translateX(0);
            }

            .yt-main {
                margin-left: 0;
            }

            .yt-search-mic { display: none; }
        }

        @media (max-width: 768px) {
            .yt-header-center { display: none; }
            .yt-main { padding: 16px; }
            .yt-main.video-view-page { padding: 0 !important; }
            .video-view-page .yt-sidebar-container { padding: 0 16px; }
        }

        /* Mobile Search Toggle Button */
        .yt-mobile-search-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.2rem;
        }

        .yt-mobile-search-toggle:hover {
            background: var(--border-color);
        }

        /* Mobile Search Overlay */
        .mobile-search-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-dark);
            z-index: 1001;
            padding: 60px 16px 16px;
            justify-content: center;
            align-items: flex-start;
        }

        .mobile-search-overlay.active {
            display: flex;
        }

        .mobile-search-form {
            display: flex;
            width: 100%;
            max-width: 600px;
            height: 44px;
        }

        .mobile-search-input {
            flex: 1;
            background: #121212;
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: 20px 0 0 20px;
            padding: 0 16px;
            color: var(--text-primary);
            font-size: 16px;
        }

        .mobile-search-input:focus {
            outline: none;
            border-color: #1c62b9;
        }

        .mobile-search-submit {
            width: 50px;
            background: #222;
            border: 1px solid var(--border-color);
            border-radius: 0 20px 20px 0;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-search-submit:hover {
            background: #303030;
        }

        /* Dropdown */
        .dropdown-menu-dark {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .dropdown-item {
            color: var(--text-primary);
        }

        .dropdown-item:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        /* Modal input focus */
        #deleteVideoInput:focus {
            outline: none;
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .delete-otp-input {
            width: 100%;
            background: #1a1a1a;
            border: 1px solid rgba(239, 68, 68, 0.5);
            border-radius: 8px;
            color: #fff;
            padding: 12px 16px;
            font-size: 22px;
            letter-spacing: 0.4em;
            text-align: center;
            text-indent: 0.4em;
            font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
            box-sizing: border-box;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .delete-otp-input::placeholder {
            color: rgba(255, 255, 255, 0.35);
            letter-spacing: 0.4em;
        }
        .delete-otp-input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
        }

        /* ── Playlist controls bar (sidebar, all video types) ── */
        .pl-controls-bar {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 10px;
            padding: 6px 8px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .pl-ctrl-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 15px;
            transition: color .15s, background .15s;
            white-space: nowrap;
        }
        .pl-ctrl-btn:hover:not(:disabled) { color: var(--text-primary); background: rgba(255,255,255,.07); }
        .pl-ctrl-btn:disabled { opacity: .35; cursor: default; }
        .pl-ctrl-btn.pl-ctrl-active { color: var(--brand-red); }
        .pl-ctrl-divider { width: 1px; height: 18px; background: var(--border-color); margin: 0 2px; flex-shrink: 0; }
        .pl-ctrl-autoplay { margin-left: auto; }
        .pl-autoplay-label { font-size: 12px; font-weight: 500; }

        /* ── Mini-player ─────────────────────────────────────── */
        #ytpMini {
            position: fixed;
            bottom: calc(64px + env(safe-area-inset-bottom, 0px));
            right: 12px;
            width: 300px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            overflow: hidden;
            z-index: 1999;
            box-shadow: 0 4px 24px rgba(0,0,0,.6);
        }
        #ytpMiniVideo {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            overflow: hidden;
            cursor: move;          /* drag handle */
            user-select: none;
            -webkit-user-select: none;
        }
        #ytpMini.dragging { cursor: grabbing; opacity: .92; }
        #ytpMini.dragging #ytpMiniVideo { cursor: grabbing; }
        #ytpMiniVideo video { width:100%; height:100%; object-fit:contain; display:block; }
        #ytpMiniVideo .ytp-chrome-bottom,
        #ytpMiniVideo .ytp-gradient-bottom,
        #ytpMiniVideo .ytp-large-play-btn,
        #ytpMiniVideo .ytp-spinner,
        #ytpMiniVideo .ytp-dbl-left,
        #ytpMiniVideo .ytp-dbl-right { display:none !important; }
        #ytpMiniBar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            gap: 6px;
            background: #1a1a1a;
        }
        #ytpMiniInfo {
            flex:1; min-width:0;
            cursor: move;       /* secondary drag handle on the title area */
            user-select: none;
            -webkit-user-select: none;
        }
        #ytpMiniTitle {
            font-size: 12px;
            color: #eee;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        #ytpMiniControls {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        #ytpMiniControls button,
        #ytpMiniControls a {
            background: none;
            border: none;
            color: #ccc;
            cursor: pointer;
            padding: 4px 6px;
            font-size: 16px;
            border-radius: 4px;
            text-decoration: none;
            line-height: 1;
        }
        #ytpMiniControls button:hover,
        #ytpMiniControls a:hover { color:#fff; background:rgba(255,255,255,.1); }
        @media (max-width: 480px) {
            #ytpMini { width: calc(100vw - 24px); right: 12px; }
        }

        /* ── Mobile responsive ───────────────────────────────── */
        @media (max-width: 480px) {
            .yt-menu-btn, .yt-icon-btn, .yt-mobile-search-toggle {
                min-width: 44px;
                min-height: 44px;
            }
            .yt-main { padding: 12px 8px !important; }
            .yt-video-title, .video-title { font-size: 14px !important; }
            .channel-info { gap: 8px !important; }
            .channel-avatar { width: 32px !important; height: 32px !important; }
            .channel-name { font-size: 14px !important; }
            .channel-subs { font-size: 12px !important; }
            .yt-channel-name, .yt-video-meta { font-size: 12px !important; }
            .video-actions {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 8px;
                width: 100%;
            }
            .yt-action-btn { flex-shrink: 0; padding: 8px 12px; font-size: 13px; }
            .comment-item { flex-direction: column; }
            .comment-item > img { width: 32px !important; height: 32px !important; }
            .yt-search-input { font-size: 14px; padding: 0 12px; }
            .yt-header { padding: 0 8px; }
            .dropdown-menu { width: 100%; min-width: 200px; }
        }
        @media (max-width: 360px) {
            .yt-main { padding: 8px 4px !important; }
            .yt-main.video-view-page { padding: 0 !important; }
            .yt-header-right .yt-icon-btn:not(:first-child) { display: none; }
        }
        /* Base grid — kept in the layout (not the per-page extra_styles block)
           so SPA navigations from a video page back to a gallery still get it.
           No !important: pages with their own .yt-video-grid rules (e.g. the
           channel page) override these via normal cascade since their <style>
           comes from in <body>. */
        .yt-video-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 992px) { .yt-video-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .yt-video-grid { grid-template-columns: 1fr; gap: 14px; } }
        @media (max-height: 500px) and (orientation: landscape) {
            .yt-sidebar { width: 200px; }
            .yt-video-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        }
        @media (min-width: 1440px) {
            .yt-video-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .video-container {
                border-radius: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
        }
        @media (hover: none) {
            .yt-sidebar-link { padding: 0 16px; }
            .yt-sidebar-link:hover { background: transparent; }
            .yt-sidebar-link:active { background: var(--border-color); }
        }

        /* ── Bottom nav + mobile scroll model ───────────────── */
        .yt-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(56px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            padding-left: 8px;
            padding-right: 8px;
            background: var(--bg-dark);
            border-top: 1px solid var(--border-color);
            z-index: 1000;
            justify-content: space-around;
            align-items: center;
            -webkit-tap-highlight-color: transparent;
            will-change: transform;
        }
        .yt-bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            height: 100%;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 12px;
            gap: 4px;
            transition: color 0.2s;
            cursor: pointer;
            background: transparent;
            border: none;
            min-width: 56px;
        }
        .yt-bottom-nav-item:hover { color: var(--text-primary); }
        .yt-bottom-nav-item.active { color: var(--text-primary); }
        .yt-bottom-nav-item i { font-size: 24px; }
        .yt-bottom-nav-item span { font-size: 10px; font-weight: 500; }
        @media (max-width: 768px) {
            html { overflow: hidden; height: 100%; max-width: 100%; }
            body {
                overflow: hidden;
                height: 100%;
                max-width: 100%;
                position: fixed;
                width: 100%;
            }
            .yt-bottom-nav { display: flex; transform: none !important; }
            .yt-main {
                position: fixed !important;
                top: 56px !important;
                left: 0 !important;
                right: 0 !important;
                bottom: calc(56px + env(safe-area-inset-bottom, 0px)) !important;
                margin: 0 !important;
                padding: 16px !important;
                padding-bottom: 16px !important;
                min-height: unset !important;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior-y: contain;
            }
            body.has-impersonate-bar .yt-main { top: calc(56px + 40px) !important; }
            .yt-main.video-view-page { padding: 0 !important; }
            .yt-filter-bar-wrap {
                position: relative !important;
                top: auto !important;
                z-index: auto !important;
                margin: -16px -16px 16px !important;
            }
            .yt-filter-bar {
                padding: 12px 16px !important;
            }
        }
    </style>

        <style>
        /* ===== Video Section ===== */
        .yt-video-section {
            flex: 1;
            min-width: 0;
        }

        /* ===== Sidebar (copied from music.blade.php) ===== */
        .yt-sidebar-container {
            width: 400px;
            flex-shrink: 0;
        }

        .sidebar-video-card {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            cursor: pointer;
        }

        .sidebar-thumb {
            width: 168px;
            aspect-ratio: 16/9;
            border-radius: 8px;
            overflow: hidden;
            background: #1a1a1a;
            flex-shrink: 0;
        }

        .sidebar-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-title {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sidebar-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* ===== Video Player ===== */
        .video-container {
            position: relative;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            max-height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .video-container.portrait,
        .video-container.square,
        .video-container.ultrawide {
            margin: 0 auto;
            width: auto;
        }

        .video-container.portrait {
            aspect-ratio: 9/16;
            max-width: 50vh;
        }

        .video-container.square {
            aspect-ratio: 1/1;
            max-width: 70vh;
        }

        .video-container.ultrawide {
            aspect-ratio: 21/9;
            max-width: 100%;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Coach note overlay (subtitle-style) */
        .coach-note-overlay {
            position: absolute;
            left: 50%;
            bottom: 6cqi;
            transform: translateX(-50%);
            max-width: min(80%, 900px);
            width: fit-content;
            /* Scale font-size, padding, border-radius with the player width so
               the note grows/shrinks proportionally on resize / fullscreen. */
            font-size: clamp(11px, 2.2cqi, 26px);
            padding: clamp(4px, 0.9cqi, 14px) clamp(8px, 1.6cqi, 20px);
            border-radius: clamp(4px, 0.6cqi, 10px);
            background: rgba(0, 0, 0, 0.68);
            color: #fff;
            font-weight: 600;
            line-height: 1.35;
            text-align: center;
            z-index: 14;
            box-shadow: 0 6px 22px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.18);
            display: none;
            pointer-events: none;
            backdrop-filter: blur(3px);
        }

        .coach-note-overlay.show { display: block; }

        /* ═══════════════════════════════════════════════════════════════
           REPLAY badge — sports-broadcast style. Diagonal red-to-orange
           bar with "REPLAY" over a big ×N speed indicator. Shows during
           point replays and slow-mo phases; scales with the player width.
           ═══════════════════════════════════════════════════════════════ */
        .replay-badge {
            position: absolute;
            /* Pushed down so it clears the scoreboard's KARATE KUMITE identity block at top-left */
            top: 8cqi; left: 2.5cqi;
            z-index: 15;
            display: flex; flex-direction: column; align-items: center;
            padding: clamp(4px, 0.7cqi, 8px) clamp(7px, 1.1cqi, 12px);
            background: linear-gradient(135deg, #b91c1c 0%, #e61e1e 45%, #f97316 100%);
            color: #fff;
            border-radius: clamp(3px, 0.4cqi, 7px);
            box-shadow: 0 4px 14px rgba(0,0,0,.5), 0 0 0 1.5px rgba(255,255,255,.14) inset;
            transform: skewX(-8deg);
            pointer-events: none;
            font-family: 'Bebas Neue', 'Arial Narrow', Impact, sans-serif;
            letter-spacing: .1em;
        }
        .replay-badge[hidden] { display: none; }
        .replay-badge > * { transform: skewX(8deg); }
        .replay-badge-word {
            font-size: clamp(9px, 1.2cqi, 15px);
            font-weight: 900; text-transform: uppercase;
            text-shadow: 0 1px 3px rgba(0,0,0,.5);
            line-height: 1;
        }
        .replay-badge-speed {
            font-size: clamp(13px, 2cqi, 22px);
            font-weight: 900;
            line-height: 1;
            margin-top: 1px;
            text-shadow: 0 1px 4px rgba(0,0,0,.55), 0 0 8px rgba(255,255,255,.22);
            font-variant-numeric: tabular-nums;
        }
        /* Slow-mo variant: swap to a cool blue/purple palette so the phase
           change is instantly readable — no extra copy, just the palette + speed. */
        .replay-badge.is-slowmo {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 45%, #a855f7 100%);
        }

        /* Drag mode (while composing / editing a coach note) — reveal grab UX.
           Bumped above the capture strip (z-index 60) so it can be dragged into
           the area the strip currently covers. */
        .coach-note-overlay.draggable {
            pointer-events: auto;
            cursor: grab;
            outline: 2px dashed rgba(230, 30, 30, .65);
            outline-offset: 2px;
            user-select: none;
            z-index: 70;
        }
        .coach-note-overlay.draggable:active,
        .coach-note-overlay.dragging { cursor: grabbing; }
        .coach-note-overlay.draggable::after {
            content: '↕ drag me';
            position: absolute;
            top: calc(-1.4em - 4px); left: 50%; transform: translateX(-50%);
            font-size: 0.6em; font-weight: 700; color: var(--brand-red, #e61e1e);
            background: rgba(0,0,0,.7); padding: 1px 6px; border-radius: 3px;
            white-space: nowrap;
        }
        /* When positioned by the user we anchor by the overlay's CENTER at
           (left, top). Using percentage left/top + translate(-50%,-50%) makes
           the overlay scale correctly with the video area on any resize /
           fullscreen. */
        .coach-note-overlay.positioned {
            bottom: auto; right: auto;
            transform: translate(-50%, -50%);
        }

        /* Match Highlights Toggle Button */
        .match-highlights-toggle {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            width: 64px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            z-index: 15;
            transition: all 0.2s ease;
            backdrop-filter: blur(12px);
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.2px;
            padding: 0 6px;
        }

        .match-highlights-toggle:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* No rotation animation */

        /* Hide sidebar by default */
        .events-sidebar {
            display: none;
        }

        .events-sidebar.show {
            display: flex;
        }

        /* ===== Playlist Navigation Controls ===== */
        .playlist-controls {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0, 0, 0, 0.8);
            padding: 8px 16px;
            border-radius: 24px;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .video-container:hover .playlist-controls,
        .playlist-controls.visible {
            opacity: 1;
        }

        .playlist-nav-btn {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }

        .playlist-nav-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .playlist-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .playlist-nav-btn:disabled:hover {
            background: transparent;
        }

        .playlist-nav-btn i {
            font-size: 20px;
        }

        .playlist-nav-label {
            font-size: 12px;
            color: #aaa;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===== Autoplay Toggle ===== */
        .autoplay-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 8px;
            border-left: 1px solid rgba(255, 255, 255, 0.2);
        }

        .autoplay-toggle label {
            font-size: 12px;
            color: #aaa;
            cursor: pointer;
            white-space: nowrap;
        }

        .autoplay-switch {
            position: relative;
            width: 36px;
            height: 20px;
        }

        .autoplay-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .autoplay-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #666;
            transition: 0.3s;
            border-radius: 20px;
        }

        .autoplay-slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .autoplay-switch input:checked+.autoplay-slider {
            background-color: var(--brand-red);
        }

        .autoplay-switch input:checked+.autoplay-slider:before {
            transform: translateX(16px);
        }

        /* ===== Keyboard Hint ===== */
        .keyboard-hint {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
            background: rgba(0, 0, 0, 0.6);
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* ===== Video Info ===== */
        .video-title {
            font-size: 20px;
            font-weight: 500;
            margin: 16px 0 8px;
            line-height: 1.3;
        }

        .video-stats-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }

        .video-stats-left {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-secondary);
        }

        .video-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== Channel Row ===== */
        .channel-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
        }

        .channel-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .channel-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #555;
        }

        .channel-name {
            font-size: 16px;
            font-weight: 500;
        }

        .channel-subs {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .subscribe-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .subscribe-btn:hover {
            background: var(--border-color);
            transform: translateY(-1px);
        }

        .mobile-action-dropdown {
            display: none;
            position: relative;
        }

        .mobile-action-dropdown .dropdown-menu {
            right: 0;
            left: auto;
            min-width: 200px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 0;
            z-index: 1200;
        }

        .mobile-action-dropdown .dropdown-item {
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            padding: 8px 12px;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
        }

        .mobile-action-dropdown .dropdown-item:hover {
            background: var(--border-color);
        }

        /* ===== Event Header ===== */
        .event-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            padding: 12px 0;
        }

        .event-header-left {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }

        .event-logo {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            overflow: hidden;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .event-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .event-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }

        .event-text .title {
            font-size: 1.4rem;
            margin: 0;
            line-height: 1.3;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .event-text .title .trophy-icon {
            color: #ef4444;
            font-size: 1.2rem;
        }

        .event-text .title a {
            color: inherit;
            text-decoration: none;
        }

        .event-text .title a:hover {
            text-decoration: underline;
            opacity: 0.85;
        }

        .event-text .meta-link {
            font-size: 0.85rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .event-text .meta-link:hover {
            color: var(--brand-red);
        }

        .event-text .meta-link a {
            color: inherit;
            font-weight: bold;
            text-decoration: none;
        }

        .event-text .meta-link a:hover {
            text-decoration: underline;
        }

        .event-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .event-header-actions .action-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .event-header-actions .action-btn:hover {
            background: var(--border-color);
            transform: translateY(-1px);
        }

        .event-header-actions .action-btn:active {
            transform: translateY(0);
        }

        .event-header-actions .action-btn svg,
        .event-header-actions .action-btn i {
            flex-shrink: 0;
        }

        .event-header-actions .action-btn.liked {
            color: var(--brand-red);
        }

        .action-btn,
        .action-btn a,
        .comment-section .action-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .action-btn:hover,
        .action-btn a:hover,
        .comment-section .action-btn:hover {
            background: var(--border-color);
            transform: translateY(-1px);
            text-decoration: none;
        }

        .action-btn:active,
        .comment-section .action-btn:active {
            transform: translateY(0);
        }

        .action-btn svg,
        .action-btn i,
        .comment-section .action-btn svg,
        .comment-section .action-btn i {
            flex-shrink: 0;
        }

        .action-btn.comment-btn {
            background: var(--brand-red);
            color: white;
            border-color: var(--brand-red);
        }

        .event-header-actions .action-btn.disliked {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
            border-color: #ef4444;
        }

        .event-header-actions .action-btn.saved {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.15);
            border-color: #f59e0b;
        }

        .event-header-actions .action-btn.subscribed {
            color: #ffffff;
            background: #6b7280;
            border-color: #6b7280;
        }

        /* Video Owner Badge */
        .video-owner-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .video-owner-badge .owner-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .video-owner-badge .owner-name {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* ===== Description Panel ===== */
        .description-panel {
            margin-top: 16px;
            border-radius: 12px;
            padding: 8px 10px 10px;
        }

        .description-toggle {
            width: 100%;
            border: none;
            background: transparent;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            cursor: pointer;
            padding: 4px 0;
        }

        .description-toggle:hover {
            color: #ffffff;
        }

        .description-body {
            margin-top: 6px;
            display: none;
        }

        .description-body.open {
            display: block;
        }

        /* ===== Match Card ===== */
        .match-card {
            margin-top: 16px;
            padding: 16px 18px;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .match-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
        }

        .match-label {
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: var(--text-primary);
            text-align: center;
            font-weight: 600;
        }

        .fighters-row {
            display: grid;
            grid-template-columns: 1.1fr 0.8fr 1.1fr;
            align-items: stretch;
            gap: 16px;
        }

        .fighter-card {
            border-radius: 14px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .fighter-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .fighter-photo {
            width: 110px;
            height: 145px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 0 1px var(--bg-primary);
            flex-shrink: 0;
        }

        .fighter-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fighter-logo {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            overflow: hidden;
            background: var(--bg-primary);
        }

        .fighter-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .fighter-badge {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid #404040;
            color: var(--text-primary);
        }

        .fighter-badge.blue {
            background: rgba(37, 99, 235, 0.15);
            border-color: #1d4ed8;
        }

        .fighter-badge.red {
            background: rgba(239, 68, 68, 0.14);
            border-color: #b91c1c;
        }

        .fighter-name.blue-corner {
            color: #2563eb;
            font-weight: bold;
        }

        .fighter-name.red-corner {
            color: #ef4444;
            font-weight: bold;
        }

        .fighter-name a {
            color: inherit;
            text-decoration: none;
        }

        .fighter-name a:hover {
            text-decoration: underline;
        }

        .fighter-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
            text-align: center;
            font-size: 0.8rem;
        }

        .fighter-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .fighter-team {
            color: var(--text-secondary);
        }

        .fighter-team a {
            color: inherit;
            text-decoration: none;
        }

        .fighter-team a:hover {
            text-decoration: underline;
        }

        .fighter-country {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .fighter-country-flag { display: inline-flex; align-items: center; }
        .fighter-country-flag .fi { width: 22px; height: 16px; border-radius: 2px; }

        .match-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .score-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .score-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .score-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .referee-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .referee-photo {
            width: 80px;
            height: 115px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 0 1px var(--bg-primary);
            flex-shrink: 0;
        }

        .referee-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .referee-block a {
            color: inherit;
            text-decoration: none;
        }

        .referee-block a:hover {
            text-decoration: underline;
        }

        .ref-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .match-footer {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #666666;
            text-align: center;
        }

        .hall-link {
            color: #e61e1e;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .hall-link:hover {
            color: #ff4757;
            text-decoration: underline;
        }

        /* ===== Markdown Body ===== */
        .markdown-body {
            font-size: 0.95rem;
            line-height: 1.6;
            margin-top: 16px;
        }

        .markdown-body h2 {
            font-size: 1.05rem;
            margin-top: 14px;
        }

        .description-text {
            font-size: 14px;
            line-height: 1.5;
            color: var(--text-primary);
        }

        .description-text p {
            margin-bottom: 8px;
        }

        .description-text a {
            color: #3ea6ff;
        }

        /* ===== Sidebar ===== */
        .yt-sidebar-container {
            width: 400px;
            flex-shrink: 0;
        }

        /* ===== Match Events Sidebar ===== */
        .events-sidebar {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            width: 400px;
            flex-shrink: 0;
            height: var(--sidebar-height, 400px);
            display: none !important;
            flex-direction: column;
        }

        .events-sidebar.show {
            display: flex !important;
        }

        .events-sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .events-sidebar-header h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .close-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .close-btn:hover {
            color: var(--text-primary);
        }

        .tab-header {
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        /* Slow-mo speed picker — sits at the right end of the tab header */
        .slowmo-picker {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 2px;
            padding: 3px 6px 3px 8px;
            font-size: 11px; color: var(--text-secondary);
        }
        .slowmo-picker-lbl { color: var(--brand-red, #e61e1e); font-size: 12px; margin-right: 2px; }
        .slowmo-opt {
            border: none; background: transparent;
            color: var(--text-secondary);
            padding: 3px 6px; border-radius: 4px;
            font-family: inherit; font-size: 11px; font-weight: 700;
            cursor: pointer; line-height: 1;
            transition: background .1s, color .1s;
        }
        .slowmo-opt:hover { background: rgba(255,255,255,.06); color: var(--text-primary); }
        .slowmo-opt.is-active {
            background: var(--brand-red, #e61e1e); color: #fff;
        }

        .tab-button {
            flex: 1;
            padding: 12px 16px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }

        .tab-button:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-button.active {
            color: var(--brand-red);
            border-bottom-color: var(--brand-red);
        }

        .tab-panels {
            height: 100%;
            overflow-y: auto;
            flex: 1;
        }

        /* Custom Scrollbar for Match Highlights */
        .tab-panels::-webkit-scrollbar {
            width: 6px;
        }

        .tab-panels::-webkit-scrollbar-track {
            background: var(--bg-primary);
            border-radius: 3px;
        }

        .tab-panels::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .tab-panels::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        .tab-panels::-webkit-scrollbar-corner {
            background: var(--bg-primary);
        }

        .tab-panel {
            display: none;
            padding: 16px;
        }

        .tab-panel.active {
            display: block;
        }

        .section-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .btn-icon {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-icon:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }


        .event-list {
            margin-top: 12px;
        }

        .round-marker {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .round-marker:first-child {
            border-top: none;
            margin-top: 0;
        }

        .round-badge {
            background-color: #fbbf24;
            color: #000000;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            display: inline-block;
        }

        .round-actions {
            display: flex;
            gap: 4px;
        }

        .round-action-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        .round-action-btn:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .event-item {
            display: flex;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-bottom: 4px;
            position: relative;
        }

        .event-item:hover {
            background: var(--border-color);
        }

        .event-item:hover .event-actions {
            opacity: 1;
        }

        .event-actions {
            position: absolute;
            right: 8px;
            top: 8px;
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .event-action-btn {
            background: var(--bg-primary);
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 10px;
        }

        .event-action-btn:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .event-action-btn.delete:hover {
            color: var(--brand-red);
        }

        .event-time {
            font-size: 12px;
            color: #3ea6ff;
            font-weight: 500;
            min-width: 55px;
            text-align: center;
        }

        .event-label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .event-meta {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .event-meta .meta-round      { color: #eab308; font-weight: 700; letter-spacing: .02em; }
        .event-meta .meta-sep        { margin: 0 4px; opacity: .5; }
        .event-meta .meta-score-blue,
        .event-meta .meta-score-red {
            display: inline-block; min-width: 20px; text-align: center;
            padding: 1px 6px; border-radius: 4px;
            color: #fff; font-weight: 700;
            font-variant-numeric: tabular-nums; font-size: 11px;
            line-height: 1.3;
        }
        .event-meta .meta-score-blue { background: #3b82f6; }
        .event-meta .meta-score-red  { background: #ef4444; }
        .event-meta .meta-score-sep  { margin: 0 4px; color: var(--text-secondary); opacity: .7; }

        .pill {
            display: inline-block;
            min-width: 42px;         /* equal width for Blue/Red — no visual bias */
            text-align: center;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            font-variant-numeric: tabular-nums;
            box-sizing: border-box;
        }

        .pill-blue {
            background: #3b82f6;
            color: white;
        }

        .pill-red {
            background: #ef4444;
            color: white;
        }

        /* Point entry (single or grouped) — one card, inline chips per side */
        .event-item-point .event-body { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
        .event-chips {
            display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center;
        }
        .event-chip {
            display: inline-flex; align-items: center; gap: 6px; min-width: 0;
            padding: 3px 4px 3px 3px; border-radius: 6px;
            transition: background .12s;
        }
        .chip-action {
            font-size: 13px; color: var(--text-primary);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            max-width: 220px;
        }
        .chip-pt {
            font-size: 11px; font-weight: 700; color: var(--text-secondary);
            background: rgba(255,255,255,.06); padding: 1px 6px; border-radius: 3px;
        }

        /* ===== Coach Review Tab Specific Styles ===== */
        #tab-review .event-item {
            position: relative;
            display: block;
            padding: 10px;
            margin-bottom: 4px;
            border-radius: 8px;
            background: transparent;
            border: none;
            transition: background 0.2s ease;
        }

        #tab-review .event-item:hover {
            background: var(--border-color);
            transform: none;
            box-shadow: none;
        }

        /* ═══════════════════════════════════════════════════════════════
           Coach-review timeline — vertical spine with red dots. Each review
           is [dot] [time label] [card with emoji + note + author + tools].
           ═══════════════════════════════════════════════════════════════ */
        #reviewEvents { position: relative; padding-left: 30px; }
        /* The spine line runs behind every item */
        #reviewEvents::before {
            content: ''; position: absolute;
            left: 11px; top: 6px; bottom: 6px;
            width: 2px; background: rgba(255,255,255,.08);
        }
        /* Empty state should NOT show the spine */
        #reviewEvents .event-empty { padding-left: 0; }
        #reviewEvents:has(> .event-empty:only-child)::before { display: none; }

        .review-card {
            position: relative;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            margin: 0 0 22px;
            cursor: default;   /* the card is only a container now; interactive parts handle their own clicks */
        }
        .review-card:last-child { margin-bottom: 0; }

        /* Red dot on the spine, aligned with the time label */
        .review-card::before {
            content: '';
            position: absolute;
            left: -25px; top: 4px;
            width: 12px; height: 12px; border-radius: 50%;
            background: var(--brand-red, #e61e1e);
            box-shadow: 0 0 0 3px rgba(230,30,30,.22);
        }

        /* Time label ("chapter title" style above the card) */
        .review-side {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 6px;
        }
        .review-emoji-badge { display: none; } /* emoji shown inside the card, not header */
        .review-range {
            font-size: 11px; font-weight: 700; letter-spacing: .06em;
            color: var(--brand-red, #e61e1e); text-transform: uppercase;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .review-range-sep { margin: 0 4px; opacity: .7; }

        /* The card body */
        .review-main {
            display: flex; align-items: flex-start; gap: 10px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            transition: background .12s, border-color .12s;
        }
        .review-main:hover {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.12);
        }
        /* Bring the emoji into the card as the leading indicator */
        .review-main::before {
            content: attr(data-emoji);
            font-size: 18px; line-height: 1.3; flex-shrink: 0;
        }
        .review-body-col { flex: 1; min-width: 0; }
        .review-note {
            color: var(--text-primary);
            font-size: 14px; font-weight: 500; line-height: 1.4;
            margin: 0 0 4px;
            word-break: break-word;
        }
        .review-foot {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; min-width: 0;
        }
        .review-author {
            display: inline-flex; align-items: center; gap: 6px; min-width: 0;
            color: var(--text-secondary); font-size: 11.5px;
        }
        .review-author-dot { display: none; }
        .review-author-name {
            color: var(--text-secondary); font-weight: 500;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        .review-tools {
            display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;
            align-self: center;
        }
        .review-tool, .review-card .event-action-btn {
            width: 26px; height: 26px; padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 5px; border: none;
            background: rgba(255,255,255,.06); cursor: pointer;
            color: var(--text-primary);
            transition: background .12s, color .12s, transform .06s;
            font-size: 12px; line-height: 1;
        }
        .review-tool:hover, .review-card .event-action-btn:hover { background: rgba(255,255,255,.14); }
        .review-tool:active, .review-card .event-action-btn:active { transform: translateY(1px); }
        .review-card .event-action-btn.delete:hover { background: rgba(230,30,30,.24); color: #ff8a8a; }
        .review-slowmo-btn {
            width: auto; padding: 0 10px; gap: 4px;
            background: rgba(230,30,30,.14); color: var(--brand-red, #e61e1e);
        }
        .review-slowmo-btn > i { font-size: 12px; line-height: 1; }
        .review-slowmo-btn:hover { background: rgba(230,30,30,.24); color: #fff; }
        .review-slowmo-btn.is-playing {
            background: var(--brand-red, #e61e1e); color: #fff;
            box-shadow: 0 0 0 2px rgba(230,30,30,.35);
        }

        .review-note-text {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 400;
        }

        #tab-review .event-item .event-actions {
            top: 8px;
            right: 8px;
        }

        /* ===== Match Modal Styles ===== */
        .match-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .match-modal-overlay.active {
            display: flex;
        }

        .match-modal-content {
            background: linear-gradient(145deg, rgba(30, 30, 38, 0.95), rgba(24, 24, 31, 0.96));
            border-radius: 16px;
            padding: 18px;
            width: 92%;
            max-width: 420px;
            max-height: 85vh;
            overflow-y: auto;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
        }

        .match-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .match-modal-header h3 {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #f8fafc;
            background: none;
            -webkit-text-fill-color: initial;
        }

        .modal-title-icon {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            background: rgba(239, 68, 68, 0.16);
            color: #fda4af;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .match-modal-close {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .match-modal-close:hover {
            color: var(--text-primary);
        }

        .match-form-group {
            margin-bottom: 16px;
        }

        .match-form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-primary);
        }

        .match-form-group input,
        .match-form-group select,
        .match-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
        }

        .match-form-group input:focus,
        .match-form-group select:focus,
        .match-form-group textarea:focus {
            outline: none;
            border-color: var(--brand-red);
        }

        .match-form-row {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .match-form-row .match-form-group {
            flex: 1;
        }

        .match-form-row .form-group {
            flex: 1;
        }

        .match-form-row .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #d1d5db;
            letter-spacing: 0.2px;
        }

        .match-form-row .form-group input,
        .match-form-row .form-group select,
        .match-form-row .form-group textarea {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
            font-size: 13px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            backdrop-filter: blur(8px);
        }

        .match-form-row .form-group input:hover,
        .match-form-row .form-group select:hover,
        .match-form-row .form-group textarea:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.06);
        }

        .match-form-row .form-group input:focus,
        .match-form-row .form-group select:focus,
        .match-form-row .form-group textarea:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Premium form input styles */
        .match-form-group input,
        .match-form-group select,
        .match-form-group textarea {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
            font-size: 13px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            backdrop-filter: blur(8px);
        }

        .match-form-group input:hover,
        .match-form-group select:hover,
        .match-form-group textarea:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-1px);
        }

        .match-form-group input:focus,
        .match-form-group select:focus,
        .match-form-group textarea:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15),
                0 8px 25px rgba(239, 68, 68, 0.1);
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .match-form-group textarea {
            resize: vertical;
            min-height: 72px;
            font-family: inherit;
        }

        .match-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #d1d5db;
            letter-spacing: 0.2px;
        }

        /* Emoji option buttons (coach notes modal) */
        .emoji-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .emoji-option-btn {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .emoji-option-btn:hover {
            border-color: rgba(248, 113, 113, 0.55);
            background: rgba(239, 68, 68, 0.12);
            transform: translateY(-1px);
        }

        .emoji-option-btn.active {
            border-color: rgba(248, 113, 113, 0.7);
            background: rgba(239, 68, 68, 0.2);
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
        }

        /* Comment timestamp badge */
        .comment-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(62, 166, 255, 0.15);
            border: 1px solid rgba(62, 166, 255, 0.4);
            color: #7dd3fc;
            font-weight: 600;
            font-size: 12px;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
            user-select: none;
            margin: 0 2px;
            transition: all 0.2s ease;
        }

        .comment-time-badge:hover {
            background: rgba(62, 166, 255, 0.26);
            border-color: rgba(125, 211, 252, 0.8);
            color: #e0f2fe;
            transform: translateY(-1px);
        }

        /* Custom scrollbar for modals - blended & subtle */
        .match-modal-content::-webkit-scrollbar {
            width: 4px;
        }

        .match-modal-content::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 2px;
        }

        .match-modal-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
        }

        .match-modal-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        /* Hide scrollbar for Firefox */
        .match-modal-content {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        /* Modal responsive */
        @media (max-width: 500px) {
            .match-modal-content {
                width: calc(100% - 16px);
                max-width: none;
                padding: 14px;
                border-radius: 14px;
            }

            .match-modal-header h3 {
                font-size: 15px;
            }

            .match-form-row {
                flex-direction: column;
                gap: 6px;
                margin-bottom: 8px;
            }

            .match-form-group {
                margin-bottom: 10px;
            }

            .match-modal-actions {
                gap: 6px;
                margin-top: 10px;
                padding-top: 8px;
            }

            .match-btn-cancel,
            .match-btn-save {
                font-size: 11px;
                padding: 8px 10px;
            }
        }

        .match-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .match-modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .match-btn-cancel {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #e5e7eb;
            padding: 8px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            line-height: 1;
        }

        .match-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(255, 255, 255, 0.24);
        }

        .match-btn-save {
            background: linear-gradient(135deg, #ef4444, #f87171);
            border: 1px solid rgba(248, 113, 113, 0.5);
            color: #fff;
            padding: 8px 13px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s ease;
            line-height: 1;
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.25);
        }

        .match-btn-save:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
        }

        .match-btn-delete {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid var(--brand-red);
            color: var(--brand-red);
            padding: 14px 28px;
            border-radius: 14px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 140px;
        }

        .match-btn-delete:hover {
            background: var(--brand-red);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .delete-confirm-message {
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .delete-confirm-sub {
            color: var(--text-secondary);
            font-size: 12px;
            margin-bottom: 2px;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1300px) {
            .yt-sidebar-container {
                width: 300px;
            }

            .events-sidebar {
                width: 300px;
            }
        }

        /* ── Narrow desktop: too little room for a side rail ──────────────
           Between 992px and 1300px the left nav (240px) and the 300px rail
           leave the video column under ~700px: Up Next titles collapse to a
           ~124px channel and the match header/fighters grid break apart.
           Trigger the SAME stacking the ≤991px layout already does, just
           earlier. Nothing is removed — the ≤991px block still owns the
           phone treatment (thumb-on-top cards, review view, bottom sheet). */
        @media (max-width: 1300px) {
            .video-layout-container {
                flex-direction: column !important;
            }

            .yt-video-section {
                width: 100% !important;
                flex: none !important;
            }

            .yt-sidebar-container,
            .events-sidebar {
                width: 100% !important;
            }

            .yt-sidebar-container {
                margin-top: 16px;
            }

            .fighters-row {
                grid-template-columns: 1fr !important;
            }

            .event-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }


        @media (max-width: 991px) {
            .yt-main {
                margin-left: 0;
                flex-direction: column;
            }

            .yt-sidebar-container,
            .events-sidebar {
                width: 100%;
            }

            .sidebar-video-card {
                flex-direction: column;
            }

            .sidebar-thumb {
                width: 100%;
            }

            .video-layout-container {
                flex-direction: column !important;
            }

            .yt-video-section {
                width: 100% !important;
                flex: none !important;
            }

            .yt-sidebar-container {
                width: 100% !important;
                margin-top: 16px;
            }

            .fighters-row {
                grid-template-columns: 1fr !important;
            }

            .match-center {
                order: -1;
            }

            .event-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .event-header-actions {
                align-self: flex-end;
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (max-width: 576px) {
            .video-stats-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .video-actions {
                width: 100%;
                overflow-x: auto;
                justify-content: flex-start;
            }

            .video-container {
                max-height: 50vh !important;
                border-radius: 0 !important;
            }

            .video-container video {
                object-fit: contain !important;
            }

            .video-title {
                font-size: 16px !important;
                margin: 12px 0 6px !important;
            }

            .channel-row {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .channel-info {
                width: 100%;
            }

            .subscribe-btn {
                width: 100%;
            }

            .description-panel {
                padding: 8px !important;
            }

            .match-card {
                padding: 12px !important;
            }

            .event-header {
                padding: 8px 0;
            }

            .event-text .title {
                font-size: 1.1rem;
            }

            .event-header-actions {
                gap: 4px;
            }

            .event-header-actions .action-btn {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
        }

        /* ============================================================
           MATCH HIGHLIGHTS — mobile bottom sheet (Option A)
           Desktop keeps its side pane untouched. Below 991px the pane
           becomes a bottom sheet so the player stays visible up top
           while points / coach reviews scroll in the lower half.
           ============================================================ */
        .hl-sheet-backdrop { display: none; }
        .hl-sheet-grab { display: none; }

        /* ============================================================
           MATCH REVIEW (.mrv) — the portrait Highlights design.
           A complete replacement rather than a restyle: below 991px this
           block IS the Highlights content and the desktop pane's own
           header/panels are hidden. Above 991px the reverse, so the side
           pane is exactly what it always was.
           Design: drafts/match-review-standalone.html
           ============================================================ */
        .mrv { display: none; }

        /* The view itself: hidden until Highlights is open on a narrow screen. */
        .mrv-view { display: none; }

        /* ── Event header on a phone ──────────────────────────────────
           The 56px logo tile (an event poster, or a trophy placeholder when
           there is none) costs 68px of a ~390px row and pushes the title into
           a narrow column where it wraps over several lines. On a phone the
           text is what matters, so the tile steps aside and the title, meta and
           actions take the full width. Desktop keeps it. */
        @media (max-width: 991px) {
            .event-header .event-logo { display: none; }
            .event-header-left { gap: 0; }
            .event-text { flex: 1 1 auto; min-width: 0; }
        }

        @media (max-width: 991px) {
            /* The old bottom sheet is retired on mobile — its content lives in
               the view now. Desktop keeps the side pane exactly as it was. */
            .events-sidebar { display: none !important; }
            .hl-sheet-grab, .hl-sheet-backdrop { display: none !important; }

            body.mrv-on { overflow: hidden !important; }

            body.mrv-on .mrv-view {
                display: flex; flex-direction: column;
                position: fixed; inset: 0;
                z-index: 3000;                 /* above the header and bottom nav */
                background: #0c0e11;
                padding-top: env(safe-area-inset-top);
            }
            /* The player sits at the top and never moves: it is a flex item with
               its own height, and nothing above or below it scrolls. */
            .mrv-video { flex: 0 0 auto; width: 100%; background: #000; }
            .mrv-video #ytpWrap { width: 100% !important; margin: 0 !important; border-radius: 0 !important; }

            /* .mrv fills the rest; only .mrv-scroll inside it moves. */
            body.mrv-on .mrv { display: flex; flex: 1; min-height: 0; }

            /* ── Player settings (gear) panel ─────────────────────────
               .ytp-wrap is `overflow: hidden` with `aspect-ratio: 16/9` and no
               explicit height. Two consequences:
                 · a tall panel is CLIPPED at the player's top edge, so on a phone
                   the first items (Mini player, Playback speed, Score bar) sit
                   above the clip and cannot be reached — the overflow is OUTSIDE
                   the panel, so its own scrolling cannot help;
                 · percentage heights have no basis to resolve against, so
                   `max-height: calc(100% - …)` collapses the panel to nothing.

               On a phone the player is full-width 16:9, so its height IS 56.25vw
               — a cap in those terms genuinely fits inside the clip, and the
               panel then scrolls internally (it is already overflow-y: auto).
               The 140px floor means it can never collapse.

               This applies to the whole mobile watch page, not just review mode:
               the clipping happens either way. It lives in the match view rather
               than the shared player component so the music and generic types
               keep exactly the behaviour they have today (RULE #3). */
            .ytp-wrap .ytp-settings-panel {
                max-height: max(140px, calc(56.25vw - 56px));
            }
        }


        @media (max-width: 991px) {
            /* the desktop pane's content steps aside */
            .events-sidebar > .tab-header,
            .events-sidebar > .tab-panels { display: none !important; }

            .events-sidebar { background: #0c0e11; padding: 0 !important; }

            /* Board and tabs hold still; only .mrv-scroll moves. min-height:0 is
               what allows a flex child to actually scroll instead of growing. */
            .mrv {
                display: flex; flex-direction: column;
                flex: 1; min-height: 0;
                background: #0c0e11;
                color: #e8eaed;
                font-family: 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
            }

            /* ── tabs ─────────────────────────────────────────────── */
            .mrv-tabs { display: flex; gap: 4px; padding: 12px 16px 0; flex-shrink: 0; }
            .mrv-tab {
                flex: 1; background: none; border: none; border-bottom: 2px solid transparent;
                padding: 10px 4px 12px; font-family: inherit; font-size: 14px; cursor: pointer;
                color: #6b7482; font-weight: 500;
            }
            .mrv-tab.is-on { color: #e8eaed; font-weight: 700; border-bottom-color: #e8eaed; }

            /* ── panes: one visible at a time ─────────────────────── */
            .mrv-pane { display: none; }
            .mrv-pane.is-on { display: flex; flex-direction: column; flex: 1; min-height: 0; }

            /* The ONLY scrolling region. min-height:0 is what lets a flex child
               scroll rather than grow to fit its content. */
            .mrv-scroll {
                flex: 1; min-height: 0;
                overflow-y: auto; -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                padding: 16px calc(16px + env(safe-area-inset-right)) 16px calc(16px + env(safe-area-inset-left));
            }

            /* round header */
            /* The round header holds at the top of the scroller while its own
               points scroll past, then the next round's header pushes it out and
               takes its place — plain CSS sticky, no scroll listener.
               An opaque background is required: without it the rows would scroll
               through it. Stretched over the scroller's side padding so the bar
               reaches both edges. */
            .mrv-rhead {
                position: sticky; top: 0; z-index: 2;
                display: flex; align-items: center; justify-content: space-between; gap: 10px;
                margin: 0 calc(-16px - env(safe-area-inset-left)) 0 calc(-16px - env(safe-area-inset-left));
                padding: 10px calc(16px + env(safe-area-inset-right)) 8px calc(16px + env(safe-area-inset-left));
                background: #0c0e11;
                border-bottom: 1px solid #171b21;
            }
            .mrv-rhead-l { display: flex; align-items: center; gap: 10px; min-width: 0; }
            .mrv-rname { font-size: 12px; font-weight: 800; letter-spacing: 1.5px; color: #e8eaed; }
            .mrv-rcount { font-size: 11px; color: #6b7482; font-weight: 500; white-space: nowrap; }
            .mrv-rhead-r { display: flex; gap: 12px; flex-shrink: 0; }
            .mrv-txtbtn { background: none; border: none; color: #6b7482; font-family: inherit; font-size: 11px; font-weight: 600; letter-spacing: .5px; cursor: pointer; padding: 4px; }
            .mrv-txtbtn:hover { color: #e8eaed; }
            .mrv-txtbtn-del:hover { color: oklch(0.72 0.17 25); }

            /* one point */
            .mrv-row { display: grid; grid-template-columns: 56px 16px 1fr auto; align-items: center; gap: 10px; padding: 13px 0; border-bottom: 1px solid #171b21; cursor: pointer; -webkit-tap-highlight-color: transparent; }

            /* The shared .hlp-* point row, tuned for a finger: taller rows, a
               divider between them, and a press state — the design is the same,
               only the touch target grows. */
            .mrv-hlp { padding: 12px 4px; border-bottom: 1px solid #171b21; -webkit-tap-highlight-color: transparent; }
            .mrv-hlp:active { background: #12151b; }
            .mrv-hlp .hlp-label { font-size: 13px; }
            .mrv-hlp .hlp-who { font-size: 11px; }
            .mrv-hlp .hlp-run { font-size: 13px; }
            /* The time is a real button for keyboard users; strip the chrome so it
               reads as the same monospace stamp as the other two renderers. */
            .mrv-hlp-time { background: none; border: 0; padding: 4px 0; text-align: left; cursor: pointer; font-size: 12px; }
            .mrv-hlp .hlp-ico { padding: 6px 7px; font-size: 13px; }
            .mrv-row:active { background: #12151b; }
            .mrv-time {
                font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;
                color: #9aa3b0; background: #151920; border: 1px solid #20252d; border-radius: 6px;
                padding: 5px 0; cursor: pointer; text-align: center;
            }
            .mrv-time:hover { color: #e8eaed; border-color: #3a4250; }
            .mrv-dot { width: 8px; height: 8px; border-radius: 50%; background: #3a4250; justify-self: center; }
            .mrv-dot-red  { background: oklch(0.62 0.19 25); }
            .mrv-dot-blue { background: oklch(0.62 0.19 255); }
            .mrv-what { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
            .mrv-act { font-size: 13px; font-weight: 600; color: #c6ccd4; overflow-wrap: anywhere; }
            .mrv-note { font-size: 11px; color: #6b7482; overflow-wrap: anywhere; }
            .mrv-run { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; display: flex; gap: 5px; align-items: center; }
            .mrv-run-red  { color: oklch(0.72 0.17 25); }
            .mrv-run-blue { color: oklch(0.72 0.15 255); }
            .mrv-run-sep  { color: #4a525e; }
            .mrv-rowtools { display: flex; gap: 2px; margin-left: 6px; }
            .mrv-icon { background: none; border: none; color: #4a525e; font-size: 13px; cursor: pointer; padding: 2px 4px; }
            .mrv-icon:hover { color: #e8eaed; }

            /* coach notes */
            .mrv-note-card { background: #12151b; border: 1px solid #1c212a; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; cursor: pointer; -webkit-tap-highlight-color: transparent; }
            .mrv-note-card:active { background: #171b22; border-color: #262c36; }
            .mrv-note-top { display: flex; align-items: center; gap: 10px; }
            .mrv-ava { width: 28px; height: 28px; flex: none; border-radius: 50%; background: #20252d; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #9aa3b0; }
            .mrv-who { display: flex; flex-direction: column; min-width: 0; }
            .mrv-coach { font-size: 12px; font-weight: 700; }
            .mrv-at { background: none; border: none; padding: 0; text-align: left; font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 11px; color: #8ab4f8; cursor: pointer; }
            .mrv-tag { margin-left: auto; font-size: 14px; line-height: 1; }
            .mrv-note-text { font-size: 13px; line-height: 1.5; color: #c6ccd4; overflow-wrap: anywhere; }

            .mrv-empty { padding: 28px 4px; text-align: center; color: #6b7482; font-size: 13px; }
            /* Pinned beneath the scroller, never scrolls away. */
            .mrv-adds {
                display: flex; gap: 8px; flex-shrink: 0;
                padding: 12px calc(16px + env(safe-area-inset-right)) calc(12px + env(safe-area-inset-bottom)) calc(16px + env(safe-area-inset-left));
                border-top: 1px solid #171b21; background: #0c0e11;
            }
            .mrv-add { flex: 1; background: #151920; border: 1px dashed #2c333d; border-radius: 10px; color: #9aa3b0; font-family: inherit; font-size: 13px; font-weight: 600; padding: 13px; cursor: pointer; }
            .mrv-add:hover { color: #e8eaed; border-color: #3a4250; }
        }

        @media (max-width: 991px) {
            .events-sidebar {
                display: flex !important;   /* override desktop display:none so it can slide */
                position: fixed;
                left: 0; right: 0; bottom: 0;
                /* Top sits exactly at the player's bottom edge so nothing (title,
                   actions, channel row) shows between the video and the sheet. JS
                   sets the precise px; the 56.25vw fallback = a full-width 16:9 player. */
                top: var(--hl-sheet-top, 56.25vw);
                width: 100% !important;
                height: auto; max-height: none;
                margin: 0 !important;
                border-radius: 18px 18px 0 0;
                box-shadow: 0 -14px 44px rgba(0, 0, 0, 0.55);
                transform: translateY(100%);
                transition: transform 0.34s cubic-bezier(0.32, 0.72, 0, 1);
                pointer-events: none;
                z-index: 1400;              /* above the fixed bottom nav (z-index 1000) */
            }
            .events-sidebar.show {
                display: flex !important;
                transform: translateY(0);
                pointer-events: auto;
            }
            /* Drag affordance at the top of the sheet */
            .hl-sheet-grab {
                display: block; flex-shrink: 0;
                width: 40px; height: 4px; border-radius: 3px;
                background: rgba(255, 255, 255, 0.3);
                margin: 10px auto 4px; cursor: pointer;
            }
            /* Transparent tap-to-dismiss catcher — no dim, so the match video keeps
               full brightness and detail while the sheet is open. */
            .hl-sheet-backdrop {
                display: block;
                position: fixed; inset: 0;
                background: transparent;
                visibility: hidden;
                z-index: 1399;
                /* The sheet only closes via its own toggle now — the backdrop
                   is purely decorative and MUST let taps pass through to the
                   video / other controls underneath. */
                pointer-events: none;
            }
            .hl-sheet-backdrop.show { visibility: visible; }

            /* Reclaim the top bar's space while the sheet is open: slide the header
               up and float the scroll area to the very top so the player gets more
               room. Both revert the moment the sheet closes. */
            .yt-header { transition: transform 0.3s ease; }
            body.hl-sheet-open .yt-header { transform: translateY(-100%); }
            #main.yt-main { transition: top 0.3s ease; }
            body.hl-sheet-open #main.yt-main { top: 0 !important; }

            /* Hide the bottom nav too so the sheet owns the whole lower area.
               !important overrides the base `.yt-bottom-nav { transform: none !important }`. */
            .yt-bottom-nav { transition: transform 0.3s ease; }
            body.hl-sheet-open .yt-bottom-nav { transform: translateY(100%) !important; }

            /* Coach-note overlay on the small mobile player: pinned to the very
               bottom edge (max 2 lines) so it never blocks the view — but only
               when the user hasn't dragged it to a specific spot. Font-size /
               padding are driven by container queries so they scale with the
               player width automatically. */
            .coach-note-overlay:not(.positioned) {
                bottom: 6px;
                max-width: 94%;
            }
            .coach-note-overlay.show:not(.positioned) {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
        }
        @media (max-width: 991px) and (prefers-reduced-motion: reduce) {
            .events-sidebar,
            .yt-header,
            .yt-bottom-nav,
            #main.yt-main { transition: none; }
        }

        /* ============================================================
           MATCH HIGHLIGHTS — fullscreen landscape drawer
           Native fullscreen renders only #ytpWrap + descendants, so JS
           relocates the pane inside the player. It becomes a narrow solid
           right-side drawer, and the video (inside .ytp) is squeezed into
           the remaining left area so nothing overlaps — both are fully
           visible side by side. Only while .ytp-fullscreen is on.
           ============================================================ */
        .ytp-wrap.ytp-fullscreen { --hl-drawer-w: clamp(230px, 30vw, 360px); }

        .ytp-wrap.ytp-fullscreen .events-sidebar {
            display: flex !important;
            position: absolute; top: 0; right: 0; bottom: 0; left: auto;
            /* !important is required: on a <=991px landscape phone the mobile sheet
               rule sets width:100% !important, which would otherwise stretch the
               drawer across the whole video. */
            width: var(--hl-drawer-w) !important;
            height: auto !important; max-height: none !important;
            margin: 0 !important; border-radius: 0 !important;
            background: var(--bg-secondary, #17171b);   /* solid: the video is squeezed aside, not behind it */
            border-left: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: none;
            transform: translateX(100%);
            transition: transform 0.34s cubic-bezier(0.32, 0.72, 0, 1);
            pointer-events: none; z-index: 40;
        }
        .ytp-wrap.ytp-fullscreen .events-sidebar.show { transform: translateX(0); pointer-events: auto; }
        .ytp-wrap.ytp-fullscreen .events-sidebar .hl-sheet-grab { display: none; }

        /* ────────────────────────────────────────────────────────────────
           FULLSCREEN DRAWER CONTENT — drafts/fullscreen-highlights-standalone.html
           Every colour, size and spacing below is taken from that file.
           ──────────────────────────────────────────────────────────────── */
        .fsh { display: none; }

        /* In fullscreen the drawer shows .fsh and nothing else. */
        .ytp-wrap.ytp-fullscreen .events-sidebar > .tab-header,
        .ytp-wrap.ytp-fullscreen .events-sidebar > .tab-panels { display: none !important; }
        .ytp-wrap.ytp-fullscreen .fsh { display: flex; flex-direction: column; flex: 1; min-height: 0; }

        .ytp-wrap.ytp-fullscreen { --hl-drawer-w: 280px; }
        .ytp-wrap.ytp-fullscreen .events-sidebar {
            background: rgba(10, 12, 15, 0.94);
            backdrop-filter: blur(6px);
            border-left: 1px solid rgba(255, 255, 255, 0.08);
            color: #e8eaed;
            font-family: 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
            padding: 0 !important;
        }

        /* header: two tabs + close */
        .fsh-head { display: flex; align-items: center; padding: 12px 12px 10px; gap: 4px; flex-shrink: 0; }
        .fsh-tab {
            flex: 0 1 auto; background: none; border: none; border-bottom: 2px solid transparent;
            padding: 6px 8px 8px; font-family: inherit; font-size: 12px; cursor: pointer;
            color: #6b7482; font-weight: 500;
        }
        .fsh-tab.is-on { color: #e8eaed; font-weight: 700; border-bottom-color: #e8eaed; }
        .fsh-x {
            margin-left: auto; background: none; border: none; color: #6b7482;
            font-size: 18px; line-height: 1; cursor: pointer; padding: 4px;
        }
        .fsh-x:hover { color: #e8eaed; }

        /* lists — one at a time, each scrolls on its own */
        .fsh-list { display: none; }
        .fsh-list.is-on {
            display: flex; flex-direction: column; flex: 1; min-height: 0;
            overflow-y: auto; overscroll-behavior: contain;
            padding: 0 10px 12px; gap: 4px;
        }
        .fsh-list[data-fsh-panel="coach"].is-on { padding: 0 12px 12px; gap: 8px; }

        /* round header: gold label, count, rule */
        .fsh-rhead { display: flex; align-items: center; gap: 8px; padding: 4px 8px 8px; }
        .fsh-rname { font-size: 11px; font-weight: 800; letter-spacing: 2px; color: oklch(0.85 0.15 90); }
        .fsh-rcount { font-size: 10px; color: #6b7482; font-weight: 500; white-space: nowrap; }
        .fsh-rrule { flex: 1; height: 1px; background: oklch(0.85 0.15 90 / 0.4); }

        /* one point */
        /*
         * The point row, shared by the Points tab and the fullscreen sheet.
         *
         * One grid: time · side bar · what happened · running score · tools. The
         * fullscreen sheet already used these values; the Points tab used pills and
         * emoji instead, so the same moment looked like two different products
         * depending on which panel you opened. These are the shared classes.
         */
        .hlp-row {
            display: grid;
            grid-template-columns: 44px 4px 1fr auto auto;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
            padding: 9px 8px;
            cursor: pointer;
        }
        .hlp-row:hover { background: rgba(255, 255, 255, 0.06); }
        .hlp-time { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; color: #9aa3b0; }
        .hlp-bar { width: 4px; height: 22px; border-radius: 2px; display: block; background: #4a525e; }
        .hlp-bar-red  { background: oklch(0.62 0.19 25); }
        .hlp-bar-blue { background: oklch(0.62 0.19 255); }
        /* Both sides scored at the same instant: one row, one bar, split in two. */
        .hlp-bar-both { background: linear-gradient(oklch(0.62 0.19 25) 50%, oklch(0.62 0.19 255) 50%); }
        .hlp-what { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
        .hlp-label { font-size: 12px; font-weight: 700; color: #e8eaed; letter-spacing: .5px; }
        .hlp-who { font-size: 10px; color: #6b7482; font-weight: 500; }
        .hlp-run { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; display: flex; gap: 3px; }
        .hlp-run-red  { color: oklch(0.72 0.17 25); }
        .hlp-run-blue { color: oklch(0.72 0.15 255); }
        .hlp-run-sep  { color: #4a525e; }
        .hlp-tools { display: flex; gap: 2px; }
        .hlp-ico {
            background: none; border: none; color: #4a525e; cursor: pointer;
            padding: 4px 5px; border-radius: 5px; font-weight: 700; line-height: 1;
        }
        .hlp-ico-edit { font-size: 11px; }
        .hlp-ico-del  { font-size: 12px; font-weight: 600; }
        .hlp-ico:hover { color: #e8eaed; background: rgba(255, 255, 255, 0.08); }
        .hlp-ico-del:hover { color: oklch(0.72 0.17 25); background: rgba(255, 255, 255, 0.08); }

        .fsh-row {
            display: grid; grid-template-columns: 44px 4px 1fr auto auto;
            align-items: center; gap: 8px;
            border-radius: 8px; padding: 9px 8px; cursor: pointer;
        }
        .fsh-row:hover { background: rgba(255, 255, 255, 0.04); }
        .fsh-time { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; color: #9aa3b0; }
        .fsh-bar { width: 4px; height: 22px; border-radius: 2px; display: block; background: #4a525e; }
        .fsh-bar-red  { background: oklch(0.62 0.19 25); }
        .fsh-bar-blue { background: oklch(0.62 0.19 255); }
        /* Both sides scored at the same instant: one bar, split red over blue. */
        .fsh-bar-both { background: linear-gradient(oklch(0.62 0.19 25) 50%, oklch(0.62 0.19 255) 50%); }
        .fsh-what { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
        .fsh-label { font-size: 12px; font-weight: 700; color: #e8eaed; letter-spacing: .5px; }
        .fsh-who { font-size: 10px; color: #6b7482; font-weight: 500; }
        .fsh-run { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; display: flex; gap: 3px; }
        .fsh-run-red  { color: oklch(0.72 0.17 25); }
        .fsh-run-blue { color: oklch(0.72 0.15 255); }
        .fsh-run-sep  { color: #4a525e; }
        .fsh-tools { display: flex; gap: 2px; }
        .fsh-ico {
            background: none; border: none; color: #4a525e; font-size: 11px; font-weight: 700;
            cursor: pointer; padding: 4px 5px; border-radius: 5px; font-family: inherit;
        }
        .fsh-ico:hover { color: #e8eaed; background: rgba(255, 255, 255, 0.06); }

        /* coach note card */
        .fsh-note {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 10px; padding: 10px 12px;
            display: flex; flex-direction: column; gap: 6px; cursor: pointer;
        }
        .fsh-note:hover { background: rgba(255, 255, 255, 0.06); }
        .fsh-note-top { display: flex; align-items: center; gap: 8px; }
        .fsh-note-time { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; color: #8ab4f8; }
        .fsh-note-coach { font-size: 10px; color: #6b7482; font-weight: 600; }
        .fsh-note-tag { margin-left: auto; font-size: 12px; line-height: 1; }
        .fsh-note-text { font-size: 11.5px; line-height: 1.5; color: #c6ccd4; }

        .fsh-empty { padding: 22px 8px; text-align: center; color: #6b7482; font-size: 12px; }
        .fsh-adds { display: flex; gap: 6px; margin: 4px 8px 0; }
        .fsh-add {
            flex: 1; background: none; border: 1px dashed rgba(255, 255, 255, 0.2);
            border-radius: 8px; color: #9aa3b0; font-family: inherit;
            font-size: 11px; font-weight: 600; padding: 9px; cursor: pointer;
        }
        .fsh-add:hover { color: #e8eaed; border-color: rgba(255, 255, 255, 0.35); }

        /* The scoring toasts belong to the video; with the drawer open there is
           no room for them, and the draft hides them whenever it is open. */
        .ytp-wrap.ytp-fullscreen.hl-drawer-open .msb-feed { display: none !important; }



        /* ── Coach note in landscape fullscreen ──────────────────────────
           Fullscreen is the big screen, so the note goes back to being a
           real subtitle: centred over the video area, clear of the control
           bar, and no longer clamped to the two-line strip. That strip is a
           rule for the SMALL inline player (<=991px), and a landscape phone
           in fullscreen matches that width too — which pinned the note to
           the bezel behind the controls. Overridden here, not there, so the
           inline player keeps the behaviour it was designed with.
           z-index clears the highlights drawer (40) as well as the chrome. */
        .ytp-wrap.ytp-fullscreen .coach-note-overlay { z-index: 45; }
        .ytp-wrap.ytp-fullscreen .coach-note-overlay:not(.positioned) {
            bottom: clamp(56px, 8cqi, 120px);
            max-width: min(80%, 900px);
        }
        .ytp-wrap.ytp-fullscreen .coach-note-overlay.show:not(.positioned) {
            display: block;
            -webkit-line-clamp: none;
            overflow: visible;
        }

        /* Squeeze the video into the remaining left area when the drawer is open.
           Everything else (controls, coach caption, the Highlights button) lives
           inside .ytp, so it all moves with the video — nothing sits under the drawer. */
        .ytp-wrap.ytp-fullscreen .ytp { transition: width 0.34s cubic-bezier(0.32, 0.72, 0, 1); }
        .ytp-wrap.ytp-fullscreen.hl-drawer-open .ytp { width: calc(100% - var(--hl-drawer-w)); }
        @media (prefers-reduced-motion: reduce) {
            .ytp-wrap.ytp-fullscreen .events-sidebar { transition: none; }
        }
    </style>

    
<style>
    /* This page carries no header and no tab bar, so nothing is reserved for
       them. Written here rather than by editing the design's own rules, which
       stay as they were handed over. */
    #main.yt-main { margin-top: 0 !important; margin-left: 0 !important; min-height: 100vh; }

    @media (max-width: 991px) {
        #main.yt-main { top: 0 !important; bottom: 0 !important; }
    }

    /* The Highlights chip exists for fullscreen and nowhere else.
       On the page the pane is not a drawer — it IS the page under the video —
       so a control that "opens" it would toggle a state the reader is already
       in. In fullscreen it genuinely is a drawer over the picture, and there
       is no other way to reach it. */
    .match-highlights-toggle { display: none !important; }
    .ytp-wrap.ytp-fullscreen .match-highlights-toggle { display: flex !important; }

    /* The match card IS the Match tab.
       Every pane that holds it gives up its padding and its background, so the
       card meets all four edges instead of sitting in a lighter box inside a
       darker one. It scrolls itself rather than through a wrapper. */
    .mrv-pane-flush { padding: 0 !important; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }

    /* A card taller than its pane was being SQUEEZED rather than scrolled.
       These panes are column flex containers, and a flex item shrinks before it
       overflows — so the card compressed to the available height and clipped
       its own contents, with nothing for the scroller to scroll. Telling the
       card to keep its natural height gives the pane something to scroll. */
    .mrv-pane-flush > *,
    .fsh-list[data-fsh-panel="match"] > *,
    #tab-match > * { flex: 0 0 auto !important; }
    .mrv-pane-flush,
    .fsh-list[data-fsh-panel="match"],
    #tab-match { padding: 0 !important; background: #000 !important; }

    /* One official per row.
       Several names wrapping across a line read as a single run of text; a
       column gives each their own line, which is how a scoresheet lists them. */
    .macm-refs,
    .mac-refs { flex-direction: column !important; align-items: flex-start !important; }
    .macm-foot,
    .mac-foot { align-items: flex-start !important; }

    /* The portrait beside each name. 3:4 already, from the design — but it was
       sized for several officials sharing a line. On its own row it has the
       width, so it grows to the height of the label-plus-name beside it. */
    .macm-ref-pic { width: 10cqw !important; }
    .mac-ref-pic { width: 5.6cqw !important; }

    /* The name beside it grows with it. The design's type was cut for a portrait
       a little over half this size, sharing a line with four other officials;
       against the larger picture on its own row it read as a caption. */
    .macm-ref { gap: 3cqw !important; }
    .macm-ref-lbl { font-size: 2.9cqw !important; letter-spacing: .55cqw !important; }
    .macm-ref-name { font-size: 4.6cqw !important; }
    .macm-ref-txt { gap: .6cqw !important; }

    /* ── The review tabs ──────────────────────────────────────────────────
       The design's row was page-sized type sitting directly under a video: a
       14px label per tab, four of them, competing with the picture above.
       These are a segmented control instead — small, capitalised, on their own
       dark rail, with the active one filled rather than underlined so it reads
       at a glance without a rule crossing the screen. */
    .mrv-tabs {
        gap: 4px !important;
        padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
        background: #0c0e11;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
    }
    .mrv-tab {
        flex: 1 1 0 !important;
        min-width: 0;
        border: 0 !important;
        border-radius: 999px !important;
        padding: 7px 6px !important;
        background: rgba(255, 255, 255, .04) !important;
        color: #7b8494 !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        letter-spacing: .04em;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background .15s ease, color .15s ease;
    }
    .mrv-tab.is-on {
        background: #e8eaed !important;
        color: #0c0e11 !important;
        font-weight: 700 !important;
    }

    /* ── The Points list ──────────────────────────────────────────────────
       One line per moment, as the design draws it, full width: the scroller
       gives up its side padding and the row takes it, so the tap target and
       the divider under it run edge to edge rather than floating in a channel.

       The running score is what a reader scans for, so it is the one large
       thing on the row. The timestamp stays small beside it — it is a label
       for the tap, not the headline. Its column sizes to its own content
       rather than the design's fixed 44px, which was cut for MM:SS and is too
       narrow for the millisecond stamp. */
    .mrv-pane[data-mrv-panel="points"] .mrv-scroll {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .mrv-hlp {
        grid-template-columns: auto 4px 1fr auto auto !important;
        border-radius: 0 !important;
        padding: 12px calc(14px + env(safe-area-inset-right)) 12px calc(14px + env(safe-area-inset-left)) !important;
    }
    .mrv-hlp-time {
        font-size: 11px !important;
        font-weight: 600 !important;
        font-variant-numeric: tabular-nums;
        /* The row's grid gap is one value for every column, so the breathing
           room between the stamp and the corner bar is a margin on the stamp
           rather than a wider gap everywhere. */
        margin-right: 8px !important;
    }
    .mrv-hlp .hlp-run {
        font-size: 22px !important;
        font-weight: 700 !important;
        line-height: 1;
        gap: 5px !important;
        align-items: baseline;
        font-variant-numeric: tabular-nums;
    }
    .mrv-hlp .hlp-run-sep { font-size: 14px; }

    /* The corner bar reads as the row's colour, so it runs most of the row's
       height rather than sitting as a short tick beside it. */
    .mrv-hlp .hlp-bar { height: 32px !important; }

    /* The round header cancelled the scroller's side padding with a matching
       negative margin — and that padding is gone now, so it was hanging 16px
       off the left edge. It gets its own indent instead, a little deeper than
       the rows beneath it so it reads as the heading of the group. */
    .mrv-pane[data-mrv-panel="points"] .mrv-rhead {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: calc(18px + env(safe-area-inset-left)) !important;
        padding-right: calc(18px + env(safe-area-inset-right)) !important;
    }

    .macm,
    .mac-card,
    .mac-shell {
        margin: 0 !important;
        width: 100% !important;
        max-width: none !important;
        border-left: 0 !important;
        border-right: 0 !important;
        border-radius: 0 !important;
        background: #000 !important;
    }
</style>
</head>
<body class=" ">
<script>
/*
 * The bout's own endpoints. Everything below builds its requests from here, so
 * there is exactly one place that knows what this page may ask the server for.
 *
 * A note and a comment travel on their UUID. The numeric `id` the design uses
 * in its markup is a position in the list it rendered — enough for its own
 * in-memory lookups, and deliberately not something that addresses a row.
 */
window.TOB = {
    csrf:        @json(csrf_token()),
    data:        @json(route('me.events.bout.video.data', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    notes:       @json(route('me.events.bout.notes.store', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    comments:    @json(route('me.events.bout.comments.store', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    destroy:     @json($may_delete_video ? $delete_video_url : null),
    download:    @json($video['mp4'] ?? null),
    share:       @json(url()->current()),
    gallery:     @json($bout['gallery_url']),
    note:        function (uuid) { return this.notes + '/' + encodeURIComponent(uuid); },
    comment:     function (uuid) { return this.comments + '/' + encodeURIComponent(uuid); },
    commentLike: function (uuid) { return this.comment(uuid) + '/like'; },
    // A note's uuid from the position the design is holding.
    noteUuid:    function (id) {
        var r = (window.matchReviews || []).find(function (x) { return Number(x.id) === Number(id); });
        return r ? r.uuid : null;
    },
};
</script>
    <!-- Header -->
    <!-- Header -->


    <!-- Impersonation Banner -->
    



    <!-- Main Content -->
    <main class="yt-main video-view-page" id="main">
            
    <!-- Video Layout Container -->
    <div class="video-layout-container" style="display: flex; gap: 24px; max-width: 1800px; margin: 0 auto;">
        <!-- Video Section -->
        <div class="yt-video-section">
            <!-- Video Player -->
            <div class="ytp-wrap " id="ytpWrap"
     data-video-id="{{ $bout['match_no'] }}">
<div class="ytp" id="videoContainer" tabindex="0">

    
    <video id="videoPlayer" playsinline preload="auto" autoplay muted></video>

    {{-- Only ever seen in fullscreen; see the rule in the page's override
         block. The initialiser treats it as optional either way. --}}
    <button class="match-highlights-toggle" id="matchHighlightsToggle"
            title="{{ __('events.bout_video_tab_highlights') }}">
        <span>{{ __('events.bout_video_tab_highlights') }}</span>
    </button>

    

                    
                    <div class="coach-note-overlay" id="coachNoteOverlay"></div>
                    
                    <div class="replay-badge" id="replayBadge" hidden>
                        <span class="replay-badge-word">REPLAY</span>
                        <span class="replay-badge-speed" id="replayBadgeSpeed">×1</span>
                    </div>
                    
                    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Zen+Old+Mincho:wght@400;700&display=swap" rel="stylesheet">

<style>
/* ────────────────────────────── keyframes ────────────────────────────── */
@keyframes msbRiseIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
@keyframes msbPulse  { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }

/* ────────────────────────────── root layer ─────────────────────────────
   No z-index on the root: source order inside #ytpWrap places the video
   controls AFTER the overlay slot, so they paint on top and stay clickable.
------------------------------------------------------------------------ */
.msb-root {
    position: absolute; inset: 0;
    pointer-events: none;
    font-family: 'Barlow Condensed', sans-serif;
    color: #efe9e0;
    -webkit-font-smoothing: antialiased;
    overflow: hidden;
}
.msb-root * { box-sizing: border-box; }

/*
 * Fixed 1150 px design canvas. `msb-scale` is positioned at (0, 0), sized
 * to 1150 px wide, and JS-scaled by `transform: scale(playerWidth / 1150)`
 * so every child element (positioned in that 1150 px coord space) shrinks
 * or grows proportionally with the player. Height is set at runtime.
 */
.msb-scale {
    position: absolute; top: 0; left: 0;
    width: 1150px;
    transform-origin: top left;
    /* transform + height set by JS */
}

/* Scrim disabled — every scoreboard text element has its own text-shadow
   for legibility, so we don't tint the video at all. */
.msb-scrim { display: none; }

/* ────────────────────────────── match info (top-left) ────────────────── */
.msb-info { position: absolute; top: 22px; left: 26px; display: flex; align-items: stretch; gap: 12px; }
.msb-info .rule { width: 4px; background: linear-gradient(#e8534a, #7c1d18); }
.msb-info .txt  { display: flex; flex-direction: column; gap: 2px; }
.msb-info .disc {
    font-family: 'Zen Old Mincho', serif; font-size: 15px; letter-spacing: .34em;
    color: #efe9e0; text-transform: uppercase;
}
.msb-info .sub {
    font-size: 13px; letter-spacing: .28em; color: #cfc9c1;
    text-transform: uppercase; text-shadow: 0 1px 6px rgba(0,0,0,.9);
}
/* Auto-hide row when its text is empty (server empties data-msb-empty) */
.msb-info [data-msb-empty] { display: none; }

/* ────────────────────────────── live scoring ticker (top-right) ──────── */
/* top:56px clears the existing Highlights toggle chip that sits at top:8/right:8 (h=24) */
.msb-feed { position: absolute; top: 56px; right: 24px; width: 262px; display: flex; flex-direction: column; gap: 7px; }
.msb-feed .hdr {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    font-size: 12px; letter-spacing: .3em; color: #d5cfc7; text-transform: uppercase;
    text-shadow: 0 1px 6px rgba(0,0,0,.9);
}
.msb-feed .hdr .dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #e8534a; animation: msbPulse 1.6s infinite;
}
.msb-feed .list { display: flex; flex-direction: column; gap: 7px; }
.msb-feed .entry {
    display: flex; align-items: center; justify-content: flex-end; gap: 10px;
    padding: 7px 10px;
    background: rgba(10,10,12,.62); backdrop-filter: blur(6px);
    border-right: 3px solid transparent;
}
.msb-feed .entry.msb-new { animation: msbRiseIn .45s ease both; }
.msb-feed .entry .ts   { font-size: 12px; line-height: 1; letter-spacing: .16em; color: #79736c; }
.msb-feed .entry .name { font-size: 17px; line-height: 1; letter-spacing: .12em; font-weight: 700; color: #efe9e0; text-transform: uppercase; }
.msb-feed .entry .pts  { font-family: 'Zen Old Mincho', serif; font-size: 19px; line-height: 1; font-weight: 700; }

/*
 * Score bar + timeline visuals live entirely as inline styles on the verbatim
 * markup from drafts/score-bar.html. Nothing here targets those elements
 * except the visibility toggles below (via data-msb-part), so the design's
 * exact pixel measurements are never overridden.
 */

/* ────────────────────────────── visibility toggles ──────────────────── */
/* Body-level classes hide any part with the matching data-msb-part attr
   without touching its inline styles. Master-off hides everything. */
body.msb-off-scorebar  [data-msb-part="scorebar"],
body.msb-off-timeline  [data-msb-part="timeline"],
body.msb-off-info      [data-msb-part="info"],
body.msb-off-feed      [data-msb-part="feed"],
body.msb-off-clubs     [data-msb-part="clubs"],
body.msb-off-flags     [data-msb-part="flags"],
body.msb-off-penalties [data-msb-part="penalties"] { display: none !important; }

/* ────────────────────────────── responsive ──────────────────────────── */
/* The `msb-small` class is still toggled by JS off a ResizeObserver (player
   width < 700px, i.e. any phone) and remains available for sizing.

   It no longer HIDES anything. It used to force `display: none` on the match
   identity and the scoring ticker below 700px (brief §7), which meant that on
   a phone those two rows were invisible even with their gear toggles switched
   on — the menu said one thing and the video showed another. The toggle is the
   user's explicit instruction and now wins; anyone who finds them cramped on a
   small player can switch them off, which is what the toggle is for. */

/* ────────────────────────────── injected gear rows ──────────────────── */
/* Matches the design prototype's custom look (26×14 track, 10 px bone knob),
   scoped so it never touches the platform's other rows. */
.msb-gear-header {
    padding: 10px 14px;
    font-size: 11px; letter-spacing: .3em; color: #8f8a83; text-transform: uppercase;
    border-top: 1px solid rgba(255,255,255,.1); margin-top: 4px;
    font-family: 'Barlow Condensed', sans-serif;
}
.msb-gear-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; padding: 9px 14px;
    background: transparent; border: 0; width: 100%; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 14px; letter-spacing: .14em; color: #efe9e0;
    text-transform: uppercase; text-align: left;
    transition: background .15s ease;
}
.msb-gear-row:hover { background: rgba(255,255,255,.07); }
.msb-gear-track {
    position: relative; width: 26px; height: 14px; flex: none;
    background: #3a3734; transition: background .18s ease;
}
.msb-gear-knob {
    position: absolute; top: 2px; left: 2px;
    width: 10px; height: 10px; background: #efe9e0;
    transition: left .18s ease;
}
.msb-gear-row.on .msb-gear-track { background: #e8534a; }
.msb-gear-row.on .msb-gear-knob  { left: 14px; }
/* Turn the gear icon red while the settings panel is open — matches design */
body.msb-menu-open #ytpSettingsBtn svg { fill: #e8534a; }
</style>
<div class="msb-root" id="msbRoot" data-video-id="193">
    <div class="msb-scrim"></div>

    
    <div class="msb-scale" id="msbScale">

    
    <div class="msb-info" data-msb="info" data-msb-part="info">
        <div class="rule"></div>
        <div class="txt">
            <div class="disc" data-msb="disc"></div>
            <div class="sub"  data-msb="sub"></div>
        </div>
    </div>

    
    <div class="msb-feed" data-msb="feedWrap" data-msb-part="feed">
        <div class="hdr"><span class="dot"></span><span>Live scoring</span></div>
        <div class="list" data-msb="feed"></div>
    </div>

    
    <div data-msb-part="scorebar" style="position:absolute;left:0;right:0;bottom:56px;padding:0 26px;display:flex;align-items:stretch;gap:0;height:84px;z-index:2;pointer-events:none">

      
      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(122,26,22,.94), rgba(180,52,44,.9));border-bottom:3px solid #ff6a5e">
        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
          <div data-msb="redClub" data-msb-part="clubs" style="width:46px;height:46px;flex:none;background:rgba(0,0,0,.32);border:1px solid rgba(255,255,255,.24);display:flex;align-items:center;justify-content:center;font-family:ui-monospace,monospace;font-size:8px;letter-spacing:.05em;color:rgba(255,255,255,.65);text-align:center;line-height:1.25">CLUB<br>LOGO</div>
          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <span data-msb="redFlag" data-msb-part="flags" style="width:26px;height:17px;flex:none;background:repeating-linear-gradient(135deg,rgba(255,255,255,.28) 0 4px,rgba(255,255,255,.1) 4px 8px);box-shadow:0 0 0 1px rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-family:ui-monospace,monospace;font-size:7px;color:rgba(255,255,255,.85)">FLAG</span>
              <span data-msb="redName" style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Fawzia Abdulla</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
              <span data-msb="redClubName" data-msb-part="clubs" style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(255,232,228,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Sparta Worrior</span>
              <span data-msb="redSenshu" data-msb-part="penalties" style="display:none;font-size:10px;letter-spacing:.18em;padding:1px 6px;background:#ffdf6b;color:#3a2a00;font-weight:700;flex:none">SENSHU</span>
            </div>
          </div>
          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:5px">
            <span data-msb="redCorner" style="font-size:11px;letter-spacing:.24em;color:rgba(255,236,232,.7)">AKA</span>
            <span data-msb="redScore" style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">0</span>
          </div>
        </div>
      </div>

      
      <div style="width:118px;flex:none;transform:skewX(-9deg);background:rgba(9,9,11,.9);backdrop-filter:blur(8px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-bottom:3px solid #2c2a28">
        <div data-msb="roundLabel" style="transform:skewX(9deg);font-size:11px;letter-spacing:.3em;color:#8f8a83;text-transform:uppercase">Round 1</div>
        <div data-msb="clock" style="transform:skewX(9deg);font-size:34px;line-height:1;font-weight:800;color:#efe9e0;font-variant-numeric:tabular-nums">0:00</div>
        <div style="transform:skewX(9deg);display:flex;gap:4px">
          <span style="width:20px;height:3px;background:#e8534a"></span>
          <span style="width:20px;height:3px;background:#e8534a"></span>
          <span style="width:20px;height:3px;background:#3a3734"></span>
        </div>
      </div>

      
      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(30,72,140,.9), rgba(18,44,92,.94));border-bottom:3px solid #6aa6ff">
        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-start;gap:5px">
            <span data-msb="blueCorner" style="font-size:11px;letter-spacing:.24em;color:rgba(226,238,255,.7)">AO</span>
            <span data-msb="blueScore" style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">0</span>
          </div>
          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px;align-items:flex-end;text-align:right">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <span data-msb="blueName" style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Sharifa Sanad</span>
              <span data-msb="blueFlag" data-msb-part="flags" style="width:26px;height:17px;flex:none;background:repeating-linear-gradient(135deg,rgba(255,255,255,.28) 0 4px,rgba(255,255,255,.1) 4px 8px);box-shadow:0 0 0 1px rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-family:ui-monospace,monospace;font-size:7px;color:rgba(255,255,255,.85)">FLAG</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
              <span data-msb="bluePenalty" data-msb-part="penalties" style="display:none;font-size:10px;letter-spacing:.18em;padding:1px 6px;background:#c8492f;color:#fff;font-weight:700;flex:none">C1</span>
              <span data-msb="blueClubName" data-msb-part="clubs" style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(226,238,255,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Al Hala Karate Club</span>
            </div>
          </div>
          <div data-msb="blueClub" data-msb-part="clubs" style="width:46px;height:46px;flex:none;background:rgba(0,0,0,.32);border:1px solid rgba(255,255,255,.24);display:flex;align-items:center;justify-content:center;font-family:ui-monospace,monospace;font-size:8px;letter-spacing:.05em;color:rgba(255,255,255,.65);text-align:center;line-height:1.25">CLUB<br>LOGO</div>
        </div>
      </div>
    </div>

    
    <div data-msb="timeline" data-msb-part="timeline" style="position:absolute;left:26px;right:26px;bottom:48px;height:5px;display:flex;gap:2px;z-index:2;pointer-events:none">
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
              <div style="flex:1;background:#2a2724"></div>
          </div>

    </div>
</div>
<script>
(function () {
    const MSB_STATE  = @json($play['msb'] ?? []);
    const VIDEO_ID   = @json($bout['match_no']);
    const LS_KEY     = 'msb_prefs';

    const RUN = function () {
        const root = document.getElementById('msbRoot');
        if (!root) return;
        const $ = (name) => document.querySelector('[data-msb="' + name + '"]');
        const player = () => document.getElementById('videoPlayer');

        // ── Preferences (per-user, persisted) ─────────────────────────────
        const APP_DEFAULTS = { scorebar: true, feed: true, info: true, timeline: true, clubs: true, flags: true, penalties: false };
        const VIDEO_DEFAULTS = MSB_STATE.defaults || {};
        const DEFAULTS = Object.assign({}, APP_DEFAULTS, VIDEO_DEFAULTS);

        let prefs;
        try { prefs = Object.assign({}, DEFAULTS, JSON.parse(localStorage.getItem(LS_KEY) || '{}')); }
        catch (e) { prefs = { ...DEFAULTS }; }

        const savePrefs = () => { try { localStorage.setItem(LS_KEY, JSON.stringify(prefs)); } catch (e) {} };
        const applyPrefsClasses = () => {
            for (const key of Object.keys(APP_DEFAULTS)) {
                document.body.classList.toggle('msb-off-' + key, !prefs[key]);
            }
            // Signal to the scrim which parts are visible
            root.classList.toggle('msb-has-scorebar', !!prefs.scorebar);
            root.classList.toggle('msb-has-feed',     !!prefs.feed);
            root.classList.toggle('msb-has-info',     !!prefs.info);
            root.classList.toggle('msb-has-timeline', !!prefs.timeline);
        };

        // ── Corner-label localisation ─────────────────────────────────────
        const CORNER_LABELS = {
            redblue:   { red: 'Red',   blue: 'Blue',  disc: '' },
            karate:    { red: 'Aka',   blue: 'Ao',    disc: 'Karate Kumite' },
            taekwondo: { red: 'Hong',  blue: 'Chung', disc: 'Taekwondo Kyorugi' },
        };
        function labelSet() {
            const sport = (MSB_STATE.sport || 'karate').toLowerCase();
            if (sport === 'taekwondo' || sport === 'taekwondo_kyorugi') return CORNER_LABELS.taekwondo;
            if (sport === 'karate'    || sport === 'karate_kumite')     return CORNER_LABELS.karate;
            return CORNER_LABELS.redblue;
        }
        function pointLabel(p) {
            const sport = (MSB_STATE.sport || '').toLowerCase();
            if (sport.startsWith('taekwondo')) {
                if (p.pts >= 4) return 'Turning body';
                if (p.pts === 3) return 'Head kick';
                if (p.pts === 2) return 'Body kick';
                if (p.pts === 1) return 'Punch';
            }
            if (p.pts === 3) return 'Ippon';
            if (p.pts === 2) return 'Waza-ari';
            if (p.pts === 1) return 'Yuko';
            return p.action || 'Point';
        }
        const colorFor = (side) => side === 'red' ? '#ff6a5e' : '#6aa6ff';

        // ── Fixed-value paints (only once per load) ───────────────────────
        function paintFixed() {
            const labels = labelSet();
            const setText = (name, val, hideIfEmpty = true) => {
                const el = $(name); if (!el) return;
                el.textContent = val || '';
                if (hideIfEmpty) el.toggleAttribute('data-msb-empty', !val);
            };
            // Replace the placeholder "CLUB LOGO" text with a real <img>
            // (or leave the placeholder if no image). Never touches inline styles.
            const setImage = (name, path) => {
                const el = $(name); if (!el) return;
                if (!path) return;
                el.textContent = '';
                el.style.background = 'rgba(0,0,0,.32)';
                const img = document.createElement('img');
                img.src = path; img.alt = '';
                img.style.cssText = 'width:100%;height:100%;object-fit:contain;display:block';
                el.appendChild(img);
            };
            // Swap the placeholder "FLAG" box for a real flag image by pointing
            // its background-image at the self-hosted flag-icons SVG.
            const setFlag = (name, iso2) => {
                const el = $(name); if (!el) return;
                const code = (iso2 || '').toLowerCase();
                if (!/^[a-z]{2}$/.test(code) || code === 'xx') return;
                el.textContent = '';
                el.style.background     = '#000';
                el.style.backgroundImage = 'url(https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/flags/4x3/' + code + '.svg)';
                el.style.backgroundSize  = 'cover';
                el.style.backgroundPosition = 'center';
            };

            // Identity — default to karate discipline label when sport unknown
            setText('disc', labels.disc || MSB_STATE.discipline || '');
            setText('sub',  MSB_STATE.subtitle || '');
            // Hide the whole info block (rule + text) when both rows are empty
            const infoBlock = document.querySelector('[data-msb="info"]');
            if (infoBlock) {
                const empty = !$('disc').textContent && !$('sub').textContent;
                infoBlock.style.display = empty ? 'none' : '';
            }

            // Athletes — brief §2: "no athlete name → show the corner label
            // ('RED' / 'BLUE') in the name slot" (always uppercase, not the
            // sport-flavoured AKA/AO which would collide with the corner label
            // that already sits above the numeral).
            setText('redName',  MSB_STATE.red.name  || 'RED');
            setText('blueName', MSB_STATE.blue.name || 'BLUE');
            setText('redClubName',  MSB_STATE.red.club  || '');
            setText('blueClubName', MSB_STATE.blue.club || '');
            setText('redCorner',  labels.red.toUpperCase(),  false);
            setText('blueCorner', labels.blue.toUpperCase(), false);

            setFlag('redFlag',  MSB_STATE.red.flag);
            setFlag('blueFlag', MSB_STATE.blue.flag);
            setImage('redClub',  MSB_STATE.red.club_logo);
            setImage('blueClub', MSB_STATE.blue.club_logo);
        }

        // ── Playback-time-driven render (memoised) ────────────────────────
        function currentRound(t) {
            const rs = MSB_STATE.rounds || [];
            if (!rs.length) return { n: 1, name: '', start: 0 };
            let cur = rs[0];
            for (const r of rs) { if (t >= (r.start || 0)) cur = r; }
            return cur;
        }
        function pointsUpTo(t) {
            return (MSB_STATE.points || []).filter(p => p.t <= t + 0.001);
        }
        function fmtClock(sec) {
            sec = Math.max(0, Math.floor(sec || 0));
            return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');
        }
        function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

        let lastRound = '', lastClock = '', lastScore = '', lastFeed = '', lastTl = '';
        function updateForTime(t) {
            const round = currentRound(t);
            const totalRounds = Math.max(1, (MSB_STATE.rounds || []).length);
            const sport = (MSB_STATE.sport || '').toLowerCase();

            // Round label
            let roundText = round.name || '';
            if (sport.startsWith('taekwondo')) roundText = 'Round ' + round.n + ' / ' + totalRounds;
            else if (sport.startsWith('karate')) roundText = roundText || 'Kumite';
            if (roundText !== lastRound) { $('roundLabel').textContent = roundText; lastRound = roundText; }

            // Clock = time since current round's start (or since t=0 if no round starts)
            const inRound = Math.max(0, t - (round.start || 0));
            const clockStr = fmtClock(inRound);
            if (clockStr !== lastClock) { $('clock').textContent = clockStr; lastClock = clockStr; }

            const seen = pointsUpTo(t);
            const last = seen[seen.length - 1];
            const red  = last ? last.sr : 0;
            const blue = last ? last.sb : 0;
            const sSig = red + '|' + blue;
            if (sSig !== lastScore) {
                $('redScore').textContent  = red;
                $('blueScore').textContent = blue;
                lastScore = sSig;
            }

            // Timeline segments across the duration — writes into the design's
            // verbatim inline-styled container (14 pre-rendered <div>s).
            const SEG_COUNT = Math.max(14, (MSB_STATE.points || []).length);
            const tlSig = SEG_COUNT + '|' + seen.map(p => p.side[0]).join('');
            if (tlSig !== lastTl) {
                const tl = $('timeline');
                if (tl) {
                    let html = '';
                    for (let i = 0; i < SEG_COUNT; i++) {
                        const p = seen[i];
                        const color = p ? colorFor(p.side) : '#2a2724';
                        html += '<div style="flex:1;background:' + color + '"></div>';
                    }
                    tl.innerHTML = html;
                }
                lastTl = tlSig;
            }

            // Ticker: last 4, newest first
            const last4 = seen.slice(-4).reverse();
            const fSig = last4.map(p => p.t + ':' + p.side + ':' + p.pts).join(',');
            if (fSig !== lastFeed) {
                const feed = $('feed');
                if (feed) {
                    feed.innerHTML = last4.map(p => {
                        const col = colorFor(p.side);
                        return '<div class="entry msb-new" style="border-color:' + col + '">'
                            +    '<span class="ts">' + fmtClock(p.t) + '</span>'
                            +    '<span class="name">' + esc(pointLabel(p)) + '</span>'
                            +    '<span class="pts" style="color:' + col + '">+' + p.pts + '</span>'
                            +  '</div>';
                    }).join('');
                }
                lastFeed = fSig;
            }
        }

        // ── Gear-menu injection ───────────────────────────────────────────
        // Custom row markup (per design): compact label + 26×14 track + 10 px
        // bone knob. Nothing on the platform side changes.
        function injectGearRows() {
            const panel = document.getElementById('ytpSettingsPanel');
            if (!panel) return false;
            if (panel.dataset.msbInjected) return true;

            const header = document.createElement('div');
            header.className = 'msb-gear-header';
            header.textContent = 'Scoreboard';
            panel.appendChild(header);

            const ROWS = [
                ['scorebar',  'Score bar'],
                ['feed',      'Live scoring feed'],
                ['info',      'Match info'],
                ['timeline',  'Point timeline'],
                ['clubs',     'Club logos'],
                ['flags',     'Country flags'],
                ['penalties', 'Penalties'],
            ];
            for (const [key, label] of ROWS) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'msb-gear-row' + (prefs[key] ? ' on' : '');
                row.dataset.msbToggle = key;
                row.innerHTML =
                    '<span>' + label + '</span>' +
                    '<span class="msb-gear-track"><span class="msb-gear-knob"></span></span>';
                row.addEventListener('click', (e) => {
                    e.stopPropagation();
                    prefs[key] = !prefs[key];
                    row.classList.toggle('on', prefs[key]);
                    savePrefs(); applyPrefsClasses();
                });
                panel.appendChild(row);
            }
            panel.dataset.msbInjected = '1';

            // Design: gear icon turns red while the menu is open.
            const sync = () => document.body.classList.toggle('msb-menu-open', panel.classList.contains('open'));
            new MutationObserver(sync).observe(panel, { attributes: true, attributeFilter: ['class'] });
            sync();
            return true;
        }
        function tryInject(attemptsLeft) {
            if (injectGearRows()) return;
            if (attemptsLeft > 0) setTimeout(() => tryInject(attemptsLeft - 1), 200);
        }
        tryInject(30);

        // ── Overlay scaling to a fixed 1150 px design canvas ──────────────
        // Keeps the score bar, ticker, and identity at their pixel-perfect
        // proportions no matter how wide the actual player is.
        function initScale() {
            // Scale against #videoContainer (the .ytp inner box) — that's the
            // element that gets squeezed when the fullscreen highlights drawer
            // opens (CSS: `.ytp-wrap.ytp-fullscreen.hl-drawer-open .ytp { width:
            // calc(100% - var(--hl-drawer-w)) }`). Using #ytpWrap here would
            // keep the overlay at full-screen width and clip its right edge
            // behind the drawer.
            const wrap  = document.getElementById('videoContainer') || document.getElementById('ytpWrap');
            const layer = document.getElementById('msbScale');
            if (!wrap || !layer) return;
            const DESIGN_W = 1150;
            const update = () => {
                const w = wrap.clientWidth  || 1;
                const h = wrap.clientHeight || 1;
                const s = w / DESIGN_W;
                layer.style.transform = 'scale(' + s + ')';
                layer.style.height    = (h / s) + 'px';
                // "small player" auto-hides identity + feed. Skip it in
                // fullscreen — the highlights drawer squeezes the container
                // width but the user still wants to see the live feed next
                // to the drawer.
                const inFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
                document.body.classList.toggle('msb-small', !inFs && w < 700);
            };
            // Re-run when entering/exiting fullscreen so the msb-small flag
            // updates immediately.
            document.addEventListener('fullscreenchange',       update);
            document.addEventListener('webkitfullscreenchange', update);
            update();
            if (typeof ResizeObserver === 'function') {
                new ResizeObserver(update).observe(wrap);
            }
            window.addEventListener('resize', update);
        }

        // ── Wire everything ───────────────────────────────────────────────
        applyPrefsClasses();
        paintFixed();
        initScale();

        function attachPlayer() {
            const v = player();
            if (!v) { setTimeout(attachPlayer, 250); return; }
            const tick = () => updateForTime(v.currentTime || 0);
            v.addEventListener('timeupdate',     tick);
            v.addEventListener('seeked',         tick);
            v.addEventListener('loadedmetadata', tick);
            v.addEventListener('ratechange',     tick);
            tick();
        }
        attachPlayer();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', RUN);
    } else {
        setTimeout(RUN, 0);
    }
})();
</script>
                    
                    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow+Condensed:wght@400;600;700;800&display=swap">

@php
    // A face only where the athlete chose to show it — the same rule the
    // bracket and the board follow. Its absence is silent, never a label.
    $refereeName = collect($officials)->first()['name'] ?? null;
@endphp
<div id="vsScreen" class="vs-screen" role="dialog" aria-label="{{ __('events.bout_video_intro') }}">
    <button type="button" class="vs-skip" id="vsSkip" aria-label="{{ __('events.bout_video_skip') }}">
        {{ __('events.bout_video_skip') }} <span id="vsSkipTimer">6</span>
    </button>

    <div class="vs-stage">

        <div class="vs-panel vs-panel-red">
            <div class="vs-panel-photo vs-panel-photo-red"
                 @if ($red['photo']) style="background-image:url('{{ $red['photo'] }}')" @endif></div>
            <div class="vs-panel-scrim vs-panel-scrim-red"></div>
            <div class="vs-panel-gradient"></div>
        </div>

        <div class="vs-panel vs-panel-blue">
            <div class="vs-panel-photo vs-panel-photo-blue"
                 @if ($blue['photo']) style="background-image:url('{{ $blue['photo'] }}')" @endif></div>
            <div class="vs-panel-scrim vs-panel-scrim-blue"></div>
            <div class="vs-panel-gradient"></div>
        </div>

        <div class="vs-divider"></div>

        <div class="vs-info vs-info-red">
            <div class="vs-corner-tag vs-corner-tag-red">{{ Str::upper($red['corner_label']) }}</div>
            <div class="vs-flag-row vs-nullable-row">
                @if ($red['country'])<span class="vs-flag fi fi-{{ Str::lower($red['country']) }} vs-flag-slot"></span>@endif
                <div class="vs-country vs-country-red vs-nullable">{{ $red['country'] ? Str::upper($red['country']) : '' }}</div>
            </div>
            <div class="vs-name vs-nullable">{{ $red['name'] }}</div>
            <div class="vs-club-row vs-nullable-row">
                <div class="vs-club vs-nullable">{{ $red['club'] }}</div>
            </div>
        </div>

        <div class="vs-info vs-info-blue">
            <div class="vs-corner-tag vs-corner-tag-blue">{{ Str::upper($blue['corner_label']) }}</div>
            <div class="vs-flag-row vs-flag-row-r vs-nullable-row">
                @if ($blue['country'])<span class="vs-flag fi fi-{{ Str::lower($blue['country']) }} vs-flag-slot"></span>@endif
                <div class="vs-country vs-country-blue vs-nullable">{{ $blue['country'] ? Str::upper($blue['country']) : '' }}</div>
            </div>
            <div class="vs-name vs-nullable">{{ $blue['name'] }}</div>
            <div class="vs-club-row vs-club-row-r vs-nullable-row">
                <div class="vs-club vs-nullable">{{ $blue['club'] }}</div>
            </div>
        </div>

        <div class="vs-top">
            <div class="vs-top-event vs-nullable">{{ $e['title'] }}</div>
            <div class="vs-top-stage-row ">
                <div class="vs-top-stage-line vs-top-stage-line-l"></div>
                <div class="vs-top-stage">{{ $bout['round'] }}</div>
                <div class="vs-top-stage-line vs-top-stage-line-r"></div>
            </div>
            <div class="vs-top-weight vs-nullable">{{ $bout['division'] }}</div>
        </div>

        <div class="vs-center">
            <div class="vs-word">VS
                <div class="vs-shine-wrap"><div class="vs-shine"></div></div>
            </div>
        </div>

        <div class="vs-flash"></div>

        <div class="vs-bottom">
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_match') }}</span>
                <span class="vs-chip-value vs-nullable">{{ $bout['match_no'] }}</span>
            </div>
            <div class="vs-diamond vs-diamond-match-court"></div>
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_court') }}</span>
                <span class="vs-chip-value vs-nullable">{{ $bout['court'] }}</span>
            </div>
            <div class="vs-diamond vs-diamond-court-ref"></div>
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_referee') }}</span>
                <span class="vs-chip-value vs-chip-value-name vs-nullable">{{ $refereeName }}</span>
            </div>
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════════
   Fixed 1920×1080 canvas, transform-scaled to fit its parent (.ytp
   inside the player, or .vs-preview-stage on the preview page). Every
   child dimension is in ABSOLUTE PIXELS on that canvas — never vw/vh —
   so the layout is identical whether the box is 400 px or 4000 px wide.
   JS writes --vs-scale via ResizeObserver on the parent.
   ═══════════════════════════════════════════════════════════════════════ */
#vsScreen.vs-screen {
    position: absolute; inset: 0; z-index: 40;
    background: #050507;
    font-family: 'Barlow Condensed', sans-serif;
    color: #e8e6e0;
    overflow: hidden;
    opacity: 1;
    transition: opacity .35s ease;
    --vs-scale: 1;
    --vs-stage-w: 1920px;
    --vs-stage-h: 1080px;
}
#vsScreen.vs-hide { opacity: 0; pointer-events: none; }

#vsScreen .vs-stage {
    position: absolute;
    left: 50%; top: 50%;
    width: var(--vs-stage-w); height: var(--vs-stage-h);
    transform: translate(-50%, -50%) scale(var(--vs-scale));
    transform-origin: center center;
    background: radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%);
    overflow: hidden;
    will-change: transform;
}

/* Skip button lives OUTSIDE the scaled stage so it stays at real pixels
   in the top-right of the video area at any container size. */
#vsScreen .vs-skip {
    position: absolute; top: 12px; right: 14px; z-index: 50;
    background: rgba(10,10,14,.72);
    color: #fff; border: 1px solid rgba(255,255,255,.28);
    padding: 6px 14px;
    font: 700 12px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .22em; text-transform: uppercase;
    cursor: pointer;
    backdrop-filter: blur(6px);
    transition: background .15s, border-color .15s;
}
#vsScreen .vs-skip:hover { background: rgba(10,10,14,.92); border-color: rgba(255,255,255,.55); }
#vsScreen .vs-skip #vsSkipTimer { display: inline-block; margin-left: 6px; opacity: .8; }

/* ══ EVERYTHING BELOW: absolute pixels on the 1920×1080 canvas ══ */

/* ── Side panels ── */
#vsScreen .vs-panel { position: absolute; overflow: hidden; }
#vsScreen .vs-panel-red {
    inset: 0 auto 0 0; width: 56%;
    background: oklch(0.28 0.09 25);
    clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);
    animation: vsPanelL .9s cubic-bezier(.22,1,.36,1) both;
}
#vsScreen .vs-panel-blue {
    inset: 0 0 0 auto; width: 56%;
    background: oklch(0.28 0.09 255);
    clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);
    animation: vsPanelR .9s cubic-bezier(.22,1,.36,1) both;
}
#vsScreen .vs-panel-photo {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    animation: vsSlowDrift 18s ease-in-out infinite;
}
#vsScreen .vs-panel-photo-blue { animation-direction: reverse; }
#vsScreen .vs-panel-scrim-red   { position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(115deg, oklch(0.45 0.18 25 / 0.55) 0%, transparent 55%); }
#vsScreen .vs-panel-scrim-blue  { position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(245deg, oklch(0.45 0.15 255 / 0.55) 0%, transparent 55%); }
#vsScreen .vs-panel-gradient {
    position: absolute; inset: 0; pointer-events: none;
    background:
        linear-gradient(to top, rgba(5,5,7,.95) 0%, rgba(5,5,7,.72) 22%, rgba(5,5,7,.30) 42%, transparent 62%),
        linear-gradient(to bottom, rgba(5,5,7,.82) 0%, rgba(5,5,7,.35) 18%, transparent 32%);
}

/* ── Divider ── */
#vsScreen .vs-divider {
    position: absolute; top: -6%; bottom: -6%; left: 50%;
    width: 3px; margin-left: -1.5px;
    transform: rotate(10.15deg);
    background: linear-gradient(to bottom, transparent, oklch(0.85 0.16 85 / .9) 20%, oklch(0.85 0.16 85 / .9) 80%, transparent);
    pointer-events: none; filter: blur(1px);
}

/* ── Fighter identity blocks ── (absolute px on the 1920×1080 canvas) */
#vsScreen .vs-info {
    position: absolute; z-index: 6; max-width: 44%;
    display: flex; flex-direction: column; gap: 13px;
}
#vsScreen .vs-info-red  { left: 43.2px; bottom: 118.8px; align-items: flex-start;  animation: vsRiseUp .8s .7s cubic-bezier(.22,1,.36,1) both; }
#vsScreen .vs-info-blue { right: 43.2px; bottom: 118.8px; align-items: flex-end; text-align: right; animation: vsRiseUp .8s .85s cubic-bezier(.22,1,.36,1) both; }

#vsScreen .vs-corner-tag {
    font: 800 21.6px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .35em; color: #fff;
    padding: 5.4px 15.1px 5.4px 18.9px;
}
#vsScreen .vs-corner-tag-red  { background: oklch(0.55 0.20 25); }
#vsScreen .vs-corner-tag-blue { background: oklch(0.50 0.16 255); }

#vsScreen .vs-flag-row   { display: flex; align-items: center; gap: 15.1px; }
#vsScreen .vs-flag-row-r { flex-direction: row-reverse; }
#vsScreen .vs-flag {
    width: 56.2px; height: auto; aspect-ratio: 4/3;
    background-size: cover !important; background-position: center !important;
    border: 1px solid rgba(255,255,255,.35);
    box-shadow: 0 4px 18px rgba(0,0,0,.6);
    display: inline-block; line-height: 0;
}
#vsScreen .vs-country       { font: 700 32.4px/1 'Barlow Condensed', sans-serif; letter-spacing: .28em; }
#vsScreen .vs-country-red   { color: oklch(0.85 0.05 25); }
#vsScreen .vs-country-blue  { color: oklch(0.85 0.05 255); }

#vsScreen .vs-name {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 71.3px; line-height: .95;
    text-transform: uppercase; color: #fff;
    text-shadow: 0 6px 30px rgba(0,0,0,.8);
}

#vsScreen .vs-club-row   { display: flex; align-items: center; gap: 13px; margin-top: 4.3px; }
#vsScreen .vs-club-row-r { flex-direction: row-reverse; }
#vsScreen .vs-club-logo {
    width: 69.1px; height: 69.1px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
    background-size: cover; background-position: center;
    border: 1px solid rgba(255,255,255,.2);
}
#vsScreen .vs-club {
    font: 600 30.2px/1.1 'Barlow Condensed', sans-serif;
    letter-spacing: .12em; text-transform: uppercase;
    color: rgba(232,230,224,.9);
}

/* Fighter chips row (Record / Rank / Stats) — from the original artifact. */
#vsScreen .vs-chips-row {
    display: flex; flex-wrap: wrap; gap: 9.7px; margin-top: 5.4px;
}
#vsScreen .vs-info-blue .vs-chips-row { justify-content: flex-end; }
#vsScreen .vs-chip-fighter {
    font: 700 23.8px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .12em; text-transform: uppercase;
    padding: 5.4px 14px;
    background: rgba(10,10,14,.62);
    border: 1px solid rgba(255,255,255,.25);
    color: #fff;
}
#vsScreen .vs-chip-fighter-gold {
    border-color: oklch(0.85 0.16 85 / .6);
    color: oklch(0.87 0.14 85);
}

/* ══ Placeholder hiding ══
   Every placeholder DOM element is always rendered. Empty ones are hidden
   here — this keeps the design's HTML shape intact (same as the artifact)
   while adapting the visible layout to whatever data actually exists. */
#vsScreen .vs-nullable:empty { display: none; }
/* When every direct child of a "row" wrapper is hidden, the row collapses too. */
#vsScreen .vs-nullable-row:not(:has(> :not(.vs-nullable-hide):not(.vs-nullable:empty))) { display: none; }
/* Image slots explicitly marked as missing (flag / club logo without data). */
#vsScreen .vs-nullable-hide { display: none; }

/* Bottom chips: whole chip hides when its value is empty, and the
   diamond separators next to a hidden chip hide too. */
#vsScreen .vs-chip.vs-nullable-chip[data-vs-empty="1"] { display: none; }
#vsScreen .vs-bottom .vs-diamond-match-court:has(+ .vs-chip[data-vs-empty="1"]),
#vsScreen .vs-bottom .vs-chip[data-vs-empty="1"] + .vs-diamond-court-ref,
#vsScreen .vs-bottom .vs-chip[data-vs-empty="1"] + .vs-diamond-match-court,
#vsScreen .vs-bottom .vs-diamond-court-ref:has(+ .vs-chip[data-vs-empty="1"]) { display: none; }

/* ── Top (event / stage / weight) ── */
#vsScreen .vs-top {
    position: absolute; top: 34.6px; left: 50%; transform: translateX(-50%);
    display: flex; flex-direction: column; align-items: center; gap: 10.8px;
    z-index: 8; width: 92%; pointer-events: none;
    animation: vsDropIn .8s .5s cubic-bezier(.22,1,.36,1) both;
}
#vsScreen .vs-top-event {
    font: 700 30.2px/1.05 'Barlow Condensed', sans-serif;
    letter-spacing: .42em; text-transform: uppercase;
    color: rgba(232,230,224,.92); text-align: center;
    text-shadow: 0 2px 14px rgba(0,0,0,.9);
}
#vsScreen .vs-top-stage-row  { display: flex; align-items: center; gap: 17.3px; }
#vsScreen .vs-top-stage-line { height: 2px; width: 64.8px; }
#vsScreen .vs-top-stage-line-l { background: linear-gradient(to left,  oklch(0.85 0.16 85), transparent); }
#vsScreen .vs-top-stage-line-r { background: linear-gradient(to right, oklch(0.85 0.16 85), transparent); }
#vsScreen .vs-top-stage {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 38.9px;
    letter-spacing: .30em; padding-left: .3em;
    color: oklch(0.85 0.16 85); text-transform: uppercase;
}
#vsScreen .vs-top-weight {
    font: 700 42px/1.1 'Anton', 'Barlow Condensed', sans-serif;
    letter-spacing: .28em; padding-left: .28em; text-transform: uppercase;
    color: #ffffff;
    text-shadow: 0 2px 14px rgba(0,0,0,.9);
}

/* ── Center VS ── */
#vsScreen .vs-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -52%);
    z-index: 7; pointer-events: none;
    display: flex; align-items: center; justify-content: center;
    animation: vsSlam .7s 1.1s cubic-bezier(.22,1,.36,1) both;
}
#vsScreen .vs-word {
    position: relative;
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 183.6px;
    font-style: italic;
    color: #fffdf5; line-height: 1;
    -webkit-text-stroke: 2px oklch(0.85 0.16 85 / 0.6);
    animation: vsPulse 2.4s ease-in-out infinite;
    overflow: visible;
}
#vsScreen .vs-shine-wrap { position: absolute; inset: -10% -20%; overflow: hidden; pointer-events: none; }
#vsScreen .vs-shine {
    position: absolute; top: 0; bottom: 0; width: 34%;
    background: linear-gradient(to right, transparent, rgba(255,255,255,.16), transparent);
    animation: vsShine 5s ease-in-out infinite;
}

#vsScreen .vs-flash {
    position: absolute; inset: 0; background: #fff;
    opacity: 0; pointer-events: none; z-index: 9;
    animation: vsFlash .9s 1.5s ease-out both;
}

/* ── Bottom chips ── */
#vsScreen .vs-bottom {
    position: absolute; bottom: 32.4px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 17.3px; z-index: 8; align-items: center;
    flex-wrap: wrap; justify-content: center; max-width: 94%;
    animation: vsRiseC .8s 1.3s cubic-bezier(.22,1,.36,1) both;
}
#vsScreen .vs-chip {
    display: flex; align-items: baseline; gap: 8.6px;
    background: rgba(10,10,14,.72);
    border: 1px solid oklch(0.85 0.16 85 / .45);
    padding: 10.8px 23.8px; backdrop-filter: blur(6px);
}
#vsScreen .vs-chip-label {
    font: 600 23.8px/1 'Barlow Condensed', sans-serif;
    letter-spacing: .30em; text-transform: uppercase;
    color: rgba(232,230,224,.65);
}
#vsScreen .vs-chip-value {
    font-family: 'Anton', 'Barlow Condensed', sans-serif;
    font-size: 34.6px; color: #fff;
}
#vsScreen .vs-chip-value-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    font-size: 28.1px;
}
#vsScreen .vs-diamond {
    width: 6px; height: 6px; transform: rotate(45deg);
    background: oklch(0.85 0.16 85);
}

/* ── Portrait canvas (1080×1920): stack layout ── */
#vsScreen.vs-portrait { --vs-stage-w: 1080px; --vs-stage-h: 1920px; }
#vsScreen.vs-portrait .vs-panel-red  { inset: 0 0 auto 0; width: 100%; height: 56%;
    clip-path: polygon(0 0, 100% 0, 100% 82%, 0 96%); animation-name: vsPanelT; }
#vsScreen.vs-portrait .vs-panel-blue { inset: auto 0 0 0; width: 100%; height: 56%;
    clip-path: polygon(0 18%, 100% 4%, 100% 100%, 0 100%); animation-name: vsPanelB; }
#vsScreen.vs-portrait .vs-divider {
    left: -4%; right: -4%; top: 50%; bottom: auto;
    width: auto; height: 3px; margin-top: -1.5px; margin-left: 0;
    transform: rotate(-4.48deg);
    background: linear-gradient(to right, transparent, oklch(0.85 0.16 85 / .9) 20%, oklch(0.85 0.16 85 / .9) 80%, transparent);
}
#vsScreen.vs-portrait .vs-info-red  { left: 43.2px; top: 129.6px; bottom: auto; }
#vsScreen.vs-portrait .vs-info-blue { right: 43.2px; bottom: 129.6px; top: auto; }

/* ── Animations ── */
@keyframes vsPulse {
    0%,100% { text-shadow: 0 0 30px rgba(255,215,120,.55), 0 0 90px rgba(255,170,60,.3); transform: scale(1); }
    50%     { text-shadow: 0 0 65px rgba(255,220,130,1),   0 0 160px rgba(255,170,60,.7); transform: scale(1.045); }
}
@keyframes vsShine     { 0% { transform: translateX(-130%) skewX(-18deg); } 60%,100% { transform: translateX(230%) skewX(-18deg); } }
@keyframes vsSlowDrift { 0% { transform: translate3d(0,0,0) scale(1.02); } 50% { transform: translate3d(0,-1.2%,0) scale(1.05); } 100% { transform: translate3d(0,0,0) scale(1.02); } }
@keyframes vsPanelL    { from { transform: translateX(-105%); } to { transform: translateX(0); } }
@keyframes vsPanelR    { from { transform: translateX( 105%); } to { transform: translateX(0); } }
@keyframes vsPanelT    { from { transform: translateY(-105%); } to { transform: translateY(0); } }
@keyframes vsPanelB    { from { transform: translateY( 105%); } to { transform: translateY(0); } }
@keyframes vsRiseUp    { from { opacity: 0; transform: translateY(43.2px); } to { opacity: 1; transform: translateY(0); } }
@keyframes vsRiseC     { from { opacity: 0; transform: translate(-50%, 43.2px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsDropIn    { from { opacity: 0; transform: translate(-50%, -32.4px); } to { opacity: 1; transform: translate(-50%, 0); } }
@keyframes vsSlam {
    0%   { opacity: 0; transform: translate(-50%,-52%) scale(3.4) rotate(-6deg); }
    60%  { opacity: 1; transform: translate(-50%,-52%) scale(.92) rotate(1deg); }
    80%  {              transform: translate(-50%,-52%) scale(1.06); }
    100% { opacity: 1; transform: translate(-50%,-52%) scale(1) rotate(0deg); }
}
@keyframes vsFlash { 0% { opacity: 0; } 12% { opacity: .85; } 100% { opacity: 0; } }
</style>

<script>
(function () {
    const VS_INTRO_SECONDS = 6;

    const vs      = document.getElementById('vsScreen');
    if (!vs) return;
    const video   = document.getElementById('videoPlayer');
    const timerEl = document.getElementById('vsSkipTimer');
    let hidden = false, tickInt = null;

    // ── Fit the fixed 1920×1080 (or 1080×1920) canvas to the parent box.
    function fit() {
        const host = vs.parentElement;
        if (!host) return;
        const w = host.clientWidth, h = host.clientHeight;
        if (!w || !h) return;
        const portrait = h > w * 1.05;
        vs.classList.toggle('vs-portrait', portrait);
        const sw = portrait ? 1080 : 1920;
        const sh = portrait ? 1920 : 1080;
        const scale = Math.min(w / sw, h / sh);
        vs.style.setProperty('--vs-scale', scale);
    }
    fit();
    if (window.ResizeObserver && vs.parentElement) {
        new ResizeObserver(fit).observe(vs.parentElement);
    } else {
        window.addEventListener('resize', fit);
    }

    // ── Hold the video at t=0 while the intro counts down ──────────
    // Autoplay may already have kicked in before this script ran; pause
    // it, keep it pinned at the start, and only release it at 0.
    function pinVideo() {
        if (!video) return;
        try { video.pause(); } catch (_) {}
        try { video.currentTime = 0; } catch (_) {}
    }
    pinVideo();
    // Some browsers fire 'play' immediately after we pause (autoplay
    // retry). Re-pause until the intro finishes.
    const playGuard = () => { if (!hidden) pinVideo(); };
    if (video) video.addEventListener('play', playGuard);

    function releaseVideo() {
        if (!video) return;
        video.removeEventListener('play', playGuard);
        try { video.currentTime = 0; } catch (_) {}
        const p = video.play();
        if (p && typeof p.catch === 'function') p.catch(() => {});
    }

    // ── Dismiss overlay + start playback ───────────────────────────
    function hide() {
        if (hidden) return;
        hidden = true;
        if (tickInt) { clearInterval(tickInt); tickInt = null; }
        releaseVideo();
        vs.classList.add('vs-hide');
        setTimeout(() => { vs.remove(); }, 400);
    }

    document.getElementById('vsSkip')?.addEventListener('click', (e) => {
        e.stopPropagation(); e.preventDefault(); hide();
    });

    // ── Real-time countdown, independent of video timeline ─────────
    const started = performance.now();
    function tick() {
        const elapsed = (performance.now() - started) / 1000;
        const left    = Math.max(0, VS_INTRO_SECONDS - elapsed);
        if (timerEl) timerEl.textContent = Math.ceil(left);
        if (left <= 0) hide();
    }
    tick();
    tickInt = setInterval(tick, 100);
})();
</script>

    
    <div class="ytp-dbl-left"  id="ytpDblLeft"><i class="bi bi-arrow-counterclockwise"></i><span>10</span></div>
    <div class="ytp-dbl-right" id="ytpDblRight"><i class="bi bi-arrow-clockwise"></i><span>10</span></div>

    
    <div class="ytp-spinner" id="ytpSpinner">
        <div class="ytp-spinner-circle"></div>
    </div>

    
    <div class="ytp-large-play-btn" id="ytpLargePlay">
        <i class="bi bi-play-fill"></i>
    </div>

    
    <div class="ytp-gradient-bottom"></div>

    
    <div class="ytp-chrome-bottom" id="ytpControls">

        
        <div class="ytp-progress-bar-container" id="ytpProgressContainer">
            <div class="ytp-progress-bar" id="ytpProgressBar">
                <div class="ytp-load-progress"  id="ytpBuffered"></div>
                <div class="ytp-play-progress"  id="ytpPlayed"></div>
                <div class="ytp-scrubber-container">
                    <div class="ytp-scrubber-button" id="ytpScrubber"></div>
                </div>
                <div class="ytp-hover-time" id="ytpHoverTime"></div>
            </div>
        </div>

        
        <div class="ytp-chrome-controls">

            
            <div class="ytp-left-controls">

                
                <button class="ytp-button ytp-play-btn" id="ytpPlayBtn" title="Play (k)">
                    <svg class="ytp-svg-play"  viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <svg class="ytp-svg-pause" viewBox="0 0 24 24" style="display:none"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>

                
                                
                
                <div class="ytp-volume-area" id="ytpVolumeArea">
                    <button class="ytp-button ytp-mute-btn" id="ytpMuteBtn" title="Mute (m)">
                        <svg class="ytp-svg-vol3" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                        <svg class="ytp-svg-vol2" viewBox="0 0 24 24" style="display:none"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
                        <svg class="ytp-svg-vol1" viewBox="0 0 24 24" style="display:none"><path d="M7 9v6h4l5 5V4l-5 5H7z"/></svg>
                        <svg class="ytp-svg-vol0" viewBox="0 0 24 24" style="display:none"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4 9.91 6.09 12 8.18V4z"/></svg>
                    </button>
                    <div class="ytp-volume-slider-wrap" id="ytpVolumeWrap">
                        <input type="range" class="ytp-volume-range" id="ytpVolume"
                               min="0" max="100" step="1" value="50">
                    </div>
                </div>

                
                <div class="ytp-time-display">
                    <span id="ytpCurrent">0:00</span>
                    <span class="ytp-time-sep"> / </span>
                    <span id="ytpDuration">0:00</span>
                </div>

            </div>

            
            <div class="ytp-right-controls">

                
                <div class="ytp-settings-wrap" id="ytpSettingsWrap">
                    <button class="ytp-button ytp-settings-btn" id="ytpSettingsBtn" title="Settings">
                        <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    </button>
                    <div class="ytp-settings-panel" id="ytpSettingsPanel">
                        
                        <div class="ytp-settings-item" id="ytpMiniToggleRow" onclick="(function(el){var on=!(window._ytpMiniEnabled&&window._ytpMiniEnabled());window._ytpMiniSetEnabled(on);el.querySelector('.ytp-settings-val').textContent=on?'On':'Off';})(this)">
                            <svg viewBox="0 0 24 24"><path d="M19 11h-8v6h8v-6zm4 8V4.98C23 3.88 22.1 3 21 3H3c-1.1 0-2 .88-2 1.98V19c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zm-2 .02H3V4.97h18v14.05z"/></svg>
                            <span>Mini player</span>
                            <span class="ytp-settings-val">On</span>
                        </div>
                        <div class="ytp-settings-item" id="ytpSpeedRow">
                            <svg viewBox="0 0 24 24"><path d="M10 8v8l6-4-6-4zm6.5 4A6.5 6.5 0 1 1 9 6.04V4.02A8.5 8.5 0 1 0 18.5 12H16.5z"/></svg>
                            <span>Playback speed</span>
                            <span class="ytp-settings-val" id="ytpSpeedLabel">Normal</span>
                            <svg class="ytp-chevron" viewBox="0 0 24 24"><path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                        </div>
                        <div class="ytp-speed-panel" id="ytpSpeedPanel">
                            <div class="ytp-speed-back" id="ytpSpeedBack">
                                <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                                Playback speed
                            </div>
                                                        <div class="ytp-speed-option " data-speed="0.25">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                0.25
                            </div>
                                                        <div class="ytp-speed-option " data-speed="0.5">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                0.5
                            </div>
                                                        <div class="ytp-speed-option " data-speed="0.75">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                0.75
                            </div>
                                                        <div class="ytp-speed-option active" data-speed="1">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                Normal
                            </div>
                                                        <div class="ytp-speed-option " data-speed="1.25">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                1.25
                            </div>
                                                        <div class="ytp-speed-option " data-speed="1.5">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                1.5
                            </div>
                                                        <div class="ytp-speed-option " data-speed="1.75">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                1.75
                            </div>
                                                        <div class="ytp-speed-option " data-speed="2">
                                <svg class="ytp-speed-check" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                2
                            </div>
                                                    </div>
                        
                                            </div>
                </div>

                
                <button class="ytp-button ytp-loop-btn" id="ytpLoopBtn" title="Loop">
                    <svg viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
                </button>

                
                <button class="ytp-button ytp-pip-btn" id="ytpPipBtn" title="Miniplayer (i)">
                    <svg viewBox="0 0 24 24"><path d="M19 11h-8v6h8v-6zm4 8V4.98C23 3.88 22.1 3 21 3H3c-1.1 0-2 .88-2 1.98V19c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zm-2 .02H3V4.97h18v14.05z"/></svg>
                </button>

                
                <button class="ytp-button ytp-theater-btn" id="ytpTheaterBtn" title="Theater mode (t)">
                    <svg class="ytp-svg-theater-off" viewBox="0 0 24 24"><path d="M19 6H5c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 10H5V8h14v8z"/></svg>
                    <svg class="ytp-svg-theater-on" viewBox="0 0 24 24" style="display:none"><path d="M19 7H5c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zm0 8H5V9h14v6z"/></svg>
                </button>

                
                <button class="ytp-button ytp-fs-btn" id="ytpFsBtn" title="Full screen (f)">
                    <svg class="ytp-svg-fs-enter" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                    <svg class="ytp-svg-fs-exit" viewBox="0 0 24 24" style="display:none"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>
                </button>

            </div>
        </div>
    </div>

</div>
</div>


<style>
/* ══════════════════════════════════════════════════════════
   YTP PLAYER — YouTube-identical custom player
══════════════════════════════════════════════════════════ */
.ytp-wrap {
    position: relative;
    width: 100%;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    /* Always 16:9 — no max-height so the shape is never distorted. */
    aspect-ratio: 16/9;
    /* Establish a size container so descendants can size themselves relative
       to the player width (used by the coach-note overlay to scale). */
    container-type: inline-size;
    container-name: ytpwrap;
}
/* Orientation classes intentionally forced to 16:9 too, so the frame
   shape never changes with the underlying media orientation. The video
   element itself uses object-fit: contain and will letterbox as needed. */
.ytp-wrap.portrait,
.ytp-wrap.square,
.ytp-wrap.ultrawide {
    aspect-ratio: 16/9;
    width: 100%;
    max-width: none;
    max-height: none;
    margin: 0;
}

/* Theater mode — keep 16:9 shape, just remove rounded corners. */
.ytp-wrap.theater {
    aspect-ratio: 16/9;
    height: auto;
    max-height: none;
    border-radius: 0;
}

/* Fullscreen */
.ytp-wrap.ytp-fullscreen {
    position: fixed !important;
    inset: 0;
    z-index: 99999;
    max-height: 100vh;
    height: 100vh;
    width: 100vw;
    border-radius: 0;
    aspect-ratio: unset;
    margin: 0 !important;
}

/* ── Inner player ── */
.ytp {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
    cursor: pointer;
    outline: none;
    user-select: none;
    overflow: hidden;
    font-family: Roboto, Arial, sans-serif;
}
.ytp:focus { outline: none; }

/* ── Video element ── */
#videoPlayer {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

/* ── Gradient bottom ── */
.ytp-gradient-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 98px;
    background: linear-gradient(rgba(0,0,0,0), rgba(0,0,0,.7));
    pointer-events: none;
    transition: opacity .25s;
}

/* ── Large play overlay ── */
.ytp-large-play-btn {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    opacity: 0;
    transition: opacity .2s;
}
.ytp-large-play-btn i {
    font-size: 72px;
    color: rgba(255,255,255,.9);
    text-shadow: 0 0 30px rgba(0,0,0,.6);
}
.ytp-large-play-btn.visible { opacity: 1; }

/* ── Spinner ── */
.ytp-spinner {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    opacity: 0;
    transition: opacity .2s;
}
.ytp-spinner.active { opacity: 1; }
.ytp-spinner-circle {
    width: 48px;
    height: 48px;
    border: 4px solid rgba(255,255,255,.2);
    border-top-color: #fff;
    border-radius: 50%;
    animation: ytpSpin .8s linear infinite;
}
@keyframes ytpSpin { to { transform: rotate(360deg); } }

/* ── Double-tap feedback ── */
.ytp-dbl-left, .ytp-dbl-right {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 30%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    pointer-events: none;
    opacity: 0;
    transition: opacity .2s;
    border-radius: 50%;
}
.ytp-dbl-left  { left: 0; }
.ytp-dbl-right { right: 0; }
.ytp-dbl-left i, .ytp-dbl-right i { font-size: 40px; }
.ytp-dbl-left.show, .ytp-dbl-right.show { opacity: 1; }

/* ══ CONTROLS ══ */
.ytp-chrome-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0 12px 8px;
    transition: opacity .25s, transform .25s;
    transform: translateY(0);
}
/* hidden state */
.ytp.controls-hidden .ytp-chrome-bottom { opacity: 0; transform: translateY(4px); pointer-events: none; }
.ytp.controls-hidden .ytp-gradient-bottom { opacity: 0; }

/* ── Progress bar ── */
.ytp-progress-bar-container {
    padding: 4px 0;
    cursor: pointer;
    margin-bottom: 4px;
}
.ytp-progress-bar {
    position: relative;
    height: 3px;
    background: rgba(255,255,255,.2);
    border-radius: 2px;
    transition: height .1s;
}
.ytp-progress-bar-container:hover .ytp-progress-bar,
.ytp-progress-bar.dragging { height: 5px; }

.ytp-load-progress {
    position: absolute;
    top: 0; left: 0; bottom: 0;
    background: rgba(255,255,255,.4);
    border-radius: 2px;
    width: 0;
    pointer-events: none;
}
.ytp-play-progress {
    position: absolute;
    top: 0; left: 0; bottom: 0;
    background: #f00;
    border-radius: 2px;
    width: 0;
    pointer-events: none;
}
.ytp-scrubber-container {
    position: absolute;
    top: 50%;
    width: 0;
    pointer-events: none;
}
.ytp-scrubber-button {
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #f00;
    /* Center the knob on the track: -50% X sits it on the play head, -50% Y keeps it
       vertically centered on the bar's mid-line (container top:50%) at any bar height. */
    transform: translate(-50%, -50%) scale(0);
    transition: transform .1s;
}
.ytp-progress-bar-container:hover .ytp-scrubber-button,
.ytp-progress-bar.dragging .ytp-scrubber-button { transform: translate(-50%, -50%) scale(1); }

.ytp-hover-time {
    position: absolute;
    bottom: 16px;
    background: rgba(28,28,28,.9);
    color: #fff;
    font-size: 12px;
    padding: 3px 6px;
    border-radius: 4px;
    pointer-events: none;
    opacity: 0;
    transform: translateX(-50%);
    white-space: nowrap;
    transition: opacity .1s;
}
.ytp-progress-bar-container:hover .ytp-hover-time { opacity: 1; }

/* ── Chrome controls row ── */
.ytp-chrome-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 36px;
}
.ytp-left-controls, .ytp-right-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Buttons ── */
.ytp-button {
    background: none;
    border: none;
    color: #fff;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background .15s;
    flex-shrink: 0;
}
.ytp-button:hover { background: rgba(255,255,255,.1); }
.ytp-button svg { width: 22px; height: 22px; fill: #fff; pointer-events: none; }
.ytp-button:focus { outline: none; }

/* play btn slightly larger icon */
.ytp-play-btn svg { width: 26px; height: 26px; }

/* ── Volume area ── */
.ytp-volume-area {
    display: flex;
    align-items: center;
    gap: 0;
}
.ytp-volume-slider-wrap {
    overflow: hidden;
    width: 0;
    transition: width .2s;
    display: flex;
    align-items: center;
}
.ytp-volume-area:hover .ytp-volume-slider-wrap,
.ytp-volume-area:focus-within .ytp-volume-slider-wrap { width: 60px; }

.ytp-volume-range {
    -webkit-appearance: none;
    appearance: none;
    width: 52px;
    height: 3px;
    border-radius: 2px;
    background: linear-gradient(to right, #fff var(--vol, 50%), rgba(255,255,255,.3) var(--vol, 50%));
    outline: none;
    cursor: pointer;
    margin: 0 4px;
}
.ytp-volume-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
}
.ytp-volume-range::-moz-range-thumb {
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #fff;
    border: none;
    cursor: pointer;
}

/* ── Time display ── */
.ytp-time-display {
    font-size: 13px;
    color: #fff;
    white-space: nowrap;
    padding: 0 6px;
    line-height: 36px;
    font-weight: 400;
}
.ytp-time-sep { opacity: .6; margin: 0 2px; }

/* ── Settings panel ── */
.ytp-settings-wrap { position: relative; }
.ytp-settings-panel {
    display: none;
    position: absolute;
    bottom: 44px;
    right: 0;
    background: rgba(28,28,28,.95);
    border-radius: 12px;
    min-width: 200px;
    /* Cap height so the panel stays inside the player and scrolls when the
       item list (mini/speed/autoplay/shuffle + injected Scoreboard toggles)
       grows taller than the available space above the control bar. */
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    box-shadow: 0 4px 24px rgba(0,0,0,.6);
    z-index: 100;
}
/* Slim, on-brand scrollbar so it doesn't clash with the dark panel. */
.ytp-settings-panel::-webkit-scrollbar { width: 6px; }
.ytp-settings-panel::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.22); border-radius: 3px;
}
.ytp-settings-panel::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.35); }
.ytp-settings-panel::-webkit-scrollbar-track { background: transparent; }
.ytp-settings-panel.open { display: block; }
.ytp-settings-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
    transition: background .15s;
    white-space: nowrap;
}
.ytp-settings-item:hover { background: rgba(255,255,255,.1); }
.ytp-settings-item svg { width: 20px; height: 20px; fill: #fff; flex-shrink: 0; }
.ytp-settings-item .ytp-settings-val {
    margin-left: auto;
    color: rgba(255,255,255,.7);
    font-size: 12px;
    margin-right: 4px;
}
.ytp-chevron { width: 18px; height: 18px; flex-shrink: 0; }

.ytp-speed-panel { display: none; }
.ytp-speed-panel.open { display: block; }
.ytp-speed-back {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,.15);
    font-weight: 600;
}
.ytp-speed-back:hover { background: rgba(255,255,255,.1); }
.ytp-speed-back svg { width: 20px; height: 20px; fill: #fff; }
.ytp-speed-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
    transition: background .15s;
}
.ytp-speed-option:hover { background: rgba(255,255,255,.1); }
.ytp-speed-check { width: 18px; height: 18px; fill: #fff; opacity: 0; flex-shrink: 0; }
.ytp-speed-option.active .ytp-speed-check { opacity: 1; }

/* ── Mobile ── */
@media (max-width: 768px) {
    .ytp-wrap { border-radius: 0; margin: 0; width: 100%; aspect-ratio: 16/9; max-height: none; }
    .ytp-wrap.portrait,
    .ytp-wrap.square,
    .ytp-wrap.ultrawide { aspect-ratio: 16/9; width: 100%; max-width: none; max-height: none; }
    .video-view-page .ytp-wrap ~ * { padding-left: 16px; padding-right: 16px; }
}

@media (max-width: 576px) {
    .ytp-button { width: 32px; height: 32px; }
    .ytp-button svg { width: 18px; height: 18px; }
    .ytp-time-display { font-size: 11px; padding: 0 4px; }
    .ytp-pip-btn, .ytp-theater-btn { display: none; }
}

/* Hide PIP if not supported – handled by JS */
.ytp-pip-btn.unsupported { display: none; }

/* Standalone loop button */
.ytp-loop-btn svg { opacity: .75; transition: opacity .15s, fill .15s; }
.ytp-loop-btn:hover svg { opacity: 1; }
.ytp-loop-btn.is-on svg { fill: #e61e1e; opacity: 1; }

/* Settings panel toggle rows */
.ytp-toggle-row { border-top: 1px solid rgba(255,255,255,.08); }
.ytp-toggle-row svg { opacity: .75; }
.ytp-toggle-val {
    font-size: 11px !important;
    opacity: .6;
    transition: color .15s, opacity .15s;
}
.ytp-toggle-row.is-on .ytp-toggle-val { color: #f00; opacity: 1; }
.ytp-toggle-row.is-on svg { fill: #f00; opacity: 1; }

/* Toggle switch pill */
.ytp-toggle-switch {
    width: 28px; height: 16px;
    background: rgba(255,255,255,.2);
    border-radius: 8px;
    position: relative;
    flex-shrink: 0;
    transition: background .2s;
    margin-left: 6px;
}
.ytp-toggle-row.is-on .ytp-toggle-switch { background: #e61e1e; }
.ytp-toggle-thumb {
    position: absolute;
    top: 2px; left: 2px;
    width: 12px; height: 12px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.4);
}
.ytp-toggle-row.is-on .ytp-toggle-thumb { transform: translateX(12px); }

/* ── Theater mode layout (landscape / ultrawide) ── */
.video-layout-container.theater-mode {
    flex-wrap: wrap;
}
.video-layout-container.theater-mode > .ytp-wrap {
    width: 100%;
    flex-shrink: 0;
}
.video-layout-container.theater-mode .yt-video-section {
    flex: 1;
    min-width: 0;
}
@media (max-width: 1300px) {
    .video-layout-container.theater-mode > .yt-sidebar-container { width: 300px; }
}
@media (min-width: 1301px) {
    .video-layout-container.theater-mode > .yt-sidebar-container { width: 400px; }
}

/* ── Theater mode layout (portrait) — stack everything below ── */
.video-layout-container.theater-mode-portrait {
    flex-direction: column;
}
.video-layout-container.theater-mode-portrait > .ytp-wrap {
    width: 100%;
    flex-shrink: 0;
}
.video-layout-container.theater-mode-portrait .yt-video-section,
.video-layout-container.theater-mode-portrait > .yt-sidebar-container {
    width: 100%;
}
</style>

<script>
/* ══════════════════════════════════════════════════════
   YTP PLAYER ENGINE
══════════════════════════════════════════════════════ */
(function () {

// ── DOM refs ──────────────────────────────────────────
const wrap        = document.getElementById('ytpWrap');
const player      = document.getElementById('videoContainer');
const video       = document.getElementById('videoPlayer');
const playBtn     = document.getElementById('ytpPlayBtn');
const muteBtn     = document.getElementById('ytpMuteBtn');
const volRange    = document.getElementById('ytpVolume');
const volWrap     = document.getElementById('ytpVolumeWrap');
const timeCur     = document.getElementById('ytpCurrent');
const timeDur     = document.getElementById('ytpDuration');
const controls    = document.getElementById('ytpControls');
const progCont    = document.getElementById('ytpProgressContainer');
const progBar     = document.getElementById('ytpProgressBar');
const buffered    = document.getElementById('ytpBuffered');
const played      = document.getElementById('ytpPlayed');
const scrubber    = document.getElementById('ytpScrubber');
const hoverTime   = document.getElementById('ytpHoverTime');
const spinner     = document.getElementById('ytpSpinner');
const largePlay   = document.getElementById('ytpLargePlay');
const dblLeft     = document.getElementById('ytpDblLeft');
const dblRight    = document.getElementById('ytpDblRight');
const settingsBtn = document.getElementById('ytpSettingsBtn');
const settingsPanel = document.getElementById('ytpSettingsPanel');
const speedRow    = document.getElementById('ytpSpeedRow');
const speedPanel  = document.getElementById('ytpSpeedPanel');
const speedBack   = document.getElementById('ytpSpeedBack');
const speedLabel  = document.getElementById('ytpSpeedLabel');
const speedOpts   = document.querySelectorAll('.ytp-speed-option');
const fsBtn       = document.getElementById('ytpFsBtn');
const pipBtn      = document.getElementById('ytpPipBtn');
const theaterBtn  = document.getElementById('ytpTheaterBtn');
const loopBtn     = document.getElementById('ytpLoopBtn');

// ── State ─────────────────────────────────────────────
let hideTimer           = null;
let isDragging          = false;
let isTheater           = false;
let isFullscreen        = false;
let currentSpeed        = 1;
let lastTap             = 0;
let tapTimer            = null;
let userSeeking         = false; // true from seekTo() until 'seeked' fires
let wasPlayingBeforeSeek = false; // remember play state so we can resume after seek

// ── HLS source ────────────────────────────────────────
const HLS_URL   = @json($video['hls'] ?? null);
const MP4_URL   = @json($video['mp4'] ?? null);
const NEXT_URL  = null;
const PREV_URL  = null;
const PL_DATA   = null;  // null when not in a playlist

// ── Playlist shuffle/autoplay state ───────────────────
const shuffleRow  = document.getElementById('ytpShuffleRow');
const shuffleVal  = document.getElementById('ytpShuffleVal');
const autoplayRow = document.getElementById('ytpAutoplayRow');
const autoplayVal = document.getElementById('ytpAutoplayVal');

const plId = PL_DATA?.playlistId ?? null;

let shuffleOn  = plId ? localStorage.getItem('ytpShuffleOn_'  + plId) === '1' : false;
let autoplayOn = plId ? localStorage.getItem('ytpAutoplay') !== '0' : false;

function applyShuffleState() {
    if (!shuffleRow) return;
    shuffleRow.classList.toggle('is-on', shuffleOn);
    if (shuffleVal) shuffleVal.textContent = shuffleOn ? 'On' : 'Off';
}
function applyAutoplayState() {
    if (!autoplayRow) return;
    autoplayRow.classList.toggle('is-on', autoplayOn);
    if (autoplayVal) autoplayVal.textContent = autoplayOn ? 'On' : 'Off';
}

if (shuffleRow) {
    applyShuffleState();
    shuffleRow.addEventListener('click', e => {
        e.stopPropagation();
        shuffleOn = !shuffleOn;
        localStorage.setItem('ytpShuffleOn_' + plId, shuffleOn ? '1' : '0');
        if (shuffleOn) buildShuffledOrder();
        else localStorage.removeItem('ytpShuffledOrder_' + plId);
        applyShuffleState();
    });
}
if (autoplayRow) {
    applyAutoplayState();
    autoplayRow.addEventListener('click', e => {
        e.stopPropagation();
        autoplayOn = !autoplayOn;
        localStorage.setItem('ytpAutoplay', autoplayOn ? '1' : '0');
        applyAutoplayState();
    });
}

function buildShuffledOrder() {
    if (!PL_DATA) return;
    const vids = PL_DATA.videos;
    const curIdx = vids.findIndex(v => v.id === PL_DATA.currentVideoId);
    const others = vids.map((_, i) => i).filter(i => i !== curIdx);
    for (let i = others.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [others[i], others[j]] = [others[j], others[i]];
    }
    const order = [curIdx, ...others];
    localStorage.setItem('ytpShuffledOrder_' + plId, JSON.stringify(order));
    return order;
}

function getShuffledNextUrl() {
    if (!PL_DATA) return NEXT_URL;
    const vids   = PL_DATA.videos;
    const curIdx = vids.findIndex(v => v.id === PL_DATA.currentVideoId);
    const stored = localStorage.getItem('ytpShuffledOrder_' + plId);
    const order  = stored ? JSON.parse(stored) : buildShuffledOrder();
    const posNow = order.indexOf(curIdx);
    const posNext = (posNow + 1) % order.length;
    // Don't wrap around unless loop-all
    if (!isLooping && posNext === 0) return null;
    return vids[order[posNext]]?.showUrl ?? null;
}
function getShuffledPrevUrl() {
    if (!PL_DATA) return PREV_URL;
    const vids   = PL_DATA.videos;
    const curIdx = vids.findIndex(v => v.id === PL_DATA.currentVideoId);
    const stored = localStorage.getItem('ytpShuffledOrder_' + plId);
    const order  = stored ? JSON.parse(stored) : buildShuffledOrder();
    const posNow = order.indexOf(curIdx);
    const posPrev = (posNow - 1 + order.length) % order.length;
    return vids[order[posPrev]]?.showUrl ?? PREV_URL;
}

window._ytpHls        = null;
window._ytpMasterHls  = @json($video['hls'] ?? null);
window._ytpMasterMp4  = @json($video['mp4'] ?? null);
window._ytpWasPlaying = false;
video.addEventListener('play',  function () { window._ytpWasPlaying = true;  });
video.addEventListener('pause', function () { window._ytpWasPlaying = false; });

function initSource() {
    video.muted    = true;
    video.autoplay = true;
    /* Resume handoff from the mini player: ?t=<sec> seeks the video to that
       position once metadata is ready. One-shot — only the initial load. */
    try {
        var _qs = new URLSearchParams(location.search);
        var _t  = parseInt(_qs.get('t') || '0', 10);
        if (_t > 0) {
            video.addEventListener('loadedmetadata', function () {
                if (_t < (video.duration || Infinity)) {
                    try { video.currentTime = _t; } catch (e) {}
                }
            }, { once: true });
        }
    } catch (e) {}
    if (HLS_URL && window.Hls && Hls.isSupported()) {
        window._ytpHls = new Hls({ startLevel: -1 });
        // Register MANIFEST_PARSED before loadSource to avoid cache race condition
        window._ytpHls.once(Hls.Events.MANIFEST_PARSED, function() {
            video.muted = true;
            video.play().catch(function(){});
        });
        window._ytpHls.loadSource(HLS_URL);
        window._ytpHls.attachMedia(video);
    } else if (HLS_URL && video.canPlayType('application/vnd.apple.mpegurl')) {
        // Native HLS (Safari) — set src then play on loadedmetadata
        video.src = HLS_URL;
        video.load();
        video.addEventListener('loadedmetadata', function() {
            video.muted = true;
            video.play().catch(function(){});
        }, { once: true });
    } else {
        // Plain MP4 — play on loadedmetadata (canplay/MANIFEST_PARSED don't apply)
        video.src = MP4_URL;
        video.load();
        video.addEventListener('loadedmetadata', function() {
            video.muted = true;
            video.play().catch(function(){});
        }, { once: true });
    }
}

// Reinitialize source after SPA transition — called by playlist overlay scripts
window._ytpLoadSource = function(hlsUrl, mp4Url) {
    window._ytpMasterHls = hlsUrl || null;
    window._ytpMasterMp4 = mp4Url || null;
    const _vol   = video.volume;
    const _muted = video.muted;
    if (window._ytpHls) { window._ytpHls.destroy(); window._ytpHls = null; }
    video.muted    = true;
    video.autoplay = true;
    if (hlsUrl && window.Hls && Hls.isSupported()) {
        window._ytpHls = new Hls({ startLevel: -1 });
        window._ytpHls.once(Hls.Events.MANIFEST_PARSED, function() {
            video.volume = _vol;
            video.muted  = _muted;
            video.play().catch(function(){});
        });
        window._ytpHls.loadSource(hlsUrl);
        window._ytpHls.attachMedia(video);
    } else if (hlsUrl && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = hlsUrl;
        video.load();
        video.volume = _vol;
        video.addEventListener('loadedmetadata', function() {
            video.muted = _muted;
            video.play().catch(function(){});
        }, { once: true });
    } else {
        video.src = mp4Url;
        video.load();
        video.volume = _vol;
        video.addEventListener('loadedmetadata', function() {
            video.muted = _muted;
            video.play().catch(function(){});
        }, { once: true });
    }
};

// Load HLS.js from CDN then init
function loadHlsJs(cb) {
    if (window.Hls) { cb(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5/dist/hls.min.js';
    s.onload = cb;
    document.head.appendChild(s);
}

// ── Helpers ───────────────────────────────────────────
function fmt(s) {
    if (!isFinite(s) || isNaN(s)) return '0:00';
    s = Math.floor(s);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) return h + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
    return m + ':' + String(sec).padStart(2,'0');
}

function updatePlayIcon() {
    const playSvg  = playBtn.querySelector('.ytp-svg-play');
    const pauseSvg = playBtn.querySelector('.ytp-svg-pause');
    if (video.paused) {
        playSvg.style.display  = '';
        pauseSvg.style.display = 'none';
    } else {
        playSvg.style.display  = 'none';
        pauseSvg.style.display = '';
    }
}

function updateVolumeIcon() {
    const svgs = ['ytp-svg-vol3','ytp-svg-vol2','ytp-svg-vol1','ytp-svg-vol0'];
    const vol = video.volume;
    const muted = video.muted || vol === 0;
    svgs.forEach(c => muteBtn.querySelector('.'+c).style.display = 'none');
    if (muted)         muteBtn.querySelector('.ytp-svg-vol0').style.display = '';
    else if (vol > .5) muteBtn.querySelector('.ytp-svg-vol3').style.display = '';
    else if (vol > .1) muteBtn.querySelector('.ytp-svg-vol2').style.display = '';
    else               muteBtn.querySelector('.ytp-svg-vol1').style.display = '';
    volRange.value = muted ? 0 : Math.round(vol * 100);
    volRange.style.setProperty('--vol', (muted ? 0 : vol * 100) + '%');
}

function updateProgress() {
    if (!video.duration) return;
    const pct = (video.currentTime / video.duration) * 100;
    played.style.width = pct + '%';
    scrubber.parentElement.style.left = pct + '%';
    timeCur.textContent = fmt(video.currentTime);

    // buffered
    if (video.buffered.length > 0) {
        const bufEnd = video.buffered.end(video.buffered.length - 1);
        buffered.style.width = ((bufEnd / video.duration) * 100) + '%';
    }
}

// ── Controls visibility ───────────────────────────────
function showControls() {
    player.classList.remove('controls-hidden');
    resetHideTimer();
}
function resetHideTimer() {
    clearTimeout(hideTimer);
    if (!video.paused) {
        hideTimer = setTimeout(() => player.classList.add('controls-hidden'), 3000);
    }
}
function onActivity() { showControls(); }

// ── Play / Pause ──────────────────────────────────────
function togglePlay() {
    if (video.paused) {
        video.play();
        largePlay.classList.remove('visible');
    } else {
        video.pause();
        largePlay.classList.add('visible');
    }
}
playBtn.addEventListener('click', e => { e.stopPropagation(); togglePlay(); showControls(); });

// ── Click on video to play/pause, dbl-click seek/fullscreen ──
let clickTimer = null;
player.addEventListener('click', e => {
    if (e.target === settingsBtn || settingsPanel.contains(e.target)) return;
    if (progCont.contains(e.target)) return;
    clearTimeout(clickTimer);
    clickTimer = setTimeout(() => { togglePlay(); }, 200);
});
player.addEventListener('dblclick', e => {
    clearTimeout(clickTimer);
    const rect = player.getBoundingClientRect();
    const x = e.clientX - rect.left;
    if (x < rect.width * .4) {
        seekRelative(-10);
        flashDbl(dblLeft);
    } else if (x > rect.width * .6) {
        seekRelative(10);
        flashDbl(dblRight);
    } else {
        toggleFullscreen();
    }
});

function flashDbl(el) {
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 600);
}

// ── Mouse move / touch on player ─────────────────────
player.addEventListener('mousemove', onActivity);
player.addEventListener('touchstart', onActivity, { passive: true });

// ── Volume ────────────────────────────────────────────
muteBtn.addEventListener('click', e => {
    e.stopPropagation();
    video.muted = !video.muted;
    if (!video.muted && video.volume === 0) video.volume = 0.2;
    updateVolumeIcon();
    localStorage.setItem('ytpMuted', video.muted ? '1' : '0');
    showControls();
});
volRange.addEventListener('input', e => {
    e.stopPropagation();
    const v = parseInt(e.target.value) / 100;
    video.volume = v;
    video.muted  = v === 0;
    updateVolumeIcon();
    localStorage.setItem('ytpVolume', e.target.value);
    localStorage.setItem('ytpMuted', v === 0 ? '1' : '0');
});
volRange.addEventListener('click', e => e.stopPropagation());

// ── Progress bar scrubbing ────────────────────────────
function seekTo(pct) {
    if (!video.duration) return;
    if (!userSeeking) wasPlayingBeforeSeek = !video.paused;
    userSeeking = true;
    video.currentTime = pct * video.duration;
    updateProgress();
}
function progressPct(e) {
    const rect = progBar.getBoundingClientRect();
    return Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
}
function touchProgressPct(e) {
    const rect = progBar.getBoundingClientRect();
    return Math.max(0, Math.min(1, (e.touches[0].clientX - rect.left) / rect.width));
}

progCont.addEventListener('mousemove', e => {
    const pct = progressPct(e);
    hoverTime.textContent = fmt(pct * (video.duration || 0));
    hoverTime.style.left  = (pct * 100) + '%';
    if (isDragging) {
        seekTo(pct);
        played.style.width = (pct * 100) + '%';
        scrubber.parentElement.style.left = (pct * 100) + '%';
    }
});
progCont.addEventListener('mousedown', e => {
    e.preventDefault();
    isDragging = true;
    progBar.classList.add('dragging');
    seekTo(progressPct(e));
});
document.addEventListener('mouseup', () => {
    if (isDragging) { isDragging = false; progBar.classList.remove('dragging'); }
});
document.addEventListener('mousemove', e => {
    if (isDragging) {
        const rect = progBar.getBoundingClientRect();
        const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        seekTo(pct);
    }
});
progCont.addEventListener('touchstart', e => {
    isDragging = true;
    progBar.classList.add('dragging');
    seekTo(touchProgressPct(e));
}, { passive: true });
progCont.addEventListener('touchmove', e => {
    if (isDragging) seekTo(touchProgressPct(e));
}, { passive: true });
progCont.addEventListener('touchend', e => { e.preventDefault(); isDragging = false; progBar.classList.remove('dragging'); });

// ── Settings panel ────────────────────────────────────
settingsBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = settingsPanel.classList.toggle('open');
    if (!open) { speedPanel.classList.remove('open'); speedRow.style.display = ''; }
    /* Sync the mini-player toggle row's label to the current preference each
       time the gear opens, so reloading the page or toggling from the music
       player keeps the indicator honest. */
    if (open) {
        const miniRow = document.getElementById('ytpMiniToggleRow');
        if (miniRow) {
            const v = miniRow.querySelector('.ytp-settings-val');
            const on = !window._ytpMiniEnabled || window._ytpMiniEnabled();
            if (v) v.textContent = on ? 'On' : 'Off';
        }
    }
    showControls();
    clearTimeout(hideTimer); // keep controls visible while settings open
});
document.addEventListener('click', e => {
    if (!document.getElementById('ytpSettingsWrap').contains(e.target)) {
        settingsPanel.classList.remove('open');
        speedPanel.classList.remove('open');
        speedRow.style.display = '';
    }
});
if (speedRow) {
    speedRow.addEventListener('click', e => {
        e.stopPropagation();
        speedRow.style.display = 'none';
        speedPanel.classList.add('open');
    });
}
if (speedBack) {
    speedBack.addEventListener('click', e => {
        e.stopPropagation();
        speedPanel.classList.remove('open');
        speedRow.style.display = '';
    });
}
speedOpts.forEach(opt => {
    opt.addEventListener('click', e => {
        e.stopPropagation();
        const speed = parseFloat(opt.dataset.speed);
        video.playbackRate = speed;
        currentSpeed = speed;
        speedOpts.forEach(o => o.classList.remove('active'));
        opt.classList.add('active');
        speedLabel.textContent = speed === 1 ? 'Normal' : speed;
        settingsPanel.classList.remove('open');
        speedPanel.classList.remove('open');
        speedRow.style.display = '';
    });
});

// ── Fullscreen ────────────────────────────────────────
function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        wrap.requestFullscreen && wrap.requestFullscreen();
    }
}
fsBtn.addEventListener('click', e => { e.stopPropagation(); toggleFullscreen(); showControls(); });
document.addEventListener('fullscreenchange', () => {
    isFullscreen = !!document.fullscreenElement;
    wrap.classList.toggle('ytp-fullscreen', isFullscreen);
    const enter = fsBtn.querySelector('.ytp-svg-fs-enter');
    const exit  = fsBtn.querySelector('.ytp-svg-fs-exit');
    enter.style.display = isFullscreen ? 'none' : '';
    exit.style.display  = isFullscreen ? '' : 'none';
    if (screen.orientation && screen.orientation.lock) {
        if (isFullscreen) screen.orientation.lock('landscape').catch(function(){});
        else              screen.orientation.unlock();
    }
});

// ── Theater mode ──────────────────────────────────────
if (theaterBtn) {
    theaterBtn.addEventListener('click', e => {
        e.stopPropagation();
        isTheater = !isTheater;
        wrap.classList.toggle('theater', isTheater);
        theaterBtn.querySelector('.ytp-svg-theater-off').style.display = isTheater ? 'none' : '';
        theaterBtn.querySelector('.ytp-svg-theater-on').style.display  = isTheater ? '' : 'none';

        const layout       = document.querySelector('.video-layout-container');
        const videoSection = document.querySelector('.yt-video-section');

        if (layout && videoSection) {
            const isPortrait = wrap.classList.contains('portrait');
            if (isTheater) {
                layout.insertBefore(wrap, layout.firstChild);
                // Portrait videos are tall/narrow — stack everything below.
                // Landscape/ultrawide — keep comments and sidebar side by side.
                layout.classList.add(isPortrait ? 'theater-mode-portrait' : 'theater-mode');
            } else {
                videoSection.insertBefore(wrap, videoSection.firstChild);
                layout.classList.remove('theater-mode', 'theater-mode-portrait');
            }
        }
        showControls();
    });
}

// ── Loop ──────────────────────────────────────────────
let isLooping = localStorage.getItem('ytpLoop') === '1';
video.loop = isLooping;
function applyLoopState() {
    if (!loopBtn) return;
    loopBtn.classList.toggle('is-on', isLooping);
    loopBtn.title = isLooping ? 'Loop: On' : 'Loop';
}
applyLoopState();
if (loopBtn) {
    loopBtn.addEventListener('click', e => {
        e.stopPropagation();
        isLooping = !isLooping;
        video.loop = isLooping;
        localStorage.setItem('ytpLoop', isLooping ? '1' : '0');
        applyLoopState();
        showControls();
    });
}

// ── Wake Lock (keep screen on while playing) ──────────
let wakeLock = null;
async function requestWakeLock() {
    if (!('wakeLock' in navigator) || wakeLock) return;
    try { wakeLock = await navigator.wakeLock.request('screen'); } catch(e) {}
}
function releaseWakeLock() {
    if (wakeLock) { wakeLock.release(); wakeLock = null; }
}
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && !video.paused) requestWakeLock();
});

// ── Picture-in-Picture ────────────────────────────────
if (pipBtn) {
    if (!document.pictureInPictureEnabled) {
        pipBtn.classList.add('unsupported');
    } else {
        pipBtn.addEventListener('click', e => {
            e.stopPropagation();
            if (document.pictureInPictureElement) {
                document.exitPictureInPicture();
            } else {
                video.requestPictureInPicture().catch(() => {});
            }
            showControls();
        });
    }
}

// ── Video events ──────────────────────────────────────
video.addEventListener('play',     () => { updatePlayIcon(); resetHideTimer(); largePlay.classList.remove('visible'); requestWakeLock(); });
video.addEventListener('pause',    () => {
    if (userSeeking) return; // suppress mid-seek pause events
    updatePlayIcon(); showControls(); largePlay.classList.add('visible'); clearTimeout(hideTimer); releaseWakeLock();
});
video.addEventListener('seeked',   () => {
    userSeeking = false;
    if (wasPlayingBeforeSeek) {
        // Resume if the browser/HLS.js paused the video during seeking
        if (video.paused) video.play().catch(() => {});
        largePlay.classList.remove('visible');
        resetHideTimer();
    }
});
video.addEventListener('timeupdate', updateProgress);
video.addEventListener('progress',   updateProgress);
video.addEventListener('durationchange', () => { timeDur.textContent = fmt(video.duration); });
video.addEventListener('waiting',  () => spinner.classList.add('active'));
video.addEventListener('playing',  () => spinner.classList.remove('active'));
video.addEventListener('canplay',  () => spinner.classList.remove('active'));
function navigateNext() {
    const url = shuffleOn ? getShuffledNextUrl() : NEXT_URL;
    if (window._ytpNavOverride?.next) { window._ytpNavOverride.next(url); return; }
    if (url) window.location.href = url;
}
function navigatePrev() {
    const url = shuffleOn ? getShuffledPrevUrl() : PREV_URL;
    if (window._ytpNavOverride?.prev) { window._ytpNavOverride.prev(url); return; }
    if (url) window.location.href = url;
}
window._ytpNav = { next: navigateNext, prev: navigatePrev };

video.addEventListener('ended', () => {
    updatePlayIcon();
    largePlay.classList.add('visible');
    clearTimeout(hideTimer);
    releaseWakeLock();
    if (window._plOnVideoEnd) {
        window._plOnVideoEnd();
    } else if (autoplayOn || isLooping) {
        navigateNext();
    }
});
video.addEventListener('volumechange', updateVolumeIcon);

// ── Keyboard shortcuts ────────────────────────────────
document.addEventListener('keydown', e => {
    const tag = document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable) return;

    switch (e.key) {
        case ' ':
        case 'k': case 'K':
            e.preventDefault(); togglePlay(); showControls(); break;
        case 'm': case 'M':
            e.preventDefault();
            video.muted = !video.muted;
            if (!video.muted && video.volume === 0) video.volume = 0.5;
            updateVolumeIcon();
            localStorage.setItem('ytpMuted', video.muted ? '1' : '0');
            showControls(); break;
        case 'f': case 'F':
            e.preventDefault(); toggleFullscreen(); break;
        case 't': case 'T':
            e.preventDefault(); theaterBtn && theaterBtn.click(); break;
        case 'i': case 'I':
            e.preventDefault(); pipBtn && !pipBtn.classList.contains('unsupported') && pipBtn.click(); break;
        case 'j': case 'J':
            e.preventDefault(); seekRelative(-10); flashDbl(dblLeft); showControls(); break;
        case 'l': case 'L':
            e.preventDefault(); seekRelative(10); flashDbl(dblRight); showControls(); break;
        case 'ArrowLeft':
            e.preventDefault(); seekRelative(-5); showControls(); break;
        case 'ArrowRight':
            e.preventDefault(); seekRelative(5); showControls(); break;
        case 'ArrowUp':
            e.preventDefault();
            video.volume = Math.min(1, video.volume + 0.05);
            video.muted  = false;
            updateVolumeIcon();
            showControls(); break;
        case 'ArrowDown':
            e.preventDefault();
            video.volume = Math.max(0, video.volume - 0.05);
            updateVolumeIcon();
            showControls(); break;
        default:
            if (e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                if (video.duration) video.currentTime = (parseInt(e.key) / 10) * video.duration;
                showControls();
            }
    }
});

function seekRelative(secs) {
    if (!video.duration) return;
    video.currentTime = Math.max(0, Math.min(video.duration, video.currentTime + secs));
    updateProgress();
}

// ── Init ──────────────────────────────────────────────
function init() {
    const savedVol   = localStorage.getItem('ytpVolume');
    const savedMuted = localStorage.getItem('ytpMuted');
    video.volume = savedVol ? parseInt(savedVol) / 100 : 0.5;
    video.muted  = true; // start muted so autoplay is never blocked
    updateVolumeIcon();

    // After first play, restore user's preferred mute state
    video.addEventListener('playing', function restoreSound() {
        if (savedMuted !== '1') {
            video.muted = false;
            updateVolumeIcon();
        }
    }, { once: true });

    // If user returned from mini-player, seek to the saved timestamp
    const miniRaw = sessionStorage.getItem('ytpMiniState');
    let miniSeekTime = 0;
    if (miniRaw) {
        try {
            const ms = JSON.parse(miniRaw);
            if (ms && ms.time > 0) miniSeekTime = ms.time;
        } catch(e) {}
        sessionStorage.removeItem('ytpMiniState');
    }

    // canplay is a belt-and-suspenders fallback — also mute before play
    video.addEventListener('canplay', function autoStart() {
        video.removeEventListener('canplay', autoStart);
        if (miniSeekTime > 0) {
            video.currentTime = miniSeekTime;
            miniSeekTime = 0;
        }
        if (video.paused) {
            video.muted = true;
            video.play().catch(() => {});
        }
    });

    // Update duration when metadata is ready
    video.addEventListener('loadedmetadata', () => {
        timeDur.textContent = fmt(video.duration);
    });

    // Load source
    loadHlsJs(initSource);

    // Initial state
    largePlay.classList.add('visible');
    showControls();

    // Scroll-based mini player: watch when #ytpWrap leaves the viewport.
    // Desktop-only — on mobile the fixed bottom-nav + locked scroll model
    // make a floating overlay disruptive.
    if (window.IntersectionObserver && window._miniPlayer && window.innerWidth > 768) {
        var _scrollRoot  = null; /* desktop: window scrolls */
        var _scrollMiniOn = false; /* guard against rapid toggling / initial fire */
        new IntersectionObserver(function (entries) {
            var e0 = entries[0];
            /* Only activate after the video has actually started playing (_ytpWasPlaying).
               Using !video.paused was unreliable: autoplay fires asynchronously and the
               initial IntersectionObserver callback could run before HLS.js even attaches,
               teleporting the element before it ever played in the main player. */
            var miniAllowed = !window._ytpMiniEnabled || window._ytpMiniEnabled();
            if (!e0.isIntersecting && !_scrollMiniOn && window._ytpWasPlaying && miniAllowed && !window._miniPlayer.isNavMode()) {
                _scrollMiniOn = true;
                window._miniPlayer.activateScroll(
                    document.title.replace(/\s*\|.*$/, '').trim(),
                    window.location.href
                );
            } else if (e0.isIntersecting && _scrollMiniOn) {
                _scrollMiniOn = false;
                window._miniPlayer.deactivateScroll();
            }
        }, { root: _scrollRoot, threshold: 0.15 }).observe(wrap);

        /* User clicked the X on the mini while still on the video page —
           reset the flag so a subsequent scroll-away re-activates it. */
        window.addEventListener('miniplayer:scroll-closed', function () {
            _scrollMiniOn = false;
        });
    }
}

document.addEventListener('DOMContentLoaded', init);

// ── View-progress heartbeat ──────────────────────────────
(function() {
    const _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let _hbVideoId = null;
    let _hbUrl     = null;
    let _hbLast    = 0;
    let _hbCompleted = false;

    function _refreshHbTarget() {
        const wrap = document.getElementById('ytpWrap');
        if (!wrap) return;
        const vid = parseInt(wrap.dataset.videoId || '0', 10);
        if (vid && vid !== _hbVideoId) {
            _hbVideoId   = vid;
            _hbUrl       = wrap.dataset.progressUrl || null;
            _hbLast      = 0;
            _hbCompleted = false;
        }
    }
    function _sendHb(completed) {
        if (!_hbUrl) return;
        const v = document.getElementById('videoPlayer');
        if (!v) return;
        const cur = Math.floor(v.currentTime || 0);
        if (!completed && cur <= _hbLast) return;
        const body = new URLSearchParams({ watched_seconds: cur, completed: completed ? '1' : '0' });
        try {
            if (completed && navigator.sendBeacon) {
                const blob = new Blob([body.toString() + '&_token=' + _csrf], { type: 'application/x-www-form-urlencoded' });
                navigator.sendBeacon(_hbUrl, blob);
            } else {
                fetch(_hbUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': _csrf, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                    body: body.toString(),
                    keepalive: true,
                    credentials: 'same-origin',
                }).catch(function() {});
            }
            _hbLast = cur;
            if (completed) _hbCompleted = true;
        } catch (e) {}
    }
    function _hbBind() {
        const v = document.getElementById('videoPlayer');
        if (!v || v._hbBound) return;
        v._hbBound = true;
        _refreshHbTarget();
        v.addEventListener('ended',    () => _sendHb(true));
        v.addEventListener('pause',    () => _sendHb(false));
    }
    setInterval(function() { _refreshHbTarget(); if (!_hbCompleted) _sendHb(false); }, 5000);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') _sendHb(false);
    });
    window.addEventListener('pagehide', function() { _sendHb(false); });
    document.addEventListener('DOMContentLoaded', _hbBind);
    // also rebind after SPA source swaps
    const _origLoad = window._ytpLoadSource;
    if (typeof _origLoad === 'function') {
        window._ytpLoadSource = function() {
            _refreshHbTarget();
            return _origLoad.apply(this, arguments);
        };
    }
})();

})();
</script>



            
            <style>
/* ── Description box ─────────────────────────────────── */
.vdb-wrap { background:var(--bg-secondary); border-radius:12px; margin-top:12px; overflow:hidden; border:1px solid var(--border-color); }
.vdb-tabs { display:flex; border-bottom:1px solid var(--border-color); padding:0 4px; }
.vdb-tab { background:none; border:none; color:var(--text-secondary); font-size:13px; font-weight:600; padding:0 16px; height:44px; cursor:pointer; position:relative; transition:color .15s; white-space:nowrap; display:flex; align-items:center; gap:6px; }
.vdb-tab:hover { color:var(--text-primary); }
.vdb-tab.active { color:var(--text-primary); }
.vdb-tab.active::after { content:''; position:absolute; bottom:0; left:0; right:0; height:2px; background:#ef4444; border-radius:2px 2px 0 0; }
.vdb-panel { display:none; padding:14px 16px 16px; }
.vdb-panel.active { display:block; }
.vdb-meta { font-size:13px; font-weight:600; color:var(--text-secondary); margin-bottom:10px; display:flex; gap:10px; flex-wrap:wrap; }
.vdb-desc-text { font-size:14px; line-height:1.6; color:var(--text-primary); word-break:break-word; }
.vdb-desc-text p { margin:0 0 8px; }
.vdb-desc-text p:last-child { margin-bottom:0; }
.vdb-desc-text h2 { font-size:19px; font-weight:700; margin:6px 0 8px; }
.vdb-desc-text h3 { font-size:16px; font-weight:700; margin:6px 0 6px; }
.vdb-desc-text ul, .vdb-desc-text ol { margin:0 0 8px; padding-left:22px; }
.vdb-desc-text blockquote { margin:0 0 8px; padding-left:12px; border-left:3px solid var(--border-color); color:var(--text-secondary); }
.vdb-desc-text a { color:#3ea6ff; }
.vdb-desc-text a.action-btn { display:inline-flex; margin:4px 6px 4px 0; color:inherit; text-decoration:none; vertical-align:middle; }
.vdb-desc-text.vdb-clamp { max-height:130px; overflow:hidden; -webkit-mask-image:linear-gradient(180deg,#000 70%,transparent); mask-image:linear-gradient(180deg,#000 70%,transparent); }
.vdb-desc-text.vdb-clamp.vdb-expanded { max-height:none; -webkit-mask-image:none; mask-image:none; }
.vdb-show-more { display:flex; align-items:center; justify-content:center; gap:6px; margin:12px auto 0; background:var(--bg-secondary); border:1px solid var(--border-color); color:var(--text-primary); font-weight:700; font-size:13px; cursor:pointer; padding:7px 16px; border-radius:18px; transition:background .15s ease, border-color .15s ease; }
.vdb-show-more:hover { background:var(--bg-hover, rgba(127,127,127,.12)); }
.vdb-show-more i { font-size:14px; transition:transform .2s ease; }
.vdb-show-more.expanded i { transform:rotate(180deg); }
</style>

{{-- Emptied: its content is the "Match" tab now. Kept, and hidden, so the
     stylesheet it carries still reaches the card wherever that tab renders. --}}
<div class="vdb-wrap" id="vdbWrap" style="display:none" aria-hidden="true">
    <div class="vdb-panel active" id="vdb-about">
                    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Barlow+Condensed:wght@400;600;700&display=swap">

<style>
/* ── Match about card ─────────────────────────────────────────────────
   Sized in container-query units so the whole card scales with the About
   panel, not the viewport (it sits in a column that changes width with the
   sidebar). Below 640px the container query flips to a stacked, px-sized
   layout — cqw text would be unreadable at phone widths.                */
/* Full-bleed inside the About panel: the negative margins cancel the panel's
   own 14px/16px padding so the card meets the description box's edges, and the
   shell drops its top/side border + radius to merge into .vdb-wrap. The bottom
   border then doubles as the divider above the views/date meta row. */
.mac-card { container-type: inline-size; width: auto; margin: -14px -16px 14px; font-family: 'Barlow Condensed', 'Archivo', system-ui, sans-serif; }
.mac-shell { position: relative; background: #17171a; border: 0; border-bottom: 1px solid #2a2a2e; border-radius: 0; overflow: hidden; color: #eaeaea; display: flex; flex-direction: column; }
.mac-shell a { text-decoration: none; color: inherit; transition: color .15s; }
.mac-shell a:hover { color: #ffd75e; }

.mac-head { display: flex; align-items: baseline; justify-content: space-between; gap: 2cqw; padding: 1.8cqw 3cqw 0; }
.mac-head-l { display: flex; flex-direction: column; gap: .6cqw; min-width: 0; }
.mac-event { font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; font-size: 2.2cqw; letter-spacing: .04em; color: #fff; }
.mac-sub { display: flex; align-items: center; flex-wrap: wrap; gap: .8cqw; font-size: 1.45cqw; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #9a9aa0; }
.mac-gold { color: #ffd75e; }
.mac-dot { color: #55555c; }
.mac-head-r { display: flex; flex-direction: column; align-items: flex-end; gap: .35cqw; font-size: 1.45cqw; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; text-align: right; color: #9a9aa0; white-space: nowrap; }
.mac-when { color: #d9d9d9; }

.mac-body { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 2cqw; padding: 1.6cqw 3cqw; }
.mac-side { display: flex; align-items: center; gap: 1.6cqw; min-width: 0; }
.mac-side-blue { justify-content: flex-end; text-align: right; }
.mac-photo { display: block; width: 8.5cqw; height: 10cqw; flex: none; border-radius: 8px; background: #1f1f23 center/cover no-repeat; transition: box-shadow .15s, transform .15s; }
a.mac-photo:hover { box-shadow: 0 0 0 2px #ffd75e; transform: translateY(-1px); }
.mac-photo-red  { border-left: 3px solid #e01a2b; }
.mac-photo-blue { border-right: 3px solid #1e46e0; }
.mac-id { display: flex; flex-direction: column; gap: .5cqw; min-width: 0; }
.mac-side-blue .mac-id { align-items: flex-end; }
.mac-corner { display: flex; align-items: center; gap: .7cqw; font-size: 1.35cqw; font-weight: 700; letter-spacing: .1em; }
.mac-corner-red  { color: #ff5c6a; }
.mac-corner-blue { color: #6f8cff; }
/* Flags are sized in `em` off the corner label so they always track the text,
   locked to the 4:3 aspect of the flag-icons SVGs, `flex: none` so a tight row
   can never squash one, and `cover` so the artwork fills the box edge to edge
   instead of leaving the card background showing through. */
.mac-flag { display: inline-block; flex: none; width: 2.13em; height: 1.6em; border-radius: 2px; background-size: cover !important; background-position: 50% !important; box-shadow: inset 0 0 0 1px rgba(255,255,255,.18); }
.mac-win { background: #ffd75e; color: #1a1206; padding: .1cqw .6cqw; border-radius: 3px; letter-spacing: .08em; }
.mac-name { display: block; font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; font-size: 2.3cqw; line-height: 1.05; color: #fff; overflow-wrap: anywhere; }
.mac-club { display: flex; align-items: center; gap: .7cqw; font-size: 1.4cqw; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #9a9aa0; }
.mac-logo { width: 3.2cqw; height: 3.2cqw; flex: none; border-radius: 50%; background: #1f1f23 center/contain no-repeat; }

.mac-score { display: flex; flex-direction: column; align-items: center; gap: .3cqw; padding: 0 2cqw; }
.mac-score-lbl { font-size: 1.1cqw; font-weight: 700; letter-spacing: .3em; color: #9a9aa0; }
.mac-score-row { display: flex; align-items: baseline; gap: 1cqw; font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; }
.mac-pts { font-size: 5.2cqw; line-height: 1; }
.mac-pts-red  { color: #ff5c6a; }
.mac-pts-blue { color: #6f8cff; }
.mac-pts-txt { font-size: 2.4cqw; color: #fff; white-space: nowrap; }
.mac-dash { font-size: 2.4cqw; color: #55555c; }

.mac-foot { display: flex; align-items: center; justify-content: space-between; gap: 2cqw; padding: 1.2cqw 3cqw; border-top: 1px solid #2a2a2e; }
/* Several officials sit in a row and wrap; one on its own is indistinguishable
   from before, which is the point. */
.mac-refs { display: flex; align-items: center; flex-wrap: wrap; gap: 1.2cqw 3cqw; min-width: 0; }
.mac-ref { display: flex; align-items: center; gap: 1cqw; min-width: 0; }
/* 3:4 portrait, sized by the text beside it rather than by a fixed number:
   align-self:stretch takes the height of the label-plus-name block (so the picture
   runs from the top of the label to the bottom of the flag), and aspect-ratio
   derives the width from it. Nothing to re-tune when the type scale changes. */
.mac-ref-pic { align-self: center; width: 4.85cqw; height: auto; aspect-ratio: 3 / 4; flex: 0 0 auto; border-radius: .5cqw; background: #1f1f23 center 20% / cover no-repeat; }
.mac-ref-txt { display: flex; flex-direction: column; justify-content: center; gap: .1cqw; min-width: 0; }
.mac-ref-lbl { font-size: 1cqw; font-weight: 700; letter-spacing: .25em; color: #9a9aa0; }
.mac-ref-name { display: flex; align-items: center; gap: .6cqw; font-size: 1.5cqw; font-weight: 700; line-height: 1.1; color: #d9d9d9; }
.mac-more { font-size: 1.3cqw; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #9a9aa0; white-space: nowrap; }

/* ── Narrow container: stack the corners, fix the type in px ─────────── */
@container (max-width: 640px) {
    .mac-head { flex-direction: column; align-items: flex-start; gap: 6px; padding: 12px 14px 0; }
    .mac-event { font-size: 17px; }
    .mac-sub { font-size: 11px; gap: 5px; }
    .mac-head-r { align-items: flex-start; text-align: left; font-size: 11px; gap: 2px; }

    .mac-body { grid-template-columns: 1fr; gap: 10px; padding: 12px 14px; }
    .mac-side, .mac-side-blue { justify-content: flex-start; text-align: left; gap: 10px; }
    .mac-side-red { order: 1; }
    .mac-side-blue { order: 3; }
    .mac-side-blue { flex-direction: row-reverse; }
    .mac-side-blue .mac-id { align-items: flex-start; }
    .mac-side-blue .mac-club { flex-direction: row-reverse; justify-content: flex-end; }
    .mac-side-blue .mac-corner { flex-direction: row-reverse; justify-content: flex-end; }
    .mac-photo { width: 54px; height: 66px; border-radius: 6px; }
    .mac-id { gap: 3px; }
    .mac-corner { font-size: 11px; gap: 5px; }
    .mac-win { padding: 1px 5px; }
    .mac-name { font-size: 18px; }
    .mac-club { font-size: 11px; gap: 5px; }
    .mac-logo { width: 18px; height: 18px; }

    .mac-score { flex-direction: row; align-items: center; justify-content: center; gap: 10px; padding: 8px 0; border-top: 1px solid #2a2a2e; border-bottom: 1px solid #2a2a2e; order: 2; }
    .mac-score-lbl { font-size: 10px; }
    .mac-pts { font-size: 34px; }
    .mac-pts-txt, .mac-dash { font-size: 18px; }

    .mac-foot { padding: 10px 14px; gap: 10px; }
    .mac-ref-lbl { font-size: 9px; }
    .mac-ref-name { font-size: 13px; }
    .mac-more { font-size: 11px; }
}

/* ══════════════════════════════════════════════════════════════════════
   MOBILE BLOCK (.macm) — drafts/match-about-mobile.html
   Its own container so the cqw units below size against the card's width.
   Only one of the two blocks is ever shown; the swap is a MEDIA query
   because it has to toggle the containers themselves, and a container
   query can never match its own container element.

   The threshold is 991px, which is the breakpoint Play already treats as
   "mobile" elsewhere on this page (see isMobile() in match.blade.php). At
   640px there was a dead band from 641-991px where neither layout was the
   intended one: the mobile block was still hidden and the desktop card fell
   back to its old stacked narrow branch, whose 12px/14px padding read as
   gaps down the sides.
   ══════════════════════════════════════════════════════════════════════ */
.macm { display: none; }

@media (max-width: 991px) {
    .mac-card { display: none; }

    /* Insights stat cards: let them use the width they have.
       The panel pads 16px each side, so on a phone the 2-up grid was sitting in
       a narrow channel with the cards squeezed. Pulling just the GRID out to the
       panel edges gives each card ~16px more without disturbing the charts and
       rows below it, which keep their padding. Slightly tighter gap and a bigger
       number for the space gained.
       Match-page only (this stylesheet ships with match videos), so the music
       and generic insights panels are untouched. */
    #vdb-insights .ins-grid {
        margin-left: -16px; margin-right: -16px;
        gap: 6px;
    }
    #vdb-insights .ins-card { padding: 12px 10px 11px; }
    #vdb-insights .ins-card-val { font-size: 24px; }
    #vdb-insights .ins-card-sub { font-size: 11px; }

    /* Square corners on the About/Insights box.
       The card inside runs edge to edge, so a 12px radius on the wrapper left
       rounded stubs of panel background at the four corners. Declared here
       rather than in description-box, which is shared with the music and
       generic types (RULE #3) — this stylesheet only ships with a match video. */
    .vdb-wrap { border-radius: 0; }

    /* Flush to the About panel's edges.
       Rather than cancelling the panel's padding with matching negative
       margins — which only works while the two numbers agree, and left a strip
       of the panel background showing when they did not — the panel's own
       padding is removed and given back to the two elements below the card
       that still want it. Scoped to #vdb-about, and this stylesheet only ever
       ships with a match video, so no other panel or video type is affected. */
    /* No padding surgery on the panel: the script at the end of this file
       measures the gap that is actually left and corrects it, so the panel
       keeps its own padding for the meta row and description below. */

    .macm {
        display: flex; flex-direction: column;
        container-type: inline-size;
        position: relative;
        margin: 0 0 14px;
        background: #17171a;
        border-bottom: 1px solid #2a2a2e;
        overflow: hidden;
        color: #eaeaea;
        font-family: 'Barlow Condensed', 'Archivo', system-ui, sans-serif;
    }
    .macm a { text-decoration: none; color: inherit; transition: color .15s; }
    .macm a:hover { color: #ffd75e; }

    /* header — drafts/macm-inner.html */
    .macm-head { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 4.5cqw 5.5cqw 0; }
    .macm-kicker-row { display: flex; align-items: center; justify-content: center; width: 100%; }
    .macm-kicker { font-size: 2.7cqw; font-weight: 700; letter-spacing: 1cqw; text-transform: uppercase; color: #9a9aa0; }
    .macm-event { font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; font-size: 5.6cqw; line-height: 1.12; letter-spacing: .15cqw; color: #fff; text-transform: uppercase; margin-top: 1.6cqw; max-width: 26ch; }
    .macm-pills { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 2cqw; margin-top: 2.4cqw; }
    .macm-pill { display: inline-flex; align-items: center; background: rgba(255,255,255,.05); border: 1px solid #2a2a2e; color: #d9d9d9; font-size: 2.9cqw; font-weight: 600; letter-spacing: .35cqw; text-transform: uppercase; padding: 1cqw 2.6cqw; border-radius: 99px; }
    .macm-pill-gold { background: rgba(255,215,94,.1); border-color: rgba(255,215,94,.35); color: #ffd75e; font-weight: 700; }
    .macm-when-row { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 2cqw; margin-top: 2cqw; font-size: 3cqw; font-weight: 600; letter-spacing: .3cqw; text-transform: uppercase; }
    .macm-when { color: #9a9aa0; }
    .macm-sep { color: #55555c; }
    .macm-venue { color: #9a9aa0; }

    /* fighters, side by side and identical — no mirroring */
    .macm-body { display: grid; grid-template-columns: 1fr 1fr; gap: 4cqw; padding: 4.5cqw 5.5cqw 3cqw; align-items: start; }
    .macm-fighter { display: flex; flex-direction: column; align-items: center; gap: 1.6cqw; text-align: center; min-width: 0; }
    .macm-pic-wrap { display: block; padding: .9cqw; border-radius: 2.6cqw; }
    .macm-pic { display: block; width: 22cqw; height: 26cqw; border-radius: 2cqw; overflow: hidden; background: #1f1f23 center/cover no-repeat; }
    .macm-pic-red  { border: .6cqw solid #e01a2b; }
    .macm-pic-blue { border: .6cqw solid #1e46e0; }
    .macm-corner { display: flex; align-items: center; gap: 1.4cqw; font-size: 2.8cqw; font-weight: 700; letter-spacing: .4cqw; }
    .macm-corner-red  { color: #ff5c6a; }
    .macm-corner-blue { color: #6f8cff; }
    .macm-flag { display: inline-block; flex: none; width: 4.5cqw; height: 3.4cqw; border-radius: 2px; background-size: cover !important; background-position: 50% !important; box-shadow: inset 0 0 0 1px rgba(255,255,255,.18); }
    /* equal height so both club rows line up when one name wraps and one does not */
    /* Two stacked lines, always — the column keeps the given name on top and
       the family name beneath, so both fighters read identically. */
    .macm-name { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 9.7cqw; font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; font-size: 4.6cqw; line-height: 1.05; color: #fff; text-transform: uppercase; }
    .macm-name-l { display: block; overflow-wrap: anywhere; }
    .macm-club { display: flex; align-items: center; justify-content: center; gap: 1.4cqw; font-size: 2.9cqw; font-weight: 600; letter-spacing: .25cqw; text-transform: uppercase; color: #9a9aa0; }
    /* Mirrored, so the two crests sit on the outer edges facing away from each
       other rather than both hugging the centre gutter. */
    .macm-club-blue { flex-direction: row-reverse; }
    .macm-logo { width: 5cqw; height: 5cqw; flex: none; border-radius: 50%; overflow: hidden; background: #1f1f23 center/cover no-repeat; }

    /* the winner's photo: an orbiting gold ring, no badge */
    .macm-win { position: relative; isolation: isolate; }
    .macm-win::before {
        content: ''; position: absolute; inset: 0; border-radius: 2.6cqw; z-index: 0;
        padding: .9cqw; box-sizing: border-box;
        background: conic-gradient(from var(--macm-orbit), rgba(255,215,94,.15) 0 62%, #ffd75e 78%, #fff 85%, #ffd75e 92%, rgba(255,215,94,.15) 100%);
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); mask-composite: exclude;
        animation: macm-orbit 2.2s linear infinite;
        filter: drop-shadow(0 0 1.2cqw rgba(255,215,94,.8));
    }
    .macm-win .macm-pic { position: relative; z-index: 1; }
    @media (prefers-reduced-motion: reduce) { .macm-win::before { animation: none; } }

    /* Score: its own full-width band, ruled off above rather than flanked by
       two short lines. The digits carry it, so nothing else competes. */
    .macm-score { display: flex; justify-content: center; padding: 1.8cqw 5.5cqw 2cqw; border-top: 1px solid #2a2a2e; }
    /* FINAL sits tight on the digits (negative margin, per the draft) */
    .macm-score-mid { display: flex; flex-direction: column; align-items: center; }
    .macm-score-lbl { font-size: 2.8cqw; font-weight: 700; letter-spacing: 1cqw; color: #9a9aa0; margin-bottom: -1.6cqw; }
    .macm-score-row { display: flex; align-items: baseline; gap: 1.8cqw; line-height: 1; font-family: 'Archivo Black', 'Archivo', sans-serif; font-weight: 900; }
    .macm-pts-red  { color: #ff5c6a; }
    .macm-pts-blue { color: #6f8cff; }
    .macm-pts { font-size: 10cqw; line-height: 1; }
    .macm-dash { font-size: 4.5cqw; color: #55555c; }
    .macm-pts-txt { font-size: 4.5cqw; color: #d9d9d9; }

    /* footer */
    .macm-foot { display: flex; align-items: center; justify-content: space-between; gap: 3cqw; padding: 3.5cqw 5.5cqw; border-top: 1px solid #2a2a2e; }
    .macm-ref { display: flex; align-items: center; gap: 2.2cqw; min-width: 0; }
    /* 3:4 portrait, matching every other profile picture on the platform. Was a
       circle, which cropped a portrait to its centre and cut the top of the head.
       Height comes from the text beside it — label top to flag bottom — and the
       width follows from the ratio. */
    .macm-ref-pic { align-self: center; width: 5.62cqw; height: auto; aspect-ratio: 3 / 4; flex: 0 0 auto; border-radius: 1.2cqw; overflow: hidden; background: #1f1f23 center 20% / cover no-repeat; }
    .macm-ref-txt { display: flex; flex-direction: column; justify-content: center; gap: .3cqw; min-width: 0; }
    .macm-ref-lbl { font-size: 2.4cqw; font-weight: 700; letter-spacing: .8cqw; color: #9a9aa0; }
    .macm-ref-name { display: flex; align-items: center; gap: 1.4cqw; font-size: 3.6cqw; font-weight: 700; line-height: 1; color: #d9d9d9; }
    /* Several officials wrap; one on its own looks exactly as it did before. */
    .macm-refs { display: flex; align-items: center; flex-wrap: wrap; gap: 2cqw 4cqw; min-width: 0; }
.macm-more { font-size: 3cqw; font-weight: 600; letter-spacing: .4cqw; text-transform: uppercase; color: #9a9aa0; text-align: right; }
}

@property --macm-orbit { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
@keyframes macm-orbit { to { --macm-orbit: 360deg; } }
</style>



    </div>
</div>

<script>
// Remember which tab the user opened so SPA navigation between videos keeps it active.
window._vdbActiveTab = window._vdbActiveTab || 'vdb-about';

// Scroll back up to the video player on every SPA video-to-video swap.
// On mobile the window is locked (see CLAUDE.md mobile scroll model) and `.yt-main`
// (id="main") is the real scroll container, so we scroll that instead.
window._spaScrollToVideo = window._spaScrollToVideo || function () {
    const main = document.getElementById('main');
    if (window.innerWidth <= 768 && main) {
        main.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

// ── Tab switching ──────────────────────────────────────
function switchVdbTab(panelId, btn) {
    window._vdbActiveTab = panelId;
    document.querySelectorAll('.vdb-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.vdb-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(panelId).classList.add('active');
    // About just became visible — re-measure now that its panel is laid out, since
    // a measurement taken while it was display:none would have read 0.
    if (panelId === 'vdb-about' && window._vdbScheduleOverflowCheck) window._vdbScheduleOverflowCheck();
    if (panelId === 'vdb-insights') {
        const panel      = document.getElementById('vdb-insights');
        const currentUrl = panel && panel.dataset.insightsBase;
        if (currentUrl && currentUrl !== window._insLoadedUrl) loadInsights();
    }
}

// Re-apply the remembered tab after an SPA swap replaces #vdbWrap's contents.
// If the remembered tab doesn't exist on the new video (e.g. Insights when the
// viewer isn't the owner), fall back to About so nothing is left blank.
function _vdbApplyActiveTab() {
    const wrap = document.getElementById('vdbWrap');
    if (!wrap) return;
    let target = window._vdbActiveTab || 'vdb-about';
    let btn = wrap.querySelector('.vdb-tab[data-panel="' + target + '"]');
    if (!btn) { target = 'vdb-about'; btn = wrap.querySelector('.vdb-tab[data-panel="vdb-about"]'); }
    if (!btn) return;
    wrap.querySelectorAll('.vdb-tab').forEach(b => b.classList.remove('active'));
    wrap.querySelectorAll('.vdb-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById(target);
    if (panel) panel.classList.add('active');
    if (target === 'vdb-insights') {
        const ip = document.getElementById('vdb-insights');
        const currentUrl = ip && ip.dataset.insightsBase;
        if (currentUrl && currentUrl !== window._insLoadedUrl && typeof loadInsights === 'function') loadInsights();
    }
}
// Observe #vdbWrap so the active tab is re-applied whenever the SPA layer
// rewrites its innerHTML during a video-to-video transition.
(function _vdbWatchSwaps() {
    const wrap = document.getElementById('vdbWrap');
    if (!wrap || wrap._vdbTabObserver) return;
    const obs = new MutationObserver(() => {
        _vdbApplyActiveTab();
        // The swap replaced the description markup — re-evaluate the "Show more"
        // button once layout/fonts/images settle. Central here so every SPA swap
        // path is covered, not just the ones that remember to call it themselves.
        if (window._vdbScheduleOverflowCheck) window._vdbScheduleOverflowCheck();
    });
    obs.observe(wrap, { childList: true, subtree: false });
    wrap._vdbTabObserver = obs;
})();
function toggleVdbDesc(btn) {
    const d = document.getElementById('vdbDescShort');
    if (!d) return;
    const expanded = d.classList.toggle('vdb-expanded');
    btn.classList.toggle('expanded', expanded);
    const label = btn.querySelector('span');
    if (label) label.textContent = expanded ? 'Show less' : 'Show more';
}
// Reveal "Show more" only when the description overflows the clamp. Compare the
// natural content height to the clamp limit (130px) rather than clientHeight,
// which is unreliable right after a content swap.
function _vdbCheckOverflow() {
    const d = document.getElementById('vdbDescShort'), b = document.getElementById('vdbShowMore');
    if (!d || !b) return;
    if (d.classList.contains('vdb-expanded')) { b.style.display = 'flex'; return; }
    // If the About panel isn't laid out yet (e.g. Insights tab active, or mid-swap),
    // scrollHeight reads 0 — don't hide the button on a bogus zero measurement.
    if (d.scrollHeight === 0) return;
    b.style.display = (d.scrollHeight > 138) ? 'flex' : 'none';
}
// A single measurement right after an SPA swap is unreliable: web-fonts, images
// inside the description, and panel layout can all change the height a beat later,
// each of which flips whether the clamp overflows. Re-check at every settling point.
window._vdbScheduleOverflowCheck = window._vdbScheduleOverflowCheck || function () {
    requestAnimationFrame(_vdbCheckOverflow);
    setTimeout(_vdbCheckOverflow, 120);
    setTimeout(_vdbCheckOverflow, 400);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(_vdbCheckOverflow);
    const d = document.getElementById('vdbDescShort');
    if (d) d.querySelectorAll('img').forEach(function (img) {
        if (!img.complete) img.addEventListener('load', _vdbCheckOverflow, { once: true });
    });
};
document.addEventListener('DOMContentLoaded', window._vdbScheduleOverflowCheck);
window.addEventListener('load', window._vdbScheduleOverflowCheck);
</script>

            
            
            
            <script>
                // Match Highlights Toggle with localStorage persistence
                // Extracted into a globally-callable initializer so SPA navigation
                // (recSwapContent / plSwapContent) can re-bind the click handler
                // against the FRESH .events-sidebar node after the sidebar is
                // swapped in from the target page.
                window.initMatchHighlightsToggle = function () {
                    const toggleBtn = document.getElementById('matchHighlightsToggle');
                    const sidebar = document.querySelector('.events-sidebar');
                    // Prefer the freshest videoId in case SPA nav updated it
                    const videoId = window.videoId;
                    // The flag lives on the SIDEBAR, which is what this sets up.
                    // A fresh node after an SPA swap carries none, so it rebinds;
                    // the same node twice does nothing. The toggle button is
                    // optional — this page does not render one.
                    if (sidebar && sidebar._hlBound) {
                        return; // Already set up for this exact sidebar node
                    }

                    if (sidebar) {
                        // Wipe any prior click listener bound to a stale sidebar
                        if (toggleBtn && toggleBtn._hlHandler) toggleBtn.removeEventListener('click', toggleBtn._hlHandler);
                        sidebar._hlBound = true;
                        const backdrop = document.getElementById('hlSheetBackdrop');
                        const grab = sidebar.querySelector('.hl-sheet-grab');
                        const mainScroll = document.getElementById('main');
                        const player = document.getElementById('ytpWrap');
                        const isMobile = () => window.matchMedia('(max-width: 991px)').matches;

                        // Pin the sheet's top edge to the bottom of the player,
                        // or — if a capture strip is open BELOW the player — to
                        // the bottom of that strip, so nothing overlaps the tools.
                        const positionSheet = () => {
                            if (!isMobile()) { sidebar.style.removeProperty('--hl-sheet-top'); return; }
                            const wrap = document.getElementById('ytpWrap');
                            if (!wrap) return;
                            let bottom = wrap.offsetTop + wrap.offsetHeight;
                            // A capture strip in "below" mode is a sibling of the
                            // player wrap; when visible, push the sheet under it.
                            const strip = document.querySelector('.point-capture-strip.pcs-below:not([hidden])');
                            if (strip) {
                                bottom = Math.max(bottom, strip.offsetTop + strip.offsetHeight);
                            }
                            if (bottom > 0) sidebar.style.setProperty('--hl-sheet-top', bottom + 'px');
                        };
                        // Expose so the point / review capture flows can re-position
                        // the sheet the moment their strip opens or closes.
                        window.positionHighlightsSheet = positionSheet;

                        /*
                         * Swap the page for the review view (mobile only).
                         *
                         * The player is MOVED rather than duplicated — a second
                         * <video> would mean two players, two audio tracks and a
                         * second set of listeners. A comment node marks where it
                         * came from so it goes back exactly there on close, the
                         * same approach as the fullscreen drawer below.
                         */
                        const reviewView = document.getElementById('matchReviewView');
                        const videoSlot  = document.getElementById('mrvVideoSlot');
                        let playerHome = null;
                        /*
                         * In native fullscreen the player must NOT be moved.
                         * Re-parenting the element that IS the fullscreen element
                         * makes the browser exit fullscreen — which is why tapping
                         * Highlights in landscape threw the user back to the
                         * portrait page. Fullscreen has its own drawer (the pane
                         * relocated inside the player), so the review view simply
                         * stands down while it is active.
                         */
                        const inFullscreen = () =>
                            !!(document.fullscreenElement || document.webkitFullscreenElement);

                        const setReview = (on) => {
                            if (!reviewView || !videoSlot || !player) return;
                            if (on && isMobile() && !inFullscreen()) {
                                if (!playerHome) {
                                    playerHome = document.createComment('ytp-home');
                                    player.parentNode.insertBefore(playerHome, player);
                                }
                                if (player.parentNode !== videoSlot) videoSlot.appendChild(player);
                                document.body.classList.add('mrv-on');
                            } else {
                                if (playerHome && playerHome.parentNode) {
                                    playerHome.parentNode.insertBefore(player, playerHome);
                                    playerHome.remove();
                                    playerHome = null;
                                }
                                document.body.classList.remove('mrv-on');
                            }
                        };

                        const setOpen = (open) => {
                            setReview(open);
                            sidebar.classList.toggle('show', open);
                            toggleBtn?.classList.toggle('expanded', open);
                            // Fullscreen drawer state — harmless when not fullscreen (CSS is gated).
                            if (player) player.classList.toggle('hl-drawer-open', open);
                            if (backdrop) backdrop.classList.toggle('show', open && isMobile());
                            // Hide the top bar (mobile only) to give the player more room.
                            document.body.classList.toggle('hl-sheet-open', open && isMobile());
                            if (open) {
                                localStorage.setItem(`highlights_${videoId}`, 'open');
                                // Mobile: the pane is a bottom sheet — bring the player back
                                // to the top so the user can watch while reading highlights.
                                if (isMobile()) {
                                    if (mainScroll) mainScroll.scrollTo({ top: 0, behavior: 'smooth' });
                                    positionSheet();
                                }
                            } else {
                                localStorage.removeItem(`highlights_${videoId}`);
                            }
                        };

                        // Restore last state on DESKTOP only — a sheet auto-popping open on
                        // every mobile page load would be jarring.
                        setOpen(true);

                        if (toggleBtn) {
                            toggleBtn._hlHandler = () => setOpen(!sidebar.classList.contains('show'));
                            toggleBtn.addEventListener('click', toggleBtn._hlHandler);
                        }
                        // The highlights sheet closes ONLY via its own toggle button.
                        // Removing the backdrop / grab close handlers so tapping the
                        // video (or anywhere else) never dismisses it.

                        // Keep the scrim + header state honest if the viewport crosses
                        // the mobile boundary while the pane is open.
                        window.addEventListener('resize', () => {
                            /*
                             * Entering fullscreen resizes the viewport, so this
                             * fires mid-transition. setReview() would then move
                             * #ytpWrap — and re-parenting the element that just
                             * became the fullscreen element makes the browser
                             * exit immediately, which is why tapping fullscreen
                             * from the portrait Highlights view only shook the
                             * video in place. Fullscreen owns the player while
                             * it is on; nothing here may touch it.
                             */
                            if (inFullscreen()) return;
                            const openMobile = sidebar.classList.contains('show') && isMobile();
                            // Crossing to desktop while open: give the player back.
                            setReview(openMobile);
                            if (backdrop) backdrop.classList.toggle('show', openMobile);
                            document.body.classList.toggle('hl-sheet-open', openMobile);
                            positionSheet();
                        });

                        // Fullscreen: native fullscreen paints only #ytpWrap and its
                        // descendants, so the pane must live INSIDE the player to be
                        // visible. Relocate it there on enter, move it home on exit; CSS
                        // reshapes it into the transparent right-side drawer.
                        if (player) {
                            let fsHome = null;
                            // What the page's drawer state was before fullscreen, so
                            // exiting hands it back exactly as the user left it.
                            let fsPrevOpen = null;
                            const onFsChange = () => {
                                const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                                const inPlayerFs = fsEl === player;
                                /*
                                 * The player is deliberately NOT moved on entering
                                 * fullscreen. It may well be sitting inside the review
                                 * view's video slot, and that is fine: native fullscreen
                                 * paints the fullscreen element and its descendants, so
                                 * whatever surrounds it is irrelevant. Moving it here
                                 * re-parented the element that had just gone fullscreen,
                                 * which made the browser exit immediately — the video
                                 * appeared to bounce straight back to the portrait page.
                                 * On exit it is already in the right place.
                                 */
                                if (inPlayerFs && !fsHome) {
                                    fsHome = document.createComment('events-sidebar-home');
                                    sidebar.parentNode.insertBefore(fsHome, sidebar);
                                    player.appendChild(sidebar);
                                    /*
                                     * Landscape fullscreen IS the watch-and-read view:
                                     * the highlights drawer comes with it. Only the
                                     * drawer's own classes are set here — setOpen()
                                     * would drag in the portrait sheet's bookkeeping
                                     * (backdrop, body class, localStorage), none of
                                     * which belongs to a fullscreen drawer.
                                     */
                                    fsPrevOpen = sidebar.classList.contains('show');
                                    sidebar.classList.add('show');
                                    toggleBtn?.classList.add('expanded');
                                    player.classList.add('hl-drawer-open');
                                } else if (!inPlayerFs && fsHome) {
                                    fsHome.parentNode.insertBefore(sidebar, fsHome);
                                    fsHome.parentNode.removeChild(fsHome);
                                    fsHome = null;
                                    /*
                                     * Give the page back the state it had before —
                                     * but honour a close made INSIDE fullscreen:
                                     * open after only if it is still open now AND
                                     * was already open before.
                                     */
                                    if (fsPrevOpen !== null) {
                                        setOpen(sidebar.classList.contains('show') && fsPrevOpen);
                                        fsPrevOpen = null;
                                    }
                                }
                            };
                            document.addEventListener('fullscreenchange', onFsChange);
                            document.addEventListener('webkitfullscreenchange', onFsChange);
                        }
                    }
                };
                // First run on initial page load
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', window.initMatchHighlightsToggle);
                } else {
                    window.initMatchHighlightsToggle();
                }
            </script>


            <div id="ytcHome" style="display:none">
            <div class="ytc" id="ytcSection">

    
    <div class="ytc-header">
        <span class="ytc-count" id="ytcCount">{{ trans_choice('events.bout_video_comments_count', $commentTotal = collect($comments)->sum(fn ($c) => 1 + count($c['replies'] ?? [])), ['count' => $commentTotal]) }}</span>
        <div class="ytc-sort-wrap" id="ytcSortWrap">
            <button class="ytc-sort-btn" id="ytcSortBtn">
                <svg viewBox="0 0 24 24"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
                Sort by
            </button>
            <div class="ytc-sort-menu" id="ytcSortMenu">
                <div class="ytc-sort-opt active" data-sort="newest">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Top comments
                </div>
                <div class="ytc-sort-opt" data-sort="top">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" opacity="0"/></svg>
                    Newest first
                </div>
            </div>
        </div>
    </div>

    
    <div class="ytc-new-form">
        <span class="ytc-avatar-link">
            <span class="ytc-avatar" style="background: {{ \App\Models\BoutComment::tint(auth()->user()->full_name ?: auth()->user()->name) }}; display:grid; place-items:center; font-weight:700; font-size:13px; color:#fff;">{{ \App\Models\BoutComment::initials(auth()->user()->full_name ?: auth()->user()->name) }}</span>
        </span>
        <div class="ytc-input-wrap">
            <textarea class="ytc-textarea" id="ytcTextarea" rows="1" maxlength="1000"
                      placeholder="{{ __('events.bout_video_comment_placeholder') }}"></textarea>
            <div class="ytc-form-actions" id="ytcFormActions">
                <button class="ytc-btn ytc-btn-cancel" id="ytcCancelBtn" type="button">{{ __('shared.cancel') }}</button>
                <button class="ytc-btn ytc-btn-submit" id="ytcSubmitBtn" type="button">{{ __('events.bout_video_comment_send') }}</button>
            </div>
        </div>
    </div>

    <div id="ytcList">
        @forelse ($comments as $c)
            @include('personal.mobile.partials.bout-comment', ['c' => $c, 'isReply' => false])
        @empty
            <div class="ytc-empty" id="ytcEmpty">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                <p>{{ __('events.bout_video_no_comments') }}</p>
            </div>
        @endforelse
    </div>

</div>


</div>

<div class="ytc-modal" id="ytcDeleteModal">
    <div class="ytc-modal-box">
        <h4 class="ytc-modal-title">Delete comment?</h4>
        <p class="ytc-modal-msg">This will permanently delete your comment.</p>
        <div class="ytc-modal-actions">
            <button class="ytc-btn ytc-btn-cancel" id="ytcModalCancel">Cancel</button>
            <button class="ytc-btn ytc-btn-danger" id="ytcModalConfirm">Delete</button>
        </div>
    </div>
</div>

<style>
/* ══════════════════════════════════════════════════
   YTC — YouTube-style comments
══════════════════════════════════════════════════ */
.ytc {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
    font-family: Roboto, Arial, sans-serif;
}

/* ── Header ── */
.ytc-header {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
    position: relative;
}
.ytc-count {
    font-size: 16px;
    font-weight: 400;
    color: var(--text-primary);
}
.ytc-sort-wrap { position: relative; }
.ytc-sort-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 18px;
    transition: background .15s;
}
.ytc-sort-btn:hover { background: rgba(255,255,255,.1); }
.ytc-sort-btn svg { width: 18px; height: 18px; fill: currentColor; }
.ytc-sort-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    min-width: 180px;
    z-index: 200;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.4);
}
.ytc-sort-menu.open { display: block; }
.ytc-sort-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    font-size: 14px;
    color: var(--text-primary);
    cursor: pointer;
    transition: background .15s;
}
.ytc-sort-opt:hover { background: rgba(255,255,255,.08); }
.ytc-sort-opt svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }

/* ── Login prompt ── */
.ytc-login-prompt {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    font-size: 14px;
    color: var(--text-secondary);
    padding: 16px 0;
    border-bottom: 1px solid var(--border-color);
}
.ytc-login-prompt svg { width: 24px; height: 24px; fill: var(--text-secondary); }
.ytc-login-prompt a { color: #3ea6ff; text-decoration: none; font-weight: 500; }

/* ── Avatar ── */
.ytc-avatar-link { flex-shrink: 0; display: block; }
.ytc-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.ytc-avatar-sm { width: 24px; height: 24px; }

/* ── New comment form ── */
.ytc-new-form {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 32px;
}
.ytc-input-wrap { flex: 1; }
.ytc-textarea {
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    padding: 6px 0;
    resize: none;
    outline: none;
    transition: border-color .2s;
    line-height: 1.5;
    overflow: hidden;
    min-height: 32px;
    box-sizing: border-box;
    display: block;
}
.ytc-textarea:focus { border-bottom-color: var(--text-primary); }
.ytc-form-actions {
    display: none;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}
.ytc-form-actions.visible { display: flex; }

/* Reply form shown state */
.ytc-reply-form {
    display: none;
    gap: 12px;
    align-items: flex-start;
    margin-top: 16px;
}
.ytc-reply-form.open { display: flex; }
.ytc-reply-form .ytc-form-actions { display: flex; }

/* ── Buttons ── */
.ytc-btn {
    border: none;
    border-radius: 18px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s;
    white-space: nowrap;
    font-family: inherit;
}
.ytc-btn-cancel {
    background: transparent;
    color: var(--text-primary);
}
.ytc-btn-cancel:hover { background: rgba(255,255,255,.1); }
.ytc-btn-submit {
    background: #3ea6ff;
    color: #0d0d0d;
}
.ytc-btn-submit:hover { background: #65b8ff; }
.ytc-btn-submit:disabled { opacity: .5; cursor: not-allowed; }
.ytc-btn-danger {
    background: #cc0000;
    color: #fff;
}
.ytc-btn-danger:hover { background: #aa0000; }

/* ── Comment item ── */
.ytc-comment {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
}
.ytc-reply { margin-left: 56px; margin-bottom: 16px; }
.ytc-body-wrap { flex: 1; min-width: 0; }

/* ── Meta row ── */
.ytc-meta {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}
.ytc-author {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
    text-decoration: none;
}
.ytc-author:hover { text-decoration: underline; }
.ytc-time {
    font-size: 12px;
    color: var(--text-secondary);
}

/* ── Comment text ── */
.ytc-text {
    font-size: 14px;
    line-height: 1.6;
    color: var(--text-primary);
    word-break: break-word;
    white-space: pre-wrap;
}

/* ── Actions ── */
.ytc-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
}
.ytc-like-btn, .ytc-dislike-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 13px;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 18px;
    transition: background .15s, color .15s;
}
.ytc-like-btn:hover, .ytc-dislike-btn:hover {
    background: rgba(255,255,255,.1);
    color: var(--text-primary);
}
.ytc-like-btn.liked { color: #3ea6ff; }
.ytc-dislike-btn.disliked { color: #aaa; }
.ytc-like-btn svg, .ytc-dislike-btn svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
}
.ytc-like-count:empty { display: none; }
.ytc-reply-btn {
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 18px;
    margin-left: 4px;
    transition: background .15s;
    font-family: inherit;
}
.ytc-reply-btn:hover { background: rgba(255,255,255,.1); }

/* ── Three-dot menu ── */
.ytc-more-wrap {
    position: relative;
    margin-left: auto;
}
.ytc-more-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background .15s;
    opacity: 0;
    transition: opacity .15s, background .15s;
}
.ytc-comment:hover .ytc-more-btn { opacity: 1; }
.ytc-more-btn:focus, .ytc-more-btn[aria-expanded="true"] { opacity: 1; background: rgba(255,255,255,.1); }
.ytc-more-btn:hover { background: rgba(255,255,255,.1); opacity: 1; }
.ytc-more-btn svg { width: 20px; height: 20px; fill: currentColor; }
.ytc-more-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    min-width: 160px;
    z-index: 200;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.4);
}
.ytc-more-menu.open { display: block; }
.ytc-more-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 16px;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 14px;
    cursor: pointer;
    text-align: left;
    transition: background .15s;
    font-family: inherit;
}
.ytc-more-item:hover { background: rgba(255,255,255,.08); }
.ytc-more-item svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
.ytc-more-delete { color: #f28b82; }
.ytc-more-delete svg { fill: #f28b82; }

/* ── Edit form ── */
.ytc-edit-form { margin-top: 8px; }
.ytc-edit-textarea {
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 2px solid #3ea6ff;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    padding: 6px 0;
    resize: none;
    outline: none;
    line-height: 1.5;
    box-sizing: border-box;
}
.ytc-edit-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
}
.ytc-edit-hint {
    font-size: 12px;
    color: var(--text-secondary);
    margin-right: auto;
}

/* ── Replies section ── */
.ytc-replies-section { margin-top: 12px; }
.ytc-replies-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    color: #3ea6ff;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 18px;
    transition: background .15s;
    font-family: inherit;
}
.ytc-replies-toggle:hover { background: rgba(62,166,255,.1); }
.ytc-chevron {
    width: 18px;
    height: 18px;
    fill: #3ea6ff;
    transition: transform .2s;
}
.ytc-replies-toggle[data-open="1"] .ytc-chevron { transform: rotate(180deg); }
.ytc-replies-list { padding-top: 8px; }

/* ── Timestamp badge ── */
._comment-time-badge {
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 14px;
    background: rgba(62,166,255,.15);
    border: 1px solid rgba(62,166,255,.3);
    color: #3ea6ff;
    font-weight: 500;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    margin: 0 2px;
    transition: background .15s;
    white-space: nowrap;
}
._comment-time-badge:hover { background: rgba(62,166,255,.25); }
._comment-time-badge i, ._comment-time-badge svg { font-size: 11px; }

/* @mention styling */
.ytc-mention { color: #3ea6ff; font-weight: 500; cursor: pointer; }

/* ── Empty state ── */
.ytc-empty {
    text-align: center;
    padding: 48px 24px;
    color: var(--text-secondary);
}
.ytc-empty svg { width: 48px; height: 48px; fill: var(--text-secondary); margin-bottom: 12px; display: block; margin-inline: auto; }
.ytc-empty p { font-size: 14px; }

/* ── Delete modal ── */
.ytc-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.ytc-modal.open { display: flex; }
.ytc-modal-box {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 28px 32px;
    width: 90%;
    max-width: 380px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
.ytc-modal-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 8px;
    color: var(--text-primary);
}
.ytc-modal-msg {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0 0 24px;
    line-height: 1.5;
}
.ytc-modal-actions { display: flex; justify-content: flex-end; gap: 8px; }

/* ── Mobile ── */
@media (max-width: 576px) {
    .ytc-comment { gap: 10px; }
    .ytc-reply { margin-left: 34px; }
    .ytc-avatar { width: 32px; height: 32px; }
    .ytc-more-btn { opacity: 1; }
}
</style>

<script>
(function () {
'use strict';

const YTC = {
    videoId:   window.TOB && window.TOB.comments,
    csrf:      window.TOB ? window.TOB.csrf : '',
    // Truthy for anyone who reached this page: it is not reachable signed out.
    userId:    @json((int) auth()->id()),
    deleteId:  null,
    toastTimer: null,
};

// ── Helpers ──────────────────────────────────────────
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function fmt(s) { return s ?? ''; }
function avatarTile(c, isReply) {
    const bg = (c && c.bg) || '#374151';
    const initials = esc((c && c.initials) || '?');
    return '<span class="ytc-avatar-link"><span class="ytc-avatar' + (isReply ? ' ytc-avatar-sm' : '') +
        '" style="background:' + esc(bg) + ';display:grid;place-items:center;font-weight:700;font-size:13px;color:#fff;">' +
        initials + '</span></span>';
}

// ── Toast (use global if available, else local) ───────
function toast(msg, type) {
    if (window.showToast) { window.showToast(msg, type || 'info'); return; }
    console.log('[YTC]', msg);
}

// ── Auto-resize textareas ────────────────────────────
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}
document.querySelectorAll('.ytc-textarea, .ytc-edit-textarea').forEach(ta => {
    ta.addEventListener('input', () => autoResize(ta));
});

// ── New comment form focus expand ────────────────────
const newTextarea = document.getElementById('ytcTextarea');
const formActions = document.getElementById('ytcFormActions');
const cancelBtn   = document.getElementById('ytcCancelBtn');
const submitBtn   = document.getElementById('ytcSubmitBtn');

if (newTextarea) {
    newTextarea.addEventListener('focus', () => formActions.classList.add('visible'));
}
if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
        newTextarea.value = '';
        autoResize(newTextarea);
        formActions.classList.remove('visible');
        newTextarea.blur();
    });
}
if (submitBtn) {
    submitBtn.addEventListener('click', () => postComment());
}

// ── Sort ─────────────────────────────────────────────
const sortBtn  = document.getElementById('ytcSortBtn');
const sortMenu = document.getElementById('ytcSortMenu');
if (sortBtn) {
    sortBtn.addEventListener('click', e => {
        e.stopPropagation();
        sortMenu.classList.toggle('open');
    });
    document.querySelectorAll('.ytc-sort-opt').forEach(opt => {
        opt.addEventListener('click', () => {
            document.querySelectorAll('.ytc-sort-opt').forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            sortMenu.classList.remove('open');
            sortComments(opt.dataset.sort);
        });
    });
}
document.addEventListener('click', e => {
    if (!document.getElementById('ytcSortWrap')?.contains(e.target))
        sortMenu?.classList.remove('open');
});

function sortComments(dir) {
    const list = document.getElementById('ytcList');
    if (!list) return;
    const items = Array.from(list.querySelectorAll(':scope > .ytc-comment'));
    if (dir === 'newest') {
        items.sort((a, b) => {
            const ta = a.querySelector('.ytc-time')?.textContent || '';
            const tb = b.querySelector('.ytc-time')?.textContent || '';
            return ta.localeCompare(tb);
        });
    }
    items.forEach(el => list.appendChild(el));
}

// ── Reply toggle ─────────────────────────────────────
window.ytcToggleReplies = function(btn, commentId) {
    const list = document.getElementById('replies-' + commentId);
    if (!list) return;
    const open = btn.dataset.open === '1';
    if (open) {
        list.style.display = 'none';
        btn.dataset.open = '0';
    } else {
        list.style.display = 'block';
        btn.dataset.open = '1';
    }
};

// ── Three-dot menus ───────────────────────────────────
document.addEventListener('click', e => {
    // Toggle own menu
    const moreBtn = e.target.closest('.ytc-more-btn');
    if (moreBtn) {
        e.stopPropagation();
        const menu = moreBtn.nextElementSibling;
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.ytc-more-menu.open').forEach(m => m.classList.remove('open'));
        if (!isOpen) menu.classList.add('open');
        return;
    }
    // Close all
    if (!e.target.closest('.ytc-more-wrap')) {
        document.querySelectorAll('.ytc-more-menu.open').forEach(m => m.classList.remove('open'));
    }
});

// ── Delete modal ─────────────────────────────────────
const modal        = document.getElementById('ytcDeleteModal');
const modalCancel  = document.getElementById('ytcModalCancel');
const modalConfirm = document.getElementById('ytcModalConfirm');

function openDeleteModal(id) {
    YTC.deleteId = id;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    modal.classList.remove('open');
    YTC.deleteId = null;
    document.body.style.overflow = '';
}
if (modalCancel) modalCancel.addEventListener('click', closeDeleteModal);
modal?.addEventListener('click', e => { if (e.target === modal) closeDeleteModal(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeDeleteModal();
});

if (modalConfirm) {
    modalConfirm.addEventListener('click', async () => {
        if (!YTC.deleteId) return;
        const id = YTC.deleteId;
        modalConfirm.disabled = true;
        modalConfirm.textContent = 'Deleting…';
        try {
            const r = await fetch(window.TOB.comment(id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const d = await r.json();
            if (!r.ok && !d.success && !d.deleted) throw new Error(d.error || 'Failed');
            const el = document.getElementById('comment-' + id);
            if (el) {
                el.style.transition = 'opacity .2s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 200);
            }
            updateCount(-1);
            toast('Comment deleted', 'success');
            closeDeleteModal();
        } catch (err) {
            toast(err.message || 'Failed to delete', 'error');
        } finally {
            modalConfirm.disabled = false;
            modalConfirm.textContent = 'Delete';
        }
    });
}

// ── Event delegation ─────────────────────────────────
document.getElementById('ytcList')?.addEventListener('click', e => {
    // Delete trigger
    const dt = e.target.closest('._comment-delete-trigger');
    if (dt) { e.stopPropagation(); openDeleteModal(dt.dataset.commentId); return; }

    // Reply trigger
    const rt = e.target.closest('._comment-reply-trigger');
    if (rt) { e.stopPropagation(); toggleReplyForm(rt.dataset.commentId); return; }

    // Edit trigger
    const et = e.target.closest('._comment-edit-trigger');
    if (et) { e.stopPropagation(); startEdit(et.dataset.commentId); return; }

    // Cancel edit
    const ce = e.target.closest('._comment-cancel-edit-trigger');
    if (ce) { e.stopPropagation(); cancelEdit(ce.dataset.commentId); return; }

    // Save edit
    const se = e.target.closest('._comment-save-edit-trigger');
    if (se) { e.stopPropagation(); saveEdit(se.dataset.commentId); return; }

    // Cancel reply
    const cr = e.target.closest('._comment-cancel-reply-trigger');
    if (cr) { e.stopPropagation(); toggleReplyForm(cr.dataset.commentId); return; }

    // Submit reply
    const sr = e.target.closest('._comment-submit-reply-trigger');
    if (sr) { e.stopPropagation(); postReply(sr.dataset.videoId, sr.dataset.parentId); return; }

    // Like btn
    const lb = e.target.closest('.ytc-like-btn');
    if (lb) { e.stopPropagation(); toggleLike(lb); return; }

    // Dislike btn
    const db = e.target.closest('.ytc-dislike-btn');
    if (db) { e.stopPropagation(); toggleDislike(db); return; }
});

// ── Reply form ────────────────────────────────────────
function toggleReplyForm(commentId) {
    const form = document.getElementById('replyForm' + commentId);
    if (!form) return;
    const isOpen = form.classList.contains('open');
    document.querySelectorAll('.ytc-reply-form.open').forEach(f => f.classList.remove('open'));
    if (!isOpen) {
        form.classList.add('open');
        const ta = document.getElementById('replyBody' + commentId);
        if (ta) { ta.focus(); autoResize(ta); }
    }
}

// ── Edit ──────────────────────────────────────────────
function startEdit(commentId) {
    const body = document.querySelector('#comment-' + commentId + ' .ytc-text');
    const wrap = document.getElementById('commentEditWrap' + commentId);
    const input = document.getElementById('commentEditInput' + commentId);
    if (!body || !wrap || !input) return;
    body.style.display = 'none';
    wrap.style.display = 'block';
    autoResize(input);
    input.focus();
    document.querySelectorAll('.ytc-more-menu.open').forEach(m => m.classList.remove('open'));
}
function cancelEdit(commentId) {
    const body = document.querySelector('#comment-' + commentId + ' .ytc-text');
    const wrap = document.getElementById('commentEditWrap' + commentId);
    if (body) body.style.display = '';
    if (wrap) wrap.style.display = 'none';
}
async function saveEdit(commentId) {
    const input = document.getElementById('commentEditInput' + commentId);
    const body  = document.querySelector('#comment-' + commentId + ' .ytc-text');
    const wrap  = document.getElementById('commentEditWrap' + commentId);
    if (!input || !body || !wrap) return;
    const text = input.value.trim();
    if (!text) { toast('Comment cannot be empty', 'error'); return; }
    const btn = wrap.querySelector('._comment-save-edit-trigger');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    try {
        const r = await fetch('/comments/' + commentId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf },
            body: JSON.stringify({ body: text })
        });
        const d = await r.json();
        if (r.ok && (d.success || d.body)) {
            body.textContent = d.body || text;
            body.dataset._commentEnhanced = '0';
            enhanceBody(body);
            wrap.style.display = 'none';
            body.style.display = '';
            toast('Comment updated', 'success');
        } else throw new Error(d.error || 'Failed');
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    }
}

// ── Post comment ──────────────────────────────────────
async function postComment() {
    const ta = document.getElementById('ytcTextarea');
    if (!ta) return;
    const text = ta.value.trim();
    if (!text) { toast('Write something first', 'error'); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Posting…';
    try {
        const r = await fetch(window.TOB.comments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body: text })
        });
        if (!(r.headers.get('content-type') || '').includes('application/json')) {
            if (r.status === 419) throw new Error('Session expired — please refresh the page');
            if (r.status === 401) throw new Error('Please sign in to comment');
            throw new Error('Something went wrong — please refresh and try again');
        }
        const d = await r.json();
        if (r.ok && d.success) {
            ta.value = '';
            autoResize(ta);
            formActions?.classList.remove('visible');
            ta.blur();
            prependComment(d.comment);
            updateCount(1);
            toast('Comment posted', 'success');
        } else throw new Error(d.error || 'Failed');
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Comment';
    }
}

// ── Post reply ────────────────────────────────────────
async function postReply(vid, parentId) {
    const input = document.getElementById('replyBody' + parentId);
    if (!input) return;
    const text = input.value.trim();
    if (!text) { toast('Write something first', 'error'); return; }
    const btn = input.closest('.ytc-reply-form')?.querySelector('._comment-submit-reply-trigger');
    if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }
    try {
        const r = await fetch(window.TOB.comments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body: text, parent: parentId })
        });
        if (!(r.headers.get('content-type') || '').includes('application/json')) {
            if (r.status === 419) throw new Error('Session expired — please refresh the page');
            if (r.status === 401) throw new Error('Please sign in to comment');
            throw new Error('Something went wrong — please refresh and try again');
        }
        const d = await r.json();
        if (r.ok && d.success) {
            input.value = '';
            toggleReplyForm(parentId);
            appendReply(parentId, d.comment);
            updateCount(1);
            toast('Reply posted', 'success');
        } else throw new Error(d.error || 'Failed');
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Reply'; }
    }
}

// ── Render new comment (optimistic prepend) ───────────
function prependComment(c) {
    const list  = document.getElementById('ytcList');
    const empty = document.getElementById('ytcEmpty');
    if (empty) empty.remove();
    const el = buildCommentEl(c, false);
    list.insertBefore(el, list.firstChild);
    enhanceBody(el.querySelector('.ytc-text'));
    el.querySelectorAll('.ytc-textarea, .ytc-edit-textarea').forEach(ta =>
        ta.addEventListener('input', () => autoResize(ta)));
}

// ── Append a new reply under a parent ────────────────
function appendReply(parentId, c) {
    const parent  = document.getElementById('comment-' + parentId);
    if (!parent) return;
    let section = parent.querySelector('.ytc-replies-section');
    if (!section) {
        section = document.createElement('div');
        section.className = 'ytc-replies-section';
        section.innerHTML = `<button class="ytc-replies-toggle" data-open="1"
            onclick="ytcToggleReplies(this, ${parentId})">
            <svg class="ytc-chevron" viewBox="0 0 24 24"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
            <span class="ytc-reply-count">1 reply</span></button>
            <div class="ytc-replies-list" id="replies-${parentId}" style="display:block;padding-top:8px;"></div>`;
        parent.querySelector('.ytc-body-wrap').appendChild(section);
    }
    const list  = document.getElementById('replies-' + parentId);
    const toggl = section.querySelector('.ytc-replies-toggle');
    const el    = buildCommentEl(c, true);
    list.appendChild(el);
    if (list.style.display === 'none') {
        list.style.display = 'block';
        if (toggl) toggl.dataset.open = '1';
    }
    // Update count label
    const existing = list.querySelectorAll('.ytc-comment').length;
    if (toggl) {
        const label = toggl.querySelector('.ytc-reply-count') || toggl;
        label.textContent = existing + (existing === 1 ? ' reply' : ' replies');
    }
    enhanceBody(el.querySelector('.ytc-text'));
    el.querySelectorAll('.ytc-textarea, .ytc-edit-textarea').forEach(ta =>
        ta.addEventListener('input', () => autoResize(ta)));
}

function buildCommentEl(c, isReply) {
    const isOwn  = !!c.mine;
    const name   = esc(c.name || 'User');
    const time   = esc(c.when || '');
    const body   = esc(c.text || '');
    const id     = c.key;

    const moreMenu = isOwn ? `
        <div class="ytc-more-wrap">
            <button class="ytc-more-btn">
                <svg viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </button>
            <div class="ytc-more-menu">
                <button class="ytc-more-item ytc-more-delete _comment-delete-trigger" data-comment-id="${id}">
                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    Delete
                </button>
            </div>
        </div>` : '';

    const editForm = '';

    const replyForm = YTC.userId ? `
        <div class="ytc-reply-form" id="replyForm${id}" style="display:none">
            <img src="" class="ytc-avatar ytc-avatar-sm" alt="">
            <div class="ytc-input-wrap">
                <textarea class="ytc-textarea ytc-reply-textarea" id="replyBody${id}" placeholder="Add a reply..." rows="1"></textarea>
                <div class="ytc-form-actions">
                    <button class="ytc-btn ytc-btn-cancel _comment-cancel-reply-trigger" data-comment-id="${id}">Cancel</button>
                    <button class="ytc-btn ytc-btn-submit _comment-submit-reply-trigger" data-video-id="${YTC.videoId}" data-parent-id="${id}">Reply</button>
                </div>
            </div>
        </div>` : '';

    const replyBtn = YTC.userId ? `<button class="ytc-reply-btn _comment-reply-trigger" data-comment-id="${id}">Reply</button>` : '';

    const div = document.createElement('div');
    div.className = 'ytc-comment' + (isReply ? ' ytc-reply' : '');
    div.id = 'comment-' + id;
    div.innerHTML = `
        ${avatarTile(c, isReply)}
        <div class="ytc-body-wrap">
            <div class="ytc-meta">
                <span class="ytc-author">${name}</span>
                <span class="ytc-time">${time}</span>
            </div>
            <div class="ytc-text _comment-body" data-_comment-enhanced="0">${body}</div>
            ${editForm}
            <div class="ytc-actions">
                <button class="ytc-like-btn" data-id="${id}" title="Like">
                    <svg viewBox="0 0 24 24"><path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>
                    <span class="ytc-like-count" data-id="${id}"></span>
                </button>
                <button class="ytc-dislike-btn" data-id="${id}" title="Dislike">
                    <svg viewBox="0 0 24 24"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L10.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>
                </button>
                ${replyBtn}
                ${moreMenu}
            </div>
            ${replyForm}
        </div>`;
    return div;
}

// ── Count ─────────────────────────────────────────────
function updateCount(delta) {
    const el = document.getElementById('ytcCount');
    if (!el) return;
    const n = parseInt(el.textContent) || 0;
    const v = Math.max(0, n + delta);
    el.textContent = v.toLocaleString() + ' Comment' + (v !== 1 ? 's' : '');
}

// ── Like (backend) / Dislike (UI-only) ───────────────
async function toggleLike(btn) {
    if (!YTC.userId) { toast('Sign in to like comments', 'info'); return; }
    const id       = btn.dataset.id;
    const wasLiked = btn.classList.contains('liked');
    const countEl  = document.querySelector(`.ytc-like-count[data-id="${id}"]`);
    const prev     = parseInt(btn.dataset.count || '0');

    // Optimistic update
    const next = wasLiked ? Math.max(0, prev - 1) : prev + 1;
    btn.dataset.count = next;
    btn.classList.toggle('liked', !wasLiked);
    if (countEl) countEl.textContent = next > 0 ? next : '';
    if (!wasLiked) {
        const db = document.querySelector(`.ytc-dislike-btn[data-id="${id}"]`);
        if (db) db.classList.remove('disliked');
    }

    try {
        const r = await fetch(window.TOB.commentLike(id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const d = await r.json();
        if (r.ok) {
            btn.dataset.count = d.likes;
            btn.classList.toggle('liked', d.liked);
            if (countEl) countEl.textContent = d.likes > 0 ? d.likes : '';
        } else {
            // Revert on server error
            btn.dataset.count = prev;
            btn.classList.toggle('liked', wasLiked);
            if (countEl) countEl.textContent = prev > 0 ? prev : '';
        }
    } catch (e) {
        btn.dataset.count = prev;
        btn.classList.toggle('liked', wasLiked);
        if (countEl) countEl.textContent = prev > 0 ? prev : '';
    }
}

function toggleDislike(btn) {
    const id = btn.dataset.id;
    if (btn.classList.toggle('disliked')) {
        const lb = document.querySelector(`.ytc-like-btn[data-id="${id}"]`);
        if (lb && lb.classList.contains('liked')) lb.click(); // un-like via backend
    }
}

function initLikeStates() {} // server-rendered state is already in the HTML

// ── Timestamp & @mention parsing ─────────────────────
function enhanceBody(el) {
    if (!el || el.dataset._commentEnhanced === '1') return;
    let text = el.textContent || '';
    // @mm:ss, @mm.ss, @mm:ss-mm:ss, @mm.ss-mm.ss timestamps (colon or dot separator)
    text = text.replace(/@(\d{1,2})[.:](\d{2})(?:-(\d{1,2})[.:](\d{2}))?/g, (m, sM, sS, eM, eS) => {
        const sm = parseInt(sM), ss = parseInt(sS);
        if (isNaN(sm) || isNaN(ss) || ss > 59) return m;
        const start = sm * 60 + ss;
        const startFmt = String(sm).padStart(2,'0') + ':' + String(ss).padStart(2,'0');
        if (eM && eS) {
            const em = parseInt(eM), es = parseInt(eS);
            if (isNaN(em) || isNaN(es) || es > 59) return m;
            const end = em * 60 + es;
            const endFmt = String(em).padStart(2,'0') + ':' + String(es).padStart(2,'0');
            return `<span class="_comment-time-badge" data-start="${start}" data-end="${end}" title="Play ${startFmt}–${endFmt}"><i class="bi bi-play-fill" style="font-size:11px"></i> ${startFmt}–${endFmt}</span>`;
        }
        return `<span class="_comment-time-badge" data-start="${start}" title="Jump to ${startFmt}"><i class="bi bi-play-fill" style="font-size:11px"></i> ${startFmt}</span>`;
    });
    // @username mentions (only letters/digits/underscore — won't match already-replaced badge spans)
    text = text.replace(/@([a-zA-Z][a-zA-Z0-9_]*)/g, '<span class="ytc-mention">@$1</span>');
    el.innerHTML = text;
    el.dataset._commentEnhanced = '1';
    // Attach timestamp click
    el.querySelectorAll('._comment-time-badge').forEach(badge => {
        badge.addEventListener('click', e => {
            e.stopPropagation();
            playTimeRange(parseFloat(badge.dataset.start), badge.dataset.end ? parseFloat(badge.dataset.end) : null);
        });
    });
}
function enhanceAll() {
    document.querySelectorAll('._comment-body:not([data-_comment-enhanced="1"])').forEach(enhanceBody);
}

// ── Video seek ────────────────────────────────────────
let rangeHandler = null;
function playTimeRange(start, end) {
    const v = document.getElementById('videoPlayer') || document.getElementById('audioEl');
    if (!v) { toast('Player not found', 'error'); return; }
    if (rangeHandler) v.removeEventListener('timeupdate', rangeHandler);

    const playerEl = document.getElementById('ytpWrap') || document.getElementById('videoContainer') || v;
    playerEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

    setTimeout(() => {
        v.currentTime = Math.max(0, start);
        v.play().catch(() => {});
        if (end) {
            rangeHandler = () => { if (v.currentTime >= end) { v.pause(); v.removeEventListener('timeupdate', rangeHandler); rangeHandler = null; } };
            v.addEventListener('timeupdate', rangeHandler);
        }
    }, 500);
}

// ── Edit textarea Esc key ─────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const open = document.querySelector('.ytc-edit-form[style*="block"]');
        if (open) {
            const id = open.id.replace('commentEditWrap', '');
            cancelEdit(id);
        }
        const replyOpen = document.querySelector('.ytc-reply-form.open');
        if (replyOpen) {
            const id = replyOpen.id.replace('replyForm', '');
            toggleReplyForm(id);
        }
    }
});

// ── Auto-resize for dynamically added textareas ───────
const listObserver = new MutationObserver(() => {
    document.querySelectorAll('.ytc-textarea, .ytc-edit-textarea').forEach(ta => {
        if (!ta._ytcResizeAttached) {
            ta.addEventListener('input', () => autoResize(ta));
            ta._ytcResizeAttached = true;
        }
    });
    enhanceAll();
});
const list = document.getElementById('ytcList');
if (list) listObserver.observe(list, { childList: true, subtree: true });

// ── Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    enhanceAll();
    initLikeStates();
});
if (document.readyState !== 'loading') {
    enhanceAll();
    initLikeStates();
}

// ── Public API (for backward compat) ─────────────────
window._comment = {
    deleteComment: openDeleteModal,
    playTimeRange: playTimeRange,
};

})();
</script>

        </div>


        
        <div class="mrv-view" id="matchReviewView">
            <div class="mrv-video" id="mrvVideoSlot"></div>
            
                
                <div class="mrv" id="matchReview">

                
                    <div class="mrv-tabs">
                        <button type="button" class="mrv-tab is-on" data-mrv-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="points">{{ __('events.bout_video_tab_points') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="coach">{{ __('events.bout_video_tab_coach') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>
                    </div>

                    <div class="mrv-pane mrv-pane-flush is-on" data-mrv-panel="match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <div class="mrv-pane" data-mrv-panel="points">
                        <div class="mrv-scroll">
                            @forelse ($timeline['rounds'] as $round)
                                @php $moments = collect($timeline['moments'])->where('round', $round['number']); @endphp
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ $round['name'] }}</span>
                                        <span class="mrv-rcount">{{ $moments->count() }} {{ __('events.bout_video_moments') }}</span>
                                    </div>
                                </div>

                                @foreach ($moments as $m)
                                    <div class="hlp-row mrv-hlp" data-mrv-seek="{{ $m['t'] }}">
                                        <button type="button" class="hlp-time mrv-hlp-time" data-mrv-seek="{{ $m['t'] }}">{{ $stamp($m['t']) }}</button>
                                        <span class="hlp-bar hlp-bar-{{ $m['side'] }}"></span>
                                        <span class="hlp-what">
                                            <span class="hlp-label">{{ $m['label'] }}</span>
                                            <span class="hlp-who">{{ $m['who'] }}</span>
                                        </span>
                                        <span class="hlp-run">
                                            <span class="hlp-run-red">{{ $m['score_red'] }}</span>
                                            <span class="hlp-run-sep">–</span>
                                            <span class="hlp-run-blue">{{ $m['score_blue'] }}</span>
                                        </span>
                                        <span></span>
                                    </div>
                                @endforeach
                            @empty
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ $timeline['anchored'] ? __('events.bout_video_no_points') : __('events.bout_video_no_anchor') }}</span>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="mrv-pane" data-mrv-panel="coach">
                        <div class="mrv-scroll">
                            @forelse ($play['reviews'] ?? [] as $r)
                                <div class="mrv-note-card"
                                     data-mrv-seek="{{ $r['start_time_seconds'] }}"
                                     data-mrv-rev="{{ $r['id'] }}"
                                     @if ($r['end_time_seconds'] !== null) data-mrv-end="{{ $r['end_time_seconds'] }}" @endif>
                                    <div class="mrv-note-top">
                                        <span class="mrv-ava">{{ \App\Models\BoutComment::initials($r['coach_name']) }}</span>
                                        <span class="mrv-who">
                                            <span class="mrv-coach">{{ $r['coach_name'] }}</span>
                                            <button type="button" class="mrv-at" data-mrv-seek="{{ $r['start_time_seconds'] }}">{{ $clock($r['start_time_seconds']) }}</button>
                                        </span>
                                        <span class="mrv-tag">{{ $r['emoji'] }}</span>
                                    </div>
                                    <div class="mrv-note-text">{{ $r['note'] }}</div>
                                </div>
                            @empty
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ __('events.bout_video_no_notes') }}</span>
                                    </div>
                                </div>
                            @endforelse

                            @if ($canAnnotate)
                                <button type="button" class="mrv-at" style="margin-top:12px;" onclick="beginReviewCapture()">
                                    + {{ __('events.bout_video_add_note') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- The conversation lives in ONE place in the document and is
                         moved into whichever comments slot the reader opened; see
                         the mount helper further down. --}}
                    <div class="mrv-pane" data-mrv-panel="comments">
                        <div class="mrv-scroll" data-bout-comments-slot></div>
                    </div>
                </div>
        </div>

        <!-- Sidebar - Match Highlights -->
        <div class="yt-sidebar-container" style="display: flex; flex-direction: column; gap: 16px;">
            
            <div class="hl-sheet-backdrop" id="hlSheetBackdrop"></div>
            <aside class="events-sidebar">
                
                <div class="hl-sheet-grab"></div>

                
                <div class="fsh" id="fsHighlights">
                    <div class="fsh-head">
                        <button type="button" class="fsh-tab is-on" data-fsh-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="points">{{ __('events.bout_video_tab_points') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="coach">{{ __('events.bout_video_tab_coach') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>

                    </div>

                    <div class="fsh-list is-on" data-fsh-panel="match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <div class="fsh-list" data-fsh-panel="points">
                        @forelse ($timeline['rounds'] as $round)
                            @php $moments = collect($timeline['moments'])->where('round', $round['number']); @endphp
                            <div class="fsh-rhead">
                                <span class="fsh-rname">{{ $round['name'] }}</span>
                                <span class="fsh-rcount">{{ $moments->count() }} {{ __('events.bout_video_moments') }}</span>
                                <span class="fsh-rrule"></span>
                            </div>

                            @foreach ($moments as $m)
                                <div class="fsh-row" data-mrv-seek="{{ $m['t'] }}">
                                    <span class="fsh-time">{{ $stamp($m['t']) }}</span>
                                    <span class="fsh-bar fsh-bar-{{ $m['side'] }}"></span>
                                    <span class="fsh-what">
                                        <span class="fsh-label">{{ $m['label'] }}</span>
                                        <span class="fsh-who">{{ $m['who'] }}</span>
                                    </span>
                                    <span class="fsh-run">
                                        <span class="fsh-run-red">{{ $m['score_red'] }}</span>
                                        <span class="fsh-run-sep">–</span>
                                        <span class="fsh-run-blue">{{ $m['score_blue'] }}</span>
                                    </span>
                                    <span></span>
                                </div>
                            @endforeach
                        @empty
                            <div class="fsh-rhead">
                                <span class="fsh-rname">{{ $timeline['anchored'] ? __('events.bout_video_no_points') : __('events.bout_video_no_anchor') }}</span>
                                <span class="fsh-rrule"></span>
                            </div>
                        @endforelse
                    </div>

                    <div class="fsh-list" data-fsh-panel="coach">
                        @foreach ($play['reviews'] ?? [] as $r)
                            <div class="fsh-note" data-mrv-seek="{{ $r['start_time_seconds'] }}"
                                 data-mrv-rev="{{ $r['id'] }}"
                                 @if ($r['end_time_seconds'] !== null) data-mrv-end="{{ $r['end_time_seconds'] }}" @endif>
                                <div class="fsh-note-top">
                                    <span class="fsh-note-time">{{ $clock($r['start_time_seconds']) }}</span>
                                    <span class="fsh-note-coach">{{ $r['coach_name'] }}</span>
                                    <span class="fsh-note-tag">{{ $r['emoji'] }}</span>
                                </div>
                                <div class="fsh-note-text">{{ $r['note'] }}</div>
                            </div>
                        @endforeach
                        @if ($canAnnotate)
                            <button type="button" class="fsh-note" style="width:100%;border:0;font:inherit;color:#8ab4f8;text-align:center;cursor:pointer;"
                                    onclick="beginReviewCapture()">+ {{ __('events.bout_video_add_note') }}</button>
                        @endif
                    </div>

                    <div class="fsh-list" data-fsh-panel="comments">
                        <div data-bout-comments-slot></div>
                    </div>
                </div>

                <div class="tab-header">
                    <button class="tab-button active" data-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                    <button class="tab-button" data-tab="official">{{ __('events.bout_video_tab_points') }}</button>
                    <button class="tab-button" data-tab="review">{{ __('events.bout_video_tab_coach') }}</button>
                    <button class="tab-button" data-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>


                </div>
                <div class="tab-panels">
                    <!-- Match Tab -->
                    <div class="tab-panel active" id="tab-match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <!-- Points Tab -->
                    <div class="tab-panel" id="tab-official">
                        <div class="event-list" id="officialEvents"></div>
                    </div>

                    <!-- Coach Review Tab -->
                    <div class="tab-panel" id="tab-review">
                        <div class="event-list" id="reviewEvents"></div>
                        @if ($canAnnotate)
                            <button type="button" class="event-action-btn" style="margin-top:12px;"
                                    onclick="beginReviewCapture()">+ {{ __('events.bout_video_add_note') }}</button>
                        @endif
                    </div>

                    <!-- Comments Tab -->
                    <div class="tab-panel" id="tab-comments">
                        <div data-bout-comments-slot></div>
                    </div>
                </div>
            </aside>



        </div>
    </div>

    <!-- Modals -->
    

    <!-- Add Round Modal -->
    <div class="match-modal-overlay" id="addRoundModal">
        <div class="match-modal-content">
            <div class="match-modal-header">
                <h3><span class="modal-title-icon">➕</span> Add New Round</h3>
                <button class="match-modal-close" onclick="closeModal('addRoundModal')">×</button>
            </div>
            <form id="addRoundForm">
                <div class="match-form-row">
                    <div class="match-form-group">
                        <label>Round Number</label>
                        <input type="number" id="roundNumber" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Round Name (optional)</label>
                        <input type="text" id="roundName" placeholder="e.g., ROUND 1">
                    </div>
                </div>
                <div class="match-form-group">
                    <label>Start Time (mm.ss, optional)</label>
                    <input type="text" id="roundStartTime" inputmode="decimal" placeholder="e.g., 1.30 = 1m 30s">
                </div>
                <div class="match-modal-actions">
                    <button type="button" class="match-btn-cancel" onclick="closeModal('addRoundModal')">Cancel</button>
                    <button type="submit" class="match-btn-save">Add Round</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Point-timestamp capture strip
         Rendered here at page level, moved into #ytpWrap by beginPointCapture()
         so it visually replaces the player's normal control bar during capture. -->
    <div id="pointCaptureBar" class="point-capture-strip" hidden>
        <div class="pcs-row pcs-row-top">
            <div class="pcs-copy">
                <i class="bi bi-crosshair"></i>
                <span class="pcs-title">Drag the marker to the exact moment</span>
            </div>
            <div class="pcs-clock">
                <span id="pcbCurrentTime">00:00</span>
                <span class="pcs-clock-sep">/</span>
                <span id="pcbDuration">00:00</span>
            </div>
        </div>
        <div class="pcs-scrubber">
            <button type="button" class="pcs-step" onclick="pcbNudge(-1)" title="Back 1s"><i class="bi bi-chevron-left"></i></button>
            <input type="range" id="pcbSlider" min="0" max="0" step="0.1" value="0" class="pcs-range">
            <button type="button" class="pcs-step" onclick="pcbNudge(1)" title="Forward 1s"><i class="bi bi-chevron-right"></i></button>
            <div class="pcs-zoom" role="group" aria-label="Scrubber zoom">
                <button type="button" class="pcs-zoom-btn is-active" data-pcs-zoom="1"  title="Full range">1×</button>
                <button type="button" class="pcs-zoom-btn"           data-pcs-zoom="5"  title="Zoom 5× — window ≈ duration / 5">5×</button>
                <button type="button" class="pcs-zoom-btn"           data-pcs-zoom="20" title="Zoom 20× — fine positioning">20×</button>
            </div>
        </div>
        <div class="pcs-zoom-info" id="pcbZoomInfo" hidden>
            <i class="bi bi-zoom-in"></i>
            Showing <span id="pcbWinStart">00:00</span> – <span id="pcbWinEnd">00:00</span>
            (<span id="pcbWinSize">0s</span> window)
        </div>

        
        <div class="pcs-form">
            <div class="pcs-time-edit" title="Point time (mm:ss) — type to jump, or drag the marker">
                <i class="bi bi-clock"></i>
                <input type="text" id="pcbTimeInput" class="pcs-time-input" value="00:00"
                       inputmode="numeric" spellcheck="false" autocomplete="off"
                       aria-label="Point time (mm:ss)">
                <span class="pcs-time-sep">/</span>
                <span id="pcbDurationInline" class="pcs-time-dur">00:00</span>
            </div>
            <div class="pcs-field pcs-field-competitor">
                <div class="pcs-seg" role="group" aria-label="Competitor">
                    <button type="button" class="pcs-seg-opt pcs-seg-blue is-active" data-pcs-competitor="blue">Blue</button>
                    <button type="button" class="pcs-seg-opt pcs-seg-red"           data-pcs-competitor="red">Red</button>
                    <button type="button" class="pcs-seg-opt pcs-seg-both"          data-pcs-competitor="both">Both</button>
                </div>
            </div>
            <div class="pcs-actions">
                <button type="button" class="pcs-btn pcs-btn-danger" id="pcbDeleteBtn" onclick="pcbDeleteFromStrip()" title="Delete point" hidden>
                    <i class="bi bi-trash"></i> <span>Delete</span>
                </button>
                <button type="button" class="pcs-btn pcs-btn-ghost" onclick="cancelPointCapture()" title="Cancel (Esc)">
                    <i class="bi bi-x-lg"></i> <span>Cancel</span>
                </button>
                <button type="button" class="pcs-btn pcs-btn-primary" onclick="confirmPointCapture()" title="Save (Enter)">
                    <i class="bi bi-check2"></i> <span>Save</span>
                </button>
            </div>
        </div>

        
        <div class="pcs-entry pcs-entry-single" data-pcs-role="single">
            <span class="pcs-side-dot" data-pcs-side-dot></span>
            <input type="text" id="pcsAction" class="pcs-input pcs-action" placeholder="Action (e.g. body kick)" autocomplete="off">
            <input type="number" id="pcsPoints" class="pcs-input pcs-input-num" min="1" value="1" title="Points">
            <span class="pcs-unit">pt</span>
        </div>

        
        <div class="pcs-entry pcs-entry-both" data-pcs-role="both" hidden>
            <div class="pcs-entry-row pcs-side-blue">
                <span class="pcs-side-dot pcs-dot-blue"></span>
                <input type="text" id="pcsActionBlue" class="pcs-input pcs-action" placeholder="Blue action" autocomplete="off">
                <input type="number" id="pcsPointsBlue" class="pcs-input pcs-input-num" min="1" value="1" title="Blue points">
                <span class="pcs-unit">pt</span>
            </div>
            <div class="pcs-entry-row pcs-side-red">
                <span class="pcs-side-dot pcs-dot-red"></span>
                <input type="text" id="pcsActionRed" class="pcs-input pcs-action" placeholder="Red action" autocomplete="off">
                <input type="number" id="pcsPointsRed" class="pcs-input pcs-input-num" min="1" value="1" title="Red points">
                <span class="pcs-unit">pt</span>
            </div>
        </div>

        <input type="hidden" id="pcsCompetitor" value="blue">
    </div>

    
    <div id="reviewCaptureBar" class="point-capture-strip point-capture-strip-review" hidden>
        <div class="pcs-row-top">
            <div class="pcs-copy">
                <i class="bi bi-clipboard-check"></i>
                <span class="pcs-title">Drag the marker — set start (and optional end) of the note</span>
            </div>
        </div>

        <div class="pcs-form">
            <div class="rcb-time-edit is-active" data-rcb-slot="start" title="Note start — click to set">
                <span class="rcb-slot-label"><i class="bi bi-play-circle"></i> Start</span>
                <input type="text" id="rcbStart" class="pcs-time-input" value="00:00"
                       inputmode="numeric" spellcheck="false" autocomplete="off">
            </div>
            <span class="rcb-arrow">→</span>
            <div class="rcb-time-edit rcb-time-end" data-rcb-slot="end" title="Note end (optional) — click to set">
                <span class="rcb-slot-label"><i class="bi bi-stop-circle"></i> End</span>
                <input type="text" id="rcbEnd" class="pcs-time-input" value=""
                       placeholder="—" inputmode="numeric" spellcheck="false" autocomplete="off">
                <button type="button" class="rcb-clear" onclick="rcbClearEnd(event)" title="Clear end">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <button type="button" class="pcs-step rcb-preview" id="rcbPreviewBtn"
                    onclick="rcbTogglePreview()" title="Preview from Start → End">
                <i class="bi bi-play-fill"></i>
            </button>
            <span class="pcs-time-sep">/</span>
            <span id="rcbDuration" class="pcs-time-dur">00:00</span>

            <div class="pcs-actions">
                <button type="button" class="pcs-btn pcs-btn-danger" id="rcbDeleteBtn" onclick="rcbDeleteFromStrip()" title="Delete note" hidden>
                    <i class="bi bi-trash"></i> <span>Delete</span>
                </button>
                <button type="button" class="pcs-btn pcs-btn-ghost" onclick="cancelReviewCapture()" title="Cancel (Esc)">
                    <i class="bi bi-x-lg"></i> <span>Cancel</span>
                </button>
                <button type="button" class="pcs-btn pcs-btn-primary" onclick="confirmReviewCapture()" title="Save (Enter)">
                    <i class="bi bi-check2"></i> <span>Save</span>
                </button>
            </div>
        </div>

        <div class="pcs-scrubber pcs-scrubber-dual">
            <button type="button" class="pcs-step" onclick="rcbNudge(-1)" title="Back 1s (nudges the last-touched marker)"><i class="bi bi-chevron-left"></i></button>
            <div class="rcb-dual">
                <div class="rcb-range-fill" id="rcbRangeFill"></div>
                <input type="range" id="rcbSliderStart" class="rcb-range-dual rcb-range-start" min="0" max="0" step="0.1" value="0">
                <input type="range" id="rcbSliderEnd"   class="rcb-range-dual rcb-range-end"   min="0" max="0" step="0.1" value="0">
            </div>
            <button type="button" class="pcs-step" onclick="rcbNudge(1)" title="Forward 1s (nudges the last-touched marker)"><i class="bi bi-chevron-right"></i></button>
            <div class="pcs-zoom" role="group" aria-label="Scrubber zoom">
                <button type="button" class="pcs-zoom-btn is-active" data-rcb-zoom="1"  title="Full range">1×</button>
                <button type="button" class="pcs-zoom-btn"           data-rcb-zoom="5"  title="Zoom 5×">5×</button>
                <button type="button" class="pcs-zoom-btn"           data-rcb-zoom="20" title="Zoom 20× — fine positioning">20×</button>
            </div>
        </div>
        <input type="hidden" id="rcbSlider">

        <div class="rcb-form">
            <div class="rcb-emoji-picker" role="group" aria-label="Emoji">
                <button type="button" class="rcb-emoji-btn is-active" data-rcb-emoji="🔥">🔥</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="🤔">🤔</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="😄">😄</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="💪">💪</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="⚠️">⚠️</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="👀">👀</button>
                <button type="button" class="rcb-emoji-btn" data-rcb-emoji="📝">📝</button>
            </div>
            <input type="text" id="rcbCoach" class="pcs-input rcb-coach" placeholder="Coach name" autocomplete="off">
            <input type="text" id="rcbNote"  class="pcs-input rcb-note"  placeholder="Note — what did the coach see?" autocomplete="off">
        </div>

        <input type="hidden" id="rcbEmoji" value="🔥">
        <input type="hidden" id="rcbActiveSlot" value="start">
    </div>

    <style>
        /* Point-capture strip lives INSIDE #ytpWrap during capture, positioned
           over the normal control bar. Toggled by data-pcb-active on the wrap. */
        .point-capture-strip {
            position: absolute; left: 0; right: 0; bottom: 0; z-index: 60;
            padding: 10px 16px 12px;
            background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.75) 40%, rgba(0,0,0,.92) 100%);
            display: flex; flex-direction: column; gap: 8px;
            color: #fff; font-family: inherit;
            animation: pcsFadeUp .18s ease-out;
        }
        .point-capture-strip[hidden] { display: none; }
        @keyframes pcsFadeUp { from { transform: translateY(6px); opacity: 0; } to { transform: none; opacity: 1; } }

        /* While capture is active on this player, hide the normal chrome controls */
        #ytpWrap[data-pcb-active] #ytpControls { display: none !important; }
        /* Prevent the click-to-toggle overlay from stealing pointer events */
        #ytpWrap[data-pcb-active] { cursor: default; }

        /* MOBILE PORTRAIT: strip lives BELOW the video (in-flow), not overlaid.
           Renders as a solid card so it's readable against page bg. Class is
           applied by beginPointCapture / beginReviewCapture at runtime. */
        .point-capture-strip.pcs-below {
            position: static;
            background: #101010;
            border-top: 1px solid #262626;
            border-bottom: 1px solid #262626;
            /* Wider horizontal padding so the inner rows sit off the strip's edges
               (and therefore off the window's edges). Vertical padding untouched
               so nothing shifts up or down. */
            padding: 10px 20px 14px;
            margin: 0 -12px 12px;                 /* strip still bleeds edge-to-edge on mobile */
            box-shadow: 0 6px 20px rgba(0,0,0,.35);
            animation: pcsSlideDown .18s ease-out;
            z-index: auto;
        }
        @keyframes pcsSlideDown {
            from { transform: translateY(-6px); opacity: 0; }
            to   { transform: none;             opacity: 1; }
        }

        .pcs-row-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .pcs-copy { display: flex; align-items: center; gap: 8px; min-width: 0;
            color: #fff; font-size: 13px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,.6); }
        .pcs-copy > i { color: var(--brand-red, #e61e1e); font-size: 18px; flex-shrink: 0; }
        .pcs-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .pcs-clock { font-variant-numeric: tabular-nums; font-size: 13px; color: #ddd; flex-shrink: 0; text-shadow: 0 1px 2px rgba(0,0,0,.6); }
        #pcbCurrentTime { color: #fff; font-weight: 700; }
        .pcs-clock-sep  { margin: 0 4px; opacity: .5; }
        #pcbDuration    { color: #bbb; }

        .pcs-scrubber { display: flex; align-items: center; gap: 10px; }
        /* ── Dual-thumb scrubber (coach review Start / End) ─────────────
           Two <input type=range> stacked on the same track with pointer
           events only on the thumbs; a red fill sits between the thumbs to
           visualise the note's time range. */
        .rcb-dual {
            position: relative; flex: 1;
            height: 22px; display: flex; align-items: center;
        }
        .rcb-dual::before {
            content: ''; position: absolute; left: 0; right: 0;
            height: 6px; border-radius: 3px;
            background: rgba(255,255,255,.25);
        }
        .rcb-range-fill {
            position: absolute;
            left: 0; width: 0;
            height: 6px; border-radius: 3px;
            background: var(--brand-red, #e61e1e);
            pointer-events: none;
            transition: left .04s linear, width .04s linear;
        }
        .rcb-range-dual {
            position: absolute; left: 0; right: 0; top: 0; bottom: 0;
            width: 100%; margin: 0;
            -webkit-appearance: none; appearance: none;
            background: transparent; cursor: pointer;
            pointer-events: none;    /* only thumbs are interactive */
        }
        .rcb-range-dual::-webkit-slider-runnable-track {
            height: 6px; background: transparent;
        }
        .rcb-range-dual::-moz-range-track {
            height: 6px; background: transparent;
        }
        .rcb-range-dual::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--brand-red, #e61e1e); border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,.6);
            margin-top: -6px;
            cursor: grab; pointer-events: auto;   /* re-enable on thumb */
        }
        .rcb-range-dual:active::-webkit-slider-thumb { cursor: grabbing; }
        .rcb-range-dual::-moz-range-thumb {
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--brand-red, #e61e1e); border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,.6);
            cursor: grab; pointer-events: auto;
        }
        /* Give Start thumb a slight left offset visual accent (chevron-like) */
        .rcb-range-start::-webkit-slider-thumb {
            background: linear-gradient(135deg, #e61e1e 50%, #b91c1c 50%);
        }
        .rcb-range-end::-webkit-slider-thumb {
            background: linear-gradient(135deg, #b91c1c 50%, #e61e1e 50%);
        }
        /* Zoom controls */
        .pcs-zoom {
            display: inline-flex; border-radius: 6px; overflow: hidden; flex-shrink: 0;
            border: 1px solid rgba(255,255,255,.18); background: rgba(0,0,0,.55);
        }
        .pcs-zoom-btn {
            border: none; background: transparent; color: rgba(255,255,255,.75);
            padding: 5px 8px; font-size: 11px; font-weight: 700; cursor: pointer;
            font-family: inherit; min-width: 32px;
            transition: background .12s, color .12s;
            font-variant-numeric: tabular-nums;
        }
        .pcs-zoom-btn:hover { background: rgba(255,255,255,.08); color: #fff; }
        .pcs-zoom-btn.is-active { background: var(--brand-red, #e61e1e); color: #fff; }
        .pcs-zoom-info {
            font-size: 11px; color: rgba(255,255,255,.75); display: inline-flex;
            align-items: center; gap: 6px; margin-top: -4px;
        }
        .pcs-zoom-info > i { color: var(--brand-red, #e61e1e); }
        .pcs-zoom-info[hidden] { display: none; }
        .pcs-step {
            width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%;
            border: 1px solid rgba(255,255,255,.16); background: rgba(0,0,0,.55); color: #fff;
            display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
            transition: background .12s, border-color .12s; font-size: 14px;
        }
        .pcs-step:hover { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.3); }

        /* The draggable marker itself — big, brand-red, high-contrast */
        .pcs-range {
            flex: 1; -webkit-appearance: none; appearance: none;
            height: 8px; background: transparent; cursor: pointer; margin: 0;
        }
        .pcs-range::-webkit-slider-runnable-track {
            height: 6px; background: rgba(255,255,255,.25); border-radius: 3px;
        }
        .pcs-range::-moz-range-track {
            height: 6px; background: rgba(255,255,255,.25); border-radius: 3px;
        }
        .pcs-range::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none;
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--brand-red, #e61e1e); border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.7);
            margin-top: -7px; cursor: grab;
        }
        .pcs-range:active::-webkit-slider-thumb { cursor: grabbing; }
        .pcs-range::-moz-range-thumb {
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--brand-red, #e61e1e); border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.7); cursor: grab;
        }

        .pcs-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .pcs-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;
            border: 1px solid transparent; cursor: pointer; font-family: inherit;
            transition: background .12s, border-color .12s, transform .06s;
            white-space: nowrap;
        }
        .pcs-btn:active { transform: translateY(1px); }
        .pcs-btn-ghost   { background: rgba(0,0,0,.55); color: #fff; border-color: rgba(255,255,255,.18); }
        .pcs-btn-ghost:hover { background: rgba(0,0,0,.75); border-color: rgba(255,255,255,.32); }
        .pcs-btn-primary { background: var(--brand-red, #e61e1e); color: #fff; }
        .pcs-btn-primary:hover { background: #c81818; }
        .pcs-btn-primary[disabled] { opacity: .55; cursor: not-allowed; }
        .pcs-btn-danger  { background: rgba(0,0,0,.55); color: #ff6b6b; border-color: rgba(255,107,107,.4); }
        .pcs-btn-danger:hover  { background: rgba(255,107,107,.14); color: #fff; border-color: #ff6b6b; }
        .pcs-btn-danger[hidden] { display: none; }

        /* Inline form row: [time] [competitor] ......... [Cancel/Save] */
        .pcs-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .pcs-form .pcs-time-edit        { order: 1; }
        .pcs-form .pcs-field-competitor { order: 2; }
        .pcs-form .pcs-actions          { order: 3; margin-left: auto; }

        /* Editable time input */
        .pcs-time-edit {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(0,0,0,.55); border: 1px solid rgba(255,255,255,.18);
            border-radius: 6px; padding: 4px 8px; color: #fff;
            font-variant-numeric: tabular-nums;
        }
        .pcs-time-edit > i { color: var(--brand-red, #e61e1e); font-size: 13px; }
        .pcs-time-input {
            width: 56px; text-align: center; background: transparent;
            border: none; outline: none; color: #fff; font-weight: 700;
            font-size: 13px; font-family: inherit; padding: 0;
        }
        .pcs-time-input:focus { color: var(--brand-red, #fff); }
        .pcs-time-sep { opacity: .5; }
        .pcs-time-dur { color: #bbb; font-size: 13px; }

        /* Coach-review capture strip — two time slots + note/coach/emoji form */
        .rcb-time-edit {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 8px; border-radius: 6px;
            background: rgba(0,0,0,.55); border: 1px solid rgba(255,255,255,.18);
            font-variant-numeric: tabular-nums; color: #fff; cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .rcb-time-edit.is-active {
            border-color: var(--brand-red, #e61e1e);
            box-shadow: 0 0 0 2px rgba(230,30,30,.22);
        }
        .rcb-time-edit:hover { border-color: rgba(255,255,255,.32); }
        .rcb-slot-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: rgba(255,255,255,.6);
            display: inline-flex; align-items: center; gap: 4px;
        }
        .rcb-time-edit.is-active .rcb-slot-label { color: var(--brand-red, #e61e1e); }
        .rcb-time-edit .rcb-slot-label i { font-size: 11px; }
        .rcb-time-edit .pcs-time-input { width: 52px; font-size: 12px; padding: 0 2px; }
        .rcb-clear {
            border: none; background: transparent; color: rgba(255,255,255,.4); cursor: pointer;
            padding: 0 2px; font-size: 10px; line-height: 1;
        }
        .rcb-clear:hover { color: #fff; }
        .rcb-arrow { color: rgba(255,255,255,.4); font-weight: 700; }

        /* Preview (▶/⏸) button — plays start→end while composing the note */
        .rcb-preview {
            color: var(--brand-red, #e61e1e);
            border-color: rgba(230,30,30,.5);
            background: rgba(230,30,30,.14);
            /* Center the icon perfectly regardless of glyph */
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0; line-height: 1;
        }
        .rcb-preview > i {
            display: block; line-height: 1; font-size: 14px;
        }
        /* The play triangle's optical center sits ~1.5px to the left of its
           geometric center, so nudge it to look centered. */
        .rcb-preview > i.bi-play-fill { margin-left: 2px; }
        .rcb-preview > i.bi-pause-fill { margin-left: 0; }
        .rcb-preview:hover { background: rgba(230,30,30,.24); border-color: var(--brand-red, #e61e1e); }
        .rcb-preview.is-playing { background: var(--brand-red, #e61e1e); color: #fff; border-color: var(--brand-red, #e61e1e); }

        /* Second row of the review strip: emoji chips + coach + note */
        .rcb-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .rcb-emoji-picker {
            display: inline-flex; gap: 2px; padding: 2px; border-radius: 6px;
            background: rgba(0,0,0,.55); border: 1px solid rgba(255,255,255,.18);
        }
        .rcb-emoji-btn {
            border: none; background: transparent; font-size: 16px; line-height: 1;
            padding: 4px 6px; border-radius: 4px; cursor: pointer;
            transition: background .12s, transform .1s;
        }
        .rcb-emoji-btn:hover { background: rgba(255,255,255,.08); }
        .rcb-emoji-btn.is-active { background: rgba(230,30,30,.28); transform: scale(1.05); }
        .rcb-coach { flex: 0 0 160px; min-width: 120px; }
        .rcb-note  { flex: 1 1 260px; min-width: 200px; }

        @media (max-width: 640px) {
            .rcb-coach, .rcb-note { flex: 1 1 100%; }
            .rcb-arrow { display: none; }
            .rcb-time-edit .pcs-time-input { width: 44px; }
        }

        /* Equal-width Blue/Red/Both — no visual bias toward any one */
        .pcs-seg-opt { min-width: 62px; text-align: center; }
        .pcs-field { display: flex; align-items: center; gap: 4px; }
        .pcs-field-action { flex: 1 1 220px; min-width: 160px; }
        .pcs-input {
            width: 100%; background: rgba(0,0,0,.55); border: 1px solid rgba(255,255,255,.18);
            border-radius: 6px; padding: 7px 10px; color: #fff; font-size: 13px; font-family: inherit;
            outline: none; transition: border-color .12s, background .12s;
        }
        .pcs-input::placeholder { color: rgba(255,255,255,.5); }
        .pcs-input:focus { border-color: var(--brand-red, #e61e1e); background: rgba(0,0,0,.7); }
        .pcs-input-num { width: 56px; text-align: center; -moz-appearance: textfield; }
        .pcs-input-num::-webkit-outer-spin-button,
        .pcs-input-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pcs-unit { font-size: 12px; color: #bbb; }

        /* Blue / Red toggle */
        .pcs-seg { display: inline-flex; border-radius: 6px; overflow: hidden;
            border: 1px solid rgba(255,255,255,.18); background: rgba(0,0,0,.55); }
        .pcs-seg-opt {
            border: none; background: transparent; color: rgba(255,255,255,.75);
            padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: background .12s, color .12s;
        }
        .pcs-seg-opt:hover { background: rgba(255,255,255,.08); }
        .pcs-seg-opt.is-active.pcs-seg-blue { background: #2563eb; color: #fff; }
        .pcs-seg-opt.is-active.pcs-seg-red  { background: #e61e1e; color: #fff; }
        .pcs-seg-opt.is-active.pcs-seg-both {
            background: linear-gradient(90deg, #2563eb 0%, #2563eb 50%, #e61e1e 50%, #e61e1e 100%);
            color: #fff;
        }

        /* Entry rows (single or split-both) */
        .pcs-entry { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; min-width: 0; }
        .pcs-entry[hidden] { display: none; }
        /* Action is the flex-shrink target so the row always fits on one line */
        .pcs-entry-single .pcs-action { flex: 1 1 0; min-width: 0; }
        .pcs-entry-both { flex-direction: column; align-items: stretch; gap: 6px; }
        .pcs-entry-row {
            display: flex; align-items: center; gap: 8px;
            background: rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.1);
            border-radius: 6px; padding: 4px 8px;
        }
        .pcs-entry-row .pcs-action { flex: 1 1 0; min-width: 0; background: transparent; border-color: rgba(255,255,255,.14); }
        .pcs-side-blue { border-left: 3px solid #2563eb; }
        .pcs-side-red  { border-left: 3px solid #e61e1e; }
        .pcs-side-dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
            background: #2563eb;
        }
        .pcs-side-dot.pcs-dot-red  { background: #e61e1e; }
        .pcs-entry-single[data-side="red"] .pcs-side-dot { background: #e61e1e; }

        @media (max-width: 640px) {
            .point-capture-strip { padding: 8px 10px 10px; gap: 6px; }
            .pcs-title { font-size: 12px; }
            .pcs-step { width: 28px; height: 28px; }
            .pcs-form { gap: 6px; }
            /* Icons only on mobile (X for cancel, ✓ for save); text hidden. */
            .pcs-btn span { display: none; }
            .pcs-btn { padding: 8px 12px; }
            .pcs-entry-row .pcs-input { font-size: 12px; }
        }
    </style>

    

    <!-- Edit Round Modal -->
    <div class="match-modal-overlay" id="editRoundModal">
        <div class="match-modal-content">
            <div class="match-modal-header">
                <h3><span class="modal-title-icon">✏️</span> Edit Round</h3>
                <button class="match-modal-close" onclick="closeModal('editRoundModal')">×</button>
            </div>
            <form id="editRoundForm">
                <input type="hidden" id="editRoundId">
                <div class="match-form-group">
                    <label>Round Number</label>
                    <input type="number" id="editRoundNumber" min="1" required>
                </div>
                <div class="match-form-group">
                    <label>Round Name</label>
                    <input type="text" id="editRoundName" placeholder="e.g., ROUND 1" required>
                </div>
                <div class="match-form-group">
                    <label>Start Time (mm.ss, optional)</label>
                    <input type="text" id="editRoundStartTime" inputmode="decimal" placeholder="e.g., 2.05 = 2m 5s">
                </div>
                <div class="match-modal-actions">
                    <button type="button" class="match-btn-cancel"
                        onclick="closeModal('editRoundModal')">Cancel</button>
                    <button type="button" class="match-btn-save" onclick="confirmDeleteRound()">Delete Round</button>
                    <button type="submit" class="match-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    

    <!-- Delete Confirmation Modal -->
    <div class="match-modal-overlay" id="deleteConfirmModal">
        <div class="match-modal-content">
            <div class="match-modal-header">
                <h3><span class="modal-title-icon">🗑️</span> Confirm Delete</h3>
                <button class="match-modal-close" onclick="closeModal('deleteConfirmModal')">×</button>
            </div>
            <div class="delete-confirm-message" id="deleteConfirmMessage">
                Are you sure you want to delete this item?
            </div>
            <div class="delete-confirm-sub" id="deleteConfirmSub">
                This action cannot be undone.
            </div>
            <div class="match-modal-actions">
                <button type="button" class="match-btn-cancel"
                    onclick="closeModal('deleteConfirmModal')">Cancel</button>
                <button type="button" class="match-btn-delete" onclick="executeDelete()">Delete</button>
            </div>
        </div>
    </div>

    
    <script>
        // These live on `window` so SPA navigation (recSwapContent) can update
        // them for the newly-loaded match without a page refresh. Downstream
        // code references `videoId` and `isOwner` as free identifiers, which
        // resolve to window.videoId / window.isOwner via the global scope.
        window.videoId = @json($bout['match_no']);
        // "Owner" here is the design's word for whoever may write on the
        // footage. That is the organiser and the two athletes who fought it —
        // the server decides, and re-decides on every write.
        window.isOwner = @json((bool) $canAnnotate);
        var videoId = window.videoId;
        var isOwner = window.isOwner;
        // Rounds and points come from the mat console's own log. Nobody types
        // them on this page, so the design's editing affordances for them are
        // not rendered — only the coach-note ones, which `isOwner` still gates.
        window.canEditPoints = false;
        var canEditPoints = false;

        document.addEventListener('DOMContentLoaded', function() {
            loadMatchData();
        });

        async function loadMatchData() {
            try {
                const response = await fetch(window.TOB.data, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const data = await response.json();
                if (data.success) {
                    // Keep the in-memory cache in sync so edit/lookup handlers
                    // find newly-added points without needing a page reload.
                    window.matchRounds  = data.rounds  || [];
                    window.matchReviews = data.reviews || [];
                    renderMatchData(window.matchRounds, window.matchReviews);
                } else {
                    console.warn('No match data:', data.message);
                    renderStaticData();
                }
            } catch (error) {
                console.error('Failed to load match data:', error);
                if (window.matchRounds && window.matchReviews) {
                    renderMatchData(window.matchRounds, window.matchReviews);
                } else {
                    renderStaticData();
                }
            }
        }

        // Pass server-side data to JS if available
        window.matchRounds = @json($play['rounds'] ?? []);
        window.matchReviews = @json($play['reviews'] ?? []);
        if (window.matchRounds.length > 0) {
            renderMatchData(window.matchRounds, window.matchReviews);
        }

        function renderStaticData() {
            // No static demo data - empty state
            const pointsContainer = document.getElementById('officialEvents');
            const reviewsContainer = document.getElementById('reviewEvents');
            if (pointsContainer) pointsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">@lang('events.bout_video_no_points')</div>';
            if (reviewsContainer) reviewsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">@lang('events.bout_video_no_notes')</div>';
        }

        function renderMatchData(rounds, reviews) {
            if (!rounds || rounds.length === 0) return;
            const pointsContainer = document.getElementById('officialEvents');
            const reviewsContainer = document.getElementById('reviewEvents');
            let pointsHtml = '';
            let reviewsHtml = '';

            rounds.forEach(round => {
                const safeRoundName = String(round.name || '').replace(/'/g, "\\'");
                const roundStart = (round.start_time_seconds !== null && round.start_time_seconds !== undefined) ?
                    Number(round.start_time_seconds) : '';
                pointsHtml += `
        <div class="round-marker" data-round="${round.round_number}" data-round-id="${round.id}" data-start-time="${roundStart}">
            <span class="round-badge" data-start-time="${roundStart}" title="Jump to ${roundStart !== '' ? formatTime(roundStart) : 'start'}">${round.name}</span>
            ${canEditPoints ? `
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <div class="round-actions">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <button class="round-action-btn" onclick="beginPointCapture(${round.round_number}, ${round.id})" title="Add point">+</button>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <button class="round-action-btn" onclick="editRound(${round.round_number}, ${round.id}, '${safeRoundName}', ${roundStart === '' ? 'null' : roundStart})" title="Edit round">✏️</button>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <button class="round-action-btn" onclick="deleteRound(${round.id})" title="Delete round">🗑️</button>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    ` : ''}
        </div>`;

                if (round.points && round.points.length) {
                    // Group points by identical timestamp within the round so Blue+Red
                    // recorded at the same moment collapse into ONE clean entry.
                    const groups = new Map();
                    round.points
                        .slice()
                        .sort((a, b) => Number(a.timestamp_seconds) - Number(b.timestamp_seconds))
                        .forEach(p => {
                            const key = Number(p.timestamp_seconds);
                            if (!groups.has(key)) groups.set(key, []);
                            groups.get(key).push(p);
                        });

                    for (const [ts, pts] of groups) {
                        const timeStr = formatTime(ts);

                        // Order Blue first, Red second so the anchor's running score
                        // reflects the total after the whole moment.
                        pts.sort((a, b) => (a.competitor === 'blue' ? -1 : 1));
                        const anchor = pts[pts.length - 1];

                        /*
                         * One line describing the moment, in the order the points were
                         * scored: "Point +1 / +1" when both sides scored at once, plus a
                         * second line naming who — or "Both scored" when it was both.
                         * Reads as a scoresheet rather than a row of tags.
                         */
                        const sides = pts.map(p => (p.competitor === 'red' ? 'red' : 'blue'));
                        const bothSides = sides.includes('red') && sides.includes('blue');
                        const barCls = bothSides ? 'hlp-bar-both' : (sides[0] === 'red' ? 'hlp-bar-red' : 'hlp-bar-blue');

                        const action = (pts[0].action || 'Point');
                        const deltas = pts.map(p => `+${p.points ?? 1}`).join(' / ');
                        const hlpLabel = `${action} ${deltas}`;

                        const hlpWho = bothSides
                            ? 'Both scored'
                            : pts.map(p => `${p.competitor === 'red' ? 'Red' : 'Blue'}`).join(', ');

                        // ONE edit/delete pair per timestamp entry. Grouped entries edit
                        // both sides at once (opens the scrubber in "Both" mode); single
                        // entries edit that one point.
                        const ids = pts.map(p => p.id);
                        const editCall = ids.length > 1
                            ? `editPointGroup(${ids.join(',')})`
                            : `editPoint(${ids[0]})`;
                        const delCall = ids.length > 1
                            ? `deletePointGroup(${ids.join(',')})`
                            : `deletePoint(${ids[0]})`;
                        const actionsHtml = canEditPoints ? `
                <span class="hlp-tools">
                    <button type="button" class="hlp-ico hlp-ico-edit" onclick="${editCall}" title="Edit point">✎</button>
                    <button type="button" class="hlp-ico hlp-ico-del" onclick="${delCall}" title="Delete point">✕</button>
                </span>` : '<span></span>';

                        pointsHtml += `
                <div class="hlp-row event-item event-item-point" data-time-start="${ts}" data-round="${round.round_number}"
                     data-id="${ids.join(',')}">
                    <span class="hlp-time">${timeStr}</span>
                    <span class="hlp-bar ${barCls}"></span>
                    <span class="hlp-what">
                        <span class="hlp-label">${hlpLabel}</span>
                        <span class="hlp-who">${hlpWho}</span>
                    </span>
                    <span class="hlp-run">
                        <span class="hlp-run-red">${anchor.score_red ?? 0}</span>
                        <span class="hlp-run-sep">–</span>
                        <span class="hlp-run-blue">${anchor.score_blue ?? 0}</span>
                    </span>
                    ${actionsHtml}
                </div>`;
                    }
                }
            });

            if (reviews) {
                reviews.forEach(review => {
                    const timeStart = formatTime(review.start_time_seconds);
                    const timeEnd = review.end_time_seconds ? formatTime(review.end_time_seconds) : '';
                    const rangeHtml = timeEnd
                        ? `${timeStart} <span class="review-range-sep">—</span> ${timeEnd}`
                        : `${timeStart}`;
                    const emoji = review.emoji || '📝';
                    reviewsHtml += `
            <div class="event-item review-card" data-time-start="${review.start_time_seconds}" data-time-end="${review.end_time_seconds || ''}" data-id="${review.id}">
                <div class="review-side">
                    <span class="review-range">${rangeHtml}</span>
                </div>
                <div class="review-main" data-emoji="${emoji}">
                    <div class="review-body-col">
                        <div class="review-note">${review.note || ''}</div>
                        <div class="review-foot">
                            <span class="review-author">
                                <span class="review-author-name">${review.coach_name || 'Coach'}</span>
                            </span>
                            <div class="review-tools">
                                    ${isOwner ? `
                                    <button class="event-action-btn" onclick="editReview(${review.id})" title="Edit">✏️</button>
                                    <button class="event-action-btn delete" onclick="deleteReview(${review.id})" title="Delete">🗑️</button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
                });
            }

            if (pointsContainer) pointsContainer.innerHTML = pointsHtml;
            if (reviewsContainer) reviewsContainer.innerHTML = reviewsHtml;
            attachEventListeners();
        }

        // Compact MM:SS display for the sidebar (frames omitted — the strip
        // uses the SMPTE MM:SS.FF format for editing).
        function formatTime(seconds) {
            const t    = Math.max(0, Math.floor(Number(seconds) || 0));
            const mins = Math.floor(t / 60);
            const secs = t % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function parseMinuteSecondInput(value) {
            if (value === null || value === undefined) return null;
            const raw = String(value).trim();
            if (!raw) return null;

            // allow plain integer seconds too
            if (/^\d+$/.test(raw)) return parseInt(raw, 10);

            // expected mm.ss (dot as separator)
            const m = raw.match(/^(\d+)\.(\d{1,2})$/);
            if (!m) return null;

            const minutes = parseInt(m[1], 10);
            const secPart = m[2].padStart(2, '0');
            const secs = parseInt(secPart, 10);

            if (Number.isNaN(minutes) || Number.isNaN(secs) || secs > 59) return null;
            return (minutes * 60) + secs;
        }

        function toMinuteSecondInput(totalSeconds) {
            if (totalSeconds === null || totalSeconds === undefined || totalSeconds === '') return '';
            const s = Math.max(0, Math.floor(Number(totalSeconds)));
            const mins = Math.floor(s / 60);
            const secs = s % 60;
            return `${mins}.${secs.toString().padStart(2, '0')}`;
        }

        function requireValidTimeInput(value, fieldLabel) {
            const parsed = parseMinuteSecondInput(value);
            if (parsed === null) {
                showToast(`${fieldLabel} must be in mm.ss format (example: 1.30 for 1m 30s) or plain seconds.`, 'error');
                return null;
            }
            return parsed;
        }

        // Sync sidebar height to video player height
        function initSidebarHeightSync() {
            const wrap = document.getElementById('ytpWrap');
            if (!wrap) return;
            const apply = () => {
                const h = wrap.getBoundingClientRect().height;
                if (h > 0) document.documentElement.style.setProperty('--sidebar-height', h + 'px');
            };
            apply();
            new ResizeObserver(apply).observe(wrap);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebarHeightSync);
        } else {
            initSidebarHeightSync();
        }

        // Extracted so SPA nav can rebind against the fresh .tab-button / .event-item
        // elements after the sidebar is swapped in.
        window.initMatchTabsAndEvents = function () {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanels  = document.querySelectorAll('.tab-panel');
            tabButtons.forEach(button => {
                if (button._tabBound) return;
                button._tabBound = true;
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    // Re-query on every click — the sidebar may have been re-rendered
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
                    this.classList.add('active');
                    const target = document.getElementById('tab-' + targetTab);
                    if (target) target.classList.add('active');
                });
            });
            attachEventListeners();
        };
        document.addEventListener('DOMContentLoaded', function() {
            window.initMatchTabsAndEvents();
            document.getElementById('addRoundForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                storeRound(
                    document.getElementById('roundNumber').value,
                    document.getElementById('roundName').value || 'ROUND ' + document.getElementById(
                        'roundNumber').value,
                    document.getElementById('roundStartTime').value
                );
            });
            document.getElementById('editRoundForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                updateRound();
            });
        });

        // Called from recSwapContent / plSwapContent when SPA navigation lands
        // on a different match video. Updates window.videoId from the URL, then
        // reloads the match data and re-binds all UI handlers.
        window.reloadMatchVideoState = function (url) {
            try {
                const path = new URL(url, window.location.origin).pathname;
                const parts = path.split('/').filter(Boolean);
                const idx = parts.indexOf('videos');
                if (idx !== -1 && parts[idx + 1]) window.videoId = parts[idx + 1];
            } catch (_) {}
            // Keep the local `videoId` reference in sync for handlers that
            // captured the free identifier at module scope
            try { videoId = window.videoId; } catch (_) {}
            if (typeof loadMatchData === 'function') loadMatchData();
            if (typeof window.initMatchTabsAndEvents === 'function') window.initMatchTabsAndEvents();
            if (typeof window.initMatchHighlightsToggle === 'function') window.initMatchHighlightsToggle();
            if (typeof initSidebarHeightSync === 'function') initSidebarHeightSync();
        };

        let reviewPlaybackStopHandler = null;
        let reviewPlaybackEndTime = null;

        // A note with no end time still has to leave the screen on its own,
        // so it gets a default window; a ranged note is cleared by its replay.
        const COACH_NOTE_FALLBACK_SECS = 6;
        let _coachNoteWatcher = null;

        function _clearCoachNoteWatcher() {
            const video = document.getElementById('videoPlayer');
            if (_coachNoteWatcher && video) video.removeEventListener('timeupdate', _coachNoteWatcher);
            _coachNoteWatcher = null;
        }

        function showCoachNoteOverlay(text, pos) {
            const overlay = document.getElementById('coachNoteOverlay');
            if (!overlay) return;
            _clearCoachNoteWatcher();
            overlay.textContent = text || '';
            // Clear any previous absolute position, then re-apply if provided
            overlay.classList.remove('positioned');
            overlay.style.left = ''; overlay.style.top = '';
            overlay.classList.add('show');
            if (pos && Number.isFinite(pos.x) && Number.isFinite(pos.y)) {
                // Wait one frame so offsetWidth/Height reflect the new text
                requestAnimationFrame(() => rcbApplyOverlayPos(pos));
            }
        }

        function hideCoachNoteOverlay() {
            _clearCoachNoteWatcher();
            const overlay = document.getElementById('coachNoteOverlay');
            if (!overlay) return;
            overlay.classList.remove('show', 'positioned');
            overlay.textContent = '';
            overlay.style.left = ''; overlay.style.top = '';
        }

        function clearReviewPlaybackHandler(videoPlayer) {
            if (videoPlayer && reviewPlaybackStopHandler) {
                videoPlayer.removeEventListener('timeupdate', reviewPlaybackStopHandler);
            }
            reviewPlaybackStopHandler = null;
            reviewPlaybackEndTime = null;
        }

        function attachEventListeners() {
            document.querySelectorAll('.event-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (e.target.closest('.event-actions, .review-tools')) return;

                    const timeStart = parseFloat(this.getAttribute('data-time-start'));
                    const timeEndAttr = this.getAttribute('data-time-end');
                    const timeEnd = timeEndAttr === null || timeEndAttr === '' ? null : parseFloat(
                        timeEndAttr);

                    if (timeStart || timeStart === 0) {
                        const videoPlayer = document.getElementById('videoPlayer');
                        if (videoPlayer) {
                            const isReviewItem = this.closest('#tab-review') !== null;

                            // clear any previous stop watcher
                            clearReviewPlaybackHandler(videoPlayer);
                            hideCoachNoteOverlay();
                            // Cancel any in-flight slow-mo replay (review or point) and restore normal speed
                            if (typeof _stopReviewSlowmo === 'function') _stopReviewSlowmo();
                            if (typeof _stopPointReplay === 'function') _stopPointReplay();
                            videoPlayer.playbackRate = 1;

                            if (isReviewItem) {
                                const revId = Number(this.dataset.id);
                                const rev   = (window.matchReviews || []).find(r => Number(r.id) === revId);

                                // One entry point for every view: it paints the
                                // overlay, replays a ranged note, and always takes
                                // the overlay away again when the note is over.
                                const noteText =
                                    this.querySelector('.review-note')?.textContent?.trim() ||
                                    this.querySelector('.review-note-title')?.textContent?.trim() ||
                                    this.querySelector('.event-label')?.textContent?.trim() || '';
                                playCoachReview(revId, noteText, timeStart);
                                return;
                            } else {
                                // Point items: play a short clip around the point at 1×,
                                // replay it at 0.5×, then keep going at 1× — matches the
                                // coach-review flow so both interactions feel the same.
                                playPointReplay(timeStart);
                                _hidePlayerChrome();
                                return;
                            }

                            videoPlayer.play();
                            _hidePlayerChrome();
                        }
                    }
                });
            });

            const mainVideoPlayer = document.getElementById('videoPlayer');
            if (mainVideoPlayer && !mainVideoPlayer.dataset.reviewOverlayBound) {
                mainVideoPlayer.addEventListener('seeking', function() {
                    if (!reviewPlaybackStopHandler) return;
                    if (reviewPlaybackEndTime !== null && this.currentTime > reviewPlaybackEndTime + 0.15) {
                        hideCoachNoteOverlay();
                        clearReviewPlaybackHandler(this);
                    }
                });

                mainVideoPlayer.addEventListener('ended', function() {
                    hideCoachNoteOverlay();
                    clearReviewPlaybackHandler(this);
                });

                mainVideoPlayer.dataset.reviewOverlayBound = '1';
            }

            document.querySelectorAll('.round-badge').forEach(badge => {
                badge.style.cursor = 'pointer';
                badge.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target.closest('.round-actions')) return;

                    const marker = this.closest('.round-marker');
                    const startAttr = this.getAttribute('data-start-time') ?? marker?.getAttribute(
                        'data-start-time');
                    const startTime = startAttr === '' || startAttr === null ? 0 : parseFloat(startAttr);

                    if (!Number.isNaN(startTime)) {
                        const videoPlayer = document.getElementById('videoPlayer');
                        if (videoPlayer) {
                            videoPlayer.currentTime = Math.max(0, startTime);
                            videoPlayer.play();
                        }
                    }
                });
            });
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        let pendingDeleteAction = null;
        let pendingDeleteId = null;

        function requestDelete(type, id) {
            if (!id) return;
            pendingDeleteAction = type;
            pendingDeleteId = id;

            const message = document.getElementById('deleteConfirmMessage');
            const sub = document.getElementById('deleteConfirmSub');
            if (message) {
                message.textContent = `Delete this ${type}?`;
            }
            if (sub) {
                sub.textContent = 'This action cannot be undone.';
            }

            openModal('deleteConfirmModal');
        }

        function executeDelete() {
            if (!pendingDeleteAction || !pendingDeleteId) {
                closeModal('deleteConfirmModal');
                return;
            }

            if (pendingDeleteAction === 'round') {
                performDeleteRound(pendingDeleteId);
            } else if (pendingDeleteAction === 'point') {
                performDeletePoint(pendingDeleteId);
            } else if (pendingDeleteAction === 'point group') {
                pcbDeleteGroupNow(window._pendingGroupDelete || []);
                window._pendingGroupDelete = null;
            } else if (pendingDeleteAction === 'review') {
                performDeleteReview(pendingDeleteId);
            }

            pendingDeleteAction = null;
            pendingDeleteId = null;
            closeModal('deleteConfirmModal');
        }
        document.querySelectorAll('.match-modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        const csrfToken = @json(csrf_token());
        const isDemoMode = @json(! (bool) $canAnnotate);

        // Round/Point/Review functions (abbreviated for brevity)
        function openAddRoundModal() {
            if (isDemoMode || !csrfToken) {
                showToast('Demo Mode: Requires authentication', 'info');
                return;
            }
            document.getElementById('roundNumber').value = '';
            document.getElementById('roundName').value = '';
            document.getElementById('roundStartTime').value = '';
            openModal('addRoundModal');
        }
        async function storeRound(roundNumber, roundName, roundStartTime) {
            if (!csrfToken) return;
            try {
                const res = await fetch(`/videos/${videoId}/rounds`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        round_number: parseInt(roundNumber),
                        name: roundName,
                        start_time_seconds: roundStartTime !== '' ? parseMinuteSecondInput(
                            roundStartTime) : null
                    })
                });
                const data = await res.json();
                if (data.success) {
                    closeModal('addRoundModal');
                    loadMatchData();
                } else showToast(data.message || 'Error', 'error');
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        function editRound(roundNumber, roundId, currentName, currentStartTime = null) {
            if (isDemoMode || !csrfToken) {
                showToast('Demo Mode', 'info');
                return;
            }

            // Fallback to DOM values if params are missing
            if (!roundId) {
                const marker = document.querySelector(`.round-marker[data-round="${roundNumber}"]`);
                if (marker) {
                    roundId = marker.getAttribute('data-round-id') || roundId;
                    currentStartTime = marker.getAttribute('data-start-time') ?? currentStartTime;
                    currentName = marker.querySelector('.round-badge')?.textContent?.trim() || currentName;
                }
            }

            document.getElementById('editRoundId').value = roundId || '';
            document.getElementById('editRoundNumber').value = roundNumber || '';
            document.getElementById('editRoundName').value = currentName || '';
            document.getElementById('editRoundStartTime').value = (currentStartTime === null || currentStartTime ===
                undefined || currentStartTime === '') ? '' : toMinuteSecondInput(currentStartTime);
            openModal('editRoundModal');
        }
        async function updateRound() {
            if (!csrfToken) return;
            const roundId = document.getElementById('editRoundId').value;
            const roundNumber = document.getElementById('editRoundNumber').value;
            const roundName = document.getElementById('editRoundName').value;
            const roundStartTime = document.getElementById('editRoundStartTime').value;

            try {
                // Use method spoofing to avoid servers/proxies that block true PUT and return HTML error pages
                const payload = new URLSearchParams();
                payload.append('_method', 'PUT');
                payload.append('round_number', parseInt(roundNumber));
                payload.append('name', roundName);
                if (roundStartTime !== '') {
                    const parsedRoundStart = requireValidTimeInput(roundStartTime, 'Round start time');
                    if (parsedRoundStart === null) return;
                    payload.append('start_time_seconds', parsedRoundStart);
                }

                const res = await fetch(`/rounds/${roundId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: payload.toString()
                });

                let data;
                const contentType = res.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    const text = await res.text();
                    throw new Error(`Server returned non-JSON response (status ${res.status}). ${text.slice(0, 180)}`);
                }

                if (res.ok && data.success) {
                    closeModal('editRoundModal');
                    loadMatchData();
                } else {
                    throw new Error(data.message || `Update failed (status ${res.status})`);
                }
            } catch (e) {
                console.error('updateRound error:', e);
                showToast('Error updating round: ' + e.message, 'error');
            }
        }
        async function deleteRound(roundId) {
            requestDelete('round', roundId);
        }

        async function performDeleteRound(roundId) {
            if (!csrfToken) return;
            try {
                const res = await fetch(`/rounds/${roundId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                const data = await res.json();
                if (data.success) loadMatchData();
                else showToast(data.message || 'Error', 'error');
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        // ── Point-timestamp capture ──────────────────────────────────────
        // Pauses the video and swaps the player's normal control bar for a
        // capture strip embedded inside #ytpWrap. The big red marker on the
        // strip's slider is the moment being captured; dragging it seeks the
        // video live. Confirm/Cancel restore the normal controls.
        // Detect mobile portrait — capture strips render BELOW the video
        // instead of inside it so the paused frame stays visible while typing.
        function _mobilePortraitStrip() {
            return window.matchMedia('(max-width: 768px) and (orientation: portrait)').matches;
        }
        // On mobile the .yt-main container is the scroll parent (per CLAUDE.md
        // — the window itself is locked). Scroll the strip into view so the
        // user can see the fields they're about to type into.
        function _scrollStripIntoView(bar) {
            if (!bar || !_mobilePortraitStrip()) return;
            // Highlights sheet must slide UNDER the strip so it doesn't cover it
            if (typeof window.positionHighlightsSheet === 'function') {
                window.positionHighlightsSheet();
            }
            const main = document.getElementById('main');
            const wrap = document.getElementById('ytpWrap');
            if (main && wrap) {
                const wrapBottom = wrap.offsetTop + wrap.offsetHeight;
                main.scrollTo({ top: Math.max(0, wrapBottom - 8), behavior: 'smooth' });
            } else if (bar.scrollIntoView) {
                bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        // Called on cancel / save so the sheet slides back up to the player edge
        function _stripClosed() {
            if (typeof window.positionHighlightsSheet === 'function') {
                // Wait a tick so the strip is truly hidden (offsetHeight = 0)
                setTimeout(() => window.positionHighlightsSheet(), 20);
            }
        }

        let _pcbCtx = null;
        function beginPointCapture(roundNumber, roundId, existing) {
            if (isDemoMode || !csrfToken) { showToast('Demo Mode', 'info'); return; }
            const video = document.getElementById('videoPlayer');
            const wrap  = document.getElementById('ytpWrap');
            const bar   = document.getElementById('pointCaptureBar');
            if (!video || !wrap || !bar) return;

            // Normalize `existing` — can be a single point object, an array of
            // two (blue + red) for group edit, or null/undefined for add mode.
            let existingSingle = null;
            let existingBlue = null, existingRed = null;
            if (Array.isArray(existing)) {
                existing.forEach(p => {
                    if (p.competitor === 'red') existingRed = p; else existingBlue = p;
                });
            } else if (existing && typeof existing === 'object') {
                existingSingle = existing;
            }

            try { video.pause(); } catch (_) {}
            // Seek to the recorded timestamp so the marker lands on the right moment.
            const seedTs = existingSingle
                ? Number(existingSingle.timestamp_seconds)
                : (existingBlue ? Number(existingBlue.timestamp_seconds)
                                : (existingRed ? Number(existingRed.timestamp_seconds) : null));
            if (seedTs !== null && Number.isFinite(seedTs)) {
                try { video.currentTime = seedTs; } catch (_) {}
            }

            // Placement: on desktop / mobile landscape the strip sits INSIDE the
            // player wrap (replaces the control bar, inherits fullscreen). On
            // mobile portrait it goes BELOW the video so the user can still see
            // the paused frame while entering the point.
            const useBelow = _mobilePortraitStrip();
            if (bar.parentElement !== (useBelow ? wrap.parentNode : wrap)) {
                bar._pcbOrigParent = bar.parentElement;
                bar._pcbOrigNext   = bar.nextSibling;
                if (useBelow) {
                    wrap.parentNode.insertBefore(bar, wrap.nextSibling);
                    bar.classList.add('pcs-below');
                } else {
                    wrap.appendChild(bar);
                    bar.classList.remove('pcs-below');
                }
            } else if (!useBelow) {
                bar.classList.remove('pcs-below');
            }
            wrap.setAttribute('data-pcb-active', '1');

            const slider  = document.getElementById('pcbSlider');
            const clockEl = document.getElementById('pcbCurrentTime');
            const durEl   = document.getElementById('pcbDuration');
            const timeInp = document.getElementById('pcbTimeInput');
            const durInln = document.getElementById('pcbDurationInline');

            const wireDuration = () => {
                const d = Number(video.duration);
                if (isFinite(d) && d > 0) {
                    durEl.textContent = toMinuteSecondClock(d);
                    if (durInln) durInln.textContent = toMinuteSecondClock(d);
                    _pcbApplyZoomWindow(); // fresh full-range window now that we know duration
                }
            };
            wireDuration();
            if (!(isFinite(video.duration) && video.duration > 0)) {
                video.addEventListener('loadedmetadata', wireDuration, { once: true });
            }

            // Zoom state — 1× = full duration, N× = window of (duration / N)
            _pcbZoom = 1;
            _pcbApplyZoomWindow();

            slider.value = Math.max(Number(slider.min) || 0, Math.min(Number(slider.max) || 0, video.currentTime || 0));
            const initClock = toMinuteSecondClock(video.currentTime || 0);
            clockEl.textContent = initClock;
            if (timeInp) timeInp.value = initClock;

            // Reset inline form fields (fresh capture)
            document.getElementById('pcsAction').value      = '';
            document.getElementById('pcsPoints').value      = '1';
            document.getElementById('pcsActionBlue').value  = '';
            document.getElementById('pcsPointsBlue').value  = '1';
            document.getElementById('pcsActionRed').value   = '';
            document.getElementById('pcsPointsRed').value   = '1';
            _pcsSetCompetitor('blue');

            // Edit-mode setup: pre-fill from existing point(s), show Delete
            const delBtn  = document.getElementById('pcbDeleteBtn');
            const saveBtn = bar.querySelector('.pcs-btn-primary');
            const editing = !!(existingSingle || existingBlue || existingRed);
            if (editing) {
                if (existingSingle) {
                    _pcsSetCompetitor(existingSingle.competitor === 'red' ? 'red' : 'blue');
                    document.getElementById('pcsAction').value = existingSingle.action || '';
                    document.getElementById('pcsPoints').value = existingSingle.points || 1;
                } else {
                    // Group edit — start in Both mode with both sides pre-filled
                    _pcsSetCompetitor('both');
                    if (existingBlue) {
                        document.getElementById('pcsActionBlue').value = existingBlue.action || '';
                        document.getElementById('pcsPointsBlue').value = existingBlue.points || 1;
                    }
                    if (existingRed) {
                        document.getElementById('pcsActionRed').value = existingRed.action || '';
                        document.getElementById('pcsPointsRed').value = existingRed.points || 1;
                    }
                }
                wrap.setAttribute('data-pcb-mode', 'edit');
                if (delBtn)  delBtn.hidden = false;
                if (saveBtn) { saveBtn.title = 'Update (Enter)'; saveBtn.querySelector('span').textContent = 'Update'; }
            } else {
                wrap.removeAttribute('data-pcb-mode');
                if (delBtn)  delBtn.hidden = true;
                if (saveBtn) { saveBtn.title = 'Save (Enter)';   saveBtn.querySelector('span').textContent = 'Save';   }
            }

            // Slider → video seek (live scrub) → time input mirror
            // Aggressive pause — unconditional and covers HLS.js's async play state.
            const forcePause = () => {
                try { video.pause(); } catch (_) {}
                // HLS layer may re-issue play() during a pending buffer/seek;
                // a second pause a tick later beats the race in most builds.
                setTimeout(() => { try { video.pause(); } catch (_) {} }, 30);
            };
            const onSlide = () => {
                forcePause();
                const t = snapToFrame(Number(slider.value) || 0);
                slider.value = t;
                try { video.currentTime = t; } catch (_) {}
                const clock = toMinuteSecondClock(t);
                clockEl.textContent = clock;
                if (timeInp && document.activeElement !== timeInp) timeInp.value = clock;
            };
            // On slider release (pointer up), recentre the window when at edge
            const onSlideCommit = () => {
                if (_pcbZoom <= 1) return;
                const val = Number(slider.value) || 0;
                const min = Number(slider.min) || 0;
                const max = Number(slider.max) || 0;
                const edge = (max - min) * 0.02; // 2% tolerance
                if (val <= min + edge || val >= max - edge) _pcbApplyZoomWindow();
            };
            // Video → slider + input mirror
            const onVideoTime = () => {
                if (document.activeElement === slider) return;
                const t = video.currentTime || 0;
                // If seeking landed outside the zoom window, recentre it around t
                if (_pcbZoom > 1 && (t < Number(slider.min) || t > Number(slider.max))) {
                    _pcbApplyZoomWindow();
                }
                slider.value = t;
                const clock = toMinuteSecondClock(t);
                clockEl.textContent = clock;
                if (timeInp && document.activeElement !== timeInp) timeInp.value = clock;
            };
            // Time input → video seek (on commit)
            const commitTimeInput = () => {
                if (!timeInp) return;
                const secs = parsePcbTimeInput(timeInp.value);
                if (secs === null) {
                    // Invalid — snap back to current
                    timeInp.value = toMinuteSecondClock(video.currentTime || 0);
                    return;
                }
                // Typing a timestamp is a scrubber interaction — freeze the
                // frame. Only the play button resumes playback.
                forcePause();
                const dur = Number(video.duration) || 0;
                const clamped = Math.max(0, dur > 0 ? Math.min(dur, secs) : secs);
                try { video.currentTime = clamped; } catch (_) {}
                slider.value = clamped;
                timeInp.value = toMinuteSecondClock(clamped);
                clockEl.textContent = timeInp.value;
            };
            const onTimeInputKey = (e) => {
                if (e.key === 'Enter') { e.preventDefault(); commitTimeInput(); timeInp.blur(); }
            };

            // Esc = cancel, Enter (in text fields OTHER than the time input) = save
            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); cancelPointCapture(); return; }
                if (e.key === 'Enter'  && e.target && bar.contains(e.target) &&
                    e.target !== timeInp &&
                    (e.target.tagName === 'INPUT' && e.target.type !== 'range')) {
                    e.preventDefault();
                    confirmPointCapture();
                }
            };

            slider.addEventListener('input', onSlide);
            // Pause the second the user touches the slider — not only on input.
            // Some browsers / HLS builds keep playing until the first input fires;
            // this guarantees the frame is frozen before the drag even moves.
            const onSliderGrab = () => forcePause();
            slider.addEventListener('mousedown',  onSliderGrab);
            slider.addEventListener('pointerdown', onSliderGrab);
            slider.addEventListener('touchstart',  onSliderGrab, { passive: true });
            slider.addEventListener('change', onSlideCommit);
            video.addEventListener('timeupdate', onVideoTime);
            video.addEventListener('seeked',     onVideoTime);
            document.addEventListener('keydown', onKey);
            if (timeInp) {
                timeInp.addEventListener('change',  commitTimeInput);
                timeInp.addEventListener('blur',    commitTimeInput);
                timeInp.addEventListener('keydown', onTimeInputKey);
            }

            bar.hidden = false;
            setTimeout(() => {
                const focusTarget = document.querySelector('.pcs-entry:not([hidden]) .pcs-action');
                if (focusTarget) focusTarget.focus();
                // On mobile portrait the strip lives below the video — scroll
                // the main container so the user can actually see it.
                _scrollStripIntoView(bar);
            }, 30);

            _pcbCtx = { roundNumber, roundId, video, wrap, bar, slider,
                        onSlide, onSlideCommit, onVideoTime, onKey, onSliderGrab,
                        timeInp, commitTimeInput, onTimeInputKey,
                        // Exposed so pcbNudge / commitTimeInput (defined
                        // outside this closure) can guarantee the video
                        // stays paused during any scrubber interaction.
                        forcePause,
                        existingId:      existingSingle ? existingSingle.id : null,
                        existingBlueId:  existingBlue   ? existingBlue.id   : null,
                        existingRedId:   existingRed    ? existingRed.id    : null };
        }
        // Apply the current zoom level: recentre the window on the video's currentTime.
        let _pcbZoom = 1;
        function _pcbApplyZoomWindow() {
            const video  = document.getElementById('videoPlayer');
            const slider = document.getElementById('pcbSlider');
            if (!video || !slider) return;
            const dur = Number(video.duration) || 0;
            if (dur <= 0) { slider.min = 0; slider.max = 0; slider.step = 0.1; return; }

            const cur = Math.max(0, Math.min(dur, Number(video.currentTime) || 0));
            const winSize = Math.max(0.5, dur / (_pcbZoom || 1));
            let start = cur - winSize / 2;
            let end   = cur + winSize / 2;
            if (start < 0)   { end -= start; start = 0; }
            if (end   > dur) { start -= (end - dur); end = dur; if (start < 0) start = 0; }

            slider.min = start;
            slider.max = end;
            // Finer step at higher zoom for smoother frame-level positioning
            // Always step by 1 frame — the scrubber is frame-accurate.
            slider.step = FRAME_STEP();
            slider.value = cur;

            // Info line under the scrubber
            const info = document.getElementById('pcbZoomInfo');
            if (info) {
                info.hidden = _pcbZoom <= 1;
                document.getElementById('pcbWinStart').textContent = toMinuteSecondClock(start);
                document.getElementById('pcbWinEnd').textContent   = toMinuteSecondClock(end);
                const size = end - start;
                document.getElementById('pcbWinSize').textContent  = (size < 60
                    ? size.toFixed(size < 10 ? 1 : 0) + 's'
                    : toMinuteSecondClock(size));
            }
        }
        function _pcbSetZoom(level) {
            _pcbZoom = Math.max(1, Number(level) || 1);
            // Scope strictly to the POINT strip's own zoom buttons — the review
            // strip uses the same class but has data-rcb-zoom, and clicks on it
            // must not clobber this state.
            document.querySelectorAll('.pcs-zoom-btn[data-pcs-zoom]').forEach(el => {
                el.classList.toggle('is-active', Number(el.dataset.pcsZoom) === _pcbZoom);
            });
            _pcbApplyZoomWindow();
        }
        // Click delegation only for point-strip zoom buttons.
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.pcs-zoom-btn[data-pcs-zoom]');
            if (!btn) return;
            _pcbSetZoom(Number(btn.dataset.pcsZoom) || 1);
        });
        // Parse "mm:ss" (or "mm.ss" or plain seconds) into total seconds.
        // Accepts:  MM:SS.FF (frames)  |  MM:SS  |  MM.SS  |  plain seconds
        function parsePcbTimeInput(raw) {
            const s = String(raw || '').trim();
            if (!s) return null;
            const fps = window.matchFPS || 30;
            // MM:SS.FF  or MM:SS,FF
            let m = s.match(/^(\d+)[:.](\d{1,2})[.,](\d{1,2})$/);
            if (m) {
                const mins = parseInt(m[1], 10);
                const secs = parseInt(m[2], 10);
                const frames = parseInt(m[3], 10);
                if ([mins, secs, frames].some(Number.isNaN) || secs > 59 || frames >= fps) return null;
                return snapToFrame(mins * 60 + secs + frames / fps);
            }
            // MM:SS or MM.SS
            m = s.match(/^(\d+)[:.](\d{1,2})$/);
            if (m) {
                const mins = parseInt(m[1], 10);
                const secs = parseInt(m[2].padStart(2, '0'), 10);
                if (Number.isNaN(mins) || Number.isNaN(secs) || secs > 59) return null;
                return snapToFrame(mins * 60 + secs);
            }
            // Plain integer seconds
            if (/^\d+$/.test(s)) return snapToFrame(parseInt(s, 10));
            return null;
        }
        function _pcsSetCompetitor(which) {
            const hidden = document.getElementById('pcsCompetitor');
            const prev   = hidden ? hidden.value : 'blue';
            if (hidden) hidden.value = which;
            document.querySelectorAll('.pcs-seg-opt').forEach(el => {
                el.classList.toggle('is-active', el.dataset.pcsCompetitor === which);
            });

            const single = document.querySelector('.pcs-entry-single');
            const both   = document.querySelector('.pcs-entry-both');
            if (!single || !both) return;

            // Value carry-over between single and both modes so no typing is wasted
            const sAction = document.getElementById('pcsAction');
            const sPoints = document.getElementById('pcsPoints');
            const bAction = document.getElementById('pcsActionBlue');
            const bPoints = document.getElementById('pcsPointsBlue');
            const rAction = document.getElementById('pcsActionRed');
            const rPoints = document.getElementById('pcsPointsRed');

            if (which === 'both' && prev !== 'both') {
                // Seed the appropriate side of "both" from whatever the user typed in single
                if (prev === 'red') {
                    if (!rAction.value) rAction.value = sAction.value;
                    if (rPoints.value == '1' && sPoints.value != '1') rPoints.value = sPoints.value;
                } else {
                    if (!bAction.value) bAction.value = sAction.value;
                    if (bPoints.value == '1' && sPoints.value != '1') bPoints.value = sPoints.value;
                }
            } else if (which !== 'both' && prev === 'both') {
                // Coming out of both back into single — pull the matching side's values
                const src = which === 'red'
                    ? { a: rAction.value, p: rPoints.value }
                    : { a: bAction.value, p: bPoints.value };
                if (src.a && !sAction.value) sAction.value = src.a;
                if (src.p && sPoints.value == '1') sPoints.value = src.p;
            }

            if (which === 'both') {
                single.hidden = true;
                both.hidden   = false;
                setTimeout(() => (bAction.focus()), 20);
            } else {
                single.hidden = false;
                both.hidden   = true;
                single.dataset.side = which;
                setTimeout(() => (sAction.focus()), 20);
            }
        }
        // One-time wiring of the segmented control
        document.addEventListener('click', function (e) {
            const opt = e.target.closest('.pcs-seg-opt');
            if (!opt) return;
            _pcsSetCompetitor(opt.dataset.pcsCompetitor);
        });
        function cancelPointCapture() {
            const barEarly = document.getElementById('pointCaptureBar');
            if (barEarly) {
                const sb = barEarly.querySelector('.pcs-btn-primary');
                if (sb) sb.disabled = false;
            }
            if (!_pcbCtx) {
                if (barEarly) barEarly.hidden = true;
                return;
            }
            const { video, wrap, bar, slider, onSlide, onSlideCommit, onVideoTime, onKey,
                    onSliderGrab, timeInp, commitTimeInput, onTimeInputKey } = _pcbCtx;
            slider.removeEventListener('input',      onSlide);
            slider.removeEventListener('change',     onSlideCommit);
            if (onSliderGrab) {
                slider.removeEventListener('mousedown',   onSliderGrab);
                slider.removeEventListener('pointerdown', onSliderGrab);
                slider.removeEventListener('touchstart',  onSliderGrab);
            }
            video.removeEventListener('timeupdate',  onVideoTime);
            video.removeEventListener('seeked',      onVideoTime);
            document.removeEventListener('keydown',  onKey);
            if (timeInp) {
                timeInp.removeEventListener('change',  commitTimeInput);
                timeInp.removeEventListener('blur',    commitTimeInput);
                timeInp.removeEventListener('keydown', onTimeInputKey);
            }

            bar.hidden = true;
            wrap.removeAttribute('data-pcb-active');
            wrap.removeAttribute('data-pcb-mode');
            // Restore original parent so page-level DOM stays predictable
            if (bar._pcbOrigParent) {
                if (bar._pcbOrigNext && bar._pcbOrigNext.parentNode === bar._pcbOrigParent) {
                    bar._pcbOrigParent.insertBefore(bar, bar._pcbOrigNext);
                } else {
                    bar._pcbOrigParent.appendChild(bar);
                }
                delete bar._pcbOrigParent;
                delete bar._pcbOrigNext;
            }
            _pcbCtx = null;
            _stripClosed();
        }
        function pcbNudge(deltaSeconds) {
            if (!_pcbCtx) return;
            const { video, slider, forcePause } = _pcbCtx;
            // Any scrubber interaction must freeze the frame — the user is
            // trying to land on a precise moment. Playback resumes only when
            // they explicitly press the play button.
            if (forcePause) forcePause();
            // Direction only — nudge is always ±1 frame, at every zoom level
            const dir  = deltaSeconds >= 0 ? 1 : -1;
            const step = FRAME_STEP() * dir;
            const dur = Number(video.duration) || 0;
            const next = snapToFrame(Math.max(0, Math.min(dur, (Number(video.currentTime) || 0) + step)));
            try { video.currentTime = next; } catch (_) {}
            // Recentre window if next fell out of the current zoom window
            if (_pcbZoom > 1 && (next < Number(slider.min) || next > Number(slider.max))) {
                _pcbApplyZoomWindow();
            }
            slider.value = next;
            const clock = toMinuteSecondClock(next);
            document.getElementById('pcbCurrentTime').textContent = clock;
            const timeInp = document.getElementById('pcbTimeInput');
            if (timeInp && document.activeElement !== timeInp) timeInp.value = clock;
        }
        async function confirmPointCapture() {
            if (!_pcbCtx) return;
            if (!csrfToken) { cancelPointCapture(); return; }

            const { roundId, video, bar, timeInp, existingId, existingBlueId, existingRedId } = _pcbCtx;
            // Prefer whatever's typed in the time input (auto-committed on blur too)
            // Frame-accurate: snap to the nearest frame instead of rounding to a whole second
            let seconds = Math.max(0, snapToFrame(Number(video.currentTime) || 0));
            if (timeInp) {
                const parsed = parsePcbTimeInput(timeInp.value);
                if (parsed !== null) seconds = parsed;
            }
            const competitor = document.getElementById('pcsCompetitor').value || 'blue';
            const editingGroup = !!(existingBlueId && existingRedId);

            if (!roundId && !existingId && !editingGroup) {
                showToast('Round is missing — refresh and try again.', 'error');
                return;
            }

            // Edit-group mode: PUT both existing rows (or PUT one + DELETE other
            // if the user switched to single competitor).
            if (editingGroup) {
                const saveBtn = bar.querySelector('.pcs-btn-primary');
                if (competitor === 'both') {
                    const blueAction = document.getElementById('pcsActionBlue').value.trim();
                    const redAction  = document.getElementById('pcsActionRed').value.trim();
                    const bluePts    = Math.max(1, parseInt(document.getElementById('pcsPointsBlue').value, 10) || 1);
                    const redPts     = Math.max(1, parseInt(document.getElementById('pcsPointsRed').value, 10) || 1);
                    if (!blueAction || !redAction) {
                        showToast('Enter an action for both Blue and Red.', 'error');
                        (blueAction ? document.getElementById('pcsActionRed') : document.getElementById('pcsActionBlue')).focus();
                        return;
                    }
                    if (saveBtn) saveBtn.disabled = true;
                    try {
                        await pcbPutPoint(existingBlueId, { timestamp_seconds: seconds, action: blueAction, points: bluePts, competitor: 'blue' });
                        await pcbPutPoint(existingRedId,  { timestamp_seconds: seconds, action: redAction,  points: redPts,  competitor: 'red'  });
                        cancelPointCapture(); loadMatchData();
                    } catch (e) {
                        showToast(e.message || 'Could not update points', 'error');
                        if (saveBtn) saveBtn.disabled = false;
                    }
                } else {
                    // User switched to single competitor — keep that side, delete the other
                    const action = document.getElementById('pcsAction').value.trim();
                    const points = Math.max(1, parseInt(document.getElementById('pcsPoints').value, 10) || 1);
                    if (!action) {
                        showToast('Enter an action for this point.', 'error');
                        document.getElementById('pcsAction').focus();
                        return;
                    }
                    const keepId   = competitor === 'red' ? existingRedId : existingBlueId;
                    const removeId = competitor === 'red' ? existingBlueId : existingRedId;
                    if (saveBtn) saveBtn.disabled = true;
                    try {
                        await pcbPutPoint(keepId, { timestamp_seconds: seconds, action, points, competitor });
                        await pcbDeletePoint(removeId);
                        cancelPointCapture(); loadMatchData();
                    } catch (e) {
                        showToast(e.message || 'Could not update points', 'error');
                        if (saveBtn) saveBtn.disabled = false;
                    }
                }
                return;
            }

            // Edit-single mode: PUT the existing point. If the user switched to
            // "Both", also POST a companion point for the other side.
            if (existingId) {
                const saveBtn = bar.querySelector('.pcs-btn-primary');

                let putBody = null, extraPost = null;
                if (competitor === 'both') {
                    const blueAction = document.getElementById('pcsActionBlue').value.trim();
                    const redAction  = document.getElementById('pcsActionRed').value.trim();
                    const bluePts    = Math.max(1, parseInt(document.getElementById('pcsPointsBlue').value, 10) || 1);
                    const redPts     = Math.max(1, parseInt(document.getElementById('pcsPointsRed').value, 10) || 1);
                    if (!blueAction || !redAction) {
                        showToast('Enter an action for both Blue and Red.', 'error');
                        (blueAction ? document.getElementById('pcsActionRed') : document.getElementById('pcsActionBlue')).focus();
                        return;
                    }
                    // Keep the existing row as Blue, add a new Red row (arbitrary but stable)
                    putBody   = { timestamp_seconds: seconds, action: blueAction, points: bluePts, competitor: 'blue' };
                    extraPost = { competitor: 'red',  action: redAction,  points: redPts  };
                } else {
                    const action = document.getElementById('pcsAction').value.trim();
                    const points = Math.max(1, parseInt(document.getElementById('pcsPoints').value, 10) || 1);
                    if (!action) {
                        showToast('Enter an action for this point.', 'error');
                        document.getElementById('pcsAction').focus();
                        return;
                    }
                    putBody = { timestamp_seconds: seconds, action, points, competitor };
                }

                if (saveBtn) saveBtn.disabled = true;
                try {
                    const res = await fetch(`/points/${existingId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify(putBody),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        showToast(data.message || 'Could not update point', 'error');
                        if (saveBtn) saveBtn.disabled = false;
                        return;
                    }
                    if (extraPost) {
                        const res2 = await fetch(`/videos/${videoId}/points`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                round_id: _pcbCtx.roundId,
                                timestamp_seconds: seconds,
                                action: extraPost.action,
                                points: extraPost.points,
                                competitor: extraPost.competitor,
                            }),
                        });
                        const data2 = await res2.json().catch(() => ({}));
                        if (!res2.ok || !data2.success) {
                            showToast(data2.message || 'Updated one side, but the second failed to save', 'error');
                            if (saveBtn) saveBtn.disabled = false;
                            loadMatchData();
                            return;
                        }
                    }
                    cancelPointCapture();
                    loadMatchData();
                } catch (e) {
                    showToast('Error: ' + e.message, 'error');
                    if (saveBtn) saveBtn.disabled = false;
                }
                return;
            }

            // Build one or two point payloads depending on competitor mode
            let payloads = [];
            if (competitor === 'both') {
                const blueAction = document.getElementById('pcsActionBlue').value.trim();
                const redAction  = document.getElementById('pcsActionRed').value.trim();
                const bluePts    = Math.max(1, parseInt(document.getElementById('pcsPointsBlue').value, 10) || 1);
                const redPts     = Math.max(1, parseInt(document.getElementById('pcsPointsRed').value, 10) || 1);
                if (!blueAction || !redAction) {
                    showToast('Enter an action for both Blue and Red.', 'error');
                    (blueAction ? document.getElementById('pcsActionRed') : document.getElementById('pcsActionBlue')).focus();
                    return;
                }
                payloads.push({ competitor: 'blue', action: blueAction, points: bluePts });
                payloads.push({ competitor: 'red',  action: redAction,  points: redPts  });
            } else {
                const action = document.getElementById('pcsAction').value.trim();
                const points = Math.max(1, parseInt(document.getElementById('pcsPoints').value, 10) || 1);
                if (!action) {
                    showToast('Enter an action for this point.', 'error');
                    document.getElementById('pcsAction').focus();
                    return;
                }
                payloads.push({ competitor, action, points });
            }

            const saveBtn = bar.querySelector('.pcs-btn-primary');
            if (saveBtn) saveBtn.disabled = true;

            try {
                for (const p of payloads) {
                    const res = await fetch(`/videos/${videoId}/points`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            round_id: roundId,
                            timestamp_seconds: seconds,
                            action: p.action,
                            points: p.points,
                            competitor: p.competitor,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        showToast(data.message || 'Could not save point', 'error');
                        if (saveBtn) saveBtn.disabled = false;
                        return;
                    }
                }
                cancelPointCapture();
                loadMatchData();
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
                if (saveBtn) saveBtn.disabled = false;
            }
        }
        // Frame rate assumed for match footage (SMPTE 30fps by default).
        // Set window.matchFPS from outside to override per video.
        window.matchFPS = window.matchFPS || 30;
        const FRAME_STEP = () => 1 / (window.matchFPS || 30);

        // Slow-mo replay speed — user-selectable via the picker in the sidebar
        // tab header. Read by playPointReplay and playReviewSlowmo.
        window.slowmoRate = window.slowmoRate || 0.5;
        // Hide the player's chrome when a sidebar card triggers playback. The
        // player's `pause` handler synchronously fires showControls(), and the
        // `play` handler resets a 3s hide timer that keeps the chrome up. To
        // beat that whole race we apply the hide over several ticks — mousemove
        // over the actual video still brings the controls back instantly.
        function _hidePlayerChrome() {
            const container = document.getElementById('videoContainer');
            if (!container) return;
            const hide = () => container.classList.add('controls-hidden');
            hide();
            [10, 60, 200, 500].forEach(ms => setTimeout(hide, ms));
        }
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.slowmo-opt');
            if (!btn) return;
            const rate = Number(btn.dataset.slowmo);
            if (!rate) return;
            window.slowmoRate = rate;
            document.querySelectorAll('.slowmo-opt').forEach(el => {
                el.classList.toggle('is-active', Number(el.dataset.slowmo) === rate);
            });
        });
        // Snap an arbitrary seconds value onto the nearest frame boundary
        function snapToFrame(totalSeconds) {
            const fps = window.matchFPS || 30;
            const frame = Math.round((Number(totalSeconds) || 0) * fps);
            return frame / fps;
        }
        // Broadcast MM:SS.FF (0-indexed frames within the second)
        function toMinuteSecondClock(totalSeconds) {
            const t = Math.max(0, Number(totalSeconds) || 0);
            const fps = window.matchFPS || 30;
            const totalFrames = Math.round(t * fps);
            const totalSecs   = Math.floor(totalFrames / fps);
            const frames      = totalFrames % fps;
            const m = Math.floor(totalSecs / 60);
            const s = totalSecs % 60;
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}.${String(frames).padStart(2, '0')}`;
        }

        // Edit a point inline in the capture strip — no popup.
        function editPoint(pointId) {
            if (isDemoMode || !csrfToken) { showToast('Demo Mode', 'info'); return; }
            const rounds = window.matchRounds || [];
            let point = null, roundRef = null;
            for (const r of rounds) {
                const p = (r.points || []).find(x => Number(x.id) === Number(pointId));
                if (p) { point = p; roundRef = r; break; }
            }
            if (!point || !roundRef) { showToast('Could not find that point — reload and try again.', 'error'); return; }
            beginPointCapture(roundRef.round_number, roundRef.id, point);
        }
        // Edit a grouped entry (Blue + Red at the same moment) — opens the strip
        // in Both mode with both sides pre-filled.
        function editPointGroup(...ids) {
            if (isDemoMode || !csrfToken) { showToast('Demo Mode', 'info'); return; }
            const rounds = window.matchRounds || [];
            let group = [], roundRef = null;
            for (const r of rounds) {
                const found = (r.points || []).filter(x => ids.map(Number).includes(Number(x.id)));
                if (found.length) { group = found; roundRef = r; break; }
            }
            if (!group.length || !roundRef) { showToast('Could not find that entry — reload and try again.', 'error'); return; }
            beginPointCapture(roundRef.round_number, roundRef.id, group);
        }
        // Delete both points in a grouped entry — routes through the shared
        // custom-confirm flow, deleting sequentially on confirm.
        function deletePointGroup(...ids) {
            if (isDemoMode || !csrfToken) { return; }
            const idList = ids.map(Number).filter(Boolean);
            if (!idList.length) return;
            // Reuse the delete-confirm modal but with our own executor
            window._pendingGroupDelete = idList;
            if (typeof requestDelete === 'function') {
                requestDelete('point group', 'group'); // uses the shared confirm; executeDelete handles 'group'
            } else {
                pcbDeleteGroupNow(idList);
            }
        }
        async function pcbDeleteGroupNow(idList) {
            for (const id of idList) {
                try { await pcbDeletePoint(id); } catch (e) { /* keep going */ }
            }
            loadMatchData();
        }
        // Fetch helpers used by the edit-group / group-delete flows
        async function pcbPutPoint(id, body) {
            const res = await fetch(`/points/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || 'Update failed');
            return data;
        }
        async function pcbDeletePoint(id) {
            const res = await fetch(`/points/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || 'Delete failed');
            return data;
        }

        // Delete the point that's currently being edited in the strip.
        async function pcbDeleteFromStrip() {
            if (!_pcbCtx || !_pcbCtx.existingId) return;
            if (!csrfToken) { cancelPointCapture(); return; }
            if (typeof requestDelete === 'function') {
                // Reuse the existing custom confirm flow, then close the strip on success
                const id = _pcbCtx.existingId;
                cancelPointCapture();
                requestDelete('point', id);
                return;
            }
            try {
                const res = await fetch(`/points/${_pcbCtx.existingId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    cancelPointCapture();
                    loadMatchData();
                } else {
                    showToast(data.message || 'Could not delete point', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }
        async function deletePoint(pointId) {
            requestDelete('point', pointId);
        }

        async function performDeletePoint(pointId) {
            if (!csrfToken) return;
            try {
                const res = await fetch(`/points/${pointId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    loadMatchData();
                } else showToast(data.message || 'Error', 'error');
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        // ── Coach-review capture strip ────────────────────────────────────
        // Twin of the point strip: pauses the video, hides the player's chrome,
        // shows an inline strip with two time slots (start + optional end), a
        // scrubber that drives whichever slot is active, plus note/coach/emoji.
        let _rcbCtx = null;
        let _rcbZoom = 1;
        function openAddReviewModal() { beginReviewCapture(); }  // legacy alias
        function beginReviewCapture(existing) {
            if (isDemoMode || !csrfToken) { showToast('Demo Mode', 'info'); return; }
            const video = document.getElementById('videoPlayer');
            const wrap  = document.getElementById('ytpWrap');
            const bar   = document.getElementById('reviewCaptureBar');
            if (!video || !wrap || !bar) return;

            try { video.pause(); } catch (_) {}

            // Placement: inside the player wrap on desktop / landscape,
            // BELOW the player on mobile portrait so the video stays visible.
            const useBelowR = _mobilePortraitStrip();
            if (bar.parentElement !== (useBelowR ? wrap.parentNode : wrap)) {
                bar._rcbOrigParent = bar.parentElement;
                bar._rcbOrigNext   = bar.nextSibling;
                if (useBelowR) {
                    wrap.parentNode.insertBefore(bar, wrap.nextSibling);
                    bar.classList.add('pcs-below');
                } else {
                    wrap.appendChild(bar);
                    bar.classList.remove('pcs-below');
                }
            } else if (!useBelowR) {
                bar.classList.remove('pcs-below');
            }
            wrap.setAttribute('data-pcb-active', '1');

            // Seek to existing start (if editing) so marker lands on the recorded moment
            if (existing && Number.isFinite(Number(existing.start_time_seconds))) {
                try { video.currentTime = Number(existing.start_time_seconds); } catch (_) {}
            }

            // Reset / seed fields
            const startInp = document.getElementById('rcbStart');
            const endInp   = document.getElementById('rcbEnd');
            const noteInp  = document.getElementById('rcbNote');
            const coachInp = document.getElementById('rcbCoach');
            const sliderS  = document.getElementById('rcbSliderStart');
            const sliderE  = document.getElementById('rcbSliderEnd');
            const fillEl   = document.getElementById('rcbRangeFill');
            const durEl    = document.getElementById('rcbDuration');

            noteInp.value  = existing ? (existing.note        || '') : '';
            coachInp.value = existing ? (existing.coach_name  || '') : '';
            _rcbSetEmoji(existing && existing.emoji ? existing.emoji : '🔥');

            const seedStart = existing ? Number(existing.start_time_seconds || 0) : Math.max(0, Math.round(video.currentTime || 0));
            const seedEnd   = existing && existing.end_time_seconds !== null && existing.end_time_seconds !== undefined
                                ? Number(existing.end_time_seconds)
                                : seedStart + 3;  // default to a 3s window when there's no end yet
            startInp.value = toMinuteSecondClock(seedStart);
            endInp.value   = toMinuteSecondClock(seedEnd);

            _rcbSetActiveSlot('start');

            // Zoom + slider wiring
            _rcbZoom = 1;
            _rcbSetZoomButtons(1);
            const wireDuration = () => {
                const d = Number(video.duration);
                if (isFinite(d) && d > 0) {
                    durEl.textContent = toMinuteSecondClock(d);
                    _rcbApplyZoomWindow();
                    _rcbRefreshFill();
                }
            };
            wireDuration();
            if (!(isFinite(video.duration) && video.duration > 0)) {
                video.addEventListener('loadedmetadata', wireDuration, { once: true });
            }
            _rcbApplyZoomWindow();
            sliderS.value = Math.max(Number(sliderS.min) || 0, Math.min(Number(sliderS.max) || 0, seedStart));
            sliderE.value = Math.max(Number(sliderE.min) || 0, Math.min(Number(sliderE.max) || 0, seedEnd));
            _rcbRefreshFill();

            // Which marker was touched last — nudge buttons act on it.
            let _rcbLastTouched = 'start';

            // Aggressive pause — beats HLS.js's async play state re-issuing on seek.
            const forcePauseR = () => {
                try { video.pause(); } catch (_) {}
                setTimeout(() => { try { video.pause(); } catch (_) {} }, 30);
            };
            const onSlideStart = () => {
                forcePauseR();
                let t = snapToFrame(Number(sliderS.value) || 0);
                const eNow = Number(sliderE.value) || 0;
                if (t > eNow) t = eNow;
                sliderS.value = t;
                _rcbLastTouched = 'start';
                _rcbSetActiveSlot('start');
                try { video.currentTime = t; } catch (_) {}
                if (document.activeElement !== startInp) startInp.value = toMinuteSecondClock(t);
                _rcbRefreshFill();
            };
            const onSlideEnd = () => {
                forcePauseR();
                let t = snapToFrame(Number(sliderE.value) || 0);
                const sNow = Number(sliderS.value) || 0;
                if (t < sNow) t = sNow;
                sliderE.value = t;
                _rcbLastTouched = 'end';
                _rcbSetActiveSlot('end');
                try { video.currentTime = t; } catch (_) {}
                if (document.activeElement !== endInp) endInp.value = toMinuteSecondClock(t);
                _rcbRefreshFill();
            };
            const onSlideCommit = () => { /* auto-recenter disabled — user zooms explicitly */ };
            const onVideoTime = () => {
                // When the video moves on its own (preview, external seek),
                // update the fill so the range visualization stays in sync.
                _rcbRefreshFill();
            };

            // Time input commit (parse and clamp both slots)
            const commitStart = () => { _rcbCommitTimeInput('start'); _rcbRefreshFill(); };
            const commitEnd   = () => { _rcbCommitTimeInput('end');   _rcbRefreshFill(); };
            _rcbCtxLastTouched = () => _rcbLastTouched;

            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); cancelReviewCapture(); return; }
                if (e.key === 'Enter' && e.target && bar.contains(e.target) &&
                    e.target.tagName === 'INPUT' && e.target.type !== 'range' &&
                    e.target !== startInp && e.target !== endInp) {
                    e.preventDefault();
                    confirmReviewCapture();
                }
            };
            const onTimeKey = (e, which) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    _rcbCommitTimeInput(which);
                    e.target.blur();
                }
            };

            sliderS.addEventListener('input',  onSlideStart);
            sliderE.addEventListener('input',  onSlideEnd);
            sliderS.addEventListener('change', onSlideCommit);
            sliderE.addEventListener('change', onSlideCommit);
            // Freeze the frame the moment either thumb is grabbed
            const onSliderGrabR = () => forcePauseR();
            [sliderS, sliderE].forEach(sl => {
                sl.addEventListener('mousedown',   onSliderGrabR);
                sl.addEventListener('pointerdown', onSliderGrabR);
                sl.addEventListener('touchstart',  onSliderGrabR, { passive: true });
            });
            video.addEventListener('timeupdate', onVideoTime);
            video.addEventListener('seeked',     onVideoTime);
            document.addEventListener('keydown', onKey);
            startInp.addEventListener('change', commitStart);
            startInp.addEventListener('blur',   commitStart);
            startInp.addEventListener('keydown', (e) => onTimeKey(e, 'start'));
            endInp.addEventListener('change', commitEnd);
            endInp.addEventListener('blur',   commitEnd);
            endInp.addEventListener('keydown', (e) => onTimeKey(e, 'end'));

            // Live preview of the note on the video + drag-to-position
            const overlay   = document.getElementById('coachNoteOverlay');
            const previewFn = () => rcbUpdateOverlayText();
            noteInp.addEventListener('input', previewFn);
            document.querySelectorAll('.rcb-emoji-btn').forEach(el => el.addEventListener('click', previewFn));
            rcbUpdateOverlayText();                 // seed initial text
            rcbEnableOverlayDrag(overlay, wrap);    // make it draggable
            // Click a slot to activate it — the scrubber updates that slot
            bar.querySelectorAll('.rcb-time-edit').forEach(el => {
                el._rcbClick = () => _rcbSetActiveSlot(el.dataset.rcbSlot);
                el.addEventListener('click', el._rcbClick);
            });

            // Show delete button in edit mode; adjust save copy
            const delBtn  = document.getElementById('rcbDeleteBtn');
            const saveBtn = bar.querySelector('.pcs-btn-primary');
            if (existing) {
                if (delBtn)  delBtn.hidden = false;
                if (saveBtn) { saveBtn.title = 'Update (Enter)'; saveBtn.querySelector('span').textContent = 'Update'; }
                wrap.setAttribute('data-rcb-mode', 'edit');
            } else {
                if (delBtn)  delBtn.hidden = true;
                if (saveBtn) { saveBtn.title = 'Save (Enter)';   saveBtn.querySelector('span').textContent = 'Save';   }
                wrap.removeAttribute('data-rcb-mode');
            }

            bar.hidden = false;
            setTimeout(() => {
                noteInp.focus();
                _scrollStripIntoView(bar);
            }, 30);

            _rcbCtx = { existingId: existing ? existing.id : null,
                        video, wrap, bar,
                        sliderS, sliderE, fillEl,
                        onSlideStart, onSlideEnd, onSlideCommit, onVideoTime, onKey,
                        onSliderGrabR,
                        // Exposed for rcbNudge / commitStart / commitEnd so
                        // every scrubber interaction freezes the frame — only
                        // an explicit play press should resume playback.
                        forcePause: forcePauseR,
                        startInp, endInp, commitStart, commitEnd,
                        noteInp, previewFn, overlay,
                        // Seed from existing position when editing, else default
                        overlayPos: (existing && existing.position_x != null && existing.position_y != null)
                                    ? { x: Number(existing.position_x), y: Number(existing.position_y) }
                                    : null };

            // Apply seeded position (edit-mode) after the strip is on screen
            if (_rcbCtx.overlayPos) rcbApplyOverlayPos(_rcbCtx.overlayPos);
        }
        // Live-update the overlay text from the current note + emoji fields
        function rcbUpdateOverlayText() {
            const overlay = document.getElementById('coachNoteOverlay');
            if (!overlay) return;
            const note  = (document.getElementById('rcbNote').value || '').trim();
            const emoji = document.getElementById('rcbEmoji').value || '';
            const text  = note ? (emoji ? emoji + '  ' + note : note) : '';
            overlay.textContent = text;
            if (text) overlay.classList.add('show'); else overlay.classList.remove('show');
        }
        // Apply a normalized {x,y} (0..1) representing the overlay's CENTER
        // as a proportion of the video wrap. Using percentages + translate(-50%)
        // means the position stays put on any resize / fullscreen.
        function rcbApplyOverlayPos(pos) {
            const overlay = document.getElementById('coachNoteOverlay');
            if (!overlay || !pos) return;
            overlay.classList.add('positioned');
            overlay.style.left = (pos.x * 100).toFixed(3) + '%';
            overlay.style.top  = (pos.y * 100).toFixed(3) + '%';
        }
        // Enable drag-to-position while composing / editing a note.
        // Track the CENTER of the overlay in normalized 0..1 coords so the
        // stored position scales with the video area on resize / fullscreen.
        function rcbEnableOverlayDrag(overlay, wrap) {
            if (!overlay || !wrap) return;
            overlay.classList.add('draggable');
            let dragging = false;
            // Offset from mouse to overlay CENTER at drag start
            let offCx = 0, offCy = 0;

            const onDown = (e) => {
                if (e.button !== undefined && e.button !== 0) return;
                dragging = true;
                overlay.classList.add('dragging');
                const pt = e.touches ? e.touches[0] : e;
                const rect = overlay.getBoundingClientRect();
                const cx = rect.left + rect.width  / 2;
                const cy = rect.top  + rect.height / 2;
                offCx = pt.clientX - cx;
                offCy = pt.clientY - cy;
                e.preventDefault();
            };
            const onMove = (e) => {
                if (!dragging) return;
                const pt = e.touches ? e.touches[0] : e;
                const wr = wrap.getBoundingClientRect();
                // Target CENTER position relative to wrap
                let cx = pt.clientX - offCx - wr.left;
                let cy = pt.clientY - offCy - wr.top;
                // Clamp so the overlay center can't leave the video area
                const halfW = overlay.offsetWidth  / 2;
                const halfH = overlay.offsetHeight / 2;
                cx = Math.max(halfW, Math.min(wr.width  - halfW, cx));
                cy = Math.max(halfH, Math.min(wr.height - halfH, cy));
                const nx = cx / wr.width;
                const ny = cy / wr.height;
                rcbApplyOverlayPos({ x: nx, y: ny });
                if (_rcbCtx) _rcbCtx.overlayPos = { x: nx, y: ny };
                e.preventDefault();
            };
            const onUp = () => {
                if (!dragging) return;
                dragging = false;
                overlay.classList.remove('dragging');
            };

            overlay._rcbDown  = onDown;
            overlay._rcbMove  = onMove;
            overlay._rcbUp    = onUp;
            overlay.addEventListener('mousedown',  onDown);
            overlay.addEventListener('touchstart', onDown, { passive: false });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('mouseup',   onUp);
            document.addEventListener('touchend',  onUp);
        }
        function rcbDisableOverlayDrag() {
            const overlay = document.getElementById('coachNoteOverlay');
            if (!overlay) return;
            overlay.classList.remove('draggable', 'dragging', 'positioned');
            overlay.style.left = '';
            overlay.style.top  = '';
            if (overlay._rcbDown) overlay.removeEventListener('mousedown',  overlay._rcbDown);
            if (overlay._rcbDown) overlay.removeEventListener('touchstart', overlay._rcbDown);
            if (overlay._rcbMove) document.removeEventListener('mousemove', overlay._rcbMove);
            if (overlay._rcbMove) document.removeEventListener('touchmove', overlay._rcbMove);
            if (overlay._rcbUp)   document.removeEventListener('mouseup',   overlay._rcbUp);
            if (overlay._rcbUp)   document.removeEventListener('touchend',  overlay._rcbUp);
            delete overlay._rcbDown; delete overlay._rcbMove; delete overlay._rcbUp;
        }
        function _rcbSetEmoji(emoji) {
            const hidden = document.getElementById('rcbEmoji');
            if (hidden) hidden.value = emoji;
            document.querySelectorAll('.rcb-emoji-btn').forEach(el => {
                el.classList.toggle('is-active', el.dataset.rcbEmoji === emoji);
            });
        }
        function _rcbSetActiveSlot(which) {
            document.getElementById('rcbActiveSlot').value = which;
            document.querySelectorAll('.rcb-time-edit').forEach(el => {
                el.classList.toggle('is-active', el.dataset.rcbSlot === which);
            });
            // Move the video to whatever that slot currently holds (if valid)
            const val = document.getElementById(which === 'end' ? 'rcbEnd' : 'rcbStart').value;
            const secs = parsePcbTimeInput(val);
            if (secs !== null && _rcbCtx && _rcbCtx.video) {
                // Switching the active slot re-scrubs the video — pause it
                // so the user doesn't unexpectedly resume playback.
                if (_rcbCtx.forcePause) _rcbCtx.forcePause();
                try { _rcbCtx.video.currentTime = secs; } catch (_) {}
                // Keep dual sliders in sync with the input value
                if (_rcbCtx.sliderS && which === 'start') _rcbCtx.sliderS.value = secs;
                if (_rcbCtx.sliderE && which === 'end')   _rcbCtx.sliderE.value = secs;
                _rcbRefreshFill();
            }
        }
        function _rcbSetActiveTime(clock) {
            const active = document.getElementById('rcbActiveSlot').value || 'start';
            const inp = document.getElementById(active === 'end' ? 'rcbEnd' : 'rcbStart');
            if (inp && document.activeElement !== inp) inp.value = clock;
        }
        function _rcbCommitTimeInput(which) {
            if (!_rcbCtx) return;
            const inp = document.getElementById(which === 'end' ? 'rcbEnd' : 'rcbStart');
            if (!inp) return;
            const raw = inp.value.trim();
            if (!raw && which === 'end') { return; } // end may be empty
            const secs = parsePcbTimeInput(raw);
            if (secs === null) {
                // invalid — revert to whichever is source-of-truth
                if (which === 'end') { inp.value = ''; return; }
                inp.value = toMinuteSecondClock(_rcbCtx.video.currentTime || 0);
                return;
            }
            // Typing a timestamp is a scrubber interaction — freeze the
            // frame. Playback resumes only on an explicit play press.
            if (_rcbCtx.forcePause) _rcbCtx.forcePause();
            const dur = Number(_rcbCtx.video.duration) || 0;
            const clamped = Math.max(0, dur > 0 ? Math.min(dur, secs) : secs);
            inp.value = toMinuteSecondClock(clamped);
            if ((document.getElementById('rcbActiveSlot').value || 'start') === which) {
                try { _rcbCtx.video.currentTime = clamped; } catch (_) {}
            }
            // Mirror the committed value into the corresponding scrubber thumb
            if (which === 'start' && _rcbCtx.sliderS) _rcbCtx.sliderS.value = clamped;
            if (which === 'end'   && _rcbCtx.sliderE) _rcbCtx.sliderE.value = clamped;
            _rcbRefreshFill();
        }
        function rcbClearEnd(e) {
            if (e) e.stopPropagation();
            const inp = document.getElementById('rcbEnd');
            if (inp) inp.value = '';
            _rcbSetActiveSlot('start');
        }
        // Preview the coach note by playing start → end, then pausing.
        // Click again to stop early. The overlay is already live-updating so the
        // user sees exactly what the viewer will see.
        function rcbTogglePreview() {
            if (!_rcbCtx) return;
            const btn = document.getElementById('rcbPreviewBtn');
            const video = _rcbCtx.video;
            if (!video || !btn) return;

            // Already playing preview → stop
            if (_rcbCtx.previewWatcher) {
                _rcbEndPreview();
                return;
            }

            // Commit any pending edits so we use the current typed values
            _rcbCommitTimeInput('start');
            _rcbCommitTimeInput('end');

            const startSecs = parsePcbTimeInput(document.getElementById('rcbStart').value);
            const endRaw    = document.getElementById('rcbEnd').value.trim();
            const endSecs   = endRaw ? parsePcbTimeInput(endRaw) : null;
            if (startSecs === null) { showToast('Set a start time first.', 'error'); return; }

            try { video.currentTime = Math.max(0, startSecs); } catch (_) {}

            const watcher = function () {
                if (endSecs !== null && video.currentTime >= endSecs) {
                    _rcbEndPreview();
                }
            };
            _rcbCtx.previewWatcher = watcher;
            if (endSecs !== null) video.addEventListener('timeupdate', watcher);

            const p = video.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});

            btn.classList.add('is-playing');
            btn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            btn.title = 'Stop preview';
        }
        function _rcbEndPreview() {
            if (!_rcbCtx) return;
            const video = _rcbCtx.video;
            const btn   = document.getElementById('rcbPreviewBtn');
            if (_rcbCtx.previewWatcher && video) {
                video.removeEventListener('timeupdate', _rcbCtx.previewWatcher);
            }
            _rcbCtx.previewWatcher = null;
            try { video.pause(); } catch (_) {}
            if (btn) {
                btn.classList.remove('is-playing');
                btn.innerHTML = '<i class="bi bi-play-fill"></i>';
                btn.title = 'Preview from Start → End';
            }
        }
        // Nudge the LAST-TOUCHED scrubber (start or end) by ±1 frame.
        function rcbNudge(deltaSeconds) {
            if (!_rcbCtx) return;
            const { video, sliderS, sliderE, startInp, endInp, forcePause } = _rcbCtx;
            // Freeze the frame — scrubber steps are for precise landing,
            // not incidental resume-playback triggers.
            if (forcePause) forcePause();
            const which = (typeof _rcbCtxLastTouched === 'function' ? _rcbCtxLastTouched() : 'start');
            const target   = which === 'end' ? sliderE : sliderS;
            const otherVal = Number(which === 'end' ? sliderS.value : sliderE.value) || 0;
            const dir  = deltaSeconds >= 0 ? 1 : -1;
            const step = FRAME_STEP() * dir;
            const dur = Number(video.duration) || 0;
            let next = snapToFrame(Math.max(0, Math.min(dur, (Number(target.value) || 0) + step)));
            // Enforce start <= end
            if (which === 'end'   && next < otherVal) next = otherVal;
            if (which === 'start' && next > otherVal) next = otherVal;
            try { video.currentTime = next; } catch (_) {}
            target.value = next;
            const inp = which === 'end' ? endInp : startInp;
            if (inp && document.activeElement !== inp) inp.value = toMinuteSecondClock(next);
            _rcbRefreshFill();
        }
        // Apply the current zoom window to BOTH scrubbers so they share a track
        function _rcbApplyZoomWindow() {
            const video = document.getElementById('videoPlayer');
            const sS = document.getElementById('rcbSliderStart');
            const sE = document.getElementById('rcbSliderEnd');
            if (!video || !sS || !sE) return;
            const dur = Number(video.duration) || 0;
            const apply = (mn, mx) => {
                sS.min = mn; sS.max = mx; sE.min = mn; sE.max = mx;
                // Always step by 1 frame — the scrubber is frame-accurate
                sS.step = FRAME_STEP(); sE.step = FRAME_STEP();
            };
            if (dur <= 0) { apply(0, 0); return; }
            const cur = Math.max(0, Math.min(dur, Number(video.currentTime) || 0));
            const winSize = Math.max(0.5, dur / (_rcbZoom || 1));
            let s = cur - winSize / 2, e = cur + winSize / 2;
            if (s < 0)   { e -= s; s = 0; }
            if (e > dur) { s -= (e - dur); e = dur; if (s < 0) s = 0; }
            apply(s, e);
            _rcbRefreshFill();
        }
        // Update the red bar between the two thumbs to match current values
        function _rcbRefreshFill() {
            const sS = document.getElementById('rcbSliderStart');
            const sE = document.getElementById('rcbSliderEnd');
            const fill = document.getElementById('rcbRangeFill');
            if (!sS || !sE || !fill) return;
            const mn = Number(sS.min) || 0, mx = Number(sS.max) || 0;
            const span = mx - mn || 1;
            const a = (Number(sS.value) - mn) / span;
            const b = (Number(sE.value) - mn) / span;
            const left  = Math.max(0, Math.min(1, Math.min(a, b))) * 100;
            const right = Math.max(0, Math.min(1, Math.max(a, b))) * 100;
            fill.style.left  = left + '%';
            fill.style.width = (right - left) + '%';
        }
        let _rcbCtxLastTouched = null;
        function _rcbSetZoomButtons(level) {
            document.querySelectorAll('.pcs-zoom-btn[data-rcb-zoom]').forEach(el => {
                el.classList.toggle('is-active', Number(el.dataset.rcbZoom) === Number(level));
            });
        }
        // Delegated click for review-strip zoom buttons + emoji buttons
        document.addEventListener('click', function (e) {
            const zoomBtn = e.target.closest('.pcs-zoom-btn[data-rcb-zoom]');
            if (zoomBtn) { _rcbZoom = Number(zoomBtn.dataset.rcbZoom) || 1; _rcbSetZoomButtons(_rcbZoom); _rcbApplyZoomWindow(); return; }
            const emojiBtn = e.target.closest('.rcb-emoji-btn');
            if (emojiBtn) { _rcbSetEmoji(emojiBtn.dataset.rcbEmoji); }
        });

        function cancelReviewCapture() {
            const bar = document.getElementById('reviewCaptureBar');
            if (bar) {
                bar.hidden = true;
                // Re-enable the primary button so the NEXT open works — otherwise
                // it stays disabled after a successful save.
                const sb = bar.querySelector('.pcs-btn-primary');
                if (sb) sb.disabled = false;
            }
            if (!_rcbCtx) return;
            const { video, wrap, sliderS, sliderE, startInp, endInp,
                    onSlideStart, onSlideEnd, onSlideCommit, onVideoTime, onKey,
                    onSliderGrabR,
                    commitStart, commitEnd, noteInp, previewFn, overlay } = _rcbCtx;

            // Stop any in-flight preview and reset the button
            _rcbEndPreview();

            // Tear down overlay live-preview + drag mode
            if (noteInp && previewFn) noteInp.removeEventListener('input', previewFn);
            document.querySelectorAll('.rcb-emoji-btn').forEach(el => previewFn && el.removeEventListener('click', previewFn));
            hideCoachNoteOverlay();
            rcbDisableOverlayDrag();
            if (sliderS) {
                sliderS.removeEventListener('input',  onSlideStart);
                sliderS.removeEventListener('change', onSlideCommit);
                if (onSliderGrabR) {
                    sliderS.removeEventListener('mousedown',   onSliderGrabR);
                    sliderS.removeEventListener('pointerdown', onSliderGrabR);
                    sliderS.removeEventListener('touchstart',  onSliderGrabR);
                }
            }
            if (sliderE) {
                sliderE.removeEventListener('input',  onSlideEnd);
                sliderE.removeEventListener('change', onSlideCommit);
                if (onSliderGrabR) {
                    sliderE.removeEventListener('mousedown',   onSliderGrabR);
                    sliderE.removeEventListener('pointerdown', onSliderGrabR);
                    sliderE.removeEventListener('touchstart',  onSliderGrabR);
                }
            }
            video.removeEventListener('timeupdate', onVideoTime);
            video.removeEventListener('seeked',     onVideoTime);
            document.removeEventListener('keydown', onKey);
            startInp.removeEventListener('change', commitStart);
            startInp.removeEventListener('blur',   commitStart);
            endInp.removeEventListener('change', commitEnd);
            endInp.removeEventListener('blur',   commitEnd);
            _rcbCtxLastTouched = null;
            bar.querySelectorAll('.rcb-time-edit').forEach(el => {
                if (el._rcbClick) { el.removeEventListener('click', el._rcbClick); delete el._rcbClick; }
            });

            wrap.removeAttribute('data-pcb-active');
            wrap.removeAttribute('data-rcb-mode');
            if (bar._rcbOrigParent) {
                if (bar._rcbOrigNext && bar._rcbOrigNext.parentNode === bar._rcbOrigParent) {
                    bar._rcbOrigParent.insertBefore(bar, bar._rcbOrigNext);
                } else {
                    bar._rcbOrigParent.appendChild(bar);
                }
                delete bar._rcbOrigParent; delete bar._rcbOrigNext;
            }
            _rcbCtx = null;
            _stripClosed();
        }

        async function confirmReviewCapture() {
            if (!_rcbCtx) return;
            if (!csrfToken) { cancelReviewCapture(); return; }

            const { existingId, bar } = _rcbCtx;
            // Commit any pending edits in the time inputs first
            _rcbCommitTimeInput('start');
            _rcbCommitTimeInput('end');

            const startSecs = parsePcbTimeInput(document.getElementById('rcbStart').value);
            const endRaw    = document.getElementById('rcbEnd').value.trim();
            const endSecs   = endRaw ? parsePcbTimeInput(endRaw) : null;
            const note      = document.getElementById('rcbNote').value.trim();
            const coach     = document.getElementById('rcbCoach').value.trim();
            const emoji     = document.getElementById('rcbEmoji').value || '🔥';

            if (startSecs === null) { showToast('Enter a valid start time.', 'error'); document.getElementById('rcbStart').focus(); return; }
            if (endRaw && endSecs === null) { showToast('End time is invalid.', 'error'); document.getElementById('rcbEnd').focus(); return; }
            if (endSecs !== null && endSecs < startSecs) { showToast('End must be after start.', 'error'); document.getElementById('rcbEnd').focus(); return; }
            if (!note) { showToast('Enter a note.', 'error'); document.getElementById('rcbNote').focus(); return; }
            if (!coach) { showToast('Enter a coach name.', 'error'); document.getElementById('rcbCoach').focus(); return; }

            const payload = { start_seconds: startSecs, end_seconds: endSecs, note, coach_name: coach, emoji };
            if (_rcbCtx.overlayPos) {
                payload.position_x = Number(_rcbCtx.overlayPos.x.toFixed(4));
                payload.position_y = Number(_rcbCtx.overlayPos.y.toFixed(4));
            }
            const saveBtn = bar.querySelector('.pcs-btn-primary');
            if (saveBtn) saveBtn.disabled = true;

            try {
                const uuid   = existingId ? window.TOB.noteUuid(existingId) : null;
                const url    = uuid ? window.TOB.note(uuid) : window.TOB.notes;
                const method = uuid ? 'PUT' : 'POST';
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    showToast(data.message || 'Could not save note', 'error');
                    if (saveBtn) saveBtn.disabled = false;
                    return;
                }
                cancelReviewCapture();
                loadMatchData();
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
                if (saveBtn) saveBtn.disabled = false;
            }
        }

        async function rcbDeleteFromStrip() {
            if (!_rcbCtx || !_rcbCtx.existingId) return;
            const id = _rcbCtx.existingId;
            cancelReviewCapture();
            requestDelete('review', id);
        }

        // Open the capture strip for an existing review (looked up in memory).
        function editReview(reviewId) {
            if (isDemoMode || !csrfToken) { showToast('Demo Mode', 'info'); return; }
            const reviews = window.matchReviews || [];
            const r = reviews.find(x => Number(x.id) === Number(reviewId));
            if (!r) { showToast('Could not find that note — reload and try again.', 'error'); return; }
            beginReviewCapture(r);
        }

        // The ONE way a coach note is played — desktop pane, desktop fullscreen
        // drawer, portrait Highlights and the mobile fullscreen drawer all come
        // through here, so a note shows and clears identically everywhere.
        function playCoachReview(reviewId, fallbackText, fallbackStart) {
            const video = document.getElementById('videoPlayer');
            if (!video) return;
            const rev = (window.matchReviews || []).find(r => Number(r.id) === Number(reviewId));
            const start = rev ? Number(rev.start_time_seconds)
                              : (Number.isFinite(Number(fallbackStart)) ? Number(fallbackStart) : NaN);
            const end   = rev && rev.end_time_seconds != null ? Number(rev.end_time_seconds) : null;

            // Nothing from a previous tap may stay on screen.
            clearReviewPlaybackHandler(video);
            _stopPointReplay();
            _stopReviewSlowmo();
            hideCoachNoteOverlay();
            video.playbackRate = 1;

            // Ranged note: its own replay paints and clears the overlay.
            if (rev && Number.isFinite(start) && Number.isFinite(end) && end > start) {
                playReviewSlowmo(reviewId);
                return;
            }

            const text = rev
                ? ((rev.emoji ? rev.emoji + '  ' : '') + (rev.note || ''))
                : (fallbackText || '');
            const pos = (rev && rev.position_x != null && rev.position_y != null)
                ? { x: Number(rev.position_x), y: Number(rev.position_y) } : null;
            const from = Number.isFinite(start) ? Math.max(0, start) : video.currentTime;

            if (text) showCoachNoteOverlay(text, pos);
            try { video.currentTime = from; } catch (_) {}

            // No end time — give the note a window, then take it away.
            const until = from + COACH_NOTE_FALLBACK_SECS;
            const watcher = function () {
                if (video.currentTime >= from - 0.75 && video.currentTime < until) return;
                hideCoachNoteOverlay();
            };
            _coachNoteWatcher = watcher;
            video.addEventListener('timeupdate', watcher);
            const p = video.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});
            _hidePlayerChrome();
        }

        // Play a coach note twice: first at normal speed, then at 0.5×, then stop.
        // Requires an end_time — the small ▶+⏳ button is only rendered when
        // a range is set on the review.
        let _reviewSlowmoWatcher = null;
        function playReviewSlowmo(reviewId, event) {
            if (event) event.stopPropagation();
            const video = document.getElementById('videoPlayer');
            if (!video) return;
            const rev = (window.matchReviews || []).find(r => Number(r.id) === Number(reviewId));
            if (!rev) return;
            const start = Number(rev.start_time_seconds);
            const end   = rev.end_time_seconds != null ? Number(rev.end_time_seconds) : null;
            if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return;

            // Cancel any currently-running review playback so states don't overlap
            clearReviewPlaybackHandler(video);
            _stopReviewSlowmo();

            // Show the note overlay at its saved position, if any
            const pos = (rev.position_x != null && rev.position_y != null)
                ? { x: Number(rev.position_x), y: Number(rev.position_y) } : null;
            const emojiPrefix = rev.emoji ? rev.emoji + '  ' : '';
            if (rev.note) showCoachNoteOverlay(emojiPrefix + rev.note, pos);

            const btn = document.querySelector(`.review-slowmo-btn[onclick*="playReviewSlowmo(${reviewId}"]`);
            if (btn) btn.classList.add('is-playing');

            let phase = 'normal';                // 'normal' → 'slowmo' → 'done'
            video.playbackRate = 1;
            try { video.currentTime = Math.max(0, start); } catch (_) {}
            _setReplayBadge('1', 'normal');

            const watcher = function () {
                if (video.currentTime < end) return;
                if (phase === 'normal') {
                    phase = 'slowmo';
                    const rate = Number(window.slowmoRate) || 0.5;
                    video.playbackRate = rate;
                    try { video.currentTime = Math.max(0, start); } catch (_) {}
                    _setReplayBadge(String(rate), 'slowmo');
                } else {
                    _stopReviewSlowmo(btn);
                }
            };
            _reviewSlowmoWatcher = { fn: watcher, btn };
            video.addEventListener('timeupdate', watcher);
            const p = video.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});
            _hidePlayerChrome();
        }
        function _stopReviewSlowmo(btn) {
            const video = document.getElementById('videoPlayer');
            if (_reviewSlowmoWatcher) {
                if (video) video.removeEventListener('timeupdate', _reviewSlowmoWatcher.fn);
                if (_reviewSlowmoWatcher.btn) _reviewSlowmoWatcher.btn.classList.remove('is-playing');
                _reviewSlowmoWatcher = null;
            }
            if (btn) btn.classList.remove('is-playing');
            if (video) {
                try { video.pause(); } catch (_) {}
                video.playbackRate = 1;
            }
            _setReplayBadge(null);
            hideCoachNoteOverlay();
        }

        // Point replay: play a short clip around the point at 1×, replay at
        // 0.5×, then RESUME normal playback. A sports-broadcast REPLAY badge
        // sits on the video, flipping from red (×1) to blue (SLOW-MO ×0.5).
        let _pointReplayWatcher = null;
        function _setReplayBadge(speed, mode) {
            const badge = document.getElementById('replayBadge');
            const speedEl = document.getElementById('replayBadgeSpeed');
            if (!badge || !speedEl) return;
            if (!speed) { badge.hidden = true; badge.classList.remove('is-slowmo'); return; }
            badge.hidden = false;
            badge.classList.toggle('is-slowmo', mode === 'slowmo');
            speedEl.textContent = '×' + speed;
        }
        function playPointReplay(pointTimeSec) {
            const video = document.getElementById('videoPlayer');
            if (!video || !Number.isFinite(pointTimeSec)) return;

            const preroll  = 1.5;
            const postroll = 2.5;
            const start = Math.max(0, pointTimeSec - preroll);
            const end   = pointTimeSec + postroll;

            _stopPointReplay();          // cancel any prior replay
            // A coach note still on screen would sit over the replay — take it
            // away, whichever view the point was tapped in.
            _stopReviewSlowmo();
            hideCoachNoteOverlay();
            video.playbackRate = 1;
            try { video.currentTime = start; } catch (_) {}
            _setReplayBadge('1', 'normal');

            let phase = 'normal'; // normal → slowmo → done (resume normal)
            const watcher = function () {
                if (video.currentTime < end) return;
                if (phase === 'normal') {
                    phase = 'slowmo';
                    const rate = Number(window.slowmoRate) || 0.5;
                    video.playbackRate = rate;
                    try { video.currentTime = start; } catch (_) {}
                    _setReplayBadge(String(rate), 'slowmo');
                } else if (phase === 'slowmo') {
                    phase = 'done';
                    video.playbackRate = 1;
                    video.removeEventListener('timeupdate', watcher);
                    _pointReplayWatcher = null;
                    _setReplayBadge(null); // hide when done — resume normal
                }
            };
            _pointReplayWatcher = watcher;
            video.addEventListener('timeupdate', watcher);
            const p = video.play();
            if (p && typeof p.catch === 'function') p.catch(() => {});
            _hidePlayerChrome();
        }
        function _stopPointReplay() {
            const video = document.getElementById('videoPlayer');
            if (_pointReplayWatcher && video) {
                video.removeEventListener('timeupdate', _pointReplayWatcher);
            }
            _pointReplayWatcher = null;
            if (video) video.playbackRate = 1;
            _setReplayBadge(null);
        }
        async function deleteReview(reviewId) {
            requestDelete('review', reviewId);
        }

        async function performDeleteReview(reviewId) {
            if (!csrfToken) return;
            try {
                const uuid = window.TOB.noteUuid(reviewId);
                if (!uuid) { showToast('@lang('shared.something_went_wrong')', 'error'); return; }
                const res = await fetch(window.TOB.note(uuid), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    loadMatchData();
                } else showToast(data.message || 'Error', 'error');
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

    </script>
<script>
/*
 * Tabs, across the three panes that have them.
 *
 * Two jobs, both small:
 *
 *   • Move the one comment module into the comments slot of whichever pane the
 *     reader is looking at. Appending a node re-parents it — listeners, scroll
 *     position and ids all survive, which is why there is one module and not
 *     three.
 *
 *   • Keep a control that is NOT a tab out of the tab handlers. The design's
 *     own handlers act on any .tab-button / .fsh-tab that is clicked, so an
 *     "+ Note" button wearing that class blanked the pane. They are guarded
 *     here, in the capture phase, so the check happens before those handlers
 *     see the event rather than being duplicated inside each of them.
 */
(function () {
    if (window.__boutTabsInit) return;
    window.__boutTabsInit = true;

    function mount(slot) {
        var sec = document.getElementById('ytcSection');
        if (sec && slot && sec.parentElement !== slot) slot.appendChild(sec);
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-tab], [data-mrv-tab], [data-fsh-tab]');
        if (!t) return;
        var name = t.dataset.tab || t.dataset.mrvTab || t.dataset.fshTab;
        if (name !== 'comments') return;
        // The pane this tab belongs to owns the slot the module goes into.
        var pane = t.closest('.tab-header, .mrv-tabs, .fsh-head');
        var root = pane ? pane.parentElement : document;
        // The handler that reveals the panel runs after this one, so wait a
        // frame before measuring anything that depends on it being visible.
        requestAnimationFrame(function () {
            mount(root.querySelector('[data-bout-comments-slot]'));
        });
    });

    // A button in a tab row that carries no tab name is an action, not a tab.
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.tab-button, .fsh-tab, .mrv-tab');
        if (!b) return;
        if (b.dataset.tab || b.dataset.mrvTab || b.dataset.fshTab) return;
        e.stopPropagation();
    }, true);
})();
</script>

    </main>

    <!-- Upload Modal -->
    
    <!-- Share Modal - Available on all pages -->
    <!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding: 20px 24px;">
                <h5 class="modal-title" id="shareModalLabel" style="font-weight: 600; color: var(--text-primary);">
                    <i class="bi bi-share me-2"></i>Share
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <p style="color: var(--text-secondary); margin-bottom: 16px;">Share this link with your friends:</p>
                
                <div class="share-link-container" style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" id="shareLinkInput" class="form-control" readonly 
                           style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 12px 16px; border-radius: 8px;">
                    <button type="button" id="copyLinkBtn" class="btn-copy action-btn action-btn-primary">
                        <i class="bi bi-clipboard"></i> <span>Copy</span>
                    </button>
                </div>
                
                <div id="copySuccess" class="copy-success" style="display: none; margin-top: 12px; color: #4caf50; font-size: 14px; text-align: center;">
                    <i class="bi bi-check-circle-fill me-1"></i> Link copied to clipboard!
                </div>
                
                <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">Share on social media:</p>
                    <div style="display: flex; gap: 12px;">
                        <a href="#" id="shareFacebook" class="social-share-btn" target="_blank" 
                           style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #1877f2; color: white; text-decoration: none;">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" id="shareTwitter" class="social-share-btn" target="_blank"
                           style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #1da1f2; color: white; text-decoration: none;">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="#" id="shareWhatsApp" class="social-share-btn" target="_blank"
                           style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #25d366; color: white; text-decoration: none;">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <a href="#" id="shareTelegram" class="social-share-btn" target="_blank"
                           style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #0088cc; color: white; text-decoration: none;">
                            <i class="bi bi-telegram"></i>
                        </a>
                        <a href="#" id="shareEmailBtn" class="social-share-btn" role="button"
                           style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #6b7280; color: white; text-decoration: none;">
                            <i class="bi bi-envelope-fill"></i>
                        </a>
                        <a href="#" id="shareMembersBtn" class="social-share-btn" role="button" title="Share with members"
                           style="display: none; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #e61e1e; color: white; text-decoration: none;">
                            <i class="bi bi-people-fill"></i>
                        </a>
                    </div>
                </div>

                
                <div id="shareEmailSection" style="display:none; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">
                        <i class="bi bi-envelope me-1"></i> Send to a friend by email:
                    </p>
                    <input type="email" id="shareEmailTo" class="form-control" placeholder="friend@example.com" autocomplete="off"
                           style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: 8px;">
                    <textarea id="shareEmailMsg" class="form-control" rows="2" maxlength="500" placeholder="Add a short message (optional)"
                           style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: 8px; margin-top: 8px; resize: vertical;"></textarea>
                    <button type="button" id="shareEmailSend" class="action-btn action-btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                        <i class="bi bi-send"></i> <span>Send email</span>
                    </button>
                    <div id="shareEmailStatus" style="display:none; margin-top:10px; font-size:13px; text-align:center;"></div>
                </div>

                
                <div id="shareMembersSection" style="display:none; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 12px;">
                        <i class="bi bi-people me-1"></i> Search members and share — they get a notification and an email:
                    </p>
                    <div style="position: relative;">
                        <input type="text" id="shareMemberSearch" class="form-control" placeholder="Search members by name…" autocomplete="off"
                               style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: 8px;">
                        <div id="shareMemberResults" class="share-member-results" style="display:none;"></div>
                    </div>
                    <div id="shareMemberChips" class="share-member-chips"></div>
                    <textarea id="shareMemberMsg" class="form-control" rows="2" maxlength="500" placeholder="Add a short message (optional)"
                              style="background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 14px; border-radius: 8px; margin-top: 10px; resize: vertical;"></textarea>
                    <button type="button" id="shareMemberSend" class="action-btn action-btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                        <i class="bi bi-send"></i> <span>Send to members</span>
                    </button>
                    <div id="shareMemberStatus" style="display:none; margin-top:10px; font-size:13px; text-align:center;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.social-share-btn {
    transition: transform 0.2s, opacity 0.2s;
}
.social-share-btn:hover {
    transform: scale(1.1);
    opacity: 0.9;
}
.btn-copy.copied {
    background: #4caf50 !important;
    border-color: #4caf50 !important;
}
/* Member share picker */
.share-member-results {
    position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 20;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,.5);
    max-height: 240px; overflow-y: auto; padding: 4px;
}
.share-member-opt {
    display: flex; align-items: center; gap: 10px; padding: 8px 10px;
    border-radius: 6px; cursor: pointer;
}
.share-member-opt:hover { background: rgba(255,255,255,.06); }
.share-member-opt img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.share-member-opt .smo-name { color: var(--text-primary); font-size: 14px; font-weight: 600; line-height: 1.2; }
.share-member-opt .smo-handle { color: var(--text-secondary); font-size: 12px; }
.share-member-empty { padding: 10px; color: var(--text-secondary); font-size: 13px; text-align: center; }
.share-member-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.share-member-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 4px;
    background: rgba(230,30,30,.12); border: 1px solid rgba(230,30,30,.3);
    border-radius: 20px; color: var(--text-primary); font-size: 13px;
}
.share-member-chip img { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.share-member-chip button { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0 2px; line-height: 1; }
.share-member-chip button:hover { color: #e61e1e; }
</style>

<script>
function _getLatestCsrf() {
    var match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    return (typeof csrf !== 'undefined') ? csrf : '';
}

// Set per-open so the "Send by email" form knows the endpoint + which version to send.
var _shareEmailUrl   = '';
var _shareMembersUrl = '';
var _shareTrack      = '';

async function openShareModal(videoUrl, videoTitle, recordUrl, emailUrl, membersUrl) {
    var csrfToken = _getLatestCsrf();
    var shareUrl  = videoUrl;

    // Preserve the version selector (?track=) from the requested URL — the server's tracked
    // share link replaces shareUrl below, so we re-attach it afterwards.
    var trackParam = '';
    try { trackParam = (new URL(videoUrl, window.location.origin)).searchParams.get('track') || ''; } catch (e) {}
    _shareEmailUrl   = emailUrl || '';
    _shareMembersUrl = membersUrl || '';
    _shareTrack      = trackParam;

    // Obtain a unique tracked share link from the server
    if (recordUrl) {
        try {
            var res = await fetch(recordUrl, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':     csrfToken,
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                var data = await res.json();
                if (data.url) shareUrl = data.url;
            }
        } catch (e) { /* fallback to plain URL */ }
    }

    // Re-attach the version selector so the recipient opens the right language.
    if (trackParam) {
        shareUrl += (shareUrl.indexOf('?') === -1 ? '?' : '&') + 'track=' + encodeURIComponent(trackParam);
    }

    // Mobile: use native share sheet with the unique link
    if (window.innerWidth <= 768 && navigator.share) {
        navigator.share({ title: videoTitle, url: shareUrl }).catch(function() {});
        return;
    }

    // Desktop: show modal
    _populateShareModal(shareUrl, videoTitle);
    var modal = new bootstrap.Modal(document.getElementById('shareModal'), { backdrop: true, keyboard: true });
    modal.show();
}

function _populateShareModal(shareUrl, videoTitle) {
    document.getElementById('shareLinkInput').value = shareUrl;

    var encodedUrl   = encodeURIComponent(shareUrl);
    var encodedTitle = encodeURIComponent(videoTitle);
    var waText       = encodeURIComponent(videoTitle + '\n' + shareUrl);

    document.getElementById('shareFacebook').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl;
    document.getElementById('shareTwitter').href  = 'https://twitter.com/intent/tweet?url=' + encodedUrl + '&text=' + encodedTitle;
    document.getElementById('shareWhatsApp').href = 'https://wa.me/?text=' + waText;
    document.getElementById('shareTelegram').href = 'https://t.me/share/url?url=' + encodedUrl + '&text=' + encodedTitle;

    var copyBtn = document.getElementById('copyLinkBtn');
    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> <span>Copy</span>';
    copyBtn.classList.remove('copied');
    document.getElementById('copySuccess').style.display = 'none';

    // Reset the email form; the envelope button only works when an endpoint was provided.
    var emailBtn = document.getElementById('shareEmailBtn');
    var emailSec = document.getElementById('shareEmailSection');
    if (emailBtn) emailBtn.style.display = _shareEmailUrl ? 'flex' : 'none';
    if (emailSec) {
        emailSec.style.display = 'none';
        var to = document.getElementById('shareEmailTo');   if (to) to.value = '';
        var msg = document.getElementById('shareEmailMsg'); if (msg) msg.value = '';
        var st = document.getElementById('shareEmailStatus'); if (st) st.style.display = 'none';
    }

    // Reset the members picker; the people button only works when an endpoint was provided.
    var membersBtn = document.getElementById('shareMembersBtn');
    var membersSec = document.getElementById('shareMembersSection');
    if (membersBtn) membersBtn.style.display = _shareMembersUrl ? 'flex' : 'none';
    if (typeof _resetMembersPicker === 'function') _resetMembersPicker();
    if (membersSec) membersSec.style.display = 'none';
}

function _copyToClipboard(text) {
    // Prefer modern clipboard API (requires HTTPS)
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    // Textarea fallback — works even inside Bootstrap modals
    return new Promise(function(resolve, reject) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        ok ? resolve() : reject();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var copyBtn     = document.getElementById('copyLinkBtn');
    var shareInput  = document.getElementById('shareLinkInput');
    var copySuccess = document.getElementById('copySuccess');

    if (!copyBtn || !shareInput) return;

    copyBtn.addEventListener('click', function() {
        _copyToClipboard(shareInput.value).then(function() {
            copyBtn.innerHTML = '<i class="bi bi-check-lg"></i> <span>Copied!</span>';
            copyBtn.classList.add('copied');
            copySuccess.style.display = 'block';
            setTimeout(function() {
                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> <span>Copy</span>';
                copyBtn.classList.remove('copied');
                copySuccess.style.display = 'none';
            }, 2500);
        }).catch(function() {
            showToast('Could not copy — please copy the link manually.', 'error');
        });
    });

    // ── Send by email ──────────────────────────────────────────────
    var emailBtn   = document.getElementById('shareEmailBtn');
    var emailSec   = document.getElementById('shareEmailSection');
    var emailTo    = document.getElementById('shareEmailTo');
    var emailMsg   = document.getElementById('shareEmailMsg');
    var emailSend  = document.getElementById('shareEmailSend');
    var emailStat  = document.getElementById('shareEmailStatus');

    if (emailBtn && emailSec) {
        emailBtn.addEventListener('click', function(e) {
            e.preventDefault();
            emailSec.style.display = (emailSec.style.display === 'none' || !emailSec.style.display) ? 'block' : 'none';
            if (emailSec.style.display === 'block' && emailTo) emailTo.focus();
        });
    }

    function _showEmailStatus(msg, color) {
        if (!emailStat) return;
        emailStat.textContent = msg;
        emailStat.style.color = color;
        emailStat.style.display = 'block';
    }

    if (emailSend) {
        emailSend.addEventListener('click', function() {
            var to = (emailTo && emailTo.value || '').trim();
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)) {
                _showEmailStatus('Please enter a valid email address.', '#ef4444');
                if (emailTo) emailTo.focus();
                return;
            }
            if (!_shareEmailUrl) { _showEmailStatus('Email sharing is unavailable here.', '#ef4444'); return; }

            emailSend.disabled = true;
            var _orig = emailSend.innerHTML;
            emailSend.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Sending…</span>';
            _showEmailStatus('Sending…', 'var(--text-secondary)');

            var body = new URLSearchParams({
                _token:  window.TOB.csrf,
                email:   to,
                message: (emailMsg && emailMsg.value || ''),
                track:   _shareTrack || '0',
            });

            fetch(_shareEmailUrl, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':     _getLatestCsrf(),
                    'Accept':           'application/json',
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            })
            .then(function(r) { return r.json().then(function(d){ return { ok: r.ok, d: d }; }); })
            .then(function(res) {
                emailSend.disabled = false;
                emailSend.innerHTML = _orig;
                if (res.ok && res.d.success) {
                    _showEmailStatus('Sent! Your friend will get the email shortly.', '#4caf50');
                    if (emailTo) emailTo.value = '';
                    if (emailMsg) emailMsg.value = '';
                    setTimeout(function(){ if (emailSec) emailSec.style.display = 'none'; }, 1800);
                } else {
                    _showEmailStatus((res.d && res.d.error) || 'Could not send the email. Please try again.', '#ef4444');
                }
            })
            .catch(function() {
                emailSend.disabled = false;
                emailSend.innerHTML = _orig;
                _showEmailStatus('Network error — please try again.', '#ef4444');
            });
        });
    }

    // ── Share with members ─────────────────────────────────────────
    var membersBtn    = document.getElementById('shareMembersBtn');
    var membersSec    = document.getElementById('shareMembersSection');
    var memberSearch  = document.getElementById('shareMemberSearch');
    var memberResults = document.getElementById('shareMemberResults');
    var memberChips   = document.getElementById('shareMemberChips');
    var memberMsg     = document.getElementById('shareMemberMsg');
    var memberSend    = document.getElementById('shareMemberSend');
    var memberStatus  = document.getElementById('shareMemberStatus');
    var _selectedMembers = [];
    var _searchTimer = null;

    function escAttr(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    window._resetMembersPicker = function () {
        _selectedMembers = [];
        if (memberSearch) memberSearch.value = '';
        if (memberMsg) memberMsg.value = '';
        if (memberResults) { memberResults.style.display = 'none'; memberResults.innerHTML = ''; }
        if (memberStatus) memberStatus.style.display = 'none';
        _renderChips();
    };

    function _showMemberStatus(msg, color) {
        if (!memberStatus) return;
        memberStatus.textContent = msg; memberStatus.style.color = color; memberStatus.style.display = 'block';
    }

    function _renderChips() {
        if (!memberChips) return;
        memberChips.innerHTML = _selectedMembers.map(function (u) {
            return '<span class="share-member-chip">'
                 + '<img src="' + escAttr(u.avatar) + '" alt="">'
                 + escAttr(u.name)
                 + '<button type="button" data-id="' + u.id + '" aria-label="Remove"><i class="bi bi-x-lg"></i></button>'
                 + '</span>';
        }).join('');
        memberChips.querySelectorAll('button[data-id]').forEach(function (b) {
            b.addEventListener('click', function () {
                _selectedMembers = _selectedMembers.filter(function (u) { return String(u.id) !== b.getAttribute('data-id'); });
                _renderChips();
            });
        });
    }

    function _renderResults(users) {
        if (!memberResults) return;
        var available = users.filter(function (u) { return !_selectedMembers.some(function (s) { return s.id === u.id; }); });
        if (!available.length) {
            memberResults.innerHTML = '<div class="share-member-empty">No members found</div>';
        } else {
            memberResults.innerHTML = available.map(function (u) {
                return '<div class="share-member-opt" data-id="' + u.id + '">'
                     + '<img src="' + escAttr(u.avatar) + '" alt="">'
                     + '<div><div class="smo-name">' + escAttr(u.name) + '</div>'
                     + '<div class="smo-handle">@' + escAttr(u.channel) + '</div></div>'
                     + '</div>';
            }).join('');
            memberResults.querySelectorAll('.share-member-opt').forEach(function (el) {
                el.addEventListener('click', function () {
                    var id = parseInt(el.getAttribute('data-id'), 10);
                    var u = available.find(function (x) { return x.id === id; });
                    if (u && !_selectedMembers.some(function (s) { return s.id === u.id; })) {
                        _selectedMembers.push(u);
                        _renderChips();
                    }
                    memberSearch.value = '';
                    memberResults.style.display = 'none';
                    memberSearch.focus();
                });
            });
        }
        memberResults.style.display = 'block';
    }

    if (membersBtn && membersSec) {
        membersBtn.addEventListener('click', function (e) {
            e.preventDefault();
            membersSec.style.display = (membersSec.style.display === 'none' || !membersSec.style.display) ? 'block' : 'none';
            if (membersSec.style.display === 'block' && memberSearch) memberSearch.focus();
        });
    }

    if (memberSearch) {
        memberSearch.addEventListener('input', function () {
            var q = memberSearch.value.trim();
            clearTimeout(_searchTimer);
            if (!q) { memberResults.style.display = 'none'; return; }
            _searchTimer = setTimeout(function () {
                if (memberResults) memberResults.style.display = 'none';
            }, 250);
        });
        document.addEventListener('click', function (e) {
            if (memberResults && !memberResults.contains(e.target) && e.target !== memberSearch) {
                memberResults.style.display = 'none';
            }
        });
    }

    if (memberSend) {
        memberSend.addEventListener('click', function () {
            if (!_shareMembersUrl) { _showMemberStatus('Member sharing is unavailable here.', '#ef4444'); return; }
            if (!_selectedMembers.length) { _showMemberStatus('Select at least one member.', '#ef4444'); return; }

            memberSend.disabled = true;
            var _orig = memberSend.innerHTML;
            memberSend.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Sending…</span>';
            _showMemberStatus('Sending…', 'var(--text-secondary)');

            var body = new URLSearchParams();
            body.append('_token', window.TOB.csrf);
            body.append('message', (memberMsg && memberMsg.value) || '');
            _selectedMembers.forEach(function (u) { body.append('user_ids[]', u.id); });

            fetch(_shareMembersUrl, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':     _getLatestCsrf(),
                    'Accept':           'application/json',
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                memberSend.disabled = false;
                memberSend.innerHTML = _orig;
                if (res.ok && res.d.success) {
                    _showMemberStatus('Shared with ' + (res.d.count || _selectedMembers.length) + ' member(s) — notification + email sent.', '#4caf50');
                    _selectedMembers = []; _renderChips();
                    if (memberMsg) memberMsg.value = '';
                    setTimeout(function () { if (membersSec) membersSec.style.display = 'none'; }, 2000);
                } else {
                    _showMemberStatus((res.d && res.d.error) || 'Could not share. Please try again.', '#ef4444');
                }
            })
            .catch(function () {
                memberSend.disabled = false;
                memberSend.innerHTML = _orig;
                _showMemberStatus('Network error — please try again.', '#ef4444');
            });
        });
    }
});
</script>


    <!-- Delete Video Modal -->
    
    <!-- Toast Container -->
    <div id="toast-container" style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;"></div>

    <!-- Generic Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="background:#1a1a1a;border:1px solid #3f3f3f;border-radius:12px;">
                <div class="modal-body" style="padding:24px;">
                    <p id="confirmModalMessage" style="color:#fff;margin:0 0 20px;font-size:15px;line-height:1.5;"></p>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" class="btn" data-bs-dismiss="modal" style="background:#3f3f3f;color:#fff;padding:8px 18px;border-radius:8px;border:none;font-size:14px;">Cancel</button>
                        <button type="button" id="confirmModalOkBtn" class="btn" style="background:#ef4444;color:#fff;padding:8px 18px;border-radius:8px;border:none;font-size:14px;">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global toast notification — replaces alert()
        function showToast(message, type) {
            type = type || 'info';
            const colors = { success: '#22c55e', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
            const icons  = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
            const color  = colors[type] || colors.info;
            const icon   = icons[type]  || icons.info;
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.style.cssText = 'background:#1a1a1a;border:1px solid #3f3f3f;border-left:4px solid ' + color + ';color:#fff;padding:14px 16px;border-radius:8px;font-size:14px;display:flex;align-items:center;gap:10px;min-width:260px;max-width:380px;box-shadow:0 4px 20px rgba(0,0,0,.6);pointer-events:all;opacity:0;transition:opacity .25s ease;';
            toast.innerHTML = '<i class="bi ' + icon + '" style="color:' + color + ';font-size:16px;flex-shrink:0;"></i><span style="flex:1;">' + message + '</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#888;cursor:pointer;padding:0;font-size:18px;line-height:1;">&times;</button>';
            container.appendChild(toast);
            requestAnimationFrame(function() { toast.style.opacity = '1'; });
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 280);
            }, 4000);
        }

        // Flash session toasts
                                
        // Global confirm modal — replaces confirm()
        function showConfirm(message, onConfirm, confirmLabel) {
            document.getElementById('confirmModalMessage').textContent = message;
            const okBtn = document.getElementById('confirmModalOkBtn');
            okBtn.textContent = confirmLabel || 'Confirm';
            const modalEl = document.getElementById('confirmModal');
            const modal = new bootstrap.Modal(modalEl);
            const handler = function() {
                okBtn.removeEventListener('click', handler);
                modal.hide();
                onConfirm();
            };
            okBtn.addEventListener('click', handler);
            modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                okBtn.removeEventListener('click', handler);
                modalEl.removeEventListener('hidden.bs.modal', cleanup);
            });
            modal.show();
        }

    </script>

    
    <div id="page-scripts">
            </div>

    
    
    <!-- ── Persistent Mini-Player ──────────────────────────────────────────
         #videoPlayer is teleported into #ytpMiniVideo via DOM adoption so
         HLS.js keeps streaming without any reconnection or discontinuity.
         Activated by scroll (video leaves viewport) or by SPA navigation.
    ──────────────────────────────────────────────────────────────────── -->
    <div id="ytpMini" style="display:none;" aria-label="Mini player">
        <div id="ytpMiniVideo"></div>
        <div id="ytpMiniBar">
            <div id="ytpMiniInfo">
                <span id="ytpMiniTitle"></span>
            </div>
            <div id="ytpMiniControls">
                <button id="ytpMiniPlay"  title="Play / Pause"><i class="bi bi-play-fill"></i></button>
                <a      id="ytpMiniExpand" title="Back to video" href="#"><i class="bi bi-box-arrow-up-right"></i></a>
                <button id="ytpMiniClose" title="Close"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>

    <script>
    /* ── Mini-player controller (teleportation-based) ─────────────────────
       Moves the actual #videoPlayer element into the mini slot so HLS.js
       never drops the stream.  Two modes:
         'scroll' — player scrolled out of viewport on the video page
         'nav'    — user navigated to a non-video page via SPA
    ───────────────────────────────────────────────────────────────────── */
    /* Global on/off for the floating mini player. Persisted in localStorage so
       the user's choice survives reloads and applies across video AND music
       players. Default ON. The gear-menu toggles in each player flip this. */
    window._ytpMiniEnabled = function () {
        try { return localStorage.getItem('ytpMiniEnabled') !== '0'; }
        catch (e) { return true; }
    };
    window._ytpMiniSetEnabled = function (on) {
        try { localStorage.setItem('ytpMiniEnabled', on ? '1' : '0'); } catch (e) {}
        /* Closing the mini cleanly if the user disabled it while it was active. */
        if (!on && window._miniPlayer && window._miniPlayer.isActive()) {
            window._miniPlayer.deactivate();
        }
    };

    window._miniPlayer = (function () {
        var wrap      = document.getElementById('ytpMini');
        var slot      = document.getElementById('ytpMiniVideo');
        var titleEl   = document.getElementById('ytpMiniTitle');
        var playBtn   = document.getElementById('ytpMiniPlay');
        var expandBtn = document.getElementById('ytpMiniExpand');
        var closeBtn  = document.getElementById('ytpMiniClose');

        var _mode        = null;   /* 'scroll' | 'nav' | null */
        var _kind        = null;   /* 'video' | 'audio' | null */
        var _origParent  = null;
        var _origNext    = null;

        function getVid()   { return document.getElementById('videoPlayer'); }
        function getAudio() { return document.getElementById('audioEl'); }
        /* The element the mini player drives — video element if present, else the
           page's <audio>. Returned as a generic HTMLMediaElement either way. */
        function getMedia() { return getVid() || getAudio(); }

        function syncBtn() {
            var m = getMedia();
            if (!playBtn) return;
            playBtn.querySelector('i').className = (!m || m.paused)
                ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        }

        function activate(title, url, mode) {
            if (_mode !== null) return false; /* already active — prevent re-entry */
            /*
             * Never while fullscreen. This works by adopting the <video> into a
             * floating box, and re-parenting the element that IS the fullscreen
             * element makes the browser leave fullscreen on the spot. Fullscreen
             * ends when the viewer says it ends, not because something else on
             * the page wanted the player.
             */
            if (document.fullscreenElement || document.webkitFullscreenElement) return false;
            if (!slot) return false;

            var v = getVid();
            if (v) {
                /* VIDEO MODE — teleport the <video> element so HLS.js stays attached */
                _kind = 'video';
                _origParent = v.parentNode;
                _origNext   = v.nextSibling;
                slot.appendChild(v);
            } else {
                /* AUDIO MODE — teleport the <audio> element OUT of #main so SPA
                   navigation (which replaces #main.innerHTML) cannot destroy it,
                   and playback continues uninterrupted. Show the current cover
                   art (or active slide) inside the visible slot. */
                var a = getAudio();
                if (!a) return false;
                _kind = 'audio';
                var coverSrc = '';
                var slideA = document.getElementById('slideA');
                if (slideA && slideA.offsetParent !== null && slideA.src) coverSrc = slideA.src;
                if (!coverSrc) {
                    var cover = document.getElementById('audioCoverImg');
                    if (cover && cover.src) coverSrc = cover.src;
                }
                slot.innerHTML = coverSrc
                    ? '<img id="ytpMiniCover" src="' + coverSrc + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">'
                    : '<div style="width:100%;height:100%;background:#1a1a1a;display:flex;align-items:center;justify-content:center;color:#666;"><i class="bi bi-music-note-beamed" style="font-size:32px;"></i></div>';

                /* Teleport the audio element to the mini wrap. <audio> is
                   invisible, so visual layout is unaffected. */
                _origParent = a.parentNode;
                _origNext   = a.nextSibling;
                wrap.appendChild(a);
            }

            titleEl.textContent = title || 'Now playing';
            expandBtn.href      = url   || '#';
            _mode = mode;
            wrap.style.display  = 'block';
            syncBtn();
            var m = getMedia();
            if (m) {
                m.addEventListener('play',  syncBtn);
                m.addEventListener('pause', syncBtn);
            }
            return true;
        }

        function restore() {
            if (_kind === 'video') {
                var v = getVid();
                if (!v || !_origParent) return;
                if (_origNext && _origNext.isConnected && _origNext.parentNode === _origParent) {
                    _origParent.insertBefore(v, _origNext);
                } else {
                    _origParent.appendChild(v);
                }
            } else if (_kind === 'audio') {
                /* Move <audio> back to its original parent if it still exists
                   (e.g. scroll-mode → user scrolled back to the player). If the
                   parent was wiped by an SPA nav, leave the audio in the mini. */
                var a = getAudio();
                if (a && _origParent && _origParent.isConnected) {
                    if (_origNext && _origNext.isConnected && _origNext.parentNode === _origParent) {
                        _origParent.insertBefore(a, _origNext);
                    } else {
                        _origParent.appendChild(a);
                    }
                }
                if (slot) slot.innerHTML = '';
            }
            _origParent = null;
            _origNext   = null;
            _kind = null;
        }

        function deactivate() {
            restore();
            wrap.style.display = 'none';
            _mode = null;
        }

        if (playBtn) {
            playBtn.addEventListener('click', function () {
                var m = getMedia();
                if (!m) return;
                if (m.paused) m.play().catch(function(){});
                else          m.pause();
            });
        }

        if (expandBtn) {
            expandBtn.addEventListener('click', function (e) {
                if (_mode === 'scroll') {
                    e.preventDefault();
                    /* Scroll back to the player and restore it */
                    var main = document.getElementById('main');
                    if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
                    deactivate();
                    return;
                }
                /* nav mode — hand off the playhead so the destination page
                   resumes playback from where the mini left off. The expand
                   target is the original player URL; we append:
                     resume=1   — tells the player to auto-start
                     t=<sec>    — current playhead position
                   The video/audio player reads these query params on init. */
                var m = getMedia();
                if (!m) return; /* fall through to default nav */
                var href = expandBtn.getAttribute('href') || '';
                if (!href || href === '#') return;
                try {
                    var u = new URL(href, location.href);
                    u.searchParams.set('resume', '1');
                    if (!isNaN(m.currentTime) && m.currentTime > 0) {
                        u.searchParams.set('t', Math.floor(m.currentTime).toString());
                    }
                    expandBtn.setAttribute('href', u.toString());
                } catch (err) { /* leave href as-is */ }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (_mode === 'scroll') {
                    /* User is still on the player's own page. Close the mini
                       and put the media element back in its original box so it
                       keeps playing like a background tab — no pause, no scroll
                       back up. We dispatch a custom event so the per-page
                       IntersectionObservers can reset their local "is the mini
                       on?" flag — otherwise scrolling away again wouldn't
                       re-trigger the mini until the user scrolls back over the
                       player first. */
                    restore();
                    wrap.style.display = 'none';
                    _mode = null;
                    window.dispatchEvent(new CustomEvent('miniplayer:scroll-closed'));
                    return;
                }
                /* nav mode — user has navigated away from the player's page.
                   Pause and fully tear down; the original player no longer
                   exists in the DOM to receive the media element. */
                var m = getMedia();
                if (m) m.pause();
                wrap.style.display = 'none';
                _mode = null;
                _kind = null;
            });
        }

        /* ── Drag-to-reposition ────────────────────────────────────────────
           Desktop-only (mobile mini is already disabled). Persists the chosen
           position in localStorage so the next session keeps it. Buttons /
           anchors inside the bar are NOT drag handles — pointerdown on those
           goes through to their click handler. */
        var _drag = null;
        var POS_KEY = 'ytpMiniPos';

        function clampToViewport(left, top) {
            var r = wrap.getBoundingClientRect();
            var maxL = window.innerWidth  - r.width  - 4;
            var maxT = window.innerHeight - r.height - 4;
            return {
                left: Math.max(4, Math.min(left, maxL)),
                top:  Math.max(4, Math.min(top,  maxT)),
            };
        }

        function applyPos(left, top) {
            var c = clampToViewport(left, top);
            wrap.style.left   = c.left + 'px';
            wrap.style.top    = c.top  + 'px';
            wrap.style.right  = 'auto';
            wrap.style.bottom = 'auto';
        }

        function loadSavedPos() {
            try {
                var raw = localStorage.getItem(POS_KEY);
                if (!raw) return;
                var p = JSON.parse(raw);
                if (typeof p.left === 'number' && typeof p.top === 'number') {
                    applyPos(p.left, p.top);
                }
            } catch (e) {}
        }

        function startDrag(e) {
            /* Ignore drag attempts on interactive children — buttons/anchors
               keep their click semantics. */
            if (e.target.closest('button, a')) return;
            if (e.button !== undefined && e.button !== 0) return;
            var r = wrap.getBoundingClientRect();
            _drag = { dx: e.clientX - r.left, dy: e.clientY - r.top };
            wrap.classList.add('dragging');
            /* Lock in pixel coords for the first move so the wrap stops
               relying on right/bottom anchoring. */
            applyPos(r.left, r.top);
            try { wrap.setPointerCapture(e.pointerId); } catch (er) {}
            e.preventDefault();
        }

        function moveDrag(e) {
            if (!_drag) return;
            applyPos(e.clientX - _drag.dx, e.clientY - _drag.dy);
        }

        function endDrag(e) {
            if (!_drag) return;
            _drag = null;
            wrap.classList.remove('dragging');
            try { wrap.releasePointerCapture(e.pointerId); } catch (er) {}
            try {
                var r = wrap.getBoundingClientRect();
                localStorage.setItem(POS_KEY, JSON.stringify({ left: r.left, top: r.top }));
            } catch (er) {}
        }

        wrap.addEventListener('pointerdown', startDrag);
        wrap.addEventListener('pointermove', moveDrag);
        wrap.addEventListener('pointerup',   endDrag);
        wrap.addEventListener('pointercancel', endDrag);

        /* Re-clamp on resize so the mini doesn't get stranded off-screen. */
        window.addEventListener('resize', function () {
            if (wrap.style.display === 'none') return;
            var r = wrap.getBoundingClientRect();
            applyPos(r.left, r.top);
        });

        /* Apply saved position when the mini activates (no point reading it
           while the wrap is display:none — getBoundingClientRect would be 0). */
        function _activateAndPosition(title, url, mode) {
            var ok = activate(title, url, mode);
            if (ok) loadSavedPos();
            return ok;
        }

        return {
            activate:         function (t, u) { return _activateAndPosition(t, u, 'nav'); },
            activateScroll:   function (t, u) { return _activateAndPosition(t, u, 'scroll'); },
            deactivate:       deactivate,
            deactivateScroll: function () { if (_mode === 'scroll') deactivate(); },
            isActive:         function () { return _mode !== null; },
            isScrollMode:     function () { return _mode === 'scroll'; },
            isNavMode:        function () { return _mode === 'nav'; },
            setUrl:           function (u) { if (expandBtn) expandBtn.href = u; },
            setTitle:         function (t) { if (titleEl) titleEl.textContent = t || 'Video'; },
            /* Called when the user SPA-navigates away while the mini is in
               scroll mode — converts it to nav mode so the expand button
               returns to the player's original URL instead of scrolling. */
            convertToNav:     function (u) {
                if (_mode !== 'scroll') return;
                _mode = 'nav';
                if (u && expandBtn) expandBtn.href = u;
                /* Audio mode: the original parent is about to be wiped by the
                   SPA innerHTML swap, so forget it — restore() will then leave
                   the audio in the mini wrap on deactivate. */
                _origParent = null;
                _origNext   = null;
            },
            syncBtn:          syncBtn
        };
    })();

    /* ── SPA navigation — swaps <main> content so the mini player video
       element lives on across page changes without any HLS reconnection ── */
    (function () {

        function isVideoShowPage(url) {
            /* /videos/{key} — two path segments, second not a static word */
            var parts = new URL(url, location.href).pathname
                            .replace(/\/$/, '').split('/').filter(Boolean);
            if (parts.length !== 2 || parts[0] !== 'videos') return false;
            var STATIC = { search:1, trending:1, create:1, shorts:1 };
            return !STATIC[parts[1]];
        }

        function isInternal(href) {
            try { return new URL(href, location.href).origin === location.origin; }
            catch(e) { return false; }
        }

        function reExecScripts(container) {
            Array.from(container.querySelectorAll('script')).forEach(function (old) {
                var n = document.createElement('script');
                Array.from(old.attributes).forEach(function (a) {
                    n.setAttribute(a.name, a.value);
                });
                n.textContent = old.textContent;
                old.parentNode.replaceChild(n, old);
            });
        }

        function updateNavStates(url) {
            var path = new URL(url, location.href).pathname;
            document.querySelectorAll('.yt-bottom-nav-item, .yt-sidebar-item[href]')
                .forEach(function (el) {
                    try {
                        var ep = new URL(el.getAttribute('href') || '', location.href).pathname;
                        el.classList.toggle('active', ep !== '/' && path.startsWith(ep) || ep === path);
                    } catch(e) {}
                });
        }

        /* Import <style> blocks from the destination doc's <head> that
           aren't already present in the current head. Idempotent: identical
           textContent is only added once across the SPA session, so navigating
           between pages doesn't keep growing the head. */
        function importHeadStyles(doc) {
            try {
                var have = {};
                document.head.querySelectorAll('style[data-spa-style]').forEach(function (s) {
                    have[s.dataset.spaStyle] = true;
                });
                var srcStyles = doc.head ? doc.head.querySelectorAll('style') : [];
                Array.prototype.forEach.call(srcStyles, function (s) {
                    var txt = s.textContent || '';
                    if (!txt.trim()) return;
                    /* Hash via length + first/last bytes — cheap dedupe key */
                    var key = txt.length + ':' + txt.slice(0, 80) + ':' + txt.slice(-40);
                    if (have[key]) return;
                    have[key] = true;
                    var n = document.createElement('style');
                    n.dataset.spaStyle = key;
                    n.textContent = txt;
                    document.head.appendChild(n);
                });
            } catch (e) { /* non-fatal */ }
        }

        /* Top progress bar — gives the user immediate visual feedback that
           the SPA navigation is in flight. The actual DOM swap can take
           a noticeable beat on big pages; without this the click feels dead. */
        var _spaBar = null;
        function spaBarStart() {
            if (!_spaBar) {
                _spaBar = document.createElement('div');
                _spaBar.style.cssText = 'position:fixed;top:0;left:0;height:2px;background:var(--brand-red,#e61e1e);z-index:99999;width:0%;transition:width .2s ease,opacity .25s ease;pointer-events:none;box-shadow:0 0 8px rgba(230,30,30,.6);';
                document.body.appendChild(_spaBar);
            }
            _spaBar.style.opacity = '1';
            _spaBar.style.width = '0%';
            /* Two-stage trickle: jump to 25% immediately, creep to 70% while
               waiting on network. spaBarDone() finishes the run. */
            requestAnimationFrame(function () { _spaBar.style.width = '25%'; });
            setTimeout(function () { if (_spaBar) _spaBar.style.width = '70%'; }, 300);
        }
        function spaBarDone() {
            if (!_spaBar) return;
            _spaBar.style.width = '100%';
            setTimeout(function () {
                if (!_spaBar) return;
                _spaBar.style.opacity = '0';
                setTimeout(function () { if (_spaBar) _spaBar.style.width = '0%'; }, 250);
            }, 150);
        }

        function spaGo(url) {
            spaBarStart();
            /* Update the URL immediately so the address bar reflects the click.
               If the load fails, popstate-like recovery isn't needed: the catch
               below falls back to a hard nav which corrects the URL again. */
            try { history.pushState({ spa: true, url: url, pending: true }, '', url); } catch (e) {}
            updateNavStates(url);

            fetch(url, { headers: { 'X-SPA-Nav': '1' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc  = new DOMParser().parseFromString(html, 'text/html');
                    var newM = doc.getElementById('main');
                    var curM = document.getElementById('main');
                    if (!newM || !curM) { location.href = url; return; }

                    /* Safety: if destination turned out to be a video page, hard-navigate */
                    if (doc.getElementById('ytpWrap') || doc.getElementById('videoPlayer')) {
                        location.href = url;
                        return;
                    }

                    document.title = doc.title;
                    curM.className = newM.className;
                    curM.innerHTML = newM.innerHTML;
                    reExecScripts(curM);
                    /* Pages render into <head>, so a
                       plain #main swap loses the destination's styles. Copy
                       any <style> blocks from the new doc's <head> that we
                       don't already have. Identified by data-spa-style (set
                       below on first import) or by content hash. */
                    importHeadStyles(doc);
                    /* Page-level scripts live in #page-scripts (the wrapper
                       around the per-page scripts section) — swap and re-
                       execute so per-page helpers like channel's switchTab()
                       get defined again on this navigation. */
                    var srcPS = doc.getElementById('page-scripts');
                    var curPS = document.getElementById('page-scripts');
                    if (srcPS && curPS) {
                        curPS.innerHTML = srcPS.innerHTML;
                        reExecScripts(curPS);
                    }
                    curM.scrollTop = 0;
                    /* URL was already pushed at the top; replace state to drop
                       the `pending` flag now that the load succeeded. */
                    history.replaceState({ spa: true, url: url }, doc.title, url);
                    spaBarDone();
                })
                .catch(function () { location.href = url; });
        }

        function stopMiniAndNavigate(url) {
            var v = document.getElementById('videoPlayer');
            var a = document.getElementById('audioEl');
            if (v) v.pause();
            if (a) a.pause();
            document.getElementById('ytpMini').style.display = 'none';
            /* Allow browser to do a normal full-page load */
        }

        /* ── Intercept link clicks ── */
        document.addEventListener('click', function (e) {
            var a = e.target.closest('a[href]');
            if (!a) return;
            var href = a.getAttribute('href');
            if (!href || href === '#' || /^(javascript:|mailto:|tel:)/.test(href)) return;
            if (a.target === '_blank') return;
            if (!isInternal(href)) return;
            /* SPA-managed video cards handle their own transitions */
            if (a.hasAttribute('data-rec-url') || a.hasAttribute('data-pl-id')) return;

            var destUrl = new URL(href, location.href).href;
            var v       = document.getElementById('videoPlayer');
            var aEl     = document.getElementById('audioEl');
            var playing = (v && (window._ytpWasPlaying || !v.paused))
                       || (aEl && !aEl.paused);
            var miniOn  = window._miniPlayer && window._miniPlayer.isActive();

            if (!playing && !miniOn) return;

            /* Mobile: no floating mini player. Let the browser do a normal
               full-page navigation; playback stops with the page like any
               other site. The desktop-only mini is the only place where a
               persistent floating bar makes sense. */
            if (window.innerWidth <= 768) {
                if (miniOn) stopMiniAndNavigate(destUrl);
                return;
            }

            /* Going to a video page: stop mini, let browser do a full load */
            if (isVideoShowPage(destUrl)) {
                if (miniOn) stopMiniAndNavigate(destUrl);
                return;
            }

            /* Mini disabled in the user's gear preference: treat as a normal
               full navigation — pause and let the page change cleanly. Must
               come BEFORE preventDefault(), otherwise we cancel the browser
               click without running spaGo() and the link goes nowhere. */
            if (!window._ytpMiniEnabled()) {
                if (miniOn) stopMiniAndNavigate(destUrl);
                return;
            }

            e.preventDefault();

            /* Activate mini if the video is live in the page (not already in mini) */
            if (playing && !miniOn) {
                var title = document.title.replace(/\s*\|.*$/, '').trim();
                window._miniPlayer.activate(title, location.href);
            } else if (miniOn && window._miniPlayer.isScrollMode()) {
                /* Already in scroll mode — convert to nav mode so the expand
                   button returns to the original player URL after SPA nav. */
                window._miniPlayer.convertToNav(location.href);
            }

            spaGo(destUrl);
        }, true);

        /* ── Back / forward button ── */
        window.addEventListener('popstate', function (e) {
            var url    = location.href;
            var miniOn = window._miniPlayer && window._miniPlayer.isActive();

            if (isVideoShowPage(url)) {
                if (miniOn) stopMiniAndNavigate(url);
                location.reload();
                return;
            }

            if (miniOn || (e.state && e.state.spa)) {
                spaGo(url);
            }
        });

    })();
    </script>

    
    <script>
    (function () {
        var _csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-profile-visit-url]');
            if (!a) return;
            var url = a.getAttribute('data-profile-visit-url');
            var src = a.getAttribute('data-source-video-id') || '';
            if (!url) return;
            try {
                var body = new URLSearchParams({ source_video_id: src }).toString();
                if (navigator.sendBeacon) {
                    var blob = new Blob([body + '&_token=' + encodeURIComponent(_csrf)], { type: 'application/x-www-form-urlencoded' });
                    navigator.sendBeacon(url, blob);
                } else {
                    fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': _csrf, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                        body: body,
                        credentials: 'same-origin',
                        keepalive: true,
                    }).catch(function(){});
                }
            } catch (err) {}
        });
    })();
    </script>

</body>
</html>
