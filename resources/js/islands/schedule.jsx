/**
 * schedule.jsx — Phase M1: the FIRST React island.
 *
 * A straight PORT of `resources/views/partials/schedule-board-script.blade.php`
 * (the `scheduleBoard()` IIFE) to React. Same markup, same Tailwind classes,
 * same behaviour, same live-update semantics. This is deliberately NOT a
 * redesign (Design Rule #1) — every class string below is copied from the
 * legacy renderer so the two paths are visually identical.
 *
 * It is rendered ONLY when `config('features.react_schedule')` is on; the
 * legacy include remains the default path and is untouched.
 *
 * LIVE UPDATES (the whole point of the island):
 *   - `realtime:schedule`          MQTT, via realtime.js
 *       {action:'refresh'}                    -> silent re-fetch of me.schedule.data
 *       {action:'deleted', session:{id}}      -> remove that one session
 *       {session:{…}}                         -> upsert that one session
 *   - `schedule-session-saved`     the create/edit sheet   -> upsert
 *   - `schedule-session-deleted`   the create/edit sheet   -> remove
 * All three are bound in ONE effect and removed in its teardown, which the
 * island runtime guarantees runs before the shell replaces the content.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { mountIsland } from '../island';

const WEEKDAY_KEYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const ORDER = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };

/* ===== Browser-LOCAL time helpers (identical to the legacy script) =====
   The server runs in UTC, so deriving "today"/status from it rolls over hours
   late for users ahead of UTC. Everything below is computed from the user's
   real local clock. */

function localTodayKey() {
    return WEEKDAY_KEYS[new Date().getDay()];
}

function startOfLocalWeek() {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() - d.getDay()); // back to Sunday
    return d;
}

function buildWeekDays(shortLabels) {
    const ws = startOfLocalWeek();
    const todayMid = new Date();
    todayMid.setHours(0, 0, 0, 0);
    const out = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(ws);
        d.setDate(ws.getDate() + i);
        out.push({
            key: WEEKDAY_KEYS[i],
            short: (shortLabels && shortLabels[i]) || WEEKDAY_KEYS[i].slice(0, 3),
            d: String(d.getDate()),
            isToday: d.getTime() === todayMid.getTime(),
            isPast: d.getTime() < todayMid.getTime(),
        });
    }
    return out;
}

/** This week's local Date for a weekday + "HH:MM[:SS]". */
function occurrenceDate(dayKey, hhmm) {
    const ws = startOfLocalWeek();
    const d = new Date(ws);
    d.setDate(ws.getDate() + (ORDER[dayKey] || 0));
    const p = String(hhmm || '00:00').split(':');
    d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
    return d;
}

/** Local YYYY-MM-DD (not toISOString, which would shift by the UTC offset). */
function ymdLocal(d) {
    const m = d.getMonth() + 1;
    const day = d.getDate();
    return `${d.getFullYear()}-${m < 10 ? '0' : ''}${m}-${day < 10 ? '0' : ''}${day}`;
}

function fmtCountdown(ms) {
    let v = ms < 0 ? 0 : ms;
    const t = Math.floor(v / 1000);
    const days = Math.floor(t / 86400);
    const h = Math.floor((t % 86400) / 3600);
    const mn = Math.floor((t % 3600) / 60);
    const sc = t % 60;
    const p = (n) => (n < 10 ? '0' : '') + n;
    return (days > 0 ? days + 'd ' : '') + p(h) + ':' + p(mn) + ':' + p(sc);
}

function durMins(s) {
    const n = parseInt(String((s && s.duration) || '').replace(/[^0-9]/g, ''), 10);
    return isNaN(n) ? 0 : n;
}

/** done (ended) · live (in progress) · today (starts later today) · upcoming. */
function statusFor(s, todayKey, nowMs) {
    const now = new Date(nowMs);
    const start = occurrenceDate(s.day, s.start_raw || s.start || '00:00');
    let end = s.end_raw
        ? occurrenceDate(s.day, s.end_raw)
        : new Date(start.getTime() + durMins(s) * 60000);
    if (end.getTime() <= start.getTime()) end = new Date(end.getTime() + 86400000); // crosses midnight
    if (now >= end) return 'done';
    if (now >= start) return 'live';
    return s.day === todayKey ? 'today' : 'upcoming';
}

