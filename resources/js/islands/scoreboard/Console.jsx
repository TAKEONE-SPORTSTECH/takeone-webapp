/**
 * Console.jsx — the Brazilian Jiu-Jitsu SCORING TABLE, in React (desktop).
 *
 * A straight PORT of `bjj/scoreboard/desktop/control.blade.php` plus the
 * behaviour that lived in `runtime.blade.php` (Design Rule #1: not a redesign —
 * the two paths share one stylesheet, bjj/scoreboard/partials/console-styles).
 * It renders only when `features.react_scoreboard` is on.
 *
 * ── What React actually buys at a mat ──────────────────────────────────────
 * A press moves the number in the SAME FRAME. The old console posted, waited
 * for the reply, and then repainted — perfectly correct, and perfectly visible
 * as a hesitation on a busy table. Here the in-flight action is added to the
 * displayed score immediately (see console-state.js) while the server's replay
 * stays the only thing that can SET it, so the operator gets an instrument that
 * responds like a stopwatch without the console ever holding an opinion about
 * the score.
 *
 * Everything else is unchanged and deliberately so: the graduated friction
 * (400ms lockout · 5s undo toast · 1s hold on a log row · modal for the
 * destructive three · modal AND the match number for DQ and finalize), the
 * stalling flow being referee-only, and the log that appends reversals rather
 * than erasing anything.
 */

import React, { useCallback, useMemo, useState } from 'react';
import { useClock, useCourtBoard, useHeartbeat, useStage, useWarning } from './hooks';
import { useConsole } from './console-state';
import { LogList, Modal, QueueList, ScoreButtons, Toast } from './ConsoleParts';

/** Mirrors Scoring::METHODS_NEEDING_WINNER — the server still decides. */
const METHODS_NEEDING_WINNER = ['submission', 'decision', 'dq', 'walkover', 'medical', 'forfeit'];

/** The clock plate: its own component so a tick repaints two lines, not the console. */
function Clock({ state, receivedAt, words }) {
    const { words: display } = useClock(state, receivedAt);
    const warn = useWarning(state, receivedAt);

    return (
        <div id="ctlClock">
            <div id="clockState">{words.statuses[state.status] || ''}</div>
            <div id="clockVal" className={warn ? 'warn' : undefined}>{display}</div>
        </div>
    );
}

/** The referee's own count, on this console and nowhere else. */
function StallCount({ stall }) {
    const [left, setLeft] = useState('—');

    React.useEffect(() => {
        if (!stall || !stall.until) { setLeft('—'); return undefined; }
        const tick = () => {
            const remaining = Math.max(0, Math.ceil((new Date(stall.until).getTime() - Date.now()) / 1000));
            setLeft(String(remaining));
        };
        tick();
        const id = setInterval(tick, 200);
        return () => clearInterval(id);
    }, [stall]);

    return <span id="stallCount">{left}</span>;
}

