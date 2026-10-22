{{--
    The federation dashboard's officials editor (Officials tab): appoint an
    official (a member found by name, phone or email, or a new person written
    down), change an appointment, or remove it — built from the platform's own
    controls, in a same-origin frame like the athlete editor
    (federations::athlete.edit). The two talk to the dashboard by postMessage:

      frame → dashboard   {type: 'fed-official:close'}
                          {type: 'fed-official:saved', message}

    Holds no person data in its source. The APPOINTMENT (job, club, pay) is
    written by the event's own officiating endpoints (me.events.officials.*);
    the PERSON (name, gender, nationality, photo) by federations.api.officials.*.
    Each authorises every request on its own.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('federations::partials.frame-head', ['title' => __('Official')])
    <script>
    /* The editor's state. Registered before Alpine starts (Alpine is deferred). */
    window.fedOfficialEdit = function (cfg) {
        return {
            cfg, mode: cfg.official ? 'edit' : 'add', tab: 'member',
            loading: true, failed: '', busy: false, msg: '', confirmDel: false,
            roles: [], clubs: [], comps: [], currency: '',
            o: { jobs: [] }, photo: '', photoUrl: '',
            q: '', results: [], searching: false, pick: null, seq: 0,
            /* Pickers opened with "+ Add designation", one per click, not yet
               turned into a job: { uid, value, text }. */
            adding: [], addSeq: 0,
            /* `jobs`: the chosen jobs, by key — a role value, `id:<n>` for a
               job this person holds that is not on the list ("Referee 1"), or
               `other:<wording>` for a designation typed in here. */
            f: { name: '', gender: '', nat: '', jobs: [], club: '', comp: 'volunteer', fee: '' },

            get isNew() { return this.mode === 'add' && this.tab === 'new'; },
            get isMember() { return this.mode === 'add' && this.tab === 'member'; },
            get personEditable() { return this.mode === 'edit' || this.isNew; },
            /* One tick per job. A new person may only fill jobs on the mat — the
               server refuses a role that grants access to somebody with no account. */
            get jobOptions() {
                const held = (this.o.jobs || []).filter(j => ! this.roles.some(r => r.value === j.role && j.role !== 'other'))
                    .map(j => ({ key: 'id:' + j.id, label: j.label, hint: @js(__('As appointed')) }));
                return held.concat(this.roles.filter(r => ! this.isNew || r.group !== 'platform')
                    .map(r => ({ key: r.value, label: r.label, hint: r.hint || '', group: r.group, section: r.section, sectionLabel: r.section_label || '' })));
            },
            heldKey(j) { return this.roles.some(r => r.value === j.role && j.role !== 'other') ? j.role : 'id:' + j.id; },
            get clubOptions() {
                const list = [{ value: '', label: @js(__('Independent')), logo: null }];
                /* One row per CLUB, not per key. A club that entered while it was
                   still applying is written down on the event under its own key,
                   and once approved it is also a platform club — the same club
                   twice. The first one met wins, and the order below puts the
                   event's own record (where its athletes are filed) ahead of the
                   federation's list. */
                const norm = n => String(n || '').trim().toLowerCase().replace(/\s+/g, ' ');
                const add = c => {
                    if (! c || ! c.slug) return;
                    const same = list.find(x => x.value === c.slug || (x.value && norm(x.label) === norm(c.name)));
                    if (same) { if (! same.logo && c.logo) same.logo = c.logo; return; }   // the platform club's crest, when the event's record has none
                    list.push({ value: c.slug, label: c.name, logo: c.logo || null });
                };
                // The club on record first, then the event's clubs, then every club of the federation.
                if (this.o.club) add({ slug: this.o.club, name: this.o.clubName || this.o.club, logo: this.o.clubLogo });
                this.clubs.forEach(add);
                (cfg.fedClubs || []).forEach(add);
                return [list[0]].concat(list.slice(1).sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: 'base' })));
            },
            get clubName() { const c = this.clubOptions.find(x => x.value === this.f.club); return c && c.value ? c.label : ''; },
            get jobsLine() { return this.f.jobs.map(k => this.jobLabel(k)).filter(Boolean).join(' · '); },
            jobLabel(k) {
                if (k.startsWith('other:')) return k.slice(6);
                const j = this.jobOptions.find(x => x.key === k);
                return j ? j.label : '';
            },
            jobNote(k) {
                if (k.startsWith('other:')) return @js(__('Own designation'));
                const j = this.jobOptions.find(x => x.key === k);
                return j ? (j.section ? j.sectionLabel : j.hint) : '';
            },
            /* A picker's list: every designation not chosen yet, then "Other". */
            pickOptions() {
                return this.jobOptions.filter(j => j.section && ! this.f.jobs.includes(j.key))
                    .map(j => ({ value: j.key, label: j.label }))
                    .concat([{ value: '__other', label: @js(__('+ New designation…')) }]);
            },
            addRow() {
                this.adding.push({ uid: ++this.addSeq, value: '', text: '', section: '', saving: false });
            },
            /* The parts of the officials list a new designation can read under,
               named as the server names them. */
            get sections() {
                const seen = {};
                this.roles.forEach(r => { if (r.section && ! seen[r.section]) seen[r.section] = r.section_label || r.section; });
                return ['organising', 'technical', 'referees', 'table', 'team'].filter(k => seen[k]).map(k => ({ key: k, label: seen[k] }));
            },
            /* Where a new name most likely belongs — the organiser can change it. */
            guessSection(text) {
                const t = String(text || '').toLowerCase(), has = w => w.some(x => t.includes(x));
                return has(['team', 'coach', 'فريق', 'مدرب']) ? 'team' : has(['delegate', 'supervisor', 'jury', 'مندوب', 'مشرف']) ? 'technical'
                    : has(['referee', 'judge', 'حكم']) ? 'referees' : has(['timekeeper', 'scorekeeper', 'recorder', 'ميقات', 'مسجل']) ? 'table' : 'organising';
            },
            dropRow(d) { this.adding = this.adding.filter(x => x.uid !== d.uid); },
            /* A listed designation is taken the moment it is picked; "Other"
               waits for its wording. */
            pickedJob(d) {
                if (! d.value || d.value === '__other') {
                    if (d.value === '__other') this.$nextTick(() => { const el = document.getElementById('ofOther' + d.uid); if (el) el.focus(); });
                    return;
                }
                this.addJob(d.value); this.dropRow(d);
            },
            /* Typed wording that matches a listed designation becomes that one,
               so "referee" is the Referee job and not a second, look-alike one. */
            otherKey(text) {
                const t = String(text || '').trim().replace(/\s+/g, ' ');
                if (t.length < 2) return '';
                const low = t.toLowerCase();
                const same = this.jobOptions.find(j => String(j.label).trim().toLowerCase() === low);
                if (same) return same.key;
                const held = this.f.jobs.find(k => k.startsWith('other:') && k.slice(6).toLowerCase() === low);
                return held || 'other:' + t.slice(0, 80);
            },
            /* A name the list already has is that designation; a new one is saved to
               the host's list (offered on all its events from now on) and chosen. */
            async commitOther(d) {
                const k = this.otherKey(d.text);
                if (! k) { this.msg = @js(__('Write what the designation is called.')); return; }
                this.msg = '';
                if (! k.startsWith('other:') || ! cfg.urls.designation) { this.addJob(k); this.dropRow(d); return; }
                if (d.saving) return;
                d.saving = true;
                const j = await this.call(cfg.urls.designation, 'POST', { label: k.slice(6), section: d.section || this.guessSection(d.text) });
                d.saving = false;
                if (! j.__ok || ! j.role) { this.msg = this.errOf(j); return; }
                if (! this.roles.some(r => r.value === j.role.value)) this.roles = this.roles.concat([j.role]);
                this.addJob(j.role.value); this.dropRow(d);
            },
            addJob(k) {
                if (! this.f.jobs.includes(k)) this.f.jobs = this.f.jobs.concat([k]);
                if (this.isMember && ! this.pick) this.search(this.q, true);
            },
            removeJob(k) { this.f.jobs = this.f.jobs.filter(x => x !== k); },
            /* An "Other" whose wording is written but not yet added counts. */
            flushAdding() {
                this.adding.filter(d => d.value === '__other' && this.otherKey(d.text)).forEach(d => this.commitOther(d));
            },
            get pendingJobs() { return this.adding.filter(d => d.value === '__other' && this.otherKey(d.text)).length; },
            jobPayload(k) {
                return k.startsWith('other:') ? { role: 'other', role_label: k.slice(6) } : { role: k };
            },
            get title() {
                if (this.mode === 'edit') return this.f.name || @js(__('Official'));
                if (this.isNew) return this.f.name || @js(__('Appoint an official'));
                return this.pick ? this.pick.name : @js(__('Appoint an official'));
            },
            get ready() {
                const who = this.mode === 'edit' || (this.isNew ? this.f.name.trim().length >= 2 : !! this.pick);
                const fee = this.f.comp !== 'paid' || Number(this.f.fee) > 0;
                return who && (this.f.jobs.length + this.pendingJobs) > 0 && fee && ! this.busy;
            },

            post(type, extra) {
                try { if (window.parent !== window) window.parent.postMessage(Object.assign({ type }, extra || {}), window.location.origin); } catch (e) {}
            },
            close() { this.post('fed-official:close'); },
            csrf() { return document.querySelector('meta[name=csrf-token]').content; },
            async call(url, method, body) {
                try {
                    const res = await fetch(url, { method, credentials: 'same-origin',
                        headers: Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                            body ? { 'Content-Type': 'application/json' } : {}),
                        body: body ? JSON.stringify(body) : undefined });
                    if (res.status === 401 || res.status === 419) { window.top.location.reload(); return { __ok: false }; }
                    const j = await res.json().catch(() => ({}));
                    j.__ok = res.ok && j.success !== false;
                    return j;
                } catch (e) {
                    return { __ok: false, message: 'The connection dropped. Nothing was changed — try again.' };
                }
            },
            errOf(j) {
                if (j && j.errors) { const k = Object.keys(j.errors)[0]; if (k) return j.errors[k][0]; }
                return (j && j.message) || 'That did not save. Try again.';
            },

            async init() {
                const key = '__ofCrop';
                if (window[key]) document.removeEventListener('cropperCropped', window[key]);
                window[key] = (e) => { const d = e.detail || {}; if (d.id === 'ofPhoto' && d.base64) { this.photo = d.base64; this.photoUrl = d.base64; } };
                document.addEventListener('cropperCropped', window[key]);
                // Escape closes the window — but first whatever is open inside it.
                document.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape') return;
                    const ed = document.getElementById('editor_ofPhoto');
                    if (ed && ed.style.display === 'flex') return;
                    const at = document.activeElement;
                    if (at && at !== document.body && at.closest('main')) { at.blur(); return; }
                    this.close();
                });

                // Roles, clubs, pay types and currency come from the officiating endpoint.
                const meta = await this.call(cfg.urls.list + '?q=', 'GET');
                if (! meta.__ok) { this.failed = 'The officials could not be read. Try again.'; this.loading = false; return; }
                this.roles = meta.roles || []; this.clubs = meta.clubs || []; this.comps = meta.compensations || []; this.currency = meta.currency || '';

                if (this.mode === 'edit') {
                    const j = await this.call(cfg.urls.show + '?event=' + encodeURIComponent(cfg.event) + '&official=' + encodeURIComponent(cfg.official), 'GET');
                    if (! j.__ok || ! j.official) { this.failed = 'That official could not be opened.'; this.loading = false; return; }
                    this.fill(j.official);
                } else {
                    this.search('', true);
                }
                this.loading = false;
            },
            fill(o) {
                this.o = Object.assign({ jobs: [] }, o);
                this.adding = [];
                this.photo = ''; this.photoUrl = o.photo || '';
                this.f = { name: o.name || '', gender: o.gender || '', nat: o.nat || '', jobs: (o.jobs || []).map(j => this.heldKey(j)),
                    club: o.club || '', comp: o.compensation || 'volunteer', fee: o.fee != null ? String(o.fee) : '' };
            },
            setTab(t) { this.tab = t; this.f.jobs = this.f.jobs.filter(k => ! this.isNew || ! this.roles.some(r => r.value === k && r.group === 'platform')); this.msg = ''; },
            /* Search the pool the ticked jobs open — a job on the mat when one is
               ticked, otherwise the mat pool anyway, so somebody can be found
               before their jobs are chosen (the host club alone was too narrow). */
            searchRole() {
                const mat = this.roles.filter(r => r.group !== 'platform').map(r => r.value);
                const ticked = this.f.jobs.find(k => mat.includes(k));
                const access = this.f.jobs.find(k => ! k.startsWith('id:') && ! k.startsWith('other:') && ! mat.includes(k));
                return ticked || (access ? access : (mat[0] || ''));
            },
            search(q, now) {
                this.q = q; this.pick = null;
                clearTimeout(this._t);
                const seq = ++this.seq;
                this._t = setTimeout(async () => {
                    this.searching = true;
                    const role = this.searchRole();
                    const j = await this.call(cfg.urls.list + '?q=' + encodeURIComponent(q.trim()) + (role ? '&role=' + encodeURIComponent(role) : ''), 'GET');
                    if (seq !== this.seq) return;
                    this.searching = false;
                    this.results = (j.candidates || []).slice(0, 12);
                }, now ? 0 : 300);
            },
            choose(c) { this.pick = { id: c.id, name: c.name, avatar: c.avatar || null, nationality: c.nationality || '', flag: c.flag || null, roles: c.roles || [] }; },

            /* The fee is counted once for a person: it rides on their first job;
               any further job is added as volunteer. */
            async save() {
                if (! this.ready) return;
                this.flushAdding();
                const f = this.f;
                if (this.personEditable && f.name.trim().length < 2) { this.msg = 'Enter the official’s full name.'; return; }
                const pay = { compensation: f.comp, fee: f.comp === 'paid' ? f.fee : null, club: f.club || '' };
                const extra = { compensation: 'volunteer', fee: null, club: f.club || '' };
                const roleKeys = f.jobs.filter(k => ! k.startsWith('id:'));
                this.busy = true; this.msg = '';
                const fail = (j) => { this.msg = this.errOf(j); this.busy = false; };
                let j;

                if (this.mode === 'edit') {
                    const person = {};
                    if (f.name.trim() !== (this.o.name || '')) person.name = f.name.trim();
                    if ((f.gender || '') !== (this.o.gender || '')) person.gender = f.gender || null;
                    if ((f.nat || '') !== (this.o.nat || '')) person.nat = f.nat || null;
                    if (this.photo) person.photo = this.photo;
                    if (Object.keys(person).length) {
                        j = await this.call(cfg.urls.person, 'POST', Object.assign({ event: cfg.event, official: cfg.official }, person));
                        if (! j.__ok) return fail(j);
                    }
                    const held = this.o.jobs || [];
                    const kept = held.filter(h => f.jobs.includes(this.heldKey(h)));
                    /* A typed-in job replaced by another typed-in one is RENAMED, not
                       removed and re-added: the person keeps one appointment (and its
                       badge and pay) and the event's one-typed-in-job limit is never hit. */
                    const spare = held.filter(h => ! kept.includes(h) && h.role === 'other');
                    const renamed = [];
                    for (const r of roleKeys.filter(k => k.startsWith('other:') && ! held.some(h => this.heldKey(h) === k))) {
                        const h = spare.shift();
                        if (! h) break;
                        const primary = h.id === cfg.official;
                        j = await this.call(cfg.urls.one.replace('__ID__', encodeURIComponent(h.id)), 'PUT', Object.assign(
                            primary ? pay : { compensation: h.compensation, fee: h.compensation === 'paid' ? h.fee : null, club: f.club || '' },
                            { role: 'other', role_label: r.slice(6) }));
                        if (! j.__ok) return fail(j);
                        renamed.push(h, r);
                    }
                    // Jobs chosen that they do not hold yet.
                    for (const r of roleKeys.filter(k => ! held.some(h => this.heldKey(h) === k) && ! renamed.includes(k))) {
                        j = await this.call(cfg.urls.store, 'POST', Object.assign({}, kept.length || renamed.length ? extra : pay, { user_id: this.o.userId }, this.jobPayload(r)));
                        if (! j.__ok) return fail(j);
                    }
                    // The jobs kept: the one opened carries the pay; the others keep theirs, all take the club.
                    // The fee is counted once: on the job opened, wherever it went.
                    const payHolder = kept.find(x => x.id === cfg.official) || (renamed.some(x => x.id === cfg.official) ? null : kept[0]);
                    for (const h of kept) {
                        const primary = !! payHolder && h.id === payHolder.id;
                        j = await this.call(cfg.urls.one.replace('__ID__', encodeURIComponent(h.id)), 'PUT',
                            primary ? pay : { compensation: h.compensation, fee: h.compensation === 'paid' ? h.fee : null, club: f.club || '' });
                        if (! j.__ok) return fail(j);
                    }
                    // Jobs removed (a renamed one is still theirs).
                    for (const h of held.filter(x => ! kept.includes(x) && ! renamed.includes(x))) {
                        j = await this.call(cfg.urls.one.replace('__ID__', encodeURIComponent(h.id)), 'DELETE');
                        if (! j.__ok) return fail(j);
                    }
                    this.post('fed-official:saved', { message: 'Official updated.' });
                } else {
                    let userId = this.pick ? this.pick.id : null;
                    let first = true;
                    for (const r of roleKeys) {
                        if (this.isNew && ! userId) {
                            j = await this.call(cfg.urls.newPerson, 'POST', Object.assign({}, pay, {
                                full_name: f.name.trim(), gender: f.gender || null, nationality: f.nat || null,
                            }, this.jobPayload(r), this.photo ? { photo: this.photo } : {}));
                            if (! j.__ok) return fail(j);
                            userId = j.official && j.official.user_id;
                        } else {
                            j = await this.call(cfg.urls.store, 'POST', Object.assign({}, first ? pay : extra, { user_id: userId }, this.jobPayload(r)));
                            if (! j.__ok) return fail(j);
                        }
                        first = false;
                    }
                    this.post('fed-official:saved', { message: roleKeys.length > 1 ? 'Official appointed to ' + roleKeys.length + ' jobs.' : (j.message || 'Official appointed.') });
                }
                this.busy = false;
            },
            /* Remove the person from the event: every job they hold. */
            async remove() {
                if (this.busy) return;
                if (! this.confirmDel) { this.confirmDel = true; return; }
                this.busy = true; this.msg = '';
                for (const h of (this.o.jobs || [{ id: cfg.official }])) {
                    const j = await this.call(cfg.urls.one.replace('__ID__', encodeURIComponent(h.id)), 'DELETE');
                    if (! j.__ok) { this.msg = this.errOf(j); this.busy = false; this.confirmDel = false; return; }
                }
                this.post('fed-official:saved', { message: 'Removed from the event.' });
            },

            /* The photo opens the cropper on the picture already there, to adjust
               it — or to swap it ("New photo" in the sheet). No picture yet: the
               file picker, straight away. */
            pickPhoto() {
                if (! this.personEditable) return;
                if (this.photoUrl) {
                    document.dispatchEvent(new CustomEvent('cropper:open', { detail: { id: 'ofPhoto', src: this.photoUrl } }));
                    return;
                }
                if (this.$refs.file) this.$refs.file.click();
            },
            picked(ev) {
                const file = ev.target.files && ev.target.files[0];
                ev.target.value = '';
                const input = document.getElementById('input_ofPhoto');
                if (! file || ! input) return;
                const dt = new DataTransfer(); dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
        };
    };
    </script>
    <script defer src="{{ asset('vendor/alpinejs/3.17.4/alpine.min.js') }}"></script>
    <style>
        .of-job { cursor: default; align-items: center; }
        .of-x { width: 32px; height: 32px; flex: none; border-radius: 999px; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; display: grid; place-items: center; cursor: pointer; font-size: .75rem; transition: color .15s, border-color .15s, background .15s; }
        .of-x:hover { color: #b4483c; border-color: #f0c9c3; background: #fdf3f1; }
        .of-pick { padding: 10px; border: 1.5px dashed #d5dae3; border-radius: 14px; background: #fafbfc; }
        .of-add { width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1.5px dashed #caa14f; border-radius: 14px; background: #fff; color: #8a6a24; font: inherit; font-size: .84rem; font-weight: 700; cursor: pointer; transition: background .15s; }
        .of-add:hover { background: #fdf6e6; }
        .ae-ghost:disabled { opacity: .5; cursor: not-allowed; }
        .of-sec { height: 32px; padding: 0 12px; border: 1.5px solid #e5e7eb; border-radius: 999px; background: #fff; font: inherit; font-size: .75rem; font-weight: 600; color: #5d6880; cursor: pointer; transition: border-color .15s, background .15s; }
        .of-sec.is-on { border-color: #caa14f; background: #fdf6e6; color: #1e2c4f; }
    </style>
</head>
<body class="antialiased">
<div x-data="fedOfficialEdit(@js(['event' => $event, 'official' => $official, 'urls' => $urls, 'fedClubs' => $fedClubs]))"
     class="ae-shell flex flex-col">

    {{-- Header band (Design Rule #8): the official is the headline. --}}
    <header class="flex-shrink-0 z-40 px-5 pt-4 pb-4 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #1e2c4f, #1e2c4fb0), #fff;">
        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative flex items-start gap-3">
            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                <i class="bi bi-person-check-fill text-xl" style="color:#edcd85"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black leading-tight" style="overflow-wrap:anywhere" x-text="title">{{ __('Official') }}</h1>
                <p class="text-[12px] text-white/85 mt-0.5">
                    <span x-text="jobsLine || (mode === 'edit' ? @js(__('Official')) : @js(__('A new appointment')))"></span><template x-if="clubName"><span> &middot; <span x-text="clubName"></span></span></template>
                </p>
            </div>
            <button type="button" @click="close()" aria-label="{{ __('Close') }}"
                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform hover:bg-white/25">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </header>

    <main class="ae-scroll flex-1 min-h-0 px-5 py-4 space-y-4">
        <template x-if="loading">
            <div class="space-y-3" aria-busy="true">
                <div class="flex gap-4"><div class="ae-skel" style="width:108px;height:144px;border-radius:16px"></div>
                    <div class="flex-1 space-y-3"><div class="ae-skel h-11"></div><div class="ae-skel h-11"></div></div></div>
                <div class="ae-skel h-24"></div><div class="ae-skel h-24"></div>
            </div>
        </template>
        <template x-if="failed">
            <div role="alert" class="rounded-2xl px-4 py-3 text-sm font-semibold" style="background:#fdecea;color:#8f2f25" x-text="failed"></div>
        </template>

        <div x-show="! loading && ! failed" x-cloak class="space-y-4">
            {{-- Appointing: an existing member, or somebody new --}}
            <template x-if="mode === 'add'">
                <div class="ae-seg w-full" role="group" aria-label="{{ __('Who is being appointed') }}" style="display:flex">
                    <button type="button" class="flex-1" :class="tab === 'member' && 'is-on'" :aria-pressed="tab === 'member'" @click="setTab('member')"><i class="bi bi-search"></i> {{ __('Find a member') }}</button>
                    <button type="button" class="flex-1" :class="tab === 'new' && 'is-on'" :aria-pressed="tab === 'new'" @click="setTab('new')"><i class="bi bi-person-plus"></i> {{ __('New person') }}</button>
                </div>
            </template>

            <template x-if="mode === 'edit' && o.claimed">
                <div class="rounded-2xl px-4 py-3 text-[13px] leading-relaxed flex gap-2" style="background:#fdf6e6;color:#7a5d17">
                    <i class="bi bi-info-circle-fill mt-0.5"></i>
                    <span>{{ __('This official has their own TakeOne account. What you change here changes their profile.') }}</span>
                </div>
            </template>

            {{-- Find a member --}}
            <section class="ae-sec" x-show="isMember">
                <h2 class="ae-h"><i class="bi bi-search"></i>{{ __('Find a member') }}</h2>
                <template x-if="pick">
                    <div class="flex items-center gap-3 rounded-2xl p-3" style="background:#fdf6e6;border:1.5px solid #caa14f">
                        <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 grid place-items-center">
                            <template x-if="pick.avatar"><img :src="pick.avatar" alt="" class="w-full h-full object-cover" x-on:error="$el.remove()"></template>
                            <template x-if="! pick.avatar"><i class="bi bi-person text-gray-400"></i></template>
                        </span>
                        <span class="flex-1 text-[15px] font-bold" style="overflow-wrap:anywhere" x-text="pick.name"></span>
                        <template x-if="pick.flag"><span class="fi" :class="'fi-' + pick.flag" style="width:20px;height:15px;border-radius:2px;flex:none" :title="pick.nationality"></span></template>
                        <button type="button" class="ae-ghost" style="height:34px" @click="pick = null">{{ __('Change') }}</button>
                    </div>
                </template>
                <template x-if="! pick">
                    <div>
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" class="ae-in" style="padding-left:2.4rem" :value="q" @input="search($event.target.value)"
                                   placeholder="{{ __('Name, phone or email…') }}" aria-label="{{ __('Find a member') }}" autocomplete="off" spellcheck="false">
                        </div>
                        <p class="text-[12px] text-gray-500 mt-2" x-show="searching">{{ __('Searching…') }}</p>
                        <p class="text-[12px] text-gray-500 mt-2" x-show="! searching && q.trim().length >= 2 && ! results.length" x-cloak>{{ __('Nobody matches. Try another spelling, or write them down as a new person.') }}</p>
                        <div class="mt-2 space-y-1.5 max-h-72 overflow-y-auto">
                            <template x-for="c in results" :key="c.id">
                                <button type="button" class="ae-card" style="align-items:center" @click="choose(c)">
                                    <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 grid place-items-center">
                                        <template x-if="c.avatar"><img :src="c.avatar" alt="" loading="lazy" class="w-full h-full object-cover" x-on:error="$el.remove()"></template>
                                        <template x-if="! c.avatar"><i class="bi bi-person text-gray-400"></i></template>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <b style="overflow-wrap:anywhere" x-text="c.name"></b>
                                        <small class="flex items-center gap-1.5 flex-wrap">
                                            <template x-if="c.flag"><span class="fi" :class="'fi-' + c.flag" style="width:16px;height:12px;border-radius:2px;flex:none" :title="c.nationality"></span></template>
                                            <span x-text="[c.nationality, (c.roles || []).length ? @js(__('already')) + ': ' + c.roles.join(', ') : ''].filter(Boolean).join(' · ')"></span>
                                        </small>
                                    </span>
                                    <i class="bi bi-chevron-right text-gray-400 rtl:rotate-180"></i>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </section>

            {{-- The person --}}
            <section class="ae-sec" x-show="personEditable">
                <h2 class="ae-h"><i class="bi bi-person-fill"></i>{{ __('Official') }}</h2>
                <div class="flex gap-4 items-start flex-wrap">
                    <div class="flex flex-col items-center gap-1.5">
                        <button type="button" class="ae-photo" @click="pickPhoto()" aria-label="{{ __('Change photo') }}">
                            <template x-if="photoUrl"><img :src="photoUrl" alt="" x-on:error="photoUrl = ''"></template>
                            <template x-if="! photoUrl && f.gender === 'Female'">
                                <x-gender-avatar gender="female" sizes="108px" class="w-full h-full" />
                            </template>
                            <template x-if="! photoUrl && f.gender !== 'Female'">
                                <x-gender-avatar gender="male" sizes="108px" class="w-full h-full" />
                            </template>
                            <span class="ae-cam"><i class="bi bi-camera-fill"></i></span>
                        </button>
                        <template x-if="photo">
                            <button type="button" class="text-[11px] font-semibold text-gray-500 hover:text-red-600" @click="photo = ''; photoUrl = o.photo || ''">{{ __('Undo new photo') }}</button>
                        </template>
                    </div>
                    <div class="flex-1 space-y-3" style="min-width:220px">
                        <div>
                            <label class="ae-lbl" for="ofName">{{ __('Full name') }} <span class="text-red-500">*</span></label>
                            <input id="ofName" type="text" class="ae-in" x-model="f.name" maxlength="120" autocomplete="off" spellcheck="false">
                        </div>
                        <div>
                            <span class="ae-lbl">{{ __('Gender') }}</span>
                            <x-fields.gender-toggle model="f.gender" />
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="ae-lbl">{{ __('Nationality') }}</span>
                    <x-fields.country model="f.nat" :inline="true" id="ofNat" name="of_nat" label="" wrapper-class="" />
                </div>
            </section>

            {{-- The designations: one person may hold several (Referee 1 and
                 Referee 2, Tatami manager and Scorekeeper…) — one appointment
                 each. "+ Add designation" opens a picker per click; "Other"
                 takes a designation the list has no word for. --}}
            <section class="ae-sec">
                <div class="flex items-center justify-between gap-2" style="margin-bottom:12px">
                    <h2 class="ae-h" style="margin:0"><i class="bi bi-person-badge-fill"></i>{{ __('Designations') }}</h2>
                    <span class="text-[12px] font-semibold text-gray-500" x-text="f.jobs.length ? f.jobs.length + ' ' + (f.jobs.length === 1 ? @js(__('designation')) : @js(__('designations'))) : @js(__('Add one or more'))"></span>
                </div>

                <div class="space-y-2" x-show="f.jobs.length" role="list" aria-label="{{ __('Designations') }}">
                    <template x-for="k in f.jobs" :key="k">
                        <div class="ae-card is-on of-job" role="listitem">
                            <span class="ae-box"><i class="bi bi-check-lg"></i></span>
                            <span class="min-w-0 flex-1"><b style="overflow-wrap:anywhere" x-text="jobLabel(k)"></b><small x-show="jobNote(k)" x-text="jobNote(k)"></small></span>
                            <button type="button" class="of-x" @click="removeJob(k)" :aria-label="@js(__('Remove')) + ' ' + jobLabel(k)" :title="@js(__('Remove'))"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </template>
                </div>

                <div class="space-y-2" :class="f.jobs.length && 'mt-2'" x-show="adding.length">
                    <template x-for="d in adding" :key="d.uid">
                        <div class="of-pick">
                            <div class="flex items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <x-fields.select model="d.value" options-expr="pickOptions()" placeholder="{{ __('Choose a designation…') }}"
                                        :searchable="true" search-placeholder="{{ __('Search designations…') }}" change="pickedJob(d)" />
                                </div>
                                <button type="button" class="of-x" style="margin-top:6px" @click="dropRow(d)" aria-label="{{ __('Cancel') }}" title="{{ __('Cancel') }}"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="mt-2" x-show="d.value === '__other'" x-cloak>
                                <div class="flex items-center gap-2">
                                    <input type="text" class="ae-in flex-1" :id="'ofOther' + d.uid" x-model="d.text" maxlength="80"
                                           @keydown.enter.prevent="commitOther(d)" placeholder="{{ __('e.g. Announcer, Results officer…') }}"
                                           aria-label="{{ __('New designation') }}" autocomplete="off">
                                    <button type="button" class="ae-ghost" :disabled="! otherKey(d.text) || d.saving" @click="commitOther(d)" x-text="d.saving ? @js(__('Saving…')) : @js(__('Add'))">{{ __('Add') }}</button>
                                </div>
                                <span class="ae-lbl" style="margin-top:10px">{{ __('Listed under') }}</span>
                                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('Listed under') }}">
                                    <template x-for="s in sections" :key="s.key">
                                        <button type="button" role="radio" class="of-sec" :class="(d.section || guessSection(d.text)) === s.key && 'is-on'"
                                                :aria-checked="(d.section || guessSection(d.text)) === s.key" @click="d.section = s.key" x-text="s.label"></button>
                                    </template>
                                </div>
                                <p class="text-[12px] text-gray-500 mt-2">{{ __('Saved to your list of designations, so you can pick it at every event from now on.') }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" class="of-add" :class="(f.jobs.length || adding.length) && 'mt-2'" @click="addRow()">
                    <i class="bi bi-plus-lg"></i>{{ __('Add designation') }}
                </button>
                <p class="text-[12px] text-gray-500 mt-2" x-show="mode === 'edit'" x-cloak>{{ __('Remove a designation to take them off it. Their fee is counted once, on the designation you opened.') }}</p>
            </section>

            {{-- Club and pay --}}
            <section class="ae-sec">
                <h2 class="ae-h"><i class="bi bi-shield-fill"></i>{{ __('Club and pay') }}</h2>
                <span class="ae-lbl">{{ __('Club') }}</span>
                <x-fields.select model="f.club" options-expr="clubOptions" placeholder="{{ __('Independent') }}"
                    :searchable="true" :logos="true" search-placeholder="{{ __('Search clubs…') }}" panel-class="bottom-full mb-1" />

                <span class="ae-lbl" style="margin-top:14px">{{ __('Pay') }}</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="c in comps" :key="c.value">
                        <button type="button" class="ae-card" :class="f.comp === c.value && 'is-on'" :aria-pressed="f.comp === c.value" @click="f.comp = c.value">
                            <span class="ae-dot"></span><span class="min-w-0"><b x-text="c.label"></b><small x-show="c.hint" x-text="c.hint"></small></span>
                        </button>
                    </template>
                </div>
                <div class="mt-3 flex items-center gap-2" x-show="f.comp === 'paid'" x-cloak>
                    <input type="text" class="ae-in" style="max-width:180px" x-model="f.fee" inputmode="decimal" maxlength="9" placeholder="{{ __('Fee') }}" aria-label="{{ __('Fee') }}">
                    <span class="text-sm font-semibold text-gray-500" x-text="currency"></span>
                </div>
            </section>
        </div>
    </main>

    <footer class="flex-shrink-0 z-40 px-5 py-3 bg-white border-t border-gray-100 flex items-center gap-3 flex-wrap"
            style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" x-show="! loading && ! failed" x-cloak>
        <template x-if="mode === 'edit'">
            <button type="button" class="ae-danger" :class="confirmDel && 'is-armed'" @click="remove()"
                    x-text="confirmDel ? @js(__('Tap again to remove')) : @js(__('Remove from event'))"></button>
        </template>
        <p class="flex-1 text-[13px] font-semibold" style="color:#b4483c;min-width:120px" role="alert" x-text="msg"></p>
        <button type="button" class="ae-ghost" @click="close()">{{ __('Cancel') }}</button>
        <button type="button" class="ae-save" :disabled="! ready" @click="save()"
                x-text="busy ? @js(__('Saving…')) : (mode === 'edit' ? @js(__('Save')) : @js(__('Appoint')))">{{ __('Save') }}</button>
    </footer>

    {{-- The project's one cropper, never uploading on its own (see the athlete editor). --}}
    <input type="file" x-ref="file" accept="image/*" class="hidden" style="display:none" @change="picked($event)">
    <div class="hidden" style="display:none">
        <x-takeone-cropper id="ofPhoto" mode="form" :inline="true" :replaceable="true"
            :width="600" :height="800" shape="rectangle" :canvasHeight="400"
            folder="temp" filename="official" inputName="of_photo"
            sheetMaxWidth="min(600px, calc(100vw - 32px))" sheetClass="ae-crop rounded-2xl shadow-2xl bg-white" editorAlign="items-center"
            :showControls="true" :showCancel="true" saveText="{{ __('Use photo') }}" />
    </div>
</div>

@include('partials.toast-host')
@stack('modals')
@stack('scripts')
</body>
</html>
