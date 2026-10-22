/**
 * The phone editor — the same document, store, canvas and panels as the
 * desktop, arranged for a thumb: a slim top bar, the page filling the screen
 * (pinch to zoom), the selection's toolbar along the bottom, and every panel as
 * a bottom sheet. This IS the Android app's editor (the app is a WebView).
 */
import React, { useState } from 'react';
import { useDesigner, t } from './context';
import { useStore, useSelected } from './store';
import Canvas from './Canvas';
import Toolbar from './Toolbar';
import { ActivePanel, Presenter, ContextMenu, StartFromBar } from './Editor';
import { Icon, PopButton, MenuList } from './ui';

function TopBar({ env }) {
    const { store, boot, actions } = useDesigner();
    const canUndo = useStore(store, (s) => s.canUndo);
    const canRedo = useStore(store, (s) => s.canRedo);
    const saveState = useStore(store, (s) => s.saveState);
    const preview = useStore(store, (s) => s.preview);
    const real = useStore(store, (s) => s.real);
    const title = useStore(store, (s) => s.title);
    const pages = useStore(store, (s) => s.doc.pages.length);
    const icon = { saved: 'cloud-check', saving: 'cloud-arrow-up', error: 'cloud-slash', conflict: 'exclamation-triangle', dirty: 'cloud', new: 'cloud' }[saveState] || 'cloud';

    return (
        <header className="dzm-top">
            {boot.back
                ? <a href={boot.back} className="dz-round" aria-label={t('back')}><Icon name="chevron-left" className="rtl-flip" /></a>
                : <span />}
            <div className="dzm-title">
                <b>{title || boot.kind.title}</b>
                <small className={`dz-savestate is-${saveState}`}><Icon name={icon} /> {t(saveState === 'saved' ? 'all_saved' : saveState === 'saving' ? 'saving' : saveState === 'conflict' ? 'conflict_short' : saveState === 'error' ? 'save_error' : 'unsaved')}</small>
            </div>
            <button type="button" className="dz-round" disabled={!canUndo} onClick={() => store.undo()} aria-label={t('undo')}><Icon name="arrow-counterclockwise" /></button>
            <button type="button" className="dz-round" disabled={!canRedo} onClick={() => store.redo()} aria-label={t('redo')}><Icon name="arrow-clockwise" /></button>
            <PopButton label={t('more')} width={250} align="end" className="dz-round" render={() => <Icon name="three-dots" />}>
                {(close) => <MenuList onClose={close} items={[
                    ...((boot.fields || []).length ? [{ icon: preview ? 'eye-fill' : 'eye', label: t('preview_samples'), checked: preview, onClick: () => store.set({ preview: !preview }) }] : []),
                    ...(boot.endpoints?.people ? [{ icon: real ? 'person-check-fill' : 'person-badge', label: real ? real.label : t('real_person'), checked: !!real, onClick: () => store.set({ panel: 'real' }) }] : []),
                    { icon: 'aspect-ratio', label: t('resize'), onClick: () => store.set({ panel: 'size' }) },
                    { icon: 'file-earmark-plus', label: t('add_page'), onClick: () => actions.addPage(pages) },
                    { icon: 'copy', label: t('duplicate_page'), onClick: () => actions.duplicatePage() },
                    pages > 1 ? { icon: 'trash3', label: t('delete_page'), danger: true, onClick: () => actions.deletePage() } : null,
                    { icon: 'journal-text', label: t('notes'), onClick: () => store.set({ panel: 'notes' }) },
                    { divider: true },
                    ...((boot.slots || []).length > 1 ? boot.slots.map((s) => ({ icon: 'collection', label: s.label, checked: s.key === boot.slot, onClick: () => env.persistence.flush().finally(() => { window.location.href = s.url; }) })) : []),
                    { icon: 'fullscreen', label: t('present'), onClick: () => store.set({ presenting: true }) },
                    { icon: 'printer', label: t('print_pdf'), onClick: env.print },
                ]} />}
            </PopButton>
        </header>
    );
}

function BottomBar() {
    const { store, boot, actions } = useDesigner();
    const els = useSelected(store);
    const mode = useStore(store, (s) => s.mode);
    const panel = useStore(store, (s) => s.panel);
    const pageIndex = useStore(store, (s) => s.pageIndex);
    const pages = useStore(store, (s) => s.doc.pages.length);

    if (els.length || mode === 'page') {
        return (
            <div className="dzm-ctx">
                <div className="dzm-ctx-scroll"><Toolbar /></div>
                <button type="button" className="dzm-done" onClick={() => actions.clear()} aria-label={t('done')}><Icon name="check-lg" /></button>
            </div>
        );
    }
    const tabs = [
        ['elements', 'pentagon-half', t('elements')],
        ['text', 'fonts', t('text')],
        ...((boot.fields || []).length ? [['fields', 'magic', t('fields')]] : []),
        ['uploads', 'cloud-arrow-up', t('uploads')],
        ['layers', 'layers', t('layers')],
    ];
    return (
        <>
            {pages > 1 && (
                <div className="dzm-pager">
                    <button type="button" disabled={pageIndex === 0} onClick={() => actions.goPage(pageIndex - 1)} aria-label={t('page_up')}><Icon name="chevron-left" /></button>
                    <span>{t('page_n', { n: pageIndex + 1 })} / {pages}</span>
                    <button type="button" disabled={pageIndex === pages - 1} onClick={() => actions.goPage(pageIndex + 1)} aria-label={t('page_down')}><Icon name="chevron-right" /></button>
                </div>
            )}
            <nav className="dzm-tabs">
                {tabs.map(([k, icon, label]) => {
                    const on = k === 'layers' ? panel === 'position' : panel === k;
                    return (
                        <button key={k} type="button" className={on ? 'is-on' : ''} onClick={() => store.set(k === 'layers' ? { panel: on ? null : 'position', positionTab: 'layers' } : { panel: on ? null : k })}>
                            <Icon name={icon} /><span>{label}</span>
                        </button>
                    );
                })}
            </nav>
        </>
    );
}

export default function MobileEditor({ env }) {
    const { store } = useDesigner();
    const panel = useStore(store, (s) => s.panel);
    const conflict = useStore(store, (s) => s.saveState === 'conflict');
    const [, force] = useState(0);

    return (
        <div className="dz-app is-mobile">
            <TopBar env={env} />
            {conflict && (
                <div className="dz-conflict" role="alert">
                    <Icon name="exclamation-triangle-fill" /> {t('conflict')}
                    <button type="button" className="dz-btn is-sm" onClick={() => { env.persistence.loadLatest(); force((n) => n + 1); }}>{t('load_theirs')}</button>
                    <button type="button" className="dz-btn is-sm is-primary" onClick={env.persistence.keepMine}>{t('keep_mine')}</button>
                </div>
            )}
            <StartFromBar />
            <main className="dz-main"><Canvas mobile /></main>
            <BottomBar />
            {panel && <div className="dzm-sheet-back" onClick={() => store.set({ panel: null })} />}
            <ActivePanel />
            <ContextMenu />
            <Presenter />
        </div>
    );
}
