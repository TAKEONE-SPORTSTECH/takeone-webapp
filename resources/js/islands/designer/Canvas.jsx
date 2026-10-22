/**
 * The editing area: every page stacked in a scrolling column, the open page
 * drawn live with its selection overlay, the others as previews you click to
 * open. Zoom is one scale() on each page; the overlay is drawn in SCREEN space
 * beside it, so handles keep their size at any zoom and stay visible outside
 * the page edge while the content itself is clipped to the paper.
 */
import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { useDesigner, t } from './context';
import { useStore } from './store';
import { PageView, textStyle } from './Renderer';
import * as M from './model';
import { aabb, unionBox, round } from './geometry';
import { startMove, startResize, startRotate, startMarquee } from './interactions';
import { Icon, Tip } from './ui';

const PAD_X = 56;
const PAD_TOP = 96;
const PAD_BOTTOM = 72;

/* ── measuring a one-line text's natural width (for auto-widen) ── */
let probe = null;
export function measureText(el) {
    if (!probe) {
        probe = document.createElement('span');
        probe.style.cssText = 'position:absolute;left:-99999px;top:0;visibility:hidden;white-space:pre;';
        document.body.appendChild(probe);
    }
    Object.assign(probe.style, textStyle(el), { whiteSpace: 'pre', width: 'auto' });
    probe.textContent = el.text || '';
    return probe.offsetWidth;
}

/** Which top-level element (direct child of the page) a DOM node belongs to. */
function topLevelId(node) {
    let n = node?.closest?.('.dz-el');
    while (n && !n.parentElement?.classList.contains('dz-page')) n = n.parentElement?.closest('.dz-el');
    return n?.dataset.id || null;
}

export function useCanvasContext() {
    const { store, actions, boot } = useDesigner();
    const preview = useStore(store, (s) => s.preview);
    const editingId = useStore(store, (s) => s.editingId);
    const caret = useStore(store, (s) => s.caret);
    const assets = useStore(store, (s) => s.assets);
    const real = useStore(store, (s) => s.real);

    return useMemo(() => ({
        assets: Object.fromEntries(assets.map((a) => [a.uuid, a])),
        fields: Object.fromEntries((boot.fields || []).map((f) => [f.key, f])),
        // A real person picked: every field shows exactly what prints for them.
        values: real ? real.values : undefined,
        preview,
        editingId,
        caret,
        onText: actions.setText,
        measure: actions.measure,
        onEditKey: (e) => {
            const mod = e.ctrlKey || e.metaKey;
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                actions.stopEditing();
            } else if (mod && ['b', 'i', 'u'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                const el = M.selected(store.get())[0];
                if (!el) return;
                const k = e.key.toLowerCase();
                if (k === 'b') actions.live({ fontWeight: (el.fontWeight || 400) >= 600 ? 400 : 700 });
                if (k === 'i') actions.live({ italic: !el.italic });
                if (k === 'u') actions.live({ underline: !el.underline });
            }
        },
    }), [assets, boot.fields, real, preview, editingId, caret, actions, store]);
}

/* ── the selection overlay ─────────────────────────────────────────── */

