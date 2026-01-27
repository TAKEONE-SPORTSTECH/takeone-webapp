@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Members</h2>
            <p class="text-muted mb-0">Manage club members and subscriptions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#walkInModal">
                <i class="bi bi-person-walking me-2"></i>Walk-in
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="bi bi-plus-lg me-2"></i>Add Member
            </button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search members..." id="searchMembers">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterPackage">
                        <option value="">All Packages</option>
                        @foreach($packages ?? [] as $package)
                        <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    @if(isset($members) && count($members) > 0)
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Member</th>
                        <th>Package</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                @if($member->user->profile_picture)
                                <img src="{{ asset('storage/' . $member->user->profile_picture) }}" alt="" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                @else
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="text-white fw-bold">{{ strtoupper(substr($member->user->full_name ?? 'M', 0, 1)) }}</span>
                                </div>
                                @endif
                                <div>
                                    <p class="fw-semibold mb-0">{{ $member->user->full_name ?? 'N/A' }}</p>
                                    <p class="text-muted small mb-0">{{ $member->user->email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $member->subscription->package->name ?? 'No package' }}</td>
                        <td>{{ $member->subscription->start_date ? $member->subscription->start_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $member->subscription->end_date ? $member->subscription->end_date->format('M d, Y') : 'N/A' }}</td>
                        <td>
                            @if($member->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($member->status === 'expired')
                                <span class="badge bg-danger">Expired</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ ucfirst($member->status) }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-eye me-2"></i>View</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-repeat me-2"></i>Renew</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-trash me-2"></i>Remove</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($members instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="card-footer bg-white border-0">
            {{ $members->links() }}
        </div>
        @endif
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No members yet</h5>
            <p class="text-muted mb-3">Start adding members to your club</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="bi bi-plus-lg me-2"></i>Add Member
            </button>
        </div>
    </div>
    @endif
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add New Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.members.store', $club->id) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Package</label>
                            <select name="package_id" class="form-select" required>
                                <option value="">Select package</option>
                                @foreach($packages ?? [] as $package)
                                <option value="{{ $package->id }}">{{ $package->name }} - {{ $club->currency ?? 'BHD' }} {{ number_format($package->price, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Add Member</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
