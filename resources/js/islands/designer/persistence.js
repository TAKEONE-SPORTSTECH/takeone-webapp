/**
 * Saving. Every change is saved on its own about a second after the last one
 * (no Save button to forget); the first save creates the design. The server
 * keeps a revision number: if somebody else saved in between, the save is
 * REFUSED and the editor says so, rather than either side silently losing work.
 */
import * as M from './model';
import { t } from './context';
import { toast } from './ui';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function request(url, { method = 'GET', body, json = true, keepalive = false } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    if (json && body !== undefined) headers['Content-Type'] = 'application/json';
    const res = await fetch(url, { method, headers, body: json && body !== undefined ? JSON.stringify(body) : body, credentials: 'same-origin', keepalive });
    let data = null;
    try { data = await res.json(); } catch { /* not JSON */ }
    return { ok: res.ok, status: res.status, data };
}

/*
 * A picture larger than any page needs is made smaller BEFORE it is sent: 4000 px
 * on its long side is more than an A4 page at 300 dpi, so nothing is lost in
 * print, and a 15 MB phone photo goes up as ~1-2 MB instead of crawling. JPEG stays
 * JPEG; PNG/WebP become WebP so transparency survives. Anything that cannot be
 * decoded here (or a GIF) is sent untouched and the server decides.
 */
const UPLOAD_SIDE = 4000;
const UPLOAD_SMALL = 3 * 1024 * 1024;

async function fitForUpload(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== 'function') return file;
    // Never let shrinking hold an upload hostage: if decoding stalls, send the original.
    const within = (p, ms) => Promise.race([p, new Promise((_, no) => setTimeout(() => no(new Error('slow')), ms))]);
    let bmp;
    try { bmp = await within(createImageBitmap(file), 15000); } catch { return file; }
    const long = Math.max(bmp.width, bmp.height);
    if (long <= UPLOAD_SIDE && file.size <= UPLOAD_SMALL) { bmp.close?.(); return file; }
    const k = Math.min(1, UPLOAD_SIDE / long);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bmp.width * k);
    canvas.height = Math.round(bmp.height * k);
    canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
    bmp.close?.();
    const jpeg = file.type === 'image/jpeg';
    let blob = null;
    try { blob = await within(new Promise((res) => canvas.toBlob(res, jpeg ? 'image/jpeg' : 'image/webp', jpeg ? 0.9 : 0.92)), 15000); } catch { return file; }
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + (jpeg ? '.jpg' : '.webp'), { type: blob.type });
}

