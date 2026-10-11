{{--
    The running-order board's behaviour — ONE implementation, driven by both the
    mobile and the desktop board. The two views differ in layout only; the rules
    about what may move, what a write sends and what a nudge re-reads are the
    same rules, and a second copy of them would drift (CLAUDE.md → Shared Stays
    Shared).

    Registered with Alpine.data() rather than written inline, because the add
    sheets live inside <template x-teleport="body"> and a <script> in there is
    inert — an inline x-data would resolve to an EMPTY scope and every binding
    would fail separately and silently.

    Every write patches the board in place from the server's own answer (the
    No-Reload rule); nothing here reloads the page, and nothing recomputes a
    clock time in the browser — the times come back worked out, from the same
    RunSheet the public page reads.
--}}
@once
@push('scripts')
<script>
(function () {
    function register() {
        if (! window.Alpine || window.Alpine.data.__runOrderBoard) { return; }
        window.Alpine.data.__runOrderBoard = true;

        window.Alpine.data('runOrderBoard', function (cfg) {
            return {
                cfg: cfg,
                sheet: cfg.sheet || { rows: [], unscheduled: [], conflicts: [] },
                busy: false,
                picked: null,          // uuid of the part being moved
                drag: null,            // uuid of the card being dragged
                dragFrom: null,        // its index when the drag began
                dragTo: null,          // the index it would take if dropped now
                suppressTap: false,    // a drop is not also a tap on the card
                addOpen: false,        // the "add divisions" sheet
                blockOpen: false,      // the "add a part" sheet
                chosen: [],            // division ids ticked in the add sheet
                chosenParts: {},       // division id => the parts ticked for it (empty = all that is left)
                together: false,       // one medal ceremony for everyone at the end of the day
                blockKind: 'ceremony',
                blockLabel: '',
                blockDuration: 15,

                init() {
                    /*
                     * Another organiser moving a part must show up here without
                     * a refresh. A refresh SIGNAL, not a payload: what a board
                     * may show depends on who is reading it, so each one
                     * re-fetches its own view rather than being handed someone
                     * else's.
                     *
                     * Deduped on window, because this page can be swapped in by
                     * the mobile shell and the listener would otherwise stack.
                     */
                    const key = '__runOrderRealtime_' + this.cfg.eventUuid;
                    if (window[key]) { window.removeEventListener('realtime:events', window[key]); }
                    window[key] = (e) => {
                        const d = e.detail || {};
                        if (d.event && d.event !== this.cfg.eventUuid) { return; }
                        this.reload();
                    };
                    window.addEventListener('realtime:events', window[key]);
                },

                get rows() { return this.sheet.rows || []; },
                get bench() { return this.sheet.unscheduled || []; },
                get conflicts() { return this.sheet.conflicts || []; },
                get isEmpty() { return this.rows.length === 0; },

                /* ---------- reads ---------- */

                async reload() {
                    try {
                        const r = await fetch(this.cfg.dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (! r.ok) { return; }
                        const j = await r.json();
                        if (j && j.sheet) { this.sheet = j.sheet; }
                    } catch (e) { /* a dropped connection leaves the board as it was */ }
                },

                /* ---------- writes ---------- */

                async send(url, method, body) {
                    if (this.busy) { return false; }
                    this.busy = true;
                    try {
                        const r = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: body ? JSON.stringify(body) : null,
                        });
                        const j = await r.json().catch(() => null);
                        if (! r.ok || ! j || ! j.success) {
                            window.showToast?.('error', (j && j.message) || this.cfg.errorText);
                            return false;
                        }
                        if (j.sheet) { this.sheet = j.sheet; }
                        return true;
                    } catch (e) {
                        window.showToast?.('error', this.cfg.errorText);
                        return false;
                    } finally {
                        this.busy = false;
                    }
                },

                /* ---------- moving a part ---------- */

                pick(uuid) {
                    if (this.suppressTap) { return; }
                    this.picked = this.picked === uuid ? null : uuid;
                },

                /** Place the picked part at index `at` in the current order. */
                async placeAt(at) {
                    const uuid = this.picked;
                    if (! uuid) { return; }

                    const order = this.rows.map(r => r.uuid).filter(u => u !== uuid);
                    const from = this.rows.findIndex(r => r.uuid === uuid);
                    let target = at;
                    if (from > -1 && from < at) { target = at - 1; }
                    order.splice(Math.max(0, Math.min(order.length, target)), 0, uuid);

                    this.picked = null;
                    await this.send(this.cfg.reorderUrl, 'PUT', { day: this.cfg.day, order: order });
                },

                /**
                 * Hold a placed part's CARD and drag it into place (owner,
                 * 2026-10-07). The card lifts and follows the pointer; the
                 * cards it passes slide aside to open the gap it will drop
                 * into; letting go saves through placeAt(), the same write
                 * tap-to-move uses.
                 *
                 * Mouse: press and move. Touch: press and HOLD, then move — a
                 * quick swipe still scrolls the list. Touch events (not
                 * pointer events) on a phone, because the WebView cancels a
                 * pointer the moment the page starts to scroll. The card's own
                 * buttons (pin, remove, mats, arrows) never start a drag.
                 */
                rowDown(e, uuid, i) {
                    const touch = e.type === 'touchstart';
                    if (! touch && (e.pointerType === 'touch' || (e.button !== undefined && e.button !== 0))) { return; }
                    const t = e.target;
                    if (t.closest && t.closest('button') && ! t.closest('[data-drag-ok]')) { return; }
                    if (this.drag !== null || this.busy) { return; }

                    const p0 = touch ? e.touches[0] : e;
                    const x0 = p0.clientX, y0 = p0.clientY;
                    let started = false, timer = null;
                    const ev = touch ? ['touchmove', 'touchend', 'touchcancel'] : ['pointermove', 'pointerup', 'pointercancel'];

                    const begin = () => {
                        started = true;
                        this.beginDrag(uuid, i, y0);
                        if (touch && navigator.vibrate) { try { navigator.vibrate(12); } catch (x) {} }
                    };
                    const move = (m) => {
                        const p = touch ? m.touches[0] : m;
                        if (! started) {
                            const far = Math.abs(p.clientY - y0) > 8 || Math.abs(p.clientX - x0) > 8;
                            if (touch) { if (far) { stop(); } return; }   // a swipe: let it scroll
                            if (Math.abs(p.clientY - y0) > 5) { begin(); } else { return; }
                        }
                        if (m.cancelable) { m.preventDefault(); }
                        this.dragMove(p.clientY);
                    };
                    const stop = () => {
                        clearTimeout(timer);
                        window.removeEventListener(ev[0], move);
                        window.removeEventListener(ev[1], end);
                        window.removeEventListener(ev[2], end);
                    };
                    const end = () => {
                        stop();
                        if (! started) { return; }
                        // The release must not also count as a tap on the card.
                        this.suppressTap = true;
                        setTimeout(() => { this.suppressTap = false; }, 80);
                        this.endDrag();
                    };

                    if (touch) { timer = setTimeout(begin, 260); }
                    window.addEventListener(ev[0], move, { passive: false });
                    window.addEventListener(ev[1], end);
                    window.addEventListener(ev[2], end);
                },

                beginDrag(uuid, i, y0) {
                    const els = Array.from(this.$root.querySelectorAll('[data-ro-row]')).filter(el => el.offsetParent !== null);
                    const rects = els.map(el => el.getBoundingClientRect());
                    this.picked = null;
                    this.drag = uuid; this.dragFrom = i; this.dragTo = i;
                    this._dg = {
                        els: els, mids: rects.map(r => r.top + r.height / 2), y0: y0, s0: window.scrollY,
                        // The gap a card leaves: its height plus the space to its neighbour.
                        step: (rects[i] ? rects[i].height : 0) + (rects.length > 1 ? Math.max(0, rects[1].top - rects[0].bottom) : 0),
                    };
                    els.forEach((el, k) => {
                        el.style.transition = k === i ? 'box-shadow .15s, transform 0s' : 'transform .18s cubic-bezier(.2,.8,.2,1)';
                        el.style.willChange = 'transform';
                    });
                    const me = els[i];
                    if (me) { me.style.position = 'relative'; me.style.zIndex = '20'; me.style.boxShadow = '0 14px 34px rgba(17,24,39,.22)'; me.style.borderRadius = '14px'; me.style.background = '#fff'; me.style.transform = 'scale(1.02)'; }
                },

                dragMove(y) {
                    const g = this._dg;
                    if (! g) { return; }
                    const edge = 70;
                    if (y < edge) { window.scrollBy(0, -14); } else if (y > window.innerHeight - edge) { window.scrollBy(0, 14); }

                    const dy = (y - g.y0) + (window.scrollY - g.s0);
                    const from = this.dragFrom;
                    const centre = g.mids[from] + dy;

                    // Where it would land: past every card whose middle it has passed.
                    let to = 0;
                    g.mids.forEach((m, k) => { if (k !== from && centre > m) { to++; } });
                    this.dragTo = to;

                    g.els.forEach((el, k) => {
                        if (k === from) { el.style.transform = `translateY(${dy}px) scale(1.02)`; return; }
                        let shift = 0;
                        if (from < to && k > from && k <= to) { shift = -g.step; }
                        if (to < from && k >= to && k < from) { shift = g.step; }
                        el.style.transform = shift ? `translateY(${shift}px)` : '';
                    });
                },

                async endDrag() {
                    const g = this._dg, from = this.dragFrom, to = this.dragTo, uuid = this.drag;
                    if (g) {
                        g.els.forEach(el => { el.style.transition = 'none'; el.style.transform = ''; el.style.boxShadow = ''; el.style.zIndex = ''; el.style.willChange = ''; el.style.background = ''; });
                    }
                    this._dg = null; this.drag = null; this.dragFrom = null; this.dragTo = null;
                    if (uuid === null || to === null || to === from) { return; }

                    // Into place at once — the server's answer (with the new
                    // times) follows a moment later.
                    const rows = (this.sheet.rows || []).slice();
                    rows.splice(to, 0, rows.splice(from, 1)[0]);
                    this.sheet = Object.assign({}, this.sheet, { rows: rows });
                    await this.send(this.cfg.reorderUrl, 'PUT', { day: this.cfg.day, order: rows.map(r => r.uuid) });
                },

                /** The desktop board's arrows — one step, no picking. */
                async nudge(uuid, delta) {
                    const order = this.rows.map(r => r.uuid);
                    const i = order.indexOf(uuid);
                    const to = i + delta;
                    if (i < 0 || to < 0 || to >= order.length) { return; }
                    order.splice(to, 0, order.splice(i, 1)[0]);
                    await this.send(this.cfg.reorderUrl, 'PUT', { day: this.cfg.day, order: order });
                },

                /* ---------- one part ---------- */

                slotUrl(uuid) { return this.cfg.slotUrl.replace('__SLOT__', uuid); },

                async togglePin(row) {
                    await this.send(this.slotUrl(row.uuid), 'PUT', {
                        pin: ! row.pinned,
                        pinned_at: row.starts_at,
                    });
                },

                async remove(row) {
                    const ok = window.confirmAction
                        ? await window.confirmAction({
                            title: this.cfg.removeTitle,
                            message: this.cfg.removeBody.replace(':name', row.name),
                            type: 'danger',
                            confirmText: this.cfg.removeConfirm,
                        })
                        : true;
                    if (! ok) { return; }
                    await this.send(this.slotUrl(row.uuid), 'DELETE', null);
                },

                /* ---------- adding ---------- */

                // Nothing is ticked when the sheet opens (owner, 2026-10-07): an
                // organiser picks what goes on the day — Select all is one tap.
                openAdd() { this.chosen = []; this.chosenParts = {}; this.together = !! this.sheet.together; this.addOpen = true; },
                toggleChosen(id) {
                    const i = this.chosen.indexOf(id);
                    if (i > -1) { this.chosen.splice(i, 1); delete this.chosenParts[id]; } else { this.chosen.push(id); }
                },
                isChosen(id) { return this.chosen.indexOf(id) > -1; },
                get allChosen() { return this.bench.length > 0 && this.chosen.length === this.bench.length; },
                toggleAll() {
                    if (this.allChosen) { this.chosen = []; this.chosenParts = {}; }
                    else { this.chosen = this.bench.map(c => c.id); }
                },

                /* Parts (BoutPart): a division with a draw can go on the day
                   part by part — All, or any of what is LEFT of it. Ticking no
                   part means All; ticking every part is All too. */
                partLabel(p) { return (this.sheet.part_labels || {})[p] || p; },
                // With one ceremony for everyone, a division's own awarding is not offered.
                partsOf(c) {
                    const r = Array.isArray(c.remaining) && Array.isArray(c.parts) && c.parts.length ? c.remaining : [];
                    return this.together ? r.filter(p => p !== 'awarding') : r;
                },
                hasPart(c, p) { return (this.chosenParts[c.id] || []).indexOf(p) > -1; },
                isAllParts(c) { const t = this.chosenParts[c.id] || []; return t.length === 0 || t.length === this.partsOf(c).length; },
                togglePart(c, p) {
                    if (! this.isChosen(c.id)) { this.chosen.push(c.id); }
                    const t = (this.chosenParts[c.id] || []).slice();
                    const i = t.indexOf(p);
                    if (i > -1) { t.splice(i, 1); } else { t.push(p); }
                    this.chosenParts = Object.assign({}, this.chosenParts, { [c.id]: t });
                },
                setAllParts(c) {
                    if (! this.isChosen(c.id)) { this.chosen.push(c.id); }
                    this.chosenParts = Object.assign({}, this.chosenParts, { [c.id]: [] });
                },

                get eventBreak() { return this.sheet.event_break || null; },
                /* Editing the event's break (PersonalEventController::updateEventBreak):
                   times in 24-hour "HH:MM", stepped by five minutes. */
                breakOpen: false, breakStart: '13:00', breakEnd: '14:00',
                openBreak() {
                    const b = this.eventBreak || this.sheet.break_times || {};
                    this.breakStart = b.start || '13:00'; this.breakEnd = b.end || '14:00';
                    this.breakOpen = true;
                },
                stepTime(which, delta) {
                    const key = which === 'start' ? 'breakStart' : 'breakEnd';
                    const [h, m] = this[key].split(':').map(Number);
                    let t = Math.max(0, Math.min(23 * 60 + 55, h * 60 + m + delta));
                    this[key] = String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
                },
                clock12(hm) {
                    const [h, m] = hm.split(':').map(Number);
                    return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + (h < 12 ? 'AM' : 'PM');
                },
                get breakMinutes() {
                    const t = s => { const [h, m] = s.split(':').map(Number); return h * 60 + m; };
                    return t(this.breakEnd) - t(this.breakStart);
                },
                async saveBreak() {
                    if (this.breakMinutes <= 0) { return; }
                    const ok = await this.send(this.cfg.breakUrl, 'PUT', { day: this.cfg.day, start: this.breakStart, end: this.breakEnd });
                    if (ok) { this.breakOpen = false; }
                },
                async addEventBreak() {
                    await this.send(this.cfg.storeUrl, 'POST', { day: this.cfg.day, event_break: true });
                },

                async addDivisions() {
                    if (! this.chosen.length) { this.addOpen = false; return; }
                    const divisions = this.chosen.map(id => {
                        const c = this.bench.find(b => b.id === id) || {};
                        const t = this.chosenParts[id] || [];
                        return { id: id, parts: (t.length && t.length < this.partsOf(c).length) ? t : null };
                    });
                    const ok = await this.send(this.cfg.storeUrl, 'POST', { day: this.cfg.day, divisions: divisions, together_ceremony: this.together });
                    if (ok) { this.addOpen = false; this.chosen = []; this.chosenParts = {}; }
                },

                openBlock(kind) {
                    this.blockKind = kind;
                    this.blockLabel = '';
                    this.blockDuration = kind === 'break' ? 30 : 15;
                    this.blockOpen = true;
                },

                async addBlock() {
                    const ok = await this.send(this.cfg.storeUrl, 'POST', {
                        day: this.cfg.day,
                        kind: this.blockKind,
                        label: this.blockLabel || null,
                        duration_minutes: parseInt(this.blockDuration, 10) || null,
                    });
                    if (ok) { this.blockOpen = false; }
                },

                /* ---------- drawing ---------- */

                dot(row) {
                    if (row.kind === 'break') { return '#9ca3af'; }
                    if (row.kind === 'ceremony') { return '#d97706'; }
                    return this.cfg.color;
                },

                /* THIS DIVISION, ON THE MATS — the other tab of this page,
                   highlighted on the division that was tapped. The page owns
                   which view is showing (personal/partials/order-page-script);
                   this board only says what was asked for, on the window,
                   because the two boards are siblings with no scope in common. */
                jumpToMats(row) {
                    if (! row || ! row.category_id) { return; }
                    window.dispatchEvent(new CustomEvent('order-page:show-mats', {
                        detail: { division: row.category_id },
                    }));
                },

                meta(row) {
                    if (row.orphaned) { return this.cfg.orphanText; }
                    if (row.is_division) {
                        return this.cfg.enteredText
                            .replace(':count', row.entrants ?? 0)
                            .replace(':bouts', row.bouts ?? 0);
                    }
                    return row.pinned
                        ? this.cfg.pinnedText.replace(':time', row.starts_label)
                        : this.cfg.noBoutsText;
                },
            };
        });
    }

    if (window.Alpine) { register(); }
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce
