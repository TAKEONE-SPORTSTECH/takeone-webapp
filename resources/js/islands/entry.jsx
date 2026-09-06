/**
 * entry.jsx — Phase M2: the SECOND React island.
 *
 * A port of `resources/views/entry/public/my-entry.blade.php` (the
 * `myEntry()` Alpine component and the markup around it) to React. Same
 * markup, same class strings, same server contract, same live-update
 * semantics. Deliberately NOT a redesign (Design Rule #1) — every class and
 * inline style below is copied from the Blade panel so the two paths are
 * visually identical.
 *
 * It is rendered ONLY when `config('features.react_entry')` is on; the Blade
 * panel remains the default path and is untouched. Only one of the two ever
 * renders.
 *
 * WHAT IT DOES NOT DECIDE
 * -----------------------
 * Nothing. Every rule about what may be changed, by whom and until when lives
 * in App\Events\Support\EntryEditor, and every rule about leaving lives in
 * App\Events\Support\Withdrawal. This island RENDERS `permissions` — it never
 * infers them — so a field the server will refuse is drawn LOCKED with the
 * server's own reason beside it, and a field whose `editable` is false is
 * never put in a request body at all.
 *
 * THE PHOTOGRAPH IS NOT IN HERE, ON PURPOSE
 * -----------------------------------------
 * `<x-takeone-cropper>` is a Blade + jQuery widget, and this project has
 * exactly ONE cropper (CLAUDE.md → One Cropper Everywhere). It cannot be
 * rendered from React and must not be reimplemented, so the photo sheet, the
 * camera and the cropper stay in Blade, OUTSIDE the island. The seam is two
 * events:
 *
 *   island → Blade   `entry-photo:open`     the pass card's camera button
 *   Blade  → island  `entry:updated`        {detail: <the PUT's JSON body>}
 *
 * The island reads the current photograph from `entry.photo` like any other
 * server value, and re-fetches its state after the Blade half reports a save.
 * Clearing a photograph is an ordinary PUT and stays in here.
 *
 * LIVE UPDATES
 * ------------
 *   `realtime:events`  MQTT, via realtime.js. When `action` is
 *   'entry-updated' or 'withdrawal' AND `event` is this event's uuid, the
 *   panel silently re-fetches its own state. The payload is a SIGNAL, not the
 *   data: what each recipient may see differs, so the panel re-reads rather
 *   than trusting a broadcast body (CLAUDE.md → Realtime, refresh shape).
 *
 * The listener is registered in a `useEffect` and removed in its teardown.
 * That teardown is what the island runtime runs when the container leaves the
 * document — which is why the `window.__xxx` dedup idiom the Blade panel uses
 * is NOT repeated here. That idiom exists for pages with no unmount hook; a
 * React island has one, and using it is the reason islands exist.
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { mountIsland } from '../island';

/* ===== Plumbing ===================================================== */

function csrf() {
    const el = document.querySelector('meta[name=csrf-token]');
    return (el && el.content) || '';
}

/** The one toast the sealed event surface publishes (see the page's script). */
function toast(type, msg) {
    if (typeof window.showToast === 'function') window.showToast(type, msg);
}

/** A row keyed 'belt' asks the server about `belt_colour`. */
function realField(f) {
    return f === 'belt' ? 'belt_colour' : f;
}

/* ===== The Day / Month / Year picker ================================
   A port of `<x-date-picker variant="dropdown">`, which is Alpine and cannot
   be mounted inside a React tree. Same classes, same behaviour: the panels
   expand IN FLOW so a scrolling sheet body cannot clip them, and a native
   `<input type="date">` is still banned (Design Rule #4). Values are ISO
   `YYYY-MM-DD` or ''. */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    .map((s, i) => ({ v: String(i + 1).padStart(2, '0'), s }));

function daysInMonth(year, month) {
    if (!year || !month) return 31;
    return new Date(Number(year), Number(month), 0).getDate();
}

