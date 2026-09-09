{{--
    The Languages screen's behaviour, shared by both breakpoints.

    One copy, included by `index` (desktop) and `mobile`, so the two layouts can
    differ in shape and never in what they DO — the same reason the event
    cover's state lives in one partial (CLAUDE.md → Shared Stays Shared).
--}}
<script>
/* Registered rather than inline: the desktop admin shell swaps content in place
   (partials/admin-shell-nav), and a top-level declaration only runs once per
   session, so the component has to be found by name on every visit. */
function platformLanguages(languages, tier) {
    return {
        languages: languages,
        tier: tier,
        open: null,
        rows: [],
        page: 1,
        pages: 1,
        search: '',
        only: 'all',
        loading: false,
        timer: null,

        openLocale(row) {
            this.open = row;
            this.page = 1;
            this.search = '';
            this.only = 'all';
            this.load();
        },

        async load() {
            if (! this.open) return;
            this.loading = true;

            try {
                const url = new URL(@js(url('/admin/languages')) + '/' + this.open.code + '/strings', window.location.origin);
                url.searchParams.set('tier', this.tier);
                url.searchParams.set('page', this.page);
                url.searchParams.set('only', this.only);
                if (this.search) url.searchParams.set('q', this.search);

                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (! res.ok) throw new Error('load');

                const data = await res.json();
                this.rows = data.rows || [];
                this.pages = data.pages || 1;
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('shared.something_went_wrong')));
            } finally {
                this.loading = false;
            }
        },

        /* Saved on blur. A correction is a person's words and outranks the
           machine's for good, so it is worth writing the moment it is made
           rather than behind a Save button somebody forgets to press. */
        async save(row) {
            try {
                const res = await fetch(@js(url('/admin/languages')) + '/' + this.open.code, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ file: row.file, key: row.key, value: row.value }),
                });

                if (! res.ok) throw new Error('save');

                row.origin = row.value ? 'human' : null;
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('shared.something_went_wrong')));
            }
        },

        async translate(row) {
            try {
                const res = await fetch(@js(url('/admin/languages')) + '/' + row.code + '/translate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ tier: this.tier, rewrite: row.missing === 0 }),
                });

                const data = await res.json();
                window.showToast && window.showToast(data.success ? 'success' : 'error', data.message);

                if (data.success) {
                    row.run = { state: 'running' };
                    this.watch(row);
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('shared.something_went_wrong')));
            }
        },

        /* A run is fifteen minutes to an hour, so the card keeps itself honest
           rather than making somebody reload to find out. Polled, not pushed:
           this is one super-admin watching their own action, not a fan-out. */
        watch(row) {
            clearInterval(this.timer);

            this.timer = setInterval(async () => {
                try {
                    const res = await fetch(@js(url('/admin/languages')) + '/' + row.code + '/status?tier=' + this.tier, {
                        headers: { 'Accept': 'application/json' }, cache: 'no-store',
                    });
                    if (! res.ok) return;

                    const data = await res.json();
                    row.run = data.run;
                    row.stored = data.stored;
                    row.missing = data.missing;

                    if (data.run?.state !== 'running') clearInterval(this.timer);
                } catch (e) { /* a dropped poll is not a failure */ }
            }, 5000);
        },

        init() {
            // Pick up a run already going when the page was opened.
            this.languages.filter(r => r.run?.state === 'running').forEach(r => this.watch(r));
        },
    };
}
</script>
