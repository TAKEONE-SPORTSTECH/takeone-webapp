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
                addOpen: false,        // the "add divisions" sheet
                blockOpen: false,      // the "add a part" sheet
                chosen: [],            // division ids ticked in the add sheet
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

                openAdd() { this.chosen = this.bench.map(c => c.id); this.addOpen = true; },
                toggleChosen(id) {
                    const i = this.chosen.indexOf(id);
                    if (i > -1) { this.chosen.splice(i, 1); } else { this.chosen.push(id); }
                },
                isChosen(id) { return this.chosen.indexOf(id) > -1; },

                async addDivisions() {
                    if (! this.chosen.length) { this.addOpen = false; return; }
                    const ok = await this.send(this.cfg.storeUrl, 'POST', { day: this.cfg.day, category_ids: this.chosen });
                    if (ok) { this.addOpen = false; this.chosen = []; }
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
