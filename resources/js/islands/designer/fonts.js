/**
 * Loads the designer's self-hosted typefaces on demand (FontFace API), so a
 * family is fetched the first time something uses it — the font list does not
 * download eighteen families to draw its own names.
 */
const loaded = new Map(); // family -> Promise
let registry = [];

export function setFonts(list) {
    registry = Array.isArray(list) ? list : [];
}

export const fontList = () => registry;

export function ensureFont(family) {
    if (!family) return Promise.resolve();
    if (loaded.has(family)) return loaded.get(family);
    const entry = registry.find((f) => f.family === family);
    if (!entry || typeof FontFace === 'undefined') {
        const p = Promise.resolve();
        loaded.set(family, p);
        return p;
    }
    const p = Promise.all(
        entry.faces.map((face) => {
            const ff = new FontFace(family, `url("${face.url}")`, { weight: face.weight, style: face.style || 'normal', display: 'swap' });
            return ff.load().then((f) => document.fonts.add(f)).catch(() => null);
        }),
    );
    loaded.set(family, p);
    return p;
}

export const ensureFonts = (families) => Promise.all(families.map(ensureFont));

/**
 * Just enough of a family to draw its own name in the font list: ONE face (the
 * regular one), not every weight — with hundreds of families, and Japanese ones
 * several megabytes a weight, the list must not fetch whole families as it scrolls.
 */
const previewed = new Map();
export function ensureFontPreview(family) {
    if (loaded.has(family)) return loaded.get(family);
    if (previewed.has(family)) return previewed.get(family);
    const entry = registry.find((f) => f.family === family);
    if (!entry || !entry.faces.length || typeof FontFace === 'undefined') return Promise.resolve();
    const face = entry.faces.find((f) => (f.style || 'normal') === 'normal') || entry.faces[0];
    const p = new FontFace(family, `url("${face.url}")`, { weight: face.weight, style: face.style || 'normal', display: 'swap' })
        .load().then((f) => document.fonts.add(f)).catch(() => null);
    previewed.set(family, p);
    return p;
}

/** The group a family belongs to, for a sample line in its own script. */
export const fontGroup = (family) => registry.find((f) => f.family === family)?.group || '';

/*
 * Letters a font does not have (owner, 2026-10-20). Most Latin faces carry no
 * Arabic or Japanese at all, and the browser then draws those letters in whatever
 * font the computer happens to have — or, on the print server, nothing. So every
 * face is backed by a COMPANION drawn for that script, matched to its style
 * (a serif by a serif), and the companion is loaded whenever the design or the
 * values filling it actually hold that script. Same stack in the editor and in print.
 */
const ARABIC = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
const JAPANESE = /[\u3040-\u30FF\u3400-\u4DBF\u4E00-\u9FFF\uFF66-\uFF9F]/;
export const SCRIPTS = ['arabic', 'japanese'];

/** Which of the scripts a piece of text holds. */
export const scriptsOf = (text) => ({ arabic: ARABIC.test(text || ''), japanese: JAPANESE.test(text || '') });

/** Does this family draw that script itself? (Arabic faces carry Latin too; Latin faces carry neither.) */
export const covers = (family, script) => fontGroup(family) === script;

/** The face that draws a script a family lacks: the style matched where we can. */
export function companion(family, script) {
    const serif = fontGroup(family) === 'serif';
    if (script === 'arabic') return serif ? 'Amiri' : 'Cairo';
    return serif ? 'Noto Serif JP' : 'Noto Sans JP';
}

/** The CSS stack for a text in `family`: itself, then its companions, then the default. */
export function fontStack(family) {
    const f = family || 'Inter';
    const extra = SCRIPTS.filter((s) => !covers(f, s)).map((s) => `"${companion(f, s)}"`);
    return [`"${f}"`, ...extra, '"Inter"', 'system-ui', 'sans-serif'].join(', ');
}

/**
 * The families to load for these texts: the design's own fonts, plus a companion
 * for each script the texts hold that one of those fonts lacks.
 *
 * @param {string[]} families  the design's fonts
 * @param {string[]} texts     its words, and the values that will fill its {tokens}
 */
export function fontsToLoad(families, texts) {
    const found = scriptsOf((texts || []).filter((t) => typeof t === 'string' && !t.startsWith('data:')).join(' '));
    const out = new Set(families);
    families.forEach((f) => SCRIPTS.forEach((s) => { if (found[s] && !covers(f, s)) out.add(companion(f, s)); }));
    return [...out];
}
