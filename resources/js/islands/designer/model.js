/**
 * The document model and every pure operation on it.
 *
 *   doc   = { version, pages: [page] }
 *   page  = { id, background, locked, notes, title, elements: [element] }
 *   element (all)  id, type, name, x, y, width, height, rotation, flipX, flipY, opacity, locked
 *     text         text, fontFamily, fontSize, fontWeight, italic, underline, strike, uppercase,
 *                  color, align, lineHeight, letterSpacing (1/1000 em), list, background
 *     shape        shape, sides, fill, fill2, gradientAngle, radius, stroke{color,width,style} + the text props
 *     line         stroke{color,width,style}, capStart, capEnd
 *     image        asset (uuid) | field (key) | graphic (key), colors{from:to}, fit, radius, stroke
 *     group        children (coordinates relative to the group box)
 *
 * Z-order is array order: later = in front. Every function here returns a NEW
 * doc and never mutates — the history keeps the old one by reference.
 */
import { aabb, unionBox, center, rotatePoint } from './geometry';

let counter = 0;
export const uid = (p = 'e') => `${p}${Date.now().toString(36)}${(counter++).toString(36)}${Math.random().toString(36).slice(2, 6)}`;

export const page = (doc, i) => doc.pages[Math.max(0, Math.min(i, doc.pages.length - 1))];

export function findIn(elements, id) {
    return elements.find((e) => e.id === id) || null;
}

export function selected(state) {
    const p = page(state.doc, state.pageIndex);
    return state.selection.map((id) => findIn(p.elements, id)).filter(Boolean);
}

/** Replace the elements of one page. */
export function withElements(doc, pageIndex, fn) {
    const pages = doc.pages.slice();
    const p = pages[pageIndex];
    pages[pageIndex] = { ...p, elements: fn(p.elements) };
    return { ...doc, pages };
}

/** Patch the elements whose ids are listed; `patch` is an object or (el) => object. */
export function patchElements(doc, pageIndex, ids, patch) {
    const set = new Set(ids);
    return withElements(doc, pageIndex, (els) =>
        els.map((e) => (set.has(e.id) ? { ...e, ...(typeof patch === 'function' ? patch(e) : patch) } : e)),
    );
}

export function patchPage(doc, pageIndex, patch) {
    const pages = doc.pages.slice();
    pages[pageIndex] = { ...pages[pageIndex], ...patch };
    return { ...doc, pages };
}

export function addElements(doc, pageIndex, els) {
    return withElements(doc, pageIndex, (list) => [...list, ...els]);
}

export function removeElements(doc, pageIndex, ids) {
    const set = new Set(ids);
    return withElements(doc, pageIndex, (els) => els.filter((e) => !set.has(e.id)));
}

/** Give an element (and any children) fresh ids. */
export function cloneElement(el, dx = 0, dy = 0) {
    const copy = { ...el, id: uid(), x: el.x + dx, y: el.y + dy };
    if (el.children) copy.children = el.children.map((c) => cloneElement(c));
    return copy;
}

/* ── Layer order ─────────────────────────────────────────────────────── */

export function reorder(doc, pageIndex, ids, how) {
    const set = new Set(ids);
    return withElements(doc, pageIndex, (els) => {
        const list = els.slice();
        if (how === 'front') return [...list.filter((e) => !set.has(e.id)), ...list.filter((e) => set.has(e.id))];
        if (how === 'back') return [...list.filter((e) => set.has(e.id)), ...list.filter((e) => !set.has(e.id))];
        if (how === 'forward') {
            for (let i = list.length - 2; i >= 0; i--) {
                if (set.has(list[i].id) && !set.has(list[i + 1].id)) [list[i], list[i + 1]] = [list[i + 1], list[i]];
            }
        } else if (how === 'backward') {
            for (let i = 1; i < list.length; i++) {
                if (set.has(list[i].id) && !set.has(list[i - 1].id)) [list[i], list[i - 1]] = [list[i - 1], list[i]];
            }
        }
        return list;
    });
}

export function moveToIndex(doc, pageIndex, id, index) {
    return withElements(doc, pageIndex, (els) => {
        const list = els.filter((e) => e.id !== id);
        const el = els.find((e) => e.id === id);
        if (!el) return els;
        list.splice(Math.max(0, Math.min(index, list.length)), 0, el);
        return list;
    });
}

/** Can the selection move one step towards the front / back? */
export function layerRange(elements, ids) {
    const idx = ids.map((id) => elements.findIndex((e) => e.id === id)).filter((i) => i >= 0);
    if (!idx.length) return { front: false, back: false };
    const n = idx.length;
    return {
        front: Math.min(...idx) < elements.length - n || idx.some((i, k) => !idx.includes(i + 1) && i < elements.length - 1),
        back: Math.max(...idx) >= n || idx.some((i) => i > 0 && !idx.includes(i - 1)),
    };
}

/* ── Groups ──────────────────────────────────────────────────────────── */

export function group(doc, pageIndex, ids) {
    const p = doc.pages[pageIndex];
    const members = p.elements.filter((e) => ids.includes(e.id));
    if (members.length < 2) return { doc, id: null };
    const box = unionBox(members);
    const g = {
        id: uid('g'), type: 'group', name: '', x: box.x, y: box.y, width: box.width, height: box.height,
        rotation: 0, flipX: false, flipY: false, opacity: 1, locked: false,
        children: members.map((m) => ({ ...m, x: m.x - box.x, y: m.y - box.y })),
    };
    const top = Math.max(...members.map((m) => p.elements.indexOf(m)));
    const next = withElements(doc, pageIndex, (els) => {
        const out = [];
        els.forEach((e, i) => {
            if (i === top) out.push(g);
            if (!ids.includes(e.id)) out.push(e);
        });
        return out;
    });
    return { doc: next, id: g.id };
}