function Corner({ side, label, colour, plateInk, order, delay, mirror, angle, name, score, rules, words, sources, disabled, onScore, onAdvantage, onPenalty }) {
    const rev = mirror ? { flexDirection: 'row-reverse' } : null;
    const warnAt = (rules && rules.penalty_warn_at) || 3;

    return (
        <div
            className="card"
            style={{
                borderTop: `8px solid ${colour}`, padding: '22px 26px', display: 'flex',
                flexDirection: 'column', gap: 16, order, minHeight: 0,
                animation: `cardIn .6s ${delay} cubic-bezier(.2,.8,.2,1) both`,
            }}
        >
            {/* The score plate: the corner's colour bleeds under its own label,
                so the number is read against the side it belongs to. POINTS
                only — the other two ladders have their own plates below. */}
            <div style={{
                ...rev, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 18,
                padding: '8px 22px', borderRadius: 12,
                background: `linear-gradient(${angle},color-mix(in srgb, ${colour} 22%, transparent),#0a0b10 55%)`,
            }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0, alignItems: mirror ? 'flex-end' : undefined }}>
                    <div style={{ fontSize: 26, fontWeight: 700, letterSpacing: '.28em', color: plateInk, textTransform: 'uppercase' }}>
                        {label}
                    </div>
                    <div id={`${side}Name`} style={{
                        fontSize: 30, fontWeight: 600, color: 'var(--text)', whiteSpace: 'nowrap',
                        overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 420,
                    }}>
                        {name}
                    </div>
                </div>
                <div id={`${side}Score`} style={{
                    fontFamily: "'Anton',sans-serif", fontSize: 96, lineHeight: 1,
                    fontVariantNumeric: 'tabular-nums', textShadow: '0 4px 30px rgba(0,0,0,.8)',
                }}>
                    {score.points}
                </div>
            </div>

            <div className="scoreGrid" id={`${side}ScoreGrid`} style={{ flex: 1, minHeight: 0 }}>
                <ScoreButtons side={side} sources={sources} disabled={disabled} onScore={onScore} />
            </div>

            <div className="scoreGrid">
                <button type="button" className="btn adv" disabled={disabled} onClick={() => onAdvantage(side)}>
                    {words.advantages}
                </button>
                <button type="button" className="btn pen" disabled={disabled} onClick={() => onPenalty(side)}>
                    {words.penalties}
                </button>
            </div>

            <div className="counters" style={rev || undefined}>
                <div className="c a" style={rev || undefined}>
                    <span className="l">{words.adv_short}</span>
                    <span className="n" id={`${side}Adv`}>{score.advantages}</span>
                </div>
                <div className="c p" style={rev || undefined}>
                    <span className="l">{words.pen_short}</span>
                    <span className="n" id={`${side}Pen`}>{score.penalties}</span>
                </div>
            </div>

            {/* Console only. The hall is never told what is about to happen to
                somebody — it learns of a disqualification when there is one. */}
            {score.penalties >= warnAt && (
                <div className="warnDq" id={`${side}WarnDq`}>{words.penalty_next_is_dq}</div>
            )}
        </div>
    );
}

