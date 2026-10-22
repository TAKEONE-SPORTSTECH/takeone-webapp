/**
 * Pointer gestures on the page: move, resize, rotate, marquee. Each starts on
 * pointerdown, follows the pointer with window listeners (so leaving the page
 * mid-drag never drops the gesture) and is ONE history step.
 *
 * Coordinates arrive in screen px and are turned into design px with the
 * current zoom. Rotation-aware resizing works in the element's own frame: the
 * pointer is rotated back into it, the box is solved there, and the opposite
 * corner/edge stays where it was on the page.
 */
import * as M from './model';
import { aabb, unionBox, center, rotatePoint, angleOf, intersects, round, snapBox } from './geometry';

const MIN = 4;

function track(onMove, onUp) {
    const move = (e) => onMove(e);
    const up = (e) => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        window.removeEventListener('pointercancel', up);
        onUp(e);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', up);
}

/** Move the selection. Calls onClick(wasSelected) instead when the pointer never really moved. */
export function startMove(e, { store, zoom, ids, onClick }) {
    const s0 = store.get();
    const p0 = M.page(s0.doc, s0.pageIndex);
    const moving = p0.elements.filter((el) => ids.includes(el.id) && !el.locked);
    const start = { x: e.clientX, y: e.clientY };
    const originals = Object.fromEntries(moving.map((el) => [el.id, { x: el.x, y: el.y }]));
    const others = p0.elements.filter((el) => !ids.includes(el.id)).map(aabb);
    const box0 = unionBox(moving);
    let started = false;

    track(
        (ev) => {
            const dx = (ev.clientX - start.x) / zoom;
            const dy = (ev.clientY - start.y) / zoom;
            if (!started) {
                if (Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 3 || !moving.length) return;
                started = true;
                store.begin();
            }
            let sx = 0;
            let sy = 0;
            let guides = [];
            if (box0 && !ev.altKey) {
                const snap = snapBox({ ...box0, x: box0.x + dx, y: box0.y + dy }, others, { width: s0.size.w, height: s0.size.h }, 6 / zoom);
                sx = snap.dx;
                sy = snap.dy;
                guides = snap.guides;
            }
            const s = store.get();
            store.set({
                doc: M.patchElements(s.doc, s.pageIndex, Object.keys(originals), (el) => ({ x: round(originals[el.id].x + dx + sx), y: round(originals[el.id].y + dy + sy) })),
                guides,
                gesture: 'move',
            });
        },
        () => {
            if (started) {
                store.end();
                store.set({ guides: [], gesture: null });
            } else {
                onClick?.();
            }
        },
    );
}


/* ── snapping while resizing ── */

/** Lines an edge may snap to: the page's edges and centre, and every other element's edges and centres. */
function snapLines(state, exclude) {
    const pg = { w: state.size.w, h: state.size.h };
    const xs = [{ v: 0 }, { v: pg.w / 2, center: true }, { v: pg.w }];
    const ys = [{ v: 0 }, { v: pg.h / 2, center: true }, { v: pg.h }];
    M.page(state.doc, state.pageIndex).elements.filter((el) => !exclude.includes(el.id)).map(aabb).forEach((b) => {
        xs.push({ v: b.x }, { v: b.x + b.width / 2 }, { v: b.x + b.width });
        ys.push({ v: b.y }, { v: b.y + b.height / 2 }, { v: b.y + b.height });
    });
    return { xs, ys, pg };
}

function nearest(value, lines, threshold) {
    let hit = null;
    for (const l of lines) {
        const d = l.v - value;
        if (Math.abs(d) <= threshold && (!hit || Math.abs(d) < Math.abs(hit.d))) hit = { d, v: l.v, center: l.center };
    }
    return hit;
}

const guide = (axis, hit, pg) => ({ axis, v: hit.v, from: 0, to: axis === 'x' ? pg.h : pg.w, kind: hit.center ? 'center' : 'page' });

/**
 * Resize. `handle` is n/s/e/w/ne/nw/se/sw. Corners are proportional for
 * pictures, shapes, groups and text (text scales its font), sides stretch one
 * axis (text: width only, it rewraps). Shift flips proportional/free on a
 * corner; Alt resizes from the centre. The edge or corner being dragged snaps to
 * the page and to other elements (unrotated boxes); hold Ctrl/⌘ to resize freely.
 */