export function ungroup(doc, pageIndex, id) {
    const p = doc.pages[pageIndex];
    const g = findIn(p.elements, id);
    if (!g || g.type !== 'group') return { doc, ids: [] };
    const gc = center(g);
    const sx = g.flipX ? -1 : 1;
    const sy = g.flipY ? -1 : 1;
    const kids = g.children.map((c) => {
        // A child's centre, in page space, through the group's flip and rotation.
        const local = { x: g.x + c.x + c.width / 2, y: g.y + c.y + c.height / 2 };
        const fx = gc.x + (local.x - gc.x) * sx;
        const fy = gc.y + (local.y - gc.y) * sy;
        const r = rotatePoint(fx, fy, gc.x, gc.y, g.rotation || 0);
        return {
            ...c,
            x: r.x - c.width / 2,
            y: r.y - c.height / 2,
            rotation: ((c.rotation || 0) + (g.rotation || 0)) % 360,
            opacity: (c.opacity ?? 1) * (g.opacity ?? 1),
            flipX: g.flipX ? !c.flipX : c.flipX,
            flipY: g.flipY ? !c.flipY : c.flipY,
        };
    });
    const next = withElements(doc, pageIndex, (els) => els.flatMap((e) => (e.id === id ? kids : [e])));
    return { doc: next, ids: kids.map((k) => k.id) };
}

/** Scale an element's content (text size, stroke) by f — used by proportional resize. */
export function scaleContent(el, f) {
    const out = {};
    if (el.fontSize) out.fontSize = Math.round(el.fontSize * f * 10) / 10;
    if (el.shadow) out.shadow = { ...el.shadow, x: Math.round(el.shadow.x * f * 10) / 10, y: Math.round(el.shadow.y * f * 10) / 10, blur: Math.round(el.shadow.blur * f * 10) / 10 };
    if (el.outline) out.outline = { ...el.outline, width: Math.round(el.outline.width * f * 10) / 10 };
    if (el.minHeight) out.minHeight = Math.round(el.minHeight * f * 10) / 10;
    if (el.stroke?.width && el.type !== 'line') out.stroke = { ...el.stroke, width: Math.round(el.stroke.width * f * 10) / 10 };
    if (el.radius) out.radius = Math.round(el.radius * f * 10) / 10;
    if (el.children) {
        out.children = el.children.map((c) => ({
            ...c, x: c.x * f, y: c.y * f, width: c.width * f, height: c.height * f, ...scaleContent(c, f),
        }));
    }
    return out;
}

/** Scale children of a group non-uniformly (side handles on a group). */
export function scaleChildren(children, fx, fy) {
    return children.map((c) => ({
        ...c, x: c.x * fx, y: c.y * fy, width: c.width * fx, height: c.type === 'text' ? c.height : c.height * fy,
        ...(c.children ? { children: scaleChildren(c.children, fx, fy) } : {}),
    }));
}

/* ── Pages ───────────────────────────────────────────────────────────── */

export const blankPage = () => ({ id: uid('p'), background: '#ffffff', locked: false, notes: '', title: '', elements: [] });

export function insertPage(doc, at, p = blankPage()) {
    const pages = doc.pages.slice();
    pages.splice(at, 0, p);
    return { ...doc, pages };
}

export function duplicatePage(doc, i) {
    const src = doc.pages[i];
    return insertPage(doc, i + 1, { ...src, id: uid('p'), elements: src.elements.map((e) => cloneElement(e)) });
}

export function deletePage(doc, i) {
    if (doc.pages.length < 2) return doc;
    return { ...doc, pages: doc.pages.filter((_, k) => k !== i) };
}

export function movePage(doc, from, to) {
    const pages = doc.pages.slice();
    const [p] = pages.splice(from, 1);
    pages.splice(to, 0, p);
    return { ...doc, pages };
}

/* ── Colours used in a design (for the colour panel) ─────────────────── */

export function documentColors(doc) {
    const set = new Set();
    const visit = (e) => {
        ['color', 'fill', 'fill2', 'background'].forEach((k) => typeof e[k] === 'string' && e[k].startsWith('#') && set.add(e[k].slice(0, 7)));
        if (e.stroke?.color) set.add(e.stroke.color.slice(0, 7));
        Object.values(e.colors || {}).forEach((c) => set.add(c.slice(0, 7)));
        (e.children || []).forEach(visit);
    };
    doc.pages.forEach((p) => {
        if (p.background?.startsWith('#')) set.add(p.background.slice(0, 7));
        p.elements.forEach(visit);
    });
    return [...set];
}

export function documentFonts(doc) {
    const set = new Set();
    const visit = (e) => {
        if (e.fontFamily && (e.type === 'text' || (e.type === 'shape' && e.text))) set.add(e.fontFamily);
        (e.children || []).forEach(visit);
    };
    doc.pages.forEach((p) => p.elements.forEach(visit));
    return [...set];
}

/** Every word written on the design, through groups — to see which scripts it holds. */
export function documentTexts(doc) {
    const out = [];
    const visit = (e) => {
        if (typeof e.text === 'string' && e.text) out.push(e.text);
        (e.children || []).forEach(visit);
    };
    doc.pages.forEach((p) => p.elements.forEach(visit));
    return out;
}

/** Every element, through groups — for "replace a font everywhere". */
export function mapDeep(doc, fn) {
    const visit = (e) => {
        const next = fn(e);
        return next.children ? { ...next, children: next.children.map(visit) } : next;
    };
    return { ...doc, pages: doc.pages.map((p) => ({ ...p, elements: p.elements.map(visit) })) };
}

export { aabb, unionBox };
