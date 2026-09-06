/**
 * ConsoleParts.jsx — the furniture both scoring consoles are built from.
 *
 * The laptop and the tablet are different instruments; these are the pieces
 * they genuinely share, so a change to how an undo is confirmed or how a
 * refusal is shown lands on both at once.
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';

/* ══════════════════════════════════════════════════════════════════════════
   Hold-to-confirm
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Correcting past the toast takes a 1s hold, so a stray tap on a busy table
 * cannot rewrite a match. Releasing cancels it.
 */
export function HoldButton({ onHold, className, style, title, children }) {
    const [holding, setHolding] = useState(false);
    const timer = useRef(null);

    const down = useCallback((e) => {
        e.preventDefault();
        setHolding(true);
        timer.current = setTimeout(() => { setHolding(false); onHold(); }, 1000);
    }, [onHold]);

    const up = useCallback(() => {
        clearTimeout(timer.current);
        setHolding(false);
    }, []);

    useEffect(() => () => clearTimeout(timer.current), []);

    return (
        <button
            type="button"
            title={title}
            className={`${className || ''}${holding ? ' holding' : ''}`}
            style={style}
            onPointerDown={down}
            onPointerUp={up}
            onPointerLeave={up}
            onPointerCancel={up}
        >
            <span className="sweep" />
            <span style={{ position: 'relative' }}>{children}</span>
        </button>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The one dialog every decision goes through
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * The friction the spec asks for: end, reset, correction and disqualification
 * are all this same card, and DQ / finalize additionally make the operator type
 * the match number.
 *
 * `fields` is [{name, type:'choice'|'text', label, options, value, placeholder}].
 */
export function Modal({ dialog, words, onCancel, onConfirm }) {
    const [values, setValues] = useState({});

    useEffect(() => {
        if (!dialog) return;
        const seed = {};
        (dialog.fields || []).forEach((f) => { seed[f.name] = f.value == null ? '' : f.value; });
        setValues(seed);
    }, [dialog]);

    if (!dialog) return null;

    return (
        <div id="scrim" onMouseDown={(e) => { if (e.target.id === 'scrim') onCancel(); }}>
            <div id="modal" role="dialog" aria-modal="true">
                <div id="modalTitle">{dialog.title}</div>

                <div id="modalBody">
                    {(dialog.fields || []).map((f) => (
                        <div key={f.name}>
                            <label htmlFor={`f-${f.name}`}>{f.label}</label>

                            {f.type === 'choice' ? (
                                <div className="choiceRow">
                                    {Object.keys(f.options || {}).map((k) => (
                                        <button
                                            key={k}
                                            type="button"
                                            className={`choice${values[f.name] === k ? ' on' : ''}`}
                                            onClick={() => setValues((v) => ({ ...v, [f.name]: k }))}
                                        >
                                            {f.options[k]}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <input
                                    id={`f-${f.name}`}
                                    type="text"
                                    value={values[f.name] || ''}
                                    placeholder={f.placeholder || ''}
                                    onChange={(e) => setValues((v) => ({ ...v, [f.name]: e.target.value }))}
                                />
                            )}
                        </div>
                    ))}
                </div>

                <div id="modalActions">
                    <button type="button" className="btn small" id="modalCancel" onClick={onCancel}>{words.ctl_cancel}</button>
                    <button type="button" className="btn small ok" id="modalOk" onClick={() => onConfirm(values)}>{words.ctl_confirm}</button>
                </div>
            </div>
        </div>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The event log
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Nothing is ever erased: an undo APPENDS a reversal with a reason, and the row
 * it reversed stays on the record, struck through. That is what "audit, not
 * erase" means from the operator's side.
 */
export function LogList({ log, words, sources, penalties, onReverse }) {
    return (
        <>
            {log.map((row) => {
                const kind = ['point', 'advantage', 'penalty', 'reverse'].includes(row.action) ? row.action : 'other';
                const detail = [
                    row.side ? (row.side === 'blue' ? words.corner_blue : words.corner_white) : null,
                    row.value ? `+${row.value}` : null,
                    row.source ? ((sources[row.source] && sources[row.source].label) || penalties[row.source] || row.source) : null,
                    row.reason || null,
                    row.by || null,
                ].filter(Boolean).join(' · ');

                return (
                    <div key={row.id} className={`logRow${row.reversed ? ' rev' : ''}`}>
                        <span className="ts">{row.clock || ''}</span>
                        <span className={`logChip ${kind}`}>{row.action}</span>
                        <span className="d">{detail}</span>

                        {!row.reversed && ['point', 'advantage', 'penalty'].includes(row.action) && (
                            <HoldButton
                                className="btn undo"
                                // 32px, matching the runtime the Blade console
                                // uses: a log row is a record to read, and its
                                // undo must not grow into a button somebody hits
                                // while scanning the list.
                                style={{ minHeight: 32 }}
                                title={words.ctl_undo_hint}
                                onHold={() => onReverse(row.id)}
                            >
                                {words.ctl_undo}
                            </HoldButton>
                        )}
                    </div>
                );
            })}
        </>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The running order
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * A match still waiting on a feeder is LISTED — the operator needs to see what
 * is coming — but it cannot be loaded: nobody can be introduced as "winner of
 * match 3".
 */
export function QueueList({ queue, words, onLoad }) {
    return (
        <>
            {queue.map((m) => (
                <div key={m.id} className="qItem">
                    <span className="n">{m.number ? `#${m.number}` : '—'}</span>
                    <div className="who">
                        {`${(m.blue && m.blue.name) || words.tbd}  ·  ${(m.white && m.white.name) || words.tbd}`}
                        <div className="m">{[m.division, m.stage].filter(Boolean).join(' · ')}</div>
                    </div>
                    <button
                        type="button"
                        className="btn"
                        style={{ minHeight: 40 }}
                        disabled={!m.runnable}
                        onClick={() => onLoad(m.id)}
                    >
                        {words.ctl_load}
                    </button>
                </div>
            ))}
        </>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   Scoring buttons
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * Six actions per corner, built from the server's own price list.
 *
 * The VALUE is shown because it is what an official is thinking in, and the
 * ACTION is named beneath it because in jiu-jitsu the number does not name the
 * action — two points is three different things.
 */
export function ScoreButtons({ side, sources, disabled, onScore }) {
    return (
        <>
            {Object.keys(sources).map((key) => (
                <button
                    key={key}
                    type="button"
                    className="btn score"
                    disabled={disabled}
                    onClick={() => onScore(side, key, sources[key].value)}
                >
                    <span className="v">{`+${sources[key].value}`}</span>
                    <span className="l">{sources[key].label}</span>
                </button>
            ))}
        </>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The toast
   ══════════════════════════════════════════════════════════════════════════ */

export function Toast({ toast, words, onUndo }) {
    if (!toast) return null;
    return (
        <div id="toast">
            <span id="toastText">{toast.text}</span>
            {toast.entryId ? (
                <button type="button" className="btn small" id="toastUndo" onClick={() => onUndo(toast.entryId)}>
                    {words.ctl_undo}
                </button>
            ) : null}
        </div>
    );
}
