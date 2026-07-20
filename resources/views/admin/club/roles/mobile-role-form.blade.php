{{-- Mobile "Add / Edit Role definition" — teleported bottom sheet. Opens on
     `open-role-form` (detail = role card object for edit, or empty for add). Posts to
     the SAME def.store / def.update JSON endpoints, then dispatches `role-saved`.
     Super-admin only (the page includes this partial only when $canEdit). --}}
<script>
window.roleFormSheet = function () {
    return {
        open: false,
        saving: false,
        editId: null,
        isSystem: false,
        csrf: '{{ csrf_token() }}',
        colors: ['primary', 'success', 'info', 'warning', 'danger', 'secondary'],
        groups: @js($groupsData),
        total: {{ (int) $totalPerms }},

        label: '',
        description: '',
        color: 'primary',
        perms: [],

        dotColor(c) { return (window.ROLE_DOT || {})[c] || '#7c6cf0'; },
        has(slug) { return this.perms.includes(slug); },
        toggle(slug) { const i = this.perms.indexOf(slug); i > -1 ? this.perms.splice(i, 1) : this.perms.push(slug); },
        setAll(v) { this.perms = v ? this.groups.flatMap(g => g.perms.map(p => p.slug)) : []; },

        get formAction() {
            return this.editId
                ? `{{ url('admin/club/' . $club->slug . '/roles/definitions') }}/${this.editId}`
                : `{{ route('admin.club.roles.def.store', $club->slug) }}`;
        },

        openForm(detail) {
            const d = detail && detail.id ? detail : null;
            this.editId = d ? d.id : null;
            this.isSystem = d ? !!d.isSystem : false;
            this.label = d ? (d.label || '') : '';
            this.description = d ? (d.description || '') : '';
            this.color = d ? (d.color || 'primary') : 'primary';
            this.perms = d && Array.isArray(d.permissions) ? d.permissions.slice() : [];
            this.open = true;
        },

        async submit() {
            if (this.saving) return;
            if (!this.label.trim()) { window.showToast('warning', '{{ __('admin.role_label_required') }}'); return; }
            this.saving = true;
            try {
                const res = await fetch(this.formAction, {
                    method: this.editId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ label: this.label, description: this.description, color: this.color, permissions: this.perms }),
                });
                const dd = await res.json().catch(() => ({}));
                if (!res.ok || dd.success === false) {
                    const msg = dd.message || (dd.errors ? Object.values(dd.errors)[0][0] : null) || 'Error';
                    throw new Error(msg);
                }
                window.dispatchEvent(new CustomEvent('role-saved', { detail: { role: dd.role } }));
                this.open = false;
                window.showToast('success', dd.message || '{{ __('admin.role_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="roleFormSheet()"
     @open-role-form.window="openForm($event.detail)"
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
                <h5 class="text-base font-semibold flex items-center">
                    <i class="bi bi-shield-lock mr-2"></i><span x-text="editId ? '{{ __('admin.role_edit') }}' : '{{ __('admin.role_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4">

                {{-- Label --}}
                <div>
                    <label class="form-label">{{ __('admin.role_label') }} <span class="text-red-500">*</span></label>
                    <input type="text" x-model="label" :readonly="isSystem" maxlength="60"
                           class="form-control" :class="isSystem ? 'bg-gray-50 text-gray-500' : ''"
                           placeholder="{{ __('admin.role_label') }}">
                </div>

                {{-- Description --}}
                <div>
                    <label class="form-label">{{ __('admin.role_desc') }}</label>
                    <textarea x-model="description" rows="2" maxlength="255" class="form-control resize-none" placeholder="{{ __('admin.role_desc') }}"></textarea>
                </div>

                {{-- Color --}}
                <div>
                    <label class="form-label">{{ __('admin.role_color') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="c in colors" :key="c">
                            <button type="button" @click="color = c"
                                    class="w-8 h-8 rounded-full border-2 transition-transform hover:scale-110"
                                    :style="`background:${dotColor(c)}`"
                                    :class="color === c ? 'ring-2 ring-offset-2 ring-primary scale-110 border-white' : 'border-white/70'"></button>
                        </template>
                    </div>
                </div>

                {{-- Permissions --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">{{ __('admin.role_permissions') }} (<span x-text="perms.length"></span>/<span x-text="total"></span>)</label>
                        <div class="flex gap-1.5">
                            <button type="button" @click="setAll(true)" class="text-[11px] px-2 py-1 rounded-md bg-accent text-primary font-medium">{{ __('admin.club_roles_index_select_all') }}</button>
                            <button type="button" @click="setAll(false)" class="text-[11px] px-2 py-1 rounded-md bg-muted text-gray-600 font-medium">{{ __('admin.club_roles_index_clear_all') }}</button>
                        </div>
                    </div>
                    <div class="border border-gray-100 rounded-xl p-2 space-y-2">
                        <template x-for="g in groups" :key="g.label">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wide text-primary/70 px-2 pt-1 pb-1" x-text="g.label"></div>
                                <template x-for="p in g.perms" :key="p.slug">
                                    <label class="flex items-start gap-2.5 px-2 py-2 rounded-lg hover:bg-muted/50 cursor-pointer">
                                        <input type="checkbox" class="mt-0.5" style="width:16px;height:16px;accent-color:hsl(250 65% 65%);" :checked="has(p.slug)" @change="toggle(p.slug)">
                                        <span class="min-w-0">
                                            <span class="block text-sm text-foreground" x-text="p.name"></span>
                                            <span class="block text-xs text-muted-foreground" x-text="p.desc"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i><span x-text="editId ? '{{ __('admin.update') }}' : '{{ __('admin.role_add') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
