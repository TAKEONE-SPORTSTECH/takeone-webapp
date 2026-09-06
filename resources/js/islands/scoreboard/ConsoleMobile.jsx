/**
 * ConsoleMobile.jsx — the tablet at the mat's edge, in React.
 *
 * A straight PORT of `bjj/scoreboard/mobile/control.blade.php`, sharing every
 * rule with the laptop console through `console-state.js`: same commands, same
 * endpoint, same graduated friction, same optimistic display. Only the
 * INSTRUMENT differs (CLAUDE.md → Mobile / Desktop Separation) — the person
 * holding this is standing beside the mat with one hand free, looking at the
 * fight and not at the screen, so the corners are full-height columns under the
 * thumbs, the clock and transport are a fixed bar at the bottom, and everything
 * that is read rather than pressed lives in a sheet that is opened deliberately.
 */

import React, { useCallback, useState } from 'react';
import { useClock, useCourtBoard, useHeartbeat, useWarning } from './hooks';
import { useConsole } from './console-state';
import { LogList, Modal, QueueList, ScoreButtons, Toast } from './ConsoleParts';

const METHODS_NEEDING_WINNER = ['submission', 'decision', 'dq', 'walkover', 'medical', 'forfeit'];

function Side({ side, label, name, score, rules, words, sources, disabled, onScore, onAdvantage, onPenalty }) {
    const warnAt = (rules && rules.penalty_warn_at) || 3;

    return (
        <div className={`side ${side}`}>
            <div className="head">
                <div style={{ minWidth: 0 }}>
                    <div className="chip">{label}</div>
                    <div className="who" id={`${side}Name`}>{name}</div>
                </div>
                <div className="big num" id={`${side}Score`}>{score.points}</div>
            </div>

            <div className="grid" id={`${side}ScoreGrid`}>
                <ScoreButtons side={side} sources={sources} disabled={disabled} onScore={onScore} />
            </div>

            <div className="grid">
                <button type="button" className="btn adv" disabled={disabled} onClick={() => onAdvantage(side)}>
                    {words.adv_short}
                </button>
                <button type="button" className="btn pen" disabled={disabled} onClick={() => onPenalty(side)}>
                    {words.pen_short}
                </button>
            </div>

            <div className="counters">
                <div className="c a"><span className="l">{words.adv_short}</span><span className="n" id={`${side}Adv`}>{score.advantages}</span></div>
                <div className="c p"><span className="l">{words.pen_short}</span><span className="n" id={`${side}Pen`}>{score.penalties}</span></div>
            </div>

            {score.penalties >= warnAt && (
                <div className="warnDq" id={`${side}WarnDq`}>{words.penalty_next_is_dq}</div>
            )}
        </div>
    );
}

