/**
 * The desktop editor: header · rail · side panel · canvas · footer (spec §1).
 * Opening a panel narrows the canvas and the page re-centres in what is left;
 * the Tools strip floats and does not.
 */
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useDesigner, t } from './context';
import { useStore, useSelected } from './store';
import * as M from './model';
import Canvas, { useCanvasContext } from './Canvas';
import Toolbar from './Toolbar';
import { PageView } from './Renderer';
import { PositionPanel, ColorPanel, FontPanel, TextPanel, ElementsPanel, UploadsPanel, FieldsPanel, RealPersonPanel, SizePanel, NotesPanel, ShortcutsPanel } from './panels';
import QrPanel from './QrPanel';
import { Icon, Tip, Popover, MenuList, PopButton } from './ui';
import { shapeElement, lineElement, textElement } from './library';

/* ── header ─────────────────────────────────────────────────────────── */

function SaveStatus() {
    const { store } = useDesigner();
    const state = useStore(store, (s) => s.saveState);
    const map = {
        saved: ['cloud-check', t('all_saved')],
        dirty: ['cloud', t('unsaved')],
        saving: ['cloud-arrow-up', t('saving')],
        error: ['cloud-slash', t('save_error')],
        conflict: ['exclamation-triangle', t('conflict_short')],
        new: ['cloud', t('not_saved_yet')],
    };
    const [icon, label] = map[state] || map.saved;
    return <Tip label={label}><span className={`dz-savestate is-${state}`} aria-live="polite"><Icon name={icon} /><span className="dz-sr">{label}</span></span></Tip>;
}

