{{-- The championship workspace's behaviour.

     Registered through Alpine.data() — not an inline x-data object — because
     the sheets live inside <template x-teleport="body">, where an inline
     script is inert. Both registration paths are covered, since the mobile
     shell may swap this page in long after Alpine started.

     The server owns every rule. This file only collects what the organiser
     typed, sends it, and re-draws the division the response carries. --}}
@php $m = 'event-sport_championship::messages.'; @endphp
@once
@push('scripts')
<script>
(function () {
    const T = {
        formats: {
            knockout: @js(__($m.'format_knockout')), round_robin: @js(__($m.'format_round_robin')),
            groups_knockout: @js(__($m.'format_groups_knockout')), league: @js(__($m.'format_league')),
            swiss: @js(__($m.'format_swiss')), heats: @js(__($m.'format_heats')), open: @js(__($m.'format_open')),
        },
        entrants: { individual: @js(__($m.'entrant_individual')), pair: @js(__($m.'entrant_pair')), team: @js(__($m.'entrant_team')) },
        entrants0: @js(trans_choice($m.'entrants_count', 0, ['n' => 0])),
        entrants1: @js(trans_choice($m.'entrants_count', 1, ['n' => 1])),
        entrantsN: @js(trans_choice($m.'entrants_count', 5, ['n' => ':n'])),
        notDrawn: @js(__($m.'not_drawn')), played: @js(__($m.'played', ['done' => ':done', 'total' => ':total'])), decided: @js(__($m.'status_done')),
        set: @js(__($m.'set_n', ['n' => ':n'])), attempt: @js(__($m.'attempt_n', ['n' => ':n'])), judge: @js(__($m.'judge_n', ['n' => ':n'])),
        heat: @js(__($m.'heat', ['n' => ':n'])), final: @js(__($m.'final')), record: @js(__($m.'record')),
        sidesTitle: @js(__($m.'sides_title')), sideNew: @js(__($m.'side_new')), addMember: @js(__($m.'add_member')),
        sidesCount: @js(__($m.'sides_count', ['n' => ':n'])), sidesNone: @js(__($m.'sides_none')),
        confirmRedraw: @js(__($m.'confirm_redraw')), confirmDeleteSide: @js(__($m.'confirm_delete_side')),
        status: { ok: @js(__($m.'status_ok')), dns: @js(__($m.'dns')), dnf: @js(__($m.'dnf')), dq: @js(__($m.'dq')) },
        error: @js(__('shared.something_went_wrong')),
    };

    const define = (Alpine) => Alpine.data('championshipWorkspace', (cfg) => ({
        uuid: cfg.uuid,
        urls: cfg.urls,
        c: cfg.c,

        open: null,      // a division id, 'sides', or null (the hub)
        sheet: null,     // 'contest' | 'performance' | 'side' | 'member'
        contest: null,
        perf: null,
        pickFor: null,
        search: '',
        busy: false,
        form: {},

        init() {
            // Other organisers' changes: a refresh signal, deduped on window
            // because the mobile shell re-runs this script on every visit.
            if (window.__champRealtime) window.removeEventListener('realtime:events', window.__champRealtime);
            window.__champRealtime = (ev) => {
                const d = ev.detail || {};
                if ((d.action === 'competition' || d.action === 'sides') && d.event === this.uuid) this.reload();
            };
            window.addEventListener('realtime:events', window.__champRealtime);

            const hash = (location.hash || '').slice(1);
            if (hash === 'sides' || this.c.divisions.some(d => String(d.id) === hash)) this.openPanel(hash === 'sides' ? 'sides' : Number(hash));
        },

        /* ---------------- reading ---------------- */

        get div() { return typeof this.open === 'number' ? this.c.divisions.find(d => d.id === this.open) || null : null; },
        get entrantLabel() { return T.entrants[this.c.entrant] || ''; },
        formatLabel(f) { return T.formats[f] || f; },

        divisionSummary(d) {
            const n = d.entrants === 0 ? T.entrants0 : (d.entrants === 1 ? T.entrants1 : T.entrantsN.replace(':n', d.entrants));
            if (d.podium.length) return n + ' · ' + T.decided;
            const real = d.contests.filter(m => !m.bye);
            if (real.length) return n + ' · ' + T.played.replace(':done', real.filter(m => m.status === 'done').length).replace(':total', real.length);
            if (d.performances.length) return n + ' · ' + T.played.replace(':done', d.performances.filter(p => p.value !== null || p.status !== 'ok').length).replace(':total', d.performances.length);
            return n + ' · ' + T.notDrawn;
        },

        get sidesSummary() { return this.c.sides.length ? T.sidesCount.replace(':n', this.c.sides.length) : T.sidesNone; },
        get sidesReady() { return this.c.sides.length > 0 && this.c.sides.every(s => s.complete); },

        get contestGroups() {
            if (!this.div) return [];
            const groups = [];
            for (const m of this.div.contests) {
                let g = groups.find(x => x.label === m.round);
                if (!g) groups.push(g = { label: m.round, knockout: String(m.phase || '').startsWith('ko:'), items: [], done: 0 });
                g.items.push(m);
                if (m.status === 'done') g.done++;
            }
            return groups;
        },

        get heatGroups() {
            if (!this.div) return [];
            const groups = [];
            for (const p of this.div.performances) {
                const label = p.round === 'final' ? T.final : T.heat.replace(':n', p.heat);
                let g = groups.find(x => x.label === label);
                if (!g) groups.push(g = { label, final: p.round === 'final', items: [] });
                g.items.push(p);
            }
            // The final first: it is the answer.
            return groups.sort((a, b) => (b.final ? 1 : 0) - (a.final ? 1 : 0));
        },

        recordable(m) { return !m.bye && !!m.a && !!m.b; },

        perfValue(p) {
            if (p.status && p.status !== 'ok') return (T.status[p.status] || p.status).toUpperCase();
            if (p.value === null || p.value === undefined) return '';
            const v = Number(p.value);
            if (this.c.result === 'time') {
                if (v < 60) return v.toFixed(2);
                const min = Math.floor(v / 60), sec = (v - min * 60).toFixed(2).padStart(5, '0');
                return min + ':' + sec;
            }
            return String(+v.toFixed(3)) + (this.c.scoring.unit && this.c.result !== 'time' ? ' ' + this.c.scoring.unit : '');
        },

        get statuses() { return ['ok', 'dns', 'dnf', 'dq'].map(key => ({ key, label: T.status[key] })); },
        setLabel(i) { return T.set.replace(':n', i + 1); },
        attemptLabel(i) { return (this.c.result === 'marks' ? T.judge : T.attempt).replace(':n', i + 1); },

        get candidates() {
            if (!this.pickFor) return [];
            const cat = this.pickFor.category_id;
            const taken = new Set(this.c.sides.filter(s => !cat || s.category_id === cat).flatMap(s => s.members.map(m => m.registration_id)));
            const q = this.search.trim().toLowerCase();
            return this.c.entries
                .filter(p => (!cat || p.category_id === cat) && !taken.has(p.id))
                .filter(p => !q || String(p.name || '').toLowerCase().includes(q));
        },

        /* ---------------- the sheets ---------------- */

        get sheetTitle() {
            if (this.sheet === 'contest' && this.contest) return this.contest.a + ' · ' + this.contest.b;
            if (this.sheet === 'performance' && this.perf) return this.perf.name;
            if (this.sheet === 'side') return T.sideNew;
            if (this.sheet === 'member' && this.pickFor) return this.pickFor.display_name;
            return '';
        },
        get sheetSub() {
            if (this.sheet === 'contest' && this.contest) return (this.contest.no ? '#' + this.contest.no + ' · ' : '') + this.contest.round;
            if (this.sheet === 'performance' && this.perf) return (this.perf.round === 'final' ? T.final : T.heat.replace(':n', this.perf.heat)) + ' · #' + this.perf.lane;
            if (this.sheet === 'side' || this.sheet === 'member') return this.sheet === 'member' ? T.addMember : T.sidesTitle;
            return '';
        },
        get sheetIcon() {
            return { contest: 'bi-pencil-square', performance: 'bi-stopwatch', side: 'bi-people-fill', member: 'bi-person-plus' }[this.sheet] || 'bi-trophy';
        },

        get needsDecider() {
            if (!this.contest || this.c.result === 'sets') return false;
            if (this.form.a === '' || this.form.b === '' || Number(this.form.a) !== Number(this.form.b)) return false;
            return String(this.contest.phase || '').startsWith('ko:') || !this.c.draws;
        },

        openPanel(id) {
            this.open = id;
            try { history.replaceState(null, '', '#' + id); } catch (e) {}
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        openContest(m) {
            if (!this.recordable(m)) return;
            const bestOf = Math.max(1, Number(this.c.scoring.best_of || 3));
            const had = (m.detail && m.detail.sets) || [];
            const num = (v) => (v === null || v === undefined || v === '' || isNaN(Number(v))) ? '' : Number(v);
            this.contest = m;
            this.form = {
                sets: Array.from({ length: bestOf }, (_, i) => had[i] ? [had[i][0], had[i][1]] : ['', '']),
                a: num(m.a_score), b: num(m.b_score),
                pa: m.detail?.penalties?.[0] ?? '', pb: m.detail?.penalties?.[1] ?? '',
                walkover: m.detail?.walkover ? m.winner : null,
            };
            this.sheet = 'contest';
        },

        openPerformance(p) {
            const s = this.c.scoring || {};
            const n = this.c.result === 'marks' ? Number(s.judges || 5) : (this.c.result === 'distance' ? Number(s.attempts || 3) : 1);
            const had = Array.isArray(p.attempts) ? p.attempts : (p.attempts ? Object.values(p.attempts) : []);
            this.perf = p;
            this.form = {
                attempts: Array.from({ length: n }, (_, i) => had[i] ?? (i === 0 && p.value !== null && n === 1 ? p.value : '')),
                difficulty: (p.attempts && p.attempts.dd) || '',
                status: p.status || 'ok',
            };
            this.sheet = 'performance';
        },

        async submitSheet() {
            if (this.sheet === 'contest') return this.saveContest();
            if (this.sheet === 'performance') return this.savePerformance();
            if (this.sheet === 'side') return this.createSide();
        },

        /* ---------------- writing ---------------- */

        async req(url, method, body) {
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) throw new Error(data.message || T.error);
                return data;
            } catch (err) {
                window.showToast('error', err.message);
                return null;
            } finally {
                this.busy = false;
            }
        },

        take(d) {
            if (!d) return false;
            if (d.division) {
                const i = this.c.divisions.findIndex(x => x.id === d.division.id);
                if (i >= 0) this.c.divisions.splice(i, 1, d.division);
            }
            if (d.message) window.showToast('success', d.message);
            return true;
        },

        async act(kind) {
            const d = this.div;
            if (!d) return;
            if (kind === 'draw' && (d.contests.length || d.performances.length)) {
                if (!(await window.confirmAction({ title: T.formats[d.format] || '', message: T.confirmRedraw, type: 'warning' }))) return;
            }
            const url = { draw: this.urls.draw, knockout: this.urls.knockout, next: this.urls.next, final: this.urls.final }[kind];
            this.take(await this.req(url.replace('__ID__', d.id), 'POST'));
        },

        async saveContest() {
            const f = this.form;
            let body;
            if (f.walkover) body = { walkover: f.walkover };
            else if (this.c.result === 'sets') {
                body = { sets: f.sets.filter(s => s[0] !== '' && s[1] !== '').map(s => [Number(s[0]), Number(s[1])]) };
            } else {
                body = { a: f.a === '' ? null : Number(f.a), b: f.b === '' ? null : Number(f.b) };
                if (this.needsDecider && f.pa !== '' && f.pb !== '') body.penalties = [Number(f.pa), Number(f.pb)];
            }
            if (this.take(await this.req(this.urls.contest.replace('__ID__', this.contest.id), 'PUT', body))) this.sheet = null;
        },

        async savePerformance() {
            const f = this.form;
            const body = { status: f.status };
            if (f.status === 'ok') {
                body.attempts = f.attempts.map(v => v === '' || v === null ? null : Number(v));
                if (this.c.scoring.difficulty && f.difficulty !== '') body.difficulty = Number(f.difficulty);
            }
            if (this.take(await this.req(this.urls.performance.replace('__ID__', this.perf.id), 'PUT', body))) this.sheet = null;
        },

        async createSide() {
            const d = await this.req(this.urls.sides, 'POST', { name: this.form.name || null, category_id: this.form.category_id || null });
            if (!d) return;
            window.showToast('success', d.message);
            this.sheet = null;
            await this.reload();
            const made = this.c.sides.find(s => s.uuid === d.side.uuid);
            if (made) { this.pickFor = made; this.search = ''; this.sheet = 'member'; }
        },

        async addMember(p) {
            const d = await this.req(this.urls.sideMembers.replace('__ID__', this.pickFor.uuid), 'POST', { registration_id: p.id });
            if (!d) return;
            this.patchSide(d.side);
            this.pickFor = d.side;
            window.showToast('success', d.message);
            if (d.side.size.max && d.side.players >= d.side.size.max) this.sheet = null;
            this.reload();
        },

        async removeMember(s, m) {
            const d = await this.req(this.urls.sideMember.replace('__ID__', s.uuid).replace('__M__', m.id), 'DELETE');
            if (!d) return;
            this.patchSide(d.side);
            window.showToast('success', d.message);
            this.reload();
        },

        async removeSide(s) {
            if (!(await window.confirmAction({ title: s.display_name, message: T.confirmDeleteSide, type: 'danger' }))) return;
            const d = await this.req(this.urls.side.replace('__ID__', s.uuid), 'DELETE');
            if (!d) return;
            this.c.sides = this.c.sides.filter(x => x.uuid !== s.uuid);
            window.showToast('success', d.message);
            this.reload();
        },

        patchSide(side) {
            const i = this.c.sides.findIndex(x => x.uuid === side.uuid);
            if (i >= 0) this.c.sides.splice(i, 1, side); else this.c.sides.push(side);
        },

        async reload() {
            try {
                const res = await fetch(this.urls.data, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const d = await res.json();
                if (d && d.competition) this.c = d.competition;
            } catch (e) { /* the next signal will try again */ }
        },
    }));

    if (window.Alpine) define(window.Alpine);
    else document.addEventListener('alpine:init', () => define(window.Alpine));
})();
</script>
@endpush
@endonce