function Handles({ el, zoom, store, mobile }) {
    const isText = el.type === 'text';
    const isLine = el.type === 'line';
    const corners = isLine ? [] : ['nw', 'ne', 'sw', 'se'];
    const sides = isLine ? ['w', 'e'] : el.type === 'image' && el.graphic ? [] : ['n', 's', 'w', 'e'];
    const label = { nw: 'top left corner', ne: 'top right corner', sw: 'bottom left corner', se: 'bottom right corner', n: 'top edge', s: 'bottom edge', w: 'left edge', e: 'right edge' };
    const w = el.width * zoom;
    const h = el.height * zoom;
    const hit = mobile ? 40 : 32;

    return (
        <>
            {corners.map((c) => (
                <button
                    key={c}
                    type="button"
                    aria-label={`Resize selection from ${label[c]}`}
                    className={`dz-h dz-h-corner dz-h-${c}`}
                    style={{ width: hit, height: hit, left: (c.includes('w') ? 0 : w) - hit / 2, top: (c.includes('n') ? 0 : h) - hit / 2 }}
                    onPointerDown={(e) => startResize(e, { store, zoom, handle: c })}
                ><i /></button>
            ))}
            {sides.map((s) => {
                const vertical = s === 'w' || s === 'e';
                const edge = vertical ? h : w;
                // Between the corners; on a short edge (a one-line text) a small grip centred on it.
                const len = isLine ? hit : Math.max(18, edge - hit);
                const start = (edge - len) / 2;
                if (!vertical && edge < 40) return null;
                return (
                    <button
                        key={s}
                        type="button"
                        aria-label={`Resize selection from ${label[s]}`}
                        className={`dz-h dz-h-side dz-h-${vertical ? 'v' : 'h'}`}
                        style={vertical
                            ? { width: hit, height: len, left: (s === 'w' ? 0 : w) - hit / 2, top: start }
                            : { height: hit, width: len, top: (s === 'n' ? 0 : h) - hit / 2, left: start }}
                        onPointerDown={(e) => startResize(e, { store, zoom, handle: s })}
                    ><i /></button>
                );
            })}
        </>
    );
}

function Overlay({ zoom, mobile }) {
    const { store, actions } = useDesigner();
    const selection = useStore(store, (s) => s.selection);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const elements = useStore(store, (s) => M.page(s.doc, s.pageIndex).elements);
    const hoverId = useStore(store, (s) => s.hoverId);
    const mode = useStore(store, (s) => s.mode);
    const gesture = useStore(store, (s) => s.gesture);
    const guides = useStore(store, (s) => s.guides);
    const marquee = useStore(store, (s) => s.marquee);
    const nudging = useStore(store, (s) => s.nudging);
    const editingId = useStore(store, (s) => s.editingId);
    const size = useStore(store, (s) => s.size);

    const sel = selection.map((id) => elements.find((e) => e.id === id)).filter(Boolean);
    const hover = hoverId && !selection.includes(hoverId) ? elements.find((e) => e.id === hoverId) : null;
    const box = (el, extra = {}) => ({
        left: el.x * zoom, top: el.y * zoom, width: el.width * zoom, height: el.height * zoom,
        transform: `rotate(${el.rotation || 0}deg)`, ...extra,
    });
    const hideHandles = nudging || gesture === 'move';

    return (
        <div className="dz-overlay" aria-hidden={false}>
            {mode === 'page' && !sel.length && <div className="dz-page-outline" />}
            {hover && !gesture && <div className="dz-hover" style={box(hover)} />}

            {sel.length === 1 && (
                <div className={`dz-sel ${editingId ? 'is-editing' : ''} ${sel[0].locked ? 'is-locked' : ''}`} style={box(sel[0])}>
                    {!hideHandles && !sel[0].locked && !editingId && (
                        <>
                            <Handles el={sel[0]} zoom={zoom} store={store} mobile={mobile} />
                            <div className="dz-rot-row" style={{ top: sel[0].height * zoom + 14 }}>
                                <button type="button" aria-label="Rotate selection" className="dz-rot" onPointerDown={(e) => startRotate(e, { store, zoom })}>
                                    <Icon name="arrow-repeat" />
                                </button>
                                {sel[0].type === 'text' && (
                                    <button type="button" aria-label="Move" className="dz-rot" onPointerDown={(e) => { e.stopPropagation(); startMove(e, { store, zoom, ids: selection }); }}>
                                        <Icon name="arrows-move" />
                                    </button>
                                )}
                            </div>
                        </>
                    )}
                    {sel[0].locked && <span className="dz-lock-badge"><Icon name="lock-fill" /></span>}
                    {gesture === 'rotate' && <span className="dz-tip-float">{round(sel[0].rotation || 0, 0)}°</span>}
                    {gesture === 'resize' && <span className="dz-tip-float">{Math.round(actions.mm(sel[0].width))} × {Math.round(actions.mm(sel[0].height))} mm</span>}
                </div>
            )}

            {sel.length > 1 && (() => {
                const u = unionBox(sel);
                return (
                    <>
                        {sel.map((el) => <div key={el.id} className="dz-member" style={box(el)} />)}
                        <div className="dz-sel is-multi" style={{ left: u.x * zoom, top: u.y * zoom, width: u.width * zoom, height: u.height * zoom }}>
                            {!hideHandles && ['nw', 'ne', 'sw', 'se'].map((c) => (
                                <button key={c} type="button" aria-label={`Resize selection from ${c}`} className={`dz-h dz-h-corner dz-h-${c}`}
                                    style={{ width: 32, height: 32, left: (c.includes('w') ? 0 : u.width * zoom) - 16, top: (c.includes('n') ? 0 : u.height * zoom) - 16 }}
                                    onPointerDown={(e) => startResize(e, { store, zoom, handle: c })}><i /></button>
                            ))}
                            {!hideHandles && (
                                <div className="dz-rot-row" style={{ top: u.height * zoom + 14 }}>
                                    <button type="button" aria-label="Rotate selection" className="dz-rot" onPointerDown={(e) => startRotate(e, { store, zoom })}><Icon name="arrow-repeat" /></button>
                                </div>
                            )}
                        </div>
                    </>
                );
            })()}

            {guides?.map((g, i) => (
                <div key={i} className={`dz-guide ${g.kind === 'center' ? 'is-center' : ''}`}
                    style={g.axis === 'x'
                        ? { left: g.v * zoom, top: Math.min(0, g.from * zoom), height: Math.max(size.h, g.to - g.from) * zoom, width: 0 }
                        : { top: g.v * zoom, left: Math.min(0, g.from * zoom), width: Math.max(size.w, g.to - g.from) * zoom, height: 0 }} />
            ))}
            {marquee && <div className="dz-marquee" style={{ left: marquee.x * zoom, top: marquee.y * zoom, width: marquee.width * zoom, height: marquee.height * zoom }} />}
            <span hidden>{pageIndex}</span>
        </div>
    );
}

