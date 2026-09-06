/**
 * console-state.js — the scoring table's whole conversation with the server.
 *
 * ONE implementation, both layouts (the laptop at the table and the tablet in
 * the referee's hand are different instruments but not different RULES —
 * CLAUDE.md → Mobile / Desktop Separation). It is the React replacement for
 * `bjj/scoreboard/runtime.blade.php`, and it keeps that file's contract exactly:
 * post an INTENTION, draw the answer.
 *
 * ── The console still decides NOTHING about the score ──────────────────────
 * Every button posts "blue passed the guard", never "blue now has five". The
 * server prices the action from Ledger::POINT_SOURCES and replays the whole
 * score from the ledger, and THAT is what gets drawn. A console that has fallen
 * behind cannot overwrite the truth.
 *
 * ── …but it does not wait to SHOW it ───────────────────────────────────────
 * `optimistic` holds the deltas of commands that are in flight, and the numbers
 * on screen are authoritative + in-flight. A referee's press therefore moves the
 * number in the same frame instead of one round trip later, while the server's
 * reply remains the only thing that can SET it: when the reply lands the delta
 * is dropped, and if the server refused, the number simply returns to the truth.
 *
 * Only POINTS and ADVANTAGES are shown optimistically — the two actions the
 * design spec already treats as no-confirmation. A penalty can disqualify
 * somebody, so it is never displayed before the server has agreed to it.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

let deltaId = 0;

export function useConsole(props) {
    const [state, setState] = useState(props.state);
    const [log, setLog] = useState(props.log || []);
    const [queue, setQueue] = useState(props.queue || []);
    const [stall, setStall] = useState(null);          // {side, until} — private to this console
    const [receivedAt, setReceivedAt] = useState(() => performance.now());
    const [optimistic, setOptimistic] = useState([]);  // in-flight display deltas

    const csrf = useRef(
        (document.querySelector('meta[name="csrf-token"]') || {}).content
        || (document.querySelector('meta[name="csrf-token"]') || { getAttribute: () => '' }).getAttribute('content')
    );

    /* ── One tap is one event ──────────────────────────────────────────────
       400ms, exactly as the design spec sets out: a referee's hand on a tablet
       at the edge of a mat produces double-taps, and the cost of one is a point
       nobody scored, on a wall, in front of a hall. */
    const locked = useRef(false);
    const guarded = useCallback((fn) => {
        if (locked.current) return;
        locked.current = true;
        setTimeout(() => { locked.current = false; }, 400);
        fn();
    }, []);

    /* ── The undo toast ───────────────────────────────────────────────────
       Not a delayed write: the point is ALREADY recorded and already on the
       wall. The toast is a fast path to the reversal that would otherwise take
       a hold on the log row. */
    const [toast, setToast] = useState(null);          // {text, entryId} | {text}
    const toastTimer = useRef(null);

    const showToast = useCallback((next, ms) => {
        setToast(next);
        clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setToast(null), ms);
    }, []);

    const flash = useCallback((message) => {
        if (!message) return;
        // The console is a standalone document with no app shell behind it, so
        // it cannot reach window.showToast. Same idea, its own furniture.
        showToast({ text: message }, 4000);
    }, [showToast]);

    /**
     * Post an intention and draw the answer.
     *
     * A 422 is a REFUSAL an official needs to read, and it comes back with the
     * current state so a console that had fallen behind corrects itself.
     */
    const send = useCallback((command, payload, done) => {
        const optimism = payload && payload.__optimistic;
        const id = optimism ? ++deltaId : null;
        if (optimism) setOptimistic((list) => [...list, { id, ...optimism }]);

        const body = { mat: props.court, command, ...(payload || {}) };
        delete body.__optimistic;

        return fetch(props.urls.command, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf.current || '',
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        })
            .then((r) => r.json().then((j) => ({ ok: r.ok, body: j })))
            .then((res) => {
                // The truth rides along with a refusal too, so draw it either way.
                if (res.body.state) { setState(res.body.state); setReceivedAt(performance.now()); }
                if (res.body.log) setLog(res.body.log);
                if (res.body.queue) setQueue(res.body.queue);
                if (Object.prototype.hasOwnProperty.call(res.body, 'stall')) setStall(res.body.stall);
                if (id) setOptimistic((list) => list.filter((d) => d.id !== id));

                if (!res.ok) { flash(res.body.message || ''); return null; }
                if (done) done(res.body);
                return res.body;
            })
            .catch(() => {
                // The press never reached the server: drop the display delta so
                // the console shows the truth rather than a point that is not in
                // the ledger.
                if (id) setOptimistic((list) => list.filter((d) => d.id !== id));
                flash('');
                return null;
            });
    }, [props.court, props.urls.command, flash]);

    /* ── The live link ────────────────────────────────────────────────────
       The console listens on the MAT's topic, which is what makes two consoles
       at one mat possible: the laptop at the table and the tablet in the
       referee's hand hear the same messages, so a point scored on one appears
       on the other at once. partials/screen-link owns the socket and calls this
       through window.CourtBoard — unchanged by the island. */
    const absorb = useCallback((p) => {
        if (!p) return;

        if (p.state) {
            setState(p.state);
            if (p.log) setLog(p.log);
            if (p.queue) setQueue(p.queue);
            if (Object.prototype.hasOwnProperty.call(p, 'stall')) setStall(p.stall);
        } else if (p.mode) {
            setState(p);
        } else {
            return;                        // not a shape this page knows
        }

        setReceivedAt(performance.now());
    }, []);

    useEffect(() => () => clearTimeout(toastTimer.current), []);

    /* ── What the operator actually sees ──────────────────────────────────
       Authoritative + in-flight. The two are added only for DISPLAY; nothing
       here is ever posted back, so an optimistic number can never become a
       second truth. */
    const score = { ...(state.score || {}) };
    optimistic.forEach((d) => {
        if (d.points) score[`${d.side}Points`] = (score[`${d.side}Points`] || 0) + d.points;
        if (d.advantages) score[`${d.side}Advantages`] = (score[`${d.side}Advantages`] || 0) + d.advantages;
    });

    return {
        state, score, log, queue, stall, receivedAt,
        pending: optimistic.length > 0,
        toast, showToast, flash, send, guarded, absorb,
        setStall,
    };
}
