/**
 * What can be added to a page: text styles, shapes, lines, the built-in
 * graphics and the live picture fields — each as a factory returning a new
 * element, plus the SVG geometry that draws shapes and graphics.
 *
 * Built-in graphics are vector art drawn here, in code, with a fixed palette
 * per graphic. Recolouring maps one palette colour to another (element.colors),
 * which is how "pick a new colour for the medal's gold" changes every gold part.
 * Uploaded pictures are raster only — an uploaded SVG could carry script.
 */
import { uid } from './model';

/* ── Shape geometry ─────────────────────────────────────────────────── */

const pts = (list) => list.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ');

function regular(n, w, h, inset, star = false) {
    const cx = w / 2;
    const cy = h / 2;
    const rx = w / 2 - inset;
    const ry = h / 2 - inset;
    const out = [];
    const count = star ? n * 2 : n;
    for (let i = 0; i < count; i++) {
        const a = -Math.PI / 2 + (i * 2 * Math.PI) / count;
        const k = star && i % 2 ? 0.45 : 1;
        out.push([cx + Math.cos(a) * rx * k, cy + Math.sin(a) * ry * k]);
    }
    return out;
}

/** SVG markup pieces for a shape of w×h with a stroke of sw: { tag, attrs }. */
export function shapeGeometry(el) {
    const w = el.width;
    const h = el.height;
    const sw = el.stroke?.width || 0;
    const i = sw / 2;
    switch (el.shape) {
        case 'ellipse':
            return { tag: 'ellipse', attrs: { cx: w / 2, cy: h / 2, rx: Math.max(0, w / 2 - i), ry: Math.max(0, h / 2 - i) } };
        case 'triangle':
            return { tag: 'polygon', attrs: { points: pts([[w / 2, i], [w - i, h - i], [i, h - i]]) } };
        case 'diamond':
            return { tag: 'polygon', attrs: { points: pts([[w / 2, i], [w - i, h / 2], [w / 2, h - i], [i, h / 2]]) } };
        case 'polygon':
            return { tag: 'polygon', attrs: { points: pts(regular(el.sides || 6, w, h, i)) } };
        case 'star':
            return { tag: 'polygon', attrs: { points: pts(regular(el.sides || 5, w, h, i, true)) } };
        case 'arrow':
            return { tag: 'polygon', attrs: { points: pts([[i, h * 0.3], [w * 0.62, h * 0.3], [w * 0.62, i], [w - i, h / 2], [w * 0.62, h - i], [w * 0.62, h * 0.7], [i, h * 0.7]]) } };
        case 'heart':
            return {
                tag: 'path',
                attrs: {
                    d: `M ${w / 2} ${h - i} C ${w * 0.1} ${h * 0.62}, ${i} ${h * 0.38}, ${i} ${h * 0.27} C ${i} ${h * 0.1}, ${w * 0.18} ${i}, ${w * 0.3} ${i} C ${w * 0.4} ${i}, ${w * 0.47} ${h * 0.06}, ${w / 2} ${h * 0.16} C ${w * 0.53} ${h * 0.06}, ${w * 0.6} ${i}, ${w * 0.7} ${i} C ${w * 0.82} ${i}, ${w - i} ${h * 0.1}, ${w - i} ${h * 0.27} C ${w - i} ${h * 0.38}, ${w * 0.9} ${h * 0.62}, ${w / 2} ${h - i} Z`,
                },
            };
        default: {
            const r = Math.max(0, Math.min(el.radius || 0, w / 2 - i, h / 2 - i));
            return { tag: 'rect', attrs: { x: i, y: i, width: Math.max(0, w - sw), height: Math.max(0, h - sw), rx: r, ry: r } };
        }
    }
}

/* ── Built-in graphics (viewBox 0 0 100 100 unless noted) ────────────── */