export default function ConsoleMobileIsland({ props }) {
    const words = props.words;
    const sources = props.sources;
    const { state, score, log, queue, stall, receivedAt, toast, showToast, flash, send, guarded, absorb } = useConsole(props);

    useCourtBoard({ pinned: 'console', onUpdate: absorb, getMode: () => state.mode || 'upcoming' });
    useHeartbeat(props.urls && props.urls.heartbeat);

    const { words: clock } = useClock(state, receivedAt);
    const warn = useWarning(state, receivedAt);

    const [dialog, setDialog] = useState(null);
    const [sheet, setSheet] = useState(null);        // null | 'queue' | 'log' | 'more'

    const loaded = !!state.matchId;
    const over = !!state.finished;
    const running = !!state.running;
    const boutDisabled = !loaded || over;
    const level = loaded && !over && state.awaitingDecision && !(state.score || {}).leader;

    const onScore = useCallback((side, source, value) => {
        guarded(() => {
            send('point', { side, source, __optimistic: { side, points: value } }, (body) => {
                const last = (body.log || [])[0];
                if (last) showToast({ text: `${side === 'blue' ? words.corner_blue : words.corner_white} · +${last.value}`, entryId: last.id }, 5000);
            });
        });
    }, [guarded, send, showToast, words]);

    const onAdvantage = useCallback((side) => {
        guarded(() => {
            send('advantage', { side, __optimistic: { side, advantages: 1 } }, (body) => {
                const last = (body.log || [])[0];
                if (last) showToast({ text: `${side === 'blue' ? words.corner_blue : words.corner_white} · ${words.ctl_undo_hint}`, entryId: last.id }, 5000);
            });
        });
    }, [guarded, send, showToast, words]);

    const onPenalty = useCallback((side) => {
        setDialog({
            title: words.penalties,
            fields: [{ name: 'source', type: 'choice', label: words.ctl_reason, options: props.penalties, value: 'other' }],
            confirm: (out) => { setDialog(null); guarded(() => send('penalty', { side, source: out.source || 'other' })); },
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
                { name: 'winner', type: 'choice', label: words.ctl_winner, options: { blue: words.corner_blue, white: words.corner_white }, value: (state.score || {}).leader || '' },
                { name: 'method', type: 'choice', label: words.ctl_method, options: props.methods, value: 'points' },
                { name: 'note', type: 'text', label: words.ctl_reason },
            ],
            confirm: (out) => {
                if (!out.winner && METHODS_NEEDING_WINNER.includes(out.method)) { flash(words.ctl_winner_required); return; }
                if (out.method === 'dq') { setDialog(null); confirmWithNumber(words.ctl_end, () => send('end', out)); return; }
                setDialog(null);
                send('end', out);
            },
        });
    }, [state.score, send, flash, confirmWithNumber, words, props.methods]);

    const startLabel = running ? words.ctl_pause : (state.status === 'idle' ? words.ctl_start : words.ctl_resume);
    const startAction = running ? 'pause' : (state.status === 'idle' ? 'start' : 'resume');

    return (
        <>
            <div id="top">
                <div style={{ minWidth: 0 }}>
                    <div className="m" id="topMeta">
                        {[state.division, state.stage, state.matchNo ? `#${state.matchNo}` : null].filter(Boolean).join(' · ')}
                    </div>
                    <div className="m" id="topReferee">{state.referee || ''}</div>
                </div>
                <button type="button" className="btn" id="btnSheet" onClick={() => setSheet('queue')}>☰</button>
                <div id="liveBadge">
                    <span id="liveDot" className={state.status === 'live' ? 'on' : undefined} />
                    <span id="liveText">{words.statuses[state.status] || ''}</span>
                </div>
            </div>

            {/* Blue is on the LEFT here as well. The console and the wall must
                never disagree about which side somebody is on. */}
            <div id="corners">
                <Side
                    side="blue" label={words.corner_blue}
                    name={(state.blue && state.blue.name ? state.blue.name : words.tbd).toUpperCase()}
                    score={{ points: score.bluePoints || 0, advantages: score.blueAdvantages || 0, penalties: score.bluePenalties || 0 }}
                    rules={state.rules} words={words} sources={sources} disabled={boutDisabled}
                    onScore={onScore} onAdvantage={onAdvantage} onPenalty={onPenalty}
                />
                <Side
                    side="white" label={words.corner_white}
                    name={(state.white && state.white.name ? state.white.name : words.tbd).toUpperCase()}
                    score={{ points: score.whitePoints || 0, advantages: score.whiteAdvantages || 0, penalties: score.whitePenalties || 0 }}
                    rules={state.rules} words={words} sources={sources} disabled={boutDisabled}
                    onScore={onScore} onAdvantage={onAdvantage} onPenalty={onPenalty}
                />
            </div>

            <div id="foot">
                <div id="clockRow">
                    <div id="clockVal" className={`num${warn ? ' warn' : ''}`}>{clock}</div>
                    <div>
                        <div id="clockState">{words.statuses[state.status] || ''}</div>
                        {level && (
                            <button type="button" className="btn" id="btnDecision" style={{ minHeight: 44, marginTop: 'var(--s1)' }}
                                    onClick={() => setDialog({
                                        title: words.ctl_decision,
                                        fields: [{ name: 'winner', type: 'choice', label: words.ctl_decision, options: { blue: words.corner_blue, white: words.corner_white } }],
                                        confirm: (out) => { if (!out.winner) return; setDialog(null); send('decision', { winner: out.winner }); },
                                    })}>
                                {words.ctl_decision}
                            </button>
                        )}
                    </div>
                </div>
                <div id="transport">
                    <button type="button" className="btn ok" id="btnStart" disabled={boutDisabled}
                            onClick={() => guarded(() => send(startAction))}>
                        {startLabel}
                    </button>
                    <button type="button" className="btn" disabled={boutDisabled} onClick={() => guarded(() => send('review'))}>
                        {words.ctl_review}
                    </button>
                    <button type="button" className="btn danger" id="btnEnd" disabled={boutDisabled} onClick={onEnd}>
                        {words.ctl_end}
                    </button>
                </div>
            </div>

            {/* Everything read rather than pressed, opened deliberately so it can
                never be under a thumb that meant to score. */}
            {sheet && (
                <>
                    <div id="sheetScrim" onClick={() => setSheet(null)} />
                    <div id="sheet">
                        <div id="sheetHandle" />
                        <div className="sheetTabs">
                            {[['queue', words.ctl_queue], ['log', words.ctl_log], ['more', words.ctl_settings]].map(([key, label]) => (
                                <button key={key} type="button" className={`btn${sheet === key ? ' on' : ''}`} onClick={() => setSheet(key)}>
                                    {label}
                                </button>
                            ))}
                        </div>

                        <div id="sheetBody">
                            {sheet === 'queue' && (
                                <div id="queueList">
                                    <QueueList queue={queue} words={words}
                                               onLoad={(id) => { setSheet(null); guarded(() => send('load', { match_id: id })); }} />
                                </div>
                            )}

                            {sheet === 'log' && (
                                <div id="log">
                                    <LogList log={log} words={words} sources={sources} penalties={props.penalties} onReverse={askReverse} />
                                </div>
                            )}

                            {sheet === 'more' && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--s3)', paddingBottom: 'var(--s4)' }}>
                                    <div className="chip">{words.ctl_stall}</div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--s3)' }}>
                                        <span id="stallCount">{stall && stall.until
                                            ? Math.max(0, Math.ceil((new Date(stall.until).getTime() - Date.now()) / 1000))
                                            : '—'}</span>
                                        <button type="button" className="btn" style={{ flex: 1 }} disabled={boutDisabled}
                                                onClick={() => guarded(() => send('stall', { side: 'blue', phase: 'start' }))}>
                                            {words.corner_blue}
                                        </button>
                                        <button type="button" className="btn" style={{ flex: 1 }} disabled={boutDisabled}
                                                onClick={() => guarded(() => send('stall', { side: 'white', phase: 'start' }))}>
                                            {words.corner_white}
                                        </button>
                                    </div>
                                    <div style={{ display: 'flex', gap: 'var(--s2)' }}>
                                        <button type="button" className="btn" id="stallCancel" style={{ flex: 1 }} disabled={!stall || !stall.until}
                                                onClick={() => send('stall', { phase: 'cancel' })}>
                                            {words.ctl_stall_cancel}
                                        </button>
                                        <button type="button" className="btn pen" id="stallApply" style={{ flex: 1, minHeight: 56 }} disabled={!stall || !stall.until}
                                                onClick={() => setDialog({ title: words.penalties, fields: [], confirm: () => { setDialog(null); send('stall', { phase: 'apply' }); } })}>
                                            {words.ctl_stall_apply}
                                        </button>
                                    </div>

                                    <div className="chip" style={{ marginTop: 'var(--s4)' }}>{words.ctl_settings}</div>
                                    <div style={{ display: 'flex', gap: 'var(--s2)' }}>
                                        <button type="button" className="btn" style={{ flex: 1 }} disabled={boutDisabled}
                                                onClick={() => guarded(() => send('medical'))}>
                                            {words.ctl_medical}
                                        </button>
                                        <button type="button" className="btn" id="btnOvertime" style={{ flex: 1 }} disabled={boutDisabled}
                                                onClick={() => setDialog({
                                                    title: words.ctl_overtime,
                                                    fields: [{ name: 'seconds', type: 'text', label: words.ctl_overtime, value: '180' }],
                                                    confirm: (out) => { setDialog(null); send('overtime', { seconds: parseInt(out.seconds, 10) || 180 }); },
                                                })}>
                                            {words.ctl_overtime}
                                        </button>
                                        <button type="button" className="btn" style={{ flex: 1 }} disabled={!loaded}
                                                onClick={() => guarded(() => send('intro'))}>
                                            {words.vs}
                                        </button>
                                    </div>

                                    <div id="endRow">
                                        <button type="button" className="btn danger" id="btnReset" disabled={!loaded}
                                                onClick={() => setDialog({ title: words.ctl_reset, fields: [], confirm: () => { setDialog(null); send('reset'); } })}>
                                            {words.ctl_reset}
                                        </button>
                                        <button type="button" className="btn ok" id="btnCommit" disabled={!loaded || !over}
                                                onClick={() => confirmWithNumber(words.ctl_commit, () => send('commit'))}>
                                            {words.ctl_commit}
                                        </button>
                                    </div>

                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--s3)', marginTop: 'var(--s4)' }}>
                                        <span className="chip" id="screensLine">
                                            {props.screens ? words.ctl_screens.replace(':count', props.screens) : words.ctl_no_screens}
                                        </span>
                                        <button type="button" className="btn" style={{ minHeight: 44 }} onClick={() => send('resync')}>↻</button>
                                        <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--s2)', fontSize: 14 }}>
                                            <input type="checkbox" id="themeToggle" checked={state.theme === 'venue'}
                                                   onChange={(e) => send('theme', { theme: e.target.checked ? 'venue' : 'arena' })} />
                                            {' '}{words.ctl_theme}
                                        </label>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div id="sheetFoot">
                            <button type="button" className="btn" id="sheetClose" style={{ width: '100%' }} onClick={() => setSheet(null)}>
                                {words.ctl_cancel}
                            </button>
                        </div>
                    </div>
                </>
            )}

            <Modal dialog={dialog} words={words} onCancel={() => setDialog(null)} onConfirm={(v) => dialog.confirm(v)} />
            <Toast toast={toast} words={words} onUndo={(id) => { showToast(null, 0); askReverse(id); }} />
        </>
    );
}