export function startResize(e, { store, zoom, handle }) {
    e.stopPropagation();
    e.preventDefault();
    const s0 = store.get();
    const els = M.selected(s0).filter((el) => !el.locked);
    if (!els.length) return;
    store.begin();
    const sx = handle.includes('e') ? 1 : handle.includes('w') ? -1 : 0;
    const sy = handle.includes('s') ? 1 : handle.includes('n') ? -1 : 0;
    const corner = sx !== 0 && sy !== 0;

    // Several elements resize as one box, proportionally, about the opposite corner.
    if (els.length > 1) {
        const box = unionBox(els);
        const ax = box.x + (sx === 1 ? 0 : sx === -1 ? box.width : box.width / 2);
        const ay = box.y + (sy === 1 ? 0 : sy === -1 ? box.height : box.height / 2);
        const rect = e.currentTarget.closest('.dz-pagewrap').getBoundingClientRect();
        const lines = snapLines(s0, els.map((x) => x.id));
        track(
            (ev) => {
                const px = (ev.clientX - rect.left) / zoom;
                const py = (ev.clientY - rect.top) / zoom;
                const fx = sx ? Math.abs(px - ax) / box.width : 0;
                const fy = sy ? Math.abs(py - ay) / box.height : 0;
                let f = Math.max(0.05, corner ? Math.max(fx, fy) : fx || fy);
                const guides = [];
                if (!(ev.ctrlKey || ev.metaKey)) {
                    const th = 6 / zoom;
                    const hx = sx ? nearest(ax + sx * f * box.width, lines.xs, th) : null;
                    const hy = sy ? nearest(ay + sy * f * box.height, lines.ys, th) : null;
                    const use = hx && (!hy || Math.abs(hx.d) <= Math.abs(hy.d)) ? 'x' : hy ? 'y' : null;
                    if (use === 'x') { f = (sx * (hx.v - ax)) / box.width; guides.push(guide('x', hx, lines.pg)); }
                    if (use === 'y') { f = (sy * (hy.v - ay)) / box.height; guides.push(guide('y', hy, lines.pg)); }
                    f = Math.max(0.05, f);
                }
                const s = store.get();
                store.set({
                    guides,
                    gesture: 'resize',
                    doc: M.patchElements(s.doc, s.pageIndex, els.map((x) => x.id), (el) => {
                        const o = els.find((x) => x.id === el.id);
                        return {
                            x: round(ax + (o.x - ax) * f), y: round(ay + (o.y - ay) * f),
                            width: round(o.width * f), height: round(o.height * f), ...M.scaleContent(o, f),
                        };
                    }),
                });
            },
            () => { store.end(); store.set({ gesture: null, guides: [] }); },
        );
        return;
    }

    const el0 = els[0];
    const c0 = center(el0);
    const rot = el0.rotation || 0;
    const w0 = el0.width;
    const h0 = el0.height;
    const isText = el0.type === 'text';
    // A text box set to squeeze or shrink a long line keeps its font size when
    // resized: dragging any handle changes the box only, and only a line too
    // long for the new box is made smaller (Renderer → FitLine).
    const keepsFont = isText && (el0.fit === 'squeeze' || el0.fit === 'shrink');
    const isLine = el0.type === 'line';
    const proportionalByType = corner && el0.type !== 'line';
    const rect = e.currentTarget.closest('.dz-pagewrap').getBoundingClientRect();
    const lines = snapLines(s0, [el0.id]);
    // Snapping works on straight boxes; a rotated one resizes freely.
    const canSnap = Math.abs(((rot % 360) + 360) % 360) < 0.01;

    track(
        (ev) => {
            const fromCentre = ev.altKey;
            const proportional = keepsFont ? false : isText && corner ? true : ev.shiftKey ? !proportionalByType : proportionalByType;
            const pw = { x: (ev.clientX - rect.left) / zoom, y: (ev.clientY - rect.top) / zoom };
            // The anchor (what stays put), in page space.
            const anchorLocal = fromCentre ? { x: 0, y: 0 } : { x: -sx * w0 / 2, y: -sy * h0 / 2 };
            const anchor = rotatePoint(c0.x + anchorLocal.x, c0.y + anchorLocal.y, c0.x, c0.y, rot);
            // The pointer in the element's own (unrotated) frame, relative to the anchor.
            const local = rotatePoint(pw.x, pw.y, anchor.x, anchor.y, -rot);
            let vx = (local.x - anchor.x) * (fromCentre ? 2 : 1);
            let vy = (local.y - anchor.y) * (fromCentre ? 2 : 1);

            let w = sx ? Math.max(MIN, sx * vx) : w0;
            let h = sy ? Math.max(MIN, sy * vy) : h0;
            if (proportional) {
                const f = Math.max(MIN / Math.min(w0, h0), (sx * vx * w0 + sy * vy * h0) / (w0 * w0 + h0 * h0));
                w = w0 * f;
                h = h0 * f;
            }
            if (isLine) h = h0;

            // Snap the edge (or corner) being dragged; the anchor stays where it is.
            const guides = [];
            if (canSnap && !fromCentre && !(ev.ctrlKey || ev.metaKey)) {
                const th = 6 / zoom;
                const snapY = sy && !isLine;
                const hx = sx ? nearest(anchor.x + sx * w, lines.xs, th) : null;
                const hy = snapY ? nearest(anchor.y + sy * h, lines.ys, th) : null;
                if (corner && proportional) {
                    const use = hx && (!hy || Math.abs(hx.d) <= Math.abs(hy.d)) ? 'x' : hy ? 'y' : null;
                    const f = use === 'x' ? (w + sx * hx.d) / w : use === 'y' ? (h + sy * hy.d) / h : 1;
                    if (use) {
                        w = Math.max(MIN, w * f);
                        h = Math.max(MIN, h * f);
                        guides.push(guide(use, use === 'x' ? hx : hy, lines.pg));
                    }
                } else {
                    if (hx) { w = Math.max(MIN, w + sx * hx.d); guides.push(guide('x', hx, lines.pg)); }
                    if (hy) { h = Math.max(MIN, h + sy * hy.d); guides.push(guide('y', hy, lines.pg)); }
                }
            }

            // New centre: from the anchor, half the new size along the handle's direction.
            // (A text box's y is its top edge, so when it re-measures after
            // rewrapping it grows downward on its own.)
            const half = fromCentre ? { x: 0, y: 0 } : { x: sx * w / 2, y: sy * h / 2 };
            const cLocal = { x: anchor.x + half.x, y: anchor.y + half.y };
            const c = rotatePoint(cLocal.x, cLocal.y, anchor.x, anchor.y, rot);

            const patch = { x: round(c.x - w / 2), y: round(c.y - h / 2), width: round(w), height: round(h) };
            if (isText && sy && (!corner || keepsFont)) {
                // A text box's height follows its text; the top/bottom handles set a taller box (never shorter than the text).
                patch.minHeight = round(h);
            }
            if (corner && proportional) {
                Object.assign(patch, M.scaleContent(el0, w / w0));
            } else if (el0.type === 'group') {
                patch.children = M.scaleChildren(el0.children, w / w0, h / h0);
            }
            const s = store.get();
            store.set({ gesture: 'resize', guides, doc: M.patchElements(s.doc, s.pageIndex, [el0.id], patch) });
        },
        () => { store.end(); store.set({ gesture: null, guides: [] }); },
    );
}