/* ── the small toolbar floating above a selection ───────────────────── */

export function MiniToolbar({ zoom }) {
    const { store, actions } = useDesigner();
    const selection = useStore(store, (s) => s.selection);
    const elements = useStore(store, (s) => M.page(s.doc, s.pageIndex).elements);
    const gesture = useStore(store, (s) => s.gesture);
    const editingId = useStore(store, (s) => s.editingId);
    const nudging = useStore(store, (s) => s.nudging);
    const sel = selection.map((id) => elements.find((e) => e.id === id)).filter(Boolean);
    if (!sel.length || gesture || editingId || nudging) return null;

    const u = unionBox(sel);
    const locked = sel.every((e) => e.locked);
    const isGroup = sel.length === 1 && sel[0].type === 'group';
    const openMenu = (e) => {
        const r = e.currentTarget.getBoundingClientRect();
        store.set({ menu: { x: r.left, y: r.bottom + 6 } });
    };

    return (
        <div className="dz-mini" role="toolbar" aria-label="Selected element common controls" style={{ left: (u.x + u.width / 2) * zoom, top: u.y * zoom - 12 }}
            onPointerDown={(e) => e.stopPropagation()}>
            {sel.length > 1 && <Tip label={t('group')} kbd="Ctrl+G"><button type="button" className="dz-mini-btn is-text" onClick={actions.group}>{t('group')}</button></Tip>}
            {isGroup && <Tip label={t('ungroup')} kbd="Ctrl+Shift+G"><button type="button" className="dz-mini-btn is-text" onClick={actions.ungroup}>{t('ungroup')}</button></Tip>}
            <Tip label={locked ? t('unlock') : t('lock')} kbd="Alt+Shift+L"><button type="button" className="dz-mini-btn" onClick={actions.toggleLock}><Icon name={locked ? 'lock-fill' : 'unlock'} /></button></Tip>
            <Tip label={t('duplicate')} kbd="Ctrl+D"><button type="button" className="dz-mini-btn" onClick={actions.duplicate} disabled={locked}><Icon name="copy" /></button></Tip>
            <Tip label={t('delete')} kbd="Del"><button type="button" className="dz-mini-btn" onClick={actions.remove} disabled={locked}><Icon name="trash3" /></button></Tip>
            <Tip label={t('more')}><button type="button" className="dz-mini-btn" onClick={openMenu}><Icon name="three-dots" /></button></Tip>
        </div>
    );
}

