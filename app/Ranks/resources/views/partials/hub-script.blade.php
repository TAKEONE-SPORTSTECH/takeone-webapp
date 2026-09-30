{{-- The ranks hub's state and requests — one copy, used by the desktop page
     (inside @push('scripts')) and the mobile page (inline, for the shell swap).
     Every server value is drawn with x-text / :style, never innerHTML. --}}
<script>
window.ranksHub = function (data) {
    return {
        activities: data.activities || [],
        choices: data.choices || [],
        canChoose: !! data.canChoose,
        urls: data.urls,
        today: data.today,
        sel: null,
        q: '',
        filter: 'all',
        statusLabels: data.statusLabels || {},
        words: data.words || {},
        req: { open: false, act: null, rows: {}, saving: false },
        gr: { open: false, mode: 'new', act: null, title: '', held_on: '', picks: {}, detail: null, results: {}, saving: false, loading: false },
        panel: null,                  // mobile drill-down: an activity uuid
        sheet: { open: false, act: null, m: null, belt: null, grade: null, since: '', note: '', saving: false },
        hist: { open: false, m: null, rows: [], loading: false },
        pkg: { open: false, act: null, saving: false },

        init() {
            const first = this.activities.find(a => a.ranked);
            this.sel = first ? first.uuid : null;
        },
        get ranked() { return this.activities.filter(a => a.ranked); },
        get others() { return this.activities.filter(a => ! a.ranked); },
        get current() { return this.activities.find(a => a.uuid === (this.panel || this.sel)) || null; },
        filtered(a) {
            const q = this.q.trim().toLowerCase();
            let list = (a && a.members) || [];
            if (this.filter !== 'all') list = list.filter(m => m.status === this.filter);
            return q ? list.filter(m => (m.name || '').toLowerCase().includes(q)) : list;
        },
        countStatus(a, st) { return ((a && a.members) || []).filter(m => m.status === st).length; },
        dot(m) {
            return { eligible: 'bg-emerald-500', nearly: 'bg-amber-400', not_yet: 'bg-gray-300', no_rules: 'bg-gray-200', top: 'bg-primary', unranked: 'bg-gray-200' }[m.status] || 'bg-gray-200';
        },
        progressText(m) {
            if (! m.progress) return '';
            const p = m.progress, out = [];
            if (p.classes) out.push(p.classes[0] + '/' + p.classes[1] + ' ' + this.words.classes);
            if (p.months) out.push(p.months[0] + '/' + p.months[1] + ' ' + this.words.months);
            if (p.age) out.push((p.age[0] ?? '–') + '/' + p.age[1] + ' ' + this.words.years);
            return out.join(' · ');
        },
        progressPct(m) {
            if (! m.progress) return 0;
            const r = Object.values(m.progress).map(([h, n]) => n ? Math.min(1, (h || 0) / n) : 1);
            return Math.round(Math.min(...r) * 100);
        },
        eligibleText(a) { return (this.words.eligible || '__N__').replace('__N__', this.countStatus(a, 'eligible')); },
        // ── Gradings ─────────────────────────────────────────────────────
        openNewGrading(a) {
            const picks = {};
            for (const m of a.members) {
                const target = m.next ? m.next.belt : (m.rank ? m.rank.belt : (a.ladder[0] && a.ladder[0].value));
                const st = this.step(a, target);
                picks[m.uuid] = { on: m.status === 'eligible' || m.status === 'nearly', belt: target, grade: st && st.grades.length ? st.grades[0] : null };
            }
            this.gr = { open: true, mode: 'new', act: a, title: data.defaultGradingTitle || '', held_on: this.today, picks, detail: null, results: {}, saving: false, loading: false };
        },
        pickCount() { return Object.values(this.gr.picks).filter(p => p.on).length; },
        async saveNewGrading() {
            const g = this.gr;
            if (g.saving || ! this.pickCount()) return;
            g.saving = true;
            try {
                const candidates = Object.entries(g.picks).filter(([, p]) => p.on).map(([uuid, p]) => ({ uuid, belt: p.belt, grade: p.grade }));
                const res = await fetch(this.url(this.urls.gradings, g.act.uuid), { method: 'POST', headers: this.headers(), body: JSON.stringify({ title: g.title, held_on: g.held_on, candidates }) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not save.');
                Object.assign(g.act, d.activity);
                this.showGradingDetail(g.act, d.grading);
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); } finally { this.gr.saving = false; }
        },
        showGradingDetail(a, detail) {
            const results = {};
            for (const c of detail.candidates) results[c.uuid] = c.result;
            this.gr = { open: true, mode: 'view', act: a, title: detail.title, held_on: detail.held_on, picks: {}, detail, results, saving: false, loading: false };
        },
        async openGrading(a, row) {
            this.gr = { open: true, mode: 'view', act: a, title: row.title, held_on: row.held_on, picks: {}, detail: null, results: {}, saving: false, loading: true };
            try {
                const res = await fetch(this.urlG(a.uuid, row.uuid), { headers: this.headers() });
                const d = await res.json();
                this.showGradingDetail(a, d.grading);
            } catch (e) { window.showToast?.('error', 'Could not load the grading.'); this.gr.loading = false; }
        },
        urlG(l, g) { return this.urls.grading.replace('__L__', encodeURIComponent(l)).replace('__G__', encodeURIComponent(g)); },
        passedCount() { return Object.values(this.gr.results).filter(r => r === 'passed').length; },
        async saveResults(finish) {
            const g = this.gr;
            if (g.saving || ! g.detail) return;
            if (finish && window.confirmAction) {
                const ok = await window.confirmAction({ title: data.words.finishTitle, message: data.words.finishConfirm, type: 'warning', confirmText: data.words.finish });
                if (! ok) return;
            }
            g.saving = true;
            try {
                const res = await fetch(this.urlG(g.act.uuid, g.detail.uuid), { method: 'PUT', headers: this.headers(), body: JSON.stringify({ results: g.results, finish: !! finish }) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not save.');
                Object.assign(g.act, d.activity);
                this.showGradingDetail(g.act, d.grading);
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); } finally { this.gr.saving = false; }
        },
        async deleteGrading() {
            const g = this.gr;
            if (g.saving || ! g.detail) return;
            g.saving = true;
            try {
                const res = await fetch(this.urlG(g.act.uuid, g.detail.uuid), { method: 'DELETE', headers: this.headers() });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not remove.');
                Object.assign(g.act, d.activity);
                g.open = false;
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); } finally { this.gr.saving = false; }
        },

        openReq(a) {
            const rows = {};
            for (const b of a.ladder) {
                const r = (a.requirements || {})[b.value] || {};
                rows[b.value] = { min_classes: r.min_classes ?? '', min_months: r.min_months ?? '', min_age: r.min_age ?? '' };
            }
            this.req = { open: true, act: a, rows, saving: false };
        },
        async saveReq() {
            const r = this.req;
            if (r.saving) return;
            r.saving = true;
            try {
                const res = await fetch(this.url(this.urls.requirements, r.act.uuid), { method: 'PUT', headers: this.headers(), body: JSON.stringify({ rows: r.rows }) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not save.');
                Object.assign(r.act, d.activity);
                r.open = false;
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); } finally { r.saving = false; }
        },
        countRanked(a) { return (a.members || []).filter(m => m.rank).length; },
        step(a, v) { return (a.ladder || []).find(b => b.value === v) || null; },
        groups(a) {
            const out = [];
            for (const b of ((a && a.ladder) || [])) {
                const g = b.group || '';
                let last = out[out.length - 1];
                if (! last || last.group !== g) out.push(last = { group: g, label: (data.groupLabels || {})[g] || g, belts: [] });
                last.belts.push(b);
            }
            return out;
        },
        url(tpl, l, m) { return tpl.replace('__L__', encodeURIComponent(l)).replace('__M__', encodeURIComponent(m || '')); },
        headers() {
            return { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' };
        },

        openSet(a, m) {
            this.sheet = { open: true, act: a, m: m, belt: m.rank ? m.rank.belt : (a.ladder[0] && a.ladder[0].value), grade: m.rank ? m.rank.grade : null,
                since: this.today, note: '', saving: false };
        },
        pickBelt(v) {
            this.sheet.belt = v;
            const s = this.step(this.sheet.act, v);
            if (! s || ! (s.grades || []).includes(this.sheet.grade)) this.sheet.grade = s && s.grades.length ? s.grades[0] : null;
        },
        async save() {
            const s = this.sheet;
            if (s.saving || ! s.belt) return;
            s.saving = true;
            try {
                const res = await fetch(this.url(this.urls.set, s.act.uuid, s.m.uuid), { method: 'POST', headers: this.headers(),
                    body: JSON.stringify({ belt: s.belt, grade: s.grade, since: s.since, note: s.note }) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not save.');
                const i = s.act.members.findIndex(x => x.uuid === d.member.uuid);
                if (i > -1) s.act.members.splice(i, 1, d.member);
                s.open = false;
                window.showToast?.('success', d.message);
                window.dispatchEvent(new CustomEvent('rank-updated', { detail: { activity: s.act.uuid, member: d.member } }));
            } catch (e) { window.showToast?.('error', e.message); } finally { s.saving = false; }
        },
        async openHistory(a, m) {
            this.hist = { open: true, m: m, rows: [], loading: true };
            try {
                const res = await fetch(this.url(this.urls.history, a.uuid, m.uuid), { headers: this.headers() });
                const d = await res.json();
                this.hist.rows = d.history || [];
            } catch (e) { window.showToast?.('error', 'Could not load the history.'); } finally { this.hist.loading = false; }
        },
        openPkg(a) { this.pkg = { open: true, act: a, saving: false }; },
        async savePkg(choice) {
            const p = this.pkg;
            if (p.saving) return;
            p.saving = true;
            try {
                const res = await fetch(this.url(this.urls.package, p.act.uuid), { method: 'PUT', headers: this.headers(),
                    body: JSON.stringify(choice ? { sport: choice.sport, style: choice.style } : { sport: null }) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Could not save.');
                Object.assign(p.act, d.activity);
                p.open = false;
                if (! this.sel && p.act.ranked) this.sel = p.act.uuid;
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); } finally { p.saving = false; }
        },
    };
};
</script>
