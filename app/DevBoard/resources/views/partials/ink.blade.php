{{--
    The ink engine: ONE drawing surface for every Margin page — a blank page
    to sketch on, or a photo to mark up. Include it once (it loads the vendored
    perfect-freehand itself); it defines window.MarginInk and owns its own
    full-screen overlay and styles.

      MarginInk.open({ T, mode: 'sketch'|'markup', image, w, h, strokes })
        → Promise<{ blob, w, h, strokes } | null>     (null = left without saving)

    Every mark is an OBJECT, the way a screenshot annotator works (modelled on
    Screenpresso): pen and highlighter lines, arrows (straight, dashed,
    double, curved, block), rectangles, ellipses, text, speech bubbles,
    numbered steps and — on a photo — blur. Any object can be selected, moved,
    resized, rotated, recoloured, re-styled, duplicated or deleted, at any time
    and again after saving: the strokes are kept beside the picture.

      p  points in PAGE units (never screen pixels):
         box objects (rect, ellipse, blur)  [corner, opposite corner]
         bubble                              [corner, opposite corner, tail tip]
         arrow                               [start, end, (control, when curved)]
         text                                [top-left]          w = font size
         step                                [centre]            w = radius, n = number
      r  rotation in radians about the object's centre (rect, ellipse, text, bubble);
         pen and highlighter lines are rotated and scaled INTO their points.
      s  arrow style.  x  text.  c  colour.  w  thickness.

    A second finger never draws, so a resting palm is safe.
--}}
<script src="{{ asset('vendor/perfect-freehand/perfect-freehand.min.js') }}"></script>
<style>
.ink { position: fixed; inset: 0; z-index: 120; display: flex; flex-direction: column; font-family: var(--sans, system-ui);
    padding: var(--sa-t, env(safe-area-inset-top, 0px)) 0 0; animation: ink-in .26s cubic-bezier(.2,.8,.2,1); }
