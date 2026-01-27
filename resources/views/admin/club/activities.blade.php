@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Activities</h2>
            <p class="text-muted mb-0">Manage club activities and classes</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-lg me-2"></i>Add Activity
        </button>
    </div>

    @if(isset($activities) && count($activities) > 0)
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Activity</th>
                        <th>Duration</th>
                        <th>Instructor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activities as $activity)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded bg-primary bg-opacity-10 p-2">
                                    <i class="bi bi-activity text-primary"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold mb-0">{{ $activity->name }}</p>
                                    <p class="text-muted small mb-0">{{ Str::limit($activity->description, 50) }}</p>
                                </div>
                            </div>
                        </td>
                        <td>{{ $activity->duration ?? 'N/A' }} min</td>
                        <td>{{ $activity->instructor->name ?? 'Not assigned' }}</td>
                        <td>
                            @if($activity->is_active ?? true)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
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
            <i class="bi bi-activity text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No activities yet</h5>
            <p class="text-muted mb-3">Add activities and classes for your members</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="bi bi-plus-lg me-2"></i>Add Activity
            </button>
        </div>
    </div>
    @endif
</div>

<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.activities.store', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Activity Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" value="60">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Instructor</label>
                            <select name="instructor_id" class="form-select">
                                <option value="">Select instructor</option>
                                @foreach($instructors ?? [] as $instructor)
                                <option value="{{ $instructor->id }}">{{ $instructor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Add Activity</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
