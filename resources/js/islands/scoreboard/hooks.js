/**
 * hooks.js — the four things both scoreboard islands need from the browser.
 *
 *   useStage        the 1920-wide stage that only ever SCALES
 *   useClock        the clock, DERIVED rather than ticked
 *   useCourtBoard   the live link, through the existing window.CourtBoard contract
 *   useFitName      a competitor's name shrunk to one line, never truncated
 *
 * None of them decide anything. The clock is the only value computed on this
 * side of the wire at all, and it is computed from `remaining` + `running` so
 * two screens on one mat cannot drift apart.
 */

import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { clockWords } from './util';

/* ────────────────────────────────────────────────────────────────────────────
   The stage
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Scale a 1920-wide stage to fit its host, without ever re-laying it out.
 *
 * `tall` is the authored height for a wall board (1080, always) and the
 * LAID-OUT height for the console, which is as tall as its layout came out.
 * offsetHeight is used for the second because it is unaffected by the transform
 * — reading scrollHeight of a scaled element measures the wrong thing.
 */
export function useStage({ fixedHeight = null } = {}) {
    const rootRef = useRef(null);
    const stageRef = useRef(null);

    useLayoutEffect(() => {
        const root = rootRef.current;
        if (!root) return undefined;

        const fit = () => {
            const r = root.getBoundingClientRect();
            if (!r.width || !r.height) return;
            const stage = stageRef.current;
            const tall = fixedHeight || Math.max(stage ? stage.offsetHeight : 1080, 1080);
            root.style.setProperty('--stage-scale', String(Math.min(r.width / 1920, r.height / tall)));
        };

        fit();

        let observer = null;
        if (window.ResizeObserver) {
            observer = new ResizeObserver(fit);
            observer.observe(root);
            if (stageRef.current) observer.observe(stageRef.current);
        } else {
            window.addEventListener('resize', fit);
        }

        return () => {
            if (observer) observer.disconnect();
            else window.removeEventListener('resize', fit);
        };
    }, [fixedHeight]);

    return { rootRef, stageRef };
}

/* ────────────────────────────────────────────────────────────────────────────
   The clock
   ────────────────────────────────────────────────────────────────────────── */

/**
 * The remaining time, derived on THIS device's own clock.
 *
 * The server sends "how much was left when I wrote this"; elapsed time is
 * measured from the moment the message arrived, because a wall screen's
 * wall-clock is frequently wrong and only the delta matters.
 *
 * Driven by requestAnimationFrame so a change is on screen in the next frame —
 * but state is only written when the DISPLAYED value actually changes, which is
 * once a second. A low-powered screen therefore renders once a second, not sixty
 * times, while a pause or a point still lands instantly.
 */
