@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_roles'))

@php
    $dotMap = [
        'primary' => '#7c6cf0', 'danger' => '#dc3545', 'info' => '#0ea5e9',
        'success' => '#10b981', 'warning' => '#f59e0b', 'secondary' => '#64748b',
    ];
    $roleIconMap = [
        'danger' => 'bi-shield-lock', 'info' => 'bi-person-badge', 'warning' => 'bi-flag',
        'success' => 'bi-person-workspace', 'primary' => 'bi-stars', 'secondary' => 'bi-person-gear',
    ];

    $pickMembers  = $members->filter(fn ($m) => $m->user);
    $typeCount    = count($rolesData);
    $teamCount    = count($roleHolders);
    $memberCount  = $pickMembers->count();
    $coveragePct  = $memberCount > 0 ? (int) round(min($teamCount, $memberCount) / $memberCount * 100) : 0;

    // Filter chips: one per role type that actually has a holder, so the chip row
    // never lists roles nobody holds.
    $heldRoleNames = collect($roleHolders)->flatMap(fn ($h) => $h['roles'])->unique()->values();
@endphp

@section('club-admin-content')
<div class="-mx-4 -mt-4"
     x-data="rolesHub()" x-init="init()"
     @role-removed.window="removeRoleId = null; removeRoleName = ''">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                    <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_roles') }}</h1>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-shield-lock text-xl m-float"></i>
                </div>
            </div>

            {{-- Coverage meter: how much of the club actually holds a role --}}
            <div class="mt-4">
                <div class="flex items-baseline justify-between text-[11px] text-white/80">
                    <span>{{ __('admin.role_coverage') }}</span>
                    <span><span class="font-black text-white text-sm" id="statTeam">{{ $teamCount }}</span> / {{ $memberCount }}</span>
                </div>
                <div class="mt-1.5 h-1.5 rounded-full bg-white/25 overflow-hidden">
                    <div class="h-full rounded-full bg-white m-bar-fill" style="width: {{ $coveragePct }}%"></div>
                </div>
            </div>
        </div>
    </header>

    {{-- ══════════════ HUB ══════════════ --}}
    <div x-show="panel === null" class="px-4 pt-5 pb-6">
        <div class="space-y-2.5 mobile-stagger">

            {{-- Primary action --}}
            <button type="button" @click="pickerOpen = true"
                    class="m-press w-full flex items-center gap-3 p-4 rounded-2xl bg-primary text-white shadow-sm">
                <span class="w-11 h-11 rounded-xl bg-white/20 border border-white/25 grid place-items-center flex-shrink-0"><i class="bi bi-person-plus text-lg"></i></span>
                <span class="min-w-0 flex-1 text-start">
                    <span class="block text-sm font-bold">{{ __('admin.role_assign') }}</span>
                    <span class="block text-[11px] text-white/80 truncate">{{ __('admin.role_pick_member_sub') }}</span>
                </span>
                <i class="bi bi-chevron-right text-white/80 text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>

            {{-- Drill-down: team & access --}}
            <button type="button" @click="open('team')" class="m-card m-press w-full flex items-center gap-3 p-4 text-start">
                <span class="w-11 h-11 rounded-xl bg-sky-100 text-sky-600 grid place-items-center flex-shrink-0"><i class="bi bi-people text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-foreground truncate">{{ __('admin.role_team_members') }}</span>
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $teamCount ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                    </span>
                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ __('admin.role_team_members_sub') }}</span>
                </span>
                <span class="text-sm font-bold text-foreground flex-shrink-0" id="statTeamRow">{{ $teamCount }}</span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>

            {{-- Drill-down: role types --}}
            <button type="button" @click="open('types')" class="m-card m-press w-full flex items-center gap-3 p-4 text-start">
                <span class="w-11 h-11 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-shield-check text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="text-sm font-semibold text-foreground truncate block">{{ __('admin.role_manage_types') }}</span>
                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ __('admin.role_manage_types_sub') }}</span>
                </span>
                <span class="text-sm font-bold text-foreground flex-shrink-0" id="statTypes">{{ $typeCount }}</span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>

            @unless($canEdit)
                <p class="text-xs text-muted-foreground flex items-start gap-1.5 px-1 pt-2">
                    <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i><span>{!! __('admin.role_superadmin_only') !!}</span>
                </p>
            @endunless
        </div>
    </div>

    {{-- ══════════════ PANEL CHROME ══════════════ --}}
    <div x-show="panel !== null" x-cloak
         class="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border px-3 py-2.5 flex items-center gap-2">
        <button type="button" @click="close()" class="m-press w-10 h-10 rounded-xl bg-white border border-border grid place-items-center flex-shrink-0" aria-label="{{ __('admin.cs_back') }}">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </button>
        <span class="font-bold text-foreground text-sm truncate flex-1" x-text="title"></span>

        <button type="button" x-show="panel === 'team'" @click="pickerOpen = true"
                class="m-press w-10 h-10 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0" aria-label="{{ __('admin.role_assign') }}">
            <i class="bi bi-person-plus"></i>
        </button>
        @if($canEdit)
            <button type="button" x-show="panel === 'types'" @click="$dispatch('open-role-form')"
                    class="m-press w-10 h-10 rounded-xl bg-primary text-white grid place-items-center flex-shrink-0" aria-label="{{ __('admin.role_add') }}">
                <i class="bi bi-plus-lg"></i>
            </button>
        @endif
    </div>

    {{-- ══════════════ PANEL: Team & access ══════════════ --}}
    <section x-show="panel === 'team'" x-cloak class="m-panel-in">

        {{-- Search + role filter chips --}}
        <div class="px-4 pt-4 pb-2 space-y-3">
            <div class="relative">
                <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                <input type="text" x-model="teamSearch" @input="applyTeamFilter()"
                       placeholder="{{ __('admin.role_search_member') }}"
                       class="w-full ps-10 pe-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
            </div>

            <div class="flex gap-2 overflow-x-auto -mx-4 px-4 pb-1" style="scrollbar-width:none;">
                <button type="button" @click="teamRole = ''; applyTeamFilter()"
                        class="m-press flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                        :class="teamRole === '' ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-200'">
                    {{ __('admin.role_filter_all') }}
                </button>
                @foreach($heldRoleNames as $rn)
                    <button type="button" @click="teamRole = @js($rn); applyTeamFilter()"
                            class="m-press flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                            :class="teamRole === @js($rn) ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-200'">
                        {{ $rn }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="px-4 pb-6">
            <div id="teamList" class="space-y-2.5">
                @foreach($roleHolders as $h)
                    <div class="m-card p-3.5 flex items-center gap-3" id="team-{{ $h['id'] }}"
                         data-name="{{ mb_strtolower($h['name']) }}" data-roles="{{ implode('|', $h['roles']) }}">
                        <span class="w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                            @if($h['avatar'])<img src="{{ $h['avatar'] }}" alt="" class="w-11 h-11 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $h['name'] }}</p>
                            <div class="flex flex-wrap gap-1 mt-1" id="team-badges-{{ $h['id'] }}">
                                @forelse($h['roles'] as $rn)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent text-primary">{{ $rn }}</span>
                                @empty
                                    <span class="text-xs text-muted-foreground" data-empty="1">{{ __('admin.role_no_role') }}</span>
                                @endforelse
                            </div>
                        </div>
                        <button type="button"
                                @click="$dispatch('open-access-form', { id: {{ $h['id'] }}, name: (window.roleTeam[{{ $h['id'] }}] || {}).name || '' })"
                                class="m-press w-10 h-10 rounded-xl border border-primary/30 text-primary grid place-items-center flex-shrink-0"
                                aria-label="{{ __('admin.role_manage_access') }}">
                            <i class="bi bi-sliders"></i>
                        </button>
                    </div>
                @endforeach
            </div>

            <p id="teamEmpty" class="text-sm text-muted-foreground py-10 text-center {{ count($roleHolders) ? 'hidden' : '' }}">{{ __('admin.role_no_team') }}</p>
            <p id="teamNoMatch" class="hidden text-sm text-muted-foreground py-10 text-center">{{ __('admin.cs_owner_no_results') }}</p>
        </div>
    </section>

    {{-- ══════════════ PANEL: Role types ══════════════ --}}
    <section x-show="panel === 'types'" x-cloak class="px-4 py-4 pb-6 m-panel-in">
        <div id="rolesList" class="space-y-2.5 mobile-stagger">
            @foreach($rolesData as $role)
                @php
                    $dot  = $dotMap[$role['color']] ?? $dotMap['secondary'];
                    $icon = $roleIconMap[$role['color']] ?? $roleIconMap['secondary'];
                @endphp
                <div class="m-card p-3.5 flex items-center gap-3" id="role-{{ $role['id'] }}">
                    <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 text-white"
                          style="background: {{ $dot }};"><i class="bi {{ $icon }} text-lg"></i></span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-semibold text-foreground truncate">{{ $role['label'] }}</span>
                            @if($role['isSystem'])<span class="text-[9px] font-bold tracking-wide text-gray-400 bg-gray-50 rounded px-1.5 py-0.5 flex-shrink-0">{{ __('admin.role_system') }}</span>@endif
                        </div>
                        <p class="text-[11px] text-muted-foreground truncate mt-0.5">
                            {{ $role['userCount'] }} {{ __('admin.role_users_count') }} · {{ count($role['permissions']) }}/{{ $totalPerms }} {{ __('admin.role_permissions') }}
                        </p>
                        @if(!empty($role['description']))
                            <p class="text-[11px] text-muted-foreground/80 truncate">{{ $role['description'] }}</p>
                        @endif
                    </div>

                    @if($canEdit)
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click="$dispatch('open-role-form', window.rolesData[{{ $role['id'] }}])"
                                    class="m-press w-9 h-9 rounded-xl border border-gray-200 text-foreground grid place-items-center" aria-label="{{ __('admin.role_edit') }}">
                                <i class="bi bi-pencil text-sm"></i>
                            </button>
                            @if(!$role['isSystem'])
                                <button type="button" @click="removeRoleId = {{ $role['id'] }}; removeRoleName = (window.rolesData[{{ $role['id'] }}] || {}).label || ''"
                                        class="m-press w-9 h-9 rounded-xl border border-red-200 text-red-600 grid place-items-center" aria-label="{{ __('admin.role_delete') }}">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div id="rolesEmpty" class="{{ count($rolesData) ? 'hidden' : '' }} text-center py-10">
            <i class="bi bi-shield-lock text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.role_no_role') }}</p>
            @if($canEdit)
                <button type="button" @click="$dispatch('open-role-form')" class="m-press mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold"><i class="bi bi-plus-lg"></i>{{ __('admin.role_add') }}</button>
            @endif
        </div>
    </section>

    {{-- ══════════════ Member picker (focused sub-task → bottom sheet) ══════════════ --}}
    <template x-teleport="body">
    <div x-show="pickerOpen" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">
        <div x-show="pickerOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="pickerOpen = false"></div>
        <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <div x-show="pickerOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                 class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col" style="max-height: 92vh;" @click.stop>
                <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>
                <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                    <div class="min-w-0">
                        <h5 class="text-base font-semibold flex items-center"><i class="bi bi-person-plus mr-2"></i>{{ __('admin.role_pick_member') }}</h5>
                        <p class="text-[11px] text-white/80">{{ __('admin.role_pick_member_sub') }}</p>
                    </div>
                    <button type="button" @click="pickerOpen = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center">&times;</button>
                </div>

                <div class="px-4 pt-3 flex-shrink-0">
                    <div class="relative">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input type="text" x-model="pickerSearch" placeholder="{{ __('admin.role_search_member') }}" class="w-full ps-10 pe-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                    @if($pickMembers->isEmpty())
                        <p class="text-sm text-muted-foreground text-center py-8">{{ __('admin.role_no_members_to_pick') }}</p>
                    @else
                        <div class="space-y-1">
                            @foreach($pickMembers as $m)
                                @php $pu = $m->user; @endphp
                                <button type="button"
                                        x-show="!pickerSearch || @js(mb_strtolower($pu->full_name)).includes(pickerSearch.toLowerCase())"
                                        @click="pickerOpen = false; open('team'); $dispatch('open-access-form', { id: {{ $pu->id }}, name: @js($pu->full_name) })"
                                        class="m-press w-full flex items-center gap-3 px-2 py-2.5 rounded-xl hover:bg-muted/60 text-start">
                                    <span class="w-9 h-9 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                        @if($pu->profile_picture)<img src="{{ asset('storage/'.$pu->profile_picture) }}" alt="" class="w-9 h-9 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-foreground truncate">{{ $pu->full_name }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right text-muted-foreground/60 rtl:rotate-180"></i>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ══════════════ Delete confirm (role type) ══════════════ --}}
    <template x-teleport="body">
    <div x-show="removeRoleId !== null" x-cloak class="fixed inset-0 z-[70] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removeRoleId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.role_delete') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeRoleId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.role_delete_confirm') }}</p>
                    <p class="font-semibold" x-text="removeRoleName"></p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removeRoleId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removeRole(removeRoleId)"><i class="bi bi-trash"></i>{{ __('admin.role_delete') }}</button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @if($canEdit)
        @include('admin.club.roles.mobile-role-form')
    @endif
    @include('admin.club.roles.mobile-access-form')

    @php
        $rolesMap = collect($rolesData)->keyBy('id');
        $teamMap  = collect($roleHolders)->keyBy('id');
    @endphp
    <script>
