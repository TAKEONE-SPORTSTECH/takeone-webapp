{{-- Super-admin: edit a business (chain) — name, description, logo, status, owner transfer. AJAX, no reload. --}}
<div x-data="businessEditModal()" x-cloak>
    <div x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="close()"></div>

        {{-- Dialog --}}
        <div class="relative bg-card rounded-2xl shadow-2xl ring-1 ring-black/5 w-full max-w-xl max-h-[calc(100vh-3rem)] flex flex-col overflow-hidden"
             x-show="open" @keydown.escape.window="close()"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">

            {{-- ── Header: editable logo · live name · live status ── --}}
            <header class="shrink-0 flex items-start gap-4 px-6 py-5 border-b border-border">
                {{-- Editable logo --}}
                <label class="group relative w-14 h-14 shrink-0 rounded-xl overflow-hidden cursor-pointer bg-white ring-1 ring-border shadow-sm flex items-center justify-center">
                    <template x-if="logoPreview">
                        <img :src="logoPreview" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!logoPreview">
                        <i class="bi bi-buildings text-primary text-xl"></i>
                    </template>
                    <span class="absolute inset-0 bg-gray-900/55 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white gap-0.5">
                        <i class="bi bi-camera-fill text-sm leading-none"></i>
                        <span class="text-[9px] font-medium leading-none">Edit</span>
                    </span>
                    <input type="file" accept="image/*" class="hidden" @change="onLogo($event)">
                </label>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Edit business</p>
                    <h2 class="text-lg font-bold text-foreground truncate leading-snug" x-text="form.name || 'Untitled business'"></h2>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium capitalize"
                              :class="{
                                'bg-green-100 text-green-700': form.status === 'approved',
                                'bg-red-100 text-red-700': form.status === 'rejected',
                                'bg-amber-100 text-amber-700': form.status === 'pending'
                              }">
                            <span class="w-1.5 h-1.5 rounded-full"
                                  :class="{
                                    'bg-green-500': form.status === 'approved',
                                    'bg-red-500': form.status === 'rejected',
                                    'bg-amber-500': form.status === 'pending'
                                  }"></span>
                            <span x-text="form.status"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                            <i class="bi bi-diagram-3"></i><span x-text="clubsCount"></span> club<span x-show="clubsCount !== 1">s</span>
                        </span>
                    </div>
                </div>

                <button type="button" @click="close()"
                        class="shrink-0 -mt-1 -mr-1 w-8 h-8 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <form @submit.prevent="submit()" class="flex flex-col min-h-0 flex-1">
                {{-- ── Scrollable body ── --}}
                <div class="flex-1 overflow-y-auto px-6 py-5 space-y-7">

                    {{-- Details --}}
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            <i class="bi bi-card-text text-primary"></i> Details
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1.5">Business name <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.name" maxlength="120" required
                                   class="w-full px-3.5 py-2.5 bg-white border border-border rounded-xl text-sm focus:ring-2 focus:ring-primary/40 focus:border-primary transition-shadow"
                                   :class="errors.name ? 'border-red-400 ring-2 ring-red-100' : ''">
                            <p class="text-xs text-red-600 mt-1" x-show="errors.name" x-text="errors.name"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1.5">Description</label>
                            <textarea x-model="form.description" rows="3" maxlength="1000" placeholder="A short description of this chain…"
                                      class="w-full px-3.5 py-2.5 bg-white border border-border rounded-xl text-sm focus:ring-2 focus:ring-primary/40 focus:border-primary transition-shadow resize-none"></textarea>
                            <p class="text-xs text-red-600 mt-1" x-show="errors.description" x-text="errors.description"></p>
                        </div>
                    </section>

                    {{-- Status --}}
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            <i class="bi bi-toggle-on text-primary"></i> Status
                        </div>

                        {{-- Segmented status control --}}
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="opt in [
                                {v:'pending',  label:'Pending',  icon:'bi-hourglass-split', on:'bg-amber-50 border-amber-300 text-amber-700 ring-2 ring-amber-100'},
                                {v:'approved', label:'Approved', icon:'bi-check-circle',     on:'bg-green-50 border-green-300 text-green-700 ring-2 ring-green-100'},
                                {v:'rejected', label:'Rejected', icon:'bi-x-circle',         on:'bg-red-50 border-red-300 text-red-700 ring-2 ring-red-100'}
                            ]" :key="opt.v">
                                <button type="button" @click="form.status = opt.v"
                                        class="flex flex-col items-center justify-center gap-1 py-3 rounded-xl border text-xs font-medium transition-all"
                                        :class="form.status === opt.v ? opt.on : 'bg-white border-border text-muted-foreground hover:bg-muted'">
                                    <i class="bi text-base" :class="opt.icon"></i>
                                    <span x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>

                        <p class="flex items-start gap-1.5 text-xs text-muted-foreground" x-show="form.status === 'approved'" x-cloak>
                            <i class="bi bi-info-circle mt-0.5"></i>
                            <span>Approving links the owner's clubs to this chain and enables the view switcher.</span>
                        </p>

                        <div x-show="form.status === 'rejected'" x-cloak>
                            <label class="block text-sm font-medium text-foreground mb-1.5">Rejection reason</label>
                            <textarea x-model="form.rejection_reason" rows="2" maxlength="1000" placeholder="Optional — shown to the owner"
                                      class="w-full px-3.5 py-2.5 bg-white border border-border rounded-xl text-sm focus:ring-2 focus:ring-primary/40 focus:border-primary transition-shadow resize-none"></textarea>
                        </div>
                    </section>

                    {{-- Clubs --}}
                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                <i class="bi bi-diagram-3 text-primary"></i> Clubs
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full bg-accent text-primary text-[10px]" x-text="clubs.length"></span>
                            </div>
                            <button type="button" @click="toggleAddClub()"
                                    class="inline-flex items-center gap-1 border border-primary text-primary bg-transparent px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary hover:text-white transition-colors">
                                <i class="bi" :class="addingClub ? 'bi-x-lg' : 'bi-plus-lg'"></i><span x-text="addingClub ? 'Close' : 'Add club'"></span>
                            </button>
                        </div>

                        {{-- Add-a-club picker --}}
                        <div x-show="addingClub" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             class="rounded-xl border border-border bg-muted/40 p-3 space-y-3">
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" x-model="clubSearch" placeholder="Search clubs by name or owner…"
                                       class="w-full pl-9 pr-3 py-2 bg-white border border-border rounded-lg text-sm focus:ring-2 focus:ring-primary/40 focus:border-primary transition-shadow">
                            </div>
                            <div class="max-h-56 overflow-y-auto overflow-x-hidden space-y-1.5 pr-1">
                                <p x-show="!filteredAvailable().length" class="text-xs text-muted-foreground italic py-2 text-center">No clubs available to add.</p>
                                <template x-for="c in filteredAvailable()" :key="c.id">
                                    <button type="button" @click="attachClub(c.id)" :disabled="clubBusy"
                                            class="w-full flex items-center gap-3 text-left rounded-lg border border-border bg-white px-3 py-2 hover:border-primary/40 hover:bg-accent/30 transition-colors disabled:opacity-60">
                                        <div class="w-8 h-8 shrink-0 rounded-lg bg-accent overflow-hidden flex items-center justify-center">
                                            <template x-if="c.logo_url"><img :src="c.logo_url" alt="" class="w-8 h-8 object-cover"></template>
                                            <template x-if="!c.logo_url"><i class="bi bi-buildings text-primary text-sm"></i></template>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-foreground truncate" x-text="c.name"></p>
                                            <p class="text-[11px] text-muted-foreground truncate" x-text="c.owner || '—'"></p>
                                        </div>
                                        <template x-if="c.business">
                                            <span class="shrink-0 text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700" x-text="'in ' + c.business"></span>
                                        </template>
                                        <i class="bi bi-plus-circle text-primary text-lg shrink-0"></i>
                                    </button>
                                </template>
                            </div>
                            <p class="text-[11px] text-muted-foreground flex items-start gap-1">
                                <i class="bi bi-info-circle mt-0.5"></i>
                                <span>Adding a club already in another chain will move it here.</span>
                            </p>
                        </div>

                        {{-- Current clubs --}}
                        <p x-show="!clubs.length" class="text-xs text-muted-foreground italic">No clubs in this chain yet.</p>
                        <ul x-show="clubs.length" class="space-y-1.5">
                            <template x-for="c in clubs" :key="c.id">
                                <li class="flex items-center gap-3 rounded-xl border border-border bg-white px-3 py-2">
                                    <div class="w-8 h-8 shrink-0 rounded-lg bg-accent overflow-hidden flex items-center justify-center">
                                        <template x-if="c.logo_url"><img :src="c.logo_url" alt="" class="w-8 h-8 object-cover"></template>
                                        <template x-if="!c.logo_url"><i class="bi bi-buildings text-primary text-sm"></i></template>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-foreground truncate" x-text="c.name"></p>
                                        <p class="text-[11px] text-muted-foreground truncate" x-text="c.owner || '—'"></p>
                                    </div>
                                    <button type="button" @click="detachClub(c.id, c.name)" :disabled="clubBusy"
                                            class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-60"
                                            title="Remove from chain">
                                        <i class="bi bi-x-lg text-sm"></i>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </section>

                    {{-- Ownership --}}
                    <section class="space-y-4">
                        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            <i class="bi bi-person-badge text-primary"></i> Ownership
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-xl border border-border bg-muted/40 px-3.5 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 shrink-0 rounded-full bg-primary text-white flex items-center justify-center text-sm font-semibold uppercase"
                                     x-text="(owner.name || '?').charAt(0)"></div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground truncate" x-text="owner.name || 'Unknown owner'"></p>
                                    <p class="text-xs text-muted-foreground truncate" x-text="owner.email || '—'"></p>
                                </div>
                            </div>
                            <button type="button" @click="pickOwner()"
                                    class="shrink-0 inline-flex items-center gap-1 border border-primary text-primary bg-transparent px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary hover:text-white transition-colors">
                                <i class="bi bi-arrow-left-right"></i>Change
                            </button>
                        </div>

                        {{-- Pending transfer confirmation --}}
                        <div x-show="pendingOwner" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                            <div class="flex items-center gap-2 text-sm text-amber-900">
                                <i class="bi bi-arrow-right-circle-fill text-amber-500"></i>
                                <span>Transfer to <span class="font-semibold" x-text="pendingOwner?.full_name"></span></span>
                            </div>
                            <p class="text-xs text-amber-700 -mt-1.5 pl-6 truncate" x-text="pendingOwner?.email"></p>

                            <label class="flex items-start gap-2.5 text-sm text-gray-700 cursor-pointer rounded-lg bg-white/60 border border-amber-200 px-3 py-2.5">
                                <input type="checkbox" x-model="reassignClubs" class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary">
                                <span>Also move all <span class="font-medium" x-text="clubsCount"></span> club<span x-show="clubsCount !== 1">s</span> in this chain to the new owner.</span>
                            </label>
                            <textarea x-model="transferNote" rows="2" maxlength="1000" placeholder="Reason / note (optional)"
                                      class="w-full px-3.5 py-2.5 bg-white border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/40 focus:border-primary transition-shadow resize-none"></textarea>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="confirmTransfer()" :disabled="transferring"
                                        class="inline-flex items-center bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-60">
                                    <span x-show="!transferring"><i class="bi bi-check-lg mr-1"></i>Transfer ownership</span>
                                    <span x-show="transferring" x-cloak><i class="bi bi-arrow-repeat mr-1 animate-spin inline-block"></i>Transferring…</span>
                                </button>
                                <button type="button" @click="pendingOwner = null" class="text-sm text-muted-foreground px-3 py-2 hover:text-foreground transition-colors">Cancel</button>
                            </div>
                        </div>

                        {{-- History timeline --}}
                        <div class="pt-1">
                            <p class="text-xs font-medium text-muted-foreground mb-3">Ownership history</p>
                            <p x-show="!history.length" class="text-xs text-muted-foreground italic">No ownership changes recorded yet.</p>
                            <ol x-show="history.length" class="space-y-1">
                                <template x-for="(h, i) in history" :key="i">
                                    <li class="flex gap-3">
                                        {{-- Marker rail --}}
                                        <div class="flex flex-col items-center shrink-0">
                                            <span class="mt-1.5 w-2.5 h-2.5 rounded-full bg-primary ring-4 ring-accent/60"></span>
                                            <span class="flex-1 w-px bg-border my-1" x-show="i < history.length - 1"></span>
                                        </div>
                                        {{-- Entry --}}
                                        <div class="min-w-0 flex-1 pb-3">
                                            <div class="text-sm text-foreground">
                                                <span class="font-medium" x-text="(h.from || 'No owner')"></span>
                                                <i class="bi bi-arrow-right text-muted-foreground mx-1 text-xs"></i>
                                                <span class="font-medium" x-text="h.to"></span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-0.5 text-[11px] text-muted-foreground">
                                                <span x-text="h.at"></span>
                                                <template x-if="h.changed_by">
                                                    <span x-text="'· by ' + h.changed_by"></span>
                                                </template>
                                                <template x-if="h.clubs_reassigned">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-accent text-primary font-medium"
                                                          x-text="h.clubs_count + ' club' + (h.clubs_count === 1 ? '' : 's') + ' moved'"></span>
                                                </template>
                                            </div>
                                            <p x-show="h.note" class="mt-1 text-xs text-gray-500 italic break-words" x-text="'“' + h.note + '”'"></p>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </section>
                </div>

                {{-- ── Sticky footer ── --}}
                <footer class="shrink-0 flex items-center justify-end gap-2 px-6 py-4 border-t border-border bg-muted/30">
                    <button type="button" @click="close()" class="px-4 py-2 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors">Cancel</button>
                    <button type="submit" :disabled="saving"
                            class="inline-flex items-center bg-primary text-white px-5 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-60 shadow-sm">
                        <span x-show="!saving"><i class="bi bi-check-lg mr-1.5"></i>Save changes</span>
                        <span x-show="saving" x-cloak><i class="bi bi-arrow-repeat mr-1.5 animate-spin inline-block"></i>Saving…</span>
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function businessEditModal() {
    return {
        open: false,
        saving: false,
        id: null,
        logoPreview: null,
        logoFile: null,
        form: { name: '', description: '', status: 'pending', rejection_reason: '' },
        errors: {},
        // Ownership
        owner: { id: null, name: '', email: '' },
        clubsCount: 0,
        history: [],
        // Clubs
        clubs: [],
        availableClubs: [],
        clubSearch: '',
        addingClub: false,
        clubBusy: false,
        picking: false,
        pendingOwner: null,
        reassignClubs: false,
        transferNote: '',
        transferring: false,

        init() {
            window.addEventListener('open-business-edit', (e) => this.openWith(e.detail));
            window.addEventListener('user-selected', (e) => {
                if (!this.picking) return;
                this.picking = false;
                this.pendingOwner = e.detail;
                this.reassignClubs = false;
                this.transferNote = '';
            });
        },

        openWith(b) {
            if (!b) return;
            this.id = b.id;
            this.form = {
                name: b.name || '',
                description: b.description || '',
                status: b.status || 'pending',
                rejection_reason: b.rejection_reason || '',
            };
            this.logoPreview = b.logo_url || null;
            this.logoFile = null;
            this.errors = {};
            this.owner = { id: b.owner_id, name: b.owner_name || '', email: b.owner_email || '' };
            this.clubsCount = b.clubs_count || 0;
            this.pendingOwner = null;
            this.picking = false;
            this.history = [];
            this.clubs = [];
            this.availableClubs = [];
            this.clubSearch = '';
            this.addingClub = false;
            this.open = true;
            this.loadHistory();
            this.loadClubs();
        },

        close() { this.open = false; this.picking = false; this.pendingOwner = null; this.addingClub = false; },

        async loadClubs() {
            try {
                const res = await fetch(`/admin/businesses/${this.id}/clubs`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    this.clubs = data.clubs;
                    this.availableClubs = data.available;
                }
            } catch (e) {}
        },

        toggleAddClub() { this.addingClub = !this.addingClub; this.clubSearch = ''; },

        filteredAvailable() {
            const term = (this.clubSearch || '').toLowerCase().trim();
            if (!term) return this.availableClubs;
            return this.availableClubs.filter(c =>
                (c.name || '').toLowerCase().includes(term) ||
                (c.owner || '').toLowerCase().includes(term)
            );
        },

        async attachClub(clubId) {
            if (this.clubBusy) return;
            this.clubBusy = true;
            try {
                const data = await this.clubAction('attach', clubId);
                window.showToast('success', data.message);
                this.clubs = data.clubs;
                this.clubsCount = data.business.clubs_count;
                this.availableClubs = this.availableClubs.filter(c => c.id !== clubId);
                window.dispatchEvent(new CustomEvent('business-saved', { detail: data.business }));
            } catch (err) {
                window.showToast('error', err.message);
            } finally {
                this.clubBusy = false;
            }
        },

        async detachClub(clubId, name) {
            if (this.clubBusy) return;
            const ok = await window.confirmAction({
                title: 'Remove club',
                message: `Remove “${name}” from this chain? The club itself is not deleted.`,
                type: 'warning',
                confirmText: 'Remove',
            });
            if (!ok) return;
            this.clubBusy = true;
            try {
                const data = await this.clubAction('detach', clubId);
                window.showToast('success', data.message);
                this.clubs = data.clubs;
                this.clubsCount = data.business.clubs_count;
                await this.loadClubs(); // refresh "available" so the removed club reappears
                window.dispatchEvent(new CustomEvent('business-saved', { detail: data.business }));
            } catch (err) {
                window.showToast('error', err.message);
            } finally {
                this.clubBusy = false;
            }
        },

        async clubAction(action, clubId) {
            const res = await fetch(`/admin/businesses/${this.id}/clubs/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ club_id: clubId }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Something went wrong.');
            return data;
        },

        onLogo(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.logoFile = file;
            this.logoPreview = URL.createObjectURL(file);
        },

        async loadHistory() {
            try {
                const res = await fetch(`/admin/businesses/${this.id}/history`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) this.history = data.history;
            } catch (e) {}
        },

        pickOwner() {
            this.picking = true;
            if (typeof window.openUserPickerModal === 'function') {
                window.openUserPickerModal();
            } else {
                this.picking = false;
                window.showToast('error', 'User picker is unavailable on this page.');
            }
        },

        async confirmTransfer() {
            if (this.transferring || !this.pendingOwner) return;
            this.transferring = true;
            try {
                const res = await fetch(`/admin/businesses/${this.id}/transfer-owner`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        to_user_id: this.pendingOwner.id,
                        reassign_clubs: this.reassignClubs,
                        note: this.transferNote || null,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not transfer ownership.');

                window.showToast('success', data.message);
                this.owner = { id: data.business.owner_id, name: data.business.owner_name, email: data.business.owner_email };
                this.history = data.history;
                this.pendingOwner = null;
                this.transferring = false;
                window.dispatchEvent(new CustomEvent('business-saved', { detail: data.business }));
            } catch (err) {
                window.showToast('error', err.message);
                this.transferring = false;
            }
        },

        async submit() {
            if (this.saving) return;
            this.saving = true;
            this.errors = {};

            const fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('name', this.form.name);
            fd.append('description', this.form.description || '');
            fd.append('status', this.form.status);
            fd.append('rejection_reason', this.form.rejection_reason || '');
            if (this.logoFile) fd.append('logo', this.logoFile);

            try {
                const res = await fetch(`/admin/businesses/${this.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json();
                if (res.status === 422 && data.errors) {
                    Object.keys(data.errors).forEach(k => this.errors[k] = data.errors[k][0]);
                    this.saving = false;
                    return;
                }
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not save.');

                window.showToast('success', data.message);
                window.dispatchEvent(new CustomEvent('business-saved', { detail: data.business }));
                this.saving = false;
                this.open = false;
            } catch (err) {
                window.showToast('error', err.message);
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endonce
