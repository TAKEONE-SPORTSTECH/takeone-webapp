{{-- Shared Alpine component for schedule-show — used by both mobile and desktop. --}}
        <script>
        function classCancelTool(cfg) {
            return {
                open: false, busy: false,
                todayStr: new Date().toISOString().slice(0, 10),
                form: { from: cfg.date, to: '', reason: '', credit: true },
                async submit() {
                    if (!this.form.from) { window.showToast('error', '{{ __("personal.personal_schedule_show_pick_date") }}'); return; }
                    this.busy = true;
                    try {
                        const res = await fetch(cfg.cancelUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ from: this.form.from, to: this.form.to || null, reason: this.form.reason || null, credit: this.form.credit }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_cancel") }}'); this.busy = false; return; }
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_class_cancelled") }}');
                        this.open = false; this._back();
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.busy = false; }
                },
                async restore() {
                    this.busy = true;
                    try {
                        const res = await fetch(cfg.uncancelUrl, {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_restore") }}'); this.busy = false; return; }
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_class_restored") }}');
                        this._back();
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.busy = false; }
                },
                _back() {
                    setTimeout(function () {
                        var a = document.querySelector('a[data-route="me.schedule"]');
                        if (a) a.click(); else window.location.href = cfg.listUrl;
                    }, 400);
                },
            };
        }
        </script>
