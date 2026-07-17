{{-- Shared Alpine component for schedule-show — used by both mobile and desktop. --}}
        <script>
        function classReviewsCard(cfg) {
            return {
                avg: (cfg.avg ?? null),
                count: cfg.count || 0,
                reviews: cfg.reviews || [],
                dist: cfg.dist || {},
                myUserId: cfg.myUserId || null,
                myRating: cfg.myRating || 0,
                myComment: cfg.myComment || '',
                deleting: false,
                // Has the current user already rated this class?
                get hasReview() { return this.myRating > 0; },
                // Written reviews from everyone except the current user (theirs shows in its own block).
                get othersReviews() {
                    const me = String(this.myUserId);
                    return (this.reviews || []).filter(r => String(r.user_id) !== me);
                },
                // No review yet → open the rating form straight away.
                startReview() { window.dispatchEvent(new CustomEvent('open-review-form')); },
                // Already reviewed → confirm first, then open the form pre-filled to edit.
                async editMine() {
                    const ok = await window.confirmAction({
                        title: '{{ __("personal.personal_schedule_show_revise_title") }}',
                        message: '{{ __("personal.personal_schedule_show_revise_message") }}',
                        type: 'info',
                        confirmText: '{{ __("personal.personal_schedule_show_edit_review") }}',
                        cancelText: '{{ __("personal.personal_schedule_show_keep_as_is") }}',
                    });
                    if (ok) window.dispatchEvent(new CustomEvent('open-review-form'));
                },
                // Already reviewed → confirm, then delete the review entirely.
                async deleteMine() {
                    if (this.deleting) return;
                    const ok = await window.confirmAction({
                        title: '{{ __("personal.personal_schedule_show_delete_title") }}',
                        message: '{{ __("personal.personal_schedule_show_delete_message") }}',
                        type: 'danger',
                        confirmText: '{{ __("shared.delete") }}',
                        cancelText: '{{ __("shared.cancel") }}',
                    });
                    if (!ok) return;
                    this.deleting = true;
                    try {
                        const res = await fetch(cfg.deleteUrl, {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_delete_review") }}'); return; }
                        this.avg = (data.average ?? null); this.count = data.count || 0;
                        this.reviews = data.comments || []; this.dist = data.distribution || {};
                        this.myRating = 0; this.myComment = '';
                        // Reset the form's state too, so reopening it starts fresh.
                        window.dispatchEvent(new CustomEvent('class-review-deleted', { detail: { average: (data.average ?? null), count: data.count || 0 } }));
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_review_deleted") }}');
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.deleting = false; }
                },
                // Live patch when the current user submits/updates their class rating.
                patch(d) {
                    if (!d) return;
                    if (d.average !== undefined) this.avg = d.average;
                    if (d.count !== undefined) this.count = d.count || 0;
                    if (d.comments !== undefined) this.reviews = d.comments || [];
                    if (d.distribution !== undefined) this.dist = d.distribution || {};
                    if (d.mine) { this.myRating = d.mine.rating || 0; this.myComment = d.mine.comment || ''; }
                },
            };
        }
        </script>