@keyframes ink-in { from { opacity: 0; transform: translateY(16px); } }
.ink.is-sketch { background: #fffdf8; color: #1d1530; }
.ink.is-markup { background: #0b0812; color: #fff; }
.ink-top { flex-shrink: 0; display: flex; align-items: center; gap: 8px; padding: 10px 14px; }
.ink-round { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; font-size: 16px; flex-shrink: 0; border: 1px solid; transition: transform .15s, opacity .2s; }
.ink.is-sketch .ink-round { background: rgba(29,21,48,.06); border-color: rgba(29,21,48,.1); color: #1d1530; }
.ink.is-markup .ink-round { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.16); color: #fff; }
.ink-round:active { transform: scale(.9); }
.ink-round[disabled] { opacity: .3; }
.ink-title { font: 400 22px/1 var(--serif, Georgia, serif); margin-inline-start: 4px; white-space: nowrap; }
.ink-spacer { flex: 1; }
.ink-done { height: 38px; padding: 0 16px; border-radius: 999px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; border: 0; }
.ink.is-sketch .ink-done { background: #7c6bf5; color: #fff; }
.ink.is-markup .ink-done { background: #fff; color: #1d1530; }
.ink-stage { flex: 1; min-height: 0; position: relative; display: grid; place-items: center; overflow: hidden; touch-action: none; }
.ink-canvas { display: block; touch-action: none; -webkit-user-select: none; user-select: none; cursor: crosshair; }
.ink-canvas.is-select { cursor: default; }
.ink-canvas.is-move { cursor: move; }
.ink.is-sketch .ink-canvas { background-color: #fffdf8; background-image: radial-gradient(rgba(29,21,48,.16) 1px, transparent 1.2px); background-size: 18px 18px;
    box-shadow: 0 0 0 1px rgba(29,21,48,.06), 0 20px 40px -24px rgba(29,21,48,.45); border-radius: 4px; }
.ink.is-markup .ink-canvas { border-radius: 8px; }
.ink-hint { position: absolute; left: 50%; bottom: 14px; transform: translateX(-50%); width: max-content; max-width: calc(100% - 40px); text-align: center; font-size: 12px; line-height: 1.4;
    padding: 7px 12px; border-radius: 10px; background: rgba(29,21,48,.78); color: #fff; pointer-events: none; transition: opacity .4s; }
.ink-hint.is-gone { opacity: 0; }
.ink.is-typing .ink-tray { display: none; } /* writing: the keyboard is the tool, the picture stays in view */
.ink-tray { flex-shrink: 0; background: #1d1530; color: #fff; padding: 8px 10px calc(10px + var(--sa-b, env(safe-area-inset-bottom, 0px))); display: flex; flex-direction: column; gap: 8px; }
.ink-row { display: flex; align-items: center; gap: 4px; overflow-x: auto; scrollbar-width: none; }
.ink-row::-webkit-scrollbar { display: none; }
.ink-row:first-child { min-height: 36px; }
.ink-row.is-more { -webkit-mask-image: linear-gradient(to right, #000 82%, transparent); mask-image: linear-gradient(to right, #000 82%, transparent); }
[dir=rtl] .ink-row.is-more { -webkit-mask-image: linear-gradient(to left, #000 82%, transparent); mask-image: linear-gradient(to left, #000 82%, transparent); }
.ink-tool { flex: 0 0 auto; width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; font-size: 19px; color: rgba(255,255,255,.72); background: none; border: 0; transition: background .15s, color .15s, transform .15s; }
.ink-tool:active { transform: scale(.9); }
.ink-tool.is-on { background: rgba(255,255,255,.16); color: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.22); }
.ink-tool .num { font: 800 15px/1 var(--sans, system-ui); width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; border: 2px solid currentColor; }
.ink-sep { flex: 0 0 1px; height: 24px; margin: 0 4px; background: rgba(255,255,255,.14); }
/* Rings and pressed states are drawn outside a button's box: a scrolling row
   would clip them, so each row leaves them room. */
.ink-row { padding: 5px 4px; margin: -5px 0; }
/* Phone: the tools sit on two even rows, every one on screen — nothing to
   scroll for and nothing cut at the edge. Options and colours share the width. */
@media (max-width: 559px) {
    .ink-tools { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; overflow: visible; }
    .ink-tools .ink-sep { display: none; }
    .ink-tray .ink-tools .ink-tool { width: auto; height: 40px; }
    .ink-tray .ink-opt { min-width: 34px; padding: 0 6px; }
    .ink-tray .ink-opt svg { width: 22px; }
    .ink-row:not(.ink-tools) { gap: 3px; }
    .ink-tray .ink-sw { margin: 0 3px; }
}
.ink-sw { flex: 0 0 auto; width: 28px; height: 28px; margin: 0 5px; border-radius: 50%; background: var(--c); border: 0; box-shadow: 0 0 0 2px #1d1530, 0 0 0 3.5px rgba(255,255,255,.28); transition: transform .15s; }
.ink-sw.is-on { box-shadow: 0 0 0 2px #1d1530, 0 0 0 4.5px #fff; transform: scale(1.08); }
.ink-opt { flex: 0 0 auto; height: 36px; min-width: 40px; padding: 0 10px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 12px; font-weight: 700; color: rgba(255,255,255,.75); background: rgba(255,255,255,.06); border: 0; transition: background .15s, color .15s; }
.ink-opt.is-on { background: rgba(124,107,245,.45); color: #fff; }
.ink-opt.is-danger { color: #fca5a5; }
.ink-opt svg { width: 26px; height: 16px; }
.ink-opt .dot { display: block; border-radius: 50%; background: currentColor; }
.ink-edit { position: absolute; z-index: 4; padding: 6px 8px; border-radius: 10px; border: 2px dashed #7c6bf5; background: rgba(255,255,255,.95); color: #1d1530;
    font: 600 18px/1.3 var(--sans, system-ui); outline: none; resize: none; box-shadow: 0 12px 26px -12px rgba(0,0,0,.5); }
.ink-ask { position: absolute; z-index: 6; left: 14px; right: 14px; bottom: calc(150px + var(--sa-b, env(safe-area-inset-bottom, 0px))); border-radius: 18px; background: #fff; color: #1d1530; padding: 14px;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,.6); animation: ink-in .2s; }
.ink-ask b { display: block; font-size: 15px; margin-bottom: 12px; }
.ink-ask div { display: flex; gap: 8px; }
.ink-ask button { flex: 1; height: 44px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 0; }
.ink-ask .no { background: #fee2e2; color: #b91c1c; }
.ink-ask .yes { background: #1d1530; color: #fff; }
.ink-busy { position: absolute; inset: 0; z-index: 7; display: grid; place-items: center; background: rgba(11,8,18,.35); }
.ink-busy span { width: 34px; height: 34px; border-radius: 50%; border: 3px solid #fff; border-top-color: transparent; animation: ink-spin .8s linear infinite; }
@keyframes ink-spin { to { transform: rotate(360deg); } }
@media (min-width: 760px) { .ink-tray { align-items: center; } .ink-row { max-width: 760px; width: 100%; } }
@media (prefers-reduced-motion: reduce) { .ink, .ink-ask { animation: none; } }
</style>
<script>
(function () {
    'use strict';
    if (window.MarginInk) return;

    var INKS = { sketch: ['#1d1530', '#7c6bf5', '#e0457b', '#2563eb', '#16a394', '#e89a1c', '#ffffff'],
                 markup: ['#ff3b5c', '#ffd84d', '#3fd3c1', '#7c6bf5', '#2563eb', '#ffffff', '#111111'] };
    var HIGHLIGHT = '#fde047';
    var ARROWS = ['line', 'dash', 'double', 'curve', 'block'];
    var SIZES = [0.6, 1, 1.8];
    var BOXES = { rect: 1, ellipse: 1, blur: 1, bubble: 1 };
    var ROTATES = { rect: 1, ellipse: 1, bubble: 1, text: 1, pen: 1, marker: 1 };
    var FREE = { pen: 1, marker: 1 };

    function h(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    }
    function icon(name) { return h('i', 'bi ' + name); }
    function btn(cls, ic, label) {
        var b = h('button', cls); b.type = 'button';
        if (ic) b.appendChild(icon(ic));
        if (label) { b.setAttribute('aria-label', label); b.title = label; }
        return b;
    }
    function svg(markup) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        s.setAttribute('viewBox', '0 0 26 16'); s.setAttribute('aria-hidden', 'true');
        s.innerHTML = markup; // static drawing, no data in it
        return s;
    }
    var ARROW_ICONS = {
        line: '<path d="M2 8h18M15 3l6 5-6 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        dash: '<path d="M2 8h4M9 8h4M16 8h5M16 3l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        double: '<path d="M4 8h18M9 3L3 8l6 5M17 3l6 5-6 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        curve: '<path d="M2 13C6 2 14 2 20 7M16 3l5 4-6 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        block: '<path d="M2 6h13V2l9 6-9 6v-4H2z" fill="currentColor"/>'
    };
    function loadImage(src) {
        return new Promise(function (resolve, reject) {
            var url = src instanceof Blob ? URL.createObjectURL(src) : src;
            var im = new Image();
            im.onload = function () { resolve(im); };
            im.onerror = reject;
            im.src = url;
        });
    }
    function isLight(hex) {
        var n = parseInt(hex.slice(1), 16);
        return ((n >> 16) * 299 + ((n >> 8) & 255) * 587 + (n & 255) * 114) / 1000 > 150;
    }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function rot(x, y, a) { var c = Math.cos(a), s = Math.sin(a); return [x * c - y * s, x * s + y * c]; }
    function dist(a, b) { return Math.hypot(a[0] - b[0], a[1] - b[1]); }
    function segDist(a, b, x, y) {
        var dx = b[0] - a[0], dy = b[1] - a[1], l = dx * dx + dy * dy;
        var t = l ? Math.max(0, Math.min(1, ((x - a[0]) * dx + (y - a[1]) * dy) / l)) : 0;
        return Math.hypot(a[0] + t * dx - x, a[1] + t * dy - y);
    }
    function quad(a, c, b, t) { var u = 1 - t; return [u * u * a[0] + 2 * u * t * c[0] + t * t * b[0], u * u * a[1] + 2 * u * t * c[1] + t * t * b[1]]; }
    function control(s) {
        if (s.p[2]) return s.p[2];
        var a = s.p[0], b = s.p[1], mx = (a[0] + b[0]) / 2, my = (a[1] + b[1]) / 2, l = dist(a, b) * 0.25;
        var n = rot((b[0] - a[0]) / (dist(a, b) || 1), (b[1] - a[1]) / (dist(a, b) || 1), -Math.PI / 2);
        return [mx + n[0] * l, my + n[1] * l];
    }

    /* ── measuring text (a shared scratch context) ───────────────────── */
    var measure = document.createElement('canvas').getContext('2d');
    function textFont(w) { return '700 ' + w + 'px Inter, system-ui, sans-serif'; }
    function wrap(text, font, maxW) {
        measure.font = font;
        var out = [];
        String(text || '').split('\n').forEach(function (para) {
            var words = para.split(/\s+/), line = '';
            words.forEach(function (wd) {
                var t = line ? line + ' ' + wd : wd;
                if (measure.measureText(t).width > maxW && line) { out.push(line); line = wd; } else line = t;
            });
            out.push(line);
        });
        return out;
    }

    /* ── geometry ────────────────────────────────────────────────────── */
    /* The object's box, unrotated, in page units: [x0, y0, x1, y1]. */
    function box(s) {
        var p = s.p;
        if (BOXES[s.t]) return [Math.min(p[0][0], p[1][0]), Math.min(p[0][1], p[1][1]), Math.max(p[0][0], p[1][0]), Math.max(p[0][1], p[1][1])];
        if (s.t === 'text') {
            measure.font = textFont(s.w);
            var lines = String(s.x).split('\n'), tw = 0;
            lines.forEach(function (l) { tw = Math.max(tw, measure.measureText(l).width); });
            return [p[0][0], p[0][1], p[0][0] + tw + s.w * 0.2, p[0][1] + lines.length * s.w * 1.25];
        }
        if (s.t === 'step') return [p[0][0] - s.w, p[0][1] - s.w, p[0][0] + s.w, p[0][1] + s.w];
        var xs = p.map(function (q) { return q[0]; }), ys = p.map(function (q) { return q[1]; });
        if (s.t === 'arrow' && s.s === 'curve') { var c = control(s); xs.push(c[0]); ys.push(c[1]); }
        var pad = FREE[s.t] ? s.w / 2 : 0;
        return [Math.min.apply(null, xs) - pad, Math.min.apply(null, ys) - pad, Math.max.apply(null, xs) + pad, Math.max.apply(null, ys) + pad];
    }
    function centre(s) { var b = box(s); return [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2]; }
    /* A page point in the object's own unrotated frame (centre-relative). */
    function local(s, x, y) { var c = centre(s); return rot(x - c[0], y - c[1], -(s.r || 0)); }
    function toPage(s, lx, ly) { var c = centre(s), q = rot(lx, ly, s.r || 0); return [c[0] + q[0], c[1] + q[1]]; }

    function hits(s, x, y, r) {
        var b = box(s), l = local(s, x, y), hw = (b[2] - b[0]) / 2, hh = (b[3] - b[1]) / 2;
        switch (s.t) {
            case 'rect': case 'blur': case 'bubble': case 'text':
                if (s.t === 'bubble' && s.p[2] && segDist(toPage(s, 0, 0), s.p[2], x, y) <= r + s.w * 0.3) return true;
                return Math.abs(l[0]) <= hw + r && Math.abs(l[1]) <= hh + r;
            case 'ellipse':
                return Math.pow(l[0] / (hw + r), 2) + Math.pow(l[1] / (hh + r), 2) <= 1;
            case 'step':
                return dist(s.p[0], [x, y]) <= s.w + r;
            case 'arrow': {
                var reach = r + Math.max(s.w, s.s === 'block' ? s.w * 3 : 0);
                if (s.s === 'curve') {
                    var c = control(s), prev = s.p[0];
                    for (var i = 1; i <= 24; i++) { var q = quad(s.p[0], c, s.p[1], i / 24); if (segDist(prev, q, x, y) <= reach) return true; prev = q; }
                    return false;
                }
                return segDist(s.p[0], s.p[1], x, y) <= reach;
            }
            default: // pen, marker
                for (var k = 0; k < s.p.length; k++) {
                    if (dist(s.p[k], [x, y]) <= r + s.w / 2) return true;
                    if (k && segDist(s.p[k - 1], s.p[k], x, y) <= r + s.w / 2) return true;
                }
                return false;
        }
    }

    /* ── drawing ─────────────────────────────────────────────────────── */
    function freehand(ctx, s, thinning) {
        var pressured = s.p.length && s.p[0].length > 2;
        var outline = window.PerfectFreehand.getStroke(s.p, { size: s.w, thinning: thinning, smoothing: 0.55, streamline: 0.45, simulatePressure: !pressured, last: true });
        if (!outline.length) return;
        ctx.beginPath();
        ctx.moveTo(outline[0][0], outline[0][1]);
        for (var i = 1; i < outline.length; i++) {
            var a = outline[i], b = outline[(i + 1) % outline.length];
            ctx.quadraticCurveTo(a[0], a[1], (a[0] + b[0]) / 2, (a[1] + b[1]) / 2);
        }
        ctx.closePath();
        ctx.fill();
    }
    function head(ctx, tip, from, w) {
        var ang = Math.atan2(tip[1] - from[1], tip[0] - from[0]), len = Math.max(w * 4, 14);
        ctx.beginPath();
        ctx.moveTo(tip[0] - len * Math.cos(ang - 0.5), tip[1] - len * Math.sin(ang - 0.5));
        ctx.lineTo(tip[0], tip[1]);
        ctx.lineTo(tip[0] - len * Math.cos(ang + 0.5), tip[1] - len * Math.sin(ang + 0.5));
        ctx.setLineDash([]);
        ctx.stroke();
    }
    function roundRect(ctx, x, y, w, hh, r) {
        r = Math.min(r, w / 2, hh / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + hh, r); ctx.arcTo(x + w, y + hh, x, y + hh, r);
        ctx.arcTo(x, y + hh, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
    }
    function draw(ctx, s, env) {
        ctx.save();
        ctx.fillStyle = ctx.strokeStyle = s.c;
        ctx.lineCap = ctx.lineJoin = 'round';
        var b = box(s), c = [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2], bw = b[2] - b[0], bh = b[3] - b[1];
        if (ROTATES[s.t] && !FREE[s.t] && s.r) { ctx.translate(c[0], c[1]); ctx.rotate(s.r); ctx.translate(-c[0], -c[1]); }
        switch (s.t) {
            case 'pen': freehand(ctx, s, 0.55); break;
            case 'marker': ctx.globalAlpha = 0.38; freehand(ctx, s, 0); break;
            case 'rect':
                ctx.lineWidth = s.w;
                roundRect(ctx, b[0], b[1], bw, bh, s.w * 1.2); ctx.stroke();
                break;
            case 'ellipse':
                ctx.lineWidth = s.w;
                ctx.beginPath(); ctx.ellipse(c[0], c[1], Math.max(1, bw / 2), Math.max(1, bh / 2), 0, 0, Math.PI * 2); ctx.stroke();
                break;
            case 'arrow': {
                var a = s.p[0], e = s.p[1], style = s.s || 'line';
                ctx.lineWidth = s.w;
                if (style === 'block') {
                    var ang = Math.atan2(e[1] - a[1], e[0] - a[0]), L = dist(a, e), hw = s.w * 2.6, hl = Math.min(L * 0.6, s.w * 5.5), sw = s.w * 1.1;
                    ctx.translate(a[0], a[1]); ctx.rotate(ang);
                    ctx.beginPath();
                    ctx.moveTo(0, -sw); ctx.lineTo(L - hl, -sw); ctx.lineTo(L - hl, -hw); ctx.lineTo(L, 0); ctx.lineTo(L - hl, hw); ctx.lineTo(L - hl, sw); ctx.lineTo(0, sw);
                    ctx.closePath();
                    ctx.lineWidth = Math.max(1.5, s.w * 0.35); ctx.strokeStyle = isLight(s.c) ? 'rgba(17,17,17,.45)' : 'rgba(255,255,255,.6)';
                    ctx.fill(); ctx.stroke();
                    break;
                }
                if (style === 'dash') ctx.setLineDash([s.w * 2.4, s.w * 1.9]);
                ctx.beginPath(); ctx.moveTo(a[0], a[1]);
                var from = a, back = e;
                if (style === 'curve') { var q = control(s); ctx.quadraticCurveTo(q[0], q[1], e[0], e[1]); from = q; back = q; }
                else ctx.lineTo(e[0], e[1]);
                ctx.stroke();
                head(ctx, e, style === 'curve' ? from : a, s.w);
                if (style === 'double') head(ctx, a, e, s.w);
                break;
            }
            case 'text': {
                ctx.font = textFont(s.w); ctx.textBaseline = 'top';
                ctx.lineWidth = Math.max(2, s.w / 6);
                ctx.strokeStyle = isLight(s.c) ? 'rgba(17,17,17,.6)' : 'rgba(255,255,255,.9)';
                String(s.x).split('\n').forEach(function (line, i) {
                    ctx.strokeText(line, b[0], b[1] + i * s.w * 1.25);
                    ctx.fillText(line, b[0], b[1] + i * s.w * 1.25);
                });
                break;
            }
            case 'bubble': {
                var pad = s.w * 0.7, rad = s.w * 0.9, tip = s.p[2] || [b[0] + bw * 0.25, b[3] + s.w * 1.6]; // while it is being drawn it has no tail yet
                // the tail leaves the side of the box facing its tip
                var lt = rot(tip[0] - c[0], tip[1] - c[1], -(s.r || 0)), tipL = [c[0] + lt[0], c[1] + lt[1]];
                var base = Math.min(bw, bh) * 0.18 + s.w * 0.4, side;
                if (Math.abs(lt[1]) * bw >= Math.abs(lt[0]) * bh) side = lt[1] > 0 ? 'b' : 't'; else side = lt[0] > 0 ? 'r' : 'l';
                var mx = Math.max(b[0] + rad + base, Math.min(b[2] - rad - base, tipL[0])), my = Math.max(b[1] + rad + base, Math.min(b[3] - rad - base, tipL[1]));
                var t1, t2;
                if (side === 'b') { t1 = [mx - base, b[3] - 1]; t2 = [mx + base, b[3] - 1]; }
                else if (side === 't') { t1 = [mx - base, b[1] + 1]; t2 = [mx + base, b[1] + 1]; }
                else if (side === 'r') { t1 = [b[2] - 1, my - base]; t2 = [b[2] - 1, my + base]; }
                else { t1 = [b[0] + 1, my - base]; t2 = [b[0] + 1, my + base]; }
                ctx.shadowColor = 'rgba(17,12,30,.28)'; ctx.shadowBlur = s.w * 0.6; ctx.shadowOffsetY = s.w * 0.15;
                roundRect(ctx, b[0], b[1], bw, bh, rad); ctx.fill();
                ctx.beginPath(); ctx.moveTo(t1[0], t1[1]); ctx.lineTo(tipL[0], tipL[1]); ctx.lineTo(t2[0], t2[1]); ctx.closePath(); ctx.fill();
                ctx.shadowColor = 'transparent';
                ctx.fillStyle = isLight(s.c) ? '#1d1530' : '#ffffff';
                ctx.font = textFont(s.w); ctx.textBaseline = 'top';
                var lines = wrap(s.x || '', ctx.font, Math.max(10, bw - pad * 2)), lh = s.w * 1.25;
                var ty = b[1] + Math.max(pad, (bh - lines.length * lh) / 2);
                ctx.save(); roundRect(ctx, b[0], b[1], bw, bh, rad); ctx.clip();
                lines.forEach(function (line, i) { ctx.fillText(line, b[0] + pad, ty + i * lh); });
                ctx.restore();
                break;
            }
            case 'step': {
                var p0 = s.p[0];
                ctx.beginPath(); ctx.arc(p0[0], p0[1], s.w, 0, Math.PI * 2);
                ctx.shadowColor = 'rgba(17,12,30,.35)'; ctx.shadowBlur = s.w * 0.4; ctx.fill(); ctx.shadowColor = 'transparent';
                ctx.lineWidth = Math.max(2, s.w * 0.12); ctx.strokeStyle = isLight(s.c) ? '#1d1530' : '#ffffff'; ctx.stroke();
                ctx.fillStyle = isLight(s.c) ? '#1d1530' : '#ffffff';
                ctx.font = '800 ' + Math.round(s.w * 1.05) + 'px Inter, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(String(s.n), p0[0], p0[1] + s.w * 0.04);
                break;
            }
            case 'blur': {
                var x = Math.max(0, b[0]), y = Math.max(0, b[1]), w = Math.min(env.W, b[2]) - x, hgt = Math.min(env.H, b[3]) - y;
                if (w < 2 || hgt < 2) break;
                if (env.photo) {
                    var block = Math.max(6, Math.round(Math.max(env.W, env.H) / 90));
                    var tmp = document.createElement('canvas'); tmp.width = Math.max(1, Math.ceil(w / block)); tmp.height = Math.max(1, Math.ceil(hgt / block));
                    var kx = env.photo.naturalWidth / env.W, ky = env.photo.naturalHeight / env.H;
                    tmp.getContext('2d').drawImage(env.photo, x * kx, y * ky, w * kx, hgt * ky, 0, 0, tmp.width, tmp.height);
                    ctx.imageSmoothingEnabled = false;
                    ctx.drawImage(tmp, x, y, w, hgt);
                } else {
                    ctx.fillStyle = 'rgba(29,21,48,.18)'; ctx.fillRect(x, y, w, hgt);
                }
                break;
            }
        }
        ctx.restore();
    }

    /* ── the surface ────────────────────────────────────────────────── */
    function open(o) {
        var T = o.T, mode = o.mode === 'markup' ? 'markup' : 'sketch';
        var ready = mode === 'markup' ? loadImage(o.image) : Promise.resolve(null);

        return ready.then(function (photo) {
            return new Promise(function (resolve) {
                var W, H;
                if (photo) {
                    var k = Math.min(1, 2400 / Math.max(photo.naturalWidth, photo.naturalHeight));
                    W = Math.round(o.w || photo.naturalWidth * k); H = Math.round(o.h || photo.naturalHeight * k);
                } else { W = o.w || 1200; H = o.h || 1600; }
                var env = { W: W, H: H, photo: photo };
                var unit = Math.max(W, H) / 1600; // sizes follow the page, not the screen
                var BASE = { pen: 7, marker: 26, arrow: 7, rect: 6, ellipse: 6, text: 54, bubble: 40, step: 44, blur: 1 };

                var strokes = clone(o.strokes || []), undo = [], redo = [], changed = false;
                var inks = INKS[mode], colour = inks[0], size = 1, arrowStyle = 'line';
                var tool = mode === 'markup' ? 'arrow' : 'pen', sel = -1;

                /* chrome */
                var root = h('div', 'ink is-' + mode);
                root.setAttribute('role', 'dialog'); root.setAttribute('aria-label', mode === 'markup' ? T.ink_markup : T.ink_sketch);
                var top = h('div', 'ink-top');
                var x = btn('ink-round', 'bi-x-lg', T.ink_close);
                var title = h('span', 'ink-title', mode === 'markup' ? T.ink_markup : T.ink_sketch);
                var bUndo = btn('ink-round', 'bi-arrow-counterclockwise', T.ink_undo);
                var bRedo = btn('ink-round', 'bi-arrow-clockwise', T.ink_redo);
                var done = h('button', 'ink-done'); done.type = 'button';
                done.append(icon('bi-check-lg'), document.createTextNode(T.ink_done));
                top.append(x, title, h('span', 'ink-spacer'), bUndo, bRedo, done);
                var stage = h('div', 'ink-stage');
                var canvas = h('canvas', 'ink-canvas');
                stage.appendChild(canvas);
                var hint = h('div', 'ink-hint', T.ink_hint_select); stage.appendChild(hint);
                setTimeout(function () { hint.classList.add('is-gone'); }, 4200);

                var tray = h('div', 'ink-tray');
                var rowTools = h('div', 'ink-row ink-tools'), rowOpts = h('div', 'ink-row'), rowColours = h('div', 'ink-row');
                // A row with more tools than fit fades at its edge until it is scrolled to the end.
                function more(row) {
                    var end = Math.abs(row.scrollLeft) + row.clientWidth >= row.scrollWidth - 4;
                    row.classList.toggle('is-more', row.scrollWidth > row.clientWidth + 4 && !end);
                }
                [rowTools, rowOpts].forEach(function (row) { row.addEventListener('scroll', function () { more(row); }, { passive: true }); });
                tray.append(rowOpts, rowTools, rowColours);
                root.append(top, stage, tray);
                // the note underneath may still hold the keyboard: drawing starts with it put away
                if (document.activeElement && document.activeElement !== document.body && document.activeElement.blur) document.activeElement.blur();
                document.body.appendChild(root);

                var TOOLS = [['select', 'bi-cursor', 'ink_select'], '|', ['pen', 'bi-pen', 'ink_pen'], ['marker', 'bi-highlighter', 'ink_marker'], '|',
                    ['arrow', 'bi-arrow-up-right', 'ink_arrow'], ['rect', 'bi-square', 'ink_rect'], ['ellipse', 'bi-circle', 'ink_circle'],
                    ['bubble', 'bi-chat-square-text', 'ink_bubble'], ['text', 'bi-fonts', 'ink_text'], ['step', null, 'ink_step']];
                if (photo) TOOLS.push(['blur', 'bi-grid-3x3', 'ink_blur']);
                TOOLS.push('|', ['eraser', 'bi-eraser', 'ink_eraser']);
                var toolBtns = {};
                TOOLS.forEach(function (d) {
                    if (d === '|') { rowTools.appendChild(h('span', 'ink-sep')); return; }
                    var b = btn('ink-tool', d[1], T[d[2]]);
                    if (d[0] === 'step') b.appendChild(h('span', 'num', '1'));
                    b.addEventListener('click', function () { setTool(d[0]); });
                    toolBtns[d[0]] = b; rowTools.appendChild(b);
                });
                var swatches = inks.map(function (c) {
                    var sw = btn('ink-sw', null, T.ink_colour + ' ' + c); sw.style.setProperty('--c', c);
                    sw.addEventListener('click', function () { setColour(c); });
                    rowColours.appendChild(sw); return sw;
                });

                /* ── state helpers ── */
                function selected() { return sel >= 0 ? strokes[sel] : null; }
                function snapshot() { undo.push(JSON.stringify(strokes)); if (undo.length > 120) undo.shift(); redo = []; changed = true; }
                function setTool(t) {
                    tool = t;
                    if (t !== 'select' && t !== 'eraser') { var s = selected(); if (s && s.t !== t) sel = -1; }
                    if (t === 'eraser') sel = -1;
                    canvas.classList.toggle('is-select', t === 'select');
                    if (t === 'marker' && colour !== HIGHLIGHT) { /* highlighter keeps its yellow unless chosen */ }
                    paintChrome(); render();
                }
                function setColour(c) {
                    colour = c;
                    var s = selected();
                    if (s && s.t !== 'blur') { snapshot(); s.c = c; rebuild(); }
                    paintChrome();
                }
                function setSize(i) {
                    size = SIZES[i];
                    var s = selected();
                    if (s && s.t !== 'blur') {
                        snapshot();
                        var base = BASE[s.t] * unit;
                        if (s.t === 'text' || s.t === 'bubble' || s.t === 'step') s.w = Math.round(base * size);
                        else s.w = Math.round(base * size * 10) / 10;
                        rebuild();
                    }
                    paintChrome();
                }
                function setArrow(st) {
                    arrowStyle = st;
                    var s = selected();
                    if (s && s.t === 'arrow') { snapshot(); s.s = st; if (st === 'curve' && !s.p[2]) s.p[2] = control(s).map(Math.round); if (st !== 'curve') s.p.length = 2; rebuild(); }
                    paintChrome();
                }
                function sizeIndexOf(s) {
                    var base = BASE[s.t] * unit, best = 1, bd = 1e9;
                    SIZES.forEach(function (m, i) { var d = Math.abs(s.w - base * m); if (d < bd) { bd = d; best = i; } });
                    return best;
                }
                function paintChrome() {
                    Object.keys(toolBtns).forEach(function (k) { toolBtns[k].classList.toggle('is-on', k === tool); });
                    toolBtns.step.querySelector('.num').textContent = String(nextStep());
                    var s = selected(), c = s ? s.c : colour;
                    swatches.forEach(function (sw, i) { sw.classList.toggle('is-on', inks[i] === c && !(s && s.t === 'blur')); });
                    bUndo.disabled = !undo.length; bRedo.disabled = !redo.length;
                    // options: arrow styles, thickness, and what can be done to the selection
                    rowOpts.replaceChildren();
                    var kind = s ? s.t : tool;
                    if (kind === 'arrow') {
                        ARROWS.forEach(function (st) {
                            var b = btn('ink-opt' + ((s ? (s.s || 'line') : arrowStyle) === st ? ' is-on' : ''), null, T['ink_arrow_' + st]);
                            b.appendChild(svg(ARROW_ICONS[st]));
                            b.addEventListener('click', function () { setArrow(st); });
                            rowOpts.appendChild(b);
                        });
                        rowOpts.appendChild(h('span', 'ink-sep'));
                    }
                    if (kind && kind !== 'select' && kind !== 'eraser' && kind !== 'blur') {
                        var cur = s ? sizeIndexOf(s) : SIZES.indexOf(size);
                        SIZES.forEach(function (m, i) {
                            var b = btn('ink-opt' + (cur === i ? ' is-on' : ''), null, T['ink_size_' + i]);
                            var d = h('span', 'dot'); d.style.width = d.style.height = (4 + i * 4) + 'px';
                            b.appendChild(d);
                            b.addEventListener('click', function () { setSize(i); });
                            rowOpts.appendChild(b);
                        });
                    }
                    if (s) {
                        rowOpts.appendChild(h('span', 'ink-sep'));
                        if (s.t === 'text' || s.t === 'bubble') {
                            var ed = btn('ink-opt', 'bi-input-cursor-text', T.ink_edit_text);
                            ed.appendChild(document.createTextNode(T.ink_edit_text));
                            ed.addEventListener('click', function () { editText(sel); });
                            rowOpts.appendChild(ed);
                        }
                        var dup = btn('ink-opt', 'bi-copy', T.ink_duplicate);
                        dup.addEventListener('click', duplicate);
                        var del = btn('ink-opt is-danger', 'bi-trash3', T.ink_delete);
                        del.addEventListener('click', remove);
                        rowOpts.append(dup, del);
                    }
                    // The row keeps its height when empty, so the picture never jumps under a finger.
                    rowOpts.style.visibility = rowOpts.childNodes.length ? 'visible' : 'hidden';
                    more(rowOpts);
                }
                function nextStep() { var n = 0; strokes.forEach(function (s) { if (s.t === 'step') n = Math.max(n, s.n); }); return n + 1; }
                function duplicate() {
                    var s = selected(); if (!s) return;
                    snapshot();
                    var c = clone(s), d = 24 * unit;
                    c.p = c.p.map(function (q) { var r = q.slice(); r[0] += d; r[1] += d; return r; });
                    if (c.t === 'step') c.n = nextStep();
                    strokes.push(c); sel = strokes.length - 1; rebuild(); paintChrome();
                }
                function remove() {
                    if (sel < 0) return;
                    snapshot(); strokes.splice(sel, 1); sel = -1; rebuild(); paintChrome();
                }

                /* ── canvas ── */
                var scale = 1, dpr = 1, ctx = canvas.getContext('2d'), cache = document.createElement('canvas'), cctx = cache.getContext('2d');
                function layout() {
                    var r = stage.getBoundingClientRect(), pad = 14;
                    if (!r.width || !r.height) return;
                    scale = Math.min((r.width - pad * 2) / W, (r.height - pad * 2) / H);
                    dpr = Math.min(window.devicePixelRatio || 1, 2.5);
                    var cw = Math.round(W * scale), ch = Math.round(H * scale);
                    canvas.style.width = cw + 'px'; canvas.style.height = ch + 'px';
                    canvas.width = cache.width = Math.round(cw * dpr); canvas.height = cache.height = Math.round(ch * dpr);
                    rebuild();
                }
                /* Everything but the object being dragged goes into the cache once. */
                var live = null, dragging = -1;
                function rebuild() {
                    cctx.setTransform(1, 0, 0, 1, 0, 0);
                    cctx.clearRect(0, 0, cache.width, cache.height);
                    cctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
                    if (photo) cctx.drawImage(photo, 0, 0, W, H);
                    strokes.forEach(function (s, i) { if (i !== dragging) draw(cctx, s, env); });
                    render();
                }
                function render() {
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(cache, 0, 0);
                    ctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
                    if (dragging >= 0) draw(ctx, strokes[dragging], env);
                    if (live) draw(ctx, live, env);
                    var s = selected();
                    if (s && tool !== 'eraser') chrome(ctx, s);
                }
                /* The selection: a dashed frame, handles, and the rotate knob. */
                function handles(s) {
                    var px = 1 / scale, list = [];
                    if (s.t === 'arrow') {
                        list.push({ id: 'a', p: s.p[0] }, { id: 'b', p: s.p[1] });
                        if (s.s === 'curve') list.push({ id: 'c', p: control(s) });
                        return list;
                    }
                    var b = box(s), hw = (b[2] - b[0]) / 2, hh = (b[3] - b[1]) / 2;
                    [['nw', -hw, -hh], ['ne', hw, -hh], ['se', hw, hh], ['sw', -hw, hh]].forEach(function (d) { list.push({ id: d[0], p: toPage(s, d[1], d[2]) }); });
                    if (ROTATES[s.t]) list.push({ id: 'rot', p: toPage(s, 0, -hh - 34 * px) });
                    if (s.t === 'bubble') list.push({ id: 'tail', p: s.p[2] });
                    return list;
                }
                function chrome(c, s) {
                    var px = 1 / scale;
                    c.save();
                    c.lineWidth = 1.5 * px; c.strokeStyle = '#7c6bf5'; c.setLineDash([5 * px, 4 * px]);
                    if (s.t === 'arrow') {
                        if (s.s === 'curve') { var q = control(s); c.beginPath(); c.moveTo(s.p[0][0], s.p[0][1]); c.lineTo(q[0], q[1]); c.lineTo(s.p[1][0], s.p[1][1]); c.stroke(); }
                    } else {
                        var b = box(s), cc = [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2], m = 6 * px;
                        c.translate(cc[0], cc[1]); if (s.r && !FREE[s.t]) c.rotate(s.r);
                        c.strokeRect(b[0] - cc[0] - m, b[1] - cc[1] - m, b[2] - b[0] + 2 * m, b[3] - b[1] + 2 * m);
                        if (ROTATES[s.t]) { c.beginPath(); c.moveTo(0, b[1] - cc[1] - m); c.lineTo(0, b[1] - cc[1] - 34 * px); c.stroke(); }
                        c.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
                    }
                    c.setLineDash([]);
                    handles(s).forEach(function (hd) {
                        c.beginPath(); c.arc(hd.p[0], hd.p[1], (hd.id === 'rot' ? 8 : 7) * px, 0, Math.PI * 2);
                        c.fillStyle = hd.id === 'rot' || hd.id === 'tail' || hd.id === 'c' ? '#7c6bf5' : '#ffffff';
                        c.shadowColor = 'rgba(0,0,0,.35)'; c.shadowBlur = 4 * px;
                        c.fill(); c.shadowColor = 'transparent';
                        c.lineWidth = 2 * px; c.strokeStyle = '#7c6bf5'; c.stroke();
                    });
                    c.restore();
                }

                var ro = window.ResizeObserver ? new ResizeObserver(layout) : null;
                if (ro) ro.observe(stage); else window.addEventListener('resize', layout);
                layout(); paintChrome(); more(rowTools);
                window.addEventListener('resize', function () { more(rowTools); more(rowOpts); });

                bUndo.addEventListener('click', function () { if (!undo.length) return; redo.push(JSON.stringify(strokes)); strokes = JSON.parse(undo.pop()); sel = -1; rebuild(); paintChrome(); });
                bRedo.addEventListener('click', function () { if (!redo.length) return; undo.push(JSON.stringify(strokes)); strokes = JSON.parse(redo.pop()); sel = -1; rebuild(); paintChrome(); });

                /* ── pointers ── */
                function at(e) { var r = canvas.getBoundingClientRect(); return [(e.clientX - r.left) / scale, (e.clientY - r.top) / scale]; }
                function point(e) {
                    var p = at(e); p = [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10];
                    if (e.pointerType === 'pen' && e.pressure > 0) p.push(Math.round(e.pressure * 100) / 100);
                    return p;
                }
                function topmost(p) {
                    for (var i = strokes.length - 1; i >= 0; i--) if (hits(strokes[i], p[0], p[1], 10 / scale)) return i;
                    return -1;
                }
                function handleAt(p) {
                    var s = selected(); if (!s) return null;
                    var hs = handles(s);
                    for (var i = hs.length - 1; i >= 0; i--) if (dist(hs[i].p, p) <= 16 / scale) return hs[i].id;
                    return null;
                }

                var pointer = null, drag = null, erasing = false, lastTap = 0;
                canvas.addEventListener('pointerdown', function (e) {
                    hint.classList.add('is-gone');
                    // writing in a bubble or a text: a tap elsewhere finishes it — it never starts another shape
                    var typing = stage.querySelector('.ink-edit');
                    if (typing) { e.preventDefault(); typing.blur(); return; }
                    if (pointer !== null) { // a second finger: undo the half-made line or move
                        if (live) live = null;
                        if (drag && drag.moved) { strokes = JSON.parse(drag.before); undo.pop(); }
                        drag = null; dragging = -1; pointer = null; rebuild(); return;
                    }
                    var p = at(e);
                    pointer = e.pointerId;
                    try { canvas.setPointerCapture(e.pointerId); } catch (er) {}

                    if (tool === 'eraser') { erasing = false; eraseAt(p); return; }

                    // the selection's handles and body win over drawing a new mark
                    var hd = handleAt(p);
                    var onSel = sel >= 0 && hits(strokes[sel], p[0], p[1], 10 / scale);
                    if (hd || onSel || tool === 'select') {
                        var idx = hd || onSel ? sel : topmost(p);
                        if (idx < 0) { sel = -1; pointer = null; paintChrome(); render(); return; }
                        var now = Date.now();
                        if (idx === sel && !hd && now - lastTap < 350 && (strokes[idx].t === 'text' || strokes[idx].t === 'bubble')) { pointer = null; editText(idx); return; }
                        lastTap = now;
                        sel = idx;
                        drag = { id: hd || 'move', start: p, orig: clone(strokes[idx]), before: JSON.stringify(strokes), moved: false, idx: idx };
                        dragging = idx; rebuild(); paintChrome();
                        return;
                    }

                    sel = -1;
                    var w = Math.round(BASE[tool] * unit * size * 10) / 10;
                    var pt = point(e);
                    if (tool === 'pen' || tool === 'marker') live = { t: tool, c: tool === 'marker' ? (colour === inks[0] ? HIGHLIGHT : colour) : colour, w: w, p: [pt] };
                    else if (tool === 'step') {
                        snapshot();
                        strokes.push({ t: 'step', c: colour, w: Math.round(BASE.step * unit * size), p: [[Math.round(p[0]), Math.round(p[1])]], n: nextStep() });
                        sel = strokes.length - 1; pointer = null; rebuild(); paintChrome(); return;
                    } else if (tool === 'text') {
                        pointer = null;
                        snapshot();
                        strokes.push({ t: 'text', c: colour, w: Math.round(BASE.text * unit * size), p: [[Math.round(p[0]), Math.round(p[1] - BASE.text * unit * size * 0.6)]], x: '' });
                        sel = strokes.length - 1; setTool('select'); rebuild(); paintChrome(); editText(sel, true); return;
                    } else {
                        live = { t: tool, c: tool === 'blur' ? '#000000' : colour, w: tool === 'bubble' ? Math.round(BASE.bubble * unit * size) : w, p: [[p[0], p[1]], [p[0], p[1]]] };
                        if (tool === 'arrow') live.s = arrowStyle;
                    }
                    render();
                });
                canvas.addEventListener('pointermove', function (e) {
                    var p = at(e);
                    if (e.pointerId !== pointer) {
                        if (pointer === null && e.pointerType === 'mouse') canvas.classList.toggle('is-move', !!(handleAt(p) || (sel >= 0 && hits(strokes[sel], p[0], p[1], 10 / scale))));
                        return;
                    }
                    if (tool === 'eraser') { eraseAt(p); return; }
                    if (drag) { transform(p); return; }
                    if (!live) return;
                    if (live.t === 'pen' || live.t === 'marker') (e.getCoalescedEvents ? e.getCoalescedEvents() : [e]).forEach(function (ev) { if (live.p.length < 4990) live.p.push(point(ev)); });
                    else live.p[1] = [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10];
                    render();
                });
                function finishPointer(e) {
                    if (e.pointerId !== pointer) return;
                    pointer = null;
                    if (tool === 'eraser') { erasing = false; return; }
                    if (drag) {
                        var tapped = !drag.moved && drag.id === 'move' && strokes[drag.idx] && (strokes[drag.idx].t === 'text' || strokes[drag.idx].t === 'bubble') ? drag.idx : -1;
                        drag = null; dragging = -1; rebuild(); paintChrome();
                        if (tapped >= 0) editText(tapped); // a tap on a bubble or a text writes in it; a drag moves it
                        return;
                    }
                    if (!live) return;
                    var s = live; live = null;
                    var tiny = dist(s.p[0], s.p[s.p.length - 1]) < 8 / scale;
                    if (s.t === 'pen' || s.t === 'marker') {
                        if (s.p.length === 1) s.p.push([s.p[0][0] + 0.1, s.p[0][1] + 0.1]); // a dot
                    } else if (tiny) {
                        // a tap with a shape tool: a sensibly sized one there
                        var d = (s.t === 'arrow' ? 140 : 180) * unit;
                        s.p[1] = [s.p[0][0] + d, s.p[0][1] + (s.t === 'arrow' ? -d * 0.5 : d * 0.62)];
                    }
                    if (s.t === 'bubble') {
                        var b = box(s);
                        s.p = [[b[0], b[1]], [b[2], b[3]], [Math.round(b[0] + (b[2] - b[0]) * 0.25), Math.round(b[3] + 70 * unit)]];
                    }
                    if (BOXES[s.t] || s.t === 'arrow') s.p = s.p.map(function (q) { return [Math.round(q[0] * 10) / 10, Math.round(q[1] * 10) / 10]; });
                    snapshot();
                    strokes.push(s);
                    if (!FREE[s.t]) sel = strokes.length - 1;
                    rebuild(); paintChrome();
                    if (s.t === 'bubble') { setTool('select'); editText(strokes.length - 1, true); }
                }
                canvas.addEventListener('pointerup', finishPointer);
                canvas.addEventListener('pointercancel', function (e) { if (e.pointerId === pointer) { pointer = null; live = null; if (drag) { if (drag.moved) { strokes = JSON.parse(drag.before); undo.pop(); } drag = null; dragging = -1; } rebuild(); } });

                /* Move, resize, rotate, bend: always from the shape as it was when the drag began. */
                function transform(p) {
                    var o0 = drag.orig, s = strokes[sel];
                    if (!drag.moved) { if (dist(p, drag.start) < 3 / scale) return; drag.moved = true; snapshot(); }
                    var dx = p[0] - drag.start[0], dy = p[1] - drag.start[1];
                    var id = drag.id;
                    if (id === 'move') {
                        s.p = o0.p.map(function (q) { var r = q.slice(); r[0] = Math.round((q[0] + dx) * 10) / 10; r[1] = Math.round((q[1] + dy) * 10) / 10; return r; });
                    } else if (id === 'a' || id === 'b' || id === 'c' || id === 'tail') {
                        var idx = { a: 0, b: 1, c: 2, tail: 2 }[id];
                        s.p[idx] = [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10];
                    } else if (id === 'rot') {
                        var cc = centre(o0), a0 = Math.atan2(drag.start[1] - cc[1], drag.start[0] - cc[0]), a1 = Math.atan2(p[1] - cc[1], p[0] - cc[0]);
                        var da = a1 - a0;
                        var snapTo = Math.round(((o0.r || 0) + da) / (Math.PI / 12)) * (Math.PI / 12);
                        if (Math.abs(snapTo - ((o0.r || 0) + da)) < 0.05) da = snapTo - (o0.r || 0); // a gentle snap every 15°
                        if (FREE[s.t]) s.p = o0.p.map(function (q) { var r = rot(q[0] - cc[0], q[1] - cc[1], da); var n = q.slice(); n[0] = Math.round((cc[0] + r[0]) * 10) / 10; n[1] = Math.round((cc[1] + r[1]) * 10) / 10; return n; });
                        else s.r = Math.round((((o0.r || 0) + da + Math.PI * 3) % (Math.PI * 2) - Math.PI) * 10000) / 10000;
                    } else { // a corner: the opposite corner stays where it is
                        resize(s, o0, id, p);
                    }
                    render();
                }
                function resize(s, o0, id, p) {
                    var b = box(o0), hw = (b[2] - b[0]) / 2, hh = (b[3] - b[1]) / 2;
                    var sx = id === 'ne' || id === 'se' ? 1 : -1, sy = id === 'se' || id === 'sw' ? 1 : -1;
                    var anchorL = [-sx * hw, -sy * hh], anchor = toPage(o0, anchorL[0], anchorL[1]);
                    var pl = rot(p[0] - anchor[0], p[1] - anchor[1], -(o0.r || 0)); // pointer relative to the anchor, in the shape's frame
                    var min = 12 * unit;
                    if (s.t === 'step') {
                        s.w = Math.max(Math.round(8 * unit), Math.round(Math.max(sx * pl[0], sy * pl[1], 16 * unit) / 2));
                        s.p = [[Math.round(anchor[0] + sx * s.w), Math.round(anchor[1] + sy * s.w)]];
                        return;
                    }
                    if (s.t === 'text') {
                        var k = Math.max(0.2, Math.max(Math.abs(pl[0]) / (2 * hw || 1), Math.abs(pl[1]) / (2 * hh || 1)));
                        s.w = Math.max(10, Math.round(o0.w * k));
                        var nb = box({ t: 'text', w: s.w, x: s.x, p: [[0, 0]] }), nhw = nb[2] / 2, nhh = nb[3] / 2;
                        var c = rot(sx * nhw, sy * nhh, o0.r || 0), cp = [anchor[0] + c[0], anchor[1] + c[1]];
                        s.p = [[Math.round(cp[0] - nhw), Math.round(cp[1] - nhh)]];
                        return;
                    }
                    var nw = Math.max(min, sx * pl[0]), nh = Math.max(min, sy * pl[1]);
                    if (FREE[s.t]) { // pen / highlighter: scale the line itself
                        var fx = nw / (2 * hw || 1), fy = nh / (2 * hh || 1);
                        s.p = o0.p.map(function (q) { var n = q.slice(); n[0] = Math.round((anchor[0] + (q[0] - anchor[0]) * fx) * 10) / 10; n[1] = Math.round((anchor[1] + (q[1] - anchor[1]) * fy) * 10) / 10; return n; });
                        return;
                    }
                    var cl = rot(sx * nw / 2, sy * nh / 2, o0.r || 0), cpg = [anchor[0] + cl[0], anchor[1] + cl[1]];
                    s.p[0] = [Math.round((cpg[0] - nw / 2) * 10) / 10, Math.round((cpg[1] - nh / 2) * 10) / 10];
                    s.p[1] = [Math.round((cpg[0] + nw / 2) * 10) / 10, Math.round((cpg[1] + nh / 2) * 10) / 10];
                }

                function eraseAt(p) {
                    var i = topmost(p);
                    if (i < 0) return;
                    if (!erasing) { snapshot(); erasing = true; }
                    strokes.splice(i, 1); rebuild(); paintChrome();
                }

                /* Text and bubbles: typed into a box over the shape. */
                function editText(i, fresh) {
                    var s = strokes[i]; if (!s) return;
                    var stageBox = stage.getBoundingClientRect(), cv = canvas.getBoundingClientRect();
                    var b = box(s), multi = s.t === 'bubble';
                    var box2 = h(multi ? 'textarea' : 'input', 'ink-edit');
                    if (!multi) box2.type = 'text';
                    box2.maxLength = multi ? 600 : 300; box2.value = s.x || ''; box2.placeholder = T.ink_text_ph; box2.setAttribute('enterkeyhint', 'done');
                    var left = cv.left - stageBox.left + b[0] * scale, topY = cv.top - stageBox.top + b[1] * scale;
                    box2.style.left = Math.max(6, Math.min(left, stageBox.width - 220)) + 'px';
                    box2.style.top = Math.max(6, topY) + 'px';
                    box2.style.width = Math.max(200, Math.min(stageBox.width - 12, (b[2] - b[0]) * scale + 20)) + 'px';
                    if (multi) box2.style.height = Math.max(70, (b[3] - b[1]) * scale) + 'px';
                    stage.appendChild(box2);
                    root.classList.add('is-typing');
                    var vv = window.visualViewport;
                    function keepVisible() { // re-measured from the picture as it now sits; the keyboard covers the bottom, so lift the box into what is left
                        if (!box2.isConnected) return;
                        var sb = stage.getBoundingClientRect(), c2 = canvas.getBoundingClientRect(), b2 = box(s), bh = box2.offsetHeight || 70;
                        var l2 = c2.left - sb.left + b2[0] * scale, t2 = c2.top - sb.top + b2[1] * scale;
                        box2.style.left = Math.max(6, Math.min(l2, sb.width - 220)) + 'px';
                        box2.style.width = Math.max(200, Math.min(sb.width - 12, (b2[2] - b2[0]) * scale + 20)) + 'px';
                        var room = vv ? vv.offsetTop + vv.height - sb.top - bh - 10 : sb.height - bh - 6;
                        box2.style.top = Math.max(6, Math.min(Math.max(6, t2), room)) + 'px';
                    }
                    if (vv) vv.addEventListener('resize', keepVisible);
                    box2.focus();
                    requestAnimationFrame(keepVisible);
                    setTimeout(keepVisible, 350);
                    var opened = Date.now();
                    var before = JSON.stringify(strokes), closed = false;
                    function commit() {
                        if (closed) return;
                        closed = true;
                        root.classList.remove('is-typing');
                        if (vv) vv.removeEventListener('resize', keepVisible);
                        var text = box2.value.replace(/\r/g, '').trim();
                        box2.remove();
                        if (!text && s.t === 'text') { strokes.splice(strokes.indexOf(s), 1); sel = -1; if (fresh) undo.pop(); rebuild(); paintChrome(); return; }
                        if (text === (s.x || '') && !fresh) return;
                        if (!fresh) { undo.push(before); redo = []; }
                        s.x = text; changed = true;
                        if (s.t === 'bubble') { // grow the bubble to fit what was written
                            var bb = box(s), lines = wrap(text, textFont(s.w), Math.max(10, bb[2] - bb[0] - s.w * 1.4));
                            var need = lines.length * s.w * 1.25 + s.w * 1.4;
                            if (bb[3] - bb[1] < need) { var top1 = Math.min(s.p[0][1], s.p[1][1]); if (s.p[0][1] > s.p[1][1]) s.p[0][1] = top1 + need; else s.p[1][1] = top1 + need; }
                        }
                        rebuild(); paintChrome();
                    }
                    box2.addEventListener('keydown', function (ev) {
                        if (ev.key === 'Enter' && (!multi || !ev.shiftKey)) { ev.preventDefault(); commit(); }
                        if (ev.key === 'Escape') { ev.preventDefault(); commit(); }
                        ev.stopPropagation();
                    });
                    box2.addEventListener('blur', function () {
                        // the very tap that opened the box can land a moment later and take its focus: hold on to it
                        if (Date.now() - opened < 450) { setTimeout(function () { if (!closed) box2.focus(); }, 0); return; }
                        commit();
                    });
                }

                /* Leaving. */
                function finish(result) {
                    if (ro) ro.disconnect(); else window.removeEventListener('resize', layout);
                    document.removeEventListener('keydown', keys);
                    root.remove();
                    if (photo && o.image instanceof Blob) URL.revokeObjectURL(photo.src);
                    resolve(result);
                }
                x.addEventListener('click', function () {
                    if (!changed) { finish(null); return; }
                    if (root.querySelector('.ink-ask')) return;
                    var a = h('div', 'ink-ask'), row = h('div');
                    var no = h('button', 'no', T.ink_leave), yes = h('button', 'yes', T.ink_keep);
                    no.type = yes.type = 'button';
                    no.addEventListener('click', function () { finish(null); });
                    yes.addEventListener('click', function () { a.remove(); });
                    row.append(no, yes); a.append(h('b', null, T.ink_discard_q), row);
                    root.appendChild(a);
                });
                done.addEventListener('click', function () {
                    if (document.activeElement && document.activeElement.classList.contains('ink-edit')) document.activeElement.blur();
                    if (!changed) { finish(null); return; } // nothing new: leave the picture as it was
                    var busy = h('div', 'ink-busy'); busy.appendChild(h('span')); root.appendChild(busy);
                    strokes = strokes.filter(function (s) { return s.t !== 'text' || (s.x && s.x.trim()); });
                    var out = document.createElement('canvas');
                    out.width = W; out.height = H;
                    var octx = out.getContext('2d');
                    if (photo) octx.drawImage(photo, 0, 0, W, H);
                    else { octx.fillStyle = '#fffdf8'; octx.fillRect(0, 0, W, H); }
                    strokes.forEach(function (s) { draw(octx, s, env); });
                    out.toBlob(function (blob) {
                        if (!blob) { busy.remove(); return; }
                        finish({ blob: blob, w: W, h: H, strokes: strokes });
                    }, photo ? 'image/jpeg' : 'image/png', 0.92);
                });
                function keys(e) {
                    if (e.target && e.target.classList && e.target.classList.contains('ink-edit')) return;
                    var mod = e.metaKey || e.ctrlKey;
                    if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); (e.shiftKey ? bRedo : bUndo).click(); return; }
                    if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicate(); return; }
                    var s = selected(); if (!s) return;
                    if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(); return; }
                    var step = (e.shiftKey ? 10 : 1) / scale, mv = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
                    if (mv) { e.preventDefault(); snapshot(); s.p = s.p.map(function (q) { var r = q.slice(); r[0] += mv[0]; r[1] += mv[1]; return r; }); rebuild(); }
                }
                document.addEventListener('keydown', keys);
            });
        });
    }

    window.MarginInk = { open: open };
})();
</script>
