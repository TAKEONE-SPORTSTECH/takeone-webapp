{{-- Shared product-detail Alpine data — powers both the mobile and desktop
     market-show pages identically. Expects `$p` in scope. Self-contained
     (own localStorage cart sync), independent of marketHub() on the index page. --}}
x-data="{
    qty: 1,
    color: @js($p['colors'][0] ?? $p['color']),
    liked: false,
    placing: false,
    paySheet: false,
    proof: null,
    pickerShake: false,
    // Draw the eye to the variant picker when an action is blocked on an
    // unmade choice, instead of relying on the toast alone (which may be
    // disabled site-wide) or a button that otherwise looks fully enabled.
    flagPicker() {
        this.pickerShake = false;
        requestAnimationFrame(() => {
            this.pickerShake = true;
            setTimeout(() => { this.pickerShake = false; }, 650);
        });
    },
    pid: {{ (int) $p['id'] }},
    pname: @js($p['name']),
    basePrice: {{ $p['price'] }},
    productDesc: @js($p['desc'] ?? ''),
    hasVariants: {{ !empty($p['hasVariants']) ? 'true' : 'false' }},
    variants: @js(collect($p['variants'] ?? [])->where('is_active', true)->values()->all()),
    attributes: @js($p['attributes'] ?? []),
    selected: {},   // { AttrName: chosenValue }
    placeRoute: @js(route('me.orders.store')),
    ordersRoute: @js(route('me.orders')),
    csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
    cart: [],
    justAdded: false,
    init() { this.syncCart(); },
    syncCart() { try { this.cart = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) { this.cart = []; } },
    // Is the currently-selected variant already in the cart?
    get inCart() {
        const vId = this.sel ? this.sel.id : null;
        return (this.cart || []).some(i => i.id === this.pid && (i.variantId || null) === (vId || null));
    },
    get cartQty() {
        const vId = this.sel ? this.sel.id : null;
        const l = (this.cart || []).find(i => i.id === this.pid && (i.variantId || null) === (vId || null));
        return l ? l.qty : 0;
    },
    // The product's variant dimensions (name + values). Only dimensions with
    // ≥1 value are offered as selectors.
    dims() { return (this.attributes || []).filter(a => a && a.values && a.values.length); },
    // A variant's value for an attribute — from its options map, falling back
    // to the legacy brand/color/size columns for un-migrated rows.
    vopt(v, name) {
        if (v.options && v.options[name] != null) return v.options[name];
        const legacy = v[String(name).toLowerCase()];
        return legacy != null ? legacy : null;
    },
    // Effective choice for a dimension: the picked value, or the sole value of a
    // single-option dimension (so the buyer need only pick the dimensions that vary).
    effVal(name) {
        if (this.selected[name] != null) return this.selected[name];
        const a = this.dims().find(d => d.name === name);
        return (a && a.values.length === 1) ? a.values[0] : null;
    },
    // Price range across variants — shown until a full combination is picked.
    priceMin() { return this.variants.length ? Math.min(...this.variants.map(v => v.price)) : this.basePrice; },
    priceMax() { return this.variants.length ? Math.max(...this.variants.map(v => v.price)) : this.basePrice; },
    priceVaries() { return this.hasVariants && this.priceMin() !== this.priceMax(); },
    // The variant matching every dimension the product uses.
    get sel() {
        if (!this.hasVariants) return null;
        const dims = this.dims();
        if (!dims.length) return null;
        return this.variants.find(v => dims.every(d => {
            const ev = this.effVal(d.name);
            return ev != null && this.vopt(v, d.name) === ev;
        })) || null;
    },
    get unitPrice() { return this.sel ? this.sel.price : this.basePrice; },
    pickOpt(name, val) { this.selected = { ...this.selected, [name]: val }; },
    optSelected(name, val) { return this.selected[name] === val; },
    // Does variant v match every OTHER chosen dimension (used for price/availability)?
    _matchesExcept(v, exceptName) {
        return this.dims().every(d => {
            if (d.name === exceptName) return true;
            const ev = this.effVal(d.name);
            return ev == null || this.vopt(v, d.name) === ev;
        });
    },
    // Cheapest price reachable for a value given the other choices (per-tile price).
    optPrice(name, val) {
        const m = this.variants.filter(v => this.vopt(v, name) === val && this._matchesExcept(v, name));
        return m.length ? Math.min(...m.map(v => v.price)) : null;
    },
    // A value is available if some in-stock variant has it with the other chosen dims.
    optAvailable(name, val) {
        return this.variants.some(v => v.in_stock && this.vopt(v, name) === val && this._matchesExcept(v, name));
    },
    // Swatch colour for a value, if any variant carries a hex for it (Colour dims).
    dimHex(name, val) {
        const v = this.variants.find(x => this.vopt(x, name) === val && x.color_hex);
        return v ? v.color_hex : null;
    },
    // Description shown: the chosen variation's own, else the product's, else the
    // first variation that has one.
    descText() {
        if (this.sel && (this.sel.description || '').trim()) return this.sel.description;
        if ((this.productDesc || '').trim()) return this.productDesc;
        const withDesc = this.variants.find(v => (v.description || '').trim());
        return withDesc ? withDesc.description : '';
    },
    inc() { this.qty++; },
    dec() { if (this.qty > 1) this.qty--; },
    get line() { return (this.unitPrice * this.qty); },
    addToCart() {
        if (this.hasVariants && !this.sel) { this.flagPicker(); window.showToast('error', @js(__('market.select_variant'))); return; }
        if (this.sel && !this.sel.in_stock) { this.flagPicker(); window.showToast('error', @js(__('market.out_of_stock'))); return; }
        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('takeone_cart') || '[]'); } catch (e) {}
        const vId = this.sel ? this.sel.id : null;
        const ex = cart.find(i => i.id === this.pid && (i.variantId || null) === (vId || null) && (i.color || null) === (this.color || null));
        if (ex) ex.qty += this.qty;
        else cart.push({ id: this.pid, name: this.pname, price: this.unitPrice, color: this.sel ? this.sel.color_hex : this.color, icon: @js($p['icon']), qty: this.qty, variantId: vId, variantLabel: this.sel ? this.sel.label : null });
        try { localStorage.setItem('takeone_cart', JSON.stringify(cart)); } catch (e) {}
        this.syncCart();
        this.justAdded = true; setTimeout(() => { this.justAdded = false; }, 700);
        window.showToast('success', @js(__('market.added_to_cart')).replace(':name', this.pname));
    },
    buyNow() {
        if (this.hasVariants && !this.sel) { this.flagPicker(); window.showToast('error', @js(__('market.select_variant'))); return; }
        if (this.sel && !this.sel.in_stock) { this.flagPicker(); window.showToast('error', @js(__('market.out_of_stock'))); return; }
        this.proof = null; this.paySheet = true;
    },
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
    async placeOrder() {
        if (this.placing) return;
        if (!this.proof) { window.showToast('error', @js(__('market.proof_required'))); return; }
        this.placing = true;
        try {
            const res = await fetch(this.placeRoute, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ items: [{ id: this.pid, qty: this.qty, color: this.sel ? this.sel.color_hex : this.color, variant_id: this.sel ? this.sel.id : null }], proof: this.proof }),
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
            this.paySheet = false;
            window.showToast('success', d.message || @js(__('market.order_placed')));
            setTimeout(() => { window.location.href = d.redirect || this.ordersRoute; }, 700);
        } catch (e) { window.showToast('error', e.message); } finally { this.placing = false; }
    }
 }"