function Header({ env }) {
    const { store, boot } = useDesigner();
    const title = useStore(store, (s) => s.title);
    const canUndo = useStore(store, (s) => s.canUndo);
    const canRedo = useStore(store, (s) => s.canRedo);
    const preview = useStore(store, (s) => s.preview);
    const real = useStore(store, (s) => s.real);
    const panel = useStore(store, (s) => s.panel);
    const mode = useStore(store, (s) => s.mode);
    const size = useStore(store, (s) => s.size);
    const viewing = mode === 'viewing';
    const slot = (boot.slots || []).find((s) => s.key === boot.slot);
    const formatLabel = boot.formats?.formats?.[size.format]?.label || t('custom_size');

    return (
        <header className="dz-header">
            <div className="dz-header-l">
                {boot.back && (
                    <a href={boot.back} className="dz-round" aria-label={t('back')} title={t('back')}><Icon name="chevron-left" className="rtl-flip" /></a>
                )}
                <PopButton label={t('file')} width={300} align="start" className="dz-hbtn" render={() => <span>{t('file')}</span>}>
                    {(close) => (
                        <>
                            <div className="dz-filehead">
                                <b>{title || boot.kind.title}</b>
                                <small>{boot.kind.label} · {formatLabel} · {Math.round(size.wMm)} × {Math.round(size.hMm)} mm</small>
                            </div>
                            <MenuList onClose={close} items={[
                                { icon: 'cloud-arrow-up', label: t('save_now'), kbd: 'Ctrl+S', onClick: () => env.persistence.flush() },
                                { icon: 'printer', label: t('print_pdf'), kbd: 'Ctrl+P', onClick: env.print },
                                { divider: true },
                                { icon: 'aspect-ratio', label: t('resize'), onClick: () => store.set({ panel: 'size' }) },
                                { icon: 'journal-text', label: t('notes'), onClick: () => store.set({ panel: 'notes' }) },
                                { icon: 'keyboard', label: t('shortcuts'), onClick: () => store.set({ panel: 'shortcuts' }) },
                            ]} />
                        </>
                    )}
                </PopButton>
                <button type="button" className="dz-hbtn" onClick={() => store.set({ panel: 'size' })}><Icon name="aspect-ratio" /> {t('resize')}</button>
                <PopButton label={t('mode')} width={240} align="start" className="dz-hbtn" render={() => <span><Icon name={viewing ? 'eye' : 'pencil'} /> {viewing ? t('viewing') : t('editing')} <Icon name="chevron-down" /></span>}>
                    {(close) => <MenuList onClose={close} items={[
                        { icon: 'pencil', label: t('editing'), checked: !viewing, onClick: () => store.set({ mode: 'none' }) },
                        { icon: 'eye', label: t('viewing'), checked: viewing, onClick: () => store.set({ mode: 'viewing', selection: [], editingId: null, panel: null }) },
                    ]} />}
                </PopButton>
                <span className="dz-hsep" />
                <Tip label={t('undo')} kbd="Ctrl+Z"><button type="button" className="dz-hicon" disabled={!canUndo || viewing} onClick={() => store.undo()} aria-label={t('undo')}><Icon name="arrow-counterclockwise" /></button></Tip>
                <Tip label={t('redo')} kbd="Ctrl+Shift+Z"><button type="button" className="dz-hicon" disabled={!canRedo || viewing} onClick={() => store.redo()} aria-label={t('redo')}><Icon name="arrow-clockwise" /></button></Tip>
                <SaveStatus />
            </div>
            <div className="dz-header-r">
                {(boot.slots || []).length > 1 && (
                    <PopButton label={t('switch_design')} width={260} align="end" className="dz-hbtn" render={() => <span><Icon name="collection" /> {slot?.label} <Icon name="chevron-down" /></span>}>
                        {(close) => <MenuList onClose={close} items={boot.slots.map((s) => ({ label: s.label, checked: s.key === boot.slot, onClick: () => { env.persistence.flush().finally(() => { window.location.href = s.url; }); } }))} />}
                    </PopButton>
                )}
                <input
                    className="dz-title"
                    value={title || ''}
                    placeholder={boot.kind.title}
                    maxLength={160}
                    aria-label={t('design_title')}
                    onChange={(e) => store.set({ title: e.target.value, dirty: (store.get().dirty || 0) + 1 })}
                    onKeyDown={(e) => { e.stopPropagation(); if (e.key === 'Enter') e.currentTarget.blur(); }}
                />
                {(boot.fields || []).length > 0 && (
                    <Tip label={t('preview_samples')}>
                        <button type="button" className={`dz-hbtn ${preview ? 'is-on' : ''}`} onClick={() => store.set({ preview: !preview })}><Icon name={preview ? 'eye-fill' : 'eye'} /> {t('preview')}</button>
                    </Tip>
                )}
                {boot.endpoints?.people && (
                    <Tip label={t('real_person_hint')}>
                        <button type="button" className={`dz-hbtn ${real ? 'is-on' : ''}`} onClick={() => store.set({ panel: panel === 'real' ? null : 'real' })}>
                            <Icon name={real ? 'person-check-fill' : 'person-badge'} /> {real ? real.label : t('real_person')}
                        </button>
                    </Tip>
                )}
                <button type="button" className="dz-share" onClick={env.print}><Icon name="printer" /> {t('print')}</button>
            </div>
        </header>
    );
}

/**
 * A design that does not exist yet may start as a copy of one already made
 * (boot.copies — a job's certificate from the referees', say). Shown until the
 * page is first saved or the bar is put away; the copy is made on the server,
 * pictures and all, and the page reloads onto it.
 */
export function StartFromBar() {
    const { store, boot } = useDesigner();
    const fresh = useStore(store, (s) => s.saveState === 'new');
    const [state, setState] = React.useState({ busy: null, hidden: false, error: '' });
    if (!fresh || state.hidden || !(boot.copies || []).length || !boot.copyUrl) return null;
    const copy = async (slot) => {
        if (state.busy) return;
        setState({ ...state, busy: slot, error: '' });
        try {
            const res = await fetch(boot.copyUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ from: slot }),
            });
            // Made (or somebody saved one first): either way the page now has a design to open.
            if (res.ok || res.status === 409) { window.location.reload(); return; }
            const j = await res.json().catch(() => ({}));
            setState({ busy: null, hidden: false, error: j.message || t('save_failed') });
        } catch (e) {
            setState({ busy: null, hidden: false, error: t('save_failed') });
        }
    };
    return (
        <div className="dz-startfrom" role="region" aria-label={t('start_from')}>
            <span className="dz-startfrom-l"><Icon name="files" /> <b>{t('start_from')}</b> <small>{t('start_from_hint')}</small></span>
            <span className="dz-startfrom-r">
                {boot.copies.map((c) => (
                    <button key={c.slot} type="button" className="dz-btn is-sm" disabled={!!state.busy} onClick={() => copy(c.slot)}>
                        {state.busy === c.slot ? t('copying') : c.label}
                    </button>
                ))}
                <button type="button" className="dz-btn is-sm is-ghost" disabled={!!state.busy} onClick={() => setState({ ...state, hidden: true })}>{t('start_blank')}</button>
            </span>
            {state.error && <span className="dz-startfrom-err" role="alert">{state.error}</span>}
        </div>
    );
}