const circle = (cx, cy, r) => `M ${cx - r} ${cy} a ${r} ${r} 0 1 0 ${r * 2} 0 a ${r} ${r} 0 1 0 ${-r * 2} 0 Z`;
const poly = (list) => `M ${list.map(([x, y]) => `${x.toFixed(2)} ${y.toFixed(2)}`).join(' L ')} Z`;
const starPath = (cx, cy, r, n = 5, inner = 0.45) => {
    const out = [];
    for (let k = 0; k < n * 2; k++) {
        const a = -Math.PI / 2 + (k * Math.PI) / n;
        const rr = k % 2 ? r * inner : r;
        out.push([cx + Math.cos(a) * rr, cy + Math.sin(a) * rr]);
    }
    return poly(out);
};
const leaf = (x, y, len, wid, deg) => {
    const a = (deg * Math.PI) / 180;
    const tx = x + Math.cos(a) * len;
    const ty = y + Math.sin(a) * len;
    const nx = -Math.sin(a) * wid;
    const ny = Math.cos(a) * wid;
    const mx = (x + tx) / 2;
    const my = (y + ty) / 2;
    return `M ${x} ${y} Q ${mx + nx} ${my + ny} ${tx} ${ty} Q ${mx - nx} ${my - ny} ${x} ${y} Z`;
};
/** One side of a laurel: leaf pairs along an arc, each pointing up the stem. */
const branch = (mirror) => {
    let d = '';
    const steps = 8;
    for (let k = 0; k < steps; k++) {
        const theta = 100 + (k * 135) / (steps - 1); // bottom → top-left, degrees, SVG y-down
        const r = (theta * Math.PI) / 180;
        let x = 50 + Math.cos(r) * 36;
        const y = 54 + Math.sin(r) * 36;
        let along = theta + 90; // the stem's direction at this point
        if (mirror) { x = 100 - x; along = 180 - along; }
        const size = 12 - k * 0.6;
        d += leaf(x, y, size, size * 0.34, along - 32) + ' ' + leaf(x, y, size, size * 0.34, along + 32) + ' ';
    }
    return d;
};

