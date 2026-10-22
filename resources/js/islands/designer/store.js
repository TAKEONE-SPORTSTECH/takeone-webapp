/**
 * The designer's state — one small store, no library.
 *
 * Everything the editor draws comes from here: the document, which page is
 * open, what is selected, the zoom, which panel is out, the save state. React
 * reads slices through useStore(selector) (useSyncExternalStore underneath), so
 * a drag re-renders only what reads the moved element.
 *
 * HISTORY is snapshot-based and gesture-aware: a drag, a resize or a typing
 * session is ONE undo step however many frames it took. Call begin() when a
 * gesture starts and end() when it finishes; a discrete change (a colour pick,
 * a field + Enter) calls commit() before mutating. Undo restores the selection
 * with the document, as the spec asks.
 */
import { useSyncExternalStore, useRef, useCallback, useMemo } from 'react';

const LIMIT = 150;

export function createStore(initial) {
    let state = initial;
    const listeners = new Set();
    const past = [];
    const future = [];
    let pending = null; // snapshot taken at begin(), pushed at end() if anything changed

    const snapshot = () => ({
        doc: state.doc,
        size: state.size,
        selection: state.selection,
        pageIndex: state.pageIndex,
    });

    const emit = () => listeners.forEach((l) => l());

    const store = {
        get: () => state,

        /** Replace part of the state. `patch` may be an object or (state) => object. */
        set(patch) {
            const next = typeof patch === 'function' ? patch(state) : patch;
            if (!next) return;
            const quiet = next.quiet;
            state = { ...state, ...next, quiet: undefined };
            // A derived change (a text box measuring its own height) is not an edit to save.
            if (('doc' in next || 'size' in next) && !quiet) state.dirty = (state.dirty || 0) + 1;
            emit();
        },

        subscribe(fn) {
            listeners.add(fn);
            return () => listeners.delete(fn);
        },

        /* ── history ── */

        commit() {
            past.push(snapshot());
            if (past.length > LIMIT) past.shift();
            future.length = 0;
            store.set({ canUndo: true, canRedo: false });
        },

        begin() {
            if (!pending) pending = snapshot();
        },

        end() {
            if (!pending) return;
            const before = pending;
            pending = null;
            if (before.doc === state.doc && before.size === state.size) return;
            past.push(before);
            if (past.length > LIMIT) past.shift();
            future.length = 0;
            store.set({ canUndo: true, canRedo: false });
        },

        undo() {
            if (pending) store.end();
            const prev = past.pop();
            if (!prev) return;
            future.push(snapshot());
            store.set({ ...prev, mode: prev.selection.length ? 'element' : 'none', editingId: null, canUndo: past.length > 0, canRedo: true });
        },

        redo() {
            const next = future.pop();
            if (!next) return;
            past.push(snapshot());
            store.set({ ...next, mode: next.selection.length ? 'element' : 'none', editingId: null, canUndo: true, canRedo: future.length > 0 });
        },

        /** Forget history — after loading a newer version from the server. */
        resetHistory() {
            past.length = 0;
            future.length = 0;
            pending = null;
            store.set({ canUndo: false, canRedo: false });
        },
    };

    return store;
}

/**
 * Read a slice of the store. Re-renders only when the selected value changes
 * (Object.is), so select primitives or stable references.
 */
export function useStore(store, selector) {
    const sel = useRef(selector);
    sel.current = selector;
    const get = useCallback(() => sel.current(store.get()), [store]);

    return useSyncExternalStore(store.subscribe, get, get);
}

/** The selected elements of the open page, as a stable array. */
export function useSelected(store) {
    const selection = useStore(store, (s) => s.selection);
    const elements = useStore(store, (s) => s.doc.pages[Math.min(s.pageIndex, s.doc.pages.length - 1)].elements);

    return useMemo(() => selection.map((id) => elements.find((e) => e.id === id)).filter(Boolean), [selection, elements]);
}
