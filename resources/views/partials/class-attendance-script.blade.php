{{-- Shared Alpine component for schedule-show — used by both mobile and desktop. --}}
        <script>
        // Attendance toggles for the shown occurrence — optimistic, persisted via AJAX.
        function classAttendance(cfg) {
            return {
                att: cfg.attended || {},
                members: cfg.members || {},        // id -> {name, attended, total, breakdown[{date,label,attended}]}
                busy: {},
                bd: null,                          // attendance-breakdown sheet payload (a members[id] ref)
                nowTs: Date.now(),
                // Occurrence start/end as local epochs (browser clock → correct window regardless of server TZ).
                _mk: function (hhmm) {
                    if (!hhmm || !cfg.date) return null;
                    var p = String(hhmm).split(':');
                    var d = new Date(cfg.date + 'T00:00:00');         // local midnight of the occurrence
                    d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
                    return d.getTime();
                },
                get startTs() { return this._mk(cfg.startTime); },
                get endTs() {
                    var end = this._mk(cfg.endTime) || this._mk(cfg.startTime);   // prefer end; fall back to start
                    var s = this._mk(cfg.startTime), e = this._mk(cfg.endTime);
                    if (end != null && e != null && s != null && e <= s) end += 86400000;  // crosses midnight
                    return end;
                },
                // Attendance is markable only DURING the class. The server (club timezone) is
                // authoritative for the initial state; the local clock flips these live while
                // the page is open.
                get hasStarted() { return cfg.startedServer === true || (this.startTs != null && this.nowTs >= this.startTs); },
                get isOver()     { return cfg.endedServer === true   || (this.endTs   != null && this.nowTs >= this.endTs); },
                get notStarted() { return !this.hasStarted; },
                get canMarkNow() { return this.hasStarted && !this.isOver; },
                init() {
                    var self = this;
                    if (window.__attTick) clearInterval(window.__attTick);   // dedup across shell swaps
                    window.__attTick = setInterval(function () { self.nowTs = Date.now(); }, 1000);
                },
                openBreakdown(id) { this.bd = this.members[id] || null; },
                rateLabel(id) { const m = this.members[id]; return (m && m.total > 0) ? (m.attended + '/' + m.total) : ''; },
                rateClass(id) {
                    const m = this.members[id];
                    if (!m || !m.total) return '';
                    const p = m.attended / m.total;
                    return p >= 0.75 ? 'bg-green-50 text-green-600' : (p >= 0.5 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-500');
                },
                // Reflect a just-toggled attendance into the member's rate + breakdown.
                syncRate(id, attended) {
                    const m = this.members[id];
                    if (!m) return;
                    const row = (m.breakdown || []).find(b => b.key === cfg.curKey);
                    if (row) row.attended = attended;            // this session is in the counted history
                    m.attended = (m.breakdown || []).filter(b => b.attended).length;
                },
                get presentCount() { return Object.values(this.att).filter(Boolean).length; },
                async toggle(id) {
                    if (this.busy[id]) return;
                    if (this.notStarted) { window.showToast('info', '{{ __("personal.personal_schedule_show_not_started_toast") }}'); return; }
                    if (this.isOver) { window.showToast('info', '{{ __("personal.personal_schedule_show_attendance_closed") }}'); return; }
                    const next = !this.att[id];
                    this.att[id] = next;          // optimistic
                    this.busy[id] = true;
                    try {
                        const res = await fetch(cfg.url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ user_id: id, date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { this.att[id] = !next; window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_attendance") }}'); }
                        else { this.att[id] = !!data.attended; this.syncRate(id, !!data.attended); }
                    } catch (e) {
                        this.att[id] = !next;
                        window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}');
                    } finally {
                        this.busy[id] = false;
                    }
                },
            };
        }
        </script>
