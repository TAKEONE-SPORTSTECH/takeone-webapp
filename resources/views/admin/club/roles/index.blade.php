@extends('layouts.admin-club')

@php
    // Token map: role colour name → [accent hex, soft pill classes].
    $roleColor = [
        'primary'   => ['#7c6cf0', 'bg-accent text-primary'],
        'danger'    => ['#dc3545', 'bg-red-100 text-red-700'],
        'info'      => ['#0ea5e9', 'bg-sky-100 text-sky-700'],
        'success'   => ['#10b981', 'bg-emerald-100 text-emerald-700'],
        'warning'   => ['#f59e0b', 'bg-amber-100 text-amber-700'],
        'secondary' => ['#64748b', 'bg-slate-100 text-slate-700'],
    ];
    // slug → pill classes, so a member's badge matches its role-legend colour.
    $slugPill = [];
    foreach (($rolesData ?? []) as $rd) {
        $slugPill[$rd['slug']] = ($roleColor[$rd['color']] ?? $roleColor['secondary'])[1];
    }
@endphp

@section('club-admin-content')
<div class="roles-management space-y-6" x-data="{
    showAssignModal: false,
    showRemoveModal: false,
    assignData: { userId: '', userName: '', userRoles: [] },
    removeData: { userId: '', userName: '', role: '', roleLabel: '' },
    roleOpen: false,
    assignRole: '',
    assignRoleLabel: '',
    roleOptions: @js(($availableRoles ?? collect())->map(fn($r) => ['slug' => $r->slug, 'name' => $r->name])->values()),
    openAssign(d) { this.assignData = d; this.assignRole = ''; this.assignRoleLabel = ''; this.roleOpen = false; this.showAssignModal = true; }
}">

    {{-- ===== Hero ===== --}}
    <x-admin-hero
        eyebrow="{{ __('admin.club_roles_index_hero_eyebrow') }}"
        title="{{ __('admin.club_roles_index_hero_title') }}"
        :subtitle="__('admin.club_roles_index_hero_subtitle', ['club' => $club->name ?? 'this club'])"
        icon="bi-shield-lock"
        :count="isset($members) ? count($members) : 0"
        countLabel="{{ __('admin.club_roles_index_members') }}">
        <x-slot:actions>
            <button type="button" @click="$dispatch('open-walkin-modal')"
                    class="inline-flex items-center gap-2 bg-white text-primary px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/90 transition-colors">
                <i class="bi bi-person-plus"></i> {{ __('admin.club_roles_index_add_member') }}
            </button>
        </x-slot:actions>
    </x-admin-hero>

    {{-- ===== Role legend — signature: access meter per role (editable, super-admin) ===== --}}
    @php
        $roleUrls = [
            'create'  => route('admin.club.roles.def.store', $club->slug),
            'update'  => route('admin.club.roles.def.update', [$club->slug, '__ID__']),
            'destroy' => route('admin.club.roles.def.destroy', [$club->slug, '__ID__']),
        ];
        // Member coverage: split members into those with a role vs none (for the click-through popup).
        $withList = [];
        $withoutList = [];
        foreach (($members ?? collect()) as $m) {
            $u = $m->user;
            if (!$u) continue;
            $rs = $u->getRolesForTenant($club->id);
            $entry = [
                'id'     => $u->id,
                'name'   => $u->full_name ?? 'Unknown',
                'email'  => $u->email ?? '',
                'gender' => strtolower((string) ($u->gender ?? '')),
                'avatar' => $u->profile_picture ? asset('storage/' . $u->profile_picture) : null,
                'url'    => $u->uuid ? route('member.show', $u->uuid) : null,
                'roles'  => $rs->map(fn ($r) => $r->name)->values()->all(),
            ];
            $rs->count() > 0 ? $withList[] = $entry : $withoutList[] = $entry;
        }
        $withRoleCount = count($withList);
        $withoutRoleCount = count($withoutList);
    @endphp
    <section x-data="rolesManager(@js(['roles' => $rolesData, 'groups' => $groupsData, 'canEdit' => $canEdit, 'total' => $totalPerms, 'urls' => $roleUrls]))">

        {{-- Summary strip --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <button type="button" @click="openRolesList('all')"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 flex items-center gap-3 text-start hover:border-primary/40 hover:shadow-md transition-all">
                <span class="w-9 h-9 rounded-lg grid place-items-center bg-accent text-primary shrink-0"><i class="bi bi-diagram-3"></i></span>
                <div class="min-w-0"><div class="text-lg font-bold text-gray-900 leading-none" x-text="roles.length"></div><div class="text-xs text-muted-foreground flex items-center gap-1">{{ __('admin.club_roles_index_total_roles') }} <i class="bi bi-arrow-right-short"></i></div></div>
            </button>
            <button type="button" @click="openRolesList('custom')"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 flex items-center gap-3 text-start hover:border-purple-300 hover:shadow-md transition-all">
                <span class="w-9 h-9 rounded-lg grid place-items-center bg-purple-100 text-purple-600 shrink-0"><i class="bi bi-stars"></i></span>
                <div class="min-w-0"><div class="text-lg font-bold text-gray-900 leading-none" x-text="roles.filter(r => !r.isSystem).length"></div><div class="text-xs text-muted-foreground flex items-center gap-1">{{ __('admin.club_roles_index_custom_roles') }} <i class="bi bi-arrow-right-short"></i></div></div>
            </button>
            <button type="button" @click="$dispatch('show-members', { kind: 'with' })"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 flex items-center gap-3 text-start hover:border-emerald-300 hover:shadow-md transition-all">
                <span class="w-9 h-9 rounded-lg grid place-items-center bg-emerald-100 text-emerald-600 shrink-0"><i class="bi bi-person-check"></i></span>
                <div class="min-w-0"><div class="text-lg font-bold text-gray-900 leading-none">{{ $withRoleCount }}</div><div class="text-xs text-muted-foreground flex items-center gap-1">{{ __('admin.club_roles_index_members_with_role') }} <i class="bi bi-arrow-right-short"></i></div></div>
            </button>
            <button type="button" @click="$dispatch('show-members', { kind: 'without' })"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 flex items-center gap-3 text-start hover:border-gray-300 hover:shadow-md transition-all">
                <span class="w-9 h-9 rounded-lg grid place-items-center bg-gray-100 text-gray-400 shrink-0"><i class="bi bi-person-dash"></i></span>
                <div class="min-w-0"><div class="text-lg font-bold text-gray-900 leading-none">{{ $withoutRoleCount }}</div><div class="text-xs text-muted-foreground flex items-center gap-1">{{ __('admin.club_roles_index_no_role_yet') }} <i class="bi bi-arrow-right-short"></i></div></div>
            </button>
        </div>

        <div id="rolesListSection" class="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-3 scroll-mt-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2"><i class="bi bi-diagram-3 text-primary"></i>{{ __('admin.club_roles_index_roles_at_club') }}</h3>
                <p class="text-sm text-muted-foreground mb-0">{{ __('admin.club_roles_index_grants_slice_before') }} <span x-text="total"></span> {{ __('admin.club_roles_index_grants_slice_after') }}</p>
            </div>
            <div class="flex items-center gap-2 w-full md:w-auto">
                <div class="relative flex-1 md:w-64">
                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    <input type="text" x-model="q" placeholder="{{ __('admin.club_roles_index_search_roles_perms') }}"
                           class="w-full ps-10 pe-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <button x-show="canEdit" @click="openCreate()" class="btn btn-primary btn-sm shrink-0">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('admin.club_roles_index_new_role') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="role in filteredRoles()" :key="role.id">
                <div class="group relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col">
                    <span class="absolute inset-x-0 top-0 h-1" :style="'background:' + dotColor(role.color)"></span>

                    {{-- Header: name pill + SYSTEM + edit/delete --}}
                    <div class="flex items-start justify-between gap-2 px-4 pt-4 pb-2">
                        <div class="flex items-center gap-2 flex-wrap min-w-0">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold" :style="pillStyle(role.color)">
                                <span class="w-1.5 h-1.5 rounded-full" :style="'background:' + dotColor(role.color)"></span><span x-text="role.label"></span>
                            </span>
                            <span x-show="role.isSystem" class="text-[9px] font-bold tracking-wide text-gray-400 bg-gray-50 rounded px-1.5 py-0.5">{{ __('admin.club_roles_index_system_badge') }}</span>
                        </div>
                        <div class="flex gap-1 shrink-0" x-show="canEdit">
                            <button @click="openEdit(role)" title="{{ __('admin.club_roles_index_edit_role_title') }}" class="w-7 h-7 grid place-items-center rounded-md bg-accent text-primary hover:bg-primary hover:text-white transition-colors"><i class="bi bi-pencil text-xs"></i></button>
                            <button x-show="!role.isSystem" @click="remove(role)" title="{{ __('admin.club_roles_index_delete_role_title') }}" class="w-7 h-7 grid place-items-center rounded-md bg-red-50 text-red-600 hover:bg-red-100 transition-colors"><i class="bi bi-trash text-xs"></i></button>
                        </div>
                    </div>

                    {{-- Meta: member count · permission count --}}
                    <div class="flex items-center gap-2 text-xs text-muted-foreground px-4 pb-2">
                        <span><strong class="text-gray-900" x-text="role.userCount"></strong> <span x-text="role.userCount === 1 ? '{{ __('admin.club_roles_index_member_singular') }}' : '{{ __('admin.club_roles_index_members') }}'"></span></span>
                        <span class="text-gray-300">·</span>
                        <span><i class="bi bi-key"></i> <span x-text="role.permissions.length"></span> / <span x-text="total"></span> {{ __('admin.club_roles_index_permissions') }}</span>
                    </div>

                    {{-- Permission chips, grouped — granted vs denied --}}
                    <div class="px-4 pb-4 flex-1">
                        <template x-for="g in groups" :key="g.label">
                            <div>
                                <div class="perm-group-label" x-text="g.label"></div>
                                <div class="mb-1.5">
                                    <template x-for="p in g.perms" :key="p.slug">
                                        <span class="perm-chip" :class="role.permissions.includes(p.slug) ? 'granted' : 'denied'" :title="p.desc || p.name" x-text="p.name"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="filteredRoles().length === 0" class="text-center py-10 text-muted-foreground">
            <i class="bi bi-search text-3xl"></i>
            <p class="mt-2 mb-0">{{ __('admin.club_roles_index_no_roles_match_before') }}<span x-text="q"></span>{{ __('admin.club_roles_index_no_roles_match_after') }}</p>
        </div>

        {{-- Create / Edit Role modal --}}
        <div x-show="show" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/50" @click="show = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl relative flex flex-col max-h-[90vh]" @click.stop>
                    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                        <div>
                            <h5 class="font-bold mb-0" x-text="form.id ? ('{{ __('admin.club_roles_index_edit_role_prefix') }}' + form.label) : '{{ __('admin.club_roles_index_create_role') }}'"></h5>
                            <p class="text-xs text-muted-foreground mb-0">{{ __('admin.club_roles_index_modal_desc') }}</p>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600" @click="show = false"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="px-6 py-4 overflow-y-auto flex-1">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.club_roles_index_role_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="form.label" :readonly="form.isSystem"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       :class="form.isSystem ? 'bg-gray-50 text-gray-500' : ''" placeholder="{{ __('admin.club_roles_index_role_name_placeholder') }}">
                                <p x-show="form.isSystem" class="text-xs text-muted-foreground mt-1">{{ __('admin.club_roles_index_system_name_locked') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.club_roles_index_badge_colour') }}</label>
                                <div class="flex gap-2 flex-wrap mt-1">
                                    <template x-for="c in colors" :key="c">
                                        <button type="button" @click="form.color = c"
                                                class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110"
                                                :style="'background:' + dotColor(c) + ';'"
                                                :class="form.color === c ? 'border-gray-800' : 'border-transparent'"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.club_roles_index_permissions_label') }} (<span x-text="form.perms.length"></span>/<span x-text="total"></span>)</span>
                            <div class="flex gap-2">
                                <button type="button" @click="setAll(true)" class="text-xs px-2.5 py-1 rounded-md bg-accent text-primary hover:bg-primary hover:text-white transition-colors">{{ __('admin.club_roles_index_select_all') }}</button>
                                <button type="button" @click="setAll(false)" class="text-xs px-2.5 py-1 rounded-md bg-muted text-gray-600 hover:bg-gray-200 transition-colors">{{ __('admin.club_roles_index_clear_all') }}</button>
                            </div>
                        </div>

                        <div class="border border-gray-100 rounded-xl p-2 max-h-[44vh] overflow-y-auto">
                            <template x-for="g in groups" :key="g.label">
                                <div class="mb-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-primary/70 px-2 pt-1.5 pb-1" x-text="g.label"></div>
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

                    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                        <button class="btn btn-outline-secondary" @click="show = false">{{ __('shared.cancel') }}</button>
                        <button class="btn btn-primary" @click="save()" :disabled="saving">
                            <i class="bi bi-check-lg me-1"></i><span x-text="saving ? '{{ __('admin.club_roles_index_saving') }}' : (form.id ? '{{ __('admin.club_roles_index_save_changes') }}' : '{{ __('admin.club_roles_index_create_role') }}')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Roles list popup — opened from the summary tiles --}}
        <div x-show="showRolesList" x-cloak class="fixed inset-0 z-[70] overflow-y-auto"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-black/50" @click="showRolesList = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative flex flex-col max-h-[85vh]" @click.stop>
                    <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
                        <div>
                            <h5 class="font-bold mb-0 flex items-center gap-2"><i class="bi bi-diagram-3 text-primary"></i><span x-text="rolesListTitle"></span></h5>
                            <p class="text-xs text-muted-foreground mb-0"><span x-text="rolesListItems().length"></span> {{ __('admin.club_roles_index_roles_count') }}</p>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="showRolesList = false"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="px-5 pt-3">
                        <div class="relative">
                            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            <input type="text" x-model="rolesListQ" placeholder="{{ __('admin.club_roles_index_search_roles') }}"
                                   class="w-full ps-10 pe-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="px-5 py-3 overflow-y-auto flex-1 space-y-2">
                        <template x-for="role in rolesListItems()" :key="role.id">
                            <div class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100"
                                 :class="canEdit ? 'hover:border-primary/30 hover:bg-muted/40 transition-colors cursor-pointer' : ''"
                                 @click="canEdit && (showRolesList = false, openEdit(role))">
                                <span class="w-9 h-9 rounded-lg grid place-items-center font-bold text-sm shrink-0" :style="pillStyle(role.color)" x-text="monogram(role.label)"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-semibold text-gray-900 text-sm truncate" x-text="role.label"></span>
                                        <span x-show="role.isSystem" class="text-[9px] font-bold tracking-wide text-gray-400 bg-gray-50 rounded px-1.5 py-0.5">{{ __('admin.club_roles_index_system_badge') }}</span>
                                    </div>
                                    <div class="text-xs text-muted-foreground truncate">
                                        <span x-text="role.userCount"></span> <span x-text="role.userCount === 1 ? '{{ __('admin.club_roles_index_member_singular') }}' : '{{ __('admin.club_roles_index_members') }}'"></span>
                                        · <span x-text="role.permissions.length"></span>/<span x-text="total"></span> {{ __('admin.club_roles_index_permissions') }}
                                    </div>
                                </div>
                                <i x-show="canEdit" class="bi bi-pencil text-primary text-sm shrink-0"></i>
                            </div>
                        </template>
                        <div x-show="rolesListItems().length === 0" class="text-center py-10 text-muted-foreground">
                            <i class="bi bi-diagram-3 text-3xl"></i>
                            <p class="mt-2 mb-0 text-sm">{{ __('admin.club_roles_index_no_roles_here') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Assign Role Modal ===== --}}
    <div x-show="showAssignModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showAssignModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative" @click.stop>
                <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                    <h5 class="flex items-center gap-2 font-bold mb-0"><i class="bi bi-person-gear text-primary"></i>{{ __('admin.club_roles_index_assign_role') }}</h5>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="showAssignModal = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('admin.club.roles.store', $club->slug) }}" method="POST">
                    @csrf
                    <input type="hidden" name="user_id" :value="assignData.userId">
                    <input type="hidden" name="role" :value="assignRole">

                    <div class="px-6 py-4 space-y-4">
                        <p class="text-sm text-muted-foreground mb-0">{{ __('admin.club_roles_index_assign_role_to') }} <strong x-text="assignData.userName"></strong></p>

                        {{-- Custom dropdown (no native select) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_roles_index_role_label') }}</label>
                            <div class="relative" @click.outside="roleOpen = false">
                                <button type="button" @click="roleOpen = !roleOpen"
                                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm flex items-center justify-between text-start focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <span :class="assignRole ? 'text-gray-800' : 'text-gray-400'" x-text="assignRoleLabel || '{{ __('admin.club_roles_index_choose_role') }}'"></span>
                                    <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="{ 'rotate-180': roleOpen }"></i>
                                </button>
                                <div x-show="roleOpen" x-cloak x-transition
                                     class="absolute z-50 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden">
                                    <template x-for="r in roleOptions" :key="r.slug">
                                        <div @click="assignRole = r.slug; assignRoleLabel = r.name; roleOpen = false"
                                             class="px-4 py-2.5 text-sm cursor-pointer hover:bg-purple-50 flex items-center justify-between"
                                             :class="assignRole === r.slug ? 'text-purple-600 font-medium' : 'text-gray-700'">
                                            <span x-text="r.name"></span>
                                            <i x-show="assignRole === r.slug" class="bi bi-check-lg text-purple-600"></i>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="bg-muted/40 rounded-lg p-3">
                            <p class="text-xs font-medium text-gray-600 mb-2">{{ __('admin.club_roles_index_current_roles') }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-if="assignData.userRoles && assignData.userRoles.length === 0">
                                    <span class="text-xs text-gray-400 italic">{{ __('admin.club_roles_index_none_yet') }}</span>
                                </template>
                                <template x-for="role in assignData.userRoles" :key="role">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"
                                          x-text="role.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                        <button type="button" class="btn btn-outline-secondary" @click="showAssignModal = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="!assignRole">
                            <i class="bi bi-check-lg me-1"></i>{{ __('admin.club_roles_index_assign_role') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== Remove Role Modal ===== --}}
    <div x-show="showRemoveModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showRemoveModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative" @click.stop>
                <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                    <h5 class="flex items-center gap-2 font-bold text-red-600 mb-0"><i class="bi bi-exclamation-triangle"></i>{{ __('admin.club_roles_index_remove_role') }}</h5>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="showRemoveModal = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('admin.club.roles.destroy', $club->slug) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="user_id" :value="removeData.userId">
                    <input type="hidden" name="role" :value="removeData.role">
                    <div class="px-6 py-4">
                        <div class="bg-red-50 border border-red-100 rounded-lg p-3 text-sm">
                            {{ __('admin.club_roles_index_remove_confirm_before') }} <strong class="text-red-600" x-text="removeData.roleLabel"></strong> {{ __('admin.club_roles_index_remove_confirm_mid') }}
                            <strong x-text="removeData.userName"></strong>{{ __('admin.club_roles_index_remove_confirm_after') }}
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
                        <button type="button" class="btn btn-outline-secondary" @click="showRemoveModal = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>{{ __('admin.club_roles_index_remove_role') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Walk-in "Add Member" modal (same component used on the Members page) --}}
{{-- Members list popup — opened from the summary tiles --}}
<div x-data="membersListModal(@js(['with' => $withList, 'without' => $withoutList]))"
     x-on:show-members.window="openList($event.detail.kind)">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative flex flex-col max-h-[85vh]" @click.stop>
                <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h5 class="font-bold mb-0 flex items-center gap-2"><i class="bi bi-people-fill text-primary"></i><span x-text="title"></span></h5>
                        <p class="text-xs text-muted-foreground mb-0"><span x-text="source.length"></span> {{ __('admin.club_roles_index_members_count') }}</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="open = false"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="px-5 pt-3">
                    <div class="relative">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input type="text" x-model="q" placeholder="{{ __('admin.club_roles_index_search') }}"
                               class="w-full ps-10 pe-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>

                <div class="px-5 py-3 overflow-y-auto flex-1 space-y-2">
                    <template x-for="m in items" :key="m.id">
                        <a :href="m.url || '#'" class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 hover:border-primary/30 hover:bg-muted/40 transition-colors no-underline">
                            <template x-if="m.avatar">
                                <img :src="m.avatar" alt="" class="w-10 h-10 rounded-full object-cover shrink-0">
                            </template>
                            <template x-if="!m.avatar">
                                <span class="w-10 h-10 rounded-full grid place-items-center text-white font-bold text-sm shrink-0" :style="'background:' + avatarBg(m.gender)" x-text="initial(m.name)"></span>
                            </template>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-gray-900 text-sm truncate" x-text="m.name"></div>
                                <div class="text-xs text-muted-foreground truncate" x-text="m.email || '{{ __('admin.club_roles_index_no_email') }}'"></div>
                            </div>
                            <div class="flex flex-wrap gap-1 justify-end max-w-[45%]">
                                <template x-if="m.roles.length === 0">
                                    <span class="text-[10px] text-gray-400 italic">{{ __('admin.club_roles_index_no_role') }}</span>
                                </template>
                                <template x-for="r in m.roles" :key="r">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent text-primary" x-text="r"></span>
                                </template>
                            </div>
                        </a>
                    </template>
                    <div x-show="items.length === 0" class="text-center py-10 text-muted-foreground">
                        <i class="bi bi-people text-3xl"></i>
                        <p class="mt-2 mb-0 text-sm">{{ __('admin.club_roles_index_no_members_here') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-registration-walkin :club="$club" :packages="$packages ?? []" />

@push('styles')
<style>
    .perm-group-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin: 6px 0 2px; }
    .perm-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 6px; padding: 2px 7px; margin: 0 3px 3px 0; }
    .perm-chip.granted { background: hsl(250 60% 95%); color: hsl(250 55% 45%); }
    .perm-chip.denied  { background: #f3f4f6; color: #cbd5e1; }
</style>
@endpush

@push('scripts')
<script>
function membersListModal(data) {
    return {
        withList: data.with || [],
        withoutList: data.without || [],
        open: false,
        kind: 'with',
        q: '',
        get title() { return this.kind === 'with' ? '{{ __('admin.club_roles_index_members_with_role') }}' : '{{ __('admin.club_roles_index_members_without_role') }}'; },
        get source() { return this.kind === 'with' ? this.withList : this.withoutList; },
        get items() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.source;
            return this.source.filter(m => (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q));
        },
        openList(kind) { this.kind = kind === 'without' ? 'without' : 'with'; this.q = ''; this.open = true; },
        initial(n) { return (n || '?').charAt(0).toUpperCase(); },
        avatarBg(g) { return g === 'female' ? 'linear-gradient(135deg,#d63384,#a61e4d)' : 'linear-gradient(135deg, hsl(250 65% 65%), hsl(250 60% 58%))'; },
    };
}

function rolesManager(data) {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    return {
        roles: data.roles || [],
        groups: data.groups || [],
        canEdit: !!data.canEdit,
        total: data.total || 0,
        urls: data.urls || {},
        colors: ['primary', 'success', 'info', 'warning', 'danger', 'secondary'],
        q: '',
        show: false,
        saving: false,

        // Roles list popup (opened from the summary tiles)
        showRolesList: false,
        rolesListFilter: 'all', // 'all' | 'custom'
        rolesListQ: '',
        openRolesList(filter) { this.rolesListFilter = filter === 'custom' ? 'custom' : 'all'; this.rolesListQ = ''; this.showRolesList = true; },
        get rolesListTitle() { return this.rolesListFilter === 'custom' ? '{{ __('admin.club_roles_index_custom_roles') }}' : '{{ __('admin.club_roles_index_all_roles') }}'; },
        rolesListItems() {
            let list = this.rolesListFilter === 'custom' ? this.roles.filter(r => !r.isSystem) : this.roles;
            const q = this.rolesListQ.trim().toLowerCase();
            if (q) list = list.filter(r => (r.label || '').toLowerCase().includes(q) || (r.description || '').toLowerCase().includes(q));
            return list;
        },

        filteredRoles() {
            const q = this.q.trim().toLowerCase();
            if (!q) return this.roles;
            return this.roles.filter(r =>
                (r.label || '').toLowerCase().includes(q) ||
                (r.description || '').toLowerCase().includes(q) ||
                (r.permissions || []).some(p => p.toLowerCase().includes(q))
            );
        },
        form: { id: null, label: '', description: '', color: 'primary', isSystem: false, perms: [] },

        pillStyle(color) {
            const m = {
                primary: ['hsl(250 60% 95%)', 'hsl(250 55% 45%)'], danger: ['#fee2e2', '#991b1b'],
                info: ['#e0f2fe', '#0369a1'], success: ['#dcfce7', '#166534'],
                warning: ['#fef3c7', '#92400e'], secondary: ['#f1f5f9', '#475569'],
            };
            const c = m[color] || m.primary;
            return `background:${c[0]};color:${c[1]};`;
        },
        dotColor(color) {
            const m = { primary: '#7c6cf0', danger: '#dc3545', info: '#0ea5e9', success: '#10b981', warning: '#f59e0b', secondary: '#64748b' };
            return m[color] || m.primary;
        },
        softColor(color) {
            const m = { primary: 'hsl(250 60% 96%)', danger: '#fef2f2', info: '#f0f9ff', success: '#f0fdf4', warning: '#fffbeb', secondary: '#f8fafc' };
            return m[color] || m.primary;
        },
        monogram(label) { return (label || '?').trim().charAt(0).toUpperCase() || '?'; },
        coveredGroups(role) { return this.groups.filter(g => g.perms.some(p => role.permissions.includes(p.slug))).length; },
        has(slug) { return this.form.perms.includes(slug); },
        toggle(slug) { const i = this.form.perms.indexOf(slug); i > -1 ? this.form.perms.splice(i, 1) : this.form.perms.push(slug); },
        setAll(v) { this.form.perms = v ? this.groups.flatMap(g => g.perms.map(p => p.slug)) : []; },
        openCreate() { this.form = { id: null, label: '', description: '', color: 'primary', isSystem: false, perms: [] }; this.show = true; },
        openEdit(role) { this.form = { id: role.id, label: role.label, description: role.description || '', color: role.color, isSystem: role.isSystem, perms: [...role.permissions] }; this.show = true; },

        async save() {
            if (!this.form.label.trim()) { window.showToast?.('error', '{{ __('admin.club_roles_index_role_name_required') }}'); return; }
            this.saving = true;
            const isEdit = !!this.form.id;
            const url = isEdit ? this.urls.update.replace('__ID__', this.form.id) : this.urls.create;
            try {
                const r = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ label: this.form.label, description: this.form.description, color: this.form.color, permissions: this.form.perms }),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.success) throw new Error(d.message || '{{ __('admin.club_roles_index_could_not_save') }}');
                const idx = this.roles.findIndex(x => x.id === d.role.id);
                if (idx > -1) this.roles[idx] = d.role; else this.roles.push(d.role);
                window.showToast?.('success', d.message);
                this.show = false;
            } catch (e) { window.showToast?.('error', e.message); }
            finally { this.saving = false; }
        },

        async remove(role) {
            const ok = await window.confirmAction?.({ title: '{{ __('admin.club_roles_index_delete_role_title') }}', message: `{{ __('admin.club_roles_index_delete_confirm_before') }}${role.label}{{ __('admin.club_roles_index_delete_confirm_after') }}`, type: 'danger', confirmText: '{{ __('shared.delete') }}' });
            if (!ok) return;
            try {
                const r = await fetch(this.urls.destroy.replace('__ID__', role.id), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.success) throw new Error(d.message || '{{ __('admin.club_roles_index_could_not_delete') }}');
                this.roles = this.roles.filter(x => x.id !== role.id);
                window.showToast?.('success', d.message);
            } catch (e) { window.showToast?.('error', e.message); }
        },
    };
}

document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('searchMembers');
    const empty  = document.getElementById('noResultsMessage');
    const list   = document.getElementById('membersList');
    search?.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('.member-card').forEach(card => {
            const match = (card.dataset.name || '').includes(q) || (card.dataset.email || '').includes(q);
            card.classList.toggle('hidden', !match);
            if (match) visible++;
        });
        if (empty) empty.classList.toggle('hidden', !(visible === 0 && q.length > 0));
        if (list)  list.classList.toggle('hidden', visible === 0 && q.length > 0);
    });
});
</script>
@endpush
@endsection
