{{-- ===== How a division reads in a division menu — ONE copy =====

     The main board's division menu (x-draw-stage) and the second board's
     picker (x-draw-split) draw the same rows: the gender's colour and icon,
     the name, then its ages and weights, sorted youngest → lightest → male
     first (owner, 2026-10-16: "they are not the same, it's confusing").
     Both call this, so the two menus cannot drift apart.

     `@include('components.bracket.division-look')` from any host; it defines
     `window.DrawDivisionLook` once and is safe to include twice. --}}
@once
<script>
window.DrawDivisionLook = window.DrawDivisionLook || {
    /* The division's own range (BracketView), with the name as the fallback
       for a board that predates it. */
    gender(d) {
        if (! d || d.is_heading) return 'mixed';
        const g = d.range ? d.range.gender : null;
        if (g === 'male' || g === 'female') return g;
        if (d?.range) return 'mixed';
        return /\b(female|women)\b/i.test(d?.name || '') ? 'female' : 'male';
    },
    line(d) {
        // A name made from the range already says it ("Group A : 5-6y, 17-23kg",
        // App\Events\Support\GroupName) — not repeated beside it.
        if (/^Group [A-Z] : .+$/.test(String(d?.name || '').trim())) return '';
        const r = d?.range || {}, n = v => (v === null || v === undefined) ? null : Number(v);
        const kg = v => { const x = n(v); return x === null ? '' : String(+x.toFixed(2)); };
        const a1 = n(r.min_age), a2 = n(r.max_age), w1 = n(r.min_weight), w2 = n(r.max_weight);
        const bits = [];
        // Non-breaking: "8–9 yrs" never splits across two lines.
        const NB = '\u00a0';
        // …nor at its dash: word joiners either side keep "8–9" whole.
        const DASH = '\u2060–\u2060';
        if (a1 !== null && a2 !== null) bits.push(a1 === a2 ? a1 + NB + 'yrs' : a1 + DASH + a2 + NB + 'yrs');
        else if (a1 !== null) bits.push(a1 + '+' + NB + 'yrs');
        else if (a2 !== null) bits.push('up to ' + a2 + NB + 'yrs');
        const lo = w1 !== null && w1 > 0, hi = w2 !== null && w2 > 0 && w2 < 200;
        // A name that already carries its weight class ("… Male -25 kg") is
        // not followed by the same class again (2026-10-18).
        if (/(^|\s)[-+−]\s?\d+(\.\d+)?\s*kg\b/i.test(String(d?.name || ''))) return bits.join(' · ');
        if (lo && hi) bits.push(kg(w1) + DASH + kg(w2) + NB + 'kg');
        else if (lo) bits.push('+' + kg(w1) + NB + 'kg');
        else if (hi) bits.push('−' + kg(w2) + NB + 'kg');
        return bits.join(' · ');
    },
    /* A light card with the gender on its leading edge: blue, pink, or a
       neutral slate for mixed; the selected one takes a deeper tint and a ring. */
    style(d, on) {
        const g = this.gender(d);
        const c = g === 'male' ? { edge: '#3b82f6', tint: '#eff6ff', on: '#dbeafe', ring: '#2563eb' }
            : g === 'female' ? { edge: '#ec4899', tint: '#fdf2f8', on: '#fce7f3', ring: '#db2777' }
            : { edge: '#cbd5e1', tint: '#ffffff', on: '#f1f5f9', ring: '#64748b' };
        return 'background:' + (on ? c.on : c.tint) + ';color:#1e2c4f;border:1px solid ' + (on ? c.ring : '#e5e7eb')
            + ';border-inline-start:4px solid ' + c.edge + ';' + (on ? 'box-shadow:0 0 0 1px ' + c.ring + ';' : '');
    },
    /* Blue male, pink female, black for a mixed group. */
    ink(d) {
        const g = this.gender(d);
        return 'color:' + (g === 'male' ? '#1d4ed8' : g === 'female' ? '#be185d' : '#111827') + ';';
    },
    icon(d) {
        const g = this.gender(d);
        return g === 'female' ? 'bi-gender-female' : g === 'male' ? 'bi-gender-male' : 'bi-gender-ambiguous';
    },
    /* The menu's quick filter (owner, 2026-10-17): a name search, a gender
       tab, and age / weight ranges. A division stays when its own range
       OVERLAPS the asked range — "10 to 12 years" keeps the 10–11 group — and
       a division with no bound on a side is open on that side. */
    filter(list, f) {
        f = f || {};
        /* Person mode (the draw menu's 🧍 switch): the list is exactly the
           divisions the people found are in — nothing until a search lands. */
        if (f.mode === 'person') {
            const ids = Array.isArray(f.ids) ? f.ids.map(String) : null;
            return ids ? (list || []).filter(d => ids.includes(String(d.id))) : (list || []);
        }
        const num = v => (v === null || v === undefined || String(v).trim() === '' || isNaN(Number(v))) ? null : Number(v);
        const q = String(f.q || '').trim().toLowerCase();
        const a1 = num(f.a1), a2 = num(f.a2), w1 = num(f.w1), w2 = num(f.w2);
        const overlaps = (lo, hi, from, to) => (from === null || hi === null || hi >= from) && (to === null || lo === null || lo <= to);
        return (list || []).filter(d => {
            if (q && ! String(d.name || '').toLowerCase().includes(q)) return false;
            if (f.g && f.g !== 'all' && this.gender(d) !== f.g) return false;
            const r = d.range || {};
            if (! overlaps(num(r.min_age), num(r.max_age), a1, a2)) return false;
            let top = num(r.max_weight); if (top !== null && (top <= 0 || top >= 200)) top = null;
            let bot = num(r.min_weight); if (bot !== null && bot <= 0) bot = null;
            return overlaps(bot, top, w1, w2);
        });
    },
    /* Age, then weight, then gender — youngest, lightest, male first;
       open-topped classes (+70 kg) last. Headings are left out. */
    sort(list) {
        const n = (v, d) => (v === null || v === undefined || v === '' || isNaN(Number(v))) ? d : Number(v);
        const key = d => {
            const r = d.range || {}, top = n(r.max_weight, 999);
            return [n(r.min_age, 0), n(r.max_age, 999), top <= 0 || top >= 200 ? 999 : top, n(r.min_weight, 0),
                ({male: 0, female: 1, mixed: 2})[this.gender(d)]];
        };
        return (list || []).filter(d => ! d.is_heading).slice().sort((a, b) => {
            const ka = key(a), kb = key(b);
            for (let i = 0; i < ka.length; i++) if (ka[i] !== kb[i]) return ka[i] - kb[i];
            return String(a.name || '').localeCompare(String(b.name || ''), undefined, { numeric: true, sensitivity: 'base' });
        });
    },
};
</script>
@endonce
