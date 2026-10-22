{{--
    The Duplicates page's Alpine state — ONE copy, echoed into the x-data of
    both the desktop and the mobile view, so the two can never behave
    differently. An inline object (not Alpine.data) because the desktop admin
    shell swaps pages in place and runs each script once.
    ⚠️ It sits inside a double-quoted HTML attribute: single quotes only.
    Every server value reaches the DOM through x-text / :href / :src, never innerHTML.
--}}
{
    findings: @js($findings),
    watch: @js($watch),
    autofix: @js($autofix),
    lastRun: @js($lastRun),
    T: @js(trans('duplicates::messages')),
    tab: 'open',
    kind: 'all',
    busy: null,
    kinds: ['all', 'person', 'event_club', 'club', 'entry'],
    icons: { person: 'bi-people', club: 'bi-building', event_club: 'bi-pencil-square', entry: 'bi-card-list', all: 'bi-grid' },

    init() {
        // Another super-admin decided something: re-read (a refresh signal, never data).
        if (window.__dupRealtime) window.removeEventListener('realtime:duplicates', window.__dupRealtime);
        window.__dupRealtime = (e) => { if ((e.detail || {}).action === 'refresh') this.reload(); };
        window.addEventListener('realtime:duplicates', window.__dupRealtime);
    },
    get shown() {
        return this.findings.filter(f => (this.tab === 'open' ? f.status === 'open' : f.status !== 'open') && (this.kind === 'all' || f.kind === this.kind));
    },
    count(kind, tab) {
        const t = tab || this.tab;
        return this.findings.filter(f => (t === 'open' ? f.status === 'open' : f.status !== 'open') && (kind === 'all' || f.kind === kind)).length;
    },
    when(iso) {
        if (! iso) return '';
        try {
            const lang = (document.documentElement.lang || 'en').toLowerCase();
            return new Date(String(iso).replace(' ', 'T')).toLocaleDateString(lang.startsWith('en') ? 'en-GB' : lang, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch (e) { return ''; }
    },
    fill(s, v) { return String(s || '').replace(/:(\w+)/g, (m, k) => (v && k in v) ? v[k] : m); },
    statusLabel(f) {
        if (f.status === 'fixed') return this.T.status_fixed + ' · ' + ((f.fix || {}).by === 'person' ? this.T.fixed_by_person : this.T.fixed_by_watch);
        if (f.status === 'dismissed') return this.T.status_dismissed;
        if (f.status === 'gone') return this.T.status_gone;
        return f.strength === 'certain' ? this.T.certain : this.T.likely;
    },
    sides(f) {
        const s = f.subjects || {};
        const person = (p) => p ? { title: p.name, sub: [p.birth_year ? this.fill(this.T.born, { y: p.birth_year }) : null, p.unclaimed ? this.T.unclaimed : null, p.removed ? this.T.removed : null].filter(Boolean).join(' · '), url: p.url, logo: null, icon: 'bi-person' } : { title: this.T.not_found, sub: '', url: null, logo: null, icon: 'bi-person' };
        const club = (c, sub) => c ? { title: c.name, sub: [sub, c.removed ? this.T.removed : null].filter(Boolean).join(' · '), url: c.url, logo: c.logo, icon: 'bi-building' } : { title: this.T.several_clubs, sub: '', url: null, logo: null, icon: 'bi-buildings' };
        if (f.kind === 'person') return [person(s.a), person(s.b)];
        if (f.kind === 'club') return [club(s.a), club(s.b)];
        if (f.kind === 'event_club') return [
            { title: (s.typed_club || {}).name || this.T.not_found, sub: s.event ? this.fill(this.T.typed_at, { event: s.event.title }) : '', url: (s.event || {}).url || null, logo: null, icon: 'bi-pencil-square' },
            club(s.platform_club, this.T.platform_club),
        ];
        if (f.kind === 'entry') return [person(s.person), { title: (s.event || {}).title || this.T.not_found, sub: this.T.spare_row, url: (s.event || {}).url || null, logo: null, icon: 'bi-calendar-event' }];
        return [];
    },
    headers() {
        return {
            'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        };
    },
    async reload() {
        try {
            const res = await fetch(@js(route('admin.duplicates.list')), { credentials: 'same-origin', headers: this.headers() });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.success) { this.findings = d.findings; this.watch = d.watch; this.autofix = d.autofix; this.lastRun = d.lastRun; }
        } catch (e) { /* the list on screen stays; the next signal re-reads */ }
    },
    async act(f, what) {
        if (this.busy) return;
        if (what === 'dismiss') {
            const ok = await window.confirmAction({ title: this.T.act_dismiss, message: this.T.dismissed, type: 'warning', confirmText: this.T.act_dismiss });
            if (! ok) return;
        }
        this.busy = f.id + what;
        try {
            const url = (what === 'fix' ? @js(route('admin.duplicates.fix', ['finding' => '__F__'])) : @js(route('admin.duplicates.dismiss', ['finding' => '__F__']))).replace('__F__', f.id);
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: this.headers() });
            const d = await res.json().catch(() => ({}));
            if (d.finding) this.findings = this.findings.map(x => x.id === d.finding.id ? d.finding : x);
            if (! res.ok || ! d.success) throw new Error(d.message || this.T.failed);
            window.showToast('success', d.message);
        } catch (e) { window.showToast('error', e.message); }
        finally { this.busy = null; }
    },
}
