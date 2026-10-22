/**
 * The QR's drawing, as SVG path data in MODULE units (one module = 1). Pure:
 * a matrix and a style in, paths out — the editor, Preview and the printed
 * page all draw from this, so what is designed is what prints.
 *
 * Three parts, styled separately:
 *   body      every dark module outside the three finder squares
 *   eyeFrame  the 7×7 ring of each finder
 *   eyeBall   the 3×3 centre of each finder
 *
 * Asymmetric eyes (leaf, teardrop, shield) are drawn for the top-left corner,
 * pointing at the code's centre; the other two corners are mirrored so they
 * point inwards too.
 */

export const BODY = ['square', 'rounded', 'extra-rounded', 'dots', 'classy', 'classy-rounded', 'liquid', 'small-square', 'diamond', 'star', 'plus', 'v-bars', 'h-bars'];
export const FRAMES = ['square', 'rounded', 'extra-rounded', 'circle', 'leaf', 'teardrop', 'cushion', 'octagon', 'shield'];
// Measured, not guessed: a diamond, star or dotted centre and a dotted frame break the
// 1:1:3:1:1 proportions readers look for, and fail most scans (the qr-scan test), so they are not offered.
export const BALLS = ['square', 'rounded', 'circle', 'leaf', 'teardrop', 'cushion', 'octagon'];

const f = (n) => Math.round(n * 1000) / 1000;

/** A rectangle with its own radius at each corner: [topLeft, topRight, bottomRight, bottomLeft]. */
export function rr(x, y, w, h, [a, b, c, d]) {
    const m = Math.min(w, h) / 2;
    [a, b, c, d] = [a, b, c, d].map((r) => Math.max(0, Math.min(r, m)));
    return `M${f(x + a)} ${f(y)}H${f(x + w - b)}${b ? `A${f(b)} ${f(b)} 0 0 1 ${f(x + w)} ${f(y + b)}` : ''}`
        + `V${f(y + h - c)}${c ? `A${f(c)} ${f(c)} 0 0 1 ${f(x + w - c)} ${f(y + h)}` : ''}`
        + `H${f(x + d)}${d ? `A${f(d)} ${f(d)} 0 0 1 ${f(x)} ${f(y + h - d)}` : ''}`
        + `V${f(y + a)}${a ? `A${f(a)} ${f(a)} 0 0 1 ${f(x + a)} ${f(y)}` : ''}Z`;
}

const circle = (cx, cy, r) => `M${f(cx - r)} ${f(cy)}a${f(r)} ${f(r)} 0 1 0 ${f(2 * r)} 0a${f(r)} ${f(r)} 0 1 0 ${f(-2 * r)} 0Z`;
const poly = (pts) => `M${pts.map(([x, y]) => `${f(x)} ${f(y)}`).join('L')}Z`;

function star(cx, cy, R, r, points = 4) {
    const pts = [];
    for (let i = 0; i < points * 2; i++) {
        const a = (Math.PI * i) / points - Math.PI / 2;
        const k = i % 2 ? r : R;
        pts.push([cx + k * Math.cos(a), cy + k * Math.sin(a)]);
    }
    return poly(pts);
}

/** A concave fillet in the corner of an EMPTY cell, so liquid joins flow round inside bends. */
function fillet(x, y, corner, r) {
    switch (corner) {
        case 'tl': return `M${x} ${y}H${f(x + r)}A${r} ${r} 0 0 0 ${x} ${f(y + r)}Z`;
        case 'tr': return `M${x + 1} ${y}V${f(y + r)}A${r} ${r} 0 0 0 ${f(x + 1 - r)} ${y}Z`;
        case 'br': return `M${x + 1} ${y + 1}H${f(x + 1 - r)}A${r} ${r} 0 0 0 ${x + 1} ${f(y + 1 - r)}Z`;
        default: return `M${x} ${y + 1}V${f(y + 1 - r)}A${r} ${r} 0 0 0 ${f(x + r)} ${y + 1}Z`;
    }
}

