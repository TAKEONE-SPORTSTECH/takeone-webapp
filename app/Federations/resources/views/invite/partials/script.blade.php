{{--
    The club invitation's flow — ONE Alpine component, `clubInvite()`.

    A port of the prototype page's own logic (Club Invitation.dc.html →
    `class Component`), behaviour for behaviour: the same steps, the same
    checks in the same order, the same draft kept in this tab, the same
    payload to the same doors. What it does NOT own is the data: every read is
    `window.FedStore` and every write is `window.TakeOneFed`, both from
    federation-store.platform.js — the very file the prototype runs on — so
    the invoice maths, the look-up and the submit are one copy, not two.

    Every server value reaches the page through x-text / :src / :href — never
    innerHTML.
--}}
<script>
function clubInvite() {
    const T = @js($strings);
    const BOOT = window.__TAKEONE_FED || {};
    const COUNTRIES = (BOOT.dial || []).map(c => ({ i2: String(c.i2 || '').toLowerCase(), i3: c.i3, name: c.name, code: c.code }));
    const fill = (x, o) => Object.keys(o || {}).reduce((a, k) => a.split('{' + k + '}').join(o[k]), String(x || ''));
    const ageOf = dob => { if (!dob) return null; const d = new Date(dob), n = new Date(); let a = n.getFullYear() - d.getFullYear(); if (n < new Date(n.getFullYear(), d.getMonth(), d.getDate())) a--; return a; };
    const nk = x => String(x || '').toLowerCase().replace(/[^\p{L}\p{N} ]+/gu, '').replace(/\s+/g, ' ').trim();
    const isContact = q => { q = String(q || '').trim(); return /@/.test(q) || (/^[\d\s+()-]+$/.test(q) && q.replace(/\D/g, '').length >= 7); };
    const wOk = w => { const n = parseFloat(w); return n >= 15 && n <= 200; };
    const isoDob = (dd, mm, yy) => { const D0 = +dd, M0 = +mm, Y0 = +yy;
        if (!(Y0 >= 1900 && M0 >= 1 && M0 <= 12 && D0 >= 1 && D0 <= 31) || String(yy).length !== 4) return '';
        const x = new Date(Date.UTC(Y0, M0 - 1, D0)); if (x.getUTCMonth() !== M0 - 1 || x > new Date()) return '';
        return Y0 + '-' + String(M0).padStart(2, '0') + '-' + String(D0).padStart(2, '0'); };
    const initials = n => String(n || '?').split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const DRAFT_KEY = 'fedinv-draft:' + (BOOT.event || '');
    const DRAFT_FIELDS = ['step', 'accepted', 'clubMode', 'club', 'athletes', 'picks', 'pickTypes', 'transferReq', 'teams', 'staff', 'distinct'];
    const fedCountry = String((BOOT.store && BOOT.store.federation && BOOT.store.federation.country) || 'bh').toLowerCase();
    const defNat = (COUNTRIES.find(c => c.i2 === fedCountry) || {}).i3 || 'BHR';
    const defDial = (COUNTRIES.find(c => c.i2 === fedCountry) || {}).code || '+973';
    const blankStaff = () => ({ mgr: { name: '', photo: null }, ref1: { name: '', photo: null, license: '' }, ref2: { name: '', photo: null, license: '' } });

    return {
        t: T,
        tick: 0,
        step: 0,
        error: '',
        accepted: [false, false],
        confirm: false,
        club: { name: '', coach: '', dial: defDial, phone: '', email: '', address: '', logo: null, logoShape: 'circle' },
        clubMode: null, clubId: null, verified: false, viaLink: false, editClub: false,
        findQ: '', findRows: null, findPick: null, codeSent: false, codeInput: '', idContact: '',
        verifying: false, clubChoices: null, clubPicking: null, clubMatch: null, distinct: false, checkingClub: false,
        athletes: [], draft: null, editIdx: null,
        picks: {}, pickTypes: {}, transferReq: [], teams: null,
        staff: blankStaff(),
        amend: false, submitting: false, doneInfo: null, subClub: '', subCount: 0, subSkipped: 0,
        addOpen: false, alQ: '', alPick: null, alWeight: '', alTypesSel: [],
        rosterQ: '', breakdownOpen: true, copied: false,
        cropTarget: null,
        _subId: null, _findT: null, _dsT: null,

        // ─── Store access (re-read whenever the store says it changed) ────────
        st() { this.tick; return window.FedStore || null; },
        ev() { const s = this.st(); return (s && s.event()) || { fees: [], lateFee: 0, deadline: '', currency: 'BHD' }; },
        phase() { const s = this.st(); return (s && s.phase && s.phase()) || 'open'; },
        clubObj() { const s = this.st(); return this.clubId && s ? s.club(this.clubId) : null; },
        baseEntry() { const s = this.st(); return s && this.clubId ? s.entry(this.clubId) : null; },
        hasEntry() { return !! this.baseEntry(); },
        money(n) { return (this.ev().currency || 'BHD') + ' ' + (Math.round((+n || 0) * 1000) / 1000).toFixed(3); },
        country(i3) { return COUNTRIES.find(c => c.i3 === i3) || null; },
        flagOf(i3) { const c = this.country(i3); return c ? 'fi fi-' + c.i2 : ''; },
        countryName(i3) { const c = this.country(i3); return c ? c.name : (i3 || ''); },
        gender(g) { return g === 'Female' ? T.female : T.male; },
        age(dob) { return ageOf(dob); },
        initials,
        fill,

        classify(a) {
            // The event's own rule book when it runs on the platform: the
            // category the athlete will actually be placed in.
            const rb = BOOT.rulebook;
            if (rb && rb.classes && a.gender && a.dob && a.weight) {
                const age = ageOf(a.dob), w = parseFloat(a.weight), g = String(a.gender).toLowerCase();
                const group = (rb.age_groups || []).find(x => age >= x.min && (x.max === null || age <= x.max));
                if (! group) return '';
                const c = ((rb.classes[group.key] || {})[g] || []).find(x => ! x.open && w >= x.min && w <= x.max);
                return c ? [this.ev().sport, a.gender, group.key, c.label].filter(Boolean).join(' · ') : '';
            }
            if (String(this.ev().sport || '') !== 'Karate' || ! window.KarateShotukanWeightclasses) return '';
            const r = window.KarateShotukanWeightclasses.classify({ gender: a.gender, dob: a.dob, weight: a.weight });
            return r ? 'Karate · ' + a.gender + ' · ' + r.ageGroup + ' · ' + r.label : '';
        },

        // ─── Life cycle ───────────────────────────────────────────────────────
        init() {
            window.addEventListener('fedstore-change', () => { this.tick++; });
            try { history.replaceState(Object.assign({}, history.state || {}, { fedStep: 0 }), ''); } catch (e) {}
            window.addEventListener('popstate', (e) => {
                const n = e.state && typeof e.state.fedStep === 'number' ? e.state.fedStep : 0;
                this.go(n, true);
            });
            this.restoreDraft();
            // A club the server already proved (personal link, magic link, a
            // session it opened before, a signed-in owner) comes back signed in.
            const vk = window.FedStore && window.FedStore.verifiedClub();
            const c = vk && window.FedStore.club(vk);
            let viaLink = false; try { viaLink = !! new URLSearchParams(location.search).get('club'); } catch (e) {}
            if (c) this.loadClub(c, viaLink);
            // The shared cropper announces its result; the target is ours.
            document.addEventListener('cropperCropped', (ev) => {
                const d = ev.detail || {};
                if (! d.base64 || ! this.cropTarget) return;
                if (d.id === 'invLogo') { this.club.logo = d.base64; this.club.logoShape = /^data:image\/png/.test(d.base64) ? 'circle' : 'rect'; }
                else if (d.id === 'invPhoto') {
                    if (['mgr', 'ref1', 'ref2'].includes(this.cropTarget)) this.staff[this.cropTarget].photo = d.base64;
                    else if (this.draft) this.draft.photo = d.base64;
                }
                this.cropTarget = null;
            });
            this.$watch('step', () => this.saveDraft());
            ['accepted', 'club', 'athletes', 'picks', 'pickTypes', 'transferReq', 'teams', 'staff', 'distinct'].forEach(k => this.$watch(k, () => this.saveDraft()));
        },

        loadClub(c, viaLink) {
            const raw = String(c.phone || '').replace(/\s/g, '');
            const dial = COUNTRIES.slice().sort((a, b) => b.code.length - a.code.length).find(x => x.code && raw.startsWith(x.code));
            this.clubMode = 'existing'; this.verified = true; this.viaLink = !! viaLink; this.clubId = c.id;
            this.club = {
                name: c.name || '', coach: c.coach || '', email: c.email || '', address: c.address || '',
                dial: dial ? dial.code : this.club.dial,
                phone: dial ? raw.slice(dial.code.length).replace(/(\d{4})(?=\d)/g, '$1 ') : (c.phone || ''),
                logo: c.logo || null, logoShape: c.logoShape || 'circle',
            };
            this.staff = c.staff ? JSON.parse(JSON.stringify(c.staff)) : this.staff;
            ['mgr', 'ref1', 'ref2'].forEach(k => { this.staff[k] = Object.assign({ name: '', photo: null }, k === 'mgr' ? {} : { license: '' }, this.staff[k] || {}); });
            this.error = ''; this.codeSent = false; this.codeInput = '';
            this.$nextTick(() => this.setDial(this.club.dial));
        },

        /* The shared phone-code picker mirrors OUT only; a club loaded after
           the page painted sets it through Alpine's own data API. */
        setDial(code) {
            const el = this.$root.querySelector('[data-club-phone] [data-phone-group]');
            const data = el && window.Alpine ? window.Alpine.$data(el) : null;
            if (! data || ! data.countries) return;
            const m = data.countries.find(c => c.code === code);
            if (m) { data.selectedCode = m.code; data.selectedFlag = m.flag; }
        },

        // ─── Draft kept in this tab ───────────────────────────────────────────
        saveDraft() {
            clearTimeout(this._dsT);
            this._dsT = setTimeout(() => {
                if (this.step < 1 || this.step > 4) return;
                const o = {}; DRAFT_FIELDS.forEach(k => { o[k] = JSON.parse(JSON.stringify(this[k])); });
                if (o.clubMode !== 'new') o.clubMode = null;
                try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(o)); }
                catch (e) { try { o.club.logo = null; o.athletes = (o.athletes || []).map(a => ({ ...a, photo: null })); o.staff = null; sessionStorage.setItem(DRAFT_KEY, JSON.stringify(o)); } catch (e2) {} }
            }, 400);
        },
        restoreDraft() {
            let o = null; try { o = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
            if (! o || typeof o !== 'object') return;
            DRAFT_FIELDS.forEach(k => { if (o[k] !== undefined && o[k] !== null) this[k] = o[k]; });
            if (this.clubMode !== 'new') { this.clubMode = null; this.step = Math.min(this.step || 0, 2); }
            if (this.step > 0) try { history.pushState(Object.assign({}, history.state || {}, { fedStep: this.step }), ''); } catch (e) {}
        },
        clearDraft() { clearTimeout(this._dsT); try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) {} },

        // ─── Moving between screens ───────────────────────────────────────────
        go(step, fromHistory) {
            if (! fromHistory && step !== this.step) {
                try { history[step >= 5 ? 'replaceState' : 'pushState'](Object.assign({}, history.state || {}, { fedStep: step }), ''); } catch (e) {}
            }
            this.step = step; this.error = '';
            this.$nextTick(() => { try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) {} });
        },
        start() {
            if (this.coverClosed()) return;
            if (this.phase() === 'locked') return this.go(6);
            if (this.hasEntry()) return this.go(6);
            this.go(1);
        },
        coverClosed() { return this.phase() === 'locked' && ! this.hasEntry(); },
        back() {
            if (this.amend && this.step === 3) { this.resetAmend(); return this.go(6); }
            if (this.step === 6 || this.step === 5 || this.step <= 1) return this.go(0);
            this.go(this.step - 1);
        },
        resetAmend() { this.amend = false; this.picks = {}; this.pickTypes = {}; this.athletes = []; this.draft = null; this.teams = null; },
        stepTitle() { return [T.tRules, T.tRules, T.tClub, T.tAthletes, T.tReview, T.doneTitle, T.yourEntries][this.step] || ''; },
        stepChip() { return this.step >= 1 && this.step <= 4 ? fill(T.stepChip, { n: this.step }) : (this.step === 5 ? T.doneChip : T.manageChip); },
        nextLabel() {
            if (this.step === 4) return this.submitting ? T.submitting : T.submit;
            return this.checkingClub ? T.idChecking : T.next;
        },
        nextBlocked() {
            return (this.step === 1 && ! (this.accepted[0] && this.accepted[1])) || (this.step === 4 && ! this.confirm) || this.submitting || this.checkingClub;
        },

        validate(step) {
            if (step === 1 && ! (this.accepted[0] && this.accepted[1])) return T.eAccept;
            if (step === 2 && ! this.clubMode && ! this.verified) return T.eVerify;
            if (step === 2 && this.clubMode === 'existing' && ! this.verified) return T.eVerify;
            if (step === 2 && (this.clubMode === 'new' || this.editClub)) {
                const c = this.club;
                if (! c.name.trim()) return T.eClub;
                if (! c.coach.trim()) return T.eCoach;
                if (! String(c.phone).trim()) return T.ePhone;
                if (! /.+@.+\..+/.test(c.email.trim())) return T.eEmail;
                if (! c.address.trim()) return T.eAddress;
            }
            if (step === 3) {
                const dr = this.draft;
                const blank = dr && ! dr.name.trim() && ! (dr.natId || '').trim() && ! dr.dob && ! dr.dobD && ! dr.dobM && ! dr.dobY && ! dr.weight && ! dr.photo;
                if (dr && blank && (this.athletes.length || Object.keys(this.picks).length || this.amend)) { this.draft = null; this.editIdx = null; }
                else if (dr) return T.eOpen;
                const pk = Object.keys(this.picks);
                if (pk.some(id => ! (parseFloat(this.picks[id]) > 0))) return T.eWeights;
                const teamsN = Object.values(this.teamsState()).reduce((a, b) => a + (+b || 0), 0);
                if (! this.athletes.length && ! pk.length && ! teamsN) return this.amend ? (this.invoice().balance > 0 ? '' : T.eNothing) : T.eNone;
                if (! teamsN && this.entries().some(e => ! (e.types || []).length)) return T.eTypes;
            }
            if (step === 4 && ! this.confirm) return T.eConfirm;
            return '';
        },

        next() {
            if (this.submitting || this.checkingClub) return;
            const err = this.validate(this.step);
            if (err) { this.error = err; return; }
            if (this.step === 4) return this.submit();
            if (this.step === 2 && this.clubId && this.hasEntry() && ! this.amend) return this.go(6);
            // A "new" club that is already on file is caught HERE, not at Submit.
            if (this.step === 2 && this.clubMode === 'new' && ! this.distinct && window.TakeOneFed && window.TakeOneFed.checkClub) {
                this.checkingClub = true;
                return window.TakeOneFed.checkClub({ name: this.club.name, email: this.club.email, phone: (this.club.dial || '') + String(this.club.phone).replace(/\D/g, '') })
                    .then(m => {
                        this.checkingClub = false;
                        if (m) { this.clubMatch = m; try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) {} return; }
                        this.go(3);
                    });
            }
            this.go(this.step + 1);
        },

        // ─── Step 2: find and verify the club ─────────────────────────────────
        findIsContact() { return ! this.findPick && isContact(this.findQ); },
        onFindQ() {
            const q = this.findQ; this.error = ''; this.codeSent = false; this.codeInput = ''; this.clubChoices = null;
            clearTimeout(this._findT); const qq = q.trim();
            if (qq.length < 2 || isContact(qq)) { this.findRows = null; return; }
            this._findT = setTimeout(() => {
                window.TakeOneFed.searchClubs(qq).then(rows => { if ((this.findQ || '').trim() === qq) this.findRows = rows || []; });
            }, 250);
        },
        findNone() { return ! this.findPick && ! isContact(this.findQ) && !! this.findRows && ! this.findRows.length && (this.findQ || '').trim().length >= 2; },
        pickFound(k) { this.findPick = k; this.findRows = null; this.codeSent = false; this.codeInput = ''; this.error = ''; },
        unpick() { this.findPick = null; this.codeSent = false; this.codeInput = ''; this.error = ''; },
        clubSub(k) { return k.hint ? fill(T.idCodeTo, { h: k.hint }) : T.idNoEmailShort; },
        sendLabel() { return this.codeSent ? T.idResend : (this.findPick && this.findPick.hint ? fill(T.idSendTo, { h: this.findPick.hint }) : T.idSend); },
        sendClubCode() {
            if (! this.findPick || ! this.findPick.hint) return;
            window.TakeOneFed.sendClubCode(this.findPick.key);
            this.codeSent = true; this.codeInput = ''; this.error = '';
        },
        sendCode() {
            const q = (this.findQ || '').trim();
            if (! q) { this.error = T.eNoClub; this.codeSent = false; return; }
            window.TakeOneFed.sendCode(q);
            this.idContact = q; this.codeSent = true; this.codeInput = ''; this.error = '';
        },
        codeSentMsg() { return this.findPick ? fill(T.idSentClub, { h: this.findPick.hint || '' }) : fill(T.idSentTo, { c: this.idContact }); },
        verifyCode() {
            if (this.verifying) return;
            if ((this.codeInput || '').length !== 6) { this.error = T.eCode; return; }
            this.verifying = true; this.error = '';
            const p0 = this.findPick ? window.TakeOneFed.verifyClubCode(this.findPick.key, this.codeInput) : window.TakeOneFed.verifyCode(this.idContact, this.codeInput);
            p0.then(id => {
                this.verifying = false;
                if (id && id.choices) { this.clubChoices = id.choices; this.error = ''; return; }
                if (! id) { this.error = T.eCode; return; }
                const c = window.FedStore.club(id);
                if (c) { this.findPick = null; this.findQ = ''; this.findRows = null; this.clubMatch = null; this.loadClub(c, false); if (window.FedStore.entry(c.id)) setTimeout(() => this.go(6), 0); }
            });
        },
        chooseClub(k) {
            if (this.clubPicking) return;
            this.clubPicking = k.key; this.error = '';
            window.TakeOneFed.chooseClub(k.key).then(id => {
                if (! id) { this.clubPicking = null; this.clubChoices = null; this.codeSent = false; this.error = T.eCode; return; }
                this.clubPicking = null; this.clubChoices = null; this.findPick = null; this.findQ = ''; this.findRows = null;
                const c = window.FedStore.club(id);
                if (c) { this.loadClub(c, false); if (window.FedStore.entry(c.id)) setTimeout(() => this.go(6), 0); }
            });
        },
        chooseNew() {
            const q0 = (this.findQ || '').trim(), contact = isContact(q0), q = contact || (this.findRows && ! this.findRows.length) ? q0 : '';
            this.clubMode = 'new'; this.clubId = null; this.verified = false; this.editClub = false; this.picks = {}; this.transferReq = [];
            this.error = ''; this.findPick = null; this.codeSent = false; this.codeInput = ''; this.clubChoices = null;
            this.club.name = this.club.name || (contact ? '' : q);
            this.club.email = this.club.email || (/@/.test(q) ? q : '');
            this.club.phone = this.club.phone || (contact && ! /@/.test(q) ? q.replace(/^\+\d{1,4}\s*/, '') : '');
        },
        backToChoice() { this.clubMode = null; this.codeSent = false; this.codeInput = ''; this.error = ''; this.clubMatch = null; },
        matchItsUs() {
            const m = this.clubMatch; if (! m) return;
            this.clubMode = null; this.clubMatch = null; this.distinct = false; this.findPick = m; this.findQ = m.name; this.findRows = null;
            this.codeSent = !! m.hint; this.codeInput = ''; this.error = '';
            if (m.hint) window.TakeOneFed.sendClubCode(m.key);
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) {}
        },
        matchDifferent() { this.clubMatch = null; this.distinct = true; this.error = ''; },
        clubEdited() { this.error = ''; this.clubMatch = null; this.distinct = false; },
        verifiedMsg() { return this.viaLink ? fill(T.idLink, { c: this.club.name }) : fill(T.idVerifiedAs, { c: this.club.name }); },

        // ─── Photos: one cropper for the project, handed the file ────────────
        pickPhoto(target, camera) {
            this.cropTarget = target;
            const el = camera ? this.$refs.camInput : this.$refs.fileInput;
            if (el) el.click();
        },
        onPhotoFile(ev) {
            const f = ev.target.files && ev.target.files[0];
            ev.target.value = '';
            if (! f || ! this.cropTarget) return;
            const input = document.getElementById(this.cropTarget === 'logo' ? 'input_invLogo' : 'input_invPhoto');
            if (! input) return;
            const dt = new DataTransfer(); dt.items.add(f);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        // ─── Step 3: athletes ─────────────────────────────────────────────────
        roster() { const s = this.st(), c = this.clubObj(); return c && s ? s.roster(c.id) : []; },
        enteredIds() { const b = this.baseEntry(); return new Set(((b && b.athletes) || []).filter(a => a.status !== 'Withdrawn').map(a => a.athleteId)); },
        indiv(dob) { const a = ageOf(dob); return (this.ev().fees || []).filter(f => f.unit === 'athlete' && (! f.maxAge || (a === null ? 99 : a) <= f.maxAge)); },
        isLate() { return this.phase() === 'late'; },
        feeOf(dob, types) {
            return this.indiv(dob).filter(f => (types || []).includes(f.type)).reduce((n, f) => n + (+f.price || 0), 0)
                + ((types || []).length && this.isLate() ? (+this.ev().lateFee || 0) : 0);
        },
        rosterRows() {
            const rq = this.rosterQ.trim().toLowerCase(), nq = rq.replace(/[^0-9a-z]/g, '');
            const entered = this.enteredIds();
            return this.roster()
                .filter(a => ! rq || a.name.toLowerCase().includes(rq) || (nq && (String(a.natId || '').replace(/[^0-9a-z]/gi, '').toLowerCase().includes(nq) || a.id.replace(/[^0-9a-z]/gi, '').toLowerCase().includes(nq))))
                .filter(a => ! this.amend || ! entered.has(a.id));
        },
        isPicked(a) { return a.id in this.picks; },
        togglePick(a) { const p = { ...this.picks }; if (a.id in p) delete p[a.id]; else p[a.id] = ''; this.picks = p; this.error = ''; },
        togglePickType(a, type) {
            const c = this.pickTypes[a.id] || [];
            this.pickTypes = { ...this.pickTypes, [a.id]: c.includes(type) ? c.filter(x => x !== type) : [...c, type] }; this.error = '';
        },
        pickedCount() { return Object.keys(this.picks).length; },
        teamsState() { if (this.teams) return this.teams; const b = this.baseEntry(); return (b && b.teams) || {}; },
        teamFees() { return (this.ev().fees || []).filter(f => f.unit === 'team'); },
        teamN(type) { return +this.teamsState()[type] || 0; },
        teamStep(type, d) { this.teams = { ...this.teamsState(), [type]: Math.max(0, this.teamN(type) + d) }; this.error = ''; },
        transferRows() { const s = this.st(); return this.transferReq.map(id => { const a = s && s.athlete(id); const from = a && s.club(a.clubId); return { id, name: a ? a.name : id, from: from ? from.name : '' }; }); },
        removeTransfer(id) { this.transferReq = this.transferReq.filter(x => x !== id); },
        editAthlete(i) { const a = this.athletes[i], p = (a.dob || '').split('-'); this.draft = { ...a, dobY: a.dobY || p[0] || '', dobM: a.dobM || p[1] || '', dobD: a.dobD || p[2] || '', nat2: ((this.country(a.nat) || {}).i2 || '').toUpperCase() }; this.editIdx = i; this.error = ''; },
        removeAthlete(i) { this.athletes = this.athletes.filter((_, j) => j !== i); },

        // ─── The "Add an athlete" sheet ───────────────────────────────────────
        blankDraft() { return { name: '', gender: '', dob: '', dobD: '', dobM: '', dobY: '', weight: '', nat: defNat, nat2: ((this.country(defNat) || {}).i2 || '').toUpperCase(), natId: '', photo: null, types: [] }; },
        sheetOpen() { return this.addOpen || !! this.draft; },
        openAdd() {
            if (this.clubObj()) { this.addOpen = true; this.draft = null; this.editIdx = null; this.alQ = ''; this.alPick = null; this.alWeight = ''; this.alTypesSel = []; }
            else { this.addOpen = false; this.draft = this.blankDraft(); this.editIdx = null; }
            this.error = '';
        },
        closeAdd() { this.addOpen = false; this.draft = null; this.editIdx = null; this.alPick = null; this.error = ''; },
        tabNew() { this.draft = this.draft || this.blankDraft(); this.addOpen = false; this.error = ''; },
        tabLookup() { this.draft = null; this.editIdx = null; this.addOpen = true; this.error = ''; },
        setDob(k, n) { this.draft[k] = String(this.draft[k] || '').replace(/\D/g, '').slice(0, n); this.draft.dob = isoDob(this.draft.dobD, this.draft.dobM, this.draft.dobY); this.error = ''; },
        setNat2() { const c = COUNTRIES.find(x => x.i2 === String(this.draft.nat2 || '').toLowerCase()); if (c) this.draft.nat = c.i3; },
        toggleDraftType(type) { const t0 = this.draft.types || []; this.draft.types = t0.includes(type) ? t0.filter(x => x !== type) : [...t0, type]; this.error = ''; },
        alList() {
            const s = this.st(); if (! s) return [];
            const q = (this.alQ || '').trim(), ql = q.toLowerCase(), qn = ql.replace(/[^0-9a-z]/g, '');
            const own = ql.length >= 2 ? this.roster().filter(a => a.name.toLowerCase().includes(ql) || (qn.length >= 3 && (String(a.natId || '').replace(/[^0-9a-z]/gi, '').toLowerCase().includes(qn) || a.id.replace(/[^0-9a-z]/gi, '').toLowerCase().includes(qn)))) : [];
            const far = q.length >= 4 ? s.lookup(q).filter(a => ! own.some(o => o.id === a.id)) : [];
            return own.slice(0, 20).concat(far);
        },
        alView(a) {
            const s = this.st(), c = this.clubObj(), own = !! (c && a.clubId === c.id), from = s && s.club(a.clubId);
            const pendingStore = s ? s.transfers().filter(x => x.status === 'Pending') : [];
            const pend = this.transferReq.includes(a.id) || pendingStore.some(x => x.athleteId === a.id && x.toClubId === this.clubId);
            const added = a.id in this.picks, entered = this.enteredIds().has(a.id);
            return { ...a, own, pend, entered,
                tag: own ? (entered ? T.tagEntered : added ? T.tagAdded : T.tagOwn) : (pend ? T.transferPending : T.tagOther),
                tagTone: own ? (entered || added ? 'green' : 'gold') : 'amber',
                clubLine: (from ? from.name : '') + (a.id ? '  ·  ' + a.id : '') };
        },
        alHint() { const c = this.clubObj(); if (! c) return T.alHintNew; return (this.alQ || '').trim().length >= 2 && ! this.alList().length ? T.alNone : T.alHint; },
        alPicked() { const s = this.st(); const a = this.alPick && s ? s.athlete(this.alPick) : null; return a ? this.alView(a) : null; },
        alChoose(a) { this.alPick = a.id; this.alWeight = this.picks[a.id] || ''; this.alTypesSel = this.pickTypes[a.id] || []; this.error = ''; },
        alToggleType(type) { this.alTypesSel = this.alTypesSel.includes(type) ? this.alTypesSel.filter(x => x !== type) : [...this.alTypesSel, type]; this.error = ''; },
        alOtherMsg() { const s = this.st(), p = this.alPicked(); return p ? fill(T.alOtherMsg, { c: ((s && s.club(p.clubId)) || {}).name || '—' }) : ''; },
        sheetState() {
            const d = this.draft, pv = this.alPicked();
            if (d) return { ready: !! (d.name.trim() && d.gender && wOk(d.weight) && (d.types || []).length), fee: this.feeOf(d.dob, d.types), label: this.editIdx != null ? T.updateBtn : T.addBtn, types: d.types || [], other: false };
            if (pv && pv.own) return { ready: ! pv.entered && wOk(this.alWeight) && this.alTypesSel.length > 0, fee: this.feeOf(pv.dob, this.alTypesSel), label: (pv.id in this.picks) ? T.updateBtn : T.addBtn, types: this.alTypesSel, other: false };
            if (pv) return { ready: ! pv.pend, fee: 0, label: pv.pend ? T.transferRequested : T.reqTransfer, types: [], other: true };
            return { ready: false, fee: 0, label: T.addBtn, types: [], other: false };
        },
        sheetFeeSub() {
            const st = this.sheetState();
            if (st.other) return T.transferNote;
            if (! st.types.length) return T.feePick;
            if (this.isLate()) return fill(T.feeLate, { f: this.money(this.ev().lateFee) });
            return st.fee ? '' : T.feeNone;
        },
        saveNew() {
            const d = this.draft, s = this.st(), c = this.clubObj();
            if (! d.name.trim() || ! d.gender || ! d.weight) { this.error = T.eDraft; return; }
            if (! d.dob && (d.dobD || d.dobM || d.dobY)) { this.error = T.eDob; return; }
            if (! (d.types || []).length) { this.error = T.eTypes; return; }
            const onRoster = d.dob && this.roster().find(a => nk(a.name) === nk(d.name) && a.dob === d.dob);
            if (onRoster) { this.error = fill(T.eDupOwn, { n: onRoster.name }); return; }
            if (s && d.natId && d.natId.trim()) {
                const ex = s.byNatId(d.natId);
                if (ex && (this.editIdx == null || (this.athletes[this.editIdx] || {}).natId !== d.natId)) {
                    const cl = s.club(ex.clubId);
                    if (c && ex.clubId === c.id) { this.error = fill(T.eDupOwn, { n: ex.name }); return; }
                    this.error = fill(T.eDupOther, { n: ex.name, c: cl ? cl.name : '—' }); this.alQ = d.natId; return;
                }
            }
            const twin = this.athletes.find((a, j) => j !== this.editIdx && nk(a.name) === nk(d.name) && (! a.dob || ! d.dob || a.dob === d.dob));
            if (twin) { this.error = fill(T.eDupList, { n: twin.name }); return; }
            const row = { ...d, name: d.name.trim() }; delete row.nat2;
            const list = this.athletes.slice();
            if (this.editIdx != null) list[this.editIdx] = row; else list.push(row);
            this.athletes = list; this.draft = null; this.editIdx = null; this.error = '';
        },
        confirmSheet() {
            if (this.draft) return this.saveNew();
            const pv = this.alPicked(); if (! pv) return;
            if (! pv.own) { if (pv.pend) return; this.transferReq = [...this.transferReq, pv.id]; this.addOpen = false; this.alPick = null; this.error = ''; return; }
            if (pv.entered) return;
            if (! wOk(this.alWeight)) { this.error = T.eWeight; return; }
            if (! this.alTypesSel.length) { this.error = T.eTypes; return; }
            this.picks = { ...this.picks, [pv.id]: this.alWeight };
            this.pickTypes = { ...this.pickTypes, [pv.id]: this.alTypesSel };
            this.addOpen = false; this.alPick = null; this.error = '';
        },

        // ─── Money ────────────────────────────────────────────────────────────
        entries() {
            const s = this.st();
            const picked = s ? Object.keys(this.picks).map(id => { const a = s.athlete(id);
                return a ? { name: a.name, gender: a.gender, dob: a.dob, weight: this.picks[id], nat: a.nat, natId: a.natId, photo: a.photo || null, athleteId: a.id, takeoneId: a.id, types: this.pickTypes[id] || [] } : null; }).filter(Boolean) : [];
            return picked.concat(this.athletes.map(a => { const r = { ...a }; delete r.nat2; return r; }));
        },
        previewEntry() {
            const base = this.baseEntry(), late = this.isLate();
            const added = this.entries().map(e => ({ ...e, status: 'Active', late }));
            return { ...(base || { clubId: this.clubId, athletes: [], payments: [], log: [] }), athletes: ((base && base.athletes) || []).concat(added), teams: this.teamsState() };
        },
        invoice() { const s = this.st(); if (! s) return { lines: [], perAthlete: [], teams: [], total: 0, paid: 0, pending: 0, balance: 0 }; return s.invoice(this.previewEntry()); },
        invRef() { const b = this.baseEntry(); return (this.doneInfo && this.doneInfo.ref) || (b && b.ref) || T.refPending; },
        catGroups() {
            const gm = {};
            this.entries().forEach(a => { const c = this.classify(a) || (a.gender || ''); (gm[c] = gm[c] || []).push(a.name); });
            return Object.keys(gm).sort().map(k => ({ label: k, names: gm[k].join(', '), count: gm[k].length }));
        },
        countLabel(n) { return n === 1 ? T.athleteN : fill(T.athletesN, { n }); },
        copyBreakdown() {
            const inv = this.invoice();
            const txt = [this.ev().name + ' · ' + this.club.name]
                .concat(inv.perAthlete.filter(p => ! p.withdrawn).map(p => p.name + ': ' + this.money(p.total) + ' (' + p.items.map(i => i.label).join(' + ') + ')'))
                .concat(inv.teams.map(x => x.label + ' × ' + x.n + ': ' + this.money(x.amount)))
                .concat([T.subtotal + ': ' + this.money(inv.total)]).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(txt).catch(() => {});
            this.copied = true; setTimeout(() => { this.copied = false; }, 1800);
        },

        // ─── Submit ───────────────────────────────────────────────────────────
        submit() {
            if (this.submitting) return;
            // One id per submission, kept until it is answered: a retry after a
            // lost answer carries the SAME id, and the server replies with the
            // first result instead of saving the club and its athletes twice.
            if (! this._subId) this._subId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
            this.submitting = true; this.error = '';
            const c = this.club;
            const clubRec = { name: c.name.trim(), coach: c.coach.trim(), email: c.email.trim(), phone: ((c.dial || '') + ' ' + String(c.phone).replace(/\D/g, '')).trim(),
                address: c.address.trim(), logo: c.logo || null, logoShape: c.logoShape || 'circle', distinct: !! this.distinct };
            const entries = this.entries();
            window.TakeOneFed.submitEntry({ submissionId: this._subId, clubMode: this.clubMode, club: clubRec, staff: this.staff, coachName: c.coach.trim(), entries, transferReq: this.transferReq, teams: this.teamsState() })
                .then(r => {
                    this.submitting = false;
                    if (! r.ok) {
                        if (! r.network) this._subId = null;   // refused, not lost: a corrected entry is a new submission
                        if (r.code === 'club_exists' && r.match) { this.clubMatch = r.match; this.distinct = false; return this.go(2); }
                        this.error = r.message || T.eNone; return;
                    }
                    this._subId = null; this.clearDraft();
                    const payMsg = r.balance > 0 ? fill(T.transferMsg, { a: this.money(r.balance) }) : T.nothingDue;
                    const added = typeof r.added === 'number' ? r.added : entries.length;
                    this.subClub = c.name.trim(); this.subCount = added; this.subSkipped = (r.skipped || []).length;
                    this.clubMode = 'existing'; this.verified = true; this.clubId = r.clubId || this.clubId || (window.FedStore && window.FedStore.verifiedClub());
                    this.doneInfo = { ref: r.ref, payMsg };
                    this.picks = {}; this.pickTypes = {}; this.athletes = []; this.transferReq = []; this.teams = null; this.amend = false;
                    this.confirm = false; this.draft = null; this.clubMatch = null; this.distinct = false;
                    this.go(5);
                });
        },
        doneCountLabel() { return this.countLabel(this.subCount) + (this.subSkipped ? ' · ' + fill(T.skippedN, { n: this.subSkipped }) : ''); },

        // ─── Step 6: the club's entries ───────────────────────────────────────
        mgInvoice() { const s = this.st(), b = this.baseEntry(); return s && b ? s.invoice(b) : { total: 0, paid: 0, pending: 0, balance: 0 }; },
        mgActive() { const b = this.baseEntry(); return b ? b.athletes.filter(a => a.status !== 'Withdrawn') : []; },
        mgPhaseMsg() {
            const ph = this.phase(), ev = this.ev();
            if (ph === 'open') { let dl = ''; try { dl = new Date(ev.deadline).toLocaleDateString(((document.documentElement.lang || 'en').toLowerCase().startsWith('en') ? 'en-GB' : document.documentElement.lang), { day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) {} return fill(T.phaseOpen, { d: dl }); }
            return ph === 'late' ? fill(T.phaseLate, { f: this.money(ev.lateFee) }) : T.phaseLocked;
        },
        mgStats() {
            const inv = this.mgInvoice(), owe = Math.max(0, inv.balance) + (inv.pending || 0);
            const last = inv.balance < -0.0005 ? [T.credit, this.money(-inv.balance), 'green'] : owe > 0.0005 ? [T.awaiting, this.money(owe), 'amber'] : [T.stBalance, this.money(0), 'green'];
            return [[T.stAthletes, String(this.mgActive().length), 'ink'], [T.stTotal, this.money(inv.total), 'ink'], [T.stPaid, this.money(inv.paid), inv.paid > 0 ? 'green' : 'ink'], last];
        },
        mgCountLabel() {
            const b = this.baseEntry(); if (! b) return '';
            const teams = Object.keys(b.teams || {}).filter(k => +b.teams[k]);
            return this.countLabel(this.mgActive().length) + (teams.length ? ' · ' + teams.map(k => k + ' × ' + b.teams[k]).join(', ') : '');
        },
        mgMeta(a) { const c = this.classify(a); return c ? a.weight + ' kg · ' + c : this.gender(a.gender) + ' · ' + a.weight + ' kg'; },
        mgLog() {
            const b = this.baseEntry(); if (! b) return [];
            const lang = ((document.documentElement.lang || 'en').toLowerCase().startsWith('en') ? 'en-GB' : document.documentElement.lang);
            return (b.log || []).slice().reverse().map(g => {
                const key = g.key === 'logPaid' ? (g.m === 'Bank transfer' ? 'logDue' : 'logReceived') : g.key;
                let when = ''; try { const d = new Date(g.ts); when = d.toLocaleDateString(lang, { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString(lang, { hour: '2-digit', minute: '2-digit' }); } catch (e) {}
                return { when, text: fill(T[key] || key, { n: g.n || '', a: g.a || '', m: g.m || '' }) };
            });
        },
        async withdraw(i) {
            const b = this.baseEntry(), a = b && b.athletes[i]; if (! a) return;
            const ok = window.confirmAction ? await window.confirmAction({ title: T.withdraw, message: fill(T.confirmWithdraw, { n: a.name }), type: 'danger', confirmText: T.withdraw }) : false;
            if (! ok) return;
            const ph = this.phase();
            const e2 = { ...b, athletes: b.athletes.map((x, j) => j === i ? { ...x, status: 'Withdrawn', chargeable: ph !== 'open', withdrawnAt: Date.now() } : x),
                log: (b.log || []).concat([{ ts: Date.now(), key: 'logWithdrawn', n: a.name }]), updatedAt: Date.now() };
            window.FedStore.saveEntry(e2); this.tick++;
        },
        startAdd() { this.resetAmend(); this.amend = true; this.confirm = false; this.error = ''; this.go(3); },
        startPay() { this.resetAmend(); this.amend = true; this.confirm = false; this.error = ''; this.go(4); },

        // ─── The band's share control ─────────────────────────────────────────
        jump(id) {
            const ids = Array.isArray(id) ? id : [id];
            for (const one of ids) { const el = document.getElementById(one); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; } }
        },
        share() {
            // The EVENT's address only — never the `?club=` token a club's own
            // personal link carries, which would hand that club's sign-in on.
            const data = { title: @js($e['title']), url: location.origin + location.pathname + '?event=' + encodeURIComponent(BOOT.event || '') };
            if (navigator.share) { navigator.share(data).catch(() => {}); return; }
            navigator.clipboard?.writeText(data.url).then(() => window.showToast?.('success', @js(__('events::events.public_share_copied'))), () => {});
        },
    };
}
</script>
