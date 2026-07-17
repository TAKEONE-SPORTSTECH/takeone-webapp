{{-- Shared marketHub() Alpine component — powers both the mobile and desktop
     market pages identically (cart persisted to localStorage, checkout POST). --}}
<script>
function marketHub(meta) {
    return {
        meta: meta || [],
        q: '', cat: 'all', cartOpen: false, items: [], countdown: '12:00:00', _t: null,
        placing: false, cartBump: false, _actx: null,
        cartStep: 'cart', proof: null,
        routes: { place: @js(route('me.orders.store')), orders: @js(route('me.orders')) },
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        get count() { return this.items.reduce((n, i) => n + i.qty, 0); },
        get total() { return this.items.reduce((s, i) => s + i.price * i.qty, 0); },
        loadCart() { try { this.items = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) { this.items = []; } },
        saveCart() { try { localStorage.setItem('takeone_cart', JSON.stringify(this.items)); } catch (e) {} },
        sameLine(a, b) { return a.id === b.id && (a.variantId || null) === (b.variantId || null); },
        add(p) {
            const ex = this.items.find(i => this.sameLine(i, p));
            if (ex) ex.qty++; else this.items.push({ ...p, qty: 1 });
            this.saveCart();
            // Satisfying feedback: bag bumps, badge pops, a soft "pop" sound.
            this.cartBump = false;
            requestAnimationFrame(() => { this.cartBump = true; setTimeout(() => this.cartBump = false, 600); });
            this.playPop();
            window.showToast('success', @js(__('market.added_to_cart')).replace(':name', p.name));
        },
        playPop() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this._actx = this._actx || new Ctx();
                const ctx = this._actx;
                if (ctx.state === 'suspended') ctx.resume();
                const now = ctx.currentTime;
                const o = ctx.createOscillator(), g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'triangle';
                o.frequency.setValueAtTime(523, now);
                o.frequency.exponentialRampToValueAtTime(1046, now + 0.09);
                g.gain.setValueAtTime(0.0001, now);
                g.gain.exponentialRampToValueAtTime(0.18, now + 0.012);
                g.gain.exponentialRampToValueAtTime(0.0001, now + 0.2);
                o.start(now); o.stop(now + 0.22);
            } catch (e) {}
        },
        inc(it) { it.qty++; this.saveCart(); },
        dec(it) { it.qty--; if (it.qty <= 0) this.items = this.items.filter(i => !this.sameLine(i, it)); this.saveCart(); },
        async pickProof(e) {
            const f = (e.target.files || [])[0]; e.target.value = '';
            if (!f || !f.type.startsWith('image/')) return;
            // Shrink large phone photos before encoding so the upload is fast
            // (a multi-MB receipt photo would otherwise be sent as ~MBs of base64).
            let file = f;
            try {
                if (window.imageCompression && f.size > 400 * 1024) {
                    file = await window.imageCompression(f, { maxSizeMB: 0.6, maxWidthOrHeight: 1600, useWebWorker: true });
                }
            } catch (_) { file = f; }
            const r = new FileReader(); r.onload = () => { this.proof = r.result; }; r.readAsDataURL(file);
        },
        async checkout() {
            if (this.placing || !this.items.length) return;
            if (!this.proof) { window.showToast('error', @js(__('market.proof_required'))); return; }
            this.placing = true;
            try {
                const res = await fetch(this.routes.place, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        items: this.items.map(i => ({ id: i.id, qty: i.qty, color: i.color || null, variant_id: i.variantId || null })),
                        proof: this.proof,
                    }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.items = []; this.saveCart(); this.cartOpen = false; this.cartStep = 'cart'; this.proof = null;
                window.showToast('success', d.message || @js(__('market.order_placed')));
                setTimeout(() => { window.location.href = d.redirect || this.routes.orders; }, 700);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.placing = false;
            }
        },
        hasResults() {
            const q = this.q.toLowerCase();
            return this.meta.some(m => (this.cat === 'all' || m.cat === this.cat) && m.name.includes(q));
        },
        startTimer() {
            let s = 12 * 3600;
            const fmt = () => {
                const h = String(Math.floor(s / 3600)).padStart(2, '0');
                const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
                const sec = String(s % 60).padStart(2, '0');
                this.countdown = `${h}:${m}:${sec}`;
            };
            fmt();
            this._t = setInterval(() => { s = s > 0 ? s - 1 : 12 * 3600; fmt(); }, 1000);
        },
        init() { this.startTimer(); this.loadCart(); }
    };
}
</script>
