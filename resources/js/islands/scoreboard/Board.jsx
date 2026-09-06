/**
 * Board.jsx — the Brazilian Jiu-Jitsu MAT SCREEN, in React.
 *
 * A straight PORT of `bjj/screen/mat.blade.php` (Design Rule #1: this is not a
 * redesign — every measurement below is the one that document draws, and the
 * two share one stylesheet in bjj/screen/partials/board-styles.blade.php). It
 * renders only when `features.react_scoreboard` is on; the Blade document
 * remains the default and is untouched.
 *
 * WHY REACT IS WORTH IT HERE
 * --------------------------
 * The hand-written renderer patched the DOM node by node from a paint function
 * per layer, and every new fact meant another `text(id, …)` line that somebody
 * had to remember to call. Here the four surfaces are FUNCTIONS OF THE STATE:
 * a message arrives, state is replaced, and React works out what actually
 * changed. Nothing can be left un-repainted because nothing is repainted by
 * hand.
 *
 * It still decides nothing. Every value comes from MatState over MQTT, and the
 * only thing computed on this side is the clock — derived from
 * `remaining`/`running` so two screens on one mat cannot drift apart.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useClock, useCourtBoard, useFitName, useHeartbeat, useStage, useWarning } from './hooks';
import { bgImage, clockWords, flagUrl, joinFacts, matNumber, safeUrl } from './util';

const GOLD = '#fdc436';

/* ── The built-in buzzer ──────────────────────────────────────────────────
   Synthesised rather than fetched, so the clock is never silent. This package
   uploads no sounds; the bell is not decoration, it is what tells a mat the
   match is over. */
let audioContext = null;
function beep(seconds) {
    try {
        audioContext = audioContext || new (window.AudioContext || window.webkitAudioContext)();
        // A wall screen is never touched, so the context can be born suspended.
        if (audioContext.state === 'suspended' && audioContext.resume) audioContext.resume();

        const o = audioContext.createOscillator();
        const g = audioContext.createGain();
        o.type = 'square';
        o.frequency.value = 740;
        o.connect(g);
        g.connect(audioContext.destination);
        g.gain.setValueAtTime(0.25, audioContext.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + seconds);
        o.start();
        o.stop(audioContext.currentTime + seconds);
    } catch (e) { /* a screen with no audio device is not a fault */ }
}

/* ══════════════════════════════════════════════════════════════════════════
   The scoreboard
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * The two counters, as lit cells.
 *
 * Advantages and penalties are LADDERS in this sport, not a running total, so
 * they are drawn as the rungs they are. The number is repeated inside the cell
 * so a photograph of the wall still carries it.
 */
function Cells({ count, max, tone, ink }) {
    return (
        <>
            {Array.from({ length: max }, (_, i) => {
                const on = i < count;
                return (
                    <div
                        key={i}
                        style={{
                            width: 72, height: 68, borderRadius: 10, display: 'flex',
                            alignItems: 'center', justifyContent: 'center',
                            fontFamily: "'Anton',sans-serif", fontSize: 30,
                            background: on ? tone : 'rgba(0,0,0,.28)',
                            color: on ? ink : 'rgba(255,255,255,.45)',
                            border: `2px solid ${on ? 'transparent' : 'rgba(255,255,255,.25)'}`,
                            animation: on ? 'cellIn .4s both' : undefined,
                        }}
                    >
                        {i + 1}
                    </div>
                );
            })}
        </>
    );
}

/** The big points numeral, which pops when it changes and only when it changes. */
function Score({ value, colour, shadow }) {
    const ref = useRef(null);
    const previous = useRef(value);

    useEffect(() => {
        if (previous.current === value) return;
        previous.current = value;
        const node = ref.current;
        if (!node) return;
        node.style.animation = 'none';
        // Force a reflow so re-assigning the same animation replays it.
        void node.offsetWidth;
        node.style.animation = 'scorePop .45s cubic-bezier(.2,.8,.2,1)';
    }, [value]);

    return (
        <div
            ref={ref}
            style={{
                fontFamily: "'Anton',sans-serif", fontSize: 400, lineHeight: .9,
                color: colour, textShadow: shadow, fontVariantNumeric: 'tabular-nums',
            }}
        >
            {value}
        </div>
    );
}

