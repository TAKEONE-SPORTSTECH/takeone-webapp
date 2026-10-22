/**
 * Every change the editor makes, as functions over the store. Components call
 * these and never write the document themselves, so each change takes exactly
 * one history step and the selection rules (§3 of the spec) live in one place.
 */
import * as M from './model';
import { aabb, unionBox, round, center, PX_PER_MM, mmToPx } from './geometry';
import { textElement } from './library';
import { ensureFont } from './fonts';

export function createActions(store, env) {
    const S = () => store.get();
    const pageOf = () => M.page(S().doc, S().pageIndex);
    const size = () => ({ w: S().size.w, h: S().size.h });
    let clipboard = null;
    let styleClipboard = null;

    const setDoc = (doc, extra = {}) => store.set({ doc, ...extra });

    const a = {
        /* ── selection ── */
        select(ids, extra = {}) {
            store.set({ selection: ids, mode: ids.length ? 'element' : 'none', editingId: null, ...extra });
        },
        toggleSelect(id) {
            const sel = S().selection;
            const next = sel.includes(id) ? sel.filter((x) => x !== id) : [...sel, id];
            a.select(next);
        },
        selectPage(index = S().pageIndex) {
            store.set({ pageIndex: index, selection: [], mode: 'page', editingId: null });
        },
        clear() {
            if (S().editingId) a.stopEditing();
            store.set({ selection: [], mode: 'none', editingId: null });
        },
        selectAll() {
            a.select(pageOf().elements.filter((e) => !e.locked).map((e) => e.id));
        },
        /** Esc: text → element → page → nothing. */
        escape() {
            const s = S();
            if (s.editingId) return a.stopEditing();
            if (s.selection.length) return a.selectPage();
            if (s.mode === 'page') return store.set({ mode: 'none' });
            return null;
        },

        /* ── text editing ── */
        startEditing(id, caret = null) {
            const el = M.findIn(pageOf().elements, id);
            if (!el || el.locked || !(el.type === 'text' || el.type === 'shape')) return;
            store.begin();
            store.set({ selection: [id], mode: 'text', editingId: id, caret });
        },
        stopEditing() {
            const id = S().editingId;
            if (!id) return;
            const el = M.findIn(pageOf().elements, id);
            store.set({ editingId: null, mode: 'element', caret: null });
            // An emptied text box is removed, as an empty box is nothing to print.
            if (el && el.type === 'text' && !String(el.text || '').trim()) {
                setDoc(M.removeElements(S().doc, S().pageIndex, [id]), { selection: [], mode: 'none' });
            }
            store.end();
        },
        setText(id, text) {
            const s = S();
            let doc = M.patchElements(s.doc, s.pageIndex, [id], { text });
            // A one-line box grows with what is typed and stays centred on where it was.
            const el = M.findIn(M.page(doc, s.pageIndex).elements, id);
            if (el?.type === 'text' && !text.includes('\n') && env.measureText) {
                const natural = env.measureText(el);
                if (natural > el.width) {
                    const grow = natural - el.width + 2;
                    const dx = el.align === 'center' ? grow / 2 : el.align === 'right' ? grow : 0;
                    doc = M.patchElements(doc, s.pageIndex, [id], { width: round(el.width + grow), x: round(el.x - dx) });
                }
            }
            setDoc(doc);
        },
        measure(id, height) {
            const s = S();
            const p = M.page(s.doc, s.pageIndex);
            const el = M.findIn(p.elements, id);
            if (!el || Math.abs(el.height - height) < 0.5) return;
            // Text grows downward from its top edge; the box follows its content.
            store.set({ doc: M.patchElements(s.doc, s.pageIndex, [id], { height: round(height) }), quiet: true });
        },

        /* ── generic change to the selection ── */
        update(patch, { commit = true, ids } = {}) {
            const s = S();
            const target = ids || s.selection;
            if (!target.length) return;
            if (commit) store.commit();
            setDoc(M.patchElements(s.doc, s.pageIndex, target, patch));
        },
        /** Live change during a slider drag — call begin() first, end() after. */
        live(patch, ids) {
            const s = S();
            setDoc(M.patchElements(s.doc, s.pageIndex, ids || s.selection, patch));
        },
        updatePage(patch, commit = true) {
            if (commit) store.commit();
            setDoc(M.patchPage(S().doc, S().pageIndex, patch));
        },

        /* ── adding ── */
        add(el, { edit = false, below = true } = {}) {
            const s = S();
            store.commit();
            let placed = el;
            const sel = M.selected(s);
            // A new text goes directly under the selected element, at its width.
            if (below && el.type === 'text' && sel.length === 1) {
                const b = aabb(sel[0]);
                placed = { ...el, x: b.x, width: Math.max(b.width, 40), y: Math.min(b.y + b.height + 12, size().h - el.height) };
            }
            if (placed.fontFamily) ensureFont(placed.fontFamily);
            setDoc(M.addElements(s.doc, s.pageIndex, [placed]));
            if (edit) {
                store.set({ selection: [placed.id], mode: 'element' });
                a.startEditing(placed.id);
            } else {
                a.select([placed.id]);
            }
            return placed;
        },
        addText(kind, placeholder) {
            return a.add(textElement(kind, size(), placeholder), { edit: true });
        },
        /** Insert {key} at the caret when editing, or add it as a new text box. */
        insertToken(key) {
            const token = `{${key}}`;
            if (S().editingId) {
                document.execCommand('insertText', false, token);
                return;
            }
            a.add(textElement('subheading', size(), token), { edit: false });
        },

        /* ── delete / duplicate / clipboard ── */
        remove() {
            const s = S();
            const ids = M.selected(s).filter((e) => !e.locked).map((e) => e.id);
            if (!ids.length) return;
            store.commit();
            setDoc(M.removeElements(s.doc, s.pageIndex, ids), { selection: [], mode: 'page' });
        },
        duplicate() {
            const s = S();
            const els = M.selected(s);
            if (!els.length) return;
            store.commit();
            const off = Math.max(10, Math.round(size().w / 80));
            const copies = els.map((e) => ({ ...M.cloneElement(e, off, off), locked: false }));
            setDoc(M.addElements(s.doc, s.pageIndex, copies));
            a.select(copies.map((c) => c.id));
        },
        copy() {
            const els = M.selected(S());
            if (els.length) clipboard = JSON.parse(JSON.stringify(els));
        },
        cut() {
            a.copy();
            a.remove();
        },
        paste() {
            if (!clipboard?.length) return;
            const s = S();
            store.commit();
            const off = Math.max(10, Math.round(size().w / 80));
            const copies = clipboard.map((e) => M.cloneElement(e, off, off));
            clipboard = copies.map((c) => JSON.parse(JSON.stringify(c)));
            setDoc(M.addElements(s.doc, s.pageIndex, copies));
            a.select(copies.map((c) => c.id));
        },
        hasClipboard: () => Boolean(clipboard?.length),
        copyStyle() {
            const el = M.selected(S())[0];
            if (!el) return;
            const keys = ['fontFamily', 'fontSize', 'fontWeight', 'italic', 'underline', 'strike', 'uppercase', 'color', 'align', 'lineHeight', 'letterSpacing', 'fill', 'fill2', 'gradientAngle', 'stroke', 'radius', 'opacity'];
            styleClipboard = { from: el.type, style: Object.fromEntries(keys.filter((k) => k in el).map((k) => [k, el[k]])) };
            store.set({ painting: true });
        },
        pasteStyle(ids) {
            if (!styleClipboard) return;
            const s = S();
            const target = ids || s.selection;
            const els = M.page(s.doc, s.pageIndex).elements.filter((e) => target.includes(e.id));
            if (!els.length) return;
            store.commit();
            setDoc(M.patchElements(s.doc, s.pageIndex, target, (e) => Object.fromEntries(Object.entries(styleClipboard.style).filter(([k]) => k in e || (e.type === 'shape' && k !== 'fill')))));
            store.set({ painting: false });
        },
        cancelPaint: () => store.set({ painting: false }),

        /* ── arrange ── */
        layer(how) {
            const s = S();
            if (!s.selection.length) return;
            store.commit();
            setDoc(M.reorder(s.doc, s.pageIndex, s.selection, how));
        },
        moveLayer(id, index) {
            store.commit();
            setDoc(M.moveToIndex(S().doc, S().pageIndex, id, index));
        },
        align(how) {
            const s = S();
            const els = M.selected(s).filter((e) => !e.locked);
            if (!els.length) return;
            store.commit();
            const pg = size();
            // One element aligns to the page; several align to their own bounding box.
            const frame = els.length > 1 ? unionBox(els) : { x: 0, y: 0, width: pg.w, height: pg.h };
            setDoc(M.patchElements(s.doc, s.pageIndex, els.map((e) => e.id), (e) => {
                const b = aabb(e);
                const dx = { left: frame.x - b.x, center: frame.x + frame.width / 2 - (b.x + b.width / 2), right: frame.x + frame.width - (b.x + b.width) }[how];
                const dy = { top: frame.y - b.y, middle: frame.y + frame.height / 2 - (b.y + b.height / 2), bottom: frame.y + frame.height - (b.y + b.height) }[how];
                return { x: round(e.x + (dx || 0)), y: round(e.y + (dy || 0)) };
            }));
        },
        distribute(axis) {
            const s = S();
            const els = M.selected(s).filter((e) => !e.locked);
            if (els.length < 3) return;
            store.commit();
            const boxes = els.map((e) => ({ e, b: aabb(e) })).sort((p, q) => (axis === 'x' ? p.b.x - q.b.x : p.b.y - q.b.y));
            const first = boxes[0].b;
            const last = boxes[boxes.length - 1].b;
            const total = boxes.reduce((t, { b }) => t + (axis === 'x' ? b.width : b.height), 0);
            const span = axis === 'x' ? last.x + last.width - first.x : last.y + last.height - first.y;
            const gap = (span - total) / (boxes.length - 1);
            let cursor = axis === 'x' ? first.x : first.y;
            const moves = {};
            boxes.forEach(({ e, b }) => {
                moves[e.id] = axis === 'x' ? { x: round(e.x + (cursor - b.x)) } : { y: round(e.y + (cursor - b.y)) };
                cursor += (axis === 'x' ? b.width : b.height) + gap;
            });
            setDoc(M.patchElements(s.doc, s.pageIndex, Object.keys(moves), (e) => moves[e.id]));
        },
        group() {
            const s = S();
            if (s.selection.length < 2) return;
            store.commit();
            const { doc, id } = M.group(s.doc, s.pageIndex, s.selection);
            if (id) setDoc(doc, { selection: [id], mode: 'element' });
        },
        ungroup() {
            const s = S();
            const g = M.selected(s).find((e) => e.type === 'group');
            if (!g) return;
            store.commit();
            const { doc, ids } = M.ungroup(s.doc, s.pageIndex, g.id);
            setDoc(doc, { selection: ids, mode: 'element' });
        },
        toggleLock() {
            const els = M.selected(S());
            if (!els.length) return;
            const lock = !els.every((e) => e.locked);
            a.update({ locked: lock });
        },
        nudge(dx, dy) {
            const s = S();
            const els = M.selected(s).filter((e) => !e.locked);
            if (!els.length) return;
            // A run of nudges is one undo step: the gesture ends when the keys stop.
            store.begin();
            clearTimeout(a._nudgeTimer);
            a._nudgeTimer = setTimeout(() => { store.end(); store.set({ nudging: false }); }, 600);
            setDoc(M.patchElements(s.doc, s.pageIndex, els.map((e) => e.id), (e) => ({ x: round(e.x + dx), y: round(e.y + dy) })), { nudging: true });
        },
        flip(axis) {
            a.update((e) => (axis === 'x' ? { flipX: !e.flipX } : { flipY: !e.flipY }));
        },
        /** Set width/height/x/y/rotation from the Position panel; width keeps the centre. */
        setGeometry(field, value, lockRatio) {
            const s = S();
            const el = M.selected(s)[0];
            if (!el) return;
            const c = center(el);
            const patch = {};
            if (field === 'width' || field === 'height') {
                const w = field === 'width' ? value : lockRatio ? el.width * (value / el.height) : el.width;
                const h = field === 'height' ? value : lockRatio && el.type !== 'text' ? el.height * (value / el.width) : el.height;
                const f = field === 'width' ? w / el.width : h / el.height;
                Object.assign(patch, { width: round(w), height: round(h), x: round(c.x - w / 2), y: round(c.y - h / 2) });
                if (lockRatio) Object.assign(patch, M.scaleContent(el, f));
            } else if (field === 'rotation') {
                patch.rotation = ((value % 360) + 360) % 360 > 180 ? (((value % 360) + 360) % 360) - 360 : ((value % 360) + 360) % 360;
            } else {
                patch[field] = round(value);
            }
            a.update(patch);
        },

        /* ── fonts & colours across the document ── */
        replaceFont(from, to) {
            store.commit();
            setDoc(M.mapDeep(S().doc, (e) => (e.fontFamily === from ? { ...e, fontFamily: to } : e)));
        },

        /* ── pages ── */
        goPage(i) {
            if (S().editingId) a.stopEditing();
            store.set({ pageIndex: Math.max(0, Math.min(i, S().doc.pages.length - 1)), selection: [], mode: 'page', editingId: null });
        },
        addPage(at = S().pageIndex + 1) {
            store.commit();
            setDoc(M.insertPage(S().doc, at), { pageIndex: at, selection: [], mode: 'page' });
        },
        duplicatePage(i = S().pageIndex) {
            store.commit();
            setDoc(M.duplicatePage(S().doc, i), { pageIndex: i + 1, selection: [], mode: 'page' });
        },
        deletePage(i = S().pageIndex) {
            if (S().doc.pages.length < 2) return;
            store.commit();
            setDoc(M.deletePage(S().doc, i), { pageIndex: Math.max(0, i - 1), selection: [], mode: 'page' });
        },
        movePage(from, to) {
            store.commit();
            setDoc(M.movePage(S().doc, from, to), { pageIndex: to });
        },
        togglePageLock(i = S().pageIndex) {
            const p = S().doc.pages[i];
            store.commit();
            setDoc(M.patchPage(S().doc, i, { locked: !p.locked }));
        },

        /* ── canvas size ── */
        resize(format, orientation, wMm, hMm, scaleContent) {
            const s = S();
            const nw = mmToPx(wMm);
            const nh = mmToPx(hMm);
            store.commit();
            let doc = s.doc;
            if (scaleContent) {
                const f = Math.min(nw / s.size.w, nh / s.size.h);
                const ox = (nw - s.size.w * f) / 2;
                const oy = (nh - s.size.h * f) / 2;
                doc = {
                    ...doc,
                    pages: doc.pages.map((p) => ({
                        ...p,
                        elements: p.elements.map((e) => ({
                            ...e, x: round(e.x * f + ox), y: round(e.y * f + oy), width: round(e.width * f), height: round(e.height * f), ...M.scaleContent(e, f),
                        })),
                    })),
                };
            }
            store.set({ doc, size: { format, orientation, wMm, hMm, w: round(nw), h: round(nh) } });
        },

        mm: (px) => px / PX_PER_MM,
    };

    return a;
}