/* ── one page, live (the open one) ──────────────────────────────────── */

function ActivePage({ index, zoom, size, mobile }) {
    const { store, actions } = useDesigner();
    const pageData = useStore(store, (s) => s.doc.pages[index]);
    const ctx = useCanvasContext();
    const ref = useRef(null);

    const onPointerDown = useCallback((e) => {
        if (e.button === 2) return;
        const s = store.get();
        if (s.mode === 'viewing') return;
        // A locked page can be looked at, not changed: a click only opens the page.
        if (M.page(s.doc, index).locked) {
            actions.selectPage(index);
            return;
        }
        const id = topLevelId(e.target);

        if (s.painting) {
            if (id) actions.pasteStyle([id]);
            else actions.cancelPaint();
            return;
        }
        if (s.editingId) {
            if (id === s.editingId) return;
            actions.stopEditing();
        }
        if (!id) {
            if (!e.shiftKey) actions.selectPage(index);
            startMarquee(e, { store, zoom, onClick: () => actions.selectPage(index) });
            return;
        }
        // Alt+click: cycle down through what is stacked under the pointer.
        if (e.altKey) {
            const stack = [...new Set(document.elementsFromPoint(e.clientX, e.clientY).map(topLevelId).filter(Boolean))];
            const cur = stack.indexOf(s.selection[0]);
            const next = stack[(cur + 1) % stack.length] || id;
            actions.select([next]);
            return;
        }
        if (e.shiftKey) {
            actions.toggleSelect(id);
            return;
        }
        const el = M.findIn(M.page(s.doc, s.pageIndex).elements, id);
        const wasSelected = s.selection.length === 1 && s.selection[0] === id;
        const keepGroup = s.selection.includes(id) && s.selection.length > 1;
        if (!keepGroup && !wasSelected) actions.select([id]);
        startMove(e, {
            store,
            zoom,
            ids: keepGroup ? s.selection : [id],
            onClick: () => {
                // Click the selected text again: edit it, all text highlighted.
                if (wasSelected && el && !el.locked && (el.type === 'text' || el.type === 'shape')) actions.startEditing(id);
                else if (keepGroup) actions.select([id]);
            },
        });
    }, [store, actions, zoom, index]);

    const onDoubleClick = useCallback((e) => {
        const id = topLevelId(e.target);
        const el = id && M.findIn(M.page(store.get().doc, index).elements, id);
        if (el && (el.type === 'text' || el.type === 'shape')) actions.startEditing(id, { x: e.clientX, y: e.clientY });
        if (el && el.type === 'group') actions.ungroup();
    }, [store, actions, index]);

    const onContextMenu = useCallback((e) => {
        e.preventDefault();
        const id = topLevelId(e.target);
        const s = store.get();
        if (id && !s.selection.includes(id)) actions.select([id]);
        store.set({ menu: { x: e.clientX, y: e.clientY, page: !id } });
    }, [store, actions]);

    const onPointerMove = useCallback((e) => {
        if (e.pointerType !== 'mouse') return;
        const id = topLevelId(e.target);
        if (store.get().hoverId !== id) store.set({ hoverId: id });
    }, [store]);

    return (
        <div
            ref={ref}
            className="dz-pagewrap is-active"
            style={{ width: size.w * zoom, height: size.h * zoom }}
            onPointerDown={onPointerDown}
            onDoubleClick={onDoubleClick}
            onContextMenu={onContextMenu}
            onPointerMove={onPointerMove}
            onPointerLeave={() => store.set({ hoverId: null })}
        >
            <div className="dz-scaler" style={{ transform: `scale(${zoom})` }}>
                <PageView page={pageData} size={size} ctx={ctx} />
            </div>
            <Overlay zoom={zoom} mobile={mobile} />
            <MiniToolbar zoom={zoom} />
        </div>
    );
}