export function useClock(state, receivedAt) {
    const compute = useCallback(() => {
        if (!state) return 0;
        if (!state.running) return Math.max(0, state.remaining || 0);
        return Math.max(0, (state.remaining || 0) - (performance.now() - receivedAt) / 1000);
    }, [state, receivedAt]);

    // Derived IN RENDER, not from state. A push that changes the clock is
    // therefore correct on the very render that absorbs it, rather than on the
    // next animation frame — which matters because rAF is throttled in a
    // background tab and on a low-powered screen, and a board that repaints for
    // some other reason must never show a stale time while it waits for one.
    const seconds = compute();
    const secondsRef = useRef(seconds);
    secondsRef.current = seconds;

    // The tick exists only to FORCE a render when the displayed second turns
    // over. One render a second on a wall screen, not sixty.
    const [, setTick] = useState(0);
    const shown = useRef(clockWords(seconds));
    shown.current = clockWords(seconds);

    useEffect(() => {
        let frame = 0;
        const tick = () => {
            const next = clockWords(compute());
            if (next !== shown.current) setTick((n) => n + 1);
            frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [compute]);

    const remaining = useCallback(() => secondsRef.current, []);

    return { words: clockWords(seconds), remaining, seconds };
}

/**
 * Is the match inside its warning window?
 *
 * Derived from the clock rather than stored, so no two screens can disagree
 * about when the final minute started (MatState makes the same point). Computed
 * in render for the same reason the clock is.
 */
export function useWarning(state, receivedAt) {
    const limit = (state && state.rules && state.rules.warning != null) ? state.rules.warning : 60;

    const left = !state ? 0 : (state.running
        ? Math.max(0, (state.remaining || 0) - (performance.now() - receivedAt) / 1000)
        : (state.remaining || 0));

    const warn = !!state && state.status === 'live' && !!state.running && limit > 0 && left > 0 && left <= limit;

    const [, setTick] = useState(0);
    const shown = useRef(warn);
    shown.current = warn;

    useEffect(() => {
        let frame = 0;
        const tick = () => {
            const now = !state ? 0 : (state.running
                ? Math.max(0, (state.remaining || 0) - (performance.now() - receivedAt) / 1000)
                : (state.remaining || 0));
            const on = !!state && state.status === 'live' && !!state.running && limit > 0 && now > 0 && now <= limit;
            if (on !== shown.current) setTick((n) => n + 1);
            frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [state, receivedAt, limit]);

    return warn;
}

/* ────────────────────────────────────────────────────────────────────────────
   The live link
   ────────────────────────────────────────────────────────────────────────── */

/**
 * Register `window.CourtBoard`, which is the whole contract with the socket.
 *
 * partials/screen-link.blade.php is UNTOUCHED by the React work: it still
 * connects, still walks its own MQTT frames on a Worker thread, and still calls
 * `update()` / `mode()` / `stale()` on whatever object this name holds. So the
 * island answers to that name instead of replacing the transport — which is
 * what lets the feature flag be flipped back mid-event without the live link
 * knowing anything happened.
 *
 * The handlers live in a ref so re-registering never happens on a state change:
 * the object identity the worker's callbacks captured stays valid for the life
 * of the page.
 */
export function useCourtBoard({ pinned, onUpdate, getMode }) {
    const onUpdateRef = useRef(onUpdate);
    const getModeRef = useRef(getMode);
    const [stale, setStale] = useState(false);

    onUpdateRef.current = onUpdate;
    getModeRef.current = getMode;

    useEffect(() => {
        const previous = window.CourtBoard;

        window.CourtBoard = {
            update: (payload) => onUpdateRef.current && onUpdateRef.current(payload),
            mode: () => (getModeRef.current ? getModeRef.current() : 'upcoming'),
            pinned,
            stale: (on) => setStale(!!on),
        };

        return () => {
            // Put back whatever was there, so an island that unmounts inside a
            // shell swap never leaves a dead object for the worker to call.
            if (window.CourtBoard && window.CourtBoard.pinned === pinned) {
                window.CourtBoard = previous;
            }
        };
    }, [pinned]);

    return stale;
}

/**
 * The heartbeat, and the unpair signal, in one field.
 *
 * Five seconds: when the socket cannot get through (a WebView that will not run
 * the client in a Worker, a venue that blocks websockets) this poll IS the
 * experience, and a screen that has been unpaired must not take two minutes to
 * notice. A 404 or `claimed:false` means this device is no longer what it
 * thinks it is — reload, and the server sends it back to the pairing room
 * (CLAUDE.md → Unattended Devices Must Always Recover).
 */
export function useHeartbeat(statusUrl) {
    useEffect(() => {
        if (!statusUrl) return undefined;

        const id = setInterval(() => {
            fetch(statusUrl, { cache: 'no-store', headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((s) => { if (s && s.claimed === false) window.location.reload(); })
                .catch(() => { /* offline — keep the match on screen */ });
        }, 5000);

        return () => clearInterval(id);
    }, [statusUrl]);
}

/* ────────────────────────────────────────────────────────────────────────────
   Names
   ────────────────────────────────────────────────────────────────────────── */

/**
 * A competitor's name never takes a second line.
 *
 * It is the thing the hall reads first, and a name broken across two lines
 * reads as two people. So the type SHRINKS to fit and is never truncated: an
 * ellipsis through somebody's name on a wall is worse than small type. A name
 * long enough to still overflow at the floor size is CONDENSED, toward the edge
 * it is aligned to.
 *
 * Measured, not guessed — and re-measured when the value changes, when the
 * element is finally laid out (a hidden layer has no width), and once the
 * webfaces have loaded, because Anton is far narrower than the fallback and
 * fitting to the wrong metrics sizes every name wrong.
 */
export function useFitName(value, { max, min, align = 'left' }) {
    const ref = useRef(null);

    useLayoutEffect(() => {
        const node = ref.current;
        if (!node) return undefined;

        let cancelled = false;

        const fit = () => {
            if (cancelled || !node.clientWidth) return;

            node.style.transform = 'none';
            let size = max;
            node.style.fontSize = `${size}px`;

            for (let i = 0; i < 120 && size > min && node.scrollWidth > node.clientWidth; i++) {
                size -= 2;
                node.style.fontSize = `${size}px`;
            }

            if (node.scrollWidth > node.clientWidth) {
                const ratio = Math.max(0.6, node.clientWidth / node.scrollWidth);
                node.style.transformOrigin = align === 'right' ? 'right center' : 'left center';
                node.style.transform = `scaleX(${ratio.toFixed(3)})`;
            }
        };

        fit();

        // The element may be inside a layer that is not visible yet, and a
        // hidden element has no width to measure against.
        const observer = window.ResizeObserver ? new ResizeObserver(fit) : null;
        if (observer) observer.observe(node);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit).catch(() => {});

        return () => {
            cancelled = true;
            if (observer) observer.disconnect();
        };
    }, [value, max, min, align]);

    return ref;
}