// Centre rows/columns of the alignment patterns, per version (ISO/IEC 18004 Annex E).
const ALIGN = [[], [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50], [6, 30, 54], [6, 32, 58], [6, 34, 62],
    [6, 26, 46, 66], [6, 26, 48, 70], [6, 26, 50, 74], [6, 30, 54, 78], [6, 30, 56, 82], [6, 30, 58, 86], [6, 34, 62, 90], [6, 28, 50, 72, 94], [6, 26, 50, 74, 98],
    [6, 30, 54, 78, 102], [6, 28, 54, 80, 106], [6, 32, 58, 84, 110], [6, 30, 58, 86, 114], [6, 34, 62, 90, 118], [6, 26, 50, 74, 98, 122], [6, 30, 54, 78, 102, 126],
    [6, 26, 52, 78, 104, 130], [6, 30, 56, 82, 108, 134], [6, 34, 60, 86, 112, 138], [6, 30, 58, 86, 114, 142], [6, 34, 62, 90, 118, 146], [6, 30, 54, 78, 102, 126, 150],
    [6, 24, 50, 76, 102, 128, 154], [6, 28, 54, 80, 106, 132, 158], [6, 32, 58, 84, 110, 136, 162], [6, 26, 54, 82, 110, 138, 166], [6, 30, 58, 86, 114, 142, 170]];

/**
 * Is (r, c) part of an alignment pattern? Those small 5×5 targets are how a
 * reader corrects for a bent or tilted page, so they are always drawn as plain
 * square modules — whatever shape the body takes.
 */
export function inAlignment(n, r, c) {
    const pos = ALIGN[(n - 17) / 4] || [];
    for (const y of pos) {
        for (const x of pos) {
            if (inFinder(n, y, x)) continue;
            if (Math.abs(r - y) <= 2 && Math.abs(c - x) <= 2) return true;
        }
    }
    return false;
}

/** Is (r, c) inside one of the three finder patterns (with their light separator)? */
export function inFinder(n, r, c) {
    return (r < 8 && c < 8) || (r < 8 && c >= n - 8) || (r >= n - 8 && c < 8);
}

/** The body: one path for every dark module outside the finders. */
export function bodyPath(m, kind) {
    const { n } = m;
    const on = (r, c) => m.dark(r, c) && !inFinder(n, r, c);
    const out = [];

    if (kind === 'v-bars' || kind === 'h-bars') {
        const vert = kind === 'v-bars';
        for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (on(r, c) && inAlignment(n, r, c)) out.push(`M${c} ${r}h1v1h-1Z`);
        for (let a = 0; a < n; a++) {
            let start = -1;
            for (let b = 0; b <= n; b++) {
                const lit = b < n && (vert ? on(b, a) && !inAlignment(n, b, a) : on(a, b) && !inAlignment(n, a, b));
                if (lit && start < 0) start = b;
                if (!lit && start >= 0) {
                    const len = b - start;
                    out.push(vert ? rr(a + 0.1, start + 0.05, 0.8, len - 0.1, [0.4, 0.4, 0.4, 0.4]) : rr(start + 0.05, a + 0.1, len - 0.1, 0.8, [0.4, 0.4, 0.4, 0.4]));
                    start = -1;
                }
            }
        }
        return out.join('');
    }

    for (let r = 0; r < n; r++) {
        for (let c = 0; c < n; c++) {
            if (!on(r, c)) {
                if (kind === 'liquid' && !inFinder(n, r, c)) {
                    const t = on(r - 1, c); const b = on(r + 1, c); const l = on(r, c - 1); const rt = on(r, c + 1);
                    if (t && l && on(r - 1, c - 1)) out.push(fillet(c, r, 'tl', 0.5));
                    if (t && rt && on(r - 1, c + 1)) out.push(fillet(c, r, 'tr', 0.5));
                    if (b && rt && on(r + 1, c + 1)) out.push(fillet(c, r, 'br', 0.5));
                    if (b && l && on(r + 1, c - 1)) out.push(fillet(c, r, 'bl', 0.5));
                }
                continue;
            }
            const x = c; const y = r;
            if (inAlignment(n, r, c)) { out.push(`M${x} ${y}h1v1h-1Z`); continue; }
            const t = on(r - 1, c); const b = on(r + 1, c); const l = on(r, c - 1); const rt = on(r, c + 1);
            // A corner may round only where neither side it touches continues.
            const free = [!t && !l, !t && !rt, !b && !rt, !b && !l];
            switch (kind) {
                case 'dots': out.push(circle(x + 0.5, y + 0.5, 0.45)); break;
                case 'small-square': out.push(rr(x + 0.15, y + 0.15, 0.7, 0.7, [0, 0, 0, 0])); break;
                case 'diamond': out.push(poly([[x + 0.5, y], [x + 1, y + 0.5], [x + 0.5, y + 1], [x, y + 0.5]])); break;
                case 'star': out.push(star(x + 0.5, y + 0.5, 0.56, 0.22)); break;
                case 'plus': out.push(`${rr(x + 0.32, y, 0.36, 1, [0.1, 0.1, 0.1, 0.1])}${rr(x, y + 0.32, 1, 0.36, [0.1, 0.1, 0.1, 0.1])}`); break;
                case 'rounded': out.push(rr(x, y, 1, 1, free.map((v) => (v ? 0.3 : 0)))); break;
                case 'extra-rounded':
                case 'liquid': out.push(rr(x, y, 1, 1, free.map((v) => (v ? 0.5 : 0)))); break;
                case 'classy': out.push(rr(x, y, 1, 1, [free[0] ? 0.5 : 0, 0, free[2] ? 0.5 : 0, 0])); break;
                case 'classy-rounded': out.push(rr(x, y, 1, 1, [free[0] ? 0.5 : 0, free[1] ? 0.2 : 0, free[2] ? 0.5 : 0, free[3] ? 0.2 : 0])); break;
                default: out.push(`M${x} ${y}h1v1h-1Z`);
            }
        }
    }
    return out.join('');
}