/** Rotate about the centre; snaps to 45° steps within 4°, Shift snaps to 15°. */
export function startRotate(e, { store, zoom }) {
    e.stopPropagation();
    e.preventDefault();
    const s0 = store.get();
    const els = M.selected(s0).filter((el) => !el.locked);
    if (!els.length) return;
    store.begin();
    const rect = e.currentTarget.closest('.dz-pagewrap').getBoundingClientRect();
    const box = els.length > 1 ? unionBox(els) : null;
    const c = box ? { x: box.x + box.width / 2, y: box.y + box.height / 2 } : center(els[0]);
    const p0 = { x: (e.clientX - rect.left) / zoom, y: (e.clientY - rect.top) / zoom };
    const a0 = angleOf(p0.x, p0.y, c.x, c.y);

    track(
        (ev) => {
            const p = { x: (ev.clientX - rect.left) / zoom, y: (ev.clientY - rect.top) / zoom };
            let delta = angleOf(p.x, p.y, c.x, c.y) - a0;
            const s = store.get();
            const patch = (el) => {
                const o = els.find((x) => x.id === el.id);
                let r = (o.rotation || 0) + delta;
                if (els.length === 1) {
                    const step = ev.shiftKey ? 15 : 45;
                    const near = Math.round(r / step) * step;
                    if (ev.shiftKey || Math.abs(r - near) < 4) r = near;
                }
                r = ((r % 360) + 540) % 360 - 180;
                if (els.length === 1) return { rotation: round(r, 1) };
                const oc = center(o);
                const nc = rotatePoint(oc.x, oc.y, c.x, c.y, delta);
                return { rotation: round(r, 1), x: round(nc.x - o.width / 2), y: round(nc.y - o.height / 2) };
            };
            store.set({ gesture: 'rotate', doc: M.patchElements(s.doc, s.pageIndex, els.map((x) => x.id), patch) });
        },
        () => { store.end(); store.set({ gesture: null }); },
    );
}

/** Drag a box on the empty page to select what it touches; a click without a drag selects the page. */
export function startMarquee(e, { store, zoom, onClick }) {
    const rect = e.currentTarget.getBoundingClientRect();
    const x0 = (e.clientX - rect.left) / zoom;
    const y0 = (e.clientY - rect.top) / zoom;
    const additive = e.shiftKey;
    const before = store.get().selection;
    let moved = false;

    track(
        (ev) => {
            const x1 = (ev.clientX - rect.left) / zoom;
            const y1 = (ev.clientY - rect.top) / zoom;
            if (!moved && Math.hypot(x1 - x0, y1 - y0) * zoom < 4) return;
            moved = true;
            const box = { x: Math.min(x0, x1), y: Math.min(y0, y1), width: Math.abs(x1 - x0), height: Math.abs(y1 - y0) };
            const s = store.get();
            const hit = M.page(s.doc, s.pageIndex).elements.filter((el) => !el.locked && intersects(aabb(el), box)).map((el) => el.id);
            store.set({ marquee: box, selection: additive ? [...new Set([...before, ...hit])] : hit, mode: 'element' });
        },
        () => {
            const s = store.get();
            store.set({ marquee: null, mode: s.selection.length ? 'element' : moved ? 'none' : s.mode });
            if (!moved) onClick?.();
        },
    );
}
