@extends('layouts.admin-club')

@section('club-admin-content')
<div class="roles-management">
    <!-- Header -->
    <div class="d-flex align-items-start justify-content-between mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-shield-check"></i>
                Role Management
            </h2>
            <p class="text-muted mb-0">Assign and manage roles for club members</p>
        </div>
    </div>

    <!-- Members & Roles Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <h5 class="fw-semibold mb-1">Club Members & Roles</h5>
                    <p class="text-muted small mb-0">
                        Manage role assignments for {{ isset($members) ? count($members) : 0 }} active member{{ (isset($members) && count($members) !== 1) ? 's' : '' }}
                    </p>
                </div>
                <div class="d-flex gap-2 w-100 w-md-auto" style="max-width: 300px;">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="searchMembers" class="form-control border-start-0 ps-0" placeholder="Search by name or email...">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-3">
            @if(isset($members) && count($members) > 0)
                <div id="membersList" class="d-flex flex-column gap-3">
                    @foreach($members as $member)
                    <div class="member-card card border" data-name="{{ strtolower($member->user->full_name ?? '') }}" data-email="{{ strtolower($member->user->email ?? '') }}">
                        <div class="card-body p-3">
                            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                                <!-- User Info -->
                                <div class="d-flex align-items-center gap-3 flex-grow-1 min-width-0">
                                    @if($member->user->profile_picture)
                                        <img src="{{ asset('storage/' . $member->user->profile_picture) }}"
                                             alt="{{ $member->user->full_name }}"
                                             class="rounded-circle flex-shrink-0"
                                             style="width: 48px; height: 48px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width: 48px; height: 48px;">
                                            <span class="text-white fw-bold fs-5">
                                                {{ strtoupper(substr($member->user->full_name ?? 'U', 0, 1)) }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="min-width-0">
                                        <h6 class="fw-semibold mb-0 text-truncate">{{ $member->user->full_name ?? 'Unknown' }}</h6>
                                        <p class="text-muted small mb-0 text-truncate">{{ $member->user->email ?? '' }}</p>
                                    </div>
                                </div>

                                <!-- Roles & Actions -->
                                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    @php
                                        $userRoles = $member->user->getRolesForTenant($club->id);
                                    @endphp

                                    @if(count($userRoles) === 0)
                                        <span class="badge bg-light text-muted border">No roles</span>
                                    @else
                                        @foreach($userRoles as $role)
                                            <span class="badge role-badge {{ $role->slug === 'club-admin' ? 'bg-danger' : ($role->slug === 'instructor' ? 'bg-info' : 'bg-secondary') }}"
                                                  style="cursor: pointer;"
                                                  data-user-id="{{ $member->user->id }}"
                                                  data-user-name="{{ $member->user->full_name }}"
                                                  data-role="{{ $role->slug }}"
                                                  data-role-label="{{ $role->name }}"
                                                  onclick="openRemoveRoleModal(this)">
                                                {{ $role->name }}
                                                <i class="bi bi-x ms-1"></i>
                                            </span>
                                        @endforeach
                                    @endif

                                    <button class="btn btn-sm btn-outline-primary"
                                            data-user-id="{{ $member->user->id }}"
                                            data-user-name="{{ $member->user->full_name }}"
                                            data-user-roles="{{ json_encode($userRoles->pluck('slug')->toArray()) }}"
                                            onclick="openAssignRoleModal(this)">
                                        <i class="bi bi-plus me-1"></i>
                                        Assign Role
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-0">No members found</p>
                </div>
            @endif

            <!-- No Results Message (hidden by default) -->
            <div id="noResultsMessage" class="text-center py-5 d-none">
                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3 mb-0">No members match your search</p>
            </div>
        </div>
    </div>

    <!-- Available Roles Reference Card -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="fw-semibold mb-0">Available Roles</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @if(isset($availableRoles) && count($availableRoles) > 0)
                    @foreach($availableRoles as $role)
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge {{ $role->slug === 'club-admin' ? 'bg-danger' : ($role->slug === 'instructor' ? 'bg-info' : 'bg-secondary') }}">
                                    {{ $role->name }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0">{{ $role->description ?? 'No description available' }}</p>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-danger">Club Admin</span>
                            </div>
                            <p class="small text-muted mb-0">Full access to all club settings, members, and financials</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-info">Instructor</span>
                            </div>
                            <p class="small text-muted mb-0">Can manage activities, view members, and track attendance</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-secondary">Staff</span>
                            </div>
                            <p class="small text-muted mb-0">Limited access to member check-in and basic operations</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Assign Role Modal -->
<div class="modal fade" id="assignRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-person-gear"></i>
                    Assign Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignRoleForm" action="{{ route('admin.club.roles.store', $club->id) }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="assignUserId">

                <div class="modal-body">
                    <p class="text-muted mb-4">Assign a role to <strong id="assignUserName"></strong></p>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Select Role</label>
                        <select name="role" id="assignRoleSelect" class="form-select form-select-lg" required>
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
                        <p id="roleDescription" class="form-text text-muted mt-2"></p>
                    </div>

                    <div class="bg-light rounded-3 p-3">
                        <p class="small fw-medium mb-2">Current roles:</p>
                        <div id="currentRolesBadges" class="d-flex flex-wrap gap-2">
                            <!-- Will be populated by JS -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignRoleBtn" disabled>Assign Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Role Modal -->
<div class="modal fade" id="removeRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title d-flex align-items-center gap-2 text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Remove Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="removeRoleForm" action="{{ route('admin.club.roles.destroy', $club->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <input type="hidden" name="user_id" id="removeUserId">
                <input type="hidden" name="role" id="removeRoleName">

                <div class="modal-body">
                    <p class="text-muted mb-3">Are you sure you want to remove this role?</p>

                    <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 p-3">
                        <p class="mb-2">
                            You are about to remove the <strong id="removeRoleLabel" class="text-danger"></strong> role
                            from <strong id="removeUserName"></strong>.
                        </p>
                        <p class="text-muted small mb-0">
                            This action will immediately revoke their permissions associated with this role.
                        </p>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.roles-management .member-card {
    transition: all 0.2s ease;
}
.roles-management .member-card:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
.roles-management .role-badge {
    transition: opacity 0.2s;
}
.roles-management .role-badge:hover {
    opacity: 0.8;
}
.roles-management .min-width-0 {
    min-width: 0;
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
                card.classList.remove('d-none');
                visibleCount++;
            } else {
                card.classList.add('d-none');
            }
        });

        if (visibleCount === 0 && query.length > 0) {
            noResultsMessage.classList.remove('d-none');
            if (membersList) membersList.classList.add('d-none');
        } else {
            noResultsMessage.classList.add('d-none');
            if (membersList) membersList.classList.remove('d-none');
        }
    });

    // Role select description
    const roleSelect = document.getElementById('assignRoleSelect');
    const roleDescription = document.getElementById('roleDescription');

    roleSelect?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const description = selectedOption.dataset.description || '';
        roleDescription.textContent = description;

        document.getElementById('assignRoleBtn').disabled = !this.value;
    });
});