function ConflictBar({ env }) {
    const { store } = useDesigner();
    const state = useStore(store, (s) => s.saveState);
    if (state !== 'conflict') return null;
    return (
        <div className="dz-conflict" role="alert">
            <Icon name="exclamation-triangle-fill" /> {t('conflict')}
            <button type="button" className="dz-btn is-sm" onClick={env.persistence.loadLatest}>{t('load_theirs')}</button>
            <button type="button" className="dz-btn is-sm is-primary" onClick={env.persistence.keepMine}>{t('keep_mine')}</button>
        </div>
    );
}

/* ── rail ───────────────────────────────────────────────────────────── */

function Rail() {
    const { store, boot } = useDesigner();
    const panel = useStore(store, (s) => s.panel);
    const tools = useStore(store, (s) => s.tools);
    const tabs = [
        ['elements', 'pentagon-half', t('elements')],
        ['text', 'fonts', t('text')],
        ...((boot.fields || []).length ? [['fields', 'magic', t('fields')]] : []),
        ['uploads', 'cloud-arrow-up', t('uploads')],
        ['tools', 'tools', t('tools')],
        ['layers', 'layers', t('layers')],
        ['size', 'aspect-ratio', t('resize')],
    ];
    const open = (k) => {
        if (k === 'tools') return store.set({ tools: !tools });
        if (k === 'layers') return store.set({ panel: panel === 'position' ? null : 'position', positionTab: 'layers' });
        return store.set({ panel: panel === k ? null : k });
    };
    const isOn = (k) => (k === 'tools' ? tools : k === 'layers' ? panel === 'position' : panel === k);

    return (
        <nav className="dz-rail" role="tablist" aria-label="Side Panel">
            {tabs.map(([k, icon, label]) => (
                <button key={k} type="button" role="tab" aria-selected={isOn(k)} className={`dz-railtab ${isOn(k) ? 'is-on' : ''}`} onClick={() => open(k)}>
                    <span><Icon name={icon} /></span>{label}
                </button>
            ))}
        </nav>
    );
}

function ToolsStrip() {
    const { store, actions } = useDesigner();
    const tools = useStore(store, (s) => s.tools);
    const size = useStore(store, (s) => s.size);
    if (!tools) return null;
    const items = [
        ['cursor', t('select'), () => actions.clear()],
        ['square', t('shape_rect'), () => actions.add(shapeElement('rect', size))],
        ['circle', t('shape_ellipse'), () => actions.add(shapeElement('ellipse', size))],
        ['slash-lg', t('lines'), () => actions.add(lineElement('solid', size))],
        ['fonts', t('text'), () => actions.add(textElement('plain', size, t('placeholder_text')), { edit: true, below: false })],
    ];
    return (
        <div className="dz-tools">
            <button type="button" className="dz-tools-x" onClick={() => store.set({ tools: false })} aria-label={t('close')}><Icon name="x" /></button>
            {items.map(([icon, label, fn]) => <Tip key={icon} label={label} side="right"><button type="button" onClick={fn} aria-label={label}><Icon name={icon} /></button></Tip>)}
        </div>
    );
}

/* ── context menu (⋯ and right-click) ───────────────────────────────── */