export default function ConsoleIsland({ props }) {
    const words = props.words;
    const sources = props.sources;
    const console_ = useConsole(props);
    const { state, score, log, queue, stall, receivedAt, toast, showToast, flash, send, guarded, absorb } = console_;

    // 1080, not "whatever the layout came out to": the console is a fixed 16:9
    // canvas and is letterboxed inside the glass, exactly like the wall board.
    const { rootRef, stageRef } = useStage({ fixedHeight: 1080 });
    useCourtBoard({ pinned: 'console', onUpdate: absorb, getMode: () => state.mode || 'upcoming' });
    useHeartbeat(props.urls && props.urls.heartbeat);

    const [dialog, setDialog] = useState(null);
    const [queueOpen, setQueueOpen] = useState(false);

    const loaded = !!state.matchId;
    const over = !!state.finished;
    const running = !!state.running;
    const boutDisabled = !loaded || over;

    /* ── Scoring: no confirmation at all, a 400ms lockout, the undo toast ── */
    const onScore = useCallback((side, source, value) => {
        guarded(() => {
            send('point', { side, source, __optimistic: { side, points: value } }, (body) => {
                const last = (body.log || [])[0];
                if (last) {
                    showToast({
                        text: `${side === 'blue' ? words.corner_blue : words.corner_white} · +${last.value}`,
                        entryId: last.id,
                    }, 5000);
                }
            });
        });
    }, [guarded, send, showToast, words]);

    const onAdvantage = useCallback((side) => {
        guarded(() => {
            send('advantage', { side, __optimistic: { side, advantages: 1 } }, (body) => {
                const last = (body.log || [])[0];
                if (last) {
                    showToast({
                        text: `${side === 'blue' ? words.corner_blue : words.corner_white} · ${words.ctl_undo_hint}`,
                        entryId: last.id,
                    }, 5000);
                }
            });
        });
    }, [guarded, send, showToast, words]);

    /* A penalty asks WHY. It is a formal act that reaches the record, and the
       wall's four-second notice reads the reason out of it. Never optimistic. */
    const onPenalty = useCallback((side) => {
        setDialog({
            title: words.penalties,
            fields: [{ name: 'source', type: 'choice', label: words.ctl_reason, options: props.penalties, value: 'other' }],
            confirm: (out) => {
                setDialog(null);
                guarded(() => send('penalty', { side, source: out.source || 'other' }));
            },
        });
    }, [guarded, send, words, props.penalties]);

    const askReverse = useCallback((id) => {
        setDialog({
            title: words.ctl_undo,
            fields: [{ name: 'reason', type: 'text', label: words.ctl_reason, placeholder: words.reason_required }],
            confirm: (out) => {
                if (!out.reason) { flash(words.reason_required); return; }
                setDialog(null);
                send('reverse', { ledger_id: id, reason: out.reason });
            },
        });
    }, [send, flash, words]);

    /** The one action that cannot be taken back from this console at all. */
    const confirmWithNumber = useCallback((title, run) => {
        setDialog({
            title,
            fields: [{ name: 'number', type: 'text', label: words.ctl_type_match_no, placeholder: state.matchNo || '' }],
            confirm: (out) => {
                if (String(out.number || '').trim() !== String(state.matchNo || '')) { flash(words.ctl_type_match_no); return; }
                setDialog(null);
                run();
            },
        });
    }, [state.matchNo, flash, words]);

    const onEnd = useCallback(() => {
        setDialog({
            title: words.ctl_end,
            fields: [
                // Opens on whoever the SCORE says is ahead, because that is the
                // answer in most matches — but it is still a choice: a submission
                // is routinely won by the corner that is behind on points.
                { name: 'winner', type: 'choice', label: words.ctl_winner, options: { blue: words.corner_blue, white: words.corner_white }, value: (state.score || {}).leader || '' },
                { name: 'method', type: 'choice', label: words.ctl_method, options: props.methods, value: 'points' },
                { name: 'note', type: 'text', label: words.ctl_reason },
            ],
            confirm: (out) => {
                // A method that names somebody, with nobody named. Caught here so
                // the official fixes it in the dialog they are already looking at.
                if (!out.winner && METHODS_NEEDING_WINNER.includes(out.method)) { flash(words.ctl_winner_required); return; }

                if (out.method === 'dq') {
                    setDialog(null);
                    confirmWithNumber(words.ctl_end, () => send('end', out));
                    return;
                }
                setDialog(null);
                send('end', out);
            },
        });
    }, [state.score, send, flash, confirmWithNumber, words, props.methods]);

    const transport = useMemo(() => ({
        label: running ? words.ctl_pause : (state.status === 'idle' ? words.ctl_start : words.ctl_resume),
        action: running ? 'pause' : (state.status === 'idle' ? 'start' : 'resume'),
    }), [running, state.status, words]);

    // A level match at 0:00 is not a result — IBJJF sends it to the referee.
    const level = loaded && !over && state.awaitingDecision && !(state.score || {}).leader;

    return (
        <div id="root" ref={rootRef}>
            <div id="stage" ref={stageRef}>
                <div style={{ position: 'absolute', left: -120, top: 240, width: 540, height: 540, borderRadius: '50%', background: 'radial-gradient(circle,rgba(19,98,209,.16),transparent 70%)', filter: 'blur(30px)', animation: 'orbA 16s ease-in-out infinite', pointerEvents: 'none' }} />
                <div style={{ position: 'absolute', right: -120, top: 180, width: 560, height: 560, borderRadius: '50%', background: 'radial-gradient(circle,rgba(230,235,242,.09),transparent 70%)', filter: 'blur(30px)', animation: 'orbB 19s ease-in-out infinite', pointerEvents: 'none' }} />

                {/* ── Top bar. Read-only: these come from the draw, and the draw
                    is where they are corrected. ── */}
                <div id="ctlTop">
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 2, flexShrink: 0 }}>
                        <div style={{ fontFamily: "'Anton',sans-serif", fontSize: 34, letterSpacing: '.04em', textTransform: 'uppercase' }}>
                            {words.ctl_title}
                        </div>
                        <div style={{ fontSize: 18, color: 'var(--muted)', letterSpacing: '.18em', textTransform: 'uppercase' }}>
                            {props.event.title}
                        </div>
                    </div>

                    <div style={{ flex: 1, minWidth: 0, display: 'flex', justifyContent: 'flex-end', alignItems: 'flex-end', gap: 40 }}>
                        {[
                            { cap: words.sb_division, id: 'topMeta', max: 640, value: [state.division, state.stage, state.matchNo ? `#${state.matchNo}` : null].filter(Boolean).join(' · ') },
                            { cap: words.court_court, value: props.court },
                            { cap: words.ctl_ruleset, id: 'topRuleset', max: 260, value: state.ruleset || '' },
                            { cap: words.ctl_referee, id: 'topReferee', max: 260, value: state.referee || '' },
                        ].map((f) => (
                            <div key={f.cap} style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 0 }}>
                                <span className="cap">{f.cap}</span>
                                <div id={f.id} className="val" style={f.max ? { maxWidth: f.max } : undefined}>{f.value}</div>
                            </div>
                        ))}

                        {/* What the mat is doing, in a word and a light. The word
                            is the point: a colour on its own never says anything. */}
                        <div id="liveBadge" style={{ display: 'flex', alignItems: 'center', gap: 12, flexShrink: 0 }}>
                            <span id="liveDot" className={state.status === 'live' ? 'on' : undefined} />
                            <span id="liveText" style={{ fontSize: 24, fontWeight: 700, letterSpacing: '.24em', textTransform: 'uppercase', color: 'var(--gold)' }}>
                                {words.statuses[state.status] || ''}
                            </span>
                        </div>
                    </div>
                </div>

                {/* ── BLUE | centre | WHITE ── */}
                <div id="ctlBody">
                    <Corner
                        side="blue" label={words.corner_blue} colour="var(--blue)" plateInk="var(--blue-plate)"
                        order="1" delay=".05s" mirror={false} angle="100deg"
                        name={(state.blue && state.blue.name ? state.blue.name : words.tbd).toUpperCase()}
                        score={{ points: score.bluePoints || 0, advantages: score.blueAdvantages || 0, penalties: score.bluePenalties || 0 }}
                        rules={state.rules} words={words} sources={sources} disabled={boutDisabled}
                        onScore={onScore} onAdvantage={onAdvantage} onPenalty={onPenalty}
                    />

                    <div className="card" id="ctlCentre" style={{ animation: 'cardIn .6s .18s cubic-bezier(.2,.8,.2,1) both' }}>
                        {/* What this mat does between matches, and the biggest
                            thing on the panel after the clock. */}
                        <button type="button" id="btnQueue" className="btn ok" onClick={() => setQueueOpen(true)}>
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6" /><path d="M5 12 L12 19 L19 12" /></svg>
                            {words.ctl_queue}
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6" /><path d="M5 12 L12 19 L19 12" /></svg>
                        </button>

                        <Clock state={state} receivedAt={receivedAt} words={words} />

                        <button type="button" id="btnStart" className="btn ok" disabled={boutDisabled}
                                onClick={() => guarded(() => send(transport.action))}>
                            {transport.label}
                        </button>

                        <div className="transport">
                            <button type="button" id="btnPause" className="btn" disabled={!loaded || over || !running}
                                    onClick={() => guarded(() => send('pause'))}>
                                {words.ctl_pause}
                            </button>
                            <button type="button" className="btn" disabled={boutDisabled}
                                    style={{ color: '#c4b5fd', borderColor: 'rgba(167,139,250,.5)' }}
                                    onClick={() => guarded(() => send('review'))}>
                                {words.ctl_review}
                            </button>
                            <button type="button" className="btn" disabled={boutDisabled}
                                    style={{ color: '#7ae582', borderColor: 'rgba(122,229,130,.45)' }}
                                    onClick={() => guarded(() => send('medical'))}>
                                {words.ctl_medical}
                            </button>
                            <button type="button" id="btnOvertime" className="btn" disabled={boutDisabled}
                                    onClick={() => setDialog({
                                        title: words.ctl_overtime,
                                        fields: [{ name: 'seconds', type: 'text', label: words.ctl_overtime, value: '180' }],
                                        confirm: (out) => { setDialog(null); send('overtime', { seconds: parseInt(out.seconds, 10) || 180 }); },
                                    })}>
                                {words.ctl_overtime}
                            </button>
                            <button type="button" className="btn" disabled={!loaded}
                                    style={{ color: 'var(--gold)', borderColor: 'var(--gold)' }}
                                    onClick={() => guarded(() => send('intro'))}>
                                {words.vs}
                            </button>
                            {/* Never disabled with the rest: a stuck screen is most
                                likely to need this when the mat is empty. */}
                            <button type="button" className="btn"
                                    style={{ color: 'var(--blue-ink)', borderColor: 'rgba(138,180,255,.45)' }}
                                    onClick={() => send('resync')}>
                                ↻
                            </button>
                        </div>

                        {level && (
                            <button type="button" id="btnDecision" className="btn"
                                    style={{ color: 'var(--gold)', borderColor: 'var(--gold)' }}
                                    onClick={() => setDialog({
                                        title: words.ctl_decision,
                                        fields: [{ name: 'winner', type: 'choice', label: words.ctl_decision, options: { blue: words.corner_blue, white: words.corner_white } }],
                                        confirm: (out) => { if (!out.winner) return; setDialog(null); send('decision', { winner: out.winner }); },
                                    })}>
                                {words.ctl_decision}
                            </button>
                        )}

                        {/* Destructive controls sit APART from the ones above. */}
                        <div id="endRow">
                            <button type="button" id="btnEnd" className="btn danger" disabled={boutDisabled} onClick={onEnd}>
                                {words.ctl_end}
                            </button>
                            <button type="button" id="btnReset" className="btn danger" disabled={!loaded}
                                    onClick={() => setDialog({ title: words.ctl_reset, fields: [], confirm: () => { setDialog(null); send('reset'); } })}>
                                {words.ctl_reset}
                            </button>
                            <button type="button" id="btnCommit" className="btn ok" disabled={!loaded || !over}
                                    onClick={() => confirmWithNumber(words.ctl_commit, () => send('commit'))}>
                                {words.ctl_commit}
                            </button>
                        </div>

                        <div id="ctlFoot">
                            <span id="screensLine">
                                {props.screens ? words.ctl_screens.replace(':count', props.screens) : words.ctl_no_screens}
                            </span>
                            <label>
                                <input
                                    type="checkbox" id="themeToggle" style={{ width: 22, height: 22, accentColor: '#ffe135' }}
                                    checked={state.theme === 'venue'}
                                    onChange={(e) => send('theme', { theme: e.target.checked ? 'venue' : 'arena' })}
                                />
                                {words.ctl_theme}
                            </label>
                        </div>
                    </div>

                    <Corner
                        side="white" label={words.corner_white} colour="var(--white)" plateInk="var(--white-ink)"
                        order="3" delay=".3s" mirror angle="260deg"
                        name={(state.white && state.white.name ? state.white.name : words.tbd).toUpperCase()}
                        score={{ points: score.whitePoints || 0, advantages: score.whiteAdvantages || 0, penalties: score.whitePenalties || 0 }}
                        rules={state.rules} words={words} sources={sources} disabled={boutDisabled}
                        onScore={onScore} onAdvantage={onAdvantage} onPenalty={onPenalty}
                    />
                </div>

                {/* ── The record, along the bottom ── */}
                <div id="ctlBottom">
                    <div className="card" style={{ padding: '14px 20px', display: 'flex', flexDirection: 'column', gap: 8, minHeight: 0 }}>
                        <div className="cap">{words.ctl_log}</div>
                        <div id="log">
                            <LogList
                                log={log} words={words} sources={sources} penalties={props.penalties}
                                onReverse={askReverse}
                            />
                        </div>
                    </div>

                    {/* The stalling flow is REFEREE-ONLY and lives here. The public
                        board learns nothing until a penalty is actually applied. */}
                    <div className="card" style={{ padding: '14px 20px', display: 'flex', flexDirection: 'column', gap: 10, minHeight: 0 }}>
                        {/* The heading carries what the count is FOR, on one line — the
                            note's own row was the 11px this card overflowed by. */}
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 12, minWidth: 0 }}>
                            <span className="cap">{words.ctl_stall}</span>
                            <span style={{ fontSize: 17, color: 'var(--faint)', letterSpacing: '.06em', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {words.penalty_stalling}
                            </span>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                            <StallCount stall={stall} />
                            <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                                <button type="button" className="btn small" disabled={boutDisabled}
                                        style={{ color: 'var(--blue-ink)', borderColor: 'rgba(138,180,255,.45)' }}
                                        onClick={() => guarded(() => send('stall', { side: 'blue', phase: 'start' }))}>
                                    {words.corner_blue}
                                </button>
                                <button type="button" className="btn small" disabled={boutDisabled}
                                        style={{ color: 'var(--white-ink)', borderColor: 'rgba(230,235,242,.4)' }}
                                        onClick={() => guarded(() => send('stall', { side: 'white', phase: 'start' }))}>
                                    {words.corner_white}
                                </button>
                            </div>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                            <button type="button" className="btn small" id="stallCancel" disabled={!stall || !stall.until}
                                    onClick={() => send('stall', { phase: 'cancel' })}>
                                {words.ctl_stall_cancel}
                            </button>
                            <button type="button" className="btn small pen" id="stallApply" disabled={!stall || !stall.until}
                                    onClick={() => setDialog({ title: words.penalties, fields: [], confirm: () => { setDialog(null); send('stall', { phase: 'apply' }); } })}>
                                {words.ctl_stall_apply}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── The running order. A modal, because the centre column belongs
                to the clock: open it, load a match, and it closes itself. ── */}
            {queueOpen && (
                <div className="scrim" onMouseDown={(e) => { if (e.currentTarget === e.target) setQueueOpen(false); }}>
                    <div className="modal" style={{ width: 1100, height: 860 }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 20 }}>
                            <div style={{ fontFamily: "'Anton',sans-serif", fontSize: 36, letterSpacing: '.06em', textTransform: 'uppercase' }}>
                                {words.ctl_queue}
                            </div>
                            <button type="button" className="btn small" onClick={() => setQueueOpen(false)}>{words.ctl_cancel}</button>
                        </div>
                        <div style={{ flex: 1, minHeight: 0, overflow: 'auto', display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <QueueList
                                queue={queue} words={words}
                                onLoad={(id) => { setQueueOpen(false); guarded(() => send('load', { match_id: id })); }}
                            />
                        </div>
                    </div>
                </div>
            )}

            <Modal
                dialog={dialog} words={words}
                onCancel={() => setDialog(null)}
                onConfirm={(values) => dialog.confirm(values)}
            />

            <Toast toast={toast} words={words} onUndo={(id) => { showToast(null, 0); askReverse(id); }} />
        </div>
    );
}