// Modal functions
function openAssignRoleModal(button) {
    const userId = button.dataset.userId;
    const userName = button.dataset.userName;
    const userRoles = JSON.parse(button.dataset.userRoles || '[]');

    document.getElementById('assignUserId').value = userId;
    document.getElementById('assignUserName').textContent = userName;

    // Reset select
    const roleSelect = document.getElementById('assignRoleSelect');
    roleSelect.value = '';
    document.getElementById('roleDescription').textContent = '';
    document.getElementById('assignRoleBtn').disabled = true;

    // Disable already assigned roles
    Array.from(roleSelect.options).forEach(option => {
        if (option.value && userRoles.includes(option.value)) {
            option.disabled = true;
        } else {
            option.disabled = false;
        }
    });

    // Show current roles
    const currentRolesBadges = document.getElementById('currentRolesBadges');
    if (userRoles.length === 0) {
        currentRolesBadges.innerHTML = '<span class="badge bg-light text-muted border">No roles assigned</span>';
    } else {
        currentRolesBadges.innerHTML = userRoles.map(role => {
            const badgeClass = role === 'club-admin' ? 'bg-danger' : (role === 'instructor' ? 'bg-info' : 'bg-secondary');
            const label = role.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
            return `<span class="badge ${badgeClass}">${label}</span>`;
        }).join('');
    }

    const modal = new bootstrap.Modal(document.getElementById('assignRoleModal'));
    modal.show();
}

function openRemoveRoleModal(badge) {
    const userId = badge.dataset.userId;
    const userName = badge.dataset.userName;
    const role = badge.dataset.role;
    const roleLabel = badge.dataset.roleLabel;

    document.getElementById('removeUserId').value = userId;
    document.getElementById('removeRoleName').value = role;
    document.getElementById('removeUserName').textContent = userName;
    document.getElementById('removeRoleLabel').textContent = roleLabel;

    const modal = new bootstrap.Modal(document.getElementById('removeRoleModal'));
    modal.show();
}
</script>
@endpush
@endsection
