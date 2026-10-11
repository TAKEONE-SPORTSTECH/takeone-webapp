{{--
    The design room's state and requests, shared by the mobile and desktop pages
    (which are separate files; only this behaviour is one). Inline and guarded,
    not @push: the mobile shell swaps #shell-content and re-runs inline scripts.

    The preview is a PNG drawn by the same engine as the PDF (dompdf's GD
    backend), POSTed with the form as it is on screen, so what the organiser
    sees is what will print — and it works inside the Android app, which cannot
    show a PDF inline.
--}}
<script>
window.certDesign = window.certDesign || function (state, ep, strings) {
    /* The drag in progress, kept OUT of Alpine's reactive state: a pointer move then
       repaints the one box being dragged, instead of every box, chip and panel that
       reads the layout. Alpine catches up once, when the box is let go. */
    const live = { d: null };
    return {
        state, ep, strings,
        type: (state.templates[0] || {}).type,
        lang: document.documentElement.lang === 'ar' && state.locales.includes('ar') ? 'ar' : (state.locales[0] || 'en'),
        form: null,
        panel: null,
        saving: false,
        dirty: false,
        uploading: null,
        preview: { url: '', loading: false, print: false },
        _timer: null,

        init() { this.pickType(this.type); },

        get t() { return this.state.templates.find(x => x.type === this.type) || this.state.templates[0]; },
        get scopeLabel() { const s = this.state.scopes.find(x => x.value === this.form.scope); return s ? s.label : ''; },
        get locked() { return ! this.t.editable && this.form.scope === this.t.scope; },

        pickType(type) {
            this.type = type;
            this.sel = null;
            this.form = this.normal(JSON.parse(JSON.stringify(this.t)));
            this.dirty = false;
            this.histReset();
            this.refreshPreview(true);
        },
        /* ─────────────── undo / redo ───────────────
           Every change to the design is a step (typing is gathered into one step per
           pause). Ctrl+Z / Ctrl+Shift+Z or the arrows above the page walk them. */
        designKeys: ['scope', 'title', 'body', 'signature1_name', 'signature1_title', 'signature2_name', 'signature2_title',
            'ornament_level', 'background', 'show_photo', 'accent', 'print_offset_x', 'print_offset_y',
            'layout', 'orientation', 'layout_portrait', 'elements', 'artwork_fit', 'print_bare'],
        hist: [],
        hix: 0,
        histPending: false,
        _histT: null,
        designSnap() { const o = {}; this.designKeys.forEach(k => { o[k] = this.form[k]; }); return JSON.stringify(o); },
        histReset() { clearTimeout(this._histT); this.histPending = false; this.hist = [this.designSnap()]; this.hix = 0; },
        histCommit() {
            clearTimeout(this._histT);
            this.histPending = false;
            if (! this.form) return;
            const snap = this.designSnap();
            if (snap === this.hist[this.hix]) return;
            const h = this.hist.slice(0, this.hix + 1);
            h.push(snap);
            if (h.length > 100) h.shift();
            this.hist = h;
            this.hix = h.length - 1;
        },
        histLater() { clearTimeout(this._histT); this.histPending = true; this._histT = setTimeout(() => this.histCommit(), 450); },
        get canUndo() { return this.hix > 0 || this.histPending; },
        get canRedo() { return ! this.histPending && this.hix < this.hist.length - 1; },
        undo() { this.histCommit(); if (this.hix > 0) { this.hix--; this.histApply(); } },
        redo() { if (this.hix < this.hist.length - 1) { this.hix++; this.histApply(); } },
        histApply() {
            Object.assign(this.form, JSON.parse(this.hist[this.hix]));
            if (this.sel && ! this.isPage(this.sel) && ! this.keys.includes(this.sel)) this.sel = null;
            if (this.sel && this.isPage(this.sel) && this.sel !== 'photo' && ! this.hasPage(this.sel)) this.sel = null;
            this.dirty = true;
            this.refreshPreview(false, 150);
        },
        /* Canva's keys: Delete removes, Ctrl+D duplicates, Esc lets go, Ctrl+Z undoes.
           Never while typing in a field — there the browser's own keys belong to the field. */
        onKey(e) {
            const t = e.target;
            if (t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return;
            const mod = e.ctrlKey || e.metaKey, key = (e.key || '').toLowerCase();
            if (mod && key === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
            if (mod && key === 'y') { e.preventDefault(); this.redo(); return; }
            if (mod && key === 'd' && this.sel && this.canDuplicate(this.sel)) { e.preventDefault(); this.duplicate(this.sel); return; }
            if ((key === 'delete' || key === 'backspace') && this.sel && ! this.locked) { e.preventDefault(); this.removeEl(this.sel); return; }
            if (key === 'escape' && (this.sel || this.addMenu)) { this.sel = null; this.addMenu = false; }
        },
        /* The old "pre-printed paper" background is now a switch beside the page: the
           design keeps a background for the download, and the print copy leaves it out. */
        normal(f) {
            if (f.background === 'blank') { f.background = 'plain'; f.print_bare = true; }
            return f;
        },
        url(key, slot) {
            let u = this.ep[key].replace('xtypex', this.type);
            if (slot) u = u.replace(/\/signature\/1$/, '/signature/' + slot);
            return u;
        },
        touch(wait) { this.dirty = true; this.histLater(); this.refreshPreview(false, wait); },

        insert(token) {
            const el = this.$refs['body_' + this.lang];
            const text = '{' + token + '}';
            const v = this.form.body[this.lang] || '';
            if (el && typeof el.selectionStart === 'number') {
                const a = el.selectionStart, b = el.selectionEnd;
                this.form.body[this.lang] = v.slice(0, a) + text + v.slice(b);
                this.$nextTick(() => { el.focus(); el.selectionStart = el.selectionEnd = a + text.length; });
            } else {
                this.form.body[this.lang] = v + text;
            }
            this.touch();
        },

        payload() {
            const f = this.form;
            return {
                scope: f.scope, title: f.title, body: f.body,
                signature1_name: f.signature1_name, signature1_title: f.signature1_title,
                signature2_name: f.signature2_name, signature2_title: f.signature2_title,
                ornament_level: f.ornament_level, background: f.background, show_photo: !! f.show_photo,
                accent: f.accent || null, print_offset_x: +f.print_offset_x || 0, print_offset_y: +f.print_offset_y || 0,
                layout: f.layout || {},
                orientation: f.orientation === 'portrait' ? 'portrait' : 'landscape',
                layout_portrait: f.layout_portrait || {},
                elements: f.elements || [],
                artwork_fit: f.artwork_fit || null,
                print_bare: !! f.print_bare,
            };
        },
        headers(json) {
            const h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },
        take(d) {
            if (d.templates) this.state.templates = d.templates;
            if (d.scopes) this.state.scopes = d.scopes;
        },

        refreshPreview(now, wait) {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.drawPreview(), now ? 0 : (wait ?? 600));
        },
        async drawPreview() {
            if (! this.form) return;
            this.preview.loading = true;
            const snap = {};
            this.keys.forEach(k => { snap[k] = this.box(k); });
            try {
                const q = '?locale=' + encodeURIComponent(this.lang) + (this.preview.print ? '&print=1' : '');
                const res = await fetch(this.url('preview') + q, {
                    method: 'POST', headers: Object.assign(this.headers(true), { 'Accept': 'image/png' }),
                    credentials: 'same-origin', body: JSON.stringify(this.payload()),
                });
                if (! res.ok) throw new Error();
                // Where each element's ink actually landed, measured by the same engine.
                try {
                    const drawn = JSON.parse(res.headers.get('X-Cert-Drawn') || '{}');
                    this.drawn = drawn;
                    this.drawnAt = snap;
                } catch (e) { /* boxes fall back to their full area */ }
                const blob = await res.blob();
                if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                this.preview.url = URL.createObjectURL(blob);
            } catch (e) { /* the last good preview stays on screen */ }
            this.preview.loading = false;
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch(this.url('update'), { method: 'PUT', headers: this.headers(true), credentials: 'same-origin', body: JSON.stringify(this.payload()) });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : this.strings.failed));
                this.take(d);
                this.form = this.normal(JSON.parse(JSON.stringify(this.t)));
                this.dirty = false;
                window.showToast('success', d.message);
                window.dispatchEvent(new CustomEvent('certificate-design-saved', { detail: { type: this.type } }));
                this.refreshPreview(true);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.saving = false; }
        },

        async upload(kind, slot, input) {
            const file = input.files && input.files[0];
            if (! file) return;
            // Picking "Your artwork" is itself an unsaved change: save it on the way,
            // rather than refusing the upload (which read as "nothing happens").
            if (this.dirty) {
                await this.save();
                if (this.dirty) { input.value = ''; return; }
            }
            this.uploading = kind + (slot || '');
            const body = new FormData();
            body.append('image', file);
            try {
                const u = kind === 'artwork' ? this.url('artwork') : this.url('signature', slot);
                const res = await fetch(u, { method: 'POST', headers: this.headers(false), credentials: 'same-origin', body });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : this.strings.failed));
                this.take(d);
                this.form = this.normal(JSON.parse(JSON.stringify(this.t)));
                window.showToast('success', d.message);
                this.refreshPreview(true);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.uploading = null; input.value = ''; }
        },
        /* A background picture straight from the Add menu or the pattern's options: upload
           it (the server makes it the background) and select it, so its fit choices show. */
        async pickArtwork(input) {
            this.addMenu = false;
            await this.upload('artwork', null, input);
            if (this.t && this.t.has_artwork) this.sel = 'artwork';
        },
        async removeFile(kind, slot) {
            const ok = await window.confirmAction({ title: this.strings.remove_title, message: this.strings.remove_message, type: 'danger', confirmText: this.strings.remove });
            if (! ok) return;
            try {
                const u = kind === 'artwork' ? this.url('artwork') : this.url('signature', slot);
                const res = await fetch(u, { method: 'DELETE', headers: this.headers(false), credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || this.strings.failed);
                this.take(d);
                this.form = this.normal(JSON.parse(JSON.stringify(this.t)));
                window.showToast('success', d.message);
                this.refreshPreview(true);
            } catch (e) { window.showToast('error', e.message); }
        },
        /* ─────────────── arranging the page ───────────────
           Boxes are drawn over the preview from the same numbers the PDF uses
           (Support\Layout: millimetres on a 297 × 210 page, or 210 × 297 stood
           upright). Only what the organiser changes is stored, separately for each
           way up; a default is mirrored in Arabic, a box they have placed stays
           where they put it in every language. */
        sel: null,
        drag: null,
        guide: false,
        guideY: false,

        get placed() { return this.type === 'podium'; },
        get portrait() { return this.form && this.form.orientation === 'portrait'; },
        /* A template drawn in another format (an official's badge) brings its own page and
           elements (DesignController::formatState); a certificate uses the state's own. */
        get fmt() { const k = this.t && this.t.format; return k && this.state.formats && this.state.formats[k] ? this.state.formats[k] : null; },
        get photoToggle() { return ! this.fmt; },
        get isCert() { return ! this.fmt; },
        get els() { return this.fmt ? this.fmt.elements : this.state.elements; },
        fdef(key) { return (this.fmt || this.state)[key]; },
        get pageW() { return this.fmt ? this.fmt.page[this.portrait ? 'portrait' : 'landscape'][0] : (this.portrait ? 210 : 297); },
        get pageH() { return this.fmt ? this.fmt.page[this.portrait ? 'portrait' : 'landscape'][1] : (this.portrait ? 297 : 210); },
        get lkey() { return this.portrait ? 'layout_portrait' : 'layout'; },
        setOrientation(o) {
            if (this.form.orientation === o) return;
            this.form.orientation = o;
            this.sel = null;
            this.drawn = {}; this.drawnAt = {};
            this.touch(0);
        },
        get rtl() { return this.lang === 'ar'; },
        get keys() {
            return this.els.map(e => e.key).filter(k =>
                (this.placed || ! ['medal', 'ribbon'].includes(k)) && (! this.photoToggle || this.form.show_photo || k !== 'photo'))
                .concat((this.form.elements || []).filter(e => ! this.isErase(e.key) || (e.page || 'landscape') === (this.portrait ? 'portrait' : 'landscape')).map(e => e.key));
        },
        /* What is on the page, and what was taken off it (and can be put back). The four
           page-wide elements — pattern, artwork, frame, photo — are settings of the design
           rather than boxes, but they are added and removed the same way as the rest. */
        // A certificate's photo is a setting of the page; on a badge the face is an ordinary element.
        get pageEls() { return this.photoToggle ? ['pattern', 'artwork', 'frame', 'photo'] : ['pattern', 'artwork', 'frame']; },
        isPage(k) { return this.pageEls.includes(k); },
        hasPage(k) {
            const f = this.form;
            return { pattern: ['plain', 'guilloche', 'watermark', 'motif'].includes(f.background), artwork: f.background === 'artwork',
                frame: f.ornament_level !== 'off', photo: !! f.show_photo }[k];
        },
        get boxKeys() { return this.keys.filter(k => k !== 'photo' || ! this.photoToggle || this.form.show_photo); },
        /* Only what is on the page gets a box: a removed one is not drawn at all, because
           the :style binding rewrites the style attribute and would undo an x-show's display:none. */
        get onPageKeys() { return this.boxKeys.filter(k => ! this.box(k).hidden); },
        get shown() {
            return this.pageEls.filter(k => k !== 'photo' && this.hasPage(k))
                .concat(this.keys.filter(k => ! this.box(k).hidden));
        },
        get removed() {
            return this.pageEls.filter(k => k !== 'pattern' && ! this.hasPage(k))
                .concat(this.keys.filter(k => this.box(k).hidden && ! this.isCustom(k) && ! this.isErase(k) && (k !== 'photo' || ! this.photoToggle)));
        },
        /* One background at a time: adding the pattern or the artwork replaces the other. */
        addMenu: false,
        add(k) {
            const f = this.form;
            if (k === 'pattern') f.background = 'plain';
            else if (k === 'artwork') f.background = 'artwork';
            else if (k === 'frame') f.ornament_level = 'full';
            else if (k === 'photo' && this.photoToggle) { f.show_photo = true; this.setBoth('photo', { hidden: false }); }
            else this.setBoth(k, { hidden: false });
            this.addMenu = false;
            this.sel = k;
            this.touch(150);
        },
        setBoth(k, patch) {
            ['layout', 'layout_portrait'].forEach(lk => {
                const l = Object.assign({}, this.form[lk] || {});
                l[k] = Object.assign({}, l[k] || {}, patch);
                this.form[lk] = l;
            });
        },
        isCustom(k) { return /^text_[a-z0-9]{6}$/.test(k || ''); },
        isErase(k) { return /^erase_[a-z0-9]{6}$/.test(k || ''); },
        custom(k) { return (this.form.elements || []).find(e => e.key === k); },
        element(k) {
            if (this.isPage(k) && k !== 'photo') return { kind: 'page', label: this.strings['el_' + k] };
            if (this.isErase(k)) return { kind: 'erase', label: this.strings.el_erase };
            const c = this.custom(k);
            if (c) {
                const words = (c.text[this.lang] || Object.values(c.text).find(v => v) || '').trim();
                return { kind: 'para', label: words ? (words.length > 22 ? words.slice(0, 21) + '…' : words) : this.strings.text_label };
            }
            return this.els.find(e => e.key === k) || { kind: 'text', label: k };
        },
        addText() {
            const list = (this.form.elements || []).slice();
            if (this.textCount >= this.state.max_custom) return;
            const key = this.newKey('text');
            const text = {};
            this.state.locales.forEach(l => { text[l] = ''; });
            text[this.lang] = this.strings.new_text;
            list.push({ key, text, style: 'body' });
            this.form.elements = list;
            // A second new text starts a line lower than the first, not on top of it.
            const d = this.fdef('custom_default')[this.portrait ? 'portrait' : 'landscape'];
            this.setBox(key, { y: +(d.y + (this.textCount - 1) * 12).toFixed(1) });
            this.sel = key;
            this.touch(150);
        },
        get textCount() { return (this.form.elements || []).filter(e => this.isCustom(e.key)).length; },
        get eraseCount() { return this.keys.filter(k => this.isErase(k)).length; },
        /* Erase part of the picture: a box laid over the artwork (or pattern) whose inside
           is painted out with the colours around it (Support\Eraser). It belongs to this
           way up only — the picture is fitted separately for each. Placed at once, so
           Arabic never mirrors it off the thing it covers. */
        newKey(prefix) {
            const list = this.form.elements || [];
            let key;
            do { key = prefix + '_' + Math.random().toString(36).slice(2, 8).padEnd(6, '0'); } while (list.some(e => e.key === key));
            return key;
        },
        canDuplicate(k) { return this.isCustom(k) || this.isErase(k); },
        /* A copy of the organiser's own text or erased area, a little down and to the right. */
        duplicate(k) {
            if (this.locked || ! this.canDuplicate(k)) return;
            if (this.isCustom(k) ? this.textCount >= this.state.max_custom : this.eraseCount >= this.state.max_erase) {
                window.showToast('warning', this.strings.limit_reached);
                return;
            }
            const src = (this.form.elements || []).find(e => e.key === k);
            if (! src) return;
            const key = this.newKey(this.isCustom(k) ? 'text' : 'erase');
            this.form.elements = (this.form.elements || []).concat([Object.assign(JSON.parse(JSON.stringify(src)), { key })]);
            const b = this.box(k), patch = { x: +(b.x + 6).toFixed(1), y: +(b.y + 6).toFixed(1), w: b.w, h: b.h };
            ['scale', 'color', 'align', 'valign', 'h'].forEach(f => { if (this.mine(k)[f] !== undefined) patch[f] = this.mine(k)[f]; });
            this.setBox(key, patch);
            this.sel = key;
            this.touch(150);
        },
        addErase() {
            if (this.eraseCount >= this.state.max_erase) return;
            const list = (this.form.elements || []).slice();
            const key = this.newKey('erase');
            list.push({ key, kind: 'erase', page: this.portrait ? 'portrait' : 'landscape' });
            this.form.elements = list;
            const d = this.fdef('erase_default')[this.portrait ? 'portrait' : 'landscape'];
            const n = this.eraseCount - 1;
            this.setBox(key, { x: +(d.x + n * 6).toFixed(1), y: +(d.y + n * 6).toFixed(1), w: d.w, h: d.h });
            this.sel = key;
            this.touch(150);
        },
        removeEl(k) {
            if (k === 'pattern' || k === 'artwork') {
                this.form.background = 'paper';
            } else if (k === 'frame') {
                this.form.ornament_level = 'off';
            } else if (k === 'photo' && this.photoToggle) {
                this.form.show_photo = false;
            } else if (this.isCustom(k) || this.isErase(k)) {
                this.form.elements = (this.form.elements || []).filter(e => e.key !== k);
                ['layout', 'layout_portrait'].forEach(lk => {
                    const l = Object.assign({}, this.form[lk] || {}); delete l[k]; this.form[lk] = l;
                });
            } else {
                this.setBoth(k, { hidden: true });   // gone from the page whichever way up it is
            }
            this.sel = null;
            this.touch(150);
        },
        customText(k, v) {
            const c = this.custom(k);
            if (! c) return;
            c.text = Object.assign({}, c.text, { [this.lang]: v });
            this.form.elements = (this.form.elements || []).slice();
            this.touch();
        },
        customStyle(k, st) {
            const c = this.custom(k);
            if (! c) return;
            c.style = st;
            this.form.elements = (this.form.elements || []).slice();
            this.touch(150);
        },
        mine(k) { return (this.form[this.lkey] && this.form[this.lkey][k]) || {}; },
        box(k) {
            const d = Object.assign({}, this.isErase(k)
                ? this.fdef('erase_default')[this.portrait ? 'portrait' : 'landscape']
                : this.isCustom(k)
                ? this.fdef('custom_default')[this.portrait ? 'portrait' : 'landscape']
                : this.fdef(this.portrait ? 'layout_defaults_portrait' : 'layout_defaults')[this.placed ? 'placed' : 'plain'][k]);
            const m = this.mine(k);
            const moved = m.x !== undefined || m.y !== undefined;
            if (this.rtl && ! moved && ! this.isErase(k)) {
                d.x = this.pageW - d.x - d.w;
                d.align = { start: 'end', end: 'start' }[d.align] || 'center';
            }
            const out = Object.assign(d, m, { moved });
            // While a box is being dragged it moves on screen only; the layout is written once, on release.
            return live.d && live.d.k === k && live.d.live ? Object.assign(out, live.d.live) : out;
        },
        drawn: {},
        drawnAt: {},
        /* The box around what is really printed: the measured ink, carried along by any
           move or resize made since that preview was drawn. */
        rect(k) {
            const b = this.box(k), d = this.drawn[k], r = this.drawnAt[k];
            if (! d || ! r || ! r.w || ! r.h) return b;
            const fx = b.w / r.w, fy = b.h / r.h;
            const ink = { x: b.x + (d.x - r.x) * fx, y: b.y + (d.y - r.y) * fy, w: d.w * fx, h: d.h * fy };
            // Selected words show the width they may use, as Canva's text frame does: a
            // frame pulled wider stays wide instead of snapping back to the letters.
            // The organiser's own text shows its whole box, top to bottom too, so the words
            // can be placed at its top, middle or bottom (valign).
            if (this.sel === k && this.isCustom(k)) {
                const top = Math.min(b.y, ink.y), bottom = Math.max(b.y + b.h, ink.y + ink.h);
                return { x: b.x, y: top, w: b.w, h: bottom - top };
            }
            if (this.sel === k && this.isWords(k)) return { x: b.x, y: ink.y, w: b.w, h: ink.h };
            return ink;
        },
        isWords(k) { return ['text', 'para', 'block'].includes(this.element(k).kind) && k !== 'signatures'; },
        boxStyle(k) {
            const b = this.rect(k);
            const W = this.pageW, H = this.pageH;
            return 'left:' + (b.x / W * 100) + '%;top:' + (b.y / H * 100) + '%;width:' + (b.w / W * 100) + '%;height:' + (b.h / H * 100) + '%;';
        },
        /* The floating toolbar over the selected element: above it, or below when it
           sits at the top of the page; page-wide elements get it at the top centre. */
        stageTick: 0,
        toolbarStyle() {
            this.stageTick;
            const st = this.$refs.stage, k = this.sel;
            if (! st || ! k) return 'display:none;';
            const W = st.clientWidth, H = st.clientHeight, half = this.canDuplicate(k) ? 95 : 78, th = 40;
            let cx = W / 2, top = 8;
            if (! (this.isPage(k) && k !== 'photo')) {
                const b = this.rect(k);
                const L = b.x / this.pageW * W, T = b.y / this.pageH * H, bw = b.w / this.pageW * W, bh = b.h / this.pageH * H;
                cx = L + bw / 2;
                top = T - th - 12;
                if (top < 4) top = T + bh + 14;
                if (top + th > H - 4) top = Math.max(4, T + 4);
            }
            cx = Math.min(W - half - 4, Math.max(half + 4, cx));
            return 'left:' + cx + 'px;top:' + top + 'px;transform:translateX(-50%);';
        },
        moreOptions() { this.$nextTick(() => { const p = this.$refs.selPanel; if (p) p.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }); },
        setBox(k, patch) {
            const layout = Object.assign({}, this.form[this.lkey] || {});
            layout[k] = Object.assign({}, layout[k] || {}, patch);
            this.form[this.lkey] = layout;
        },
        pick(k) { this.sel = k; },

        startDrag(e, k, mode, side) {
            const stage = e.currentTarget.closest('[data-cert-stage]');
            if (! stage) return;
            e.preventDefault();
            this.sel = k;
            // preventDefault stops the browser focusing the box; focus it so the arrow keys work.
            const own = e.currentTarget.closest('[role=button]');
            if (own && own.focus) own.focus({ preventScroll: true });
            const b = this.box(k);
            live.d = { k, mode, side: side || 'se', sx: e.clientX, sy: e.clientY, rect: stage.getBoundingClientRect(), b, r: this.rect(k), live: null, raf: 0, ev: null, el: own };
            this.drag = { k, mode, r: live.d.r };
            e.currentTarget.setPointerCapture && e.currentTarget.setPointerCapture(e.pointerId);
        },
        onDrag(e) {
            const d = live.d;
            if (! d) return;
            d.ev = { clientX: e.clientX, clientY: e.clientY };
            if (! d.raf) d.raf = requestAnimationFrame(() => { if (live.d === d) { d.raf = 0; this.dragFrame(d.ev); } });
        },
        dragFrame(e) {
            const d = live.d;
            if (! d) return;
            const b = d.b;
            const mmX = (e.clientX - d.sx) / d.rect.width * this.pageW;
            const mmY = (e.clientY - d.sy) / d.rect.height * this.pageH;
            const kind = this.element(d.k).kind;
            if (d.mode === 'move') {
                let x = b.x + mmX;
                // Snap what you SEE to the page's centre line: the middle of the ink
                // (the hugged box), which need not be the middle of the element's area.
                const inkOffset = (d.r.x - b.x) + d.r.w / 2;
                const mid = this.pageW / 2;
                this.guide = Math.abs(x + inkOffset - mid) < 2;
                if (this.guide) x = mid - inkOffset;
                // …and to the page's middle going down, the same way.
                let y = b.y + mmY;
                const inkOffsetY = (d.r.y - b.y) + d.r.h / 2;
                this.guideY = Math.abs(y + inkOffsetY - this.pageH / 2) < 2;
                if (this.guideY) y = this.pageH / 2 - inkOffsetY;
                d.live = { x: +x.toFixed(1), y: +y.toFixed(1) };
            } else if (d.mode === 'width') {
                // A side handle: that edge moves, the opposite one stays put.
                const minW = this.isErase(d.k) ? 3 : 10;
                if (d.side === 'l' || d.side === 'r') {
                    let w = Math.max(minW, b.w + (d.side === 'l' ? -mmX : mmX));
                    // Words stay on the sheet: the edge being pulled stops at the page's edge.
                    if (this.isWords(d.k)) w = Math.min(w, d.side === 'l' ? b.x + b.w : this.pageW - b.x);
                    d.live = { x: +(d.side === 'l' ? b.x + b.w - w : b.x).toFixed(1), y: +b.y.toFixed(1), w: +w.toFixed(1) };
                } else {
                    const h = Math.max(3, b.h + (d.side === 't' ? -mmY : mmY));
                    d.live = { x: +b.x.toFixed(1), y: +(d.side === 't' ? b.y + b.h - h : b.y).toFixed(1), h: +h.toFixed(1) };
                }
            } else {
                // A corner scales what you see (the ratio is taken on the hugged box),
                // with the opposite corner held still — as in Canva.
                const free = d.k === 'panel' || this.isErase(d.k);
                const gx = d.side.includes('w') ? -1 : 1, gy = d.side.includes('n') ? -1 : 1;
                const f = Math.max(0.2, (d.r.w + gx * mmX) / d.r.w);
                const fy = free ? Math.max(0.2, (d.r.h + gy * mmY) / d.r.h) : f;
                const w = Math.max(free ? 3 : 6, b.w * f), h = Math.max(3, b.h * fy);
                const patch = { x: +(gx < 0 ? b.x + b.w - w : b.x).toFixed(1), y: +(gy < 0 ? b.y + b.h - h : b.y).toFixed(1), w: +w.toFixed(1), h: +h.toFixed(1) };
                if (this.isWords(d.k) && patch.w > this.pageW) {   // a frame never grows past the sheet
                    patch.w = this.pageW;
                    patch.x = 0;
                }
                if (['text', 'para', 'block'].includes(kind) && ! free) patch.scale = +Math.min(3, Math.max(0.3, (b.scale || 1) * f)).toFixed(3);
                d.live = patch;
            }
            if (d.el) d.el.setAttribute('style', this.boxStyle(d.k) + 'touch-action:none; cursor:move;');
        },
        endDrag() {
            const d = live.d;
            if (! d) return;
            if (d.raf) cancelAnimationFrame(d.raf);
            live.d = null;
            this.drag = null;
            this.guide = false;
            this.guideY = false;
            if (d.live) {
                this.setBox(d.k, d.live);
                this.dirty = true;
                this.histCommit();
                this.refreshPreview(true);   // one redraw, straight away, after the drop
            }
        },
        /* While dragging, the box carries a picture of its own ink (cut from the last
           preview), so the words themselves are seen moving — not an empty outline. */
        liftStyle(k) {
            const d = this.drag;
            if (! d || d.k !== k || ! this.preview.url || ! d.r) return 'display:none;';
            const r = d.r;
            const W = this.pageW, H = this.pageH;
            const px = r.w >= W ? 0 : r.x / (W - r.w) * 100, py = r.h >= H ? 0 : r.y / (H - r.h) * 100;
            return 'background-image:url(' + this.preview.url + ');background-size:' + (W / r.w * 100) + '% ' + (H / r.h * 100) + '%;'
                + 'background-position:' + px + '% ' + py + '%;background-repeat:no-repeat;box-shadow:0 8px 24px rgba(17,12,34,.25);';
        },
        /* Centre the selected element on the page — across, down, or both — by what you
           SEE (the hugged box), the same measure the drag's centre guide snaps to. */
        centre(k, axis) {
            const b = this.box(k), r = this.rect(k), patch = {};
            if (axis !== 'y') patch.x = +(this.pageW / 2 - ((r.x - b.x) + r.w / 2)).toFixed(1);
            if (axis !== 'x') patch.y = +(this.pageH / 2 - ((r.y - b.y) + r.h / 2)).toFixed(1);
            this.setBox(k, patch);
            this.touch(150);
        },
        nudge(k, dx, dy) {
            const b = this.box(k);
            this.setBox(k, { x: +(b.x + dx).toFixed(1), y: +(b.y + dy).toFixed(1) });
            this.touch(300);
        },
        grow(k, by) {
            const b = this.box(k);
            const s = Math.min(3, Math.max(0.3, (b.scale || 1) * by));
            this.setBox(k, { scale: +s.toFixed(3), w: +(b.w * by).toFixed(1), h: +(b.h * by).toFixed(1), x: +(b.x - b.w * (by - 1) / 2).toFixed(1), y: +b.y.toFixed(1) });
            this.touch(300);
        },
        colour(k, c) { this.setBox(k, { color: c }); this.touch(150); },
        align(k, a) { this.setBox(k, { align: a }); this.touch(150); },
        /* What can be placed top / middle / bottom in its box: the organiser's own texts and
           the one-line words (PageKit::line). The role and ribbon draw their own band. */
        valignable(k) { return !! k && (this.isCustom(k) || (this.element(k).kind === 'text' && ! ['role', 'ribbon'].includes(k))); },
        valign(k, a) { this.setBox(k, { valign: a }); this.touch(150); },
        toggle(k) { this.setBox(k, { hidden: ! this.box(k).hidden }); this.touch(150); },
        resetOne(k) {
            const layout = Object.assign({}, this.form[this.lkey] || {});
            delete layout[k];
            this.form[this.lkey] = layout;
            this.touch(150);
        },
        resetAll() {
            // Back to the default arrangement — but what was removed stays removed, and the
            // organiser's own texts stay (only their positions reset).
            const keep = {};
            Object.entries(this.form[this.lkey] || {}).forEach(([k, b]) => {
                if (b && this.isErase(k)) keep[k] = b;   // an erased area stays where the picture needs it
                else if (b && b.hidden && ! this.isCustom(k)) keep[k] = { hidden: true };
            });
            this.form[this.lkey] = keep;
            this.sel = null;
            this.touch();
        },
        panelOn() { this.setBox('panel', { hidden: false }); this.sel = 'panel'; this.touch(); },

        sigSrc(slot) { return this.url('signature', slot) + '?v=' + (this.t.version || 0); },
        nudgePrint(axis, by) {
            const k = axis === 'x' ? 'print_offset_x' : 'print_offset_y';
            this.form[k] = Math.max(-30, Math.min(30, (+this.form[k] || 0) + by));
            this.touch();
        },
    };
};
</script>
