@extends('layouts.admin-club')

@section('club-admin-content')
<div class="roles-management" x-data="{
    showAssignModal: false,
    showRemoveModal: false,
    assignData: { userId: '', userName: '', userRoles: [] },
    removeData: { userId: '', userName: '', role: '', roleLabel: '' }
}">
    <!-- Header -->
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-2xl font-bold mb-1 flex items-center gap-2">
                <i class="bi bi-shield-check"></i>
                Role Management
            </h2>
            <p class="text-muted-foreground mb-0">Assign and manage roles for club members</p>
        </div>
    </div>

    <!-- Members & Roles Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-b border-border py-3">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h5 class="font-semibold mb-1">Club Members & Roles</h5>
                    <p class="text-muted-foreground text-sm mb-0">
                        Manage role assignments for {{ isset($members) ? count($members) : 0 }} active member{{ (isset($members) && count($members) !== 1) ? 's' : '' }}
                    </p>
                </div>
                <div class="flex gap-2 w-full md:w-auto max-w-xs">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-r-0">
                            <i class="bi bi-search text-muted-foreground"></i>
                        </span>
                        <input type="text" id="searchMembers" class="form-control border-l-0 pl-0" placeholder="Search by name or email...">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-3">
            @if(isset($members) && count($members) > 0)
                <div id="membersList" class="flex flex-col gap-3">
                    @foreach($members as $member)
                    <div class="member-card card border border-border" data-name="{{ strtolower($member->user->full_name ?? '') }}" data-email="{{ strtolower($member->user->email ?? '') }}">
                        <div class="card-body p-3">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                                <!-- User Info -->
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    @if($member->user->profile_picture)
                                        <img src="{{ asset('storage/' . $member->user->profile_picture) }}"
                                             alt="{{ $member->user->full_name }}"
                                             class="rounded-full shrink-0 w-12 h-12 object-cover">
                                    @else
                                        <div class="rounded-full bg-primary flex items-center justify-center shrink-0 w-12 h-12">
                                            <span class="text-white font-bold text-xl">
                                                {{ strtoupper(substr($member->user->full_name ?? 'U', 0, 1)) }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <h6 class="font-semibold mb-0 truncate">{{ $member->user->full_name ?? 'Unknown' }}</h6>
                                        <p class="text-muted-foreground text-sm mb-0 truncate">{{ $member->user->email ?? '' }}</p>
                                    </div>
                                </div>

                                <!-- Roles & Actions -->
                                <div class="flex items-center gap-2 flex-wrap justify-end">
                                    @php
                                        $userRoles = $member->user->getRolesForTenant($club->id);
                                    @endphp

                                    @if(count($userRoles) === 0)
                                        <span class="badge bg-muted/30 text-muted-foreground border border-border">No roles</span>
                                    @else
                                        @foreach($userRoles as $role)
                                            <span class="badge role-badge cursor-pointer {{ $role->slug === 'club-admin' ? 'bg-destructive' : ($role->slug === 'instructor' ? 'bg-info' : 'bg-secondary') }}"
                                                  @click="removeData = {
                                                      userId: '{{ $member->user->id }}',
                                                      userName: '{{ $member->user->full_name }}',
                                                      role: '{{ $role->slug }}',
                                                      roleLabel: '{{ $role->name }}'
                                                  }; showRemoveModal = true">
                                                {{ $role->name }}
                                                <i class="bi bi-x ml-1"></i>
                                            </span>
                                        @endforeach
                                    @endif

                                    <button class="btn btn-sm btn-outline-primary"
                                            @click="assignData = {
                                                userId: '{{ $member->user->id }}',
                                                userName: '{{ $member->user->full_name }}',
                                                userRoles: {{ json_encode($userRoles->pluck('slug')->toArray()) }}
                                            }; showAssignModal = true">
                                        <i class="bi bi-plus mr-1"></i>
                                        Assign Role
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <i class="bi bi-people text-muted-foreground text-5xl"></i>
                    <p class="text-muted-foreground mt-3 mb-0">No members found</p>
                </div>
            @endif

            <!-- No Results Message (hidden by default) -->
            <div id="noResultsMessage" class="text-center py-12 hidden">
                <i class="bi bi-search text-muted-foreground text-5xl"></i>
                <p class="text-muted-foreground mt-3 mb-0">No members match your search</p>
            </div>
        </div>
    </div>

    <!-- Available Roles Reference Card -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-b border-border py-3">
            <h5 class="font-semibold mb-0">Available Roles</h5>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @if(isset($availableRoles) && count($availableRoles) > 0)
                    @foreach($availableRoles as $role)
                    <div class="border border-border rounded-lg p-3 h-full">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge {{ $role->slug === 'club-admin' ? 'bg-destructive' : ($role->slug === 'instructor' ? 'bg-info' : 'bg-secondary') }}">
                                {{ $role->name }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground mb-0">{{ $role->description ?? 'No description available' }}</p>
                    </div>
                    @endforeach
                @else
                    <div class="border border-border rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge bg-destructive">Club Admin</span>
                        </div>
                        <p class="text-sm text-muted-foreground mb-0">Full access to all club settings, members, and financials</p>
                    </div>
                    <div class="border border-border rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge bg-info">Instructor</span>
                        </div>
                        <p class="text-sm text-muted-foreground mb-0">Can manage activities, view members, and track attendance</p>
                    </div>
                    <div class="border border-border rounded-lg p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="badge bg-secondary">Staff</span>
                        </div>
                        <p class="text-sm text-muted-foreground mb-0">Limited access to member check-in and basic operations</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Assign Role Modal -->
    <div x-show="showAssignModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showAssignModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title flex items-center gap-2 font-bold">
                        <i class="bi bi-person-gear"></i>
                        Assign Role
                    </h5>
                    <button type="button" class="btn-close" @click="showAssignModal = false"></button>
                </div>
                <form id="assignRoleForm" action="{{ route('admin.club.roles.store', $club->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="user_id" :value="assignData.userId">

                    <div class="modal-body px-6 py-4">
                        <p class="text-muted-foreground mb-4">Assign a role to <strong x-text="assignData.userName"></strong></p>

                        <div class="mb-4">
                            <label class="form-label font-medium">Select Role</label>
                            <select name="role" id="assignRoleSelect" class="form-select" required>
                                <option value="">Choose a role...</option>
                                @if(isset($availableRoles))
                                    @foreach($availableRoles as $role)
                                        <option value="{{ $role->slug }}" data-description="{{ $role->description ?? '' }}">
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                @else
                                    <option value="club-admin" data-description="Full access to all club settings">Club Admin</option>
                                    <option value="instructor" data-description="Can manage activities and track attendance">Instructor</option>
                                    <option value="staff" data-description="Limited access to basic operations">Staff</option>
                                @endif
                            </select>
                            <p id="roleDescription" class="text-muted-foreground text-sm mt-2"></p>
                        </div>

                        <div class="bg-muted/30 rounded-lg p-3">
                            <p class="text-sm font-medium mb-2">Current roles:</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-if="assignData.userRoles && assignData.userRoles.length === 0">
                                    <span class="badge bg-muted/30 text-muted-foreground border border-border">No roles assigned</span>
                                </template>
                                <template x-for="role in assignData.userRoles" :key="role">
                                    <span class="badge"
                                          :class="{
                                              'bg-destructive': role === 'club-admin',
                                              'bg-info': role === 'instructor',
                                              'bg-secondary': role !== 'club-admin' && role !== 'instructor'
                                          }"
                                          x-text="role.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-t border-border px-6 py-4">
                        <button type="button" class="btn btn-outline-secondary" @click="showAssignModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="assignRoleBtn">Assign Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Role Modal -->
    <div x-show="showRemoveModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showRemoveModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title flex items-center gap-2 text-destructive font-bold">
                        <i class="bi bi-exclamation-triangle"></i>
                        Remove Role
                    </h5>
                    <button type="button" class="btn-close" @click="showRemoveModal = false"></button>
                </div>
                <form id="removeRoleForm" action="{{ route('admin.club.roles.destroy', $club->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="user_id" :value="removeData.userId">
                    <input type="hidden" name="role" :value="removeData.role">

                    <div class="modal-body px-6 py-4">
                        <p class="text-muted-foreground mb-3">Are you sure you want to remove this role?</p>

                        <div class="bg-destructive/10 border border-destructive/25 rounded-lg p-3">
                            <p class="mb-2">
                                You are about to remove the <strong class="text-destructive" x-text="removeData.roleLabel"></strong> role
                                from <strong x-text="removeData.userName"></strong>.
                            </p>
                            <p class="text-muted-foreground text-sm mb-0">
                                This action will immediately revoke their permissions associated with this role.
                            </p>
                        </div>
                    </div>

                    <div class="modal-footer border-t border-border px-6 py-4">
                        <button type="button" class="btn btn-outline-secondary" @click="showRemoveModal = false">Cancel</button>
                        <button type="submit" class="btn btn-danger">Remove Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.roles-management .member-card {
    transition: all 0.2s ease;
}
.roles-management .member-card:hover {
    border-color: hsl(var(--primary)) !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
.roles-management .role-badge {
    transition: opacity 0.2s;
}
.roles-management .role-badge:hover {
    opacity: 0.8;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchMembers');
    const memberCards = document.querySelectorAll('.member-card');
    const noResultsMessage = document.getElementById('noResultsMessage');
    const membersList = document.getElementById('membersList');

    searchInput?.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        memberCards.forEach(card => {
            const name = card.dataset.name || '';
            const email = card.dataset.email || '';

            if (name.includes(query) || email.includes(query)) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        if (visibleCount === 0 && query.length > 0) {
            noResultsMessage.classList.remove('hidden');
            if (membersList) membersList.classList.add('hidden');
        } else {
            noResultsMessage.classList.add('hidden');
            if (membersList) membersList.classList.remove('hidden');
        }
    });

    // Role select description
    const roleSelect = document.getElementById('assignRoleSelect');
    const roleDescription = document.getElementById('roleDescription');

    roleSelect?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const description = selectedOption.dataset.description || '';
        roleDescription.textContent = description;
    });
});
</script>
@endpush
@endsection
