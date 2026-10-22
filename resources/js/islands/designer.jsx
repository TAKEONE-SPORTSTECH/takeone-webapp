/**
 * designer.jsx — the printables designer island (app/Printables).
 *
 * One editor for everything the platform prints. The page that mounts it
 * (printables::desktop / printables::mobile) hands over everything in one JSON
 * boot block: the design, the page sizes and fonts this kind may use, its live
 * fields, its pictures, the endpoints and the strings. The island decides
 * nothing about who may do what — every endpoint re-authorises.
 */
import React, { useEffect, useMemo } from 'react';
import { mountIsland } from '../island';
import { createStore } from './designer/store';
import { createActions } from './designer/actions';
import { createPersistence } from './designer/persistence';
import { bindShortcuts } from './designer/shortcuts';
import { DesignerContext, setStrings, t } from './designer/context';
import { setFonts, ensureFonts, fontsToLoad } from './designer/fonts';
import { documentFonts, documentTexts } from './designer/model';
import { mmToPx, PX_PER_MM } from './designer/geometry';
import { assetElement } from './designer/library';
import { measureText } from './designer/Canvas';
import { toast } from './designer/ui';
import Editor from './designer/Editor';
import MobileEditor from './designer/MobileEditor';
import './designer/designer.css';

function App({ boot }) {
    const value = useMemo(() => {
        setStrings(boot.strings);
        setFonts(boot.fonts);
        const d = boot.design;
        const store = createStore({
            doc: d.document,
            size: { format: d.format, orientation: d.orientation, wMm: d.widthMm, hMm: d.heightMm, w: mmToPx(d.widthMm), h: mmToPx(d.heightMm) },
            title: d.title || '',
            uuid: d.uuid,
            revision: d.revision,
            assets: boot.assets || [],
            pageIndex: 0,
            selection: [],
            mode: 'none',
            editingId: null,
            zoom: 1,
            zoomMode: 'fit',
            panel: null,
            preview: false,
            // A real person's data shown in place of the samples: { id, label, values } (RealPersonPanel).
            real: null,
            guides: [],
            marquee: null,
            saveState: d.uuid ? 'saved' : 'new',
            dirty: 0,
            canUndo: false,
            canRedo: false,
        });
        const env = { measureText, unitPx: () => PX_PER_MM };
        const actions = createActions(store, env);
        const persistence = createPersistence(store, boot);
        Object.assign(env, {
            persistence,
            zoomBy: (f) => store.set({ zoom: Math.max(0.1, Math.min(5, store.get().zoom * f)), zoomMode: 'manual' }),
            upload: persistence.upload,
            deleteAsset: persistence.deleteAsset,
            async uploadAndAdd(file) {
                try {
                    const a = await persistence.upload(file);
                    actions.add(assetElement(a, store.get().size));
                } catch (e) {
                    toast('error', e.message || t('upload_failed'));
                }
            },
            async print() {
                // Open the tab first (a popup opened after an await is blocked), then point it at the print view.
                const win = window.open('about:blank', '_blank');
                const uuid = await persistence.ensureSaved();
                await persistence.flush();
                const url = uuid ? persistence.printUrl() : null;
                if (!url) { win?.close(); toast('error', t('save_first')); return; }
                if (win) win.location.href = url;
                else window.location.href = url;
            },
        });
        // The design's fonts, and a companion for any Arabic or Japanese it holds (or its
        // preview samples hold) that a chosen face cannot draw — again as the design changes.
        const samples = (boot.fields || []).map((f) => f.sample);
        const load = (doc) => ensureFonts(fontsToLoad(documentFonts(doc), [...documentTexts(doc), ...samples]));
        load(d.document);
        let lastDoc = d.document, timer = null;
        store.subscribe(() => {
            const doc = store.get().doc;
            if (doc === lastDoc) return;
            lastDoc = doc;
            clearTimeout(timer);
            timer = setTimeout(() => load(doc), 250);
        });

        return { store, actions, env, boot };
    }, [boot]);

    useEffect(() => bindShortcuts(value.store, value.actions, value.env), [value]);

    return (
        <DesignerContext.Provider value={value}>
            {boot.layout === 'mobile' ? <MobileEditor env={value.env} /> : <Editor env={value.env} />}
        </DesignerContext.Provider>
    );
}

mountIsland('#printables-designer', (el) => {
    const boot = JSON.parse(document.getElementById('printables-boot')?.textContent || '{}');
    if (el.dataset.layout) boot.layout = el.dataset.layout;
    return <App boot={boot} />;
});