/** One corner of the board. BLUE on the left, WHITE on the right. Always. */
function Corner({ side, competitor, score, rules, words, winning, tbd }) {
    const blue = side === 'blue';
    const c = competitor || {};

    const nameRef = useFitName(c.name || tbd, { max: 120, min: 38, align: blue ? 'left' : 'right' });
    const countryRef = useFitName(c.country || '', { max: 52, min: 26, align: blue ? 'left' : 'right' });

    const flag = safeUrl(flagUrl(c.flag));
    const limit = (rules && rules.penalty_limit) || 4;
    const ink = blue ? '#fff' : '#0a0b10';
    const rowReverse = blue ? undefined : 'row-reverse';

    return (
        <div
            style={{
                flex: 1, minWidth: 0, overflow: 'hidden', position: 'relative',
                display: 'flex', flexDirection: 'column',
                background: blue
                    ? 'linear-gradient(135deg,#1362d1 0%,#082a55 100%)'
                    : 'linear-gradient(225deg,#f4f7fb 0%,#9fadc0 100%)',
                color: ink,
                alignItems: blue ? undefined : 'flex-end',
                textAlign: blue ? undefined : 'right',
                padding: blue ? '44px 30px 30px 56px' : '44px 56px 30px 30px',
                animation: `${blue ? 'introSlideL' : 'introSlideR'} .7s cubic-bezier(.2,.8,.2,1) both`,
            }}
        >
            {winning && (
                <div style={{
                    position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 3,
                    border: `14px solid ${blue ? '#ffd666' : '#b3861f'}`,
                    animation: 'winnerGlow 1.2s ease-in-out infinite',
                }} />
            )}

            <div style={{ display: 'flex', alignItems: 'center', gap: 28, flexDirection: rowReverse }}>
                <img
                    alt=""
                    src={flag || undefined}
                    style={{
                        width: 150, height: 100, objectFit: 'fill', borderRadius: 8,
                        visibility: flag ? 'visible' : 'hidden',
                        boxShadow: `0 8px 30px rgba(0,0,0,${blue ? '.5' : '.35'})`,
                    }}
                />
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: blue ? undefined : 'flex-end' }}>
                    <div
                        ref={countryRef}
                        style={{
                            maxWidth: 690, fontSize: 52, fontWeight: 700, letterSpacing: '.1em',
                            textTransform: 'uppercase', lineHeight: 1, whiteSpace: 'nowrap',
                        }}
                    >
                        {c.country || ''}
                    </div>
                    <div style={{
                        fontSize: 34, fontWeight: 600, letterSpacing: '.3em', marginTop: 6,
                        color: blue ? 'rgba(255,255,255,.65)' : 'rgba(10,11,16,.6)',
                    }}>
                        {blue ? words.corner_blue : words.corner_white}
                    </div>
                </div>
            </div>

            <div
                ref={nameRef}
                style={{
                    maxWidth: '100%', fontFamily: "'Anton',sans-serif", fontSize: 120, lineHeight: 1,
                    textTransform: 'uppercase', marginTop: 36, paddingBottom: 14, whiteSpace: 'nowrap',
                    textShadow: blue ? '0 6px 30px rgba(0,0,0,.4)' : '0 6px 30px rgba(255,255,255,.35)',
                }}
            >
                {c.name || tbd}
            </div>

            <div style={{
                fontSize: 42, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
                marginTop: 10, color: blue ? 'rgba(255,255,255,.8)' : 'rgba(10,11,16,.72)',
            }}>
                {c.club || ''}
            </div>

            {/* POINTS, and only points. The other two ladders are counted
                separately below: adding them together would be a different sport. */}
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', alignSelf: 'stretch' }}>
                <Score
                    value={score.points}
                    colour={blue ? '#fff' : '#0a0b10'}
                    shadow={blue ? '0 10px 60px rgba(0,0,0,.5)' : '0 10px 60px rgba(255,255,255,.4)'}
                />
            </div>

            <div style={{
                display: 'flex', flexDirection: 'column', gap: 12, minHeight: 180,
                justifyContent: 'flex-end', alignItems: blue ? undefined : 'flex-end',
            }}>
                {[
                    { label: words.adv_short, count: score.advantages, max: 3, tone: blue ? '#ffd666' : '#b3861f', ink: blue ? '#000' : '#fff' },
                    { label: words.pen_short, count: score.penalties, max: limit, tone: blue ? '#ff3b47' : '#c81f2a', ink: '#fff' },
                ].map((row) => (
                    <div key={row.label} style={{ display: 'flex', alignItems: 'center', gap: 20, flexDirection: rowReverse }}>
                        <div style={{
                            width: 96, fontSize: 26, fontWeight: 700, letterSpacing: '.2em',
                            textTransform: 'uppercase', textAlign: blue ? undefined : 'right',
                            color: blue ? 'rgba(255,255,255,.7)' : 'rgba(10,11,16,.65)',
                        }}>
                            {row.label}
                        </div>
                        <div style={{ display: 'flex', gap: 10, flexDirection: rowReverse }}>
                            <Cells count={row.count} max={row.max} tone={row.tone} ink={row.ink} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * The clock column: the division, the time, the bar and the status WORD.
 *
 * Its own component so the second-by-second tick re-renders 40 lines of DOM
 * rather than the whole board.
 */
function ClockColumn({ state, receivedAt, words }) {
    const { words: display, remaining } = useClock(state, receivedAt);
    const warn = useWarning(state, receivedAt);
    const told = useRef(!!state.finished);

    // The bell, once per match. The board reaches zero on its OWN clock and the
    // hall should hear it then, not when the server gets round to saying so.
    useEffect(() => { told.current = false; }, [state.matchId]);
    useEffect(() => {
        if (!state.running) return undefined;
        let frame = 0;
        const watch = () => {
            if (remaining() <= 0 && !told.current) {
                told.current = true;
                if (!state.rules || state.rules.time_up_buzzer !== false) beep(1.1);
            }
            frame = requestAnimationFrame(watch);
        };
        frame = requestAnimationFrame(watch);
        return () => cancelAnimationFrame(frame);
    }, [state.running, state.rules, remaining]);

    const division = state.division || '';
    const divisionSize = division.length <= 9 ? 84 : division.length <= 14 ? 64 : division.length <= 22 ? 46 : 34;
    const pct = state.duration ? Math.max(0, Math.min(100, (remaining() / state.duration) * 100)) : 0;

    return (
        <div style={{
            width: 470, background: '#0a0b10', display: 'flex', flexDirection: 'column',
            alignItems: 'center', justifyContent: 'center', gap: 20, position: 'relative',
            zIndex: 2, boxShadow: '0 0 80px rgba(0,0,0,.8)',
        }}>
            {/* The division sits in the column everybody is already watching: it
                is the single fact a coach or a spectator looks for first. */}
            <div style={{
                fontFamily: "'Anton',sans-serif", fontSize: divisionSize, lineHeight: 1,
                letterSpacing: '.06em', color: '#ffd666', textTransform: 'uppercase',
                whiteSpace: 'nowrap', maxWidth: 440, textAlign: 'center',
            }}>
                {division}
            </div>

            <div style={{
                fontFamily: "'Anton',sans-serif", fontSize: 200, lineHeight: 1, letterSpacing: '.02em',
                fontVariantNumeric: 'tabular-nums', color: warn ? undefined : '#fff',
                animation: warn ? 'timerPulse 1s ease-in-out infinite' : 'none',
            }}>
                {display}
            </div>

            <div style={{ width: 340, height: 10, background: 'rgba(255,255,255,.1)', borderRadius: 5, overflow: 'hidden' }}>
                <div style={{
                    height: '100%', width: `${pct}%`, borderRadius: 5,
                    background: warn ? '#ff3b47' : '#ffd666',
                    transition: 'width .12s linear, background .3s',
                }} />
            </div>

            {/* The status line always carries a WORD: a colour on its own never
                says anything on this screen. */}
            <div style={{
                fontSize: 44, fontWeight: 700, letterSpacing: '.34em', textTransform: 'uppercase',
                marginTop: 12, textAlign: 'center', lineHeight: 1.1,
                color: warn ? '#ff6b78' : '#ffd666',
            }}>
                {warn ? words.status_warning : (words.statuses[state.status] || words.statuses.idle)}
            </div>

            <div style={{ fontSize: 24, fontWeight: 600, letterSpacing: '.28em', color: '#7d8296', textTransform: 'uppercase' }}>
                {state.status === 'overtime' ? words.overtime_time : words.regulation_time}
            </div>
        </div>
    );
}

/** The final minute bleeds red from the edges. */
function LowVignette({ state, receivedAt }) {
    const warn = useWarning(state, receivedAt);
    if (!warn) return null;
    return (
        <div style={{
            position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 5,
            background: 'radial-gradient(ellipse at center, transparent 52%, rgba(255,59,71,.55) 100%)',
            animation: 'vignettePulse 1s ease-in-out infinite',
        }} />
    );
}

/**
 * The callout: the ACTION, once, on the scoring side.
 *
 * In jiu-jitsu the number does not name the action — two points is a takedown,
 * a sweep or a knee-on-belly — so the hall is told which one it was, with the
 * value under it.
 */
function Callout({ event, words }) {
    if (!event) return null;

    const blue = event.side === 'blue';
    const colour = blue ? '#4d9aff' : '#e6ebf2';
    const label = (event.kind === 'advantage' ? words.advantages : (words.sources[event.source] || '')).toUpperCase();

    return (
        <div style={{ position: 'absolute', zIndex: 9, pointerEvents: 'none', left: blue ? '25%' : '75%', top: '44%' }}>
            <div style={{
                position: 'absolute', left: 0, top: 0, width: 380, height: 380, borderRadius: '50%',
                border: `18px solid ${colour}`, transform: 'translate(-50%,-50%)',
                animation: 'ringBurst .9s cubic-bezier(.2,.8,.2,1) both',
            }} />
            <div style={{
                position: 'absolute', left: 0, top: 0, transform: 'translate(-50%,-50%)',
                animation: 'calloutIn 1.5s cubic-bezier(.2,.8,.2,1) both',
                display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4,
            }}>
                <div style={{
                    fontFamily: "'Anton',sans-serif", fontSize: 104, lineHeight: 1, color: '#fff',
                    whiteSpace: 'nowrap', textShadow: `0 0 80px ${colour}, 0 8px 30px rgba(0,0,0,.7)`,
                }}>
                    {label}
                </div>
                {event.kind === 'point' && (
                    <div style={{
                        fontFamily: "'Anton',sans-serif", fontSize: 72, color: '#ffd666',
                        textShadow: '0 0 40px rgba(255,214,102,.7)',
                    }}>
                        {`+${event.value || 0}`}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * The penalty notice: which corner, what for, and at what time.
 *
 * A penalty is the one thing the hall is TOLD in words rather than shown as a
 * number. There is no public countdown and no stalling row — the referee's own
 * count is private until they decide to give something.
 */
function PenaltyNotice({ text }) {
    if (!text) return null;
    return (
        <div style={{
            position: 'absolute', left: '50%', bottom: 52, transform: 'translateX(-50%)', zIndex: 10,
            padding: '16px 44px', background: 'rgba(10,10,14,.9)', border: '2px solid #ff3b47',
            color: '#fff', fontSize: 38, fontWeight: 700, letterSpacing: '.14em',
            textTransform: 'uppercase', whiteSpace: 'nowrap', animation: 'noticeIn 4.2s ease-out both',
        }}>
            {text}
        </div>
    );
}

/**
 * The hold card: paused, under review, or with the doctor on the mat.
 *
 * A level match at 0:00 waiting on a referee decision holds here too, rather
 * than the wall inventing a result — IBJJF sends that to the referee, and until
 * they answer the honest thing to show is that it is not over.
 */
function Hold({ state, receivedAt, words }) {
    const { words: display } = useClock(state, receivedAt);
    const score = state.score || {};

    return (
        <div style={{
            position: 'absolute', inset: 0, zIndex: 7, pointerEvents: 'none',
            background: 'rgba(5,5,7,.72)', display: 'flex', flexDirection: 'column',
            alignItems: 'center', justifyContent: 'center', gap: 26,
        }}>
            <div style={{
                fontFamily: "'Anton',sans-serif", fontSize: 150, lineHeight: 1, letterSpacing: '.08em',
                textTransform: 'uppercase', color: '#ffd666',
            }}>
                {words.statuses[state.status] || words.statuses.paused}
            </div>
            <div style={{ height: 3, width: 520, background: 'linear-gradient(to right, transparent, #ffd666, transparent)' }} />
            <div style={{
                fontFamily: "'Anton',sans-serif", fontSize: 120, lineHeight: 1,
                fontVariantNumeric: 'tabular-nums', color: 'rgba(255,255,255,.85)',
            }}>
                {display}
            </div>
            <div style={{ fontSize: 40, fontWeight: 600, letterSpacing: '.24em', textTransform: 'uppercase', color: 'rgba(232,230,224,.75)' }}>
                {`${words.corner_blue} ${score.bluePoints || 0}  —  ${score.whitePoints || 0} ${words.corner_white}`}
            </div>
            <div style={{ fontSize: 30, fontWeight: 600, letterSpacing: '.3em', textTransform: 'uppercase', color: 'rgba(232,230,224,.45)' }}>
                {words.please_wait}
            </div>
        </div>
    );
}

/**
 * The celebration — this package's own scene, painted imperatively.
 *
 * Deliberately NOT ported to JSX: it is a self-contained standalone component
 * with its own faces, keyframes and deterministic confetti sky, shared with the
 * Blade path. React owns WHEN it is on screen and WHO won; the scene owns
 * itself (CLAUDE.md → Standalone Self-Contained Components).
 */
function Winner({ state, words }) {
    const hostRef = useRef(null);
    const side = state.winner;
    const competitor = (side === 'blue' ? state.blue : state.white) || {};
    const score = state.score || {};

    useEffect(() => {
        const host = hostRef.current;
        if (!host || !window.WinnerCelebration) return undefined;

        // WHY they won, in words. A declared method answers it for a submission,
        // a disqualification or a walkover; `decidedBy` answers it for a match
        // settled on the numbers — which of the three counters broke the tie.
        const method = state.declared ? (words.methods[state.winMethod] || '') : null;
        const note = method
            ? words.won_by.replace(':method', method)
            : (words.decided[score.decidedBy] || words.decided.points || '');

        window.WinnerCelebration.paint(host, {
            corner: side === 'blue' ? 'blue' : 'white',
            name: competitor.name || '',
            club: competitor.club || '',
            logo: competitor.logo || null,
            photo: competitor.photo || null,
            label: words.winner,
            note: (note || '').toUpperCase(),
        });

        return () => { if (window.WinnerCelebration) window.WinnerCelebration.clear(host); };
    }, [side, competitor.name, competitor.club, competitor.logo, competitor.photo,
        state.declared, state.winMethod, score.decidedBy, words]);

    return (
        <div
            ref={hostRef}
            style={{ position: 'absolute', inset: 0, zIndex: 8, pointerEvents: 'none', background: 'rgba(0,0,0,.45)', overflow: 'hidden' }}
        />
    );
}

function Scoreboard({ state, receivedAt, words, callout, notice, eventTitle, eventLogo, court }) {
    const score = state.score || {};
    const finished = !!state.finished;
    const waiting = !finished && (state.awaitingDecision
        || state.status === 'review' || state.status === 'medical' || state.status === 'paused');
    const celebrating = finished && state.winner && !state.celebrationClosed;

    return (
        <div className="layer" style={{ background: '#000', display: 'flex', flexDirection: 'column' }}>
            <div style={{
                height: 110, background: '#0a0b10', display: 'flex', alignItems: 'center',
                justifyContent: 'space-between', padding: '0 48px', borderBottom: '3px solid #1c1e28',
                animation: 'introDrop .6s cubic-bezier(.2,.8,.2,1) both',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 26 }}>
                    {/* The design ships a dashed placeholder; a real crest replaces it. */}
                    <div style={{
                        width: 72, height: 72, borderRadius: 10, display: 'flex', alignItems: 'center',
                        justifyContent: 'center', fontFamily: 'monospace', fontSize: 12, color: '#7d8296',
                        textAlign: 'center', lineHeight: 1.2, backgroundSize: 'cover', backgroundPosition: 'center',
                        border: eventLogo ? 'none' : '2px dashed rgba(255,255,255,.3)',
                        backgroundImage: bgImage(eventLogo),
                    }}>
                        {eventLogo ? '' : 'event logo'}
                    </div>
                    <div style={{ fontSize: 44, fontWeight: 700, letterSpacing: '.05em', textTransform: 'uppercase' }}>
                        {state.tournament || eventTitle}
                    </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 36, fontSize: 36, fontWeight: 700, textTransform: 'uppercase' }}>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
                        <span style={{ color: '#7d8296', fontSize: 26, letterSpacing: '.18em' }}>{words.court_match}</span>
                        <span style={{ fontFamily: "'Anton',sans-serif" }}>{state.matchNo || ''}</span>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
                        <span style={{ color: '#7d8296', fontSize: 26, letterSpacing: '.18em' }}>{words.court_court}</span>
                        <span style={{ fontFamily: "'Anton',sans-serif" }}>{matNumber(state.courtLabel || court)}</span>
                    </div>
                    <div style={{ background: '#ffd666', color: '#000', padding: '6px 24px', borderRadius: 6, fontSize: 32, letterSpacing: '.1em' }}>
                        {state.stage || ''}
                    </div>
                </div>
            </div>

            <div style={{ flex: 1, display: 'flex', position: 'relative' }}>
                <Corner
                    side="blue" competitor={state.blue} rules={state.rules} words={words} tbd={words.tbd}
                    winning={finished && state.winner === 'blue'}
                    score={{ points: score.bluePoints || 0, advantages: score.blueAdvantages || 0, penalties: score.bluePenalties || 0 }}
                />
                <ClockColumn state={state} receivedAt={receivedAt} words={words} />
                <Corner
                    side="white" competitor={state.white} rules={state.rules} words={words} tbd={words.tbd}
                    winning={finished && state.winner === 'white'}
                    score={{ points: score.whitePoints || 0, advantages: score.whiteAdvantages || 0, penalties: score.whitePenalties || 0 }}
                />
            </div>

            <LowVignette state={state} receivedAt={receivedAt} />
            <Callout event={callout} words={words} />
            <PenaltyNotice text={notice} />
            {waiting && <Hold state={state} receivedAt={receivedAt} words={words} />}
            {celebrating && <Winner state={state} words={words} />}
        </div>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The introduction
   ══════════════════════════════════════════════════════════════════════════ */

function VsChip({ value, gold }) {
    if (!value) return null;   // an absent fact is hidden, never invented
    return (
        <div style={{
            fontWeight: 700, fontSize: 23.8, letterSpacing: '0.12em', textTransform: 'uppercase',
            padding: '5.4px 14px', background: 'rgba(10,10,14,0.62)',
            border: `1px solid ${gold ? 'rgba(253,196,54,0.6)' : 'rgba(255,255,255,0.25)'}`,
            color: gold ? '#e8c96a' : '#fff',
        }}>
            {value}
        </div>
    );
}

function VsSide({ side, competitor, words, tbd }) {
    const blue = side === 'blue';
    const c = competitor || {};
    const nameRef = useFitName(c.name || tbd, { max: 71.3, min: 26, align: blue ? 'left' : 'right' });
    const countryRef = useFitName(c.country || '', { max: 32.4, min: 18, align: blue ? 'left' : 'right' });

    // "24Y · 178cm · 74kg" — each part only when we actually have it.
    const stats = joinFacts([c.age ? `${c.age}Y` : null, c.height ? `${c.height}cm` : null, c.weight ? `${c.weight}kg` : null]);

    return (
        <div style={{
            position: 'absolute', bottom: 118.8, zIndex: 6, maxWidth: '44%', minWidth: 0,
            left: blue ? 43.2 : undefined, right: blue ? undefined : 43.2,
            display: 'flex', flexDirection: 'column', gap: 13,
            alignItems: blue ? 'flex-start' : 'flex-end',
            textAlign: blue ? undefined : 'right',
            animation: `riseUp 0.8s ${blue ? '0.7s' : '0.85s'} cubic-bezier(0.22,1,0.36,1) both`,
        }}>
            <div style={{
                fontWeight: 800, fontSize: 21.6, letterSpacing: '0.35em', padding: '5.4px 15.1px 5.4px 18.9px',
                color: blue ? '#fff' : '#0a0b10', background: blue ? '#1362d1' : '#e6ebf2',
            }}>
                {blue ? words.corner_blue : words.corner_white}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 15.1, flexDirection: blue ? undefined : 'row-reverse' }}>
                <div style={{
                    width: 56.2, aspectRatio: '4/3', backgroundSize: '100% 100%', backgroundPosition: 'center',
                    border: '1px solid rgba(255,255,255,0.35)', boxShadow: '0 4px 18px rgba(0,0,0,0.6)',
                    backgroundImage: bgImage(flagUrl(c.flag)),
                }} />
                <div ref={countryRef} style={{
                    maxWidth: 760, fontWeight: 700, fontSize: 32.4, letterSpacing: '0.28em',
                    textTransform: 'uppercase', whiteSpace: 'nowrap', color: blue ? '#a9c6ea' : '#dfe6ef',
                }}>
                    {c.country || ''}
                </div>
            </div>

            <div ref={nameRef} style={{
                maxWidth: '100%', fontFamily: "'Anton',sans-serif", fontSize: 71.3, lineHeight: 0.95,
                textTransform: 'uppercase', color: '#fff', whiteSpace: 'nowrap',
                textShadow: '0 6px 30px rgba(0,0,0,0.8)',
            }}>
                {c.name || tbd}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 13, marginTop: 4.3, flexDirection: blue ? undefined : 'row-reverse' }}>
                <div style={{
                    width: 69.1, height: 69.1, borderRadius: '50%', background: 'rgba(255,255,255,0.06)',
                    border: '1px solid rgba(255,255,255,0.2)', backgroundSize: 'cover', backgroundPosition: 'center',
                    backgroundImage: bgImage(c.logo),
                }} />
                <div style={{ fontWeight: 600, fontSize: 30.2, letterSpacing: '0.12em', textTransform: 'uppercase', color: 'rgba(232,230,224,0.9)' }}>
                    {c.club || ''}
                </div>
            </div>

            <div style={{
                display: 'flex', flexWrap: 'wrap', gap: 9.7, marginTop: 5.4,
                justifyContent: blue ? undefined : 'flex-end',
            }}>
                <VsChip value={c.belt} gold />
                <VsChip value={stats} />
            </div>
        </div>
    );
}

function Vs({ state, words, eventTitle, court, exiting }) {
    const b = state.blue || {};
    const w = state.white || {};

    const face = (c) => c.photo || c.fallback;
    const photoStyle = (c, reverse) => ({
        position: 'absolute', inset: 0, backgroundSize: 'cover', backgroundPosition: 'center 12%',
        backgroundImage: bgImage(face(c)),
        // The stand-in sits well back: this panel is two metres of wall and a
        // drawing at full strength would read as a photograph of the athlete.
        opacity: face(c) && !c.photo ? 0.45 : 1,
        animation: `slowDrift 18s ease-in-out infinite${reverse ? ' reverse' : ''}`,
    });

    return (
        <div
            className="layer"
            style={{
                zIndex: 12, overflow: 'hidden', color: '#e8e6e0', fontFamily: "'Barlow Condensed',sans-serif",
                background: 'radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%)',
                animation: exiting ? 'vsExit .55s cubic-bezier(.4,0,1,1) both' : undefined,
            }}
        >
            <div style={{
                position: 'absolute', inset: '0 auto 0 0', width: '56%', overflow: 'hidden', background: '#10233f',
                clipPath: 'polygon(0 0, 100% 0, 82% 100%, 0 100%)',
                animation: 'panelL 0.9s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                <div style={photoStyle(b, false)} />
                <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', background: 'linear-gradient(115deg, rgba(40,86,152,0.55) 0%, transparent 55%)' }} />
                <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', background: 'linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%)' }} />
            </div>

            <div style={{
                position: 'absolute', inset: '0 0 0 auto', width: '56%', overflow: 'hidden', background: '#2c3340',
                clipPath: 'polygon(18% 0, 100% 0, 100% 100%, 0 100%)',
                animation: 'panelR 0.9s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                <div style={photoStyle(w, true)} />
                <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', background: 'linear-gradient(245deg, rgba(226,232,240,0.42) 0%, transparent 55%)' }} />
                <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', background: 'linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%)' }} />
            </div>

            <div style={{
                position: 'absolute', top: '-6%', bottom: '-6%', left: '50%', width: 3, marginLeft: -1.5,
                transform: 'rotate(10.15deg)', pointerEvents: 'none', filter: 'blur(1px)',
                background: 'linear-gradient(to bottom, transparent, rgba(253,196,54,0.9) 20%, rgba(253,196,54,0.9) 80%, transparent)',
            }} />

            <VsSide side="blue" competitor={b} words={words} tbd={words.tbd} />
            <VsSide side="white" competitor={w} words={words} tbd={words.tbd} />

            <div style={{
                position: 'absolute', top: 34.6, left: '50%', transform: 'translateX(-50%)', zIndex: 8,
                width: '92%', pointerEvents: 'none', display: 'flex', flexDirection: 'column',
                alignItems: 'center', gap: 10.8, animation: 'dropIn 0.8s 0.5s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                <div style={{
                    fontWeight: 700, fontSize: 30.2, letterSpacing: '0.42em', textTransform: 'uppercase',
                    color: 'rgba(232,230,224,0.92)', textAlign: 'center', textShadow: '0 2px 14px rgba(0,0,0,0.9)',
                }}>
                    {state.tournament || eventTitle}
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 17.3 }}>
                    <div style={{ height: 2, width: 64.8, background: `linear-gradient(to left, ${GOLD}, transparent)` }} />
                    <div style={{
                        fontFamily: "'Anton',sans-serif", fontSize: 38.9, letterSpacing: '0.3em',
                        paddingLeft: '0.3em', color: GOLD, textTransform: 'uppercase',
                    }}>
                        {state.stage || ''}
                    </div>
                    <div style={{ height: 2, width: 64.8, background: `linear-gradient(to right, ${GOLD}, transparent)` }} />
                </div>
                <div style={{ fontWeight: 600, fontSize: 28.1, letterSpacing: '0.3em', textTransform: 'uppercase', color: 'rgba(232,230,224,0.75)' }}>
                    {state.division || ''}
                </div>
            </div>

            <div style={{
                position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%,-52%)', zIndex: 7,
                pointerEvents: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center',
                animation: 'vsSlam 0.7s 1.1s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                <div style={{
                    position: 'relative', fontFamily: "'Anton',sans-serif", fontSize: 183.6, fontStyle: 'italic',
                    color: '#fffdf5', lineHeight: 1, WebkitTextStroke: '2px rgba(253,196,54,0.6)', overflow: 'visible',
                    animation: 'vsPulse 2.4s ease-in-out infinite',
                }}>
                    {words.vs}
                    <div style={{ position: 'absolute', inset: '-10% -20%', overflow: 'hidden', pointerEvents: 'none' }}>
                        <div style={{
                            position: 'absolute', top: 0, bottom: 0, width: '34%',
                            background: 'linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent)',
                            animation: 'shineSweep 5s ease-in-out infinite',
                        }} />
                    </div>
                </div>
            </div>

            <div style={{ position: 'absolute', inset: 0, background: '#fff', opacity: 0, pointerEvents: 'none', zIndex: 9, animation: 'flashOut 0.9s 1.5s ease-out both' }} />

            <div style={{
                position: 'absolute', bottom: 32.4, left: '50%', transform: 'translateX(-50%)', zIndex: 8,
                display: 'flex', gap: 17.3, alignItems: 'center', flexWrap: 'wrap', justifyContent: 'center',
                maxWidth: '94%', animation: 'riseC 0.8s 1.3s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                {[
                    { label: words.court_match, value: state.matchNo || '', anton: true },
                    { label: words.court_court, value: matNumber(state.courtLabel || court), anton: true },
                    { label: words.vs_referee, value: state.referee || '', anton: false, hide: !state.referee },
                ].map((chip, i) => (chip.hide ? null : (
                    <React.Fragment key={chip.label}>
                        {i > 0 && <div style={{ width: 6, height: 6, transform: 'rotate(45deg)', background: GOLD }} />}
                        <div style={{
                            display: 'flex', alignItems: 'baseline', gap: 8.6, background: 'rgba(10,10,14,0.72)',
                            border: '1px solid rgba(253,196,54,0.45)', padding: '10.8px 23.8px', backdropFilter: 'blur(6px)',
                        }}>
                            <span style={{ fontWeight: 600, fontSize: 23.8, letterSpacing: '0.3em', color: 'rgba(232,230,224,0.65)', textTransform: 'uppercase' }}>
                                {chip.label}
                            </span>
                            <span style={chip.anton
                                ? { fontFamily: "'Anton',sans-serif", fontSize: 34.6, color: '#fff' }
                                : { fontWeight: 700, fontSize: 28.1, letterSpacing: '0.08em', color: '#fff', textTransform: 'uppercase' }}>
                                {chip.value}
                            </span>
                        </div>
                    </React.Fragment>
                )))}
            </div>
        </div>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The running order
   ══════════════════════════════════════════════════════════════════════════ */

/** One corner half — photo, name, flag, crest, club. Absent facts HIDE. */
function QueueHalf({ match, corner, tbd }) {
    const blue = corner === 'blue';
    const photo = safeUrl(match[`${corner}Photo`]);
    const flag = safeUrl(flagUrl(match[`${corner}Flag`]));
    const crest = safeUrl(match[`${corner}Logo`]);
    const club = (match[`${corner}Club`] || '').trim();

    return (
        <div style={{
            flex: 1, minWidth: 0, display: 'flex', alignItems: 'stretch',
            ...(blue ? {
                background: 'linear-gradient(90deg, #1362d1 0%, #08284f 100%)',
                clipPath: 'polygon(0 0, 100% 0, calc(100% - 60px) 100%, 0 100%)',
                marginRight: -30, color: '#fff',
            } : {
                flexDirection: 'row-reverse',
                background: 'linear-gradient(270deg, #eef2f7 0%, #93a1b3 100%)',
                clipPath: 'polygon(60px 0, 100% 0, 100% 100%, 0 100%)',
                marginLeft: -30, color: '#0a0b10',
            }),
        }}>
            {photo && (
                <div style={{
                    width: 230, flex: '0 0 auto', backgroundColor: blue ? '#0e3f80' : '#7f8b9c',
                    backgroundSize: 'cover', backgroundPosition: 'center 12%', backgroundImage: bgImage(photo),
                }} />
            )}

            <div style={{
                flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 6,
                ...(blue ? { padding: '10px 60px 10px 26px' }
                    : { alignItems: 'flex-end', padding: '10px 26px 10px 60px', textAlign: 'right' }),
            }}>
                {/* The one field that never hides: a nameless half looks broken
                    from ten metres, so an undrawn slot says so instead. */}
                <div style={{
                    fontFamily: "'Anton',sans-serif", fontSize: 56, lineHeight: 1, textTransform: 'uppercase',
                    whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                }}>
                    {match[`${corner}Name`] || tbd}
                </div>

                {(flag || crest || club) && (
                    <div style={{
                        display: 'flex', alignItems: 'center', gap: 12, maxWidth: '100%',
                        flexDirection: blue ? undefined : 'row-reverse',
                    }}>
                        {/* Stretched to fill the box — flags run 1.43:1 to 2:1 while
                            this plate is 4:3, and stretch is the only option that
                            keeps every flag WHOLE. */}
                        {flag && (
                            <div style={{
                                width: 76, flex: '0 0 auto', aspectRatio: '4/3', backgroundColor: 'rgba(0,0,0,0.25)',
                                backgroundSize: '100% 100%', backgroundRepeat: 'no-repeat', backgroundPosition: 'center',
                                border: '1px solid rgba(255,255,255,0.4)', backgroundImage: bgImage(flag),
                            }} />
                        )}
                        {crest && (
                            <div style={{
                                width: 66, height: 66, flex: '0 0 auto', borderRadius: '50%',
                                backgroundColor: 'rgba(255,255,255,0.12)', backgroundSize: 'cover',
                                backgroundPosition: 'center', border: '1px solid rgba(255,255,255,0.35)',
                                backgroundImage: bgImage(crest),
                            }} />
                        )}
                        {club && (
                            <div style={{
                                minWidth: 0, fontWeight: 600, fontSize: 30, letterSpacing: '0.06em',
                                textTransform: 'uppercase', opacity: .85, whiteSpace: 'nowrap',
                                overflow: 'hidden', textOverflow: 'ellipsis',
                            }}>
                                {club}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

/** The centre plate: match number, round, division — gold when it is next. */
function QueuePlate({ match, isNext, words, enter, index }) {
    const ink = isNext ? '#141210' : '#fff';
    const accent = isNext ? '#141210' : GOLD;
    const muted = isNext ? 'rgba(20,18,16,0.65)' : 'rgba(232,230,224,0.65)';

    return (
        <div style={{
            position: 'absolute', left: '50%', top: '50%', transform: 'translate(-50%,-50%)',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2,
            background: isNext ? GOLD : '#101016',
            border: `2px solid ${isNext ? '#fffdf0' : 'rgba(255,255,255,0.3)'}`,
            padding: '10px 26px 12px', minWidth: 220, boxShadow: '0 0 30px rgba(0,0,0,0.7)', zIndex: 3,
            animation: enter ? `platePop 0.55s ${0.55 + index * 0.15}s cubic-bezier(0.22,1,0.36,1) both` : undefined,
        }}>
            {isNext && (
                // GET READY breathes by STRETCHING its word, not by animating
                // letter-spacing — tracking is a layout property.
                <div style={{
                    fontWeight: 800, fontSize: 26, letterSpacing: '0.2em', textTransform: 'uppercase',
                    background: '#141210', color: GOLD, padding: '3px 18px 3px 20px', marginBottom: 2, overflow: 'hidden',
                }}>
                    <span style={{ display: 'inline-block', animation: 'readyBreath 1.6s ease-in-out infinite' }}>
                        {words.get_ready}
                    </span>
                </div>
            )}

            {/* The label is only meaningful next to a number, so the whole line
                steps aside for a match that has not been given one yet. */}
            {match.number && (
                <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                    <span style={{ fontWeight: 600, fontSize: 26, letterSpacing: '0.24em', textTransform: 'uppercase', color: muted }}>
                        {words.court_match}
                    </span>
                    <span style={{
                        fontFamily: "'Anton',sans-serif", fontSize: 54, lineHeight: 1, color: accent,
                        animation: isNext ? 'numBeat 1.6s ease-in-out infinite' : undefined,
                    }}>
                        {match.number}
                    </span>
                </div>
            )}

            {match.stage && (
                <div style={{ fontWeight: 800, fontSize: 30, letterSpacing: '0.16em', textTransform: 'uppercase', color: ink }}>
                    {match.stage}
                </div>
            )}
            {match.weightClass && (
                <div style={{ fontWeight: 600, fontSize: 26, letterSpacing: '0.14em', textTransform: 'uppercase', color: muted }}>
                    {match.weightClass}
                </div>
            )}
        </div>
    );
}

/**
 * One match row.
 *
 * `enter` is false for a match that was already on screen before this update:
 * when one finishes the rows shift up, and re-playing the entrance on all four
 * would read as the whole board flinching.
 */
function QueueRow({ match, index, isNext, enter, words, tbd }) {
    return (
        <div style={{
            flex: 1, minHeight: 0, position: 'relative', display: 'flex', alignItems: 'stretch',
            background: '#101016',
            border: `1px solid ${isNext ? 'rgba(253,196,54,0.8)' : 'rgba(255,255,255,0.15)'}`,
            boxShadow: isNext ? '0 0 16px rgba(253,196,54,0.35), 0 0 44px rgba(253,196,54,0.15)' : undefined,
            animation: enter
                ? `${index % 2 === 0 ? 'rowEnterL' : 'rowEnterR'} 0.7s ${0.2 + index * 0.15}s cubic-bezier(0.22,1,0.36,1) both`
                : undefined,
        }}>
            <div style={{ position: 'absolute', inset: 0, overflow: 'hidden', pointerEvents: 'none', zIndex: 2 }}>
                <div style={{
                    position: 'absolute', top: 0, bottom: 0, width: '26%',
                    background: 'linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent)',
                    animation: `rowSweep 4.5s ${1.2 + index * 0.55}s ease-in-out infinite`,
                }} />
            </div>

            {isNext && (
                <>
                    {/* The breath: the strong shadow rasterised ONCE and faded in
                        and out, rather than a 110px blur re-drawn every frame. */}
                    <div style={{
                        position: 'absolute', inset: 0, zIndex: -1, pointerEvents: 'none', opacity: 0,
                        boxShadow: '0 0 36px rgba(253,196,54,0.8), 0 0 110px rgba(253,196,54,0.35)',
                        animation: 'glowPulse 2.2s 1.2s ease-in-out infinite',
                    }} />
                    {/* The running border, as four thin strips rather than one
                        masked box: a mask forces a render surface. */}
                    <div style={{ position: 'absolute', inset: -2, pointerEvents: 'none', zIndex: 2 }}>
                        {['top', 'bottom'].map((edge) => (
                            <div key={edge} style={{ position: 'absolute', left: 0, right: 0, [edge]: 0, height: 3, overflow: 'hidden' }}>
                                <div style={{
                                    position: 'absolute', top: 0, bottom: 0, left: 0, width: '300%',
                                    background: `linear-gradient(90deg, ${GOLD}, #fffdf0 25%, ${GOLD} 50%, #6b5310 75%, ${GOLD})`,
                                    animation: 'goldSlide 2.5s linear infinite',
                                }} />
                            </div>
                        ))}
                        {['left', 'right'].map((edge) => (
                            <div key={edge} style={{ position: 'absolute', top: 0, bottom: 0, [edge]: 0, width: 3, background: GOLD }} />
                        ))}
                    </div>
                </>
            )}

            <QueueHalf match={match} corner="blue" tbd={tbd} />
            <QueueHalf match={match} corner="white" tbd={tbd} />
            <QueuePlate match={match} isNext={isNext} words={words} enter={enter} index={index} />
        </div>
    );
}

function Queue({ board, words, eventTitle, court, rows }) {
    const matches = Array.isArray(board.matches) ? board.matches.slice(0, rows) : [];
    const keyOf = (m) => `${m.number}|${m.blueName || ''}|${m.whiteName || ''}`;

    // Only genuinely NEW rows fly in — see QueueRow.
    const seen = useRef(new Set());
    const first = useRef(true);
    const entering = matches.map((m) => first.current || !seen.current.has(keyOf(m)));

    useEffect(() => {
        seen.current = new Set(matches.map(keyOf));
        first.current = false;
    });

    return (
        <div className="layer" style={{
            background: 'radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%)',
            display: 'flex', flexDirection: 'column',
        }}>
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 30,
                padding: '34px 60px 24px', animation: 'headerIn 0.8s cubic-bezier(0.22,1,0.36,1) both',
            }}>
                <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 18 }}>
                    <div style={{ width: 10, height: 64, background: '#1677FF' }} />
                    <div style={{
                        fontWeight: 700, fontSize: 34, letterSpacing: '0.24em', textTransform: 'uppercase',
                        color: 'rgba(232,230,224,0.85)', maxWidth: 520,
                    }}>
                        {(board.event && board.event.title) || eventTitle}
                    </div>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4 }}>
                    <div style={{
                        fontFamily: "'Anton',sans-serif", fontSize: 64, letterSpacing: '0.2em', paddingLeft: '0.2em',
                        textTransform: 'uppercase', whiteSpace: 'nowrap',
                        background: `linear-gradient(100deg, ${GOLD} 40%, #fffdf0 50%, ${GOLD} 60%)`,
                        backgroundSize: '200% 100%', WebkitBackgroundClip: 'text', backgroundClip: 'text',
                        WebkitTextFillColor: 'transparent', animation: 'titleShimmer 3.5s linear infinite',
                    }}>
                        {words.court_title}
                    </div>
                    <div style={{ height: 3, width: '100%', background: `linear-gradient(to right, transparent, ${GOLD}, transparent)` }} />
                </div>

                <div style={{ flex: 1, display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: 18 }}>
                    <div style={{
                        display: 'flex', alignItems: 'baseline', gap: 12, background: 'rgba(10,10,14,0.72)',
                        border: '1px solid rgba(253,196,54,0.5)', padding: '10px 28px',
                    }}>
                        <span style={{ fontWeight: 600, fontSize: 32, letterSpacing: '0.3em', color: 'rgba(232,230,224,0.65)', textTransform: 'uppercase' }}>
                            {words.court_court}
                        </span>
                        <span style={{ fontFamily: "'Anton',sans-serif", fontSize: 52, color: '#fff' }}>
                            {board.courtNumber || matNumber(court)}
                        </span>
                    </div>
                    <div style={{ width: 10, height: 64, background: '#e6ebf2' }} />
                </div>
            </div>

            <div style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', gap: 16, padding: '0 60px 34px' }}>
                {matches.length === 0 ? (
                    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 18 }}>
                        <div style={{ fontFamily: "'Anton',sans-serif", fontSize: 92, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'rgba(232,230,224,0.22)' }}>
                            {words.court_idle_title}
                        </div>
                        <div style={{ fontWeight: 600, fontSize: 34, letterSpacing: '0.3em', textTransform: 'uppercase', color: 'rgba(232,230,224,0.35)' }}>
                            {words.court_idle_sub}
                        </div>
                    </div>
                ) : matches.map((m, i) => (
                    <QueueRow
                        key={keyOf(m)} match={m} index={i} isNext={i === 0}
                        enter={entering[i]} words={words} tbd={words.tbd}
                    />
                ))}
            </div>
        </div>
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   The document
   ══════════════════════════════════════════════════════════════════════════ */

export default function BoardIsland({ props }) {
    const words = props.words;
    const pinned = props.pinned;

    const [state, setState] = useState(props.state);
    const [board, setBoard] = useState(props.board || { matches: [] });
    const [receivedAt, setReceivedAt] = useState(() => performance.now());

    const { rootRef, stageRef } = useStage({ fixedHeight: 1080 });

    /**
     * ONE entry point for both payload shapes.
     *
     * The mat state and the running order are different documents on the server
     * and different shapes on the wire; they are told apart HERE by what they
     * contain, so the socket never has to know which endpoint answered it.
     */
    const onUpdate = useCallback((payload) => {
        if (!payload || typeof payload !== 'object') return;

        if (payload.matches) { setBoard(payload); return; }
        if (!payload.mode) return;

        setState(payload);
        setReceivedAt(performance.now());
    }, []);

    const stale = useCourtBoard({ pinned, onUpdate, getMode: () => state.mode || 'upcoming' });
    useHeartbeat(props.urls && props.urls.status);

    /* ── Which layer is up ─────────────────────────────────────────────────
       WHAT THIS SCREEN WAS HUNG UP TO BE comes first; the mat only decides for
       a screen that was told to follow it. This package draws one document, so
       unlike Karate and Taekwondo — which render a different page per pin — the
       pin has to be honoured here. */
    const idle = state.mode === 'upcoming';
    const onQueue = pinned === 'queue' || (pinned === 'both' && idle);
    const onIdle = pinned === 'bout' && idle;
    const onVs = !onQueue && !onIdle && state.mode === 'vs';
    const onBoard = !onQueue && !onIdle && !onVs;

    /* The handover: the introduction WIPES itself off the scoreboard already
       drawn behind it, rather than the page changing. That transition is what
       the whole single-document design exists for. */
    const [exitingVs, setExitingVs] = useState(false);
    const wasVs = useRef(onVs);
    useEffect(() => {
        if (wasVs.current && !onVs) {
            setExitingVs(true);
            const id = setTimeout(() => setExitingVs(false), 560);
            wasVs.current = onVs;
            return () => clearTimeout(id);
        }
        wasVs.current = onVs;
        return undefined;
    }, [onVs]);

    /* ── The callout and the penalty notice ────────────────────────────────
       The FIRST state a screen is handed is history, not news: a board reloads
       for all sorts of reasons and the state it wakes to still carries whatever
       was scored last. Announcing it would burst a callout over the hall for
       something that happened ten minutes ago. */
    const [callout, setCallout] = useState(null);
    const [notice, setNotice] = useState(null);
    const lastTs = useRef(props.state && props.state.lastEvent ? props.state.lastEvent.ts : 0);

    useEffect(() => {
        const e = state.lastEvent;
        if (!e || !e.ts || e.ts === lastTs.current) return undefined;
        lastTs.current = e.ts;

        if (e.kind === 'penalty') {
            setNotice(words.penalty_notice
                .replace(':corner', e.side === 'blue' ? words.corner_blue : words.corner_white)
                .replace(':reason', (words.penalties[e.source] || words.penalties.other || '').toUpperCase())
                .replace(':time', clockWords(state.running
                    ? Math.max(0, state.remaining - (performance.now() - receivedAt) / 1000)
                    : state.remaining)));
            const id = setTimeout(() => setNotice(null), 4300);
            return () => clearTimeout(id);
        }

        if (e.kind === 'point' || e.kind === 'advantage') {
            setCallout(e);
            const id = setTimeout(() => setCallout(null), 1600);
            return () => clearTimeout(id);
        }

        return undefined;
    }, [state.lastEvent, words]);   // eslint-disable-line react-hooks/exhaustive-deps

    // The bright-venue theme is ONE switch, chosen at the scoring table, so
    // every screen on the mat changes together.
    useEffect(() => {
        document.documentElement.setAttribute('data-theme', state.theme === 'venue' ? 'venue' : 'arena');
    }, [state.theme]);

    return (
        <div id="root" ref={rootRef}>
            <div id="stage" ref={stageRef}>
                {onIdle && (
                    <div id="idle">
                        <div className="t">{words.court_idle_title}</div>
                        <div className="s">{words.court_idle_sub}</div>
                    </div>
                )}

                {(onBoard || onVs) && (
                    <Scoreboard
                        state={state} receivedAt={receivedAt} words={words}
                        callout={callout} notice={notice}
                        eventTitle={props.event.title} eventLogo={props.event.logo} court={props.court}
                    />
                )}

                {(onVs || exitingVs) && (
                    <Vs state={state} words={words} eventTitle={props.event.title} court={props.court} exiting={exitingVs} />
                )}

                {onQueue && (
                    <Queue board={board} words={words} eventTitle={props.event.title} court={props.court} rows={props.rows || 4} />
                )}

                <div id="stale" className={stale ? 'on' : undefined}>
                    <span className="dot" />
                    <span>{words.court_reconnecting}</span>
                </div>
            </div>
        </div>
    );
}