export function ContextMenu() {
    const { store, actions } = useDesigner();
    const menu = useStore(store, (s) => s.menu);
    const els = useSelected(store);
    const anchor = useMemo(() => {
        if (!menu) return null;
        return { getBoundingClientRect: () => ({ left: menu.x, right: menu.x, top: menu.y, bottom: menu.y, width: 0, height: 0 }), contains: () => false };
    }, [menu]);
    if (!menu) return null;
    const close = () => store.set({ menu: null });
    const locked = els.length && els.every((e) => e.locked);
    const isGroup = els.length === 1 && els[0].type === 'group';

    const items = menu.page || !els.length ? [
        { icon: 'clipboard', label: t('paste'), kbd: 'Ctrl+V', disabled: !actions.hasClipboard(), onClick: actions.paste },
        { icon: 'check2-square', label: t('select_all'), kbd: 'Ctrl+A', onClick: actions.selectAll },
        { divider: true },
        { icon: 'file-earmark-plus', label: t('add_page'), onClick: () => actions.addPage() },
        { icon: 'copy', label: t('duplicate_page'), onClick: () => actions.duplicatePage() },
        { icon: 'palette', label: t('background_color'), onClick: () => store.set({ panel: 'color', colorTarget: { prop: 'page' } }) },
    ] : [
        { icon: 'copy', label: t('copy'), kbd: 'Ctrl+C', onClick: actions.copy },
        { icon: 'paint-bucket', label: t('copy_style'), kbd: 'Ctrl+Alt+C', onClick: actions.copyStyle },
        { icon: 'clipboard', label: t('paste'), kbd: 'Ctrl+V', disabled: !actions.hasClipboard(), onClick: actions.paste },
        { icon: 'front', label: t('duplicate'), kbd: 'Ctrl+D', disabled: locked, onClick: actions.duplicate },
        { icon: 'trash3', label: t('delete'), kbd: 'Del', disabled: locked, danger: true, onClick: actions.remove },
        { divider: true },
        { icon: 'layers', label: t('layer'), sub: [
            { icon: 'layer-forward', label: t('forward'), kbd: 'Ctrl+]', onClick: () => actions.layer('forward') },
            { icon: 'layer-backward', label: t('backward'), kbd: 'Ctrl+[', onClick: () => actions.layer('backward') },
            { icon: 'front', label: t('to_front'), kbd: 'Ctrl+Shift+]', onClick: () => actions.layer('front') },
            { icon: 'back', label: t('to_back'), kbd: 'Ctrl+Shift+[', onClick: () => actions.layer('back') },
        ] },
        { icon: 'align-center', label: els.length > 1 ? t('align_elements') : t('align_to_page'), sub: [
            { icon: 'align-start', label: t('align_left'), onClick: () => actions.align('left') },
            { icon: 'align-center', label: t('align_center'), onClick: () => actions.align('center') },
            { icon: 'align-end', label: t('align_right'), onClick: () => actions.align('right') },
            { icon: 'align-top', label: t('align_top'), onClick: () => actions.align('top') },
            { icon: 'align-middle', label: t('align_middle'), onClick: () => actions.align('middle') },
            { icon: 'align-bottom', label: t('align_bottom'), onClick: () => actions.align('bottom') },
        ] },
        { divider: true },
        els.length > 1 ? { icon: 'collection', label: t('group'), kbd: 'Ctrl+G', onClick: actions.group } : null,
        isGroup ? { icon: 'collection', label: t('ungroup'), kbd: 'Ctrl+Shift+G', onClick: actions.ungroup } : null,
        { icon: locked ? 'unlock' : 'lock', label: locked ? t('unlock') : t('lock'), kbd: 'Alt+Shift+L', onClick: actions.toggleLock },
        { icon: 'sliders', label: t('position'), onClick: () => store.set({ panel: 'position', positionTab: 'arrange' }) },
    ];

    return (
        <Popover anchor={anchor} open onClose={close} width={260} align="start" className="dz-ctx">
            <MenuList items={items} onClose={close} />
        </Popover>
    );
}

/* ── footer ─────────────────────────────────────────────────────────── */

function PageThumb({ index, width = 120, onClick, active }) {
    const { store } = useDesigner();
    const page = useStore(store, (s) => s.doc.pages[index]);
    const size = useStore(store, (s) => s.size);
    const ctx = useCanvasContext();
    const still = useMemo(() => ({ ...ctx, editingId: null, measure: null }), [ctx]);
    const k = width / size.w;
    return (
        <button type="button" className={`dz-thumb ${active ? 'is-on' : ''}`} style={{ width, height: size.h * k }} onClick={onClick}>
            <div style={{ transform: `scale(${k})`, transformOrigin: '0 0' }}><PageView page={page} size={size} ctx={still} /></div>
            <span className="dz-thumb-n">{index + 1}</span>
        </button>
    );
}