export const GRAPHICS = [
    {
        key: 'medal', name: 'Medal', w: 60, h: 100, viewBox: '0 0 60 100', palette: ['#c0392b', '#1d4ed8', '#d4a017', '#f5c542'],
        parts: [
            { d: poly([[8, 0], [24, 0], [36, 44], [22, 44]]), c: 0 },
            { d: poly([[36, 0], [52, 0], [38, 44], [24, 44]]), c: 1 },
            { d: circle(30, 68, 28), c: 2 },
            { d: circle(30, 68, 21), c: 3 },
            { d: starPath(30, 68, 13), c: 2 },
        ],
    },
    {
        key: 'rosette', name: 'Rosette', w: 70, h: 100, viewBox: '0 0 70 100', palette: ['#1d4ed8', '#ffffff', '#d4a017'],
        parts: [
            { d: poly([[18, 50], [32, 52], [26, 98], [17, 88], [8, 96]]), c: 0 },
            { d: poly([[38, 52], [52, 50], [62, 96], [53, 88], [44, 98]]), c: 0 },
            { d: starPath(35, 35, 34, 18, 0.86), c: 0 },
            { d: circle(35, 35, 24), c: 1 },
            { d: circle(35, 35, 20), c: 2 },
        ],
    },
    {
        key: 'laurel', name: 'Laurel wreath', w: 100, h: 100, viewBox: '0 0 100 100', palette: ['#b8860b'],
        parts: [{ d: branch(false), c: 0 }, { d: branch(true), c: 0 }],
    },
    {
        key: 'seal', name: 'Seal', w: 100, h: 100, viewBox: '0 0 100 100', palette: ['#d4a017', '#f5c542', '#ffffff'],
        parts: [
            { d: starPath(50, 50, 49, 24, 0.88), c: 0 },
            { d: circle(50, 50, 38), c: 1 },
            { d: circle(50, 50, 33), c: 0 },
            { d: starPath(50, 50, 16), c: 2 },
        ],
    },
    {
        key: 'trophy', name: 'Trophy', w: 90, h: 100, viewBox: '0 0 90 100', palette: ['#d4a017', '#f5c542'],
        parts: [
            { d: 'M 20 6 H 70 V 30 C 70 50 58 60 45 62 C 32 60 20 50 20 30 Z', c: 1 },
            { d: 'M 20 12 H 6 C 4 30 12 40 24 42 L 22 36 C 14 34 11 26 12 18 H 20 Z M 70 12 H 84 C 86 30 78 40 66 42 L 68 36 C 76 34 79 26 78 18 H 70 Z', c: 0 },
            { d: 'M 40 62 H 50 V 78 H 40 Z', c: 0 },
            { d: 'M 26 78 H 64 V 88 H 26 Z M 20 88 H 70 V 96 H 20 Z', c: 0 },
            { d: starPath(45, 30, 10), c: 0 },
        ],
    },
    {
        key: 'ribbon-banner', name: 'Ribbon banner', w: 200, h: 50, viewBox: '0 0 200 50', palette: ['#1e3a8a', '#172554'],
        parts: [
            { d: poly([[0, 14], [30, 14], [30, 48], [0, 48], [10, 31]]), c: 1 },
            { d: poly([[170, 14], [200, 14], [190, 31], [200, 48], [170, 48]]), c: 1 },
            { d: poly([[20, 4], [180, 4], [180, 40], [20, 40]]), c: 0 },
        ],
    },
    {
        key: 'shield', name: 'Shield', w: 84, h: 100, viewBox: '0 0 84 100', palette: ['#1e3a8a', '#d4a017'],
        parts: [
            { d: 'M 42 0 L 84 12 V 46 C 84 74 64 90 42 100 C 20 90 0 74 0 46 V 12 Z', c: 1 },
            { d: 'M 42 7 L 77 17 V 46 C 77 70 60 84 42 92 C 24 84 7 70 7 46 V 17 Z', c: 0 },
        ],
    },
    {
        key: 'wave-top', name: 'Wave (top)', w: 300, h: 80, viewBox: '0 0 300 80', palette: ['#1e3a8a', '#3b82f6'],
        parts: [
            { d: 'M 0 0 H 300 V 46 C 240 74 170 30 100 52 C 60 64 25 62 0 54 Z', c: 1 },
            { d: 'M 0 0 H 300 V 30 C 230 56 165 16 95 38 C 55 50 22 46 0 40 Z', c: 0 },
        ],
    },
    {
        key: 'wave-bottom', name: 'Wave (bottom)', w: 300, h: 80, viewBox: '0 0 300 80', palette: ['#1e3a8a', '#3b82f6'],
        parts: [
            { d: 'M 0 80 H 300 V 26 C 250 18 215 36 160 32 C 95 26 45 2 0 26 Z', c: 1 },
            { d: 'M 0 80 H 300 V 42 C 245 34 210 52 155 48 C 92 42 44 20 0 42 Z', c: 0 },
        ],
    },
    {
        key: 'corner', name: 'Corner ornament', w: 100, h: 100, viewBox: '0 0 100 100', palette: ['#d4a017'],
        parts: [{ d: 'M 0 0 H 100 V 6 H 6 V 100 H 0 Z M 14 14 H 70 V 18 H 18 V 70 H 14 Z M 24 24 m -5 0 a 5 5 0 1 0 10 0 a 5 5 0 1 0 -10 0 Z', c: 0 }],
    },
    {
        key: 'divider', name: 'Divider', w: 300, h: 20, viewBox: '0 0 300 20', palette: ['#d4a017'],
        parts: [{ d: 'M 0 9 H 130 V 11 H 0 Z M 170 9 H 300 V 11 H 170 Z M 150 0 L 160 10 L 150 20 L 140 10 Z', c: 0 }],
    },
    {
        key: 'frame', name: 'Double frame', w: 300, h: 212, viewBox: '0 0 300 212', palette: ['#1e3a8a', '#d4a017'],
        parts: [
            { d: 'M 0 0 H 300 V 212 H 0 Z M 6 6 V 206 H 294 V 6 Z', c: 0, rule: 'evenodd' },
            { d: 'M 12 12 H 288 V 200 H 12 Z M 14 14 V 198 H 286 V 14 Z', c: 1, rule: 'evenodd' },
        ],
    },
    {
        key: 'stars', name: 'Stars', w: 200, h: 40, viewBox: '0 0 200 40', palette: ['#d4a017'],
        parts: [{ d: [20, 60, 100, 140, 180].map((x, k) => starPath(x, 20, k === 2 ? 19 : 14)).join(' '), c: 0 }],
    },
];

export const graphic = (key) => GRAPHICS.find((g) => g.key === key) || null;

/* ── Factories ──────────────────────────────────────────────────────── */

const base = (type, x, y, width, height, extra = {}) => ({
    id: uid(), type, name: '', x, y, width, height, rotation: 0, flipX: false, flipY: false, opacity: 1, locked: false, ...extra,
});

