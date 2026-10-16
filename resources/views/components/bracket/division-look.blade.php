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
        if (a1 !== null && a2 !== null) bits.push(a1 === a2 ? a1 + ' yrs' : a1 + '–' + a2 + ' yrs');
        else if (a1 !== null) bits.push(a1 + '+ yrs');
        else if (a2 !== null) bits.push('up to ' + a2 + ' yrs');
        const lo = w1 !== null && w1 > 0, hi = w2 !== null && w2 > 0 && w2 < 200;
        if (lo && hi) bits.push(kg(w1) + '–' + kg(w2) + ' kg');
        else if (lo) bits.push('+' + kg(w1) + ' kg');
        else if (hi) bits.push('−' + kg(w2) + ' kg');
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
