{{-- Mobile "Manage member access" — teleported bottom sheet. Opens on
     `open-access-form` (detail = {id, name}). On open it fetches the member's current
     access, then lets you assign a standard role (club-admin allowed) or — only when the
     server says canEdit (super-admin) — a custom permission set. Posts to the SAME
     member.permissions.store endpoint, then patches the member's badges via `access-saved`. --}}
<script>
window.accessFormSheet = function () {
    return {
        open: false,
        loading: false,
        saving: false,
        canEdit: false,
        csrf: '{{ csrf_token() }}',

        userId: null,
        userName: '',
        role: '',
        custom: false,          // custom-mode toggle
        perms: [],
        groups: [],
        total: 0,

        openGroups: {},         // which permission groups are expanded
        roleOptions: @js(($availableRoles ?? collect())->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->values()),
        get roleLabel() { return (this.roleOptions.find(r => r.slug === this.role) || {}).name || ''; },

        /** A recognisable glyph per role so the cards read at a glance. */
        roleIcon(slug) {
            const s = String(slug || '');
            if (s.includes('owner'))                          return 'bi-key';
            if (s.includes('admin'))                          return 'bi-shield-lock';
            if (s.includes('instructor') || s.includes('coach') || s.includes('trainer')) return 'bi-person-badge';
            if (s.includes('moderator'))                      return 'bi-flag';
            if (s.includes('staff'))                          return 'bi-person-workspace';
            return 'bi-person';
        },

        has(slug) { return this.perms.includes(slug); },
        toggle(slug) { const i = this.perms.indexOf(slug); i > -1 ? this.perms.splice(i, 1) : this.perms.push(slug); },

        // ── Permission-group helpers: keep 20+ checkboxes usable on a phone ──
        groupOpen(label) { return !!this.openGroups[label]; },
        toggleGroupOpen(label) { this.openGroups[label] = !this.openGroups[label]; },
        groupCount(g) { return (g.perms || []).filter(p => this.has(p.slug)).length; },
        groupAllOn(g) { return (g.perms || []).length > 0 && this.groupCount(g) === g.perms.length; },
        setGroup(g, on) {
            (g.perms || []).forEach(p => {
                const i = this.perms.indexOf(p.slug);
                if (on && i === -1) this.perms.push(p.slug);
                if (!on && i > -1) this.perms.splice(i, 1);
            });
        },
        selectAll() { this.perms = this.groups.flatMap(g => (g.perms || []).map(p => p.slug)); },
        clearAll()  { this.perms = []; },

        async openForm(detail) {
            this.userId = detail && detail.id ? detail.id : null;
            this.userName = detail && detail.name ? detail.name : '';
            if (!this.userId) return;
            this.role = ''; this.custom = false; this.perms = []; this.groups = []; this.total = 0;
            this.canEdit = false; this.openGroups = {};
            this.open = true;
            this.loading = true;
            try {
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/roles/member') }}/${this.userId}/permissions`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
                this.role = d.role || '';
                this.custom = !!d.custom;
                this.perms = Array.isArray(d.permissions) ? d.permissions.slice() : [];
                this.groups = Array.isArray(d.groups) ? d.groups : [];
                this.total = d.total || 0;
                this.canEdit = !!d.canEdit;
            } catch (e) {
                window.showToast('error', e.message);
                this.open = false;
            } finally {
                this.loading = false;
            }
        },

        async submit() {
            if (this.saving) return;
            const useCustom = this.canEdit && this.custom;
            if (!useCustom && !this.role) { window.showToast('warning', '{{ __('admin.role_assign_role') }}'); return; }
            this.saving = true;
            const body = useCustom
                ? { user_id: this.userId, permissions: this.perms }
                : { user_id: this.userId, role: this.role };
            try {
                const res = await fetch(`{{ route('admin.club.roles.member.permissions.store', $club->slug) }}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) {
                    const msg = d.message || (d.errors ? Object.values(d.errors)[0][0] : null) || 'Error';
                    throw new Error(msg);
                }
                window.dispatchEvent(new CustomEvent('access-saved', {
                    detail: { id: this.userId, custom: useCustom, label: useCustom ? '{{ __('admin.role_custom_access') }}' : this.roleLabel },
                }));
                this.open = false;
                window.showToast('success', d.message || '{{ __('admin.role_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="accessFormSheet()"
     @open-access-form.window="openForm($event.detail)"
     @keydown.escape.window="open = false">
<template x-teleport="body">
<div x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
             style="max-height: 92vh;" @click.stop>

            <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

            <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                <h5 class="text-base font-semibold flex items-center min-w-0">
                    <i class="bi bi-sliders mr-2"></i><span class="truncate" x-text="userName || '{{ __('admin.role_manage_access') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1 flex-shrink-0">&times;</button>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4">

                {{-- Loading --}}
                <div x-show="loading" class="py-10 text-center text-muted-foreground">
                    <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                </div>

                <div x-show="!loading" x-cloak class="space-y-4">
                    {{-- Standard role — inline option cards. A pop-over dropdown gets clipped by
                         this sheet's scroll body and gives tiny tap targets, so the choices are
                         laid out directly: one tap, full-width rows, no overlay. --}}
                    <div x-show="!(canEdit && custom)">
                        <label class="form-label">{{ __('admin.role_assign_role') }}</label>
                        <div class="space-y-2">
                            <template x-for="r in roleOptions" :key="r.slug">
                                <button type="button" @click="role = r.slug"
                                        class="m-press w-full flex items-center gap-3 px-3.5 py-3 rounded-xl border text-start transition-colors"
                                        :class="role === r.slug ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                        :aria-pressed="role === r.slug">
                                    <span class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0 transition-colors"
                                          :class="role === r.slug ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                        <i class="bi" :class="roleIcon(r.slug)"></i>
                                    </span>
                                    <span class="flex-1 min-w-0 text-sm font-semibold text-foreground truncate" x-text="r.name"></span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 transition-colors"
                                          :class="role === r.slug ? 'border-primary bg-primary' : 'border-gray-300'">
                                        <i x-show="role === r.slug" class="bi bi-check text-white text-xs"></i>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Custom access (super-admin only) --}}
                    <template x-if="canEdit">
                        <div>
                            <label class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-gray-200 bg-muted/30 cursor-pointer">
                                <span class="text-sm font-medium text-foreground flex items-center gap-2"><i class="bi bi-stars text-primary"></i>{{ __('admin.role_custom_access') }}</span>
                                <input type="checkbox" x-model="custom" style="width:18px;height:18px;accent-color:hsl(250 65% 65%);">
                            </label>

                            {{-- Groups are collapsed by default with a live count, so 20+ permissions
                                 stay scannable on a phone instead of one endless checkbox column. --}}
                            <div x-show="custom" x-cloak class="mt-3 space-y-2">

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-foreground flex-1">
                                        <span x-text="perms.length"></span> / <span x-text="total"></span>
                                    </span>
                                    <button type="button" @click="selectAll()" class="m-press px-3 py-1.5 rounded-full text-[11px] font-semibold border border-primary text-primary">{{ __('admin.role_select_all') }}</button>
                                    <button type="button" @click="clearAll()" class="m-press px-3 py-1.5 rounded-full text-[11px] font-semibold border border-gray-200 text-muted-foreground">{{ __('admin.role_clear_all') }}</button>
                                </div>

                                <template x-for="g in groups" :key="g.label">
                                    <div class="border border-gray-100 rounded-xl overflow-hidden">
                                        {{-- Group header: tap to expand, or use the pill to flip the whole group --}}
                                        <div class="flex items-center gap-2 px-3 py-2.5 bg-muted/40">
                                            <button type="button" @click="toggleGroupOpen(g.label)" class="flex items-center gap-2 flex-1 min-w-0 text-start">
                                                <i class="bi bi-chevron-right text-xs text-muted-foreground transition-transform flex-shrink-0"
                                                   :class="groupOpen(g.label) ? 'rotate-90' : 'rtl:rotate-180'"></i>
                                                <span class="text-xs font-bold uppercase tracking-wide text-foreground truncate" x-text="g.label"></span>
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0"
                                                      :class="groupCount(g) ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-muted-foreground'"
                                                      x-text="groupCount(g) + '/' + g.perms.length"></span>
                                            </button>
                                            <button type="button" @click="setGroup(g, !groupAllOn(g))"
                                                    class="m-press text-[11px] font-semibold text-primary flex-shrink-0 px-2 py-1"
                                                    x-text="groupAllOn(g) ? '{{ __('admin.role_clear_all') }}' : '{{ __('admin.role_select_all') }}'"></button>
                                        </div>

                                        <div x-show="groupOpen(g.label)" x-cloak class="p-1"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 -translate-y-1"
                                             x-transition:enter-end="opacity-100 translate-y-0">
                                            <template x-for="p in g.perms" :key="p.slug">
                                                <label class="flex items-start gap-3 px-2.5 py-2.5 rounded-lg hover:bg-muted/50 cursor-pointer">
                                                    <input type="checkbox" class="mt-0.5 flex-shrink-0" style="width:18px;height:18px;accent-color:hsl(250 65% 65%);" :checked="has(p.slug)" @change="toggle(p.slug)">
                                                    <span class="min-w-0">
                                                        <span class="block text-sm text-foreground" x-text="p.name"></span>
                                                        <span class="block text-xs text-muted-foreground" x-text="p.desc"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Note for non-super-admins --}}
                    <p x-show="!canEdit" class="text-xs text-muted-foreground flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5"></i><span>{!! __('admin.role_superadmin_only') !!}</span>
                    </p>
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving || loading" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('admin.role_save_access') }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