function DatePartsPicker({ value, max, onChange, t }) {
    const [dOpen, setDOpen] = useState(false);
    const [mOpen, setMOpen] = useState(false);

    // Seeded from the value; re-seeded whenever the value changes underneath us
    // (a re-fetch, or the sheet being reopened).
    const parts = useMemo(() => {
        if (value && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
            const [y, m, d] = value.split('-');
            return { y, m, d };
        }
        return { y: '', m: '', d: '' };
    }, [value]);

    const [local, setLocal] = useState(parts);
    const seeded = useRef(value);
    if (seeded.current !== value) {
        // Value changed from outside — adopt it without an effect round-trip.
        seeded.current = value;
        if (local.y !== parts.y || local.m !== parts.m || local.d !== parts.d) setLocal(parts);
    }

    const maxYear = max ? Number(String(max).slice(0, 4)) : new Date().getFullYear() + 10;
    const minYear = new Date().getFullYear() - 120;

    const compose = useCallback((d, m, y) => {
        if (!/^\d{4}$/.test(String(y)) || !m || !d) return '';
        if (Number(y) < minYear || Number(y) > maxYear) return '';
        const dim = daysInMonth(y, m);
        if (Number(d) < 1 || Number(d) > dim) return '';
        return `${y}-${m}-${String(d).padStart(2, '0')}`;
    }, [minYear, maxYear]);

    const oob = useCallback((iso) => !!(max && iso > max), [max]);

    const push = useCallback((next) => {
        setLocal(next);
        const iso = compose(next.d, next.m, next.y);
        onChange(iso && !oob(iso) ? iso : '');
    }, [compose, oob, onChange]);

    const days = useMemo(
        () => Array.from({ length: daysInMonth(local.y, local.m) }, (_, i) => String(i + 1).padStart(2, '0')),
        [local.y, local.m]
    );

    const dayOut = (d) => {
        const iso = compose(d, local.m, local.y);
        return iso ? oob(iso) : false;
    };
    const monthOut = (m) => {
        if (!local.y) return false;
        return !!(max && `${local.y}-${m}-01` > max);
    };
    const monthLabel = (MONTHS.find((m) => m.v === local.m) || {}).s || '';

    return (
        <div className="w-full">
            <div className="grid grid-cols-3 gap-2">
                {/* Day */}
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => { setDOpen((v) => !v); setMOpen(false); }}
                        className={`w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent border-gray-200 ${dOpen ? 'ring-2 ring-primary border-transparent' : ''}`}
                    >
                        <span className="flex items-center gap-2 truncate">
                            <i className={`bi bi-calendar-day ${local.d ? 'text-primary' : 'text-primary/40'}`}></i>
                            <span className={local.d ? 'text-foreground font-medium' : 'text-muted-foreground'}>{local.d || t.day}</span>
                        </span>
                        <i className={`bi bi-chevron-down text-xs text-gray-400 transition-transform ${dOpen ? 'rotate-180' : ''}`}></i>
                    </button>
                    {dOpen ? (
                        <div className="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                            {days.map((d) => (
                                <button
                                    key={d}
                                    type="button"
                                    disabled={dayOut(d)}
                                    onClick={() => { push({ ...local, d }); setDOpen(false); }}
                                    className={`w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors ${local.d === d ? 'bg-primary/5 font-semibold text-primary' : (dayOut(d) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')}`}
                                >
                                    {d}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                {/* Month */}
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => { setMOpen((v) => !v); setDOpen(false); }}
                        className={`w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent border-gray-200 ${mOpen ? 'ring-2 ring-primary border-transparent' : ''}`}
                    >
                        <span className="flex items-center gap-2 truncate">
                            <i className={`bi bi-calendar-month ${local.m ? 'text-primary' : 'text-primary/40'}`}></i>
                            <span className={local.m ? 'text-foreground font-medium' : 'text-muted-foreground'}>{monthLabel || t.month}</span>
                        </span>
                        <i className={`bi bi-chevron-down text-xs text-gray-400 transition-transform ${mOpen ? 'rotate-180' : ''}`}></i>
                    </button>
                    {mOpen ? (
                        <div className="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                            {MONTHS.map((mo) => (
                                <button
                                    key={mo.v}
                                    type="button"
                                    disabled={monthOut(mo.v)}
                                    onClick={() => {
                                        // Clamp a 31st that no longer exists in the new month.
                                        const dim = daysInMonth(local.y, mo.v);
                                        const d = local.d && Number(local.d) > dim ? String(dim).padStart(2, '0') : local.d;
                                        push({ ...local, m: mo.v, d });
                                        setMOpen(false);
                                    }}
                                    className={`w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors ${local.m === mo.v ? 'bg-primary/5 font-semibold text-primary' : (monthOut(mo.v) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')}`}
                                >
                                    {mo.s}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                {/* Year — typeable, so a date decades away is reachable without paging. */}
                <div className="relative">
                    <i
                        className={`bi bi-calendar-event absolute start-3 top-1/2 -translate-y-1/2 pointer-events-none ${local.y ? 'text-primary' : ((local.d || local.m) ? 'text-amber-500' : 'text-primary/40')}`}
                    ></i>
                    <input
                        type="text"
                        inputMode="numeric"
                        maxLength={4}
                        placeholder={t.year}
                        value={local.y}
                        onChange={(e) => push({ ...local, y: e.target.value.replace(/\D/g, '').slice(0, 4) })}
                        className={`w-full ps-9 pe-3 py-2.5 bg-white border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-colors ${(local.d || local.m) && !local.y ? 'border-amber-400 ring-1 ring-amber-300 placeholder:text-amber-500' : 'border-gray-200'}`}
                    />
                </div>
            </div>
            {(local.d || local.m) && !local.y ? (
                <p className="mt-1.5 text-[11px] text-amber-600 flex items-center gap-1">
                    <i className="bi bi-arrow-up"></i>{t.year_to_finish}
                </p>
            ) : null}
        </div>
    );
}

/* ===== The country picker ==========================================
   A port of `<x-country-dropdown>`, which is Alpine and cannot be mounted
   inside a React tree. Same classes, same searchable list, same ISO-2 value —
   the LIST itself is handed over as data on the mount element (props.countries,
   read server-side from the one file every country picker in the product uses),
   so this is a second renderer of one list, never a second list.

   The panel is absolutely positioned exactly as the Blade component's is, which
   is why the sheet body that holds it carries a min-height: a one-field sheet
   is shorter than the panel and would clip it. */

function CountryPicker({ value, countries, onChange, t }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const wrap = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const away = (e) => { if (wrap.current && !wrap.current.contains(e.target)) setOpen(false); };
        document.addEventListener('click', away);
        return () => document.removeEventListener('click', away);
    }, [open]);

    const current = countries.find((c) => c.code === String(value || '').toLowerCase());
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return countries;
        return countries.filter((c) => c.name.toLowerCase().includes(term) || c.code.includes(term));
    }, [search, countries]);

    return (
        <div ref={wrap} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="e-field w-full h-12 px-4 rounded-2xl text-[15px] flex items-center justify-between"
            >
                <span className="flex items-center gap-2">
                    {current ? <span className={`fi fi-${current.code}`}></span> : null}
                    <span className={`text-sm ${current ? '' : 'text-gray-400'}`}>{current ? current.name : t.country_search}</span>
                </span>
                <i className={`bi bi-chevron-down text-xs transition-transform ${open ? 'rotate-180' : ''}`}></i>
            </button>

            {open ? (
                <div className="tf-dropdown-panel top-full mt-1">
                    <div className="p-2 border-b border-gray-100">
                        <input
                            type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                            onClick={(e) => e.stopPropagation()}
                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                            placeholder={t.country_search}
                        />
                    </div>
                    <div className="max-h-60 overflow-y-auto">
                        {filtered.map((c) => (
                            <div
                                key={c.code} className="tf-dropdown-item-sm"
                                onClick={() => { onChange(c.code.toUpperCase()); setSearch(''); setOpen(false); }}
                            >
                                <span className={`fi fi-${c.code} mr-2`}></span>
                                <span>{c.name}</span>
                            </div>
                        ))}
                        {!filtered.length ? (
                            <div className="px-4 py-2 text-gray-500 text-sm">{t.country_none}</div>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

/* ===== The gender toggle ===========================================
   A port of `<x-gender-toggle>`. The colours live ONLY in the conditional
   branch — never also in the static class list — because Tailwind emits
   `.border-gray-200` after `.border-blue-500` and equal-specificity classes
   resolve by stylesheet order, so a static grey would beat the bound colour. */

function GenderToggle({ value, onChange, t }) {
    const base = 'flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all font-semibold text-sm';
    return (
        <div className="grid grid-cols-2 gap-3">
            <button
                type="button"
                onClick={() => onChange('Male')}
                className={`${base} ${value === 'Male' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}
            >
                <i className="bi bi-gender-male"></i><span>{t.gender_male}</span>
            </button>
            <button
                type="button"
                onClick={() => onChange('Female')}
                className={`${base} ${value === 'Female' ? 'border-pink-500 bg-pink-50 text-pink-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}
            >
                <i className="bi bi-gender-female"></i><span>{t.gender_female}</span>
            </button>
        </div>
    );
}

/* ===== The panel ==================================================== */

export default function EntryPanel({ props, t }) {
    const theme = props.theme || {};
    const urls = props.urls || {};
    const belts = useMemo(() => (Array.isArray(props.belts) ? props.belts : []), [props.belts]);
    const beltLabels = useMemo(
        () => belts.reduce((a, b) => { a[b.value] = b.label; return a; }, {}),
        [belts]
    );

    /* [{code: 'bh', name: 'Bahrain'}, …] — handed over as data, the same way
       the belts and the clubs are. */
    const countries = useMemo(() => (Array.isArray(props.countries) ? props.countries : []), [props.countries]);
    const countryName = useCallback((code) => {
        if (!code) return '';
        const c = countries.find((x) => x.code === String(code).toLowerCase());
        return c ? c.name : String(code).toUpperCase();
    }, [countries]);

    /* Server truth, and the only truth. Every one of these is replaced
       wholesale by what a save or a re-fetch hands back — the panel never
       patches a value it computed itself. */
    const [entry, setEntry] = useState(() => props.entry || {});
    const [perm, setPerm] = useState(() => props.permissions || { window: {}, fields: {} });
    const [withdrawal, setWithdrawal] = useState(() => props.withdrawal || null);

    const [clubOptions, setClubOptions] = useState(() => (Array.isArray(props.clubs) ? props.clubs : []));
    const [clubQuery, setClubQuery] = useState('');
    const [clubResults, setClubResults] = useState([]);
    const [clubSearching, setClubSearching] = useState(false);

    const [sheet, setSheet] = useState('');
    const [draft, setDraft] = useState({});
    const [saving, setSaving] = useState(false);
    const [notices, setNotices] = useState([]);
    const noticeId = useRef(0);

    const windowOpen = !!(perm.window && perm.window.open);

    const editable = useCallback(
        (f) => !!(perm.fields && perm.fields[realField(f)] && perm.fields[realField(f)].editable),
        [perm]
    );
    const reason = useCallback(
        (f) => (perm.fields && perm.fields[realField(f)] && perm.fields[realField(f)].reason)
            || (perm.window && perm.window.reason) || '',
        [perm]
    );

    const pushNotice = useCallback((tone, text) => {
        if (!text) return;
        noticeId.current += 1;
        const id = noticeId.current;
        setNotices((prev) => prev.concat([{ id, tone, text }]));
    }, []);
    const dismiss = useCallback((id) => setNotices((prev) => prev.filter((n) => n.id !== id)), []);

    /* ---- Reading it back ---------------------------------------------
       The whole panel, re-fetched. Silent on purpose: this runs because
       somebody ELSE changed something, and a toast for every organiser
       keystroke would be noise. */
    const refresh = useCallback(async () => {
        if (!urls.state) return;
        try {
            const res = await fetch(urls.state, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const d = await res.json().catch(() => ({}));
            if (!d.success) return;
            setEntry(d.entry);
            setPerm(d.permissions);
            setWithdrawal(d.withdrawal);
        } catch (e) {
            /* the DB is the truth; a dropped poll changes nothing */
        }
    }, [urls.state]);

    /* ---- What a save came back with ----------------------------------
       Shared by the island's own PUTs and by the Blade photo half, which
       reports its response over `entry:updated` so both land here. */
    const applySaved = useCallback((d) => {
        if (!d) return;
        if (d.entry) setEntry(d.entry);
        setSheet('');

        /* A re-weigh and a division move are the two outcomes that must not
           vanish with a toast: the first sends them back to the desk, the
           second changes who they fight. Both stay on the page until
           dismissed. */
        if (d.reweigh) pushNotice('warn', d.message);
        if (d.division_changed) pushNotice('brand', t.division_moved);
        if (!d.reweigh) toast('success', d.message);
    }, [pushNotice, t.division_moved]);

    /* ---- Writing ------------------------------------------------------ */

    const save = useCallback(async (fields) => {
        if (saving) return;

        /* Never send a field the server has already said is locked — it would
           refuse with a 422 naming it, and the panel would be asking for
           something it can see it may not have. */
        const body = {};
        Object.keys(fields).forEach((k) => {
            if (editable(k)) body[k] = fields[k];
        });
        if (!Object.keys(body).length) {
            toast('error', reason(Object.keys(fields)[0]) || t.not_yours);
            return;
        }

        setSaving(true);
        try {
            const res = await fetch(urls.update, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || !d.success) throw new Error(d.message || t.not_yours);
            applySaved(d);
        } catch (e) {
            toast('error', e.message);
        } finally {
            setSaving(false);
        }
    }, [saving, editable, reason, urls.update, applySaved, t.not_yours]);

    const withdraw = useCallback(async (reasonText) => {
        if (saving) return;
        setSaving(true);
        try {
            const res = await fetch(urls.withdraw, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ reason: reasonText || null }),
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || !d.success) throw new Error(d.message || t.withdraw_not_entered);
            setWithdrawal(d.withdrawal);
            setSheet('');
            toast('success', d.message);
        } catch (e) {
            toast('error', e.message);
        } finally {
            setSaving(false);
        }
    }, [saving, urls.withdraw, t.withdraw_not_entered]);

    const takeBack = useCallback(async () => {
        if (saving) return;
        setSaving(true);
        try {
            const res = await fetch(urls.withdraw, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin',
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || !d.success) throw new Error(d.message || t.withdraw_nothing_to_cancel);
            setWithdrawal(null);
            toast('success', d.message);
        } catch (e) {
            toast('error', e.message);
        } finally {
            setSaving(false);
        }
    }, [saving, urls.withdraw, t.withdraw_nothing_to_cancel]);

    /* ---- The club search (organiser only) ----------------------------- */

    const searchTimer = useRef(null);
    const onClubQuery = useCallback((q) => {
        setClubQuery(q);
        if (searchTimer.current) clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(async () => {
            const term = q.trim();
            if (term.length < 2) { setClubResults([]); return; }
            setClubSearching(true);
            try {
                const res = await fetch(`${urls.clubSearch}?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                setClubResults(Array.isArray(d.clubs) ? d.clubs : (Array.isArray(d) ? d : []));
            } catch (e) {
                setClubResults([]);
            } finally {
                setClubSearching(false);
            }
        }, 300);
    }, [urls.clubSearch]);

    // A debounce left running past an unmount would set state on a dead root.
    useEffect(() => () => { if (searchTimer.current) clearTimeout(searchTimer.current); }, []);

    /* A searched club joins the selection cards and is chosen, so the chosen
       state is always read from ONE list. */
    const adopt = useCallback((c) => {
        setClubOptions((prev) => (prev.some((o) => o.slug === c.slug)
            ? prev
            : prev.concat([{ slug: c.slug, name: c.name, logo: c.logo || '', country: c.country || '' }])));
        setDraft((d) => ({ ...d, club: c.slug }));
        setClubQuery('');
        setClubResults([]);
    }, []);

    /* ---- Live + the Blade photo half ----------------------------------
       ONE effect, one teardown. The island runtime unmounts this root when
       the container leaves the document, and that is what removes these
       listeners — no `window.__xxx` parking needed. */
    useEffect(() => {
        const onRealtime = (ev) => {
            const d = ev.detail || {};
            if (d.event !== props.eventUuid) return;
            if (d.action !== 'entry-updated' && d.action !== 'withdrawal') return;
            refresh();
        };
        // The Blade cropper half reports the PUT it made; re-read state so the
        // panel never trusts a body it did not ask for.
        const onPhotoSaved = (ev) => { applySaved(ev.detail); refresh(); };
        /* The Blade half could not read the current photograph back — the file
           is gone from disk. Remembering it here is what hides "Adjust the
           crop" and falls the pass card back to the silhouette. */
        const onPhotoBroken = (ev) => { setBrokenPhoto((ev.detail && ev.detail.src) || null); };

        window.addEventListener('realtime:events', onRealtime);
        window.addEventListener('entry:updated', onPhotoSaved);
        window.addEventListener('entry-photo:broken', onPhotoBroken);

        return () => {
            window.removeEventListener('realtime:events', onRealtime);
            window.removeEventListener('entry:updated', onPhotoSaved);
            window.removeEventListener('entry-photo:broken', onPhotoBroken);
        };
    }, [props.eventUuid, refresh, applySaved]);

    /* ---- The one sheet ------------------------------------------------ */

    const openSheet = useCallback((which) => {
        // Seeded from the server's copy every time, so a sheet closed without
        // saving leaves nothing behind.
        setDraft({
            weight: entry.weight,
            belt_colour: entry.belt_colour || null,
            belt_grade: entry.belt_grade || '',
            club: entry.club ? entry.club.slug : '',
            name: entry.name || '',
            nationality: entry.nationality || '',
            birthdate: entry.birthdate || '',
            gender: entry.gender || '',
            reason: '',
        });
        setClubQuery('');
        setClubResults([]);
        setSheet(which);
    }, [entry]);

    const closeSheet = useCallback(() => setSheet(''), []);

    const bump = useCallback((by) => {
        setDraft((d) => {
            const v = (d.weight == null ? 60 : d.weight) + by;
            return { ...d, weight: Math.min(140, Math.max(20, Math.round(v * 2) / 2)) };
        });
    }, []);

    /* Only the field this sheet is about — absent is not blank, and a sheet
       that saves one thing must never wipe the other six. */
    const commit = useCallback(() => {
        if (sheet === 'withdraw') return withdraw(draft.reason);
        if (sheet === 'weight') return save({ weight: draft.weight });
        if (sheet === 'belt') return save({ belt_colour: draft.belt_colour, belt_grade: draft.belt_grade });
        if (sheet === 'club') return save({ club: draft.club || null });
        if (sheet === 'birthdate') return save({ birthdate: draft.birthdate || null });
        if (sheet === 'gender') return save({ gender: draft.gender || null });
        if (sheet === 'nationality') return save({ nationality: draft.nationality || null });

        if (sheet === 'name') {
            /* Collapsed the same way the server collapses it, and refused the
               same way: a blank name is never written, so asking for it and
               then quietly keeping the old one would be a lie. */
            const v = String(draft.name || '').trim().replace(/\s+/g, ' ');
            if (v.length < 2) { toast('error', t.name_min); return undefined; }
            return save({ name: v });
        }

        return undefined;
    }, [sheet, draft, save, withdraw, t.name_min]);

    const sheetTitle = {
        weight: t.field_weight, belt: t.field_belt, club: t.field_club,
        birthdate: t.field_birthdate, gender: t.field_gender, withdraw: t.withdraw_title,
        name: t.field_name, nationality: t.field_nationality,
    }[sheet] || '';

    /* The sub-line says WHOSE entry this is, not what the page is for. It used
       to fall back to the page subtitle, so every sheet repeated "Everything
       you compete as. Fix anything that is wrong." under a heading like
       "Belt" — true of the page, meaningless on the sheet. */
    const sheetHint = sheet === 'withdraw' ? t.withdraw_explain : (entry.name || '');

    const sheetIcon = {
        weight: 'bi-speedometer2', belt: 'bi-award-fill', club: 'bi-building',
        birthdate: 'bi-calendar-event', gender: 'bi-gender-ambiguous',
        name: 'bi-person-vcard', nationality: 'bi-globe2',
        withdraw: 'bi-box-arrow-left',
    }[sheet] || 'bi-pencil-fill';

    /* ---- What each row says ------------------------------------------- */

    const rows = [
        { field: 'name', icon: 'bi-person-vcard', label: t.field_name },
        { field: 'belt', icon: 'bi-award-fill', label: t.field_belt },
        { field: 'club', icon: 'bi-building', label: t.field_club },
        { field: 'birthdate', icon: 'bi-calendar-event', label: t.field_birthdate },
        { field: 'gender', icon: 'bi-gender-ambiguous', label: t.field_gender },
        { field: 'nationality', icon: 'bi-globe2', label: t.field_nationality },
    ];

    const rowValue = (f) => {
        if (f === 'belt') {
            const c = entry.belt_colour ? (beltLabels[entry.belt_colour] || entry.belt_colour) : '';
            const g = entry.belt_grade || '';
            return [c, g].filter(Boolean).join(' · ') || '—';
        }
        if (f === 'club') return entry.club ? entry.club.name : t.no_club;
        if (f === 'birthdate') return entry.birthdate || '—';
        if (f === 'gender') return entry.gender || '—';
        if (f === 'name') return entry.name || '—';
        if (f === 'nationality') return countryName(entry.nationality) || '—';
        return '—';
    };

    const pending = !!(withdrawal && withdrawal.state === 'pending');

    /* The gendered silhouette, server-rendered by <x-gender-avatar> and handed
       over as markup. It is OUR Blade output, never user input — and rendering
       it beats reimplementing the component's srcset cuts in JS. Both genders
       travel so a gender change repaints without a reload. */
    /* A photo URL whose FILE is not on disk. The row still points at it, so
       `entry.photo` is truthy and the null-check below would happily render a
       broken-image glyph — which is exactly what a spectator sees today on the
       participants list, where 8 of 30 competitor photographs 404. A missing
       face must be SILENT (CLAUDE.md, Profile Pictures Are Portrait 3:4), so a
       404 falls back to the same gendered silhouette a blank one gets. */
    const [brokenPhoto, setBrokenPhoto] = useState(null);
    const photoUsable = entry.photo && entry.photo !== brokenPhoto;

    /* ---------- What is still missing ----------
       The list the prompt above the pass asks for, in the order it matters:
       the PHOTOGRAPH first, then the facts that place them in a division. Read
       straight off `entry`, which every save patches, so the prompt shrinks as
       they fill it in. A field the server will not let them change is never
       asked for, and neither is anything at all once the window is shut. */
    const missing = useMemo(() => {
        if (!windowOpen) return [];

        const want = [
            ['photo',     'bi-camera-fill',      t.field_photo,     !photoUsable],
            ['birthdate', 'bi-calendar-event',   t.field_birthdate, !entry.birthdate],
            ['gender',    'bi-gender-ambiguous', t.field_gender,    !entry.gender],
            ['weight',    'bi-speedometer2',     t.field_weight,    entry.weight === null || entry.weight === undefined || entry.weight === ''],
            ['belt',      'bi-award-fill',       t.field_belt,      !entry.belt_colour],
            ['club',      'bi-building',         t.field_club,      !entry.club],
        ];

        return want
            .filter(([field, , , isMissing]) => isMissing && editable(field))
            .map(([field, icon, label]) => ({ field, icon, label }));
    }, [windowOpen, photoUsable, entry, editable, t]);

    /* One tap from the prompt to the thing it is asking for. The photograph has
       its own sheet, which lives in Blade (one cropper per project). */
    const askFor = useCallback((field) => {
        if (field === 'photo') { window.dispatchEvent(new CustomEvent('entry-photo:open')); return; }

        openSheet(field);
    }, [openSheet]);

    const avatarHtml = (props.avatars || {})[
        ['f', 'female', 'woman', 'girl'].indexOf(String(entry.gender || '').toLowerCase()) >= 0 ? 'female' : 'male'
    ] || '';

    /* ===== Render (markup copied from the Blade panel) ================= */

    return (
        <div className="-mx-4 -mt-4">

            {/* ===== The band — enrol-mine's, verbatim ===== */}
            <header
                className="relative overflow-hidden text-white"
                style={{ padding: '22px 24px 26px', background: `linear-gradient(155deg, ${theme.evDeep} 0%, ${theme.evFade} 100%)` }}
            >
                <div className="absolute rounded-full" style={{ right: '-56px', top: '-56px', width: '190px', height: '190px', background: 'rgba(255,255,255,.07)' }}></div>

                <div className="flex items-center justify-between gap-3 relative z-10">
                    <a
                        href={props.backUrl}
                        className="m-press ev-ico inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
                        aria-label={t.back} title={t.back}
                    >
                        <i className="bi bi-chevron-left rtl:rotate-180"></i>
                    </a>
                </div>

                <div className="relative z-10" style={{ marginTop: '22px' }}>
                    <span className="flex items-center" style={{ gap: '10px' }}>
                        <span className="flex-none" style={{ width: '38px', height: '3px', borderRadius: '2px', background: 'rgba(255,255,255,.85)' }}></span>
                        <span className="uppercase truncate" style={{ fontSize: '11px', fontWeight: 600, letterSpacing: '.2em', color: 'rgba(255,255,255,.85)' }}>{props.eventTitle}</span>
                    </span>

                    <h1 style={{ margin: '12px 0 0', fontSize: '23px', lineHeight: 1.2, fontWeight: 700, letterSpacing: '-.01em' }}>{t.title}</h1>

                    <p style={{ margin: '9px 0 0', fontSize: '13px', color: 'rgba(255,255,255,.82)' }}>{t.subtitle}</p>
                </div>
            </header>

            <div className="mx-auto w-full max-w-lg px-4 -mt-3 relative z-10">
                <div className="pb-[max(4rem,calc(3rem+env(safe-area-inset-bottom)))] mobile-stagger space-y-3.5">

                    {/* ===== The window, when it is shut =====
                        One calm sentence at the top, not an error beside every
                        field. It is not the athlete's mistake that the
                        competition started. */}
                    {!windowOpen ? (
                        <div className="rounded-2xl px-4 py-3.5 flex items-start gap-2.5"
                             style={{ background: '#f1f5f9', border: '1px solid hsl(210 14% 88%)' }}>
                            <i className="bi bi-lock-fill mt-0.5 text-muted-foreground"></i>
                            <p className="text-[12.5px] text-muted-foreground leading-snug">{perm.window && perm.window.reason}</p>
                        </div>
                    ) : null}

                    {/* ===== What the last save changed ===== */}
                    {notices.map((n) => (
                        <div
                            key={n.id}
                            className="rounded-2xl px-4 py-3.5 flex items-start gap-2.5"
                            style={n.tone === 'warn'
                                ? { background: '#fef3c7', border: '1px solid #fcd34d', color: '#92400e' }
                                : { background: theme.evA10, border: `1px solid ${theme.evA30}`, color: theme.evDeep }}
                        >
                            <i className={`bi mt-0.5 ${n.tone === 'warn' ? 'bi-exclamation-triangle-fill' : 'bi-diagram-3 bracket-icon'}`}></i>
                            <p className="text-[12.5px] font-bold leading-snug flex-1">{n.text}</p>
                            <button type="button" onClick={() => dismiss(n.id)} className="m-press flex-shrink-0" aria-label={t.close}>
                                <i className="bi bi-x-lg text-xs"></i>
                            </button>
                        </div>
                    ))}

                    {/* ===== Waiting to be let out =====
                        While a request is pending the withdraw button is gone:
                        there is nothing to ask twice, and the only useful
                        action left is taking it back. */}
                    {pending ? (
                        <div className="rounded-2xl p-4" style={{ background: '#fef3c7', border: '1px solid #fcd34d' }}>
                            <p className="text-[12.5px] font-bold leading-snug flex items-start gap-2" style={{ color: '#92400e' }}>
                                <i className="bi bi-hourglass-split mt-0.5"></i>{t.withdraw_pending_banner}
                            </p>
                            {withdrawal.reason ? (
                                <p className="text-[11.5px] mt-1.5 ps-6" style={{ color: '#b45309' }}>{withdrawal.reason}</p>
                            ) : null}
                            <button
                                type="button" onClick={takeBack} disabled={saving}
                                className={`m-press mt-3 w-full h-11 rounded-2xl text-[12.5px] font-black text-white ${saving ? 'opacity-40' : ''}`}
                                style={{ background: '#b45309' }}
                            >
                                {t.withdraw_take_back}
                            </button>
                        </div>
                    ) : null}

                    {/* ===== Finish your entry =====
                        The Blade panel's prompt, mirrored. The door only asks
                        for a name, a number and a password since 2026-09-05, so
                        almost everything an organiser needs arrives here blank
                        and this is what asks for it. The photograph first — the
                        draw and the hall screens introduce a competitor with
                        their face. Warm, never a nag: nothing is required and
                        nothing is blocked, and each chip leaves as it is
                        filled, off the same `entry` state every save patches. */}
                    {missing.length ? (
                        <div className="fin m-card">
                            <div className="flex items-start gap-3">
                                <span className="fin-tile"><i className="bi bi-stars"></i></span>
                                <div className="min-w-0 flex-1">
                                    <p className="fin-title">{t.finish_title}</p>
                                    <p className="fin-hint">{t.finish_hint}</p>
                                </div>
                                <span className="fin-count">{missing.length}</span>
                            </div>

                            <div className="fin-chips">
                                {missing.map((m) => (
                                    <button
                                        key={m.field} type="button" onClick={() => askFor(m.field)}
                                        className={`fin-chip m-press${m.field === 'photo' ? ' is-key' : ''}`}
                                    >
                                        <i className={`bi ${m.icon}`}></i>
                                        <span>{m.label}</span>
                                        <i className="bi bi-chevron-right rtl:rotate-180" style={{ fontSize: '9px', opacity: .6 }}></i>
                                    </button>
                                ))}
                            </div>

                            {/* The "why the photo matters" line lives on the pass
                                card, beside the photograph. Printing it here too
                                put the same sentence on screen twice. */}
                        </div>
                    ) : null}

                    {/* ===== The pass =====
                        The photograph, the name and the division drawn as the
                        object they become: the event's band across the top, the
                        portrait plate riding up over its tail, the actions quiet
                        underneath. Empty, the gendered silhouette shows through
                        a wash of the event's colour with one shutter on it — a
                        human shape says "your face goes here" better than an
                        outline and a "3:4" label did, and the amber "a photo is
                        needed" pill it replaced read as an error when nothing
                        had gone wrong.

                        PORTRAIT 3:4 at every state (CLAUDE.md), so nothing jumps
                        when a new picture lands. The camera button hands off to
                        the Blade half — see this file's header. The `.pass-*`
                        rules live in my-entry.blade.php's shared style push,
                        outside the feature flag, so both paths wear them. */}
                    <div className="pass m-card">
                        <div className="pass-band">
                            <span className="pass-orb" style={{ insetInlineEnd: '-44px', top: '-56px', width: '150px', height: '150px' }}></span>
                            <span className="pass-orb" style={{ insetInlineEnd: '38px', bottom: '-26px', width: '70px', height: '70px', background: 'rgba(255,255,255,.06)' }}></span>

                            <div className="relative flex items-center justify-between gap-2">
                                <span className="pass-eyebrow">{t.field_photo}</span>
                                {/* The division, said plainly — including when there is not
                                    one yet, because "blank" and "not placed" read the same
                                    and only one of them is true. */}
                                <span className="pass-chip">
                                    <i className="bi bi-diagram-3 bracket-icon"></i>
                                    <span className="truncate">{entry.division || t.no_division_yet}</span>
                                </span>
                            </div>
                        </div>

                        <div className="pass-body">
                            <button
                                type="button"
                                onClick={() => editable('photo') && window.dispatchEvent(new CustomEvent('entry-photo:open'))}
                                disabled={!editable('photo')}
                                className="pass-plate m-press"
                                aria-label={photoUsable ? t.photo_change : t.photo_add_cta}
                            >
                                {photoUsable ? (
                                    <img src={entry.photo} alt="" onError={() => setBrokenPhoto(entry.photo)} />
                                ) : (
                                    /* Server-rendered <x-gender-avatar> markup (ours, not user
                                       input). No photograph is not a bug and must be silent. */
                                    <span className="pass-ghost" dangerouslySetInnerHTML={{ __html: avatarHtml }} />
                                )}

                                {!photoUsable && editable('photo') ? (
                                    <span className="pass-wash">
                                        <span className="pass-add"><i className="bi bi-camera-fill"></i></span>
                                    </span>
                                ) : null}

                                {photoUsable && editable('photo') ? (
                                    <span className="pass-fab"><i className="bi bi-camera-fill"></i></span>
                                ) : null}
                            </button>

                            <div className="min-w-0 flex-1" style={{ paddingBottom: '2px' }}>
                                <p className="pass-name truncate">{entry.name}</p>

                                {photoUsable ? (
                                    <span className="pass-state" style={{ color: theme.ev, background: theme.evA10 }}>
                                        <i className="bi bi-check-circle-fill"></i>{t.photo_on_file}
                                    </span>
                                ) : null}
                                {/* No "add a photo" badge when empty: it read as a
                                    third button two inches from the real one. The
                                    empty plate and the single CTA below say it. */}
                            </div>
                        </div>

                        {/* One warm line saying WHY, only while it is missing. */}
                        {!photoUsable ? (
                            <p className="pass-why">{t.finish_photo_why}</p>
                        ) : null}

                        {editable('photo') ? (
                            <div className="pass-acts">
                                <button
                                    type="button"
                                    onClick={() => window.dispatchEvent(new CustomEvent('entry-photo:open'))}
                                    className="pass-cta m-press"
                                >
                                    <i className={`bi ${photoUsable ? 'bi-arrow-repeat' : 'bi-camera-fill'}`}></i>
                                    <span>{photoUsable ? t.photo_change : t.photo_add_cta}</span>
                                </button>

                                {/* Re-frame what is already there. Hidden when the file
                                    behind the URL is missing from disk: there is nothing
                                    to re-crop, and offering it would open an empty
                                    cropper. The cropper itself lives in Blade — see this
                                    file's header — so the button only asks for it. */}
                                {photoUsable ? (
                                    <button
                                        type="button"
                                        onClick={() => window.dispatchEvent(new CustomEvent('entry-photo:recrop', { detail: { src: entry.photo } }))}
                                        className="pass-ico m-press"
                                        aria-label={t.photo_recrop} title={t.photo_recrop_hint}
                                    >
                                        <i className="bi bi-crop"></i>
                                    </button>
                                ) : null}

                                {/* `photoUsable`, not `entry.photo`: a row pointing at
                                    a file that is gone from disk has nothing to
                                    remove, and offering it is confusing. */}
                                {photoUsable ? (
                                    <button
                                        type="button" onClick={() => save({ photo: null })}
                                        className="pass-ico m-press"
                                        aria-label={t.photo_remove} title={t.photo_remove}
                                    >
                                        <i className="bi bi-trash3"></i>
                                    </button>
                                ) : null}
                            </div>
                        ) : (
                            <p className="pass-locked">
                                <i className="bi bi-lock-fill" style={{ marginTop: '2px' }}></i><span>{reason('photo')}</span>
                            </p>
                        )}
                    </div>

                    {/* ===== The weigh-in truth =====
                        A figure an official signed for and a figure somebody
                        typed are not the same fact and must never look the
                        same. */}
                    <div className="m-card rounded-2xl p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-[11px] uppercase font-bold text-muted-foreground" style={{ letterSpacing: '.12em' }}>
                                    {t.field_weight}
                                </p>
                                <p className="mt-1">
                                    <span className="text-[32px] font-black leading-none tabular-nums text-foreground">
                                        {entry.weight !== null && entry.weight !== undefined ? Number(entry.weight).toFixed(1) : '—'}
                                    </span>
                                    {entry.weight !== null && entry.weight !== undefined ? (
                                        <span className="text-[13px] font-bold text-muted-foreground ms-1">kg</span>
                                    ) : null}
                                </p>
                            </div>

                            {editable('weight') ? (
                                <button
                                    type="button" onClick={() => openSheet('weight')}
                                    className="m-press flex-shrink-0 inline-flex items-center gap-1.5 h-10 px-4 rounded-2xl text-[12px] font-black text-white"
                                    style={{ background: theme.ev }}
                                >
                                    <i className="bi bi-pencil-fill text-[11px]"></i>{t.edit}
                                </button>
                            ) : null}
                        </div>

                        <p
                            className="mt-3 inline-flex items-start gap-1.5 px-2.5 py-1.5 rounded-xl text-[11.5px] font-bold leading-snug"
                            style={entry.weighed_in ? { color: '#15803d', background: '#dcfce7' } : { color: '#b45309', background: '#fef3c7' }}
                        >
                            <i className={`bi mt-0.5 ${entry.weighed_in ? 'bi-check-circle-fill' : 'bi-info-circle-fill'}`}></i>
                            <span>{entry.weighed_in ? t.weighed_in : t.self_declared}</span>
                        </p>

                        {!editable('weight') ? (
                            <p className="text-[11px] text-muted-foreground mt-2 flex items-start gap-1.5">
                                <i className="bi bi-lock-fill mt-0.5"></i><span>{reason('weight')}</span>
                            </p>
                        ) : null}
                    </div>

                    {/* ===== The rest, as rows =====
                        One screen, one job: a row states the fact and opens a
                        sheet that asks for exactly that one thing. */}
                    <div className="m-card rounded-2xl overflow-hidden">
                        {rows.map((row, i) => (
                            <div key={row.field}>
                                {i > 0 ? <div className="border-t border-gray-100"></div> : null}
                                <button
                                    type="button"
                                    onClick={() => editable(row.field) && openSheet(row.field)}
                                    disabled={!editable(row.field)}
                                    className="m-press w-full px-4 py-3.5 flex items-center gap-3 text-start"
                                >
                                    <span className="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                          style={{ color: theme.ev, background: theme.evA10 }}>
                                        <i className={`bi ${row.icon}`}></i>
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[11px] font-bold uppercase text-muted-foreground" style={{ letterSpacing: '.1em' }}>
                                            {row.label}
                                        </span>
                                        <span className="block text-[13.5px] font-black text-foreground truncate mt-0.5">
                                            {row.field === 'nationality' && entry.nationality ? (
                                                <span className={`fi fi-${String(entry.nationality).toLowerCase()} inline-block align-middle me-1.5`}
                                                      style={{ width: '20px', height: '15px', borderRadius: '3px' }}></span>
                                            ) : null}
                                            {rowValue(row.field)}
                                        </span>
                                        {!editable(row.field) ? (
                                            <span className="block text-[11px] text-muted-foreground mt-1">{reason(row.field)}</span>
                                        ) : null}
                                    </span>
                                    <i className={`bi flex-shrink-0 text-muted-foreground ${editable(row.field) ? 'bi-chevron-right rtl:rotate-180' : 'bi-lock-fill'}`}></i>
                                </button>
                            </div>
                        ))}
                    </div>

                    {/* ===== Leaving =====
                        Low emphasis, at the very bottom. Withdrawing is a real
                        answer, not a mistake — but it is also not what most
                        people opened this for. */}
                    {!pending && windowOpen ? (
                        <div className="pt-2 text-center">
                            <button
                                type="button" onClick={() => openSheet('withdraw')}
                                className="m-press inline-flex items-center gap-2 text-[12.5px] font-bold text-muted-foreground"
                            >
                                <i className="bi bi-box-arrow-left rtl:rotate-180"></i>{t.withdraw_action}
                            </button>
                        </div>
                    ) : null}
                </div>
            </div>

            {/* ================= The one sheet =================
                Every field opens the SAME sheet with a different body. One
                header band, one scroll body, one sticky footer.

                Portalled to <body>, which is React's x-teleport: the wrapper
                above carries `mobile-stagger`, whose animation leaves a
                transform on its children, and a transform makes that element
                the containing block for anything `position: fixed` inside it —
                a sheet left in place resolves `bottom-0` against a
                few-hundred-pixel wrapper and is clipped. */}
            {sheet ? createPortal(
                <div className="fixed inset-0 z-[60]">
                    <div onClick={closeSheet} className="absolute inset-0 bg-black/50"></div>

                    <div className="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                        {/* The band. The gradient is `#hex → #hex + b0`: the
                            alpha suffix is HEX-ONLY, and an hsl() with it
                            appended is an invalid gradient the browser drops
                            whole. */}
                        <div className="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                             style={{ background: `linear-gradient(150deg, ${theme.ev}, ${theme.ev}b0)` }}>
                            <div className="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                            <div className="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                            <div className="relative flex items-start gap-3">
                                <span className="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                    <i className={`bi text-xl ${sheetIcon}`}></i>
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-lg font-black leading-tight">{sheetTitle}</h3>
                                    <p className="text-[12px] text-white/85 mt-0.5">{sheetHint}</p>
                                </div>
                                <button
                                    type="button" onClick={closeSheet} aria-label={t.close}
                                    className="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"
                                >
                                    <i className="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto px-5 pt-4 pb-3">

                            {/* ---- Weight ---- */}
                            {sheet === 'weight' ? (
                                <div>
                                    <div className="m-card rounded-2xl p-4">
                                        <div className="flex items-center justify-between">
                                            <label className="text-[12px] font-bold text-foreground">{t.claim_weight}</label>
                                            {draft.weight ? (
                                                <button type="button" onClick={() => setDraft((d) => ({ ...d, weight: null }))}
                                                        className="m-press text-[11px] font-bold text-muted-foreground">{t.clear}</button>
                                            ) : null}
                                        </div>
                                        <div className="flex items-center justify-center gap-5 mt-2">
                                            <button type="button" onClick={() => bump(-0.5)}
                                                    className="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                                <i className="bi bi-dash-lg"></i>
                                            </button>
                                            <div className="text-center min-w-[7rem]">
                                                <span className="text-[40px] font-black leading-none tabular-nums text-foreground">
                                                    {draft.weight ? Number(draft.weight).toFixed(1) : '—'}
                                                </span>
                                                <span className="text-[13px] font-bold text-muted-foreground ms-1">kg</span>
                                            </div>
                                            <button type="button" onClick={() => bump(0.5)}
                                                    className="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                                <i className="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                        <input
                                            type="range" min="20" max="140" step="0.5" className="e-range w-full mt-3"
                                            value={draft.weight == null ? 60 : draft.weight}
                                            onChange={(e) => setDraft((d) => ({ ...d, weight: Number(e.target.value) }))}
                                        />
                                        {!draft.weight ? (
                                            <p className="text-[11.5px] text-muted-foreground mt-2 text-center">{t.claim_weight_blank}</p>
                                        ) : null}
                                    </div>

                                    {/* Said BEFORE they save, not after: changing a figure an
                                        official signed for sends them back to the desk, and
                                        that is worth knowing while the slider is still under
                                        a thumb. */}
                                    {entry.weighed_in ? (
                                        <p className="mt-3 rounded-2xl px-3.5 py-3 text-[11.5px] font-bold leading-snug flex items-start gap-2"
                                           style={{ background: '#fef3c7', color: '#92400e' }}>
                                            <i className="bi bi-exclamation-triangle-fill mt-0.5"></i>
                                            <span>{t.reweigh_warning}</span>
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}

                            {/* ---- Name ----
                                The one field that is never optional: a competitor with no
                                name breaks every listing, card and search result on the
                                platform, so the server refuses a blank and so does this.
                                Two characters is the server's own floor. */}
                            {sheet === 'name' ? (
                                <div>
                                    <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.field_name}</label>
                                    <input
                                        type="text" maxLength={120} autoComplete="name" value={draft.name || ''}
                                        onChange={(e) => setDraft((d) => ({ ...d, name: e.target.value }))}
                                        className="e-field w-full h-12 px-4 rounded-2xl text-[15px]"
                                    />
                                    <p className="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                                        <i className="bi bi-info-circle-fill mt-0.5"></i>
                                        <span>{String(draft.name || '').trim().length < 2 ? t.name_min : t.name_hint}</span>
                                    </p>
                                </div>
                            ) : null}

                            {/* ---- Nationality ----
                                The min-height is load-bearing — the picker's panel is
                                absolutely positioned exactly as the Blade component's is,
                                and a one-field sheet is shorter than the panel, so without
                                it the list would be clipped by this scrolling body. */}
                            {sheet === 'nationality' ? (
                                <div style={{ minHeight: '340px' }}>
                                    <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.field_nationality}</label>
                                    <CountryPicker
                                        value={draft.nationality || ''}
                                        countries={countries}
                                        onChange={(v) => setDraft((d) => ({ ...d, nationality: v }))}
                                        t={t}
                                    />
                                </div>
                            ) : null}

                            {/* ---- Belt ---- */}
                            {sheet === 'belt' ? (
                                <div>
                                    <label className="block text-[12px] font-bold text-foreground mb-2">{t.claim_belt}</label>
                                    <div className="grid grid-cols-3 gap-2">
                                        {belts.map((b) => (
                                            <button
                                                key={b.value}
                                                type="button"
                                                onClick={() => setDraft((d) => ({ ...d, belt_colour: d.belt_colour === b.value ? null : b.value }))}
                                                className={`m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5 ${draft.belt_colour === b.value ? 'is-on' : ''}`}
                                            >
                                                <span className="w-8 h-2.5 rounded-full border border-white/25" style={{ background: b.bg }}></span>
                                                <span className="text-[11.5px] font-bold">{b.label}</span>
                                            </button>
                                        ))}
                                    </div>

                                    <div className="mt-4">
                                        <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.field_grade}</label>
                                        <input
                                            type="text" maxLength={32} value={draft.belt_grade || ''}
                                            onChange={(e) => setDraft((d) => ({ ...d, belt_grade: e.target.value }))}
                                            className="e-field w-full h-12 px-4 rounded-2xl text-[15px]"
                                        />
                                    </div>
                                </div>
                            ) : null}

                            {/* ---- Competing for ----
                                Selection cards, not a dropdown: the answer set is short,
                                known and already scoped by the server to the clubs this
                                athlete is actually an active member of. An organiser
                                editing their OWN entry gets the search as well. */}
                            {sheet === 'club' ? (
                                <div>
                                    <div className="space-y-2">
                                        <button
                                            type="button" onClick={() => setDraft((d) => ({ ...d, club: '' }))}
                                            className={`m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start ${!draft.club ? 'is-on' : ''}`}
                                        >
                                            <span className="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                                  style={{ color: theme.ev, background: theme.evA10 }}>
                                                <i className="bi bi-person"></i>
                                            </span>
                                            <span className="min-w-0 flex-1 text-[13.5px] font-bold text-foreground">{t.no_club}</span>
                                            {!draft.club ? <i className="bi bi-check-lg flex-shrink-0" style={{ color: theme.ev }}></i> : null}
                                        </button>

                                        {clubOptions.map((c) => (
                                            <button
                                                key={c.slug} type="button" onClick={() => setDraft((d) => ({ ...d, club: c.slug }))}
                                                className={`m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start ${draft.club === c.slug ? 'is-on' : ''}`}
                                            >
                                                {c.logo ? (
                                                    /* A logo is a transparent PNG on a bare sizing box —
                                                       never a white tile (Design Rule #5). */
                                                    <span className="w-9 h-9 flex-shrink-0">
                                                        <img src={c.logo} alt="" className="w-full h-full object-contain" />
                                                    </span>
                                                ) : (
                                                    <span className="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                                          style={{ color: theme.ev, background: theme.evA10 }}>
                                                        <i className="bi bi-building"></i>
                                                    </span>
                                                )}
                                                <span className="min-w-0 flex-1 text-[13.5px] font-bold text-foreground truncate">{c.name}</span>
                                                {c.country ? (
                                                    <span className={`fi fi-${String(c.country).toLowerCase()} flex-shrink-0`}
                                                          style={{ width: '20px', height: '15px', borderRadius: '3px' }}></span>
                                                ) : null}
                                                {draft.club === c.slug ? <i className="bi bi-check-lg flex-shrink-0" style={{ color: theme.ev }}></i> : null}
                                            </button>
                                        ))}
                                    </div>

                                    {perm.role === 'organiser' ? (
                                        <div className="mt-4">
                                            <div className="relative">
                                                <input
                                                    type="text" value={clubQuery} onChange={(e) => onClubQuery(e.target.value)}
                                                    placeholder={t.club_search}
                                                    className="e-field w-full h-12 ps-10 pe-4 rounded-2xl text-[15px]"
                                                />
                                                <i className="bi bi-search absolute start-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                                            </div>
                                            {clubResults.length ? (
                                                <div className="mt-2 space-y-1.5">
                                                    {clubResults.map((c) => (
                                                        <button
                                                            key={`s${c.slug}`} type="button" onClick={() => adopt(c)}
                                                            className="m-press e-pick w-full rounded-2xl px-3 py-2.5 flex items-center gap-3 text-start"
                                                        >
                                                            <span className="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate">{c.name}</span>
                                                            {c.country ? (
                                                                <span className={`fi fi-${String(c.country).toLowerCase()} flex-shrink-0`}
                                                                      style={{ width: '20px', height: '15px', borderRadius: '3px' }}></span>
                                                            ) : null}
                                                        </button>
                                                    ))}
                                                </div>
                                            ) : null}
                                            {clubQuery.trim().length >= 2 && !clubResults.length && !clubSearching ? (
                                                <p className="text-[11.5px] text-muted-foreground mt-2">{t.club_none_found}</p>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            {/* ---- Date of birth ----
                                Never demanded of anyone (CLAUDE.md). The picker expands IN
                                FLOW, so it cannot be clipped by this scrolling body. */}
                            {sheet === 'birthdate' ? (
                                <div>
                                    <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.field_birthdate}</label>
                                    <DatePartsPicker
                                        value={draft.birthdate || ''}
                                        max={props.maxBirthdate}
                                        onChange={(v) => setDraft((d) => ({ ...d, birthdate: v }))}
                                        t={t}
                                    />
                                    {!draft.birthdate ? (
                                        <p className="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                                            <i className="bi bi-info-circle-fill mt-0.5"></i>
                                            <span>{t.claim_birthdate_blank}</span>
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}

                            {/* ---- Gender ---- */}
                            {sheet === 'gender' ? (
                                <div>
                                    <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.field_gender}</label>
                                    <GenderToggle
                                        value={draft.gender || ''}
                                        onChange={(v) => setDraft((d) => ({ ...d, gender: v }))}
                                        t={t}
                                    />
                                </div>
                            ) : null}

                            {/* ---- Withdraw ----
                                A sheet, never a native confirm(). It explains who actually
                                decides before it asks for anything. */}
                            {sheet === 'withdraw' ? (
                                <div>
                                    <p className="text-[12.5px] text-muted-foreground leading-snug">{t.withdraw_explain}</p>

                                    <div className="mt-4">
                                        <label className="block text-[12px] font-bold text-foreground mb-1.5">{t.withdraw_reason_label}</label>
                                        <textarea
                                            maxLength={300} rows={3} value={draft.reason || ''}
                                            onChange={(e) => setDraft((d) => ({ ...d, reason: e.target.value }))}
                                            className="e-field w-full px-4 py-3 rounded-2xl text-[15px]"
                                        ></textarea>
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        {/* The footer. It belongs to a sheet that has something to
                            SUBMIT — every one of these does. */}
                        <div className="flex-shrink-0 px-5 pt-3 bg-white border-t border-gray-100"
                             style={{ paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom))' }}>
                            <button
                                type="button" onClick={commit} disabled={saving}
                                className={`m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2 text-white ${saving ? 'opacity-40' : ''}`}
                                style={{ background: theme.ev, boxShadow: `0 18px 40px -18px ${theme.ev}` }}
                            >
                                <span>{saving ? '…' : (sheet === 'withdraw' ? t.withdraw_action : t.save)}</span>
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            ) : null}
        </div>
    );
}

/* ===== Entry: register the island =====
   Registering at module top level is safe — `mountIsland` only RECORDS the
   island here; the actual mount/unmount is driven by `shell:navigated` (plus
   one immediate attempt for the first paint). The sealed event surface has no
   shell navigator today, but the island is written to survive one. */
mountIsland('#entry-island', (el) => {
    let props = {};
    let i18n = {};
    try {
        props = JSON.parse(el.getAttribute('data-island-props') || '{}');
    } catch (e) {
        console.error('[entry island] bad props', e);
    }
    try {
        // Copy is the SERVER's, always — the island never carries English of
        // its own and never builds a second translation system.
        i18n = JSON.parse(el.getAttribute('data-i18n') || '{}');
    } catch (e) {
        console.error('[entry island] bad i18n', e);
    }
    return <EntryPanel props={props} t={i18n} />;
});
