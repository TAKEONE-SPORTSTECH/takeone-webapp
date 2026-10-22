/** What every designer component reads: the store, the actions, the boot payload, the strings. */
import { createContext, useContext } from 'react';

export const DesignerContext = createContext(null);

export const useDesigner = () => useContext(DesignerContext);

let strings = {};

export function setStrings(s) {
    strings = s || {};
}

/** A UI string by key, with :name replacements; falls back to the key so a gap is visible, not blank. */
export function t(key, replace = {}) {
    let s = strings[key] ?? key;
    Object.entries(replace).forEach(([k, v]) => { s = s.replace(`:${k}`, String(v)); });
    return s;
}