/* ===== Small presentational pieces (markup copied verbatim) ===== */

function Avatar({ member, sizeClass, textClass }) {
    const m = member || {};
    if (m.avatar) {
        return (
            <span className={`${sizeClass} rounded-full overflow-hidden flex-shrink-0 block`}>
                <img src={m.avatar} alt="" className={`${sizeClass} object-cover`} />
            </span>
        );
    }
    return (
        <span
            className={`${sizeClass} rounded-full overflow-hidden flex-shrink-0 grid place-items-center text-white ${textClass} font-bold`}
            style={{ background: m.color || '#7c3aed' }}
        >
            {m.initials || ''}
        </span>
    );
}

function StatusBadge({ session, status, nowMs, t }) {
    if (session.is_cancelled) return null; // the "Cancelled" pill says it all
    if (status === 'done') {
        return (
            <span className="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600">
                <i className="bi bi-check2"></i> {t.done}
            </span>
        );
    }
    if (status === 'live') {
        return (
            <span className="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white inline-flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> {t.live_now}
            </span>
        );
    }
    // today (later) or upcoming → live countdown to the start time
    const startMs = occurrenceDate(session.day, session.start_raw || session.start || '00:00').getTime();
    return (
        <span className="ms-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent text-primary inline-flex items-center gap-1">
            <i className="bi bi-hourglass-split"></i> <span className="cd-val">{fmtCountdown(startMs - nowMs)}</span>
        </span>
    );
}

function SessionPill({ session, t }) {
    const s = session;
    if (s.is_cancelled) {
        return (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-600 inline-flex items-center gap-1">
                <i className="bi bi-calendar-x"></i> {t.cancelled}
            </span>
        );
    }
    if (s.source === 'substituting') {
        return (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600 inline-flex items-center gap-1">
                <i className="bi bi-person-check-fill"></i> {t.covering}
            </span>
        );
    }
    if (s.source === 'teaching') {
        return (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 inline-flex items-center gap-1">
                <i className="bi bi-person-video3"></i> {t.teaching}
            </span>
        );
    }
    if (s.source === 'synced') {
        return (
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 inline-flex items-center gap-1">
                <i className="bi bi-arrow-repeat"></i> {t.synced}
            </span>
        );
    }
    const style = { background: `${s.color}1a`, color: s.color };
    if (s.intensity) {
        return <span className="text-[10px] font-bold px-2 py-0.5 rounded-full" style={style}>{s.intensity}</span>;
    }
    return <span className="text-[10px] font-bold px-2 py-0.5 rounded-full" style={style}>{t.personal}</span>;
}