function Footer({ env }) {
    const { store, actions } = useDesigner();
    const zoom = useStore(store, (s) => s.zoom);
    const zoomMode = useStore(store, (s) => s.zoomMode);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const pages = useStore(store, (s) => s.doc.pages.length);
    const strip = useStore(store, (s) => s.strip);
    const grid = useStore(store, (s) => s.grid);
    const [zoomOpen, setZoomOpen] = useState(false);
    const zRef = useRef(null);

    return (
        <>
            {strip && (
                <div className="dz-strip">
                    {Array.from({ length: pages }, (_, i) => <PageThumb key={i} index={i} width={110} active={i === pageIndex} onClick={() => actions.goPage(i)} />)}
                    <button type="button" className="dz-strip-add" onClick={() => actions.addPage(pages)} aria-label={t('add_page')}><Icon name="plus-lg" /></button>
                </div>
            )}
            <footer className="dz-footer">
                <div className="dz-footer-l">
                    <button type="button" className="dz-fbtn" onClick={() => store.set({ panel: 'notes' })}><Icon name="journal-text" /> {t('notes')}</button>
                </div>
                <div className="dz-footer-r">
                    <input className="dz-zoom" type="range" min="10" max="500" value={Math.round(zoom * 100)} aria-label={t('zoom')} style={{ '--pct': `${((zoom * 100 - 10) / 490) * 100}%` }} onChange={(e) => store.set({ zoom: Number(e.target.value) / 100, zoomMode: 'manual' })} />
                    <button ref={zRef} type="button" className="dz-fbtn dz-zoompct" onClick={() => setZoomOpen((v) => !v)}>{Math.round(zoom * 100)}% <Icon name="chevron-up" /></button>
                    <Popover anchor={zRef.current} open={zoomOpen} onClose={() => setZoomOpen(false)} width={160}>
                        <MenuList onClose={() => setZoomOpen(false)} items={[
                            ...[300, 200, 125, 100, 75, 50, 25, 10].map((p) => ({ label: `${p}%`, checked: zoomMode === 'manual' && Math.round(zoom * 100) === p, onClick: () => store.set({ zoom: p / 100, zoomMode: 'manual' }) })),
                            { divider: true },
                            { label: t('fit'), checked: zoomMode === 'fit', onClick: () => store.set({ zoomMode: 'fit' }) },
                            { label: t('fill'), checked: zoomMode === 'fill', onClick: () => store.set({ zoomMode: 'fill' }) },
                        ]} />
                    </Popover>
                    <Tip label={t('pages')} side="top"><button type="button" className={`dz-fbtn ${strip ? 'is-on' : ''}`} onClick={() => store.set({ strip: !strip })}><Icon name="collection" /> {t('pages')}</button></Tip>
                    <span className="dz-pagecount">{pageIndex + 1} / {pages}</span>
                    <Tip label={t('grid_view')} side="top"><button type="button" className={`dz-fbtn ${grid ? 'is-on' : ''}`} onClick={() => store.set({ grid: !grid })} aria-label={t('grid_view')}><Icon name="grid" /></button></Tip>
                    <Tip label={t('present')} side="top"><button type="button" className="dz-fbtn" onClick={() => store.set({ presenting: true })} aria-label={t('present')}><Icon name="fullscreen" /></button></Tip>
                    <Tip label={t('shortcuts')} side="top"><button type="button" className="dz-fbtn" onClick={() => store.set({ panel: 'shortcuts' })} aria-label={t('shortcuts')}><Icon name="question-circle" /></button></Tip>
                </div>
            </footer>
        </>
    );
}

