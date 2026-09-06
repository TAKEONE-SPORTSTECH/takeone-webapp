/**
 * island.js — the shell-aware React island mount helper.
 *
 * WHY THIS EXISTS
 * ---------------
 * Both admin/mobile shells navigate by replacing a container's `innerHTML`
 * (`#shell-content` on mobile, `<main data-shell-main>` on desktop admin) and
 * then dispatching `shell:navigated` on `window`. A React root whose container
 * is torn out that way is never told: its effects keep running, its intervals
 * keep ticking and its `realtime:*` listeners stay bound to `window` forever.
 * Every subsequent visit stacks another leaked root on top.
 *
 * So mounting is NEVER done at script top level (the desktop admin shell also
 * dedupes scripts and runs each one only once per session, which would mean a
 * top-level mount fires exactly once and never again). It is driven entirely by
 * events, and every mount is paired with an unmount.
 *
 * CONTRACT
 * --------
 *   mountIsland(selector, renderFn)
 *     selector — CSS selector for the mount point (may match 0 or 1 elements)
 *     renderFn — (element) => ReactNode
 *
 * Idempotent: calling it twice for the same selector registers one island, and
 * an element already carrying `data-island-mounted` is never mounted twice.
 */

import { createRoot } from 'react-dom/client';

/** Registered islands: { selector, renderFn }. */
const registry = [];

/** element -> react root. Non-iterable by design; `live` is what we sweep. */
const roots = new WeakMap();

/**
 * Every currently-mounted { el, root }. A plain Set (not a WeakSet) because we
 * must be able to walk it to find orphans; entries are removed the moment their
 * element leaves the document, so nothing is retained past its swap.
 */
const live = new Set();

/** Unmount every root whose element is no longer in the document. */
function sweep() {
    for (const entry of Array.from(live)) {
        if (document.contains(entry.el)) continue;
        try {
            entry.root.unmount();
        } catch (e) {
            /* best-effort: a torn-out root must never break navigation */
        }
        roots.delete(entry.el);
        entry.el.removeAttribute('data-island-mounted');
        live.delete(entry);
    }
}

/** Mount one registered island if its element is present and unmounted. */
function mountOne({ selector, renderFn }) {
    const el = document.querySelector(selector);
    if (!el) return;
    if (el.hasAttribute('data-island-mounted') || roots.has(el)) return;

    let root;
    try {
        root = createRoot(el);
        root.render(renderFn(el));
    } catch (e) {
        // A failed island must leave the page usable, not blank.
        if (root) {
            try { root.unmount(); } catch (e2) { /* ignore */ }
        }
        console.error('[island] failed to mount', selector, e);
        return;
    }

    el.setAttribute('data-island-mounted', '1');
    roots.set(el, root);
    live.add({ el, root });
}

/** Sweep orphans first, then (re)mount anything present. */
function sync() {
    sweep();
    registry.forEach(mountOne);
}

/**
 * Wire the shell listeners exactly once per page session, even if several
 * island entrypoints import this module.
 */
if (!window.__islandRuntime) {
    window.__islandRuntime = true;

    // The shells swap content and THEN dispatch this, so by the time we run the
    // old container is already detached — `sweep()` finds it and unmounts it,
    // which is what runs the component's effect teardowns (listeners, intervals).
    window.addEventListener('shell:navigated', sync);

    // The desktop admin shell also re-fires DOMContentLoaded after a swap; the
    // mobile shell does not. Listening to both is harmless because `sync()` is
    // idempotent.
    window.addEventListener('DOMContentLoaded', sync);

    // A hard back/forward that restores a bfcache page must re-check the DOM.
    window.addEventListener('pageshow', sync);
}

/**
 * Register an island. Safe to call at module top level — it only REGISTERS
 * there; the actual mounting is event-driven (plus one immediate attempt for
 * the first paint, when no shell event will ever fire).
 */
export function mountIsland(selector, renderFn) {
    if (registry.some((r) => r.selector === selector)) return;
    registry.push({ selector, renderFn });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sync, { once: true });
    } else {
        sync();
    }
}

export default mountIsland;