function StillPage({ index, zoom, size, onOpen }) {
    const { store } = useDesigner();
    const pageData = useStore(store, (s) => s.doc.pages[index]);
    const ctx = useCanvasContext();
    const still = useMemo(() => ({ ...ctx, editingId: null, measure: null }), [ctx]);

    return (
        <div className="dz-pagewrap" style={{ width: size.w * zoom, height: size.h * zoom }} onPointerDown={onOpen}>
            <div className="dz-scaler" style={{ transform: `scale(${zoom})` }}>
                <PageView page={pageData} size={size} ctx={still} />
            </div>
        </div>
    );
}

function PageControls({ index, count }) {
    const { store, actions } = useDesigner();
    const locked = useStore(store, (s) => s.doc.pages[index]?.locked);
    return (
        <div className="dz-pagectl">
            <span className="dz-pagectl-label">{t('page_n', { n: index + 1 })}{count > 1 ? ` / ${count}` : ''}</span>
            <div className="dz-pagectl-btns">
                {index > 0 && <Tip label={t('page_up')}><button type="button" onClick={() => actions.movePage(index, index - 1)}><Icon name="chevron-up" /></button></Tip>}
                {index < count - 1 && <Tip label={t('page_down')}><button type="button" onClick={() => actions.movePage(index, index + 1)}><Icon name="chevron-down" /></button></Tip>}
                <Tip label={locked ? t('unlock_page') : t('lock_page')}><button type="button" onClick={() => actions.togglePageLock(index)}><Icon name={locked ? 'lock-fill' : 'unlock'} /></button></Tip>
                <Tip label={t('duplicate_page')}><button type="button" onClick={() => actions.duplicatePage(index)}><Icon name="copy" /></button></Tip>
                {count > 1 && <Tip label={t('delete_page')}><button type="button" onClick={() => actions.deletePage(index)}><Icon name="trash3" /></button></Tip>}
                <Tip label={t('add_page')}><button type="button" onClick={() => actions.addPage(index + 1)}><Icon name="file-earmark-plus" /></button></Tip>
            </div>
        </div>
    );
}

/* ── the scrolling column ───────────────────────────────────────────── */