function SessionCard({ session, members, todayKey, nowMs, showUrl, t }) {
    const s = session;
    const m = members[s.who] || members.me || { color: '#7c3aed', initials: '', relation: '' };
    const status = statusFor(s, todayKey, nowMs);

    // A club class (enrolled / taught / covering) links to its own detail and
    // carries THIS occurrence's date; anything else is a personal session.
    const isClubClass = s.source === 'synced' || s.source === 'teaching' || s.source === 'substituting';
    let href;
    if (isClubClass) {
        href = s.detail_url || '#';
        if (href !== '#') {
            const onYmd = ymdLocal(occurrenceDate(s.day, s.start_raw || s.start || '00:00'));
            href += (href.indexOf('?') >= 0 ? '&' : '?') + 'on=' + onYmd;
        }
    } else {
        href = `${showUrl}/${s.id}`;
    }

    return (
        <a href={href} data-shell-link data-route="me.schedule" className="block m-card m-press rounded-2xl overflow-hidden">
            <div className="flex">
                <div className="w-1.5 flex-shrink-0" style={{ background: s.color }}></div>
                <div className="flex-1 p-3.5">
                    <div className="flex items-start gap-3">
                        <div
                            className="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0"
                            style={{ background: `linear-gradient(160deg, ${s.color}, ${s.color}d0)` }}
                        >
                            <i className={`bi ${s.icon || ''} text-xl`}></i>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className="text-[11px] font-bold text-foreground">{s.start || ''}</span>
                                <span className="text-[10px] text-muted-foreground">· {s.duration || ''}</span>
                                <StatusBadge session={s} status={status} nowMs={nowMs} t={t} />
                            </div>
                            <h3 className={`font-bold text-foreground mt-0.5 truncate ${s.is_cancelled ? 'line-through opacity-60' : ''}`}>
                                {s.title}
                            </h3>
                            {s.discipline ? (
                                <p className="text-[11px] font-semibold mt-0.5 truncate" style={{ color: s.color }}>
                                    <i className="bi bi-box-seam text-[10px] me-1"></i>{s.discipline}
                                </p>
                            ) : null}
                            {(s.location || s.coach) ? (
                                <p className="text-xs text-muted-foreground mt-0.5 truncate flex items-center gap-1.5">
                                    {s.location ? (
                                        <>
                                            <i className="bi bi-geo-alt text-[11px]"></i>{s.location}
                                        </>
                                    ) : null}
                                    {s.coach ? (
                                        <>
                                            {s.location ? <span className="text-gray-300">·</span> : null}
                                            {s.is_substituted ? (
                                                <>
                                                    <i className="bi bi-arrow-left-right text-[11px] text-amber-500"></i>
                                                    <span className="text-amber-600 font-semibold">{s.coach}</span>
                                                </>
                                            ) : (
                                                s.coach
                                            )}
                                        </>
                                    ) : null}
                                </p>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex items-center justify-between mt-2.5">
                        <span className="inline-flex items-center gap-1.5">
                            <Avatar member={m} sizeClass="w-5 h-5" textClass="text-[8px]" />
                            <span className="text-[11px] font-medium text-muted-foreground">{m.relation}</span>
                        </span>
                        <SessionPill session={s} t={t} />
                    </div>
                </div>
            </div>
        </a>
    );
}

/* ===== The board ===== */

export default function ScheduleIsland({ props }) {
    const t = props.i18n || {};
    const showUrl = props.showUrl;
    const dataUrl = props.dataUrl;
    const gridLayout = !!props.gridLayout;
    const shortLabels = props.weekdayShort || [];

    const [sessions, setSessions] = useState(() => (Array.isArray(props.sessions) ? props.sessions : []));
    const [members, setMembers] = useState(() => props.members || {});
    const [who, setWho] = useState('all');
    const [nowMs, setNowMs] = useState(() => Date.now());

    // Today / the week strip come from the user's LOCAL clock, recomputed only
    // when the local weekday actually changes (midnight rollover while open).
    const todayKey = useMemo(() => localTodayKey(), [nowMs && new Date(nowMs).getDay()]); // eslint-disable-line react-hooks/exhaustive-deps
    const weekDays = useMemo(() => buildWeekDays(shortLabels), [todayKey]); // eslint-disable-line react-hooks/exhaustive-deps

    // Open on the day from the URL (?day=…) when present — so returning from a
    // card detail keeps the day you were browsing instead of snapping to today.
    const [day, setDay] = useState(() => {
        let urlDay = null;
        try {
            urlDay = new URLSearchParams(window.location.search).get('day');
        } catch (e) { /* ignore */ }
        return urlDay && ORDER[urlDay] !== undefined ? urlDay : localTodayKey();
    });

    const visibleForWho = useCallback((s) => who === 'all' || s.who === 'me', [who]);

    /* ---- Live updates ------------------------------------------------- */

    const upsert = useCallback((session) => {
        if (!session || session.id == null) return;
        setSessions((prev) => {
            const i = prev.findIndex((s) => String(s.id) === String(session.id));
            if (i >= 0) {
                const next = prev.slice();
                next[i] = session;
                return next;
            }
            return prev.concat([session]);
        });
        if (session.day) setDay(session.day); // jump to the affected day
    }, []);

    const removeById = useCallback((id) => {
        if (id == null) return;
        setSessions((prev) => prev.filter((s) => String(s.id) !== String(id)));
    }, []);

    // Re-pull the whole schedule (used for club-class changes, where the same
    // change renders differently per user) and re-render in place.
    const reloading = useRef(false);
    const reloadData = useCallback(async () => {
        if (reloading.current || !dataUrl) return;
        reloading.current = true;
        try {
            const res = await fetch(dataUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.sessions)) setSessions(data.sessions);
            if (data.members) setMembers(data.members);
            setNowMs(Date.now()); // refresh the local week/today alongside the data
        } catch (e) {
            /* best-effort — the DB stays the source of truth */
        } finally {
            reloading.current = false;
        }
    }, [dataUrl]);

    useEffect(() => {
        const onSaved = (e) => upsert(e.detail && e.detail.session);
        const onDeleted = (e) => removeById(e.detail && e.detail.id);
        const onRealtime = (e) => {
            const d = e.detail || {};
            if (d.action === 'refresh') { reloadData(); return; }
            if (d.action === 'deleted') { removeById(d.session && d.session.id); return; }
            if (d.session) upsert(d.session);
        };

        window.addEventListener('schedule-session-saved', onSaved);
        window.addEventListener('schedule-session-deleted', onDeleted);
        window.addEventListener('realtime:schedule', onRealtime);

        return () => {
            window.removeEventListener('schedule-session-saved', onSaved);
            window.removeEventListener('schedule-session-deleted', onDeleted);
            window.removeEventListener('realtime:schedule', onRealtime);
        };
    }, [upsert, removeById, reloadData]);

    // Live countdown ticker — the one interval the legacy script had. Cleared on
    // unmount, which the island runtime triggers before the shell swaps content.
    useEffect(() => {
        const id = setInterval(() => setNowMs(Date.now()), 1000);
        return () => clearInterval(id);
    }, []);

    /* ---- Derived ------------------------------------------------------ */

    const visible = useMemo(() => sessions.filter(visibleForWho), [sessions, visibleForWho]);
    const statCount = visible.length;
    const statDone = useMemo(
        () => visible.filter((s) => statusFor(s, todayKey, nowMs) === 'done').length,
        [visible, todayKey, nowMs]
    );
    const statVol = useMemo(() => {
        const mins = visible.reduce((a, s) => a + durMins(s), 0);
        return Math.round(mins / 6) / 10;
    }, [visible]);

    const dayAll = useMemo(() => sessions.filter((s) => s.day === day), [sessions, day]);
    const dayVis = useMemo(
        () => dayAll
            .filter(visibleForWho)
            .slice()
            .sort((a, b) => String(a.start_raw || a.start || '').localeCompare(String(b.start_raw || b.start || ''))),
        [dayAll, visibleForWho]
    );

    const pickDay = useCallback((key) => {
        setDay(key);
        // Reflect the selected day in the URL so back (in-page or device) restores it.
        try {
            history.replaceState(history.state || { shell: true }, '', '?day=' + key);
        } catch (e) { /* ignore */ }
    }, []);

    /* ---- Render (markup copied from the legacy renderer) --------------- */

    return (
        <div className="-mx-4 -mt-4 pb-4">
            {/* ===== Hero ===== */}
            <header className="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
                <div className="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
                <div className="flex items-center justify-between relative z-10">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-white/70">{t.this_week}</p>
                        <h1 className="text-2xl font-black mt-0.5">{t.heading}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => window.dispatchEvent(new CustomEvent('open-schedule-form'))}
                            className="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform"
                            aria-label={t.add_session}
                        >
                            <i className="bi bi-plus-lg text-xl"></i>
                        </button>
                        <div className="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                            <i className="bi bi-calendar2-week-fill text-xl m-float"></i>
                        </div>
                    </div>
                </div>

                <div className="flex gap-2 mt-5 relative z-10">
                    <div className="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                        <p className="text-lg font-black leading-none">{statCount}</p>
                        <p className="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{t.stat_sessions}</p>
                    </div>
                    <div className="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                        <p className="text-lg font-black leading-none">{statDone}</p>
                        <p className="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{t.done}</p>
                    </div>
                    <div className="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                        <p className="text-lg font-black leading-none">{statVol}h</p>
                        <p className="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{t.stat_volume}</p>
                    </div>
                </div>
            </header>

            {/* ===== Me / Family toggle (overlaps hero) ===== */}
            <div className="px-4 -mt-6 relative z-10">
                <div className="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex">
                    <button
                        type="button"
                        data-who="all"
                        onClick={() => setWho('all')}
                        className={`m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 ${who === 'all' ? 'bg-primary text-white' : 'text-muted-foreground'}`}
                    >
                        <i className="bi bi-people-fill"></i> {t.family}
                    </button>
                    <button
                        type="button"
                        data-who="me"
                        onClick={() => setWho('me')}
                        className={`m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 ${who === 'me' ? 'bg-primary text-white' : 'text-muted-foreground'}`}
                    >
                        <i className="bi bi-person-fill"></i> {t.just_me}
                    </button>
                </div>
            </div>

            {/* ===== Week-day strip ===== */}
            <div className="px-4 mt-4">
                <div className="flex gap-1">
                    {weekDays.map((wd) => {
                        const count = sessions.filter((s) => s.day === wd.key && visibleForWho(s)).length;
                        const active = day === wd.key;
                        const dots = [];
                        for (let i = 0; i < Math.min(count, 3); i++) {
                            dots.push(<span key={i} className={`w-1 h-1 rounded-full ${active ? 'bg-white' : 'bg-primary'}`}></span>);
                        }
                        return (
                            <button
                                key={wd.key}
                                type="button"
                                data-day={wd.key}
                                onClick={() => pickDay(wd.key)}
                                className={`m-press flex-1 min-w-0 flex flex-col items-center justify-start pt-2 pb-1.5 rounded-xl border transition-colors ${active ? 'bg-primary border-primary text-white' : 'bg-white border-gray-100 text-foreground'}`}
                            >
                                <span className={`text-[10px] uppercase tracking-wide leading-none ${active ? 'text-white/80' : 'text-muted-foreground'}`}>{wd.short}</span>
                                <span className="text-lg font-black leading-none mt-1.5">{wd.d}</span>
                                <span className="mt-1.5 h-1 flex items-center justify-center gap-0.5">{dots}</span>
                                <span className="mt-auto h-3 flex items-center text-[8px] font-bold leading-none">
                                    {wd.isToday ? <span className={active ? 'text-white' : 'text-primary'}>{t.today}</span> : null}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* ===== Sessions for the selected day ===== */}
            <div className="px-4 mt-5">
                {!dayAll.length ? (
                    <div className={gridLayout ? 'grid grid-cols-1 xl:grid-cols-2 gap-3' : 'space-y-3'}>
                        <div className="bg-white rounded-2xl border border-gray-100 px-5 py-12 text-center">
                            <div className="w-16 h-16 mx-auto rounded-3xl bg-accent text-primary grid place-items-center">
                                <i className="bi bi-cup-hot text-2xl m-float"></i>
                            </div>
                            <p className="text-sm font-bold text-foreground mt-3">{t.rest_day}</p>
                            <p className="text-xs text-muted-foreground mt-1">{t.no_training}</p>
                        </div>
                    </div>
                ) : !dayVis.length ? (
                    <div className={gridLayout ? 'grid grid-cols-1 xl:grid-cols-2 gap-3' : 'space-y-3'}>
                        <div className="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                            <i className="bi bi-cup-hot text-2xl text-gray-300 m-float"></i>
                            <p className="text-sm text-muted-foreground mt-2">{t.no_personal}</p>
                        </div>
                    </div>
                ) : (
                    <div className={gridLayout ? 'grid grid-cols-1 xl:grid-cols-2 gap-3' : 'space-y-3'}>
                        {dayVis.map((s) => (
                            <SessionCard
                                key={String(s.id ?? `${s.source}-${s.day}-${s.start_raw || s.start}-${s.token || ''}`)}
                                session={s}
                                members={members}
                                todayKey={todayKey}
                                nowMs={nowMs}
                                showUrl={showUrl}
                                t={t}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ===== Entry: register the island =====
   This file is the Vite entry for the schedule island. Registering at module
   top level is safe — `mountIsland` only RECORDS the island here; the actual
   mount/unmount is driven by `shell:navigated` (plus one immediate attempt for
   the first paint), so a shell that executes this script only once per session
   still gets a fresh root on every visit. */
mountIsland('#schedule-island', (el) => {
    let props = {};
    try {
        props = JSON.parse(el.getAttribute('data-island-props') || '{}');
    } catch (e) {
        console.error('[schedule island] bad props', e);
    }
    return <ScheduleIsland props={props} />;
});