/** One closed outline of size s at (x, y) — the outer edge of a frame, or a ball. Drawn top-left-facing. */
function outline(kind, x, y, s) {
    switch (kind) {
        case 'rounded': return rr(x, y, s, s, [s * 0.22, s * 0.22, s * 0.22, s * 0.22]);
        case 'extra-rounded': return rr(x, y, s, s, [s * 0.38, s * 0.38, s * 0.38, s * 0.38]);
        case 'circle': return circle(x + s / 2, y + s / 2, s / 2);
        case 'leaf': return rr(x, y, s, s, [s / 2, 0, s / 2, 0]);
        case 'teardrop': return rr(x, y, s, s, [s / 2, s / 2, 0, s / 2]);
        case 'octagon': {
            const k = s * 0.29;
            return poly([[x + k, y], [x + s - k, y], [x + s, y + k], [x + s, y + s - k], [x + s - k, y + s], [x + k, y + s], [x, y + s - k], [x, y + k]]);
        }
        case 'cushion': {
            const k = s * 0.08;
            return `M${f(x + s / 2)} ${f(y)}C${f(x + s - k)} ${f(y)} ${f(x + s)} ${f(y + k)} ${f(x + s)} ${f(y + s / 2)}`
                + `C${f(x + s)} ${f(y + s - k)} ${f(x + s - k)} ${f(y + s)} ${f(x + s / 2)} ${f(y + s)}`
                + `C${f(x + k)} ${f(y + s)} ${f(x)} ${f(y + s - k)} ${f(x)} ${f(y + s / 2)}`
                + `C${f(x)} ${f(y + k)} ${f(x + k)} ${f(y)} ${f(x + s / 2)} ${f(y)}Z`;
        }
        case 'shield': {
            const k = s * 0.15;
            return `M${f(x + k)} ${f(y)}H${f(x + s - k)}A${f(k)} ${f(k)} 0 0 1 ${f(x + s)} ${f(y + k)}V${f(y + s * 0.55)}`
                + `Q${f(x + s)} ${f(y + s * 0.9)} ${f(x + s / 2)} ${f(y + s)}Q${f(x)} ${f(y + s * 0.9)} ${f(x)} ${f(y + s * 0.55)}`
                + `V${f(y + k)}A${f(k)} ${f(k)} 0 0 1 ${f(x + k)} ${f(y)}Z`;
        }
        default: return `M${f(x)} ${f(y)}h${f(s)}v${f(s)}h${f(-s)}Z`;
    }
}

/** A finder's ring: the outline minus the same outline one module in (even-odd). */
export function framePath(kind) {
    return outline(kind, 0, 0, 7) + outline(kind, 1, 1, 5);
}

/** A finder's centre (3×3 at offset 2). */
export function ballPath(kind) {
    return outline(kind, 2, 2, 3);
}

/**
 * Where each eye sits and how it is turned so asymmetric shapes face the centre.
 * Returns an SVG transform per corner (module units, before the margin).
 */
export function eyeTransforms(n, flips = {}) {
    const at = {
        tl: { x: 0, y: 0, sx: 1, sy: 1 },
        tr: { x: n - 7, y: 0, sx: -1, sy: 1 },
        bl: { x: 0, y: n - 7, sx: 1, sy: -1 },
    };
    const out = {};
    Object.entries(at).forEach(([k, v]) => {
        let { sx, sy } = v;
        if (flips[k]) { sx = -sx; sy = -sy; }
        out[k] = `translate(${v.x + 3.5} ${v.y + 3.5}) scale(${sx} ${sy}) translate(-3.5 -3.5)`;
    });
    return out;
}