export default function Canvas({ mobile = false, toolbar = null }) {
    const { store, actions } = useDesigner();
    const pages = useStore(store, (s) => s.doc.pages.length);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const size = useStore(store, (s) => s.size);
    const zoom = useStore(store, (s) => s.zoom);
    const zoomMode = useStore(store, (s) => s.zoomMode);
    const scrollRef = useRef(null);
    const [vp, setVp] = useState({ w: 800, h: 600 });

    useLayoutEffect(() => {
        const node = scrollRef.current;
        const ro = new ResizeObserver(() => setVp({ w: node.clientWidth, h: node.clientHeight }));
        ro.observe(node);
        setVp({ w: node.clientWidth, h: node.clientHeight });
        return () => ro.disconnect();
    }, []);

    // Fit / Fill follow the space available — opening a side panel re-centres the page.
    useEffect(() => {
        const padX = mobile ? 16 : PAD_X;
        const padY = mobile ? 40 : PAD_TOP + PAD_BOTTOM;
        const fit = Math.max(0.05, Math.min((vp.w - padX * 2) / size.w, (vp.h - padY) / size.h));
        const fill = Math.max(0.05, (vp.w - padX * 2) / size.w);
        if (zoomMode === 'fit' && Math.abs(fit - zoom) > 0.001) store.set({ zoom: fit, fitZoom: fit });
        else if (zoomMode === 'fill' && Math.abs(fill - zoom) > 0.001) store.set({ zoom: fill, fitZoom: fit });
        else if (store.get().fitZoom !== fit) store.set({ fitZoom: fit });
    }, [vp, size.w, size.h, zoomMode, zoom, store, mobile]);

    // Ctrl/⌘ + wheel zooms about the pointer.
    useEffect(() => {
        const node = scrollRef.current;
        const onWheel = (e) => {
            if (!(e.ctrlKey || e.metaKey)) return;
            e.preventDefault();
            const s = store.get();
            const next = Math.max(0.1, Math.min(5, s.zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1)));
            const rect = node.getBoundingClientRect();
            const px = e.clientX - rect.left + node.scrollLeft;
            const py = e.clientY - rect.top + node.scrollTop;
            const k = next / s.zoom;
            store.set({ zoom: next, zoomMode: 'manual' });
            requestAnimationFrame(() => {
                node.scrollLeft = px * k - (e.clientX - rect.left);
                node.scrollTop = py * k - (e.clientY - rect.top);
            });
        };
        node.addEventListener('wheel', onWheel, { passive: false });
        return () => node.removeEventListener('wheel', onWheel);
    }, [store]);

    // Two-finger pinch zooms on touch screens.
    useEffect(() => {
        const node = scrollRef.current;
        const touches = new Map();
        let base = null;
        const down = (e) => {
            if (e.pointerType !== 'touch') return;
            touches.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (touches.size === 2) {
                const [a, b] = [...touches.values()];
                base = { d: Math.hypot(a.x - b.x, a.y - b.y), z: store.get().zoom };
            }
        };
        const move = (e) => {
            if (!touches.has(e.pointerId)) return;
            touches.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (touches.size === 2 && base) {
                const [a, b] = [...touches.values()];
                const d = Math.hypot(a.x - b.x, a.y - b.y);
                store.set({ zoom: Math.max(0.1, Math.min(5, base.z * (d / base.d))), zoomMode: 'manual' });
            }
        };
        const up = (e) => {
            touches.delete(e.pointerId);
            if (touches.size < 2) base = null;
        };
        node.addEventListener('pointerdown', down, true);
        node.addEventListener('pointermove', move, true);
        window.addEventListener('pointerup', up, true);
        window.addEventListener('pointercancel', up, true);
        return () => {
            node.removeEventListener('pointerdown', down, true);
            node.removeEventListener('pointermove', move, true);
            window.removeEventListener('pointerup', up, true);
            window.removeEventListener('pointercancel', up, true);
        };
    }, [store]);

    // Keep the open page in view when it changes.
    useEffect(() => {
        const node = scrollRef.current?.querySelector(`[data-page-index="${pageIndex}"]`);
        if (node && pages > 1) node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [pageIndex, pages]);

    const contentW = Math.max(vp.w, size.w * zoom + (mobile ? 32 : PAD_X * 2));

    return (
        <div className="dz-canvas">
            {toolbar}
            <div
                ref={scrollRef}
                className="dz-scroll"
                onPointerDown={(e) => {
                    if (e.target === e.currentTarget || e.target.classList.contains('dz-column') || e.target.classList.contains('dz-pageblock')) actions.clear();
                }}
            >
                <div className="dz-column" style={{ minWidth: contentW, paddingTop: mobile ? 20 : PAD_TOP, paddingBottom: mobile ? 24 : PAD_BOTTOM }}>
                    {Array.from({ length: pages }, (_, i) => (
                        <div key={i} className="dz-pageblock" data-page-index={i}>
                            {!mobile && <PageControls index={i} count={pages} />}
                            {i === pageIndex
                                ? <ActivePage index={i} zoom={zoom} size={size} mobile={mobile} />
                                : <StillPage index={i} zoom={zoom} size={size} onOpen={() => actions.goPage(i)} />}
                        </div>
                    ))}
                    {!mobile && (
                        <button type="button" className="dz-addpage" style={{ width: size.w * zoom }} onClick={() => actions.addPage(pages)}>
                            <Icon name="plus-lg" /> {t('add_page')}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