function GridView() {
    const { store, actions } = useDesigner();
    const pages = useStore(store, (s) => s.doc.pages.length);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    return (
        <div className="dz-grid-view">
            {Array.from({ length: pages }, (_, i) => (
                <div key={i} className="dz-grid-cell">
                    <PageThumb index={i} width={240} active={i === pageIndex} onClick={() => { actions.goPage(i); store.set({ grid: false }); }} />
                    <div className="dz-grid-ctl">
                        <button type="button" onClick={() => actions.duplicatePage(i)} aria-label={t('duplicate_page')}><Icon name="copy" /></button>
                        {pages > 1 && <button type="button" onClick={() => actions.deletePage(i)} aria-label={t('delete_page')}><Icon name="trash3" /></button>}
                    </div>
                </div>
            ))}
            <button type="button" className="dz-grid-add" onClick={() => actions.addPage(pages)}><Icon name="plus-lg" /> {t('add_page')}</button>
        </div>
    );
}

export function Presenter() {
    const { store, actions } = useDesigner();
    const presenting = useStore(store, (s) => s.presenting);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const pages = useStore(store, (s) => s.doc.pages.length);
    const page = useStore(store, (s) => M.page(s.doc, s.pageIndex));
    const size = useStore(store, (s) => s.size);
    const ctx = useCanvasContext();
    const shown = useMemo(() => ({ ...ctx, preview: true, editingId: null, measure: null }), [ctx]);
    const [vp, setVp] = useState({ w: window.innerWidth, h: window.innerHeight });

    useEffect(() => {
        if (!presenting) return undefined;
        const key = (e) => {
            if (e.key === 'Escape') store.set({ presenting: false });
            if (e.key === 'ArrowRight' || e.key === ' ') actions.goPage(pageIndex + 1);
            if (e.key === 'ArrowLeft') actions.goPage(pageIndex - 1);
            e.stopPropagation();
        };
        const rs = () => setVp({ w: window.innerWidth, h: window.innerHeight });
        window.addEventListener('keydown', key, true);
        window.addEventListener('resize', rs);
        document.documentElement.requestFullscreen?.().catch(() => {});
        return () => {
            window.removeEventListener('keydown', key, true);
            window.removeEventListener('resize', rs);
            if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
        };
    }, [presenting, pageIndex, store, actions]);

    if (!presenting) return null;
    const k = Math.min((vp.w - 40) / size.w, (vp.h - 40) / size.h);
    return (
        <div className="dz-present" onClick={() => actions.goPage((pageIndex + 1) % pages)}>
            <div style={{ width: size.w * k, height: size.h * k }}>
                <div style={{ transform: `scale(${k})`, transformOrigin: '0 0' }}><PageView page={page} size={size} ctx={shown} /></div>
            </div>
            <button type="button" className="dz-present-x" onClick={(e) => { e.stopPropagation(); store.set({ presenting: false }); }} aria-label={t('close')}><Icon name="x-lg" /></button>
        </div>
    );
}

/* ── the editor ─────────────────────────────────────────────────────── */

export function ActivePanel() {
    const { store } = useDesigner();
    const panel = useStore(store, (s) => s.panel);
    const ctx = useCanvasContext();
    switch (panel) {
        case 'position': return <PositionPanel ctx={ctx} />;
        case 'color': return <ColorPanel />;
        case 'font': return <FontPanel />;
        case 'text': return <TextPanel />;
        case 'elements': return <ElementsPanel />;
        case 'uploads': return <UploadsPanel />;
        case 'fields': return <FieldsPanel />;
        case 'real': return <RealPersonPanel />;
        case 'size': return <SizePanel />;
        case 'notes': return <NotesPanel />;
        case 'shortcuts': return <ShortcutsPanel />;
        case 'qr': return <QrPanel />;
        default: return null;
    }
}

export default function Editor({ env }) {
    const { store } = useDesigner();
    const grid = useStore(store, (s) => s.grid);
    const viewing = useStore(store, (s) => s.mode === 'viewing');
    const painting = useStore(store, (s) => s.painting);

    return (
        <div className={`dz-app ${viewing ? 'is-viewing' : ''} ${painting ? 'is-painting' : ''}`}>
            <Header env={env} />
            <ConflictBar env={env} />
            <StartFromBar />
            <div className="dz-body">
                <Rail />
                <ToolsStrip />
                <ActivePanel />
                <main className="dz-main">
                    {grid ? <GridView /> : <Canvas toolbar={viewing ? null : <Toolbar />} />}
                </main>
            </div>
            <Footer env={env} />
            <ContextMenu />
            <Presenter />
        </div>
    );
}
