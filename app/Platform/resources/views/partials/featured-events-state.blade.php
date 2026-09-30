{{--
    The Featured-events review page's Alpine state — ONE copy, echoed into the
    x-data attribute of both the desktop and the mobile view, so the two
    layouts can never behave differently.

    Written as an inline object on purpose (not Alpine.data): the desktop admin
    shell swaps pages in place and runs each script once, and an inline x-data
    needs no registration to be ready when the swapped markup initialises.
    ⚠️ It sits inside a double-quoted HTML attribute: single quotes only.

    Every server value reaches the DOM through x-text / :href, never innerHTML.
--}}
{
    fee: @js($fee ? (string) $fee['amount'] : ''),
    currency: @js($currency),
    feeLabel: @js($fee['display'] ?? null),
    savingFee: false,
    requests: @js($requests),
    tab: 'pending',
    busy: null,
    sheet: { open: false, mode: 'reject', req: null, reason: '' },

    get shown() {
        if (this.tab === 'pending') return this.requests.filter(r => r.status === 'pending');
        if (this.tab === 'approved') return this.requests.filter(r => r.status === 'approved');
        return this.requests.filter(r => r.status === 'rejected' || r.status === 'revoked');
    },
    count(t) {
        if (t === 'pending') return this.requests.filter(r => r.status === 'pending').length;
        if (t === 'approved') return this.requests.filter(r => r.status === 'approved').length;
        return this.requests.filter(r => r.status === 'rejected' || r.status === 'revoked').length;
    },
    when(iso) {
        if (! iso) return '';
        try { return new Date(iso).toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) { return ''; }
    },
    headers() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        };
    },
    async saveFee() {
        if (this.savingFee) return;
        this.savingFee = true;
        try {
            const res = await fetch(@js(route('admin.platform.featured-events.fee')), {
                method: 'PUT', credentials: 'same-origin', headers: this.headers(),
                body: JSON.stringify({ amount: this.fee === '' ? null : this.fee, currency: this.currency }),
            });
            const d = await res.json().catch(() => ({}));
            if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
            this.feeLabel = d.fee ? d.fee.display : null;
            this.fee = d.fee ? String(d.fee.amount) : '';
            this.currency = d.currency;
            window.showToast('success', d.message);
        } catch (e) { window.showToast('error', e.message); }
        finally { this.savingFee = false; }
    },
    proof(r) {
        if (! r.proof_url) return;
        window.dispatchEvent(new CustomEvent('open-media-lightbox', { detail: { src: r.proof_url, label: r.title, kind: r.proof_is_pdf ? 'pdf' : 'image' } }));
    },
    ask(r, mode) { this.sheet = { open: true, mode, req: r, reason: '' }; },
    async decide(r, decision, reason) {
        if (this.busy) return;
        this.busy = r.uuid;
        try {
            const url = @js(route('admin.platform.featured-events.decide', ['featureRequest' => '__R__', 'decision' => '__D__'])).replace('__R__', r.uuid).replace('__D__', decision);
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: this.headers(), body: JSON.stringify({ reason: reason || null }) });
            const d = await res.json().catch(() => ({}));
            if (d.request) this.requests = this.requests.map(x => x.uuid === d.request.uuid ? d.request : x);
            if (! res.ok || ! d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : @js(__('personal.event_show_action_failed'))));
            this.sheet.open = false;
            window.showToast('success', d.message);
        } catch (e) { window.showToast('error', e.message); }
        finally { this.busy = null; }
    },
    async refresh() {
        try {
            const res = await fetch(@js(route('admin.platform.featured-events.list')), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await res.json().catch(() => ({}));
            if (res.ok && d.requests) this.requests = d.requests;
        } catch (e) { /* keep what is on screen */ }
    },
    init() {
        /* A new request reaches every super-admin as a notification (MQTT):
           re-read the list then, so it appears here without a reload. */
        if (window.__featuredEventsRt) window.removeEventListener('realtime:notification', window.__featuredEventsRt);
        window.__featuredEventsRt = () => { if (document.body.contains(this.$el)) this.refresh(); };
        window.addEventListener('realtime:notification', window.__featuredEventsRt);
    },
}