/** Text presets scale with the page, so a heading on a badge is not the heading of a poster. */
export function textElement(kind, size, placeholder, extra = {}) {
    const s = Math.max(0.35, Math.min(2.5, size.w / 1123));
    const preset = {
        heading: { fontSize: 48, fontWeight: 800, fontFamily: 'Montserrat' },
        subheading: { fontSize: 28, fontWeight: 600, fontFamily: 'Montserrat' },
        body: { fontSize: 16, fontWeight: 400, fontFamily: 'Montserrat' },
    }[kind] || { fontSize: 18, fontWeight: 400, fontFamily: 'Inter' };
    const fontSize = Math.round(preset.fontSize * s * 10) / 10;
    const width = kind === 'heading' ? size.w * 0.6 : kind === 'body' ? size.w * 0.4 : size.w * 0.45;
    const height = fontSize * 1.3;

    return base('text', (size.w - width) / 2, (size.h - height) / 2, width, height, {
        text: placeholder, ...preset, fontSize, italic: false, underline: false, strike: false, uppercase: false,
        color: '#0f172a', align: 'center', lineHeight: 1.3, letterSpacing: 0, list: 'none', background: null, ...extra,
    });
}

export function shapeElement(shape, size, extra = {}) {
    const d = Math.min(size.w, size.h) * 0.22;
    const w = shape === 'arrow' ? d * 1.6 : d;

    return base('shape', (size.w - w) / 2, (size.h - d) / 2, w, d, {
        shape, sides: shape === 'star' ? 5 : 6, fill: '#a5c8f5', fill2: null, gradientAngle: 90, radius: 0, stroke: null,
        text: '', fontFamily: 'Inter', fontSize: Math.round(d / 7), fontWeight: 600, italic: false, underline: false, strike: false,
        uppercase: false, color: '#0f172a', align: 'center', lineHeight: 1.3, letterSpacing: 0, list: 'none', ...extra,
    });
}

export function lineElement(variant, size) {
    const w = size.w * 0.3;
    return base('line', (size.w - w) / 2, size.h / 2, w, 4, {
        stroke: { color: '#0f172a', width: 4, style: variant === 'dashed' ? 'dashed' : variant === 'dotted' ? 'dotted' : 'solid' },
        capStart: 'none', capEnd: variant === 'arrow' ? 'arrow' : 'none',
    });
}

export function graphicElement(key, size) {
    const g = graphic(key);
    const longest = Math.min(size.w, size.h) * (g.w > g.h * 2 ? 0.6 : 0.25);
    const k = longest / Math.max(g.w, g.h);
    const w = g.w * k;
    const h = g.h * k;
    return base('image', (size.w - w) / 2, (size.h - h) / 2, w, h, { graphic: key, colors: {}, fit: 'fill', radius: 0, stroke: null });
}

export function assetElement(asset, size) {
    const maxW = size.w * 0.5;
    const maxH = size.h * 0.5;
    const k = Math.min(maxW / (asset.width || maxW), maxH / (asset.height || maxH), 1) || 1;
    const w = (asset.width || maxW) * k;
    const h = (asset.height || maxH) * k;
    return base('image', (size.w - w) / 2, (size.h - h) / 2, w, h, { asset: asset.uuid, colors: {}, fit: 'fill', radius: 0, stroke: null });
}

export function fieldElement(key, size) {
    // A photo is portrait 3:4, a flag landscape 4:3 and filled edge to edge; anything else square.
    const flag = /_flag$/.test(key);
    const h = Math.min(size.w, size.h) * (key === 'photo' ? 0.3 : flag ? 0.08 : 0.15);
    const w = key === 'photo' ? (h * 3) / 4 : flag ? (h * 4) / 3 : h;
    return base('image', (size.w - w) / 2, (size.h - h) / 2, w, h, { field: key, colors: {}, fit: key === 'photo' || flag ? 'cover' : 'contain', radius: flag ? 2 : 0, stroke: null });
}

export const SHAPES = ['rect', 'ellipse', 'triangle', 'diamond', 'polygon', 'star', 'arrow', 'heart'];

export const DEFAULT_COLORS = [
    '#000000', '#545454', '#737373', '#a6a6a6', '#d9d9d9', '#ffffff', '#1e3a8a',
    '#ff3131', '#ff5757', '#ff66c4', '#cb6ce6', '#8c52ff', '#5e17eb', '#7c6bf5',
    '#0097b2', '#0cc0df', '#5ce1e6', '#38b6ff', '#5271ff', '#004aad', '#172554',
    '#00bf63', '#7ed957', '#c1ff72', '#ffde59', '#ffbd59', '#ff914d', '#d4a017',
];
