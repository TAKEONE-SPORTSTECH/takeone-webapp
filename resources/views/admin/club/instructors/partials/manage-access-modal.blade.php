{{-- "Manage Access" modal for the Instructors/Staff page's 3-dot menu. Reuses the
     same backend as the member popup's permission editor
     (App\Http\Controllers\Admin\ClubRoleController::memberPermissions /
     storeMemberPermissions), packaged standalone since staff may not have a club
     Membership row (the member popup requires one and would 404 for pure staff).
     Trigger from anywhere with: window.dispatchEvent(new CustomEvent('open-manage-access', { detail: { userId, name } })) --}}
<div x-data="manageAccessModal()" @open-manage-access.window="open($event.detail.userId, $event.detail.name)">
<template x-teleport="body">
<div x-show="isOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
    <div x-show="isOpen" x-transition.opacity class="fixed inset-0 bg-black/50" @click="isOpen = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="isOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col" @click.stop>

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                <div class="min-w-0">
                    <h5 class="font-bold text-foreground">{{ __('admin.ins_manage_access') }}</h5>
                    <p class="text-xs text-muted-foreground truncate" x-text="name"></p>
                </div>
                <button type="button" @click="isOpen = false" class="text-muted-foreground hover:text-foreground flex-shrink-0"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="px-5 py-4 overflow-y-auto flex-1">
                <template x-if="loading">
                    <div class="flex flex-col items-center justify-center py-10 gap-3">
                        <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-400">{{ __('admin.ins_loading') }}</p>
                    </div>
                </template>

                <template x-if="!loading">
                    <div class="space-y-4">
                        <template x-if="canEdit">
                            <div class="grid grid-cols-2 gap-1 p-1 bg-muted rounded-2xl text-sm font-semibold">
                                <button type="button" @click="mode = 'role'" :class="mode === 'role' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="rounded-xl py-2 transition-colors">{{ __('admin.ins_access_mode_role') }}</button>
                                <button type="button" @click="mode = 'custom'" :class="mode === 'custom' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="rounded-xl py-2 transition-colors">{{ __('admin.ins_access_mode_custom') }}</button>
                            </div>
                        </template>

                        {{-- Standard role assignment --}}
                        <div x-show="mode === 'role'">
                            <label class="form-label">{{ __('admin.ins_access_role') }}</label>
                            <x-select-menu model="role" :placeholder="__('admin.ins_access_select_role')" :options="$availableRoles->map(fn ($r) => ['value' => $r->slug, 'label' => $r->name])->all()" />
                        </div>

                        {{-- Custom permission checklist (super-admin only) --}}
                        <div x-show="mode === 'custom'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground"><span x-text="perms.length"></span> / <span x-text="total"></span></span>
                                <div class="flex gap-2">
                                    <button type="button" @click="perms = allPermSlugs()" class="text-xs px-2.5 py-1 rounded-md bg-accent text-primary hover:bg-primary hover:text-white transition-colors">{{ __('admin.ins_access_select_all') }}</button>
                                    <button type="button" @click="perms = []" class="text-xs px-2.5 py-1 rounded-md bg-muted text-gray-600 hover:bg-gray-200 transition-colors">{{ __('admin.ins_access_clear_all') }}</button>
                                </div>
                            </div>
                            <div class="border border-gray-100 rounded-xl p-2 max-h-[40vh] overflow-y-auto">
                                <template x-for="g in groups" :key="g.label">
                                    <div class="mb-2">
                                        <div class="text-[11px] font-bold uppercase tracking-wide text-primary/70 px-2 pt-1.5 pb-1" x-text="g.label"></div>
                                        <template x-for="p in g.perms" :key="p.slug">
                                            <label class="flex items-start gap-2.5 px-2 py-2 rounded-lg hover:bg-muted/50 cursor-pointer">
                                                <input type="checkbox" :checked="perms.includes(p.slug)" @change="togglePerm(p.slug)" class="mt-0.5" style="width:16px;height:16px;accent-color:hsl(250 65% 65%);">
                                                <span class="min-w-0">
                                                    <span class="block text-sm text-foreground" x-text="p.name"></span>
                                                    <span class="block text-xs text-muted-foreground" x-text="p.desc || ''"></span>
                                                </span>
                                            </label>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <p class="text-xs text-muted-foreground mt-2"><i class="bi bi-info-circle me-1"></i>{{ __('admin.ins_access_custom_note') }}</p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 flex-shrink-0 grid grid-cols-2 gap-2">
                <button type="button" @click="isOpen = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('shared.cancel') }}</button>
                <button type="button" @click="save()" :disabled="saving || loading" class="px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-medium disabled:opacity-50">
                    <i class="bi bi-check-lg mr-1"></i>{{ __('admin.ins_access_save') }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>

@once
@push('scripts')
<script>
function manageAccessModal() {
    return {
        isOpen: false, loading: false, saving: false,
        userId: null, name: '',
        mode: 'role', role: '', canEdit: false,
        perms: [], groups: [], total: 0,
        _base: '{{ url('admin/club/' . $club->slug . '/roles/member') }}',
        _saveUrl: '{{ route('admin.club.roles.member.permissions.store', $club->slug) }}',

        open(userId, name) {
            this.userId = userId; this.name = name; this.isOpen = true; this.loading = true;
            this.mode = 'role'; this.role = ''; this.perms = []; this.groups = []; this.total = 0;
            fetch(`${this._base}/${userId}/permissions`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) throw new Error();
                    this.canEdit = !!d.canEdit;
                    this.role = d.role || '';
                    this.perms = d.permissions || [];
                    this.groups = d.groups || [];
                    this.total = d.total || 0;
                    this.mode = (d.custom && this.canEdit) ? 'custom' : 'role';
                })
                .catch(() => window.showToast('error', @js(__('admin.ins_access_load_failed'))))
                .finally(() => { this.loading = false; });
        },

        allPermSlugs() { return this.groups.flatMap(g => g.perms.map(p => p.slug)); },
        togglePerm(slug) {
            const i = this.perms.indexOf(slug);
            if (i > -1) this.perms.splice(i, 1); else this.perms.push(slug);
        },

        save() {
            if (this.saving || this.loading) return;
            if (this.mode === 'role' && !this.role) { window.showToast('warning', @js(__('admin.ins_access_pick_role'))); return; }
            this.saving = true;
            const body = this.mode === 'custom'
                ? { user_id: this.userId, permissions: this.perms }
                : { user_id: this.userId, role: this.role };

            fetch(this._saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(body),
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { window.showToast('success', d.message || @js(__('admin.ins_access_saved'))); this.isOpen = false; }
                else window.showToast('error', d.message || @js(__('admin.ins_access_save_failed')));
            })
            .catch(() => window.showToast('error', @js(__('admin.ins_access_save_failed'))))
            .finally(() => { this.saving = false; });
        },
    };
}
</script>
@endpush
@endonce
