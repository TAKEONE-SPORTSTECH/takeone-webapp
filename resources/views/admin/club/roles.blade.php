@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Roles & Permissions</h2>
            <p class="text-muted mb-0">Manage club staff roles and access</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="bi bi-plus-lg me-2"></i>Add Staff
        </button>
    </div>

    <!-- Staff with Roles -->
    @if(isset($staffMembers) && count($staffMembers) > 0)
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>Role</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffMembers as $staff)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                @if($staff->user->profile_picture)
                                <img src="{{ asset('storage/' . $staff->user->profile_picture) }}" alt="" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                @else
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="text-white fw-bold">{{ strtoupper(substr($staff->user->full_name ?? 'S', 0, 1)) }}</span>
                                </div>
                                @endif
                                <div>
                                    <p class="fw-semibold mb-0">{{ $staff->user->full_name ?? 'N/A' }}</p>
                                    <p class="text-muted small mb-0">{{ $staff->user->email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($staff->role === 'admin')
                                <span class="badge bg-danger">Admin</span>
                            @elseif($staff->role === 'instructor')
                                <span class="badge bg-info">Instructor</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($staff->role) }}</span>
                            @endif
                        </td>
                        <td>{{ $staff->created_at ? $staff->created_at->format('M d, Y') : 'N/A' }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-check text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No staff roles assigned</h5>
            <p class="text-muted mb-3">Add staff members and assign roles</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="bi bi-plus-lg me-2"></i>Add Staff
            </button>
        </div>
    </div>
    @endif

    <!-- Available Roles Info -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0">
            <h5 class="fw-semibold mb-0">Available Roles</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-danger">Admin</span>
                        </div>
                        <p class="small text-muted mb-0">Full access to all club settings, members, and financials</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-info">Instructor</span>
                        </div>
                        <p class="small text-muted mb-0">Can manage activities, view members, and track attendance</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary">Staff</span>
                        </div>
                        <p class="small text-muted mb-0">Limited access to member check-in and basic operations</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.roles.store', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Search User</label>
                        <input type="text" class="form-control" placeholder="Search by email or name..." id="searchUser">
                        <input type="hidden" name="user_id" id="selectedUserId">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="">Select role</option>
                            <option value="admin">Admin</option>
                            <option value="instructor">Instructor</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Assign Role</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
