{{--
    The federation record form's behaviour, shared by the desktop and mobile
    layouts. Defined once on window (the admin shell runs a pushed script once
    per session, before Alpine initialises the swapped page), and fed its
    starting state by the page: x-data="fedAdminForm(@js($cfg))".

    Saves over fetch with FormData (files included) and patches itself from the
    answer — readiness, documents, logo, errors — without a reload; only a
    CREATE navigates, to the new record. Every value the server sends back is
    rendered with x-text / :src, never as HTML.
--}}
<script>
window.fedAdminForm = window.fedAdminForm || function (cfg) {
    const SECTION_OF = {
        name: 'identity', short_name: 'identity', country: 'identity', sports: 'identity', timezone: 'identity', currency: 'identity', founded_year: 'identity', languages: 'identity',
        owner_email: 'owner', owner: 'owner',
        email: 'contact', phone_code: 'contact', phone_number: 'contact', phone: 'contact', office_hours: 'contact', website: 'contact', address: 'contact', lat: 'contact', lng: 'contact', social_links: 'contact',
        registration_number: 'legal', registration_authority: 'legal', registration_certificate: 'legal',
        tax_number: 'tax', invoice_name: 'tax', invoice_address: 'tax',
        bank_account_name: 'bank', bank_name: 'bank', iban: 'bank', swift: 'bank',
        officers: 'governance', board: 'governance', president: 'governance', secretary_general: 'governance',
        international_federation: 'documents', olympic_recognised: 'documents', documents: 'documents', statutes: 'documents', international_letter: 'documents',
        color: 'branding', logo: 'branding',
    };
    const sectionOf = (key) => SECTION_OF[String(key).split('.')[0]] || (String(key).startsWith('documents.registration_certificate') ? 'legal' : 'identity');

    return {
        v: cfg.values,
        board: (cfg.values.board || []).length ? cfg.values.board.map(b => ({...b})) : [],
        readiness: cfg.readiness,
        documents: cfg.documents || {},
        pendingDocs: {},
        logoPreview: cfg.values.logo,
        errors: {},
        saving: false,
        panel: null,                       // mobile: the open drill-down panel
        labels: cfg.labels,
        // A NEW federation starts on the quick create (name, sport, country,
        // owner); `full` opens the whole form instead. An existing one is always full.
        full: cfg.method !== 'POST' || /[?&]full=1\b/.test(location.search),
        // The gentle nudge on a record still missing details: dismissible, never a gate.
        welcome: /[?&]welcome=1\b/.test(location.search),
        nudgeHidden: (() => { try { return !!cfg.uuid && localStorage.getItem('fed-nudge-' + cfg.uuid) === '1'; } catch (e) { return false; } })(),

        init() {
            const h = (location.hash || '').slice(1);
            if (h && cfg.sections.includes(h)) this.panel = h;
        },

        err(k) { const e = this.errors[k]; return e ? e[0] : ''; },
        hasErr(k) { return !!this.errors[k]; },
        toggleSport(s) { const i = this.v.sports.indexOf(s); i >= 0 ? this.v.sports.splice(i, 1) : this.v.sports.push(s); },
        toggleLang(l) { const i = this.v.languages.indexOf(l); i >= 0 ? this.v.languages.splice(i, 1) : this.v.languages.push(l); },
        initials() { const s = (this.v.short_name || this.v.name || '').trim(); return (s.length <= 5 ? s : s.split(/\s+/).map(w => w[0]).join('').slice(0, 4)).toUpperCase() || '—'; },
        gradient() { const c = /^#[0-9a-f]{6}$/i.test(this.v.color || '') ? this.v.color : '#1e2c4f'; return 'background: linear-gradient(150deg, ' + c + ', ' + c + 'b0);'; },

        // ---------------------------------------------------- readiness
        stage(k) { return this.readiness[k] || {done: 0, total: 1, complete: false, missing: []}; },
        arc(k) { const s = this.stage(k); const C = 2 * Math.PI * 22; return 'stroke-dasharray:' + C + ';stroke-dashoffset:' + (C * (1 - s.done / s.total)); },
        missingText(k) { return this.stage(k).missing.map(m => this.labels[m] || m).join(' · '); },
        sectionState(sec) {
            const need = cfg.sectionChecks[sec] || [];
            if (!need.length) return 'opt';
            const missing = [].concat(...Object.values(this.readiness).map(s => s.missing));
            return need.some(n => missing.includes(n)) ? 'todo' : 'ok';
        },
        sectionErr(sec) { return Object.keys(this.errors).some(k => sectionOf(k) === sec); },

        // ---------------------------------------------------- board
        addMember() { if (this.board.length < 12) this.board.push({name: '', role: ''}); },
        removeMember(i) { this.board.splice(i, 1); },

        // ---------------------------------------------------- files
        pickLogo(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!/^image\/(png|jpeg|webp)$/.test(f.type)) { e.target.value = ''; return window.showToast && showToast('error', cfg.labels.__logo_invalid); }
            this.logoPreview = URL.createObjectURL(f);
        },
        pickDoc(kind, e) { const f = e.target.files && e.target.files[0]; this.pendingDocs = {...this.pendingDocs, [kind]: f ? f.name : null}; },
        async removeDoc(kind) {
            const d = this.documents[kind];
            if (!d) return;
            const ok = window.confirmAction ? await window.confirmAction({title: cfg.labels.__remove_title, message: d.name, type: 'danger', confirmText: cfg.labels.__remove}) : false;
            if (!ok) return;
            const r = await fetch(d.remove, {method: 'DELETE', headers: this.headers()});
            const j = await r.json().catch(() => ({}));
            if (!r.ok) return window.showToast && showToast('error', j.message || 'Error');
            this.documents = j.documents || {};
            this.readiness = j.readiness || this.readiness;
            window.showToast && showToast('success', j.message);
        },

        headers() { return {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}; },

        // ---------------------------------------------------- save
        async submit() {
            if (this.saving) return;
            this.saving = true;
            const form = this.$refs.form;
            const fd = new FormData(form);
            if (cfg.method !== 'POST') fd.append('_method', cfg.method);
            try {
                const r = await fetch(cfg.action, {method: 'POST', headers: this.headers(), body: fd});
                const j = await r.json().catch(() => ({}));
                if (r.status === 422) {
                    this.errors = j.errors || {};
                    const first = Object.keys(this.errors)[0];
                    if (first) this.reveal(sectionOf(first));
                    window.showToast && showToast('error', cfg.labels.__fix);
                    return;
                }
                if (!r.ok) { window.showToast && showToast('error', j.message || 'Error'); return; }
                this.errors = {};
                if (j.redirect) { window.location.href = j.redirect; return; }
                this.readiness = j.readiness;
                this.documents = j.documents || {};
                this.pendingDocs = {};
                form.querySelectorAll('input[type=file]').forEach(i => { i.value = ''; });
                if (j.federation && j.federation.logo) this.logoPreview = j.federation.logo;
                for (const [k, msg] of Object.entries(j.rejected || {})) this.errors[k] = [msg];
                window.dispatchEvent(new CustomEvent('federation-updated', {detail: j.federation}));
                window.showToast && showToast(Object.keys(j.rejected || {}).length ? 'warning' : 'success', j.message);
            } catch (e) {
                window.showToast && showToast('error', 'Network error — nothing was saved.');
            } finally {
                this.saving = false;
            }
        },

        // ---------------------------------------------------- quick create
        // Only the four essentials; the server works out the short name. On
        // success it lands on the new record, which then nudges for the rest.
        async quickCreate() {
            if (this.saving) return;
            this.saving = true;
            try {
                const r = await fetch(cfg.action, {method: 'POST', headers: this.headers(), body: new FormData(this.$refs.quick)});
                const j = await r.json().catch(() => ({}));
                if (r.status === 422) { this.errors = j.errors || {}; window.showToast && showToast('error', cfg.labels.__fix); return; }
                if (!r.ok) { window.showToast && showToast('error', j.message || 'Error'); return; }
                this.errors = {};
                if (j.redirect) { window.location.href = j.redirect + (j.redirect.includes('?') ? '&' : '?') + 'welcome=1'; return; }
            } catch (e) {
                window.showToast && showToast('error', 'Network error — nothing was saved.');
            } finally {
                this.saving = false;
            }
        },

        // ---------------------------------------------------- the nudge
        nudgeShown() { return cfg.method !== 'POST' && !this.nudgeHidden && (this.welcome || !this.stage('open').complete); },
        hideNudge() { this.nudgeHidden = true; try { cfg.uuid && localStorage.setItem('fed-nudge-' + cfg.uuid, '1'); } catch (e) {} },
        // What is still missing, first the "ready to open" facts, then a stage summary.
        nudgeItems() {
            return this.stage('open').missing.map(k => ({key: k, label: this.labels[k] || k, sec: sectionOf(k === 'phone' ? 'phone_number' : k)}));
        },

        // Desktop scrolls to the chapter; mobile opens its panel.
        reveal(sec) {
            if (cfg.mobile) { this.panel = sec; return; }
            const el = document.getElementById('sec-' + sec);
            if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
        },
        go(sec) { this.panel = sec; history.replaceState(null, '', '#' + sec); window.scrollTo(0, 0); },
        back() { this.panel = null; history.replaceState(null, '', location.pathname); },
    };
};
</script>
