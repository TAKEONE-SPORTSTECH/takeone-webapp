{{-- Shared Alpine component for schedule-show — used by both mobile and desktop. --}}
        <script>
        function classEngage(cfg) {
            return {
                panel: false, savingAll: false,
                counts: cfg.counts || {}, mine: cfg.mine || null, rating: cfg.rating || 0, comment: cfg.comment || '',
                classRating: cfg.classRating || 0, classComment: cfg.classComment || '',
                classAvg: (cfg.classAvg ?? null), classCount: cfg.classCount || 0, comments: cfg.comments || [],
                async react(emoji) {
                    try {
                        const res = await fetch(cfg.reactUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin', body: JSON.stringify({ emoji: emoji, date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_react") }}'); return; }
                        this.counts = data.counts || {}; this.mine = data.mine || null;
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                },
                rate(n) { this.rating = n; },           // select locally; saved on submit
                rateClass(n) { this.classRating = n; }, // select locally; saved on submit
                // One submit: save whichever ratings were given, then close the sheet.
                async submitAll() {
                    if (this.savingAll) return;
                    if (this.classRating < 1 && this.rating < 1) { this.panel = false; return; }
                    this.savingAll = true;
                    try {
                        if (this.classRating > 0) await this._post(cfg.rateClassUrl, { rating: this.classRating, comment: this.classComment || null }, (data) => {
                            this.classAvg = (data.average ?? null); this.classCount = data.count || 0; this.comments = data.comments || [];
                            // Patch the standalone "Trainee reviews" card (lives in its own Alpine scope, beneath the map).
                            window.dispatchEvent(new CustomEvent('class-reviews-updated', { detail: {
                                average: (data.average ?? null), count: data.count || 0,
                                comments: data.comments || [], distribution: data.distribution || {},
                                mine: { rating: this.classRating, comment: this.classComment || '' },
                            }}));
                        });
                        if (this.rating > 0) await this._post(cfg.rateUrl, { rating: this.rating, comment: this.comment || null, date: cfg.date });
                        window.showToast('success', '{{ __("personal.personal_schedule_show_rating_saved") }}');
                        this.panel = false;
                    } catch (e) { window.showToast('error', e.message || '{{ __("personal.personal_schedule_show_could_not_save_rating") }}'); }
                    finally { this.savingAll = false; }
                },
                async _post(url, body, onOk) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin', body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) throw new Error(data.message || '{{ __("personal.personal_schedule_show_could_not_save_rating") }}');
                    if (onOk) onOk(data);
                    return data;
                },
            };
        }
        </script>
