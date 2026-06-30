@props(['club'])

@php $cur = $club->currency ?: 'BHD'; @endphp

{{--
  Equipment manager for a single activity. Equipment = a SHOP PRODUCT linked to
  this activity + a "required" flag. The product (name/price/image/stock) lives
  in the club shop; here the admin only picks which products are gear and whether
  they're required. Opened via:
  dispatchEvent(new CustomEvent('open-equipment-manager', { detail: { id, name } })).
--}}
<div
    x-data="activityEquipmentManager('{{ $club->slug }}', '{{ $cur }}')"
    x-show="open"
    x-cloak
    @open-equipment-manager.window="openFor($event.detail)"
    @keydown.escape.window="open = false"
    class="fixed inset-0 z-[70] flex items-center justify-center p-4"
    style="display:none;"
>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        class="relative bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-xl max-h-[90vh] flex flex-col"
    >
        {{-- Header --}}
        <div class="flex items-start justify-between p-5 border-b border-gray-100 flex-shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Equipment</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Shop products required for <span class="font-medium text-foreground" x-text="activityName"></span>
                </p>
            </div>
            <button type="button" @click="open = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-muted hover:text-gray-700 transition-colors">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5">
            <div x-show="loading" class="text-center py-8 text-muted-foreground">
                <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
            </div>

            {{-- No products in the shop at all --}}
            <div x-show="!loading && products.length === 0" class="text-center py-8">
                <i class="bi bi-shop text-muted-foreground text-4xl"></i>
                <p class="text-sm text-muted-foreground mt-2">No shop products yet. Add products in the club Shop first, then link them here as gear.</p>
                <a href="/admin/club/{{ $club->slug }}/shop" class="inline-block mt-3 text-primary text-sm font-medium hover:underline">
                    <i class="bi bi-box-arrow-up-right mr-1"></i>Open Shop
                </a>
            </div>

            {{-- ── Linked gear (chips/rows) ─────────────────────────── --}}
            <div x-show="!loading && items.length > 0">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Linked gear</p>
                <div class="space-y-2">
                    <template x-for="item in items" :key="item.id">
                        <div class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 bg-card">
                            <div class="w-10 h-10 rounded-lg bg-accent flex items-center justify-center flex-shrink-0 overflow-hidden">
                                <template x-if="item.image"><img :src="item.image" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="!item.image"><i class="bi bi-box-seam text-primary"></i></template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-foreground truncate" x-text="item.name || '(product removed)'"></span>
                                    <span x-show="!item.is_active" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground flex-shrink-0">Hidden</span>
                                </div>
                                <span class="text-xs text-muted-foreground" x-text="cur + ' ' + item.price.toFixed(2)"></span>
                            </div>
                            <button type="button" @click="toggleRequired(item)"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors flex-shrink-0 flex items-center gap-1"
                                    :class="item.is_required ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-gray-400'">
                                <i class="bi" :class="item.is_required ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                <span x-text="item.is_required ? 'Required' : 'Optional'"></span>
                            </button>
                            <button type="button" @click="remove(item)" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors flex-shrink-0">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ── Add from shop: selectable product cards ───────────── --}}
            <div x-show="!loading && availableProducts.length > 0">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Add from shop</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <template x-for="p in availableProducts" :key="p.id">
                        <button type="button" @click="selectedProductId = (selectedProductId === p.id ? null : p.id)"
                                class="relative text-start rounded-xl border-2 overflow-hidden transition-all hover:border-primary/50"
                                :class="selectedProductId === p.id ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100'">
                            {{-- selected check badge --}}
                            <span x-show="selectedProductId === p.id"
                                  class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center shadow-sm z-10">
                                <i class="bi bi-check-lg text-xs"></i>
                            </span>
                            {{-- image / placeholder --}}
                            <div class="h-20 bg-accent/50 flex items-center justify-center overflow-hidden">
                                <template x-if="p.image"><img :src="p.image" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="!p.image"><i class="bi bi-box-seam text-primary text-2xl"></i></template>
                            </div>
                            <div class="p-2">
                                <p class="text-xs font-medium text-foreground truncate" x-text="p.name"></p>
                                <p class="text-[11px] text-primary font-semibold mt-0.5" x-text="cur + ' ' + p.price.toFixed(2)"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <div x-show="!loading && products.length > 0 && availableProducts.length === 0 && items.length > 0" class="text-center py-2">
                <p class="text-xs text-muted-foreground">All shop products are linked to this activity.</p>
            </div>
        </div>

        {{-- Footer: required toggle + add the selected card --}}
        <div class="p-4 border-t border-gray-100 flex-shrink-0 bg-muted/20 rounded-b-xl flex items-center justify-between gap-3"
             x-show="availableProducts.length > 0">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="newRequired" class="rounded border-gray-300 text-primary focus:ring-purple-500">
                <span class="text-xs text-gray-700">Required at registration</span>
            </label>
            <button type="button" @click="add()" :disabled="saving || !selectedProductId"
                    class="bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-40 flex items-center gap-1.5">
                <i class="bi bi-plus-lg"></i>
                <span x-text="selectedProductId ? 'Add selected' : 'Select a product'"></span>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function activityEquipmentManager(clubSlug, cur) {
    return {
        open: false,
        loading: false,
        saving: false,
        activityId: null,
        activityName: '',
        cur: cur,
        items: [],            // linked equipment
        products: [],         // all shop products
        selectedProductId: null,
        newRequired: true,

        base() { return `/admin/club/${clubSlug}/activities/${this.activityId}/equipment`; },
        csrf() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); },

        // Products not yet linked to this activity.
        get availableProducts() {
            const linked = this.items.map(i => i.product_id);
            return this.products.filter(p => !linked.includes(p.id));
        },

        openFor(detail) {
            this.activityId = detail.id;
            this.activityName = detail.name || '';
            this.selectedProductId = null;
            this.newRequired = true;
            this.open = true;
            this.load();
        },

        load() {
            this.loading = true;
            fetch(this.base(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => { if (d.success) { this.items = d.equipment; this.products = d.products; } })
                .finally(() => this.loading = false);
        },

        add() {
            if (!this.selectedProductId) return;
            this.saving = true;
            fetch(this.base(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                body: JSON.stringify({
                    club_product_id: this.selectedProductId,
                    is_required: this.newRequired ? 1 : 0,
                    is_active: 1,
                }),
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { window.showToast('error', d.message || 'Something went wrong.'); return; }
                const i = this.items.findIndex(x => x.id === d.equipment.id);
                if (i !== -1) this.items.splice(i, 1, d.equipment); else this.items.push(d.equipment);
                this.selectedProductId = null;
                this.newRequired = true;
                window.showToast('success', d.message);
            })
            .catch(() => window.showToast('error', 'Something went wrong.'))
            .finally(() => this.saving = false);
        },

        toggleRequired(item) {
            const next = !item.is_required;
            fetch(`${this.base()}/${item.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                body: JSON.stringify({ is_required: next ? 1 : 0, is_active: item.is_active ? 1 : 0 }),
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { window.showToast('error', d.message || 'Something went wrong.'); return; }
                const i = this.items.findIndex(x => x.id === item.id);
                if (i !== -1) this.items.splice(i, 1, d.equipment);
            });
        },

        remove(item) {
            window.confirmAction({ title: 'Remove Equipment', message: `Unlink "${item.name}" from this activity?`, confirmText: 'Remove', type: 'danger' })
                .then(ok => {
                    if (!ok) return;
                    fetch(`${this.base()}/${item.id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { window.showToast('error', d.message || 'Something went wrong.'); return; }
                        this.items = this.items.filter(x => x.id !== item.id);
                        window.showToast('success', d.message);
                    });
                });
        },
    };
}
</script>
@endpush
@endonce
