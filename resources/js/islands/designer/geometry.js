/**
 * Geometry for the designer. Every length is a CSS pixel at 96 dpi ("design
 * px"); an element's x/y is the top-left of its UNROTATED box and rotation
 * turns it about its centre, clockwise for positive degrees.
 */

export const PX_PER_MM = 96 / 25.4;

export const mmToPx = (mm) => mm * PX_PER_MM;
export const pxToMm = (px) => px / PX_PER_MM;

export const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
export const round = (v, d = 2) => Math.round(v * 10 ** d) / 10 ** d;

const rad = (deg) => (deg * Math.PI) / 180;

export function rotatePoint(x, y, cx, cy, deg) {
    if (!deg) return { x, y };
    const r = rad(deg);
    const cos = Math.cos(r);
    const sin = Math.sin(r);
    const dx = x - cx;
    const dy = y - cy;

    return { x: cx + dx * cos - dy * sin, y: cy + dx * sin + dy * cos };
}

export const center = (el) => ({ x: el.x + el.width / 2, y: el.y + el.height / 2 });

/** The four corners of an element as drawn (rotation applied): tl, tr, br, bl. */
export function corners(el) {
    const c = center(el);
    return [
        [el.x, el.y],
        [el.x + el.width, el.y],
        [el.x + el.width, el.y + el.height],
        [el.x, el.y + el.height],
    ].map(([x, y]) => rotatePoint(x, y, c.x, c.y, el.rotation || 0));
}

/** Axis-aligned bounding box of an element as drawn. */
export function aabb(el) {
    if (!el.rotation) return { x: el.x, y: el.y, width: el.width, height: el.height };
    const pts = corners(el);
    const xs = pts.map((p) => p.x);
    const ys = pts.map((p) => p.y);
    const x = Math.min(...xs);
    const y = Math.min(...ys);

    return { x, y, width: Math.max(...xs) - x, height: Math.max(...ys) - y };
}

/** One box around several elements. */
export function unionBox(els) {
    if (!els.length) return null;
    const boxes = els.map(aabb);
    const x = Math.min(...boxes.map((b) => b.x));
    const y = Math.min(...boxes.map((b) => b.y));
    const r = Math.max(...boxes.map((b) => b.x + b.width));
    const b = Math.max(...boxes.map((bb) => bb.y + bb.height));

    return { x, y, width: r - x, height: b - y };
}

export function intersects(a, b) {
    return a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y;
}

/** Angle in degrees of the vector from (cx,cy) to (x,y), 0 = pointing right, clockwise positive. */
export const angleOf = (x, y, cx, cy) => (Math.atan2(y - cy, x - cx) * 180) / Math.PI;

export function normaliseAngle(deg) {
    let d = deg % 360;
    if (d > 180) d -= 360;
    if (d <= -180) d += 360;
    return round(d, 1);
}

/**
 * Snap a moving box to the page and to other elements.
 * Returns the offset to apply and the guide lines to draw.
 *
 * @param box       the moving box {x,y,width,height} (already moved)
 * @param others    boxes of every element not being moved
 * @param page      {width,height}
 * @param threshold design px
 */
export function snapBox(box, others, page, threshold) {
    const xs = [
        { v: 0, kind: 'page' },
        { v: page.width / 2, kind: 'center' },
        { v: page.width, kind: 'page' },
    ];
    const ys = [
        { v: 0, kind: 'page' },
        { v: page.height / 2, kind: 'center' },
        { v: page.height, kind: 'page' },
    ];
    for (const o of others) {
        xs.push({ v: o.x, kind: 'el', o }, { v: o.x + o.width / 2, kind: 'el', o }, { v: o.x + o.width, kind: 'el', o });
        ys.push({ v: o.y, kind: 'el', o }, { v: o.y + o.height / 2, kind: 'el', o }, { v: o.y + o.height, kind: 'el', o });
    }
    const mine = (start, size) => [start, start + size / 2, start + size];

    const best = (targets, own) => {
        let hit = null;
        for (const t of targets) {
            for (const m of own) {
                const d = t.v - m;
                if (Math.abs(d) <= threshold && (!hit || Math.abs(d) < Math.abs(hit.d))) hit = { d, v: t.v, t };
            }
        }
        return hit;
    };

    const hx = best(xs, mine(box.x, box.width));
    const hy = best(ys, mine(box.y, box.height));
    const dx = hx ? hx.d : 0;
    const dy = hy ? hy.d : 0;
    const guides = [];
    const moved = { x: box.x + dx, y: box.y + dy, width: box.width, height: box.height };
    if (hx) {
        const span = hx.t.o ? [Math.min(moved.y, hx.t.o.y), Math.max(moved.y + moved.height, hx.t.o.y + hx.t.o.height)] : [0, page.height];
        guides.push({ axis: 'x', v: hx.v, from: span[0], to: span[1], kind: hx.t.kind });
    }
    if (hy) {
        const span = hy.t.o ? [Math.min(moved.x, hy.t.o.x), Math.max(moved.x + moved.width, hy.t.o.x + hy.t.o.width)] : [0, page.width];
        guides.push({ axis: 'y', v: hy.v, from: span[0], to: span[1], kind: hy.t.kind });
    }

    return { dx, dy, guides };
}
