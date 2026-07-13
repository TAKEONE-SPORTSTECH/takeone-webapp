{{-- Shared JS for the inline owner controls on the public club page. Included
     once (only when the viewer can manage the club). Powers <x-owner-actions>:
     Edit dispatches a window event the matching modal listens for; Hide toggles
     visibility in place; Delete removes the card in place — all no-reload. --}}
<script>
(function () {
    if (window.__ownerControlsInit) return;
    window.__ownerControlsInit = true;

    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

    async function send(url, method) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) throw new Error(data.message || @js(__('shared.error')));
        return data;
    }

    window.ownerActions = function (cfg) {
        return {
            open: false,
            y: 0, r: 0,
            hidden: !!cfg.hidden,
            editEvent: cfg.editEvent || null,
            get card() { return this.$root.closest('[data-owner-card]'); },

            toggle(btn) {
                if (!this.open) {
                    const rect = btn.getBoundingClientRect();
                    this.y = Math.round(rect.bottom + 6);
                    this.r = Math.round(window.innerWidth - rect.right);
                }
                this.open = !this.open;
            },

            edit() {
                this.open = false;
                if (this.editEvent) window.dispatchEvent(new CustomEvent(this.editEvent, { detail: { id: cfg.id, data: cfg.data || null } }));
            },

            async toggleHide() {
                this.open = false;
                try {
                    const d = await send(cfg.hideUrl, 'POST');
                    this.hidden = !!d.hidden;
                    // Dim the card + flag it while hidden from the public.
                    const card = this.card;
                    if (card) {
                        card.classList.toggle('owner-hidden', this.hidden);
                        let badge = card.querySelector('[data-owner-hidden-badge]');
                        if (this.hidden && !badge) {
                            badge = document.createElement('span');
                            badge.setAttribute('data-owner-hidden-badge', '');
                            badge.className = 'absolute top-2 left-2 z-20 inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded-full';
                            badge.innerHTML = '<i class="bi bi-eye-slash"></i> ' + @js(__('Hidden'));
                            card.appendChild(badge);
                        } else if (!this.hidden && badge) {
                            badge.remove();
                        }
                    }
                    window.showToast && window.showToast('success', d.message || '');
                } catch (e) {
                    window.showToast && window.showToast('error', e.message);
                }
            },

            async remove() {
                this.open = false;
                const ok = await window.confirmAction({
                    title: @js(__('Delete')) + ' ' + cfg.label,
                    message: @js(__('This will permanently remove it. This cannot be undone.')),
                    type: 'danger', confirmText: @js(__('Delete')),
                });
                if (!ok) return;
                try {
                    const d = await send(cfg.deleteUrl, 'DELETE');
                    const card = this.card;
                    if (card) {
                        card.style.transition = 'opacity .2s, transform .2s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.96)';
                        setTimeout(() => card.remove(), 200);
                    }
                    window.showToast && window.showToast('success', d.message || @js(__('Deleted')));
                } catch (e) {
                    window.showToast && window.showToast('error', e.message);
                }
            },
        };
    };
})();
</script>
<style>
    [data-owner-card] { position: relative; }
    .owner-hidden { opacity: .5; }
</style>
