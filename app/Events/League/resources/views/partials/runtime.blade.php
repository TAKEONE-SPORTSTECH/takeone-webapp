{{-- The league board's behaviour.

     Registered through Alpine.data() rather than written as an inline x-data
     object, because the sheets live inside <template x-teleport="body"> and a
     script written inside a teleported template is inert — the scope comes back
     empty and every binding fails separately. Both registration paths are
     covered: the mobile shell may swap this page in long after Alpine started.

     The server owns the table. Every write returns the new one and this re-reads
     it rather than recomputing — the order includes a head-to-head tie-break
     that no browser should be reimplementing, and two answers to "who is top"
     is exactly the thing a league cannot have. --}}
@once
@push('scripts')
<script>
(function () {
    const define = (Alpine) => Alpine.data('leagueBoard', (cfg) => ({
        canManage: cfg.canManage,
        canScore: cfg.canScore,
        urls: cfg.urls,
        uuid: cfg.uuid,

        tab: 'table',
        sheet: null,
        busy: false,

        teams: cfg.league.teams || [],
        fixtures: cfg.league.matches || [],
        table: cfg.league.table || [],
        stage: cfg.league.stage || 'draft',

        form: { name: '', short_name: '', rounds: (cfg.league.settings || {}).rounds || 1, fixture: null, home_score: '', away_score: '' },

        init() {
            /* Somebody else's result changes this table. The payload is a
               refresh signal, not the table itself — what a reader may see
               differs — so each screen re-reads its own. Deduped on window
               because the mobile shell re-runs this script on every nav. */
            if (window.__leagueRealtime) {
                window.removeEventListener('realtime:events', window.__leagueRealtime);
            }

            window.__leagueRealtime = (ev) => {
                const d = ev.detail || {};
                if (d.action === 'league' && d.event === this.uuid) this.reload();
            };

            window.addEventListener('realtime:events', window.__leagueRealtime);
        },

        /* ---- What the header and the tray say ---- */

        get tabs() {
            return [
                { key: 'table', icon: 'bi-table', label: @js(__('event-league::messages.table')), count: this.table.length },
                { key: 'fixtures', icon: 'bi-calendar-week', label: @js(__('event-league::messages.fixtures')), count: this.fixtures.length },
            ];
        },

        get stageLabel() {
            return {
                draft: @js(__('event-league::messages.stage_draft')),
                scheduled: @js(__('event-league::messages.stage_scheduled')),
                running: @js(__('event-league::messages.stage_running')),
                completed: @js(__('event-league::messages.stage_completed')),
            }[this.stage] || '';
        },

        get playedLabel() {
            const played = this.fixtures.filter(f => f.played).length;
            return @js(__('event-league::messages.played_of', ['played' => ':p', 'total' => ':t']))
                .replace(':p', played).replace(':t', this.fixtures.length);
        },

        /* Fixtures grouped into the weeks they are played in. */
        get matchdays() {
            const days = new Map();

            this.fixtures.forEach(fx => {
                if (!days.has(fx.matchday)) {
                    days.set(fx.matchday, {
                        no: fx.matchday,
                        label: fx.matchday
                            ? @js(__('event-league::messages.matchday', ['no' => ':n'])).replace(':n', fx.matchday)
                            : @js(__('event-league::messages.fixtures')),
                        date: fx.kickoff || '',
                        fixtures: [],
                    });
                }
                days.get(fx.matchday).fixtures.push(fx);
            });

            return Array.from(days.values()).sort((a, b) => a.no - b.no);
        },

        /* ---- The sheet ---- */

        get sheetTitle() {
            return {
                team: @js(__('event-league::messages.add_team')),
                season: @js(__('event-league::messages.action_generate')),
                result: @js(__('event-league::messages.enter_result')),
            }[this.sheet] || '';
        },

        get sheetSub() {
            if (this.sheet === 'result' && this.form.fixture) {
                return this.form.fixture.home.name + ' — ' + this.form.fixture.away.name;
            }
            if (this.sheet === 'season') return this.teams.length + ' ' + @js(__('event-league::messages.teams'));
            return '';
        },

        get sheetIcon() {
            return { team: 'bi-plus-lg', season: 'bi-calendar-week', result: 'bi-pencil-square' }[this.sheet] || 'bi-table';
        },

        get sheetCta() {
            return this.sheet === 'season' ? @js(__('event-league::messages.generate')) : @js(__('event-league::messages.save'));
        },

        openResult(fx) {
            this.form.fixture = fx;
            this.form.home_score = fx.home_score ?? '';
            this.form.away_score = fx.away_score ?? '';
            this.sheet = 'result';
        },

        submit() {
            if (this.sheet === 'team') return this.saveTeam();
            if (this.sheet === 'season') return this.generate();
            if (this.sheet === 'result') return this.saveResult();
        },

        /* ---- Writes. Each patches in place; none reloads the page. ---- */

        async req(url, method, body) {
            this.busy = true;
            let data = {};

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

                data = await res.json().catch(() => ({}));

                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Something went wrong.');
                }
            } catch (err) {
                this.busy = false;
                window.showToast('error', err.message);
                return null;
            }

            this.busy = false;
            return data;
        },

        apply(d) {
            if (Array.isArray(d.teams)) this.teams = d.teams;
            if (Array.isArray(d.matches)) this.fixtures = d.matches;
            if (Array.isArray(d.table)) this.table = d.table;
            if (d.stage) this.stage = d.stage;
        },

        async saveTeam() {
            if (!this.form.name.trim()) return;

            const d = await this.req(this.urls.teams, 'POST', {
                name: this.form.name.trim(),
                short_name: this.form.short_name.trim() || null,
            });

            if (!d) return;

            this.apply(d);
            this.form.name = '';
            this.form.short_name = '';
            this.sheet = null;
            window.showToast('success', d.message);
        },

        async removeTeam(team) {
            const ok = await window.confirmAction({
                title: team.name,
                message: @js(__('event-league::messages.remove_team')),
                type: 'danger',
            });

            if (!ok) return;

            const d = await this.req(this.urls.team.replace('__ID__', team.id), 'DELETE');
            if (!d) return;

            this.apply(d);
            window.showToast('success', d.message);
        },

        async generate() {
            const d = await this.req(this.urls.generate, 'POST', { rounds: this.form.rounds });
            if (!d) return;

            this.apply(d);
            this.sheet = null;
            this.tab = 'fixtures';
            window.showToast('success', d.message);
        },

        async saveResult() {
            const d = await this.req(this.urls.fixture.replace('__ID__', this.form.fixture.id), 'PUT', {
                home_score: this.form.home_score === '' ? null : Number(this.form.home_score),
                away_score: this.form.away_score === '' ? null : Number(this.form.away_score),
            });

            if (!d) return;

            this.apply(d);
            if (d.fixture) this.patchFixture(d.fixture);
            this.sheet = null;
            window.showToast('success', d.message);
        },

        async clearResult() {
            this.form.home_score = '';
            this.form.away_score = '';
            await this.saveResult();
        },

        patchFixture(row) {
            const i = this.fixtures.findIndex(f => f.id === row.id);
            if (i > -1) this.fixtures[i] = row;
        },

        /* A silent re-read, for a change somebody else made. */
        async reload() {
            const res = await fetch(this.urls.data, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;

            const d = await res.json().catch(() => null);
            if (d) this.apply(d);
        },
    }));

    if (window.Alpine) define(window.Alpine);
    else document.addEventListener('alpine:init', () => define(window.Alpine));
})();
</script>
@endpush
@endonce
