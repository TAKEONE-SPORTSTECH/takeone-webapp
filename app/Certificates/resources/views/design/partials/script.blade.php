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
            this.form = JSON.parse(JSON.stringify(this.t));
            this.dirty = false;
            this.refreshPreview(true);
        },
        url(key, slot) {
            let u = this.ep[key].replace('xtypex', this.type);
            if (slot) u = u.replace(/\/signature\/1$/, '/signature/' + slot);
            return u;
        },
        touch(wait) { this.dirty = true; this.refreshPreview(false, wait); },

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
                this.form = JSON.parse(JSON.stringify(this.t));
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
                this.form = JSON.parse(JSON.stringify(this.t));
                window.showToast('success', d.message);
                this.refreshPreview(true);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.uploading = null; input.value = ''; }
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
                this.form = JSON.parse(JSON.stringify(this.t));
                window.showToast('success', d.message);
                this.refreshPreview(true);
            } catch (e) { window.showToast('error', e.message); }
        },
        /* ─────────────── arranging the page ───────────────
           Boxes are drawn over the preview from the same numbers the PDF uses
           (Support\Layout: millimetres on a 297 × 210 page). Only what the
           organiser changes is stored; a default is mirrored in Arabic, a box
           they have placed stays where they put it in every language. */
        sel: null,
        drag: null,
        guide: false,

        get placed() { return this.type === 'podium'; },
        get rtl() { return this.lang === 'ar'; },
        get keys() {
            return this.state.elements.map(e => e.key).filter(k =>
                (this.placed || ! ['medal', 'ribbon'].includes(k)) && (this.form.show_photo || k !== 'photo'));
        },
        element(k) { return this.state.elements.find(e => e.key === k) || { kind: 'text', label: k }; },
        mine(k) { return (this.form.layout && this.form.layout[k]) || {}; },
        box(k) {
            const d = Object.assign({}, this.state.layout_defaults[this.placed ? 'placed' : 'plain'][k]);
            const m = this.mine(k);
            const moved = m.x !== undefined || m.y !== undefined;
            if (this.rtl && ! moved) {
                d.x = 297 - d.x - d.w;
                d.align = { start: 'end', end: 'start' }[d.align] || 'center';
            }
            const out = Object.assign(d, m, { moved });
            // While a box is being dragged it moves on screen only; the layout is written once, on release.
            return this.drag && this.drag.k === k && this.drag.live ? Object.assign(out, this.drag.live) : out;
        },
        drawn: {},
        drawnAt: {},
        /* The box around what is really printed: the measured ink, carried along by any
           move or resize made since that preview was drawn. */
        rect(k) {
            const b = this.box(k), d = this.drawn[k], r = this.drawnAt[k];
            if (! d || ! r || ! r.w || ! r.h) return b;
            const fx = b.w / r.w, fy = b.h / r.h;
            return { x: b.x + (d.x - r.x) * fx, y: b.y + (d.y - r.y) * fy, w: d.w * fx, h: d.h * fy };
        },
        boxStyle(k) {
            const b = this.rect(k);
            return 'left:' + (b.x / 297 * 100) + '%;top:' + (b.y / 210 * 100) + '%;width:' + (b.w / 297 * 100) + '%;height:' + (b.h / 210 * 100) + '%;';
        },
        setBox(k, patch) {
            const layout = Object.assign({}, this.form.layout || {});
            layout[k] = Object.assign({}, layout[k] || {}, patch);
            this.form.layout = layout;
        },
        pick(k) { this.sel = k; },

        startDrag(e, k, mode) {
            const stage = e.currentTarget.closest('[data-cert-stage]');
            if (! stage) return;
            e.preventDefault();
            this.sel = k;
            // preventDefault stops the browser focusing the box; focus it so the arrow keys work.
            const own = e.currentTarget.closest('[role=button]');
            if (own && own.focus) own.focus({ preventScroll: true });
            const b = this.box(k);
            this.drag = { k, mode, sx: e.clientX, sy: e.clientY, rect: stage.getBoundingClientRect(), b, r: this.rect(k), live: null, raf: 0, ev: null };
            e.currentTarget.setPointerCapture && e.currentTarget.setPointerCapture(e.pointerId);
        },
        onDrag(e) {
            if (! this.drag) return;
            this.drag.ev = { clientX: e.clientX, clientY: e.clientY };
            if (! this.drag.raf) this.drag.raf = requestAnimationFrame(() => { if (this.drag) { this.drag.raf = 0; this.dragFrame(this.drag.ev); } });
        },
        dragFrame(e) {
            const d = this.drag, b = d.b;
            const mmX = (e.clientX - d.sx) / d.rect.width * 297;
            const mmY = (e.clientY - d.sy) / d.rect.height * 210;
            const kind = this.element(d.k).kind;
            if (d.mode === 'move') {
                let x = b.x + mmX;
                // Snap what you SEE to the page's centre line: the middle of the ink
                // (the hugged box), which need not be the middle of the element's area.
                const inkOffset = (d.r.x - b.x) + d.r.w / 2;
                this.guide = Math.abs(x + inkOffset - 148.5) < 2;
                if (this.guide) x = 148.5 - inkOffset;
                d.live = { x: +x.toFixed(1), y: +(b.y + mmY).toFixed(1) };
            } else if (d.mode === 'width') {
                d.live = { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +Math.max(10, b.w + mmX).toFixed(1) };
            } else {
                // The corner scales what you see: the ratio is taken on the hugged box.
                const free = d.k === 'panel';
                const f = Math.max(0.2, (d.r.w + mmX) / d.r.w);
                const fy = free ? Math.max(0.2, (d.r.h + mmY) / d.r.h) : f;
                const patch = { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +Math.max(6, b.w * f).toFixed(1), h: +Math.max(3, b.h * fy).toFixed(1) };
                if (['text', 'para', 'block'].includes(kind) && ! free) patch.scale = +Math.min(3, Math.max(0.3, (b.scale || 1) * f)).toFixed(3);
                d.live = patch;
            }
        },
        endDrag() {
            if (! this.drag) return;
            const d = this.drag;
            if (d.raf) cancelAnimationFrame(d.raf);
            this.drag = null;
            this.guide = false;
            if (d.live) {
                this.setBox(d.k, d.live);
                this.dirty = true;
                this.refreshPreview(true);   // one redraw, straight away, after the drop
            }
        },
        /* While dragging, the box carries a picture of its own ink (cut from the last
           preview), so the words themselves are seen moving — not an empty outline. */
        liftStyle(k) {
            const d = this.drag;
            if (! d || d.k !== k || ! this.preview.url || ! d.r) return 'display:none;';
            const r = d.r;
            const px = r.w >= 297 ? 0 : r.x / (297 - r.w) * 100, py = r.h >= 210 ? 0 : r.y / (210 - r.h) * 100;
            return 'background-image:url(' + this.preview.url + ');background-size:' + (297 / r.w * 100) + '% ' + (210 / r.h * 100) + '%;'
                + 'background-position:' + px + '% ' + py + '%;background-repeat:no-repeat;box-shadow:0 8px 24px rgba(17,12,34,.25);';
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
        toggle(k) { this.setBox(k, { hidden: ! this.box(k).hidden }); this.touch(150); },
        resetOne(k) {
            const layout = Object.assign({}, this.form.layout || {});
            delete layout[k];
            this.form.layout = layout;
            this.touch(150);
        },
        resetAll() { this.form.layout = {}; this.sel = null; this.touch(); },
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