export function createPersistence(store, boot) {
    const ep = boot.endpoints;
    const url = (tpl, uuid) => tpl.replace('__UUID__', uuid);
    let timer = null;
    let inflight = null;
    let savedDirty = store.get().dirty || 0;

    const payload = () => {
        const s = store.get();
        return {
            revision: s.revision,
            title: s.title,
            format: s.size.format,
            orientation: s.size.orientation,
            width_mm: s.size.wMm,
            height_mm: s.size.hMm,
            document: s.doc,
        };
    };

    async function save() {
        if (inflight) return inflight;
        const s = store.get();
        if (s.saveState === 'conflict' || s.mode === 'viewing') return null;
        const dirtyAt = s.dirty;
        store.set({ saveState: 'saving' });
        inflight = (async () => {
            try {
                const r = s.uuid
                    ? await request(url(ep.save, s.uuid), { method: 'PUT', body: payload() })
                    : await request(ep.create, { method: 'POST', body: payload() });
                if (r.ok && r.data?.success) {
                    savedDirty = dirtyAt;
                    store.set({ uuid: r.data.design.uuid, revision: r.data.design.revision, saveState: store.get().dirty === dirtyAt ? 'saved' : 'dirty', savedAt: Date.now() });
                    return r.data.design.uuid;
                }
                if (r.status === 409) {
                    store.set({ saveState: 'conflict', conflict: r.data?.design || null });
                    return null;
                }
                store.set({ saveState: 'error' });
                toast('error', r.data?.message || r.data?.errors?.document?.[0] || t('save_failed'));
                return null;
            } catch {
                store.set({ saveState: 'error' });
                return null;
            } finally {
                inflight = null;
            }
        })();
        const result = await inflight;
        // Something changed while we were saving: go again.
        if (store.get().dirty !== savedDirty && store.get().saveState === 'dirty') schedule(600);
        return result;
    }

    function schedule(ms = 1000) {
        clearTimeout(timer);
        timer = setTimeout(save, ms);
    }

    let lastDirty = store.get().dirty;
    let inGesture = false;
    store.subscribe(() => {
        const s = store.get();
        const changed = s.dirty !== lastDirty;
        const gestureEnded = inGesture && !s.gesture;
        inGesture = Boolean(s.gesture);
        if (changed) {
            lastDirty = s.dirty;
            if (s.saveState !== 'conflict' && s.saveState !== 'saving' && s.saveState !== 'dirty') store.set({ saveState: 'dirty' });
        }
        // Saving waits for a drag or resize to finish.
        if ((changed || gestureEnded) && !s.gesture && s.dirty !== savedDirty) schedule();
    });

    window.addEventListener('beforeunload', (e) => {
        if (store.get().dirty !== savedDirty && store.get().saveState !== 'conflict') {
            save();
            e.preventDefault();
            e.returnValue = '';
        }
    });

    const api = {
        save,
        flush: () => { clearTimeout(timer); return save(); },
        async ensureSaved() {
            const s = store.get();
            if (s.uuid) return s.uuid;
            return api.flush();
        },
        async upload(original) {
            const file = await fitForUpload(original);
            const max = Number(boot.limits?.upload_bytes) || 0;
            // Say so now, not after the whole file has crawled up to a server that will refuse it.
            if (max && file.size > max) throw new Error(t('upload_too_big', { mb: Math.floor(max / 1048576) }));
            const uuid = await api.ensureSaved();
            if (!uuid) throw new Error(t('save_first'));
            const fd = new FormData();
            fd.append('file', file);
            const r = await request(url(ep.upload, uuid), { method: 'POST', body: fd, json: false });
            if (!r.ok || !r.data?.success) throw new Error(r.data?.message || r.data?.errors?.file?.[0] || t('upload_failed'));
            store.set({ assets: [r.data.asset, ...store.get().assets] });
            return r.data.asset;
        },
        async deleteAsset(assetUuid) {
            const r = await request(url(ep.assetDelete, assetUuid), { method: 'DELETE' });
            if (!r.ok) { toast('error', t('delete_failed')); return; }
            const s = store.get();
            store.set({ assets: s.assets.filter((a) => a.uuid !== assetUuid) });
            // Anything showing that picture goes with it.
            const pages = s.doc.pages.map((p) => ({ ...p, elements: p.elements.filter((e) => e.asset !== assetUuid) }));
            if (pages.some((p, i) => p.elements.length !== s.doc.pages[i].elements.length)) {
                store.commit();
                store.set({ doc: { ...s.doc, pages }, selection: [], mode: 'none' });
            }
        },
        /** Take the newer version somebody else saved (after a conflict). */
        loadLatest() {
            const d = store.get().conflict;
            if (!d) return;
            store.resetHistory();
            store.set({
                doc: d.document, revision: d.revision, title: d.title,
                size: { ...store.get().size, format: d.format, orientation: d.orientation, wMm: d.widthMm, hMm: d.heightMm, w: d.widthMm * 96 / 25.4, h: d.heightMm * 96 / 25.4 },
                selection: [], mode: 'none', editingId: null, conflict: null, saveState: 'saved', quiet: true,
            });
            savedDirty = store.get().dirty;
        },
        /** Keep MY version: take the server's revision number and save over it. */
        keepMine() {
            const d = store.get().conflict;
            if (!d) return;
            store.set({ revision: d.revision, conflict: null, saveState: 'dirty' });
            save();
        },
        printUrl: () => (store.get().uuid ? url(ep.print, store.get().uuid) : null),
    };

    return api;
}

export { M };