window.rolesData = @json($rolesMap);
window.roleTeam = @json($teamMap);
window.rolesCanEdit = @json($canEdit);
window.ROLE_DOT  = { primary: '#7c6cf0', danger: '#dc3545', info: '#0ea5e9', success: '#10b981', warning: '#f59e0b', secondary: '#64748b' };
window.ROLE_ICON = { primary: 'bi-stars', danger: 'bi-shield-lock', info: 'bi-person-badge', success: 'bi-person-workspace', warning: 'bi-flag', secondary: 'bi-person-gear' };
window.ROLE_TOTAL_PERMS = {{ (int) $totalPerms }};

function escRole(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Hub state: which panel is open, plus the team filter. */
function rolesHub() {
    return {
        panel: null,
        title: '',
        pickerOpen: false,
        pickerSearch: '',
        teamSearch: '',
        teamRole: '',
        removeRoleId: null,
        removeRoleName: '',
        titles: {
            team:  @json(__('admin.role_team_members')),
            types: @json(__('admin.role_manage_types')),
        },

        init() {
            const hash = (window.location.hash || '').replace('#', '');
            if (hash && this.titles[hash]) this.open(hash);
        },

        open(part) {
            this.panel = part;
            this.title = this.titles[part] || '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (part === 'team') this.$nextTick(() => this.applyTeamFilter());
        },

        close() {
            this.panel = null;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        /** Search + role-chip filter over the flat team list. */
        applyTeamFilter() {
            const q = (this.teamSearch || '').trim().toLowerCase();
            const role = this.teamRole;
            const list = document.getElementById('teamList');
            if (!list) return;
            let shown = 0;
            Array.from(list.children).forEach(function (row) {
                const name = row.getAttribute('data-name') || '';
                const roles = (row.getAttribute('data-roles') || '').split('|').filter(Boolean);
                const okName = !q || name.includes(q);
                const okRole = !role || roles.indexOf(role) !== -1;
                const show = okName && okRole;
                row.classList.toggle('hidden', !show);
                if (show) shown++;
            });
            const noMatch = document.getElementById('teamNoMatch');
            const empty = document.getElementById('teamEmpty');
            const total = list.children.length;
            if (noMatch) noMatch.classList.toggle('hidden', shown > 0 || total === 0);
            if (empty) empty.classList.toggle('hidden', total > 0);
        },
    };
}

function roleRowHtml(r) {
    const dot  = window.ROLE_DOT[r.color] || window.ROLE_DOT.secondary;
    const icon = window.ROLE_ICON[r.color] || window.ROLE_ICON.secondary;
    const permCount = Array.isArray(r.permissions) ? r.permissions.length : 0;
    const sysTag = r.isSystem ? '<span class="text-[9px] font-bold tracking-wide text-gray-400 bg-gray-50 rounded px-1.5 py-0.5 flex-shrink-0">{{ __('admin.role_system') }}</span>' : '';
    let actions = '';
    if (window.rolesCanEdit) {
        const del = !r.isSystem
            ? `<button type="button" @click="removeRoleId = ${Number(r.id)}; removeRoleName = (window.rolesData[${Number(r.id)}] || {}).label || ''" class="m-press w-9 h-9 rounded-xl border border-red-200 text-red-600 grid place-items-center"><i class="bi bi-trash text-sm"></i></button>`
            : '';
        actions = `
        <div class="flex items-center gap-1 flex-shrink-0">
            <button type="button" @click="$dispatch('open-role-form', window.rolesData[${Number(r.id)}])" class="m-press w-9 h-9 rounded-xl border border-gray-200 text-foreground grid place-items-center"><i class="bi bi-pencil text-sm"></i></button>
            ${del}
        </div>`;
    }
    return `
    <div class="m-card p-3.5 flex items-center gap-3" id="role-${Number(r.id)}">
        <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: ${dot};"><i class="bi ${icon} text-lg"></i></span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <span class="text-sm font-semibold text-foreground truncate">${escRole(r.label)}</span>
                ${sysTag}
            </div>
            <p class="text-[11px] text-muted-foreground truncate mt-0.5">${Number(r.userCount || 0)} {{ __('admin.role_users_count') }} · ${permCount}/${window.ROLE_TOTAL_PERMS} {{ __('admin.role_permissions') }}</p>
            ${r.description ? `<p class="text-[11px] text-muted-foreground/80 truncate">${escRole(r.description)}</p>` : ''}
        </div>
        ${actions}
    </div>`;
}

function teamRowHtml(h) {
    const roles = Array.isArray(h.roles) ? h.roles : [];
    const badges = roles.length
        ? roles.map(n => `<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent text-primary">${escRole(n)}</span>`).join('')
        : `<span class="text-xs text-muted-foreground" data-empty="1">{{ __('admin.role_no_role') }}</span>`;
    const avatar = h.avatar
        ? `<img src="${escRole(h.avatar)}" alt="" class="w-11 h-11 object-cover">`
        : `<i class="bi bi-person text-muted-foreground"></i>`;
    return `
    <div class="m-card p-3.5 flex items-center gap-3" id="team-${Number(h.id)}"
         data-name="${escRole(String(h.name || '').toLowerCase())}" data-roles="${escRole(roles.join('|'))}">
        <span class="w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">${avatar}</span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground truncate">${escRole(h.name)}</p>
            <div class="flex flex-wrap gap-1 mt-1" id="team-badges-${Number(h.id)}">${badges}</div>
        </div>
        <button type="button" @click="$dispatch('open-access-form', { id: ${Number(h.id)}, name: (window.roleTeam[${Number(h.id)}] || {}).name || '' })"
                class="m-press w-10 h-10 rounded-xl border border-primary/30 text-primary grid place-items-center flex-shrink-0">
            <i class="bi bi-sliders"></i>
        </button>
    </div>`;
}

/** Keep the hero counter + hub row in sync after a live upsert. */
function syncRoleStats() {
    const team = document.getElementById('teamList');
    const roles = document.getElementById('rolesList');
    if (team) {
        const n = team.children.length;
        const a = document.getElementById('statTeam'); if (a) a.textContent = n;
        const b = document.getElementById('statTeamRow'); if (b) b.textContent = n;
    }
    if (roles) {
        const c = document.getElementById('statTypes'); if (c) c.textContent = roles.children.length;
    }
}

// Role type saved → upsert its row.
window.__roleSaved && window.removeEventListener('role-saved', window.__roleSaved);
window.__roleSaved = function (ev) {
    const r = ev.detail && ev.detail.role;
    if (!r || !r.id) return;
    window.rolesData[r.id] = r;
    const existing = document.getElementById('role-' + r.id);
    if (existing) existing.outerHTML = roleRowHtml(r);
    else { document.getElementById('rolesEmpty')?.classList.add('hidden'); document.getElementById('rolesList')?.insertAdjacentHTML('beforeend', roleRowHtml(r)); }
    syncRoleStats();
};
window.addEventListener('role-saved', window.__roleSaved);

// Access assigned/changed → add or update the member's row in the team list.
window.__accessSaved && window.removeEventListener('access-saved', window.__accessSaved);
window.__accessSaved = function (ev) {
    const d = ev.detail || {};
    if (!d.id) return;
    const cur = window.roleTeam[d.id] || { id: d.id, name: d.name || '', avatar: null, roles: [] };
    if (!cur.name && d.name) cur.name = d.name;
    if (d.custom) {
        cur.roles = [d.label || '{{ __('admin.role_custom_access') }}'];
    } else if (d.label) {
        cur.roles = Array.isArray(cur.roles) ? cur.roles.slice() : [];
        if (!cur.roles.includes(d.label)) cur.roles.push(d.label);
    }
    window.roleTeam[d.id] = cur;
    const existing = document.getElementById('team-' + d.id);
    if (existing) existing.outerHTML = teamRowHtml(cur);
    else { document.getElementById('teamEmpty')?.classList.add('hidden'); document.getElementById('teamList')?.insertAdjacentHTML('beforeend', teamRowHtml(cur)); }
    syncRoleStats();
};
window.addEventListener('access-saved', window.__accessSaved);

function removeRole(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/roles/definitions') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(d => {
        if (!d.success) return Promise.reject();
        document.getElementById('role-' + id)?.remove();
        delete window.rolesData[id];
        const list = document.getElementById('rolesList');
        if (list && !list.children.length) document.getElementById('rolesEmpty')?.classList.remove('hidden');
        syncRoleStats();
        window.dispatchEvent(new CustomEvent('role-removed'));
        window.showToast('success', d.message || '{{ __('admin.role_deleted') }}');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}
    </script>
</div>
@endsection
